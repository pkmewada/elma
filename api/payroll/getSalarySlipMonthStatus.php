<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
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

$periodStart = trim((string)($_GET['periodStart'] ?? date('Y-m-01')));
$periodEnd = trim((string)($_GET['periodEnd'] ?? date('Y-m-t')));

if (strtotime($periodStart) === false || strtotime($periodEnd) === false) {
    respond(false, 'Invalid payroll period.');
}

if (strtotime($periodEnd) < strtotime($periodStart)) {
    respond(false, 'Period end must be after period start.');
}

try {
    $engine = new PayrollApprovalEngine($con);

    respond(true, 'Salary slip month status loaded successfully.', [
        'salarySlips' => $engine->listEmployeeMonthStatus($periodStart, $periodEnd),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
