<?php
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Client Deliverables</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Client Deliverables</li>
                </ol>
            </div>
        </div>

        <!-- Compact Filter Section - Single Row -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <label class="fw-medium text-muted small mb-0">Client:</label>
                                <select id="clientFilter" class="form-select form-select-sm" style="min-width: 160px; width: auto;">
                                    <option value="">All Clients</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="fw-medium text-muted small mb-0">Service:</label>
                                <select id="serviceFilter" class="form-select form-select-sm" style="min-width: 160px; width: auto;">
                                    <option value="">All Services</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="fw-medium text-muted small mb-0">Month:</label>
                                <select id="monthFilter" class="form-select form-select-sm" style="min-width: 140px; width: auto;">
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <span class="text-muted small" id="recordCount">0 clients</span>
                                <button class="btn btn-outline-secondary btn-sm" id="refreshBtn" title="Refresh">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Excel-like Pivot Table -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between py-2">
                        <div class="card-title fs-14">
                            <i class="ri-table-line me-1"></i> Deliverables Planning
                            <small class="text-muted ms-2 fw-normal">(Click any number to edit, auto-saves on Enter)</small>
                        </div>
                    </div>
                    <div class="card-body p-0" id="deliverableTableContainer">
                        <!-- Table rendered by JavaScript -->
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Loading deliverables...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/client-deliverable.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>