# CLAUDE.md — Elma Real Estate CRM

## Purpose
Compact persistent context for Claude working on this project. **Read this file first.**
Do not re-scan the whole project to rediscover architecture or settled decisions; inspect only
the files relevant to the current task. If a task conflicts with this file, explain the conflict
before changing architecture.

---

## 1. Project

**Real Estate Sales CRM** for **Elma Real Estate** (client-approved scope, quotation 23 Sep 2026).

Built by converting an existing Modlus codebase: same foundation, admin experience and master
components, with CRM business logic. Unrelated Modlus modules (social media, HRMS, agreements,
recruitment) were removed in Phase 1. Do not rebuild the application from scratch.

- Stack: Core PHP + MySQL/MariaDB (mysqli, prepared statements), Bootstrap theme in `dist/`, jQuery + AJAX.
- Local: WAMP (`http://localhost/elma`). Hosting: Hostinger.
- `MOCKZONE_ADMIN_PRINCIPLES.md` describes a different project. Use it only for compatible
  coding/UI conventions; never apply its architecture rules (it has no roles/permissions).

---

## 2. Environment & database — CRITICAL

- The CRM has its **own database**: `elma_realestate_crm`. **Never connect to, migrate, or modify
  any Modlus database.** `includes/db.php` refuses any DB name or user containing `modlus`.
- All configuration comes from `CRM_*` environment variables (never `MODLUS_*`):
  `CRM_DB_HOST`, `CRM_DB_USER`, `CRM_DB_PASS`, `CRM_DB_NAME`, `CRM_DB_PORT`, `CRM_BASE_URL`,
  `CRM_ENCRYPTION_KEY`, `CRM_SUPER_ADMIN_EMAILS` (comma list), `CRM_BRAND_NAME`, `CRM_DEV_MODE`.
- Local fallback: MySQL user `elma_crm` (no password, granted **only** `elma_realestate_crm.*`).
  Any non-local host without `CRM_DB_*` fails closed. No secrets in code, ever.
- `BRAND_NAME` (default "Elma Real Estate") is the client-facing name for titles, toasts and auth
  emails. Internal identifiers (`ModlusUI`, `data-modlus-*`) are not renamed.
- Session cookie is `ELMACRMSESSID` (isolated from other apps on the same host).
- `storage/basic-config.json` holds runtime settings incl. the Gmail app password. It is
  gitignored and web-blocked; never commit it.

### Migrations
`database/migrations/YYYY-MM-DD[x]-crm-*.sql`: additive, idempotent, run against the CRM DB only.
Baseline (Phase 1): `2026-09-26-crm-baseline-schema.sql` → `b` routes seed → `c` deactivate
Modlus routes → `d` provisional role access → `e` sidebar structure.
Table names use the casing the code uses (Linux is case-sensitive): `employeeusers` lower-case,
everything else camelCase (`routesMaster`, `leadFollowUps`…). Engine InnoDB, `utf8mb4_unicode_ci`.
First admin: `php database/create-admin.php "Name" email@example.com` (CLI only).

---

## 3. Architecture to preserve

| Concern | Where |
|---|---|
| Page routing | `.htaccess` → `routes.php` → `routesMaster` row (`isActive`, `isPublic`, `pageFile`) + `hasRoutePermission(path,'canView')` |
| API routing | `.htaccess` → `api-gateway.php` → `api/<module>/<action>.php` (+ `permissionActions` mapping) |
| Response format | JSON `{success, message, data}` |
| Admin auth | `users` table, `$_SESSION['userId']`, `includes/auth.php`; admins have full access |
| Employee auth | `employeeusers`, `$_SESSION['candidateId']`, `includes/emp-auth.php` (enforces forced password reset) |
| Permissions | role = `employeeusers.designationName`; `userPermissionOverrides` → `rolePermissions` → deny; button actions via `permissionActions`/`roleActionPermissions` (`includes/permission-helper.php`) |
| CSRF | `includes/Csrf.php`; `CSRF_TOKEN` JS const; send `X-CSRF-Token`; catch `requireValidCsrfToken()` and return 403 JSON |
| DB | `includes/db.php` (`getDbConnection()` / global `$con`) |
| Sidebar | DB-driven: `routesMaster.moduleName` must match a group in `includes/sidebar-menu.php` |
| Layouts | admin: `header.php`/`sidebar.php`/`footer.php`; employee: `emp-header.php`/`emp-sidebar.php`/`emp-footer.php` |
| Activity log | `includes/leadActivityLogger.php` → `leadsActivityLogs` (append-only) |
| Mail | `includes/mailer.php` (Gmail from Basic Setup) — never add a second mailer |

Roles: **Admin** (`users`), **Sales Manager**, **Sales Executive** (`employeeusers.designationName`).
Employee accounts are created from Employee Directory → Add Employee
(`api/employee/addEmployee.php`: Active + Verified, temporary password hash only, forced reset).
Do not create a new auth or permission system.

### CRITICAL: Sidebar/Menu
Every new page ships a migration inserting its `routesMaster` row with the right `moduleName`
**in the same change**. Admin groups: `CRM` (Dashboard), `Lead Management`, `Projects`,
`Employees`, `Reports`, `Integrations`, `Setup` (shown as Settings). Employee groups:
`Employee Panel`, `Lead Management`, `Projects`. Empty groups are hidden automatically.

---

## 4. Master UI Rule

Every new CRM page = existing masters + CRM logic. Do **not** add another admin theme or
table/modal/toast/alert framework.

