<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstagramAutomation.php';
require_once __DIR__ . '/FacebookPublisher.php';

/*
|--------------------------------------------------------------------------
| Phase 6: Unified Social Post Engine
|--------------------------------------------------------------------------
| Orchestration only — no platform-specific Graph API logic lives here.
| Instagram publishing is delegated to the existing functions in
| InstagramAutomation.php (publishInstagramImagePost() etc.); Facebook
| publishing is delegated to FacebookPublisher.php. Both already route
| through the shared instagramGraphApiRequest() transport, so nothing here
| talks to Meta directly.
|
| This file is deliberately synchronous/stateless: publishSocialPost()
| takes a request, publishes to the requested platforms right away, and
| returns a structured per-platform result. It does not touch
| socialPosts or any other table — persistence (if any) is the caller's
| responsibility. This keeps the engine equally usable from a synchronous
| "Publish Now" API call today and from a future scheduler (Phase 7)
| without redesigning it — the same function signature works either way.
*/

const SOCIAL_POST_ENGINE_PLATFORMS = ['instagram', 'facebook'];
const SOCIAL_POST_ENGINE_TYPES = ['image', 'text'];

/**
 * $content keys used depending on $type:
 *   image => 'imageUrl' (required), 'caption' (optional)
 *   text  => 'message' (required) — falls back to 'caption' if 'message'
 *            is not provided, so callers that only collect a single
 *            caption field don't need to duplicate it under two keys.
 */
function publishSocialPost(mysqli $con, int $clientId, int $accountId, array $platforms, string $type, array $content): array
{
    $platforms = array_values(array_unique(array_map('strtolower', array_map('trim', $platforms))));
    $invalidPlatforms = array_diff($platforms, SOCIAL_POST_ENGINE_PLATFORMS);

    if (empty($platforms) || !empty($invalidPlatforms)) {
        return socialPostEngineFailure('Please select at least one valid platform (instagram, facebook).');
    }

    if (!in_array($type, SOCIAL_POST_ENGINE_TYPES, true)) {
        return socialPostEngineFailure('Invalid post type.');
    }

    if ($clientId <= 0 || !instagramClientExists($con, $clientId)) {
        return socialPostEngineFailure('Please select a valid client.');
    }

    // The same cross-client guard already used by api/saveSocialPost.php —
    // no second account/ownership lookup system introduced here.
    if ($accountId <= 0 || !instagramAccountBelongsToClient($con, $accountId, $clientId)) {
        return socialPostEngineFailure('The selected account does not belong to this client.');
    }

    $account = getInstagramAccountById($con, $accountId);

    if (!$account) {
        return socialPostEngineFailure('This account is not connected or has expired. Please reconnect it.');
    }

    $imageUrl = trim((string)($content['imageUrl'] ?? ''));
    $caption = (string)($content['caption'] ?? '');
    $message = trim((string)($content['message'] ?? '')) ?: trim($caption);

    if ($type === 'image' && $imageUrl === '') {
        return socialPostEngineFailure('An image URL is required for an image post.');
    }

    if ($type === 'text' && $message === '') {
        return socialPostEngineFailure('A message is required for a text post.');
    }

    if (in_array('facebook', $platforms, true) && !facebookPageAccountValid($account)) {
        return socialPostEngineFailure('This account has no linked Facebook Page to publish to.');
    }

    $platformResults = [];

    foreach ($platforms as $platform) {
        $platformResults[$platform] = socialPostEnginePublishOne($account, $platform, $type, $imageUrl, $caption, $message);
        socialPostEngineLog($clientId, $accountId, $platform, $type, $platformResults[$platform]);
    }

    return socialPostEngineFinalize($platformResults);
}

/**
 * Publishes to exactly one platform and normalizes its result. Never
 * throws — a Meta/transport error is caught here and turned into a
 * failed-but-structured result so one platform's failure can't take down
 * the other platform's result or the whole request.
 */
