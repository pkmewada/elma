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

if ($clientId <= 0) {
    respond(false, 'Please select a client.');
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
    $accounts = fetchGoogleBusinessProfileAccounts($accessToken);

    respond(true, 'Google Business Profile accounts loaded successfully.', ['accounts' => $accounts]);
} catch (GoogleBusinessProfilePermissionException $e) {
    // Not a bug — this Google Cloud project's Business Profile API access
    // has not been approved yet (0 QPM until approved — see
    // docs/GMB_INTEGRATION_FOUNDATION_PHASE_14.md §2). Surfaced distinctly
    // so it reads as an external dependency, not a broken feature.
    respond(false, 'Google has not yet approved this project for Business Profile API access. Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
