<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$employees = [];

$employeeStmt = mysqli_prepare(
    $con,
    "SELECT
        id,
        employeeCode,
        fullName,
        departmentName,
        designationName
     FROM employeeusers
     WHERE employmentStatus = 'Active'
     ORDER BY fullName ASC"
);

if ($employeeStmt) {
    mysqli_stmt_execute($employeeStmt);
    $employeeResult = mysqli_stmt_get_result($employeeStmt);

    while ($row = mysqli_fetch_assoc($employeeResult)) {
        $employees[] = $row;
    }

    mysqli_stmt_close($employeeStmt);
}

$periodStart = date('Y-m-01');
$periodEnd = date('Y-m-t');

// Current Month Salary Slip Status is really about reviewing LAST month's
// payroll run before it's too late -- only relevant during the first 10
// days of the month, generating the previous calendar month's slip.
$showSalarySlipStatus = ((int)date('j')) <= 10;
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<style>
.salary-slip-summary-card {
    border: 1px solid rgba(0, 0, 0, 0.06);
    border-radius: 12px;
    padding: 1rem;
    background: #fff;
    height: 100%;
}

.salary-slip-summary-card span {
    color: #6b7280;
    font-size: 12px;
}

.salary-slip-summary-card strong {
    display: block;
    font-size: 20px;
    margin-top: 4px;
}

.salary-slip-print-area {
    background: #fff;
    border-radius: 12px;
}

.salary-slip-company-title {
    letter-spacing: 0.04em;
}

.salary-slip-table th,
.salary-slip-table td {
    vertical-align: middle;
}

.salary-slip-netpay {
    background: rgba(var(--primary-rgb), 0.08);
    border: 1px solid rgba(var(--primary-rgb), 0.18);
    border-radius: 12px;
    padding: 1rem;
}

