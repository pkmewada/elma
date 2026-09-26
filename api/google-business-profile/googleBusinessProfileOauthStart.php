<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/GoogleBusinessProfileAutomation.php';

$clientId = (int)($_GET['clientId'] ?? 0);

// instagramClientExists() is a generic clientMaster existence check reused
// as-is (see GoogleBusinessProfileAutomation.php's require of
// InstagramAutomation.php).
if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    header('Location: ' . BASE_URL . '/instagram-automation?gbpStatus=error&gbpMessage='
        . urlencode('Please select a valid client before connecting a Google Business Profile account.'));
    exit;
}

$settings = getGoogleBusinessProfileSettingsForOAuth($con);

if (!$settings) {
    header('Location: ' . BASE_URL . '/instagram-automation?gbpStatus=error&gbpMessage='
        . urlencode('Please save your Google Client ID and Client Secret before connecting an account.'));
    exit;
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/google-business-profile/googleBusinessProfileOauthCallback.php';

$state = bin2hex(random_bytes(16));
$_SESSION['googleBusinessProfileOauthState'] = $state;
$_SESSION['googleBusinessProfileOauthClientId'] = $clientId;

// access_type=offline + prompt=consent: required to receive a
// refresh_token (source doc §7 "request offline access so Modlus can
// refresh tokens and operate when the user is not present"). Without
// prompt=consent, Google only issues a refresh_token on a user's very
// first authorization of this app — forcing consent guarantees Modlus
// always gets one on (re)connect, matching the "reconnect must still
// yield a usable connection" behavior LinkedIn/Pinterest provide via
// their own OAuth flows.
$authorizeUrl = GOOGLE_OAUTH_AUTHORIZE_URL . '?' . http_build_query([
    'client_id' => $settings['googleClientId'],
    'redirect_uri' => $redirectUri,
    'response_type' => 'code',
    'scope' => GOOGLE_OAUTH_SCOPE,
    'access_type' => 'offline',
    'prompt' => 'consent',
    'include_granted_scopes' => 'true',
    'state' => $state,
]);

header('Location: ' . $authorizeUrl);
exit;
