<?php
/*
|--------------------------------------------------------------------------
| Social Media — Client Health Overview
|--------------------------------------------------------------------------
|
| Backed by the same api/social-content/*.php endpoints and
| includes/socialContentEngine.php as pages/social-data-entry.php — this
| page just aggregates every client's plan/entries for a month instead of
| one client at a time.
|
| Problem this solves: pages/social-data-entry.php is a per-client editor.
| At 50+ clients x 5 platforms x 2+ features, nobody should have to open
| every client to find out who is behind. This page is the bird's-eye
| dashboard that sits in FRONT of that editor:
|
|   1. Client Health Matrix — client x platform completion heatmap.
|   2. Pending Queue        — every unfilled slot, across every client,
|                             sorted soonest-first.
|
| "Filled" here means a content record exists (data entry done), same
| definition social-data-entry.php uses — it is not about publish status.
|
*/
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";

// Loaded inside social-data-entry.php's "Add Entry" full-screen modal iframe
// (?embed=1) — skip the top navbar + sidebar, the parent page already has them.
$hideAppChrome = isset($_GET['embed']) && $_GET['embed'] === '1';

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* ==========================================================================
   Social Overview — scoped styles (sov-*). Theme tokens only.
   ========================================================================== */

.sov-filterbar label {
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.2rem;
}

.sov-filterbar .form-select,
.sov-filterbar .form-control { border-radius: 30px; }

/* Choices.js (Find Client) dropdown ships with z-index:1, which sits below
   the KPI/matrix cards that follow it in the DOM — raise it so the open
   dropdown list always renders on top of the rest of the page. */
.sov-filterbar .choices {
    position: relative;
    z-index: 3;
}
.sov-filterbar .choices.is-open {
    z-index: 1050;
}
.sov-filterbar .choices__list--dropdown {
    z-index: 1050;
}

.sov-check {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.8rem;
    color: var(--default-text-color);
    white-space: nowrap;
}

/* ---------- KPI chips ---------- */
.sov-kpis {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 0.75rem;
    margin-bottom: 1rem;
}

.sov-kpi {
    border: 1px solid var(--default-border);
    border-radius: 14px;
    padding: 0.85rem 1rem;
    background: var(--custom-white);
    cursor: pointer;
    transition: 0.15s;
}

.sov-kpi:hover { border-color: var(--primary-border); background: var(--primary005, rgba(var(--primary-rgb), 0.04)); }
.sov-kpi.active { border-color: var(--primary-color); background: var(--primary01); }

.sov-kpi .num { font-size: 1.4rem; font-weight: 700; line-height: 1.1; color: var(--default-text-color); }
.sov-kpi .lbl { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; margin-top: 2px; }
.sov-kpi.good .num { color: rgb(var(--success-rgb)); }
.sov-kpi.warn .num { color: rgb(var(--warning-rgb)); }
.sov-kpi.bad  .num { color: rgb(var(--danger-rgb)); }

@media (max-width: 991.98px) {
    .sov-kpis { grid-template-columns: repeat(2, 1fr); }
}

/* ---------- Matrix ---------- */
.sov-matrix-wrap { overflow-x: auto; }

.sov-matrix {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.82rem;
    min-width: 720px;
}

.sov-matrix thead th {
    position: sticky;
    top: 0;
    background: var(--default-background);
    border-bottom: 2px solid var(--default-border);
    padding: 0.55rem 0.7rem;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-muted);
    white-space: nowrap;
    z-index: 1;
}

.sov-matrix th.sortable { cursor: pointer; user-select: none; }
.sov-matrix th.sortable:hover { color: var(--primary-color); }
.sov-matrix th .sov-sort-icon { font-size: 0.65rem; margin-left: 3px; opacity: 0.5; }
.sov-matrix th.sort-active .sov-sort-icon { opacity: 1; color: var(--primary-color); }

.sov-matrix td {
    padding: 0.5rem 0.7rem;
    border-bottom: 1px solid var(--default-border);
    vertical-align: middle;
}

.sov-matrix tbody tr:hover { background: var(--primary005, rgba(var(--primary-rgb), 0.035)); }

.sov-client-cell {
    cursor: pointer;
    min-width: 190px;
}

.sov-client-name { font-weight: 600; color: var(--default-text-color); }
.sov-client-code { font-size: 0.7rem; color: var(--text-muted); }

.sov-cell {
    text-align: center;
    cursor: pointer;
    border-radius: 8px;
}

.sov-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    padding: 0.18rem 0.6rem;
    border-radius: 30px;
    font-size: 0.74rem;
    font-weight: 700;
    white-space: nowrap;
}

.sov-pill.good { background: rgba(var(--success-rgb), 0.13); color: rgb(var(--success-rgb)); }
.sov-pill.warn { background: rgba(var(--warning-rgb), 0.15); color: rgb(var(--warning-rgb)); }
.sov-pill.bad  { background: rgba(var(--danger-rgb), 0.12);  color: rgb(var(--danger-rgb)); }
.sov-pill.na   { background: var(--default-background); color: var(--text-muted); border: 1px dashed var(--default-border); }

.sov-overall-cell { min-width: 150px; }
.sov-overall-bar {
    margin-top: 4px;
    height: 5px;
    border-radius: 5px;
    background: var(--default-border);
    overflow: hidden;
}
.sov-overall-bar span { display: block; height: 100%; border-radius: 5px; }
.sov-overall-bar.good span { background: rgb(var(--success-rgb)); }
.sov-overall-bar.warn span { background: rgb(var(--warning-rgb)); }
.sov-overall-bar.bad span  { background: rgb(var(--danger-rgb)); }

/* ---------- scope chip above the queue ---------- */
.sov-scope-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary01);
    border: 1px solid var(--primary-border);
    color: var(--primary-color);
    font-size: 0.78rem;
    font-weight: 600;
    padding: 0.3rem 0.6rem 0.3rem 0.9rem;
    border-radius: 30px;
}

.sov-scope-chip button {
    border: none;
    background: none;
    color: inherit;
    font-size: 0.95rem;
    line-height: 1;
    cursor: pointer;
    opacity: 0.75;
}
.sov-scope-chip button:hover { opacity: 1; }

/* ---------- Queue ---------- */
.sov-queue-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.6rem;
    align-items: center;
}

.sov-queue-toolbar .form-select,
.sov-queue-toolbar .form-control {
    border-radius: 30px;
    font-size: 0.82rem;
}

.sov-queue-table-wrap { overflow-x: auto; }

.sov-queue {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 0.83rem;
    min-width: 760px;
}

.sov-queue thead th {
    padding: 0.5rem 0.7rem;
    font-size: 0.66rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--text-muted);
    border-bottom: 2px solid var(--default-border);
    white-space: nowrap;
}

.sov-queue td {
    padding: 0.55rem 0.7rem;
    border-bottom: 1px solid var(--default-border);
    vertical-align: middle;
}

