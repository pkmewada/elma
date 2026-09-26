# Instagram Automation — Module Flow Documentation

Last Updated: 2026-08-22 (Phase 3.1 Production Hardening)

## 1. Document Purpose

This documents how the Instagram Automation module works internally: OAuth
connection, media upload, publishing, the cron scheduler, token handling, and
error handling.

It exists mainly because `includes/InstagramAutomation.php` is a single
shared helper file that Phase 3 (analytics, comments, webhooks) will extend.
Read this before changing that file — several behaviors below (transient vs.
permanent error handling, the single-instance cron lock, the JPEG-only
requirement, the client-scoping added in Phase 2.5) are not obvious from the
code alone and exist to fix specific bugs or requirements found along the way.

**Phase 2.5 changed the core architecture**: the module is no longer
single-account. Every Instagram account belongs to a Modlus client
(`clientMaster`), and every post belongs to both a client and one of that
client's specific accounts. If you're reading this to understand "which
account does a post publish through," the answer is now always **"its own
`instagramAccountId`, looked up per-post"** — never "whichever account
connected most recently." See §4, §7, and §10.

**Phase 3 (Analytics, Comments, Webhooks) is implemented.** §12–§14 document
how it actually works, built to one settled client/account ownership model
(§11) instead of each feature inventing its own. If you're about to extend
any of it further, read §11 first — it's the single most important section
in this document for avoiding a regression back to single-account-style
logic, and it's exactly what caught the pattern Phase 3 had to avoid.

## 2. File Map

| File | Role |
| --- | --- |
| `includes/InstagramAutomation.php` | All domain logic: settings, accounts, posts, media upload, Meta Graph API calls. Everything below routes through this file. |
| `includes/Crypto.php` | `encryptSecret()` / `decryptSecret()` — AES-256-CBC, used for the Meta App Secret and account access tokens. Never Instagram-specific; reusable by any module. |
| `includes/Csrf.php` | `generateCsrfToken()` / `requireValidCsrfToken()` — reusable app-wide CSRF helper. All Instagram POST endpoints use it. |
| `pages/instagram-automation.php` | Settings page: Meta App ID / Secret / Redirect URL, "Connect Instagram Account" button, connected-accounts list. |
| `pages/instagram-create-post.php` | Create/edit a post: media upload, caption, draft or schedule. |
| `pages/instagram-scheduled-posts.php` | List of all posts (draft/scheduled/publishing/published/failed) with filter, edit, delete. |
| `api/instagram/getInstagramSettings.php`, `api/instagram/saveInstagramSettings.php` | Settings CRUD. |
| `api/instagram/instagramOauthStart.php`, `api/instagram/instagramOauthCallback.php` | OAuth connect flow (see §4). |
| `api/instagram/disconnectInstagramAccount.php` | Manually disconnect an account. |
| `api/getInstagramPosts.php`, `api/saveInstagramPost.php`, `api/deleteInstagramPost.php` | Post CRUD. |
| `cron/instagramScheduler.php` | CLI-only scheduler that actually publishes to Meta (see §7). |
| `includes/InstagramInsights.php` | Phase 3: analytics domain logic (§12). `require_once`s `InstagramAutomation.php` for shared primitives — does not duplicate them. |
| `includes/InstagramComments.php` | Phase 3: comments domain logic + real Meta reply/hide calls (§13). Same reuse pattern. |
| `includes/InstagramWebhooks.php` | Phase 3: webhook event storage + signature verification (§14). Same reuse pattern. |
| `api/instagram/instagramWebhook.php` | Phase 3: the Meta-facing webhook receiver — the one endpoint in this module without `includes/auth.php` (§14). |
| `api/instagram/getInstagramComments.php`, `api/instagram/replyInstagramComment.php`, `api/instagram/hideInstagramComment.php` | Phase 3: comment admin actions (§13). |
| `api/instagram/getInstagramInsights.php` | Phase 3: analytics read endpoint (§12). |
| `api/instagram/getInstagramWebhookEvents.php` | Phase 3.1: admin-facing read of recent webhook events, incl. failures (§16, Task 5 error visibility). |
| `cron/instagramAnalyticsSync.php` | Phase 3: CLI-only, separately-scheduled sync that populates `instagramInsights` (§12); Phase 3.1 added `markInstagramAccountAnalyticsSync()` calls for last-sync status. |
| `pages/instagram-comments.php`, `pages/instagram-analytics.php` | Phase 3 UI — both use the same client → account cascading-selector pattern as `instagram-create-post.php`. Phase 3.1 added a sync-status banner and a "Recent Webhook Events" card to the analytics page. |
| `database/migrations/2026-08-22-instagram-automation-tables.sql` | `instagramSettings`, `instagramAccounts` + route registration. |
| `database/migrations/2026-08-22-instagram-posts-tables.sql` | `instagramPosts` + route registration for the create/list pages. |
| `database/migrations/2026-08-22-instagram-multi-client.sql` | Phase 2.5: adds `clientId` to `instagramAccounts`, `clientId` + `instagramAccountId` to `instagramPosts`, with real FK constraints to `clientMaster`/`instagramAccounts` (`ON DELETE CASCADE`). |
| `database/migrations/2026-08-22-instagram-phase3-tables.sql` | Phase 3: `instagramInsights`, `instagramComments`, `instagramWebhookEvents`, `instagramSettings.webhookVerifyToken`, + route registration for the two new pages. |
| `database/migrations/2026-08-22-instagram-phase31-hardening.sql` | Phase 3.1: adds `instagramAccounts.lastAnalyticsSyncAt` / `lastAnalyticsSyncError`. |
| `docs/instagram-automation-production-checklist.md` | Phase 3.1: pre-launch checklist (Meta setup, server setup, security, testing). |
| `docs/instagram-automation-readme.md` | Phase 3.1: standalone module README — architecture, complete flow, troubleshooting, roadmap. Start here if you're new to this module; come to this file (`instagram-automation-flow.md`) for the internals once you need them. |

## 3. Database Schema

### `instagramSettings` (single active row)

Meta App ID/Secret/Redirect URL. `metaAppSecret` is stored encrypted
(`encryptSecret()`), never returned to the client — `getInstagramSettings()`
only returns a `hasAppSecret` boolean.

### `instagramAccounts` (one row per connected IG Business account)

| Column | Notes |
| --- | --- |
| `clientId` | FK → `clientMaster(id)`, `ON DELETE CASCADE`. **Nullable** at the DB level (see §10, "Migration leaves pre-existing rows unassigned") but every write path (`saveInstagramAccountFromOAuth()`) requires a real one — `instagramClientExists()` gates it before the OAuth flow even starts. |
| `instagramUserId` | Instagram Business Account id, **unique key** — reconnecting the same account updates the existing row rather than duplicating it. Global uniqueness is intentional: a real IG Business account should only ever belong to one Modlus client at a time. |
| `facebookPageId` | The linked Facebook Page id. |
| `accessToken` | The **Page** access token, encrypted. Never returned to the client. |
| `tokenExpiry` | Populated only if Meta ever returns an expiry; usually `NULL` (see §8). |
| `lastAnalyticsSyncAt` / `lastAnalyticsSyncError` | Added in Phase 3.1 for admin error visibility. Written by `markInstagramAccountAnalyticsSync()` after every `cron/instagramAnalyticsSync.php` attempt for this account — `lastAnalyticsSyncError` is `NULL` on success (clears any prior error) and set on failure. Same "each row carries its own last-known-state" convention as `instagramPosts.status`/`errorMessage`, not a separate log table. Surfaced on `pages/instagram-analytics.php`. |
| `status` | `connected` \| `disconnected`. |

