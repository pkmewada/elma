<?php
/*
|--------------------------------------------------------------------------
| Meta Lead Ads webhook -> CRM lead
|--------------------------------------------------------------------------
| PUBLIC endpoint (exempted from session auth in api-gateway.php).
|
| GET  = Meta's one-time subscribe handshake (hub.mode/hub.verify_token/
|        hub.challenge) -- must echo back hub.challenge verbatim.
| POST = the actual event. The webhook payload only ever contains
|        identifiers (leadgen_id, form_id, page_id), never customer field
|        data -- Meta requires a follow-up Graph API call
|        (GET /{leadgen_id}?access_token=...) to fetch field_data. This is
|        implemented to that documented contract but has not been
|        exercised against a real Page Access Token -- see CLAUDE.md
|        Phase 6 for what "implemented" vs "live-verified" means here.
| Every POST must carry a valid X-Hub-Signature-256 (HMAC-SHA256 of the raw
| body with the configured App Secret) -- Meta's own webhook security
| mechanism, not a CRM session/CSRF token.
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

const PROVIDER = 'meta';

$secrets = getIntegrationSecrets($con, PROVIDER);
$setting = getIntegrationSetting($con, PROVIDER);

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET') {
    // Subscribe handshake can happen before the integration is marked
    // enabled (Meta requires it to succeed before the toggle can be saved),
    // so it only needs a configured verify token, not isEnabled.
    $mode = (string)($_GET['hub_mode'] ?? '');
    $token = (string)($_GET['hub_verify_token'] ?? '');
    $challenge = (string)($_GET['hub_challenge'] ?? '');

    if (verifyMetaChallenge((string)($secrets['verifyToken'] ?? ''), $mode, $token)) {
        header('Content-Type: text/plain');
        echo $challenge;
        exit;
    }

    logIntegrationEvent($con, PROVIDER, null, 'webhook_verify', null, 'error', 'Verification token mismatch');
    http_response_code(403);
    exit('Verification failed.');
}

if (!$setting || !$setting['isEnabled']) {
    integrationJsonExit(503, 'Meta Lead Ads integration is not enabled.');
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
    integrationJsonExit(413, 'Request too large.');
}

$rawBody = file_get_contents('php://input');

if (!verifyMetaSignature($rawBody, (string)($secrets['appSecret'] ?? ''), $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null)) {
    logIntegrationEvent($con, PROVIDER, null, 'lead_event', null, 'error', 'Invalid or missing signature');
    integrationJsonExit(401, 'Invalid signature.');
}

$payload = json_decode($rawBody, true);
$entries = is_array($payload['entry'] ?? null) ? $payload['entry'] : [];
$accessToken = (string)($secrets['pageAccessToken'] ?? '');
$processed = 0;

foreach ($entries as $entry) {
    $changes = is_array($entry['changes'] ?? null) ? $entry['changes'] : [];

    foreach ($changes as $change) {
        $value = $change['value'] ?? [];
        $leadgenId = (string)($value['leadgen_id'] ?? '');
        $formId = (string)($value['form_id'] ?? '');
        $pageId = (string)($value['page_id'] ?? $entry['id'] ?? '');

        if ($leadgenId === '') {
            logIntegrationEvent($con, PROVIDER, null, 'lead_event', null, 'rejected', 'Event missing leadgen_id');
            continue;
        }

        if ($accessToken === '') {
            logIntegrationEvent($con, PROVIDER, $leadgenId, 'lead_event', null, 'error', 'No Page Access Token configured', ['form_id' => $formId]);
            continue;
        }

        // Meta's webhook never carries customer fields, only the id to fetch them with.
        $leadDetails = metaGraphApiGet($leadgenId, $accessToken);

        if ($leadDetails === null) {
            logIntegrationEvent($con, PROVIDER, $leadgenId, 'lead_event', null, 'error', 'Graph API fetch failed', ['form_id' => $formId]);
            continue;
        }

        $flat = flattenMetaFieldData(is_array($leadDetails['field_data'] ?? null) ? $leadDetails['field_data'] : []);
        $normalized = normalizeExternalLeadFields($flat);

        if ($normalized['phone'] === '' || strlen($normalized['phone']) < 6) {
            logIntegrationEvent($con, PROVIDER, $leadgenId, 'lead_event', null, 'rejected', 'Missing required phone', ['form_id' => $formId]);
            continue;
        }

        $mapping = $formId !== '' ? getFormMapping($con, PROVIDER, $formId) : null;
        $config = getIntegrationConfig($con, PROVIDER);
        $defaultSourceId = (int)($config['defaultSourceId'] ?? 0);
        $source = $defaultSourceId > 0 ? resolveLeadSource($con, $defaultSourceId) : null;

        if (!$source) {
            $stmt = mysqli_prepare($con, "SELECT id, sourceKey, sourceName FROM leadSources WHERE sourceKey = 'meta_lead_ads' LIMIT 1");
            mysqli_stmt_execute($stmt);
            $source = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
        }

        $projectId = null;
        $projectName = null;

        if ($mapping && $mapping['projectId']) {
            $projectRow = mysqli_fetch_assoc(mysqli_query($con, 'SELECT id, projectName FROM projects WHERE id = ' . (int)$mapping['projectId'] . ' AND isActive = 1'));
            if ($projectRow) {
                $projectId = (int)$projectRow['id'];
                $projectName = $projectRow['projectName'];
            }
        }

        $assignee = null;
        if ($mapping && $mapping['defaultAssigneeId']) {
            $assignee = resolveAssignee($con, (int)$mapping['defaultAssigneeId']);
        } elseif ((int)($config['defaultAssigneeId'] ?? 0) > 0) {
            $assignee = resolveAssignee($con, (int)$config['defaultAssigneeId']);
        }

        try {
            $result = createLeadFromSource($con, PROVIDER, $leadgenId, [
                'fullName' => $normalized['fullName'], 'phone' => $normalized['phone'], 'country' => 'India', 'countryCode' => '+91',
                'email' => $normalized['email'],
                'sourceId' => (int)$source['id'], 'sourceName' => $source['sourceName'],
                'projectId' => $projectId, 'projectName' => $projectName,
                'assignedToId' => $assignee['id'] ?? null, 'assigneeName' => $assignee['fullName'] ?? null,
                'remark' => $normalized['remark'], 'createLabel' => 'Lead created from Meta Lead Ads',
            ]);

            logIntegrationEvent(
                $con, PROVIDER, $leadgenId, 'lead_event', $result['leadId'],
                $result['duplicate'] ? 'duplicate' : 'created',
                $result['duplicate'] ? $result['reason'] : 'Lead created',
                ['form_id' => $formId, 'page_id' => $pageId, 'mapped' => (bool)$mapping]
            );
            $processed++;
        } catch (Throwable $e) {
            error_log('meta-webhook failed: ' . $e->getMessage());
            logIntegrationEvent($con, PROVIDER, $leadgenId, 'lead_event', null, 'error', 'Lead creation failed', ['form_id' => $formId]);
        }
    }
}

// Meta only requires a 200 response; it does not read the body.
echo json_encode(['success' => true, 'processed' => $processed]);
