<?php
/*
|--------------------------------------------------------------------------
| Shared Management Dashboard body
|--------------------------------------------------------------------------
| Used by pages/dashboard.php, pages/lead-dashboard.php and
| employee/emp-lead-dashboard.php (admin gets full company data; a Sales
| Manager without 'view-all-leads', or any other employee, is auto-scoped
| to their own leads by api/leads/get-dashboard-summary.php -- no role
| branching needed here). Expects $ldHomeUrl / $ldLeadsUrl for breadcrumbs.
| Data: includes/leadDashboardEngine.php via get-dashboard-filters.php /
| get-dashboard-summary.php. Filters: Date Range, Assigned Employee
| (hidden when the caller's scope is restricted to themselves), Lead
| Source, Project -- one row on desktop (.crm-filter-bar).
*/
$ldEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<style>
    .ld-empty { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .ld-empty i { font-size: 2.2rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }
    .ld-badge {
        display: inline-block; font-size: 0.72rem; font-weight: 600;
        padding: 0.15rem 0.65rem; border-radius: 30px; white-space: nowrap;
    }
    .ld-badge.Pending   { background: rgba(var(--warning-rgb), 0.14); color: rgb(var(--warning-rgb)); }
    .ld-badge.Completed { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }
    .ld-badge.Skipped   { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Dashboard</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= $ldEsc($ldHomeUrl) ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
                </ol>
            </div>
        </div>

        <!-- ==================== FILTERS (one row, standing rule) ==================== -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="crm-filter-bar">
                    <select class="form-select" id="ldRangeType">
                        <option value="this_month" selected>This Month</option>
                        <option value="last_month">Last Month</option>
                        <option value="this_week">This Week</option>
                        <option value="custom">Custom Range</option>
                    </select>
                    <div class="crm-filter-field d-none" id="ldCustomRangeWrap">
                        <input type="text" class="form-control" id="ldCustomRange" placeholder="Select date range" autocomplete="off">
                    </div>
                    <select class="form-select d-none" id="ldEmployee">
                        <option value="">All Salespeople</option>
                    </select>
                    <select class="form-select" id="ldSource">
                        <option value="">All Sources</option>
                    </select>
                    <select class="form-select" id="ldProject">
                        <option value="">All Projects</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- ==================== SUMMARY CARDS ==================== -->
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-3 mb-3">
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-users fs-24 text-primary"></i>
                <div><div class="fs-20 fw-semibold" id="ldTotalLeads">—</div><div class="text-muted fs-12">Total Leads</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-user-plus fs-24 text-info"></i>
                <div><div class="fs-20 fw-semibold" id="ldNewLeads">—</div><div class="text-muted fs-12">New Leads</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-calendar-event fs-24 text-warning"></i>
                <div><div class="fs-20 fw-semibold" id="ldTodayFollowUps">—</div><div class="text-muted fs-12">Today's Follow-ups</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-alert-triangle fs-24 text-danger"></i>
                <div><div class="fs-20 fw-semibold" id="ldOverdueFollowUps">—</div><div class="text-muted fs-12">Overdue Follow-ups</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-building-community fs-24 text-purple"></i>
                <div><div class="fs-20 fw-semibold" id="ldSiteVisits">—</div><div class="text-muted fs-12">Site Visits</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-trophy fs-24 text-success"></i>
                <div><div class="fs-20 fw-semibold" id="ldConvertedLeads">—</div><div class="text-muted fs-12">Converted Leads</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-circle-x fs-24 text-secondary"></i>
                <div><div class="fs-20 fw-semibold" id="ldLostLeads">—</div><div class="text-muted fs-12">Lost Leads</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-percentage fs-24 text-primary"></i>
                <div><div class="fs-20 fw-semibold" id="ldConversionRate">—</div><div class="text-muted fs-12">Conversion Rate</div></div>
            </div></div></div>
        </div>

        <!-- ==================== CHARTS ROW 1 ==================== -->
        <div class="row">
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Lead Trend</h5></div>
                    <div class="card-body"><div id="ldLeadTrendChart"></div></div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Sales Pipeline</h5></div>
                    <div class="card-body"><div id="ldStatusChart"></div></div>
                </div>
            </div>
        </div>

        <!-- ==================== CHARTS ROW 2 ==================== -->
        <div class="row">
            <div class="col-xl-6">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Leads by Source</h5></div>
                    <div class="card-body"><div id="ldSourceChart"></div></div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Leads by Project</h5></div>
                    <div class="card-body">
                        <div id="ldProjectChartEmpty" class="ld-empty d-none"><i class="ri-building-line"></i><div class="fs-12">No project-linked leads match the current filters.</div></div>
                        <div id="ldProjectChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CHARTS ROW 3 ==================== -->
        <div class="row">
            <div class="col-xl-5">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Follow-up Performance</h5></div>
                    <div class="card-body"><div id="ldFollowUpChart"></div></div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Leads by Sales Executive</h5></div>
                    <div class="card-body">
                        <div id="ldEmployeeChartEmpty" class="ld-empty d-none"><i class="ri-team-line"></i><div class="fs-12">No assigned leads match the current filters.</div></div>
                        <div id="ldEmployeeChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RECENT FOLLOW UP TABLE ==================== -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">Recent Follow-up Activity</h5></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead><tr><th>Lead Name</th><th>Assigned To</th><th>Follow-up Date</th><th>Type</th><th>Status</th><th>Last Action</th></tr></thead>
                                <tbody id="ldRecentTableBody"><tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/libs/apexcharts/apexcharts.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/lead-dashboard.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/lead-dashboard.js') ?>"></script>
