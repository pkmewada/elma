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

function postValue(string $key): string
{
    return trim((string)($_POST[$key] ?? ''));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$pinterestClientId = postValue('pinterestClientId');
$pinterestClientSecret = postValue('pinterestClientSecret');
$redirectUrl = postValue('redirectUrl');

if ($pinterestClientId === '') {
    respond(false, 'Pinterest Client ID is required.');
}

if ($redirectUrl !== '' && !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
    respond(false, 'Please enter a valid redirect URL.');
}

try {
    $saved = savePinterestSettings(
        $con,
        [
            'pinterestClientId' => $pinterestClientId,
            'pinterestClientSecret' => $pinterestClientSecret,
            'redirectUrl' => $redirectUrl,
        ],
        getLoggedInUserId()
    );

    if ($saved) {
        saveActivityLog(
            $con,
            'PinterestAutomation',
            0,
            'update',
            'Updated Pinterest Automation API settings.'
        );
    }

    respond(
        $saved,
        $saved ? 'Pinterest API settings updated successfully.' : 'Unable to save Pinterest settings.',
        ['pinterestSettings' => getPinterestSettings($con)]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