.sov-queue tbody tr:hover { background: var(--primary005, rgba(var(--primary-rgb), 0.035)); }
.sov-queue tbody tr.is-overdue { background: rgba(var(--danger-rgb), 0.045); }

.sov-flag {
    display: inline-block;
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.6rem;
    border-radius: 30px;
    white-space: nowrap;
}
.sov-flag.overdue  { background: rgba(var(--danger-rgb), 0.13); color: rgb(var(--danger-rgb)); }
.sov-flag.today    { background: rgba(var(--warning-rgb), 0.16); color: rgb(var(--warning-rgb)); }
.sov-flag.upcoming { background: rgba(var(--info-rgb), 0.13); color: rgb(var(--info-rgb)); }
.sov-flag.filled   { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }

.sov-feature-chip {
    display: inline-block;
    padding: 0.1rem 0.6rem;
    border-radius: 30px;
    background: var(--primary01);
    color: var(--primary-color);
    font-size: 0.72rem;
    font-weight: 600;
    white-space: nowrap;
}

.sov-content-chip { cursor: pointer; }
.sov-content-chip:hover { background: var(--primary-color); color: #fff; }

/* Posting Type / Content Format classification line under each content chip
   in the Pending Queue's Content column -- see classifyPostType() below. */
.sov-content-item {
    display: inline-block;
    vertical-align: top;
    margin: 2px 8px 2px 0;
}

.sov-post-format {
    font-size: 0.64rem;
    color: var(--text-muted);
    margin-top: 2px;
    white-space: nowrap;
}

/* Wider Add Entry modal -- more room for the Raw Content/Content Note
   textareas and the Posting Type/Content Format/Title row, now that
   Platform/Feature/Handle no longer take up space. Bootstrap's own
   .modal-dialog responsive rules (the margin/max-width it applies below
   this breakpoint) are untouched, so this only takes effect once the
   viewport is actually wide enough. */
.sov-entry-dialog {
    max-width: 1200px;
}

.sov-icon-btn {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--default-border);
    background: var(--custom-white);
    color: var(--text-muted);
    font-size: 0.85rem;
    line-height: 1;
    transition: 0.15s;
}
.sov-icon-btn:hover { background: var(--primary01); color: var(--primary-color); border-color: var(--primary-border); }
.sov-icon-btn.danger:hover { background: rgba(var(--danger-rgb), 0.1); color: rgb(var(--danger-rgb)); border-color: rgba(var(--danger-rgb), 0.3); }

.sov-actions { display: flex; gap: 0.25rem; justify-content: flex-end; }

.sov-empty {
    text-align: center;
    padding: 2.5rem 1rem;
    color: var(--text-muted);
}
.sov-empty i { font-size: 2.4rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }

.sov-loadmore-wrap { text-align: center; padding: 0.9rem 0; }

