<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadFollowUpEngine.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Add lead (manual entry)
|--------------------------------------------------------------------------
| POST (JSON) + CSRF (gateway) + canAdd on the caller's lead page.
| Required: fullName, phone (+country/countryCode), sourceId.
| Optional: email, projectId (an enquiry may arrive before the project is
| known), status (default New), assignedToId (assigners only; other
| employees always get the lead themselves), nextFollowUp, remark.
*/
requireLeadPost();
requireLeadPermission('canAdd');

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$fullName = trim((string)($payload['fullName'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$phone = preg_replace('/\D/', '', (string)($payload['phone'] ?? '')) ?? '';
$country = trim((string)($payload['country'] ?? ''));
$countryCode = trim((string)($payload['countryCode'] ?? ''));
$status = trim((string)($payload['status'] ?? 'new')) ?: 'new';
$remark = trim((string)($payload['remark'] ?? ''));

if ($fullName === '' || mb_strlen($fullName) > 100) {
    leadJsonExit(422, 'Customer name is required (max 100 characters).');
}

if (strlen($phone) < 6 || strlen($phone) > 15) {
    leadJsonExit(422, 'Enter a valid contact number (6-15 digits).');
}

if ($country === '' || !preg_match('/^\+\d{1,4}$/', $countryCode)) {
    leadJsonExit(422, 'Select a valid country.');
}

if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) {
    leadJsonExit(422, 'Enter a valid email address.');
}

if (!isset(LEAD_STATUSES[$status])) {
    leadJsonExit(422, 'Invalid lead status.');
}

if (in_array($status, LEAD_CLOSING_STATUSES, true) && $remark === '') {
    leadJsonExit(422, 'A remark / reason is required for ' . LEAD_STATUSES[$status] . ' leads.');
}

$source = resolveLeadSource($con, (int)($payload['sourceId'] ?? 0));
$project = resolveLeadProject($con, (int)($payload['projectId'] ?? 0));
$assignee = resolveNewLeadAssignee($con, (int)($payload['assignedToId'] ?? 0));
$followUp = parseFollowUpDateTime((string)($payload['nextFollowUp'] ?? ''));

// Duplicate protection: same contact number already on a lead.
$dupStmt = mysqli_prepare($con, 'SELECT id FROM leads WHERE phone = ? AND countryCode = ? LIMIT 1');
mysqli_stmt_bind_param($dupStmt, 'ss', $phone, $countryCode);
mysqli_stmt_execute($dupStmt);
$duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));
mysqli_stmt_close($dupStmt);

if ($duplicate) {
    leadJsonExit(409, 'A lead with this contact number already exists.');
}

$actor = getCurrentActor();

try {
    $leadId = createLeadCore($con, [
        'fullName' => $fullName, 'email' => $email, 'phone' => $phone, 'country' => $country, 'countryCode' => $countryCode,
        'projectId' => $project['id'] ?? null, 'projectName' => $project['projectName'] ?? null,
        'sourceId' => $source['id'], 'sourceName' => $source['sourceName'],
        'assignedToId' => $assignee['id'] ?? null, 'assigneeName' => $assignee['fullName'] ?? null,
        'status' => $status, 'remark' => $remark, 'followUp' => $followUp,
        'externalSource' => null, 'externalLeadId' => null,
    ], $actor);
} catch (Throwable $e) {
    error_log('addLead failed: ' . $e->getMessage());
    leadJsonExit(500, 'Failed to add lead.');
}

echo json_encode([
    'success' => true,
    'message' => 'Lead added successfully.',
    'data' => ['id' => $leadId, 'status' => $status],
]);
