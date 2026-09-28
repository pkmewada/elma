<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<?php
// Phase 1 landing page. The CRM management dashboard (lead totals,
// pipeline, source/project/executive breakdowns) replaces this in Phase 5.
$dashboardAdmin = getLoggedInAdminUser();
$dashboardName = trim((string)($dashboardAdmin['fullName'] ?? ''));

$dashboardLinks = [
    ['route' => 'leads', 'title' => 'All Leads', 'text' => 'Capture, update and track every enquiry.', 'icon' => 'ti ti-user-search', 'color' => 'primary'],
    ['route' => 'lead-follow-up-list', 'title' => 'Follow-ups', 'text' => "Today's, upcoming and overdue follow-ups.", 'icon' => 'ti ti-calendar-event', 'color' => 'success'],
    ['route' => 'lead-dashboard', 'title' => 'Lead Dashboard', 'text' => 'Lead trend, status and employee performance.', 'icon' => 'ti ti-chart-bar', 'color' => 'warning'],
    ['route' => 'projects', 'title' => 'Projects', 'text' => 'Project portfolio, brochures, floor plans and price lists.', 'icon' => 'ti ti-building', 'color' => 'secondary'],
    ['route' => 'employee-directory', 'title' => 'Employees', 'text' => 'Sales team accounts and roles.', 'icon' => 'ti ti-users', 'color' => 'info'],
];
?>

<!-- Start::app-content -->
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- Start::page-header -->
        <div class="d-flex align-items-center justify-content-between my-4 page-header-breadcrumb flex-wrap gap-2">
            <div>
                <p class="fw-medium fs-20 mb-0">
                    Hello<?= $dashboardName !== '' ? ', ' . htmlspecialchars($dashboardName, ENT_QUOTES, 'UTF-8') : ''; ?>
                </p>
                <p class="fs-13 text-muted mb-0">Real Estate Sales CRM</p>
            </div>
        </div>
        <!-- End::page-header -->

        <div class="row">
            <?php foreach ($dashboardLinks as $link): ?>
                <?php if (!hasRoutePermission('/' . $link['route'], 'canView')) { continue; } ?>
                <div class="col-xxl-3 col-xl-3 col-lg-6 col-md-6 col-sm-6 col-12">
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
