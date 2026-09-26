<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/GoogleBusinessProfileAutomation.php';
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

$googleClientId = postValue('googleClientId');
$googleClientSecret = postValue('googleClientSecret');
$redirectUrl = postValue('redirectUrl');

if ($googleClientId === '') {
    respond(false, 'Google Client ID is required.');
}

if ($redirectUrl !== '' && !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
    respond(false, 'Please enter a valid redirect URL.');
}

try {
    $saved = saveGoogleBusinessProfileSettings(
        $con,
        [
            'googleClientId' => $googleClientId,
            'googleClientSecret' => $googleClientSecret,
            'redirectUrl' => $redirectUrl,
        ],
        getLoggedInUserId()
    );

    if ($saved) {
        saveActivityLog(
            $con,
            'GoogleBusinessProfileAutomation',
            0,
            'update',
            'Updated Google Business Profile Automation API settings.'
        );
    }

    respond(
        $saved,
        $saved ? 'Google Business Profile API settings updated successfully.' : 'Unable to save Google Business Profile settings.',
        ['googleBusinessProfileSettings' => getGoogleBusinessProfileSettings($con)]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
