# Elma Real Estate CRM

Web-based Real Estate Sales CRM for **Elma Real Estate**: captures enquiries from digital and
offline sources, moves them through the sales pipeline, schedules follow-ups, and gives the sales
team and management one place for leads, communication history, projects and reports.

Built on an existing core-PHP admin foundation (routing, API gateway, role permissions, admin +
employee portals, master UI components); unrelated modules from that codebase were removed.
Engineering context for AI assistants lives in [CLAUDE.md](CLAUDE.md) (full phase-by-phase history).

## Main modules

| Module | Status |
|---|---|
| Leads (manual entry, CSV import, pipeline, assignment, remarks, documents, Call/WhatsApp) | Available |
| Follow-ups (Today / Upcoming / Overdue / Completed; rule-based + manual) | Available |
| Project Portfolio + documents (images, brochures, floor plans, price lists) | Available |
| Lead Dashboard / Lead Activity (append-only, actor-typed history) | Available |
| Employees (accounts, roles, permissions) | Available |
| Management dashboard + reports + exports | Available |
| Meta Lead Ads / Google Lead Forms / website enquiry integrations | Available (needs live provider credentials — see below) |
| WhatsApp CRM Chat (Meta WhatsApp Cloud API) | Available (needs live provider credentials — see below) |

## Tech stack

- PHP 8.x (core PHP, no framework), MySQL 8 / MariaDB via `mysqli` prepared statements
- Bootstrap 5 admin theme (prebuilt in `dist/`), jQuery, DataTables 1.12 + Buttons, ApexCharts
- PHPMailer (Gmail SMTP with an app password)
- Apache / LiteSpeed with `mod_rewrite` (`.htaccess` routing)

## Structure

```
routes.php        page router (routesMaster table) + login/permission + CSRF on POST
api-gateway.php   entry for every api/**.php: login + CSRF on non-GET + mapped action checks
                  (3 provider webhooks/public endpoints are explicitly exempted — see Integrations)
pages/            admin pages            employee/   employee portal pages
api/<module>/     JSON endpoints ({success, message, data})
includes/         config, db, auth, permissions, CSRF, layouts, engines, mailer, integrations
database/         migrations + CLI scripts (web-blocked)
storage/          runtime config + private lead/project/whatsapp files (web-blocked, gitignored)
uploads/          public media (PHP execution disabled)
dist/             theme assets (treat as vendor)
```

## Local setup (WAMP / XAMPP)

1. Place the project at `C:\wamp64\www\elma` (or `htdocs/elma`) with `mod_rewrite` enabled.
   App URL: `http://localhost/elma`.
2. Create the database and a MySQL user limited to it:
   ```sql
   CREATE DATABASE elma_realestate_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'elma_crm'@'localhost' IDENTIFIED BY '<choose-a-local-password>';
   GRANT ALL PRIVILEGES ON elma_realestate_crm.* TO 'elma_crm'@'localhost';
   ```
   On localhost the app defaults to user `elma_crm` / database `elma_realestate_crm`; set
   `CRM_DB_PASS` (and anything else) in a local `.env` — see below.
3. Run the migrations in filename order (next section).
4. Copy `storage/basic-config.example.json` to `storage/basic-config.json`.
5. Create the first admin: `php database/create-admin.php "Full Name" admin@example.com`
   (prints a one-time password — change it via Forgot Password).
6. Sign in at `/login`, then add sales staff from **Employees → Employee Directory → Add Employee**.

## Environment variables

Configuration is read from `CRM_*` environment variables (server `SetEnv` / hosting panel), or
from an optional `.env` file in the project root (gitignored and blocked from the web). Real
environment variables take precedence. See [.env.example](.env.example).

