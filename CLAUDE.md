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

**Phase 5 (complete, 2026-09-29):** Management Dashboard + Reports, reusing the existing
`LeadDashboardEngine`/lead APIs rather than building new ones.
- `includes/lead-dashboard-page.php` is the ONE dashboard body shared by `pages/dashboard.php`
  (admin landing), `pages/lead-dashboard.php` and `employee/emp-lead-dashboard.php` (Sales Manager,
  already gated by existing route permission). KPIs: Total/New Leads, Today's/Overdue Follow-ups,
  Site Visits, Converted/Lost Leads, Conversion Rate. Charts: Lead Trend, Sales Pipeline (status
  donut), Leads by Source, Leads by Project, Follow-up Performance, Leads by Sales Executive
  (renamed from "Employee Performance", same chart). Filters (one row, `.crm-filter-bar`): Date
  Range, Assigned Employee (auto-hidden when the caller's scope is restricted), Lead Source,
  Project. Data is auto-scoped by the existing `getLeadScopeEmployeeId()` — a Sales Manager without
  `view-all-leads` (or any other employee) sees only their own leads with zero UI branching.
- `employee/emp-dashboard.php` (the always-accessible employee landing page) gained a compact,
  filter-less "Overview" widget (4 KPI cards + one pipeline chart, this-month only) using the same
  `get-dashboard-summary.php` — this is how a Sales Executive gets their own dashboard without any
  new permission grant (`/emp-dashboard` is already an always-allowed route).
- `includes/leadDashboardEngine.php` extended (not rewritten): `projectId` threaded through every
  method; new `todayFollowUps`/`overdueFollowUps` (same Converted/Lost-excluding rule as
  `leadFollowUpEngine`'s today/overdue views, duplicated as a one-line SQL condition, not
  cross-called), `siteVisits`, `lostLeads`, `conversionRate`, `unassignedLeads`; new `getBySource()`/
  `getByProject()` (each omits its own filter dimension so picking one source/project doesn't
  collapse its own chart to one bar). No Status filter added to the dashboard (would conflict with
  the fixed-status KPI cards) — deliberately out of scope per spec.
- **Reports** (`/reports`, admin-only by default route permission, new sidebar item under the
  pre-existing empty "Reports" group — zero `sidebar-menu.php` changes needed): ONE page, a
  "Report Type" toggle (Leads | Follow-ups) driving ONE DataTable, **zero new API endpoints**:
  - Leads type reuses `api/leads/getLeads.php`, extended with optional `projectId`/`sourceId`
    filters and a `reason` column (latest `leadStatusRemarks.remark` for the lead's current
    status — populated for Converted/Lost, blank otherwise). Covers Lead/Source/Project/
    Sales-Executive/Pipeline/Converted-Lost reporting via filters+columns, not 8 separate reports.
  - Follow-ups type reuses `api/leads/getLeadFollowUps.php` / `leadFollowUpEngine::getFollowUpList()`,
    extended with a new `all` view plus optional `status` (Pending/Completed/Skipped)/`dateFrom`/
    `dateTo`/`projectId`/`employeeId` (the last only honoured for a full-scope caller — a restricted
    caller's own-leads scope always wins). All additions are no-ops for the existing Today/Upcoming/
    Overdue/Completed tabs, which never send them.
  - Filter options reuse `api/leads/getLeadMasterData.php` (already returns projects/sources/
    assignees). Export reuses the same DataTables Buttons pattern as `dist/assets/js/lead.js`
    (CSV/Excel/PDF, respects active filters since it exports the currently-filtered table).
  - Data scope: same `getLeadScopeEmployeeId()` rule as every lead API — if a Sales Manager is
    ever granted `/reports` canView without `view-all-leads`, the report is still correctly
    restricted to their own leads (no special-casing needed).
- **2 real (pre-existing) bugs fixed, found by browser testing:** `dist/assets/js/lead-dashboard.js`
  called `resolveChartColors()` (converts CSS `var(--...)` colors to concrete values, since
  ApexCharts throws on them) only on a chart's *first* render, not on `.updateOptions()` — any
  filter change after the initial load crashed every chart. Fixed on all 6 charts. Unrelated:
  `employee/emp-dashboard.php`'s new inline jQuery block loaded before jQuery itself — added the
  missing `<script src="jquery">` tag (this file had no jQuery usage before Phase 5).
- **Follow Up Setup**: kept — `leadFollowUpEngine::generateForLead()` still reads
  `leadFollowUpSettings` to auto-generate rule-based follow-ups on lead creation. Still needed.
- **Basic Setup**: audited, nothing dead — Gmail (OTP mail), Organization Roles (= permission
  designations), Departments (employee directory) are all still live. No changes made.
- Migration: `2026-09-29-crm-phase5-reports.sql` (routesMaster row only; no new tables — Dashboard
  reuses the routes already registered in Phase 1).
- Status badge colours (`.lead-status-<status>`) moved from `includes/lead-page.php` into the
  already-shared `includes/crm-ui.php`, so Reports (and any future page) gets them without
  redefining them — no visual change on the Leads page itself.

**Phase 6 (complete, 2026-09-28):** Lead Capture Integrations — Meta Lead Ads, Google Lead Forms,
Website enquiry form. All three share ONE creation path; nothing integration-specific was added to
Dashboard/Reports/Leads.
- **Central creation path** (`includes/leadAccess.php`): `createLeadCore()` is the single
  insert+activity+follow-up-generation routine, extracted from `api/leads/addLead.php` (which now
  just resolves fields and calls it — behavior-preserving, confirmed by the full regression suite
  before any integration code was written). `createLeadFromSource($con, $externalSource,
  $externalLeadId, $lead)` wraps it for external sources: checks `externalSource`+`externalLeadId`
  dedup first (falls back to the pre-existing phone+countryCode dedup, not a new rule), forces
  `status='new'`, and calls `createLeadCore()` with `actor=['type'=>'system','id'=>0]`. Every
  integration lead therefore gets the same activity logging, auto-assignment, and
  `leadFollowUpEngine::generateForLead()` follow-up generation as a manually entered lead — and
  shows up in Dashboard/Reports/Leads/exports with zero integration-specific queries (verified: a
  website-captured lead appeared correctly via `getLeads.php?sourceId=`, and
  `get-dashboard-summary.php` returns 200 with integration leads present).
- **Idempotency**: `leads.externalSource`/`externalLeadId` (both nullable, `UNIQUE KEY
  uq_leads_external`) — MySQL treats each NULL pair as distinct, so manual leads are unaffected. A
  retried webhook/duplicate external id returns success without a second row (verified for all
  three providers).
- **`includes/integrationAccess.php`** (new): `INTEGRATION_PROVIDERS`, settings CRUD
  (`getIntegrationSetting/Secrets/Config`, `saveIntegrationSettings` — a `null` `$secrets` arg
  leaves the encrypted value unchanged, so the settings form never round-trips a decrypted secret),
  `logIntegrationEvent()` (append-only, `payloadSummary` capped at 500 chars of normalized fields
  only — never a raw payload or token), provider auth (`verifyMetaSignature`/`verifyMetaChallenge`/
  `verifyGoogleKey`/`verifyWebsiteApiKey`), `metaGraphApiGet()`, `flattenMetaFieldData()`,
  `normalizeExternalLeadFields()` (maps common aliases to fullName/phone/email/remark; unrecognized
  fields are folded into the remark, never invented as new CRM fields, never break lead creation).
- **Secrets**: one encrypted JSON blob per provider in `integrationSettings.secretEncrypted`, using
  `includes/Crypto.php`'s `encryptSecret`/`decryptSecret` (`CRM_ENCRYPTION_KEY`) — this was the
  first real use of that file. Never returned by any API (`getIntegrationSettings.php` only reports
  `hasSecret: bool`), never logged.
- **Meta** (`api/integrations/meta-webhook.php`, public): GET handshake
  (`hub_mode`/`hub_verify_token`/`hub_challenge`) needs only a configured verify token (can succeed
  before the integration is marked enabled, since Meta requires the handshake first). POST requires
  `isEnabled` + a valid `X-Hub-Signature-256` (HMAC-SHA256 of the raw body with the App Secret).
  The webhook payload only ever carries `leadgen_id`/`form_id`/`page_id` — `metaGraphApiGet()`
  fetches `field_data` via `GET /{leadgen_id}?access_token=...` using the configured Page Access
  Token. **Implemented and locally verified** (handshake, invalid-token 403, valid/invalid
  signature, error logging when no Page Access Token is set) but **not live-verified** — no real
  Meta Page Access Token was available, so the Graph API fetch itself has not been exercised
  against Meta's live API.
- **Google** (`api/integrations/google-lead.php`, public): Google Lead Form Extensions' documented
  flow — POST with a shared `google_key` + `user_column_data: [{column_id, string_value}]`, no
  OAuth. **Implemented and locally verified** with simulated payloads only — no live Google Ads
  account was used.
- **Website** (`api/integrations/website-lead.php`, public): `POST` with `name`/`phone`/`email`/
  `project`/`message`/`requestId` (used as the external id for idempotency), authenticated by an
  `X-Integration-Key` header checked against the encrypted `apiKey` secret (not a CRM session — this
  is a machine endpoint). **Implemented and fully verified end-to-end** (this is our own form
  contract, not a third party's) — valid create, duplicate retry, invalid key, missing phone all
  confirmed against the running app and DB.
- **Project mapping** (`integrationFormMappings`, Meta/Google only — Website has no forms): provider
  + externalFormId → optional projectId/defaultAssigneeId, `getFormMapping()`/`saveFormMapping.php`.
  A mapped project that's since been deactivated falls back to unmapped rather than rejecting the
  lead (never silently discard a valid lead over a mapping problem).
  `api/integrations/getFormMappings.php` / `saveFormMapping.php` (admin, `manage_integrations`).
- **Assignment**: simplest useful rule only — mapping's `defaultAssigneeId`, else the provider's own
  `config.defaultAssigneeId`, else unassigned. No round robin/routing.
- **Activity actor**: all integration leads have `createdByType='system'` /
  `leadsActivityLogs.actorType='system'` — verified never `admin`/`employee` for an automated lead.
- **Source**: reused the existing `leadSources` master (`meta_lead_ads`/`google_ads`/`website`
  keys, already seeded) — no new source table. An admin-configured `config.defaultSourceId` can
  override which existing source row is used.
- **Integrations Settings page** (`/integrations`, admin sidebar group "Integrations", already
  existed as an empty group): `pages/integrations.php` → `includes/integrations-page.php` (shared
  body, existing card/modal/DataTable/toast masters — no new UI framework) +
  `dist/assets/js/integrations.js`. Three provider cards (Connected/Not Configured,
  Enabled/Disabled, Last Successful Lead, Last Error — statuses are read from real DB state, never
  hardcoded), one Configure modal (fields toggle per provider), a Form Mapping table+modal
  (Meta/Google), and a Log table with the standing one-row `.crm-filter-bar` (provider/status
  filters + search). APIs: `getIntegrationSettings.php`, `saveIntegrationSettings.php` (gated by the
  `manage_integrations` special action via `requireApiActionPermission()`, same pattern as every
  other admin-only settings API), `getIntegrationLogs.php`, `getFormMappings.php`,
  `saveFormMapping.php`.
- **`api-gateway.php`**: added an explicit 3-item allowlist
  (`meta-webhook.php`/`google-lead.php`/`website-lead.php`) exempting only session+CSRF for those
  exact files — the path-traversal/file-existence checks above it still apply unchanged. Verified:
  all three are reachable with no session (no 401; they return their own provider-specific
  status/error instead), while every other existing API still returns 401 without a session.
- **Permissions/sidebar**: `/integrations` is `layoutType='admin'`, not in any employee sidebar
  group; admins always pass (existing admin-bypass rule), `manage_integrations` is a special action
  not granted to any role by default. No `sidebar-menu.php` changes needed (the "Integrations" group
  already existed, empty, from Phase 1).
- **Privacy/Terms/Data-Deletion pages**: rewritten for Elma Real Estate (previously
  Modlus-branded, describing social-media features that don't apply to this CRM) and their
  routes reactivated by the migration. Content is implementation-accurate (describes actual lead
  capture/storage/retention behavior) but uses a placeholder `CONTACT_EMAIL` — **the client's legal
  counsel must review and finalize this content, and replace the placeholder contact, before using
  these pages for a real Meta App Review submission.**
- Migration: `2026-09-30-crm-phase6-integrations.sql` — `leads.externalSource`/`externalLeadId` +
  unique key; `integrationSettings`/`integrationFormMappings`/`integrationLogs` tables; `/integrations`
  route + `manage_integrations` action; reactivates the 3 legal-page routes. Additive/idempotent,
  applied twice locally to confirm.
- **Tests**: full existing regression suite (`sectest.sh`, phases 1–5) re-run after the
  `addLead.php`/gateway changes — 201/201 passed, no regression. New `sectest-phase6.sh` (36 checks:
  admin-only access, website/Google/Meta valid/invalid/duplicate/mapping flows, actor=system,
  secret-never-exposed, dashboard/report visibility) — combined suite 235/235 passed. Browser
  verification (`run-p6.mjs`) at 1440/1280/390px: 28/28 passed, zero console/JS/network errors —
  3 provider cards, configure modal (field toggling, endpoint URL), mapping table+modal, log table
  with one-row filter bar at ≥992px / wraps at 390px, employee correctly denied `/integrations`, no
  regression to Dashboard/Reports.
- **Deferred / needs real credentials before go-live**: a live Meta Page Access Token to verify the
  actual Graph API fetch; a live Google Ads Lead Form Extension to verify Google's real payload
  shape matches what's implemented; final legal review of the 3 rewritten legal pages and a real
  contact address; rotating out the test secrets used during local verification
  (`getIntegrationSettings.php` currently reports Meta/Google/Website as configured with test
  values — replace before any real provider is pointed at these endpoints).

**Historical Phase 1 gaps (fixed in Phase 2/3):**
- `api/leads/*` and `api/employee/*` (except `addEmployee`), `api/company/*`: no CSRF; lead APIs check session only, not route/action permission; `permissionActions` has no API mappings yet.
- `deleteLead` / `updateLead` / `updateLeadStatus`: no ownership check.
- `uploadLeadDocument`: extension-only check → add `finfo`.
- Lead ownership is `leads.createdByCandidateId` (creator = owner); no assignee column yet.
- Login forms have no CSRF token.

**Next phases:** 7 testing, deployment.

---

## 8. How Claude must work

- Read this file → identify relevant files → inspect engine/API/page/migration/permission/sidebar → edit.
- Before deleting anything: search references (PHP includes, API paths in JS, route slugs, table names), deactivate routes first.
- Run `php -l` (PHP 8.3 at `C:/wamp64/bin/php/php8.3.28/php.exe`) on every touched file; test auth, permission, CSRF, validation and ownership paths.
- Never claim a test or browser verification that was not performed.
- Do not commit, push or deploy unless explicitly asked.
- After work report: files changed, DB changes, security changes, tests + results, browser verification status, deferred items, concerns.
