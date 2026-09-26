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

$linkedinClientId = postValue('linkedinClientId');
$linkedinClientSecret = postValue('linkedinClientSecret');
$redirectUrl = postValue('redirectUrl');

if ($linkedinClientId === '') {
    respond(false, 'LinkedIn Client ID is required.');
}

if ($redirectUrl !== '' && !filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
    respond(false, 'Please enter a valid redirect URL.');
}

try {
    $saved = saveLinkedinSettings(
        $con,
        [
            'linkedinClientId' => $linkedinClientId,
            'linkedinClientSecret' => $linkedinClientSecret,
            'redirectUrl' => $redirectUrl,
        ],
        getLoggedInUserId()
    );

    if ($saved) {
        saveActivityLog(
            $con,
            'LinkedInAutomation',
            0,
            'update',
            'Updated LinkedIn Automation API settings.'
        );
    }

    respond(
        $saved,
        $saved ? 'LinkedIn API settings updated successfully.' : 'Unable to save LinkedIn settings.',
        ['linkedinSettings' => getLinkedinSettings($con)]
    );
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
