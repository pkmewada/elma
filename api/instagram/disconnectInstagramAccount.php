<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';
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
    respond(false, 'Invalid Instagram account.');
}

try {
    $accountRow = null;
    $stmt = mysqli_prepare($con, "SELECT clientId FROM instagramAccounts WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $accountId);
    mysqli_stmt_execute($stmt);
    $accountRow = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    $clientId = $accountRow && $accountRow['clientId'] !== null ? (int)$accountRow['clientId'] : null;
    $ok = disconnectInstagramAccount($con, $accountId);

    if ($ok) {
        $clientLabel = getInstagramClientLabel($con, $clientId);
        saveActivityLog(
            $con,
            'InstagramAutomation',
            $accountId,
            'disconnect',
            'Disconnected an Instagram Business account for Client: ' . $clientLabel . '.'
        );
    }

    respond(
        $ok,
        $ok ? 'Instagram account disconnected.' : 'Unable to disconnect Instagram account.',
        ['instagramAccounts' => getInstagramAccounts($con, $clientId)]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
