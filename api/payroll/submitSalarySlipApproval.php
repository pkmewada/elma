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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

if (!hasRoutePermission('/salary-slip', 'canAdd') && !hasRoutePermission('/salary-slip', 'canEdit')) {
    respond(false, 'You do not have permission to submit salary slips.');
}

$payload = json_decode((string)file_get_contents('php://input'), true);

if (!is_array($payload)) {
    respond(false, 'Invalid payload.');
}

$employeeId = (int)($payload['employeeId'] ?? 0);
$periodStart = trim((string)($payload['periodStart'] ?? ''));
$periodEnd = trim((string)($payload['periodEnd'] ?? ''));

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
    $engine = new PayrollApprovalEngine($con);
    $result = $engine->submitForApproval($employeeId, $periodStart, $periodEnd, getLoggedInUserId());

    respond((bool)$result['success'], (string)$result['message'], $result);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
