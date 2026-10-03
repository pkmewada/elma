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

**Phase 6.5 (complete, 2026-09-28):** WhatsApp CRM Chat via the official Meta WhatsApp Cloud API —
1-to-1 chat inside the CRM, so CRM users no longer need WhatsApp Web/Desktop/mobile for normal
communication. Reuses Phase 6's integration settings/secret/log infrastructure and the Mamix
theme's own (pre-existing but unused since Phase 1's demo-page cleanup) chat CSS — no new theme,
no WebSockets, no queue.
- **Data model**: `whatsappConversations` (leadId nullable, waId unique, phoneNumber, customerName,
  assignedToId nullable — used only until a conversation is linked to a lead, lastMessageAt/
  lastMessagePreview/unreadCount) and `whatsappMessages` (conversationId, metaMessageId nullable-
  unique, direction, messageType, messageText, mediaId/mediaPath, status, errorMessage, sentByType/
  sentById, sentAt/deliveredAt/readAt). Both additive; no existing table changed except
  `integrationSettings.provider` ENUM widened to add `'whatsapp'` as a 4th provider (same table,
  same secret/log pattern as Meta/Google/Website — no second config system).
- **Conversation access** (`includes/whatsappAccess.php`): reuses `getLeadScopeEmployeeId()`/
  `canAccessAllLeads()` from `includes/leadAccess.php` directly — no second permission model. A
  linked conversation's owner is its lead's `assignedToId`; an unlinked conversation's owner is its
  own `assignedToId` (or nobody, in which case only a full-scope caller — admin / `view-all-leads`
  — can see it, until it's linked/assigned). Verified: a Sales Executive gets 404 on an unassigned
  conversation, and gains access the moment its lead is assigned to them.
- **Inbound flow** (`api/integrations/whatsapp-webhook.php`, public): same Meta webhook mechanism
  already built for Lead Ads in Phase 6 (`verifyMetaChallenge()`/`verifyMetaSignature()` reused
  as-is — GET handshake, POST signed with `X-Hub-Signature-256`). Handles two independent event
  shapes per Meta's docs: `messages[]` (dedup by `metaMessageId`, find-or-create the conversation by
  `wa_id`, best-effort auto-link to an existing lead by phone via `findLeadByWaId()`, store the
  message, bump `unreadCount`, log a summary-level `WHATSAPP_RECEIVED` lead activity — never the
  full message body) and `statuses[]` (updates the matching outbound message's status; a rank check
  in `whatsappStatusRank()` means an out-of-order/duplicate webhook can never downgrade an
  already-more-advanced status, e.g. a late `sent` after `delivered` is ignored).
- **Outbound flow** (`api/whatsapp/send-message.php`, normal CRM session + CSRF +
  `send_whatsapp_message` special action + `requireConversationAccess()`): inserts the message row
  as `pending` **before** any Meta API call (media upload included) so a failure at any step still
  leaves a visible, retryable `failed` row instead of silently vanishing — a real bug caught during
  testing (the first draft only inserted the row for text/template; a media-upload failure used to
  exit before ever creating one). On success the row becomes `sent` with the real `metaMessageId`
  (later advanced to `delivered`/`read` by the webhook); on failure a safe, generic reason is stored
  (never the raw Meta response/secrets) and logged to `integrationLogs`.
- **24-hour customer service window**: confirmed via Meta's current docs
  (developers.facebook.com/docs/whatsapp/cloud-api) before implementing — free-form text/media is
  only allowed within 24h of the customer's last inbound message (`isConversationWindowOpen()`,
  computed from `MAX(sentAt) WHERE direction='inbound'`); outside it, `send-message.php` rejects
  free-form sends with 422 and the chat UI shows a banner requiring a template. Template sends are
  never blocked by the window (that's their purpose).
- **Templates**: managed in Meta itself, not by this CRM. An admin lists approved
  name/language/variable-count/label pairs as plain text in the WhatsApp integration card
  (`config.templates`, one per line); the composer's template picker and variable-count inputs are
  driven entirely by that list — no live Meta template-list fetch (no credentials available), and no
  template-management UI was built (matches the "CRM only needs to load/configure/send" scope).
- **Media**: image + document, both directions, reusing the existing private-file infrastructure
  (`includes/privateFiles.php`'s `storePrivateFile()`/`streamPrivateFile()`/type constants — the
  same code lead/project documents already use) under `storage/whatsapp-media/<conversationId>/`,
  served back only through `api/whatsapp/media.php` (session + conversation access re-checked, MIME
  re-verified, never a raw path from the request). Outbound: upload to Meta's `/media` endpoint,
  then send by media id. Inbound: two-step download (fetch URL, then bytes) via
  `downloadWhatsappMediaBinary()`; unsupported inbound types (audio/video/sticker/location/etc.) are
  recorded as metadata-only placeholders, never dropped, never downloaded.
- **Lead linking** (`api/whatsapp/linkLead.php`, full-scope callers only): a small opt-in action on
  an unlinked conversation — "Link to Existing Lead" (validates the lead via the existing
  `requireLeadAccess()`) or "Create Lead" (reuses `createLeadFromSource()` with source `whatsapp`,
  the pre-existing `leadSources.sourceKey='whatsapp'` row — the CRM already had this source, nothing
  new to seed). No automatic lead creation from an inbound message; the conversation is simply
  captured unlinked until someone acts. A wa_id is opportunistically split into the CRM's
  (countryCode, phone) shape only for the exact `91 + 10 digits` pattern the CRM already defaults to
  elsewhere (`splitWaIdForLead()`); any other international number is kept as-is, never guessed.
- **Lead-page WhatsApp action changed** (`dist/assets/js/lead.js`, `includes/lead-page.php`): the
  button no longer opens `wa.me`; it links to the new `WHATSAPP_CHAT_URL` (`/whatsapp` admin,
  `/emp-whatsapp` employee — injected per-layout exactly like the existing `FOLLOW_UP_LEADS_URL`
  pattern) with `?leadId=`, which `getMessages.php` resolves into that lead's conversation
  (creating it on first use) — reuses the pre-existing `?leadId=` deep-link convention from the
  Follow-ups page. No message is ever auto-sent; the user still has to type and hit send. The old
  per-click `logLeadContact.php` "WhatsApp action opened" log entry is gone (superseded by the real
  message-level `WHATSAPP_SENT`/`WHATSAPP_RECEIVED` activity logging); `call-btn` logging is
  unchanged.
- **Chat page** (`/whatsapp` admin, `/emp-whatsapp` employee — `includes/whatsapp-page.php` shared
  body, `moduleName='Lead Management'`, the group both sidebars already have — zero
  `sidebar-menu.php` changes): reuses the Mamix theme's own chat CSS classes (`.main-chart-wrapper`,
  `.chat-info`, `.main-chat-area`, `.chat-content`/`.chat-item-start`/`.chat-item-end`/
  `.main-chat-msg`, `.chat-footer`, `.responsive-chat-close`) straight from
  `dist/assets/css/styles.css` — the theme's demo `chat.html` page was deleted in Phase 1's cleanup
  but its compiled CSS was still present, so no new chat markup/CSS was invented. SimpleBar (already
  loaded globally) for the scroll panes. `dist/assets/js/whatsapp.js`: conversation list polls every
  8s, an open conversation's messages poll every 4s (Section 13's documented interval), search +
  All/Unread/Unlinked filter in a `.crm-filter-bar` row, composer (text/attach/template), window
  banner, Link/Create Lead modal, Send Template modal. All rendered text (customer name, message
  body) goes through the same `esc()`/`.text()` escaping pattern as every other CRM list page —
  verified an inbound `<script>`/`<img onerror>` payload renders inert, never executes.
