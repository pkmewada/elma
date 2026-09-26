<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/GoogleBusinessProfileAutomation.php';

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
    respond(true, 'Google Business Profile settings loaded successfully.', [
        'googleBusinessProfileSettings' => getGoogleBusinessProfileSettings($con),
        'googleBusinessProfileAccount' => $clientId ? getGoogleBusinessProfileAccountForDisplay($con, $clientId) : null,
        'defaultRedirectUrl' => BASE_URL . '/api/google-business-profile/googleBusinessProfileOauthCallback.php',
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
