<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$currentPath = $_GET['from'] ?? ($_SERVER['REQUEST_URI'] ?? '');

$isEmployeePanel = (
    strpos($currentPath, '/emp-') !== false ||
    strpos($currentPath, '/employee-') !== false ||
    !empty($_SESSION['candidateId'])
);

if ($isEmployeePanel) {
    include __DIR__ . '/../includes/emp-header.php';
    include __DIR__ . '/../includes/emp-sidebar.php';
} else {
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
}
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="row justify-content-center align-items-center" style="min-height: 70vh;">
            <div class="col-xl-5 col-lg-6 col-md-8">
                <div class="card custom-card text-center">
                    <div class="card-body p-5">

                        <div class="mb-4">
                            <span class="avatar avatar-xxl bg-danger-transparent rounded-circle">
                                <i class="ri-shield-cross-line fs-1 text-danger"></i>
                            </span>
                        </div>

                        <h4 class="fw-semibold mb-2">Permission Denied</h4>

                        <p class="text-muted mb-4">
                            You do not have permission to access this page.
                            Please contact your administrator.
                        </p>

                        <a href="<?= BASE_URL; ?>/<?= $isEmployeePanel ? 'emp-dashboard' : 'dashboard'; ?>" class="btn btn-primary">
                            <i class="ri-arrow-left-line me-1"></i>
                            Back to Dashboard
                        </a>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
if ($isEmployeePanel) {
    include __DIR__ . '/../includes/emp-footer.php';
} else {
    include __DIR__ . '/../includes/footer.php';
}
?>
