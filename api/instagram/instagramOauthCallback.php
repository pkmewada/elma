<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

function redirectWithStatus(string $status, string $message, int $clientId = 0): void
{
    $clientParam = $clientId > 0 ? ('&clientId=' . $clientId) : '';
    header('Location: ' . BASE_URL . '/instagram-automation?igStatus=' . urlencode($status)
        . '&igMessage=' . urlencode($message) . $clientParam);
    exit;
}

$state = (string)($_GET['state'] ?? '');
$sessionState = (string)($_SESSION['instagramOauthState'] ?? '');
$clientId = (int)($_SESSION['instagramOauthClientId'] ?? 0);
unset($_SESSION['instagramOauthState'], $_SESSION['instagramOauthClientId']);

if ($state === '' || $sessionState === '' || !hash_equals($sessionState, $state)) {
    redirectWithStatus('error', 'Instagram connection request could not be verified. Please try again.');
}

if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
    redirectWithStatus('error', 'The selected client could not be verified. Please start the connection again.');
}

if (!empty($_GET['error'])) {
    redirectWithStatus('error', (string)($_GET['error_description'] ?? 'Instagram authorization was cancelled.'), $clientId);
}

$code = (string)($_GET['code'] ?? '');

if ($code === '') {
    redirectWithStatus('error', 'Missing authorization code from Meta.', $clientId);
}

$settings = getInstagramSettingsForOAuth($con);

if (!$settings) {
    redirectWithStatus('error', 'Instagram API settings are not configured.', $clientId);
}

$redirectUri = $settings['redirectUrl'] !== ''
    ? $settings['redirectUrl']
    : BASE_URL . '/api/instagram/instagramOauthCallback.php';

try {
    $tokenResponse = instagramGraphApiRequest('https://graph.facebook.com/v19.0/oauth/access_token', [
        'client_id' => $settings['metaAppId'],
        'client_secret' => $settings['metaAppSecret'],
        'redirect_uri' => $redirectUri,
        'code' => $code,
    ]);

    if (empty($tokenResponse['access_token'])) {
        redirectWithStatus('error', 'Unable to obtain an access token from Meta.', $clientId);
    }

    $userAccessToken = (string)$tokenResponse['access_token'];

    // The token Meta just issued is short-lived (~1-2 hours). Page access
    // tokens derived from a long-lived user token are effectively
    // non-expiring, so exchange before deriving them — without this, cron
    // publishing would start failing within hours of connecting an account.
    // Failure here is non-fatal: fall back to the short-lived token rather
    // than aborting the whole connection.
    try {
        $longLivedResponse = instagramGraphApiRequest('https://graph.facebook.com/v19.0/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $settings['metaAppId'],
            'client_secret' => $settings['metaAppSecret'],
            'fb_exchange_token' => $userAccessToken,
        ], 'GET');

        if (!empty($longLivedResponse['access_token'])) {
            $userAccessToken = (string)$longLivedResponse['access_token'];
        }
    } catch (Throwable $e) {
        // Continue with the short-lived token; better than failing the connect flow.
    }

    $pagesResponse = instagramGraphApiRequest('https://graph.facebook.com/v19.0/me/accounts', [
        'access_token' => $userAccessToken,
        'fields' => 'id,name,access_token,instagram_business_account',
    ], 'GET');

    $pages = $pagesResponse['data'] ?? [];
    $connectedCount = 0;

    foreach ($pages as $page) {
        $igAccount = $page['instagram_business_account'] ?? null;

        if (!$igAccount || empty($igAccount['id'])) {
            continue;
        }

        $pageAccessToken = (string)($page['access_token'] ?? $userAccessToken);

        $igProfile = instagramGraphApiRequest('https://graph.facebook.com/v19.0/' . $igAccount['id'], [
            'fields' => 'username',
            'access_token' => $pageAccessToken,
        ], 'GET');

        $accountId = saveInstagramAccountFromOAuth($con, [
            'clientId' => $clientId,
            'instagramUserId' => (string)$igAccount['id'],
            'facebookPageId' => (string)($page['id'] ?? ''),
            'username' => (string)($igProfile['username'] ?? ''),
            'accessToken' => $pageAccessToken,
            'tokenExpiry' => null,
        ], getLoggedInUserId());

        if ($accountId > 0) {
            $connectedCount++;

            // Enrolls this Instagram account for Meta webhook delivery (see
            // subscribeInstagramAccountWebhooks() doc comment) — independent
            // of account connection success, so its return value
            // deliberately does not affect $connectedCount or the redirect
            // status below.
            subscribeInstagramAccountWebhooks((string)$igAccount['id'], $pageAccessToken);
        }
    }

    if ($connectedCount === 0) {
        redirectWithStatus('error', 'No Instagram Business account was found on your Facebook Pages.', $clientId);
    }

    $clientLabel = getInstagramClientLabel($con, $clientId);

    saveActivityLog(
        $con,
        'InstagramAutomation',
        0,
        'connect',
        'Instagram account connected for Client: ' . $clientLabel . '.'
    );

    redirectWithStatus('success', 'Instagram account connected successfully for ' . $clientLabel . '.', $clientId);
} catch (Throwable $e) {
    error_log('Instagram OAuth callback error (clientId ' . $clientId . '): ' . $e->getMessage());
    redirectWithStatus('error', 'Instagram connection failed. Please try again or contact the administrator.', $clientId);
}
