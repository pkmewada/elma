<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/PinterestAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

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

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$accountId = (int)($_POST['accountId'] ?? 0);

if ($accountId <= 0) {
    respond(false, 'Invalid Pinterest account.');
}

try {
    $account = getPinterestAccountById($con, $accountId);
    $clientId = $account['clientId'] ?? null;

    $ok = disconnectPinterestAccount($con, $accountId);

    if ($ok) {
        $clientLabel = getInstagramClientLabel($con, $clientId);
        saveActivityLog(
            $con,
            'PinterestAutomation',
            $accountId,
            'disconnect',
            'Disconnected a Pinterest account for Client: ' . $clientLabel . '.'
        );
    }

    respond(
        $ok,
        $ok ? 'Pinterest account disconnected.' : 'Unable to disconnect Pinterest account.',
        ['pinterestAccount' => $clientId ? getPinterestAccountForDisplay($con, (int)$clientId) : null]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
