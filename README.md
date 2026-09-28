# Elma Real Estate CRM

Web-based Real Estate Sales CRM for **Elma Real Estate**: captures enquiries from digital and
offline sources, moves them through the sales pipeline, schedules follow-ups, and gives the sales
team and management one place for leads, communication history, projects and reports.

Built on an existing core-PHP admin foundation (routing, API gateway, role permissions, admin +
employee portals, master UI components); unrelated modules from that codebase were removed.
Engineering context for AI assistants lives in [CLAUDE.md](CLAUDE.md).

## Main modules

| Module | Status |
|---|---|
| Leads (manual entry, CSV import, pipeline, assignment, remarks, documents, Call/WhatsApp) | Available |
| Follow-ups (Today / Upcoming / Overdue / Completed; rule-based + manual) | Available |
| Project Portfolio + documents (images, brochures, floor plans, price lists) | Available |
| Lead Dashboard / Lead Activity (append-only, actor-typed history) | Available |
| Employees (accounts, roles, permissions) | Available |
| Management dashboard + reports + exports | Planned (Phase 5) |
| Meta Lead Ads / Google lead forms / website enquiry integrations | Planned (Phase 6) |

## Tech stack

- PHP 8.x (core PHP, no framework), MySQL 8 / MariaDB via `mysqli` prepared statements
- Bootstrap 5 admin theme (prebuilt in `dist/`), jQuery, DataTables 1.12 + Buttons, ApexCharts
- PHPMailer (Gmail SMTP with an app password)
- Apache / LiteSpeed with `mod_rewrite` (`.htaccess` routing)

## Structure

```
routes.php        page router (routesMaster table) + login/permission + CSRF on POST
api-gateway.php   entry for every api/**.php: login + CSRF on non-GET + mapped action checks
pages/            admin pages            employee/   employee portal pages
api/<module>/     JSON endpoints ({success, message, data})
includes/         config, db, auth, permissions, CSRF, layouts, engines, mailer
app/              auth controllers/views (admin + employee login, OTP, password reset)
database/         migrations + CLI scripts (web-blocked)
storage/          runtime config + private lead documents (web-blocked, gitignored)
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
| `CRM_DB_HOST`, `CRM_DB_PORT`, `CRM_DB_NAME`, `CRM_DB_USER`, `CRM_DB_PASS` | Yes (outside localhost) | Database connection; the app refuses to start without them |
| `CRM_BASE_URL` | Recommended | Public base URL (needed for CLI; auto-detected on web requests) |
| `CRM_ENCRYPTION_KEY` | Before storing integration secrets | Key for `includes/Crypto.php`; empty = encryption refused |
| `CRM_SUPER_ADMIN_EMAILS` | Recommended | Admins allowed into Route / Page Setup (comma-separated) |
| `CRM_SMTP_USERNAME`, `CRM_SMTP_APP_PASSWORD` | Optional | Mail credentials; override the Basic Setup values |
| `CRM_BRAND_NAME` | Optional | Client-facing name (default "Elma Real Estate") |
| `CRM_DEV_MODE` | Local only | `1` skips real OTP checks — never in production |

Safety: a database or DB user whose name contains `modlus` is always refused.

## Database and migrations

- Migrations live in `database/migrations/YYYY-MM-DD[x]-crm-*.sql`, are additive and idempotent,
  and are applied in filename order:
  ```bash
  mysql -u elma_crm -p elma_realestate_crm < database/migrations/2026-09-26-crm-baseline-schema.sql
  # ... then 2026-09-26b, c, d, e, f in order
  ```
- Table names follow the casing used in code (Linux MySQL is case-sensitive): `employeeusers`
  lower-case, everything else camelCase. Build production from the migrations — never by copying
  a Windows database.
- No migration creates user accounts or passwords.

## Users, roles and permissions

- **Admin** — `users` table, full access (admin panel).
- **Sales Manager / Sales Executive** — `employeeusers`, role = `designationName`, employee portal.
- Permissions: `userPermissionOverrides` → `rolePermissions` → deny; button/API actions via
  `permissionActions` + `roleActionPermissions` (managed in **Roles & Permissions**).
- Lead access: admins see all leads; employees see only leads they own unless their role has the
  **View All Leads** action (granted to Sales Manager by default).

## Security notes

- **Never commit secrets.** `.env`, `storage/*.json`, logs, uploads and private documents are
  gitignored; `storage/`, `database/`, `logs/`, `cron/`, dotfiles and docs are blocked from the web.
- CSRF: every non-GET API call and every page form post requires the session token (added
  automatically by the layouts' CSRF client and `getCsrfInput()` in forms).
- Uploads are validated by real MIME type (`finfo`), stored under generated names, and lead
  documents are served only through an authorised endpoint.
- Hiding a button is never authorisation — every endpoint checks login, permission and ownership.

## Development conventions

- Reuse the existing masters (page structure, DataTables, Bootstrap modals, confirm modal,
  `showToast()`, form validation, filters, status badges). No new UI/CSS/JS frameworks.
- New page = migration (routesMaster row with the right `moduleName`) + API + page + sidebar group,
  in one change. Mutating APIs: POST, permission, ownership, validation, JSON response.
- camelCase for variables, tables and columns; prepared statements only; escape all output.
- Run `php -l` on every touched file and test auth / permission / CSRF / ownership paths.