@media print {
    body * {
        visibility: hidden;
    }

    #salarySlipPrintArea,
    #salarySlipPrintArea * {
        visibility: visible;
    }

    #salarySlipPrintArea {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        box-shadow: none !important;
        border: 0 !important;
    }

    .app-sidebar,
    .app-header,
    .salary-slip-actions,
    .salary-slip-filter-card {
        display: none !important;
    }
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Salary Slip</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Salary Slip</li>
                </ol>
            </div>
        </div>

        <div class="card custom-card salary-slip-filter-card">
            <div class="card-header">
                <h5 class="mb-0">Generate Salary Slip</h5>
            </div>

            <div class="card-body">
                <form id="salarySlipForm" class="row g-3" autocomplete="off">
                    <div class="col-xl-4 col-md-6">
                        <label class="form-label" for="employeeId">Employee</label>
                        <select id="employeeId" class="form-select" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= (int)$employee['id']; ?>">
                                    <?= htmlspecialchars(
                                        trim(
                                            (string)($employee['fullName'] ?? '') .
                                            (
                                                !empty($employee['employeeCode'])
                                                    ? ' - ' . $employee['employeeCode']
                                                    : ''
                                            )
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <label class="form-label" for="periodStart">Period Start</label>
                        <input type="date" class="form-control" id="periodStart" value="<?= $periodStart; ?>" required>
                    </div>

                    <div class="col-xl-3 col-md-6">
                        <label class="form-label" for="periodEnd">Period End</label>
                        <input type="date" class="form-control" id="periodEnd" value="<?= $periodEnd; ?>" required>
                    </div>

                    <div class="col-xl-2 col-md-6 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100" id="generateSalarySlipBtn">
                            Generate
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div id="salarySlipEmptyState" class="card custom-card">
            <div class="card-body text-center py-5">
                <i class="ri-file-list-3-line fs-1 text-muted"></i>
                <h5 class="mt-3 mb-1">No salary slip generated</h5>
                <p class="text-muted mb-0">Select an employee and payroll period to preview salary details.</p>
            </div>
        </div>

        <div id="salarySlipResult" class="d-none">
            <div class="row g-3 mb-3">
                <div class="col-xl-3 col-md-6">
                    <div class="salary-slip-summary-card">
                        <span>Gross Earnings</span>
                        <strong id="grossEarningsSummary">Rs. 0</strong>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="salary-slip-summary-card">
                        <span>Total Deductions</span>
                        <strong id="deductionSummary">Rs. 0</strong>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="salary-slip-summary-card">
                        <span>Net Pay</span>
                        <strong id="netPaySummary">Rs. 0</strong>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="salary-slip-summary-card">
                        <span>Payable Days</span>
                        <strong id="payableDaysSummary">0</strong>
                    </div>
                </div>
            </div>

            <div class="card custom-card salary-slip-print-area" id="salarySlipPrintArea">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 border-bottom pb-3 mb-3">
                        <div>
                            <h4 class="salary-slip-company-title mb-1">SALARY SLIP</h4>
                            <p class="text-muted mb-0" id="slipPeriodLabel">Payroll Period</p>
                        </div>

                        <div class="salary-slip-actions">
                            <button type="button" class="btn btn-success me-2" id="submitSalarySlipApprovalBtn">
                                <i class="ri-send-plane-line me-1"></i>
                                Submit for Approval
                            </button>

                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="ps-0">Employee</th>
                                        <td id="employeeNameCell">--</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Employee Code</th>
                                        <td id="employeeCodeCell">--</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Department</th>
                                        <td id="departmentCell">--</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody>
                                    <tr>
                                        <th class="ps-0">Designation</th>
                                        <td id="designationCell">--</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Joining Date</th>
                                        <td id="joiningDateCell">--</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Effective Start</th>
                                        <td id="effectiveStartCell">--</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Payable Days</th>
                                        <td id="payableDaysCell">--</td>
                                    </tr>
                                    <tr>
                                        <th class="ps-0">Paid Days</th>
                                        <td id="paidDaysAfterLeaveCell">--</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-lg-6">
                            <h6 class="mb-3">Earnings</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered salary-slip-table">
                                    <thead>
                                        <tr>
                                            <th>Particulars</th>
                                            <th class="text-end">Master</th>
                                            <th class="text-end">Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody id="earningsTableBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Gross Earnings</th>
                                            <th></th>
                                            <th class="text-end" id="grossEarningsCell">Rs. 0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <h6 class="mb-3">Deductions</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered salary-slip-table">
                                    <tbody id="deductionsTableBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total Deductions</th>
                                            <th class="text-end" id="totalDeductionsCell">Rs. 0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="salary-slip-netpay mt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="text-muted">Net Payable Amount</span>
                            <h4 class="mb-0" id="netPayCell">Rs. 0</h4>
                        </div>
                        <div class="text-muted small" id="netPayWords">Generated from payroll constants</div>
                    </div>

                    <div class="row g-4 mt-2">
                        <div class="col-lg-6">
                            <h6 class="mb-3">Reimbursements</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered salary-slip-table">
                                    <tbody id="reimbursementsTableBody"></tbody>
                                    <tfoot>
                                        <tr>
                                            <th>Total Reimbursements</th>
                                            <th class="text-end" id="totalReimbursementsCell">Rs. 0</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="col-lg-6">
                            <h6 class="mb-3">Attendance Summary</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered salary-slip-table">
                                    <tbody id="attendanceTableBody"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12">
                            <h6 class="mb-3">Leave Summary</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered salary-slip-table">
                                    <tbody id="leaveTableBody"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($showSalarySlipStatus): ?>
        <div class="card custom-card mt-4">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Current Month Salary Slip Status</h5>
                <button type="button" class="btn btn-sm btn-primary" id="refreshSalarySlipStatusBtn">
                    <i class="ri-refresh-line"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Status</th>
                                <th class="text-end">Net Pay</th>
                                <th>Remark / PDF</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="salarySlipStatusBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">Loading salary slip status...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {
    // Only present in the DOM during the 1st-10th (see $showSalarySlipStatus
    // in the PHP above) -- guard so we don't fire a pointless request the
    // rest of the month.
    if ($('#salarySlipStatusBody').length) {
        loadSalarySlipMonthStatus();
    }

    $('#salarySlipForm').on('submit', function(e) {
        e.preventDefault();
        generateSalarySlip();
    });

    $('#submitSalarySlipApprovalBtn').on('click', submitSalarySlipForApproval);
    $('#refreshSalarySlipStatusBtn').on('click', loadSalarySlipMonthStatus);

    // Generate, from this status section, must always target the previous
    // calendar month -- never whatever happens to be in the manual
    // "Generate Salary Slip" form's own date pickers above (which default
    // to the current month for ad-hoc use), so the current month can never
    // be generated by accident from here.
    $(document).on('click', '.generate-slip-for-employee', function() {
        const range = getPreviousMonthRange();
        $('#employeeId').val($(this).data('employee-id'));
        $('#periodStart').val(range.start);
        $('#periodEnd').val(range.end);
        $('#salarySlipForm').trigger('submit');
        window.scrollTo({top: 0, behavior: 'smooth'});
    });
});

function getPreviousMonthRange() {
    function fmt(d) {
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return d.getFullYear() + '-' + mm + '-' + dd;
    }

    const now = new Date();
    const lastOfPrevMonth = new Date(now.getFullYear(), now.getMonth(), 0);
    const firstOfPrevMonth = new Date(lastOfPrevMonth.getFullYear(), lastOfPrevMonth.getMonth(), 1);

    return { start: fmt(firstOfPrevMonth), end: fmt(lastOfPrevMonth) };
}

function escapeHtml(value) {
    return $('<div>').text(value === null || value === undefined ? '' : value).html();
}

function money(value) {
    return 'Rs. ' + Number(value || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function numberValue(value) {
    return Number(value || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    });
}

function formatDate(value) {
    if (!value) {
        return '--';
    }

    const date = new Date(value + 'T00:00:00');

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric'
    });
}

