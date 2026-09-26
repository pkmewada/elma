<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
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

$metaAppId = postValue('metaAppId');
$metaAppSecret = postValue('metaAppSecret');
$metaConfigId = postValue('metaConfigId');
$redirectUrl = postValue('redirectUrl');

if ($metaAppId === '') {
    respond(false, 'Meta App ID is required.');
}

if ($redirectUrl !== '' && !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
    respond(false, 'Please enter a valid redirect URL.');
}

try {
    $saved = saveInstagramSettings(
        $con,
        [
            'metaAppId' => $metaAppId,
            'metaAppSecret' => $metaAppSecret,
            'metaConfigId' => $metaConfigId,
            'redirectUrl' => $redirectUrl,
        ],
        getLoggedInUserId()
    );

    if ($saved) {
        saveActivityLog(
            $con,
            'InstagramAutomation',
            0,
            'update',
            'Updated Instagram Automation API settings.'
        );
    }

    respond(
        $saved,
        $saved ? 'Instagram API settings updated successfully.' : 'Unable to save Instagram settings.',
        ['instagramSettings' => getInstagramSettings($con)]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
