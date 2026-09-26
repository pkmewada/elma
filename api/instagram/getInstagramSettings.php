<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';

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

try {
    respond(true, 'Instagram settings loaded successfully.', [
        'instagramSettings' => getInstagramSettings($con),
        'instagramAccounts' => getInstagramAccounts($con, $clientId),
        'defaultRedirectUrl' => BASE_URL . '/api/instagram/instagramOauthCallback.php',
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
