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

$clientId = (int)($_POST['clientId'] ?? 0);
$boardId = trim((string)($_POST['boardId'] ?? ''));

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    respond(false, 'Please select a valid client.');
}

if ($boardId === '') {
    respond(false, 'Please select a board.');
}

$account = getPinterestAccountByClientId($con, $clientId);

if (!$account) {
    respond(false, 'No connected Pinterest account for this client.');
}

try {
    // Never trust the board name the browser posted — re-verify
    // server-side, against Pinterest itself, that this user actually owns
    // the requested board id before persisting anything.
    $boardName = pinterestUserOwnsBoard($account['accessToken'], $boardId);

    if ($boardName === null) {
        respond(false, 'This Pinterest account does not own the selected board.');
    }

    $saved = savePinterestBoardSelection($con, $account['id'], $clientId, $boardId, $boardName);

    if ($saved) {
        $clientLabel = getInstagramClientLabel($con, $clientId);
        saveActivityLog(
            $con,
            'PinterestAutomation',
            $account['id'],
            'select_board',
            'Pinterest board "' . $boardName . '" selected for Client: ' . $clientLabel . '.'
        );
    }

    respond(
        $saved,
        $saved ? 'Pinterest board saved successfully.' : 'Unable to save the selected Pinterest board.',
        ['pinterestAccount' => getPinterestAccountForDisplay($con, $clientId)]
    );
} catch (PinterestPermissionException $e) {
    respond(false, 'Pinterest has not yet granted this app access to verify boards. Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
