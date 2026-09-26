<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PinterestAutomation.php';

$clientId = (int)($_GET['clientId'] ?? 0);

// instagramClientExists() is a generic clientMaster existence check reused
// as-is (see PinterestAutomation.php's require of InstagramAutomation.php).
if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    header('Location: ' . BASE_URL . '/instagram-automation?piStatus=error&piMessage='
        . urlencode('Please select a valid client before connecting a Pinterest account.'));
    exit;
}

$settings = getPinterestSettingsForOAuth($con);

if (!$settings) {
    header('Location: ' . BASE_URL . '/instagram-automation?piStatus=error&piMessage='
        . urlencode('Please save your Pinterest Client ID and Client Secret before connecting an account.'));
    exit;
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/pinterest/pinterestOauthCallback.php';

$state = bin2hex(random_bytes(16));
$_SESSION['pinterestOauthState'] = $state;
$_SESSION['pinterestOauthClientId'] = $clientId;

// user_accounts:read + boards:read: read-only identity + board discovery
// for this foundation phase. pins:write/boards:write are deliberately not
// requested — this phase does not publish anything (see
// docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md Pinterest Foundation
// section for the full scope breakdown).
$scope = 'user_accounts:read,boards:read';

$authorizeUrl = PINTEREST_OAUTH_AUTHORIZE_URL . '?' . http_build_query([
    'client_id' => $settings['pinterestClientId'],
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => $scope,
    'state' => $state,
]);

header('Location: ' . $authorizeUrl);
exit;
