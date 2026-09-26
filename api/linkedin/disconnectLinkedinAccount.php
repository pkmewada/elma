<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/LinkedInAutomation.php';
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
    respond(false, 'Invalid LinkedIn account.');
}

try {
    $account = getLinkedinAccountById($con, $accountId);
    $clientId = $account['clientId'] ?? null;

    $ok = disconnectLinkedinAccount($con, $accountId);

    if ($ok) {
        $clientLabel = getInstagramClientLabel($con, $clientId);
        saveActivityLog(
            $con,
            'LinkedInAutomation',
            $accountId,
            'disconnect',
            'Disconnected a LinkedIn account for Client: ' . $clientLabel . '.'
        );
    }

    respond(
        $ok,
        $ok ? 'LinkedIn account disconnected.' : 'Unable to disconnect LinkedIn account.',
        ['linkedinAccount' => $clientId ? getLinkedinAccountForDisplay($con, (int)$clientId) : null]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