- **Permissions/sidebar**: `send_whatsapp_message` special action (one `permissionActions` row per
  route, `/whatsapp` and `/emp-whatsapp`), granted by default to Sales Executive and Sales Manager
  (same tier as their existing `/emp-leads` canEdit — the real restriction is conversation
  ownership, not this action). `/emp-whatsapp` also got the same provisional `rolePermissions`
  row (canView/canAdd/canEdit) those two roles already have on `/emp-leads`.
- Migration: `2026-10-01-crm-phase6.5-whatsapp.sql` — `integrationSettings.provider` ENUM widened;
  `whatsappConversations`/`whatsappMessages` tables; `/whatsapp` + `/emp-whatsapp` routes;
  provisional role access + `send_whatsapp_message` action. Additive/idempotent, applied twice
  locally to confirm.
- **Tests**: full existing regression suite re-run after the `lead.js`/`lead-page.php`/
  `api-gateway.php` changes — 235/235 passed, no regression. New `sectest-phase6.5.sh` (40 checks:
  admin-only access, webhook verification/signature/dedup, out-of-order status handling, conversation
  scope (linked/unlinked/newly-assigned), link/create-lead, send validation (CSRF/empty/oversized/
  invalid-conversation), the 24h window (text blocked, template allowed), mark-read, XSS escaping,
  secret-never-exposed, media endpoint access control) — combined suite 275/275 passed. A real bug
  was caught and fixed during this pass: a failed media-upload-to-Meta used to leave no message row
  at all; now every send path inserts `pending` first, matching text/template. Browser verification
  (`run-p65.mjs`) at 1440/1280/390px: 31/31 passed, zero unexpected console/JS/network events (the
  one 404 for a decorative background SVG and the one 502 from the deliberately-fake test token are
  expected, not bugs) — conversation list, opening a thread, composer send attempt, template modal,
  search filtering, XSS-safe rendering, Link/Create Lead action, executive scope restriction, the
  Leads page's WhatsApp button now opening the CRM chat instead of `wa.me`, filter bar one row at
  ≥992px / wraps at 390px.
- **Demo data**: at the user's request, ~10 realistic WhatsApp conversations (varied Indian names/
  numbers, 3–5 messages each, mixed read/delivered/sent/failed outbound statuses, unread counts, 3
  linked to freshly-created demo leads across all three pipeline stages/salespeople, 7 unlinked) were
  seeded via a one-off CLI script for a client walkthrough — not part of the app, not migrated, safe
  to clear before go-live (`TRUNCATE whatsappMessages; TRUNCATE whatsappConversations;`, and delete
  the 3 demo leads it created if desired).
- **Live Meta WhatsApp Cloud API verification status**: **implemented and locally verified** end to
  end (webhook verify/signature/dedup/status-updates, outbound send reaching a real HTTPS call to
  `graph.facebook.com` and failing gracefully on an intentionally-fake token, media upload attempt,
  24h window enforcement, permissions, XSS safety) using a test Phone Number ID/access token — **not
  live-verified against a real WhatsApp Business phone number**, since no live credentials were
  available. The request/response shapes (message send, template send, media send, webhook payload)
  were checked against the current official docs
  (developers.facebook.com/docs/whatsapp/cloud-api) before implementing, not invented.
- **Deferred / needs real credentials before go-live**: a live Phone Number ID + access token to
  verify an actual message reaches a real WhatsApp number and a real inbound webhook fires; a live
  Meta App Secret/Verify Token for the real webhook subscription; confirming the exact approved
  template names/variable counts from the client's own Meta Business Manager; deciding whether to
  keep or clear the demo conversations/leads before go-live.

**Phase 6.5 WhatsApp Template Support + Meta App Review prep (complete, 2026-09-29):** Split
template sending into its own validated endpoint and prepared the Meta App Review paperwork.
Nothing here rebuilds Phase 6.5 — the composer, window-closed detection, and per-provider template
config (`config.templates`) already existed; this closes the one real gap (an unvalidated template
send) and documents Meta App Review readiness.
- **`api/whatsapp/send-template.php`** (new): dedicated endpoint for template sends, split out of
  `send-message.php`'s old `messageType=template` branch. Same gate as every other WhatsApp send
  (session + CSRF via `api-gateway.php` + `send_whatsapp_message` action + `requireConversationAccess()`
  scoping), plus new validation the old branch never had: `findApprovedWhatsappTemplate()`
  (`includes/whatsappAccess.php`) rejects any name/language not present in the admin's configured
  `config.templates` list, and the variable count submitted must exactly match that template's
  configured `variableCount` — both 422 before any Graph API call. `send-message.php` now rejects
  `messageType=template` outright (422, points at the new endpoint) so there is exactly one,
  validated path to send a template. Template sends are allowed regardless of the 24h window (that
  remains their purpose, unchanged from Phase 6.5).