function renderAmountRows(rows) {
    return rows.map(function(row) {
        return `
            <tr>
                <td>${escapeHtml(row.label)}</td>
                <td class="text-end">${money(row.value ?? row.amount)}</td>
            </tr>
        `;
    }).join('');
}

function renderEarningRows(rows) {
    return rows.map(function(row) {
        return `
            <tr>
                <td>${escapeHtml(row.label)}</td>
                <td class="text-end">${money(row.master)}</td>
                <td class="text-end">${money(row.amount)}</td>
            </tr>
        `;
    }).join('');
}

function buildLeaveDeductionRows(leave, deductions) {
    // Breaks the single "Leave Deduction" total into the same per-category
    // day counts already computed server-side (PayrollEngine::getLeaveSummary()),
    // so HR can see paid-vs-unpaid leave directly in the Deductions section
    // instead of only an opaque lump sum. A category is only shown when its
    // day count is greater than zero, so this stays empty (no rows) for an
    // employee with no leave impact that period. Paid leave within the
    // monthly entitlement is shown even though its amount is normally 0 --
    // that's the point: it confirms those days were taken and are NOT
    // reducing pay.
    const categories = [
        {days: leave.paidLeaveCoveredDays, amount: deductions.paidLeaveCoveredAmount, label: 'Paid Leave (Within Entitlement)'},
        {days: leave.approvedPaidLeaveExcessDays, amount: deductions.excessPaidLeaveAmount, label: 'Excess Un-Paid Leave (Beyond Entitlement)'},
        {days: leave.approvedUnpaidLeaveDays, amount: deductions.unpaidLeaveAmount, label: 'Unpaid Leave'},
        {days: leave.probationLeaveDays, amount: deductions.probationLeaveAmount, label: 'Probation Period Leave'},
        {days: leave.noticeLeaveDays, amount: deductions.noticeLeaveAmount, label: 'Notice Period Leave'},
        {days: leave.informedLeaveDays, amount: deductions.informedLeaveAmount, label: 'Pending Leave (Informed)'},
        {days: leave.uninformedLeaveDays, amount: deductions.uninformedLeaveAmount, label: 'Absent / Uninformed Leave'},
    ];

    return categories
        .filter(function(category) { return Number(category.days || 0) > 0; })
        .map(function(category) {
            return {
                label: `${category.label} (${numberValue(category.days)} ${Number(category.days) === 1 ? 'Day' : 'Days'})`,
                amount: category.amount
            };
        });
}

