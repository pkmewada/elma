<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/GoogleBusinessProfileAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

function redirectWithGoogleBusinessProfileStatus(string $status, string $message, int $clientId = 0): void
{
    $clientParam = $clientId > 0 ? ('&clientId=' . $clientId) : '';
    header('Location: ' . BASE_URL . '/instagram-automation?gbpStatus=' . urlencode($status)
        . '&gbpMessage=' . urlencode($message) . $clientParam);
    exit;
}

$state = (string)($_GET['state'] ?? '');
$sessionState = (string)($_SESSION['googleBusinessProfileOauthState'] ?? '');
$clientId = (int)($_SESSION['googleBusinessProfileOauthClientId'] ?? 0);
unset($_SESSION['googleBusinessProfileOauthState'], $_SESSION['googleBusinessProfileOauthClientId']);

if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
    redirectWithGoogleBusinessProfileStatus('error', 'Google connection request could not be verified. Please try again.');
}

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    redirectWithGoogleBusinessProfileStatus('error', 'The selected client could not be verified. Please start the connection again.');
}

if (!empty($_GET['error'])) {
    redirectWithGoogleBusinessProfileStatus('error', (string)($_GET['error_description'] ?? $_GET['error'] ?? 'Google authorization was cancelled.'), $clientId);
}

$code = (string)($_GET['code'] ?? '');

if ($code === '') {
    redirectWithGoogleBusinessProfileStatus('error', 'Missing authorization code from Google.', $clientId);
}

$settings = getGoogleBusinessProfileSettingsForOAuth($con);

if (!$settings) {
    redirectWithGoogleBusinessProfileStatus('error', 'Google API settings are not configured.', $clientId);
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/google-business-profile/googleBusinessProfileOauthCallback.php';

try {
    $tokens = exchangeGoogleBusinessProfileAuthorizationCode(
        $settings['googleClientId'],
        $settings['googleClientSecret'],
        $code,
        $redirectUri
    );

    if ($tokens['accessToken'] === '') {
        redirectWithGoogleBusinessProfileStatus('error', 'Unable to obtain an access token from Google.', $clientId);
    }

    $profile = fetchGoogleUserProfile($tokens['accessToken']);

    if ($profile['googleUserId'] === '') {
        redirectWithGoogleBusinessProfileStatus('error', 'Unable to identify the authenticated Google user.', $clientId);
    }

    $accountId = saveGoogleBusinessProfileAccountFromOAuth($con, [
        'clientId' => $clientId,
        'googleUserId' => $profile['googleUserId'],
        'googleUserEmail' => $profile['googleUserEmail'],
        'accessToken' => $tokens['accessToken'],
        'refreshToken' => $tokens['refreshToken'],
        'tokenExpiry' => $tokens['tokenExpiry'],
    ], getLoggedInUserId());

    if ($accountId <= 0) {
        redirectWithGoogleBusinessProfileStatus('error', 'Unable to save the Google connection.', $clientId);
    }

    $clientLabel = getInstagramClientLabel($con, $clientId);

    saveActivityLog(
        $con,
        'GoogleBusinessProfileAutomation',
        $accountId,
        'connect',
        'Google Business Profile account connected for Client: ' . $clientLabel . '.'
    );

    redirectWithGoogleBusinessProfileStatus('success', 'Google account connected successfully for ' . $clientLabel . '. Select a Business Profile account and location to finish setup.', $clientId);
} catch (Throwable $e) {
    error_log('Google Business Profile OAuth callback error (clientId ' . $clientId . '): ' . $e->getMessage());
    saveActivityLog($con, 'GoogleBusinessProfileAutomation', 0, 'connect_failed', 'Google Business Profile connection failed for Client: ' . getInstagramClientLabel($con, $clientId) . '.');
    redirectWithGoogleBusinessProfileStatus('error', 'Google connection failed. Please try again or contact the administrator.', $clientId);
}
