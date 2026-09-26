<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leave-balance.php';

header('Content-Type: application/json');

function respond($success, $message, $data = []) {
    echo json_encode(compact('success', 'message', 'data'));
    exit;
}

$leaveTypeId = (int)($_GET['leaveTypeId'] ?? 0);
$employeeId  = (int)($_GET['employeeId'] ?? 0);

if ($employeeId <= 0 || $leaveTypeId <= 0) {
    respond(false, 'Invalid employee or leave type');
}

$employeeStmt = mysqli_prepare(
    $con,
    "SELECT id FROM employeeusers WHERE id=? AND employmentStatus='Active' LIMIT 1"
);

if (!$employeeStmt) {
    respond(false, 'Failed to validate employee');
}

mysqli_stmt_bind_param($employeeStmt, "i", $employeeId);
mysqli_stmt_execute($employeeStmt);

$employeeRes = mysqli_stmt_get_result($employeeStmt);
$employee = mysqli_fetch_assoc($employeeRes);
mysqli_stmt_close($employeeStmt);

if (!$employee) {
    respond(false, 'Invalid employee selected');
}

$balance = getOrCreateBalance(
    $con,
    $employeeId,
    $leaveTypeId
);

respond(true, 'Balance fetched', [
    'remainingLeaves' => (float)$balance['remainingLeaves'],
    'usedLeaves'      => (float)$balance['usedLeaves']
]);


?>