function renderMetricRows(rows) {
    return rows.map(function(row) {
        return `
            <tr>
                <td>${escapeHtml(row.label)}</td>
                <td class="text-end">${escapeHtml(row.value)}</td>
            </tr>
        `;
    }).join('');
}

function payrollAlert(type, message) {
    if (window.Swal) {
        Swal.fire({
            icon: type,
            text: message
        });
        return;
    }

    window.showToast && window.showToast(type === 'error' ? 'danger' : type, message);
}

function statusBadge(status) {
    const labels = {
        not_created: 'Not Created',
        pending: 'Pending',
        approved: 'Approved',
        rejected: 'Rejected'
    };

    const colors = {
        not_created: 'secondary',
        pending: 'warning',
        approved: 'success',
        rejected: 'danger'
    };

    const color = colors[status] || 'secondary';

    return `
        <span class="btn btn-sm btn-outline-${color}">
            ${escapeHtml(labels[status] || status || '-')}
        </span>
    `;
}

function bindSalarySlip(data) {
    const employee = data.employee || {};
    const period = data.period || {};
    const earnings = data.earnings || {};
    const deductions = data.deductions || {};
    const reimbursements = data.reimbursements || {};
    const attendance = data.attendance || {};
    const leave = data.leave || {};
    const points = data.points || {};
    const netFormula = data.netFormula || {};

    $('#salarySlipEmptyState').addClass('d-none');
    $('#salarySlipResult').removeClass('d-none');

    $('#grossEarningsSummary, #grossEarningsCell').text(money(earnings.grossEarnings));
    $('#deductionSummary, #totalDeductionsCell').text(money(deductions.totalDeductions));
    $('#netPaySummary, #netPayCell').text(money(data.netPay));
    $('#totalReimbursementsCell').text(money(reimbursements.totalReimbursements));
    $('#payableDaysSummary, #payableDaysCell').text(numberValue(period.payableDays));
    $('#paidDaysAfterLeaveCell').text(numberValue(period.paidDaysAfterLeave));
    $('#effectiveStartCell').text(formatDate(period.effectiveStart || period.start));

    $('#slipPeriodLabel').text(`${formatDate(period.start)} to ${formatDate(period.end)}`);
    $('#employeeNameCell').text(employee.fullName || '--');
    $('#employeeCodeCell').text(employee.employeeCode || '--');
    $('#departmentCell').text(employee.departmentName || '--');
    $('#designationCell').text(employee.designationName || '--');
    $('#joiningDateCell').text(formatDate(employee.joiningDate));

    // Only the editable "Other Earning"/"Other Allowances" rows are hidden
    // at zero -- every other earnings row (Basic, HRA, Special Allowance,
    // ...) keeps its existing always-shown behavior, unchanged.
    var hideIfZeroLabels = ['Other Earning 1', 'Other Earning 2', 'Other Earning 3', 'Other Allowances'];
    var visibleEarningRows = (earnings.rows || []).filter(function(row) {
        return hideIfZeroLabels.indexOf(row.label) === -1 || Number(row.amount || 0) > 0;
    });
    $('#earningsTableBody').html(renderEarningRows(visibleEarningRows));

    const statutoryDeductionRows = deductions.rows || [];
    const leaveDeductionRows = buildLeaveDeductionRows(leave, deductions);
    const operationalDeductionRows = [
        {label: `Training Hold (${numberValue(data.settings.trainingHoldDays)} Days)`,amount: deductions.trainingHoldDeduction},
        {label: 'Half Day Deduction', amount: deductions.halfDayDeduction},
        {label: 'Manual Deduction', amount: deductions.manualDeduction},
        {label: 'Fixed Employee Deduction', amount: deductions.fixedEmployeeDeduction},
        {label: `Point Deduction (${numberValue(points.impactPoints)} pts / ${numberValue(points.hitCount)} hits)`, amount: deductions.pointDeduction},
    ];
    // renderAmountRows() shows every row it's given unconditionally (same
    // as the rows above), so unlike those, Advance/Loan are only added to
    // the array at all when applicable -- most employees have neither, and
    // a permanent "Rs. 0.00" line would be noise on every slip.
    const conditionalDeductionRows = [
        {label: 'Advance Payment Deduction', amount: deductions.advanceDeduction},
        {label: 'Loan Payment Deduction', amount: deductions.loanDeduction},
    ].filter(function(row) { return Number(row.amount || 0) > 0; });

    $('#deductionsTableBody').html(renderAmountRows(statutoryDeductionRows.concat(leaveDeductionRows).concat(operationalDeductionRows).concat(conditionalDeductionRows)));
    $('#reimbursementsTableBody').html(renderAmountRows(reimbursements.rows || []));
    $('#netPayWords').text(
        `${money(netFormula.grossEarnings)} - ${money(netFormula.totalDeductions)} + ${money(netFormula.totalReimbursements)}`
    );

    $('#attendanceTableBody').html(renderMetricRows([
        {label: 'Present Days', value: numberValue(attendance.presentDays)},
        {label: 'Half Days', value: numberValue(attendance.halfDays)},
        {label: 'Absent Days', value: numberValue(attendance.absentDays)},
        {label: 'Late Days', value: numberValue(attendance.lateDays)},
        {label: 'Working Hours', value: numberValue((attendance.workingSeconds || 0) / 3600)}
    ]));

    $('#leaveTableBody').html(renderMetricRows([
        {label: 'Monthly Paid Leave Allotted', value: numberValue(leave.monthlyPaidLeaveDays)},
        {label: 'Carry Forward Paid Leave', value: numberValue(leave.paidLeaveCarryForwardDays)},
        {label: 'Available Paid Leave', value: numberValue(leave.paidLeaveAvailableDays)},
        {label: 'Approved Paid Leave', value: numberValue(leave.approvedPaidLeaveDays)},
        {label: 'Birthday Paid Leave', value: numberValue(leave.birthdayPaidLeaveDays)},
        {label: 'Quota Paid Leave', value: numberValue(leave.quotaPaidLeaveDays)},
        {label: 'Paid Leave Covered', value: numberValue(leave.paidLeaveCoveredDays)},
        {label: 'Excess Un-Paid Leave', value: numberValue(leave.approvedPaidLeaveExcessDays)},
        {label: 'Paid Leave Remaining', value: numberValue(leave.paidLeaveRemainingDays)},
        {label: 'Approved Unpaid Leave', value: numberValue(leave.approvedUnpaidLeaveDays)},
        {label: 'Probation Leave', value: numberValue(leave.probationLeaveDays)},
        {label: 'Notice Period Leave', value: numberValue(leave.noticeLeaveDays)},
        {label: 'Informed Leave', value: numberValue(leave.informedLeaveDays)},
        {label: 'Uninformed Leave', value: numberValue(leave.uninformedLeaveDays)}
    ]));
}

