<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/PayrollApprovalEngine.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

if (!isLoggedInUserSuperAdmin()) {
    respond(false, 'Only Super Admin can view salary slip approvals.');
}

$status = trim((string)($_GET['status'] ?? ''));
$month = trim((string)($_GET['month'] ?? ''));

if ($status !== '' && !in_array($status, ['pending', 'approved', 'rejected'], true)) {
    respond(false, 'Invalid salary slip status.');
}

try {
    $engine = new PayrollApprovalEngine($con);

    respond(true, 'Salary slip approvals loaded successfully.', [
        'salarySlips' => $engine->listSlips($status, $month),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
