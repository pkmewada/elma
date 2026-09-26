# Instagram Automation Module

Last Updated: 2026-08-22 (Phase 3.1)

This is the standalone onboarding document for this module — read this
first if you're new to it. For internal implementation details (exact
function signatures, line-by-line flow diagrams, the reasoning behind
specific design decisions), see `docs/instagram-automation-flow.md`. For a
pre-launch checklist, see `docs/instagram-automation-production-checklist.md`.

## Overview

Instagram Automation lets Modlus CRM manage Instagram Business accounts on
behalf of multiple clients from one admin panel: connect each client's
Instagram account via Meta OAuth, create and schedule posts (images,
carousels, reels), publish them automatically via a cron-driven scheduler,
track account and post analytics, and receive/manage comments via Meta
webhooks.

It was built in phases — Meta credential storage and account connection
first, then content publishing, then upgraded from a single-account
prototype into a proper multi-client architecture, then extended with
analytics/comments/webhooks, then hardened for production. Every phase's
reasoning is preserved in `instagram-automation-flow.md`'s Change Log if you
need the "why," not just the "what."

## Architecture Diagram

```
Modlus CRM
    |
clientMaster                (an existing Modlus entity — a converted lead)
    |
instagramAccounts           (one client can connect multiple IG accounts)
    |
instagramPosts               (every post belongs to one client + one of
    |                          that client's accounts)
    |
Analytics / Comments / Webhooks
(instagramInsights / instagramComments / instagramWebhookEvents —
 all traceable to the same clientId + instagramAccountId)
```

**The one rule that matters more than any other in this module**: every
Instagram object is resolved to a specific `instagramAccountId` first, and
`clientId` is *derived from that account*, never picked from session/UI
state, never defaulted to "whichever account connected most recently." A
whole architecture upgrade (Phase 2.5) existed solely to fix an earlier
version of this module that got this wrong. See
`instagram-automation-flow.md` §11 for the full reasoning if you're about
to add a new feature here.

## Complete Flow

### 1. Setup Meta API

An admin visits `pages/instagram-automation.php`, enters the Meta App ID
and App Secret (one Meta Developer App, shared by the whole Modlus
platform — not per-client), and a Redirect URL. Saving also auto-generates
a Webhook Verify Token, needed later for step 7. The App Secret is
encrypted before storage and never displayed again.

### 2. Connect Client Instagram Account

The admin selects a **client** from a dropdown (the same client
picker used throughout Modlus — `api/client/getClients.php`), then clicks Connect
Instagram Account. This kicks off Meta's OAuth flow; when it completes,
Modlus stores the connected Instagram Business account **against that
client** — `instagramAccounts.clientId`. A client can connect more than one
Instagram account.

### 3. Create Content

The admin picks a client, then one of that client's connected accounts,
then a post type (image / carousel / reel), uploads media, and writes a
caption. Media is validated for real content-type (not just trusting the
file extension) and stored under `uploads/instagram-posts/`.

### 4. Schedule / Publish

Saving a post either stores it as a `draft`, or schedules it for a future
time (or "as soon as possible," which really means "whenever the scheduler
next runs"). A CLI cron (`cron/instagramScheduler.php`) picks up due posts
and publishes them through Meta's Graph API, using **that post's own**
Instagram account — never a different one, even if another account for the
same client exists.

### 5. Fetch Analytics

A separate, independently-scheduled cron (`cron/instagramAnalyticsSync.php`,
daily is enough) loops every connected account across every client, pulls
account-level metrics (followers, reach, profile views) and post-level
metrics (reach, likes, comments, saves, shares, plays for reels) from
Meta's Insights API, and stores them. The Analytics page
(`pages/instagram-analytics.php`) shows a client's account picked from
that same client → account selector, filtered so one client never sees
another's numbers.

### 6. Manage Comments

Comments arrive via Meta's webhook (step 7) and land in `instagramComments`,
already attributed to the correct client and account. The Comments page
(`pages/instagram-comments.php`) lists them per client/account, with real
Reply and Hide actions that call Meta's Graph API (not just a local
"marked as replied" flag with no actual effect on Instagram).

### 7. Receive Webhooks