function submitSalarySlipForApproval() {
    const employeeId = $('#employeeId').val();
    const periodStart = $('#periodStart').val();
    const periodEnd = $('#periodEnd').val();

    if (!employeeId || !periodStart || !periodEnd || $('#salarySlipResult').hasClass('d-none')) {
        payrollAlert('warning', 'Please generate a salary slip first.');
        return;
    }

    if (periodEnd < periodStart) {
        payrollAlert('warning', 'Period end must be after period start.');
        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Submit for approval?',
        text: 'Super Admin will review this salary slip before PDF generation.',
        showCancelButton: true,
        confirmButtonText: 'Submit',
        cancelButtonText: 'Cancel'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }

        const $btn = $('#submitSalarySlipApprovalBtn');

        $btn
            .prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-2"></span>Submitting...');

        $.ajax({
            url: API_BASE + '/payroll/submitSalarySlipApproval.php',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({
                employeeId,
                periodStart,
                periodEnd
            }),
            success: function(res) {
                payrollAlert(res && res.success ? 'success' : 'error', res && res.message ? res.message : 'Unable to submit salary slip.');

                if (res && res.success) {
                    loadSalarySlipMonthStatus();
                }
            },
            error: function() {
                payrollAlert('error', 'Unable to submit salary slip.');
            },
            complete: function() {
                $btn
                    .prop('disabled', false)
                    .html('<i class="ri-send-plane-line me-1"></i>Submit for Approval');
            }
        });
    });
}