function socialPostEnginePublishOne(array $account, string $platform, string $type, string $imageUrl, string $caption, string $message): array
{
    try {
        if ($platform === 'instagram') {
            if ($type === 'text') {
                // Instagram has no normal text-only feed post — never call
                // a Meta endpoint for this, just report it as unsupported.
                return [
                    'success' => false,
                    'postId' => null,
                    'message' => 'Instagram does not support text-only feed posts.',
                    'unsupported' => true,
                ];
            }

            $result = publishInstagramImagePost($account, $imageUrl, $caption);

            return [
                'success' => true,
                'postId' => $result['instagramMediaId'] ?? null,
                'message' => 'Published successfully.',
            ];
        }

        if ($platform === 'facebook') {
            $result = $type === 'text'
                ? publishFacebookTextPost($account, $message)
                : publishFacebookImagePost($account, $imageUrl, $caption);

            return [
                'success' => true,
                'postId' => $result['facebookPostId'] ?? null,
                'message' => 'Published successfully.',
            ];
        }
    } catch (InstagramTransientApiException $e) {
        // Network-level blip talking to Meta, not Meta rejecting the post.
        // Flagged distinctly so a scheduled-post caller (Phase 7) can leave
        // this platform's persisted state untouched for a later retry
        // instead of recording a permanent failure — see
        // finalizeSocialScheduledPost() in InstagramAutomation.php. A
        // synchronous caller (Publish Now) can safely treat this the same
        // as any other failure since there is nothing to retry later there.
        return [
            'success' => false,
            'postId' => null,
            'message' => $e->getMessage(),
            'transient' => true,
        ];
    } catch (Throwable $e) {
        // $e->getMessage() here is always the sanitized Meta error message
        // (instagramGraphApiRequest() already strips access_token/
        // client_secret/code before it ever reaches an exception) — safe to
        // surface directly to the caller/UI.
        return [
            'success' => false,
            'postId' => null,
            'message' => $e->getMessage(),
        ];
    }

    return ['success' => false, 'postId' => null, 'message' => 'Unknown platform.'];
}

/**
 * Rolls per-platform results into one overall status. 'partial' is
 * returned whenever platforms disagree — the caller must never collapse
 * that into a plain "failed", since at least one platform actually
 * succeeded (see docs §22.7, Partial Success).
 */
function socialPostEngineFinalize(array $platformResults): array
{
    $successCount = 0;

    foreach ($platformResults as $result) {
        if (!empty($result['success'])) {
            $successCount++;
        }
    }

    $totalCount = count($platformResults);

    if ($successCount === 0) {
        $status = 'failed';
    } elseif ($successCount === $totalCount) {
        $status = 'success';
    } else {
        $status = 'partial';
    }

    return [
        'success' => $status === 'success',
        'status' => $status,
        'platforms' => $platformResults,
    ];
}

function socialPostEngineFailure(string $message): array
{
    return [
        'success' => false,
        'status' => 'failed',
        'message' => $message,
        'platforms' => [],
    ];
}

/**
 * Diagnostic-only log, mirroring instagramWriteApiDebugLog()'s
 * never-log-credentials convention — writes only account/client/platform/
 * type/outcome. $result['message'] is always the sanitized Meta error (see
 * socialPostEnginePublishOne()), never a token.
 */
function socialPostEngineLog(int $clientId, int $accountId, string $platform, string $type, array $result): void
{
    $logDir = dirname(__DIR__) . '/logs';

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    if (!is_dir($logDir)) {
        return;
    }

    $line = sprintf(
        '[%s] clientId=%d accountId=%d platform=%s type=%s success=%s postId=%s message=%s',
        date('Y-m-d H:i:s'),
        $clientId,
        $accountId,
        $platform,
        $type,
        !empty($result['success']) ? 'true' : 'false',
        $result['postId'] ?? 'n/a',
        (string)($result['message'] ?? '')
    );

    @file_put_contents($logDir . '/social-post-engine.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}
