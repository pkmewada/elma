# Social Content Production — Foundation

## Purpose

Think of the Social Media module as a small factory:

- **Social Media Data Entry** (`clientSocialContent`) = raw material — the brief for what needs to be made.
- **Content Production** (`socialContentProduction`, this document) = manufacturing — turning that brief into finished content, with a manager assigning work and reviewing it.
- **Social Media Automation** (`socialPosts`, `SocialPostEngine.php`) = final dispatch — publishing finished content to Instagram/Facebook.

This document covers only the middle stage. It is a **foundation**: assignment, status tracking, TAT, and remark history. It intentionally does not yet include the actual content-production fields (captions, song selection, media upload, etc.) — those are future phases built on top of this same `socialContentProduction` row.

## Relationship to clientSocialContent

`socialContentProduction.clientSocialContentId` references `clientSocialContent.id`. One raw entry gets **at most one** production task (enforced by a unique key). `clientSocialContent` remains the single source of truth for the raw brief (client, platform, feature, date, title, raw content, etc.); `socialContentProduction` only stores workflow state and never duplicates those fields.

**The identity chain is entirely id-based, end to end, confirmed by a project-wide audit (2026-09-12): `clientSocialContent.id` → `socialContentProduction.id` (via the unique `clientSocialContentId` above) → `socialContentAutomationHandoff.id` (via its own unique `productionId`, see Phase 4.1 below).** `clientId`+`contentDate`+`platformId`+`featureId` is **not**, and must never be treated as, a unique key — the exact same tuple may legitimately belong to any number of independent `clientSocialContent` rows (see "Calendar Planning defines the minimum" further below). Every mutating operation across Assign/Reassign, Start, Submit/Resubmit, Correction, Approve, Mark Ready, and Send to Automation resolves its target strictly by the production task's own `id` (`lockTask()`/`lockOwnTask()`) or, for Automation, by `productionId` (`checkEligibility()`/`registerHandoff()`/`findExistingHandoff()`) — never by re-deriving a record from client/date/platform/feature. Duplicate-handoff prevention is likewise per production task (`UNIQUE(productionId)` on `socialContentAutomationHandoff`), not a global date/platform/feature dedupe: two records sharing an identical tuple are assigned, reviewed, approved, and sent to Automation completely independently, each with its own history and its own handoff outcome. Verified directly: two content records created with an identical client/date/platform/feature tuple were carried through the full Assign → Start → Submit → Correction → Approve → Mark Ready → Send to Automation → retry lifecycle independently, with zero cross-record interference and zero duplicate handoffs.

## Post Type classification (Post/Story → Format)

*(Section name kept as the original technical/conceptual name — `postType`/`format` are the actual property names `classifyPostType()` returns and are unchanged. The current **on-screen labels** are "Posting Type" and "Content Format" — see "Terminology: 'Plan' vs 'Posting Type' / 'Content Format'" further below for the final, authoritative wording and why it changed from what this section originally shipped with.)*

`clientSocialContent.postType` is still exactly **one column** — this was a UI/documentation-only change (2026-09-14), no migration, no new field. What changed is how that one column's value is interpreted and displayed everywhere it's read: as **Content Format** (the granular media type), from which the parent **Posting Type** (`Post` or `Story`) is always derived, never stored separately.

```
Posting Type
├─ Post
│   ├─ Image
│   ├─ Reel
│   ├─ Carousel
│   └─ Video
└─ Story
    ├─ Image Story
    └─ Video Story
```

This replaces the earlier flat, single-level `postType` vocabulary (`Post` / `Reel` / `Story` / `Carousel` / `Video`, with no way to say "this is a Story, specifically a Video Story") with the two-level hierarchy above. Future development should use **Posting Type** for the top-level `Post`/`Story` distinction and **Content Format** for the specific media type underneath it — not "Feature Type" or bare "Post Type"/"Format" (both tried and superseded, see the terminology section below), and not "Feature," which was never this column's name and is easily confused with the unrelated `featureId`/`featureName` dimension (Calendar-planning's Post/Stories/Shorts/etc. — see "Identity is `platformId` + `featureId`" above; that dimension is untouched by this change, and is what "Plan" refers to in the UI — again, see below).

**Where `postType` is captured:** the Fill Now / Add-Edit entry modal (`#sovEntryModal`, `pages/social-overview.php`) is the only place it's ever written — `pages/social-data-entry.php`'s own Add/Edit form has never collected it, unchanged. That modal's single flat "Post Type" `<select>` (original, pre-hierarchy label) was replaced with two cascading selects, now labelled "Posting Type" and "Content Format": `#sovFormPostType` (`Post`/`Story`) and `#sovFormPostFormat` (options depend on the first). Only the **Content Format** value (`#sovFormPostFormat`) is sent to `save-entry.php` as `postType`; the top-level Posting Type is never persisted as its own value — it's a display/UI concept, re-derived on every read.

`#sovFormPostFormat`'s options under `Post` are `Image`/`Reel`/`Carousel`/`Video` (value and label identical). Under `Story` the **stored value** stays `Image Story`/`Video Story` (so Posting Type remains recoverable from the one column), but the **displayed option label** is shortened to just `Image`/`Video` (`POST_TYPE_FORMATS.Story` in `pages/social-overview.php`) — repeating "Story" in the dropdown is redundant once "Posting Type: Story" is already selected right next to it. `classifyPostType()` and the DB value are unaffected by this label-only shortening.

**Classification helper — `classifyPostType(raw)`.** Duplicated per-page the same way `esc()`/`fmtLongDate()` already are (no shared JS module exists in this codebase — see `pages/social-overview.php` and `pages/social-data-entry.php`). Maps a raw stored `postType` string, case-insensitively, to `{ postType, format }` (property names unchanged; displayed as "Posting Type"/"Content Format"):

| Stored value (any case) | Posting Type | Content Format |
|---|---|---|
| `image` | Post | Image |
| `reel` | Post | Reel |
| `carousel` | Post | Carousel |
| `video` | Post | Video |
| `image story` | Story | Image Story |
| `video story` | Story | Video Story |
| `post` *(legacy)* | Post | Post |
| `story` *(legacy)* | Story | Story |

Legacy values saved before this change (`Post`, `Story`, `Reel`, `Carousel`, `Video`, `Image`, any case) are all still classified correctly — a bare legacy `Post`/`Story` (no specific format was ever captured under the old flat vocabulary) maps to itself as both its own Posting Type and its own Content Format, which reads honestly as "Posting Type: Post · Content Format: Post" rather than guessing a format that was never actually entered. An entry with no `postType` at all — every entry created through `social-data-entry.php`'s own Add/Edit form, which never collects it — is simply not classified (`classifyPostType()` returns `null`), and every Posting Type/Content Format display is omitted for that entry rather than fabricated from `featureId`/`featureName`.

**Where it's shown:**
- `pages/social-overview.php`'s Pending Queue **Content** column — each content chip (`.sov-content-chip`, still labelled by `featureName`, unchanged) now carries a small "Posting Type: X · Content Format: Y" line beneath it when the entry has a classifiable `postType`, via `contentChipsFor()`.
- `pages/social-data-entry.php`'s **View Entry** modal (`renderViewDetails()`) shows "Posting Type" and "Content Format" as separate detail cells, in place of the single flat "Content Type" cell used immediately before this change.

**Deliberately unchanged:** `pages/social-content-production.php`'s existing "Content Brief" block still shows the raw `task.postType` value under its own "Post Type" label (`briefRow('Post Type', task.postType)`) — Production is a completed workflow and was left untouched per this change's scope; it will simply display whichever Format string (`Reel`, `Image Story`, etc.) the entry was saved with, same as it always displayed whatever `postType` held.

### Entry modal simplification (`#sovEntryModal`, `pages/social-overview.php`, 2026-09-14)

The Fill Now / Add-Edit entry modal no longer asks for Platform, Feature, or Social Media Handle:

- **Platform and Feature are locked to the row/record's own context, permanently** — they're no longer `<select>` inputs at all (this reverts the brief "Platform/Feature are live, user-selectable" capability documented above; see the corrected bullet there). `openEntryModal()` reads `ctx.platformId`/`ctx.featureId` straight from the Pending Queue row (Fill Now) or the record itself (Edit) and shows them, read-only, in the scope strip (`#sovModalScope`, alongside Client/Date, which were already locked) — `saveEntry()` sends `Number(ctx.platformId)`/`Number(ctx.featureId)` directly, never a form value.
- **Social Media Handle was removed from the form entirely**, not merged into the scope strip (it isn't a per-row/per-slot attribute the way Platform/Feature are, so there's nothing to preselect it *from*). To avoid silently blanking out existing entries' handles — `saveEntry()`/the engine overwrite every `CONTENT_COLUMNS` value on every save, a missing key included — `saveEntry()` now round-trips `ctx.socialMediaHandle || ''` unchanged instead of reading a removed field. New entries simply save an empty handle, same as any other field this modal doesn't collect.
- **Raw Content and Content Note (renamed from "Content Description") are now Quill rich-text editors** (`data-ui-editor="quill"`, `.sov-quill-field`) instead of a plain `<input>`/`<textarea>` — this reuses the one existing "text editor" component already wired up site-wide via `includes/footer.php`'s `window.ModlusUI.initEditors()` (the same convention `pages/setup.php` demonstrates), not a new library. Content is still saved as **plain text** — `quill.getText().trim()`, never `quill.root.innerHTML` — because `rawContent`/`contentDescription` are plain string columns and `pages/social-content-production.php`'s Content Brief already renders them as escaped text with `\n` → `<br>` (Phase 3, above); saving HTML would corrupt that unchanged, completed-workflow display.
  - Because a Quill container is a `<div>`, not an `<input>`/`<select>`/`<textarea>`, it's invisible to the page's existing delegated dirty-tracking listener (`input, select, textarea`). A second listener is bound directly to each Quill instance's own `text-change` event, lazily from inside `openEntryModal()` (`bindQuillDirtyTracking()`) — Quill itself is only constructed once `includes/footer.php`'s `DOMContentLoaded` handler runs, which happens *after* this page's own `$(function(){})` body (`footer.php` is included further down the page), so binding at page-load time would silently find nothing.

**Unchanged:** everything else about the modal — Client/Date locking, `#sovClearDataBtn`'s guard against deleting a record with real production work, the `reloadAndRender()` refresh after save/delete, CSRF, and the underlying `save-entry.php`/`SocialContentEngine::saveEntry()` API contract (still receives `platformId`/`featureId`/`socialMediaHandle` exactly as before — only *where those values come from client-side* changed).

### Terminology: "Plan" vs "Posting Type" / "Content Format" (UI-only, final — 2026-09-14)

Two genuinely different concepts live in the same Add Entry / View Entry / Pending Queue surfaces. Their user-facing labels went through two passes on the same day before landing here, and this section is the current, authoritative naming — earlier attempts are summarized at the end of this section for history, but code and UI match what's below.

**Planning Context → "Plan"** (backed by `featureId`/`deliverableFeatures.featureName`, unchanged). Wherever the UI shows the calendar-planning dimension for a specific date/client (e.g. Instagram's "Post" or "Stories" feature) — the Pending Queue table's column header, the Add Entry / Fill Now modal's scope-strip line, Data Entry's day-board column header, its filter-bar label/placeholder, and its Add/Edit form's (locked) field label — the label is **"Plan"** (e.g. `Plan: Post`), because the value comes from calendar planning context, not content classification.

**Content Entry → "Posting Type" + "Content Format"** (backed by `clientSocialContent.postType`, one column, unchanged — see "Post Type classification" above for the full stored-value/legacy-mapping details, which this rename does not affect). The two-level classification captured in the Fill Now / Add-Edit modal and shown in Data Entry's View Entry modal is labelled:
- **Posting Type** — `Post` or `Story` (`#sovFormPostType`, `classifyPostType().postType`)
- **Content Format** — `Image`/`Reel`/`Carousel`/`Video` under Post, `Image Story`/`Video Story` under Story (shown as just `Image`/`Video` in the Story dropdown) (`#sovFormPostFormat`, `classifyPostType().format`)

The Pending Queue's per-chip classification sub-line (`contentChipsFor()`) and both validation error messages (`'Posting type is required.'` / `'Content format is required.'`) use the same two terms.

**This is a pure label rename, both directions — no `id`, function, CSS class, or column changed.** `featureId`, `CATALOG.features`, `featureById()`, `fillFeatureOptions()`, `buildFeatureFilterOptions()`, the `#sdeFeature`/`#sdeFormFeature`/`#sovFormPostType`/`#sovFormPostFormat` element ids, `classifyPostType()`, `POST_TYPE_FORMATS`, `LEGACY_POST_TYPE_MAP`, the `postType`/`format` object properties `classifyPostType()` returns, the `.sov-feature-chip`/`.sde-feature`/`.sov-post-format` CSS classes, and `clientSocialContent.postType` itself are all exactly as documented earlier in this file.

**Internal Feature references remain only where required by planning/calendar architecture.** `pages/calendar.php` (Calendar Planner), `pages/social-media-setup.php` (Feature Configuration), and `pages/social-content-production.php`'s Task Overview/Content Brief (`Feature` cell; `Post Type` label on `task.postType`) all still say "Feature"/"Post Type" — none of them were touched by any pass of this rename. That wording is correct on the Calendar/Setup pages, which operate on the actual Calendar-planning dimension ("Identity is `platformId` + `featureId`" above); Production is a completed workflow left untouched per every prior phase's scope in this document, so it still shows the older "Post Type" label on the classification value even though Data Entry/Overview have since moved to "Posting Type."

**Superseded, 2026-09-15 — the Add Entry modal's `#sovFormPostType` select no longer exists.** The paragraph above and the `#sovFormPostType` references throughout this section describe the modal as it existed through 2026-09-14, when Posting Type was still a manually-picked field. It is not anymore — see "Add Entry modal — Posting Type inherited from Plan, no longer picked" further below for the current, authoritative behavior. `classifyPostType()`, `POST_TYPE_FORMATS`, `LEGACY_POST_TYPE_MAP`, and the `clientSocialContent.postType` column itself are all still exactly as documented here; only the modal's own form fields changed.

<details><summary>Superseded: the brief "Feature" → "Post Type" pass (same day, kept for history)</summary>

An earlier pass renamed every "Feature" label above (Pending Queue column header, scope strip, day-board column header, filter, Add/Edit form field) straight to "Post Type," on the reasoning that "Feature" was itself confusing next to the classification work. That immediately collided with the classification's own "Post Type"/"Format" labels inside the *same* Add Entry modal — accepted at the time as "the same on-screen word for two different things, not a bug" — and was corrected the same day into the three-way split above ("Plan" for planning context, "Posting Type"/"Content Format" for classification) once the collision was flagged as a real usability problem.

</details>

## Phase 3 — content brief visibility

Phase 3's audit found that most of the "what does Production need to see" fields already existed in `clientSocialContent` — they just weren't read or displayed anywhere. Phase 3 itself is a **read/display change only**: no new tables, no new columns, no migration.

**What changed:** `SocialContentProductionEngine::getTask()` and `::listTasks()` now additionally `SELECT` `c.caption, c.contentDescription, c.songUrl, c.ideaReference, c.referenceLink, c.socialMediaHandle, c.postType, c.remarks AS contentRemarks` from the same `clientSocialContent` join those two queries already had (they were already selecting `c.title`/`c.rawContent`). No new query was added — the existing join was extended.

**Where it's shown:** a new "Content Brief" block inside the existing "Production Task" detail modal on both `pages/social-content-production.php` (manager) and `employee/emp-content-production.php` (editor) — the same modal that already showed scope + history, no new modal or page. Each field renders only if non-empty (no blank labels, no literal "null"/"undefined"). `songUrl`/`referenceLink` render as clickable links only when they pass a `^https?://` check; otherwise as plain escaped text — never raw/unescaped HTML.

**Read-only, deliberately:** these fields are rendered, never editable, from Production. Data Entry remains their sole owner. If an editor or manager needs to communicate something *about* the task, that still goes through the existing `socialContentProductionHistory.remark` mechanism (assignment notes, submission notes, correction instructions, approval notes) — aliased in the query as `contentRemarks` (the Data Entry note) vs. `lastRemark` (the most recent Production history note) so the two are never confused in the API response or the UI.

**Unchanged:** status transitions, assignment/reassignment, ownership checks, TAT, approval, `markReady()` (no remark parameter added — the audit found insufficient evidence to justify it), CSRF, client isolation, the sidebar entries.

**Deferred, not fixed in Phase 3** (all previously flagged by the audit, left untouched again): the `scheduled`/`posted` status badge-vs-task-existence gap; the `caption`/`contentDescription` naming overlap; the `ideaReference`/`referenceLink` naming overlap; the three placeholder sidebar routes with no backing page; production media/file upload; the Automation handoff field mapping.

## Phase 3 — production output, manager review, automatic TAT

The Content Brief phase above made the raw material *visible*; this phase gives the editor an actual way to hand back finished work, and switches TAT from "unset until a manager types one" to a formula driven by the content's calendar date.

**Submission — two columns, nothing duplicated.** `socialContentProduction` gained `submissionType` (`'drive'` or `'media'`) and `submissionUrl` (the Drive link, or the path to an uploaded file — one column serves both, since only one is ever populated). No `submissionRemark` column: the editor's note continues through the existing `socialContentProductionHistory.remark` mechanism on the `submitted` action, exactly as before. Migration: `database/migrations/2026-09-02d-social-content-production-submission.sql`.

**`submit()` → `submitProduction()`.** A bare "submit with just a note and no actual output" no longer represents reality, so the method was replaced rather than duplicated — `IN_PROGRESS → SUBMITTED` now requires a valid `submissionType` + non-blank `submissionUrl`, enforced server-side regardless of what the client sends. `api/social-content-production/emp-update-task.php`'s old `'submit'` action was removed; the sole path is the new `api/social-content-production/emp-submit-production.php` (a dedicated multipart-capable endpoint, since a JSON body can't also carry `$_FILES`).

