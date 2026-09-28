<?php
/*
|--------------------------------------------------------------------------
| Google Lead Form Extensions -> CRM lead
|--------------------------------------------------------------------------
| PUBLIC endpoint (exempted from session auth in api-gateway.php). Google's
| Lead Form Extensions webhook posts a shared secret ("google_key",
| configured on both sides) with the lead payload -- this is the provider's
| own supported verification mechanism, not a CRM session/CSRF token, and
| the simplest flow Google supports (no OAuth needed for this product).
| See https://support.google.com/google-ads/answer/9552636 for the payload
| shape this expects (field names below match Google's documented schema).
|
| Because we have no live Google Ads account to test against, this endpoint
| is implemented to that documented contract and exercised here with
| simulated payloads only -- see CLAUDE.md Phase 6 for what that does and
| does not prove.
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

const PROVIDER = 'google';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    integrationJsonExit(405, 'Method not allowed.');
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
    integrationJsonExit(413, 'Request too large.');
}

$setting = getIntegrationSetting($con, PROVIDER);

if (!$setting || !$setting['isEnabled']) {
    integrationJsonExit(503, 'Google Lead Forms integration is not enabled.');
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string)$rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$secrets = getIntegrationSecrets($con, PROVIDER);

if (!verifyGoogleKey((string)($secrets['sharedKey'] ?? ''), isset($payload['google_key']) ? (string)$payload['google_key'] : null)) {
    logIntegrationEvent($con, PROVIDER, null, 'lead_form', null, 'error', 'Invalid or missing shared key');
    integrationJsonExit(401, 'Invalid shared key.');
}

$externalLeadId = isset($payload['lead_id']) ? mb_substr((string)$payload['lead_id'], 0, 191) : null;
$formId = (string)($payload['form_id'] ?? '');
$userColumnData = $payload['user_column_data'] ?? [];

// Google sends {column_id/string_value} pairs, not flat field names.
$flat = [];
if (is_array($userColumnData)) {
    foreach ($userColumnData as $column) {
        $key = strtolower(str_replace(' ', '_', (string)($column['column_id'] ?? '')));
        if ($key !== '') {
            $flat[$key] = (string)($column['string_value'] ?? '');
        }
    }
}

$normalized = normalizeExternalLeadFields($flat + (is_array($payload) ? $payload : []));

if ($normalized['phone'] === '' || strlen($normalized['phone']) < 6) {
    logIntegrationEvent($con, PROVIDER, $externalLeadId, 'lead_form', null, 'rejected', 'Missing required phone', ['form_id' => $formId]);
    integrationJsonExit(422, 'Missing required phone field.');
}

$mapping = $formId !== '' ? getFormMapping($con, PROVIDER, $formId) : null;
$config = getIntegrationConfig($con, PROVIDER);
$defaultSourceId = (int)($config['defaultSourceId'] ?? 0);
$source = $defaultSourceId > 0 ? resolveLeadSource($con, $defaultSourceId) : null;

if (!$source) {
    $stmt = mysqli_prepare($con, "SELECT id, sourceKey, sourceName FROM leadSources WHERE sourceKey = 'google_ads' LIMIT 1");
    mysqli_stmt_execute($stmt);
    $source = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$projectId = null;
$projectName = null;

// A mapped project may have been deactivated since the mapping was saved;
// fall back to unmapped rather than reject the lead (Section 9: never
// silently discard a valid lead because of a mapping problem).
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
    $result = createLeadFromSource($con, PROVIDER, $externalLeadId, [
        'fullName' => $normalized['fullName'], 'phone' => $normalized['phone'], 'country' => 'India', 'countryCode' => '+91',
        'email' => $normalized['email'],
        'sourceId' => (int)$source['id'], 'sourceName' => $source['sourceName'],
        'projectId' => $projectId, 'projectName' => $projectName,
        'assignedToId' => $assignee['id'] ?? null, 'assigneeName' => $assignee['fullName'] ?? null,
        'remark' => $normalized['remark'], 'createLabel' => 'Lead created from Google Lead Form',
    ]);
} catch (Throwable $e) {
    error_log('google-lead failed: ' . $e->getMessage());
    logIntegrationEvent($con, PROVIDER, $externalLeadId, 'lead_form', null, 'error', 'Lead creation failed', ['form_id' => $formId]);
    integrationJsonExit(500, 'Unable to process lead.');
}

logIntegrationEvent(
    $con, PROVIDER, $externalLeadId, 'lead_form', $result['leadId'],
    $result['duplicate'] ? 'duplicate' : 'created',
    $result['duplicate'] ? $result['reason'] : 'Lead created',
    ['form_id' => $formId, 'mapped' => (bool)$mapping]
);

echo json_encode(['success' => true, 'message' => 'ok', 'data' => []]);
