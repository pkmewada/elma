<?php

/*
|--------------------------------------------------------------------------
| Manual test tool for Phase 5 (Facebook Page Publishing) — NOT a cron job.
| Do not register this on Hostinger Cron. Run by hand from CLI to verify
| publishFacebookImagePost()/publishFacebookTextPost() against one real,
| already-connected account before Phase 6/7 wires Facebook into any
| scheduler.
|
| Usage:
|   php cron/testFacebookPublish.php <instagramAccountId> image <imageUrl> [caption]
|   php cron/testFacebookPublish.php <instagramAccountId> text "<message>"
|--------------------------------------------------------------------------
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';
require_once __DIR__ . '/../includes/FacebookPublisher.php';

$accountId = (int)($argv[1] ?? 0);
$mode = (string)($argv[2] ?? '');

if ($accountId <= 0 || !in_array($mode, ['image', 'text'], true)) {
    fwrite(STDERR, "Usage:\n"
        . "  php cron/testFacebookPublish.php <instagramAccountId> image <imageUrl> [caption]\n"
        . "  php cron/testFacebookPublish.php <instagramAccountId> text \"<message>\"\n");
    exit(1);
}

$account = getInstagramAccountById($con, $accountId);

if (!$account) {
    fwrite(STDERR, "No connected Instagram account found with id {$accountId}.\n");
    exit(1);
}

if ($account['facebookPageId'] === '') {
    fwrite(STDERR, "Account #{$accountId} has no linked facebookPageId.\n");
    exit(1);
}

try {
    if ($mode === 'image') {
        $imageUrl = (string)($argv[3] ?? '');
        $caption = (string)($argv[4] ?? '');

        if ($imageUrl === '') {
            fwrite(STDERR, "Missing <imageUrl> for image mode.\n");
            exit(1);
        }

        $result = publishFacebookImagePost($account, $imageUrl, $caption);
    } else {
        $message = (string)($argv[3] ?? '');

        if ($message === '') {
            fwrite(STDERR, "Missing \"<message>\" for text mode.\n");
            exit(1);
        }

        $result = publishFacebookTextPost($account, $message);
    }

    echo "Success. Facebook post id: {$result['facebookPostId']}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Failed: ' . $e->getMessage() . "\n");
    exit(1);
}
