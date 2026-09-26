# MockZone Admin — Core Principles

The rulebook for the MockZone staff admin panel in `admin/`. Read this before any admin work.
It records how the (slimmed Modlus) architecture works and the rules every new module follows.

- **Status (2026-09-25):** runs on the `mockzone` database. Routes: login flow + Dashboard.
  **Admins only** (one `users` table, no roles, no permission system, no company concept): every
  logged-in admin can open every private page and API. Modlus CRM/HRMS/social modules, the
  employee portal, permissions and company settings are removed.
- **Scope:** `admin/` is the staff backend for the single MockZone project. The public website stays
  in the project root and follows the root `CLAUDE.md` / `AGENTS.md` / `PROJECT_MAP.md`.
- **Authority:** current task → this file → root `CLAUDE.md`/`AGENTS.md` → existing admin code.
  The Modlus docs were deleted; this file is the admin reference.

---

## 1. Architecture at a glance

Core PHP (no framework) + MySQL via **mysqli**, Bootstrap admin theme (Spruko template, prebuilt in
`dist/`), jQuery + AJAX pages, JSON APIs.

```
Browser
  │
  ▼
admin/.htaccess ── blocks includes/, vendor/, pages/, employee/, app/ (403)
  │                routes /api/*.php → api-gateway.php
  │                serves real files (dist/, uploads/ with its own rules)
  │                everything else → routes.php
  ▼
routes.php                                   api-gateway.php
  ├─ normalise path against BASE_URL           ├─ path + realpath() containment check
  ├─ getRouteByPath() → routesMaster (DB)      ├─ isLoggedIn() else 401 JSON
  ├─ not public? isLoggedIn() else /login      └─ include api/<module>/<action>.php
  └─ require pageFile ─┐                             │
                       ▼                             ▼
        pages/<page>.php                  CSRF for POST → Model → JSON {success,message,data}
        auth.php → header.php → sidebar.php → HTML + inline JS (AJAX to api/) → footer.php
```

Routing **and** the sidebar menu are database-driven: a page exists only if it has a row in
`routesMaster`. Login/session/route helpers: `includes/admin-session.php` (`isLoggedIn()`,
`getLoggedInAdminUser()`, `getRouteByPath()`).

## 2. Folder structure and responsibilities

| Path | Responsibility | Convention |
|---|---|---|
| `routes.php` | Page dispatcher (DB routes + login check) | Do not add `switch` cases; add a `routesMaster` row |
| `api-gateway.php` | Entry point for every `api/**.php`; requires admin login | Never bypass it |
| `pages/` | Admin screens | File names stay as they are; each includes `auth.php`, `header.php`, `sidebar.php`, `footer.php` |
| `api/<module>/` | JSON endpoints, one action per file | `camelCaseAction.php` |
| `app/models/` | DB access classes | `<Name>Model.php`, constructor takes the mysqli `$con` |
| `app/controllers/`, `app/views/` | Only the auth screens (login, OTP, forgot/reset) | New features use pages + APIs |
| `includes/` | Shared core: config, DB, session helpers, CSRF, layout, auth mail | |
| `database/` | Migrations + `create-admin.php` (CLI) | Denied to the web; `YYYY-MM-DD-short-description.sql`, additive |
| `dist/` | Compiled theme assets (`ASSET_URL`) | Treat as vendor; do not hand-edit |
| `uploads/` | **The single media root for the whole project** (§7) | Public media whitelist; `private/` denied |

## 3. Core modules

