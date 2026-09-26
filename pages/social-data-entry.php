<?php
/*
|--------------------------------------------------------------------------
| Social Media — Data Entry
|--------------------------------------------------------------------------
|
| Real-world flow (mirrors pages/calendar.php):
|   calendar.php    -> decides WHICH dates a client/platform/feature runs on
|                      (clientCalendarPlans.selectedDates, read here via
|                      api/social-content/get-plan.php)
|   this page       -> fills in WHAT goes out on each of those dates
|                      (clientSocialContent, read/written here via
|                      api/social-content/get-entries.php, save-entry.php,
|                      delete-entry.php)
|
| Dimensions handled here: date x client x platform x feature.
|
*/
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
$sdeCurrentUserId = (int)($_SESSION['userId'] ?? 0);
?>

<style>
    /* ==========================================================================
   Social Data Entry — scoped styles (sde-*)
   Theme tokens only, so light/dark mode both stay correct.
   ========================================================================== */

    .sde-filterbar .form-select,
    .sde-filterbar .form-control {
        border-radius: 30px;
    }

    .sde-filterbar label {
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 0.2rem;
    }

    /* every filter (incl. Search) gets the same width and wraps as a group */
    .sde-filter-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.6rem;
        align-items: flex-end;
    }

    .sde-filter-item {
        flex: 1 1 160px;
        min-width: 150px;
    }

    /* ---------- Left rail : the date list ---------- */
    .sde-rail-card .card-body {
        padding: 0.5rem;
    }

    .sde-rail {
        max-height: 62vh;
        overflow-y: auto;
        padding-right: 2px;
    }

    .sde-rail::-webkit-scrollbar { width: 5px; }
    .sde-rail::-webkit-scrollbar-thumb {
        background: var(--default-border);
        border-radius: 10px;
    }

    .sde-date-item {
        width: 100%;
        display: grid;
        grid-template-columns: 44px 1fr;
        align-items: center;
        gap: 0.55rem;
        text-align: left;
        background: transparent;
        border: 1px solid transparent;
        border-radius: 12px;
        padding: 0.4rem 0.45rem;
        margin-bottom: 2px;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease;
    }

    .sde-date-item:hover {
        background: var(--primary005, rgba(var(--primary-rgb), 0.05));
        border-color: var(--default-border);
    }

    .sde-date-item.active {
        background: var(--primary01);
        /* border-color: var(--primary-border); */
    }

    .sde-date-num {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: var(--default-background);
        border: 1px solid var(--default-border);
        line-height: 1.05;
    }

    .sde-date-item.active .sde-date-num {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: #fff;
    }

    .sde-date-num b { font-size: 1rem; font-weight: 700; }
    .sde-date-num span { font-size: 0.6rem; text-transform: uppercase; opacity: 0.75; }

    .sde-date-toprow {
        display: flex;
        flex-direction: column;
        gap: 1px;
    }

    .sde-date-dow {
        font-size: 0.78rem;
        font-weight: 600;
        line-height: 1.1;
        color: var(--default-text-color);
    }

    .sde-date-progress {
        margin-top: 4px;
        height: 4px;
        border-radius: 4px;
        background: var(--default-border);
        overflow: hidden;
    }

    .sde-date-progress span {
        display: block;
        height: 100%;
        border-radius: 4px;
        background: var(--primary-color);
    }

    .sde-date-progress.is-done span { background: rgb(var(--success-rgb)); }
    .sde-date-progress.is-empty span { background: rgb(var(--warning-rgb)); }

    .sde-date-count {
        font-size: 0.66rem;
        font-weight: 700;
        line-height: 1.1;
        color: var(--text-muted);
        white-space: nowrap;
    }

    /* ---------- Right panel : the day board ---------- */
    .sde-day-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .sde-day-title {
        font-size: 1.05rem;
        font-weight: 600;
        color: var(--default-text-color);
        margin: 0;
    }

    .sde-stat {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        font-size: 0.74rem;
        font-weight: 600;
        padding: 0.2rem 0.7rem;
        border-radius: 30px;
        border: 1px solid var(--default-border);
        background: var(--default-background);
        color: var(--text-muted);
    }

    .sde-stat b { color: var(--default-text-color); }
    .sde-stat i { font-size: 0.55rem; }

    /* platform group */
    .sde-group {
        border: 1px solid var(--default-border);
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 0.9rem;
    }

    .sde-group-head {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.9rem;
        background: var(--default-background);
        border-bottom: 1px solid var(--default-border);
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--default-text-color);
    }

    .sde-group-head i.sde-plat-icon {
        font-size: 1.05rem;
        color: var(--primary-color);
    }

    /* slot rows */
    .sde-slot-head,
    .sde-slot {
        display: grid;
        grid-template-columns: 150px 1fr 150px 150px 64px;
        gap: 0.75rem;
        align-items: center;
        padding: 0.6rem 0.9rem;
        border-bottom: 1px solid var(--default-border);
    }

    .sde-slot:last-child { border-bottom: none; }

    .sde-slot-head {
        padding-top: 0.4rem;
        padding-bottom: 0.4rem;
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--text-muted);
        background: var(--custom-white);
    }

    .sde-slot:hover { background: var(--primary005, rgba(var(--primary-rgb), 0.04)); }

    .sde-slot.is-empty { background: rgba(var(--warning-rgb), 0.04); }

    .sde-feature {
        display: inline-block;
        max-width: 100%;
        padding: 0.15rem 0.7rem;
        border-radius: 30px;
        background: var(--primary01);
        color: var(--primary-color);
        font-size: 0.75rem;
        font-weight: 600;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sde-title {
        font-size: 0.82rem;
        font-weight: 600;
        color: var(--default-text-color);
        margin-bottom: 1px;
    }

    .sde-caption {
        font-size: 0.72rem;
        color: var(--text-muted);
        display: -webkit-box;
        -webkit-line-clamp: 1;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .sde-badge {
        display: inline-block;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 0.15rem 0.65rem;
        border-radius: 30px;
        white-space: nowrap;
    }

    .sde-badge.draft     { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
    .sde-badge.ready     { background: rgba(var(--info-rgb), 0.12);      color: rgb(var(--info-rgb)); }
    .sde-badge.scheduled { background: rgba(var(--warning-rgb), 0.14);   color: rgb(var(--warning-rgb)); }
    .sde-badge.posted    { background: rgba(var(--success-rgb), 0.12);   color: rgb(var(--success-rgb)); }
    .sde-badge.pending   { background: rgba(var(--danger-rgb), 0.10);    color: rgb(var(--danger-rgb)); }

    /* status badge + "Production Created" indicator, centered and stacked
       inside the (now wide-enough) Status column — see grid-template-columns
       above, which gives this column the room the capsule actually needs. */
    .sde-status-stack {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 4px;
        width: 100%;
    }

    .sde-status-stack .badge {
        white-space: nowrap;
    }

    .sde-meta {
        font-size: 0.7rem;
        color: var(--text-muted);
        line-height: 1.4;
    }

    .sde-actions {
        display: flex;
        gap: 0.25rem;
        justify-content: flex-end;
    }

    .sde-icon-btn {
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

    .sde-icon-btn:hover { background: var(--primary01); color: var(--primary-color); border-color: var(--primary-border); }
    .sde-icon-btn.danger:hover { background: rgba(var(--danger-rgb), 0.1); color: rgb(var(--danger-rgb)); border-color: rgba(var(--danger-rgb), 0.3); }

    .sde-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--text-muted);
    }

    .sde-empty i { font-size: 2.4rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }

    /* the entry form */
    .sde-form label.form-label {
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--default-text-color);
    }

    .sde-form .form-control,
    .sde-form .form-select { font-size: 0.85rem; }

    .sde-scope {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        padding: 0.6rem 0.8rem;
        border-radius: 12px;
        background: var(--default-background);
        border: 1px solid var(--default-border);
        margin-bottom: 1rem;
    }

    .sde-scope span {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--text-muted);
    }

    .sde-scope b { color: var(--default-text-color); }

    /* ---------- View Entry (detail presentation, no form controls) ---------- */
    .sde-view-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 0.75rem;
        padding-bottom: 0.9rem;
        margin-bottom: 0.9rem;
        border-bottom: 1px solid var(--default-border);
    }

    .sde-view-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--default-text-color);
        margin-bottom: 0.4rem;
    }

    .sde-view-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.35rem;
        align-items: center;
    }

    .sde-view-meta {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
        font-size: 0.72rem;
        color: var(--text-muted);
        text-align: right;
    }

    .sde-view-section { margin-bottom: 1.1rem; }
    .sde-view-section:last-child { margin-bottom: 0; }

    .sde-view-section-title {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 0.6rem;
    }

    .sde-view-block {
        background: var(--default-background);
        border: 1px solid var(--default-border);
        border-radius: 12px;
        padding: 0.7rem 0.9rem;
        margin-bottom: 0.6rem;
    }

    .sde-view-block:last-child { margin-bottom: 0; }

    .sde-view-block .sde-view-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.03em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 0.3rem;
    }

    .sde-view-block .sde-view-value {
        font-size: 0.85rem;
        color: var(--default-text-color);
        white-space: pre-line;
        word-break: break-word;
    }

    .sde-view-media {
        display: block;
        max-width: 100%;
        max-height: 240px;
        border-radius: 10px;
        border: 1px solid var(--default-border);
    }

    /* responsive */
    @media (max-width: 1199.98px) {
        .sde-rail { max-height: 260px; }
    }

    @media (max-width: 991.98px) {
        .sde-slot-head { display: none; }
        .sde-slot {
            grid-template-columns: 1fr;
            gap: 0.4rem;
        }
        .sde-actions { justify-content: flex-start; }
    }

    /* ---------- full-page "Social Media Overview" modal ---------- */
    .sde-overview-body {
        padding: 0;
        overflow: hidden;
    }

    .sde-overview-frame {
        width: 100%;
        height: 100%;
        border: 0;
        display: block;
    }

    .sde-rail-card .card-header {
        padding-left: 0.9rem;
        padding-right: 0.9rem;
        display: flex;
        flex-wrap: nowrap;
        align-items: center;
        justify-content: space-between;
        gap: 0.4rem;
    }

    .sde-rail-card .card-header .card-title {
        flex: 1 1 auto;
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        font-size: 0.7rem;
        font-weight: 700;
    }

    .sde-rail-card #sdeRailCount {
        flex: 0 0 auto;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 0.15rem 0.45rem;
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- ============================ PAGE HEADER ============================ -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Social Media Data Entry</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Data Entry</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-light btn-sm" id="sdeAnalyticsBtn">
                    <i class="ri-bar-chart-2-line me-1"></i> Data Analytics
                </button>
                <a href="calendar" class="btn btn-light btn-sm">
                    <i class="ri-calendar-event-line me-1"></i> Calendar Planner
                </a>
                <button class="btn btn-primary btn-sm" id="sdeAddBtn">
                    <i class="ri-add-line me-1"></i> Add Entry
                </button>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
       <div class="card custom-card sde-filterbar">
            <div class="card-body py-3">
                <div class="sde-filter-row">

                    <div class="sde-filter-item">
                        <label for="sdeClient">Client</label>
                        <select class="form-select form-select-sm" id="sdeClient"></select>
                    </div>

                    <div class="sde-filter-item">
                        <label for="sdeDateRange">Date Range</label>
                        <input type="text" class="form-control form-control-sm" id="sdeDateRange"
                               placeholder="Select date or range" autocomplete="off">
                    </div>

                    <div class="sde-filter-item">
                        <label for="sdePlatform">Platform</label>
                        <select class="form-select form-select-sm" id="sdePlatform">
                            <option value="">All Platforms</option>
                        </select>
                    </div>

                    <div class="sde-filter-item">
                        <label for="sdeFeature">Plan</label>
                        <select class="form-select form-select-sm" id="sdeFeature">
                            <option value="">All Plans</option>
                        </select>
                    </div>

                    <div class="sde-filter-item">
                        <label for="sdeEditable">Editable</label>
                        <select class="form-select form-select-sm" id="sdeEditable">
                            <option value="">All</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>

                    <div class="sde-filter-item">
                        <label for="sdeStatus">Status</label>
                        <select class="form-select form-select-sm" id="sdeStatus">
                            <option value="">All Status</option>
                            <option value="pending">Not Filled</option>
                            <option value="draft">Draft</option>
                            <option value="ready">Ready</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="posted">Posted</option>
                        </select>
                    </div>

                    <div class="sde-filter-item">
                        <label for="sdeSearch">Search</label>
                        <div class="d-flex gap-2">
                            <input type="text"
                                class="form-control form-control-sm"
                                id="sdeSearch"
                                placeholder="Title, caption or plan...">

                            <button class="btn btn-light btn-sm flex-shrink-0"
                                    id="sdeResetBtn"
                                    title="Reset filters">
                                <i class="ri-refresh-line"></i>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- ============================ BOARD ============================ -->
        <div class="row">

            <!-- ---------- date rail ---------- -->
            <div class="col-xl-2 col-lg-2">
                <div class="card custom-card sde-rail-card">
                    <div class="card-header py-2">
                        <div class="card-title mb-0" id="sdeRailTitle" title="">Dates</div>
                        <!-- <span class="badge bg-light text-default" id="sdeRailCount">0</span> -->
                    </div>
                    <div class="card-body">
                        <div class="sde-rail" id="sdeRail"></div>
                    </div>
                    <div class="card-footer py-2 text-muted fs-11">
                        <i class="ri-information-line me-1"></i>
                        Only dates planned in the Calendar are listed.
                    </div>
                </div>
            </div>

            <!-- ---------- day board ---------- -->
            <div class="col-xl-10 col-lg-10">
                <div class="card custom-card">
                    <div class="card-header py-3">
                        <div class="sde-day-head w-100">
                            <div>
                                <h6 class="sde-day-title mb-1" id="sdeDayTitle">Select a date</h6>
                                <div class="d-flex gap-2 flex-wrap" id="sdeDayStats"></div>
                            </div>
                            <!-- <button class="btn btn-primary btn-sm" id="sdeAddForDateBtn" disabled>
                                <i class="ri-add-line me-1"></i> Add Entry for this Date
                            </button> -->
                        </div>
                    </div>
                    <div class="card-body" id="sdeBoard"></div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ============================ SOCIAL MEDIA OVERVIEW (full-page) MODAL ============================ -->
