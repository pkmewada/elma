# Modlus — Project Documentation

Last Updated: 2026-09-01

## 1. Document Purpose

This README is the main working document for both the client and the development team.

It explains:

- what the project is
- what functionality is currently available
- what has been completed so far
- how the project is structured
- how to run the project locally
- what still needs refinement before production

This file should be updated whenever the project grows, changes, or moves to a new phase.

**A naming note, for transparency**: the codebase, database, and internal docs consistently refer to this project as **Modlus** (class names, table names, `docs/` filenames, code comments all use this name), the browser tab title currently shows **MQlus**, and `package.json`'s `name` field still says `mamix` (a leftover from the original template this project started from). These three names all refer to the same application. This should be formally decided and cleaned up at some point — see §14.

## 2. Project Overview

Modlus is a full **HR, payroll, CRM, and social-media-automation platform** built with PHP, MySQL, Bootstrap 5, and a customized admin dashboard theme.

What started as a small CRM (authentication + lead management) has grown into a platform covering:

- lead management and client onboarding (CRM core)
- candidate recruitment and employee onboarding/HR verification
- attendance (punch in/out, breaks, half-day/absence tracking)
- leave management (apply/approve/cancel, balances, monthly accrual, carry-forward)
- payroll (settings-driven salary calculation, statutory deductions, salary slip generation and approval)
- overtime, expenses, employee-point programs, commission/bonus tracking
- asset management (assignment/return)
- role- and action-level permission management, with a dedicated API permission gateway
- company/attendance/payroll/leave/route setup screens
- client-facing social media content planning (a calendar/deliverables grid)
- social media platform integrations: **Instagram/Facebook** (production-verified publishing), and **LinkedIn**, **Pinterest**, **Google Business Profile** foundations (implemented, pending external platform approvals — see §10 and `docs/`)

## 3. Project Goals

### Client-Facing Goal

Provide a single admin panel where an operator can:

- manage leads through to client conversion and onboarding
- recruit and onboard candidates into employees, with HR verification
- run day-to-day HR operations: attendance, leave, overtime, expenses, assets
- generate and approve monthly payroll/salary slips
- manage per-client social media content planning and publishing across multiple platforms
- control who can see and do what, per route and per action

And provide an employee-facing panel where staff can:

- punch in/out, apply for leave, submit expenses/overtime, view their own commission/points/salary history

### Developer-Facing Goal

Maintain an extendable Core PHP codebase with:

