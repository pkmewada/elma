<?php

/*
|--------------------------------------------------------------------------
| Manual diagnostic tool — NOT a cron job. Do not register on Hostinger
| Cron.
|--------------------------------------------------------------------------
| Looks up one connected instagramAccounts row by id, decrypts its stored
| Page Access Token IN MEMORY ONLY, and calls Meta's own /debug_token
| endpoint (the server-side equivalent of the Access Token Debugger UI) —
| https://developers.facebook.com/docs/graph-api/reference/debug_token/
|
| The plaintext token is NEVER printed, logged, or returned — only the
| fields Meta's debug_token response contains (validity, scopes, expiry,
| app id, etc.). If you need to inspect the token itself, that is a
| deliberate limitation, not an oversight (docs/
| INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §14/§20 rule 9: never expose
| access tokens).
|
| Usage:
|   php cron/debugFacebookAccessToken.php <instagramAccountId>
|--------------------------------------------------------------------------
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';

$accountId = (int)($argv[1] ?? 0);

if ($accountId <= 0) {
    fwrite(STDERR, "Usage: php cron/debugFacebookAccessToken.php <instagramAccountId>\n");
    exit(1);
}

$account = getInstagramAccountById($con, $accountId);

if (!$account) {
    fwrite(STDERR, "No connected Instagram account found with id {$accountId}.\n");
    exit(1);
}

$settings = getInstagramSettingsForOAuth($con);

if (!$settings) {
    fwrite(STDERR, "Instagram API settings (Meta App ID/Secret) are not configured.\n");
    exit(1);
}

try {
    // Meta's debug_token accepts "app_id|app_secret" directly as the
    // access_token param (an app access token) — no separate token
    // exchange call needed.
    $appAccessToken = $settings['metaAppId'] . '|' . $settings['metaAppSecret'];

    $debug = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/debug_token',
        [
            'input_token' => $account['accessToken'],
            'access_token' => $appAccessToken,
        ],
        'GET'
    );

    $data = $debug['data'] ?? [];

    echo "Account: id={$account['id']} facebookPageId={$account['facebookPageId']} instagramUserId={$account['instagramUserId']}\n";
    echo "---- Meta /debug_token result (token value withheld) ----\n";
    echo 'is_valid: ' . (isset($data['is_valid']) ? ($data['is_valid'] ? 'true' : 'false') : 'unknown') . "\n";
    echo 'type: ' . (string)($data['type'] ?? 'unknown') . "\n";
    echo 'app_id: ' . (string)($data['app_id'] ?? 'unknown') . "\n";
    echo 'application: ' . (string)($data['application'] ?? 'unknown') . "\n";
    echo 'profile_id / user_id: ' . (string)($data['profile_id'] ?? $data['user_id'] ?? 'n/a') . "\n";
    echo 'issued_at: ' . (isset($data['issued_at']) ? date('Y-m-d H:i:s', (int)$data['issued_at']) : 'n/a') . "\n";
    echo 'expires_at: ' . (isset($data['expires_at']) && (int)$data['expires_at'] > 0
        ? date('Y-m-d H:i:s', (int)$data['expires_at'])
        : 'never / not provided') . "\n";
    echo 'data_access_expires_at: ' . (isset($data['data_access_expires_at'])
        ? date('Y-m-d H:i:s', (int)$data['data_access_expires_at'])
        : 'n/a') . "\n";
    echo 'scopes: ' . (isset($data['scopes']) ? implode(', ', $data['scopes']) : 'n/a') . "\n";

    if (!empty($data['error'])) {
        echo 'error: ' . json_encode($data['error']) . "\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
