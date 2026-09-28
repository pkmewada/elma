<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';

// Phase 1 landing page for sales employees. The executive/manager
// dashboard (my leads, today's follow-ups, pipeline) replaces this in
// Phase 5. The previous attendance/HR profile widgets were removed with
// the HRMS modules.
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

<?php include __DIR__ . '/../includes/emp-footer.php';