- **`includes/whatsappAccess.php`**: extracted `insertPendingWhatsappMessage()` and
  `finalizeWhatsappSend()` (the pending-row-insert and sent/failed-bookkeeping-plus-activity-and-
  integration-logging that `send-message.php` already had) so `send-template.php` reuses the exact
  same status lifecycle/logging instead of a second copy. `send-message.php`'s text/image/document
  behavior is unchanged — confirmed by re-sending a text message and re-checking `windowOpen`
  handling against the existing demo conversations after the refactor.
- **UI** (`dist/assets/js/whatsapp.js`): template sends now POST to `send-template.php` instead of
  `send-message.php` with `messageType=template` (no `messageType` field needed on that endpoint —
  it's always a template). `#waTemplateBtn` is now also disabled when `!canSend`/integration
  disabled, matching the existing text/attach/send disabling — it was previously always enabled
  regardless of permission. No HTML/modal changes needed: the composer already showed a disabled
  text box + "Send Template" button when the window is closed, and a template picker + per-variable
  inputs modal, from Phase 6.5.
- **Legal pages updated for WhatsApp** (`pages/privacy-policy.php`, `pages/data-deletion.php`):
  Phase 6.5 added WhatsApp conversations/messages as a stored data type but the legal pages never
  mentioned it explicitly (only "follow-up via WhatsApp"). Added: WhatsApp as an enquiry channel and
  as message content collected, a dedicated "WhatsApp Messaging" section in the privacy policy
  (conversation storage, the 24h window, template-only messaging outside it), WhatsApp conversation
  data added to what can be deleted, and Meta/WhatsApp added to the "can't delete third-party
  platform data" notice. `terms-of-service.php` already covered WhatsApp generically and needed no
  change. `CONTACT_EMAIL` remains the Phase 6 placeholder in all three pages (unchanged) — still
  pending the client's real address.
- **Meta App Review readiness audited** (documented in [README.md](README.md) section "9a"):
  privacy/terms/data-deletion URLs, App Domains, contact info, webhook URLs, and WhatsApp
  configuration requirements were all verified against the actual code (none hardcode a domain —
  everything is `CRM_BASE_URL`-driven) and split into what's done vs. what only the client/Meta
  dashboard can supply (real domain, real contact address, real WhatsApp Business assets, the live
  webhook handshake, legal sign-off).
- **Security verified**: `getIntegrationSettings.php`/`getMessages.php` confirmed to never return
  `accessToken`/`appSecret`/`verifyToken` (only `hasSecret: bool` and the non-secret `config`,
  including `templates`); a live curl-based test against the running local app confirmed a
  restricted Sales Executive gets 404 on a conversation they don't own or that's unassigned (cannot
  send a template to it), an admin can send successfully, CSRF-less and session-less requests are
  rejected (403/401), an unknown template name and a wrong variable count are both rejected (422)
  before any Meta call, and the old `send-message.php` template path is now hard-rejected (422).
- **Tests**: no committed regression-suite scripts exist in this repo to re-run (the `sectest*.sh`/
  `run-p*.mjs` scripts referenced by earlier phases were session-local and were never committed);
  verification here was done directly against the running local WAMP app (`http://localhost/elma`)
  using temporary PHP-session-backed admin/employee sessions and `curl`, covering every case listed
  above, with all test message rows cleaned up afterward. No browser/UI click-through was performed
  in this phase (no browser tool was used) — the composer/modal HTML and JS wiring were verified by
  code review only; a manual click-through of the template modal is recommended before relying on
  it for a client demo.
- **Remaining / needs Meta dashboard or client action**: everything under README's "9a" table
  marked "Needs"; a live Meta Business Manager template actually approved and exercised end-to-end;
  final legal review of the updated privacy/deletion pages; the real production domain entered into
  the Meta App dashboard's App Domains field.

**Bug Fix: WhatsApp Template Sending Debug + Public Legal Routes (2026-09-29):**

- **Root Cause (WhatsApp template send failure):** not a payload/template bug. `whatsappGraphRequest()`
  (`includes/whatsappAccess.php`) called `curl_exec()` and, on outright transport failure (SSL/DNS/
  timeout — `curl_exec()` returns `false`, no HTTP response at all), discarded `curl_error()`
  entirely. Every failure — a real Meta API error *and* a request that never reached Meta — collapsed
  into the same generic `"Provider send failed"` in `whatsappMessages.errorMessage` and
  `integrationLogs`, making the actual cause unrecoverable from the CRM's own logs. Reproduced on
  this local WAMP box: PHP's bundled cURL had no CA bundle configured (`curl.cainfo`/`openssl.cafile`
  both unset in `php.ini`/`phpForApache.ini`), so `CURLOPT_SSL_VERIFYPEER => true` failed TLS
  verification on every single outbound HTTPS call (`curl_errno 60`, "SSL certificate problem:
  unable to get local issuer certificate") — this affected WhatsApp send/media/status calls AND the
  Meta Lead Ads Graph API fetch identically, not just templates. The template-specific symptoms
  (window detection, template button, UI) were all already correct, exactly as reported; only the
  actual Graph API round trip was silently failing.
- **Fix:** `whatsappGraphRequest()` now captures `curl_error()` when `curl_exec()` returns `false`
  and returns it as `transportError`. `finalizeWhatsappSend()` and `uploadWhatsappMedia()` now build
  the stored/logged error from, in order: the real Meta `error.message` → `"Could not reach WhatsApp:
  " . $transportError` → the old generic fallback (kept only for the case where neither is available).
  `integrationLogs.payloadSummary` on a send failure now also includes `httpCode`, `metaErrorCode`
  (Meta's numeric `error.code`), and a masked recipient number (`maskWhatsappNumber()` — last 4
  digits only, new helper) — exactly the safe fields Section 33's original design intended to
  capture but never did. `curl_error()` text is transport-level only (e.g. "SSL certificate problem",
  "Could not resolve host", "Operation timed out") and never contains the Authorization header or
  its token, so this is safe to store/display to admins; the token itself is still never returned to
  the browser (unchanged: the browser only ever sees "Message could not be sent...").
- **Local WAMP environment fix (outside the git repo, not deployed):** added
  `curl.cainfo`/`openssl.cafile` pointing at this project's own
  `vendor/composer/ca-bundle/res/cacert.pem` (a legitimate, already-present Mozilla CA bundle) to
  both `C:\wamp64\bin\php\php8.3.28\php.ini` (CLI) and `...\phpForApache.ini` (the file
  `C:\wamp64\bin\apache\apache2.4.65\bin\php.ini` symlinks to — confirmed via `php_ini_loaded_file()`
  through an actual HTTP request). This is a Windows/WAMP-only gotcha (PHP on Windows ships without a
  default CA bundle); most Linux production hosts (Hostinger included) already have a working system
  CA store and should never hit `curl_errno 60`. **If the client's real production box ever shows
  the same `"Could not reach WhatsApp: SSL certificate problem..."` in the Integration Log, the fix
  is identical: point that server's `curl.cainfo`/`openssl.cafile` at a valid CA bundle.** This
  environment change could not be fully applied in this session: the CLI `php.ini` picked it up
  immediately (re-read fresh on every invocation), but the Apache/mod_php worker process needs an
  actual restart to reload `phpForApache.ini`, and restarting the `wampapache64` Windows service
  requires elevated privileges this session does not have (`Restart-Service`/`net stop` both failed
  with access-denied) — **restarting WAMP/Apache is a pending manual step for whoever has admin
  rights on this machine.**
- **Files changed:** `includes/whatsappAccess.php` (`whatsappGraphRequest()`, `finalizeWhatsappSend()`,
  `uploadWhatsappMedia()` signature change to `['mediaId','error']`, new `maskWhatsappNumber()`),
  `api/whatsapp/send-message.php` (updated to the new `uploadWhatsappMedia()` return shape).
- **Verification:** re-tested the exact CRM → `whatsapp.js` → `send-template.php` →
  `whatsappAccess.php` → Graph API flow end-to-end via `curl` against the running app (admin
  session). Before the local CA fix: HTTP request through `send-template.php` recorded
  `"Could not reach WhatsApp: SSL certificate problem: unable to get local issuer certificate"` in
  both `whatsappMessages.errorMessage` and `integrationLogs` (proving the new error-surfacing code
  itself works, and is exactly what would have shown this root cause immediately instead of the old
  generic message). Via direct CLI PHP (which picked up the `php.ini` CA fix immediately, unlike the
  still-unrestarted Apache worker): the identical request reached `graph.facebook.com` cleanly
  (`curl_errno 0`) and got back a real, parseable Meta error — `HTTP 401 {"error":{"message":"Invalid
  OAuth access token - Cannot parse access token","type":"OAuthException","code":190}}` — using this
  environment's test/placeholder access token, confirming the request URL, headers and JSON payload
  construction are correct per Meta's Cloud API docs and that a *real* access token is the only
  remaining requirement for a real send to succeed. Template validation (unknown name → 422, wrong
  variable count → 422), permission/ownership scoping, and text-message sending were all re-confirmed
  unaffected by this change. All test rows/sessions created during verification were deleted
  afterward.
- **Public Legal Routes — Root Cause:** NOT a `routes.php`/router bug. `routes.php`,
  `getRouteByPath()`, and the `.htaccess` rules were all re-read and traced end-to-end and are
  correct: `.htaccess` line `RewriteRule ^(pages|employee|app)/ - [F,L]` only blocks *direct* HTTP
  requests to `/pages/...`; it does not affect `routes.php`'s internal `require_once` (a filesystem
  include, not a second HTTP request), which is how `/privacy-policy` correctly reaches
  `pages/privacy-policy.php` today (re-verified live: 200 OK, no session, no redirect, on this
  environment). The actual bug is in migration history:
  `2026-09-26c-crm-deactivate-modlus-routes.sql` deactivated `/privacy-policy`, `/terms-of-service`,
  and `/data-deletion` (`isActive = 0`) along with the rest of that phase's Modlus-route sweep, on
  the documented understanding they'd be re-enabled once rewritten for this CRM. They were rewritten
  in Phase 6, and `2026-09-30-crm-phase6-integrations.sql` does reactivate them (`UPDATE routesMaster
  SET isActive = 1 ... WHERE routePath IN (...)`) — but that reactivation is bundled inside a
  migration named/scoped for "integrations". **Any environment that applied migrations only through
  Phase 3/5 and skipped or has not yet reached the Phase 6 integrations migration (a very reasonable
  thing to defer if Meta/Google/WhatsApp aren't being configured yet) is left with all three legal
  routes still `isActive = 0`** — `getRouteByPath()` requires `isActive = 1`, so `routes.php` returns
  a plain 404 for all three URLs. This fully explains "routes exist as files but are not publicly
  accessible" without any code defect.
- **Fix / Routes Added:** new migration `database/migrations/2026-10-02-crm-fix-legal-page-routes.sql`
  — does not assume any prior migration's state. It unconditionally repairs the 3 rows
  (`isActive = 1, isPublic = 1, layoutType = 'public'`, `pageFile` recomputed from `routePath`) if
  they exist in any wrong state, and self-heals (`INSERT ... WHERE NOT EXISTS`) if a row is missing
  entirely. Applied and re-applied twice against the local DB to confirm idempotency (second run is
  a no-op).
- **Authentication Behavior (verified live, no session/cookies):** `/privacy-policy`,
  `/terms-of-service`, `/data-deletion` → HTTP 200, correct rendered content, no login redirect.
  Direct access to `/pages/privacy-policy.php` (and the other two) → HTTP 403 (blocked by
  `.htaccess`, exactly as required — the router is the only path in). `/includes/db.php`,
  `/storage/basic-config.json`, `/database/migrations` → HTTP 403. No PHP warnings in the rendered
  output for any of the three pages.
- **Meta Review Readiness:** with this migration applied, all three URLs required for Meta App
  Review (Privacy Policy, Terms of Service, Data Deletion Instructions) are live and public with no
  further code change — closing the one concrete gap in README's "9a" Meta App Review readiness
  table (everything else there was already correct/`CRM_BASE_URL`-driven).
- **`includes/db.php` git-tracking (raised alongside these two bugs):** confirmed tracked
  (`git ls-files includes/db.php` returned it) despite being added to `.gitignore` — adding a path to
  `.gitignore` never untracks an already-tracked file, so it was still being committed on every
  change. Checked its full git history (2 commits): content has always been the current
  env-var-only implementation (`CRM_DB_*` via `crmEnv()`, local fallback to the passwordless
  `elma_crm` MySQL user already documented in this file's Section 2) — **no real credential was ever
  committed**, so no history rewrite (BFG/filter-repo) is warranted, only `git rm --cached
  includes/db.php` (run this session, staged, not committed) to make future commits actually respect
  the `.gitignore` entry going forward. `.env` was confirmed never tracked.
- **Tests:** see "Verification" above for WhatsApp; see "Authentication Behavior" above for the
  legal routes. No committed regression-suite scripts exist in this repo (same note as the prior
  Phase 6.5 template-support entry).
- **Remaining manual steps:** (1) restart WAMP/Apache (elevated privileges required, not available
  to this session) to load the CA bundle fix for the Apache-served app — until then, this local
  environment's own outbound Meta/WhatsApp/Google calls will keep failing with the SSL error the new
  code now correctly reports; (2) run `2026-10-02-crm-fix-legal-page-routes.sql` against the client's
  actual production database — this is the one that fixes their real reported bug; (3) once a real
  WhatsApp access token is configured, re-verify an actual template delivery end-to-end (this session
  proved the request construction is correct and reaches Meta, but never had a real token to
  complete a real send); (4) commit `git rm --cached includes/db.php` when the user is ready (left
  staged, not committed, per instruction not to commit).

**Bug Fix: WhatsApp Inbound Webhook Debug (2026-09-29):**

- **Symptom:** GET verification succeeds, the "messages" field subscription shows active in Meta's
  dashboard, outbound CRM → WhatsApp sends work, but inbound customer replies never appear in
  `/whatsapp`'s `getMessages.php`, and — critically — `integrationLogs` has **zero** rows for the
  webhook at all, not even a rejection/error row.
- **Root cause: could not be pinned down from this environment alone** — this needs to be diagnosed
  against the client's live traffic, not this local box (there is no way to make Meta send a real
  webhook to `localhost`). What this audit *does* establish with certainty, by reading every file in
  the flow (`api/integrations/whatsapp-webhook.php`, `includes/whatsappAccess.php`,
  `includes/integrationAccess.php`, the `whatsappMessages`/`integrationLogs` schemas,
  `api/whatsapp/getMessages.php`, `api-gateway.php`) and by replaying a real signed webhook POST
  against this local app:
  - `getMessages.php` has no `direction` filter — it would show inbound messages exactly like
    outbound ones if any existed in `whatsappMessages`. **Not a read-side bug.**
  - `api-gateway.php`'s public-endpoint exemption list already includes
    `/api/integrations/whatsapp-webhook.php` for both GET and POST (same boolean check, not
    method-specific) — since GET verification is confirmed working, POST requests reach the same
    file too. **Not a gateway/routing bug.**
  - A full signed POST with a `messages[]` payload, replayed locally end-to-end, correctly created
    the conversation, inserted the `whatsappMessages` row (`status='received'`), bumped
    `unreadCount`, and logged `message_received`/`created`. **The parser and DB insert logic are
    correct for a `messages[]` payload in the shape Meta currently documents** (dedup by
    `metaMessageId`, contact-name lookup by `wa_id`, image/document media download — nothing here
    is broken by itself).
  - The **real, confirmed gap** this audit found: the file had **no logging path that fires
    unconditionally on receipt** — every existing `logIntegrationEvent()` call was gated behind a
    specific rejection condition (bad signature, missing `from`/`id`, duplicate id). If a POST never
    reaches this file at all (wrong callback URL registered in Meta, a proxy/CDN rule, a different
    file entirely), or if PHP fatals partway through processing (`db.php` runs with
    `mysqli_report(MYSQLI_REPORT_OFF)`, so a failed `mysqli_stmt_execute()` on the
    `whatsappMessages` INSERT was previously **completely silent** — no exception, no warning, no
    log row, the message just vanishes), the result is indistinguishable from "nothing happened" —
    exactly the reported symptom of zero log rows.
  - **Leading hypothesis** (not proven, needs the client's Meta App dashboard or live production
    logs to confirm): Meta's WhatsApp product has its own separate webhook **Callback URL +
    Verify Token** configuration (under WhatsApp → Configuration), independent of the App-level
    "Webhooks" product page used for Lead Ads. It's a common setup mistake to leave the WhatsApp
    "messages" field subscribed pointing at the *Lead Ads* callback URL (`meta-webhook.php`) left
    over from initial setup, rather than `whatsapp-webhook.php`. If that's what happened here,
    inbound WhatsApp messages land on `meta-webhook.php`, which only understands `leadgen_id` —
    they'd be silently logged as `provider='meta', eventType='lead_event', status='rejected',
    message='Event missing leadgen_id'` (already-existing code, not new), never touching
    `provider='whatsapp'` at all. **Action for the client/whoever has Meta dashboard access:**
    check the exact Callback URL configured for the WhatsApp Business Account's "messages" field
    subscription and confirm it is `https://<production-domain>/api/integrations/whatsapp-webhook.php`
    (not the Lead Ads URL) — also check `integrationLogs` filtered to `provider = 'meta'` around the
    time an inbound message was expected, for a `lead_event`/`rejected` row.
- **Fix (minimal debug logging added, no architecture change):** `api/integrations/whatsapp-webhook.php`
  now:
  1. Logs an unconditional `webhook_received`/`received` row on every POST, **before** the signature
     check — `bytes`, whether the signature header was present, whether `entry` exists, and its
     count (never the payload content or the signature value). This alone answers "did the POST
     arrive at all?" definitively the next time this is checked.
  2. Logs a `webhook_event`/`rejected` row ("Unrecognized change value shape") whenever a `change.value`
     has neither `messages[]` nor `statuses[]` — including the change's `field` name and the value's
     top-level keys (never their content). This is exactly what would catch the "wrong field landed
     here" hypothesis above, from either direction.
  3. Checks `mysqli_stmt_execute()` on the `whatsappMessages` INSERT and logs
     `message_received`/`error` with the DB error text if it fails, instead of silently continuing —
     closing the "DB insert failing silently" gap.
  4. Wraps the whole entry/change processing loop in `try/catch (Throwable)`, logging
     `webhook_event`/`error` with the exception message on any uncaught error — closing the "PHP
     fatal produces no trace" gap.
  All four are additive logging only; the message/status processing logic itself (parsing, dedup,
  conversation matching, status-rank guard) is unchanged.
- **Files changed:** `api/integrations/whatsapp-webhook.php` only.
- **Verification:** replayed real HMAC-signed POST requests against this local app using this
  environment's actual configured App Secret (`hash_hmac('sha256', $payload, $appSecret)`, matching
  exactly what Meta computes) — (a) a valid `messages[]` text-message payload: created the
  conversation, inserted the message, logged `message_received`/`created`, **and** the new
  `webhook_received`/`received` row — full inbound flow confirmed working end-to-end for the
  documented payload shape; (b) a `leadgen`-shaped payload (simulating the misrouted-webhook
  hypothesis) sent to this same URL: correctly produced the new `webhook_event`/`rejected`
  ("Unrecognized change value shape", `field: "leadgen"`) row instead of being silently dropped.
  All test rows/conversations/temp files created during this verification were deleted afterward.
  **Not verified against Meta's actual live traffic** — this session has no way to receive a real
  webhook call (no public HTTPS endpoint reachable from Meta), so whichever of the hypotheses above
  is the client's actual root cause can only be confirmed once these new log lines capture a real
  attempt.
