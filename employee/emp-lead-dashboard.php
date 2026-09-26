<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
?>

<style>
    .ld-empty {
        text-align: center;
        padding: 2.5rem 1rem;
        color: var(--text-muted);
    }
    .ld-empty i { font-size: 2.2rem; opacity: 0.4; display: block; margin-bottom: 0.5rem; }
    .ld-badge {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.15rem 0.65rem;
        border-radius: 30px;
        white-space: nowrap;
    }
    .ld-badge.Pending   { background: rgba(var(--warning-rgb), 0.14); color: rgb(var(--warning-rgb)); }
    .ld-badge.Completed { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }
    .ld-badge.Skipped   { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Lead Dashboard</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="emp-dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="emp-leads">Lead Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Lead Dashboard</li>
                </ol>
            </div>
        </div>

        <!-- ==================== FILTERS ==================== -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Date Range</label>
                        <select class="form-select form-select-sm" id="ldRangeType">
                            <option value="this_month" selected>This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_week">This Week</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3 d-none" id="ldCustomRangeWrap">
                        <label class="form-label fs-12 mb-1">Custom Range</label>
                        <input type="text" class="form-control form-control-sm" id="ldCustomRange" placeholder="Select date range" autocomplete="off">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Assigned Employee</label>
                        <select class="form-select form-select-sm" id="ldEmployee">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Lead Source</label>
                        <select class="form-select form-select-sm" id="ldSource">
                            <option value="">All Sources</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== SUMMARY CARDS ==================== -->
        <div class="row row-cols-2 row-cols-md-3 row-cols-xl-5 g-3 mb-3">
            <div class="col">
                <div class="card custom-card mb-0">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <i class="ti ti-users fs-24 text-primary"></i>
                        <div>
                            <div class="fs-20 fw-semibold" id="ldTotalLeads">—</div>
                            <div class="text-muted fs-12">Total Leads</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card custom-card mb-0">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <i class="ti ti-user-plus fs-24 text-info"></i>
                        <div>
                            <div class="fs-20 fw-semibold" id="ldNewLeads">—</div>
                            <div class="text-muted fs-12">New Leads</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card custom-card mb-0">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <i class="ti ti-clock fs-24 text-warning"></i>
                        <div>
                            <div class="fs-20 fw-semibold" id="ldPendingFollowUps">—</div>
                            <div class="text-muted fs-12">Pending Follow Ups</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card custom-card mb-0">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <i class="ti ti-circle-check fs-24 text-success"></i>
                        <div>
                            <div class="fs-20 fw-semibold" id="ldCompletedFollowUps">—</div>
                            <div class="text-muted fs-12">Completed Follow Ups</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card custom-card mb-0">
                    <div class="card-body p-3 d-flex align-items-center gap-3">
                        <i class="ti ti-trophy fs-24 text-success"></i>
                        <div>
                            <div class="fs-20 fw-semibold" id="ldConvertedLeads">—</div>
                            <div class="text-muted fs-12">Converted Leads</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CHARTS ROW 1 ==================== -->
        <div class="row">
            <div class="col-xl-8">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lead Trend</h5>
                    </div>
                    <div class="card-body">
                        <div id="ldLeadTrendChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Lead Status Distribution</h5>
                    </div>
                    <div class="card-body">
                        <div id="ldStatusChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== CHARTS ROW 2 ==================== -->
        <div class="row">
            <div class="col-xl-5">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Follow Up Performance</h5>
                    </div>
                    <div class="card-body">
                        <div id="ldFollowUpChart"></div>
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Employee Performance</h5>
                    </div>
                    <div class="card-body">
                        <div id="ldEmployeeChartEmpty" class="ld-empty d-none">
                            <i class="ri-team-line"></i>
                            <div class="fs-12">No employee-assigned leads match the current filters.</div>
                        </div>
                        <div id="ldEmployeeChart"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== RECENT FOLLOW UP TABLE ==================== -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Follow Up Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Lead Name</th>
                                        <th>Employee</th>
                                        <th>Follow Up Date</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Last Action</th>
                                    </tr>
                                </thead>
                                <tbody id="ldRecentTableBody">
                                    <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
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
<script src="<?= ASSET_URL ?>/assets/js/lead-dashboard.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
