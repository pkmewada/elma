<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';

// Landing page for sales employees. Phase 5 adds a compact "Overview"
// widget (KPI cards + pipeline chart) using the same dashboard API as the
// full Lead Dashboard -- api/leads/get-dashboard-summary.php already
// scopes the data to this employee's own leads unless they have
// 'view-all-leads' (Sales Manager), so no role branching is needed here.
// No filters/date-range UI: always "this month" for a quick glance; the
// full filterable dashboard (Sales Manager only) is at /emp-lead-dashboard.
$employeeEngine = new EmployeeInfoEngine($con);
$currentEmployee = $employeeEngine->getCurrentEmployee() ?? [];
$employeeName = trim((string)($currentEmployee['fullName'] ?? ''));
$employeeRole = trim((string)($currentEmployee['designationName'] ?? ''));

$dashboardLinks = [
    ['route' => 'emp-leads', 'title' => 'My Leads', 'text' => 'Your assigned enquiries, status updates and remarks.', 'icon' => 'ti ti-user-search', 'color' => 'primary'],
    ['route' => 'emp-follow-ups', 'title' => 'Follow-ups', 'text' => "Today's, upcoming and overdue follow-ups.", 'icon' => 'ti ti-calendar-event', 'color' => 'success'],
    ['route' => 'emp-projects', 'title' => 'Projects', 'text' => 'Active projects and sales material.', 'icon' => 'ti ti-building', 'color' => 'secondary'],
    ['route' => 'emp-lead-dashboard', 'title' => 'Lead Dashboard', 'text' => 'Lead trend and follow-up performance.', 'icon' => 'ti ti-chart-bar', 'color' => 'warning'],
    ['route' => 'emp-lead-activity', 'title' => 'Lead Activity', 'text' => 'Chronological history of lead actions.', 'icon' => 'ti ti-history', 'color' => 'info'],
];
?>

<!-- Start::app-content -->
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- Page Header -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">
                    Hello<?= $employeeName !== '' ? ', ' . htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') : ''; ?>
                </h1>
                <p class="fs-13 text-muted mb-0">
                    <?= htmlspecialchars($employeeRole !== '' ? $employeeRole : 'Employee', ENT_QUOTES, 'UTF-8'); ?>
                </p>
            </div>
        </div>
        <!-- Page Header Close -->

        <!-- ==================== OVERVIEW (this month, own scope) ==================== -->
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-3" id="empOverviewCards">
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-users fs-24 text-primary"></i>
                <div><div class="fs-20 fw-semibold" data-ov="totalLeads">—</div><div class="text-muted fs-12">Total Leads</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-calendar-event fs-24 text-warning"></i>
                <div><div class="fs-20 fw-semibold" data-ov="todayFollowUps">—</div><div class="text-muted fs-12">Today's Follow-ups</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-alert-triangle fs-24 text-danger"></i>
                <div><div class="fs-20 fw-semibold" data-ov="overdueFollowUps">—</div><div class="text-muted fs-12">Overdue Follow-ups</div></div>
            </div></div></div>
            <div class="col"><div class="card custom-card mb-0"><div class="card-body p-3 d-flex align-items-center gap-3">
                <i class="ti ti-trophy fs-24 text-success"></i>
                <div><div class="fs-20 fw-semibold" data-ov="convertedLeads">—</div><div class="text-muted fs-12">Converted</div></div>
            </div></div></div>
        </div>
        <div class="row mb-1">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header"><h5 class="card-title mb-0">My Pipeline</h5></div>
                    <div class="card-body"><div id="empPipelineChart"></div></div>
                </div>
            </div>
        </div>

        <div class="row">
            <?php foreach ($dashboardLinks as $link): ?>
                <?php if (!hasRoutePermission('/' . $link['route'], 'canView')) { continue; } ?>
                <div class="col-xxl-4 col-xl-4 col-lg-6 col-md-6 col-sm-12">
                    <a href="<?= htmlspecialchars($link['route'], ENT_QUOTES, 'UTF-8'); ?>" class="card custom-card text-reset">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start gap-3">
                                <span class="avatar avatar-md bg-<?= $link['color']; ?>-transparent">
                                    <i class="<?= $link['icon']; ?> fs-20"></i>
                                </span>
                                <div>
                                    <h6 class="fw-semibold mb-1"><?= htmlspecialchars($link['title'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                    <p class="text-muted fs-13 mb-0"><?= htmlspecialchars($link['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
<!-- End::app-content -->

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/libs/apexcharts/apexcharts.min.js"></script>
<script>
$(function () {
    var now = new Date();
    var first = new Date(now.getFullYear(), now.getMonth(), 1);
    var last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    var ymd = function (d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };

    $.getJSON(API_BASE + '/leads/get-dashboard-summary.php', { dateFrom: ymd(first), dateTo: ymd(last) }, function (res) {
        if (!res.success) return;
        var s = res.data.summary || {};
        $('#empOverviewCards [data-ov="totalLeads"]').text(s.totalLeads ?? 0);
        $('#empOverviewCards [data-ov="todayFollowUps"]').text(s.todayFollowUps ?? 0);
        $('#empOverviewCards [data-ov="overdueFollowUps"]').text(s.overdueFollowUps ?? 0);
        $('#empOverviewCards [data-ov="convertedLeads"]').text(s.convertedLeads ?? 0);

        var rows = res.data.statusDistribution || [];
        var labels = rows.map(function (r) { return String(r.status || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }); });
        new ApexCharts(document.querySelector('#empPipelineChart'), {
            series: [{ name: 'Leads', data: rows.map(function (r) { return r.count; }) }],
            chart: { type: 'bar', height: 260, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 4, columnWidth: '45%' } },
            dataLabels: { enabled: true },
            xaxis: { categories: labels },
        }).render();
    });
});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php';