### `instagramPosts` (one row per post)

| Column | Notes |
| --- | --- |
| `clientId` | FK → `clientMaster(id)`, `ON DELETE CASCADE`. Required on every save (`api/saveInstagramPost.php` rejects a missing/invalid one). |
| `instagramAccountId` | FK → `instagramAccounts(id)`, `ON DELETE CASCADE`. **Must belong to `clientId`** — enforced by `instagramAccountBelongsToClient()` in `saveInstagramPost.php`, the actual mechanism that makes cross-client publishing impossible. This is checked at save time, not just at cron time. |
| `mediaType` | `image` \| `reel` \| `carousel`. |
| `mediaUrl` | **Always a JSON array of relative paths**, e.g. `["uploads/instagram-posts/ig_....jpg"]`, even for a single image. Carousel stores 2–10 entries. Absolute URLs are built at read/publish time via `instagramPostMediaAbsoluteUrls()` — never baked into the DB — so the row survives moving between dev/prod hostnames. |
| `status` | `draft` → `scheduled` → `publishing` → `published` \| `failed` (see §7 for the transitions). |
| `scheduledAt` | `NULL` for drafts. Set to "now" when a user schedules without picking a time (immediate/"Publish Now" case). |
| `instagramMediaId` | Dual-purpose: holds the **video container id** while a reel is mid-processing (`status = publishing`), then gets overwritten with the **final published media id** once `media_publish` succeeds. Don't assume it's always the final id — check `status` first. |
| `errorMessage` | Set on `failed`, cleared on successful (re)publish. Surfaced via "View Error" on the list page. |

**`instagramSettings` (Meta App ID/Secret/Redirect URL) is deliberately NOT
client-scoped** — it's one Meta Developer App shared by the whole Modlus
platform; individual clients' Facebook Pages/Instagram accounts authorize
against that same app via OAuth. Don't add `clientId` there without a real
reason — the Phase 2.5 spec explicitly scoped only `instagramAccounts` and
`instagramPosts`.

### Phase 3 Tables (implemented)

**Status update: implemented.** These three tables were originally
documented here as architecture preparation ahead of code; they're now real
— migrated via `database/migrations/2026-08-22-instagram-phase3-tables.sql`
and backed by `includes/InstagramInsights.php`, `includes/InstagramComments.php`,
and `includes/InstagramWebhooks.php` respectively (each with its own
`ensureXTable()` + `xEnsureColumn()` self-healing pair, not folded into
`InstagramAutomation.php` — see the Change Log's "Phase 3 Implementation"
entry for the file-split rationale). The column tables below reflect the
final, as-built schema — two details changed from the original draft during
implementation review, called out inline: `instagramComments.postId` became
nullable with `instagramMediaId` added, and `instagramWebhookEvents`' FKs
use `ON DELETE SET NULL` instead of `CASCADE`.

#### `instagramInsights` (implemented)

| Column | Notes |
| --- | --- |
| `clientId` | FK → `clientMaster(id)`, `ON DELETE CASCADE`. Required — every metric row belongs to exactly one client, same rule as `instagramAccounts`/`instagramPosts`. |
| `instagramAccountId` | FK → `instagramAccounts(id)`, `ON DELETE CASCADE`. Required — must belong to `clientId` (reuse `instagramAccountBelongsToClient()`, don't re-derive this check). |
| `postId` | FK → `instagramPosts(id)`, **nullable**. `NULL` = account-level metric (e.g. followers, account reach). Set = post-level metric (e.g. post reach, engagement). One table serves both cases via this nullable column rather than two parallel tables — mirrors how `instagramPosts.instagramMediaId` already does double duty by state rather than splitting into separate columns. |
| `metricName` | e.g. `followers_count`, `media_count`, `reach`, `profile_views` (account-level); `reach`, `impressions`, `plays`, `likes`, `comments`, `saved` (post-level). Flexible free-form column (approved Decision E) rather than fixed metric columns — adding a new metric later is a new row, not an `ALTER TABLE`. |
| `metricValue` | `BIGINT`. |
| `period` | Meta's own granularity — `day` for account-level, `lifetime` for post-level (post metrics don't change per-day the way account metrics do). |
| `capturedAt` | `DATE` — one row per (account-or-post, metric, day); re-syncing the same day updates the existing row in place (`saveInstagramInsightMetric()` does a NULL-safe `postId <=> ?` lookup first) rather than relying on a DB `UNIQUE` constraint, which MySQL's NULL-in-unique-key semantics would make unreliable for the nullable `postId` case. |

#### `instagramComments` (implemented — revised from the original draft during Phase 3 approval)

| Column | Notes |
| --- | --- |
| `clientId` | FK → `clientMaster(id)`, `ON DELETE CASCADE`. Required. |
| `instagramAccountId` | FK → `instagramAccounts(id)`, `ON DELETE CASCADE`. Required — must belong to `clientId`. |
| `instagramMediaId` | Meta's id for the Instagram post/media the comment is on. **Always present** — this, not `postId`, is the reliable reference to "which Instagram post," because Meta's webhook payload always includes it regardless of whether Modlus published that post. |
| `postId` | FK → `instagramPosts(id)`, **nullable** (revised from the original "always required" draft — see rationale below). |
| `instagramCommentId` | Meta's own comment id, **unique key** — the resolution key. A webhook event only carries `instagramCommentId`/the IG object id, never a Modlus `clientId` directly; **`instagramAccountId` is the join key that recovers `clientId`** (see §13/§14 below). |

**Why `postId` is nullable**: a comment can exist on an Instagram post that was published outside Modlus entirely (before this account was connected, or posted manually/via another tool) — there is no `instagramPosts` row to point `postId` at, but the comment must still be stored and manageable. `postId` is populated on a best-effort basis by matching `instagramMediaId` against `instagramPosts.instagramMediaId` for the same `instagramAccountId` (`resolveInstagramPostIdByMediaId()`); when no match exists, `postId` stays `NULL` and the comment is still fully usable (viewable, reply-able, hide-able) via `instagramMediaId` alone. This is a narrower, deliberate exception to §11's traceability rule — `clientId` and `instagramAccountId` remain required and resolved *before* storage exactly as §11 mandates; only the *optional* third link (a specific `instagramPosts` row) is allowed to be absent, precisely because §11 already documents post-level traceability as "optional" (see §11, point 3).

#### `instagramWebhookEvents` (implemented)

| Column | Notes |
| --- | --- |
| `id` | PK. |
| `clientId` | FK → `clientMaster(id)`, **`ON DELETE SET NULL`** (not `CASCADE`, unlike every other Phase 2.5/3 FK) — deliberate: this table's purpose is safe debugging history, which should survive a client/account being deleted rather than disappearing with it. Nullable — resolved from the webhook payload's Instagram object **before** the row is written when possible, but stays `NULL` when the account can't be identified at all (never left for later processing to figure out — see §14). |
| `instagramAccountId` | FK → `instagramAccounts(id)`, `ON DELETE SET NULL`. Same nullability/resolution-timing rule as `clientId`. |
| `eventType` | e.g. `comments`, future `messages`. Free-form `VARCHAR`, not an enum. |
| `payload` | Raw Meta payload, stored as-received (for debugging/replay — don't pre-parse away fields you don't use yet). |
| `status` | `received` → `processed` \| `failed` — mirrors the `instagramPosts.status` state-machine convention already established rather than inventing a different vocabulary. |
| `errorMessage` | Set on `failed` — mirrors `instagramPosts.errorMessage`. |
| `processedAt` | When the event left `received`. |
| `createdAt` | Standard timestamp, matching every other table's convention in this module. |

