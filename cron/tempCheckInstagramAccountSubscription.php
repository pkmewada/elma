<?php

/*
|--------------------------------------------------------------------------
| TEMPORARY, READ-ONLY diagnostic — Phase 11 webhook subscription
| correction. NOT a cron job. Do not register on Hostinger Cron. Delete
| after use.
|
| Purpose: check whether Meta App 1091563173330460 is currently subscribed
| to the INSTAGRAM BUSINESS ACCOUNT itself (not the linked Facebook Page —
| see the Phase 11 investigation note: POST /{facebookPageId}/subscribed_apps
| rejected "comments" with "(#100) Param subscribed_fields[0] must be one of
| {feed, mention, name, picture, ...}", proving "comments" is an
| Instagram-object field that belongs on the IG User node's own
| subscribed_apps edge, not the Page's).
|
| Makes exactly ONE GET call — no subscription is created or changed by
| this script. Reuses the existing account-loading, token-decryption, and
| Graph API request path. Never prints, logs, or stores the access token.
|
| Usage:
|   php cron/tempCheckInstagramAccountSubscription.php <instagramAccountId>
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
    fwrite(STDERR, "Usage: php cron/tempCheckInstagramAccountSubscription.php <instagramAccountId>\n");
    exit(1);
}

$account = getInstagramAccountById($con, $accountId);

if (!$account) {
    fwrite(STDERR, "No connected Instagram account found with id {$accountId}.\n");
    exit(1);
}

if ($account['instagramUserId'] === '') {
    fwrite(STDERR, "Account #{$accountId} has no instagramUserId.\n");
    exit(1);
}

$instagramUserId = $account['instagramUserId'];

echo "Checking current subscribed_apps state for Instagram account {$instagramUserId}...\n";

try {
    $result = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $instagramUserId . '/subscribed_apps',
        ['access_token' => $account['accessToken']],
        'GET'
    );

    // $result never contains the access token (Meta's subscribed_apps
    // response is app id/name/subscribed_fields only) — safe to print whole.
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    // instagramGraphApiRequest() never embeds access_token in the thrown
    // message (only in the params array, which it redacts before logging).
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
