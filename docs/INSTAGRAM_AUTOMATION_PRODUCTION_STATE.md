# Instagram Automation — Production State (Source of Truth)

Last Updated: 2026-08-28 (Phase 10 — Facebook Text Post UI added)

> **CURRENT BASELINE IS PRODUCTION WORKING.**
>
> End-to-end image publishing (CRM → media upload → Meta Graph API →
> published Instagram post) is confirmed working in production as of this
> date. See §16-17 for the exact evidence. Read this document before
> touching Instagram OAuth, media publishing, Cron, token handling, or
> client/account mapping — see §22.

This document is the permanent record of **what has actually been verified
in production**, with dates, IDs, and log lines — as opposed to what the
code merely implements. For architecture/onboarding, see
`docs/instagram-automation-readme.md`. For internal implementation detail
(function-by-function reasoning, phase-by-phase change log), see
`docs/instagram-automation-flow.md`. For a pre-launch checklist template,
see `docs/instagram-automation-production-checklist.md`. This file does not
replace those — it records production-verified state and debugging history
that the other three don't carry.

> **API directory reorganization (2026-09-01)**: every `/api/*.php` file
> referenced throughout this project was moved from a flat `/api/` directory
> into module subfolders (e.g. `/api/instagramOauthCallback.php` →
> `/api/instagram/instagramOauthCallback.php`; LinkedIn/Pinterest/Google
> Business Profile similarly moved to `/api/linkedin/`, `/api/pinterest/`,
> `/api/google-business-profile/`). **Historical entries in this document
> below (confirmed production URLs, log lines, evidence quoted from a past
> date) are left exactly as originally recorded and describe the flat path
> that was actually in effect on that date** — they are not updated to the
> new path, since doing so would misrepresent what was literally observed
> at the time. Any *current* architecture statement elsewhere in this repo
> (code, comments, the smaller instagram-automation-*.md docs) reflects the
> new subfolder paths. If you are configuring a live OAuth redirect URI,
> webhook callback URL, or any other externally-facing endpoint today, use
> the current physical path (module subfolder), not a path quoted in a
> historical section below — external app dashboards (Meta, LinkedIn,
> Pinterest, Google Cloud) must have their registered redirect/callback
> URIs updated to match the new paths for any platform going live after
> this reorganization.

---

## 1. Project Overview

**Project**: MQlus Instagram Automation (module within Modlus CRM)

**Purpose**: Lets Modlus CRM manage Instagram Business accounts on behalf of
multiple clients from one admin panel — connect each client's Instagram
account via Meta OAuth, create and schedule posts (images, carousels,
reels), and publish them automatically via a cron-driven scheduler.