| Variable | Required | Purpose |
|---|---|---|
| `CRM_DB_HOST`, `CRM_DB_PORT`, `CRM_DB_NAME`, `CRM_DB_USER`, `CRM_DB_PASS` | Yes (outside localhost) | Database connection; the app refuses to start without them, and refuses any name containing `modlus` |
| `CRM_BASE_URL` | Recommended | Public base URL (needed for CLI/webhook URLs; auto-detected on web requests) |
| `CRM_ENCRYPTION_KEY` | Before enabling any integration | Key for `includes/Crypto.php`; used to encrypt Meta/Google/Website/WhatsApp secrets. Empty = encryption refused, so no integration can be enabled |
| `CRM_SUPER_ADMIN_EMAILS` | Recommended | Admins allowed into Route / Page Setup (comma-separated) |
| `CRM_SMTP_USERNAME`, `CRM_SMTP_APP_PASSWORD` | Recommended for production | Gmail SMTP credentials for password-reset/OTP mail; override the Basic Setup UI values. There is one mailer (Gmail via PHPMailer) — do not add a second |
| `CRM_BRAND_NAME` | Optional | Client-facing name (default "Elma Real Estate") — appears in titles, toasts, auth emails, and the legal pages |
| `CRM_DEV_MODE` | Local only | `1` skips real OTP checks — **must not be set in production** |

Safety: a database or DB user whose name contains `modlus` is always refused, and the app fails
closed (HTTP 500, no default credentials) on any non-local host that hasn't set the `CRM_DB_*`
variables — verified in `includes/db.php`.

Provider (Meta/Google/Website/WhatsApp) credentials are **not** environment variables — they are
entered once through the admin **Integrations** page and stored encrypted with `CRM_ENCRYPTION_KEY`
in the database (`integrationSettings.secretEncrypted`). Only `CRM_ENCRYPTION_KEY` itself needs to
be set in the server environment.

## Database and migrations

Migrations live in `database/migrations/YYYY-MM-DD[x]-crm-*.sql`, are additive and idempotent
(safe to re-run), and must be applied in this exact order against the CRM database only:

```bash
mysql -u <user> -p <db> < database/migrations/2026-09-26-crm-baseline-schema.sql
mysql -u <user> -p <db> < database/migrations/2026-09-26b-crm-baseline-routes.sql
mysql -u <user> -p <db> < database/migrations/2026-09-26c-crm-deactivate-modlus-routes.sql
mysql -u <user> -p <db> < database/migrations/2026-09-26d-crm-provisional-role-access.sql
mysql -u <user> -p <db> < database/migrations/2026-09-26e-crm-sidebar-structure.sql
mysql -u <user> -p <db> < database/migrations/2026-09-26f-crm-permission-actions.sql
mysql -u <user> -p <db> < database/migrations/2026-09-27-crm-phase3-core.sql
mysql -u <user> -p <db> < database/migrations/2026-09-27b-crm-phase3-drop-modlus-lead-fields.sql
mysql -u <user> -p <db> < database/migrations/2026-09-29-crm-phase5-reports.sql
mysql -u <user> -p <db> < database/migrations/2026-09-30-crm-phase6-integrations.sql
mysql -u <user> -p <db> < database/migrations/2026-10-01-crm-phase6.5-whatsapp.sql
```

- Table names follow the casing used in code (Linux MySQL is case-sensitive): `employeeusers`
  lower-case, everything else camelCase. Build production from the migrations — never by copying
  a Windows/local database.
- No migration creates user accounts, passwords, or provider secrets.
- After running all migrations, sanity-check: `routesMaster` has the routes above,
  `integrationSettings` has 4 rows (`meta`/`google`/`website`/`whatsapp`, all `isEnabled=0` until
  configured), and `whatsappConversations`/`whatsappMessages` exist and are empty.

## Users, roles and permissions

- **Admin** — `users` table, full access (admin panel).
- **Sales Manager / Sales Executive** — `employeeusers`, role = `designationName`, employee portal.
- Permissions: `userPermissionOverrides` → `rolePermissions` → deny; button/API actions via
  `permissionActions` + `roleActionPermissions` (managed in **Roles & Permissions**).
- Lead access: admins see all leads; employees see only leads/conversations they own unless their
  role has the **View All Leads** action (granted to Sales Manager by default).