| Master | Reference |
|---|---|
| Page | `pages/leads.php` (admin), `employee/emp-leads.php` (employee) |
| DataTable | DataTables 1.12.1 + Buttons 2.2.3 (CSV/Excel/PDF export) as initialised in `dist/assets/js/lead.js`; `data-ui-table="mamix"` |
| Modal | Bootstrap `.modal fade` inline in the page (e.g. `#addLeadModal`, `#employeeAddModal`) |
| Alert / Confirmation | Bootstrap confirm modal (`#deleteConfirmModal` in `pages/leads.php`) |
| Toast | `window.showToast(type, message)` (`includes/footer.php`) |
| Form | `.needs-validation` + `ModlusUI.initFormValidation`, flatpickr, Choices, FilePond (`data-ui-upload="filepond"`) |
| Filter | status / employee / date-range row in `pages/leads.php` |
| Status badge | `.lead-status-<status>` + `formatStatusLabel()` in `lead.js` |
| Charts | ApexCharts (`dist/assets/js/lead-dashboard.js`) |
| UI gallery | `/setup` (hidden from menu) |

Practical, compact admin UI; preserve existing design; smallest necessary change.

**Filter bar rule (whole project, always):** every list page's filters, date range, reset
button and search sit on **one row** on desktop, wrapping only below 992px. Use
`<div class="crm-filter-bar">` (rules in `includes/crm-ui.php`, loaded by both layouts) and
`class="crm-filter-search"` on the search box. Never put bare `.form-select`/`.form-control` in a
`flex-wrap` row — Bootstrap makes them 100% wide and every filter stacks vertically.

---

## 5. CRM scope (client-approved)

Leads (auto capture + manual) · Sales Pipeline (New → Contacted → Interested → Follow-up →
Site Visit → Negotiation → Converted / Lost) · Assignment / Reassignment · Follow-ups (Today,
Upcoming, Overdue, Completed) · Lead Activity (append-only) · Call quick action (`tel:`, VoIP-ready)
· WhatsApp quick action (`wa.me`, Business-API-ready) · Projects + Project Documents (brochures,
floor plans, price lists, images, other) · Employees + Roles/Permissions · Dashboard · Reports ·
Export · Meta Lead Ads / Google lead / Website lead integrations · Settings.

**Do not add unless explicitly requested:** payroll, attendance, leave, HRMS, tenant/rent
management, owner or customer portal, property inventory/unit management, accounting, brokerage
or commission calculations, booking engine, social media/marketing automation.
Never fake external integrations (Meta, Google, telephony, WhatsApp API).

---

## 6. Security rules

- Never trust client-supplied IDs or ownership; check ownership + role server-side. Hiding a button is not authorisation.
- Every mutating API: login + permission + CSRF + validation; prepared statements; escape output.
- Uploads: `finfo` MIME + extension allow-list + generated filenames; `uploads/.htaccess` disables PHP.
- `.htaccess` blocks `includes/ vendor/ pages/ employee/ app/ storage/ database/ cron/ logs/ docs/`, dotfiles, `*.md`, `*.txt`, composer/npm files.

---

## 7. Phase status

**Phase 1 (complete, 2026-09-26):** separate DB + `CRM_*` config; Modlus credentials removed;
baseline schema/routes; 71 Modlus routes deactivated (rows kept, reversible); 473 Modlus files
removed after reference sweeps (backup: `C:\Users\Varun\elma-backups\elma-before-crm-phase1-2026-09-26.tar.gz`);
Add Employee; CRM sidebar; branding; `.htaccess` hardening.

**Phase 2 (complete):** CSRF in api-gateway + routes.php, lead permission/ownership checks, private finfo-validated documents, secrets out of git.

**Phase 3 (complete, 2026-09-28):** real-estate lead model — `projects`/`projectDocuments`, `leadSources`, pipeline `new…lost`, `leads.assignedToId` (= access owner), `assign_lead` / `view-all-leads` special actions, one follow-up system (`leadFollowUps`, views today/upcoming/overdue/completed), actor types on all audit rows, Call/WhatsApp logging. Modlus fields/tables dropped (migration `2026-09-27b`). Shared page bodies: `includes/lead-page.php`, `follow-up-page.php`, `project-page.php`, `lead-activity-page.php`.

**Historical Phase 1 gaps (fixed in Phase 2/3):**
- `api/leads/*` and `api/employee/*` (except `addEmployee`), `api/company/*`: no CSRF; lead APIs check session only, not route/action permission; `permissionActions` has no API mappings yet.
- `deleteLead` / `updateLead` / `updateLeadStatus`: no ownership check.
- `uploadLeadDocument`: extension-only check → add `finfo`.
- Lead ownership is `leads.createdByCandidateId` (creator = owner); no assignee column yet.
- Login forms have no CSRF token.

**Next phases:** 2 CRM DB foundation (lead fields: assignee, project, pipeline statuses,
integration source ids; roles matrix; security fixes) · 3 Leads/pipeline/follow-ups/Call/WhatsApp
· 4 Projects + documents · 5 Dashboard + reports · 6 Meta/Google/website integrations (rewrite
the Modlus-branded legal pages first) · 7 testing, deployment.

---

## 8. How Claude must work

- Read this file → identify relevant files → inspect engine/API/page/migration/permission/sidebar → edit.
- Before deleting anything: search references (PHP includes, API paths in JS, route slugs, table names), deactivate routes first.
- Run `php -l` (PHP 8.3 at `C:/wamp64/bin/php/php8.3.28/php.exe`) on every touched file; test auth, permission, CSRF, validation and ownership paths.
- Never claim a test or browser verification that was not performed.
- Do not commit, push or deploy unless explicitly asked.
- After work report: files changed, DB changes, security changes, tests + results, browser verification status, deferred items, concerns.
