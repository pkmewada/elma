<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';

$clientId = (int)($_GET['clientId'] ?? 0);

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    header('Location: ' . BASE_URL . '/instagram-automation?igStatus=error&igMessage='
        . urlencode('Please select a valid client before connecting an Instagram account.'));
    exit;
}

$settings = getInstagramSettingsForOAuth($con);

if (!$settings) {
    header('Location: ' . BASE_URL . '/instagram-automation?igStatus=error&igMessage='
        . urlencode('Please save your Meta App ID and App Secret before connecting an account.'));
    exit;
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/instagram/instagramOauthCallback.php';

$state = bin2hex(random_bytes(16));
$_SESSION['instagramOauthState'] = $state;
$_SESSION['instagramOauthClientId'] = $clientId;

$authorizeParams = [
    'client_id' => $settings['metaAppId'],
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'response_type' => 'code',
];

// Facebook Login for Business: when a login Configuration ID is set, the
// permissions requested come from that configuration in the Meta App
// Dashboard, not from a `scope` param — Meta ignores/rejects `scope`
// alongside `config_id`. Fall back to the legacy scope-based dialog for
// installs that haven't set a Configuration ID yet.
if ($settings['metaConfigId'] !== '') {
    $authorizeParams['config_id'] = $settings['metaConfigId'];
} else {
    $authorizeParams['scope'] = 'instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement';
}

$authorizeUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query($authorizeParams);

header('Location: ' . $authorizeUrl);
exit;