## Security notes

- **Never commit secrets.** `.env`, `storage/*.json`, logs, uploads and private documents
  (`storage/lead-documents/`, `storage/project-documents/`, `storage/whatsapp-media/`) are
  gitignored; `storage/`, `database/`, `logs/`, `cron/`, `includes/`, `pages/`, `employee/`,
  dotfiles and docs are blocked from the web (verified with real HTTP requests in the security suite).
- CSRF: every non-GET API call and every page form post requires the session token (added
  automatically by the layouts' CSRF client and `getCsrfInput()` in forms) — **except** the 3
  provider webhook/public endpoints under `api/integrations/*-webhook.php` /
  `api/integrations/website-lead.php`, which use the provider's own signature/API-key
  authentication instead (they are machine endpoints, not logged-in-user actions).
- Session cookie `ELMACRMSESSID` is `HttpOnly`, `SameSite=Lax`, and `Secure` on any non-local host
  (`includes/config.php`) — confirm the production domain actually serves HTTPS, or logins will fail
  (browsers refuse a `Secure` cookie over plain HTTP).
- Uploads are validated by real MIME type (`finfo`), stored under generated names, and private
  documents/media (leads, projects, WhatsApp) are served only through an authorised endpoint that
  re-checks ownership.
- Hiding a button is never authorisation — every endpoint checks login, permission and ownership.
- Integration secrets (Meta/Google/Website/WhatsApp access tokens, app secrets, verify tokens) are
  AES-encrypted at rest (`includes/Crypto.php`), never returned by any API, and never logged —
  `integrationLogs`/activity logs only ever store normalized, non-secret summaries.

## Development conventions

- Reuse the existing masters (page structure, DataTables, Bootstrap modals, confirm modal,
  `showToast()`, form validation, filters, status badges, the Mamix chat CSS for WhatsApp). No new
  UI/CSS/JS frameworks.
- New page = migration (routesMaster row with the right `moduleName`) + API + page + sidebar group,
  in one change. Mutating APIs: POST, permission, ownership, validation, JSON response.
- camelCase for variables, tables and columns; prepared statements only; escape all output.
- Run `php -l` on every touched file and test auth / permission / CSRF / ownership paths.

---

## Production deployment

This section is written for whoever deploys/administers the live site (developer or client's IT
contact) — it assumes a standard shared/VPS Apache+PHP+MySQL host (e.g. Hostinger).

### 1. Server prerequisites

- PHP 8.1+ with `mysqli`, `curl`, `fileinfo`, `mbstring`, `openssl` extensions enabled.
- Apache (or LiteSpeed) with `mod_rewrite` — the app is entirely `.htaccess`-routed.
- A domain with HTTPS (Let's Encrypt or the host's own SSL) — **required**, not optional (secure
  cookies, Meta/Google/WhatsApp webhooks all require a public HTTPS URL).

### 2. PHP configuration

Set via the hosting panel's PHP configuration screen (not `.htaccess` `php_value`, which is
unreliable under PHP-FPM):

| Setting | Target | Why |
|---|---|---|
| `upload_max_filesize` | `10M` | Matches the app's own cap (`PRIVATE_FILE_MAX_BYTES` in `includes/privateFiles.php`) for lead/project documents and WhatsApp media |
| `post_max_size` | `12M` | Must exceed `upload_max_filesize` to leave room for other form fields |
| `memory_limit` | `256M` | Comfortable for DataTables/report/PDF export pages |
| `max_execution_time` | `60` | Enough for report exports without being unbounded |
| `max_input_time` | `60` | Same reasoning |

(Local WAMP dev currently runs the PHP defaults — `2M`/`8M`/`128M` — which is fine for local
testing but was not changed here, since it has no bearing on the production host's configuration
and changing it locally would not "harden" anything.)

### 3. Database

