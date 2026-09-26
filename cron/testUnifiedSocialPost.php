<?php

/*
|--------------------------------------------------------------------------
| MANUAL TEST ONLY — NOT A CRON JOB.
|--------------------------------------------------------------------------
| Do not register this on Hostinger Cron. Exercises the Phase 6 Unified
| Social Post Engine (includes/SocialPostEngine.php) directly against one
| real, already-connected account, without going through the CRM UI.
|
| Usage:
|   php cron/testUnifiedSocialPost.php <accountId> <platforms> image <imageUrl> [caption]
|   php cron/testUnifiedSocialPost.php <accountId> <platforms> text "<message>"
|
| <platforms> is a comma-separated list: instagram | facebook | instagram,facebook
|
| Examples:
|   php cron/testUnifiedSocialPost.php 1 facebook image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Facebook Test"
|   php cron/testUnifiedSocialPost.php 1 instagram,facebook image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Unified Test"
|   php cron/testUnifiedSocialPost.php 1 facebook text "MODLUS Phase 6 Facebook Text Test"
|   php cron/testUnifiedSocialPost.php 1 instagram text "anything"   (expected: clean unsupported result)
|
| Never prints the account's access token.
|--------------------------------------------------------------------------
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';
require_once __DIR__ . '/../includes/SocialPostEngine.php';

function printUsageAndExit(): void
{
    fwrite(STDERR, "Usage:\n"
        . "  php cron/testUnifiedSocialPost.php <accountId> <platforms> image <imageUrl> [caption]\n"
        . "  php cron/testUnifiedSocialPost.php <accountId> <platforms> text \"<message>\"\n"
        . "<platforms> is comma-separated: instagram | facebook | instagram,facebook\n");
    exit(1);
}

$accountId = (int)($argv[1] ?? 0);
$platformsInput = (string)($argv[2] ?? '');
$type = (string)($argv[3] ?? '');
$platforms = array_values(array_filter(array_map('trim', explode(',', $platformsInput))));

if ($accountId <= 0 || empty($platforms) || !in_array($type, ['image', 'text'], true)) {
    printUsageAndExit();
}

$account = getInstagramAccountById($con, $accountId);

if (!$account) {
    fwrite(STDERR, "No connected Instagram account found with id {$accountId}.\n");
    exit(1);
}

$content = [];

if ($type === 'image') {
    $content['imageUrl'] = (string)($argv[4] ?? '');
    $content['caption'] = (string)($argv[5] ?? '');

    if ($content['imageUrl'] === '') {
        fwrite(STDERR, "Missing <imageUrl> for image mode.\n");
        exit(1);
    }
} else {
    $content['message'] = (string)($argv[4] ?? '');

    if ($content['message'] === '') {
        fwrite(STDERR, "Missing \"<message>\" for text mode.\n");
        exit(1);
    }
}

$result = publishSocialPost($con, (int)$account['clientId'], $accountId, $platforms, $type, $content);

echo "Overall status: {$result['status']}\n";

if (!empty($result['message'])) {
    echo "Message: {$result['message']}\n";
}

foreach (($result['platforms'] ?? []) as $platform => $platformResult) {
    echo "\n" . strtoupper($platform) . ': ' . (!empty($platformResult['success']) ? 'SUCCESS' : 'FAILED') . "\n";
    echo '  postId: ' . ($platformResult['postId'] ?? 'n/a') . "\n";
    echo '  message: ' . (string)($platformResult['message'] ?? '') . "\n";
}

exit($result['status'] === 'failed' ? 1 : 0);
