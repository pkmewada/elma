<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PayrollEngine.php';

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

$employeeId = (int)($_GET['employeeId'] ?? 0);
$periodStart = trim((string)($_GET['periodStart'] ?? ''));
$periodEnd = trim((string)($_GET['periodEnd'] ?? ''));

if ($employeeId <= 0 || $periodStart === '' || $periodEnd === '') {
    respond(false, 'Employee and payroll period are required.');
}

if (strtotime($periodStart) === false || strtotime($periodEnd) === false) {
    respond(false, 'Invalid payroll period.');
}

if (strtotime($periodEnd) < strtotime($periodStart)) {
    respond(false, 'Period end must be after period start.');
}

try {
    $engine = new PayrollEngine($con);
    $result = $engine->calculateSalarySlip($employeeId, $periodStart, $periodEnd);

    respond(
        (bool)$result['success'],
        (string)$result['message'],
        (array)($result['data'] ?? [])
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