1. Create a dedicated MySQL database + user for the CRM — **never** reuse or connect to any
   existing Modlus database (`includes/db.php` refuses this outright by name-matching `modlus`).
   ```sql
   CREATE DATABASE <crm_db_name> CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER '<crm_db_user>'@'%' IDENTIFIED BY '<strong-password>';
   GRANT ALL PRIVILEGES ON <crm_db_name>.* TO '<crm_db_user>'@'%';
   ```
   Grant privileges on that one database only — not `*.*`.
2. If a previous production CRM database already exists, back it up first (see Backups below)
   before running any new migration.
3. Set `CRM_DB_HOST`/`CRM_DB_PORT`/`CRM_DB_NAME`/`CRM_DB_USER`/`CRM_DB_PASS` in the server
   environment (hPanel "Environment Variables" if the host supports it; otherwise a
   non-web-accessible include loaded before `includes/config.php`, or a `.env` file placed outside
   the web root or blocked by `.htaccess` and never committed).
4. Run every migration in the exact order listed above. Confirm the sanity checks in that section.

### 4. File storage & permissions

Writable by the web server user, not world-writable:

```
storage/                    750 (directory)
storage/lead-documents/     750
storage/project-documents/  750
storage/whatsapp-media/     750
logs/                       750
uploads/                    755 (public, but PHP execution is disabled by uploads/.htaccess)
```

Do not use `777` — on shared hosting the PHP process usually already owns these directories, so
`750`/`755` is sufficient; only widen if the host's specific user/group setup genuinely requires it,
and document why if so. After deployment, verify:
- a lead/project document upload succeeds and downloads back correctly (through the app, not a
  direct URL),
- `GET /storage/...` from a browser returns 403 (already enforced by the root `.htaccess`),
- a `.php` file placed in `uploads/` cannot execute (already enforced by `uploads/.htaccess`).

### 5. First production admin

```bash
php database/create-admin.php "Full Name" real-admin@elmarealestate.example
```

This prints a one-time password to the terminal only (never logged, never emailed by the script
itself) — change it immediately via **Forgot Password** after first login. Do **not** leave
`admin@elma.local` or any of the local test employee accounts
(`ravi.exec@elma.local`, `meera.mgr@elma.local`, `sana.exec@elma.local`) in production; those exist
only in the local dev database used for this project's testing and are never created by any
migration.

### 6. Mail (SMTP)

Set `CRM_SMTP_USERNAME` / `CRM_SMTP_APP_PASSWORD` (a Gmail address + app password) in the server
environment, or enter them once via **Setup → Basic Setup**. Verify password-reset and OTP mail
delivery with a real mailbox you control before handover — do not mass-email arbitrary addresses
during testing.

### 7. Legal pages (`/privacy-policy`, `/terms-of-service`, `/data-deletion`)

Content was rewritten in Phase 6 to accurately describe this CRM's real behavior (see
`pages/privacy-policy.php` etc.), but each file defines a placeholder `CONTACT_EMAIL`
(`privacy@elmarealestate.example`). **REQUIRES CLIENT LEGAL APPROVAL** before relying on these
pages for a real Meta App Review submission: replace the placeholder contact with the client's real
support address, and have the client's own legal counsel review the wording (no legal commitments
were invented — the content only describes what the system technically does).

### 8. Lead-capture integrations (Meta / Google / Website)

Configure each provider under **Integrations** with real credentials once the domain is live:

- **Website**: generate an API key on the page, give it to whoever implements the enquiry form,
  point it at `POST https://<domain>/api/integrations/website-lead.php` with header
  `X-Integration-Key`. Fully testable immediately (it's this CRM's own contract).
- **Meta Lead Ads**: needs a real Meta App, Page, Lead Form, App Secret and a Page Access Token.
  Webhook URL: `https://<domain>/api/integrations/meta-webhook.php`. The verify-token handshake
  must succeed in the Meta App dashboard before a real lead event will ever arrive.
- **Google Lead Forms**: needs the advertiser's Lead Form Extension shared key. Webhook URL:
  `https://<domain>/api/integrations/google-lead.php`.

