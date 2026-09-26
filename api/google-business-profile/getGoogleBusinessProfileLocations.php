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

$clientId = (int)($_GET['clientId'] ?? 0);
$googleAccountId = trim((string)($_GET['googleAccountId'] ?? ''));

if ($clientId <= 0) {
    respond(false, 'Please select a client.');
}

if ($googleAccountId === '') {
    respond(false, 'Please select a Google Business Profile account first.');
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

    // Re-verify the requested account is actually one this Google
    // identity has access to before querying its locations — the browser
    // only ever chose from a list this same call already returned, but
    // never trust that choice without re-checking server-side.
    if (googleBusinessProfileAccountAccessible($accessToken, $googleAccountId) === null) {
        respond(false, 'This Google account does not have access to the selected Business Profile account.');
    }

    $locations = fetchGoogleBusinessProfileLocations($accessToken, $googleAccountId);

    respond(true, 'Google Business Profile locations loaded successfully.', ['locations' => $locations]);
} catch (GoogleBusinessProfilePermissionException $e) {
    respond(false, 'Google has not yet approved this project for Business Profile API access. Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
