<?php

/*
|--------------------------------------------------------------------------
| MANUAL TEST ONLY — NOT A CRON JOB.
|--------------------------------------------------------------------------
| Do not register this on Hostinger Cron. A test harness for Phase 7
| (Unified Scheduled Publishing) — lets you set up a real socialPosts
| row in a specific state and then run the REAL cron/instagramScheduler.php
| against it, instead of waiting hours for a real future schedule.
|
| Subcommands:
|
|   create <accountId> <platforms> <mediaType> <relativeMediaPath> [caption]
|     Creates a real, immediately-due (scheduledAt = NOW()) scheduled post
|     using the same saveSocialPost() function the real composer uses.
|     <platforms> is comma-separated: instagram | facebook | instagram,facebook
|     <relativeMediaPath> is relative to the project root, e.g.
|       uploads/instagram-posts/test.jpg
|     Prints the new post id. Run `php cron/instagramScheduler.php`
|     immediately after to exercise Phase B against it for real.
|
|   simulate-stuck <postId> <instagramDone:yes|no> <facebookDone:yes|no>
|     Forces an EXISTING post's row into status='publishing' with
|     instagramMediaId/facebookPostId set (a fake placeholder) or cleared to
|     match the requested done/not-done state — deterministically
|     reproducing a crash-mid-publish for Phase A2 recovery testing, without
|     needing an actual crash. Run `php cron/instagramScheduler.php`
|     immediately after: it must skip any platform marked "done" (no new
|     Meta call for it) and only attempt the platform(s) marked "not done".
|
|   show <postId>
|     Prints the row's platforms/status/instagramMediaId/facebookStatus/
|     facebookPostId/errorMessage/facebookErrorMessage. Never prints a
|     token — this table has none.
|
| Never prints an access token.
|--------------------------------------------------------------------------
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';

function printUsageAndExit(): void
{
    fwrite(STDERR, "Usage:\n"
        . "  php cron/testScheduledUnifiedSocialPost.php create <accountId> <platforms> <mediaType> <relativeMediaPath> [caption]\n"
        . "  php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> <instagramDone:yes|no> <facebookDone:yes|no>\n"
        . "  php cron/testScheduledUnifiedSocialPost.php show <postId>\n");
    exit(1);
}

$command = (string)($argv[1] ?? '');

if ($command === 'create') {
    $accountId = (int)($argv[2] ?? 0);
    $platformsInput = (string)($argv[3] ?? '');
    $mediaType = (string)($argv[4] ?? 'image');
    $relativeMediaPath = (string)($argv[5] ?? '');
    $caption = (string)($argv[6] ?? 'MODLUS Phase 7 scheduler test');

    $platforms = array_values(array_filter(array_map('trim', explode(',', $platformsInput))));

    if ($accountId <= 0 || empty($platforms) || $relativeMediaPath === '') {
        printUsageAndExit();
    }

    $account = getInstagramAccountById($con, $accountId);

    if (!$account) {
        fwrite(STDERR, "No connected Instagram account found with id {$accountId}.\n");
        exit(1);
    }

    if (in_array('facebook', $platforms, true) && $mediaType !== 'image') {
        fwrite(STDERR, "Facebook scheduling is only supported for image posts.\n");
        exit(1);
    }

    $relativeMediaPath = ltrim(str_replace(rtrim(BASE_URL, '/') . '/', '', $relativeMediaPath), '/');

    $postId = saveSocialPost($con, [
        'clientId' => (int)$account['clientId'],
        'instagramAccountId' => $accountId,
        'mediaType' => $mediaType,
        'mediaPaths' => [$relativeMediaPath],
        'caption' => $caption,
        'status' => 'scheduled',
        'scheduledAt' => date('Y-m-d H:i:s'),
        'platforms' => $platforms,
    ], 0);

    echo "Created post #{$postId} (platforms: " . implode('+', $platforms) . ", scheduledAt: now).\n";
    echo "Run: php cron/instagramScheduler.php\n";
    exit(0);
}

if ($command === 'simulate-stuck') {
    $postId = (int)($argv[2] ?? 0);
    $instagramDoneInput = strtolower((string)($argv[3] ?? ''));
    $facebookDoneInput = strtolower((string)($argv[4] ?? ''));

    if ($postId <= 0 || !in_array($instagramDoneInput, ['yes', 'no'], true) || !in_array($facebookDoneInput, ['yes', 'no'], true)) {
        printUsageAndExit();
    }

    $post = getSocialPostById($con, $postId);

    if (!$post) {
        fwrite(STDERR, "No post found with id {$postId}.\n");
        exit(1);
    }

    $instagramMediaId = $instagramDoneInput === 'yes' ? 'TEST_ALREADY_DONE_' . $postId : '';
    $facebookStatus = $facebookDoneInput === 'yes' ? 'published' : 'pending';
    $facebookPostId = $facebookDoneInput === 'yes' ? 'TEST_ALREADY_DONE_' . $postId : '';

    $stmt = mysqli_prepare(
        $con,
        "UPDATE socialPosts
         SET status = 'publishing', instagramMediaId = ?, facebookStatus = ?, facebookPostId = ?, errorMessage = '', facebookErrorMessage = NULL
         WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'sssi', $instagramMediaId, $facebookStatus, $facebookPostId, $postId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    echo "Post #{$postId} forced into status='publishing' (instagramDone={$instagramDoneInput}, facebookDone={$facebookDoneInput}).\n";
    echo "Run: php cron/instagramScheduler.php\n";
    exit(0);
}

if ($command === 'show') {
    $postId = (int)($argv[2] ?? 0);

    if ($postId <= 0) {
        printUsageAndExit();
    }

    $post = getSocialPostById($con, $postId);

    if (!$post) {
        fwrite(STDERR, "No post found with id {$postId}.\n");
        exit(1);
    }

    foreach (['id', 'clientId', 'instagramAccountId', 'mediaType', 'platforms', 'status', 'instagramMediaId', 'errorMessage', 'facebookStatus', 'facebookPostId', 'facebookErrorMessage', 'scheduledAt', 'publishedAt'] as $field) {
        echo str_pad($field, 22) . ': ' . (string)($post[$field] ?? '') . "\n";
    }

    exit(0);
}

printUsageAndExit();
