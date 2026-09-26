# Instagram Automation — Production Readiness Checklist

Last Updated: 2026-08-22 (Phase 3.1)

Use this before enabling Instagram Automation for real clients. It's a
checklist, not a tutorial — see `docs/instagram-automation-readme.md` for
the full walkthrough of what each item actually does, and
`docs/instagram-automation-flow.md` for the internals.

## Meta Setup

- [ ] **Meta App created** in [developers.facebook.com](https://developers.facebook.com) with the
      Instagram Graph API product added.
- [ ] **Instagram Business account connected** — at least one client has
      gone through Connect Instagram Account (`pages/instagram-automation.php`
      → `api/instagram/instagramOauthStart.php` → Meta OAuth → `api/instagram/instagramOauthCallback.php`)
      and shows `status = 'connected'` in `instagramAccounts`.
- [ ] **Required permissions approved** by Meta App Review for production
      use (not just Development Mode / test users):
  - `instagram_basic`
  - `instagram_manage_insights`
  - `pages_show_list`
  - `pages_read_engagement`
  - Additionally for Phase 3 comment actions: `instagram_manage_comments`
    (verify against your Meta App's current permission list — Meta's
    naming/bundling changes over time; confirm in the App Dashboard, not
    from memory).
- [ ] **Redirect URL configured** — the exact value shown in "Redirect URL"
      on the settings page (`pages/instagram-automation.php`), added to the
      Meta App's Valid OAuth Redirect URIs. Must be HTTPS in production.
- [ ] **Webhook URL configured** — the "Webhook Callback URL" shown on the
      same settings page (`{BASE_URL}/api/instagram/instagramWebhook.php`) and the
      "Webhook Verify Token" (auto-generated on first settings save),
      entered into the Meta App's Webhooks product configuration.
      Subscribe to the `comments` field on the `instagram` object.
- [ ] Webhook subscription shows as **verified/active** in the Meta App
      Dashboard after entering the URL + token (Meta calls the GET
      handshake immediately — a failure here means either the URL isn't
      reachable or the verify token doesn't match what's saved in
      `instagramSettings.webhookVerifyToken`).

## Server Setup

- [ ] **Encryption key configured** — `MODLUS_ENCRYPTION_KEY` environment
      variable set to a real, unique value in production. If unset,
      `includes/config.php` falls back to the literal string
      `default_key_123` — every encrypted secret in this module
      (`metaAppSecret`, `accessToken`) would be protected by a key anyone
      reading this repo already knows. This is the single most important
      item on this checklist.
- [ ] **Cron jobs configured** on the production scheduler (cPanel cron /
      Task Scheduler — whichever this deployment uses, matching the other
      jobs already in `cron/`):
  - `cron/instagramScheduler.php` — every 1–2 minutes (publishing feels
    slow/unresponsive if this runs less often; scheduled posts won't go
    live until the next tick).
  - `cron/instagramAnalyticsSync.php` — daily (Meta's account/post metrics
    don't change meaningfully more often than that; running it as
    frequently as the publish scheduler wastes API calls and risks rate
    limits for no benefit — see `docs/instagram-automation-flow.md` §12).
  - Both are CLI-only (`PHP_SAPI !== 'cli'` guard) — confirm the scheduler
    actually invokes `php` on the command line, not an HTTP hit against the
    file (which will 403).
- [ ] **Upload folder writable** — `uploads/instagram-posts/` exists and is
      writable by the web server user (it self-creates on first upload via
      `saveInstagramMediaFile()`, but confirm the parent `uploads/`
      directory itself has correct permissions in this environment).
- [ ] **HTTPS enabled** on the production domain — required for both the
      OAuth redirect URL and the webhook URL; Meta will not deliver
      webhooks to a plain-HTTP endpoint, and won't complete OAuth against one.

## Security

- [ ] **CSRF enabled and verified** — every state-changing Instagram
      endpoint (`saveInstagramSettings.php`, `disconnectInstagramAccount.php`,
      `saveInstagramPost.php`, `deleteInstagramPost.php`,
      `replyInstagramComment.php`, `hideInstagramComment.php`) calls
      `requireValidCsrfToken()`. Spot-check: submit one of these forms with
      browser dev tools open and confirm the `X-CSRF-Token` header is sent.
- [ ] **Secrets encrypted at rest** — confirm `instagramSettings.metaAppSecret`
      and `instagramAccounts.accessToken` are NOT plaintext in the database
      (`SELECT metaAppSecret FROM instagramSettings` should return an
      opaque base64 blob, not something readable).
- [ ] **Webhook verification enabled** — confirm a forged/unsigned POST to
      `{BASE_URL}/api/instagram/instagramWebhook.php` returns `403`, not `200`. (See
      Testing → Webhook below for the exact way to check this safely.)
- [ ] **Permissions reviewed** — read `docs/instagram-automation-flow.md`
      §16 and confirm you're comfortable that every Instagram Automation
      route is currently gated at the **route** level only (no separate
      "can edit settings" vs. "can view settings" distinction, no
      per-client access restriction). If your organization needs finer
      control before go-live, that's a real gap to close first — this
      checklist won't catch it for you, since it's a product decision, not
      a bug.

## Testing

Run these against a real (non-production, if possible) Meta App + test
Instagram Business account before enabling for real clients.

### Publishing

- [ ] **Image** post: create, save as draft, then schedule for immediate
      publish; confirm it goes live on Instagram and `instagramPosts.status`
      reaches `published` with a real `instagramMediaId`.
- [ ] **Carousel** post: 2+ images, confirm all images appear in the
      published carousel in the correct order.
- [ ] **Reel**: confirm the post starts in `publishing` (video container
      processing), and reaches `published` on a **later** cron run (not
      necessarily the same one — this is expected async behavior, not a
      bug — see `docs/instagram-automation-flow.md` §6).

### Scheduling

- [ ] **Future post**: schedule a post for 10–15 minutes out, confirm it
      stays `scheduled` until the scheduled time passes, then publishes on
      the next cron tick after that.
- [ ] **Failed post**: deliberately break something (e.g. temporarily
      revoke the app's permission, or schedule against a disconnected
      account) and confirm the post reaches `status = 'failed'` with a
      real `errorMessage`, and that it's visible via "View Error" on
      `pages/instagram-scheduled-posts.php`.

### Analytics

- [ ] **Account metrics**: after `instagramAnalyticsSync.php` runs once,
      confirm `pages/instagram-analytics.php` shows real Followers /
      Total Posts / Reach / Profile Views numbers for a connected account
      (not all dashes).
- [ ] **Post metrics**: confirm at least one published post shows reach/
      likes/comments/saved values in the Recent Post Performance table.
- [ ] Confirm the sync status line shows a real "Last analytics sync"
      timestamp, and that `lastAnalyticsSyncError` is empty/hidden when the
      sync succeeded.

### Comments

- [ ] **Receive**: post a comment on the connected Instagram account
      (from a different, real Instagram account) and confirm it appears
      on `pages/instagram-comments.php` within moments of the webhook
      firing (no manual refresh trick needed beyond reloading the page).
- [ ] **Reply**: reply to a comment from the Modlus UI and confirm the
      reply appears as a real reply on Instagram itself, not just in
      Modlus.
- [ ] **Hide**: hide a comment from the Modlus UI and confirm it's
      actually hidden on Instagram (check from a different account/logged
      out — hidden comments are still visible to the account owner).

### Webhook

- [ ] **Verification**: re-trigger the Meta App Dashboard's webhook
      verification (or re-save the Webhook URL/Verify Token fields there)
      and confirm it succeeds — proves the GET handshake in
      `api/instagram/instagramWebhook.php` is reachable and matches the stored
      `webhookVerifyToken`.
- [ ] **Signed event**: after the "Comments → Receive" test above passes,
      confirm a corresponding row exists in `instagramWebhookEvents` with
      `status = 'processed'` — proves the full chain (signature valid →
      account resolved → client resolved → comment stored → event marked
      processed) worked, not just that a comment showed up some other way.
