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

if (!isLoggedInUserSuperAdmin()) {
    respond(false, 'Only Super Admin can send salary slip emails.');
}

$payload = json_decode((string)file_get_contents('php://input'), true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$salarySlipId = (int)($payload['salarySlipId'] ?? 0);
$type = trim((string)($payload['type'] ?? 'payment'));

if ($salarySlipId <= 0 || !in_array($type, ['payment', 'overview'], true)) {
    respond(false, 'Invalid request.');
}

try {
    $engine = new PayrollApprovalEngine($con);

    $result = $type === 'overview'
        ? $engine->sendOverviewEmail($salarySlipId)
        : $engine->sendPaymentInfoEmail($salarySlipId);

    respond((bool)($result['success'] ?? false), (string)($result['message'] ?? 'Unable to send email.'));
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
