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
    respond(false, 'Only Super Admin can view salary slip details.');
}

$salarySlipId = (int)($_GET['salarySlipId'] ?? 0);

if ($salarySlipId <= 0) {
    respond(false, 'Invalid salary slip.');
}

try {
    $engine = new PayrollApprovalEngine($con);
    $overview = $engine->getSlipOverview($salarySlipId);

    respond((bool)($overview['success'] ?? false), (string)($overview['message'] ?? 'Salary slip overview loaded successfully.'), $overview);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
