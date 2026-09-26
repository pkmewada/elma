<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';
require_once __DIR__ . '/../includes/SocialPostEngine.php';
require_once __DIR__ . '/../includes/leadActivityLogger.php';

date_default_timezone_set('Asia/Kolkata');

function instagramSchedulerLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(__DIR__ . '/instagramScheduler.log', $line, FILE_APPEND);
    echo $line;
}

function handleInstagramAuthFailure(mysqli $con, array &$accountCache, array $account, Throwable $e): void
{
    if (!isInstagramAuthError($e)) {
        return;
    }

    disconnectInstagramAccount($con, $account['id']);
    // Invalidate the cache entry so any other due post sharing this account
    // in the same run correctly fails too, instead of reusing a stale
    // "connected" copy of it.
    $accountCache[$account['id']] = null;

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    instagramSchedulerLog(
        'Instagram account #' . $account['id'] . ' (Client: ' . $clientLabel . ') access token is invalid/expired — marked disconnected.'
    );
}

/**
 * Every post carries its own instagramAccountId — this loads (and caches
 * for the rest of the run) the specific account it must publish through.
 * A post is never published using "whichever account connected most
 * recently"; if its own account isn't connected/valid, the post fails
 * rather than silently falling back to a different client's account.
 */
function loadInstagramAccountForPost(mysqli $con, array &$accountCache, array $post): ?array
{
    $accountId = (int)($post['instagramAccountId'] ?? 0);

    if ($accountId <= 0) {
        return null;
    }

    if (array_key_exists($accountId, $accountCache)) {
        return $accountCache[$accountId];
    }

    return $accountCache[$accountId] = getInstagramAccountById($con, $accountId);
}

/*
|--------------------------------------------------------------------------
| Single-instance lock — prevents an overrunning invocation from overlapping
| with the next scheduled tick and double-processing/double-publishing posts.
|--------------------------------------------------------------------------
*/
$lockHandle = fopen(__DIR__ . '/.instagramScheduler.lock', 'c');

if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    instagramSchedulerLog('Another instagramScheduler run is already in progress. Exiting.');
    exit(0);
}

register_shutdown_function(static function () use ($lockHandle) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

$accountCache = [];

/*
|--------------------------------------------------------------------------
| Phase A: finalize Reels still processing at Meta (across all clients)
|--------------------------------------------------------------------------
| Explicitly scoped to mediaType = 'reel' (Phase 7) — image/carousel posts
| have their own recovery path (Phase A2 below), which must never overlap
| with this Reel-specific container-status check.
*/
$processingPosts = getSocialPostsByStatus($con, 'publishing', 20, 'reel');

foreach ($processingPosts as $post) {
    $postId = (int)$post['id'];
    $account = loadInstagramAccountForPost($con, $accountCache, $post);

    if (!$account) {
        markSocialPostFailed($con, $postId, 'Linked Instagram account is no longer connected or its token has expired.');
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed: linked account unavailable.');
        instagramSchedulerLog('Post #' . $postId . ' failed: linked Instagram account unavailable.');
        continue;
    }

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);

    try {
        $statusCode = getInstagramContainerStatus($account, (string)$post['instagramMediaId']);

        if ($statusCode === 'FINISHED') {
            $mediaId = publishInstagramContainer($account, (string)$post['instagramMediaId']);
            markSocialPostPublished($con, $postId, $mediaId);
            saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram video post via scheduler for Client: ' . $clientLabel . '.');
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (video). Media ID: ' . $mediaId);
        } elseif ($statusCode === 'ERROR' || $statusCode === 'EXPIRED') {
            markSocialPostFailed($con, $postId, 'Meta reported video processing status: ' . $statusCode);
            saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram video post failed at Meta for Client: ' . $clientLabel . ' (' . $statusCode . ').');
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') failed. Status: ' . $statusCode);
        } elseif ($statusCode === 'IN_PROGRESS') {
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') still processing at Meta (IN_PROGRESS). Will retry next run.');
        } else {
            // Unexpected status (e.g. PUBLISHED without our own record of
            // finishing the publish call) — don't retry forever; surface it.
            markSocialPostFailed($con, $postId, 'Unexpected Meta processing status: ' . $statusCode . '. Please verify manually on Instagram.');
            saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post has unexpected status for Client: ' . $clientLabel . ' (' . $statusCode . ').');
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') has unexpected status ' . $statusCode . '; marked failed for manual review.');
        }
    } catch (InstagramTransientApiException $e) {
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') status check failed transiently, will retry next run: ' . $e->getMessage());
    } catch (Throwable $e) {
        markSocialPostFailed($con, $postId, $e->getMessage());
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed for Client: ' . $clientLabel . ' (' . $e->getMessage() . ').');
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') failed: ' . $e->getMessage());
        handleInstagramAuthFailure($con, $accountCache, $account, $e);
    }
}

