<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/PinterestAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

function redirectWithPinterestStatus(string $status, string $message, int $clientId = 0): void
{
    $clientParam = $clientId > 0 ? ('&clientId=' . $clientId) : '';
    header('Location: ' . BASE_URL . '/instagram-automation?piStatus=' . urlencode($status)
        . '&piMessage=' . urlencode($message) . $clientParam);
    exit;
}

$state = (string)($_GET['state'] ?? '');
$sessionState = (string)($_SESSION['pinterestOauthState'] ?? '');
$clientId = (int)($_SESSION['pinterestOauthClientId'] ?? 0);
unset($_SESSION['pinterestOauthState'], $_SESSION['pinterestOauthClientId']);

if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
    redirectWithPinterestStatus('error', 'Pinterest connection request could not be verified. Please try again.');
}

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    redirectWithPinterestStatus('error', 'The selected client could not be verified. Please start the connection again.');
}

if (!empty($_GET['error'])) {
    redirectWithPinterestStatus('error', (string)($_GET['error_description'] ?? 'Pinterest authorization was cancelled.'), $clientId);
}

$code = (string)($_GET['code'] ?? '');

if ($code === '') {
    redirectWithPinterestStatus('error', 'Missing authorization code from Pinterest.', $clientId);
}

$settings = getPinterestSettingsForOAuth($con);

if (!$settings) {
    redirectWithPinterestStatus('error', 'Pinterest API settings are not configured.', $clientId);
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/pinterest/pinterestOauthCallback.php';

try {
    $tokens = exchangePinterestAuthorizationCode(
        $settings['pinterestClientId'],
        $settings['pinterestClientSecret'],
        $code,
        $redirectUri
    );

    if ($tokens['accessToken'] === '') {
        redirectWithPinterestStatus('error', 'Unable to obtain an access token from Pinterest.', $clientId);
    }

    $profile = fetchPinterestUserProfile($tokens['accessToken']);

    if ($profile['pinterestUserId'] === '') {
        redirectWithPinterestStatus('error', 'Unable to identify the authenticated Pinterest user.', $clientId);
    }

    $accountId = savePinterestAccountFromOAuth($con, [
        'clientId' => $clientId,
        'pinterestUserId' => $profile['pinterestUserId'],
        'username' => $profile['username'],
        'accessToken' => $tokens['accessToken'],
        'refreshToken' => $tokens['refreshToken'],
        'tokenExpiry' => $tokens['tokenExpiry'],
        'refreshTokenExpiry' => $tokens['refreshTokenExpiry'],
    ], getLoggedInUserId());

    if ($accountId <= 0) {
        redirectWithPinterestStatus('error', 'Unable to save the Pinterest connection.', $clientId);
    }

    $clientLabel = getInstagramClientLabel($con, $clientId);

    saveActivityLog(
        $con,
        'PinterestAutomation',
        $accountId,
        'connect',
        'Pinterest account connected for Client: ' . $clientLabel . '.'
    );

    redirectWithPinterestStatus('success', 'Pinterest account connected successfully for ' . $clientLabel . '. Select a board to finish setup.', $clientId);
} catch (Throwable $e) {
    error_log('Pinterest OAuth callback error (clientId ' . $clientId . '): ' . $e->getMessage());
    saveActivityLog($con, 'PinterestAutomation', 0, 'connect_failed', 'Pinterest connection failed for Client: ' . getInstagramClientLabel($con, $clientId) . '.');
    redirectWithPinterestStatus('error', 'Pinterest connection failed. Please try again or contact the administrator.', $clientId);
}
