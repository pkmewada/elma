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
    respond(false, 'Only Super Admin can record salary payments.');
}

$payload = json_decode((string)file_get_contents('php://input'), true);

if (!is_array($payload)) {
    respond(false, 'Invalid payload.');
}

try {
    $engine = new PayrollApprovalEngine($con);
    $result = $engine->addPayment($payload, getLoggedInUserId());

    respond((bool)$result['success'], (string)$result['message'], $result);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