function generateSalarySlip() {
    const employeeId = $('#employeeId').val();
    const periodStart = $('#periodStart').val();
    const periodEnd = $('#periodEnd').val();

    if (!employeeId || !periodStart || !periodEnd) {
        payrollAlert('warning', 'Please select employee and payroll period.');
        return;
    }

    if (periodEnd < periodStart) {
        payrollAlert('warning', 'Period end must be after period start.');
        return;
    }

    const $btn = $('#generateSalarySlipBtn');

    $btn
        .prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-2"></span>Generating...');

    $.ajax({
        url: API_BASE + '/payroll/calculatePayrollPreview.php',
        type: 'GET',
        dataType: 'json',
        data: {
            employeeId,
            periodStart,
            periodEnd
        },
        success: function(res) {
            if (!res || !res.success) {
                payrollAlert('error', res && res.message ? res.message : 'Unable to calculate salary slip.');
                return;
            }

            bindSalarySlip(res.data || {});
            payrollAlert('success', 'Salary slip generated.');
        },
        error: function() {
            payrollAlert('error', 'Unable to calculate salary slip.');
        },
        complete: function() {
            $btn
                .prop('disabled', false)
                .text('Generate');
        }
    });
}

function renderSalarySlipStatusRows(rows) {
    if (!rows.length) {
        $('#salarySlipStatusBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No active employees found.</td></tr>');
        return;
    }

    $('#salarySlipStatusBody').html(rows.map(function(row) {
        const pdfLink = row.status === 'approved' && row.pdfPath
            ? `<a href="${BASE_URL}/${escapeHtml(row.pdfPath)}" target="_blank" class="btn btn-sm btn-outline-primary"><i class="ri-file-pdf-2-line"></i></a>`
            : '<span class="text-muted">--</span>';
        const action = ['not_created', 'rejected'].includes(row.status)
            ? `<button type="button" class="btn btn-sm btn-primary generate-slip-for-employee" data-employee-id="${row.employeeId}">Generate</button>`
            : '<span class="text-muted">--</span>';

        return `
            <tr>
                <td>
                    <div class="fw-semibold">${escapeHtml(row.fullName)}</div>
                    <div class="text-muted small">${escapeHtml(row.employeeCode || '')}</div>
                </td>
                <td>${escapeHtml(row.departmentName || '--')}</td>
                <td>${escapeHtml(row.designationName || '--')}</td>
                <td>${statusBadge(row.status)}</td>
                <td class="text-end">${row.netPay ? money(row.netPay) : '--'}</td>
                <td>
                    ${pdfLink}
                    ${row.rejectionRemark ? `<div class="small text-danger mt-1">${escapeHtml(row.rejectionRemark)}</div>` : ''}
                </td>
                <td class="text-end">${action}</td>
            </tr>
        `;
    }).join(''));
}

function loadSalarySlipMonthStatus() {
    // Always the previous calendar month -- independent of whatever the
    // manual "Generate Salary Slip" form's own date pickers currently hold.
    const range = getPreviousMonthRange();

    $('#salarySlipStatusBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading salary slip status...</td></tr>');

    $.getJSON(API_BASE + '/payroll/getSalarySlipMonthStatus.php', {
        periodStart: range.start,
        periodEnd: range.end
    }).done(function(res) {
        if (!res || !res.success) {
            payrollAlert('error', res && res.message ? res.message : 'Unable to load salary slip status.');
            return;
        }

        renderSalarySlipStatusRows(res.data.salarySlips || []);
    }).fail(function() {
        payrollAlert('error', 'Unable to load salary slip status.');
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