Meta calls `api/instagram/instagramWebhook.php` directly whenever a subscribed event
happens (currently: new comments). This endpoint has no login — Meta isn't
a logged-in Modlus user — so it verifies Meta's cryptographic signature
instead. Every event is logged to `instagramWebhookEvents` (for debugging,
even ones that can't be resolved to a known account), and successfully
resolved comment events flow into step 6.

## Database Explanation

| Table | What it stores | Key relationships |
| --- | --- | --- |
| `instagramSettings` | The Meta App ID/Secret/Redirect URL/Webhook Verify Token. **One row, global** — not per-client; one Meta Developer App serves the whole platform. | None (intentionally unscoped). |
| `instagramAccounts` | One row per connected Instagram Business account. | `clientId` → `clientMaster.id`. Unique on `instagramUserId` — a real IG account can only belong to one client at a time. |
| `instagramPosts` | One row per post, any status (`draft`/`scheduled`/`publishing`/`published`/`failed`). | `clientId` → `clientMaster.id`, `instagramAccountId` → `instagramAccounts.id` (must belong to the same client — enforced at save time, not just at publish time). |
| `instagramInsights` | Time-series metrics, one row per (account-or-post, metric name, day). | `clientId`, `instagramAccountId` (both required); `postId` nullable — `NULL` means an account-level metric, set means a post-level one. |
| `instagramComments` | Comments received via webhook, plus their reply/hide state. | `clientId`, `instagramAccountId` (both required); `postId` **nullable** — a comment can be on an Instagram post that predates Modlus tracking it, in which case `instagramMediaId` (Meta's own post id, always present) is the only reference. |
| `instagramWebhookEvents` | Every raw webhook delivery Meta sends, for debugging. | `clientId`, `instagramAccountId` — the **one** place in this module where these are nullable (an event can arrive for an account Modlus doesn't recognize) and use `ON DELETE SET NULL` instead of cascading delete, so this debugging history survives a client being removed later. |

Every table besides `instagramSettings` carries a real SQL `FOREIGN KEY`
back to its parent (`clientMaster`, `instagramAccounts`, or `instagramPosts`)
with `ON DELETE CASCADE` (except the webhook-events exception above) —
deleting a client cleans up everything Instagram-related that belonged to
them.

## File Structure

```
pages/
  instagram-automation.php        Settings + account connection
  instagram-create-post.php       Create/edit a post
  instagram-scheduled-posts.php   List all posts, any status
  instagram-comments.php          View/reply/hide comments
  instagram-analytics.php         Metrics + sync status + webhook health

api/instagram/
  getInstagramSettings.php, saveInstagramSettings.php
  instagramOauthStart.php, instagramOauthCallback.php
  disconnectInstagramAccount.php
  getInstagramPosts.php, saveInstagramPost.php, deleteInstagramPost.php
  getInstagramInsights.php
  getInstagramComments.php, replyInstagramComment.php, hideInstagramComment.php
  getInstagramWebhookEvents.php
  instagramWebhook.php            The ONE endpoint with no login — Meta calls this directly

includes/
  InstagramAutomation.php   Settings, accounts, posts, media upload, the
                             one shared Meta Graph API curl wrapper
  InstagramInsights.php     Analytics: fetch + store metrics
  InstagramComments.php     Comments: store, reply, hide
  InstagramWebhooks.php     Webhook: store events, verify signatures
  Crypto.php                 encryptSecret()/decryptSecret() — not
                             Instagram-specific, reusable by any module
  Csrf.php                    CSRF token helper — same, app-wide

cron/
  instagramScheduler.php       Publishes due posts — run every 1-2 min
  instagramAnalyticsSync.php   Pulls metrics — run once daily

database/migrations/
  2026-08-22-instagram-automation-tables.sql   instagramSettings, instagramAccounts
  2026-08-22-instagram-posts-tables.sql        instagramPosts
  2026-08-22-instagram-multi-client.sql        clientId/instagramAccountId columns + FKs
  2026-08-22-instagram-phase3-tables.sql       instagramInsights, instagramComments, instagramWebhookEvents
  2026-08-22-instagram-phase31-hardening.sql   lastAnalyticsSyncAt/Error columns
```

Every `includes/Instagram*.php` file `require_once`s `InstagramAutomation.php`
for shared primitives (account lookup, client-label resolution, the Graph
API wrapper) rather than duplicating them — extend that file's exports
before writing a new low-level helper anywhere else.

## Security Model

- **Encryption** — `includes/Crypto.php` (AES-256-CBC) protects every
  secret this module touches: the Meta App Secret and every account's
  access token. Set a real `MODLUS_ENCRYPTION_KEY` environment variable in
  production — see the production checklist.
- **CSRF** — `includes/Csrf.php` (app-wide, not Instagram-specific).
  Required on every endpoint that changes something: settings, account
  disconnect, post save/delete, comment reply/hide.
- **OAuth** — Meta's standard `state`-parameter CSRF protection for the
  OAuth dance itself, plus a required client selection carried through
  session state so the connected account always lands on the right client.
  Tokens are exchanged for long-lived versions immediately — without this
  step, publishing would start failing within hours of connecting an
  account.
- **Webhook validation** — the one endpoint with no session/login at all.
  Meta signs every request with `X-Hub-Signature-256` (HMAC-SHA256, keyed
  by the Meta App Secret); Modlus verifies it with a constant-time
  comparison before touching anything. An unsigned or forged request is
  rejected with `403` before any database write.
- **Permissions** — every route requires login and route-level `canView`
  permission, the same mechanism the rest of Modlus uses. There is
  currently no finer separation within a route (e.g. "can view settings"
  vs. "can edit Meta credentials" are the same permission) and no
  per-client access restriction (an admin who can see the Comments page
  sees every client's comments) — this matches how the rest of Modlus
  already works, not a gap specific to this module. Full detail:
  `instagram-automation-flow.md` §16.

## Cron Jobs

| Job | Frequency | What it does if it doesn't run |
| --- | --- | --- |
| `cron/instagramScheduler.php` | Every 1–2 minutes | Scheduled posts never actually publish — they just sit at `status = 'scheduled'` forever. |
| `cron/instagramAnalyticsSync.php` | Once daily | Analytics page keeps showing stale or empty numbers; `lastAnalyticsSyncAt` on the account never updates. |

Both are CLI-only (they 403 if hit over HTTP) and use their own `flock()`
lock file, so an overrunning execution can't overlap the next scheduled
tick and double-process anything. Neither job depends on the other, and
they use separate log files (`cron/instagramScheduler.log`,
`cron/instagramAnalyticsSync.log`).

## Troubleshooting

**"Token expired" / posts suddenly failing for one client**
Check that client's account on `pages/instagram-automation.php` — if
`status` shows `disconnected`, the scheduler already detected an invalid
token (Meta error code 190) and auto-disconnected it rather than retrying
forever. The client needs to reconnect via OAuth. This is expected
behavior, not a bug — see `instagram-automation-flow.md` §8.

**"Webhook failed" / comments not showing up**
1. Check `pages/instagram-analytics.php` → select the client/account →
   "Recent Webhook Events" card. A `failed` row's error message tells you
   why (unknown account, disconnected account, or something Meta-side).
2. If there's no row at all, the delivery never reached Modlus, or failed
   signature verification before it could even be logged — check
   `cron/instagramWebhook.log` for a "Rejected webhook delivery: invalid or
   missing signature" line, which usually means the Meta App Secret
   changed without re-saving settings, or the webhook subscription is
   pointed at the wrong App.
3. Confirm the webhook subscription still shows verified in the Meta App
   Dashboard — Meta can silently stop delivering if verification lapses.

**"Publishing failed"**
Check the specific post's error on `pages/instagram-scheduled-posts.php`
("View Error" on a `failed` row) — it's Meta's own error message, not a
generic one. Common causes: JPEG required for images (PNG is rejected by
this module's own upload validation before it even reaches Meta), carousel
needs 2–10 images, reel needs MP4/MOV.

**"Analytics unavailable" / stat cards show all dashes**
Either the sync cron hasn't run yet for that account (check the "Last
analytics sync" line — if it's never run, `instagramAnalyticsSync.php`
isn't scheduled correctly, see the Cron Jobs table above), or it ran but
every metric failed (check `lastAnalyticsSyncError` on the same page — a
real Meta-side error, not a placeholder).

## Future Roadmap

Documented here as *possibilities*, not commitments — none of this is
scoped or implemented. If any of it gets built, it must follow the same
`clientId` + `instagramAccountId` ownership model as everything else in
this module (§11 of the flow doc) — no new ownership pattern should be
invented for it.

- **DM Inbox** — Instagram Direct Message viewing/management, the natural
  extension of the Comments feature to Instagram's messaging surface.
- **Chatbot** — automated first-response handling for incoming DMs/comments.
- **AI automation** — AI-drafted or AI-suggested replies for comments/DMs,
  reviewed or auto-sent by the admin.
- **Approval workflows** — a review/approve step before a scheduled post
  actually publishes, useful for agencies managing client-facing content
  where the client wants sign-off before anything goes live.