## 4. OAuth Flow

```
User selects a Client from the dropdown (pages/instagram-automation.php,
  populated via the existing api/client/getClients.php — same client-selector
  pattern as client-deliverable.js / calendar.php)
  ↓
User clicks "Connect Instagram Account"
  → GET api/instagram/instagramOauthStart.php?clientId=X
      - validates clientId via instagramClientExists()
      - loads Meta App ID/Secret from instagramSettings (global, not per-client)
      - generates a random `state`, stores it AND clientId in session
        ($_SESSION['instagramOauthState'], $_SESSION['instagramOauthClientId'])
      - redirects to https://www.facebook.com/v19.0/dialog/oauth?...

User authorizes on Meta → Meta redirects back to
  → GET api/instagram/instagramOauthCallback.php?code=...&state=...
      1. validates `state` against session (CSRF protection for the OAuth flow itself)
      2. re-validates the session's clientId is still a real client
      3. exchanges `code` for a short-lived user access token
      4. exchanges that for a LONG-LIVED user access token
         (grant_type=fb_exchange_token — see §8, this step is load-bearing)
      5. calls /me/accounts to list the user's Facebook Pages + their Page access tokens
      6. for each Page with a linked instagram_business_account:
         fetches the IG username, then upserts a row into instagramAccounts
         WITH the session's clientId (saveInstagramAccountFromOAuth() — keyed
         by instagramUserId, so reconnecting the same IG account updates
         rather than duplicates, and can move it to a different client if
         reconnected under a different clientId)
      7. audit-logs the connection including the client label, redirects back to
         /instagram-automation?igStatus=success|error&igMessage=...&clientId=X
         (clientId round-trips back so the UI restores the selected client)
```

The settings page reads `igStatus`/`igMessage`/`clientId` from the URL on
load, restores the client selection, shows a toast, then strips those query
params from the address bar.

## 5. Upload Flow

`saveInstagramMediaFile()` / `saveInstagramMediaFiles()` in
`includes/InstagramAutomation.php` mirror the validation style already used
elsewhere in Modlus (`LeadEngine::uploadFile()`):

1. Real content-type detection via `finfo_file()` — **never** trusts the
   client-supplied `$_FILES[...]['type']` or the file extension.
2. Whitelist:
   - Images (image posts + carousel items): **JPEG only**, ≤ 8 MB.
     PNG is intentionally rejected — Meta's Content Publishing API only
     accepts JPEG for `image_url`-based posts; PNG would pass our own
     validation and then fail confusingly at Meta's end.
   - Video (reels): MP4 or MOV (QuickTime), ≤ 100 MB.
3. Filename: `uniqid('ig_', true) . '_' . time() . '.' . $ext` — collision-safe,
   doesn't leak the original filename.
4. Saved to `uploads/instagram-posts/` — this folder is publicly
   web-accessible with no auth gate (same as every other `uploads/*`
   subfolder in this app), which is required: Meta's servers fetch media
   directly from the URL you give them, they don't accept file uploads.

`api/saveInstagramPost.php` enforces one more rule not inside the upload
helper itself: **changing a post's `mediaType` on edit always requires a
fresh upload.** Existing media is only carried forward when the stored
`mediaType` matches the one being submitted — otherwise a reel could end up
with a stale JPEG as its "video" and fail obscurely at Meta.

## 6. Publishing Flow

All three publish functions route through the **single** curl wrapper,
`instagramGraphApiRequest()` — do not add a second curl implementation for
a new media type; extend this one instead.

```
publishInstagramImagePost($account, $imageUrl, $caption)
  → POST /{ig-user-id}/media          (image_url + caption)
  → POST /{ig-user-id}/media_publish  (creation_id)

publishInstagramCarouselPost($account, $imageUrls[], $caption)
  → POST /{ig-user-id}/media  once per image, is_carousel_item=true  → child container ids
  → POST /{ig-user-id}/media          (media_type=CAROUSEL, children=id1,id2,..., caption)
  → POST /{ig-user-id}/media_publish  (creation_id)
  requires 2–10 images; enforced both client-side and server-side.

publishInstagramVideoPost($account, $videoUrl, $caption)
  → POST /{ig-user-id}/media  (media_type=REELS, video_url + caption) → container id
  → GET  /{container-id}?fields=status_code   (checked once immediately)
     - FINISHED → publish right away, return {status: 'published', instagramMediaId}
     - anything else → return {status: 'pending', instagramMediaId: containerId}
       (almost always this branch — Meta hasn't finished transcoding yet)
```

Reels are the only asynchronous case. The scheduler (§7 below) is what
actually finishes a "pending" reel on a later run — `publishInstagramVideoPost()`
never blocks/polls/sleeps waiting for Meta.

All three publish functions receive an `$account` array that the caller
resolved for **that specific post** (`getInstagramAccountById()`) — never a
shared/ambient "current account". Keep it that way if you extend this
section; passing a global account back in would reintroduce the pre-2.5 bug.

## 7. Cron Flow

`cron/instagramScheduler.php` — **CLI-only** (`PHP_SAPI !== 'cli'` → HTTP 403).
Must be wired to an external scheduler (Windows Task Scheduler locally,
cPanel cron in production — same mechanism as the other scripts already in
`cron/`) running every 1–2 minutes for scheduling to feel timely.

```
1. Acquire an flock() lock on cron/.instagramScheduler.lock (LOCK_EX | LOCK_NB).
   If already held, log and exit immediately — this is what prevents two
   overlapping runs from double-publishing the same post if one run takes
   longer than the scheduler interval.

2. Every post is loaded across ALL clients in one query (getInstagramPostsByStatus
   / getDueInstagramPosts have no clientId filter — the cron processes
   every client's due work in a single run, that's the point of it).
   For EACH post individually: loadInstagramAccountForPost() reads that
   post's own instagramAccountId and calls getInstagramAccountById() —
   status='connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW()).
   Results are cached per account id for the rest of the run ($accountCache)
   so posts sharing an account don't re-query it, but every account lookup
   is still scoped to that one post's instagramAccountId. If the account is
   missing/disconnected/expired, that ONE post fails immediately — it never
   falls back to a different account, and it never affects other clients'
   posts in the same run. (The old getPrimaryInstagramAccount() — "whichever
   account connected most recently" — no longer exists; do not reintroduce
   a global/ambient account lookup here.)

3. Phase A — finalize posts already in 'publishing' (in-flight reels):
   for each: getInstagramContainerStatus() using THAT post's account
     FINISHED      → publishInstagramContainer() → mark published, audit log
                      (audit message includes "for Client: {label}")
     ERROR/EXPIRED → mark failed, audit log
     IN_PROGRESS   → log only, retried next run
     (anything else, e.g. PUBLISHED) → mark failed for manual review
        — this branch exists specifically so a row can never get stuck
          retrying forever if Meta's state and ours disagree.

4. Phase B — publish newly due posts (status='scheduled' AND scheduledAt <= NOW()):
   mark 'publishing', then dispatch by mediaType to the matching publish*
   function from §6, using that post's own account. image/carousel finish
   synchronously; reel may leave the post in 'publishing' with
   instagramMediaId = container id for Phase A to pick up on the next run.

5. Every failure is caught and separated into two buckets (see §9):
   InstagramTransientApiException → requeue/retry, not a real failure
   everything else                → mark 'failed', errorMessage saved,
                                     audit-logged with client context, and if
                                     the error was Meta code 190
                                     (invalid/expired token), THAT SPECIFIC
                                     account is flipped to 'disconnected'
                                     (disconnectInstagramAccount(), reused
                                     from Phase 1) and evicted from
                                     $accountCache so any other due post
                                     sharing it in the same run fails too
                                     instead of reusing a stale "connected"
                                     copy — never affects a different
                                     client's account.

6. Release the lock (register_shutdown_function — happens even on fatal error).
```

