# CLAUDE.md — Moment / Modlus Development

## Purpose
This is the compact persistent context for Claude working on this project.

**READ THIS FILE FIRST.** Do not scan the entire project to rediscover architecture, conventions, completed phases, or settled decisions. Inspect only files directly relevant to the current task.

Before changing code:
1. Read this file.
2. Identify directly relevant files.
3. Inspect only those files and dependencies needed for the task.
4. Do not re-audit completed phases unless the task affects them.
5. Preserve working architecture unless a demonstrated requirement requires change.

If the task conflicts with this file, explain the conflict before changing architecture.

---

## 1. Core Project Rules

- Stack: Core PHP + MySQL/MariaDB.
- Local development: XAMPP.
- Hosting: Hostinger.
- **Never use `companyId`.** Multi-client isolation uses `clientId`.
- PHP files: lowercase where practical.
- Variables: camelCase.
- DB tables/columns: camelCase.
- Reuse existing architecture and patterns.
- Avoid unnecessary frameworks, abstractions, duplicate engines, duplicate permission systems, or speculative features.
- Do not rename existing fields merely for style.

### CRITICAL: Sidebar/Menu
Whenever a new page, menu, or submenu is created, integrate it into the appropriate sidebar/navigation **in the same implementation**.

Always verify:
- admin/manager sidebar
- employee sidebar when applicable
- correct menu group/submenu
- no duplicate entry

Never leave sidebar integration for later.

---

## 2. Security / Permissions

Never trust client-supplied IDs or permissions for authorization.

Use existing:
- authentication/session checks
- route permissions
- ownership checks
- `clientId` isolation
- CSRF protection for state-changing requests

Frontend restrictions are not authorization.

### Admin
Typical flow:
`login → $_SESSION['userId'] → route permission → page/API`

### Employee
Typical flow:
`employee login → $_SESSION['candidateId'] / employee auth → route permission → page/API`

Employee permission resolution:
1. `userPermissionOverrides`
2. otherwise `rolePermissions`
3. otherwise deny

Existing permission types include View/Add/Edit/Delete/Approve/Button Action.

Do not create a new role/permission system.

Do not redesign `includes/auth.php` merely to solve an employee-page problem; use the existing employee authentication pattern.

---

## 3. Social Media Architecture

### Instagram/Facebook
Production foundation and image publishing are already implemented.

Source of truth:
`docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md`

Architecture uses:
Facebook Login → Instagram Business Account → Graph API

Security includes encrypted tokens, OAuth state validation, CSRF, and client isolation.

Do not modify `SocialPostEngine.php` unless the requested task explicitly requires it.

### LinkedIn
Foundation implemented; publishing deferred/paused because real LinkedIn company/developer prerequisites are unavailable.

### Pinterest
Foundation implemented; publishing scopes deferred.

### Google Business Profile
Foundation implemented; publishing/scheduling/reviews/analytics deferred.

Do not resume these deferred areas unless explicitly requested.

---

## 4. Social Content Data Entry

Pages:
- `pages/social-data-entry.php`
- `pages/social-overview.php`

Concept:

**Data Entry = RAW MATERIAL / ACTUALS**

Main table:
`clientSocialContent`

Important fields include:
- id
- clientId
- platformId
- featureId
- contentDate
- title
- socialMediaHandle
- postType
- rawContent
- songUrl
- ideaReference
- contentDescription
- status
- caption
- referenceLink
- remarks
- created/updated metadata

No unique business key on `(clientId, contentDate, platformId, featureId)` — that constraint was removed (2026-09-12, `database/migrations/2026-09-12-remove-social-content-unique-slot.sql`). Multiple independent rows may share the same client/date/platform/feature; `id` is the only identity. Calendar planning is a minimum requirement, not a uniqueness rule.

Do not duplicate raw brief fields into Production unless a genuinely separate production value is required.

Current conceptual flow:

`Data Entry → Production`

Do not connect Data Entry directly to Automation/publishing.

---

## 5. Social Content Production

Concept:

**Production = MANUFACTURING**

Main files:
- `includes/SocialContentProductionEngine.php`
- `pages/social-content-production.php`
- `employee/emp-content-production.php`

Main tables:
- `socialContentProduction`
- `socialContentProductionHistory`

`socialContentProduction` contains production assignment/workflow/output data.

Current important fields:
- id
- clientSocialContentId
- assignedEditorId
- status
- assignedAt
- dueAt
- submittedAt
- approvedAt
- createdBy
- createdAt
- updatedAt
- submissionType
- submissionUrl

`socialContentProductionHistory` is append-only. Preserve historical workflow events.

### Statuses

