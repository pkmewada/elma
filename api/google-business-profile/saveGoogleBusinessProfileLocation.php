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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$clientId = (int)($_POST['clientId'] ?? 0);
$googleAccountId = trim((string)($_POST['googleAccountId'] ?? ''));
$googleLocationId = trim((string)($_POST['googleLocationId'] ?? ''));

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    respond(false, 'Please select a valid client.');
}

if ($googleAccountId === '') {
    respond(false, 'Please select a Google Business Profile account.');
}

if ($googleLocationId === '') {
    respond(false, 'Please select a location.');
}

$account = getGoogleBusinessProfileAccountByClientId($con, $clientId);

if (!$account) {
    respond(false, 'No connected Google Business Profile account for this client.');
}

$oauthSettings = getGoogleBusinessProfileSettingsForOAuth($con);

if (!$oauthSettings) {
    respond(false, 'Google API settings are not configured.');
}

try {
    $accessToken = googleBusinessProfileValidAccessToken($con, $account, $oauthSettings);

    // Never trust the account/location name the browser posted —
    // re-verify server-side, against Google itself, that this Google
    // identity actually has access to the account AND that the location
    // actually belongs to it, before persisting anything.
    $googleAccount = googleBusinessProfileAccountAccessible($accessToken, $googleAccountId);

    if ($googleAccount === null) {
        respond(false, 'This Google account does not have access to the selected Business Profile account.');
    }

    $location = googleBusinessProfileLocationBelongsToAccount($accessToken, $googleAccountId, $googleLocationId);

    if ($location === null) {
        respond(false, 'The selected location does not belong to the selected Business Profile account.');
    }

    $saved = saveGoogleBusinessProfileLocationSelection(
        $con,
        $account['id'],
        $clientId,
        $googleAccountId,
        $googleAccount['name'],
        $googleAccount['type'],
        $googleLocationId,
        $location['title'],
        $location['title'],
        $location['address']
    );

    if ($saved) {
        $clientLabel = getInstagramClientLabel($con, $clientId);
        saveActivityLog(
            $con,
            'GoogleBusinessProfileAutomation',
            $account['id'],
            'select_location',
            'Google Business Profile location "' . $location['title'] . '" selected for Client: ' . $clientLabel . '.'
        );
    }

    respond(
        $saved,
        $saved ? 'Google Business Profile location saved successfully.' : 'Unable to save the selected location.',
        ['googleBusinessProfileAccount' => getGoogleBusinessProfileAccountForDisplay($con, $clientId)]
    );
} catch (GoogleBusinessProfilePermissionException $e) {
    respond(false, 'Google has not yet approved this project for Business Profile API access. Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