There is no browser for cron to show a toast in — operational visibility is
`cron/instagramScheduler.log` (plain append-only log, same convention as
`cron/testCron.php`, every line now prefixed with `(Client: {label})`) plus
the DB `errorMessage` column, which the "Instagram Posts" list page surfaces
as a toast via "View Error".

## 8. Token Handling

- The token Meta issues right after OAuth login is short-lived (~1–2 hours).
  `api/instagram/instagramOauthCallback.php` immediately exchanges it for a
  **long-lived** user token (`grant_type=fb_exchange_token`) before deriving
  Page access tokens — Page tokens derived from a long-lived user token are
  effectively non-expiring. **This exchange is load-bearing**: without it,
  cron publishing starts failing within hours of connecting an account. If
  the exchange call itself fails, the flow falls back to the short-lived
  token rather than aborting the whole connection (logged as a caught
  exception, not surfaced to the user).
- `tokenExpiry` is usually left `NULL` — Meta doesn't reliably hand back an
  expiry for Page tokens derived this way, and fabricating one from the
  *user* token's `expires_in` would be misleading. `getInstagramAccountById()`
  still respects it if it's ever populated (`tokenExpiry IS NULL OR tokenExpiry > NOW()`) —
  an expired-token account simply isn't returned, and the post that needed it fails.
- The real safety net is **reactive, not proactive**: `instagramGraphApiRequest()`
  preserves Meta's numeric error code on the thrown exception
  (`new RuntimeException($message, $code)`). `isInstagramAuthError()` checks
  for code `190` (Meta's standard "invalid/expired OAuth token"). The cron
  calls this after any failure and, on a match, calls the existing
  `disconnectInstagramAccount()` — reusing Phase 1's function rather than
  adding a new one.
- Access tokens are **never** stored, logged, or returned to the client in
  plaintext — `encryptSecret()`/`decryptSecret()` from `includes/Crypto.php`
  wrap every read/write, and `getInstagramAccounts()` (used by the
  settings-page account list) never selects the `accessToken` column's
  decrypted value.

## 9. Error Handling

Two exception types flow out of `instagramGraphApiRequest()`, and the
distinction matters everywhere that calls it:

| Exception | Thrown when | Cron's response |
| --- | --- | --- |
| `InstagramTransientApiException` (extends `RuntimeException`) | `curl_exec()` itself failed — DNS, timeout, connection reset. Nothing was learned about whether Meta would accept the post. | **Not** a failure. Phase B calls `revertInstagramPostToScheduled()` (retry next run); Phase A just logs and leaves the row as `publishing`. |
| Plain `RuntimeException` | Meta returned a decoded JSON `error` object (bad request, invalid token, rejected media, etc.), or the response wasn't valid JSON at all. | Real failure. `markInstagramPostFailed()` + `errorMessage` + audit log. If the error code is `190`, also disconnects the account (§8). |

This split exists because, before the Phase 2 readiness review, a single
transient network blip during a cron run would permanently mark a
perfectly-postable post as `failed` — indistinguishable from Meta genuinely
rejecting it. There's intentionally **no retry-count ceiling** — a
persistently-failing transient post retries indefinitely rather than
eventually giving up; that's an accepted limitation (see §10), not an
oversight — adding a ceiling would need a schema change (a retry-count
column).

Everywhere else in the module (the `api/*.php` endpoints), error handling
follows the existing Modlus convention: `try { ... } catch (Throwable $e) {
respond(false, $e->getMessage()); }`, returning the caught message directly.
This is consistent with every other module's API endpoints in this
codebase, not something specific to Instagram Automation.

## 10. Known Limitations

- **No retry ceiling** on transient errors (see §9).
- **No per-employee client access restriction.** Modlus has no existing
  mechanism anywhere for "employee X can only see client Y's data" — access
  is purely route/action permission based (confirmed by auditing
  `includes/permission-helper.php` and grepping for any
  `assignedClientId`/`clientAccess` pattern — none exists). So Phase 2.5
  does not add one either: any user who can view the Instagram Automation
  routes sees every client's accounts and posts, exactly like the existing
  `client-deliverable.php` and `calendar.php` pages already behave. If
  Modlus ever adds real per-user client scoping, Instagram Automation should
  adopt the same mechanism — don't invent an Instagram-specific one.
- **Reconnecting an IG account under a different client silently moves it.**
  `saveInstagramAccountFromOAuth()` upserts by `instagramUserId`; if the same
  real Instagram Business account is connected while a *different* client is
  selected, its `clientId` is overwritten to the new client (last-OAuth-wins,
  consistent with how every other field on that upsert already behaves — not
  new behavior introduced here, just worth knowing).
- **Orphaned upload files:** if a post's DB save fails after a successful
  file upload, the file on disk isn't cleaned up. Cosmetic (disk usage
  only), not a correctness or security issue.