| Concern | Files | Notes |
|---|---|---|
| Config | `includes/config.php` | `BASE_URL`, `ASSET_URL`, `UPLOAD_URL`, `SITE_URL`, `BRAND_NAME`, `BRAND_LOGO_URL`, `BRAND_FAVICON_URL` |
| Database | `includes/db.php` | `getDbConnection()` / global `$con`; reads `MOCKZONE_DB_*` (local fallback: root@localhost, DB `mockzone`) |
| Admin auth | `app/controllers/AuthController.php`, `app/models/UserModel.php`, `app/views/{login,verifyotp,verifyresetotp,forgotpassword,resetpassword}.php`, `includes/auth.php`, `includes/auth-functions.php`, `includes/admin-session.php` | Session key `$_SESSION['userId']`; `password_verify()`; `session_regenerate_id(true)` on login. No public signup: admins are created with `php admin/database/create-admin.php` |
| CSRF | `includes/Csrf.php` | `X-CSRF-Token` header or `csrfToken` field; `requireValidCsrfToken()` |
| Layout | `includes/header.php`, `sidebar.php`, `sidebar-menu.php`, `footer.php` | Theme, menu, toasts, modals |
| Mail | `includes/sendOtp.php` → the site's `includes/mailer.php` | Gmail address + app password from Setup (fallback `MOCKZONE_SMTP_*`), logged in `mailLogs`; never add a second mailer |
| Search | `api/centralSearch.php` | Header search over menu-visible `routesMaster` rows |
| Setup | `pages/setup.php`, `api/settings/{saveSettings,sendTestEmail}.php`, `app/models/SettingsModel.php` | Brand files (re-encoded PNG into `uploads/brand/`), contact details + Gmail address/app password in `siteSettings` (site-owned table, app password encrypted via the site's `includes/site-settings.php`), own name/email/password (current password required) |

## 4. Authentication (no permissions)

- Admins (`users`) log in with email + password, optionally OTP-gated. There are no roles,
  permission tables or per-button checks; `routes.php` and `api-gateway.php` only require login.
- Hiding a button is never authorisation. If roles are ever needed, add them deliberately
  (migration + server checks) rather than restoring the Modlus permission tables.
- Mutating APIs must call `requireValidCsrfToken()`.

## 5. Database principles

- **One database for MockZone.** Site and admin share `mockzone`. Admin screens read site-owned
  tables (`students`, `studentLoginOtps`, `mailLogs`) directly; never copy student data.
- **Ownership:** site tables in root `database/migrations/NNN_description.sql`; admin tables (`users`,
  `routesMaster`, future staff content tools) in `admin/database/migrations/YYYY-MM-DD-description.sql`.
  A table has exactly one owner.
- **Naming:** camelCase table and column names (`routesMaster`, `studentLoginOtps`, `createdAt`),
  `id` auto-increment primary key, `createdAt` + `updatedAt`, flags `isActive`/`isPublic` TINYINT(1).
  Windows MySQL shows table names in lower case (`lower_case_table_names=1`); the SQL and code use
  camelCase, and Linux production keeps it — so build production from the migrations, never by
  copying a Windows database.
- **Engine and charset:** InnoDB, `utf8mb4` / `utf8mb4_unicode_ci` (not `utf8mb4_uca1400_ai_ci`).
- Every new page ships a migration inserting its `routesMaster` row. Migrations are additive and
  idempotent; no destructive SQL without explicit approval. Prepared statements only.

## 6. Recipe: adding a MockZone admin module

Example: "Course PDFs manager".

1. **Migration** `admin/database/migrations/2026-10-01-course-pdfs.sql`: tables +
   `INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isMenuVisible, sortOrder) VALUES ('/course-pdfs', '/pages/course-pdfs.php', 'Course PDFs', 'MockZone', 'admin', 1, 10);`
2. **Menu:** `moduleName` must match a group key in `includes/sidebar-menu.php`, or the item is hidden.
3. **Model** `app/models/CoursePdfModel.php`: prepared statements only, no HTML, no `$_POST`.
4. **API** `api/course-pdfs/{getCoursePdfs,saveCoursePdf,deleteCoursePdf}.php`: `require db.php` +
   model → `header('Content-Type: application/json')` → `requireValidCsrfToken()` for POST → validate
   → model → `json_encode(['success' => bool, 'message' => string, 'data' => …])`. The gateway already
   enforces login. Catch `Throwable`, log detail, return a generic message.
5. **Page** `pages/course-pdfs.php`: `auth.php` → `header.php` → `sidebar.php` → theme markup and
   `showToast()` → inline jQuery calling the API with the CSRF header → `footer.php`.
6. **Files:** store under `uploads/` (§7). Update `PROJECT_MAP.md` and this file.
7. **Verify:** `php -l`; test the API logged in / logged out / without CSRF; desktop + mobile.

## 7. Media and uploads (single place)

All managed media for the website **and** the admin lives in `admin/uploads/`:

```
admin/uploads/
├─ .htaccess              public whitelist: png jpg jpeg gif webp avif ico pdf mp4 webm only;
│                         no listing, PHP engine off, double extensions (x.php.png) refused, nosniff
├─ brand/                 logo.png, favicon.png, logo-large.png (site header/footer + admin)
├─ images/courses/        course banners      → 'image' in includes/course-content.php
├─ images/testimonials/   testimonial photos  → 'image' in pages/home.php testimonials
└─ private/               .htaccess denies everything (incl. a FilesMatch override of the whitelist)
   └─ practice-pdfs/      practice + course PDFs, streamed only by the site's api/practice/download.php
```

- Site references are relative (`admin/uploads/brand/logo.png`); admin uses `UPLOAD_URL`.
- Theme design art (hero shapes, backgrounds) stays in the site's `assets/imgs/` and the admin
  `dist/`; it is template code, not managed media.
- Future upload code: validate with `finfo`, generated filenames, whitelisted extensions only; files
  needing a login go to `private/` and are streamed by an authorised endpoint.
- `uploads/` is committed (see `admin/.gitignore`); never put secrets or personal data in it.

## 8. Security rules (non-negotiable)

1. **No secrets in code.** DB, SMTP, keys come from env vars. Rotate the Modlus production DB and
   Gmail passwords that were hard-coded before 2026-09-25 (they remain in old backups/history).
2. **Private folders stay private:** `database/` and `uploads/private/` carry `Require all denied`.
3. **No production data in the repo** (the Modlus dump was deleted 2026-09-25).
4. **Hide PHP errors in production** (`header.php` sets `display_errors=1`).
5. Server-side validation, login and CSRF on every mutating endpoint; ownership from the session.
6. The admin session is separate from the student session (`MOCKZONESESSID`).

## 9. Cleanup status

Backups (private, contain secrets/personal data): `C:\Users\Varun\mockzone-backups\admin-before-cleanup-2026-09-25.tar.gz`
(everything before cleanup) and `admin-before-permission-removal-2026-09-25.tar.gz`.

Done 2026-09-25: Modlus modules (528 files), employee portal, permission system (5 tables, pages,
APIs, button client), company settings, `/permission-denied`, signup method; media centralised in
`uploads/`; admin mail moved onto the site mailer.

Also removed (approved): `vendor/`, composer/npm/esbuild files, Modlus `README.md`/`CLAUDE.md`/`.claude/`, the
Modlus production dump, `storage/` (Gmail password JSON), `logs/`, the Modlus mailer + PHPMailer copy,
`pdf_acknowledgment`, `basic-config`, `Crypto`, signup/setup/basic-setup demo pages, `dist/assets/icon-fonts/`,
and every theme script/lib not loaded (kept: 11 `js/` files and the libs `@popperjs @simonwep
@tarekraafat apexcharts bootstrap choices.js filepond flatpickr node-waves quill simplebar`).
Later the same day: unused Choices/Pickr/autoComplete/ApexCharts + the demo sales-chart script, Spruko images, source maps and
the one-off drop-permission migration. Admin size 287 MB → 26 MB (10,320 → 492 files). Kept theme libs: `@popperjs bootstrap
filepond flatpickr node-waves quill simplebar` (FilePond/Quill/flatpickr power the footer helpers for future forms).
Env vars renamed: `MOCKZONE_ADMIN_URL`, `MOCKZONE_DEV_MODE`; unused `ENCRYPTION_KEY` removed. New pages that need another theme lib restore it from the backup.

## 10. Open decisions (do not decide silently)

- **Hosting path:** admin at `/admin` on the same domain, or a subdomain.
- **Production DB engine:** MariaDB or MySQL 8.x; migrations must suit the choice.
- **Roles:** admins only today; revisit only on an explicit requirement.

## 11. Working rules for every admin task

1. Read this file, then only the files for the module being changed.
2. Keep changes small; do not refactor neighbouring code during feature work.
3. New page = migration (route) + model + API + page + menu group, in the same change.
4. Run `php -l` on touched files and test auth, CSRF and validation paths. Never claim a test that was not run.
5. Update this file and `PROJECT_MAP.md` when an architectural fact changes.

## Changelog

| Date | Change |
|---|---|
| 2026-09-25 | Setup page (`/setup`); unused Modlus files, vendor and theme libs removed (287 MB → 39 MB). |
| 2026-09-25 | Permission system and company concept removed (admins only); `includes/admin-session.php` replaces `permission-helper.php`; gateway requires login; media centralised in `uploads/` with hardened `.htaccess`; admin OTP mail via the site mailer (`mailLogs`); malware scan clean. |
| 2026-09-25 | New `mockzone` DB + admin baseline migration, CLI admin creation, MockZone dashboard; employee/candidate portal removed. |
| 2026-09-25 | Phase 1 cleanup: 528 Modlus files removed; backup taken first. |
| 2026-09-24 | Initial principles from an analysis of the imported Modlus admin. |
