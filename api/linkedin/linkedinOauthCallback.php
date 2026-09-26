<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/LinkedInAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

function redirectWithLinkedinStatus(string $status, string $message, int $clientId = 0): void
{
    $clientParam = $clientId > 0 ? ('&clientId=' . $clientId) : '';
    header('Location: ' . BASE_URL . '/instagram-automation?liStatus=' . urlencode($status)
        . '&liMessage=' . urlencode($message) . $clientParam);
    exit;
}

$state = (string)($_GET['state'] ?? '');
$sessionState = (string)($_SESSION['linkedinOauthState'] ?? '');
$clientId = (int)($_SESSION['linkedinOauthClientId'] ?? 0);
unset($_SESSION['linkedinOauthState'], $_SESSION['linkedinOauthClientId']);

if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
    redirectWithLinkedinStatus('error', 'LinkedIn connection request could not be verified. Please try again.');
}

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    redirectWithLinkedinStatus('error', 'The selected client could not be verified. Please start the connection again.');
}

if (!empty($_GET['error'])) {
    redirectWithLinkedinStatus('error', (string)($_GET['error_description'] ?? 'LinkedIn authorization was cancelled.'), $clientId);
}

$code = (string)($_GET['code'] ?? '');

if ($code === '') {
    redirectWithLinkedinStatus('error', 'Missing authorization code from LinkedIn.', $clientId);
}

$settings = getLinkedinSettingsForOAuth($con);

if (!$settings) {
    redirectWithLinkedinStatus('error', 'LinkedIn API settings are not configured.', $clientId);
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/linkedin/linkedinOauthCallback.php';

try {
    $tokenResponse = linkedinTokenRequest('https://www.linkedin.com/oauth/v2/accessToken', [
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'client_id' => $settings['linkedinClientId'],
        'client_secret' => $settings['linkedinClientSecret'],
    ]);

    if (empty($tokenResponse['access_token'])) {
        redirectWithLinkedinStatus('error', 'Unable to obtain an access token from LinkedIn.', $clientId);
    }

    $accessToken = (string)$tokenResponse['access_token'];
    $expiresIn = isset($tokenResponse['expires_in']) ? (int)$tokenResponse['expires_in'] : null;
    $tokenExpiry = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;

    $profile = fetchLinkedinMemberProfile($accessToken);

    if ($profile['linkedinMemberId'] === '') {
        redirectWithLinkedinStatus('error', 'Unable to identify the authenticated LinkedIn member.', $clientId);
    }

    $accountId = saveLinkedinAccountFromOAuth($con, [
        'clientId' => $clientId,
        'linkedinMemberId' => $profile['linkedinMemberId'],
        'memberName' => $profile['memberName'],
        'accessToken' => $accessToken,
        'tokenExpiry' => $tokenExpiry,
    ], getLoggedInUserId());

    if ($accountId <= 0) {
        redirectWithLinkedinStatus('error', 'Unable to save the LinkedIn connection.', $clientId);
    }

    $clientLabel = getInstagramClientLabel($con, $clientId);

    saveActivityLog(
        $con,
        'LinkedInAutomation',
        $accountId,
        'connect',
        'LinkedIn account connected for Client: ' . $clientLabel . '.'
    );

    redirectWithLinkedinStatus('success', 'LinkedIn account connected successfully for ' . $clientLabel . '. Select an organization to finish setup.', $clientId);
} catch (Throwable $e) {
    error_log('LinkedIn OAuth callback error (clientId ' . $clientId . '): ' . $e->getMessage());
    saveActivityLog($con, 'LinkedInAutomation', 0, 'connect_failed', 'LinkedIn connection failed for Client: ' . getInstagramClientLabel($con, $clientId) . '.');
    redirectWithLinkedinStatus('error', 'LinkedIn connection failed. Please try again or contact the administrator.', $clientId);
}