/* entry modal scope strip */
.sov-scope-strip {
    display: flex;
    flex-wrap: wrap;
    gap: 0.4rem;
    padding: 0.6rem 0.8rem;
    border-radius: 12px;
    background: var(--default-background);
    border: 1px solid var(--default-border);
    margin-bottom: 1rem;
}
.sov-scope-strip span { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
.sov-scope-strip b { color: var(--default-text-color); }

@media (max-width: 991.98px) {
    .sov-queue thead th:nth-child(3),
    .sov-queue td:nth-child(3) { display: none; } /* hide Platform col on small screens, still in Feature chip title */
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- ============================ PAGE HEADER ============================ -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Social Media — Client Health Overview</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Overview</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="calendar" class="btn btn-light btn-sm">
                    <i class="ri-calendar-event-line me-1"></i> Calendar Planner
                </a>
                <?php if ($hideAppChrome): ?>
                <button type="button" class="btn btn-light btn-sm" id="sovEmbedCloseBtn">
                    <i class="ri-close-line me-1"></i> Close
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card sov-filterbar">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-2 col-md-4">
                        <label for="sovMonth">Month</label>
                        <select class="form-select form-select-sm" id="sovMonth"></select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="sovPlatform">Platform</label>
                        <select class="form-select form-select-sm" id="sovPlatform">
                            <option value="">All Platforms</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="sovClientSearch">Find Client</label>
                        <select class="form-select form-select-sm" id="sovClientSearch">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-6 d-flex align-items-center pt-3">
                        <label class="sov-check mb-0">
                            <input type="checkbox" class="form-check-input" id="sovAttentionOnly">
                            Only clients needing attention
                        </label>
                    </div>
                    <div class="col-xl-2 col-md-6 d-flex justify-content-md-end pt-3">
                        <button class="btn btn-light btn-sm" id="sovResetBtn">
                            <i class="ri-refresh-line me-1"></i> Reset Filters
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================ KPI CHIPS ============================ -->
        <div class="sov-kpis">
            <div class="sov-kpi" id="kpiTotal" data-kpi="">
                <div class="num" id="kpiTotalNum">0</div>
                <div class="lbl">Total Clients</div>
            </div>
            <div class="sov-kpi good" id="kpiComplete" data-kpi="complete">
                <div class="num" id="kpiCompleteNum">0</div>
                <div class="lbl">Fully Complete</div>
            </div>
            <div class="sov-kpi warn" id="kpiAttention" data-kpi="attention">
                <div class="num" id="kpiAttentionNum">0</div>
                <div class="lbl">Needs Attention</div>
            </div>
            <div class="sov-kpi bad" id="kpiCritical" data-kpi="critical">
                <div class="num" id="kpiCriticalNum">0</div>
                <div class="lbl">Critical (&lt;50%)</div>
            </div>
            <div class="sov-kpi" id="kpiNoPlan" data-kpi="noplan">
                <div class="num" id="kpiNoPlanNum">0</div>
                <div class="lbl">No Calendar Plan</div>
            </div>
        </div>

        <!-- ============================ CLIENT HEALTH MATRIX ============================ -->
        <div class="card custom-card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
                <div class="card-title mb-0 fs-14">
                    <i class="ri-grid-line me-1 text-primary"></i> Client Health Matrix
                </div>
                <span class="text-muted fs-12" id="sovMatrixHint">Click a row or a cell to filter the queue below.</span>
            </div>
            <div class="card-body">
                <div class="sov-matrix-wrap">
                    <table class="sov-matrix">
                        <thead>
                            <tr id="sovMatrixHeadRow"></tr>
                        </thead>
                        <tbody id="sovMatrixBody"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================ PENDING QUEUE ============================ -->
        <div class="card custom-card" id="sovQueueSection">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2 py-2">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <div class="card-title mb-0 fs-14">
                        <i class="ri-list-check-3 me-1 text-primary"></i> Pending Queue
                    </div>
                    <span class="badge bg-light text-default" id="sovQueueCount">0</span>
                    <span id="sovScopeChipWrap"></span>
                </div>
                <div class="sov-queue-toolbar">
                    <select class="form-select form-select-sm" id="sovQueueClient" style="min-width:170px;">
                        <option value="">All Clients</option>
                    </select>
                    <input type="text" class="form-control form-control-sm" id="sovQueueSearch"
                           placeholder="Search plan or title..." style="min-width:200px;">
                    <label class="sov-check mb-0">
                        <input type="checkbox" class="form-check-input" id="sovShowOnlyPending" checked>
                        Only pending
                    </label>
                </div>
            </div>
            <div class="card-body">
                <div class="sov-queue-table-wrap">
                    <table class="sov-queue">
                        <thead>
                            <tr>
                                <th class="sortable" id="sovSortDate">Date <i class="ri-arrow-up-line sov-sort-icon"></i></th>
                                <th>Client</th>
                                <th>Platform</th>
                                <th>Plan</th>
                                <th>Status</th>
                                <th>Content</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="sovQueueBody"></tbody>
                    </table>
                </div>
                <div class="sov-loadmore-wrap" id="sovLoadMoreWrap">
                    <button class="btn btn-outline-primary btn-sm" id="sovLoadMoreBtn">
                        <i class="ri-arrow-down-line me-1"></i> Load More
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ============================ ENTRY MODAL ============================ -->
<div class="modal fade" id="sovEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl sov-entry-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="sovEntryTitle">
                    <i class="ri-edit-box-line me-2 text-primary"></i> Add Entry
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <!-- Client/Date/Platform/Feature are all preselected from the
                     Pending Queue row (or the record being edited) and shown
                     here read-only, labelled "Plan" (calendar-planning
                     context, not content classification) -- none of them are
                     form fields anymore. See openEntryModal(). -->
                <div class="sov-scope-strip" id="sovModalScope"></div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <!-- Posting Type (Post/Story) is no longer a separate
                             field -- it's inherited from the locked Plan
                             above (see postingTypeFromPlan()), so only the
                             Content Format list it drives is shown here. -->
                        <label class="form-label" for="sovFormPostFormat">Content Format <span class="text-danger">*</span></label>
                        <select class="form-select" id="sovFormPostFormat">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="sovFormTitle">Title</label>
                        <input type="text" class="form-control" id="sovFormTitle" maxlength="120" placeholder="Enter title">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="sovFormRawContent">Raw Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="sovFormRawContent" rows="5" placeholder="Enter raw content..."></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="sovFormSong">Song URL</label>
                        <input type="text" class="form-control" id="sovFormSong" placeholder="https://example.com/song">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="sovFormReference">Reference <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="sovFormReference" placeholder="Where Did This Idea Come From?">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="sovFormDescription">Content Note</label>
                        <textarea class="form-control" id="sovFormDescription" rows="4" placeholder="Enter content notes..."></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger btn-sm me-auto d-none" id="sovClearDataBtn">
                    <i class="ri-delete-bin-line me-1"></i> Clear Data
                </button>
                <button type="button" class="btn btn-light btn-sm" id="sovCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="sovSaveBtn">
                    <i class="ri-save-line me-1"></i> Save Entry
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
/* ==========================================================================
   Real CRUD, backed by api/social-content/*.php (SocialContentEngine) —
   same backend as pages/social-data-entry.php, aggregated across every
   client at once instead of one client at a time.
   ========================================================================== */
$(function () {

    // ----------------------------------------------------------------------
    // 1. CATALOG (clients / platforms / features) — loaded once from the DB
    // ----------------------------------------------------------------------
    const CATALOG = { clients: [], platforms: [], features: {} };

    const STATUS_LABEL = { draft: 'Draft', ready: 'Ready', scheduled: 'Scheduled', posted: 'Posted' };

    function loadCatalog() {
        return $.when(
            $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }),
            $.ajax({ url: 'api/deliverables/get-platforms.php', dataType: 'json' }),
            $.ajax({ url: 'api/deliverables/get-features.php', dataType: 'json' })
        ).then(function (clientsResp, platformsResp, featuresResp) {
            const clientsRes = clientsResp[0], platformsRes = platformsResp[0], featuresRes = featuresResp[0];

            CATALOG.clients = (clientsRes.success ? clientsRes.data : [])
                .map(c => ({ id: Number(c.id), name: c.fullName, code: c.clientCode }));
            CATALOG.platforms = (platformsRes.success ? platformsRes.data : [])
                .map(p => ({ id: Number(p.id), name: p.platformName, icon: p.icon || 'ri-apps-line' }));

            CATALOG.features = {};
            (featuresRes.success ? featuresRes.data : []).forEach(f => {
                const pid = Number(f.platformId);
                (CATALOG.features[pid] = CATALOG.features[pid] || []).push({ id: Number(f.id), name: f.featureName });
            });

            if (!clientsRes.success) notify('danger', clientsRes.message || 'Failed to load clients.');
            if (!platformsRes.success) notify('danger', platformsRes.message || 'Failed to load platforms.');
            if (!featuresRes.success) notify('danger', featuresRes.message || 'Failed to load features.');
        }, function () {
            notify('danger', 'Network error while loading clients/platforms/features.');
        });
    }

    // ----------------------------------------------------------------------
    // 1b. SCOPE DATA — every client's planned slots + filled entries for the month
    // ----------------------------------------------------------------------
    let currentPlan = {};   // { 'YYYY-MM-DD': [{clientId, platformId, featureId}] }, all clients
    let entries = [];       // clientSocialContent rows, all clients, for the current month

    function loadOverviewData(month) {
        return $.when(
            $.ajax({ url: 'api/social-content/get-plan.php', data: { clientId: 0, month: month }, dataType: 'json' }),
            $.ajax({ url: 'api/social-content/get-entries.php', data: { clientId: 0, month: month }, dataType: 'json' })
        ).then(function (planResp, entriesResp) {
            const planRes = planResp[0], entriesRes = entriesResp[0];

            currentPlan = (planRes && planRes.success) ? (planRes.data || {}) : {};
            if (planRes && !planRes.success) notify('danger', planRes.message || 'Failed to load calendar plan.');

            entries = (entriesRes && entriesRes.success) ? (entriesRes.data || []) : [];
            if (entriesRes && !entriesRes.success) notify('danger', entriesRes.message || 'Failed to load entries.');
        }, function () {
            notify('danger', 'Network error while loading data.');
            currentPlan = {};
            entries = [];
        });
    }

    // ----------------------------------------------------------------------
    // 2. HELPERS
    // ----------------------------------------------------------------------
    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }

    function clientById(id) { return CATALOG.clients.find(c => c.id === Number(id)); }
    function platformById(id) { return CATALOG.platforms.find(p => p.id === Number(id)) || { name: 'Unknown', icon: 'ri-apps-line' }; }
    function featureById(platformId, featureId) {
        const list = CATALOG.features[Number(platformId)] || [];
        return list.find(f => f.id === Number(featureId)) || { name: 'Unknown' };
    }

    function findEntry(clientId, date, platformId, featureId) {
        return entries.find(e =>
            e.clientId === Number(clientId) && e.contentDate === date &&
            e.platformId === Number(platformId) && e.featureId === Number(featureId)
        );
    }

    // Compact "what's already been entered for this date" summary for the
    // Pending Queue's Content column -- every actual clientSocialContent row
    // for this client+date, independent of which specific slot a given queue
    // row represents. Calendar planning is a minimum, not a cap, so this can
    // include content beyond what was planned.
    // Each chip is tied to one exact clientSocialContent.id via data-id --
    // never matched by label/date/platform/feature, since none of that is
    // unique (two "Post" chips on the same row can be two different records).
    function contentChipsFor(clientId, date) {
        const items = entries.filter(e => e.clientId === Number(clientId) && e.contentDate === date);
        if (!items.length) return '<span class="text-muted fs-11">—</span>';
        return items.map(e => {
            const classified = classifyPostType(e.postType);
            const sub = classified
                ? `<div class="sov-post-format">Posting Type: ${esc(classified.postType)} &middot; Content Format: ${esc(classified.format)}</div>`
                : '';
            return `<span class="sov-content-item">
                <span class="sov-feature-chip sov-content-chip" data-id="${e.id}" title="Click to edit — ${esc(e.title || e.rawContent || '')}">${esc(featureById(e.platformId, e.featureId).name)}</span>
                ${sub}
            </span>`;
        }).join('');
    }

    // ------------------------------------------------------------------
    // Posting Type / Content Format classification (Post/Story -> Content
    // Format). User-facing label is "Content Format" only -- Posting Type
    // is no longer a field the user picks (2026-09-15): the Add Entry
    // modal's own Posting Type <select> was removed, since Post vs Story is
    // already known from the locked Plan context shown in the scope strip
    // (postingTypeFromPlan(), below). Internal property names (`postType`,
    // `format`) and the `classifyPostType()`/`POST_TYPE_FORMATS`
    // identifiers are unchanged, and so is the underlying
    // `clientSocialContent.postType` column -- it stays ONE column (no
    // schema change), storing the granular Content Format value (Image /
    // Reel / Carousel / Video / Image Story / Video Story); the parent
    // Posting Type (Post vs Story) shown throughout this page is always
    // derived from it here, never stored separately. Legacy flat values
    // saved before this change ('Post', 'Story', 'Reel', 'Carousel',
    // 'Video', 'Image', any case) are still classified so old entries keep
    // displaying and editing cleanly.
    // ------------------------------------------------------------------
    // { value: <what's actually stored/classified>, label: <what the select
    // shows> }. Story's options now show their full "Image Story"/"Video
    // Story" label (previously shortened to just "Image"/"Video" when a
    // separate Posting Type=Story field sat right next to this one and made
    // repeating "Story" redundant -- now that field is gone, so the format
    // needs to read unambiguously on its own).
    const POST_TYPE_FORMATS = {
        Post: [
            { value: 'Image', label: 'Image' },
            { value: 'Reel', label: 'Reel' },
            { value: 'Carousel', label: 'Carousel' },
            { value: 'Video', label: 'Video' }
        ],
        Story: [
            { value: 'Image Story', label: 'Image Story' },
            { value: 'Video Story', label: 'Video Story' }
        ]
    };

    const LEGACY_POST_TYPE_MAP = {
        'image': { postType: 'Post', format: 'Image' },
        'reel': { postType: 'Post', format: 'Reel' },
        'carousel': { postType: 'Post', format: 'Carousel' },
        'video': { postType: 'Post', format: 'Video' },
        'image story': { postType: 'Story', format: 'Image Story' },
        'video story': { postType: 'Story', format: 'Video Story' },
        // pre-hierarchy flat values -- no specific format was ever captured
        'post': { postType: 'Post', format: 'Post' },
        'story': { postType: 'Story', format: 'Story' }
    };

    // raw stored postType (current or legacy, any casing) -> { postType, format }
    function classifyPostType(raw) {
        const key = String(raw || '').trim().toLowerCase();
        if (!key) return null;
        return LEGACY_POST_TYPE_MAP[key] || { postType: 'Post', format: String(raw).trim() };
    }

    // Add Entry modal's Content Format list is now driven by the locked
    // Plan (deliverableFeatures.featureName via featureById()), not a
    // manually-picked Posting Type. Instagram/Facebook's "Stories" plan (and
    // any other feature name containing "Story"/"Stories") maps to the
    // Story format list; every other plan (Post, Community Post, Long
    // Videos, Shorts, Posting, etc.) falls back to the Post format list --
    // the same default classifyPostType() already uses for anything it
    // doesn't recognize.
    function postingTypeFromPlan(planName) {
        return /stor/i.test(String(planName || '')) ? 'Story' : 'Post';
    }

    function fillFormatOptions(postType, selectedFormat) {
        const formats = POST_TYPE_FORMATS[postType] || [];
        $('#sovFormPostFormat').html(
            '<option value="">Select</option>' +
            formats.map(f => `<option value="${esc(f.value)}" ${f.value === selectedFormat ? 'selected' : ''}>${esc(f.label)}</option>`).join('')
        );
    }

    // ------------------------------------------------------------------
    // Raw Content / Content Note — plain <textarea> fields (Quill removed).
    // rawContent/contentDescription are plain string columns, and
    // pages/social-content-production.php's Content Brief already renders
    // them as escaped plain text with \n -> <br>, so plain textarea text
    // (no HTML) is exactly what both fields need and always needed.
    // ------------------------------------------------------------------
    function getEditorText(id) {
        return $('#' + id).val().trim();
    }

    function setEditorText(id, text) {
        $('#' + id).val(text || '');
    }

    function fmtLongDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    const TODAY = new Date();
    TODAY.setHours(0, 0, 0, 0);

    function pctClass(pct) { return pct >= 90 ? 'good' : (pct >= 50 ? 'warn' : 'bad'); }

    function notify(type, message) { if (window.showToast) window.showToast(type, message); }

    function confirmDialog(opts) {
        return Swal.fire({
            title: opts.title,
            html: opts.html || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: opts.color || '#dc3545',
            reverseButtons: true
        });
    }

    // ----------------------------------------------------------------------
    // 3. STATE
    // ----------------------------------------------------------------------
    const state = {
        month: null,
        platform: '',          // global: narrows matrix columns + queue
        clientSearch: '',
        attentionOnly: false,
        kpiFilter: '',          // '', 'complete', 'attention', 'critical', 'noplan'
        matrixSortKey: 'overall',
        matrixSortDir: 'asc',   // worst-first by default — the whole point of the page
        scopeClientId: '',      // queue scope, settable from matrix click
        scopePlatformId: '',
        queueSearch: '',
        onlyPending: true,
        queuePage: 1,
        queuePageSize: 15
    };

    let entryModal = null;
    let formDirty = false;
    let activeModalContext = null; // { clientId, date, platformId, featureId, entryId }
    let sovClientChoices = null;

    // ----------------------------------------------------------------------
    // 4. BOOTSTRAP FILTER OPTIONS
    // ----------------------------------------------------------------------
    function buildMonthOptions() {
        const now = new Date();
        let html = '';
        for (let i = -2; i <= 4; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
            const val = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            const label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
            html += `<option value="${val}" ${i === 0 ? 'selected' : ''}>${label}</option>`;
        }
        return html;
    }

    // ----------------------------------------------------------------------
    // 5. COMPUTE CLIENT HEALTH (matrix rows + pending pool), for the month
    // ----------------------------------------------------------------------
    function computeHealth() {
        const rows = [];
        const pending = [];

        CATALOG.clients.forEach(client => {
            const perPlatform = {};
            CATALOG.platforms.forEach(p => { perPlatform[p.id] = { planned: 0, filled: 0 }; });

            let totalPlanned = 0;

            // PLANNED — unchanged business meaning: one per planned Calendar
            // slot. The Pending Queue's own planned-slot fulfillment (via
            // findEntry(), "does at least one record satisfy this slot")
            // is untouched here too — that stays a slot concept, not a count.
            Object.keys(currentPlan).forEach(date => {
                currentPlan[date].forEach(slot => {
                    if (slot.clientId !== client.id) return;

                    if (!perPlatform[slot.platformId]) perPlatform[slot.platformId] = { planned: 0, filled: 0 };
                    perPlatform[slot.platformId].planned++;
                    totalPlanned++;

                    const entry = findEntry(client.id, date, slot.platformId, slot.featureId);
                    pending.push({
                        clientId: client.id,
                        clientName: client.name,
                        date: date,
                        platformId: slot.platformId,
                        featureId: slot.featureId,
                        entry: entry || null
                    });
                });
            });

            // FILLED — every actual clientSocialContent record counts
            // individually by id, regardless of how many share the same
            // date/platform/feature (Calendar planning is a minimum, not a
            // uniqueness rule). Never derived from "does a slot have a
            // match" — that undercounts the moment more than one record
            // exists for the same slot. `entries` is already scoped to the
            // current month/date range and contentDate, same as before.
            let totalFilled = 0;
            entries.forEach(e => {
                if (e.clientId !== client.id) return;
                if (!perPlatform[e.platformId]) perPlatform[e.platformId] = { planned: 0, filled: 0 };
                perPlatform[e.platformId].filled++;
                totalFilled++;
            });

            rows.push({
                client: client,
                perPlatform: perPlatform,
                totalPlanned: totalPlanned,
                totalFilled: totalFilled,
                overallPct: totalPlanned ? Math.round((totalFilled / totalPlanned) * 100) : null
            });
        });

        return { rows, pending };
    }

    // ----------------------------------------------------------------------
    // 6. RENDER — KPI CHIPS
    // ----------------------------------------------------------------------
    function renderKpis(rows) {
        const withPlan = rows.filter(r => r.totalPlanned > 0);
        const complete = withPlan.filter(r => r.overallPct === 100).length;
        const critical = withPlan.filter(r => r.overallPct < 50).length;
        const attention = withPlan.filter(r => r.overallPct < 100).length;
        const noPlan = rows.length - withPlan.length;

        $('#kpiTotalNum').text(rows.length);
        $('#kpiCompleteNum').text(complete);
        $('#kpiAttentionNum').text(attention);
        $('#kpiCriticalNum').text(critical);
        $('#kpiNoPlanNum').text(noPlan);

        $('.sov-kpi').removeClass('active');
        if (state.kpiFilter) $(`.sov-kpi[data-kpi="${state.kpiFilter}"]`).addClass('active');
    }

    // ----------------------------------------------------------------------
    // 7. RENDER — MATRIX
    // ----------------------------------------------------------------------
    function visiblePlatformColumns() {
        return state.platform ? CATALOG.platforms.filter(p => String(p.id) === state.platform) : CATALOG.platforms;
    }

    function renderMatrixHead() {
        const cols = visiblePlatformColumns();
        let html = `<th class="sortable" id="sovSortClient">Client <i class="ri-arrow-up-line sov-sort-icon"></i></th>`;
        cols.forEach(p => { html += `<th class="text-center">${esc(p.name)}</th>`; });
        html += `<th class="sortable text-center" id="sovSortOverall">Overall <i class="ri-arrow-up-line sov-sort-icon"></i></th>`;
        $('#sovMatrixHeadRow').html(html);

        $('#sovSortClient').toggleClass('sort-active', state.matrixSortKey === 'client');
        $('#sovSortOverall').toggleClass('sort-active', state.matrixSortKey === 'overall');
        const icon = state.matrixSortDir === 'asc' ? 'ri-arrow-up-line' : 'ri-arrow-down-line';
        $('#sovSortClient .sov-sort-icon, #sovSortOverall .sov-sort-icon').attr('class', 'sov-sort-icon ' + icon);
    }

    function applyMatrixFilters(rows) {
        let filtered = rows;

        if (state.clientSearch) {
            filtered = filtered.filter(r => String(r.client.id) === state.clientSearch);
        }
        if (state.attentionOnly) {
            filtered = filtered.filter(r => r.overallPct === null || r.overallPct < 100);
        }
        if (state.kpiFilter === 'complete')  filtered = filtered.filter(r => r.overallPct === 100);
        if (state.kpiFilter === 'attention') filtered = filtered.filter(r => r.totalPlanned > 0 && r.overallPct < 100);
        if (state.kpiFilter === 'critical')  filtered = filtered.filter(r => r.totalPlanned > 0 && r.overallPct < 50);
        if (state.kpiFilter === 'noplan')    filtered = filtered.filter(r => r.totalPlanned === 0);

        filtered.sort((a, b) => {
            let cmp;
            if (state.matrixSortKey === 'client') {
                cmp = a.client.name.localeCompare(b.client.name);
            } else {
                const av = a.overallPct === null ? -1 : a.overallPct;
                const bv = b.overallPct === null ? -1 : b.overallPct;
                cmp = av - bv;
            }
            return state.matrixSortDir === 'asc' ? cmp : -cmp;
        });

        return filtered;
    }

    function renderMatrixBody(rows) {
        const cols = visiblePlatformColumns();
        const filtered = applyMatrixFilters(rows);
        const body = $('#sovMatrixBody');

        if (!filtered.length) {
            body.html(`
                <tr><td colspan="${cols.length + 2}">
                    <div class="sov-empty"><i class="ri-search-eye-line"></i>No clients match the current filters.</div>
                </td></tr>
            `);
            return;
        }

        let html = '';
        filtered.forEach(r => {
            html += `<tr>`;
            html += `
                <td class="sov-client-cell" data-client="${r.client.id}">
                    <div class="sov-client-name">${esc(r.client.name)}</div>
                    <div class="sov-client-code">${esc(r.client.code)}</div>
                </td>
            `;

            cols.forEach(p => {
                const stat = r.perPlatform[p.id];
                if (!stat) {
                    html += `<td class="sov-cell"><span class="sov-pill na">–</span></td>`;
                    return;
                }
                const pct = stat.planned ? Math.round((stat.filled / stat.planned) * 100) : null;
                const cls = pct === null ? 'na' : pctClass(pct);
                const label = pct === null ? 'No plan' : `${stat.filled}/${stat.planned}`;
                html += `
                    <td class="sov-cell" data-client="${r.client.id}" data-platform="${p.id}">
                        <span class="sov-pill ${cls}">${label}</span>
                    </td>
                `;
            });

            const overallCls = r.overallPct === null ? 'na' : pctClass(r.overallPct);
            const overallLabel = r.overallPct === null ? 'No calendar plan' : `${r.overallPct}% · ${r.totalFilled}/${r.totalPlanned}`;
            html += `
                <td class="sov-overall-cell" data-client="${r.client.id}">
                    <span class="sov-pill ${overallCls}">${overallLabel}</span>
                    ${r.overallPct !== null ? `<div class="sov-overall-bar ${overallCls}"><span style="width:${r.overallPct}%"></span></div>` : ''}
                </td>
            `;
            html += `</tr>`;
        });

        body.html(html);
    }

    // ----------------------------------------------------------------------
    // 8. RENDER — SCOPE CHIP
    // ----------------------------------------------------------------------
    function renderScopeChip() {
        if (!state.scopeClientId) { $('#sovScopeChipWrap').empty(); return; }
        const client = clientById(state.scopeClientId);
        const platformLabel = state.scopePlatformId ? ' · ' + platformById(state.scopePlatformId).name : '';
        $('#sovScopeChipWrap').html(`
            <span class="sov-scope-chip">
                Scope: <b>${esc(client ? client.name : 'Unknown')}${esc(platformLabel)}</b>
                <button type="button" id="sovClearScopeBtn" title="Clear scope"><i class="ri-close-line"></i></button>
            </span>
        `);
    }

    // ----------------------------------------------------------------------
    // 9. RENDER — QUEUE
    // ----------------------------------------------------------------------
    function applyQueueFilters(pending) {
        let list = pending;

        if (state.platform) list = list.filter(p => String(p.platformId) === state.platform);
        if (state.scopeClientId) list = list.filter(p => String(p.clientId) === String(state.scopeClientId));
        if (state.scopePlatformId) list = list.filter(p => String(p.platformId) === String(state.scopePlatformId));
        if ($('#sovQueueClient').val()) list = list.filter(p => String(p.clientId) === $('#sovQueueClient').val());
        if (state.onlyPending) list = list.filter(p => !p.entry);

        if (state.queueSearch) {
            const q = state.queueSearch.toLowerCase();
            list = list.filter(p => {
                const feature = featureById(p.platformId, p.featureId).name;
                const title = p.entry ? p.entry.title : '';
                return (feature + ' ' + title).toLowerCase().indexOf(q) !== -1;
            });
        }

        list = list.slice().sort((a, b) => a.date < b.date ? -1 : (a.date > b.date ? 1 : 0));
        return list;
    }

    function flagFor(item) {
        if (item.entry) {
            return { cls: 'filled', label: STATUS_LABEL[item.entry.status] || 'Filled' };
        }
        const d = new Date(item.date + 'T00:00:00');
        if (d < TODAY) return { cls: 'overdue', label: 'Overdue' };
        if (d.getTime() === TODAY.getTime()) return { cls: 'today', label: 'Due Today' };
        return { cls: 'upcoming', label: 'Upcoming' };
    }

    function renderQueue(pending) {
        const list = applyQueueFilters(pending);
        $('#sovQueueCount').text(list.length);

        const visible = list.slice(0, state.queuePage * state.queuePageSize);
        const body = $('#sovQueueBody');

        if (!list.length) {
            body.html(`
                <tr><td colspan="7">
                    <div class="sov-empty">
                        <i class="ri-checkbox-circle-line"></i>
                        Nothing pending here — everything in scope is filled.
                    </div>
                </td></tr>
            `);
            $('#sovLoadMoreWrap').hide();
            return;
        }

        let html = '';
        visible.forEach(item => {
            const client = clientById(item.clientId);
            const platform = platformById(item.platformId);
            const feature = featureById(item.platformId, item.featureId);
            const flag = flagFor(item);
            const isOverdue = flag.cls === 'overdue';

            // Always Fill Now, regardless of whether this planned slot already
            // has a matching entry — the plan is a minimum, not a cap, so more
            // actual content can always be added on this client+date. Editing
            // or deleting one specific actual record is done individually from
            // Social Data Entry's main list (a queue row no longer identifies
            // a single record once several can exist for the same date).
            const actionBtn = `<button class="btn btn-primary btn-sm sov-fill"
                        data-client="${item.clientId}" data-date="${item.date}"
                        data-platform="${item.platformId}" data-feature="${item.featureId}">
                       <i class="ri-add-line"></i> Fill Now
                   </button>`;

            html += `
                <tr class="${isOverdue ? 'is-overdue' : ''}">
                    <td class="text-nowrap">${fmtLongDate(item.date)}</td>
                    <td>${esc(client ? client.name : 'Unknown')}</td>
                    <td><i class="${platform.icon} me-1 text-muted"></i>${esc(platform.name)}</td>
                    <td><span class="sov-feature-chip">${esc(feature.name)}</span></td>
                    <td><span class="sov-flag ${flag.cls}">${flag.label}</span></td>
                    <td>${contentChipsFor(item.clientId, item.date)}</td>
                    <td><div class="sov-actions">${actionBtn}</div></td>
                </tr>
            `;
        });

        body.html(html);
        $('#sovLoadMoreWrap').toggle(visible.length < list.length);
    }

    // ----------------------------------------------------------------------
    // 10. MASTER RENDER
    // ----------------------------------------------------------------------
    let currentHealth = { rows: [], pending: [] };

    function renderAll() {
        currentHealth = computeHealth();
        renderKpis(currentHealth.rows);
        renderMatrixHead();
        renderMatrixBody(currentHealth.rows);
        renderScopeChip();
        renderQueue(currentHealth.pending);
    }

    // fetches the plan + entries for the current month (all clients), then renders
    function reloadAndRender() {
        $('#sovMatrixBody').html(`
            <tr><td colspan="3">
                <div class="sov-empty">
                    <div class="spinner-border text-primary spinner-border-sm mb-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="fs-12">Loading...</div>
                </div>
            </td></tr>
        `);
        return loadOverviewData(state.month).then(renderAll);
    }

    // ----------------------------------------------------------------------
    // 11. ENTRY FORM (reused for both "Fill Now" and "Edit")
    // ----------------------------------------------------------------------
    function openEntryModal(mode, ctx) {
        activeModalContext = ctx;
        formDirty = false;
        const isEdit = mode === 'edit';

        const client = clientById(ctx.clientId);
        const ctxDate = ctx.date || ctx.contentDate || '';

        $('#sovEntryTitle').html(isEdit
            ? '<i class="ri-edit-box-line me-2 text-primary"></i> Update Entry'
            : '<i class="ri-add-box-line me-2 text-primary"></i> Add Entry');

        // Client, Date, Platform and Feature are all preselected from the
        // Pending Queue row (Fill Now) or from the record itself (Edit) --
        // none of them are form fields; this strip is purely informational.
        $('#sovModalScope').html(`
            <span><i class="ri-lock-2-line me-1"></i>Client: <b>${esc(client ? client.name : 'Unknown')}</b></span>
            <span class="text-muted">·</span>
            <span>Date: <b>${esc(fmtLongDate(ctxDate))}</b></span>
            <span class="text-muted">·</span>
            <span>Platform: <b>${esc(platformById(ctx.platformId).name)}</b></span>
            <span class="text-muted">·</span>
            <span>Plan: <b>${esc(featureById(ctx.platformId, ctx.featureId).name)}</b></span>
        `);

        // Posting Type is inherited from the locked Plan, not picked in the
        // form -- only the Content Format list it drives is filled here.
        // classifyPostType() still runs, purely to recover the previously
        // saved format (if any) so editing an existing entry preselects it.
        const planPostingType = postingTypeFromPlan(featureById(ctx.platformId, ctx.featureId).name);
        const classified = classifyPostType(ctx.postType);
        fillFormatOptions(planPostingType, classified ? classified.format : null);
        setEditorText('sovFormRawContent', ctx.rawContent || '');
        $('#sovFormSong').val(ctx.songUrl || '');
        $('#sovFormReference').val(ctx.ideaReference || '');
        $('#sovFormTitle').val(ctx.title || '');
        setEditorText('sovFormDescription', ctx.contentDescription || '');
        $('#sovFormPostFormat, #sovFormRawContent, #sovFormReference').removeClass('is-invalid');
        $('#sovClearDataBtn').toggleClass('d-none', !isEdit);

        if (!entryModal) entryModal = new bootstrap.Modal(document.getElementById('sovEntryModal'));
        entryModal.show();
    }

    function validateForm() {
        const errors = [];
        $('#sovFormPostFormat, #sovFormRawContent, #sovFormReference').removeClass('is-invalid');

        const postFormat = $('#sovFormPostFormat').val();
        if (!postFormat) { $('#sovFormPostFormat').addClass('is-invalid'); errors.push('Content format is required.'); }

        const rawContent = getEditorText('sovFormRawContent');
        if (!rawContent) { $('#sovFormRawContent').addClass('is-invalid'); errors.push('Raw content is required.'); }

        const reference = $('#sovFormReference').val().trim();
        if (!reference) { $('#sovFormReference').addClass('is-invalid'); errors.push('Reference is required.'); }

        return errors;
    }

    function saveEntry() {
        const errors = validateForm();
        if (errors.length) { notify('danger', errors[0]); return; }

        const ctx = activeModalContext;
        const date = ctx.date || ctx.contentDate;

        // No duplicate/clash check here on purpose — Fill Now always creates
        // a genuinely new, independent record even when one already exists
        // for this exact client/date/platform/feature (clientSocialContent.id
        // is the only identity a record has; the Calendar plan is a minimum
        // requirement, not a uniqueness rule).
        const record = {
            clientId: ctx.clientId,
            contentDate: date,
            // Platform/Feature are no longer form fields -- always exactly
            // what the row/record already carried in ctx (see
            // openEntryModal() above).
            platformId: Number(ctx.platformId),
            featureId: Number(ctx.featureId),
            // Social Media Handle is no longer collected in this modal;
            // round-trip whatever the record already had so an edit-save
            // never blanks it out (saveEntry()/the engine overwrite every
            // content column on every save, missing keys included).
            socialMediaHandle: ctx.socialMediaHandle || '',
            // clientSocialContent.postType stores the granular Content Format
            // (e.g. "Reel", "Image Story") -- the parent Posting Type
            // (Post/Story) is always re-derived from it via
            // classifyPostType(), never stored separately (no schema change).
            postType: $('#sovFormPostFormat').val(),
            rawContent: getEditorText('sovFormRawContent'),
            songUrl: $('#sovFormSong').val().trim(),
            ideaReference: $('#sovFormReference').val().trim(),
            title: $('#sovFormTitle').val().trim(),
            contentDescription: getEditorText('sovFormDescription')
        };
        const payload = Object.assign({}, record, ctx.entryId ? { id: ctx.entryId } : {});

        $.ajax({
            url: 'api/social-content/save-entry.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to save entry.');
                return;
            }
            notify('success', ctx.entryId ? 'Entry updated successfully.' : 'Entry added — matrix and queue updated.');
            entryModal.hide();
            reloadAndRender();
        }, function () {
            notify('danger', 'Network error while saving entry.');
        });
    }

    // ----------------------------------------------------------------------
    // 12. EVENTS — filter bar
    // ----------------------------------------------------------------------
    $('#sovMonth').on('change', function () { state.month = $(this).val(); state.queuePage = 1; reloadAndRender(); });
    $('#sovPlatform').on('change', function () { state.platform = $(this).val(); state.queuePage = 1; renderAll(); });
    $('#sovAttentionOnly').on('change', function () { state.attentionOnly = $(this).is(':checked'); renderMatrixBody(currentHealth.rows); });

    $('#sovClientSearch').on('change', function () {
        state.clientSearch = $(this).val();
        renderMatrixBody(currentHealth.rows);
    });

    $('#sovResetBtn').on('click', function () {
        $('#sovPlatform').val('');
        if (sovClientChoices) sovClientChoices.setChoiceByValue('');
        else $('#sovClientSearch').val('');
        $('#sovAttentionOnly').prop('checked', false);
        $('#sovQueueClient').val('');
        $('#sovQueueSearch').val('');
        $('#sovShowOnlyPending').prop('checked', true);
        state.platform = state.clientSearch = state.scopeClientId = state.scopePlatformId = state.queueSearch = '';
        state.attentionOnly = false;
        state.onlyPending = true;
        state.kpiFilter = '';
        state.queuePage = 1;
        renderAll();
        notify('info', 'Filters reset.');
    });

    // ----------------------------------------------------------------------
    // 13. EVENTS — KPI chips
    // ----------------------------------------------------------------------
    $('.sov-kpi').on('click', function () {
        const kpi = $(this).data('kpi');
        state.kpiFilter = (state.kpiFilter === kpi) ? '' : kpi;
        renderKpis(currentHealth.rows);
        renderMatrixBody(currentHealth.rows);
    });

    // ----------------------------------------------------------------------
    // 14. EVENTS — matrix (sort, row click, cell click)
    // ----------------------------------------------------------------------
    $(document).on('click', '#sovSortClient', function () {
        state.matrixSortDir = (state.matrixSortKey === 'client' && state.matrixSortDir === 'asc') ? 'desc' : 'asc';
        state.matrixSortKey = 'client';
        renderMatrixHead(); renderMatrixBody(currentHealth.rows);
    });
    $(document).on('click', '#sovSortOverall', function () {
        state.matrixSortDir = (state.matrixSortKey === 'overall' && state.matrixSortDir === 'asc') ? 'desc' : 'asc';
        state.matrixSortKey = 'overall';
        renderMatrixHead(); renderMatrixBody(currentHealth.rows);
    });

    function scrollToQueue() {
        document.getElementById('sovQueueSection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    $('#sovMatrixBody').on('click', '.sov-cell', function (e) {
        e.stopPropagation();
        state.scopeClientId = $(this).data('client');
        state.scopePlatformId = String($(this).data('platform'));
        state.queuePage = 1;
        $('#sovQueueClient').val('');
        renderScopeChip();
        renderQueue(currentHealth.pending);
        scrollToQueue();
    });

    $('#sovMatrixBody').on('click', '.sov-client-cell, .sov-overall-cell', function () {
        state.scopeClientId = $(this).data('client');
        state.scopePlatformId = '';
        state.queuePage = 1;
        $('#sovQueueClient').val('');
        renderScopeChip();
        renderQueue(currentHealth.pending);
        scrollToQueue();
    });

    $(document).on('click', '#sovClearScopeBtn', function () {
        state.scopeClientId = '';
        state.scopePlatformId = '';
        renderScopeChip();
        renderQueue(currentHealth.pending);
        notify('info', 'Scope cleared — showing all clients again.');
    });

    // ----------------------------------------------------------------------
    // 15. EVENTS — queue toolbar
    // ----------------------------------------------------------------------
    $('#sovQueueClient').on('change', function () {
        if ($(this).val()) { state.scopeClientId = ''; state.scopePlatformId = ''; renderScopeChip(); }
        state.queuePage = 1;
        renderQueue(currentHealth.pending);
    });

    let queueSearchTimer = null;
    $('#sovQueueSearch').on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(queueSearchTimer);
        queueSearchTimer = setTimeout(function () { state.queueSearch = val; state.queuePage = 1; renderQueue(currentHealth.pending); }, 250);
    });

    $('#sovShowOnlyPending').on('change', function () {
        state.onlyPending = $(this).is(':checked');
        state.queuePage = 1;
        renderQueue(currentHealth.pending);
    });

    $('#sovLoadMoreBtn').on('click', function () { state.queuePage++; renderQueue(currentHealth.pending); });

    // ----------------------------------------------------------------------
    // 16. EVENTS — row action (fill now — always available, see actionBtn above)
    // ----------------------------------------------------------------------
    $('#sovQueueBody').on('click', '.sov-fill', function () {
        openEntryModal('add', {
            clientId: Number($(this).data('client')),
            date: $(this).data('date'),
            platformId: Number($(this).data('platform')),
            featureId: Number($(this).data('feature')),
            entryId: null
        });
    });

    // Content chip click -- opens the exact clicked clientSocialContent
    // record for editing (never resolved by client/date/platform/feature,
    // always by its own id, since that tuple can repeat).
    $('#sovQueueBody').on('click', '.sov-content-chip', function (e) {
        e.stopPropagation();
        const id = Number($(this).data('id'));
        const entry = entries.find(x => x.id === id);
        if (!entry) return;
        openEntryModal('edit', Object.assign({}, entry, { date: entry.contentDate, entryId: entry.id }));
    });

    // ----------------------------------------------------------------------
    // 17. EVENTS — modal
    // ----------------------------------------------------------------------
    // No Posting Type change handler needed anymore -- the Content Format
    // list is fixed per modal-open, driven by the locked Plan
    // (postingTypeFromPlan(), called once in openEntryModal()), not a
    // cascading user selection.
    $('#sovSaveBtn').on('click', saveEntry);
    $('#sovEntryModal').on('input change', 'input, select, textarea', function () { formDirty = true; });

    // Clear Data -- deletes this exact clientSocialContent.id only. Never
    // touches sibling records sharing the same client/date/platform/feature.
    // The API blocks this (with a clear message) once the entry's production
    // task has moved past NEW, so real production/automation work is never
    // silently cascaded away.
    $('#sovClearDataBtn').on('click', function () {
        const ctx = activeModalContext;
        if (!ctx || !ctx.entryId) return;

        confirmDialog({
            title: 'Clear this content entry?',
            html: 'This permanently deletes this specific content record. This cannot be undone.',
            icon: 'warning',
            confirmText: 'Clear Data',
            color: '#dc3545'
        }).then(function (res) {
            if (!res.isConfirmed) return;
            $.ajax({
                url: 'api/social-content/delete-entry.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ id: ctx.entryId }),
                dataType: 'json'
            }).then(function (delRes) {
                if (!delRes || !delRes.success) {
                    notify('danger', (delRes && delRes.message) || 'Failed to clear entry.');
                    return;
                }
                notify('success', 'Entry cleared.');
                formDirty = false;
                entryModal.hide();
                reloadAndRender();
            }, function () {
                notify('danger', 'Network error while clearing entry.');
            });
        });
    });

    $('#sovCancelBtn').on('click', function () {
        if (!formDirty) { entryModal.hide(); return; }
        confirmDialog({
            title: 'Discard changes?',
            html: 'The details you entered will not be saved.',
            icon: 'question',
            confirmText: 'Discard',
            color: '#dc3545'
        }).then(res => { if (res.isConfirmed) { formDirty = false; entryModal.hide(); } });
    });

    $('#sovEntryModal').on('hidden.bs.modal', function () { formDirty = false; activeModalContext = null; });

    // when embedded (?embed=1) in social-data-entry.php's full-screen modal,
    // "Close" asks the parent page to close that modal
    $('#sovEmbedCloseBtn').on('click', function () {
        if (window.parent && window.parent !== window) {
            window.parent.postMessage({ type: 'sde-overview-close' }, window.location.origin);
        }
    });

    // ----------------------------------------------------------------------
    // 18. INITIAL RENDER
    // ----------------------------------------------------------------------
    loadCatalog().then(function () {
        $('#sovMonth').html(buildMonthOptions());
        $('#sovPlatform').append(CATALOG.platforms.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join(''));
        $('#sovQueueClient').append(CATALOG.clients.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join(''));
        $('#sovClientSearch').append(CATALOG.clients.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join(''));
        state.month = $('#sovMonth').val();

        // searchable client dropdown — type to filter, pick one, reuses the
        // Choices.js library already loaded site-wide (includes/header.php)
        if (window.Choices) {
            sovClientChoices = new Choices('#sovClientSearch', {
                searchEnabled: true,
                searchPlaceholderValue: 'Search by client name...',
                itemSelectText: '',
                shouldSort: false
            });
        }

        reloadAndRender();
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
