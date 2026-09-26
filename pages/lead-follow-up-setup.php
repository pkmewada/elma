<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Follow Up Setup</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="leads">Lead Management</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Follow Up Setup</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-wave" id="addFollowUpRuleBtn">
                    <i class="ri-add-line me-1"></i> Add Follow Up Rule
                </button>
            </div>
        </div>

        <p class="text-muted mb-3">
            Define how many days after a lead is created each follow up should be due. New leads automatically get
            these follow ups scheduled on creation.
        </p>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Follow Up Rules</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Day After Lead Created</th>
                                        <th>Follow Up Number</th>
                                        <th>Action Type</th>
                                        <th>Status</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="followUpRulesTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ==================== ADD / EDIT RULE MODAL ==================== -->
<div class="modal fade" id="followUpRuleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="followUpRuleModalTitle">Add Follow Up Rule</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="followUpRuleId" />

                <div class="mb-3">
                    <label class="form-label"> Day After Lead Created </label>
                    <input type="number" min="1" class="form-control" id="followUpDayNumber" placeholder="e.g. 1" />
                    <div class="form-text">Number of days after the lead is created that this follow up is due.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label"> Follow Up Number </label>
                    <input type="number" min="1" class="form-control" id="followUpSequence" placeholder="e.g. 1" />
                    <div class="form-text">Order of this follow up (1st, 2nd, 3rd...).</div>
                </div>

                <div class="mb-3">
                    <label class="form-label"> Action Type </label>
                    <select class="form-select" id="followUpType">
                        <option value="Call">Call</option>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Email">Email</option>
                        <option value="Meeting">Meeting</option>
                    </select>
                </div>

                <div>
                    <label class="form-label"> Status </label>
                    <select class="form-select" id="followUpRuleStatus">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveFollowUpRuleBtn">Save Rule</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/lead-follow-up-setup.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