- **Remaining action (client/Meta dashboard, not code):** check the WhatsApp Business Account's
  "messages" field webhook Callback URL in Meta's dashboard against the exact production URL; after
  that, or after the next real inbound message attempt either way, re-check `integrationLogs`
  (all providers, not just `whatsapp`) for the new `webhook_received` row — its presence/absence
  will immediately tell whether the request is reaching this server at all.

**Full WhatsApp Integration Audit (2026-09-29), following up the webhook debug above:** the client
provided a production data export (`whatsappMessages` table) taken from the live `hyther.in`
deployment, which materially changed the diagnosis versus the prior entry above.

- **What the production export actually shows:** 9 outbound template attempts and exactly 1 inbound
  row. The single inbound row (`metaMessageId = 'ABGGFlA5Fpa'`, text "this is a text message",
  `sentAt = '2017-09-09 02:06:28'`) is **Meta's own dashboard "Test" button payload** — that exact
  short message id and stale/sample timestamp match Meta's canned test event for the "messages"
  field, not a real customer. Its presence proves the full pipeline (Meta reaching the registered
  Callback URL → signature verification → JSON parsing → conversation creation → `whatsappMessages`
  INSERT) **is working end-to-end in production** — which **rules out** the previous entry's leading
  hypothesis (WhatsApp's field pointed at the wrong/Lead-Ads callback URL). No real customer inbound
  message has been observed in this export either succeeding or failing.
- **The 9 outbound rows, read in order, are historical self-resolved configuration noise from the
  client's own live setup session, not a current code bug:** `"Unknown path components:
  /1301707419697777/messages"` (attempts 1, 4, 5) and `"Error validating access token: ... Session
  has expired"` (attempts 2, 3) both stopped occurring once the client replaced the access token;
  attempts 6-8 then failed with Meta's own `(#132001) Template name does not exist in the
  translation` because the submitted template name was literally the placeholder string `"language"`
  (attempts 1-6) and then `"order_confirmation"` (7-8) — neither ever an approved template in this
  client's Meta Business Manager (the latter was this project's own local/demo config value,
  evidently copied into the production Integrations settings). Attempts 9 and 11, using Meta's
  built-in sample template `hello_world`, **succeeded** (`status='sent'`, a real `wamid.` returned).
  This confirms the send pipeline, URL construction, API version fallback, and token loading are all
  correct once a real access token and a real approved template name are used — **no code fix
  applies to any of this; it was the client finding working values through trial and error**, exactly
  the kind of thing the Phase 6.5 template-validation fix (checking submitted names against the
  admin's own configured list) is meant to catch faster next time.
- **Confirmed bug found and fixed:** `dist/assets/js/whatsapp.js`'s `refreshActiveConversation()`
  only re-rendered the open conversation's messages when `messages.length` changed. A delivery-status
  update (Meta's `statuses[]` webhook advancing pending → sent → delivered → read, or → failed) changes
  an existing row's `status` column without changing the row count, so the open chat window could sit
  showing a stale checkmark (or miss a send failure) until the conversation was closed and reopened.
  This is real, but it does **not** explain the reported "inbound not appearing" symptom (a genuinely
  new inbound row always changes the count, which was already detected correctly) — it only affects
  outbound status-tick freshness. **Fix:** track a per-poll signature of `id:status` pairs across all
  messages instead of just the count; re-render whenever the signature changes, and still call
  `mark-read` only when the count specifically increased (preserving existing mark-read semantics).
- **Possible issues / hardening notes (not fixed, not confirmed as bugs, flagged for awareness):**
  - `whatsappMessages.sentAt`/`deliveredAt`/`readAt` are `DATETIME` (literal, no timezone conversion)
    while `createdAt` is `TIMESTAMP` (converted per-session from UTC). Both are written correctly
    together by the app's own PHP process (`db.php`'s `SET time_zone = '+05:30'` applies to both in
    the same session), so this is not a live bug — but a raw `mysqldump`/phpMyAdmin export taken with
    a *different* session default will display the two columns up to ~5.5 hours apart (exactly what
    the client's own export showed), which can look like a bug but isn't one. Worth remembering before
    trusting timestamps from an ad-hoc DB export.
  - The prior entry's new unconditional `webhook_received` log fires on every POST, before signature
    verification -- intentional (that's what makes "did a request even arrive" answerable), but it
    means `integrationLogs` will accumulate a row for any scanner/bot hitting the public webhook URL,
    not just Meta. Not a security issue (no DB write happens beyond the log row, and invalid-signature
    requests are still rejected immediately after), but worth knowing if `integrationLogs` volume
    ever needs pruning.
  - No rate limiting on the public webhook/send endpoints beyond signature verification and normal
    session/CSRF/permission checks -- consistent with the rest of this codebase's stated scope, not
    a regression, just noted since section 8 of this audit asked about it explicitly.
- **No issues found:** `getMessages.php` (no `direction` filter — inbound and outbound both render
  identically once present), `getConversations.php` (ownership scoping, search, unread badge),
  `mark-read.php`, dedup (`UNIQUE KEY` on `metaMessageId`, NULL-safe for still-pending outbound rows),
  FK constraints (`ON DELETE SET NULL`/`CASCADE` as appropriate), indexes on `conversationId`,
  `lastMessageAt`, `phoneNumber`, `assignedToId`, the out-of-order status-downgrade guard
  (`whatsappStatusRank()`), and every permission/ownership/CSRF check re-audited from Phase 6.5 —
  all unchanged and correct.
- **Files changed:** `dist/assets/js/whatsapp.js` only.
- **Test performed:** local replay only (same constraint as the prior webhook-debug entry — this
  session cannot receive a real Meta webhook). Confirmed the new signature-based comparison correctly
  triggers a re-render on a simulated status-only change (same message count, different `status`
  value) where the old count-based check would not have. Did not have live access to re-verify
  against `hyther.in`.
- **Remaining Meta dashboard steps for the client:** none newly identified by this pass — the
  previous entry's ask (double-check the WhatsApp "messages" field Callback URL) is now lower
  priority given the Test-button payload proves that URL is already correct; the actionable next
  step is simply to have a **real second phone** message the business number and then check
  `integrationLogs` for a `webhook_received` row at that moment — if it's missing, the request isn't
  reaching Meta's webhook system at all (an App Review/permissions/business-verification issue on
  Meta's side, not this codebase); if it's present but no `message_received`/`created` row follows,
  that would be the first actual evidence of a real code-level parsing/insert failure to investigate
  next.

**Phase 7 (partial — see "requires production access" below, 2026-09-28):** Production hardening,
deployment prep, and handover documentation. **This phase was worked entirely from the local WAMP
dev environment — no Hostinger/production server or real provider (Meta/Google/WhatsApp) accounts
were made available, so everything requiring live server access or live credentials is documented
as a requirement/checklist item, not claimed as done.** Full deployment/environment/backup/rollback
detail now lives in [README.md](README.md)'s "Production deployment" section (not duplicated here).
- **Pre-deployment audit**: `git status`/`git ls-files` checked — only `.env.example` and migration
  `.sql` files are tracked (both intended); `.env`, `storage/*.json`,
  `storage/{lead-documents,project-documents,whatsapp-media}/`, `logs/` all confirmed gitignored;
  no DB dump or `.env` ever appeared in git history. Nothing sensitive is at risk of being committed.
- **Session cookie hardening** (`includes/config.php`): added `session_set_cookie_params()` —
  `HttpOnly` + `SameSite=Lax` always, `Secure` whenever `isCrmLocalEnvironment()` is false (i.e.
  automatically on for the real production domain, off locally so HTTP dev logins keep working).
  This was a real, minimal gap (Phase 1–6.5 never set these flags, relying on PHP defaults) — the
  only code change made in Phase 7. Verified: local login still works (cookie now shows `HttpOnly`,
  `Secure` correctly `false` on `localhost`), and the full regression suite still passes.
- **`.htaccess` hardening**: re-verified with real HTTP requests, including the Phase 6.5 additions
  — `storage/whatsapp-media/` blocked (403), `includes/whatsappAccess.php`/`whatsapp-page.php`
  blocked, `pages/whatsapp.php`/`employee/emp-whatsapp.php` blocked direct, migrations blocked,
  the WhatsApp webhook reachable without a session (its own 403 is the app's verify-token check,
  not a gateway block — confirmed by reading the response body), `api/whatsapp/*` correctly 401s
  without a session. No `.htaccess` changes were needed — Phase 1's rules already cover every new
  path pattern from later phases.
- **`uploads/.htaccess` / storage `.htaccess`**: reviewed — already double-guards PHP execution for
  both `mod_php` and PHP-FPM-style handlers (`Require all denied` on `.ph*`/`.cgi`/`.pl`/`.py`/`.sh`
  regardless of `mod_php` presence), already correct, no change needed.
- **Full PHP lint**: all 166 `.php` files in the project — 0 syntax errors.
- **Full security regression suite**: re-run after the cookie change — 275/275 passed (Phases 2, 3,
  5, 6, 6.5 all still green). Re-run twice more in this phase for other reasons; each time the
  WhatsApp demo data was wiped by the suite's own table reset and was **re-seeded immediately after**
  (see the standing note below — this is a recurring interaction to be aware of, not a bug).
- **Sidebar review**: queried `routesMaster` directly — admin/employee active+visible routes exactly
  match the expected module structure (CRM/Lead Management/Projects/Employees/Reports/
  Integrations/Setup for admin; Employee Panel/Lead Management/Projects for employee); zero active
  routes with an unrecognized `moduleName`; zero still-active Modlus-origin routes. No changes needed.
- **JS debug-log sweep**: zero `console.log`/`debugger` statements in any of the CRM's own
  business-logic JS files (`lead.js`, `lead-dashboard.js`, `reports.js`, `integrations.js`,
  `whatsapp.js`, `projects.js`, etc). The `console.log` hits found elsewhere are all inside unused
  Mamix theme demo-page scripts (`blog-details.js`, `ecommerce-*.js`, `tagify.js`, etc.) that no CRM
  route ever loads — left alone, deleting hundreds of unused vendor files is out of scope here.
- **PHP limits / file storage permissions / HTTPS / SMTP / provider live verification / backups /
  restore**: these all require the actual production host and cannot be exercised from this local
  environment. Target values, exact steps, and a full deployment checklist are documented in
  [README.md](README.md) rather than invented as "done" here.
- **Demo data conflict (flagged, not resolved unilaterally)**: this phase's own instructions say to
  clear WhatsApp demo data before go-live, but the user's immediately preceding request in this same
  session was to *create* that demo data for a client walkthrough. The demo data (10 conversations,
  3 linked demo leads) was **left in place** — clearing it was not done without explicit confirmation,
  since doing so would have silently undone work just requested. It is disposable
  (`TRUNCATE whatsappMessages; TRUNCATE whatsappConversations;` plus removing the 3 demo leads) and
  clearly flagged as needing an explicit decision before real production go-live.
- **README.md**: substantially rewritten — module status table updated (Dashboard/Reports/
  Integrations/WhatsApp all "Available" now, not "Planned"), full production deployment section
  added (server prerequisites, PHP limits, DB setup, storage permissions, admin creation, SMTP,
  legal pages, per-provider go-live steps, backups, restore, rollback, deployment checklist).
- **Not done in this phase** (all genuinely require the client's/hosting provider's involvement, not
  more engineering time): creating the actual production database/environment on Hostinger; issuing
  the production admin account; live Meta/Google/WhatsApp credential setup and end-to-end
  verification with real accounts; real SMTP delivery test; final legal sign-off on the 3 legal
  pages; an actual backup/restore exercise (there is no production system yet to back up).

**Meta Lead Ads + Google Lead Forms audit/fix (2026-10-03):** both were already fully built in
Phase 6 (webhook/endpoint, settings/secrets, mapping, logs, `createLeadFromSource()` dedup, gateway
allowlist, Integrations card UI) — nothing rebuilt, no UI/DB/migration change. Real gaps patched:
- **Phone bug (Meta + Google):** E.164 numbers (`+919876543210`) were stored as 12 digits with
  `+91`, breaking dedup and WhatsApp/call links. New `splitExternalPhone()` in
  `includes/integrationAccess.php` (91/0-prefix → 10 digits + `+91`; other international numbers
  kept whole with empty country code). Website endpoint untouched.
- **Meta Graph fetch failures were opaque:** `metaGraphApiGet()` now returns Meta's real
  `error.message` / curl transport error via a by-ref `$error`, written to the Integration Log
  ("Graph API fetch failed: ..."); Graph version v19.0 → v21.0. `meta-webhook.php` also logs an
  `webhook_received` row for every correctly signed POST (bad signatures still log
  "Invalid or missing signature"), so "did Meta reach us" is answerable.
- **Google test button:** `is_test=true` payloads are acknowledged 200 and logged but no lead is created.
- Files: `includes/integrationAccess.php`, `api/integrations/meta-webhook.php`, `api/integrations/google-lead.php`, this file.
- Tested locally (curl): Google valid/duplicate/bad key/test lead; Meta handshake ok/bad token 403,
  signed POST, bad signature 401, no page token logged; Graph error text surfaced with a fake token.
  Not live-verified (no real Meta token / Google Ads account). WhatsApp files untouched.
- **Validation pass (2026-10-03, no code bugs found, no code changed):**
  - Browser (Playwright/Chromium, admin session, 1440px + 390px): Configure modals for Meta/Google
    open with correct endpoint URL + fields, secrets never echoed (blank by design — verifyToken
    included, saving blank keeps stored value; handshake still works after UI saves), disable/enable
    save updates Status badge, Connected badge, Last Successful Lead/Last Error; Meta form mapping
    add/list/edit works; log table loads; zero console/page/network errors; no horizontal overflow.
  - Google (real endpoint, curl): public, bad key 401, `is_test` creates nothing, valid lead created
    (source `google_ads`, actor `system`), duplicate ignored, mapped form → project + assignee applied,
    missing phone 422, Last Successful Lead/Last Error update, logs written.
  - Meta (curl): handshake ok / bad token 403, bad signature 401, signed POST extracts `leadgen_id`,
    Graph call uses configured Page Access Token, Graph/transport error reaches Integration Log +
    Last Error. **Not exercised** (needs a real token; no fake Graph success was used): successful
    Graph fetch → lead creation, Meta duplicate, Meta mapping — that code is the same block as Google's.
  - Regression: PHP lint on all `api/` + `includes/` files clean; Leads/Integrations/WhatsApp/
    Dashboard/Reports/Projects pages 200; tested APIs 401 without session; WhatsApp inbound webhook
    creates conversation+message; WhatsApp send reaches Graph (fails only on the known unrestarted-
    Apache CA bundle). No committed regression suite exists to re-run. All test rows deleted.
  - Note: `rejected` outcomes (e.g. missing phone) are logged but do not set Last Error; only `error` does.
- **Bug fix — Meta verify token never saved from the UI (2026-10-03):** the Configure modal is ONE
  form containing every provider's field group (hidden ones just `d-none`), and `$(form).serialize()`
  submits hidden fields too. Meta and WhatsApp both use `secrets[verifyToken]` / `secrets[appSecret]`;
  PHP keeps the last duplicate, so the (blank) WhatsApp inputs overwrote Meta's value, and a blank
  value means "keep existing" — the new token was silently discarded and the handshake kept failing.
  Fix (`dist/assets/js/integrations.js`, 3 lines at modal open): disable inputs of hidden provider
  groups so they aren't serialized. Backend untouched. Verified in browser: saving a new Meta Verify
  Token → `hub.mode` handshake returns `12345`, wrong/old token → "Verification failed.", blank re-save
  keeps the token, WhatsApp blank save still keeps its secrets. Applies equally to Meta App Secret.

**Historical Phase 1 gaps (fixed in Phase 2/3):**
- `api/leads/*` and `api/employee/*` (except `addEmployee`), `api/company/*`: no CSRF; lead APIs check session only, not route/action permission; `permissionActions` has no API mappings yet.
- `deleteLead` / `updateLead` / `updateLeadStatus`: no ownership check.
- `uploadLeadDocument`: extension-only check → add `finfo`.
- Lead ownership is `leads.createdByCandidateId` (creator = owner); no assignee column yet.
- Login forms have no CSRF token.

**Next phases:** finish Phase 7 once production hosting + real provider credentials are available
(everything listed above under "Not done in this phase").

---

## 8. How Claude must work

- Read this file → identify relevant files → inspect engine/API/page/migration/permission/sidebar → edit.
- Before deleting anything: search references (PHP includes, API paths in JS, route slugs, table names), deactivate routes first.
- Run `php -l` (PHP 8.3 at `C:/wamp64/bin/php/php8.3.28/php.exe`) on every touched file; test auth, permission, CSRF, validation and ownership paths.
- Never claim a test or browser verification that was not performed.
- Do not commit, push or deploy unless explicitly asked.
- After work report: files changed, DB changes, security changes, tests + results, browser verification status, deferred items, concerns.
