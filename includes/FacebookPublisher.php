<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 5: Facebook Page Publishing
|--------------------------------------------------------------------------
| Kept separate from InstagramAutomation.php on purpose (per the Social
| Media Automation Platform roadmap) — Instagram and Facebook stay distinct
| publishers, unified later by a common scheduler.
|
| Reuses instagramGraphApiRequest() as the shared low-level Meta Graph API
| transport (curl + JSON decoding + error/diagnostic logging) rather than
| duplicating that plumbing — it is a generic Graph API HTTP wrapper, not
| Instagram business logic.
|
| $account must be the array shape returned by getInstagramAccountById():
| requires 'facebookPageId' (the connected account's linked Facebook Page)
| and 'accessToken', which is the Page Access Token obtained during OAuth
| (api/instagram/instagramOauthCallback.php stores $page['access_token'], not the
| user token) — no separate Facebook OAuth flow is needed to publish.
|
| Publishing to a Page requires the pages_manage_posts permission on that
| Page Access Token. As of this writing, the Facebook Login for Business
| Configuration's confirmed permission screens (see docs/
| INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md §6) only show "Read content
| posted on the Page" — pages_manage_posts is NOT confirmed granted. If
| Meta rejects a publish call with an OAuthException about missing
| permission, add pages_manage_posts to that Configuration in the Meta App
| Dashboard and reconnect the affected account (a new Page Access Token is
| required — the old one won't retroactively gain the new scope).
*/

function facebookPageAccountValid(array $account): bool
{
    return trim((string)($account['facebookPageId'] ?? '')) !== ''
        && trim((string)($account['accessToken'] ?? '')) !== '';
}

/**
 * Image post via POST /{page-id}/photos. Facebook publishes single-image
 * posts in one call (no create-container/publish split like Instagram).
 */
function publishFacebookImagePost(array $account, string $imageUrl, string $caption): array
{
    if (!facebookPageAccountValid($account)) {
        throw new RuntimeException('This Instagram account has no linked Facebook Page to publish to.');
    }

    $response = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['facebookPageId'] . '/photos',
        [
            'url' => $imageUrl,
            'caption' => $caption,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($response['post_id']) && empty($response['id'])) {
        throw new RuntimeException('Meta did not return a published Facebook post id.');
    }

    return ['facebookPostId' => (string)($response['post_id'] ?? $response['id'])];
}

/**
 * Text-only post via POST /{page-id}/feed.
 */
function publishFacebookTextPost(array $account, string $message): array
{
    if (!facebookPageAccountValid($account)) {
        throw new RuntimeException('This Instagram account has no linked Facebook Page to publish to.');
    }

    if (trim($message) === '') {
        throw new RuntimeException('A Facebook text post needs a non-empty message.');
    }

    $response = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['facebookPageId'] . '/feed',
        [
            'message' => $message,
            'access_token' => $account['accessToken'],
        ]
    );

    if (empty($response['id'])) {
        throw new RuntimeException('Meta did not return a published Facebook post id.');
    }

    return ['facebookPostId' => (string)$response['id']];
}
