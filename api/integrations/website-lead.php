<?php
/*
|--------------------------------------------------------------------------
| Website enquiry form -> CRM lead
|--------------------------------------------------------------------------
| PUBLIC endpoint (exempted from session auth in api-gateway.php). Auth is
| a configured API key in the X-Integration-Key header, set on the
| Integrations page and never returned to the browser once saved.
|
| POST fields: name, phone (required); email, project (free text, matched
| against active projects by exact name), message, requestId (optional
| client-generated id, e.g. a UUID stored in the form's hidden field and
| resent unchanged on a retry -- without it, the existing phone dedupe in
| createLeadFromSource() is the only safety net for a genuine double-click).
*/
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';
require_once __DIR__ . '/../../includes/integrationAccess.php';

const PROVIDER = 'website';

if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
    integrationJsonExit(405, 'Method not allowed.');
}

// 1 MB is generous for a small enquiry form; reject anything larger up front.
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1024 * 1024) {
    integrationJsonExit(413, 'Request too large.');
}

$setting = getIntegrationSetting($con, PROVIDER);

if (!$setting || !$setting['isEnabled']) {
    integrationJsonExit(503, 'Website lead capture is not enabled.');
}

$secrets = getIntegrationSecrets($con, PROVIDER);
$submittedKey = $_SERVER['HTTP_X_INTEGRATION_KEY'] ?? null;

if (!verifyWebsiteApiKey((string)($secrets['apiKey'] ?? ''), $submittedKey)) {
    logIntegrationEvent($con, PROVIDER, null, 'form_submit', null, 'error', 'Invalid or missing API key');
    integrationJsonExit(401, 'Invalid API key.');
}

$requestId = trim((string)($_POST['requestId'] ?? ''));
$externalLeadId = $requestId !== '' ? mb_substr($requestId, 0, 191) : null;

$name = mb_substr(trim((string)($_POST['name'] ?? '')), 0, 100);
$phone = preg_replace('/\D/', '', (string)($_POST['phone'] ?? '')) ?? '';
$email = mb_substr(trim((string)($_POST['email'] ?? '')), 0, 150);
$projectText = trim((string)($_POST['project'] ?? ''));
$message = mb_substr(trim((string)($_POST['message'] ?? '')), 0, 2000);

if ($name === '' || strlen($phone) < 6 || strlen($phone) > 15) {
    logIntegrationEvent($con, PROVIDER, $externalLeadId, 'form_submit', null, 'rejected', 'Missing required name/phone');
    integrationJsonExit(422, 'Name and a valid phone number are required.');
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    logIntegrationEvent($con, PROVIDER, $externalLeadId, 'form_submit', null, 'rejected', 'Invalid email');
    integrationJsonExit(422, 'Invalid email address.');
}

$config = getIntegrationConfig($con, PROVIDER);
$defaultSourceId = (int)($config['defaultSourceId'] ?? 0);
$source = $defaultSourceId > 0 ? resolveLeadSource($con, $defaultSourceId) : null;

if (!$source) {
    // Website is a required, always-present system source (Phase 3 seed).
    $stmt = mysqli_prepare($con, "SELECT id, sourceKey, sourceName FROM leadSources WHERE sourceKey = 'website' LIMIT 1");
    mysqli_stmt_execute($stmt);
    $source = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
}

$projectId = null;
$projectName = null;

if ($projectText !== '') {
    $stmt = mysqli_prepare($con, 'SELECT id, projectName FROM projects WHERE isActive = 1 AND projectName = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 's', $projectText);
    mysqli_stmt_execute($stmt);
    $matchedProject = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    // No silent failure: an unmatched project name is kept as free text in
    // the remark instead of being discarded, and the lead is still created.
    if ($matchedProject) {
        $projectId = (int)$matchedProject['id'];
        $projectName = $matchedProject['projectName'];
    } else {
        $message = trim("Interested project (as submitted): {$projectText}\n{$message}");
    }
}

$defaultAssigneeId = (int)($config['defaultAssigneeId'] ?? 0);
$assignee = $defaultAssigneeId > 0 ? resolveAssignee($con, $defaultAssigneeId) : null;

try {
    $result = createLeadFromSource($con, PROVIDER, $externalLeadId, [
        'fullName' => $name, 'phone' => $phone, 'country' => 'India', 'countryCode' => '+91', 'email' => $email,
        'sourceId' => (int)$source['id'], 'sourceName' => $source['sourceName'],
        'projectId' => $projectId, 'projectName' => $projectName,
        'assignedToId' => $assignee['id'] ?? null, 'assigneeName' => $assignee['fullName'] ?? null,
        'remark' => $message, 'createLabel' => 'Lead created from Website Enquiry',
    ]);
} catch (Throwable $e) {
    error_log('website-lead failed: ' . $e->getMessage());
    logIntegrationEvent($con, PROVIDER, $externalLeadId, 'form_submit', null, 'error', 'Lead creation failed');
    integrationJsonExit(500, 'Unable to process enquiry.');
}

logIntegrationEvent(
    $con, PROVIDER, $externalLeadId, 'form_submit', $result['leadId'],
    $result['duplicate'] ? 'duplicate' : 'created',
    $result['duplicate'] ? $result['reason'] : 'Lead created',
    ['name' => $name, 'project' => $projectText]
);

echo json_encode(['success' => true, 'message' => 'Thank you, we will get back to you shortly.', 'data' => []]);