- **Migration leaves pre-existing rows unassigned.** If a deployed
  environment had `instagramAccounts`/`instagramPosts` rows before Phase 2.5
  (this repo's local dev copies were empty), those rows now have
  `clientId = NULL` and won't appear in any client-scoped view until an
  operator manually attributes them with a one-time manual `UPDATE`
  (`clientId` is nullable specifically to make this migration
  non-destructive — see `database/migrations/2026-08-22-instagram-multi-client.sql`
  for the full reasoning).
- **Narrow residual cron race:** the `flock()` lock fully prevents one
  overrunning run from overlapping the next scheduled tick. It does not
  protect against two *manually* triggered runs launched in the same
  instant outside the normal single-scheduler setup — considered acceptable
  residual risk. Unrelated to client-scoping; two posts for two different
  clients were never at risk of being mixed up, only a post being processed
  twice.

## 11. Multi Client Data Ownership Rules

This section exists specifically for Phase 3. Analytics, Comments, and
Webhooks are all new *readers and writers* of Instagram data that Phase 2.5
didn't have to think about — each one is a fresh opportunity to accidentally
reintroduce the exact bug Phase 2.5 fixed (an Instagram object resolved
against the wrong client, or against no client at all). Read this before
writing any Phase 3 code, not just before touching `InstagramAutomation.php`.

### Ownership hierarchy

```
Modlus CRM
    |
clientMaster                (the client — existing entity, unchanged)
    |
instagramAccounts           (clientId required — Phase 2.5)
    |
instagramPosts               (clientId + instagramAccountId required — Phase 2.5)
    |
Analytics / Comments / Webhooks   (clientId + instagramAccountId required — Phase 3, implemented)
```

### The traceability rule

**Every Instagram object a Phase 3 feature touches must be traceable to:**

1. A Modlus client (`clientId` → `clientMaster.id`)
2. An Instagram account (`instagramAccountId` → `instagramAccounts.id`, which
   itself is already traceable to a client)
3. Optionally, an Instagram post (`postId` → `instagramPosts.id`, for
   post-level data — comments and post-level insights have one; account-level
   insights don't)

If a piece of data can't be resolved to at least (1) and (2), it should not
be written to the database — not written with a `NULL` clientId "to fix
later," not attributed to whichever client happens to be selected in the UI
at the time. This is the same principle Phase 2.5 applied to `instagramPosts`
(§3) and enforced with `instagramAccountBelongsToClient()` — Phase 3 tables
should reuse that function, not reimplement the check.

### What Phase 3 must never do

No Phase 3 feature — analytics sync, comment fetch/reply, webhook processing,
or anything after — may use:

- **"Latest connected account"** logic (the exact `getPrimaryInstagramAccount()`
  pattern removed in Phase 2.5 — see §7's changelog note). There is no
  function like this in the codebase anymore; if a Phase 3 feature needs one,
  that's a sign the feature is missing a client/account selection step
  upstream, not a sign to add one back.
- **A "global" Instagram account** — i.e., code that queries `instagramAccounts`
  without a `clientId` (or `instagramAccountId`) filter and picks one to act
  on. `getInstagramAccounts($con, $clientId)` already supports an unscoped
  call (`$clientId = null`) for legitimate admin-overview listing (e.g. the
  settings page before a client is selected) — that's fine for *display*.
  It is never fine for *choosing which account to publish/fetch/reply
  through*.
- **"Primary account" logic** of any kind — no `ORDER BY id DESC LIMIT 1`
  substitute, no "the first connected account," no per-client "default
  account" shortcut unless a client can only ever have one account (they
  can't — §3 explicitly supports multiple accounts per client).

The correct pattern, already established by the cron (§7): resolve the
specific account a specific piece of work belongs to
(`getInstagramAccountById($con, $accountId)`), and derive `clientId` from
*that* account (`$account['clientId']`), not from session/UI state that could
be stale or manipulated.

## 12. Phase 3 Analytics Flow

**Status: implemented**, built to the ownership rules in §11.

`cron/instagramAnalyticsSync.php` — same CLI-only convention as
`cron/instagramScheduler.php` (§7): `PHP_SAPI !== 'cli'` guard, `flock()`
single-instance lock (its own `.instagramAnalyticsSync.lock`, separate from
the publish scheduler's), plain append-only log file.

```
Cron starts
  ↓
Fetch all connected Instagram accounts (across all clients — one sync run
  covers every client, same "one cron, many clients" shape as the publishing
  scheduler in §7, not one cron invocation per client)
  ↓
For each account:
  Resolve clientId                    ← account['clientId'], never re-derived
                                         or looked up any other way
  ↓
  Fetch Meta Insights API             ← through instagramGraphApiRequest(),
                                         the same single curl wrapper every
                                         other Graph API call in this module
                                         uses (§6) — do not add a second
                                         curl implementation for analytics
  ↓
  Store metrics into instagramInsights (§3) — every row written with BOTH
    clientId and instagramAccountId from the account just resolved above,
    postId set only for post-level metrics, NULL for account-level ones
  ↓
Display client-specific analytics — the UI layer queries instagramInsights
  filtered by clientId (and optionally instagramAccountId/postId), following
  the same clientId-filtered-list pattern already used by
  getInstagramAccounts($con, $clientId) and getInstagramPosts($con, $status,
  $clientId) (§3)
```

**Important: analytics must never mix data between clients.** Concretely,
this means:

- The sync cron loops per-account and writes each account's metrics with
  that account's own `clientId` — it never aggregates across accounts before
  attributing a client.
- Any analytics dashboard/API endpoint must filter by `clientId` server-side
  (in the SQL `WHERE`, like `getInstagramPosts()` already does) — never fetch
  everything and filter client-side, which would expose other clients' metric
  rows in the API response even if the UI only renders a subset.
- Account-level vs. post-level metrics are distinguished by `postId IS NULL`
  vs. `postId = X` in the same table (§3) — don't let "which account is this
  metric for" and "which post is this metric for" become two separately
  resolved/cached values that could drift apart from each other within a
  sync run.

### Supported metrics (verified against actual Graph API behavior — Phase 3.1)

| Scope | Metrics | Source |
| --- | --- | --- |
| Account | `followers_count`, `media_count` | IG User object **fields** (`GET /{ig-user-id}?fields=...`) — not `/insights`, these are plain object properties. |
| Account | `reach`, `profile_views` | `/insights?period=day` |
| Post (image/carousel) | `reach`, `likes`, `comments`, `saved`, `shares` | `/{media-id}/insights` |
| Post (reel) | `reach`, `plays`, `likes`, `comments`, `saved`, `shares` | `/{media-id}/insights` |

**`impressions` is intentionally not requested.** Meta has been deprecating
it for media-level insights in favor of `reach` — requesting a deprecated
or media-type-mismatched metric doesn't just skip that one metric, it fails
Meta's **entire** `/insights` call for every metric in the same request.
`fetchInstagramInsightsResilient()` (in `InstagramInsights.php`) exists
specifically to make this safe: it tries the full batch first (fast path),
and only on failure falls back to fetching each metric individually,
silently dropping ones that error rather than fabricating a value for them
— so re-adding `impressions` later for accounts where it's still available
is safe and won't risk the other metrics. Verified directly: a real Meta
API call with an invalid token correctly triggered the per-metric fallback
and returned an empty array rather than throwing or inventing values (see
the Change Log's Phase 3.1 entry for the exact test).

A network-level failure (`InstagramTransientApiException`) is **not**
retried per-metric — it's rethrown immediately so the caller's existing
transient-vs-permanent handling (§9) applies once per account/post, not
once per metric.

## 13. Phase 3 Comment Flow

**Status: implemented**, built to the ownership rules in §11.

### Admin-side: viewing comments

```
Admin
  ↓
Select Client              ← same api/client/getClients.php + dropdown pattern
                              already used by all three existing Instagram
                              Automation pages (§4's OAuth flow, the
                              create-post page, the posts list page)
  ↓
Select Instagram Account    ← scoped to the selected client, same
                              cascading-dropdown pattern already implemented
                              in pages/instagram-create-post.php (client
                              selector → getInstagramSettings.php?clientId=
                              → account selector)
  ↓
View Comments               ← instagramComments (§3) filtered by
                              clientId + instagramAccountId (and optionally
                              postId, to view one post's thread)
```

### Meta-side: a comment arriving via webhook

```
Meta Comment (via webhook — see §14 for how it reaches Modlus at all)
  ↓
Resolve instagramAccountId  ← from the webhook payload's Instagram object id,
                               looked up against instagramAccounts.instagramUserId
                               (the same column Phase 2.5 already keys
                               reconnection-upserts on — §3)
  ↓
Find clientId                ← instagramAccounts.clientId for the account
                               just resolved. This is the ENTIRE reason
                               instagramAccountId must be resolved first and
                               separately from clientId, never guessed or
                               defaulted — see §11's traceability rule and
                               §14's webhook flow.
  ↓
Store comment                ← instagramComments row written with clientId +
                               instagramAccountId + postId + instagramCommentId,
                               all four already resolved before the INSERT —
                               never write a comment row with clientId
                               deferred to "fill in later"
  ↓
Reply / Hide                 ← any admin action on a comment must re-validate
                               it belongs to the client currently selected in
                               the UI (instagramAccountBelongsToClient()-style
                               check, reused not reimplemented) before acting
                               on it via the Graph API — this is the Phase 3
                               equivalent of saveInstagramPost.php's
                               ownership check (§3), the actual mechanism,
                               not the UI's client selector, that must
                               prevent Client A's admin from replying to or
                               hiding Client B's comment.
```

## 14. Phase 3 Webhook Flow

**Status: implemented**, built to the ownership rules in §11.

```
Meta
  ↓
Webhook Endpoint             ← a new api/*.php file (or similar), publicly
                                reachable over HTTPS the way Meta's webhook
                                delivery requires — this is a deliberate
                                exception to every other Instagram Automation
                                endpoint requiring a logged-in session
                                (includes/auth.php), because Meta itself is
                                the caller, not a Modlus user
  ↓
Validate request              ← Meta's X-Hub-Signature-256 HMAC verification
                                against the Meta App Secret (instagramSettings,
                                §3 — decrypt with the existing
                                decryptSecret(), don't add a second secret
                                store for this). This is the webhook
                                equivalent of the OAuth state check in §4 —
                                a request that fails validation is never
                                processed, only logged/rejected.
  ↓
Identify Instagram Account    ← from the payload's Instagram object id,
                                against instagramAccounts.instagramUserId
                                (same lookup as §13's Meta-side comment flow)
  ↓
Resolve Modlus Client         ← instagramAccounts.clientId for the account
                                just identified — same non-negotiable
                                ordering as §13: account first, client
                                derived from it, never the reverse
  ↓
Store Event                   ← instagramWebhookEvents (§3) row, written with
                                clientId + instagramAccountId already
                                resolved, status='received', raw payload kept
                                as-is for debugging/replay
  ↓
Process Event                 ← dispatched by eventType; comment events route
                                into §13's "Meta-side" comment flow. Status
                                updates to 'processed' or 'failed' on the
                                same instagramWebhookEvents row (mirrors the
                                instagramPosts status-machine convention, §3)
```

**Initially supported:** comment events (`eventType = 'comments'`), feeding
into §13.

**Future:** messaging events (Instagram DMs) — not scoped for the initial
Phase 3 webhook implementation, but `instagramWebhookEvents.eventType` is
deliberately a free-form column (not a 2-value enum) specifically so adding
a `messages` event type later doesn't require a schema change, only a new
`case` in the event dispatcher. See also §15, point 4 (DM automation must
follow the same ownership model when it's eventually built).

## 15. Future Multi Client Considerations

1. **No employee-level client restrictions currently exist.** Confirmed
   during Phase 2.5 research (§10) and unchanged since: Modlus has no
   `assignedClientId`/`clientAccess`-style mechanism anywhere in the
   codebase. Every Phase 3 feature inherits this — any user who can view the
   relevant route sees every client's comments/analytics/webhook events, the
   same as accounts and posts already behave. If Modlus adds real per-user
   client scoping in the future, every Instagram Automation feature
   (Phase 1 through 3) should adopt that same mechanism together, not each
   invent its own.

2. **One Meta App is shared globally through `instagramSettings`.** Still
   true post-Phase-2.5 (§3) and still correct for Phase 3: analytics,
   comments, and webhooks all authenticate to Meta using the same single
   App ID/Secret pair every client's accounts already authorize against via
   OAuth (§4). Do not add per-client Meta App credentials without a real
   product reason — it was explicitly out of scope for Phase 2.5 and remains
   out of scope here.

3. **Instagram accounts belong to clients.** The core Phase 2.5 invariant
   (§3, §11) — restated here because it's the load-bearing assumption every
   Phase 3 data model in this document is built on. If it's ever weakened
   (e.g. an account shared between clients), every table added in §3's
   "Planned Phase 3 Tables" and every flow in §12–§14 needs to be
   re-examined, not just extended.

4. **Future DM automation must follow the same ownership model.** Instagram
   Direct Message automation is not part of Phase 3 as scoped by this
   document (see §14's "Future" note), but whenever it's built, it must
   resolve `instagramAccountId` first and derive `clientId` from it — the
   identical `clientId` + `instagramAccountId` pattern used by
   `instagramPosts` (§3), `instagramInsights`/`instagramComments`/
   `instagramWebhookEvents` (§3), and the flows in §12–§14. No new ownership
   model should be invented for messaging.

## 16. Security & Permissions Model

Consolidates and cross-references the security mechanisms documented
elsewhere in this file, plus the Phase 3.1 permission verification — one
place to check before touching anything security-relevant, rather than
hunting across §8/§9/§14.

### Encryption

`includes/Crypto.php`'s `encryptSecret()`/`decryptSecret()` (AES-256-CBC,
random IV per value, `ENCRYPTION_KEY` from `includes/config.php`) protects
every secret this module stores: `instagramSettings.metaAppSecret`,
`instagramAccounts.accessToken`. Nothing Instagram-specific about the
helper — reusable by any future module. Verified (Phase 2.5): encrypt then
decrypt round-trips to the exact original plaintext. `getInstagramAccounts()`
(the settings-page account list) never selects the `accessToken` column at
all — not just "doesn't decrypt it," the encrypted value never leaves the
database in that code path.

### CSRF

`includes/Csrf.php` (`generateCsrfToken()`, `requireValidCsrfToken()`) — the
app-wide helper, not Instagram-specific. Every state-changing Instagram
endpoint calls `requireValidCsrfToken()` immediately after the HTTP-method
check and before touching any input: `saveInstagramSettings.php`,
`disconnectInstagramAccount.php`, `saveInstagramPost.php`,
`deleteInstagramPost.php`, `replyInstagramComment.php`,
`hideInstagramComment.php`. The token travels via the `X-CSRF-Token` header
(set from the `CSRF_TOKEN` JS constant `includes/header.php` emits on every
page) — verified this doesn't break any existing page's unrelated AJAX
calls (Phase 1 testing: `CSRF_TOKEN` is additive, nothing else reads it).
**`api/instagram/instagramWebhook.php` intentionally has no CSRF check** — CSRF
protects session-authenticated browser requests; Meta's webhook has no
session and is protected by signature verification instead (see below).

### Token Handling

Full detail in §8. Summary: OAuth tokens are exchanged for a long-lived
version before being stored (§4/§8 — without this, cron publishing would
fail within hours). `isInstagramAuthError()` reactively detects Meta code
`190` (invalid/expired token) after any failed API call and auto-disconnects
the specific account, across every cron and API path that talks to Meta
(publishing, analytics sync, comment reply/hide) — verified end-to-end with
a real Meta API call in both the Phase 2.5 and Phase 3 test passes.

### Signature Validation (Webhook)

Full detail in §14; verified in Phase 3.1's testing (below). `X-Hub-Signature-256`
is HMAC-SHA256 of the raw request body keyed by the Meta App Secret, compared
with `hash_equals()` — constant-time, not `==`/`strcmp()`. A request that
fails this check is rejected with `403` before any database write, any
account resolution, or any processing — it never gets far enough to leak
whether a given Instagram account id is even known to this Modlus instance.

**Verified directly** (Phase 3 test pass, re-confirmed in Phase 3.1 review by
re-reading `api/instagram/instagramWebhook.php` and `includes/InstagramWebhooks.php`
line by line): a forged signature is rejected (`403`, logged, nothing
stored); a correctly-signed delivery is accepted (`200`) and processed. Also
confirmed by direct code review for this hardening pass:

- **No session dependency** — `api/instagram/instagramWebhook.php` never includes
  `includes/auth.php`, never touches `$_SESSION`.
- **No sensitive-value logging** — `instagramWebhookLog()` calls throughout
  the file log event ids, account ids, client labels, `eventType`, and Meta's
  own error messages; grepped for it explicitly and confirmed **no log line
  ever includes `$account['accessToken']`, the Meta App Secret, or the raw
  signature header**. The decrypted token exists only in the `$account`
  array in memory, used solely to build Graph API requests.
- **JSON/malformed-request handling** — `json_decode($rawBody, true)`
  failing (returns `null`, not an array) or `entry` being absent/non-array
  is checked explicitly and logged as "no parseable entries" rather than
  causing a fatal error or an undefined-index warning.
- **Idempotency — verified, and the two tables behave differently on
  purpose**: `instagramComments` upserts by the `instagramCommentId` unique
  key (`upsertInstagramCommentFromWebhook()`), so Meta's at-least-once
  delivery guarantee redelivering the same comment event is fully safe — no
  duplicate comment row, `postId` re-resolved harmlessly. `instagramWebhookEvents`
  is **deliberately not deduplicated** — every delivery attempt, including a
  Meta retry of an identical payload, gets its own row. This is correct for
  its stated purpose (a raw, append-only debugging/audit trail of what Meta
  actually sent and when) and was a conscious choice, not an oversight; it
  does mean the table grows with retries, not just unique events.

### Permission Checks (Phase 3.1 verification)

Verified by reading `routesMaster` directly and grepping every Instagram
Automation file for `hasActionPermission`/`requireActionPermission` calls —
**zero matches**. Every route below is gated **only** at the route level
(`hasRoutePermission($path, 'canView')`, enforced centrally by `routes.php`
before the page file even loads — see the main Modlus permission system,
`includes/permission-helper.php`):

| Route | `isPublic` | Gate |
| --- | --- | --- |
| `/instagram-automation` | 0 | Route `canView` only |
| `/instagram-create-post` | 0 | Route `canView` only |
| `/instagram-scheduled-posts` | 0 | Route `canView` only |
| `/instagram-comments` | 0 | Route `canView` only |
| `/instagram-analytics` | 0 | Route `canView` only |
| `api/instagram/instagramWebhook.php` | — | **Not session-gated at all** — signature verification is its entire security boundary (see above), by design |

**Practical consequence — verify this matches your expectations before
relying on it**: within a route a user can view, there is no further
separation of privilege. Concretely:

- **Settings**: anyone who can view `/instagram-automation` can both view
  *and edit* the Meta App ID/Secret, and connect/disconnect any client's
  Instagram account.
- **Publishing**: anyone who can view `/instagram-scheduled-posts` /
  `/instagram-create-post` can create, edit, delete, and schedule posts for
  **any** client (client selection is a UI convenience, not an access
  boundary — see §10, §15 point 1).
- **Analytics**: anyone who can view `/instagram-analytics` can view every
  client's metrics.
- **Comments**: anyone who can view `/instagram-comments` can reply to or
  hide any client's comments.

This is **not a Phase 3.1 regression** — it's the same route-level-only
model every Instagram Automation route has used since Phase 1, and it
matches how the rest of Modlus already works (`client-deliverable.php`,
`calendar.php` — confirmed during Phase 2.5 research, §10). Per this
phase's explicit instruction not to invent a new permission architecture:
**no change was made here.** If finer-grained control is ever needed (e.g.
"can edit Meta credentials" separate from "can view settings"), the
existing `permissionActions`/`hasActionPermission()` mechanism
(`includes/permission-helper.php`) is the established place to add it —
matching the pattern already used for `import_leads` and similar
sensitive actions elsewhere in Modlus — not a new mechanism.

## 17. Change Log

### 2026-08-22

- Phase 1: settings page, Meta credential storage (`Crypto.php`), account
  connection foundation (OAuth start/callback), `instagramSettings` +
  `instagramAccounts` tables, audit logging, global CSRF helper (`Csrf.php`).
- Phase 2: content creation, media upload, draft/scheduling, cron publisher,
  Meta Graph API publish functions for image/reel/carousel, `instagramPosts`
  table, Create Post + Instagram Posts list pages.
- Phase 2 production-readiness review: JPEG-only image validation,
  long-lived token exchange, error-code-aware auth-failure handling
  (auto-disconnect on Meta code 190), transient-vs-permanent error handling,
  single-instance cron lock, unexpected-container-status handling,
  media-type-change-forces-reupload, immediate-vs-scheduled UX messaging,
  no-connected-account warning banners.
- This document created.
- **Phase 2.5 (Multi-Client Architecture Upgrade)**: added `clientId` to
  `instagramAccounts` and `clientId` + `instagramAccountId` to
  `instagramPosts` (real FK constraints to `clientMaster`/`instagramAccounts`,
  `ON DELETE CASCADE`) — reused the exact client-scoping convention already
  established by `clientCalendarPlans`. Removed `getPrimaryInstagramAccount()`
  entirely; replaced with per-post `getInstagramAccountById()` lookups
  (`instagramGraphApiRequest` callers now always receive an account resolved
  for the specific post being processed, never an ambient/global one).
  Added `instagramAccountBelongsToClient()` — the function that actually
  makes cross-client publishing impossible, enforced in `saveInstagramPost.php`
  at save time. OAuth flow now requires a client selection before connecting
  (`instagramOauthStart.php?clientId=`), carried through session state to the
  callback. All three UI pages gained a client selector (reusing the existing
  `api/client/getClients.php` + `client-deliverable.js` dropdown pattern). Audit log
  messages and cron log lines now include `"for Client: {label}"` context
  throughout. Verified end-to-end with a real (CLI, temporary, cleaned-up)
  test: account-ownership guard functions, token encrypt/decrypt round-trip,
  and a full cron run against a real Meta API call (garbage token →
  real `Invalid OAuth access token` error → correct account auto-disconnect
  → correct `failed` status → correct client-attributed audit log entry).

### Phase 3 Preparation Documentation Update

- Extended architecture documentation for analytics/comments/webhooks.
- Defined client/account ownership rules for all future Instagram features
  (§11) — no Phase 3 feature may use "latest connected account," a "global"
  Instagram account, or "primary account" logic; every object must resolve
  `instagramAccountId` first and derive `clientId` from it, the same
  ordering Phase 2.5 already established for posts.
- Added planned Phase 3 database relationships (§3: `instagramInsights`,
  `instagramComments`, `instagramWebhookEvents` — none implemented yet, all
  documented against `clientMaster`/`instagramAccounts`/`instagramPosts` so
  a future migration has one settled shape to build instead of three
  independently-invented ones) and data flows (§12 Analytics, §13 Comments,
  §14 Webhooks).
- No code changed in this update — documentation only, ahead of actual
  Phase 3 implementation.

### Phase 3 Implementation (Analytics / Comments / Webhooks)

- **Split into three new dedicated files** (approved Decision A) —
  `includes/InstagramInsights.php`, `includes/InstagramComments.php`,
  `includes/InstagramWebhooks.php` — each `require_once`s the existing
  `includes/InstagramAutomation.php`/`Crypto.php` for shared primitives
  (`getInstagramAccountById()`, `instagramAccountBelongsToClient()`,
  `getInstagramClientLabel()`, `instagramGraphApiRequest()`,
  `encryptSecret()`/`decryptSecret()`) rather than duplicating them.
  `InstagramAutomation.php` itself only grew by the `webhookVerifyToken`
  settings field (needed for the webhook GET handshake, not a new concern).
- **`instagramComments.postId` is nullable, `instagramMediaId` was added**
  (approved Decision B, revised from the original draft in §3) — a comment
  on an Instagram post published outside Modlus is still stored (`postId =
  NULL`), resolved by `resolveInstagramPostIdByMediaId()` matching
  `instagramMediaId` against `instagramPosts.instagramMediaId` on a
  best-effort basis.
- **`instagramWebhookEvents.clientId`/`instagramAccountId` stayed nullable**
  with `ON DELETE SET NULL` (not CASCADE, unlike every other Phase 2.5/3
  FK) — approved Decision C, so webhook debugging history survives a
  client/account being deleted later.
- **`api/instagram/instagramWebhook.php`** — the one endpoint in this module without
  `includes/auth.php` (approved Decision D). GET handles Meta's
  `hub_verify_token` handshake; POST verifies `X-Hub-Signature-256` via
  `verifyMetaWebhookSignature()` (HMAC-SHA256, `hash_equals()`) before
  touching anything. Always responds 200 to Meta once the signature is
  valid — internal processing failures are caught per-entry/per-change and
  recorded in `instagramWebhookEvents.errorMessage`, never surfaced as a
  non-200 response (which would trigger Meta retries/backoff).
- **`instagramInsights` uses the flexible `(metricName, metricValue,
  period, capturedAt)` row-per-day shape** (approved Decision E).
  `saveInstagramInsightMetric()` checks for an existing row for that
  account/post/metric/day (`postId <=> ?` NULL-safe comparison) and updates
  in place rather than relying on a DB `UNIQUE` constraint, since MySQL
  treats `NULL` as distinct in unique keys.
- **`cron/instagramAnalyticsSync.php`** — separate `flock()` lock and log
  file from `instagramScheduler.php` (approved Decision F: independent,
  daily-recommended cadence, not tied to the publish scheduler's 1–2 minute
  interval). Loops every connected account via
  `getConnectedInstagramAccountIds()`, resolves `clientId` from each
  account fresh, fetches account-level metrics
  (`fetchInstagramAccountInsights()`) and post-level metrics for that
  account's last 20 published posts (`fetchInstagramPostInsights()`) — one
  account's failure doesn't stop the run.
- **Real Meta comment actions** (approved Decision G) —
  `replyToInstagramComment()` (`POST /{comment-id}/replies`) and
  `hideInstagramComment()` (`POST /{comment-id}?hide=true`), both routed
  through the existing `instagramGraphApiRequest()`. Both
  `api/instagram/replyInstagramComment.php` and `api/instagram/hideInstagramComment.php`
  resolve the account from the comment's own stored `instagramAccountId`
  (never client-submitted), so a reply/hide can never be sent through the
  wrong Instagram identity.
- **New pages**: `pages/instagram-comments.php` (client → account
  cascading selectors, reply modal, hide/unhide), `pages/instagram-
  analytics.php` (client → account selectors, account-level stat cards +
  post-level metrics table).
- **New migration**: `database/migrations/2026-08-22-instagram-phase3-
  tables.sql` — `instagramInsights`, `instagramComments`,
  `instagramWebhookEvents`, `instagramSettings.webhookVerifyToken`, and the
  two new page routes.
- **A real bug was caught and fixed during testing**: the `instagramComments`
  INSERT's `mysqli_stmt_bind_param()` type string (`'iiisssss'`, 8
  characters) didn't match its 9 bound parameters — every webhook-driven
  comment insert failed silently into the `failed` webhook-event status
  until a full HTTP-level test (real signed webhook POST, not just a unit
  call) surfaced it. Fixed to `'iiissssss'` (9 characters) and re-verified.
- **Verified end-to-end** with a real (CLI + real HTTP, temporary, cleaned
  up) test: webhook GET handshake against a real generated verify token;
  webhook POST signature rejection (bad signature → 403) and acceptance
  (valid signature → 200); a real signed webhook comment delivery
  correctly resolving `clientId`/`instagramAccountId` and `postId` for a
  tracked post; a second delivery for an **untracked** post correctly
  storing the comment with `postId = NULL` instead of dropping it (the
  exact scenario Decision B exists for); insights upsert-by-day correctness
  (re-saving the same day updates in place, no duplicate row); and a real
  `instagramAnalyticsSync.php` cron run against the real Meta API (fake
  token → real `Invalid OAuth access token` error, logged, no crash).

### Phase 3.1 — Production Hardening, Documentation & Verification

- **Meta metrics verification (Task 2)**: reviewed every metric name in
  `includes/InstagramInsights.php` against Meta's actual account/media
  object fields and `/insights` edge behavior. Found and fixed a real
  gracefulness gap: Meta's `/insights` edge fails the **entire** request if
  even one requested metric is invalid/unsupported for that object, so a
  single deprecated or media-type-mismatched metric was silently losing
  every other metric in the same call. Added `fetchInstagramInsightsResilient()`
  — batch-first, falls back to per-metric requests on failure, drops
  metrics that individually error rather than fabricating values for them.
  Added the missing `shares` metric to post-level insights (present in the
  original task spec, absent from the Phase 3 implementation). Documented
  the final supported-metrics table in §12.
- **Permission verification (Task 3)**: confirmed by grep — zero
  `hasActionPermission()`/`requireActionPermission()` calls anywhere in this
  module — and by reading `routesMaster` directly — every Instagram
  Automation route is `isPublic = 0` with route-level `canView` as the only
  gate, no finer separation between e.g. "view settings" and "edit Meta
  credentials." No permission architecture changes made — this phase's
  instructions were to verify and document the existing (Modlus-wide)
  model, not build a new one. Findings in §16.
- **Webhook security review (Task 4)**: re-read `api/instagram/instagramWebhook.php`
  and `includes/InstagramWebhooks.php` line by line. Confirmed: no session
  dependency; `hash_equals()` (constant-time) signature comparison; no
  sensitive value (token/secret/signature) ever reaches a log line
  (grep-verified); malformed JSON/missing `entry` handled without a fatal
  error; comment data is idempotent via the `instagramCommentId` unique
  key, while the raw `instagramWebhookEvents` log is deliberately NOT
  deduplicated (append-only audit trail — a conscious choice, documented in
  §16, not an oversight). No code changes were required by this review —
  the Phase 3 implementation already met the bar; findings are documented,
  not new fixes.
- **Admin error visibility (Task 5)**: added `instagramAccounts.lastAnalyticsSyncAt`
  / `lastAnalyticsSyncError` (migration `2026-08-22-instagram-phase31-hardening.sql`),
  written by the new `markInstagramAccountAnalyticsSync()` after every
  `instagramAnalyticsSync.php` attempt per account — reuses the existing
  "each row carries its own last-known-state" convention
  (`instagramPosts.status`/`errorMessage`), not a new notification system.
  Added `getInstagramWebhookEvents()` + `api/instagram/getInstagramWebhookEvents.php`
  (read-only) and a "Recent Webhook Events" card + sync-status banner on
  `pages/instagram-analytics.php`. Publishing already had `errorMessage`
  (Phase 2); comment reply/hide failures already surface immediately via
  the existing toast system — both left as-is, since they were already
  adequate and adding persisted failure state for a synchronous,
  immediately-visible user action would have been unnecessary duplication.
- **Documentation (Task 1, 6, 7)**: this file brought fully in sync with the
  actual implemented system (file map, schema, all six flows, the new §16
  Security & Permissions Model). Created
  `docs/instagram-automation-production-checklist.md` (Task 6) and
  `docs/instagram-automation-readme.md` (Task 7) — a standalone,
  code-free onboarding doc for a new developer.
- **Verified**: re-ran the full Phase 2.5 + Phase 3 HTTP/CLI regression
  suite (no changes to existing behavior), plus a new targeted pass proving
  `lastAnalyticsSyncAt`/`lastAnalyticsSyncError` are written and exposed
  correctly, `getInstagramWebhookEvents()`'s status filter works, and
  `fetchInstagramInsightsResilient()` degrades to an empty array (not an
  exception, not a fabricated value) when every metric genuinely fails.
- **No new Instagram features added** — no DM automation, chatbot, AI
  replies, campaign automation, or new publishing features, per this
  phase's explicit scope boundary.
