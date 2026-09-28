# Phase 3 Handoff — Elma Real Estate CRM

> **Status 2026-09-28: Phase 3 COMPLETE.** All "Remaining" items below were finished (project pages, activity pages, legacy-drop migration `2026-09-27b`, tests, browser run). Kept for history.


Branch `crm-conversion`, nothing committed. Phase 1–2 complete (see CLAUDE.md).
Phase 3 is ~65% done. Everything lints clean (PHP + JS) and the lead flow works end to end
(add lead without Category/Plan → follow-up scheduled → visible in Follow-ups).

## Done

**Database** — `database/migrations/2026-09-27-crm-phase3-core.sql` (applied locally, idempotent):
- `projects`, `projectDocuments`, `leadSources` (9 system sources seeded)
- `leads`: + `projectId`, `sourceId`, `assignedToId`, `createdByType`, `updatedAt`; pipeline enum
  `new, contacted, interested, follow_up, site_visit, negotiation, converted, lost` (old values mapped)
- `leadFollowUps`: + `dueTime`, `remark`, `createdByType/Id`, `resolvedByType`; FK to leads
- actor type columns: `leadsActivityLogs.actorType`, `leadRemarks.createdByType`,
  `leadStatusRemarks.createdByType`, `leadDocuments.uploadedByType` (old rows = `unknown`)
- routes `/projects`, `/emp-projects`, `/emp-follow-ups`; `/lead-setup` retitled "Lead Sources";
  role grants (both sales roles: view projects, follow-ups); special action `assign_lead`
  (Sales Manager granted); gateway mappings for project + lead-source APIs

**Shared helpers**
- `includes/leadActivityLogger.php` — `getCurrentActor()` (admin/employee/system), `actorNameSql()`, logger stores `actorType`
- `includes/privateFiles.php` — generic finfo/private storage/stream (used by lead + project docs)
- `includes/leadAccess.php` — `LEAD_STATUSES`, scope now by `assignedToId`, `canAssignLeads()`,
  `getAssignableEmployees()`, project/source resolvers, `createManualFollowUp()`, `createLeadRemark()`
- `includes/projectAccess.php` — project permissions (employees: active projects only)

**Lead APIs rewritten** (`api/leads/`): addLead, updateLead (logs "Project changed from A to B"),
updateLeadStatus (remark required for Converted/Lost), saveLeadRemark (+ optional follow-up),
getLeadRemarks, getLeads, getLeadMasterData, importLeads (name/phone/email/project/source/status/
assignedTo/remark, exact matching, row errors reported), downloadLeadImportTemplate,
uploadLeadDocument, getLeadDocuments, downloadLeadDocument, getLeadFollowUps (views), updateLeadFollowUpStatus.
New: assignLead, logLeadContact (Call/WhatsApp "action opened"), getLeadSources, saveLeadSource.
Removed: getScheduledCalls, closeFollowup, saveLeadStatusRemark, saveLeadConversion, getLeadSetup, saveLeadSetup.

**Engines**: `leadFollowUpEngine.php` (views today/upcoming/overdue/completed + counts, actor-typed
completion); `leadDashboardEngine.php` (scope by assignee, sources via master).

**UI**
- `includes/lead-page.php` shared by `pages/leads.php` + `employee/emp-leads.php`
  (new form fields, Assign modal, tel:/WhatsApp buttons, Converted/Lost reason modal, export CSV/Excel/PDF)
- `dist/assets/js/lead.js` rewritten (750 lines)
- `includes/follow-up-page.php` shared by `pages/lead-follow-up-list.php` + new `employee/emp-follow-ups.php`;
  `dist/assets/js/lead-follow-up-list.js` rewritten (tabs Today/Upcoming/Overdue/Completed)
- `pages/lead-setup.php` = Lead Sources master
- Project APIs written: `api/projects/` getProjects, saveProject, setProjectStatus,
  getProjectDocuments, uploadProjectDocument, deleteProjectDocument, downloadProjectDocument

## Remaining (in order)

1. **Project pages** — `pages/projects.php` (DataTable, Add/Edit/View modal, activate/deactivate,
   documents modal with type select) + `employee/emp-projects.php` (read-only) + one JS file.
   Then re-enable the routes (temporarily **deactivated** so the menu has no broken link):
   `UPDATE routesMaster SET isActive=1 WHERE routePath IN ('/projects','/emp-projects');`
2. **Lead Activity pages** (`pages/lead-activity.php`, `employee/emp-lead-activity.php`): show actor via
   `actorType` (`actorNameSql`), new status labels, drop orgName/followUpDateTime labels; employee page
   must scope to accessible leads.
3. **Migration `2026-09-27b`** — drop legacy after final grep proves no references:
   `leads.orgName, categoryId, planId, source` (+ FKs), `leadRemarks.followUpDateTime, followUpremark`,
   tables `leadCategories, leadPlans, leadConversions`.
4. Sidebar check (admin: Projects group; employee: Follow-ups, Projects).
5. Update `sectest.sh` (scratchpad) for new model (sourceId instead of category/plan, assignedToId scope)
   and re-run; add assign/unassign, revoke `assign_lead` / `view-all-leads` tests.
6. Browser run (1440 + 390; admin/manager/executive) — harness in scratchpad `browser/` needs selector
   updates for the new modal fields.
7. Update CLAUDE.md §7 + README modules table; final report.

## Notes / decisions taken
- Source of truth for source = `leads.sourceId` → `leadSources` (old `source` text column to be dropped).
- Lead owner for access = `assignedToId`; `createdByCandidateId` is only the creating employee.
- Admin-created leads may be unassigned; employees without `assign_lead` always get their own leads.
- Converted = remark only (Modlus price/quotation conversion removed; no booking/accounting).
- Basic Setup kept in Settings (Gmail/roles/departments are needed) though not in the requested list.
- Local test accounts: admin@elma.local, ravi.exec / sana.exec (Sales Executive), meera.mgr (Sales Manager); local test data only.
