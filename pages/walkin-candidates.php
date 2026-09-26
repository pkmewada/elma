<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Walk-in Candidates</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Recruitment</li>
                    <li class="breadcrumb-item active" aria-current="page">Walk-in Candidates</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="addWalkInBtn">
                    <i class="ri-add-line me-1"></i> Add Candidate
                </button>
            </div>
        </div>

        <!-- ==================== FILTERS ==================== -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-12 mb-1">Status</label>
                        <select class="form-select form-select-sm" id="wicStatus">
                            <option value="">All Status</option>
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Selected">Selected</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-12 mb-1">From</label>
                        <input type="date" class="form-control form-control-sm" id="wicDateFrom">
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label fs-12 mb-1">To</label>
                        <input type="date" class="form-control form-control-sm" id="wicDateTo">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Search</label>
                        <input type="text" class="form-control form-control-sm" id="wicSearch" placeholder="Name, phone or position">
                    </div>
                    <div class="col-6 col-md-2">
                        <button type="button" class="btn btn-light btn-sm w-100" id="wicResetBtn">
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
                        <h5 class="card-title mb-0">Walk-in Records</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Candidate</th>
                                        <th>Phone</th>
                                        <th>Position Applied</th>
                                        <th>Interview Date</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Handled By</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="wicTableBody">
                                    <tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ==================== ADD / EDIT MODAL ==================== -->
<div class="modal fade" id="wicModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="wicModalTitle">Add Walk-in Candidate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="wicId" />

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Candidate Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wicCandidateName">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wicPhone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" id="wicEmail">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Position Applied For <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="wicPositionApplied">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Interview Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="wicInterviewDate">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Interview Time</label>
                        <input type="time" class="form-control" id="wicInterviewTime">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Source</label>
                        <input type="text" class="form-control" id="wicSource" placeholder="e.g. Walk-in, Referral, Naukri">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="wicStatusInput">
                            <option value="Scheduled">Scheduled</option>
                            <option value="Completed">Completed</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Selected">Selected</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Handled By</label>
                        <select class="form-select" id="wicHandledBy">
                            <option value="">Select Employee</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" id="wicRemarks" rows="3"></textarea>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveWicBtn">Save Candidate</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/hrms-walkin.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