**Resubmission after a correction** overwrites `submissionType`/`submissionUrl` with the latest version (no version table — matches "don't build a full version system"), but `submitProduction()` writes a history remark naming what was submitted each time, so the sequence of past submissions stays reconstructable from `socialContentProductionHistory` even though the live columns only hold the latest.

**Google Drive submission:** validated as `^https://(drive|docs)\.google\.com/` — no Drive API integration, no download, just a reference link, rendered as an "Open Google Drive" external link (escaped, `target="_blank" rel="noopener noreferrer"`).

**Media upload:** `api/social-content-production/emp-submit-production.php`, modeled on this app's strongest existing upload pattern (`api/employee/updateEmployeeProfile.php`'s MIME-driven validation) since no shared upload helper exists anywhere in the codebase — every feature hand-rolls its own. Allow-list: `video/mp4`, `video/quicktime` (.mov), `video/webm`, `image/jpeg`, `image/png` — checked via `finfo` on the actual file content, never the client-supplied extension (verified: a plain-text file renamed `.mp4` is correctly rejected). The stored extension is derived from the verified MIME type. Filename is fully generated (`production_{taskId}_{time()}.{ext}`) — the original filename is discarded, never trusted. Stored under `uploads/production/{taskId}/`, 100MB cap enforced in code. Ownership is checked *before* any file touches disk (a request for another editor's task never reaches the filesystem), and again inside the engine.

Two new files harden the upload directory beyond this app's existing convention (which has no such protection anywhere in `uploads/`): `uploads/production/.htaccess` blocks `.php`-family files from executing there, and `uploads/production/.user.ini` raises `upload_max_filesize`/`post_max_size` to 100M/105M for this directory only (the server's real default is a stock 2M/8M — nowhere near workable for video). **Could not be verified end-to-end without a live authenticated upload** — if uploads still fail as "too large" in practice, the environment may need this raised at the php.ini/vhost level instead.

**No auth-gated file serving was built.** Every existing upload in this app (lead documents, expense receipts, employee photos, payroll files) is served as a plain static URL under `uploads/`, protected only by an unguessable generated filename — no exception exists anywhere, including files considerably more sensitive than social content. Production media follows the same established convention rather than introducing a new one; the JSON APIs that *return* the URL remain properly session- and ownership-gated (unchanged, existing infrastructure).

**Manager review:** the existing "Production Task" detail modal gained a "Production Output" block — submission type badge, an "Open Google Drive" / "View / Open Media" link, an inline `<video>` preview for video-type uploads (plain HTML5 `<video controls>`, no player library), submitted-by/submitted-at. Existing Assign/Review/Mark-Ready controls, modals, and logic: untouched.