<!-- No modal-header here on purpose — the embedded Overview page shows its
     own page title and its own "Close" button beside "Open Client Editor". -->
<div class="modal fade" id="sdeOverviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
            <div class="modal-body sde-overview-body">
                <iframe id="sdeOverviewFrame" class="sde-overview-frame" title="Social Media Overview"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- ============================ ENTRY MODAL ============================ -->
<div class="modal fade" id="sdeEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="sdeEntryTitle">
                    <i class="ri-edit-box-line me-2 text-primary"></i> Add Entry
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- Add/Edit form — hidden entirely in View mode, replaced by
                     #sdeViewDetails below. IDs/logic untouched. -->
                <div class="sde-form" id="sdeFormFields">
                    <div class="sde-scope" id="sdeScopeStrip"></div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="sdeFormDate">Date <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sdeFormDate" placeholder="Select date">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sdeFormPlatform">Platform <span class="text-danger">*</span></label>
                            <select class="form-select" id="sdeFormPlatform"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sdeFormFeature">Plan <span class="text-danger">*</span></label>
                            <select class="form-select" id="sdeFormFeature"></select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label" for="sdeFormTitle">Content Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sdeFormTitle" maxlength="120"
                                   placeholder="e.g. Independence Day creative">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="sdeFormStatus">Status</label>
                            <select class="form-select" id="sdeFormStatus">
                                <option value="draft">Draft</option>
                                <option value="ready" disabled title="Set automatically on save">Ready</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="posted">Posted</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="sdeFormCaption">Caption / Description</label>
                            <textarea class="form-control" id="sdeFormCaption" rows="3"
                                      placeholder="Caption, hashtags, copy notes..."></textarea>
                        </div>

                        <div class="col-md-7">
                            <label class="form-label" for="sdeFormLink">Creative / Reference Link</label>
                            <input type="url" class="form-control" id="sdeFormLink" placeholder="https://drive.google.com/...">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="sdeFormRemarks">Remarks</label>
                            <input type="text" class="form-control" id="sdeFormRemarks" maxlength="120"
                                   placeholder="Internal note (optional)">
                        </div>
                    </div>
                </div>

                <!-- View mode — populated entirely by renderViewDetails(), reusing
                     the same entry payload openEntryModal() already receives.
                     No fetch, no form controls, display only. -->
                <div class="sde-view d-none" id="sdeViewDetails"></div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" id="sdeCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="sdeSaveBtn">
                    <i class="ri-save-line me-1"></i> Save Entry
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================ DATA ANALYTICS MODAL ============================ -->
<div class="modal fade" id="sdeAnalyticsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">
                    <i class="ri-bar-chart-2-line me-2 text-primary"></i> Social Data Entry Analytics
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <!-- filters -->
                <div class="sde-filter-row mb-3">
                    <div class="sde-filter-item">
                        <label for="sdaRangeType">Date Range</label>
                        <select class="form-select form-select-sm" id="sdaRangeType">
                            <option value="month" selected>Month</option>
                            <option value="week">Week</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>

                    <div class="sde-filter-item" id="sdaMonthWrap">
                        <label for="sdaMonth">Month</label>
                        <input type="month" class="form-control form-control-sm" id="sdaMonth">
                    </div>

                    <div class="sde-filter-item d-none" id="sdaWeekMonthWrap">
                        <label for="sdaWeekMonth">Month</label>
                        <input type="month" class="form-control form-control-sm" id="sdaWeekMonth">
                    </div>

                    <div class="sde-filter-item d-none" id="sdaWeekWrap">
                        <label for="sdaWeek">Week</label>
                        <select class="form-select form-select-sm" id="sdaWeek"></select>
                    </div>

                    <div class="sde-filter-item d-none" id="sdaCustomWrap">
                        <label for="sdaCustomRange">Custom Range</label>
                        <input type="text" class="form-control form-control-sm" id="sdaCustomRange"
                               placeholder="Select date range" autocomplete="off">
                    </div>
                </div>

                <!-- summary cards -->
                <div class="row g-2 mb-3" id="sdaSummaryRow">
                    <div class="col-6 col-md-3">
                        <div class="card custom-card mb-0">
                            <div class="card-body py-2 px-3 text-center">
                                <div class="fs-18 fw-semibold" id="sdaTotalContent">—</div>
                                <div class="fs-11 text-muted text-uppercase">Total Content</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card custom-card mb-0">
                            <div class="card-body py-2 px-3 text-center">
                                <div class="fs-18 fw-semibold text-success" id="sdaCompletedContent">—</div>
                                <div class="fs-11 text-muted text-uppercase">Completed</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card custom-card mb-0">
                            <div class="card-body py-2 px-3 text-center">
                                <div class="fs-18 fw-semibold text-warning" id="sdaPendingContent">—</div>
                                <div class="fs-11 text-muted text-uppercase">Pending</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card custom-card mb-0">
                            <div class="card-body py-2 px-3 text-center">
                                <div class="fs-18 fw-semibold" id="sdaTotalClients">—</div>
                                <div class="fs-11 text-muted text-uppercase">Clients</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- chart -->
                <div class="card custom-card mb-0">
                    <div class="card-body">
                        <div class="sde-empty py-5 d-none" id="sdaChartEmpty">
                            <i class="ri-bar-chart-2-line"></i>
                            <div class="fs-12">No planned content in this range.</div>
                        </div>
                        <div id="sdaChart"></div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>const CURRENT_USER_ID = <?php echo (int)$sdeCurrentUserId; ?>;</script>
<script src="<?= ASSET_URL ?>/assets/libs/apexcharts/apexcharts.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/social-data-entry.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/social-data-entry-analytics.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
