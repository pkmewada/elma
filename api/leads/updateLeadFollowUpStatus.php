<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadFollowUpEngine.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// POST {id, status: Completed|Skipped, remark?} + CSRF (gateway) + edit
// permission + the follow-up's lead in scope.
requireLeadPost();
requireApiPermission([LEAD_ADMIN_ROUTE, LEAD_EMPLOYEE_ROUTE, '/lead-follow-up-list', '/emp-follow-ups'], 'canEdit');

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = (int)($payload['id'] ?? 0);
$status = trim((string)($payload['status'] ?? ''));
$remark = trim((string)($payload['remark'] ?? ''));

if ($id <= 0) {
    leadJsonExit(422, 'Invalid follow up.');
}

$stmt = mysqli_prepare($con, 'SELECT leadId FROM leadFollowUps WHERE id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$followUp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if (!$followUp) {
    leadJsonExit(404, 'Follow up not found.');
}

requireLeadAccess($con, (int)$followUp['leadId']);

try {
    (new LeadFollowUpEngine($con))->updateStatus($id, $status, $remark);
    echo json_encode(['success' => true, 'message' => "Follow up marked {$status}."]);
} catch (Exception $e) {
    leadJsonExit(422, $e->getMessage());
}
