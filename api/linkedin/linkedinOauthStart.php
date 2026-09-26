<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/LinkedInAutomation.php';

$clientId = (int)($_GET['clientId'] ?? 0);

// instagramClientExists() is a generic clientMaster existence check reused
// as-is (see LinkedInAutomation.php's require of InstagramAutomation.php).
if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    header('Location: ' . BASE_URL . '/instagram-automation?liStatus=error&liMessage='
        . urlencode('Please select a valid client before connecting a LinkedIn account.'));
    exit;
}

$settings = getLinkedinSettingsForOAuth($con);

if (!$settings) {
    header('Location: ' . BASE_URL . '/instagram-automation?liStatus=error&liMessage='
        . urlencode('Please save your LinkedIn Client ID and Client Secret before connecting an account.'));
    exit;
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/linkedin/linkedinOauthCallback.php';

$state = bin2hex(random_bytes(16));
$_SESSION['linkedinOauthState'] = $state;
$_SESSION['linkedinOauthClientId'] = $clientId;

// openid + profile: OIDC "Sign In with LinkedIn" — identifies the member.
// r_organization_admin: discovery-only access to the organizations the
// member administers (Organization Access Control / Organization Lookup
// APIs). Posting as an organization later will require rw_organization_admin
// plus Community Management API product access — deliberately not
// requested yet, since this phase does not publish anything (see
// docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md Phase 12 section for the
// full scope breakdown).
$scope = 'openid profile r_organization_admin';

$authorizeUrl = 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
    'response_type' => 'code',
    'client_id' => $settings['linkedinClientId'],
    'redirect_uri' => $redirectUri,
    'state' => $state,
    'scope' => $scope,
]);

header('Location: ' . $authorizeUrl);
exit;