**Detail modal layout (current, both `pages/social-content-production.php` and `employee/emp-content-production.php`):** three grouped, headed sections built entirely from data `get-tasks.php`/`emp-get-tasks.php` already return (no new fields, no new query) — **Task Overview** (Client, Platform, Feature, Content Date, Status, Assigned Editor [manager page only — an editor's own tasks are trivially "assigned to me"], Due/TAT), **Content Brief**, and **Production Output**, in that order. Every Content Brief/Overview value that's empty/null is simply omitted (no blank labels) — unchanged behavior, just no longer only applied to Content Brief. **History was removed from this modal** and now lives in its own **"Production History"** modal, opened via a dedicated action (`.scp-history` / `.ecp-history`) next to the existing View action on every task row. It re-uses the exact same `get-tasks.php`/`emp-get-tasks.php?id=` response (`getTask()` already attaches `history` via `getHistory()`, `ORDER BY h.id ASC`) — no new API or table. History renders as a simple vertical timeline (a CSS border-left line + dot per entry, no library), oldest at the top and latest at the bottom, showing the same fields as before per event: action, performed-by name and type, old→new status where applicable, remark, and timestamp.

**Automatic TAT.** `dueAt = contentDate − 1 day, 17:00` — computed once, in `createTask()`, at production-task creation, not left for a manager to fill in. Verified against every example in the spec, including the September→October month rollover. If the computed deadline already falls in the past (a late save/handoff), it is **not** pushed forward — the task shows as overdue immediately, via the existing `dueAt < NOW()` logic, unchanged. The Assign/Reassign modal's due-date field is retained (not removed) as an optional manager override — it's now pre-filled with the auto-calculated value rather than left blank, so a manager only ever needs to type into it when deliberately overriding. Reassignment's existing `dueAt = COALESCE(?, dueAt)` already preserved TAT across an editor change; confirmed unchanged.

**Filter bar:** the five existing filters (originally Month, Status, Editor, Client, Overdue — Month later replaced by a Date Range filter, see below) were moved out of the queue card's header into their own compact filter card — one row on desktop, wrapping responsively on small screens — mirroring `social-data-entry.php`'s existing filter-bar layout.

**Month filter replaced with Date Range (current).** Both `pages/social-content-production.php` and `employee/emp-content-production.php` (which had no date filter at all before this) now use the same flatpickr `mode:'range'` single-input pattern `social-data-entry.php` established (`#scpDateRange` / `#ecpDateRange`, defaulting to the current month) instead of a `<input type="month">`. Server-side, `SocialContentProductionEngine::listTasks()` and `::getProductionSummary()` share one `applyDateRangeFilter()` helper that replaced `DATE_FORMAT(c.contentDate,'%Y-%m') = ?` with an inclusive range on the exact same column — `c.contentDate >= fromDate` and/or `c.contentDate <= toDate` (either bound alone is valid; neither switches the filter to `createdAt`/`updatedAt`/`dueAt`). `api/social-content-production/get-tasks.php`, `get-summary.php`, and `emp-get-tasks.php` all take `fromDate`/`toDate` (`YYYY-MM-DD`, validated server-side) in place of `month`; an invalid/missing bound is simply not applied, not an error. Employee ownership scoping (`assignedEditorId` forced server-side in `listForEditor()`) is unaffected — the date range narrows the same already-owned result set. Existing filters (Status, Editor, Client, Platform, Overdue) combine with the date range exactly as they did with Month.

## Data Entry → Production handoff

`clientSocialContent.status` carries the raw-material lifecycle: `draft` → `ready` (→ `scheduled`/`posted`, used elsewhere, not by this handoff). No new column was added — `status` already supported a `ready` value, so the existing column was reused rather than building a second status system.

- **There is no manual "Complete Entry" step anymore (current, final rule).** `api/social-content/save-entry.php` now performs the entire handoff automatically, in one transaction, on every successful save:
  1. `SocialContentEngine::saveEntry()` persists the entry (same minimum-content validation as always: a title or raw content is required, or the save is rejected before anything else happens — no stricter rule was invented).
  2. `SocialContentEngine::completeEntry()` is called immediately after — the exact same method that used to be reached only via the old manual "Complete Entry" button — setting `status = 'ready'` (a no-op if the entry is already `ready`/`scheduled`/`posted`, so re-saving/editing never downgrades it).
  3. `SocialContentProductionEngine::getTaskByContentId()` checks whether this exact `clientSocialContentId` already has a production task; only if none exists does `SocialContentProductionEngine::createTask()` run (task creation logic still lives in exactly one place — this flow doesn't reimplement it). The task starts at its normal initial state (`NEW`, unassigned) — this handoff only *creates* the task, it never touches `socialContentProduction.status` or skips ahead in the `NEW → ... → PRODUCTION_READY` lifecycle.
  4. All three steps share one DB transaction — a saved/ready entry with no production task, or a task with a rolled-back save, cannot happen.
  - **Idempotent by construction**: re-saving/editing an entry that already has a task never creates a second one (step 3's existence check), regardless of double-clicks, retries, or repeated saves — verified directly against the real database.
  - **Per-record, not per-slot**: this runs once per `clientSocialContent.id` on every save. Three independent records for the same client/date/platform/feature each get their own production task the moment each is saved — production creation was never grouped by date/platform/feature/planned slot, and still isn't.
  - **"Production Created" means the task exists** — it says nothing about the task's own `socialContentProduction.status`, which still progresses through the normal manager/editor workflow (`NEW → ASSIGNED → ... → PRODUCTION_READY`) completely independently of this badge.
  - **UI** (`pages/social-data-entry.php`): the old **Complete Entry** icon/button, its confirmation dialog, and `api/social-content/complete-entry.php` itself have all been removed — there was no other caller anywhere in the project. A filled row's Action is now **Eye (View) only**; the passive **"Production Created"** badge (Status column, stacked under the status badge) appears automatically the moment a valid save creates the task — no click required.
- **`clientSocialContent.id` is the sole identity of an actual content record (final rule, superseding both the original lock and the intermediate unique-key claim described below).** A planned slot (e.g. `12 Sep → Instagram / Post`) guarantees that date/client requirement exists; it does **not** limit what can actually be produced, and it is **not** a uniqueness rule. Multiple independent `clientSocialContent` rows may share the exact same `(clientId, contentDate, platformId, featureId)` tuple — e.g. three separate Instagram Posts for the same client on the same day are all valid, distinct records. **This required an actual schema change**: the earlier claim that the pre-existing `uqClientSocialContentSlot` unique key `(clientId, contentDate, platformId, featureId)` already satisfied "multiple platform/feature combinations per date" was correct as far as it went, but real testing found it still blocked the *same* platform/feature repeating on the same date — so that unique key was dropped entirely (`database/migrations/2026-09-12-remove-social-content-unique-slot.sql`; the plain non-unique `idxClientSocialContentClientDate` lookup index and all FKs are untouched), and the matching application-level clash guard in `SocialContentEngine::saveEntry()` (and both pages' pre-save JS checks) was removed — Add/Fill Now always inserts a genuinely new row, never an update-in-disguise. Editing an existing row still resolves it strictly by `id` (`UPDATE ... WHERE id = ?`), never by tuple, so editing one record can never touch another that happens to share its tuple.
  - **"+" on an empty planned slot** (`.sde-fill`) still opens Add Entry with Client/Date/Platform/Feature locked to that exact slot, unchanged — this is the fastest path to fill the one thing that's actually still outstanding.
  - **Pending Queue → Fill Now** (`pages/social-overview.php`) keeps Client, Date, Platform and Feature tied to the queue row's own context — all four are shown locked/read-only in the modal's scope strip, none of them are form fields. **(Reverted 2026-09-14, see "Entry modal simplification" below.)** Platform/Feature briefly went through a phase as live, user-selectable `<select>` inputs (letting a manager record content on a different valid platform/feature than what was originally planned for that date); that capability was removed at the user's explicit request — Fill Now/Edit now always submit exactly the row's/record's own `platformId`/`featureId` to `save-entry.php`, never a manager-chosen alternative. This does not affect the "multiple independent records per tuple" rule above (still true) — it only removes the ability to pick a *different* platform/feature than the slot already implies.
  - **Fill Now is always available on every Pending Queue row, regardless of whether that row's planned slot already has a matching entry.** A queue row's own Status still reflects strictly whether *that one planned requirement* is fulfilled (`Ready`/`Filled`/etc., driven by `flagFor()`/the exact slot match — unchanged), but that is independent from whether more actual content can still be added on the same client+date — the plan is a floor, not a ceiling, so Fill Now never hides itself once one matching entry exists, once status reaches `ready`, or because the Content column already shows badges. The row-level Edit/Delete buttons that used to appear once a matching entry existed were removed from the queue row itself — a single queue row can no longer safely identify one specific record once several exist for the same date.
  - **Editing/deleting one specific record (current, via the Content column, `pages/social-overview.php`).** Each Content chip carries `data-id` set to its own `clientSocialContent.id` and is resolved only by that id — never by label, date, platform, or feature (two chips both labelled "Post" on the same row are two different ids and never collide). Clicking a chip opens the same entry modal in edit mode, pre-filled from that exact record; **Save Entry** on an existing id always issues `UPDATE ... WHERE id = ?` (via the same `save-entry.php` path Fill Now uses), never an insert, and never touches any other record sharing that tuple. A **Clear Data** button (edit mode only) calls `api/social-content/delete-entry.php`, which deletes that same exact id — see the Production-dependency guard below. Both actions end with the page's existing `reloadAndRender()` call, so the Health Matrix, Pending Queue, and Content chips all refresh together, exactly as Fill Now already did.
  - **Clear Data is blocked once real production work exists (current).** `clientSocialContent → socialContentProduction` cascades on delete (and from there to `socialContentProductionHistory` and `socialContentAutomationHandoff`), so deleting a row that already has a production task in progress would silently destroy that task's history and any automation handoff along with it. Since every save auto-creates a production task in the `NEW` state (see above), `delete-entry.php` allows the delete only while that task is still `NEW` (untouched) or absent; once it has moved to `ASSIGNED`/`IN_PROGRESS`/`SUBMITTED`/`CORRECTION`/`APPROVED`/`PRODUCTION_READY`, the delete is refused server-side with a message naming the current status, instead of cascading blind. This is a status check only — no new table, no new column, no new migration.
  - **Client Health Matrix Filled count (`pages/social-overview.php`, `computeHealth()`) counts actual records, not fulfilled slots.** Previously `Filled` was derived from `findEntry()` per planned slot — a boolean "does at least one record match this slot" — so a slot matched by 3 actual records (e.g. two Posts + a Story on the same date) still only counted as `1`. Fixed: `Filled` (both per-platform and Overall) is now a direct count of `entries` for that client/platform — every distinct `clientSocialContent.id` counts once, never deduplicated by date/platform/feature/slot, so 3 actual records against a 15-planned slot now correctly show `3/15`, not `1/15`. `Planned` is untouched (still one per Calendar slot); Overall is the sum of each platform's own record count, so no id is ever counted twice. Actual is never capped at planned — `17/15` (and `>100%` when a percentage is shown) is expected, not a bug, and is not hidden. This is a pure counting fix inside `computeHealth()`; the Pending Queue's own per-slot fulfillment (`pending[].entry`, driving its Ready/Pending flag) still uses the original `findEntry()` boolean — that concept is deliberately unchanged, matching the two Fill Now bullets above.
  - **Main List** (`pages/social-data-entry.php`'s day board, via `boardRowsForDate()`) renders one row per **actual** `clientSocialContent` record — genuinely one row per `id`, never deduplicated by client/platform/feature/date, so three records sharing an identical tuple render as three separate rows, each with its own real entry attached. A planned slot with zero matching records still gets exactly one empty "+ Add" placeholder row; a slot matched by one or more records gets none (the records themselves are the rows). Action is now **Eye (View) only** — Edit and Delete were removed from this list for the same reason as below, and Complete Entry no longer exists at all (see above). The rail's Planned/Filled/Pending progress counts still come strictly from the calendar plan via `visibleSlots()` (a slot is "filled" if *at least one* record matches it — unaffected by extra content or by how many records share a tuple), so outstanding minimum requirements stay visible even as additional content accumulates.
  - **View (Eye icon)** reuses the same entry modal as Add, in a read-only mode: every field disabled, no Save button, no mutation. Mode state (disabled fields, Save visibility, locked badges) is set unconditionally on every `openEntryModal()` call, so nothing leaks between View → Add → Fill Now openings.
  - **Pending Queue's Content column** (before Action) summarizes actual content already entered for that row's client+date as compact feature-name badges (reusing the existing `.sov-feature-chip` style) — one badge per actual record, so a repeated feature (e.g. two Instagram Posts on the same date) renders as two separate badges, not collapsed into one — independent of which single slot that particular queue row represents, since a date's actual content is no longer assumed to be 1:1 with its planned slots. **This is deliberate, not a duplication bug**: if a client has two Pending Queue rows for the same date (e.g. one row for the planned Instagram Post slot, another for the planned Instagram Stories slot — both genuinely planned, both genuinely distinct requirements), the Content column on *both* rows shows the same full set of that date's actual records, because the column's job is "what exists for this client+date," not "what satisfies this one row's slot." Two Pending Queue rows for the same client/date/platform are correct whenever the Calendar plan genuinely planned two different features for that date — they must never be merged into one row.
  - **No enforcement of a maximum**: actual content count may equal or exceed `plannedCount`; nothing blocks saving once the planned count is reached (there never was such a guard in `saveEntry()`/`save-entry.php`).
  - Fixed alongside this: `openEntryModal()` on `pages/social-data-entry.php` previously required `state.clientId` (the global filter) to be non-empty before opening **View** (and the no-longer-exposed Edit), which incorrectly blocked opening any entry while "All Clients" was selected — View always resolves its own client from the record itself and never needed that filter; only the legacy free-form Add path genuinely does.

  <details><summary>Superseded: the original one-slot-one-entry lock (kept for history)</summary>

  Previously, clicking "+" on a planned slot opened Add Entry with Client, Date, Platform, and Feature filled in from that exact slot and **not editable**, and the Main List rendered one row per planned slot (at most one entry each) — the Calendar plan was treated as authoritative for all four dimensions, not just date/client. That strict lock has been replaced by the rule above.

  </details>

## Tables

**`socialContentProduction`** — one row per production task.

| Column | Meaning |
|---|---|
| `clientSocialContentId` | the raw entry this task produces |
| `assignedEditorId` | → `employeeusers.id`, nullable until assigned |
| `status` | see lifecycle below |
| `assignedAt`, `dueAt`, `submittedAt`, `approvedAt` | workflow timestamps |
| `createdBy` | who sent the entry to production |

**`socialContentProductionHistory`** — append-only log, one row per assignment/status change. Never overwritten, so every correction cycle stays visible. `performedBy` + `performedByType` (`'admin'` or `'employee'`) together identify who acted, since managers (`users`) and editors (`employeeusers`) are different tables.

## Status lifecycle

```
NEW ──assign──► ASSIGNED ──start──► IN_PROGRESS ──submit──► SUBMITTED ──approve──► APPROVED ──mark ready──► PRODUCTION_READY
                   ▲                     ▲                       │
                   │                     └───────start────────CORRECTION ◄──request correction──┘
                   └──reassign (manager, from ASSIGNED/IN_PROGRESS/CORRECTION)
```

| Status | Meaning |
|---|---|
| `NEW` | Sent to production, no editor yet |
| `ASSIGNED` | Editor assigned, hasn't started |
| `IN_PROGRESS` | Editor actively working |
| `SUBMITTED` | Editor submitted for review |
| `CORRECTION` | Manager sent it back with a required remark |
| `APPROVED` | Manager approved the work |
| `PRODUCTION_READY` | Terminal state for this phase |

All transitions are validated server-side in `includes/SocialContentProductionEngine.php` (a single transition table) — a request for an out-of-order status change is rejected regardless of what the frontend sends.

## Assignment flow

The manager assigns a task to an active `employeeusers` row with `designationName = 'Video Editor' OR `designationName` = 'Video Editor'. Reassignment is allowed from `ASSIGNED`, `IN_PROGRESS`, or `CORRECTION` — the old assignee isn't deleted from history, just superseded (`assigned`/`reassigned` history rows track it).

## Correction flow

`SUBMITTED → CORRECTION` **requires** a remark — the engine throws if one isn't provided. The editor then moves `CORRECTION → IN_PROGRESS → SUBMITTED` again; this loop can repeat any number of times, and every cycle's remark is preserved in `socialContentProductionHistory`, never overwritten.

## Approval flow

Only a `SUBMITTED` task can be reviewed. The manager chooses **Approve** (`→ APPROVED`) or **Request Correction** (`→ CORRECTION`, remark required). A separate **Mark Ready** action (`APPROVED → PRODUCTION_READY`) is the manager's explicit final checkpoint — approval and "ready for the next stage" are kept as two distinct steps on purpose.

## TAT (turnaround time)

`dueAt` is a concrete timestamp, settable/updatable by the manager (at assignment or independently). The UI computes overdue as `dueAt < now AND status NOT IN ('APPROVED','PRODUCTION_READY')`. `submittedAt`/`approvedAt` let the UI later show whether work finished before or after `dueAt` — no SLA engine, no multi-level rules, just the one timestamp per spec.

## Employee access — sidebar and permission

`/emp-content-production` is registered in `routesMaster` (moduleName `Employee Panel`) with a `rolePermissions` row granting the `Video Editor` designation `canView` + `canEdit` (not `canApprove`, `canAdd`, or `canDelete`) — the minimum needed to see the page and act on their own tasks, nothing more.

That permission row existed from Phase 1, but the page was unreachable through normal navigation: `includes/emp-sidebar.php` builds the menu from a hardcoded PHP array (`$menuGroups`), and `routesMaster`/`rolePermissions` only gate *whether an item already in that array is allowed to render* — they don't add new items. Phase 2 added one entry to the existing "Employee Panel" group in that array:

```php
['route' => 'emp-content-production', 'label' => 'Content Production'],
```

It goes through the exact same `$canRenderMenuRoute()` check every other item already uses (`routesMaster.isMenuVisible` **and** `hasRoutePermission($route, 'canView')`) — no bypass, no new gating mechanism. Verified by simulating an employee session directly against `hasRoutePermission()`: with the role's `canView` on, the item renders and the route loads; flipping `canView` to `0` and back confirmed the item disappears and reappears in lock-step, then the row was restored to its original value.

## Manager sidebar

`/social-content-production` is listed under the existing "Social Media" group in `includes/sidebar.php`, next to Calendar and Social Media Data, through the same hardcoded-array-plus-permission-gate mechanism `emp-sidebar.php` uses (routesMaster's `isMenuVisible` and `hasRoutePermission()` — no bypass). Admins reach it unconditionally, matching every other page in that group.

## Manager vs. employee separation

`/social-content-production` (manager) and `/emp-content-production` (employee) are separate routes, separate pages, separate sessions — `includes/auth.php` (admin, `$_SESSION['userId']`) vs `includes/emp-auth.php` (employee, `$_SESSION['candidateId']`). A Video Editor does not need, and was not given, any new access to the manager route. `pages/social-content-production.php` starts with `include auth.php`, which unconditionally requires `$_SESSION['userId']` and redirects otherwise — an employee session never has that, regardless of anything in `rolePermissions`/`userPermissionOverrides`.

**Cleanup performed in Phase 2.1:** a stray `rolePermissions` row and a stray `userPermissionOverrides` row — both granting the `Video Editor` designation/one specific employee full manager-level access to `/social-content-production` — were confirmed inert across three separate audits (the `auth.php` gate above makes them unreachable regardless of their content) and removed. Nothing else in either table was touched; row counts before/after confirmed only these two records changed.

## Editor list source

Both the manager page's "Editor" filter dropdown and the Assign Editor modal's dropdown are populated from a single fetch of `api/social-content-production/get-editors.php` (`SELECT id, fullName FROM employeeusers WHERE employmentStatus='Active' AND designationName='Video Editor'`) — one API call, two `<select>` elements filled from the same response, no duplicate query. Fixed in Phase 2.1: the modal's dropdown was previously left empty in markup and never populated in JS at all (a plain oversight, not an API or data problem — the filter dropdown, fed by the exact same endpoint, always worked), which made assignment impossible since there was nothing to select.

## Assignment / reassignment

The Assign Editor modal (`#scpAssignModal`) is reused for both first assignment and reassignment — its title and the dropdown's pre-selected value adapt to whether the task already has an editor. Both paths call the same `manage-task.php` `action:'assign'` → `SocialContentProductionEngine::assign()`, which records `assigned` or `reassigned` in history depending on whether an editor was already set — verified to preserve both events distinctly, never overwriting the prior assignment's history row.

## Manager responsibilities (`pages/social-content-production.php`, admin session)

View the production queue (filterable by status/editor/client/month/overdue), assign/reassign editors, set due dates, review submitted work (approve or request correction with a remark), mark approved work production-ready, view full history per task (its own "Production History" timeline modal, separate from the task detail modal — see above).

## Editor responsibilities (`employee/emp-content-production.php`, employee session)

View only their own assigned tasks (server-enforced — the query is hard-scoped to `assignedEditorId = $_SESSION['candidateId']`, never a client-supplied id), start work, submit for review with an optional note, view their own task history. Editors cannot approve their own work — there is no approve action anywhere on the editor-side API or page.

## Security model

- Manager pages/APIs: admin session (`includes/auth.php`, `$_SESSION['userId']`) — same trust level as the existing Data Entry/Overview pages.
- Editor pages/APIs: employee session (`includes/emp-auth.php`, `$_SESSION['candidateId']`) — every read and write is scoped to the logged-in employee's own id inside the SQL itself, re-verified on every mutation (mirrors `employeeInfoEngine.php`'s leave-cancellation ownership check).
- All state-changing endpoints require a valid CSRF token (`includes/Csrf.php`).
- All queries use prepared statements.
- No `companyId` concept introduced — client scoping follows the existing `clientId`/`clientMaster` pattern used throughout the app.

## What is intentionally NOT implemented in this phase

- No content-production fields yet: no caption editor, song library, title/description management, media upload, thumbnail selection, versioning, or hashtag generation.
- No connection to `socialPosts`, `SocialPostEngine.php`, `InstagramAutomation.php`, or `FacebookPublisher.php` — none of those files were touched, and nothing in this phase writes to `socialPosts`.
- No cron/scheduler changes.

**Historical note**: at the time this document was written, `PRODUCTION_READY` did not yet hand off to Social Media Automation — reaching that status was purely a business-state marker. **This has since been built** (Phases 4.1–4.7): a manager's explicit "Send to Automation" action on a `PRODUCTION_READY` task creates a `socialContentAutomationHandoff` row and the corresponding `socialPosts` row, via `includes/SocialAutomationHandoffEngine.php` — a separate engine, not a change to this one. See `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md` §28 and `docs/MODLUS_SYSTEM_ARCHITECTURE.md` §10.4 for the current, actual behavior; the paragraph below is left for historical context only.

## Future handoff (historical — now built, see note above)

A later phase will define a controlled, explicit action that takes a `PRODUCTION_READY` `socialContentProduction` row and creates the corresponding `socialPosts` draft (with the content-production fields — caption, media, song, etc. — added by then). That handoff is deliberately out of scope here so it can be designed with the real production fields in hand, rather than guessed at now.

## Phase 6 — operational monitoring (manager page, additive)

Added to `pages/social-content-production.php` without changing its existing layout/filters/modals:
- A server-derived **status/overdue summary** row (`api/social-content-production/get-summary.php` → `SocialContentProductionEngine::getProductionSummary()`) — counts per status plus overdue, respecting the client/platform/date-range filters already on the page. Never computed from the browser's already-filtered task list.
- An **Editor Workload** table — active Video Editors only, pending-work counts (assigned/in-progress/submitted/correction/overdue); `PRODUCTION_READY` work is deliberately excluded from workload since it's no longer pending.
- A **Platform** filter, populated from the existing `api/deliverables/get-platforms.php` (no new lookup endpoint).
- The **Automation status** (sent/pending/failed) is now also shown in the task detail modal's Production Output section, reusing the `automationStatus`/`automationSocialPostId`/`automationErrorMessage` fields `get-tasks.php` already attaches (Phase 4.5) — no new query.
- `SocialContentProductionEngine::recordExternalEvent()` (new, public) lets `SocialAutomationHandoffEngine` append one append-only history row (`automation_handoff_sent` / `automation_handoff_failed`) to a task's own history timeline when it hands off to Automation — previously that event was only visible in the site-wide activity log, not in this module's own history. This does not change the isolation direction: `SocialContentProductionEngine` still never calls into `SocialAutomationHandoffEngine` or writes to `socialPosts`; the new method only lets an external caller push one event in, exactly like appending to any other append-only log.

## Phase 7 — Social Media Setup & Calendar Generation (upstream foundation)

This phase is **upstream** of everything above: it governs what is allowed to be planned at all (`clientCalendarPlans`, which feeds `clientSocialContent`), not the production workflow itself. It replaces a hardcoded rule with database configuration, and adds a brand-new capability (calendar date generation) that did not exist before.

**Status: backend AND UI complete.** Phase 2 (backend engines/APIs/migration) and Phase 3 (Setup page, route/sidebar, Calendar Generate integration) have both been implemented and tested against the live schema — see "Phase 3 — UI & integration" near the end of this section for what Phase 3 specifically added.

### Where this sits in the overall flow

```
deliverablePlatforms / deliverableFeatures        (master data)
        ↓
clientDeliverables                                 (per-client monthly counts, e.g. "Instagram / Post = 8")
        ↓  DeliverableEngine::getClientDeliverablesGrouped()
        ↓  — filters by socialMediaFeatureConfig (see below), resolves carry-forward
        ↓
clientCalendarPlans                                (which dates — manual pick, OR generator-suggested + manually saved)
        ↓  SocialContentEngine::getPlan()
        ↓  — filters by the same socialMediaFeatureConfig rule
        ↓
clientSocialContent → Social Content Production    (everything described earlier in this document)
```

### Dynamic feature configuration (replaces the old hardcoded whitelist)

Previously, `includes/deliverableEngine.php` hardcoded which platform+feature combinations were allowed to be planned as Social Media content, via a static PHP array (`getSocialFeatureWhitelist()` / `isSocialFeatureAllowed($platformName, $featureName)`, name-matched). This has been replaced with a database-backed configuration table — same two call sites, same business result, no hardcoded platform/feature names or ids anywhere in the engine code.

**Table: `socialMediaFeatureConfig`**

| Column | Meaning |
|---|---|
| `platformId` | → `deliverablePlatforms.id` |
| `featureId` | → `deliverableFeatures.id` |
| `isEnabled` | `1` = allowed for Social Media planning, `0` = explicitly disabled |

Unique key on `(platformId, featureId)`. FKs to both master tables with `ON DELETE CASCADE`.

**Semantics: default-deny.** No row for a platformId+featureId combination means **not allowed** — identical in effect to `isEnabled = 0`. This is deliberate: adding a new row to `deliverableFeatures` later does **not** automatically make it available for Social Media planning; an administrator must explicitly enable it via the Social Media Setup page (`/social-media-setup`, built in Phase 3 — see below).

**Engine change:** `DeliverableEngine::isSocialFeatureAllowed($platformId, $featureId)` is now an **instance method** (was `static`), ID-based (was platform/feature-**name**-based). It lazily loads the enabled-combination map **once per `DeliverableEngine` instance** (i.e. once per request — `$this->enabledFeatureConfig`), via `SocialMediaSetupEngine::getEnabledFeatureConfig()`, and never re-queries per row. This avoids an N+1 query problem when `getClientDeliverablesGrouped()` checks it inside a loop.

Consumers, unchanged in what they do, updated only in how they call it:
- `DeliverableEngine::getClientDeliverablesGrouped()` — calls `$this->isSocialFeatureAllowed($row['platformId'], $row['featureId'])` directly (it already had both ids from its own join).
- `SocialContentEngine::getPlan()` — now holds its own `DeliverableEngine` instance (constructed once in `SocialContentEngine::__construct()`) and calls `$this->deliverableEngine->isSocialFeatureAllowed(...)`. Its SQL was simplified: the joins to `deliverablePlatforms`/`deliverableFeatures` that existed only to fetch names for the whitelist check were removed, since the check is ID-based and the ids were already selected.
- `api/deliverables/get-features.php` — now instantiates `DeliverableEngine` and calls `isSocialFeatureAllowed($row['platformId'], $row['id'])`; its join to `deliverablePlatforms` (previously needed only for the name-based check) was likewise removed.

**Currently seeded (the 9 combinations the old hardcoded whitelist actually matched):**

| Platform | Feature |
|---|---|
| Instagram | Post |
| Instagram | Stories |
| Facebook | Post |
| Facebook | Stories |
| YouTube | Community Post |
| YouTube | Long Videos |
| YouTube | Shorts |
| LinkedIn | Posting |
| Pinterest | Posting |

**Known data gap, carried over from before this phase (not introduced by it):** the old whitelist's business intent also included Instagram **"Reels"** and GMB **"Posting"** — but no `deliverableFeatures` row with those exact names exists in the live master data today. Both were therefore never actually reachable even before this change, and are correctly **not seeded** into `socialMediaFeatureConfig` (seeding a row that points at a nonexistent feature id isn't possible, and shouldn't be worked around by inventing one). If/when those feature rows are added to the master data, they must be explicitly enabled afterward — consistent with default-deny.

**Identity is `platformId` + `featureId`, everywhere — names are display labels only (2026-09-12 audit + cleanup).** `isSocialFeatureAllowed()` (above) already keys purely by `"$platformId_$featureId"`; a project-wide audit of the Social Media planning/data-entry/production flow (`deliverableEngine.php`, `socialContentEngine.php`, `socialMediaSetupEngine.php`, `socialCalendarPlanningEngine.php`, `calendarEngine.php`, and every relevant `api/deliverables*`/`api/social-content*`/`api/social-content-production*` endpoint) found no business matching, filtering, save/update, or plan resolution keyed on `featureName`/`platformName` in that flow — every `featureName`/`platformName` reference found is a pure display value. This means **duplicate feature labels across different platforms are valid and expected**: two rows can both be named "Post" (one under Instagram, one under Facebook) and remain fully distinct, since nothing ever looks them up by that string alone. On the strength of that, `deliverableFeatures.featureName` for Instagram/Facebook Post+Stories (+ Instagram Strategies/Reports, Facebook Reels) was normalized to drop the redundant platform prefix — "Instagram Post" → "Post", etc. (`database/migrations/2026-09-12-normalize-social-media-feature-names.sql`, scoped per-platform via JOIN so it can never rename the wrong platform's row; ids and every FK referencing `featureId` are untouched — no row was deleted or recreated). The Setup UI, Calendar, Data Entry, Overview, and Production pages now naturally read "Instagram → Post / Stories" and "Facebook → Post / Stories" instead of repeating the platform name inside the label — no frontend string-stripping was needed, since the master data itself is now the clean canonical name. The seed above, and this same table, were updated to reflect it — its `WHERE` still resolves by name (never a literal numeric id, so it stays portable across environments), but now accepts either the old or the normalized spelling per platform, so it seeds the correct 9 rows whether it runs against already-renamed master data or a full from-scratch migration replay that hasn't reached the rename step yet; this is deployment-time id resolution only, never a substitute for the runtime `platformId`+`featureId` matching described above.

### Calendar Planning Rules

**Table: `socialMediaPlanningRules`** — single global settings row (same shape as `companysettings`/`leavesettings`).

| Column | Meaning |
|---|---|
| `excludeWeekend` | `1` (default) = Saturday/Sunday are not valid planning dates. `0` = weekends are eligible. |

Holiday exclusion is **not** a setting in this table — it is an **always-on, non-configurable rule** (see below), per explicit decision: unlike weekends, there's no legitimate "allow holidays" business case, so there's nothing to toggle and one less setting to misconfigure.

**Engine:** `includes/socialMediaSetupEngine.php` (`class SocialMediaSetupEngine`) owns both tables — `getFeatureConfig()` / `saveFeatureConfig()` / `getEnabledFeatureConfig()` / `getPlanningRules()` / `savePlanningRules()`. This is the only file that queries either table directly; `DeliverableEngine` and `SocialCalendarPlanningEngine` both go through it rather than duplicating the SQL.

### Calendar date generation (new capability)

Before this phase, calendar date selection was **100% manual** — `pages/calendar.php` renders one Flatpickr `mode:'multiple'` picker per platform+feature, and a human clicks dates one at a time until `plannedCount` is met. There was no automatic generation and no load balancing anywhere in the codebase.

**Engine: `includes/socialCalendarPlanningEngine.php` (`class SocialCalendarPlanningEngine`)**. Composes `DeliverableEngine`, `CalendarEngine`, and `SocialMediaSetupEngine` — it does not duplicate any of their persistence or count-resolution logic.

**`generatePlans(array $clientIds, string $month)`** is the entry point. It is **read-only** — it never calls `CalendarEngine::saveCalendarPlan()` and never writes to `clientCalendarPlans`. It returns a preview:

```php
[
  [
    'clientId' => 12,
    'month' => '2026-09',
    'plans' => [
      [
        'platformId' => 1, 'featureId' => 3, 'requiredCount' => 8,
        'existingDates' => ['2026-09-03', '2026-09-10'],
        'generatedDates' => ['2026-09-15', '2026-09-22', '2026-09-29'],
        'finalDates' => ['2026-09-03','2026-09-10','2026-09-15','2026-09-22','2026-09-29'],
      ],
      // ...one entry per whitelisted platform+feature this client has a plannedCount for
    ],
  ],
  // ...one entry per clientId requested
]
```

#### Generate → Preview → Save (explicit design decision)

```
Social Media Setup (rules)  +  clientDeliverables (counts)
        ↓
api/deliverables/generate-calendar-plan.php  →  SocialCalendarPlanningEngine::generatePlans()
        ↓  (PREVIEW ONLY — nothing persisted)
Calendar UI fills the existing Flatpickr date pickers with the suggestion   ← Phase 3, not built yet
        ↓
Manager reviews / adjusts
        ↓
Existing "Save Plan" action  →  CalendarEngine::saveCalendarPlan()   (unchanged, untouched)
```

This was a deliberate choice over generate-and-save-immediately: it reuses the existing save path completely unmodified, keeps a human in the loop before anything is persisted, and means there is exactly one write path into `clientCalendarPlans` — the generator is purely advisory.

#### Safe regeneration (additive-only — no provenance column)

For each client+platform+feature: `targetCount = min(requiredCount, count(validDatePool))`, then `remainingNeeded = targetCount - count(existingDates)`.
- `remainingNeeded <= 0` → **no-op** for that item. `generatedDates` is empty; `finalDates` equals `existingDates` unchanged. Re-running generation on an already-fully-planned item never touches it. This also covers the already-satisfied case where `existingDates` alone already meets or exceeds the (possibly capped) target.
- `remainingNeeded > 0` → exactly that many **new** dates are chosen from `validDatePool \ existingDates` (never duplicating an existing date for that same slot) and merged with the existing ones. Existing dates are never removed or replaced.

**`requiredCount` exceeding the month's valid date pool is not a failure.** If a deliverable asks for more unique dates than the month physically has after weekend/holiday/past-date exclusion (e.g. `requiredCount=30` but only 21 dates are valid), the target is capped at the pool size — every eligible date is used exactly once, and generation stops there. No duplicate dates are manufactured for the same client/platform/feature to "reach" the original count.

**No `generatedBySystem`/`isGenerated`/provenance column was added to `clientCalendarPlans`**, and none is planned — this was an explicit decision. Safety comes entirely from the additive-only algorithm above, not from tracking where a date came from. `clientCalendarPlans.isEdited` was considered and explicitly ruled out for this purpose: it means "editable" (the toggle surfaced on Social Media Data Entry's Editable filter, phase implemented earlier), **not** manual-vs-generated provenance, and its meaning was not changed. Generated dates are saved with `isEdited='yes'`, identical to a normal manual save.

#### Even monthly distribution + load balancing

**This replaced the original "repeatedly pick the globally least-loaded date, ties broken by earliest date" strategy**, which in practice degenerated into picking the month's earliest N valid dates in a row whenever load was uniform (the common case for a slot with no other planning yet) — e.g. a 15-count deliverable would consume dates 1–20ish and leave the rest of the month with nothing planned. Still **deterministic, no randomness**; the same database state and inputs always produce the same output.

1. Build the valid date pool for the month (weekends + holidays + past dates removed — see below).
2. Build one `loadMap`: for **every** client/platform/feature already in `clientCalendarPlans` for the target month (via `CalendarEngine::getMonthWorkload($month)`), count how many are already planned on each date. This is a single global signal — it is **not** scoped to one client, one platform, or one feature; the goal is overall workload per date, not per-dimension isolation.
3. `clientIds` are sorted for deterministic processing order. For each client's each platform+feature needing `remainingNeeded` new dates: build `candidatePool = validDatePool \ existingDates` (already date-ascending), then call `pickEvenlySpreadDates($candidatePool, $remainingNeeded, $loadMap, $existingDates)`.
4. `pickEvenlySpreadDates()` splits `candidatePool` into `remainingNeeded` contiguous, roughly-equal, date-ordered buckets spanning the *entire* pool (bucket `i` covers indices `[floor(i·n/k), floor((i+1)·n/k))` of an `n`-date pool for `k` needed dates) — one pick per bucket guarantees the picks are spread start-to-end across the month. **Within each bucket**, the winner is no longer simply "least-loaded, ties to earliest" — it is now, in order: (a) the date with the **largest day-gap** to this slot's nearest already-selected date (existing dates, plus dates already generated earlier in the same call); (b) **lower shared `loadMap` load**, as the tie-break when spacing is equal — this is also the *entire* comparison for a slot's very first pick, since nothing has been selected yet, identical to the original least-loaded-wins behavior; (c) earliest date in the bucket as the final, deterministic tie-break. This is what eliminates avoidable clusters like `02,03,04` when a bucket actually offers more than one candidate — a single-candidate bucket still has no real choice, so consecutive dates remain expected and correct once the month is dense enough to require them (e.g. `requiredCount` close to the valid pool size). If `remainingNeeded >= count(candidatePool)`, every candidate is simply taken (no bucketing needed — there's no choice left to make).
5. Because `loadMap` is shared and mutated **within the same `generatePlans()` call** (and within one slot's own multi-bucket pick), a second client — or the next slot — processed in the same batch sees the freshly-generated load already reflected. This is what prevents "Client A gets days 1–5, Client B gets days 6–10" clustering, while the bucketing + spacing pick is what prevents both "all N dates land in the first half of the month" and "unnecessary 2–3 day clusters inside an otherwise well-spread result".

Verified via `ReflectionMethod` against the actual private `pickEvenlySpreadDates()`, `nearestGapDays()`, and `buildValidDatePool()` — even spread, the pool-exhaustion ("take everything") branch, spacing-aware selection (farthest-from-existing wins when load is equal; lower load wins when spacing is tied), `loadMap` mutation, and determinism were all confirmed directly, plus full `generatePlans()` integration runs against a sandbox month (see Testing below). End-to-end multi-*real*-client generation still could not be exercised in this environment (the local dev database has exactly one row in `clientMaster`); the algorithm itself was verified in isolation (Reflection) instead of skipped, same as before.

#### Weekend and holiday exclusion

Both live inside `SocialCalendarPlanningEngine::buildValidDatePool()` — never in frontend JavaScript.

- **Weekend**: excluded only if `socialMediaPlanningRules.excludeWeekend = 1` (the default). ISO weekday 6 (Sat) or 7 (Sun).
- **Holiday**: **always** excluded, regardless of any setting. Source: `eventholidaymaster WHERE eventType='holiday' AND status='active'`. `status='inactive'` rows are correctly ignored. **Ranges are honored**: `eventEndDate` may be `NULL` (single-day) or a later date (multi-day) — the effective exclusion window is `eventDate <= targetDate <= COALESCE(eventEndDate, eventDate)`. Holiday ranges are fetched once per `generatePlans()` call per month (one query, not one per day).
- **Past dates**: **always** excluded (`dateStr < today`) — found and fixed during Phase 3 integration, not part of the original Phase 2 spec. The existing manual Flatpickr picker on `pages/calendar.php` already enforces `minDate: new Date()`; without this, generating for the current month partway through could suggest already-passed dates that the picker would silently refuse when the frontend tried to apply them, desyncing the API's response from what actually got applied. This is a `buildValidDatePool()`-only change — no change to the load-balancing algorithm itself.

### API endpoints (new)

| Endpoint | Method | Purpose | CSRF |
|---|---|---|---|
| `api/social-media-setup/get-config.php` | GET | Returns `featureConfig` (every active platform/feature with current `isEnabled`) + `planningRules` | n/a (read) |
| `api/social-media-setup/save-feature-config.php` | POST | Enable/disable one or more platform+feature combinations (`{items:[{platformId,featureId,isEnabled}]}`) | Required |
| `api/social-media-setup/save-planning-rules.php` | POST | Update `excludeWeekend` (`{excludeWeekend: 0\|1}`) | Required |
| `api/deliverables/generate-calendar-plan.php` | POST | Preview-only calendar generation. Accepts `{clientId, month}` (single) or `{clientIds:[...], month}` (batch). Validates auth, CSRF, month format, and that every `clientId` exists in `clientMaster`. **Never writes** to `clientCalendarPlans`. | Required |

All four follow the existing pattern: `includes/db.php` + `includes/auth.php` (`$_SESSION['userId']` required) + `includes/Csrf.php`'s `requireValidCsrfToken()` for the three POST endpoints, `{success, message|data}` JSON responses.

### Database tables (new, this phase)

Migration: `database/migrations/2026-09-11-social-media-setup.sql`. Both tables `ENGINE=InnoDB`, `utf8mb4`/`utf8mb4_unicode_ci`, matching every sibling table. The seed data resolves platform/feature ids by **name** via a `SELECT` subquery, not literal ids — safe to run unmodified on any environment regardless of that environment's actual auto-increment values.

- `socialMediaFeatureConfig` (`platformId`, `featureId`, `isEnabled`) — see above.
- `socialMediaPlanningRules` (`excludeWeekend`) — see above.

One small, additive change to an existing table's engine (no schema change): `CalendarEngine::getSavedPlans()` was made **public** (was `private`) so `SocialCalendarPlanningEngine` can read existing dates directly, and a new read-only method `CalendarEngine::getMonthWorkload($month)` was added (flattens every client's `selectedDates` for a month into a per-date count — the load map's data source). Neither changes `saveCalendarPlan()` or any existing behavior.

### Testing performed (Phase 2)

No browser session was available; all testing was done by exercising the actual engine classes directly against the live local database (not a mock), using a sandbox month (`2099-01`) and the one real client row available, with full cleanup verified independently afterward. 39 checks, all passing:

- Default-deny: the 9 seeded combinations are enabled; a never-whitelisted feature (Instagram "Scripts") and a not-yet-seedable one (GMB "Photo Upload") are correctly denied; an out-of-range feature id doesn't error.
- Disabling `Instagram Post` live removed it from both `getClientDeliverablesGrouped()` and `SocialContentEngine::getPlan()` immediately (fresh engine instance = fresh per-request cache); re-enabling restored both.
- Generation: fresh slot generates exactly `requiredCount` dates; a partially-planned slot generates only the remaining gap without touching existing dates or duplicating them; a fully-planned slot generates nothing (no-op); `generatePlans()` confirmed to write zero rows to `clientCalendarPlans`; feeding its `finalDates` output into the existing `CalendarEngine::saveCalendarPlan()` persisted correctly.
- Weekend exclusion on vs. off, isolated via a pool-exhaustion technique (requiring more dates than exist in the month, so `generatedDates` length equals the exact valid-date-pool size) — confirmed the off-pool is strictly larger and specifically includes weekend dates the on-pool excludes.
- Holidays: single-day and multi-day (`eventEndDate` range) holidays both excluded; an `inactive` holiday row correctly not excluded.
- Load-balancing primitive (least-loaded selection, earliest-date tie-break, shared-load spreading across two simulated streams) verified via `ReflectionMethod` against the real private method.
- All sandbox data, holiday test rows, and feature-config toggles independently confirmed removed/restored after the run.

**Not tested**: the four new API endpoint files themselves over real HTTP (only the engine methods they call) — there is no UI yet to drive them through a browser, and simulating multipart/JSON POST + session + CSRF from CLI was judged lower-value than the engine-level coverage above given Phase 3 will exercise them directly once built. Genuine multi-*real*-client generation (as opposed to the Reflection-verified algorithm) — this dev database has only one `clientMaster` row.

### Phase 3 — UI & integration (complete)

Phase 3 is additive UI/wiring on top of the Phase 2 backend above — no engine, table, or API contract was redesigned. The one backend change made during this phase (past-date exclusion in `buildValidDatePool()`) is documented in "Weekend and holiday exclusion" above.

#### Social Media Setup UI

| | |
|---|---|
| Route | `/social-media-setup` |
| Page | `pages/social-media-setup.php` |
| `routesMaster` | `moduleName='Social Media'`, `layoutType='admin'`, `sortOrder=213` (continuing `/calendar`=0, `/social-data-entry`=210, `/social-overview`=211, `/social-content-production`=212). Registered via `database/migrations/2026-09-12-social-media-setup-route.sql` (idempotent `INSERT ... WHERE NOT EXISTS`, same pattern as `2026-08-31-social-data-entry-overview-routes.sql`). |
| Sidebar | `includes/sidebar.php`'s existing `'Setup'` category array — added as `['route' => 'social-media-setup', 'label' => 'Social Media Setup']`, alongside every other `*-setup` page (Leave Setup, Attendance Setup, etc.), per this document's own development rule. No new category was created. |
| Access | Admin session only (`includes/auth.php`). `hasRoutePermission()` returns `true` unconditionally for `getLoggedInUserType() === 'admin'` (`includes/permission-helper.php`) — same unconditional-admin-access pattern already documented above for `/social-content-production`. No `rolePermissions` row was needed. |
| Visual pattern | Modeled on `pages/leave-setup.php`: page header/breadcrumb, `card custom-card` sections, inline `<script>` (matching this module's other pages — `calendar.php`, `social-data-entry.php` — rather than `leave-setup.php`'s external-JS-file convention, since this page is functionally part of the Social Media module). |

**Section 1 — Social Media Feature Configuration.** Renders every active platform (icon + name) with every active feature under it, each with an enable/disable switch — entirely from `api/social-media-setup/get-config.php`'s `featureConfig` response (itself built from `deliverablePlatforms`/`deliverableFeatures`, `LEFT JOIN`ed to `socialMediaFeatureConfig`). No platform/feature id or name is hardcoded anywhere in the page. One "Save Feature Configuration" button collects every rendered toggle's current state (`{platformId, featureId, isEnabled}`) and POSTs the full set to `save-feature-config.php` in one request — not one request per toggle.

Each platform renders as its own compact card in a responsive Bootstrap grid (`col-xl-4 col-lg-4 col-md-6 col-12` — 3-up desktop, 2-up tablet, 1-up mobile) rather than a single full-width stacked list, to keep the page from growing very tall as more platforms/features are added. Card height is natural per platform (not forced equal), and every platform/feature the API returns is still rendered regardless of its current enabled state — this was a pure layout/CSS pass (`pages/social-media-setup.php` only) with no change to the data flow, toggle behavior, IDs, data attributes, or save payload described above.

**Section 2 — Calendar Planning Rules.** A Yes/No radio for "Exclude Weekend (Sat/Sun)", read from and saved to `planningRules.excludeWeekend` via `get-config.php`/`save-planning-rules.php`. Deliberately has no holiday toggle — a static note in the section explains holiday exclusion is always-on and not configurable here, matching the backend.

Both sections use the project's global `window.showToast(type, message)` notification convention (matching `social-data-entry.php`/`leave-setup.php`) and send `CSRF_TOKEN` (the existing global JS constant from `includes/header.php`) as an `X-CSRF-Token` header on both save calls.

#### Calendar Generate → Preview → Save integration

`pages/calendar.php`'s existing Calendar Planner modal gained one button, **"Generate Dates"**, in the modal footer next to the existing **"Save Plan"** button (`[Generate Dates] [Save Plan] [Close]`) — the manual Flatpickr `mode:'multiple'` workflow is completely unchanged and still fully usable on its own.

```
Click "Generate Dates"
        ↓
POST api/deliverables/generate-calendar-plan.php   { clientId: currentClientId, month: monthSelector.val() }
        ↓  (existing API/engine from Phase 2, untouched — no second generate API was created)
SocialCalendarPlanningEngine::generatePlans()  →  preview data (nothing persisted)
        ↓
For each {platformId, featureId, existingDates, generatedDates, finalDates} in the response:
    find the matching .feature-row[data-platform-id][data-feature-id] already on the page
    (identity is platformId+featureId, never the display name — "Posting" safely
    resolves to the right row even though it exists under 3 different platforms)
        ↓
    input._flatpickr.setDate(finalDates, true)
        ↓  (triggers the row's OWN existing onChange handler — no duplicate
        ↓   date-capsule rendering logic was added; the existing handler
        ↓   already redraws .module-selected-dates)
User reviews the now-updated pickers, can add/remove/edit dates manually exactly as before
        ↓
Click existing "Save Plan"  (completely unmodified)
        ↓
CalendarEngine::saveCalendarPlan()   ← the only write path into clientCalendarPlans
```

Key integration details:
- **Never saves.** The click handler only calls `generate-calendar-plan.php` and mutates Flatpickr instances client-side; it never calls `save-calendar-plan.php` and there is no direct `INSERT`/write from this JavaScript.
- **Additive, not destructive, in the UI too.** `setDate(finalDates, true)` is called with `finalDates` (existing ∪ generated), never with `generatedDates` alone — so a row with dates a human already picked keeps them, with the new suggestions merged in. A row where `generatedDates` is empty (already fully planned) is left completely untouched — its Flatpickr instance isn't even touched.
- **Feedback, not silent success.** If every row's `generatedDates` was empty, the user sees "All required dates are already planned." (`info`, not an error). If `requiredCount` exceeds the month's valid date pool, the backend's `targetCount = min(requiredCount, validDatePool count)` capping means every valid date got used — the frontend reports this as `success`, "All N possible dates have been selected for this month," never as a warning/shortfall (a `plan.finalDates.length < plan.requiredCount` check, since the backend guarantees `finalDates.length` always equals the capped target — it is never an unexpected shortfall). Otherwise a `success` toast states how many new dates were generated and that Save Plan is still required.
- **CSRF.** Sends `CSRF_TOKEN` as `X-CSRF-Token`, same as the Setup page — this is the first AJAX call on `pages/calendar.php` to do so (the pre-existing `save-calendar-plan.php` call does not send/require it; that pre-existing gap was left as-is, not fixed, since it's out of this phase's scope).
- **Weekend/holiday/load-balancing** are not touched, referenced, or duplicated in this JavaScript at all — it only ever reads the engine's `finalDates` and displays them.

#### Testing performed (Phase 3)

No browser session was available (same constraint as Phase 2). Both new/changed PHP surfaces were verified directly:
- `php -l` and a `node --check` pass on the extracted inline `<script>` blocks of both `pages/social-media-setup.php` and the modified `pages/calendar.php`.
- **Every one of the four API endpoints was exercised as a real, isolated HTTP-shaped request** (not just the engine methods behind them, unlike Phase 2) — a small CLI harness set a genuine session (`session_start()` before writing `$_SESSION`, matching how `includes/auth.php`/`Csrf.php` actually behave), a mocked `php://input`, and ran each endpoint as its own fresh PHP process per call (required because these files, like every API in this app, `exit()` on their failure paths, which would otherwise kill a multi-call-in-one-process harness):
  - `get-config.php` returns the full dynamic feature grid + planning rules with a real session.
  - `save-feature-config.php`: disabling `Instagram Post` live, confirmed via `get-config.php`, then re-enabled and confirmed restored; rejected outright when the `X-CSRF-Token` header is missing.
  - `save-planning-rules.php`: toggled `excludeWeekend` 1→0→1 (restored), confirmed at each step via `get-config.php`; CSRF-rejected without the header.
  - `generate-calendar-plan.php`: real client (`id=1`), real current month (`2026-09`) — confirmed a fully-planned feature (`requiredCount=15`, 15 existing dates) correctly returns zero generated dates (no-op); confirmed `clientCalendarPlans` row count for that month is identical before and after the call (preview-only, verified against real production-shaped data, not just the Phase 2 sandbox month); a temporary `clientDeliverables` row (YouTube Long Videos, `plannedCount=5`, no existing plan) confirmed **the past-date fix specifically**: all 5 generated dates were `>= 2026-09-11` (today), none from the already-passed Sept 1–10 — then the temporary row was deleted; unknown `clientId` and invalid `month` are both rejected with clear messages; CSRF-rejected without the header; the batch (`clientIds: [...]`) request shape returns the documented `{month, clients: [...]}` shape correctly.
  - All test mutations (feature toggle, planning rule, temporary deliverable row) were confirmed restored/removed; final state independently re-queried: exactly 9 enabled feature combinations, `excludeWeekend=1`, zero leftover rows, `/social-media-setup` registered exactly once in `routesMaster`.

**Not tested**: actual browser interaction (clicking the real "Generate Dates"/toggle switches, watching Flatpickr visually update) — no authenticated browser session was available. The `input._flatpickr.setDate(finalDates, true)` call and the row-matching selector were verified by code inspection against `calendar.php`'s existing `buildPlatformsHTML()`/`initFlatpickr()` markup (confirmed the exact attribute names — `data-platform-id`/`data-feature-id` on `.feature-row`, `data-platform`/`data-feature-id` on `.flatpickr-input`), not by observing them run in a live page.

#### Phase 3 status

| Item | Status |
|---|---|
| Social Media Setup UI (both sections) | Complete |
| Route (`routesMaster`) | Complete |
| Sidebar entry | Complete |
| Feature configuration (dynamic, default-deny, save) | Complete |
| Weekend configuration (read/save) | Complete |
| Calendar Generate button | Complete |
| Preview (no persistence) | Complete |
| Existing Save integration (unmodified) | Complete |
| Browser/visual verification | **Not done** — no authenticated browser session available |
| Multi-*real*-client generation end-to-end | Still only verified via the Reflection-based algorithm test from Phase 2 — this dev database has one `clientMaster` row |

### Calendar generation update — even monthly distribution + capped target (2026-09-12)

Two behavior changes to `SocialCalendarPlanningEngine::generatePlans()`, scoped entirely to that one file — no schema, API, UI, or `CalendarEngine::saveCalendarPlan()` change:

1. **Even distribution.** Replaced "repeatedly pick the globally least-loaded date, ties broken by earliest date" (`pickLeastLoadedDate()`) with `pickEvenlySpreadDates()` — see "Even monthly distribution + load balancing" above. The old strategy clustered all generated dates at the start of the month whenever load was uniform; the new one guarantees one pick per contiguous, date-ordered bucket spanning the whole valid pool, so picks are structurally spread start-to-end, with least-loaded-within-bucket still providing cross-client load awareness.
2. **Capped target.** `targetCount = min(requiredCount, count(validDatePool))` — a deliverable asking for more unique dates than the month has valid dates is no longer a shortfall to report, it's satisfied by using every valid date exactly once. See "Safe regeneration" above for the updated formula.

Generate → Preview → Save, additive-only regeneration, existing-date preservation, weekend/holiday/past-date exclusion, and the shared cross-client `loadMap` are all unchanged in behavior — only *which* dates get picked to fill a gap changed.

**Testing performed**: no browser session available (same constraint as Phases 2–3). 41 checks, all passing, against the real local database:
- Reflection-based unit tests of `pickEvenlySpreadDates()` directly: correct count, no duplicates, not the first-N-consecutive dates, spread confirmed (early first pick, late last pick) for both a 15-of-22 and an 8-of-22 case; the pool-exhaustion ("take everything") branch for 30-requested-of-21 and 21-requested-of-21; determinism (identical inputs → identical output); within-bucket least-loaded selection with a hand-crafted `loadMap` (mirrors this doc's existing precedent of Reflection-testing load balancing directly, since local `clientMaster` still has only one row); `loadMap` mutation.
- Full `generatePlans()` integration runs against a sandbox month (`2099-01`) with a real `clientDeliverables` row, covering: 15-of-22 spread (not the first 15 valid dates, zero DB writes performed); 72-required-of-22-valid capped to exactly 22 with no duplicates; required-equals-valid-count selecting the complete pool; a small count (5) spread across more than 14 days of the month; existing 5 dates preserved verbatim while only the remaining 10 were generated (and the underlying `clientCalendarPlans` row confirmed still holding only the original 5 afterward — preview-only, unchanged); already-satisfied target (5 required, 5 existing) correctly a no-op.
- Weekend exclusion: toggled `excludeWeekend` 1→0→1 (restored), confirmed the off-pool is strictly larger and specifically includes Saturday/Sunday dates the on-pool excludes.
- Holidays: a temporary single-day active holiday and a temporary 3-day active range were both fully excluded; a temporary inactive holiday was correctly *not* excluded. All temporary rows deleted afterward.
- Past-date exclusion re-verified against the real current month (`2026-09`) with a temporary deliverable row — every generated date was `>= today`.
- All temporary rows (`clientDeliverables`, `clientCalendarPlans`, `eventholidaymaster`) independently confirmed removed and `socialMediaPlanningRules.excludeWeekend` confirmed restored to its original value after the run.

**Not tested**: multi-*real*-client cross-client spreading end-to-end (same pre-existing local-database limitation as Phase 2 — one `clientMaster` row); browser/visual confirmation that `pages/calendar.php`'s Flatpickr pickers render the new spread correctly (that integration was Phase 3's, and is untouched by this change — the API response shape is identical, so no frontend change was needed or made).

### Calendar generation refinement — spacing-aware bucket selection (2026-09-12)

Polish on top of the even-distribution update above, scoped to `pickEvenlySpreadDates()` only — the bucket-based foundation, `targetCount` capping, additive regeneration, weekend/holiday/past-date rules, cross-client `loadMap`, and API/frontend are all unchanged.

**What changed**: within a bucket, the previous "least-loaded, ties to earliest" pick could still repeatedly favor the earliest date in consecutive small buckets (e.g. `02,03,04`) whenever load was uniform. The winner is now chosen by day-gap to this slot's nearest already-selected date first (existing dates + dates already generated earlier in the same call), then load as the tie-break when spacing is equal, then earliest date as the final tie-break. A slot's first pick — nothing selected yet — is unaffected: spacing has nothing to compare against, so it's decided by load exactly as before. A bucket with only one candidate still has no choice to make, so consecutive dates remain correct once the month is dense enough to force them (`requiredCount` near the valid pool size).

**Testing performed**: existing 41-check suite re-run unchanged as regression — all still pass (weekend/holiday/past-date exclusion, capped target, existing-date preservation, no-op, preview-only, determinism are all untouched code paths). 10 new checks added: a real 15-of-22 sandbox-month run produced zero avoidable 3-in-a-row consecutive-day clusters (previously the exact reported symptom) while still spanning day ~4 through day ~26; existing dates (`05`, `12`) correctly kept new picks off their immediate neighbors where alternatives existed; a 20-of-21 high-density case still produced exactly 20 dates with adjacency allowed; a symmetric-spacing tie correctly fell through to the lower-loaded date; an equal-load wide bucket correctly picked the farthest date from an existing one rather than the nearest/earliest; determinism reconfirmed with existing dates in play; preview-only (zero `clientCalendarPlans` writes) reconfirmed. All temporary rows cleaned up and independently verified removed afterward.

## Development rule (project-wide, not specific to this module)

`/social-content-production` and `/emp-content-production` both had correct `routesMaster`/permission records for one to two phases before either was reachable through actual navigation — `includes/sidebar.php` and `includes/emp-sidebar.php` build their menus from a hardcoded PHP array, and a `routesMaster` row alone does not add an entry to it.

**Whenever a new page or route is introduced, its sidebar/menu entry must be added in the same change** — not deferred to a later cleanup pass. Checklist: (1) `routesMaster` row exists, (2) the correct sidebar file's menu-group array has an entry pointing at it, (3) that entry uses the existing permission-aware rendering path (no bypass), (4) the route is reachable and correctly denied/hidden per the normal permission rules.

## Production Queue refinement — Review status (2026-09-15)

Scoped entirely to `pages/social-content-production.php`'s Production Queue table and its detail/history modals, plus one additive column and one new engine method backing it. No table redesign, no new tables — one new `manage-task.php` action reusing the existing mutation pattern every other action already follows.

### Column arrangement (current)

`Client → Content → Editor → Status → Date Time → Updated Work → Review → Action`. The first four columns are unchanged. "Due" was replaced by **"Date Time"** (Assigned + Due shown vertically in one cell, from the existing `assignedAt`/`dueAt` columns — no new data, `dueCell()`'s overdue styling reused unchanged). "Last Remark" was dropped as a dedicated column (still fully visible via the unchanged Production History modal). **"Updated Work"** and **"Review"** are new.

### Updated Work column → Production Output modal

Replaces the old "Review" button + `#scpReviewModal` (approve / request-correction) entirely — removed outright, not left dead in the DOM. The Updated Work column shows an icon only once `submissionUrl` is set (i.e. the editor has actually submitted something); clicking it opens the new **Production Output** modal (`#scpOutputModal`), which shows the existing submission (Drive link / uploaded media — `renderOutput()` reused completely unchanged) plus a **Status** dropdown.

### Review column + `reviewStatus`

New column `socialContentProduction.reviewStatus` (`VARCHAR(40) NOT NULL DEFAULT 'Open'`, migration `database/migrations/2026-09-15-social-content-production-review-status.sql`) — a manager-facing approval disposition, tracked as its own dimension separate from the existing `status` lifecycle (`NEW`..`PRODUCTION_READY`), not a new value grafted into that enum. The Review column shows this as a badge on every row; the Production Output modal's Status dropdown is what sets it, via the new `manage-task.php` `action:'review_status'` → `SocialContentProductionEngine::updateReviewStatus()`.

Six values: `Open` (default), `Approved By Varun`, `Approved By Client`, `Approval Pending From Client`, `Not Approved`, `Not For Use`. Only settable once a task has actually reached `SUBMITTED` or later — `updateReviewStatus()` throws "This task has no submitted output to review yet." otherwise.

**Bridged into the existing status lifecycle, not duplicated.** `Approved By Varun`/`Approved By Client` both mean "content is ready for the next step" per spec, so — only while the task is still exactly `SUBMITTED` — `updateReviewStatus()` internally calls the same `transition()` the old `approve()` path used, moving `status: SUBMITTED → APPROVED` and setting `approvedAt`, so **Mark Ready** and **Send to Automation** (both unchanged, both gated on `status`) keep working exactly as before. Selecting either value again afterward (e.g. Varun approves, then the client also approves) only updates `reviewStatus` — the bridge is skipped once `status` is no longer `SUBMITTED`, so it never attempts an illegal re-transition. `Not Approved` mirrors the old request-correction decision the same way (`SUBMITTED → CORRECTION`), so the editor's existing `CORRECTION → IN_PROGRESS → SUBMITTED` resubmission loop is completely unaffected. `Approval Pending From Client` and `Not For Use` don't correspond to any existing status, so they only ever update `reviewStatus` — no new value was added to the main status enum for either.

**Remark, required only for Not Approved / Not For Use.** Enforced server-side (`updateReviewStatus()` throws "A remark is required for this status." regardless of what the client sends), not just conditionally shown in the UI. No new `reviewRemark` column: exactly like `submissionType`/`submissionUrl`'s own established pattern (see "Submission — two columns, nothing duplicated" above), the remark is written only to the existing append-only `socialContentProductionHistory` (`action: 'review_status_updated'`; `oldStatus`/`newStatus` deliberately left `NULL` for this action, since it's a different dimension from the main status transition and shouldn't be conflated with it in the timeline). The modal's remark field always starts blank — there is no "current remark" to restore, matching the old Review modal's own behavior.

**Known, accepted simplification:** `reviewStatus` is not reset to `Open` on resubmission after a correction — a manager re-reviewing a resubmitted task will still see the previous `Not Approved` badge until they pick a new value themselves. Left as-is per "do not over-engineer"; revisit only if this proves confusing in practice.

### View modal simplification

`#scpDetailModal` ("View") now renders **Content Brief only**. "Task Overview" and "Production Output" were both removed — `renderOverview()`/`overviewCell()` were deleted outright as now-unused; `renderOutput()` itself was kept exactly as-is, since the new Production Output modal reuses it unchanged. History remains in its own separate "Production History" modal/timeline, completely untouched by this change.

### Testing performed

No browser session available (same constraint as every earlier phase in this document). Engine-level, against the real local database, using temporary `clientSocialContent`/`socialContentProduction` rows created specifically for this test and fully removed afterward (cascade-deleted via the existing FK chain, confirmed via re-query) — the pre-existing real production rows were independently confirmed unchanged before and after:
- Invalid `reviewStatus` rejected; `Not Approved`/`Not For Use` rejected with no remark (server-side, not just UI).
- `Approval Pending From Client` updates `reviewStatus` only, leaves `status` untouched.
- `Approved By Varun` bridges `SUBMITTED → APPROVED` and sets `approvedAt`; re-selecting `Approved By Client` afterward updates `reviewStatus` only and does not attempt an illegal re-transition (no exception).
- Reviewing a task with nothing submitted yet (`status='NEW'`) is correctly rejected.
- `Not Approved` bridges `SUBMITTED → CORRECTION`; the correction remark is the most recent history entry (matches the pre-existing `lastRemark` contract used elsewhere); the editor's existing `start()`/`submitProduction()` resubmission loop was run immediately afterward against the same task and confirmed to still work unaffected, with `reviewStatus` correctly left at `Not Approved` (not silently reset — see the accepted simplification above).
- History rows verified directly: one `review_status_updated` entry per change with `oldStatus`/`newStatus` left null; the bridged transition still logs its own pre-existing `approved`/`correction_requested` action exactly as before, unchanged.
- `php -l` clean on every touched PHP file; `node --check` clean on the page's extracted inline `<script>`.

**Not tested**: the `manage-task.php` `action:'review_status'` HTTP endpoint itself over a real request (session + CSRF) — verified by code review and `php -l` only; this mirrors this same document's precedent for endpoint-wiring left untested-over-HTTP in earlier phases. Browser/visual confirmation of the new column layout and the Production Output modal.

## Production Queue UI refinement — column reshuffle (2026-09-15, later the same day)

Pure UI/presentation changes on top of the Review-status work above — no schema, engine, or new API (the one action this relies on, `manage-task.php`'s `action:'review_status'`, already existed).

- **Assign/Reassign moved into the Editor column.** The Action column's Assign/Reassign buttons were removed; `editorCell()` now shows an "Assign Editor" button for `NEW` tasks, the assigned editor's first name as a button for `ASSIGNED`/`IN_PROGRESS`/`CORRECTION` (reassignable — the exact same status set the old Action-column buttons gated on), or the editor's name as plain text for any other status (not reassignable, same as before). `#scpAssignModal` itself is reused unchanged; only its heading text changed, to "Assign Editor" / "Re-Assign Editor". Reassigning (only when the task already had an editor before this open) now asks for confirmation — "Are you sure you want to re-assign this task to {name}?" — before submitting; a fresh first-time assignment still submits immediately, unchanged.
- **Status / Review / automation-handoff indicators are now outline buttons, not badges** — `outlineBadge()`, same color mapping as before (`STATUS_COLOR`/`REVIEW_STATUS_COLOR` untouched), styled after `pages/oboarding-lead.php`'s agreement-status column: a non-clickable `<span class="btn btn-outline-{color} btn-sm">` (`pointer-events:none`, so it never dims like a real `disabled` button would) instead of `.badge.bg-{color}`. Scoped to the Production Queue table only — the Production Output modal's own badges (submission-type pill, automation-status line) were deliberately left as `.badge`, out of this change's scope.
- **Content column** now shows `Platform - Posting Type - Date` on the first line and the content title alone on the second — same `platformName`/`postType`/`contentDate`/`title` data the engine already returned, presentation only. A small local `classifyPostingType()` (Post/Story only — a narrower, display-only duplicate of `classifyPostType()` documented above) reads the same `postType` column; when it's empty (most entries created via `social-data-entry.php`, which has never collected `postType`), that segment is simply omitted, never guessed. `featureName` ("Plan") no longer appears in this column — it was dropped in favor of Posting Type per this change's explicit spec, not merged elsewhere.
- **New "View Content" column**, placed before Action, holding the eye icon that used to live in Action (`.scp-view`, same click handler, same `#scpDetailModal` Content Brief content — nothing about what it opens changed). Action now holds only Mark Ready / Send to Automation / History — History/Timeline itself untouched.

**Testing**: no browser session available (same constraint as every earlier phase). Verified by code review, `php -l`, and `node --check`, cross-checked against real production data: the 4 pre-existing rows (ids 2, 5, 74, 113 — covering `NEW` with no editor, `SUBMITTED`/`PRODUCTION_READY` with an editor, `postType` both present and `NULL`) confirmed `editorCell()`/`contentCell()` render sensibly against real values, not just hypothetical ones; a temporary `ASSIGNED` row (created and fully cascade-deleted afterward, confirmed via re-query) confirmed the join that feeds the "editor first-name button" branch — `editorName` populated correctly (`"Vinay Vishwakarma"` → button label `"Vinay"`). The reassign confirmation dialog's own click-through (SweetAlert2 confirm/cancel) was reviewed in code but not exercised in a live browser.

## Production Queue UI correction pass, from a real screenshot (2026-09-15, later still)

The outline-button/status-gated Editor column above didn't hold up once actually seen rendered — corrected the same day, same file, no schema/engine/API change:

- **Status/Review/automation-handoff indicators reverted back to compact `.badge` pills** — the outline-button treatment (previous entry) was too large/prominent next to real action buttons in the same row. `outlineBadge()` was replaced with `compactBadge()` (`.badge.bg-{color}`, same color maps unchanged); the automation-handoff indicator specifically went back to its original `-transparent` badge variants (`bg-success-transparent`/etc.), restoring its pre-outline-button look exactly.
- **Editor column simplified — no more status gating.** `editorCell()` no longer special-cases `ASSIGNED`/`IN_PROGRESS`/`CORRECTION` vs. other statuses: it shows "Assign Editor" whenever `editorName` is empty, or the first-name button whenever one is assigned, full stop. A click against a task the engine's `ASSIGNABLE_STATUSES` no longer allows reassigning still opens the modal, same as always — submitting simply surfaces the engine's existing rejection message through the normal `manageTask()` error toast, not a new check. No behavior change server-side.
- **Content column date drops the year** — `fmtDate()` (this page's only caller) now formats `day + short-month` only (`"03 Sep"`, not `"03 Sep 2026"`); a production-planning date within the current cycle never needs the year to stay unambiguous.
- **Column order**: `Client → Content → View Content → Editor → Status → Date Time → Updated Work → Review → Action` — View Content moved from just-before-Action to right after Content. No cell function changed, only where each `<td>`/`<th>` sits in the row/header markup.

**Testing**: no browser session available. `php -l` and `node --check` clean; re-verified `editorCell()`/`contentCell()` against the same real rows (2, 5, 74, 113) to confirm the simplified branching and the no-year date still render correctly against real data.

## Add Entry modal — Posting Type inherited from Plan, no longer picked (2026-09-15)

The Fill Now / Add-Edit entry modal (`#sovEntryModal`, `pages/social-overview.php`) asked the user to pick both **Posting Type** (`Post`/`Story`, `#sovFormPostType`) and **Content Format** — a duplicate question, since Posting Type is already implied by the record's own locked **Plan** (shown read-only in the scope strip right above the form; see "Terminology: 'Plan' vs 'Posting Type'/'Content Format'" above). The Posting Type `<select>` is removed; only Content Format remains, and its option list is now derived from Plan instead of from a sibling dropdown.

**No schema/API/storage change.** `clientSocialContent.postType` still stores exactly the Content Format value it always did (`Image`/`Reel`/`Carousel`/`Video`/`Image Story`/`Video Story`, or a legacy flat value) — `saveEntry()`'s payload still reads `postType: $('#sovFormPostFormat').val()`, byte-for-byte unchanged from before this pass. `classifyPostType()`, `POST_TYPE_FORMATS`, and `LEGACY_POST_TYPE_MAP` (all `pages/social-overview.php`) are unchanged in shape — only `POST_TYPE_FORMATS.Story`'s display **labels** changed (see below), never `classifyPostType()`'s logic or the stored `value`s.

**New: `postingTypeFromPlan(planName)`.** A small local helper (`pages/social-overview.php`, next to `classifyPostType()`) mapping the locked Plan's display name to `'Post'`/`'Story'`: any name containing "Story"/"Stories" (case-insensitive — covers Instagram/Facebook's "Stories" feature) maps to `Story`; everything else (`Post`, and every other seeded plan this modal can be opened against — YouTube's `Community Post`/`Long Videos`/`Shorts`, LinkedIn/Pinterest's `Posting`) falls back to `Post` — the same default `classifyPostType()` already uses for any format it doesn't specifically recognize, so an unusual plan never breaks the form, it just gets the Post-family format list.

**`openEntryModal()` change.** Previously: `classifyPostType(ctx.postType)` supplied *both* which format list to show (`classified.postType`) *and* which option to preselect (`classified.format`), and `#sovFormPostType` was set to match. Now: `postingTypeFromPlan(featureById(ctx.platformId, ctx.featureId).name)` decides which format list to show (from Plan, not from whatever was previously saved), while `classifyPostType(ctx.postType)` is still called — purely to recover `.format` so an existing entry's previously-saved Content Format is preselected in whichever list Plan now dictates. A brand-new entry (nothing saved yet, `classifyPostType('')` returns `null`) simply opens with the format `<select>` on "Select" — never guessed.

**Content Format labels under a Story plan are now shown in full** (`"Image Story"`/`"Video Story"`, not the previously-shortened `"Image"`/`"Video"`) — that shortening existed only because a sibling "Posting Type: Story" field sat right next to it and made repeating "Story" redundant; with that field gone, the format needs to read unambiguously on its own. Stored `value`s are unchanged, so this is a label-only change with no effect on `classifyPostType()`, the Pending Queue's `contentChipsFor()` chip sub-line (which reads `classified.format` directly, never `POST_TYPE_FORMATS`' label), or any other reader of `postType`.

**Validation.** `'Posting type is required.'` was removed from `validateForm()` along with the field it validated; `'Content format is required.'` is unchanged. The shared `.removeClass('is-invalid')` selector lists (in both `openEntryModal()` and `validateForm()`) no longer mention `#sovFormPostType`, since the element doesn't exist anymore.

**Layout.** The freed grid space was given to Content Format (`col-md-3` → `col-md-4`) and Title (`col-md-6` → `col-md-8`) in the same row — a two-field row instead of three, no other row/field touched.

**Deliberately unchanged:** `pages/social-data-entry.php`'s own read-only View Entry modal still shows Posting Type *and* Content Format as two display cells (it never collected either — see "Where `postType` is captured" above) — that page has no form field to remove, and wasn't touched. The Pending Queue's per-chip classification sub-line, `pages/social-content-production.php`'s Content Brief (still shows the raw stored value under its own older "Post Type" label), and every other reader of `clientSocialContent.postType` are unaffected — they all read the same one column, exactly as before.

### Testing performed

No browser session available. Verified with a standalone Node script replicating `POST_TYPE_FORMATS`/`classifyPostType`/`postingTypeFromPlan` exactly as written in the page (17 checks, all passing): Plan `"Post"` yields the `Image`/`Reel`/`Carousel`/`Video` list; Plan `"Story"`/`"Stories"` yields `Image Story`/`Video Story` with full labels; unrelated plans (`Community Post`, `Shorts`, `Posting`) fall back to the Post list; `classifyPostType()` still classifies every current and legacy stored value identically to before; an empty/`null` stored value still classifies to `null` (never guessed); edit-preselection was simulated for both a Story-plan entry with a saved `Video Story` value and a Post-plan entry with a saved `Carousel` value, both correctly preselecting. Cross-checked against the real database: confirmed Instagram/Facebook's actual `featureName` values are exactly `"Post"`/`"Stories"` (so `postingTypeFromPlan()` matches real data, not just a hypothetical), and re-ran the same simulation against the 3 real `clientSocialContent` rows that have a non-empty `postType` today — the one real `Post`/`Image` entry preselects correctly; two rows holding the legacy flat value `"Post"` (which was never classifiable to a specific format even before this change) correctly fail to preselect anything, identical to their pre-existing behavior — not a regression introduced here. `php -l` and `node --check` clean on `pages/social-overview.php`.

**Not tested**: live browser interaction (opening the modal, watching the dropdown populate, submitting a save) — no authenticated browser session was available.

## Other Graphic Content — a second raw-material source for Production (2026-09-16)

A new module for miscellaneous content requests (Blog, Bio, Caption, YouTube Description, Short/Long Video and Reels, Notes, Others) that don't fit `clientSocialContent`'s platform/feature/postType shape, feeding the **same** Production Queue every Social Content entry already goes through. This is deliberately **not** a second production system: no second engine, no second assignment/status/review/history mechanism — one additive migration, one new raw-material table + engine (mirroring `clientSocialContent`/`SocialContentEngine`'s own role, not their columns), and source-aware read queries in the existing `SocialContentProductionEngine`.

### New page: `pages/other-graphic-content.php`

Route `/other-graphic-content` (`routesMaster`, `moduleName='Social Media'`, admin-only via the same unconditional-admin pattern `/social-content-production`/`/social-media-setup` already use — no `rolePermissions` row needed), sidebar entry added to the existing "Social Media" group in `includes/sidebar.php`, directly after "Social Media Data" — both per this document's own standing rule ("Whenever a new page or route is introduced, its sidebar/menu entry must be added in the same change").

Listing table (`#, Date, Edit Type, Client, Deadline, User, Action`) + Add button + one Add/Edit modal (`#ogcEntryModal`) collecting: Select Date, Deadline Date, Client (populated from the existing `api/client/getClients.php` — no new client field, no new client lookup), Edit Type (fixed 8-option list: Blog / Short Video and Reels / Long Video and Reels / Bio / Caption / Notes / Youtube Description / Others), Title, Hook, Reference, Notes, Content Description. Filter bar: Date Range + Client, mirroring `social-content-production.php`'s own filter-bar pattern. Edit and Delete actions per row — Delete reuses the identical "blocked once real production work exists beyond NEW" guard `social-data-entry.php`'s Clear Data already established.

### New table: `otherGraphicContent`

Migration `database/migrations/2026-09-16-other-graphic-content.sql`. Columns: `id`, `clientId` (FK → `clientMaster`, `ON DELETE CASCADE`, matching `clientSocialContent.clientId`'s own FK exactly), `contentDate`, `deadlineDate`, `editType`, `title`, `hook`, `reference`, `notes`, `contentDescription`, `status` (`draft`/`ready` — same two-value lifecycle `clientSocialContent.status` uses for this purpose, not its full `draft/ready/scheduled/posted` set, since Other Graphic Content has no publish-scheduling concept), `createdBy`/`updatedBy`/`createdAt`/`updatedAt`. No platform, no feature, no calendar-plan concept — this source has none.

### New engine: `includes/otherGraphicContentEngine.php`

Mirrors `SocialContentEngine`'s `getEntries()`/`saveEntry()`/`completeEntry()`/`deleteEntry()` pattern line-for-line (same validation shape, same "`status` can only reach `ready` via `completeEntry()`, idempotently" guard, same bind-by-reference `run()` helper) minus the calendar-plan methods, which don't apply. `EDIT_TYPES` is a public const, the single source of truth the page's own `<select>` and server-side validation both read from — no duplicate list to drift out of sync.

### Handoff: `api/other-graphic-content/save-entry.php`

Identical transaction shape to `api/social-content/save-entry.php`: `saveEntry()` → `completeEntry()` → (idempotency check via `getTaskByOtherContentId()`) → `SocialContentProductionEngine::createTaskForOther()`, all in one `mysqli_begin_transaction`/`commit`. `api/other-graphic-content/delete-entry.php` mirrors `api/social-content/delete-entry.php`'s production-dependency guard identically, just checked via `getTaskByOtherContentId()` instead of `getTaskByContentId()`.

### `socialContentProduction` widened, not redesigned

Same migration adds two columns and relaxes one constraint — no table rebuild, no column removed or renamed:
- `clientSocialContentId` becomes **nullable** (was `NOT NULL`). InnoDB unique keys already treat any number of `NULL`s as non-conflicting, so this is safe with zero data migration — every existing row keeps its non-null value untouched.
- `otherGraphicContentId INT NULL`, its own `UNIQUE KEY` (mirroring `clientSocialContentId`'s — one task per Other Graphic Content entry, same "already sent to production" clash rule `createTaskForOther()` enforces, same as `createTask()` does for the other source) and its own FK (`ON DELETE CASCADE` to `otherGraphicContent`).
- `sourceType VARCHAR(10) NOT NULL DEFAULT 'social'` — every pre-existing row defaulted to `'social'` automatically on migration, verified identical before/after.

**Invariant enforced in the engine, not a DB `CHECK` constraint** (kept additive/simple per this phase's own scope): a task's `clientSocialContentId`/`otherGraphicContentId` pair is always exactly one-set-one-null, because `createTask()` (unchanged) only ever sets the first, and the new `createTaskForOther()` only ever sets the second — no code path sets both or neither.

### `SocialContentProductionEngine` — new methods, source-aware reads

**New, additive:** `createTaskForOther($otherGraphicContentId, $deadlineDate, ...)` mirrors `createTask()` exactly (same clash check, same `created`/`NEW` history event) with one deliberate difference — `dueAt` is the user's own **Deadline Date** directly (at 17:00, matching the existing TAT convention's time-of-day, not its day-before-content-date formula), since Other Graphic Content has no calendar-plan date to compute a TAT from. `getTaskByOtherContentId()` mirrors `getTaskByContentId()`.

**Unchanged, because they never needed to change:** `assign()`, `start()`, `submitProduction()`, `review()`/`updateReviewStatus()`, `markReady()`, `getHistory()`, `transition()`, `logHistory()` all operate purely on `socialContentProduction.id`/`status` — none of them read `clientSocialContentId` or care which source a task came from. This is the whole reason "reuse existing architecture" was achievable here at all: assignment, status lifecycle, review, submission, and history needed **zero** code changes to support a second source. Verified directly — the full `assign → start → submitProduction → updateReviewStatus (bridges to APPROVED) → markReady` sequence was run against a real Other Graphic Content task end-to-end with no special-casing anywhere in that path.

**Changed, because they have to resolve from either source:** `getTask()` and `listTasks()`'s SQL now `LEFT JOIN` both `clientSocialContent` **and** `otherGraphicContent` (previously an `INNER JOIN` to `clientSocialContent` alone, which would have silently excluded every Other Graphic Content row). Shared concepts are resolved with `COALESCE(c.column, o.column)` — `clientId`, `contentDate`, `title` — and `clientMaster`/`leads` are joined once, on `COALESCE(c.clientId, o.clientId)`, rather than twice. Source-specific fields stay separately named, never conflated: Social Content's `rawContent`/`caption`/`songUrl`/`ideaReference`/`referenceLink`/`socialMediaHandle`/`postType`/`contentRemarks` (all `NULL` for an Other Graphic Content row) sit alongside Other Graphic Content's own `editType`/`hook`/`otherReference`/`otherNotes`/`otherContentDescription`/`deadlineDate` (all `NULL` for a Social Content row) — including `contentDescription` (Social) vs `otherContentDescription` (Other), deliberately given different keys since both source tables have a same-named column and mysqli would otherwise silently let one clobber the other in the result array. `platformId`/`featureId`/`platformName`/`featureName` stay Social-Content-only (`NULL` for Other Graphic Content — there is no platform/feature concept on that side). New `sourceType`/`otherGraphicContentId` fields are attached to every task row so callers (the UI, `emp-content-production.php`) can branch on source without guessing from which fields happen to be populated.

`listTasks()`'s filters: `clientId` now matches `COALESCE(c.clientId, o.clientId)` (so filtering by a client with only Other Graphic Content work still finds it); `platformId` stays `c.platformId` unchanged — deliberately: an active platform filter naturally excludes every Other Graphic Content row, which is correct, since they have no platform to filter by. A new optional `source` filter (`'social'`/`'other'`) was added alongside, matching the same `p.sourceType = ?` the summary breakdown already groups by — surfaced in the UI as a new "Source" dropdown in the filter bar (`#scpSource`, `pages/social-content-production.php`), passed through `api/social-content-production/get-tasks.php`.

### Production Summary — a separate row, Social Content counts untouched

`getProductionSummary()` now runs its status-count and overdue queries once each (`GROUP BY p.sourceType, p.status` / `GROUP BY p.sourceType`, both `LEFT JOIN`ing both source tables) and splits the results in PHP into the **existing** `statusCounts`/`overdueCount`/`unassignedCount` (still scoped to `sourceType='social'` — identical in meaning and value to every count before this phase existed, since every pre-existing row already was `'social'`) plus **new**, additively-returned `otherStatusCounts`/`otherOverdueCount`/`otherUnassignedCount` for `sourceType='other'`. `pages/social-content-production.php` renders the new counts in their own row, titled "Other Graphic Content", directly below the existing Social Content summary cards — same card markup, same 8-tile shape (New/Assigned/In Progress/Submitted/Correction/Approved/Ready/Overdue), never merged into the row above it.

### Editor Workload — combined counts, unchanged table structure

The workload subquery's `INNER JOIN clientSocialContent c` became `LEFT JOIN` (plus a new `LEFT JOIN otherGraphicContent o`, present only so the shared `$whereSql`'s `COALESCE`/platform conditions still resolve — neither table's own columns are read by this query). This was the one place an `INNER JOIN` would have silently **broken** Other Graphic Content entirely: without this change, any editor whose only current work was an Other Graphic Content task would have been dropped from the workload table outright (the `employeeusers LEFT JOIN (...)` still finds the editor row, but the inner subquery would have produced zero matching rows for them). The counts themselves are **not** split by source — per this phase's own spec ("include the additional source counts", not a second workload table) — an editor's Assigned/In Progress/Submitted/Correction/Overdue numbers now simply reflect both sources combined, exactly as before for any editor who only ever had Social Content work.

### Content Brief and Content column — source-aware rendering, both manager and employee pages

`pages/social-content-production.php`'s `contentCell()` (Production Queue's Content column) and `renderBrief()` (View Content / Content Brief) both branch on `task.sourceType === 'other'`: the Content column shows `Other Graphic Content - {editType} - {date}` / title (same two-line hierarchy Social Content uses, substituting Edit Type for Platform/Posting Type); the brief shows Title/Edit Type/Hook/Content Description/Reference/Notes/Deadline instead of Social Content's Raw Content/Caption/Song/etc. `employee/emp-content-production.php` received the same two branches (its own near-duplicate `renderBrief()`, plus its inline Content-column template), so an assigned editor sees the same meaningful information for either source — nothing else on that page was touched. Every other production-side surface (Updated Work, Review status, History/Timeline, Mark Ready, Send to Automation, Assign/Reassign) needed **no** source-specific rendering at all, since none of them display source-specific content — they already worked unchanged.

### Testing performed

No browser session available. Extensive engine-level testing against the real local database, using temporary rows created and fully cascade-deleted afterward (verified via re-query each time) — pre-existing real rows (4 Social Content production tasks) independently confirmed unchanged throughout every run:
- Baseline: existing Social Content `getTask()`/`getProductionSummary()` values captured before any Other Graphic Content data existed, then re-verified byte-identical after (`statusCounts` sum unchanged at 4; `otherStatusCounts` sum correctly 0 beforehand).
- `OtherGraphicContentEngine`: entry creation/read/complete (idempotent)/listing all verified; invalid `editType` and missing title+description both correctly rejected server-side.
- `createTaskForOther()`: task created with `sourceType='other'`, `otherGraphicContentId` set and `clientSocialContentId` null, `dueAt` exactly matching the given deadline at 17:00; every Other-specific field (`editType`/`hook`/`otherReference`/`otherNotes`/`otherContentDescription`/`deadlineDate`) populated correctly via the new `COALESCE`/`LEFT JOIN` query; `platformName`/`featureName`/every Social-only field correctly `NULL`; a second `createTaskForOther()` call against the same entry correctly rejected (clash check); `getTaskByOtherContentId()` resolves correctly.
- `listTasks()` filters: the new task appears in an unfiltered list; a `clientId` filter (now `COALESCE`-based) correctly includes it; a `platformId` filter correctly **excludes** it (no platform concept); a `source='other'` filter returns exactly it.
- **Full workflow reuse, zero special-casing**: `assign() → start() → submitProduction() → updateReviewStatus() (bridging SUBMITTED→APPROVED) → markReady()` run end-to-end against the Other Graphic Content task, every step succeeding exactly as it does for Social Content; 7-entry history timeline confirmed correct and complete.
- Summary: `otherStatusCounts.PRODUCTION_READY` correctly became 1 after the task reached that status, while `statusCounts` (Social Content) stayed byte-identical to the pre-test baseline throughout.
- Editor workload: confirmed the assigned editor's row reflects **combined** counts (a pre-existing real Social Content `SUBMITTED` task plus zero contribution from the now-`PRODUCTION_READY` Other Graphic Content task, since `PRODUCTION_READY` is excluded from every workload count for both sources identically).
- Full save-entry.php-equivalent transaction (`saveEntry()` → `completeEntry()` → idempotency-checked `createTaskForOther()`) exercised directly, matching the real API file's code path exactly.
- `php -l` clean on every touched/new PHP file (`SocialContentProductionEngine.php`, `otherGraphicContentEngine.php`, `pages/other-graphic-content.php`, `pages/social-content-production.php`, `employee/emp-content-production.php`, all three new `api/other-graphic-content/*.php` files, `api/social-content-production/get-tasks.php`, `includes/sidebar.php`); `node --check` clean on every touched page's extracted inline `<script>`.

**Not tested**: the new API endpoints themselves over a real HTTP request (session + CSRF) — verified by code review, `php -l`, and direct engine-level exercise of the exact same transaction shape only, consistent with this document's established precedent for endpoint-wiring left untested-over-HTTP elsewhere. Browser/visual confirmation of the new page, the new Production Queue columns/summary row, and the Content Brief branching — no authenticated browser session was available.

## `employee/emp-content-production.php` — Production Queue list alignment (2026-09-16)

Presentation-only pass bringing the employee table's column layout, Content column format, and badge styling into parity with `pages/social-content-production.php`'s own Production Queue (no Editor column, since this page is always "my own tasks"): `Client → Content → View Content → Status → Date Time → Updated Work → Review → Action`. Content now uses the same `Platform - Posting Type - Date` / title hierarchy (a duplicated, display-only `classifyPostingType()` + `fmtShortDate()`, same convention as every other per-page duplication in this document); View Content moved into its own column (reuses the exact same, unchanged `.ecp-view` handler and Detail modal); Status/Review render as compact badges (Review is new here — read-only, since editors have no approve/review action anywhere on this page or its APIs, it just mirrors whatever the manager set); Updated Work is a new column, shown only once `submissionUrl` exists, also reusing `.ecp-view` rather than introducing a second modal. Start/Resume/Submit/History and every API call are untouched. No engine, API, or migration file was touched for this pass (verified via file modification timestamps).

## `employee/emp-content-board.php` — card/Kanban production board (2026-09-16)

A second, additive presentation of the same "my assigned tasks" data — does not replace `employee/emp-content-production.php`'s table (both link to each other, "Board View" / "Table View"), does not add or change any API, and does not touch production workflow logic anywhere. Route `/emp-content-board` (`routesMaster`, `moduleName='Employee Panel'`, same `Video Editor` `canView`+`canEdit` `rolePermissions` grant `/emp-content-production` already has — migration `database/migrations/2026-09-16-emp-content-board-route.sql`), sidebar entry added to the existing "Employee Panel" group in `includes/emp-sidebar.php`, directly after "Content Production."

**Data and actions are 100% reused, not reimplemented.** The board calls the exact same three endpoints the table page does, with identical payloads: `api/social-content-production/emp-get-tasks.php` (list, with no filters — the board's purpose is to show everything currently assigned across every status at once, grouped visually instead of filtered), `emp-update-task.php` (`action:'start'`), and `emp-submit-production.php` (the same multipart submission modal, same drive-link/file-upload branching). View Content and History reuse the identical Task Overview / Content Brief / Production Output / timeline rendering functions the table page already has (copied, not shared — this codebase's established per-page-duplication convention, same as `classifyPostType()`/`esc()`/etc. elsewhere in this document), scoped under an `ecb-` id/class prefix so nothing collides with the table page (a separate document entirely, so collision was never actually possible — the prefix is purely for this file's own internal consistency).

**Board layout.** Seven always-visible sections (`New / Assigned / In Progress / Submitted / Correction / Approved / Ready`, matching the existing status lifecycle exactly — no new status invented), each a responsive CSS grid (`repeat(auto-fill, minmax(300px, 1fr))` — 2–3 cards per row on desktop, 1 on mobile) rather than side-scrolling Kanban columns, since "2–3 cards per row" only makes sense within a section, not across seven narrow lanes. Each card shows: content type/source (`Instagram · Post` or `Other Graphic Content · Blog`, source-aware exactly like the Production Queue's own Content column), title, client name, Assigned + Due (same `dueAt`/overdue logic as every other page), Status + Review badges, editor name, and up to three actions (the same conditional primary action the table page shows — Start/Resume/Submit/nothing, by status — plus small View/History icon buttons). A client-side search box filters by title/client name only; no new query, pure `Array.filter()` over the already-fetched task list.

**Design.** Glassmorphism cards (`backdrop-filter: blur(20px) saturate(1.6)`, translucent background, soft multi-layer shadows, 20px radius) over a very subtle radial-gradient atmosphere wash, built entirely from this theme's own CSS custom properties (`--primary-rgb`, `--custom-white`, `--default-border`, `--text-muted`, `--body-bg-rgb`) rather than a parallel color system — light/dark mode is inherited automatically, with explicit `html[data-theme-mode="dark"]` overrides only where dark mode needs different shadow/opacity values (per this app's own existing theme-switcher attribute, not a `prefers-color-scheme` media query, so it stays in sync with the app's own toggle). Status accent colors and badge colors are the same `STATUS_COLOR`/`REVIEW_STATUS_COLOR` maps every other production page already uses.

**Drag-and-drop — presentation-only by construction, not by convention.** Each status column gets its own independent SortableJS instance (`Sortable.create()`, this theme's already-bundled `dist/assets/libs/sortablejs/Sortable.min.js` — no new dependency) with no shared `group` option, which makes cross-column dragging structurally impossible rather than merely discouraged: a card can only ever be reordered within the column it's already in. `onEnd` never calls an API — it only writes the resulting id order to `localStorage` (key `ecbCardOrder_{employeeId}`, the employee's own candidateId exposed as a small JS constant, so a shared browser never mixes one editor's ordering preference into another's). This satisfies "no database changes unless absolutely required for ordering" literally — a personal display-order preference doesn't need server durability, so none was added — while still fulfilling the "no automatic status change" requirement absolutely (there is no code path from a drag event to any status-changing endpoint).

### Testing performed

No browser session available. `php -l` clean on all three touched/new PHP files (`emp-content-board.php`, `emp-content-production.php`, `emp-sidebar.php`); the inline `<script>` was extracted and `node --check`ed clean (caught and fixed one real bug in the process: an unquoted PHP-echoed integer, `const EMP_ID = <?php ... ?>;`, is not valid standalone JS before PHP evaluates it — changed to the same `Number("<?php ... ?>")` quoting convention `CSRF_TOKEN` already uses elsewhere in this codebase, so static tooling can parse it and it still evaluates correctly at runtime). Route and `rolePermissions` grant verified present in the database after applying the migration. Verified the exact data shape the board consumes against a real, currently-assigned editor's real tasks (`listForEditor(11)`) — `sourceType`, `assignedAt`, `reviewStatus`, `platformName`, `postType`, `dueAt`, `clientCode`, and `history` all resolved correctly, confirming the card renderer and Content/Date-Time cells have every field they expect. Confirmed via Bootstrap's own bundled CSS that `--bs-danger-rgb` and the `.text-bg-{color}` utility classes used for section icons/overdue-date coloring actually exist in the bundled stylesheet, and that `Sortable.min.js` exists at the referenced asset path.

**Not tested**: live browser rendering (the glassmorphism visual treatment, drag-and-drop interaction itself, responsive breakpoints) and a real HTTP round-trip through the submission/start actions from this specific page — no browser session was available; the underlying endpoints themselves were already extensively tested from the table page's own equivalent calls in earlier phases of this document.

## Final Architecture — Planning through Publishing (2026-09-18)

The complete, current chain, each stage owned by exactly one engine/table, no stage duplicated:

```
Planning (social-media-setup)
      -> Data Entry (clientSocialContent, socialContentEngine.php)
      -> Production (socialContentProduction, SocialContentProductionEngine.php)
      -> Approval (status + reviewStatus, same engine)
      -> Caption Area (socialContentCaption, socialContentCaptionEngine.php
         -> includes/AI/{CaptionGeneratorFactory,AnthropicCaptionGenerator,OllamaCaptionGenerator}.php)
      -> Automation Queue (pages/social-automation.php, read view only)
      -> Send to Automation (socialContentAutomationHandoff + socialPosts,
         SocialAutomationHandoffEngine.php — the one, locked boundary)
      -> Scheduling + Publishing (cron/instagramScheduler.php,
         InstagramAutomation.php/FacebookPublisher.php, unchanged)
```

Other Content / Other Graphic Content feed the same Production Queue but stop there — neither ever reaches Caption Area or Automation (no `platformName`, which is the actual mechanism that excludes them; see §5a).

A production task becomes eligible for Automation Queue / Send to Automation only when **all** of: `sourceType='social'`, `status='PRODUCTION_READY'`, platform is Instagram/Facebook, `reviewStatus` is still `Approved By Team`/`Approved By Client`, and its `socialContentCaption.status='selected'`. All five are re-checked fresh on every eligibility check and every queue read — none is cached or trusted from an earlier point in the flow. A task that already has a handoff record stays visible in the queue regardless of its current caption/reviewStatus (so a later, unrelated status edit never hides an already-sent/scheduled/published post).

### Production-readiness audit (2026-09-18) — one real bug found and fixed

**Reproduced with real data**: `reviewStatus` remains editable by design even after a task reaches `PRODUCTION_READY` (`SocialContentProductionEngine::REVIEWABLE_STATUSES` includes it). Before this fix, changing `reviewStatus` to `Not Approved`/`Not For Use`/`Approval Pending From Client` *after* a caption was already selected had no effect on Automation eligibility — the task stayed sendable and kept appearing in the Automation Queue, contradicting what those statuses are supposed to mean. Fixed in `SocialAutomationHandoffEngine::checkEligibility()` (new `NOT_APPROVED` state, checked after `ALREADY_HANDED_OFF` so already-sent tasks are unaffected) and `listQueue()`'s membership clause — both reuse `SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES`, no new list, no new status.

Everything else audited and found already correct, no change made:
- Deleting the source Data Entry content is already blocked once its production task leaves `NEW` (`api/social-content/delete-entry.php`), so a caption/handoff can never be orphaned by a content deletion.
- Regenerating a caption after a handoff already exists correctly resets `status` to `pending` and `selectedCaption` to `NULL` — a subsequent Retry is correctly blocked (`CAPTION_NOT_SELECTED`) until the admin re-selects, so a stale caption can never silently ride along on a retry.
- Admin-only access to Caption Area/Automation Queue is enforced at the router layer (`hasRoutePermission()` — no `rolePermissions` row exists for either route, so only `authUserType='admin'` sessions pass); duplicate-prevention (`UNIQUE(productionId)` on both `socialContentCaption` and `socialContentAutomationHandoff`) and CSRF on every mutating endpoint are both intact.
- One trivial terminology mismatch fixed: the Automation Queue's status filter said "Eligible (Not Sent)" while its badge said "Eligible — Not Sent" — aligned to the same text.

### Production/deployment requirements (found during this and the prior audit)

- **Environment variables must actually reach the web server process.** This codebase's own `getenv()` calls (`MODLUS_AI_CAPTION_PROVIDER`, `MODLUS_OLLAMA_*`, `ANTHROPIC_API_KEY`, etc.) only work if the hosting environment actually injects them into the PHP process — a Windows service (e.g. WAMP's Apache) does not inherit ad-hoc OS environment variables without a service restart, and this repo has no `.env` loader. Verify with a one-line `getenv()` check after any deployment/config change, not by assumption.
- **A CA bundle must be configured for outbound HTTPS from PHP** (both cURL and Guzzle). Without one, every outbound call to Meta's Graph API or Anthropic's API fails TLS verification before the request is even sent. Both `includes/InstagramAutomation.php` (`instagramGraphApiRequest()`) and `includes/AI/AnthropicCaptionGenerator.php` now explicitly supply `composer/ca-bundle`'s system bundle rather than relying on cURL/Guzzle auto-discovery — safe on a correctly configured server too.
- **Media must be fetchable by Meta over the public internet.** Real Instagram/Facebook publishing resolves media as `BASE_URL . '/' . <relative path>` — this only works when the app is actually reachable at that public URL and the file physically exists there. A local dev copy's `uploads/` is never reachable this way; confirmed by real production task testing (media rejected by Meta as "Only photo or video can be accepted as media type" when the underlying URL 404s).
- **Configure the AI caption provider from the UI, not by editing code** at `/ai-configuration` (admin-only, Social Media sidebar) — provider/model/API key (Anthropic) or host/model (Ollama) are saved to the database (`aiCaptionSettings`, API key encrypted at rest with the existing `includes/Crypto.php`), with a "Test Connection" button that exercises the real provider. Environment variables (`MODLUS_AI_CAPTION_PROVIDER`, `ANTHROPIC_API_KEY`, `MODLUS_OLLAMA_HOST`, `MODLUS_OLLAMA_MODEL`) remain a working fallback for anything not set in the database. **Local development**: Provider = Ollama, Host = `http://localhost:11434`, Model = `llama3.2:3b` (Ollama running locally, no key). **Production (Hostinger)**: Provider = Anthropic, API Key + Model set from this page after deploy — no code or server file edit required. See `docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md` §31.
- **`MODLUS_ENCRYPTION_KEY` must be set to a real, private value on every real deployment.** It backs `ENCRYPTION_KEY` (`includes/config.php`), which is what the AI Configuration page's (and Instagram's own) encrypted secret storage actually depends on for real security. Left unset, encryption still runs, but against a hardcoded fallback value that's public in this repository's source — meaning a stored API key would not be meaningfully protected. This is the one genuinely unavoidable external/server-level secret this feature needs.