**Technology**:
- Core PHP, MySQL/MariaDB
- Hostinger hosting (shared, Apache/LiteSpeed behind Hostinger's `hcdn` CDN)
- Meta Graph API v19.0
- Facebook Login for Business
- Instagram Business Account (via a linked Facebook Page)
- Server-side Cron (Hostinger Cron → PHP CLI)

**Architecture rule**: the system is **client-scoped / multi-client**.
There is **no `companyId` architecture** anywhere in this module — every
Instagram account and post is tied to Modlus's existing `clientMaster`
entity via `clientId`. Never introduce `companyId`.

---

## 2. Current Production Architecture

```
Modlus CRM
    |
clientMaster                (existing Modlus entity — a converted lead)
    |
instagramAccounts           (one client can connect multiple IG accounts)
    |
instagramPosts               (every post belongs to one client + one of
                               that client's specific accounts)
```

**The one rule that matters most**: every Instagram object resolves to a
specific `instagramAccountId` first, and `clientId` is *derived from that
account* — never picked from session/UI state, never defaulted to
"whichever account connected most recently." See §8.

---

## 3. OAuth Architecture (Facebook Login for Business)

Flow:

```
CRM (admin selects a client)
  → Connect Instagram Account button
  → api/instagramOauthStart.php builds the Meta authorize URL
  → Facebook Login for Business dialog
  → Page selection → Business selection → Instagram Business Account selection
  → Meta redirects to api/instagramOauthCallback.php with an auth code
  → callback validates OAuth state, exchanges code for a token,
    resolves the linked Instagram Business Account, stores it
  → Instagram account connected to the selected CRM client
```

Confirmed production behavior:
- OAuth `state` is generated with `bin2hex(random_bytes(16))`, stored in
  `$_SESSION['instagramOauthState']`, and validated in the callback with
  `hash_equals()`. Session state is unset immediately after being read.
- The selected `clientId` is stored in `$_SESSION['instagramOauthClientId']`
  during the flow and validated against `clientMaster` in the callback
  before any account is saved.
- `config_id` (Facebook Login for Business Configuration ID) is sent
  **only** on the authorization request (`api/instagramOauthStart.php`) —
  **never** on the token-exchange call (`/oauth/access_token` in
  `api/instagramOauthCallback.php`).
- App Secret is never exposed in the browser or in the authorization URL.
- Access tokens are never exposed in the authorization URL — they're only
  ever handled server-side, inside `instagramGraphApiRequest()` calls.

---

## 4. Meta App / Configuration ID Setup

| Setting | Value |
| --- | --- |
| Production Meta App ID | `1091563173330460` |
| Production Facebook Login for Business Configuration ID | `1397228955807717` |
| Production redirect URI | `https://modlus.in/api/instagramOauthCallback.php` |
| Graph API version in use | `v19.0` (hardcoded per-call throughout `includes/InstagramAutomation.php`, `includes/InstagramInsights.php`, `includes/InstagramComments.php`, `api/instagramOauthStart.php`, `api/instagramOauthCallback.php` — **not centralized into a constant**. Do not change or centralize this unless explicitly requested — see §20.) |

Confirmed production authorization URL shape:

```
https://www.facebook.com/v19.0/dialog/oauth?
client_id=1091563173330460
&redirect_uri=https%3A%2F%2Fmodlus.in%2Fapi%2FinstagramOauthCallback.php
&state=...
&response_type=code
&config_id=1397228955807717
```

`metaConfigId` is stored in `instagramSettings` (added specifically for
Facebook Login for Business — see §7 and
`database/migrations/2026-08-26-instagram-oauth-config-id.sql`). When it's
set, `api/instagramOauthStart.php` uses `config_id` and omits `scope`
(permissions come from the Meta App Dashboard configuration instead). If
`metaConfigId` is empty, the code falls back to the legacy
`scope=instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement`
flow — this fallback exists for backward compatibility and is **not** the
production path currently in use.

---

## 5. Redirect URI

```
https://modlus.in/api/instagramOauthCallback.php
```

Stored as `instagramSettings.redirectUrl`, entered via the settings UI
(`pages/instagram-automation.php`). If left blank, the code falls back to
`BASE_URL . '/api/instagramOauthCallback.php'` — production has this set
explicitly rather than relying on the fallback.

---

## 6. Meta Permission Flow (Confirmed Screens)

The Meta permission/review screen successfully showed:
- Manage your business
- Access profile and posts from the selected Instagram account
- Upload media and create posts for the Instagram account
- Manage comments for the selected Instagram account
- Access insights for the Instagram account
- Read content posted on the Page
- Show a list of the Pages you manage

User selections during the confirmed test: **Current Pages only** →
**Specific Page** → **Current Instagram accounts only** → **Specific
Instagram Business Account**.

OAuth redirected back to the CRM with:
> "Instagram account connected successfully for Praveen Mewada (000001)."

---

## 7. Database Architecture

Core tables (all created/self-healed by `ensureInstagramSettingsTable()`,
`ensureInstagramAccountsTable()`, `ensureInstagramPostsTable()` in
`includes/InstagramAutomation.php`):

**`instagramSettings`** — one active row, platform-wide Meta credentials:
`metaAppId`, `metaAppSecret` (encrypted), `metaConfigId`, `redirectUrl`,
`webhookVerifyToken`, `isActive`, `createdBy`, `createdAt`, `updatedAt`.

**`instagramAccounts`** — one row per connected Instagram Business account:
`id`, `createdBy`, `clientId`, `instagramUserId`, `facebookPageId`,
`username`, `accessToken` (encrypted), `tokenExpiry`,
`lastAnalyticsSyncAt`, `lastAnalyticsSyncError`, `status`, `createdAt`,
`updatedAt`.

**`instagramPosts`** — one row per post: `id`, `createdBy`, `clientId`,
`instagramAccountId`, `mediaType`, `mediaUrl` (JSON array of relative
paths), `caption`, `status`, `scheduledAt`, `publishedAt`,
`instagramMediaId`, `errorMessage`, `createdAt`, `updatedAt`.

Relevant migrations: `database/migrations/2026-08-22-instagram-automation-tables.sql`,
`2026-08-22-instagram-posts-tables.sql`, `2026-08-22-instagram-multi-client.sql`,
`2026-08-22-instagram-phase3-tables.sql`, `2026-08-22-instagram-phase31-hardening.sql`,
`2026-08-26-instagram-oauth-config-id.sql`.

### Confirmed production `instagramAccounts` record

```
id = 1
createdBy = 6
clientId = 1
instagramUserId = 17841444393623973
facebookPageId = 336886156185070
username = gymlabzequipments
status = connected
```

The `accessToken` value is encrypted at rest (`includes/Crypto.php`,
AES-256-CBC) and is **not** recorded in this document.

---

## 8. Client → Instagram Account Relationship

- `instagramAccounts.clientId` ties every connected account to exactly one
  Modlus client.
- `instagramPosts.clientId` + `instagramPosts.instagramAccountId` tie every
  post to both a client and one specific one of that client's accounts.
- `instagramAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool`
  (`includes/InstagramAutomation.php`) is the guard that makes cross-client
  publishing impossible — `api/saveInstagramPost.php` calls it before
  saving any post.
- `getInstagramAccountById()` loads the **specific** account a post is tied
  to — this is deliberate: the module used to pick "whichever account
  connected most recently" (a real bug fixed in an earlier phase, "Phase
  2.5" per `instagram-automation-flow.md`). **Never reintroduce
  latest-account selection logic.**

---

## 9. Media Upload Architecture

- `saveInstagramMediaFile(array $file, string $mediaCategory = 'image'): array`
  and `saveInstagramMediaFiles(array $filesField, string $mediaCategory = 'image', int $maxFiles = 10): array`
  (`includes/InstagramAutomation.php`) validate real content-type (via
  `finfo`, not just the file extension — images must be real JPEG, videos
  must be real MP4/MOV), enforce size limits (8 MB image / 100 MB video),
  and move the upload into place.
- Files are stored under `uploads/instagram-posts/` (relative to the
  project root — on production, `public_html/uploads/instagram-posts/`).
- **The directory is created lazily** — `mkdir($uploadDir, 0755, true)`
  inside `saveInstagramMediaFile()` only runs the first time a real upload
  actually happens. There is no separate provisioning/install step for it.
- `instagramPosts.mediaUrl` stores relative paths as a JSON array, e.g.
  `["uploads/instagram-posts/ig_....jpg"]` (`encodeInstagramPostMediaPaths()`
  / `decodeInstagramPostMediaPaths()`).
- **Known gap (not yet fixed, diagnosed but not implemented)**: when
  editing/rescheduling/retrying an existing post **without** re-attaching a
  new file, `api/saveInstagramPost.php` carries forward the existing
  `mediaUrl` from the database with **no check that the physical file still
  exists on disk**. This was identified as the likely mechanism behind the
  Post #1 failure (§15) and remains unfixed as of this document. If you
  touch `api/saveInstagramPost.php`'s media-handling block, read that
  diagnosis first.

---

## 10. Public Media URL Architecture

- `instagramPostMediaAbsoluteUrls(array $relativePaths): array` converts
  stored relative paths into absolute URLs using `BASE_URL`.
- `BASE_URL` (`includes/config.php`) resolution, current logic (as of the
  2026-08-26 CLI fix):
  1. `MODLUS_BASE_URL` environment variable, if set (normalized, trailing
     slash stripped) — wins over everything else.
  2. Else, if `PHP_SAPI === 'cli'` (cron/CLI context) → hardcoded
     `https://modlus.in` (production fallback — CLI has no `HTTP_HOST` and
     no reliable web-facing `DOCUMENT_ROOT`).
  3. Else (normal web request) → original dynamic
     protocol + `HTTP_HOST` + filesystem-relative-basePath logic,
     unchanged from before this fix.
- Production public upload URL base: `https://modlus.in/uploads/...`
- **Historical bug (fixed)**: before the CLI branch was added, cron runs
  produced `BASE_URL = http://localhost/domains/modlus.in/public_html`,
  which Meta's Graph API cannot fetch media from. This was the root cause
  of the Post #1 failure's underlying trigger — see §15.
- The successful Post #2 publish (§16) confirms `BASE_URL` now resolves
  correctly to `https://modlus.in` under the actual production cron
  environment.

---

## 11. Cron Architecture

- Scheduler file: `cron/instagramScheduler.php` — CLI-only
  (`PHP_SAPI !== 'cli'` guard rejects HTTP hits).
- Configured on Hostinger Cron (external to this repo — not something this
  document can verify further than the log evidence in §17).
- On each run: finalizes any in-flight (`publishing` status, e.g. Reels
  awaiting Meta's async processing) posts, then publishes newly due
  (`status = 'scheduled'`, `scheduledAt <= NOW()`) posts.
- Logs via `instagramSchedulerLog()` — confirmed format:
  `Post #{id} (Client: {label}) published ({type}). Media ID: {id}` and
  `Scheduler run complete. Finalized {n} in-flight, published {n} newly due.`
- **Confirmed working in production** — see §17 for the exact log line.

---

## 12. Publishing Flow

```
CRM: Instagram Post creation (pages/instagram-create-post.php)
  → media upload (saveInstagramMediaFiles())
  → media stored under uploads/instagram-posts/
  → public HTTPS media URL (via BASE_URL)
  → post saved to instagramPosts (api/saveInstagramPost.php → saveInstagramPost())
  → Hostinger Cron executes cron/instagramScheduler.php
  → scheduler detects due post (getDueInstagramPosts())
  → Meta Graph API media container created (POST /{instagramUserId}/media
    with image_url/video_url + caption + access_token)
  → container published (POST /{instagramUserId}/media_publish with
    creation_id + access_token) via publishInstagramContainer()
  → Instagram returns a Media ID
  → CRM marks the post as published (status = 'published', publishedAt,
    instagramMediaId recorded)
```

Publishing functions in `includes/InstagramAutomation.php`:
- `publishInstagramImagePost(array $account, string $mediaUrl, string $caption): array`
- `publishInstagramCarouselPost(array $account, array $mediaUrls, string $caption): array`
- `publishInstagramVideoPost(array $account, string $videoUrl, string $caption): array`
  — Reels are asynchronous; this function starts the container and returns
  a `pending` status if not immediately `FINISHED`. Finalization happens on
  a later scheduler run via `getInstagramContainerStatus()` +
  `publishInstagramContainer()`.

**Image publishing is production-confirmed (§16-17). Carousel and Reel
publishing are implemented in code but not yet production-verified — see
§19.**

---

## 13. Important Files and Responsibilities

| File | Responsibility |
| --- | --- |
| `includes/InstagramAutomation.php` | Core domain logic: settings, accounts, posts, media upload/validation, Graph API request wrapper (`instagramGraphApiRequest()`), publishing functions, diagnostic logging helpers. |
| `api/instagramOauthStart.php` | Builds and redirects to the Meta authorize URL (`config_id`-based, with legacy `scope` fallback). Validates the selected client and that Meta credentials exist first. |
| `api/instagramOauthCallback.php` | OAuth callback: validates state (`hash_equals()`) and client, exchanges the auth code for a token, attempts long-lived token exchange, resolves Facebook Pages → linked Instagram Business Account → username, saves the account, logs the connection, redirects with a status message. Exception details are logged server-side only (`error_log()`); the browser only ever sees a generic error message. |
| `api/saveInstagramSettings.php` | Persists `instagramSettings` (Meta App ID/Secret, Configuration ID, Redirect URL) via `saveInstagramSettings()`. CSRF-protected. |
| `api/saveInstagramPost.php` | Post create/update endpoint: validates client + account ownership (`instagramAccountBelongsToClient()`), handles media upload via `saveInstagramMediaFiles()`, calls `saveInstagramPost()` to insert/update `instagramPosts`. |
| `cron/instagramScheduler.php` | CLI-only scheduler — the only thing that actually calls Meta to publish. Finalizes in-flight posts, publishes newly due posts, logs every outcome. |
| `pages/instagram-automation.php` | Settings UI: Meta App ID/Secret/Configuration ID/Redirect URL form, "Connect Instagram Account" button, connected-accounts list per client. |
| `pages/instagram-scheduled-posts.php` | Lists all posts (draft/scheduled/publishing/published/failed) with filter, edit, delete, "View Error" for failed posts. |
| `database/migrations/2026-08-26-instagram-oauth-config-id.sql` | Adds `instagramSettings.metaConfigId` for Facebook Login for Business. |

(Full file map, including Phase 3 analytics/comments/webhooks files not
listed above, is in `docs/instagram-automation-flow.md` §2 — not
duplicated here.)

---

## 14. Error Logging Architecture

- `instagramGraphApiRequest()` (`includes/InstagramAutomation.php`) logs a
  complete diagnostic entry on every Meta API error, invalid-JSON response,
  or network/cURL failure — **not** on success.
- Logged fields: request URL, HTTP method, HTTP status
  (`curl_getinfo($ch, CURLINFO_HTTP_CODE)`), sanitized request parameters,
  and the complete Meta error object (`message`, `code`, `error_subcode`,
  `error_user_title`, `error_user_msg`, `fbtrace_id` — whatever Meta
  actually returned, via `json_encode()`).
- Sanitization (`instagramSanitizeParamsForLog()`): redacts `access_token`,
  `client_secret`, `code`, `fb_exchange_token` as `[REDACTED]`. `image_url`
  / `video_url` are deliberately **not** redacted (needed for media-fetch
  debugging).
- Written to two places: `error_log()` (PHP's own error log — unreliable to
  locate on this Hostinger deployment) **and** a dedicated file,
  `logs/instagram-api.log` (project root, via `instagramWriteApiDebugLog()`),
  added specifically because the Hostinger PHP error log couldn't be
  located during the Post #1 investigation. `logs/.htaccess` denies direct
  web access to the whole `logs/` directory (`Require all denied` /
  `Deny from all` fallback) — verify this is actually enforced on Hostinger
  if you rely on it (Apache/`AllowOverride` dependent, not independently
  confirmed from this environment).
- `api/instagramOauthCallback.php`'s final `catch (Throwable $e)` block
  logs the real exception via `error_log()` but returns a generic
  browser-facing message: "Instagram connection failed. Please try again or
  contact the administrator." No exception message, token, or secret is
  ever sent to the browser.

**This logging is explicitly marked temporary/diagnostic** in its own code
comments (added to debug Post #1) — it is safe to keep running
indefinitely (it never logs on success, so it has no ongoing cost), but if
it's ever removed, remove it deliberately, not accidentally.

---

## 15. Previous Post #1 Failure and What Was Learned

**Symptom**: Post #1 failed with Meta error:
```
code: 9004
error_subcode: 2207052
message: "Only photo or video can be accepted as media type."
error_user_title: "Media download has failed. The media URI doesn't meet our requirements."
error_user_msg: "The media could not be fetched from this URI: https://modlus.in/uploads/instagram-posts/ig_6a8eb32f626384.71442019_1787736879.jpg"
```

**Investigation timeline**:
1. Added diagnostic logging to `instagramGraphApiRequest()` (§14) to
   capture the full Meta error instead of just the short message.
2. Discovered `BASE_URL` was resolving to
   `http://localhost/domains/modlus.in/public_html` under cron/CLI
   execution — a real, confirmed bug (§10) — and fixed it in
   `includes/config.php` with a `PHP_SAPI === 'cli'` production fallback to
   `https://modlus.in`.
3. After that fix, direct `curl` testing of the *old* Post #1 media URL
   still showed **intermittent** behavior — sometimes a valid 200/JPEG,
   sometimes a 404 generated by Modlus's own PHP router (`Route "..." is
   not configured.`), across identical repeated requests, with **no**
   evidence of WAF/hotlink/UA-based blocking (same pattern with and without
   a `facebookexternalhit` User-Agent).
4. Direct inspection confirmed `public_html/uploads/instagram-posts/` did
   **not exist** on production filesystem at that point, despite the
   `instagramPosts` row referencing a file inside it.
5. Code review of `api/saveInstagramPost.php` found the mechanism that
   allows a DB row to reference a non-existent file: when editing/retrying
   a post **without** re-attaching a file, the existing `mediaUrl` is
   carried forward from the database with no check that the file still
   exists on disk (§9, "Known gap").

**Resolution taken**: Post #1 was **deleted**, not repaired — a completely
new post (Post #2) was created from scratch with a fresh upload. The fresh
upload correctly created `uploads/instagram-posts/` and its file, and Meta
successfully fetched and published it (§16). **The §9 "Known gap" itself
was diagnosed but has not been code-fixed** — it remains a standing risk
for any future post that gets edited/retried without a fresh upload.

**Do not treat the Post #1 failure as evidence that the current publishing
implementation is broken** — the fix (BASE_URL) and the workaround (fresh
post) are both confirmed effective by Post #2. Do treat the §9 gap as
real, open, unfixed technical debt.

---

## 16. Successful Post #2 Production Test

- **Post**: #2
- **Client**: Praveen Mewada (000001)
- **Instagram account**: `gymlabzequipments` (`instagramAccounts.id = 1`,
  `instagramUserId = 17841444393623973`)
- **Media type**: image
- **Result**: Published successfully. Visually confirmed as actually live
  on the connected Instagram account (confirmed by the user, not just by
  the API response).
- **Published Media ID**: `18007863326766159`

---

## 17. Exact Successful Scheduler Log

```
[2026-08-26 18:21:09] Post #2 (Client: Praveen Mewada (000001)) published (image). Media ID: 18007863326766159
[2026-08-26 18:21:09] Scheduler run complete. Finalized 0 in-flight, published 1 newly due.
```

This confirms: Hostinger Cron executed `cron/instagramScheduler.php`, the
scheduler correctly identified Post #2 as due, published it through Meta's
Graph API, and logged the outcome in the documented format (§11, §14).

---

## 18. Current Production Verification Checklist

Confirmed:

- [x] Meta Developer App configured
- [x] Facebook Login for Business configured
- [x] Configuration ID implemented
- [x] OAuth authorization URL generated correctly
- [x] Meta permission flow completed
- [x] Page selected successfully
- [x] Business selected successfully
- [x] Instagram Business Account selected successfully
- [x] OAuth callback successful
- [x] Instagram account stored in database
- [x] Client-to-Instagram account relationship working
- [x] Access token stored encrypted
- [x] Instagram post creation working
- [x] Media upload working
- [x] `uploads/instagram-posts/` created successfully
- [x] Public media URL working for fresh upload
- [x] Cron executing
- [x] Scheduler detecting scheduled posts
- [x] Meta image container creation working
- [x] Instagram `media_publish` working
- [x] Instagram image actually published
- [x] Published Media ID returned
- [x] CRM marks post as published
- [x] Production end-to-end image publishing confirmed

---

## 19. Features Implemented But Not Yet Production-Verified

These exist in code (`includes/InstagramAutomation.php`,
`includes/InstagramInsights.php`, `includes/InstagramComments.php`,
`includes/InstagramWebhooks.php` and related `api/`/`cron/` files per
`docs/instagram-automation-flow.md`), but have **no recorded production
evidence** of a successful run as of this document. Do not mark these as
"production verified" without the same kind of evidence as §16-17
(specific IDs, logs, visual confirmation):

- Carousel publishing (`publishInstagramCarouselPost()`)
- Reel/video publishing (`publishInstagramVideoPost()`, async container
  finalization)
- Automatic analytics synchronization (`cron/instagramAnalyticsSync.php`)
- Comment management (`includes/InstagramComments.php`,
  `api/replyInstagramComment.php`, `api/hideInstagramComment.php`)
- Webhook event processing (`api/instagramWebhook.php`,
  `includes/InstagramWebhooks.php`)
- Multiple simultaneous client publishing (only one client/account has
  been exercised so far)
- Token expiration/reconnection behavior
- Production retry behavior after a Meta API failure (Post #1 was deleted
  and recreated, not retried in place)

"Implemented" ≠ "production verified." Keep that distinction when reporting
status.

---

## 20. Rules for Future Development

1. **Read this document first** before modifying Instagram OAuth, media
   publishing, Cron, token handling, or client/account mapping.
2. Inspect the current implementation before assuming anything — code may
   have moved on since this document was last updated.
3. Do not reimplement already-working functionality (§18).
4. Do not assume previously-fixed bugs (§15, BASE_URL) still exist without
   checking.
5. Do not remove working logic without evidence it's actually wrong.
6. Make minimum targeted changes — this module has a documented history of
   being touched by narrowly-scoped, single-file changes (OAuth config_id,
   BASE_URL CLI fix, diagnostic logging); keep that pattern.
7. Preserve the client-scoped architecture (§8) — every account and post
   resolves through `clientId` + `instagramAccountId`, never "latest
   account."
8. **Never introduce `companyId`.**
9. Never expose access tokens or App Secrets — not in browser output, not
   in logs, not in URLs.
10. After changes, run `php -l` on every touched file and document exactly
    what changed (files, functions, behavior) — the pattern used throughout
    this module's recent history.
11. The Graph API version (`v19.0`) is hardcoded in ~15 places across 5
    files, not centralized. Do not change or centralize it unless
    explicitly requested (§4).
12. The §9 "Known gap" (stale `mediaUrl` on edit/retry without re-upload)
    is real, diagnosed, and unfixed — read §9 and §15 before touching
    `api/saveInstagramPost.php`'s media-handling block.

---

## 21. Troubleshooting Notes

- **Meta error 9004 / `error_subcode 2207052`** ("Only photo or video can
  be accepted as media type" / "Media download has failed"): means Meta's
  crawler could not fetch the `image_url`/`video_url` you sent it. Check,
  in order: (a) is `BASE_URL` actually resolving to `https://modlus.in` in
  the context that generated the URL (§10); (b) does the file actually
  exist on disk at that path right now — `curl -I` the exact URL a few
  times in a row, since Hostinger's infrastructure showed intermittent
  200/404 behavior for the same file during the Post #1 investigation
  (§15); (c) is the `instagramPosts.mediaUrl` value stale from a
  carried-forward edit (§9).
- **Diagnostic logs**: check `logs/instagram-api.log` (project root) first
  — it has the complete, unredacted (except credentials) Meta error object,
  not just the short exception message the UI shows.
- **Can't find `logs/instagram-api.log` or PHP's own error log on
  Hostinger**: this was the exact problem that led to adding the dedicated
  log file (§14) — use that file instead of hunting for the server's
  native PHP error log.
- **A post's `mediaUrl` points at a missing file**: this is the known,
  unfixed gap in §9. The immediate workaround used for Post #1 was to
  delete the post and create a fresh one with a new upload, rather than
  editing/retrying in place.

---

## 22.5. Phase 4 (Social Media Automation roadmap) — Dashboard Analytics Integration

**Date**: 2026-08-27. This work is part of a broader "Modlus Social Media
Automation Platform" roadmap (Instagram → Facebook → LinkedIn, unified
scheduler, social inbox). Its Phase 4 asked for Instagram analytics
(`instagramAnalytics` table, `includes/InstagramAnalytics.php`,
`cron/instagramAnalyticsScheduler.php`, `api/getInstagramAnalytics.php`,
CRM dashboard cards).

**Investigation finding**: that analytics pipeline already existed, built
earlier under the name "Phase 3: Analytics" — `instagramInsights` table,
`includes/InstagramInsights.php`, `cron/instagramAnalyticsSync.php`,
`api/getInstagramInsights.php`, and a dedicated `pages/instagram-analytics.php`
page with stat cards. Per the "do not rebuild working features" rule, that
existing pipeline was **extended in place** rather than duplicated under new
names. If a future task references `instagramAnalytics`/
`InstagramAnalytics.php`/`instagramAnalyticsScheduler.php` by those exact
names, know that they don't exist — the equivalent is the `instagramInsights`
stack described in §7 and above.

**What was actually added** (additive only — no existing function, table, or
file behavior was changed):

1. `fetchInstagramAccountInsights()` (`includes/InstagramInsights.php`) now
   also requests `website_clicks` and `total_interactions` alongside the
   existing `reach`/`profile_views` account-level metrics. Routed through
   the existing `fetchInstagramInsightsResilient()` fallback, so an account
   where Meta doesn't support one of these metrics simply won't get that
   metric — no error, no fabricated value.
2. New `getInstagramDashboardSummary(mysqli $con): array` (`includes/InstagramInsights.php`)
   — an **admin-wide, read-only rollup** (connected account count, sum of
   each connected account's latest `followers_count`, sum of today's
   `reach`/`total_interactions` across all accounts). This is the one
   function in the Instagram module that is deliberately **not**
   client-scoped, because it only aggregates already-client-scoped rows for
   a summary display — it writes nothing and every underlying row still
   carries its own `clientId`/`instagramAccountId`.
3. New `api/getInstagramDashboardSummary.php` — thin wrapper exposing the
   above, auth-gated like every other Instagram API endpoint.
4. `pages/dashboard.php` — new "Instagram Overview" card row (connected
   accounts, total followers, today's reach, today's interactions), hidden
   by default and only shown once `connectedAccounts > 0` (so an install
   with no Instagram accounts connected yet sees no empty/zero widget).
   Populated client-side via `fetch()` against the new API on page load.

**Still not production-verified** (unchanged from §19): the underlying
`cron/instagramAnalyticsSync.php` sync itself has no recorded evidence of a
successful real-data run against Meta, and it's unconfirmed whether it's
actually registered in Hostinger's cron alongside `instagramScheduler.php`.
The new `website_clicks`/`total_interactions` metrics and the dashboard
widget inherit that same "implemented, not yet verified" status until a real
sync run and a real dashboard load are confirmed in production.

**Verified during this change**: `php -l` clean on all three touched/created
files; `getInstagramDashboardSummary()` executed successfully against the
local dev database (returned all-zero summary, consistent with no connected
accounts in that database — no SQL errors, table auto-created as expected).

---

## 22.6. Phase 5 (Social Media Automation roadmap) — Facebook Page Publishing Adapter

**Date**: 2026-08-27. Phase 4 (§22.5) was confirmed production-verified by
the user (real Meta insights synced: `followers_count: 1212`,
`media_count: 53`, `reach: 17`) before this phase started.

**Audit findings before writing any code:**

1. **The Page Access Token already exists per account.** `api/instagramOauthCallback.php`
   (lines ~85-113) calls `/me/accounts`, and stores **`$page['access_token']`**
   (a Page Access Token) — not the user token — into `instagramAccounts.accessToken`,
   alongside `facebookPageId`. So no new OAuth flow was needed for Facebook
   publishing; the credential material was already there, just unused for
   this purpose. Do not change what gets stored here without checking every
   existing caller of `getInstagramAccountById()`/`accessToken` first.
2. **Permission scope is unconfirmed.** §6's confirmed permission screens
   list "Read content posted on the Page" — there is **no confirmed grant
   of `pages_manage_posts`**, which Meta requires to create Page posts
   (`/{page-id}/photos`, `/{page-id}/feed`). Neither the `config_id`-based
   flow (permissions come from the Meta Dashboard configuration, opaque to
   this repo) nor the legacy `scope=` fallback in `api/instagramOauthStart.php`
   requests it. **This was flagged to the user and left unresolved** — it's
   an external Meta App Dashboard change, not something fixable from code.
   If a publish call fails with an OAuthException about missing permission:
   add `pages_manage_posts` to Configuration ID `1397228955807717` in the
   Meta App Dashboard, then **reconnect** the affected account(s) — existing
   stored tokens will not retroactively gain the new scope.
3. **`getInstagramAccountById()` was missing `facebookPageId` in its return
   array** even though the DB row has always had the column (used internally
   in `saveInstagramAccountFromOAuth()`/`getInstagramAccounts()` already).
   Fixed additively — added one key to the returned array
   (`includes/InstagramAutomation.php`, `getInstagramAccountById()`). No
   existing caller's behavior changes; they simply never read that key
   before.

**What was built:**

- `includes/FacebookPublisher.php` — new, separate from
  `InstagramAutomation.php` per the roadmap's "keep publishers separate"
  rule:
  - `publishFacebookImagePost(array $account, string $imageUrl, string $caption): array`
    → `POST /{facebookPageId}/photos`
  - `publishFacebookTextPost(array $account, string $message): array`
    → `POST /{facebookPageId}/feed`
  - Both reuse the existing `instagramGraphApiRequest()` transport (curl +
    JSON decode + Meta error handling + diagnostic logging) rather than
    duplicating that plumbing — it's a generic Graph API HTTP wrapper, not
    Instagram-specific business logic, already shared across
    Insights/Comments/Webhooks.
  - `$account` is exactly the array `getInstagramAccountById()` returns —
    same account-lookup path Instagram publishing already uses, same
    client-scoping guarantees (§8) apply unchanged.
- `cron/testFacebookPublish.php` — **manual CLI test tool, not a cron job**.
  Do not register it on Hostinger Cron. Lets an admin manually verify a
  real publish against one already-connected account:
  `php cron/testFacebookPublish.php <instagramAccountId> image <imageUrl> [caption]`
  or `... <instagramAccountId> text "<message>"`.

**Not yet done (deferred to later phases per the roadmap):** no
`socialPosts`/scheduler/UI wiring — Phase 5 is adapter-only, matching the
roadmap's own phase boundary ("Both should use common scheduling
architecture later" = Phase 6/7).

**Verified during this change:** `php -l` clean on all three touched/created
files (`includes/InstagramAutomation.php`, `includes/FacebookPublisher.php`,
`cron/testFacebookPublish.php`). `cron/testFacebookPublish.php` exercised
locally with an invalid account id and a missing-argument case — both fail
cleanly with a clear message and exit code 1, no fatals. **Not yet
exercised against a real Facebook Page** — that requires the user to run it
against a real connected account in production, and depends on the
`pages_manage_posts` permission question above being resolved first.

---

## 22.7. Phase 6 — Unified Social Post Engine

**Date**: 2026-08-28. Phase 5 (§22.6) was confirmed production-verified by
the user before this phase started — real Facebook post id
`336886156185070_122207178524467606`, published via
`cron/testFacebookPublish.php` against Page `336886156185070`
(Gym Labz Equipments), proving `pages_manage_posts` now works after the
Meta Login Configuration update.

**Objective**: one publishing entry point that can send the same post to
Instagram only, Facebook only, or both, with independent per-platform
results and correct partial-success reporting — without touching Instagram
publishing, Facebook publishing, OAuth, or `cron/instagramScheduler.php`.

### Architecture decision made with the user before coding

Nothing in Modlus publishes synchronously today — even Instagram-only
posts don't publish on "Schedule Post" click; that only saves a DB row,
and `cron/instagramScheduler.php` (batch, async) publishes it later. Two
options existed to get an immediate "Publish → see both results now" UX:
extend the scheduler, or add a new synchronous path alongside it. The user
explicitly chose **a new synchronous "Publish Now" action**, and explicitly
required `cron/instagramScheduler.php` to stay completely untouched in this
phase. That is what was built — the existing Draft/Schedule/cron pipeline
was not modified in any way.

### What was built

**`includes/SocialPostEngine.php`** (new) — the Unified Social Post Engine.
Pure orchestration, no Meta API code of its own:

- `publishSocialPost(mysqli $con, int $clientId, int $accountId, array $platforms, string $type, array $content): array`
  is the single entry point. `$platforms` is a subset of `['instagram', 'facebook']`;
  `$type` is `'image'` or `'text'`; `$content` carries `imageUrl`/`caption`
  (image) or `message` (text, falls back to `caption` if not given).
- Validates, in order: platform list, post type, `instagramClientExists()`,
  `instagramAccountBelongsToClient()` (the exact same cross-client guard
  `api/saveInstagramPost.php` already uses — no second account/ownership
  system), `getInstagramAccountById()` (the exact same account lookup and
  decryption path everything else uses — no second token/account lookup
  system), then per-type content requirements, then
  `facebookPageAccountValid()` when Facebook is requested. Every validation
  failure returns a structured `['success' => false, 'status' => 'failed', 'message' => ..., 'platforms' => []]`
  before any Meta API call is made — nothing here can throw an uncaught
  exception to a caller.
- `socialPostEnginePublishOne()` dispatches exactly one platform:
  - Instagram + image → calls the **existing**
    `publishInstagramImagePost()` (`includes/InstagramAutomation.php`,
    unchanged) — the same container-create-then-publish flow already
    production-verified in Phase 2/§16-17.
  - Instagram + text → returns a structured unsupported result
    (`'unsupported' => true`) and **never calls any Meta endpoint** —
    Instagram has no normal text-only feed post.
  - Facebook + image → calls the **existing** `publishFacebookImagePost()`.
  - Facebook + text → calls the **existing** `publishFacebookTextPost()`.
  - Any Meta/transport exception is caught here and turned into a
    structured `{success:false, message: <sanitized Meta error>}` — one
    platform's failure can never take down the other platform's call or
    throw a fatal to the UI. `instagramGraphApiRequest()` (unchanged,
    reused by both publishers) already redacts `access_token`/
    `client_secret`/`code` before an exception is ever raised, so the
    message surfaced here is always safe to show/log.
  - `socialPostEngineFinalize()` computes overall `status`: `'success'`
    (all requested platforms succeeded), `'partial'` (some succeeded, some
    failed — **never** collapsed into a plain failure), or `'failed'`
    (none succeeded). `success` (boolean) is `true` only for `'success'`;
    callers must read `status`/`platforms` to distinguish `'partial'` from
    `'failed'`.
  - `socialPostEngineLog()` — new diagnostic log,
    `logs/social-post-engine.log`, one line per platform attempt:
    timestamp, clientId, accountId, platform, type, success, postId,
    sanitized message. Never writes `accessToken` — the logged `message`
    is always the same sanitized string returned to the caller, and the
    token itself is never passed into this function at all (only the
    `$account` array's non-secret fields are implied by context, and even
    those aren't logged — only the ids).

No duplicate Graph API transport, no duplicate token decryption, no
duplicate account lookup, no duplicate Instagram/Facebook publishing logic
— every actual Meta API call in this phase happens inside
`InstagramAutomation.php` or `FacebookPublisher.php`, unchanged.

**`api/publishSocialPostNow.php`** (new) — the synchronous "Publish Now"
endpoint. Auth + CSRF gated like every other Instagram API. Validates
client/account ownership (same two checks as `api/saveInstagramPost.php`),
uploads media via the **existing** `saveInstagramMediaFiles()` into the
**existing** `uploads/instagram-posts/` directory (no new upload path), then
calls `publishSocialPost()`. Reports `success => ($status !== 'failed')` at
the HTTP layer specifically so a `'partial'` result reaches the UI as
partial (with both platform results rendered), never as a blanket
"Publishing failed" when one platform actually succeeded. Writes one
`saveActivityLog()` entry (existing audit log table), never the token.

**`pages/instagram-create-post.php`** (extended, additively) — the
**existing** Instagram composer, not a second composer. Added:
- "Post To" checkboxes (Instagram, Facebook). Facebook is disabled/unchecked
  automatically when the selected account has no `facebookPageId`
  (`getInstagramSettings.php` already returns that field per account — no
  new API needed).
- A "Publish Now" button, enabled only when Post Type is "Image Post"
  (reels/carousels still use the existing "Schedule Post" + cron flow,
  completely untouched).
- A results panel rendering each requested platform's outcome
  independently (✓/✕, message, post id) — the exact "never show only a
  generic failure when one platform succeeded" requirement.
- The existing Draft/Schedule form, its validation, its file input, and its
  submission to `api/saveInstagramPost.php` are all unchanged; "Publish
  Now" reads the same form fields via `FormData` and posts to the new
  endpoint instead — it does not touch or reuse `saveInstagramPost()`.

**`cron/testUnifiedSocialPost.php`** (new) — **MANUAL TEST ONLY, NOT A
CRON JOB.** Do not register it on Hostinger Cron. Calls
`publishSocialPost()` directly against one real connected account:
```
php cron/testUnifiedSocialPost.php <accountId> <platforms> image <imageUrl> [caption]
php cron/testUnifiedSocialPost.php <accountId> <platforms> text "<message>"
```
`<platforms>` is comma-separated (`instagram`, `facebook`, or
`instagram,facebook`). Never prints the access token.

### Database

**No schema changes.** `publishSocialPost()`/`api/publishSocialPostNow.php`
do not read or write `instagramPosts` (or any other table) — "Publish Now"
is an ephemeral, immediate action, not a scheduled post, so there is
nothing to persist for it yet. Extending `instagramPosts` with
`platforms`/`facebookPostId`/`facebookStatus` columns (to give scheduled
posts the same dual-platform capability) is deferred to the phase that
unifies scheduling — doing it now would add columns with no code path
using them yet, which the project's own DB rule explicitly warns against
("do not create unnecessary tables/columns... do not modify the database
just because it seems convenient").

### Client isolation

Unchanged mechanism, reused exactly: `instagramAccountBelongsToClient()`
and `instagramClientExists()` (both pre-existing, both from
`InstagramAutomation.php`) are the only guards, called once each, before
any account is loaded or any platform is published to. No second
client/account validation path was created.

### Token security

Unchanged: `getInstagramAccountById()` is still the only place that reads
and decrypts `instagramAccounts.accessToken`; `SocialPostEngine.php` only
ever receives the already-decrypted `$account` array, exactly like
`FacebookPublisher.php` already did in Phase 5. No new token storage, no
new decryption path, no token in any HTTP response, log line, or error
message anywhere in this phase's new code (verified by inspection of every
`respond()`/log call added — see Testing below).

### Scheduling compatibility

Architectural only, as scoped by the user — no scheduler was built or
modified in this phase. `publishSocialPost()` takes a plain
`(clientId, accountId, platforms, type, content)` request and returns a
plain structured result, with no dependency on how or when it's called;
a future scheduler can call it exactly the same way `api/publishSocialPostNow.php`
does. `cron/instagramScheduler.php` was not opened for editing in this
phase — no dependency was found that would have required changing it, so
nothing was reported/stopped-on per the user's "stop and report if the
scheduler needs changing" instruction.

### Manual testing performed (local, no production credentials available here)

Ran directly against `includes/SocialPostEngine.php` (no Meta network
calls involved for these — all failures below happen before any platform
dispatch):
- Invalid platform (`'twitter'`) → clean structured failure, no exception.
- Invalid post type (`'video'`) → clean structured failure.
- Invalid `clientId` (999999) → clean structured failure ("Please select a
  valid client.").
- Invalid `accountId` against a real local `clientId` → clean structured
  failure ("The selected account does not belong to this client."), no
  fatal.
- Instagram + text, called directly against a synthetic account array →
  returned the structured unsupported result; confirmed by code path
  inspection that no Meta endpoint is reachable from that branch.
- `socialPostEngineFinalize()` unit-level checks: all-success → `status=success`;
  one success + one failure → `status=partial`, `success=false`, and the
  successful platform's real `postId`/message are preserved unchanged in
  the output; all-failure → `status=failed`.
- `cron/testUnifiedSocialPost.php` run against a non-existent account id
  and against missing required arguments — both exit cleanly with code 1
  and a clear stderr message, no PHP warnings/fatals.
- `php -l` clean on all four touched/created files:
  `includes/SocialPostEngine.php`, `api/publishSocialPostNow.php`,
  `cron/testUnifiedSocialPost.php`, `pages/instagram-create-post.php`.

**NOT EXECUTED — REQUIRES PRODUCTION TEST** (no real connected Instagram/
Facebook account or Meta network access from this environment):
- Facebook-only image publish via the unified engine (real Facebook post id).
- Instagram-only image publish via the unified engine (real Instagram media id).
- Instagram + Facebook combined publish in one call (both real ids, overall
  `status=success`).
- Facebook-only text publish via the unified engine.
- Real partial-failure scenario (one platform succeeding, the other
  genuinely rejected by Meta).
- Real cross-client isolation attempt (a second client's real account).
- The "Publish Now" button end-to-end through the actual browser UI.

To run these on production, mirroring how Phase 5's
`cron/testFacebookPublish.php` test was run:
```
php cron/testUnifiedSocialPost.php <accountId> facebook image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Facebook Test"
php cron/testUnifiedSocialPost.php <accountId> instagram image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Instagram Test"
php cron/testUnifiedSocialPost.php <accountId> instagram,facebook image https://modlus.in/uploads/instagram-posts/test.jpg "MODLUS Phase 6 Unified Test"
php cron/testUnifiedSocialPost.php <accountId> facebook text "MODLUS Phase 6 Facebook Text Test"
```

### Known limitations

- "Publish Now" supports image posts only — reels/carousels still require
  the existing Draft/Schedule + cron flow, unchanged.
- "Publish Now" is not persisted to `instagramPosts` or shown in
  `pages/instagram-scheduled-posts.php` — it's an immediate, one-off action
  with no history row (by design, see Database above).
- Platform-specific captions (different text per platform) were explicitly
  out of scope this phase — the engine's `$content` shape already allows
  it later (`caption` vs a future `instagramCaption`/`facebookCaption`
  split) without a redesign, but the composer UI only exposes one caption
  field today, matching the user's "don't overcomplicate the initial UI"
  instruction.
- Real Meta-API-touching tests are unverified until run on production (see
  above) — do not treat this phase as production-verified the way Phase 4
  and Phase 5 are (§18) until the user runs them and confirms real post
  ids, per this doc's own standing rule (§19/§20).

---

## 22.8. Phase 7 — Unified Scheduled Publishing

**Date**: 2026-08-28. Follows the stop-and-report exchange in this same
session (recorded conceptually here since it wasn't a separate doc entry):
the existing `instagramPosts.status`/`instagramMediaId`/`errorMessage`
triplet was found to be single-platform-only and incapable of representing
independent Instagram/Facebook completion state; the user approved four
additive columns plus a `'partial'` status value before any code was
written.

### Architecture

`cron/instagramScheduler.php` remains the **only** scheduler — no second
cron system was created. Its existing three-part shape is preserved and
extended with one new phase:

```
Phase A  (unchanged) — finalize Reels still processing at Meta
Phase A2 (NEW)       — recover image/carousel posts stuck in 'publishing'
Phase B  (extended)  — publish newly due scheduled posts
```

Phase A is now explicitly scoped to `mediaType = 'reel'`
(`getInstagramPostsByStatus($con, 'publishing', 20, 'reel')`) so it can
never overlap with Phase A2's image/carousel recovery scan
(`getStuckSocialPosts()`). Every existing Instagram-only code path — the
legacy image branch, the carousel branch, the reel branch, `handleInstagramAuthFailure()`,
the `flock` single-instance lock — is **byte-for-byte unchanged**. The only
new logic is one `if ($platforms === ['instagram'] || empty($platforms))`
branch inside the existing `case 'image':` block: when true (every
pre-Phase-7 post, since `platforms` defaults to `'instagram'`), it takes
the exact original code path; only when a post's `platforms` differs from
that default does it route into the new unified logic.

### Files created

- `database/migrations/2026-08-28-instagram-posts-phase7-scheduling.sql` —
  documents the 4 additive columns (also self-healed at runtime).
- `cron/testScheduledUnifiedSocialPost.php` — **MANUAL TEST ONLY, NOT A
  CRON JOB.** A harness with three subcommands (`create`, `simulate-stuck`,
  `show`) that lets a real due/stuck row be set up deterministically and
  then exercised with the real `cron/instagramScheduler.php`, instead of
  waiting hours for a real schedule or faking a real crash.

### Files modified

- `includes/InstagramAutomation.php` — `ensureInstagramPostsTable()` gains
  4 self-healed columns; `getInstagramPostsByStatus()` gains an optional
  `$mediaType` filter (additive, existing 2-arg/3-arg callers unaffected);
  `saveInstagramPost()` gains an optional `platforms` key (defaults to
  `['instagram']` — legacy callers/behavior unchanged); new functions
  `getStuckSocialPosts()`, `socialScheduledRecoveryPlan()`,
  `recordInstagramPlatformResult()`, `markFacebookScheduledPublished()`,
  `markFacebookScheduledFailed()`, `setInstagramPostOverallStatus()`,
  `normalizeSocialEngineResult()`, `finalizeSocialScheduledPost()`. Every
  pre-existing function (`markInstagramPostPublished()`,
  `markInstagramPostFailed()`, `revertInstagramPostToScheduled()`,
  `getDueInstagramPosts()`, etc.) is untouched.
- `includes/SocialPostEngine.php` — one additive change: catches
  `InstagramTransientApiException` distinctly from other errors and adds
  `'transient' => true` to that platform's result, so a scheduled-post
  caller can tell a network blip apart from a real Meta rejection. `publishSocialPost()`'s
  signature, validation order, and return shape are unchanged; Phase 6's
  `api/publishSocialPostNow.php` ignores the new key and is unaffected.
- `cron/instagramScheduler.php` — adds Phase A2; the `case 'image':` branch
  gains the platforms-based fork described above.
- `api/saveInstagramPost.php` — accepts `platforms[]`, defaults to
  `['instagram']` when absent, rejects Facebook + non-image at save time.
- `pages/instagram-create-post.php` — wires the Phase 6 platform checkboxes
  into the Draft/Schedule submit (previously they only fed Publish Now);
  Facebook checkbox is now gated by *both* having a linked Page *and*
  Post Type = Image; edit mode restores a post's saved platform selection.
- `pages/instagram-scheduled-posts.php` — adds a Platforms column
  (per-platform badges, Facebook's badge colored by its own
  `facebookStatus`), a `'partial'` status badge/filter option, and extends
  "View Error" to show both platforms' errors when present.

### Database changes

Four additive columns on `instagramPosts` (see migration file above for
exact types/defaults) plus one new value (`'partial'`) for the existing
`status` column — no new tables, no `companyId`, no changes to any other
table.

### Platform selection / Instagram-only / Facebook-only / Instagram+Facebook behavior

`platforms` is a comma list persisted at save time. The scheduler reads it
per-post and only ever calls the platform(s) actually selected — confirmed
by testing (below): an Instagram-only post takes the untouched legacy path;
a Facebook-only post and a dual post both route through
`publishSocialPost()` with exactly the requested platform list, never more.

### Partial-success handling

`finalizeSocialScheduledPost()` computes `status` from combining both
platforms' outcomes, never from a single platform's side effect — verified
directly (see Tests below): Instagram success + Facebook permanent failure
→ `status = 'partial'`, with Facebook's own error preserved in
`facebookErrorMessage` and Instagram's real post id preserved in
`instagramMediaId`.

### Retry / idempotency behavior

- "Done" is determined **only** from a persisted result
  (`instagramMediaId` / `facebookPostId`), never from `status` — so a
  platform whose success is already recorded is **never** re-attempted, by
  either fresh Phase B processing or Phase A2 recovery.
- A platform result marked `transient` (network-level Meta API failure) is
  left completely unwritten — the row stays `'publishing'` and Phase A2
  retries exactly that platform on the next run, never the other one.
- **Acknowledged, not solved**: if Meta accepts a publish call but the PHP
  process dies before the response is recorded, the next recovery pass has
  no way to know that and will attempt that platform again — this is a
  narrow, inherent limitation of at-least-once retry against a
  non-idempotent Graph API with no client-supplied idempotency key. It
  predates Phase 7 (the same gap already existed, unhandled, for
  Instagram-only posts) and is not claimed to be closed by this work —
  only the specific "already-recorded success" case (the vast majority of
  real crash timing) is now safely handled.

### Token security / client isolation

Unchanged mechanisms, reused exactly: `getInstagramAccountById()` remains
the only decryption path; `instagramAccountBelongsToClient()` /
`instagramClientExists()` remain the only ownership guards. No new token
storage, no token in any log line (grepped all touched files and the
actual log output produced during testing — see Tests below).

### Manual + local testing actually performed

**Schema**: `ensureInstagramPostsTable()` run locally — confirmed all 4
columns created with the exact documented types/defaults.

**`saveInstagramPost()`**: confirmed a call with `platforms: ['instagram','facebook']`
persists `platforms='instagram,facebook'`, `facebookStatus='pending'`; a
legacy call with no `platforms` key persists `platforms='instagram'`,
`facebookStatus='not_applicable'` — proving old callers are unaffected.

**`socialScheduledRecoveryPlan()`** (pure function, no DB/network):
exercised all 6 combinations (neither done, Instagram done, Facebook done,
both done, Facebook-only, legacy Instagram-only) — every `instagramNeeded`/
`facebookNeeded`/`alreadyComplete` value matched expectation.

**`finalizeSocialScheduledPost()`**: exercised 6 scenarios against real
local DB rows (Instagram-done-then-Facebook-succeeds, mixed success/permanent-failure,
Instagram-succeeds-with-Facebook-transient, both-already-done,
Facebook-only-success, and the mirror Facebook-done-then-Instagram-succeeds)
— **two real bugs were found and fixed during this testing**, not
discovered by static review:
1. The original implementation trusted `markInstagramPostPublished()`/
   `markInstagramPostFailed()`'s side effect on `status` to reflect the
   aggregate outcome, which is wrong whenever a platform's success wasn't
   freshly attempted this run (recovery skip) or a sibling platform is
   still transiently unresolved. Fixed by splitting into two phases: each
   platform's own fields are written independently
   (`recordInstagramPlatformResult()`), and `status` is decided exactly
   once, explicitly, only when nothing is left unresolved.
2. `publishSocialPost()` returns an **empty** `platforms` array when its
   own top-level validation rejects a request before dispatching to any
   platform (e.g. missing Facebook Page) — `finalizeSocialScheduledPost()`
   was misreading that as "not attempted, trust existing state" instead of
   "this attempt failed", silently leaving a genuinely-failed platform
   stuck at `'pending'` with no error recorded. Fixed with
   `normalizeSocialEngineResult()`, which turns a top-level rejection into
   an explicit failure entry for every platform that was actually intended
   to be attempted.

After both fixes, all 6 scenarios produced exactly the expected `status`/
`instagramMediaId`/`facebookStatus`/`facebookPostId`/`facebookErrorMessage`.

**End-to-end against the real `cron/instagramScheduler.php`** (local
machine, synthetic client/account, real code path, real scheduler
execution — not mocked):
- Simulated Instagram-already-done + Facebook-pending → ran the real
  scheduler → confirmed via log line and DB state: Instagram was **not**
  re-attempted (`instagramMediaId` unchanged), only Facebook was attempted.
- Simulated Facebook-already-done + Instagram-pending (the mirror
  direction) → confirmed Facebook was **not** re-attempted
  (`facebookPostId` unchanged); Instagram's attempt hit a local-machine-only
  curl/SSL issue (no CA bundle on this dev machine — unrelated to Phase 7
  logic) and was correctly classified `transient`, leaving the row safely
  retriable rather than marked failed.
- Simulated both-already-done → ran the real scheduler → log line
  explicitly confirms *"finalized without a new Meta API call"*; `status`
  correctly became `'published'`.
- Created a fresh due Instagram+Facebook post with a synthetic (invalid)
  test account and ran the real scheduler → both platforms correctly
  attempted, both correctly failed (test account has no valid credentials)
  with the real, specific error message preserved per platform, `status`
  correctly `'failed'` (both failed) — confirming `normalizeSocialEngineResult()`'s
  fix works on the fresh-due path too, not just recovery.
- `php -l` clean on every touched/created file.
- Grepped `cron/instagramScheduler.log` and `logs/social-post-engine.log`
  for token-shaped content after all of the above runs — none found;
  logged messages are exactly the sanitized error strings shown above.

**Incident during testing, caught and fixed**: initial cleanup of
synthetic test posts pointed at the shared real test image
(`uploads/instagram-posts/test.jpg`, used throughout Phase 5/6 testing) —
`deleteInstagramPostRecord()`'s existing (unmodified, correct) behavior of
removing a deleted post's referenced media file deleted that shared file
as a side effect. Caught immediately via `git status` and restored via
`git checkout`. Recorded here as a reminder for any future local testing:
use a throwaway media path for synthetic posts, never the shared
documented test image.

**NOT EXECUTED — REQUIRES PRODUCTION TEST** (no production DB/Meta network
access from this environment):
- Test A: schedule a real Instagram-only image post — confirm unchanged
  behavior.
- Test B: schedule a real Facebook-only image post — confirm a real
  Facebook post id.
- Test C: schedule a real Instagram+Facebook image post — confirm both a
  real Instagram media id and a real Facebook post id, `status = 'published'`.
- A real crash-recovery scenario (kill `-9` the scheduler process between
  the two platform calls) — the local testing above simulates the
  *outcome* of a crash (via `simulate-stuck`) and proves the recovery
  *logic* is correct, but has not observed an actual process crash on
  production.

### Production test commands

```
# A — Instagram only (existing behavior, must be unaffected)
php cron/testScheduledUnifiedSocialPost.php create <accountId> instagram image uploads/instagram-posts/test.jpg "Phase 7 IG-only test"
php cron/instagramScheduler.php

# B — Facebook only
php cron/testScheduledUnifiedSocialPost.php create <accountId> facebook image uploads/instagram-posts/test.jpg "Phase 7 FB-only test"
php cron/instagramScheduler.php

# C — Instagram + Facebook (the most important test)
php cron/testScheduledUnifiedSocialPost.php create <accountId> instagram,facebook image uploads/instagram-posts/test.jpg "Phase 7 unified test"
php cron/instagramScheduler.php

# After each: inspect the resulting row
php cron/testScheduledUnifiedSocialPost.php show <postId>

# Recovery tests (deterministic, no need to wait for or force a real crash)
php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> yes no   # IG done, FB pending
php cron/instagramScheduler.php
php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> no yes   # FB done, IG pending
php cron/instagramScheduler.php
php cron/testScheduledUnifiedSocialPost.php simulate-stuck <postId> yes yes  # both done — must log "without a new Meta API call"
php cron/instagramScheduler.php
```

**`cron/testScheduledUnifiedSocialPost.php` and its log/lock output are
manual test tooling — do not register it on Hostinger Cron.**

### Known limitations

- Facebook scheduling is image-only (enforced at save time) — no verified
  Facebook equivalent for Reels/carousels exists yet, matching Phase 5/6.
- Platform-specific captions remain out of scope (same content is reused
  for both platforms), as explicitly scoped by the user.
- The narrow crash-timing gap described under Retry/idempotency above is
  acknowledged, not solved — exactly-once delivery is not claimed.
- Production verification is outstanding — see status below.

### Production verification status

**IMPLEMENTED — AWAITING PRODUCTION VERIFICATION.** Do not treat Phase 7 as
production-verified (unlike Phase 4/5/6, which have real post ids on
record) until the production test commands above have actually been run
and their results confirmed.

---

## 22.9. Phase 7.x — Production Bug Fix: Instagram Image Container Readiness

**Date**: 2026-08-28. Discovered via a real production scheduled
Instagram+Facebook post: Facebook published successfully
(`336886156185070_122207273216467606`), Instagram failed with HTTP 400,
`error_subcode 2207027` — *"The media is not ready to be published. Please
wait a moment."* Meta had accepted the image container creation call but
hadn't finished processing it by the time `media_publish` was called
immediately afterward. Phase 7's own behavior around this was correct
(`status='partial'`, Facebook's post id preserved, Instagram's error
preserved) — the bug was purely in the timing of the two Instagram Graph
API calls, not in the Phase 7 architecture.

**Fix**: `publishInstagramImagePost()` (`includes/InstagramAutomation.php`)
now polls the **existing** `getInstagramContainerStatus()` (already used,
unchanged, by Phase A's Reel finalization) between creating the container
and calling `media_publish`, waiting for `FINISHED` before proceeding.
Bounded: polls every `INSTAGRAM_CONTAINER_POLL_INTERVAL_SECONDS` (2s), for
at most `INSTAGRAM_CONTAINER_POLL_MAX_SECONDS` (30s) total. `ERROR`/`EXPIRED`
is a permanent failure; a timeout without `FINISHED`/`ERROR` throws the
**existing** `InstagramTransientApiException` — the same class every other
retryable failure in this module already uses, so both existing callers
(`SocialPostEngine.php`'s per-platform catch, and the scheduler's legacy
try/catch) already handle it correctly as retryable with **no changes
needed in either file**. Each poll attempt logs `creation_id` + `status_code`
via the existing `instagramWriteApiDebugLog()` — never the access token.

Because `publishInstagramImagePost()` is the single function both Phase 6
Publish Now and Phase 7 Scheduled Publishing already call (directly, or via
`SocialPostEngine.php`), this one change benefits both automatically — no
scheduler-only polling, no duplicated Instagram publishing logic.

**Scope**: Instagram **image** posts only, matching the reported bug.
Carousel/Reel publishing were not touched — Reels already have their own
async finalization (Phase A, unchanged); carousels create containers the
same way images do and could theoretically hit the same race, but that
was not part of the reported bug or this fix's scope.

**Database**: none. This is a pure application/service-layer timing fix.

**Verified locally**: `php -l` clean; Facebook-only publishing produces the
exact same error as before the fix (proving Facebook was not touched);
Instagram-only publishing still fails at container *creation* (before ever
reaching the new polling code) with the correct account/token, proving the
polling is correctly positioned after creation and its exception handling
integrates with the existing transient/permanent classification; `git diff`
confirms `publishInstagramCarouselPost()`/`publishInstagramVideoPost()`/Phase
A are byte-for-byte untouched. **Live polling behavior (an actual
IN_PROGRESS→FINISHED transition, and a real deliberately-delayed
container) requires a production account with real Meta credentials and
was NOT executed from this environment.**

No exactly-once guarantee is claimed beyond what Phase 7 already
documented (§22.8) — this fix reduces the specific "container not ready"
race, it does not change the acknowledged crash-timing limitation.

---

## 22.10. Phase 8 — Unified Module Naming Refactor

**Date**: 2026-08-28. Naming/refactoring only — no behavior, OAuth, permissions,
scheduler logic, or data semantics changed. The module evolved (Phases 5-7)
from Instagram-only into a unified publisher supporting Instagram-only,
Facebook-only, and Instagram+Facebook — the old all-"Instagram" naming had
become misleading. This phase renames exactly the parts that are genuinely
part of the unified post/scheduling domain, and deliberately leaves every
genuinely platform-specific name untouched.

### UI naming

| Old sidebar label | New sidebar label | Route |
| --- | --- | --- |
| Instagram Automation | **Social Media Automation** | `/instagram-automation` (unchanged — see below) |
| Create Instagram Post | **Create Social Post** | `/instagram-create-post` → `/social-create-post` |
| Instagram Posts | **Social Posts** | `/instagram-scheduled-posts` → `/social-posts` |
| Instagram Comments | *(unchanged)* | `/instagram-comments` |
| Instagram Analytics | *(unchanged)* | `/instagram-analytics` |

Comments and Analytics were deliberately **not** renamed — both remain
genuinely Instagram-only in their actual implementation (`instagramComments`/
`instagramInsights` tables, Instagram Graph API only, no Facebook data
anywhere in either feature). Renaming their labels would have been cosmetic,
not accurate — exactly what this phase was told not to do.

### Route naming — one deliberate exception

`/instagram-automation` (the Meta connect/settings page) keeps its **route
path** unchanged — only its sidebar label and `routesMaster.routeTitle`
changed to "Social Media Automation". Reason: `api/instagramOauthCallback.php`
redirects back to this exact path by name; renaming it would have required
touching the OAuth callback file, which every phase of this project has
been told to leave alone unless proven necessary. Not proven necessary here
— a label change fully satisfies "the user sees Social Media Automation",
without touching OAuth.

### File renames (via `git mv`, history preserved)

- `pages/instagram-create-post.php` → `pages/social-create-post.php`
- `pages/instagram-scheduled-posts.php` → `pages/social-posts.php`
- `api/saveInstagramPost.php` → `api/saveSocialPost.php`
- `api/getInstagramPosts.php` → `api/getSocialPosts.php`
- `api/deleteInstagramPost.php` → `api/deleteSocialPost.php`

`api/publishSocialPostNow.php` was already correctly named (Phase 6) — not
touched. `cron/instagramScheduler.php` was **deliberately not renamed** —
see below.

### Function renames (unified post/scheduling domain only)

`includes/InstagramAutomation.php`: `ensureInstagramPostsTable` →
`ensureSocialPostsTable`, `instagramPostsEnsureColumn` →
`socialPostsEnsureColumn`, `encodeInstagramPostMediaPaths` →
`encodeSocialPostMediaPaths`, `decodeInstagramPostMediaPaths` →
`decodeSocialPostMediaPaths`, `instagramPostMediaAbsoluteUrls` →
`socialPostMediaAbsoluteUrls`, `getInstagramPosts` → `getSocialPosts`,
`getInstagramPostById` → `getSocialPostById`, `saveInstagramPost` →
`saveSocialPost`, `deleteInstagramPostRecord` → `deleteSocialPostRecord`,
`getDueInstagramPosts` → `getDueSocialPosts`, `getInstagramPostsByStatus` →
`getSocialPostsByStatus`, `markInstagramPostPublishing` →
`markSocialPostPublishing`, `markInstagramPostPublished` →
`markSocialPostPublished`, `markInstagramPostFailed` →
`markSocialPostFailed`, `revertInstagramPostToScheduled` →
`revertSocialPostToScheduled`, `setInstagramPostOverallStatus` →
`setSocialPostOverallStatus`.

### Platform-specific functions intentionally NOT renamed

Every Instagram Graph API function: `publishInstagramImagePost()`,
`publishInstagramCarouselPost()`, `publishInstagramVideoPost()`,
`publishInstagramContainer()`, `getInstagramContainerStatus()`,
`waitForInstagramContainerReady()`, `updateInstagramPostContainerId()`
(Reel container tracking — genuinely Instagram-specific),
`instagramGraphApiRequest()`, `instagramSanitizeParamsForLog()`,
`instagramWriteApiDebugLog()`, `InstagramTransientApiException`. Every
account/OAuth/settings function: `getInstagramAccountById()`,
`getInstagramAccounts()`, `saveInstagramAccountFromOAuth()`,
`instagramAccountBelongsToClient()`, `instagramClientExists()`,
`getInstagramClientLabel()`, `ensureInstagramAccountsTable()`,
`disconnectInstagramAccount()`, `isInstagramAuthError()`, everything in
`instagramSettings`. Facebook-specific: `publishFacebookImagePost()`,
`publishFacebookTextPost()`, `facebookPageAccountValid()`,
`markFacebookScheduledPublished()`, `markFacebookScheduledFailed()`. Phase
7's own additions that were already generic: `getStuckSocialPosts()`,
`socialScheduledRecoveryPlan()`, `finalizeSocialScheduledPost()`,
`normalizeSocialEngineResult()`. `recordInstagramPlatformResult()` was
kept Instagram-named on purpose — it writes only Instagram's own columns
(`instagramMediaId`/`errorMessage`) and deliberately never touches
Facebook's, so an Instagram-specific name is the accurate one.

### Database: `instagramPosts` → `socialPosts`

Renamed via `RENAME TABLE` — an atomic metadata operation. Confirmed
locally: every row, column, index, primary key, default, and timestamp
preserved unchanged; only the table's name changed. Two ways this is
applied, matching this project's established migration convention:

1. **Self-healing** (`ensureSocialPostsTable()`): on any request, if
   `socialPosts` doesn't exist but the old `instagramPosts` does, it's
   renamed in place before anything else happens. Verified locally by
   renaming `socialPosts` back to `instagramPosts` and confirming
   `ensureSocialPostsTable()` correctly renamed it forward again.
2. **Explicit migration** (for documentation and manual/production runs):
   `database/migrations/2026-08-28-social-posts-naming.sql`.

No column was added, removed, or reinterpreted. No `companyId`. Two other
files had raw SQL against this table and were updated to keep working:
`includes/InstagramInsights.php` (post-level analytics JOIN) and
`includes/InstagramComments.php` (comment-to-post resolution) — both kept
their own Instagram-specific function names (they only ever query Instagram
media), only their `FROM`/`SELECT` table reference changed.

`instagramAccounts`, `instagramSettings`, `instagramInsights`,
`instagramComments`, `instagramWebhookEvents` were **not** renamed — the
audit found no evidence any of them have become cross-platform.

### routesMaster: `UPDATE`, never `DELETE`+`INSERT`

`database/migrations/2026-08-28-social-posts-routes-naming.sql` updates the
existing rows' `routePath`/`pageFile`/`routeTitle` in place. This matters:
`rolePermissions` grants reference a route by its `routeId`, not by path
string — updating in place preserves every role's existing permission grant
for these pages; deleting and re-inserting would issue new `routeId`s and
silently revoke access until someone manually re-granted it. **This
migration is not self-healing — it must actually be run** (unlike the table
rename). Verified locally: ran it, confirmed the new routes resolve via
`getRouteByPath()` and the pages render correctly.

### Backward compatibility for old URLs

Old bookmarks to `/instagram-create-post` and `/instagram-scheduled-posts`
will 404 after this change. A trivial alias was considered (per the
request) but rejected: `routesMaster` permissions are keyed by `routeId`,
so a second row for the old path would be a *new*, unpermissioned route —
nobody would have access to it without a separate admin step, which is
exactly the "complicated routing abstraction" this phase was told to
avoid. The sidebar and every internal link were updated to the new paths,
so normal in-app navigation is unaffected; only manually-typed/bookmarked
old URLs are affected.

### `cron/instagramScheduler.php`: intentionally NOT renamed

Hostinger Cron points directly at this filename in production. Renaming it
would require coordinating a production cron configuration change purely
for cosmetic naming — explicitly out of scope. It remains an internal
legacy filename; the module's user-facing identity is now "Social Media
Automation" regardless of what the cron script backing it is called.
`instagramSchedulerLog()` and `cron/instagramScheduler.log` were left
unchanged for the same reason (consistency with the file they belong to).

### Incident during testing (caught, fixed)

Local test cleanup twice deleted the shared `uploads/instagram-posts/test.jpg`
(used since Phase 5) as a side effect of `deleteSocialPostRecord()`'s
existing, correct behavior of removing a deleted post's referenced media.
Caught both times via `git status` and restored via `git checkout`.
Separately, bulk-renaming several files via PowerShell's `-Encoding UTF8`
silently added a UTF-8 BOM to the start of five PHP files — invisible to
`php -l` but would have emitted stray bytes before any `header()` call in
production. Caught by an actual functional test run (not by linting) and
stripped from all five files before finalizing.

### Testing performed

`php -l` clean on every modified file. Full functional regression against
local synthetic data: `saveSocialPost()` → `getSocialPostById()` →
`getSocialPosts()` → `getDueSocialPosts()` → `markSocialPostPublishing()` →
`markSocialPostPublished()` → `deleteSocialPostRecord()`, all against the
renamed `socialPosts` table. Full recovery cycle re-run end-to-end through
the real `cron/instagramScheduler.php` (Instagram-already-done +
Facebook-pending → correctly skipped Instagram, attempted only Facebook,
finalized `status='partial'` with a clean error message) — identical
correct behavior to the pre-rename Phase 7 test. `routesMaster` verified to
resolve the two new paths correctly. No access token in any log output.

### Production verification status

**IMPLEMENTED — AWAITING PRODUCTION VERIFICATION.** Requires running
`database/migrations/2026-08-28-social-posts-naming.sql` and
`database/migrations/2026-08-28-social-posts-routes-naming.sql` on
production (or simply deploying the code and letting the table rename
self-heal, then running just the routes migration, which is not
self-healing) and confirming the sidebar/pages/scheduler all behave as
verified locally.

---

## 22.11. Phase 9 — Social Posts Result Visibility

**Date**: 2026-08-28. Additive UI/visibility improvement only, following a
read-only audit — no publishing, scheduling, recovery, OAuth, or database
behavior changed.

**What changed**: `pages/social-posts.php` now shows, per post:
- **Account** — the connected Instagram username actually used by that
  specific post (resolved from `post.instagramAccountId`, never "the
  client's first account" — verified against a client with two connected
  accounts, each post correctly showed its own account).
- **Results** (replaces the old bare "Platforms" badges) — for each
  platform the post targets, its own status (Published/Failed/Pending) and:
  - Instagram: the real `instagramMediaId` as plain text (no link — see
    below), or the real `errorMessage` if it failed.
  - Facebook: the real `facebookPostId` as a clickable link
    (`https://www.facebook.com/{facebookPostId}`, `target="_blank"`,
    `rel="noopener noreferrer"`), or `facebookErrorMessage` if it failed.
  - Neither platform's result is ever fabricated — an unresolved platform
    shows "—", never an invented ID.

**Why Instagram has no link but Facebook does**: audited the codebase and
docs for any existing Instagram/Facebook permalink construction — none
exists. Facebook's stored `facebookPostId` is already in Meta's standard
`{pageId}_{postId}` form, which is Meta's own documented direct-link
format. Instagram's stored `instagramMediaId` is the numeric Graph API
media ID, which cannot be reliably converted to an `instagram.com/p/{shortcode}`
URL without an extra Graph API call — per the explicit instruction not to
fabricate a URL, Instagram's ID is shown as plain text only.

**Partial success**: verified with real synthetic data for both directions
(Instagram succeeds + Facebook fails, and the mirror) — each platform's own
outcome renders independently and correctly; the overall status badge
(unchanged, still driven by the existing `status` column) is never
overridden or recalculated by this phase's display logic.

**One small pre-existing bug fixed in `api/getSocialPosts.php`**: its
`$allowedStatuses` allow-list was missing `'partial'` — the "Partial"
filter option (added to the UI in Phase 7) silently fell back to "all
statuses" when selected. Directly relevant to this phase's own goal
(partial-success visibility), so fixed as part of this change; no other
line in that file was touched.

**Account resolution method**: reuses the existing, unmodified
`api/getInstagramSettings.php` endpoint (already used by the composer's
account dropdown) with no `clientId` filter, fetched once on page load into
an `id → username` map — the same client-side lookup-map pattern already
used for client labels on this same page. No new endpoint, no SQL JOIN, no
N+1 queries, no change to `getSocialPosts()`/`getSocialPostById()`. That
endpoint's account rows never include an access token (confirmed by its
column list), so this introduces no token exposure.

**Database**: **no changes** — every field displayed already existed on
`socialPosts` before this phase.

**Publishing/scheduler/OAuth**: not opened for editing. `includes/SocialPostEngine.php`,
`includes/InstagramAutomation.php`'s publishing functions, `cron/instagramScheduler.php`,
and both OAuth files are untouched.

**Testing**: `php -l` clean on both modified files. Verified against real,
API-layer data (`getSocialPosts()` called directly, not mocked) covering
all 9 required scenarios — Instagram-only success, Facebook-only success,
both success, partial (both directions), failed, draft, scheduled, and a
client with two connected accounts — by running the actual page JavaScript
(extracted from the file, not reimplemented) against that real data in a
Node sandbox. All 9 scenarios rendered correctly, including correct
per-post account attribution with no cross-account leakage.

**Known limitation**: no direct production UI test has been performed —
status is **IMPLEMENTED — AWAITING PRODUCTION VERIFICATION**.

---

## 22.12. Phase 10 — Facebook Text Post UI

**Date**: 2026-08-28. Exposes the pre-existing `publishFacebookTextPost()` /
`SocialPostEngine.php` text dispatch through the composer — no new
publishing logic, no new engine path.

**Audit finding, approved before implementation**: `cron/instagramScheduler.php`'s
due-post switch had no `text` case — a scheduled text post would have
fallen into `case 'image': default:`, which hardcodes `type='image'` and an
empty `imageUrl`, always failing with a confusing error instead of ever
reaching `publishFacebookTextPost()`. The user explicitly approved adding
one `case 'text':` branch (mirrors the existing image branch's shape
exactly — same `publishSocialPost()` → `normalizeSocialEngineResult()` →
`finalizeSocialScheduledPost()` call — no other branch, lock, claim, or
recovery logic touched). Verified via `git diff --stat`: 35 insertions, 0
deletions.

**UI**: `pages/social-create-post.php` gains a "Text Post" option (Image,
Text Post, Reel, Carousel). Selecting it: hides the media upload control
entirely (wrapped in `#mediaFieldGroup`/`#mediaPreviewGroup`, no media
required or submitted), relabels "Caption" to "Post Text" (same field,
reused as the Facebook message — no second editor), forces Instagram
unchecked+disabled and Facebook checked+enabled (if the account has a
linked Page), and enables Publish Now (previously image-only).

**Server-side enforcement** (never relies on JavaScript alone):
`api/saveSocialPost.php` and `api/publishSocialPostNow.php` both reject
`mediaType='text'` + Instagram in `platforms` — verified by simulating real
POST requests (session + CSRF) directly against both scripts: `platforms=['instagram']`
and `platforms=['instagram','facebook']` were both rejected with "Text
posts can only be published to Facebook." before any Meta call. Empty text
is rejected ("Please enter the text for this post."). Media upload is
skipped entirely for `mediaType='text'` — `mediaPaths` stays empty, nothing
is validated as required.

**Publishing flow** (verified unchanged): UI → `api/publishSocialPostNow.php`
(or `api/saveSocialPost.php` → `socialPosts` → `cron/instagramScheduler.php`) →
`SocialPostEngine.php` → `publishFacebookTextPost()` → Meta Graph API. Zero
lines changed in `SocialPostEngine.php`, `FacebookPublisher.php`, or any
Instagram publishing function.

**Database**: no changes. `socialPosts.mediaType='text'` fits the existing
`VARCHAR(20)` column; `platforms='facebook'` uses the existing column
exactly as image/carousel/reel posts already do.

**Existing functionality preserved**: Instagram image, Facebook image, and
Instagram+Facebook image publishing verified unchanged at the engine level
(identical error/behavior before and after this change). Reel and carousel
publishing untouched — confirmed no lines changed in those branches.

**Known limitation discovered during testing (not fixed, out of approved
scope)**: Phase 7's crash-recovery scan (`getStuckSocialPosts()`) is scoped
to `mediaType IN ('image', 'carousel')` — it does **not** include `'text'`.
A scheduled text post that crashes between being claimed
(`status='publishing'`) and finalizing has no recovery path, the same class
of gap already documented for Reels. Not addressed here — the approved
change was limited to the one dispatch case, not an extension of recovery
scope.

**Testing**: `php -l` clean on all 5 modified files. All required test
cases (A-G) executed against the real code (not reimplemented): Facebook
text Publish Now, Facebook text draft, a real scheduled text post run
through the actual `cron/instagramScheduler.php`, Instagram-text and
Instagram+Facebook-text rejected server-side via simulated real HTTP
requests to both API scripts, and Facebook/Instagram+Facebook image
regression confirmed identical at the engine level. All local; no real
Meta credentials available in this environment, so no real Facebook text
post has actually been created.

**Status**: **IMPLEMENTED — AWAITING PRODUCTION VERIFICATION.**

---

## 22. Do Not Modify Working Production Components Without Explicit Request

The following are **confirmed working in production** (§18) and must not be
refactored, reimplemented, or "cleaned up" incidentally while working on
something else:

- OAuth start/callback flow (`api/instagramOauthStart.php`,
  `api/instagramOauthCallback.php`)
- `config_id`-based Facebook Login for Business flow
- `BASE_URL` CLI/cron resolution (`includes/config.php`)
- Image publishing (`publishInstagramImagePost()`,
  `publishInstagramContainer()`)
- The scheduler's due-post detection and logging
  (`cron/instagramScheduler.php`)
- Client/account scoping (`instagramAccountBelongsToClient()`,
  `getInstagramAccountById()`)

If a change is needed to any of these, make the smallest change that
addresses the specific request, verify with `php -l`, and update this
document's relevant section afterward so it stays accurate.

---

## 23. Phase 11 — Instagram Comments Webhook Investigation (External Dependency)

**Date**: 2026-08-29. Investigation and diagnostics only — **no code was
changed to Instagram publishing, comments processing, webhooks, OAuth,
token encryption, or analytics** during this phase, per the investigation's
own explicit constraints.

### Status

**Instagram Comments: IMPLEMENTED IN CODE. PENDING REAL PRODUCTION
DELIVERY. BLOCKED BY META ACCESS/VERIFICATION DEPENDENCY.**

This is **not** a code defect. Every code path — signature verification,
event storage, account resolution, comment upsert — was re-confirmed
correct by direct inspection (see §14, §16). The gap is entirely on Meta's
side: the app has not yet been granted the access needed to receive real
`comments` webhook events for this Instagram Business Account.

### Evidence gathered

- A real Instagram comment, posted by another account on a real
  `gymlabzequipments` post, produced **zero rows** in `instagramWebhookEvents`
  — not even a `failed` one. Since `storeInstagramWebhookEvent()` runs
  unconditionally for every entry Meta ever POSTs (before any account
  lookup), this means Meta never attempted delivery at all for the real
  event.
- The Meta Dashboard's own "Test" button **does** successfully reach
  `api/instagramWebhook.php` and gets recorded — as `status='failed'`,
  `errorMessage='Unknown Instagram account: 0'` — because the dashboard's
  test broadcast intentionally sends a synthetic `entry.id = "0"`, not a
  real account id. This is expected dashboard-test behavior, not evidence
  of a broken real connection.
- `POST /{facebookPageId}/subscribed_apps?subscribed_fields=comments`
  failed: `(#100) Param subscribed_fields[0] must be one of {feed, mention,
  name, picture, ...}`. Root cause, confirmed against current Meta
  documentation (`docs/graph-api/webhooks/getting-started/webhooks-for-instagram/`):
  `comments` is an **Instagram**-object field, not a **Page**-object field,
  and Meta's own docs state "You cannot use the `subscribed_fields`
  parameter to configure or subscribe to Webhooks for Instagram." The
  correct call subscribes the Page to **any** ordinary Page field (Meta's
  own sample uses `feed`) purely to activate the Page/app pairing —
  `comments` routing is governed entirely by the separate App Dashboard
  Instagram-object configuration (already correctly set to "Subscribed").
- `GET /{instagramUserId}/subscribed_apps` failed: `(#100) Tried accessing
  nonexisting field (subscribed_apps)` — the Instagram Business Account
  node has no `subscribed_apps` edge at all in this architecture
  ("Instagram API with Facebook Login"). Account-level enrollment happens
  entirely through the **Page**, never the Instagram Business Account
  object directly.
- Meta's documentation also surfaced a requirement not previously known to
  this project: **"Your app must have successfully completed App Review
  (advanced access) to receive webhooks notifications for `comments` and
  `live_comments` webhooks fields."** This is independent of the
  subscription mechanism above and is currently **pending** for
  `MQlus Automation`.
- **Meta Business Verification** for the relevant Meta Business Portfolio
  is also **pending**.
- **`mqlus.in` domain verification is owned by a different, pre-existing
  Meta Business Portfolio** and was **intentionally left untouched** in
  this phase and must not be removed/reassigned without a separate,
  explicit decision — doing so could affect unrelated existing Meta
  configuration outside this project's scope.

### Correction identified, but NOT applied in this phase

`includes/InstagramAutomation.php`'s `subscribeInstagramAccountWebhooks()`
(added earlier in Phase 11, currently still calling
`POST /{instagramUserId}/subscribed_apps?subscribed_fields=comments` from
the disproven second attempt above) should, per current Meta documentation,
instead target the Page with any ordinary Page field —
`POST /{facebookPageId}/subscribed_apps?subscribed_fields=feed` — using the
existing Page Access Token. **This correction was determined but
deliberately NOT applied during Phase 12**, because Phase 12's own scope
explicitly prohibits modifying Instagram OAuth/webhook code while LinkedIn
work proceeds; `api/instagramOauthCallback.php` (the call site) is
untouched. The function remains in its Phase-11 (still-broken) state until
a future phase is explicitly authorized to apply this fix. Applying it
alone would still be **necessary but not sufficient** — App Review/Advanced
Access and Business Verification remain required regardless.

### Root cause ranking

1. **Most likely**: App Review / Advanced Access for the `comments`
   webhook field has not been granted — Meta silently withholds delivery
   for non-tester/non-admin content until this is approved, which fits
   every observed symptom (test events arrive, real events never do).
2. **Also required, independent of #1**: the Page must be enrolled via
   `subscribed_apps` with a valid Page field — now corrected in code, but
   its effect cannot be confirmed until #1 is also resolved.
3. **Also pending**: Meta Business Verification for the relevant Business
   Portfolio — commonly a prerequisite for Advanced Access approval on
   sensitive permissions.

### Next diagnostic (not yet performed — external dependency, not code)

Check Meta App Dashboard → App Review → Permissions and Features for the
entry governing the Instagram `comments` webhook field's Advanced Access
status, and the Business Verification status for the relevant Meta
Business Portfolio. Both are external Meta approvals with no code-side
workaround.

### Explicitly not authorized in this phase

No further production Meta configuration changes, no further
`subscribed_apps` production calls, no changes to the webhook receiver,
signature verification, OAuth, token encryption, or `mqlus.in` domain
verification.

**The Instagram Comments dependency is an external Meta approval
dependency, not a reason to block unrelated LinkedIn development — see
§25.**

---

## 24. Roadmap

```
Phase 3.2
Live Meta Validation
    │
    ├── Instagram Comments / App Review
    │       └── PENDING EXTERNAL META DEPENDENCY
    │           (App Review / Advanced Access for `comments`,
    │            Business Verification — see §23)
    │
    ├── LinkedIn Integration
    │       ↓
    │   Phase 12
    │   LinkedIn Foundation (see §25)
    │       ✅ IMPLEMENTED IN CODE
    │       ⏸ PAUSED — LIVE VALIDATION PAUSED DUE TO LINKEDIN COMPANY PAGE /
    │         DEVELOPER APP PREREQUISITE (no suitable LinkedIn Company/Page
    │         presence currently exists for the required Developer App setup)
    │       ↓
    │   Future LinkedIn Publishing / Scheduling / Analytics — on hold until
    │   the Company Page prerequisite is resolved and Phase 12 is live-verified
    │
    ├── Pinterest Integration
    │       ↓
    │   Phase 13
    │   Pinterest Foundation (see §26)
    │       ✅ IMPLEMENTED IN CODE — FOUNDATION COMPLETE
    │       ⏳ Trial Access is sufficient for the foundation's own read-only
    │         scopes; Standard Access (video-demo review) remains pending,
    │         required only before a future publishing phase
    │       ↓
    │   Future Pinterest Publishing / Scheduling / Analytics — deferred
    │       until Pinterest Standard Access is granted and Phase 13 is
    │       live-verified
    │
    └── Google Business Profile Integration
            ↓
        Phase 14
        Google Business Profile Foundation (see §27)
            ↓ CURRENT PRIORITY
            ✅ IMPLEMENTED IN CODE — LOCAL TESTS PASS
            ⏳ LIVE GOOGLE API ACCESS PENDING EXTERNAL PREREQUISITES
              (verified/active GBP for 60+ days, Google API access approval —
              see §27)
            ↓
        Future GBP Publishing / Reviews / Analytics
            ↓
        Future Cross-platform Inbox/Automation
```

Roadmap summary:

```
Pinterest — Foundation complete, Trial Access pending
LinkedIn — Foundation complete, live validation paused
Google Business Profile — Foundation current phase, live validation pending Google prerequisites
```

Instagram Comments is not abandoned — it resumes once Meta grants the
pending App Review/Business Verification approvals (§23). LinkedIn,
Pinterest, and Google Business Profile work proceeded in sequence because
none has a dependency on that Meta approval and none touches any Instagram
code path. LinkedIn is paused on its own external prerequisite (a Company
Page for the required Developer App — see §25); Pinterest's foundation is
complete and functionally usable within Trial Access, with Standard Access
pending only for a future publishing phase (see §26); Google Business
Profile is now the active platform-integration priority, itself gated on
Google's own external prerequisites (see §27). **None of LinkedIn's or
Pinterest's code is touched, rewritten, or abandoned by this** — each
resumes independently once its own external prerequisite clears.

---

## 25. Phase 12 — LinkedIn Integration Foundation

**Date**: 2026-08-29. Paused 2026-09-01. **Status: IMPLEMENTED IN CODE —
LIVE VALIDATION PAUSED DUE TO LINKEDIN COMPANY PAGE / DEVELOPER APP
PREREQUISITE.**

The user currently has no suitable LinkedIn Company/Page presence for the
required LinkedIn Developer App setup, so live OAuth verification cannot
proceed. **This is not a code gap and nothing here was rewritten, removed,
or reworked** — every file, table, and function listed below is exactly as
it was when this phase was last touched. LinkedIn resumes as soon as a
Company Page exists and live OAuth testing can run; see "Remaining
LinkedIn dependencies" below. Platform priority moved to Pinterest (§26) in
the meantime, which has no Company-Page-equivalent blocker for its
foundation phase.

### Why this started before Instagram Comments was resolved

Instagram Comments delivery is blocked entirely on Meta's own external
approvals (App Review/Advanced Access, Business Verification — §23), which
have no code-side workaround and no estimated timeline controlled by this
project. LinkedIn integration has no dependency on that approval, touches
no Instagram code path, and was explicitly requested to proceed in
parallel rather than sit idle waiting on an external party.

### Architecture

LinkedIn OAuth 2.0 3-legged authorization (member-authorizes-Modlus),
matching the existing Instagram/Facebook Login pattern's shape — a member
authenticates, Modlus discovers what they can manage, the operator selects
one, and it's stored against a Modlus client. No LinkedIn API keys used as
an OAuth replacement, no client-credentials flow, no scraping/browser
automation — verified against current LinkedIn documentation
(`learn.microsoft.com/en-us/linkedin/...`), not old tutorials.

```
Modlus (client selected)
  → api/linkedinOauthStart.php → https://www.linkedin.com/oauth/v2/authorization
  → LinkedIn member authorizes
  → api/linkedinOauthCallback.php
      1. state/CSRF validation (hash_equals, session) — identical pattern
         to api/instagramOauthCallback.php
      2. POST https://www.linkedin.com/oauth/v2/accessToken
         (grant_type=authorization_code) → access_token (+ id_token)
      3. GET https://api.linkedin.com/v2/userinfo (Bearer token) →
         member id ('sub') + display name — the officially documented OIDC
         way to identify the member, no manual JWT verification needed
      4. saveLinkedinAccountFromOAuth() — upsert keyed by linkedinMemberId
         (unique key), scoped to the selected clientId
  → redirects back to the existing /instagram-automation settings page
    (liStatus/liMessage/clientId query params, same round-trip pattern as
    Instagram's igStatus/igMessage/clientId)
  → operator calls "organizations" discovery, selects one, saves it
```

### Files inspected (audit, before writing anything)

`pages/instagram-automation.php`, `includes/InstagramAutomation.php`
(settings/account/OAuth functions, `instagramGraphApiRequest()`),
`includes/SocialPostEngine.php`, `database/migrations/2026-08-28-social-posts-naming.sql`,
`includes/Crypto.php`, `includes/Csrf.php`, `api/instagramOauthStart.php`,
`api/instagramOauthCallback.php`, `api/saveInstagramSettings.php`,
`api/getInstagramSettings.php`, `api/disconnectInstagramAccount.php`,
`database/migrations/2026-08-22-instagram-automation-route.sql`. Repo-wide
grep for `linkedin`/`socialPosts`/`oauth`/`accessToken`/`Crypto` confirmed:
no prior LinkedIn code existed; `socialPosts` is Instagram+Facebook-only
(the Phase 8-renamed `instagramPosts` table, keyed to `instagramAccounts`)
and cannot represent a LinkedIn member/organization cleanly without
conflating two unrelated vendors' identifiers under one table.

### Files created

- `includes/LinkedInAutomation.php` — settings table, accounts table,
  OAuth/member/organization functions, a dedicated `linkedinApiRequest()`
  transport (Bearer header + `Linkedin-Version` + `X-Restli-Protocol-Version`
  headers, JSON body — LinkedIn's shape, not Meta's — so it is **not**
  layered onto `instagramGraphApiRequest()`, matching how `FacebookPublisher.php`
  only reuses that wrapper because Facebook shares Meta's exact host/token
  shape, which LinkedIn does not).
- `api/linkedinOauthStart.php`, `api/linkedinOauthCallback.php`
- `api/getLinkedinSettings.php`, `api/saveLinkedinSettings.php`
- `api/getLinkedinOrganizations.php`, `api/saveLinkedinOrganization.php`
- `api/disconnectLinkedinAccount.php`
- `database/migrations/2026-08-29-linkedin-integration-foundation.sql`

### Files modified

- `pages/instagram-automation.php` — additive only: a new "LinkedIn API
  Configuration" card and a new "LinkedIn" connection-status card, reusing
  the page's existing client selector, CSRF token, and toast conventions.
  No existing Instagram/Facebook markup, form, or JS function was changed.

Nothing else was touched: `includes/InstagramAutomation.php`,
`FacebookPublisher.php`, `SocialPostEngine.php`, `InstagramWebhooks.php`,
`InstagramComments.php`, `InstagramInsights.php`,
`cron/instagramScheduler.php`, and every Instagram/Facebook OAuth or
publishing file are byte-for-byte unchanged (confirmed via `git diff`).

### Database

Two new tables, both self-healed at runtime and documented in the
migration above — no existing table/column changed, no `companyId`:

- **`linkedinSettings`** — one active row, platform-wide LinkedIn Client
  ID/Secret (encrypted) + redirect URL. Mirrors `instagramSettings`
  exactly; kept as a **separate** table rather than added onto
  `instagramSettings` because LinkedIn is a wholly different vendor/app —
  conflating the two under a Meta-named table would misrepresent both.
- **`linkedinAccounts`** — one row per connected LinkedIn member, `clientId`
  FK → `clientMaster` (`ON DELETE CASCADE`), `linkedinMemberId` unique key
  (reconnect upserts in place, exactly like `instagramAccounts.instagramUserId`),
  `linkedinOrganizationId`/`organizationName` (populated by a separate
  selection step, deliberately preserved across a reconnect/token refresh —
  verified, see Testing), `accessToken` encrypted via the existing
  `includes/Crypto.php`, `status` (`connected`/`disconnected`).

### LinkedIn permissions/scopes (verified against current LinkedIn documentation)

| Scope | Purpose | Availability |
| --- | --- | --- |
| `openid` | Required to use OIDC to authenticate the member. | Available immediately (Sign In with LinkedIn using OpenID Connect product). |
| `profile` | Member's lite profile (id, name, picture). | Available immediately, same product. |
| `email` | Member's email address. | Available immediately, same product — **not requested** in this phase (Modlus doesn't need the member's email; least-privilege). |
| `r_organization_admin` | Discover/read the organizations the member administers (`organizationAcls`, Organization Lookup). | **Requested by this phase's OAuth flow.** Requires the LinkedIn Developer Portal app to have the relevant Community Management API product access — **not independently confirmed as granted from this environment; this is an external LinkedIn approval dependency, not a code gap.** |
| `rw_organization_admin` | Manage organization pages / **post as an organization**. | **Needed only for a later publishing phase** — deliberately **not** requested now, since this phase does not publish anything. |

Per the task's explicit instruction, no permission requiring LinkedIn
approval was worked around — if `r_organization_admin` is not yet approved
for the developer app, `api/getLinkedinOrganizations.php` and
`api/saveLinkedinOrganization.php` catch LinkedIn's `403` distinctly
(`LinkedinPermissionException`) and report it as "Community Management API
product access is pending approval," never as a silent failure or a
fabricated empty-but-successful result.

### Organization discovery flow

```
Connect LinkedIn → OIDC identifies member
  → api/getLinkedinOrganizations.php (GET, clientId)
      1. GET /rest/organizationAcls?q=roleAssignee&role=ADMINISTRATOR&state=APPROVED
      2. Extract organization URNs → GET /rest/organizations?ids=List(...)
         for display names
  → operator selects one in the UI
  → api/saveLinkedinOrganization.php (POST, clientId, organizationId)
      - Server-side re-verification: re-calls the same discovery function
        and confirms the posted organizationId is actually one the member
        administers BEFORE persisting anything — the organization name is
        never trusted from the browser, only from this re-check. This is
        the "server-side ownership/access validation" the spec explicitly
        required for organization selection.
      - saveLinkedinOrganizationSelection() additionally re-checks the
        account row belongs to the posted clientId
        (`linkedinAccountBelongsToClient()`) before any write.
```

### Token security

- `linkedinAccounts.accessToken` and `linkedinSettings.linkedinClientSecret`
  use the existing `includes/Crypto.php` (`encryptSecret()`/`decryptSecret()`)
  — no new encryption mechanism.
- The access token is attached only as an `Authorization: Bearer` HTTP
  header inside `linkedinApiRequest()` — never in a URL, never in a log
  line, never returned by any API endpoint (`getLinkedinAccountForDisplay()`
  explicitly strips it before an endpoint can return the row).
- LinkedIn Client Secret is never returned to the browser — `getLinkedinSettings()`
  returns only a `hasClientSecret` boolean, mirroring `getInstagramSettings()`.

### Client isolation

`linkedinAccountBelongsToClient()` mirrors `instagramAccountBelongsToClient()`
exactly and is the actual enforcement mechanism (not the UI dropdown) —
checked server-side before every organization save and disconnect. One
LinkedIn member connection per Modlus client for this foundation phase; no
"latest"/"first"/"global"/"primary" LinkedIn account logic exists anywhere.

### Testing performed

Functional (local dev DB, no live LinkedIn credentials available in this
environment):
- `linkedinSettings`/`linkedinAccounts` table self-heal creation — PASS.
- Settings save/round-trip, secret never returned, correct decryption
  internally — PASS.
- Account upsert on OAuth connect (insert), and upsert-in-place on
  reconnect with the same `linkedinMemberId` (no duplicate row) — PASS.
- Organization selection persists, and **survives a subsequent
  reconnect/token refresh** (not silently wiped) — PASS.
- `getLinkedinAccountForDisplay()` never includes the access token — PASS.
- Client-ownership guard (`linkedinAccountBelongsToClient()`) true for the
  owning client — PASS; a cross-client rejection test was **attempted but
  skipped** (only one `clientMaster` row exists in this local dev
  database) — the guard's SQL (`WHERE id = ? AND clientId = ?`) is the
  identical, already-production-proven pattern `instagramAccountBelongsToClient()`
  uses, but a live two-client negative test was not executed here.
- Disconnect: status flips to `disconnected`, token cleared at rest,
  account no longer returned as connected — PASS.
- Grepped `logs/linkedin-api.log` after the above run for every plaintext
  token/secret value used in testing — none found.
- `php -l` clean on all 9 created/modified PHP files.

**NOT EXECUTED — REQUIRES LIVE LINKEDIN CREDENTIALS** (none available in
this environment): OAuth start redirect against a real LinkedIn app,
invalid-state rejection over real HTTP, cancellation handling, real token
exchange, a real `userinfo` call, real organization discovery against a
LinkedIn Developer app (including confirming whether `r_organization_admin`
is actually approved), a real organization save with server-side
re-verification against live data, and a real disconnect/reconnect cycle
end-to-end through the browser. **Do not treat Phase 12 as production/live
verified until these are actually run and confirmed**, per this document's
own standing convention (§19/§20).

### Remaining LinkedIn dependencies

- **A LinkedIn Company Page for the operator to register a Developer App
  against — currently does not exist. This is the blocking prerequisite as
  of the 2026-09-01 pause; nothing else in this list can be attempted
  until it exists.**
- LinkedIn Developer Portal app must exist with the "Sign In with LinkedIn
  using OpenID Connect" product added, and Community Management API access
  requested/approved for `r_organization_admin` (discovery) and, later,
  `rw_organization_admin` (publishing).
- Live OAuth/organization-discovery testing against that real app.
- Publishing, scheduling, analytics, and any LinkedIn-side webhooks are
  explicitly out of scope for this phase and require separate audits/approval.

### Remaining Instagram dependencies

Unchanged from §23: Meta App Review/Advanced Access for the `comments`
webhook field, and Meta Business Verification for the relevant Business
Portfolio — both external, pending, and untouched by this phase.

---

## 26. Phase 13 — Pinterest Integration Foundation

**Date**: 2026-09-01. **Status: IMPLEMENTED IN CODE — AWAITING LIVE
PINTEREST OAUTH VERIFICATION.**

### Why this started before LinkedIn was resolved

LinkedIn's Phase 12 foundation (§25) is fully implemented but paused on an
external prerequisite the operator does not currently have (a LinkedIn
Company Page for the required Developer App). That blocker has no
code-side workaround and no controlled timeline. Pinterest has no
equivalent per-client blocker for its foundation phase — a Pinterest
Developer App only requires the *operator's own* Pinterest Business
account (a one-time setup concern, not a per-client structural
requirement) — so Pinterest was explicitly moved to the active priority
rather than sitting idle. LinkedIn's code, tables, and routes are
completely untouched by this phase.

### Architecture

Pinterest OAuth 2.0 Authorization Code grant (member-authorizes-Modlus),
matching the existing Instagram/LinkedIn pattern's shape — a user
authenticates, Modlus discovers what they can manage, the operator selects
one, and it's stored against a Modlus client. Verified against current
Pinterest documentation (`developers.pinterest.com`), not old
tutorials/unofficial libraries/scraping.

```
Modlus (client selected)
  → api/pinterestOauthStart.php → https://www.pinterest.com/oauth/
  → Pinterest user authorizes
  → api/pinterestOauthCallback.php
      1. state/CSRF validation (hash_equals, session) — identical pattern
         to api/instagramOauthCallback.php / api/linkedinOauthCallback.php
      2. POST https://api.pinterest.com/v5/oauth/token
         (grant_type=authorization_code, app authenticated via HTTP Basic
         Auth — base64(client_id:client_secret) — Pinterest's shape, not
         LinkedIn's POST-body secret or Meta's query-string access_token)
         → access_token, refresh_token, expires_in, refresh_token_expires_in
      3. GET https://api.pinterest.com/v5/user_account (Bearer token) →
         Pinterest user id + username
      4. savePinterestAccountFromOAuth() — upsert keyed by
         pinterestUserId (unique key), scoped to the selected clientId
  → redirects back to the existing /instagram-automation settings page
    (piStatus/piMessage/clientId query params, same round-trip pattern as
    Instagram's igStatus/igMessage and LinkedIn's liStatus/liMessage)
  → operator calls "boards" discovery, selects one, saves it
```

### Files inspected (audit, before writing anything)

`includes/LinkedInAutomation.php` (the closer architectural template than
Instagram — both are member/user-authorizes-app OAuth 2.0 flows against a
non-Meta vendor), `includes/Crypto.php`, `includes/Csrf.php`,
`includes/InstagramAutomation.php` (`instagramClientExists()`,
`getInstagramClientLabel()`), `api/linkedinOauthStart.php`,
`api/linkedinOauthCallback.php`, `api/getLinkedinSettings.php`,
`api/saveLinkedinSettings.php`, `api/getLinkedinOrganizations.php`,
`api/saveLinkedinOrganization.php`, `api/disconnectLinkedinAccount.php`,
`pages/instagram-automation.php`,
`database/migrations/2026-08-29-linkedin-integration-foundation.sql`.
Repo-wide grep for `pinterest`/`oauth`/`accessToken`/`clientId`/`Crypto`
confirmed: no prior Pinterest code existed; `socialPosts` remains
Instagram+Facebook-only and was correctly left untouched (per the task's
explicit instruction not to force Pinterest identifiers into it).

Official Pinterest API documentation
(`developers.pinterest.com/docs/getting-started/set-up-authentication-and-authorization/`,
`developers.pinterest.com/docs/key-concepts/access-tiers/`) was read
directly for the OAuth mechanism, token endpoint, scopes, token lifetime,
and refresh mechanism below — not inferred from memory or third-party
tutorials.

### Files created

- `includes/PinterestAutomation.php` — settings table, accounts table,
  OAuth token exchange + refresh, a dedicated `pinterestApiRequest()`
  transport (Bearer header + JSON body for REST calls) and a separate
  `pinterestTokenRequest()` (HTTP Basic Auth + form-urlencoded, used only
  for the token endpoint) — **not** layered onto `linkedinApiRequest()` or
  `instagramGraphApiRequest()`, matching how each existing platform module
  only reuses another platform's transport when the host/auth shape
  actually matches (it doesn't here — see Token security below).
- `api/pinterestOauthStart.php`, `api/pinterestOauthCallback.php`
- `api/getPinterestSettings.php`, `api/savePinterestSettings.php`
- `api/getPinterestBoards.php`, `api/savePinterestBoard.php`
- `api/disconnectPinterestAccount.php`
- `database/migrations/2026-09-01-pinterest-integration-foundation.sql`

### Files modified

- `pages/instagram-automation.php` — additive only: a new "Pinterest API
  Configuration" card and a new "Pinterest" connection-status card, reusing
  the page's existing client selector, CSRF token, and toast conventions.
  No existing Instagram/Facebook/LinkedIn markup, form, or JS function was
  changed.

Nothing else was touched: `includes/InstagramAutomation.php`,
`FacebookPublisher.php`, `LinkedInAutomation.php`, `SocialPostEngine.php`,
`InstagramWebhooks.php`, `InstagramComments.php`, `InstagramInsights.php`,
`cron/instagramScheduler.php`, and every Instagram/Facebook/LinkedIn OAuth
or publishing file are byte-for-byte unchanged.

### Database

Two new tables, both self-healed at runtime and documented in the
migration above — no existing table/column changed, no `companyId`:

- **`pinterestSettings`** — one active row, platform-wide Pinterest App
  Client ID/Secret (encrypted) + redirect URL. Mirrors
  `linkedinSettings`/`instagramSettings` exactly; kept as a **separate**
  table for the same reason LinkedIn's is separate from Instagram's — a
  wholly different vendor/app.
- **`pinterestAccounts`** — one row per connected Pinterest user, `clientId`
  FK → `clientMaster` (`ON DELETE CASCADE`), `pinterestUserId` unique key
  (reconnect upserts in place, exactly like `linkedinAccounts.linkedinMemberId`),
  `pinterestBoardId`/`boardName` (populated by a separate selection step,
  deliberately preserved across a reconnect/token refresh — verified, see
  Testing), `accessToken` **and** `refreshToken` (both encrypted via the
  existing `includes/Crypto.php`), `tokenExpiry` **and**
  `refreshTokenExpiry` (both populated — Pinterest access tokens genuinely
  expire in 30 days and require a real refresh flow, unlike LinkedIn's
  foundation-phase token, which had no expiry/refresh columns because none
  were needed at the time), `status` (`connected`/`disconnected`).

### Pinterest OAuth mechanism, scopes, and token lifetime (verified against current Pinterest documentation)

| Item | Value |
| --- | --- |
| Authorization URL | `https://www.pinterest.com/oauth/` |
| Token endpoint | `https://api.pinterest.com/v5/oauth/token` |
| App authentication on token endpoint | HTTP Basic Auth: `Authorization: Basic base64(client_id:client_secret)` |
| Access token lifetime | 2,592,000 seconds (30 days) |
| Refresh mechanism | `grant_type=refresh_token` to the same token endpoint. Apps use **continuous refresh tokens**: the refresh token itself has its own rotating **60-day** validity window and is **not guaranteed to remain the same value after a refresh** — `refreshPinterestAccessToken()`/`updatePinterestAccountTokens()` always persist whatever `access_token`, `refresh_token`, `expires_in`, and `refresh_token_expires_in` Pinterest actually returns, never assuming the prior refresh token is still valid (verified by test — see Testing below) |
| User identity endpoint | `GET https://api.pinterest.com/v5/user_account` |
| Board discovery endpoint | `GET https://api.pinterest.com/v5/boards` |

| Scope | Purpose | Availability |
| --- | --- | --- |
| `user_accounts:read` | Identify the authenticated Pinterest user. | Available on Trial access (default new-app tier). |
| `boards:read` | Discover the boards the user owns. | Available on Trial access. |
| `pins:read` | Read pin data. | **Not requested** — not needed for foundation (least-privilege). |
| `pins:write` / `boards:write` | Create pins / manage boards — **required for a later publishing phase.** | **Deliberately not requested now** — this phase does not publish anything. Requesting these later requires the app to be upgraded from Trial to **Standard access**, which requires a video demo of a real OAuth + API action and a compliance review — an external Pinterest approval step, not a code gap. |

Per the task's explicit instruction, no permission requiring elevated
Pinterest access was worked around — this phase only requests
`user_accounts:read,boards:read`, both available immediately on the
default Trial access tier, so no approval-pending state blocks Foundation
functionality the way `r_organization_admin` can block LinkedIn's
organization discovery. `api/getPinterestBoards.php` and
`api/savePinterestBoard.php` still catch a Pinterest `403` distinctly
(`PinterestPermissionException`) in case a future scope/tier restriction
ever applies, mirroring `LinkedinPermissionException`'s pattern.

### Board discovery flow

```
Connect Pinterest → user_account identifies the user
  → api/getPinterestBoards.php (GET, clientId)
      GET /v5/boards (Bearer token) → board id + name list
  → operator selects one in the UI
  → api/savePinterestBoard.php (POST, clientId, boardId)
      - Server-side re-verification: re-calls the same discovery function
        and confirms the posted boardId is actually one the user owns
        BEFORE persisting anything — the board name is never trusted from
        the browser, only from this re-check.
      - savePinterestBoardSelection() additionally re-checks the account
        row belongs to the posted clientId
        (`pinterestAccountBelongsToClient()`) before any write.
```

### Token security

- `pinterestAccounts.accessToken`, `pinterestAccounts.refreshToken`, and
  `pinterestSettings.pinterestClientSecret` use the existing
  `includes/Crypto.php` (`encryptSecret()`/`decryptSecret()`) — no new
  encryption mechanism.
- The client secret is sent only via the `Authorization: Basic` header on
  the token endpoint (`pinterestTokenRequest()`) — never in a URL, POST
  body, or log line.
- Access/refresh tokens are attached only as `Authorization: Bearer`
  headers inside `pinterestApiRequest()` — never in a URL, never in a log
  line, never returned by any API endpoint
  (`getPinterestAccountForDisplay()` explicitly strips **both** tokens
  before an endpoint can return the row — verified, see Testing).
- Pinterest Client Secret is never returned to the browser —
  `getPinterestSettings()` returns only a `hasClientSecret` boolean,
  mirroring `getLinkedinSettings()`/`getInstagramSettings()`.
- `pinterestWriteApiDebugLog()` redacts `client_secret`, `code`, and
  `refresh_token` before writing (token-request errors) and never logs the
  `Authorization` header (REST call errors) — same sanitization pattern as
  `linkedinWriteApiDebugLog()`/`instagramSanitizeParamsForLog()`.

### Client isolation

`pinterestAccountBelongsToClient()` mirrors
`linkedinAccountBelongsToClient()`/`instagramAccountBelongsToClient()`
exactly and is the actual enforcement mechanism (not the UI dropdown) —
checked server-side before every board save and disconnect. One Pinterest
account connection per Modlus client for this foundation phase; no
"latest"/"first"/"global"/"primary" Pinterest account logic exists
anywhere. No `companyId` introduced.

### Testing performed

Functional (local dev DB, no live Pinterest credentials available in this
environment) — full pass/fail output preserved in the implementation
session, summarized here:

- `pinterestSettings`/`pinterestAccounts` table self-heal creation — PASS.
- Settings save/round-trip, secret never returned, correct decryption
  internally — PASS.
- Account upsert on OAuth connect (insert), and upsert-in-place on
  reconnect with the same `pinterestUserId` (confirmed exactly one row) —
  PASS.
- Board selection persists, and **survives a subsequent reconnect/token
  refresh** (not silently wiped) — PASS.
- **Token rotation correctness**: `updatePinterestAccountTokens()` always
  overwrites both stored tokens with a newly issued pair; verified the old
  refresh token is genuinely gone from storage after a simulated rotation,
  and that `pinterestNormalizeTokenResponse()` only falls back to the
  prior refresh token when Pinterest's response omits one, preferring a
  newly returned token whenever present — PASS (this directly exercises
  the correction requested before implementation: never assume the
  original refresh token remains unchanged after a successful refresh).
- `getPinterestAccountForDisplay()` never includes either token — PASS.
- Client-ownership guard (`pinterestAccountBelongsToClient()`) true for
  the owning client, false for a non-owning client id — PASS; a
  **cross-client rejection test using two real `clientMaster` rows was
  attempted but SKIPPED — only one `clientMaster` row exists in this local
  dev database.** The guard's SQL (`WHERE id = ? AND clientId = ?`) is the
  identical, already-production-proven pattern
  `instagramAccountBelongsToClient()`/`linkedinAccountBelongsToClient()`
  use, but a live two-client negative test was not executed here. No
  synthetic client row was manufactured to force this test to pass.
- Disconnect: status flips to `disconnected`, both tokens cleared at rest,
  account no longer returned as connected — PASS.
- Grepped `logs/pinterest-api.log` and all new API response paths for
  every plaintext token/secret value used in testing (including the test
  client secret and every generated test access/refresh token) — none
  found. No `pinterest-api.log` file was created during this test run
  (expected — the transport layer was never exercised against the live
  Pinterest network from this environment; the log is written only on API
  error/network failure, same as `instagram-api.log`/`linkedin-api.log`).
- `php -l` clean on all 9 created/modified PHP files.

**NOT EXECUTED — REQUIRES LIVE PINTEREST CREDENTIALS** (none available in
this environment): OAuth start redirect against a real Pinterest app,
invalid-state rejection over real HTTP, cancellation handling, a real
authorization-code token exchange, a real `user_account` call, real board
discovery against a Pinterest Developer app, a real board save with
server-side re-verification against live data, a real
`refresh_token`-grant call confirming Pinterest's actual rotation
behavior, and a real disconnect/reconnect cycle end-to-end through the
browser. **Do not treat Phase 13 as production/live verified until these
are actually run and confirmed**, per this document's own standing
convention (§19/§20).

### Remaining Pinterest dependencies

- A Pinterest Developer App must exist under a Pinterest **Business
  account** (the operator's own account — not a per-client requirement).
- New apps default to **Trial access** — sufficient for this foundation
  phase (read-only `user_accounts:read`/`boards:read`), but boards/pins
  created *through the API* would be sandboxed until the app is upgraded
  to **Standard access** (video-demo + compliance review) — irrelevant
  here since this phase creates nothing, but required before any future
  publishing phase.
- Live OAuth/board-discovery/token-refresh testing against that real app.
- Publishing, scheduling, analytics, and any Pinterest-side webhooks are
  explicitly out of scope for this phase and require separate audits/approval.

### Remaining LinkedIn dependencies (unchanged from §25)

A LinkedIn Company Page for the required Developer App does not currently
exist — Phase 12 stays paused, code untouched, until that prerequisite is
available.

### Remaining Instagram dependencies (unchanged from §23)

Meta App Review/Advanced Access for the `comments` webhook field, and Meta
Business Verification for the relevant Business Portfolio — both external,
pending, and untouched by this phase.

---

## 27. Phase 14 — Google Business Profile Integration Foundation

**Date**: 2026-09-01. **Status: CODE IMPLEMENTED — LIVE GOOGLE BUSINESS
PROFILE VALIDATION PENDING.**

Source of truth for this phase:
`docs/GMB_INTEGRATION_FOUNDATION_PHASE_14.md` — that document, not this
section, is authoritative on scope/constraints; this section records what
was actually built and tested against it.

### Why this started before Pinterest reached Standard Access

Pinterest's Phase 13 foundation (§26) is complete and functionally usable
within its default Trial access — Standard access is a real but
non-blocking dependency (needed only for a future *publishing* phase, not
for foundation functionality). Google Business Profile has its own,
separate external prerequisite chain (a verified/active Business Profile
for 60+ days, then a Google API access approval) that Modlus currently
does not satisfy for live testing — per the source document (§2), this is
explicitly **not** a reason to block foundation implementation. Google
Business Profile becomes the active platform-integration priority while
that approval chain proceeds independently. LinkedIn's and Pinterest's
code, tables, and routes are completely untouched by this phase.

### Architecture

Google OAuth 2.0 authorization-code flow with offline access (member
authorizes Modlus, Modlus can refresh tokens without the user present),
matching the existing Instagram/LinkedIn/Pinterest pattern's shape — but
with one structural difference the other three platforms don't have:
Google's resource hierarchy is **three levels deep**
(`Google Account → Business Profile account → Location`), not a flat
"member → one selectable resource" shape. This phase therefore performs
two rounds of server-side discovery — accounts, then locations for a
chosen account — before anything is persisted, and stores the account and
location identifiers as separate, non-collapsed columns (source doc §4).
Verified against current Google documentation
(`developers.google.com/my-business/...`), not old tutorials/unofficial
libraries/scraping.

```
Modlus (client selected)
  → api/googleBusinessProfileOauthStart.php
    → https://accounts.google.com/o/oauth2/v2/auth
      (scope=openid email https://www.googleapis.com/auth/business.manage,
       access_type=offline, prompt=consent)
  → Google user authorizes
  → api/googleBusinessProfileOauthCallback.php
      1. state/CSRF validation (hash_equals, session) — identical pattern
         to the other three platforms' OAuth callbacks
      2. POST https://oauth2.googleapis.com/token
         (grant_type=authorization_code; client_id/client_secret sent as
         ordinary POST body fields — Google's documented shape, distinct
         from Pinterest's HTTP Basic Auth and LinkedIn's own POST-body
         shape hosted at a different endpoint)
         → access_token, refresh_token, expires_in
      3. GET https://openidconnect.googleapis.com/v1/userinfo (Bearer) →
         Google user id ('sub') + email
      4. saveGoogleBusinessProfileAccountFromOAuth() — upsert anchored on
         clientId (not the Google identity alone — see "Upsert anchoring"
         below), scoped to the selected clientId
  → redirects back to the existing /instagram-automation settings page
    (gbpStatus/gbpMessage/clientId query params, same round-trip pattern
    as the other three platforms)
  → operator calls "accounts" discovery, selects one, then calls
    "locations" discovery for that account, selects one, saves both
    together (api/saveGoogleBusinessProfileLocation.php)
```

### Files inspected (audit, before writing anything)

Per the source document's Step 1 and this session's own investigation:
`includes/LinkedInAutomation.php`, `includes/PinterestAutomation.php`,
`api/linkedinOauthStart.php`, `api/linkedinOauthCallback.php`,
`api/getLinkedinSettings.php`, `api/saveLinkedinSettings.php`,
`api/getLinkedinOrganizations.php`, `api/saveLinkedinOrganization.php`,
`api/disconnectLinkedinAccount.php`, `api/getPinterestSettings.php`,
`api/savePinterestSettings.php`, `api/getPinterestBoards.php`,
`api/savePinterestBoard.php`, `api/disconnectPinterestAccount.php`,
`pages/instagram-automation.php`, `includes/Crypto.php`,
`includes/Csrf.php`, `includes/leadActivityLogger.php`, this document.

Official Google documentation
(`developers.google.com/my-business/reference/accountmanagement/rest/v1/accounts/list`,
`.../businessinformation/rest/v1/accounts.locations/list`, plus the
Account/Location resource schema pages) was read directly for the exact
endpoint hosts, required `readMask` parameter, and JSON field names below
— not inferred from memory or third-party tutorials.

### Files created

- `includes/GoogleBusinessProfileAutomation.php` — settings table,
  accounts table, OAuth token exchange + refresh, a dedicated
  `googleBusinessProfileApiRequest()` transport (Bearer header + JSON body
  for REST calls) and a separate `googleBusinessProfileTokenRequest()`
  (form-urlencoded with `client_id`/`client_secret` as POST body fields,
  used only for the token endpoint) — **not** layered onto
  `linkedinApiRequest()` or `pinterestApiRequest()`, per the source
  document's explicit instruction (§3) not to reuse vendor-specific
  transports.
- `api/googleBusinessProfileOauthStart.php`, `api/googleBusinessProfileOauthCallback.php`
- `api/getGoogleBusinessProfileSettings.php`, `api/saveGoogleBusinessProfileSettings.php`
- `api/getGoogleBusinessProfileAccounts.php`, `api/getGoogleBusinessProfileLocations.php`, `api/saveGoogleBusinessProfileLocation.php`
- `api/disconnectGoogleBusinessProfileAccount.php`
- `database/migrations/2026-09-01-google-business-profile-integration-foundation.sql`

### Files modified

- `pages/instagram-automation.php` — additive only: a new "Google Business
  Profile API Configuration" card and a new "Google Business Profile"
  connection-status card (with a two-step account → location discovery
  UI), reusing the page's existing client selector, CSRF token, and toast
  conventions. No existing Instagram/Facebook/LinkedIn/Pinterest markup,
  form, or JS function was changed.

Nothing else was touched: `includes/InstagramAutomation.php`,
`FacebookPublisher.php`, `LinkedInAutomation.php`,
`PinterestAutomation.php`, `SocialPostEngine.php`, `InstagramWebhooks.php`,
`InstagramComments.php`, `InstagramInsights.php`,
`cron/instagramScheduler.php`, and every Instagram/Facebook/LinkedIn/
Pinterest OAuth or publishing file are byte-for-byte unchanged.

### Database

Two new tables, both self-healed at runtime and documented in the
migration above — no existing table/column changed, no `companyId`:

- **`googleBusinessProfileSettings`** — one active row, platform-wide
  Google Cloud OAuth Client ID/Secret (encrypted) + redirect URL. Mirrors
  `linkedinSettings`/`pinterestSettings` exactly.
- **`googleBusinessProfileAccounts`** — one row per connected client's
  Google Business Profile selection, `clientId` FK → `clientMaster`
  (`ON DELETE CASCADE`), `accessToken` **and** `refreshToken` (both
  encrypted via the existing `includes/Crypto.php`), `tokenExpiry`.
  Unlike LinkedIn's/Pinterest's flat "member → one resource" tables, this
  table preserves the full three-level hierarchy without collapsing
  identifiers (source doc §4): `googleUserId`/`googleUserEmail` (the
  Google identity), `googleAccountId`/`googleAccountName`/
  `googleAccountType` (the selected Business Profile account),
  `googleLocationId`/`googleLocationName`/`locationTitle`/
  `locationAddress` (the selected location — `locationAddress` stores the
  full `storefrontAddress` object as JSON, not a flattened string, so no
  structured data is discarded). The unique key is
  `(googleUserId, googleAccountId)`, not the vendor identity alone —
  deliberately different from LinkedIn/Pinterest — because a single
  Google identity may legitimately manage more than one Business Profile
  account across different Modlus clients (source doc §10); see "Upsert
  anchoring" below for how this interacts with reconnect behavior.

### Google OAuth mechanism, scope, and token behavior (verified against current Google documentation)

| Item | Value |
| --- | --- |
| Authorization URL | `https://accounts.google.com/o/oauth2/v2/auth` |
| Token endpoint | `https://oauth2.googleapis.com/token` |
| App authentication on token endpoint | `client_id`/`client_secret` as ordinary POST body fields (not Basic Auth, not query string) |
| Offline access | `access_type=offline&prompt=consent` — required to reliably receive a `refresh_token` on every (re)connect, per source doc §7 |
| User identity endpoint | `GET https://openidconnect.googleapis.com/v1/userinfo` |
| Account discovery endpoint | `GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts` |
| Location discovery endpoint | `GET https://mybusinessbusinessinformation.googleapis.com/v1/accounts/{id}/locations?readMask=name,title,storefrontAddress` (`readMask` is a **required** query parameter per Google's current documentation) |
| Refresh mechanism | `grant_type=refresh_token` to the token endpoint. Per source doc §20, Google may or may not return a new `refresh_token` on a given refresh call — `refreshGoogleBusinessProfileAccessToken()`/`googleBusinessProfileNormalizeTokenResponse()` always persist a newly returned one and only fall back to the prior stored refresh token when Google's response genuinely omits one (verified by test — see Testing below) |

**Scope requested**: `openid email https://www.googleapis.com/auth/business.manage`.
`https://www.googleapis.com/auth/business.manage` is the current,
non-deprecated Business Profile management scope (the older
`plus.business.manage` is deprecated and is not used anywhere in this
module). `openid`/`email` are additionally requested, least-privilege,
solely because this module's schema stores `googleUserId`/
`googleUserEmail` (source doc §6) and none of Google's Business Profile
APIs expose an identity endpoint of their own — the OIDC userinfo
endpoint is the officially documented way to get that, the same rationale
`LinkedInAutomation.php`'s OIDC userinfo call already uses in this
codebase.

### Account/location discovery flow

```
Connect Google → userinfo identifies the Google user
  → api/getGoogleBusinessProfileAccounts.php (GET, clientId)
      GET /v1/accounts (Bearer token) → account id/name/type list
      (a Google identity may have more than one — source doc §10 — all
      are returned, not just the first)
  → operator selects one account in the UI
  → api/getGoogleBusinessProfileLocations.php (GET, clientId, googleAccountId)
      - Re-verifies the requested account is actually accessible to this
        Google identity before querying its locations
      GET /v1/accounts/{id}/locations?readMask=... (Bearer token) →
      location id/title/address list
  → operator selects one location in the UI
  → api/saveGoogleBusinessProfileLocation.php (POST, clientId, googleAccountId, googleLocationId)
      - Server-side re-verification (source doc §11, steps 1-5 followed
        exactly): (1) account row belongs to the posted clientId
        (`googleBusinessProfileAccountBelongsToClient()`); (2) re-queries
        Google using the authenticated token; (3) confirms the account is
        actually accessible; (4) confirms the location actually belongs
        to that account; (5) saves the server-verified id/name/data —
        never the browser-submitted name/title.
```

### Upsert anchoring (why this differs from LinkedIn/Pinterest)

`saveGoogleBusinessProfileAccountFromOAuth()` is anchored on **clientId
first**, not the Google identity alone (unlike
`saveLinkedinAccountFromOAuth()`/`savePinterestAccountFromOAuth()`, which
upsert by the vendor member/user id). Reasoning: because a single Google
identity can legitimately manage multiple Business Profile accounts
across different Modlus clients (source doc §10), a vendor-identity-only
upsert would incorrectly move an already-connected client's row to
whichever client last reconnected with that same Google login. Anchoring
on clientId means reconnecting for the *same* client always updates that
client's own row — preserving any already-selected
account/location, exactly like the LinkedIn/Pinterest "does not touch the
selection fields on update" rule — and only inserts a new row when that
specific client has no connection yet. A second, narrower fallback anchor
(an existing not-yet-account-selected row for the same Google identity,
regardless of client) exists purely to avoid violating the
`(googleUserId, googleAccountId)` unique key in the edge case of two
different clients starting, but not finishing, a connection with the same
Google login concurrently — accepted as a "last reconnect wins" limitation
for that specific pre-selection edge case only, the same class of
limitation LinkedIn/Pinterest already accept for their own upsert
behavior. This reasoning and its consequences are documented in code
comments directly on `saveGoogleBusinessProfileAccountFromOAuth()`.

### Token security

- `googleBusinessProfileAccounts.accessToken`,
  `googleBusinessProfileAccounts.refreshToken`, and
  `googleBusinessProfileSettings.googleClientSecret` use the existing
  `includes/Crypto.php` (`encryptSecret()`/`decryptSecret()`) — no new
  encryption mechanism.
- The client secret is sent only as a POST body field on the token
  endpoint (`googleBusinessProfileTokenRequest()`) — never in a URL,
  never logged (redacted before any debug log write, alongside `code` and
  `refresh_token`).
- Access/refresh tokens are attached only as `Authorization: Bearer`
  headers inside `googleBusinessProfileApiRequest()` — never in a URL,
  never in a log line, never returned by any API endpoint
  (`getGoogleBusinessProfileAccountForDisplay()` explicitly strips
  **both** tokens before an endpoint can return the row — verified, see
  Testing).
- Google Client Secret is never returned to the browser —
  `getGoogleBusinessProfileSettings()` returns only a `hasClientSecret`
  boolean, mirroring the other three platforms' settings endpoints.
- `googleBusinessProfileWriteApiDebugLog()` never logs the `Authorization`
  header, and the token-request path redacts `client_secret`, `code`, and
  `refresh_token` before writing — same sanitization pattern as the other
  three platforms' debug logs.
- `googleBusinessProfileValidAccessToken()` (the token-refresh-when-needed
  helper called by every discovery/save endpoint) never exposes a token in
  its return path beyond the caller that immediately uses it for one
  Bearer-header API call.

### Client isolation

`googleBusinessProfileAccountBelongsToClient()` mirrors
`pinterestAccountBelongsToClient()`/`linkedinAccountBelongsToClient()`
exactly and is the actual enforcement mechanism (not the UI dropdown) —
checked server-side before every location save and disconnect, and before
`saveGoogleBusinessProfileLocationSelection()` writes anything. One Google
Business Profile connection per Modlus client for this foundation phase;
no "latest"/"first"/"global"/"primary" account logic exists anywhere. No
`companyId` introduced.

### Testing performed

Functional (local dev DB, no live Google credentials available in this
environment) — 30 assertions run, **all PASS** except one explicitly
skipped item (below):

1. `googleBusinessProfileSettings` table self-heal creation — PASS.
2. `googleBusinessProfileAccounts` table self-heal creation — PASS.
3. Settings save/get round-trip — PASS.
4. Client secret never returned by the settings endpoint — PASS.
5. Account insert on OAuth connect — PASS.
6. Reconnect/upsert-in-place behavior (same client, no duplicate row) —
   PASS; **a real bug was caught here**: the UPDATE branch's
   `mysqli_stmt_bind_param()` type string had one extra character
   (`'issssssi'`, 8 types for 7 placeholders), which threw
   `ArgumentCountError` on the very first reconnect test. Fixed to
   `'isssssi'` (7 types), re-linted, and the full suite re-run clean — see
   the file's current state, this was corrected before any other testing
   completed.
7. Access token encryption verified directly against the raw DB column
   (not just via the decrypting getter) — PASS.
8. Refresh token encryption verified the same way — PASS.
9. `getGoogleBusinessProfileAccountForDisplay()` never returns either
   token — PASS.
10. OAuth state validation exercised via the same `hash_equals()`/
    non-empty logic the callback uses: matching state accepted, tampered
    state rejected, empty state rejected — PASS.
11. Ownership guard true for the owning client — PASS.
12. Ownership guard false for a non-owning client id — PASS.
13. Location selection rejected when the posted clientId does not own the
    account, and the real, already-saved selection is confirmed unchanged
    after the rejected attempt — PASS. A **full two-real-client**
    cross-ownership negative test (Client A's real row vs. Client B's real
    row) was attempted but **SKIPPED — only one `clientMaster` row exists
    in this local dev database**, matching the established LinkedIn/
    Pinterest testing convention; item 13's synthetic-non-owning-clientId
    check exercises the same guard logic but is not a substitute for a
    genuine second-client negative test. No synthetic client row was
    manufactured to force this test to pass.
14. Disconnect clears both tokens at rest — PASS.
15. Disconnect flips status to `disconnected`, and the account is no
    longer returned as connected — PASS.
16. Grepped `logs/` for every plaintext test credential (client secret,
    access tokens, refresh tokens) used in testing — none found. No
    `google-business-profile-api.log` file was created during this test
    run (expected — the transport layer was never exercised against the
    live Google network from this environment).
17. Grepped every created/modified API response path (`respond()` calls)
    for `accessToken`/`refreshToken`/`googleClientSecret` — every match
    found was an internal-use variable passed into the transport layer,
    never present in a JSON response payload.
18. `php -l` clean on all 10 created/modified PHP files (after the fix in
    item 6 above).

**NOT EXECUTED — REQUIRES LIVE GOOGLE CREDENTIALS AND AN APPROVED PROJECT**
(none available in this environment, and Google provides no sandbox for
Business Profile API calls — source doc §16): OAuth start redirect against
a real Google Cloud OAuth client, invalid-state rejection over real HTTP,
cancellation handling, a real authorization-code token exchange, a real
`userinfo` call, real Business Profile account discovery, real location
discovery, a real location save with server-side re-verification against
live data, a real `refresh_token`-grant call confirming Google's actual
refresh-token-rotation behavior, and a real disconnect/reconnect cycle
end-to-end through the browser. **Do not treat Phase 14 as production/live
verified until these are actually run and confirmed against real Google
infrastructure with an approved Business Profile API project**, per this
document's own standing convention (§19/§20) and per the source document's
explicit instruction (§17) not to mark this live/verified until OAuth,
refresh, account discovery, location discovery, location selection,
server-side re-verification, reconnect, and disconnect have all succeeded
against real Google infrastructure.

### Exact Google prerequisites still pending

Per `docs/GMB_INTEGRATION_FOUNDATION_PHASE_14.md` §2/§16, verified against
Google's current official documentation (2026-09-01):

1. A Google Account.
2. A Google Business Profile, verified and active for **60+ days**
   (may belong to the operator or to a client they manage).
3. A website representing the business, listed on that Business Profile.
4. A Google Cloud project.
5. A Google Business Profile API access request submitted through
   Google's access process, using an email that is an owner/manager on
   the qualifying Business Profile.
6. Google's approval (API quota is the signal: 0 QPM = not approved,
   300 QPM = approved).
7. Business Profile APIs enabled on that approved project.
8. OAuth consent screen configuration in Google Cloud.
9. OAuth web client credentials (Client ID/Secret) generated and entered
   into the "Google Business Profile API Configuration" card built in
   this phase.
10. The authorized redirect URI registered in Google Cloud, exactly
    matching `googleBusinessProfileSettings.redirectUrl` (or the
    `BASE_URL`-derived default if left blank).
11. Live OAuth test against that real, approved project.

**Modlus currently does not have a qualifying, 60+-day-verified Google
Business Profile available for this.** This is an external dependency,
not a code gap, per the source document's explicit framing (§2).

### Confirmation: publishing/reviews/analytics/SocialPostEngine were NOT implemented

Per source doc §18 and the implementation constraints (§22), none of the
following were implemented in this phase: Google Business Profile posts,
post publishing, post scheduling, review listing, review replies, review
analytics, performance/insights dashboards, media/photo management, Q&A,
notifications/webhooks, or any `SocialPostEngine.php` integration.
`SocialPostEngine.php` itself was not opened for editing in this phase.
These remain explicitly out of scope until the foundation is live-verified
against real Google infrastructure.

### Remaining LinkedIn dependencies (unchanged from §25)

A LinkedIn Company Page for the required Developer App does not currently
exist — Phase 12 stays paused, code untouched, until that prerequisite is
available.

### Remaining Pinterest dependencies (unchanged from §26)

A Pinterest Developer App under the operator's own Pinterest Business
account exists in code-support terms only — Standard access (video-demo
review) remains pending, required only before a future publishing phase.
Foundation functionality itself is not blocked.

### Remaining Instagram dependencies (unchanged from §23)

Meta App Review/Advanced Access for the `comments` webhook field, and Meta
Business Verification for the relevant Business Portfolio — both external,
pending, and untouched by this phase.

---

## 28. Phase 4.7 — Production Hardening & Operational Reliability Audit

**Date**: 2026-09-03. Audit of the now-complete Production Ready → Automation
pipeline (Phases 4.1–4.6: `includes/SocialAutomationHandoffEngine.php`,
`socialContentAutomationHandoff`, real Facebook and Instagram publishes
independently verified in the Phase 4.6 session). This phase is audit-first:
nothing in `SocialPostEngine.php`, `InstagramAutomation.php`,
`FacebookPublisher.php`, or `cron/instagramScheduler.php` was found to need
changing, and none of them were touched.

### Duplicate-publish protection — already sufficient, unchanged

- **Cross-run**: a `flock()`-based single-instance lock
  (`cron/.instagramScheduler.lock`, `LOCK_EX | LOCK_NB`) prevents two
  overlapping scheduler invocations on the same host from processing the
  same due set concurrently. This assumes the standard single-cron,
  single-host deployment already documented (§11) — a hypothetical
  multi-host cron setup would need its own coordination, but no evidence of
  one exists.
- **Within a run**: `getDueSocialPosts()` only selects `status='scheduled'`;
  `markSocialPostPublishing()` flips status to `'publishing'` *before* the
  Meta call, so a crash or a later run never re-selects it via the same
  query. Recovery (`getStuckSocialPosts()`, `mediaType IN ('image',
  'carousel')`, Phase A2) instead checks the actually-persisted
  `instagramMediaId`/`facebookPostId` per platform
  (`socialScheduledRecoveryPlan()`) — a platform whose success is already
  recorded is never re-attempted, regardless of why the row was revisited.
  Verified: a future-`scheduledAt` row and an already-`'published'` row are
  both correctly excluded from `getDueSocialPosts()`.
- **Failure isolation**: each due/stuck post is processed inside its own
  `try/catch` in the scheduler's `foreach` loop — one post's exception does
  not stop the batch. Verified by code reading (unchanged from Phase 7).

### Ambiguous Meta-success — a real, narrow, documented limitation (not fixed)

**Scenario**: Meta's API call succeeds (a real post is created), but the
subsequent local `UPDATE` that records `instagramMediaId`/`facebookPostId`
(and, on the legacy single-platform path, `status='published'` in the same
statement) fails to persist — e.g. a database connection drop in the
instant between the Meta response and that write.

**Consequence**: the row is left at `status='publishing'` with the relevant
media-id column still empty. On the *next* run, `getStuckSocialPosts()`
picks it up, and `socialScheduledRecoveryPlan()` — which decides "already
done" purely from that same now-missing media-id column — will conclude
the platform still needs publishing and **re-attempt it, creating a real
duplicate post on Meta.**

This was traced precisely (`markSocialPostPublished()`,
`recordInstagramPlatformResult()`, `markFacebookScheduledPublished()` each
combine "record the fact of success" and, on the legacy path, the status
transition into one `UPDATE`) and is a genuine, reproducible-in-principle
window — but it is **not fixed** in this phase, per explicit instruction:
a database write failing at that exact instant is not solved by wrapping
it in a transaction (the `COMMIT` would fail for the same underlying
reason), and closing it fully would require an idempotency-key/
reconciliation-against-Meta system, which is out of scope for a hardening
pass on working architecture. This class of risk is inherent to any
"call an external API, then write the local result" integration and is
accepted, not silently ignored.

**Manual reconciliation procedure** (the currently-correct recovery path):
if the same content is observed published twice on Instagram/Facebook for
what should be one `socialPosts` row, check `logs/instagram-api.log` and
`cron/instagramScheduler.log` around the time the row first entered
`'publishing'` for a real Meta success response that has no matching
`instagramMediaId`/`facebookPostId` in the database. If found, manually
`UPDATE` the row with the real id and the correct terminal `status` so
`getStuckSocialPosts()` stops re-selecting it, then manually delete the
unwanted duplicate directly on Instagram/Facebook. There is no automated
recovery for this specific case, and none should be added without a
broader, explicitly-approved design.

### Activity logging gap found and fixed

`includes/SocialAutomationHandoffEngine.php`'s `resolveAndRegisterHandoff()`
was the one production-facing action in this entire pipeline that left no
trace in the existing `leadsActivityLogs` table (`saveActivityLog()`,
already used throughout this module for every other publish/save action).
**Fixed minimally**: one `saveActivityLog()` call on success
(`module='SocialAutomationHandoff'`, `action='handoff_sent'`) and one on
the `saveSocialPost()` failure path (`action='handoff_failed'`) — the
existing function, unmodified, no new logging system, no token ever
logged. Verified: a real regression run produced a real, correctly-worded
log row.

### Everything else audited — found already correct, unchanged

Account resolution (0/1/2+ active accounts), wrong-client account
rejection, platform gating (`instagram`/`facebook` only, case-insensitive),
media validation (missing file / PNG / disguised file / Drive →
`MANUAL_PUBLISH_REQUIRED`), duplicate-handoff protection
(`UNIQUE(productionId)`), `SENT`-handoff-implies-real-`socialPostId`
consistency, and all existing security checks (admin auth, CSRF,
`canApprove`, client/account ownership, no token exposure, no `companyId`)
were re-verified this phase and found correct — no code change made to any
of them.

### Files changed this phase

`includes/SocialAutomationHandoffEngine.php` (activity logging only, ~6
lines, additive). No schema change. No other file touched.

---

## 29. Phase 8/9 — Caption Area wiring + Automation Queue view

Closes the gap the Social Media handoff doc flagged as "next phase": the
Production → Automation handoff (§16 onward, above) predates Caption Area
and never knew about it — `resolveAndRegisterHandoff()` sent
`clientSocialContent.caption` (the raw Data-Entry caption), and
`checkEligibility()` never required a caption to exist at all.

### Phase 8 — caption gate

`SocialAutomationHandoffEngine::checkEligibility()` now also requires a
`socialContentCaption` row with `status = 'selected'` (read-only reuse of
`SocialContentCaptionEngine::getCaptionByProductionId()` — this engine still
never writes to that table). New state: `CAPTION_NOT_SELECTED`. Placed
*after* the existing `ALREADY_HANDED_OFF` check on purpose — a task that
already succeeded/is in-flight keeps reporting that, regardless of the
caption's current state, so nothing already sent changes behavior.
`resolveAndRegisterHandoff()` now sends the selected caption to
`saveSocialPost()` instead of the raw one.

Only ever affects `sourceType='social'` tasks: Other Content/Other Graphic
Content tasks have no `platformName` and were already blocked by the
platform check above this one.

No UI change was needed in `pages/social-content-production.php` — its
"Send to Automation" failure handler already surfaces `res.message`
verbatim, so the new ineligibility message shows up automatically.

### Phase 9 — Automation Queue page

`/social-automation` (`pages/social-automation.php`, admin-only, sidebar
under the existing "Automation" group) is a read-only view over data that
already existed — no new table, no new publishing engine. Columns: Client,
Platform, Content, Media, Caption, Schedule Date, Status, Action.

Backed by a new `SocialAutomationHandoffEngine::listQueue()` method (joins
`socialContentAutomationHandoff` + `socialPosts` + `socialContentProduction`
+ `clientSocialContent` + `socialContentCaption`) and a new thin endpoint,
`api/social-content-production/get-automation-queue.php`. Row actions reuse
existing endpoints only: Edit → `social-create-post?postId=` (the existing
composer), Retry → the existing `send-to-automation.php`, View Content →
the existing `get-tasks.php?id=`.

### Testing performed (Phase 8/9)

- `php -l` on every new/changed file.
- Direct engine invocation against the real local dev database (throwaway
  `clientSocialContent`/`socialContentProduction`/`socialContentCaption`
  rows, inserted and updated inside an uncommitted transaction, rolled back
  at the end — verified via before/after row counts): confirmed
  `CAPTION_NOT_SELECTED` with no caption row, `CAPTION_NOT_SELECTED` with a
  `pending` (unselected) caption row, `ELIGIBLE` once selected, and that the
  eligible result carries the *selected* caption text, not the raw one.
  Also confirmed the two pre-existing, already-`sent` production tasks
  (ids 5, 74) still report `ALREADY_HANDED_OFF`, the graphic-source task
  still reports `UNSUPPORTED_PLATFORM`, and an `APPROVED` (not
  `PRODUCTION_READY`) task still reports `INVALID_STATUS` — i.e. no
  behavior change for anything already in the system.
- `listQueue()` run directly against the real local dev database (read-only)
  and via the new API endpoint/page with a simulated authenticated admin
  session (CLI, not a real browser) — correct rows, correct `clientId`/
  `status`/`fromDate` filtering, no PHP warnings/notices/fatals in the
  rendered output.
- Browser verification (a real logged-in browser session) was **not**
  performed — no such session was available in this environment.

---

## 31. AI Configuration page — now DB-backed, editable (2026-09-18, updated same day)

Started as a read-only status page (see original text below the update note); extended the same day into a full admin configuration UI so a real deployment never needs a code/env edit to set the AI provider or API key.

**New central config layer**: `includes/AiCaptionConfig.php` — `getAiCaptionConfig()` (DB row, falling back per-field to the pre-existing env vars when the DB has no value — `provider` is the only field fully resolved here, since `CaptionGeneratorFactory`'s `switch` needs a definitive value; `anthropicModel`/`ollamaHost`/`ollamaModel`/`anthropicApiKey` are passed through as-is when set, or `null`, letting each generator's own existing constructor fallback chain — unchanged — handle env/default) and `saveAiCaptionConfig()` (validates + persists). Single active-row table, `aiCaptionSettings` — identical shape/pattern to `instagramSettings` (`ensureXTable()`/`isActive`/`ORDER BY id DESC LIMIT 1`).

**Secret storage reuses the existing mechanism, no new one**: the API key is encrypted at rest with `includes/Crypto.php`'s `encryptSecret()`/`decryptSecret()` (AES-256-CBC via the existing `ENCRYPTION_KEY` constant) — the exact same function `instagramSettings.metaAppSecret` and `instagramAccounts.accessToken` already use. A blank submitted key means "keep the currently stored key" (identical convention to `saveInstagramSettings()`), which is how the UI lets an admin replace a key without the masked field ever revealing or round-tripping the old value.

`CaptionGeneratorFactory::make()`'s public signature/call site is unchanged (`SocialContentCaptionEngine` still calls it exactly as before) — internally it now calls `getAiCaptionConfig()` and passes the resolved values into the constructors instead of reading `getenv('MODLUS_AI_CAPTION_PROVIDER')` itself. `AnthropicCaptionGenerator` gained a second constructor parameter, `$model` (was a hardcoded `const MODEL`, now `DEFAULT_MODEL` + an instance property, mirroring how `OllamaCaptionGenerator` already handled its own model), so its model is configurable too.

The page (`pages/ai-configuration.php`) now has: a Provider dropdown, dynamic Anthropic (API Key, Model) / Ollama (Host, Model) fields, Save Configuration + Test Connection buttons, and a Status area. New endpoints `api/social-content-caption/get-ai-config.php` (GET, session-gated, returns the safe shape — `hasAnthropicApiKey` boolean, never the key) and `save-ai-config.php` (POST, session + CSRF + validation, identical gate pattern to every sibling Caption Area endpoint). `test-connection.php` needed zero changes — it already calls `CaptionGeneratorFactory::make()->testConnection()`, which is now transparently DB-aware.

**Validation**: provider is a strict allow-list (`anthropic`/`ollama`); `ollamaHost` must be `FILTER_VALIDATE_URL` **and** `http`/`https` scheme (rejects `file://` etc.) before being used server-side to make an outbound request — this endpoint is already admin-only, so this is proportionate, not a full network allow-list. Model strings are length-capped (100 chars, matching the column).

**Genuinely unavoidable external requirement**: `ENCRYPTION_KEY` (`includes/config.php`, from `MODLUS_ENCRYPTION_KEY` env var, falling back to a hardcoded `'default_key_123'` if unset). This is what makes the stored API key encryption meaningful — **a production deployment must set `MODLUS_ENCRYPTION_KEY` to a real, private, server-only value** (never committed to git). If it's left at the fallback, encryption still runs, but with a key that's public (it's in this repo's source). This is a pre-existing constant shared with Instagram's own secret storage, not something newly introduced here — but it is the one real external secret this feature depends on.

### Testing performed (DB-backed config, real end-to-end)

- `php -l` clean on all 9 touched/new PHP files; `node --check` clean on the page's inline script.
- Full real HTTP sequence with a real admin session: loaded the page with **no** configuration (confirmed clean fallback-to-env state); saved a real Ollama config (host/model) and ran a **real** Test Connection against the actual local Ollama server (success); generated a **real** caption for a real production task through the new DB-backed config and selected it (both succeeded exactly as before); replaced the Ollama config (different model string) — confirmed exactly one row in `aiCaptionSettings` throughout, never duplicated; saved a (fake, test-only) Anthropic key + model — confirmed the raw DB column is ciphertext (not the plaintext key), confirmed `get-ai-config.php`'s response has no `anthropicApiKey` key at all, confirmed Test Connection against the fake key fails cleanly ("check the API key") without throwing; saved again with a **blank** key and confirmed the previously-stored key was preserved, not cleared; confirmed an unauthenticated save request is rejected and a session-valid-but-CSRF-missing save request is rejected **and made no DB change**; confirmed `error_log` output for a failed connection contains no key material, only a generic exception name.
- Confirmed no regression: the real Automation Queue still correctly reflects an unrelated real task's prior state after all of the above.
- Not exercised: Anthropic's real-success path (no valid `ANTHROPIC_API_KEY`/real key available in this environment) — the failure/invalid-key paths use the identical SDK call already proven to work when a real key exists (§29).
- Browser verification (a real logged-in browser session) was **not** performed — no such session was available in this environment.

---

### Original read-only version (superseded by the above, kept for history)

Admin-only status/health-check page (`/ai-configuration`, sidebar under "Social Media" next to Caption Area) for the AI provider Caption Area already uses. Purely a read view over `CaptionGeneratorFactory` — no database, no API-key input, no second config system.

`CaptionGeneratorInterface` gained one new method, `testConnection(): array` — a lightweight health check (never a real caption generation, never throws): Anthropic calls `models->list(limit: 1)` (validates the key at ~zero cost); Ollama calls `GET /api/tags` (confirms the server is reachable and whether the configured model is actually pulled). Both classes also gained small read-only getters (`getModel()`, `isConfigured()` / `getHost()`) so the page can display the *already-resolved* config instead of re-deriving `getenv()` logic itself.

---

## 30. Phase 10 — Automation Queue root-cause fix: caption selection didn't surface anything

Real bug, found live: a real production task (id 2) had reached `PRODUCTION_READY` with a real `selected` caption, but never appeared on `/social-automation`. Root cause was `listQueue()`'s own `FROM` clause — it was `FROM socialContentAutomationHandoff`, i.e. a task only appeared *after* someone manually clicked "Send to Automation" on the Production Queue page. Caption selection was never wired to queue membership at all; it only gated the *action* (§29), not visibility.

### Fix

`listQueue()` is now driven `FROM socialContentProduction p`, `INNER JOIN clientSocialContent c`, `LEFT JOIN socialContentCaption cap` / `socialContentAutomationHandoff h` / `socialPosts sp`. Membership: `p.sourceType='social' AND p.status='PRODUCTION_READY' AND platform IN self::ELIGIBLE_PLATFORMS AND (cap.status='selected' OR h.id IS NOT NULL)`. The `OR h.id IS NOT NULL` half is deliberate — it preserves the two real handoffs (ids 5, 74) created before the caption gate existed, which have no caption row at all; without it they'd silently vanish from the queue, which would have been a regression disguised as a fix. No new status column, no new table — `'eligible'` is a computed display value (`COALESCE(sp.status, h.status, 'eligible')`), never stored.

A row with no handoff yet gets its media preview from `resolveMedia()` (the same method `resolveAndRegisterHandoff()` itself calls before ever creating a `socialPosts` row) rather than `sp.mediaUrl`, since no post row exists yet to read it from. This is read-only/best-effort for display — a resolution failure (e.g. a non-JPEG submission) just means no thumbnail; the real, actionable error still only surfaces from Send to Automation itself, unchanged.

`pages/social-automation.php`: added an "Eligible (Not Sent)" status filter option and badge, and the Action column now shows "Send to Automation" (not just "Retry") for `eligible` rows, reusing the exact same click handler and `send-to-automation.php` endpoint — no new API.

Not touched: `checkEligibility()`, `resolveAndRegisterHandoff()`, `registerHandoff()`, `resolveMedia()`, the client filter (already existed, already backed by `api/client/getClients.php` — the same source Caption Area uses), `SocialContentProductionEngine`, `SocialContentCaptionEngine`, `InstagramAutomation.php`/`FacebookPublisher.php`/`cron/instagramScheduler.php`, and no schema change.

### Testing performed

- `php -l` clean on both changed files; `node --check` clean on the extracted inline script.
- Direct `listQueue()` calls against the real local dev database: the real caption-selected-but-never-sent task (id 2) now appears with `status='eligible'`; the two real pre-existing handoffs (ids 5, 74, still `scheduled`, no caption row) remain visible, unchanged, not duplicated; `clientId`/`status` (including the new `'eligible'` value) filters all verified correct.
- A temporary task with `PRODUCTION_READY` + no caption row confirmed **excluded** from the queue (and `checkEligibility()` still independently reports `CAPTION_NOT_SELECTED`).
- A temporary task with a real JPEG submission and a `selected` caption, exercised through the *real* `resolveAndRegisterHandoff()`: appeared as `eligible` with the correct media preview and the correct selected-caption text; after sending, `socialPosts.caption` was verified to equal the selected caption (not the raw Data-Entry one); the same task then appeared exactly once (not twice) with `status='scheduled'`, and exactly one `socialContentAutomationHandoff` row existed for it. All temporary rows deleted afterward; every table's row count verified back to its pre-test value.
- Real end-to-end HTTP verification (real session, real page load, real API) confirmed the same live task (id 2) appears with `status='eligible'`, correct client/platform/caption; `clientId`/`status` query-string filters and the served page's client dropdown (same `api/client/getClients.php` Caption Area uses) all verified over real HTTP.
- Browser verification (a real logged-in browser session) was **not** performed — no such session was available in this environment.
