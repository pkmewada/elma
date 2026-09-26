<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .el-badge {
        display: inline-block;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 0.15rem 0.65rem;
        border-radius: 30px;
        white-space: nowrap;
    }
    .el-badge.Pending   { background: rgba(var(--warning-rgb), 0.14); color: rgb(var(--warning-rgb)); }
    .el-badge.Approved  { background: rgba(var(--info-rgb), 0.12);    color: rgb(var(--info-rgb)); }
    .el-badge.Completed { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }
    .el-badge.Cancelled { background: rgba(var(--danger-rgb), 0.10);  color: rgb(var(--danger-rgb)); }
    .el-badge.Deducted  { background: rgba(var(--success-rgb), 0.12); color: rgb(var(--success-rgb)); }
    .el-badge.Waived    { background: rgba(var(--secondary-rgb), 0.12); color: rgb(var(--secondary-rgb)); }
    .el-progress {
        height: 6px;
        border-radius: 4px;
        background: var(--default-border);
        overflow: hidden;
        width: 100px;
    }
    .el-progress span {
        display: block;
        height: 100%;
        background: rgb(var(--success-rgb));
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Employee Loan</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Payroll</li>
                    <li class="breadcrumb-item active" aria-current="page">Employee Loan</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-primary btn-sm" id="addLoanBtn">
                    <i class="ri-add-line me-1"></i> Add Loan
                </button>
            </div>
        </div>

        <!-- ==================== FILTERS ==================== -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Employee</label>
                        <select class="form-select form-select-sm" id="elEmployee">
                            <option value="">All Employees</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Status</label>
                        <select class="form-select form-select-sm" id="elStatus">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label fs-12 mb-1">Search</label>
                        <input type="text" class="form-control form-control-sm" id="elSearch" placeholder="Employee name">
                    </div>
                    <div class="col-6 col-md-3">
                        <button type="button" class="btn btn-light btn-sm w-100" id="elResetBtn">
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
                        <h5 class="card-title mb-0">Employee Loans</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Loan Amount</th>
                                        <th>Duration</th>
                                        <th>Monthly Deduction</th>
                                        <th>Recovered</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th width="180">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="elTableBody">
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

<!-- ==================== ADD / EDIT / VIEW MODAL ==================== -->
<div class="modal fade" id="elModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="elModalTitle">Add Loan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="elId" />

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Employee <span class="text-danger">*</span></label>
                        <select class="form-select" id="elFormEmployee"></select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Loan Amount <span class="text-danger">*</span></label>
                        <input type="number" min="0.01" step="0.01" class="form-control" id="elLoanAmount">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Loan Start Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="elStartDate">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Duration (months) <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="12" class="form-control" id="elDuration" placeholder="1-12">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Monthly Deduction</label>
                        <input type="number" min="0" step="0.01" class="form-control" id="elMonthlyDeduction" placeholder="Auto-calculated if left blank">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Remark</label>
                        <textarea class="form-control" id="elRemark" rows="3"></textarea>
                    </div>
                </div>

                <div class="mt-3 d-none" id="elScheduleWrap">
                    <h6 class="fs-13 fw-semibold mb-2">Repayment Schedule</h6>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr><th>Month</th><th>Amount</th><th>Status</th></tr>
                            </thead>
                            <tbody id="elScheduleBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveElBtn">Save Loan</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/hrms-loan-management.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
