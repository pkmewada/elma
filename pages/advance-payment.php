<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .ap-badge {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.15rem 0.65rem;
        border-radius: 30px;
        white-space: nowrap;
    }
    .ap-badge.Pending   { background: rgba(var(--warning-rgb), 0.14); color: rgb(var(--warning-rgb)); }
    .ap-badge.Approved  { background: rgba(var(--info-rgb), 0.12);    color: rgb(var(--info-rgb)); }
    .ap-badge.Completed { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }
    .ap-badge.Cancelled { background: rgba(var(--danger-rgb), 0.10);  color: rgb(var(--danger-rgb)); }
    .ap-adj-badge {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.15rem 0.65rem;
        border-radius: 30px;
        white-space: nowrap;
    }
    .ap-adj-badge.not-adjusted { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
    .ap-adj-badge.upcoming     { background: rgba(var(--info-rgb), 0.12);      color: rgb(var(--info-rgb)); }
    .ap-adj-badge.partial      { background: rgba(var(--primary-rgb), 0.12);   color: rgb(var(--primary-rgb)); }
    .ap-badge.Deducted  { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }
    .ap-badge.Waived    { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
    .ap-progress {
        height: 6px;
        border-radius: 4px;
        background: var(--default-border);
        overflow: hidden;
        width: 100px;
    }
    .ap-progress span {
        display: block;
        height: 100%;
        background: rgb(var(--success-rgb));
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Advance Payment</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Payroll</li>
                    <li class="breadcrumb-item active" aria-current="page">Advance Payment</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="addAdvanceBtn">
                    <i class="ri-add-line me-1"></i> Add Advance
                </button>
            </div>
        </div>

        <!-- ==================== FILTERS ==================== -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Employee</label>
                        <select class="form-select form-select-sm" id="apEmployee">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Status</label>
                        <select class="form-select form-select-sm" id="apStatus">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Search</label>
                        <input type="text" class="form-control form-control-sm" id="apSearch" placeholder="Transaction / Reference no.">
                    </div>
                    <div class="col-6 col-md-3">
                        <button type="button" class="btn btn-light btn-sm w-100" id="apResetBtn">
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
                        <h5 class="card-title mb-0">Advance Payments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Transaction No</th>
                                        <th>Status</th>
                                        <th>Payment Adjustment</th>
                                        <th width="160">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="apTableBody">
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

<!-- ==================== ADD / EDIT / VIEW MODAL ==================== -->
<div class="modal fade" id="apModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="apModalTitle">Add Advance Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="apId" />

                <!-- ============ ADD / EDIT FORM (Pending only) ============ -->
                <div id="apFormWrap">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Employee <span class="text-danger">*</span></label>
                            <select class="form-select" id="apFormEmployee">
                                <option value="">Select employee</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount <span class="text-danger">*</span></label>
                            <input type="number" min="0.01" step="0.01" class="form-control" id="apAmount" placeholder="e.g. 15000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="apPaymentDate">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Payment Mode</label>
                            <select class="form-select" id="apPaymentMode">
                                <option value="">Select mode</option>
                                <option value="Bank Transfer">Bank Transfer</option>
                                <option value="UPI">UPI</option>
                                <option value="Cheque">Cheque</option>
                                <option value="Cash">Cash</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Transaction Number</label>
                            <input type="text" class="form-control" id="apTransactionNo" maxlength="100" placeholder="e.g. TXN00123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Reference Number</label>
                            <input type="text" class="form-control" id="apReferenceNo" maxlength="100" placeholder="e.g. Bank UTR / cheque no.">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Adjustment</label>
                            <select class="form-select" id="apAdjustment">
                                <option value="Not Adjusted">Not Adjusted</option>
                                <option value="Upcoming Salary">Upcoming Salary</option>
                                <option value="Partial Payment">Partial Payment / EMI</option>
                            </select>
                            <div class="form-text">Not Adjusted: no salary deduction. Upcoming Salary: full amount deducted next payroll. Partial Payment: HR-defined recovery plan.</div>
                        </div>

                        <!-- Partial Payment sub-fields -->
                        <div class="col-md-4 ap-partial-field d-none">
                            <label class="form-label">Number of Months <span class="text-danger">*</span></label>
                            <input type="number" min="1" max="12" class="form-control" id="apPartialMonths" placeholder="1-12">
                        </div>
                        <div class="col-md-4 ap-partial-field d-none">
                            <label class="form-label">Monthly Deduction Amount <span class="text-danger">*</span></label>
                            <input type="number" min="0.01" step="0.01" class="form-control" id="apPartialMonthlyAmount" placeholder="e.g. 8000">
                        </div>
                        <div class="col-md-4 ap-partial-field d-none">
                            <label class="form-label">First Salary Month <span class="text-danger">*</span></label>
                            <input type="month" class="form-control" id="apPartialFirstMonth">
                        </div>

                        <div class="col-12">
                            <label class="form-label">Remark</label>
                            <textarea class="form-control" id="apRemark" rows="3" placeholder="Optional note about this advance"></textarea>
                        </div>
                    </div>
                </div>

                <!-- ============ VIEW DETAILS (read-only) ============ -->
                <!-- Deliberately grouped 2-per-row (not copied from the
                     edit form's field widths) so every row lines up:
                     who/how much, current state, when/how paid, refs. -->
                <div id="apViewDetails" class="d-none">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Employee</div>
                            <div class="fw-semibold" id="apViewEmployee">--</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Amount</div>
                            <div class="fw-semibold" id="apViewAmount">--</div>
                        </div>

                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Status</div>
                            <div id="apViewStatus">--</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Payment Adjustment</div>
                            <div id="apViewAdjustment">--</div>
                        </div>

                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Payment Date</div>
                            <div class="fw-semibold" id="apViewPaymentDate">--</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Payment Mode</div>
                            <div class="fw-semibold" id="apViewPaymentMode">--</div>
                        </div>

                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Transaction No</div>
                            <div class="fw-semibold" id="apViewTransactionNo">--</div>
                        </div>
                        <div class="col-md-6">
                            <div class="fs-12 text-muted mb-1">Reference No</div>
                            <div class="fw-semibold" id="apViewReferenceNo">--</div>
                        </div>

                        <div class="col-12">
                            <div class="fs-12 text-muted mb-1">Remark</div>
                            <div class="fw-semibold" id="apViewRemark">--</div>
                        </div>

                        <div class="col-12 ap-partial-view d-none">
                            <hr class="my-1">
                        </div>
                        <div class="col-12 ap-partial-view d-none">
                            <div class="fs-12 text-muted mb-1">Recovery Plan</div>
                            <div class="fw-semibold" id="apViewPartialSummary">--</div>
                        </div>
                    </div>

                    <div class="mt-4 ap-partial-view d-none">
                        <h6 class="fs-13 fw-semibold mb-2">Repayment Schedule</h6>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr><th>Salary Month</th><th>Scheduled Amount</th><th>Deducted Amount</th><th>Status</th></tr>
                                </thead>
                                <tbody id="apScheduleBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveApBtn">Save Advance</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/hrms-advance-payment.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
