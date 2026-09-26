<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Follow Up List</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="leads">Lead Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Follow Up List</li>
                </ol>
            </div>
        </div>

        <!-- ==================== FILTERS ==================== -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-12 mb-1">Status</label>
                        <select class="form-select form-select-sm" id="flStatus">
                            <option value="Pending" selected>Pending</option>
                            <option value="Completed">Completed</option>
                            <option value="Skipped">Skipped</option>
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-12 mb-1">From</label>
                        <input type="date" class="form-control form-control-sm" id="flDateFrom">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-12 mb-1">To</label>
                        <input type="date" class="form-control form-control-sm" id="flDateTo">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Search</label>
                        <input type="text" class="form-control form-control-sm" id="flSearch" placeholder="Lead name or phone">
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="button" class="btn btn-light btn-sm w-100" id="flResetBtn">
                            <i class="ri-refresh-line me-1"></i> Reset
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== LIST ==================== -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Pending Follow Ups</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Lead Name</th>
                                        <th>Phone</th>
                                        <th>Lead Source</th>
                                        <th>Assigned To</th>
                                        <th>Follow Up Date</th>
                                        <th>#</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th width="180">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="followUpListTableBody">
                                    <tr><td colspan="9" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ==================== ADD NOTE MODAL (reuses api/leads/saveLeadRemark.php) ==================== -->
<div class="modal fade" id="followUpNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Follow Up Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="fnLeadId" />
                <div class="fw-semibold mb-3" id="fnLeadName"></div>
                <div>
                    <label class="form-label">Note</label>
                    <textarea class="form-control" id="fnRemark" rows="4"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFollowUpNoteBtn">Save Note</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/lead-follow-up-list.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