None of these were live-verified in this project (no real provider accounts were available) —
only simulated payloads matching the documented contract. **First real lead through each provider
must be watched end-to-end** (Integration Log + the lead appearing correctly in Leads/Dashboard)
before trusting it unattended.

### 9. WhatsApp Cloud API

Configure under **Integrations → WhatsApp Cloud API**: Phone Number ID, WhatsApp Business Account
ID, Access Token, App Secret, Verify Token, and the approved template list (name/language/variable
count — templates themselves are created and approved in Meta Business Manager, not in this CRM).
Webhook URL: `https://<domain>/api/integrations/whatsapp-webhook.php`.

**Template messages** (used when the 24-hour customer service window is closed, or any time an
approved template is wanted): `POST api/whatsapp/send-template.php` — a dedicated endpoint,
separate from the free-text `send-message.php`, so a template send is validated against the
admin-configured list (name + language + exact variable count) before it ever reaches the Graph
API. The chat composer shows a disabled text box + a "Send Template" button once the window is
closed (and the template button is always available); selecting a template renders exactly as
many variable inputs as `variableCount` says. This CRM only stores the template's name/language/
variable count — the templates themselves must already be approved in Meta Business Manager, and
this CRM cannot verify that approval status itself.

Real end-to-end verification required after go-live (not done locally, no live credentials):
1. Send a WhatsApp message to the business number from a real phone → confirm it appears in
   `/whatsapp` within seconds.
2. Reply from the CRM → confirm the customer receives it, and the status advances
   Sent → Delivered → Read.
3. Send an image and a document both directions.
4. Confirm the 24-hour window banner appears correctly, and that a real *approved* template sends
   successfully outside the window (do not consider templates production-ready until this
   succeeds for real — a template name/variable count that is only "configured" in this CRM but
   not actually approved in Meta Business Manager will still be rejected by Meta itself).

### 9a. Meta App Review readiness

What this codebase provides vs. what only the client/Meta dashboard can provide, for both the
Lead Ads app and the WhatsApp Business app review:

| Requirement | Status |
|---|---|
| Privacy Policy URL | `https://<domain>/privacy-policy` — content rewritten for this CRM (incl. WhatsApp messaging data) in Phase 6/6.5. **Needs**: client's real contact address, final legal sign-off. |
| Terms of Service URL | `https://<domain>/terms-of-service` — same status as above. |
| User Data Deletion URL | `https://<domain>/data-deletion` — same status; describes WhatsApp conversation data explicitly. |
| App Domains | Not hardcoded anywhere in code — driven entirely by `CRM_BASE_URL`. **Needs**: the client enters the live production domain in the Meta App dashboard once it's known. |
| Contact information (email/phone shown to Meta reviewers) | `CONTACT_EMAIL` in the three legal pages is a placeholder (`privacy@elmarealestate.example`). **Needs**: client's real support address before submission. |
| Webhook URLs | Computed from `CRM_BASE_URL`, never hardcoded — also returned live by `getIntegrationSettings.php`'s `urls` field for the Integrations page to display: `.../api/integrations/meta-webhook.php` (Lead Ads), `.../api/integrations/whatsapp-webhook.php` (WhatsApp). Both already implement Meta's GET handshake + `X-Hub-Signature-256` verification. **Needs**: the client/reviewer to actually point Meta's webhook subscription at the live URL and complete the handshake — cannot be done from this environment without a live App Secret/Verify Token. |
| WhatsApp configuration requirements | Phone Number ID, WABA ID, Access Token, App Secret, Verify Token, template list — all configurable under Integrations, none hardcoded. **Needs**: the client's real WhatsApp Business Account assets, and Meta's own approval of each template used. |
| HTTPS | Enforced by `includes/config.php`'s cookie `Secure` flag once off `localhost` (Phase 7). **Needs**: the production host to actually terminate HTTPS (Hostinger/Let's Encrypt), which cannot be verified from local WAMP. |

Nothing in this list required inventing new legal claims or fake credentials — everything marked
"Needs" is either a piece of information only the client has (their real domain, contact address,
Meta/WhatsApp Business assets) or an action only performable against a live server (the webhook
handshake, HTTPS).

