<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';
?>

<?php include __DIR__ . '/../includes/emp-header.php'; ?>

<style>
.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
}
.status-chip-success { color: rgb(var(--success-rgb)); border-color: rgba(var(--success-rgb), 0.3); }
.status-chip-info { color: rgb(var(--info-rgb)); border-color: rgba(var(--info-rgb), 0.3); }
.status-chip-warning { color: rgb(var(--warning-rgb)); border-color: rgba(var(--warning-rgb), 0.3); }
.status-chip-danger { color: rgb(var(--danger-rgb)); border-color: rgba(var(--danger-rgb), 0.3); }
</style>

<?php include __DIR__ . '/../includes/emp-sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">My Assets</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="emp-dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">My Assets</li>
                </ol>
            </div>
            <div>
                <button type="button" class="btn btn-primary btn-wave waves-effect waves-light" data-bs-toggle="modal" data-bs-target="#requestAssetModal">
                    <i class="ri-add-line align-middle me-1"></i>Request Asset
                </button>
            </div>
        </div>

        <!-- MY ASSETS -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">Assigned Assets</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Asset Type</th>
                                        <th>Quantity</th>
                                        <th>Assigned Date</th>
                                        <th>Purpose</th>
                                        <th>Expected Return Date</th>
                                        <th>Status</th>
                                        <th>Return / Condition</th>
                                    </tr>
                                </thead>
                                <tbody id="myAssetsTableBody">
                                    <tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- MY REQUESTS -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">My Asset Requests</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Asset</th>
                                        <th>Quantity</th>
                                        <th>Purpose</th>
                                        <th>Expected Return Date</th>
                                        <th>Requested On</th>
                                        <th>Status</th>
                                        <th>Remark</th>
                                    </tr>
                                </thead>
                                <tbody id="myRequestsTableBody">
                                    <tr><td colspan="7" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- REQUEST ASSET MODAL -->
<div class="modal fade" id="requestAssetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form id="requestAssetForm">

                <div class="modal-header">
                    <h5 class="modal-title">Request Asset</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Select Asset</label>
                        <select name="assetId" id="requestAssetDropdown" class="form-select" required>
                            <option value="">Loading...</option>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Expected Return Date</label>
                            <input type="date" name="expectedReturnDate" class="form-control">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Purpose</label>
                            <input type="text" name="purpose" class="form-control" placeholder="e.g. Shoot, Outside work, Office purpose">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="2"></textarea>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitRequestBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="submitRequestSpinner"></span>
                        <span id="submitRequestText">Submit Request</span>
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/emp-my-assets.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