- NEW
- ASSIGNED
- IN_PROGRESS
- SUBMITTED
- CORRECTION
- APPROVED
- PRODUCTION_READY

Normal flow:

`NEW → ASSIGNED → IN_PROGRESS → SUBMITTED → APPROVED → PRODUCTION_READY`

Correction:

`SUBMITTED → CORRECTION → IN_PROGRESS → SUBMITTED`

Editors:
- work only on assigned tasks
- can start
- can submit
- cannot approve
- cannot mark Production Ready
- cannot assign/reassign

Managers/admins:
- assign/reassign
- review
- approve
- request correction
- mark Production Ready

There is currently no separate REJECTED status. Do not invent one unless explicitly requested.

---

## 5a. Other Graphic Content (second Production source)

A second raw-material source feeding the **same** Production Queue, for misc. requests that don't fit `clientSocialContent`'s platform/feature shape (Blog, Bio, Caption, YouTube Description, Short/Long Video and Reels, Notes, Others).

Page: `pages/other-graphic-content.php` (route `/other-graphic-content`, sidebar under "Social Media", admin-only).
Engine: `includes/otherGraphicContentEngine.php` (mirrors `socialContentEngine.php`'s CRUD pattern, no calendar-plan concept).
Table: `otherGraphicContent` (clientId, contentDate, deadlineDate, editType, title, hook, reference, notes, contentDescription, status).

`socialContentProduction` widened, not redesigned: `clientSocialContentId` is now nullable; new `otherGraphicContentId` (own UNIQUE key + FK) and `sourceType` (`'social'`/`'other'`) columns. A task points at exactly one source, never both. `createTaskForOther()` mirrors `createTask()` (dueAt = the user's own Deadline Date, not the contentDate-1day formula).

**Assign/start/submit/review/markReady/history are 100% unchanged and source-agnostic** — they operate purely on `socialContentProduction.id`/`status`. Only `getTask()`/`listTasks()` (now `LEFT JOIN` both source tables + `COALESCE`) and `getProductionSummary()` (separate `otherStatusCounts`/`otherOverdueCount`, existing Social Content keys untouched; editor workload combines both sources) needed changes. See `docs/SOCIAL_CONTENT_PRODUCTION_FOUNDATION.md` ("Other Graphic Content — a second raw-material source for Production") for full detail.

---

## 6. Production Output Submission

Phase 3 is complete.

Editors submit finished production through:

`api/social-content-production/emp-submit-production.php`

Engine method:

`submitProduction()`

This is the single intended path for:

`IN_PROGRESS → SUBMITTED`

An empty/invalid submission must be rejected server-side.

### Google Drive
Stored as:
- `submissionType`
- `submissionUrl`

Drive/Docs HTTPS URLs are validated.

No Google Drive API integration. It is a reference link only.

### Server Upload
Storage:
`uploads/production/{taskId}/`

Use generated filenames only.

Current protection:
- extension allow-list
- real MIME validation using `finfo`
- supported image/video MIME types
- 100 MB application limit
- PHP-family execution disabled in production upload directory
- ownership checked before disk write and again inside engine

Never trust the original filename.

Never weaken MIME validation.

Do not introduce media-library/transcoding/CDN/versioning systems unless explicitly requested.

### Correction / Resubmission
Latest submission lives in `submissionType/submissionUrl`; history remains append-only.

Do not destroy previous workflow events.

---

## 7. Production Manager Review

Manager page:
`pages/social-content-production.php`

Detail modal contains (Task Overview, Content Brief, Production Output — same structure on both manager and employee pages):
- Task Overview: Client, Platform, Feature, Content Date, Status, Assigned Editor (manager page only), Due/TAT
- Content Brief
- Production Output: submission type/link/media, submitted-by / submitted-at

History is a separate "Production History" timeline modal (own action per task row, oldest-first), not part of the detail modal.

Manager actions:
- Approve
- Request Correction
- Mark Production Ready
- Send to Caption (Social Content only, once reviewStatus is approved) — sets `socialContentProduction.sentToCaptionAt` via `SocialContentProductionEngine::sendToCaption()`, which **locks** the task against further assign/start/submit/review/markReady edits (enforced in `lockTask()`/`lockOwnTask()`) and makes it visible/actionable in Caption Area (`SocialContentCaptionEngine` now also requires `sentToCaptionAt` to be set, not just an approved reviewStatus). No reverse/un-send action exists.
- Other Graphic Content only: a "Send" choice (Send to Automation / Send to Client) — Send to Client only records a Production History entry (`recordExternalEvent()`); no real client-delivery mechanism exists.

**`Send to Automation` was removed from this page's Action column** (2026-09-18) — Automation now only ever originates from Caption Area (after a caption is selected) or the Other Graphic Content choice above. `Mark Production Ready` still does **not** publish content; the actual handoff that creates the `socialPosts` row (`includes/SocialAutomationHandoffEngine.php`) is unchanged, just no longer triggered from here — see `pages/social-automation.php` (Automation Queue) and §18.

---

## 8. TAT Rule — IMPORTANT

TAT is NOT 24 hours from task creation.

Formula:

**`dueAt = contentDate - 1 day at 05:00 PM`**

Examples:
- 05 Sept → 04 Sept 05:00 PM
- 06 Sept → 05 Sept 05:00 PM
- 10 Sept → 09 Sept 05:00 PM
- 01 Oct → 30 Sept 05:00 PM

If the calculated deadline is already past:
- keep it
- do not clamp it
- do not automatically move it forward
- existing overdue logic should show overdue

Reassignment must NOT reset TAT.

Existing assign-modal manual due override may remain if already implemented, but the automatic value is the default.

---

## 9. Completed Production Phases

### Phase 1
- Complete Entry
- READY state
- automatic Production task creation
- duplicate protection
- transactional completion
- manual Send-to-Production path removed

### Phase 1.1
- READY cannot be manually selected to bypass completion
- ready entries cannot be downgraded by normal editing

### Phase 2
- assignment/reassignment
- employee assigned-task view
- start/submit workflow
- approval/correction/Production Ready
- history
- TAT
- ownership isolation
- permission verification

### Phase 2.1
- fixed manager Assign Editor modal
- verified manager/employee sidebars
- fixed stale inert production permissions

### Phase 3
- real production output submission
- Google Drive link OR server upload
- MIME validation
- manager review of actual output
- correction/resubmission preservation
- automatic calendar-based TAT
- compact filter bar
- documentation update

All reported Phase 3 tests passed.

---

## 10. Current Production API Files

Relevant APIs include:
- `api/social-content-production/get-tasks.php`
- `api/social-content-production/manage-task.php`
- `api/social-content-production/get-editors.php`
- `api/social-content-production/emp-get-tasks.php`
- `api/social-content-production/emp-update-task.php`
- `api/social-content-production/emp-submit-production.php`

Data Entry completion:
- `api/social-content/complete-entry.php`

Before changing an API path, verify current routing because the API directory reorganization was completed in September 2026.

---

## 11. API Directory Reorganization

Migration:
`database/migrations/2026-09-01-api-directory-reorg-endpoint-paths.sql`

208 APIs total:
- 206 moved into relevant subfolders
- `centralSearch.php` retained
- `api/public/submitCandidate.php` retained

Reorg lint/audit/gateway tests passed.

When changing paths, check:
- routesMaster
- frontend AJAX/fetch references
- OAuth redirect URLs where relevant
- permission/gateway mappings
- documentation

Never assume an old API path still exists.

---

## 12. Database / Migration Rules

Before schema changes:
1. Inspect current schema.
2. Confirm field/table does not already exist.
3. Prefer additive migrations.
4. Never silently drop production data.
5. Preserve FK/unique constraints.
6. Test migration.
7. Document the change.

Do not create duplicate fields for data that already has a valid home.

---

## 13. UI Rules

The user prefers practical, compact admin UI.

Do not redesign existing pages unless explicitly requested.

When modifying UI:
- preserve existing design
- reuse current cards/modals/buttons
- make the smallest necessary change
- preserve existing filters unless requirements say otherwise

Filter bars should generally be:
- compact horizontal row on desktop
- responsive wrap on small screens

---

## 14. Testing Rules

For PHP:
- run `php -l` on every touched PHP file
- test affected engine methods
- test API validation
- test authorization/ownership
- test CSRF
- test client isolation where relevant
- test lifecycle transitions
- test reachability where practical

For UI:
- verify desktop
- verify responsive behavior
- verify buttons/modals/filters
- verify sidebar/menu integration

If no authenticated browser session is available:
- explicitly say browser verification was not performed
- never claim it was performed
- still run all available non-browser tests

Never claim a test passed unless it was actually run.

---

## 15. Documentation Rules

For meaningful module/phase changes:
- update the relevant documentation
- document architecture decisions
- document security constraints
- document migrations
- document deferred work when relevant

Current Production documentation:
`docs/SOCIAL_CONTENT_PRODUCTION_FOUNDATION.md`

Do not create redundant docs for trivial changes.

---

## 16. Deferred Work — DO NOT IMPLEMENT SILENTLY

Currently deferred:
- publishing automation for LinkedIn/Pinterest/GBP (foundations only)
- unified cross-platform scheduler (a single scheduler already handles Instagram+Facebook; extending it to the deferred platforms above is not started)
- platform-specific publishing mapping
- media library
- transcoding
- thumbnails
- CDN
- production media versioning
- advanced media management
- earlier status-badge gap
- field-naming cleanup/overlap cleanup
- placeholder approval/publishing/analytics sidebar routes
- LinkedIn publishing
- Pinterest publishing
- GBP publishing

The app currently has no auth-gated file-serving architecture. This is an existing app-wide limitation, not introduced by Production Phase 3.

---

## 17. How Claude Must Work

For every task:

### FIRST
Read `CLAUDE.md`.

### THEN
Identify directly relevant files only.

### BEFORE EDITING
Inspect the relevant:
- engine
- API
- page
- database schema/migration
- auth/permission pattern
- sidebar if a page/menu is involved

### DO NOT
- scan the whole project without a reason
- reread every historical document
- rebuild completed phases
- invent requirements
- create duplicate systems
- introduce `companyId`
- bypass permissions
- trust client ownership fields
- claim unperformed tests
- claim browser testing without an authenticated browser
- connect Production to Automation prematurely

### AFTER EDITING
Run focused tests and report:
1. Files changed
2. Database changes
3. Security/permission changes
4. Tests performed
5. Test results
6. Browser verification status
7. Deferred items
8. Concerns/follow-up

---

## 18. Current State / Next Work

Current completed workflow (Phase 4, complete):

`Data Entry → Complete Entry → Production Task → Assignment → Editor Start → Production Output → Manager Review → Approve/Correction → Production Ready → Caption Area (generate + select caption) → Send to Automation → socialContentAutomationHandoff → socialPosts → existing cron/instagramScheduler.php → InstagramAutomation.php/FacebookPublisher.php → Meta Graph API → Published Post`

Production Ready → Automation is now connected (Phases 4.1–4.7), via the existing, unmodified `SocialPostEngine`/`InstagramAutomation`/`FacebookPublisher`/`cron/instagramScheduler.php`. Real Facebook and Instagram publishes (including a real scheduler-driven publish) have been performed and independently verified — see `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md` §28 for the full audit, real post ids, and documented operational limitations (an "ambiguous Meta success" edge case requiring manual reconciliation; no automatic retry).

`SocialContentProductionEngine.php` remains isolated from `socialPosts` — the handoff is owned entirely by `includes/SocialAutomationHandoffEngine.php`, a separate engine, per the approved Architecture Lock.

**Phase 8/9 (complete):** Caption Area is now wired into the handoff. `SocialAutomationHandoffEngine::checkEligibility()` requires a `socialContentCaption` row with `status='selected'` (new state `CAPTION_NOT_SELECTED` otherwise, checked after `ALREADY_HANDED_OFF` so already-sent tasks are unaffected), and `resolveAndRegisterHandoff()` now sends that selected caption to `saveSocialPost()` instead of the raw Data-Entry caption. A read-only `/social-automation` page ("Automation Queue", admin-only, under the existing "Automation" sidebar group) renders `SocialAutomationHandoffEngine::listQueue()` — a join across the existing handoff/`socialPosts`/production/caption tables, no new table, no new engine. Row actions reuse existing endpoints only (composer for edit, `send-to-automation.php` for send/retry, `get-tasks.php` for view).

**Phase 10 (complete):** `listQueue()` was originally `FROM socialContentAutomationHandoff`, so a task only appeared in the queue *after* someone manually clicked "Send to Automation" on the Production Queue — caption selection alone never surfaced anything here. Fixed to be driven `FROM socialContentProduction`: a task now appears the moment it's `PRODUCTION_READY` + `sourceType='social'` + eligible platform + **either** has a `selected` caption **or** already has a handoff row (the "or" preserves the two real handoffs created before the caption gate existed, which have no caption row at all — without it they'd silently disappear, a regression). A task with no handoff yet reports status `'eligible'` and its media preview comes from `resolveMedia()` (same method the real send already uses), not `socialPosts.mediaUrl`, since no post row exists yet. See `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md` §29/§30 for full detail and tests performed.

Next work is not yet defined; do not resume LinkedIn/Pinterest/GBP publishing or build a new scheduler unless explicitly requested.

---

## 19. Source-of-Truth Principle

When this file says something is implemented, do not rebuild it from scratch.

Inspect actual code only when:
- the requested task touches it
- a bug is reported
- a dependency must change
- implementation may differ from this file

If code and this file disagree:
1. inspect the current code
2. determine the current state
3. update documentation if needed
4. do not blindly trust either source

Keep this file concise and update it after major architectural changes.
