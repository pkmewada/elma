<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Edit lead details
|--------------------------------------------------------------------------
| POST (JSON) + CSRF (gateway) + canEdit + lead in scope. Status and
| assignment have their own endpoints (updateLeadStatus / assignLead) so
| each change is validated and logged on its own.
*/
requireLeadPost();
requireLeadPermission('canEdit');

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = (int)($payload['id'] ?? 0);
requireLeadAccess($con, $id);

$stmt = mysqli_prepare(
    $con,
    'SELECT l.fullName, l.email, l.phone, l.country, l.countryCode, l.projectId, l.sourceId, p.projectName, s.sourceName
     FROM leads l LEFT JOIN projects p ON p.id = l.projectId LEFT JOIN leadSources s ON s.id = l.sourceId
     WHERE l.id = ?'
);
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$old = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

$fullName = trim((string)($payload['fullName'] ?? ''));
$email = trim((string)($payload['email'] ?? ''));
$phone = preg_replace('/\D/', '', (string)($payload['phone'] ?? '')) ?? '';
$country = trim((string)($payload['country'] ?? ''));
$countryCode = trim((string)($payload['countryCode'] ?? ''));

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

$source = resolveLeadSource($con, (int)($payload['sourceId'] ?? 0));
$project = resolveLeadProject($con, (int)($payload['projectId'] ?? 0), true, (int)($old['projectId'] ?? 0));

$dupStmt = mysqli_prepare($con, 'SELECT id FROM leads WHERE phone = ? AND countryCode = ? AND id <> ? LIMIT 1');
mysqli_stmt_bind_param($dupStmt, 'ssi', $phone, $countryCode, $id);
mysqli_stmt_execute($dupStmt);
$duplicate = mysqli_fetch_assoc(mysqli_stmt_get_result($dupStmt));
mysqli_stmt_close($dupStmt);

if ($duplicate) {
    leadJsonExit(409, 'Another lead already uses this contact number.');
}

$emailValue = $email !== '' ? $email : null;
$projectId = $project['id'] ?? null;

$stmt = mysqli_prepare(
    $con,
    'UPDATE leads SET fullName = ?, email = ?, phone = ?, country = ?, countryCode = ?, projectId = ?, sourceId = ? WHERE id = ?'
);
mysqli_stmt_bind_param($stmt, 'sssssiii', $fullName, $emailValue, $phone, $country, $countryCode, $projectId, $source['id'], $id);

if (!mysqli_stmt_execute($stmt)) {
    error_log('updateLead failed: ' . mysqli_stmt_error($stmt));
    leadJsonExit(500, 'Failed to update lead.');
}
mysqli_stmt_close($stmt);

$new = [
    'fullName' => $fullName, 'email' => $emailValue, 'phone' => $phone, 'country' => $country,
    'countryCode' => $countryCode, 'project' => $project['projectName'] ?? null, 'source' => $source['sourceName'],
];
$before = [
    'fullName' => $old['fullName'], 'email' => $old['email'], 'phone' => $old['phone'], 'country' => $old['country'],
    'countryCode' => $old['countryCode'], 'project' => $old['projectName'], 'source' => $old['sourceName'],
];

if ($before !== $new) {
    saveActivityLog($con, 'Lead', $id, 'UPDATE', 'Lead details updated : ' . $fullName, $before, $new);
}

if ($before['project'] !== $new['project']) {
    saveActivityLog(
        $con,
        'Lead',
        $id,
        'PROJECT',
        sprintf('Project changed from "%s" to "%s"', $before['project'] ?? 'None', $new['project'] ?? 'None'),
        ['project' => $before['project']],
        ['project' => $new['project']]
    );
}

echo json_encode([
    'success' => true,
    'message' => 'Lead updated successfully.',
    'data' => ['id' => $id],
]);