### 10. Backups

Minimal, no custom tooling:

| What | How | Where | Frequency | Retention |
|---|---|---|---|---|
| Database | `mysqldump` (or the host's DB backup tool, e.g. Hostinger's scheduled MySQL backups) | Off-host storage (host's backup feature, or downloaded copy) | Daily | 7–14 days |
| `storage/lead-documents/`, `storage/project-documents/`, `storage/whatsapp-media/` | File-level backup (host's file backup feature, or `tar`/`zip` + download) | Off-host storage | Daily or weekly | 7–14 days |
| `.env` / server environment variables | Kept securely by whoever administers hosting (password manager / hPanel itself) — never in a code backup | — | On change | Indefinite |

If the host (Hostinger) provides automated daily backups, use those as the primary mechanism and
only add the above if it doesn't cover file storage.

**Restore procedure** (verify in a staging copy, never directly against production):
1. Restore the database dump into a fresh/staging database.
2. Restore the file storage directories to matching paths.
3. Point a staging copy of the app at that database (`CRM_DB_*`) and confirm login + a lead's
   documents open correctly.
4. Only once verified, restore into production if an actual incident requires it.

*(A restore was not executed against this project's data as part of this phase — there is no
incident requiring it, and doing so against the working local database would itself be destructive.
The procedure above is documented so it can be exercised on the actual production host.)*

### 11. Rollback plan

1. If the deployment is broken badly enough to need it, put the site in maintenance (a static
   holding page swapped in via the host's file manager, or `.htaccess` redirect to a maintenance
   page) rather than leaving a broken app live.
2. Restore the previous release's files (keep the last known-good deployment as a zip/copy before
   deploying a new one).
3. Only restore the database from backup if the new deployment's migration made a breaking schema
   change (migrations here are additive/idempotent by design, so this should rarely be necessary —
   check whether simply leaving the new columns/tables in place, unused, is sufficient instead).
4. Restore `storage/` from backup only if files were actually lost/corrupted.
5. Verify login and one API call succeed before removing maintenance mode.

### 12. Deployment checklist

```
[ ] Database backed up (if a prior production CRM DB exists)
[ ] Code deployed to the production document root
[ ] CRM_DB_* / CRM_ENCRYPTION_KEY / CRM_SUPER_ADMIN_EMAILS / CRM_SMTP_* set in the server environment
[ ] Database created, migrations run in order, sanity-checked
[ ] storage/ + logs/ writable, correct permissions, not 777
[ ] HTTPS active, HTTP -> HTTPS redirect confirmed
[ ] First production admin created, one-time password changed
[ ] No local test accounts (admin@elma.local, ravi/meera/sana.*@elma.local) present
[ ] SMTP verified (real password-reset email received)
[ ] .htaccess-blocked paths spot-checked with real requests (storage/, database/, includes/, .env)
[ ] Website lead endpoint verified live (real API key, real request)
[ ] Meta Lead Ads verified live (real webhook + real test lead) -- requires client's Meta assets
[ ] Google Lead Forms verified live -- requires client's Google Ads assets
[ ] WhatsApp webhook verified live (real handshake) -- requires client's Meta WhatsApp assets
[ ] WhatsApp real send/receive verified -- requires a live WhatsApp Business number
[ ] WhatsApp templates in Integrations settings match templates actually APPROVED in Meta Business Manager
[ ] Meta App Review: App Domains set to the real production domain, contact email updated from placeholder
[ ] Legal pages finalized with client's real contact + legal sign-off
[ ] Full security regression suite passing
[ ] Browser QA passing at 1440/1280/390px across Admin/Manager/Executive
[ ] Demo/seed data removed or explicitly approved to keep for a client walkthrough
```

Items requiring the client's own provider accounts/credentials cannot be checked off from this
codebase alone — see [CLAUDE.md](CLAUDE.md)'s Phase 7 section for exactly what was and wasn't
verified locally.
