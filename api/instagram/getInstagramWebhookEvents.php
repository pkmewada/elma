<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/InstagramWebhooks.php';

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

$clientId = (int)($_GET['clientId'] ?? 0) ?: null;
$accountId = (int)($_GET['accountId'] ?? 0) ?: null;
$status = trim((string)($_GET['status'] ?? '')) ?: null;

if ($clientId === null) {
    respond(false, 'Please select a client.');
}

try {
    respond(true, 'Webhook events loaded successfully.', [
        'events' => getInstagramWebhookEvents($con, $clientId, $accountId, $status, 20),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