/*
|--------------------------------------------------------------------------
| Phase A2 (Phase 7): recover image/carousel posts stuck in 'publishing'
|--------------------------------------------------------------------------
| Only reachable if a previous run's process died mid-publish — an
| image/carousel post has no legitimate reason to still be 'publishing' on
| a later run otherwise (see getStuckSocialPosts()). Never re-publishes a
| platform whose success is already persisted (instagramMediaId /
| facebookPostId) — only the still-incomplete platform(s) are attempted.
|--------------------------------------------------------------------------
*/
$stuckPosts = getStuckSocialPosts($con, 20);

foreach ($stuckPosts as $post) {
    $postId = (int)$post['id'];
    $account = loadInstagramAccountForPost($con, $accountCache, $post);

    if (!$account) {
        markSocialPostFailed($con, $postId, 'Linked Instagram account is no longer connected or its token has expired.');
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed during recovery: linked account unavailable.');
        instagramSchedulerLog('Post #' . $postId . ' failed during recovery: linked Instagram account unavailable.');
        continue;
    }

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    $plan = socialScheduledRecoveryPlan($post);

    try {
        if ($plan['alreadyComplete']) {
            // Every selected platform already has a persisted result — the
            // crash happened after publishing finished but before the row
            // was finalized. No Meta API call needed here at all.
            finalizeSocialScheduledPost($con, $post, []);
            instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') recovered: all selected platform(s) already had a persisted result — finalized without a new Meta API call.');
            continue;
        }

        if ($post['mediaType'] === 'carousel') {
            // Carousel is always Instagram-only (Facebook is image-only,
            // enforced at save time) — recovery here is simply resuming the
            // single publish attempt, mirroring Phase B's carousel case.
            $mediaUrls = socialPostMediaAbsoluteUrls(decodeSocialPostMediaPaths($post['mediaUrl']));

            try {
                $result = publishInstagramCarouselPost($account, $mediaUrls, (string)$post['caption']);
                markSocialPostPublished($con, $postId, $result['instagramMediaId']);
                saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram carousel post via scheduler recovery for Client: ' . $clientLabel . '.');
                instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') recovered and published (carousel). Media ID: ' . $result['instagramMediaId']);
            } catch (InstagramTransientApiException $e) {
                instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') recovery publish failed transiently, will retry next run: ' . $e->getMessage());
            } catch (Throwable $e) {
                markSocialPostFailed($con, $postId, $e->getMessage());
                saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed during recovery for Client: ' . $clientLabel . ' (' . $e->getMessage() . ').');
                instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') recovery failed: ' . $e->getMessage());
                handleInstagramAuthFailure($con, $accountCache, $account, $e);
            }

            continue;
        }

        // Image post — attempt only the platform(s) not already done.
        $platformsToAttempt = [];

        if ($plan['instagramNeeded']) {
            $platformsToAttempt[] = 'instagram';
        }

        if ($plan['facebookNeeded']) {
            $platformsToAttempt[] = 'facebook';
        }

        if (empty($platformsToAttempt)) {
            // Defensive — shouldn't happen alongside alreadyComplete being
            // false, but never loop forever on an unrecognized state.
            finalizeSocialScheduledPost($con, $post, []);
            continue;
        }

        $mediaUrls = socialPostMediaAbsoluteUrls(decodeSocialPostMediaPaths($post['mediaUrl']));
        $result = publishSocialPost(
            $con,
            (int)$post['clientId'],
            (int)$post['instagramAccountId'],
            $platformsToAttempt,
            'image',
            ['imageUrl' => $mediaUrls[0] ?? '', 'caption' => (string)$post['caption']]
        );

        $resultPlatforms = normalizeSocialEngineResult($result, $platformsToAttempt);
        finalizeSocialScheduledPost($con, $post, $resultPlatforms);

        foreach ($resultPlatforms as $platform => $platformResult) {
            instagramSchedulerLog(
                'Post #' . $postId . ' (Client: ' . $clientLabel . ') recovery attempted ' . $platform . ': '
                . (!empty($platformResult['success'])
                    ? 'success (postId ' . ($platformResult['postId'] ?? '') . ')'
                    : ('failed' . (!empty($platformResult['transient']) ? ' transiently, will retry next run' : '') . ': ' . ($platformResult['message'] ?? '')))
            );
        }

        saveActivityLog(
            $con,
            'InstagramAutomation',
            $postId,
            'publish',
            'Scheduler recovery attempted remaining platform(s) for Client: ' . $clientLabel . ' (' . implode('+', $platformsToAttempt) . ').'
        );
    } catch (Throwable $e) {
        // Truly unexpected failure (not a Meta/transient error, which
        // publishSocialPost() already turns into a structured result) —
        // log only, leave the row's status untouched so it's retried next
        // run rather than guessing at a terminal state.
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') recovery hit an unexpected error, will retry next run: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| Phase B: publish newly due scheduled posts (across all clients)
|--------------------------------------------------------------------------
*/
$duePosts = getDueSocialPosts($con, 20);

foreach ($duePosts as $post) {
    $postId = (int)$post['id'];
    $account = loadInstagramAccountForPost($con, $accountCache, $post);

    if (!$account) {
        markSocialPostFailed($con, $postId, 'Linked Instagram account is no longer connected or its token has expired.');
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed: linked account unavailable.');
        instagramSchedulerLog('Post #' . $postId . ' failed: linked Instagram account unavailable.');
        continue;
    }

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    markSocialPostPublishing($con, $postId);

    try {
        $mediaUrls = socialPostMediaAbsoluteUrls(decodeSocialPostMediaPaths($post['mediaUrl']));
        $caption = (string)$post['caption'];

        switch ($post['mediaType']) {
            case 'carousel':
                $result = publishInstagramCarouselPost($account, $mediaUrls, $caption);
                markSocialPostPublished($con, $postId, $result['instagramMediaId']);
                saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram carousel post via scheduler for Client: ' . $clientLabel . '.');
                instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (carousel). Media ID: ' . $result['instagramMediaId']);
                break;

            case 'reel':
                $result = publishInstagramVideoPost($account, $mediaUrls[0] ?? '', $caption);

                if ($result['status'] === 'published') {
                    markSocialPostPublished($con, $postId, $result['instagramMediaId']);
                    saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram reel via scheduler for Client: ' . $clientLabel . '.');
                    instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (reel). Media ID: ' . $result['instagramMediaId']);
                } else {
                    updateInstagramPostContainerId($con, $postId, $result['instagramMediaId']);
                    instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') video container started (' . $result['instagramMediaId'] . '). Will finalize next run.');
                }
                break;

            case 'text':
                // Phase 10: Facebook text posts. Text is Facebook-only by
                // architecture (enforced at save time in
                // api/social-media/saveSocialPost.php) — always dispatched through the
                // Phase 6 Unified Social Post Engine, same as the
                // Facebook/dual-platform image branch below. No new
                // publishing logic — publishFacebookTextPost() (unchanged)
                // does the actual Meta API call.
                $textPlatforms = array_values(array_filter(array_map('trim', explode(',', (string)($post['platforms'] ?? 'facebook')))));

                $textResult = publishSocialPost($con, (int)$post['clientId'], (int)$post['instagramAccountId'], $textPlatforms, 'text', [
                    'message' => $caption,
                ]);

                $textResultPlatforms = normalizeSocialEngineResult($textResult, $textPlatforms);
                finalizeSocialScheduledPost($con, $post, $textResultPlatforms);

                foreach ($textResultPlatforms as $platform => $platformResult) {
                    instagramSchedulerLog(
                        'Post #' . $postId . ' (Client: ' . $clientLabel . ') ' . $platform . ': '
                        . (!empty($platformResult['success'])
                            ? 'published (postId ' . ($platformResult['postId'] ?? '') . ')'
                            : ('failed' . (!empty($platformResult['transient']) ? ' transiently, will retry next run' : '') . ': ' . ($platformResult['message'] ?? '')))
                    );
                }

                saveActivityLog(
                    $con,
                    'InstagramAutomation',
                    $postId,
                    'publish',
                    'Unified social text post processed via scheduler for Client: ' . $clientLabel . ' (' . implode('+', $textPlatforms) . ', overall: ' . $textResult['status'] . ').'
                );
                break;

            case 'image':
            default:
                $platforms = array_values(array_filter(array_map('trim', explode(',', (string)($post['platforms'] ?? 'instagram')))));

                if ($platforms === ['instagram'] || empty($platforms)) {
                    // Legacy path — byte-for-byte unchanged from before Phase 7.
                    // Every post created before Phase 7 (platforms defaults to
                    // 'instagram') always takes this exact branch.
                    $result = publishInstagramImagePost($account, $mediaUrls[0] ?? '', $caption);
                    markSocialPostPublished($con, $postId, $result['instagramMediaId']);
                    saveActivityLog($con, 'InstagramAutomation', $postId, 'publish', 'Published Instagram image post via scheduler for Client: ' . $clientLabel . '.');
                    instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') published (image). Media ID: ' . $result['instagramMediaId']);
                    break;
                }

                // Phase 7: Facebook-only or Instagram+Facebook — reuse the
                // Phase 6 Unified Social Post Engine rather than duplicating
                // platform publishing logic here.
                $unifiedResult = publishSocialPost($con, (int)$post['clientId'], (int)$post['instagramAccountId'], $platforms, 'image', [
                    'imageUrl' => $mediaUrls[0] ?? '',
                    'caption' => $caption,
                ]);

                $unifiedResultPlatforms = normalizeSocialEngineResult($unifiedResult, $platforms);
                finalizeSocialScheduledPost($con, $post, $unifiedResultPlatforms);

                foreach ($unifiedResultPlatforms as $platform => $platformResult) {
                    instagramSchedulerLog(
                        'Post #' . $postId . ' (Client: ' . $clientLabel . ') ' . $platform . ': '
                        . (!empty($platformResult['success'])
                            ? 'published (postId ' . ($platformResult['postId'] ?? '') . ')'
                            : ('failed' . (!empty($platformResult['transient']) ? ' transiently, will retry next run' : '') . ': ' . ($platformResult['message'] ?? '')))
                    );
                }

                saveActivityLog(
                    $con,
                    'InstagramAutomation',
                    $postId,
                    'publish',
                    'Unified social post processed via scheduler for Client: ' . $clientLabel . ' (' . implode('+', $platforms) . ', overall: ' . $unifiedResult['status'] . ').'
                );
                break;
        }
    } catch (InstagramTransientApiException $e) {
        // Network-level blip, not Meta rejecting the post — requeue rather
        // than permanently failing it.
        revertSocialPostToScheduled($con, $postId);
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') publish failed transiently, requeued for retry: ' . $e->getMessage());
    } catch (Throwable $e) {
        markSocialPostFailed($con, $postId, $e->getMessage());
        saveActivityLog($con, 'InstagramAutomation', $postId, 'publish_failed', 'Instagram post failed for Client: ' . $clientLabel . ' (' . $e->getMessage() . ').');
        instagramSchedulerLog('Post #' . $postId . ' (Client: ' . $clientLabel . ') failed: ' . $e->getMessage());
        handleInstagramAuthFailure($con, $accountCache, $account, $e);
    }
}

instagramSchedulerLog(
    'Scheduler run complete. Finalized ' . count($processingPosts) . ' in-flight, published ' . count($duePosts) . ' newly due.'
);