- reusable headers, footers, and sidebar layout files (`includes/`)
- central routing (`routes.php` + `routesMaster` DB table) and a dedicated API-permission gateway (`api-gateway.php`)
- a module-organized `/api/` directory (see §9) rather than one flat folder
- domain "engine" classes for the more complex modules (`PayrollEngine.php`, `PayrollApprovalEngine.php`, `AttendanceEngine.php`, `leaveEngine.php`, `deliverableEngine.php`, `calendarEngine.php`, `SocialPostEngine.php`, and one dedicated automation class per social platform)
- consistent client isolation via `clientId` (never `companyId` — a deliberate, explicitly-enforced rule for every module built since the CRM's client-management phase; see §13)
- room for continued feature expansion without re-architecting existing working modules

## 4. Current Development Status

| Module | Status | Notes |
| --- | --- | --- |
| Clean routing | Completed | `routes.php` + `routesMaster` (DB-driven) provide clean URLs. Base path is resolved dynamically, not hardcoded. |
| Authentication (admin) | Completed, dev-mode caveat | Signup, login, logout, OTP verification, forgot/reset password. |
| Authentication (candidate) | Completed, dev-mode caveat | Separate candidate login/logout/forgot-password/reset flow, alongside admin auth. |
| Session protection | Completed | `includes/auth.php` (admin), `includes/emp-auth.php` (employee). Route- and action-level permission checks via `permission-helper.php` + `routesMaster`/`permissionActions`. |
| API permission gateway | Completed | `api-gateway.php` (routed via `.htaccess`) matches every `/api/*.php` request against `permissionActions.apiEndpoint` for action-level authorization. |
| Lead management (CRM core) | Completed | Add/list/filter/export leads, status updates, remarks, follow-ups, lead-to-client conversion. |
| Client management | Completed | Client master records, client onboarding form (public-facing, token-based), client service credentials. |
| Recruitment & onboarding | Completed | Candidate records, import, HR verification pipeline, joining, agreements (with e-signature capture and PDF), onboarding forms. |
| Attendance | Completed | Punch in/out, breaks, half-day/absent detection, auto punch-out cron, attendance setup (shifts, break types). |
| Leave management | Completed | Apply/cancel/approve/reject, leave types & settings, balances, monthly accrual + carry-forward crons. See §5.4. |
| Payroll | Completed for core flow | Settings-driven salary calculation (`PayrollEngine.php`), statutory deductions (PF/ESIC/PT/TDS/GST — each individually toggleable), salary slip generation, admin approval workflow, PDF salary slips. See §5.5. |
| Overtime | Completed | Request, approve/reject, rules/settings. |
| Expenses | Completed | Submit, approve/reject, reimbursement into payroll. |
| Employee points program | Completed | Point categories, transactions, payroll-linked point deduction. |
| Commission / bonus | Completed | Categories, transactions, approval levels, payroll sync. |
| Asset management | Completed | Asset master/category, assignment, return. |
| Deduction management | Completed | Manual per-employee deductions feeding into payroll. |
| Permissions & setup | Completed | Route/action permission setup, role permissions, per-user overrides, company/attendance/payroll/leave setup screens. |
| Client social calendar | Completed for core flow | Deliverables grid, calendar plan/overview per client (`pages/calendar.php`, `client-deliverable.php`, `social-data-entry.php`, `social-overview.php`). |
| Instagram/Facebook automation | **Production-verified** | OAuth, image/carousel/reel publishing, analytics sync, webhooks, unified Instagram+Facebook "Publish Now" and scheduled publishing. Comments feature implemented but blocked on Meta App Review — see `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md`. |
| LinkedIn integration | Implemented in code, **paused** | OAuth, organization discovery/selection. Live validation paused — no LinkedIn Company Page currently available for the required Developer App. Publishing not built. |
| Pinterest integration | Implemented in code, **live validation pending** | OAuth, board discovery/selection, token refresh. Foundation works within Pinterest's default Trial access; Standard access needed only before a future publishing phase. |
| Google Business Profile integration | Implemented in code, **live validation pending** | OAuth, Business Profile account + location discovery/selection, token refresh. Pending Google's own external approval chain (a 60+ day verified GBP, Business Profile API access approval). |
| Dashboard UI | Partially live | Mostly template content; one live widget (Instagram account/follower/reach summary) has been wired to real data. |
| OTP email sending | Completed, needs prod credentials | Centralized `mailer.php`/`sendLoggedMail()`, DB (`eventMailLog`) + file (`logs/mail.log`) delivery logging, retry cron. Placeholder Gmail credentials still need replacing for production. |
| Automated testing | Not started | `npm test` is only a placeholder. |
| Production hardening | Partially complete | CSRF protection now implemented across newer modules (payroll approval, permissions, all social integrations) — not yet audited for 100% coverage across older modules. DEV_MODE still enabled. See §14. |

## 5. What Has Been Done So Far

### 5.1 Authentication & Access Control

- Admin auth: signup, login, logout, OTP verification, forgot/reset password
- Candidate auth: separate login, logout, forgot/reset password, reset-OTP verification (dual login system — admin/employee and candidate are intentionally separate)
- Password hashing (`password_hash`/`password_verify`), session regeneration after login
- Route-level permission checks (`hasRoutePermission()`) and action-level (button/API) permission checks (`hasActionPermission()`), each checkable per-role and per-user-override
- `api-gateway.php` enforces the same action-level permission model directly at the API layer for any endpoint registered in `permissionActions`

### 5.2 CRM Core (Leads & Clients)

- Lead creation, listing (DataTable, CSV/PDF export, filters), status updates, remarks, follow-up scheduling
- Lead-to-client conversion flow
- Client master records; public, token-based client onboarding form with file upload
- Client service credentials tracking

### 5.3 Recruitment & Onboarding

- Candidate records, CSV import, remarks
- HR verification pipeline (verification data capture, HR review, final verification)
- Onboarding agreements with e-signature capture, PDF generation, and a review/approval step
- Joining status tracking, onboarding-queue admin screen
- Candidate self-service portal (separate login, dashboard, profile, waiting screen)

### 5.4 Attendance & Leave

- Attendance: punch in/out, break start/stop, shift/attendance setup, automatic punch-out cron for missed punch-outs
- Leave: apply (employee self-service and admin-on-behalf-of), cancel (employee, pending only), approve/reject (admin)
- Leave types with per-type rules (paid/unpaid, half-day allowed, max consecutive days, applicable gender, allow-negative-balance, monthly/yearly allocation)
- Leave balances (`getOrCreateBalance()`), deducted only on approval — not on pending/rejected/cancelled
- **Monthly accrual and carry-forward are implemented as cron jobs** (`cron/leave-accrual.php`, `cron/leave-carry-forward.php`) — confirm these are registered in the production cron schedule before relying on them.
- Centralized, logged email notifications for leave-applied/approved/rejected events (`mailer.php` → `eventMailLog` + `logs/mail.log`)

### 5.5 Payroll

- Fully settings-driven calculation (`payrollSettings` → `PayrollEngine::calculateSalarySlip()`): payable-days basis, monthly paid-leave entitlement with carry-forward, probation/notice-period leave rules, half-day/absence handling, overtime, statutory deductions (PF, ESIC, professional tax, income tax TDS, GST — each independently toggleable), training-hold/release, point-based deduction, manual/fixed employee deductions, commission/bonus sync, expense reimbursement
- **Leave-to-payroll integration is the authoritative source for paid-leave deduction** — payroll consumes `leaveApplications`/`leaveApplicationDays`/`leaveTypes` directly rather than maintaining a second leave-balance system. As of 2026-09-01 this correctly distinguishes paid leave within the monthly entitlement (no deduction) from leave beyond it (deducted), and the Deductions section of the salary slip now itemizes each leave category (paid-within-entitlement, excess-paid, unpaid, probation, notice-period, informed, uninformed) with its day count, instead of a single opaque "Leave Deduction" line.
- Salary slip generation (preview + stored), submission for approval, admin approval workflow (`PayrollApprovalEngine.php`), PDF generation (`dompdf`)

### 5.6 Overtime, Expenses, Points, Commission, Assets, Deductions

- Overtime: request, approve/reject, configurable rules/settings, payroll-linked
- Expenses: submit, approve/reject, payroll-linked reimbursement
- Employee points: categories, settings, transactions, payroll-linked deduction
- Commission/bonus: categories, settings, multi-level approval, transactions, payroll sync
- Assets: master/category records, assignment, return
- Manual per-employee deductions, independent of leave/attendance

### 5.7 Setup & Permissions

- Company setup, basic setup, attendance setup, payroll setup, leave setup
- Route/page setup (`routesMaster` management UI)
- Role permissions, per-user permission overrides, per-action (button-level) permission registry

### 5.8 Client Social Calendar

- Per-client content calendar/deliverables grid (`pages/calendar.php`, `client-deliverable.php`), platform/feature master data
- Social media data-entry and overview screens (`social-data-entry.php`, `social-overview.php`) — some panels still marked `TODO(api)` pending a dedicated content-storage endpoint; currently these operate on local/mock data for those specific panels (see the in-file `TODO(api)` comments for exactly which ones)

### 5.9 Social Media Platform Integrations

- **Instagram/Facebook**: OAuth (Facebook Login for Business), encrypted token storage, image/carousel/reel publishing, scheduling, analytics sync, webhook receiver + signature verification + event logging, unified "Publish Now" and scheduled multi-platform publishing (`SocialPostEngine.php`). Image publishing is production-verified with real post IDs; see `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md` for the full evidence trail. Comments are implemented but blocked on Meta App Review/Business Verification (external dependency).
- **LinkedIn**: OAuth, organization discovery/selection, encrypted token storage — implemented, paused pending a LinkedIn Company Page for the required Developer App.
- **Pinterest**: OAuth (with refresh-token handling per Pinterest's continuous-refresh model), board discovery/selection, encrypted token storage — implemented, live validation pending a real Pinterest Developer App.
- **Google Business Profile**: OAuth (offline access), Business Profile account discovery, location discovery/selection (Google's 3-level account→location hierarchy, preserved without collapsing identifiers), encrypted token storage — implemented, live validation pending Google's own external approval chain.
- All four platforms follow the same foundation pattern: `includes/Crypto.php` for token encryption, `includes/Csrf.php` + session-based OAuth `state` for CSRF protection, `clientId`-scoped ownership with a server-side `*AccountBelongsToClient()` guard per platform, and platform-specific HTTP transport (no shared vendor-specific code between platforms, since each vendor's auth/API shape genuinely differs).

### 5.10 API Directory Organization

As of 2026-09-01, `/api/` was reorganized from ~207 flat files into module subfolders (`api/attendance/`, `api/leave/`, `api/payroll/`, `api/leads/`, `api/instagram/`, `api/linkedin/`, `api/pinterest/`, `api/google-business-profile/`, etc. — 23 module folders total). `api-gateway.php`'s routing pattern (`^api/.+\.php$`) and every internal reference (JS `API_BASE` calls, PHP includes, OAuth redirect URLs, the `permissionActions.apiEndpoint` permission-gateway data) were updated to match. Only `api/centralSearch.php` (genuinely cross-module) and the pre-existing `api/public/` folder remain outside a module subfolder.

## 6. Current Functional Scope

The platform now supports several parallel user journeys:

**Admin/HR**: log in → manage leads through conversion to client → recruit and onboard candidates into employees → run attendance/leave/overtime/expense/asset operations → configure and generate monthly payroll → approve salary slips → manage per-client social content calendar → connect and publish to Instagram/Facebook (and, pending external approval, LinkedIn/Pinterest/Google Business Profile) → control permissions.

**Employee**: log in to a separate employee panel → punch in/out → apply for/cancel leave → submit expenses/overtime → view commission/points/salary history → view own attendance/leave history.

**Candidate**: separate public/candidate-facing flow → apply/get recorded as a candidate → go through HR verification → receive and sign an onboarding agreement → get marked as joined (becomes an employee record).

**Public/external**: client onboarding form (token-based, no login), social OAuth callbacks (Meta, LinkedIn, Pinterest, Google), Instagram webhook receiver, legal pages (privacy policy, terms, data-deletion instructions).

## 7. Current Routes and Screens

Routes are DB-driven (`routesMaster`) rather than hardcoded, and there are now 85+ registered routes across the journeys above. Rather than duplicate the full list here (it will drift out of date), use `route-setup` (`/route-setup`) in the admin panel, or query `routesMaster` directly, as the live source of truth. Routes are grouped by `moduleName` — the current module groups are: `Auth`, `Candidate Auth`, `Candidate`, `Candidate Onboarding Lead`, `Client Onboarding`, `Client Deliverable`, `Dashboard`, `Employee`, `Employee Panel`, `HR`, `HRMS`, `Lead`, `Onboarding Lead`, `Payroll`, `Public`, `Public Candidate Record`, `Setup`, `Automation`, `Social Media`, `System`.

Note: the old assumption of a hardcoded `/mamix/` base path no longer applies — `BASE_URL` (`includes/config.php`) resolves dynamically from the request/environment (with a production fallback for CLI/cron contexts), and `.htaccess` no longer hardcodes a project subfolder.

## 8. Technology Stack

### Backend

- PHP (Core PHP, procedural + a small number of domain "Engine" classes — no framework)
- MySQL/MariaDB
- PHPMailer (email)
- Dompdf (PDF generation — salary slips, onboarding agreements)

### Frontend

- Bootstrap 5
- Customized admin theme assets (originally a Spruko template)
- jQuery, jQuery DataTables
- ApexCharts and other bundled UI libraries from the original template
- SweetAlert2 for confirmations

### Tooling

- Node.js, npm, esbuild (frontend asset build pipeline — see note below)
- Composer
- WAMP/XAMPP + Apache local environment

**Build pipeline note**: the original template's buildable source (`src/`) was removed on 2026-09-01 after confirming it was unused dead weight — zero PHP/HTML pages ever referenced it, it had exactly one commit in its entire git history, and every real Modlus customization was made directly in `dist/assets/` (the build output), never round-tripped through the `src/` → `esbuild` → `dist/` pipeline. `esbuild.config.js` and the `npm run dev` script still exist in `package.json` but currently have nothing to build from; `dist/` is the actual, hand-maintained runtime asset directory.

## 9. Project Structure

```text
modlus/
|-- api/                  -- module-organized API endpoints (see §5.10)
|   |-- attendance/ leave/ payroll/ leads/ recruitment/ onboarding/
|   |-- commission/ points/ expense/ overtime/ assets/ holidays/
|   |-- client/ client-onboarding/ deliverables/ company/ permissions/
|   |-- social-media/ instagram/ linkedin/ pinterest/ google-business-profile/
|   |-- public/            -- pre-existing public-facing endpoints
|   `-- centralSearch.php  -- the one endpoint kept at api/ root (cross-module)
|-- api-gateway.php       -- API-layer permission gateway (see §5.1)
|-- app/
|   |-- controllers/      -- a handful of controllers (auth, candidate auth/profile, overtime setup)
|   |-- models/
|   `-- views/            -- auth-related views only; most pages now live directly in pages/
|-- cron/                 -- scheduled jobs (attendance auto punch-out, leave accrual/carry-forward,
|                             Instagram analytics sync/scheduler, mail retry, event notifications)
|-- database/
|   `-- migrations/       -- SQL migration files (the closest thing to a formal schema — see §10)
|-- dist/                 -- built/runtime frontend assets actually used by the PHP pages
|-- docs/                 -- detailed per-module documentation (Instagram production state,
|                             GBP integration spec, etc.) — see §5.9
|-- employee/             -- employee self-service pages (emp-* prefix)
|-- includes/             -- shared layout, auth, Crypto/Csrf, and domain "Engine" classes
|-- logs/                 -- diagnostic logs (mail, Instagram API, commission, PHP errors) — blocked
|                             from direct web access via logs/.htaccess
|-- pages/                -- the great majority of admin-panel pages and public-facing pages
|-- storage/               -- (see local environment for current contents/purpose)
|-- uploads/               -- user-uploaded files (resumes, onboarding documents, media, etc.)
|-- vendor/                -- Composer dependencies (PHPMailer, Dompdf)
|-- routes.php
|-- .htaccess
|-- package.json
|-- composer.json
`-- esbuild.config.js
```

### Important Directories and Files

- `includes/db.php` — database connection configuration (see §11 for current local defaults)
- `includes/auth.php` / `includes/emp-auth.php` — admin / employee route protection
- `includes/auth-functions.php` — redirect/input helpers, OTP generation, `DEV_MODE` handling
- `includes/permission-helper.php` — route- and action-level permission checks, shared by pages and `api-gateway.php`
- `includes/Crypto.php` — `encryptSecret()`/`decryptSecret()`, used for every OAuth token and API secret stored at rest (social integrations, never a second encryption mechanism)
- `includes/Csrf.php` — CSRF token generation/validation, used across the newer modules
- `includes/PayrollEngine.php` / `PayrollApprovalEngine.php` — payroll calculation and approval workflow
- `includes/AttendanceEngine.php`, `leaveEngine.php`, `leave-balance.php` — attendance and leave domain logic
- `includes/InstagramAutomation.php`, `FacebookPublisher.php`, `LinkedInAutomation.php`, `PinterestAutomation.php`, `GoogleBusinessProfileAutomation.php`, `SocialPostEngine.php` — one automation class per social platform, plus the unified publishing orchestrator
- `includes/header.php`, `includes/sidebar.php`, `includes/footer.php`, `includes/emp-header.php` — shared layout components (admin and employee panels each have their own header)
- `routes.php` / `routesMaster` (DB table) — routing
- `.htaccess` — clean URLs, the `api-gateway.php` rewrite rule, `includes/`/`vendor/` access blocking

## 10. Database Expectations

The project has grown from 2 expected tables to **77 tables**. Rather than hand-listing every column here (which will drift out of date immediately), this section groups tables by domain — `database/migrations/` is the actual source of truth for exact schema, and most tables are also self-healing at runtime (`ensure*Table()` functions create them if missing).

| Domain | Representative tables |
| --- | --- |
| Auth & users | `users`, `employeeusers` |
| Permissions | `routesMaster`, `permissionActions`, `rolePermissions`, `roleActionPermissions`, `userPermissionOverrides`, `userActionPermissionOverrides` |
| Leads & clients | `leads`, `leadCategories`, `leadPlans`, `leadRemarks`, `leadDocuments`, `leadStatusRemarks`, `leadConversions`, `leadsActivityLogs`, `clientMaster`, `clientOnboardingForms`, `clientFormAccessTokens`, `clientServiceCredentials` |
| Recruitment & onboarding | `candidateRecord`, `candidateRemarks`, `employeeProfileVerification`, `onboardingAgreements`, `onboardingAgreementSubmissions` |
| Attendance | `employeeAttendance`, `attendanceSettings`, `attendanceShifts`, `attendanceBreakTypes`, `attendanceBreakLogs` |
| Leave | `leaveApplications`, `leaveApplicationDays`, `leaveTypes`, `leaveSettings`, `leaveBalances` |
| Payroll | `payrollSettings`, `payrollSalarySlips`, `payrollSalarySlipPayments`, `employeeDeductions`, `employeeTrainingHoldSalary` |
| Overtime | `overtimeRequests`, `overtimeApprovals`, `overtimeRules`, `overtimeSettings` |
| Expenses | `employeeExpenses` |
| Points | `employeePointCategories`, `employeePointSettings`, `employeePointTransactions`, `pointCategories`, `pointSettings` |
| Commission/bonus | `employeeCommissionTransactions`, `commissionBonusCategories`, `commissionBonusSettings`, `commissionBonusApprovalLevels` |
| Assets | `assetMaster`, `assetCategory`, `assetAssignment` |
| Client social calendar | `clientCalendarPlans`, `clientCalendarActivityLog`, `clientDeliverables`, `deliverableFeatures`, `deliverablePlatforms`, `eventHolidayMaster` |
| Social media (cross-platform) | `socialPosts` |
| Instagram | `instagramAccounts`, `instagramSettings`, `instagramComments`, `instagramInsights`, `instagramWebhookEvents` |
| LinkedIn | `linkedinAccounts`, `linkedinSettings` |
| Pinterest | `pinterestAccounts`, `pinterestSettings` |
| Google Business Profile | `googleBusinessProfileAccounts`, `googleBusinessProfileSettings` |
| Company/system | `companySettings`, `eventMailLog` |

**Known schema quirk (pre-existing, not touched)**: a handful of the older tables (`leaveApplications`, `leaveTypes`) still carry a legacy `companyId` column from before the project's `clientId`-only architecture rule was established. It's present but effectively unused by current code — worth cleaning up eventually, but out of scope for any single feature change (see §14).

## 11. Local Setup Guide

### Requirements

- WAMP/XAMPP or another Apache + MySQL environment
- PHP 8.x
- MySQL/MariaDB
- Node.js and npm (only needed if you intend to reinstate a frontend build pipeline — see §8)
- Composer

### Setup Steps

1. Place the project inside the web root.
2. Create the required MySQL database.
3. Update database credentials inside `includes/db.php` if needed (it auto-detects a local WAMP/XAMPP environment vs. production).
4. Install Composer dependencies:

```bash
composer install
```

5. Run any pending SQL migrations from `database/migrations/` against your database (most tables also self-heal at runtime, but migrations are the documented, explicit record of schema changes).
6. Open the application in the browser using the configured Apache path.

Current code assumes (local/WAMP defaults in `includes/db.php`):

- database host: `localhost`
- database user: `root`
- database password: empty string
- database name: `modlus`

## 12. Configuration Notes

### Development Mode

`DEV_MODE` is still `true` in `includes/auth-functions.php`. In development mode, OTP email sending is bypassed (OTP values are still generated) and verification accepts any 4-digit OTP. **This must be set to `false` before production**, alongside configuring real email credentials and testing the end-to-end OTP flow.

### Email Configuration

Centralized through `includes/mailer.php` (`sendLoggedMail()`), with delivery logged to both the `eventMailLog` table and `logs/mail.log`, plus a retry cron (`cron/retryFailedMails.php`) for failed sends. `GMAIL_USERNAME`/`GMAIL_APP_PASSWORD` (or equivalent) still need real production values.

### Routing/Base Path

`BASE_URL` (`includes/config.php`) resolves dynamically — an environment variable override wins first, then a CLI/cron-specific production fallback (needed because cron has no `HTTP_HOST`), then normal request-based resolution. There is no longer a hardcoded `/mamix/`-style path baked into `.htaccess` or layout files.

### Token/Secret Encryption

Every OAuth access/refresh token and API secret across the social integrations (Instagram/Facebook, LinkedIn, Pinterest, Google Business Profile) is encrypted at rest via `includes/Crypto.php` (`ENCRYPTION_KEY`, overridable via the `MODLUS_ENCRYPTION_KEY` environment variable). Tokens are never returned to the browser, never logged, and never placed in URLs.

## 13. Security and Code Practices Already Present

- Password hashing (`password_hash()`/`password_verify()`) and prepared statements throughout
- Session-based route protection (separate admin/employee auth), with session regeneration after login
- Route- and action-level permission model (`permission-helper.php`), enforced both in page rendering and, for API endpoints registered in `permissionActions`, at the API-gateway layer (`api-gateway.php`)
- CSRF protection (`includes/Csrf.php`) — implemented across payroll approval, permission management, and all four social-platform integrations; not yet confirmed to cover every older state-changing endpoint (see §14)
- Encrypted-at-rest storage for all OAuth tokens and API secrets (`includes/Crypto.php`) — a single, reused encryption mechanism, never duplicated per-module
- Strict `clientId`-based multi-tenancy for every module built since the client-management phase — **`companyId` is deliberately never introduced** in new code, and every account/resource ownership check happens server-side (never trusting a browser-submitted `clientId`)
- Restricted access to `includes/` and `vendor/` via `.htaccess`
- `logs/.htaccess` denies direct web access to the `logs/` directory

## 14. Current Gaps and Important Notes

### Functional Gaps

- Dashboard is still mostly static/template content; only the Instagram summary widget is live
- Some client social-calendar panels (`social-data-entry.php`, `social-overview.php`) have `TODO(api)` markers for panels still running on local/mock data — see the in-file comments for exactly which
- Social login buttons on the auth pages are still UI-only
- Some header/profile links may still point to template pages instead of project routes (not recently re-audited)

### Technical Gaps

- `DEV_MODE` is still enabled — must be turned off, with real email credentials configured and the OTP flow tested end-to-end, before production
- CSRF protection is not yet confirmed across every older state-changing endpoint (implemented and confirmed for payroll approval, permissions, and all social integrations)
- A legacy `companyId` column still exists on a couple of older leave tables (unused by current logic, not yet cleaned up)
- No automated test suite is configured yet (`npm test` remains a placeholder)
- Instagram Comments, LinkedIn, Pinterest, and Google Business Profile are all implemented in code but blocked on external platform approvals/prerequisites (Meta App Review, a LinkedIn Company Page, Pinterest Standard Access, Google's Business Profile API approval chain) — not code gaps, but real blockers before those features can go live
- `leave-accrual`/`leave-carry-forward` crons exist but haven't been re-confirmed as registered in the production cron schedule

### Production Readiness Improvements Recommended

- Turn off `DEV_MODE`, configure real email/SMTP credentials
- Complete a CSRF coverage audit across all state-changing endpoints, not just the newer modules
- Resolve the `companyId` legacy-column cleanup on the leave tables
- Add automated test coverage (starting with payroll and leave calculation logic, given how much financial correctness depends on it)
- Formally resolve the MAMIX / Modlus / MQlus naming inconsistency (see §1)
- Pursue the external prerequisites for Instagram Comments, LinkedIn, Pinterest, and Google Business Profile independently, as each becomes available

## 15. Recommended Next Phase

Suggested next development priorities:

1. Turn off `DEV_MODE` and finalize production email delivery.
2. Complete a project-wide CSRF coverage audit.
3. Wire the remaining dashboard widgets to real data (attendance, leave, payroll, leads).
4. Add automated test coverage for payroll and leave calculation logic.
5. Resolve the Instagram Comments / LinkedIn / Pinterest / Google Business Profile external prerequisites as each becomes available, then complete live verification for each.
6. Clean up the legacy `companyId` column on the older leave tables.
7. Resolve the MAMIX / Modlus / MQlus naming inconsistency.

## 16. Documentation Maintenance Rules

This file should be updated whenever any of the following changes happen:

- a new route, page, or module is added
- a new database table or field is introduced
- any existing flow changes
- a feature moves from pending to completed (or gets paused/blocked on an external dependency)
- production configuration changes
- deployment steps change

When updating this README, always review these sections:

- `Last Updated`
- `Current Development Status`
- `What Has Been Done So Far`
- `Current Routes and Screens`
- `Database Expectations`
- `Current Gaps and Important Notes`
- `Recommended Next Phase`

For the social media integrations specifically, the detailed, evidence-backed record of what's actually production-verified lives in `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md` (also covers LinkedIn, Pinterest, and Google Business Profile foundations) and `docs/GMB_INTEGRATION_FOUNDATION_PHASE_14.md` — this README should stay a high-level summary and defer to those for the full history/evidence.

## 17. Change Log

### 2026-09-01

- Reorganized `/api/` from ~207 flat files into 23 module subfolders; updated every internal reference (JS, PHP, OAuth redirect URLs, the `permissionActions.apiEndpoint` permission-gateway data) and verified no broken paths remain
- Built the Pinterest Integration Foundation (OAuth, board discovery/selection, encrypted tokens)
- Built the Google Business Profile Integration Foundation (OAuth with offline access, Business Profile account + location discovery/selection, encrypted tokens)
- Fixed a real payroll bug: approved half-day paid leave was being deducted twice (once correctly as covered leave, once again through an attendance-half-day deduction path unaware of leave applications) — traced to the exact root cause, fixed with a minimal change, and verified against real August 2026 data plus 11 controlled test scenarios
- Broke the salary slip's single "Leave Deduction" line into itemized rows (paid-within-entitlement, excess-paid, unpaid, probation, notice-period, informed, uninformed), each showing its day count, so HR can see paid-vs-unpaid leave directly in the Deductions section
- Removed the unused `src/` directory (original vendor template source; confirmed zero runtime references, one commit in its entire git history, and that all real customization happened directly in `dist/`)
- Rewrote this README to reflect the project's actual current scope (previously described only the original auth + lead-management phase)

### 2026-08-29 through 2026-08-31 (LinkedIn foundation, social data entry)

- Built the LinkedIn Integration Foundation (OAuth, organization discovery/selection, encrypted tokens) — paused pending a LinkedIn Company Page for the required Developer App
- Added the Social Media Data Entry page and related client-calendar/deliverables work

### 2026-04-09 (original)

- Created initial project documentation README
- Documented the original auth, lead management, routing, and setup flow
- Added Setup menu and `/setup` route/page (Master Setup)
- Converted Add Lead flow to modal + AJAX with live table update and toast feedback
- Added leads filters for status/source and sequence-based SNo behavior
- Centralized the Mamix-branded toast system, reusable UI initializers, floating labels, and shared table styling hook

## 18. Summary

Modlus has grown from an authentication + lead-management CRM into a much broader platform: recruitment and onboarding, attendance, leave (with monthly accrual and carry-forward), a fully settings-driven payroll engine with a correctly-integrated leave-to-payroll deduction pipeline, overtime/expense/points/commission/asset tracking, a route- and action-level permission system with a dedicated API permission gateway, a per-client social content calendar, and four social media platform integrations (Instagram/Facebook production-verified; LinkedIn, Pinterest, and Google Business Profile implemented and pending their respective external approvals).

The immediate priorities are the same class of work as before, just at a larger scale: turn off `DEV_MODE` and finalize production email, complete a project-wide CSRF audit, wire the remaining dashboard widgets to real data, add automated test coverage (especially for payroll/leave, given how much financial correctness depends on it), and progress each paused social integration as its external prerequisite clears.

---

## 19. Leave Management Module — Detailed Reference

This section is the detailed technical reference for the leave module specifically (folded in from a separate note; kept here rather than as a second document per §16's "avoid competing docs" principle).

### 19.1 Core Features

**Employee side**: apply leave via modal (no page reload), view all applied leaves (live-loaded table), cancel a pending leave (SweetAlert confirmation), toast notifications (no SweetAlert spam for routine actions), live validation preview before submit (day-count calculation, balance check, rule validation).

**Admin side**: view all employee leaves, review counted leave dates in a Bootstrap modal, approve/reject individual dates or all pending dates, with parent summary statuses `pending`, `approved`, `rejected`, `partial`, and `cancelled` (employee-initiated only).

### 19.2 API Structure

Employee-facing: `api/leave/getLeaveSetup.php`, `api/leave/applyLeave.php`, `api/leave/getMyLeaves.php`, `api/leave/cancelLeave.php`, `api/leave/getLeaveBalance.php`.
Admin-facing: `api/leave/getAllLeaves.php`, `api/leave/getLeaveDays.php`, `api/leave/updateLeaveDayStatus.php`, `api/leave/updateLeaveStatus.php` (Approve All / Reject All).

(Paths reflect the `/api/` reorganization in §5.10/§17 — all leave endpoints now live under `api/leave/`.)

### 19.3 Database Structure

`leaveApplications` (id, employeeId, leaveTypeId, fromDate, toDate, totalDays, `dayType` full/half, reason, status pending/approved/rejected/partial/cancelled, createdAt), `leaveApplicationDays` (one counted leave date per parent request, `dayType` full/half, status pending/approved/rejected, reviewedBy/reviewedAt, unique leaveApplicationId+leaveDate), `leaveTypes` (name, code, isPaid, totalLeaves, allocationType monthly/yearly, isActive, allowHalfDay, maxConsecutiveDays, applicableGender, allowNegative), `leaveSettings` (workingDays, weekendPolicy, sandwichRule, maxLeavesPerRequest, minNoticeDays, carryForward, etc.), `leaveBalances` (employeeId, leaveTypeId, totalLeaves, usedLeaves, remainingLeaves), `employeeusers` (used for validation and email notifications).

### 19.4 Validation Engine

Phase 1: required fields, date validation, working-days calculation. Phase 2: max leaves per request, minimum notice period, max consecutive days, gender applicability, overlap check, leave-balance check (skipped when the leave type's `allowNegative = 1`, which both current leave types have — see below).

### 19.5 Leave Balance System

`getOrCreateBalance()` auto-creates a balance row. Balance usage is recalculated from approved `leaveApplicationDays` units (`full` = 1.0, `half` = 0.5), so repeated approvals are idempotent and approval/rejection reversals restore the stored balance correctly.

### 19.6 Admin Approval Flow

```
Employee Apply → parent status = pending + one leaveApplicationDays row per counted date
        ↓
Admin Review:
    → approve/reject individual dates
    → Approve All / Reject All updates pending child dates
    → parent summary = pending/approved/rejected/partial
    → balance recalculated from approved child-day units
        ↓
Employee Cancel (only while pending)
    → status = cancelled
```

### 19.7 UI Behavior

Apply Leave: modal-based, no confirmation popup, toast only. Admin Review opens a date-wise modal without expanding the main table. Cancel Leave: SweetAlert confirmation, then the API call. Table: live reload, no page refresh. Status badges: approved=green, rejected=red, partial=info/blue, cancelled=grey, pending=yellow.

### 19.8 Email & Logging

Uses the centralized `mailer.php`/`sendLoggedMail()` system; logs to `eventMailLog` (DB) and `logs/mail.log` (file). Events: leave applied, final leave approved/rejected. Per-date review does not intentionally send one email per click.

### 19.9 Multi-tenancy / Session Model

Leave management currently uses a simplified flow without a strict per-client `clientId` enforcement layer of its own — a deliberate, temporary decision made to avoid complexity from the dual admin/candidate login system, not an oversight. There is **no role-based access system** for leave specifically (yet); admin has full access, and candidate/employee restrictions exist separately via the employee-panel auth layer. This is a narrower scope than the `clientId`-per-resource rule described in §13, which applies to client-owned resources (leads, social accounts, etc.) — leave records belong to `employeeusers`, an internal-staff concept, not a client-owned one.

### 19.10 Payroll Integration (added since the original leave-module note)

The leave module's original design note listed "carry forward automation" and "monthly accruals" as upcoming/planned — **both now exist** as cron jobs (`cron/leave-accrual.php`, `cron/leave-carry-forward.php`; confirm they're registered in the production cron schedule). More importantly, the leave module's original note did not mention payroll at all — as of 2026-09-01, `PayrollEngine::calculateSalarySlip()` is the authoritative consumer of `leaveApplications`/`leaveTypes` for salary deduction purposes (see §5.5), reusing this module's data directly rather than maintaining any second leave-balance system.

### 19.11 Known Design Decisions (carried forward from the original note)

- No role-based access system specific to leave (yet) — see §19.9
- `companyId` logic is relaxed/legacy on the leave tables specifically (see §10's "known schema quirk") — this predates, and is an exception to, the `clientId`-only rule described in §13
- Admin has full access; employee/candidate restrictions exist via the separate employee-panel auth layer

### 19.12 Remaining/Planned

- Reports/analytics dashboard for leave
- Role-based access control specific to leave (a future module)
- Further SMTP/email delivery enhancements
