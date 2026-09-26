<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/InstagramInsights.php';

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

$clientId = (int)($_GET['clientId'] ?? 0);
$accountId = (int)($_GET['accountId'] ?? 0) ?: null;
$postId = (int)($_GET['postId'] ?? 0) ?: null;
$days = (int)($_GET['days'] ?? 30);
$days = $days > 0 && $days <= 90 ? $days : 30;

if ($clientId <= 0) {
    respond(false, 'Please select a client.');
}

try {
    respond(true, 'Instagram insights loaded successfully.', [
        'insights' => getInstagramInsights($con, $clientId, $accountId, $postId, $days),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
