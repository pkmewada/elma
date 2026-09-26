<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission-helper.php';

if (!isLoggedInUserSuperAdmin()) {
    header('Location: ' . BASE_URL . '/permission-denied?from=' . urlencode('/salary-slip-approval'));
    exit;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Salary Slip Approval</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Salary Slip Approval</li>
                </ol>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Approval Queue</h5>

                <div class="d-flex gap-2">
                    <input type="month" class="form-control form-control-sm" id="filterMonth">
                    <select class="form-select form-select-sm" id="filterStatus">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <button type="button" class="btn btn-sm btn-primary" id="refreshSalarySlipApprovalsBtn">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="text-end">Net Pay</th>
                                <th class="text-end">Paid</th>
                                <th class="text-end">Balance</th>
                                <th>Payment</th>
                                <th>Remark / PDF</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="salarySlipApprovalBody">
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">Loading salary slips...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="approveSalarySlipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Approve Salary Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approveSalarySlipId">
                <input type="hidden" id="approveNetPayAmount">

                <div class="alert alert-info py-2">
                    Net Payable: <strong id="approveNetPayLabel">Rs. 0.00</strong>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="approvePaymentAmount">Payment Amount</label>
                        <input type="number" min="0.01" step="0.01" class="form-control" id="approvePaymentAmount">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="approvePaymentMode">Payment Mode</label>
                        <select class="form-select" id="approvePaymentMode">
                            <option value="">Select Mode</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="UPI">UPI</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Cash">Cash</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="approveTransactionReference">Transaction Reference</label>
                        <input type="text" class="form-control" id="approveTransactionReference" maxlength="120">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="approveTransactionDate">Transaction Date</label>
                        <input type="date" class="form-control" id="approveTransactionDate" value="<?= date('Y-m-d'); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="approvePaymentRemarks">Payment Remarks</label>
                        <textarea class="form-control" id="approvePaymentRemarks" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="confirmApproveSalarySlipBtn">Approve & Save Payment</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectSalarySlipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Salary Slip</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectSalarySlipId">
                <label class="form-label" for="rejectRemark">Remark</label>
                <textarea class="form-control" id="rejectRemark" rows="4" required></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmRejectSalarySlipBtn">Reject</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentSalarySlipModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="paymentSalarySlipId">
                <input type="hidden" id="paymentBalanceAmount">

                <div class="alert alert-info py-2">
                    Balance: <strong id="paymentBalanceLabel">Rs. 0.00</strong>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="paymentAmount">Amount</label>
                        <input type="number" min="0.01" step="0.01" class="form-control" id="paymentAmount">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="paymentMode">Payment Mode</label>
                        <select class="form-select" id="paymentMode">
                            <option value="">Select Mode</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="UPI">UPI</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Cash">Cash</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="transactionReference">Transaction Reference</label>
                        <input type="text" class="form-control" id="transactionReference" maxlength="120">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="transactionDate">Transaction Date</label>
                        <input type="date" class="form-control" id="transactionDate" value="<?= date('Y-m-d'); ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="paymentRemarks">Remarks</label>
                        <textarea class="form-control" id="paymentRemarks" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveSalarySlipPaymentBtn">Save Payment</button>
            </div>
        </div>
    </div>
</div>


<!-- Salary Slip Preview Modal-->

<div class="modal fade" id="viewSalarySlipModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold">Salary Slip Preview</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body pt-0">
                <!-- Employee Info Card -->
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body py-3">
                        <div class="row g-3 align-items-center" id="previewEmployeeBody">
                            <!-- Employee details will be populated here -->
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card border-0 bg-primary bg-opacity-10 h-100">
                            <div class="card-body text-center py-3">
                                <span class="text-muted small text-uppercase">Gross Pay</span>
                                <h4 class="fw-bold text-primary mb-0" id="previewGross">Rs. 0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-danger bg-opacity-10 h-100">
                            <div class="card-body text-center py-3">
                                <span class="text-muted small text-uppercase">Deductions</span>
                                <h4 class="fw-bold text-danger mb-0" id="previewDeduction">Rs. 0</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 bg-success bg-opacity-10 h-100">
                            <div class="card-body text-center py-3">
                                <span class="text-muted small text-uppercase">Net Pay</span>
                                <h4 class="fw-bold text-success mb-0" id="previewNetPay">Rs. 0</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Earnings & Deductions -->
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0">
                                <h6 class="fw-bold text-primary mb-0">
                                    <i class="ri-arrow-up-circle-line me-1"></i> Earnings
                                </h6>
                            </div>
                            <div class="card-body px-3 pt-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody id="previewEarningsBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0">
                                <h6 class="fw-bold text-danger mb-0">
                                    <i class="ri-arrow-down-circle-line me-1"></i> Deductions
                                </h6>
                            </div>
                            <div class="card-body px-3 pt-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody id="previewDeductionBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm mt-4">
                            <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0">
                                <h6 class="fw-bold text-success mb-0">
                                    <i class="ri-money-rupee-circle-line me-1"></i>
                                    Reimbursements
                                </h6>
                            </div>
                        
                            <div class="card-body px-3 pt-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-borderless mb-0">
                                        <tbody id="previewReimbursementBody"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <!-- Leave Summary -->
                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0">
                        <h6 class="fw-bold text-info mb-0">
                            <i class="ri-calendar-line me-1"></i> Leave Summary
                        </h6>
                    </div>
                    <div class="card-body px-3 pt-2">
                        <div class="table-responsive">
                            <table class="table table-sm table-borderless mb-0">
                                <tbody id="previewLeaveBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payment Details (only when a payment has been recorded) -->
                <div class="card border-0 shadow-sm mt-4 d-none" id="previewPaymentCard">
                    <div class="card-header bg-transparent border-0 px-3 pt-3 pb-0">
                        <h6 class="fw-bold text-success mb-0">
                            <i class="ri-bank-card-line me-1"></i> Payment Details
                        </h6>
                    </div>
                    <div class="card-body px-3 pt-2">
                        <div id="previewPaymentBody"></div>
                    </div>
                </div>
            </div>

            <!-- Footer with Action Buttons -->
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    <i class="ri-close-line me-1"></i> Close
                </button>
                <button type="button" class="btn btn-primary" id="sendSalarySlipMailBtn">
                    <i class="ri-mail-send-line me-1"></i> Send Mail
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let salarySlipApprovalRows = [];

$(function () {
   loadSalarySlipApprovals();

   $('#refreshSalarySlipApprovalsBtn, #filterStatus, #filterMonth').on('click change', loadSalarySlipApprovals);
   $('#confirmApproveSalarySlipBtn').on('click', approveSalarySlip);
   $('#confirmRejectSalarySlipBtn').on('click', rejectSalarySlip);
   $('#saveSalarySlipPaymentBtn').on('click', saveSalarySlipPayment);

   $(document).on('click', '.approve-salary-slip', function () {
      const row = salarySlipApprovalRows.find(item => Number(item.id) === Number($(this).data('id')));

      if (!row) {
         return;
      }

      $('#approveSalarySlipId').val(row.id);
      $('#approveNetPayAmount').val(row.netPay || 0);
      $('#approveNetPayLabel').text(money(row.netPay));
      $('#approvePaymentAmount').val(row.balanceAmount || row.netPay || 0);
      $('#approvePaymentMode').val('');
      $('#approveTransactionReference').val('');
      $('#approveTransactionDate').val(new Date().toISOString().slice(0, 10));
      $('#approvePaymentRemarks').val('');
      $('#approveSalarySlipModal').modal('show');
   });

   $(document).on('click', '.reject-salary-slip', function () {
      $('#rejectSalarySlipId').val($(this).data('id'));
      $('#rejectRemark').val('');
      $('#rejectSalarySlipModal').modal('show');
   });

   $(document).on('click', '.payment-salary-slip', function () {
      const row = salarySlipApprovalRows.find(item => Number(item.id) === Number($(this).data('id')));

      if (!row) {
         return;
      }

      $('#paymentSalarySlipId').val(row.id);
      $('#paymentBalanceAmount').val(row.balanceAmount || 0);
      $('#paymentBalanceLabel').text(money(row.balanceAmount));
      $('#paymentAmount').val(row.balanceAmount || 0);
      $('#paymentMode').val('');
      $('#transactionReference').val('');
      $('#transactionDate').val(new Date().toISOString().slice(0, 10));
      $('#paymentRemarks').val('');
      $('#paymentSalarySlipModal').modal('show');
   });

   $(document).on('click', '.view-salary-slip', function () {

      const row =  salarySlipApprovalRows.find( item => Number(item.id) === Number($(this).data('id')));
      if (!row) { return; }
      viewSalarySlip(row);
   });

   $(document).on('click', '.send-salary-slip', function () {

      const row = salarySlipApprovalRows.find(item => Number(item.id) === Number($(this).data('id')));
      if (!row) { return; }
      confirmAndSendSalarySlip(row, 'payment');
   });

   $(document).on('click', '.payment-details-salary-slip', function () {

      const row = salarySlipApprovalRows.find(item => Number(item.id) === Number($(this).data('id')));
      if (!row) { return; }
      showPaymentDetails(row);
   });

});

function showPaymentDetails(row) {
   Swal.fire({
      title: 'Payment Details',
      html: '<div class="text-start"><div class="text-muted small py-3">Loading...</div></div>',
      showConfirmButton: true,
      confirmButtonText: 'Close'
   });

   $.getJSON(API_BASE + '/payroll/getSalarySlipOverview.php', { salarySlipId: row.id })
      .done(function (res) {
         if (!res || !res.success || !res.data || !res.data.payments || !res.data.payments.length) {
            Swal.update({ html: '<div class="text-start"><div class="text-muted small py-3">No payment records found.</div></div>' });
            return;
         }

         const html = res.data.payments.map(function (p) {
            const time = p.createdAt ? new Date(p.createdAt.replace(' ', 'T')).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '--';
            return `
               <div class="border rounded p-2 mb-2">
                  <div><strong>Transaction Number:</strong> ${escapeHtml(p.transactionReference)}</div>
                  <div><strong>Payment Date:</strong> ${formatDate(p.transactionDate)}</div>
                  <div><strong>Payment Time:</strong> ${time}</div>
                  <div><strong>Amount Paid:</strong> ${money(p.paymentAmount)}</div>
               </div>
            `;
         }).join('');

         Swal.update({ html: '<div class="text-start">' + html + '</div>' });
      })
      .fail(function () {
         Swal.update({ html: '<div class="text-start"><div class="text-danger small py-3">Unable to load payment details.</div></div>' });
      });
}

// Row label -> getSlipOverview() description key (mirrors the same map in
// includes/mailer.php's buildSalarySlipPreviewHtml(), kept in sync by hand
// since it's a tiny lookup, not shared business logic).
const SALARY_DESCRIPTION_KEY_BY_LABEL = {
    'Manual Deduction': 'manualDeduction',
    'Advance Payment Deduction': 'advanceDeduction',
    'Loan Payment Deduction': 'loanDeduction',
    'Leave Deduction': 'leaveDeduction',
    'Commission / Bonus': 'commissionBonus',
    'Overtime': 'overtime',
    'Expense Reimbursement': 'expenseReimbursement'
};

function descriptionSubRow(label, descriptions) {
    const key = SALARY_DESCRIPTION_KEY_BY_LABEL[label];
    const text = key && descriptions ? (descriptions[key] || '') : '';

    if (!text) {
        return '';
    }

    return `<tr><td colspan="2" class="ps-0 pt-0 pb-2 small text-muted">Reason: ${escapeHtml(text)}</td></tr>`;
}

function viewSalarySlip(row) {
    // Store the current row data for later use (Send Mail button)
    window.currentSalarySlipRow = row;

    // Uses the slip's own saved calculation snapshot (never recalculates a
    // historical/approved slip) plus traced descriptions and payment
    // records -- see PayrollApprovalEngine::getSlipOverview().
    $.ajax({
        url: API_BASE + '/payroll/getSalarySlipOverview.php',
        type: 'GET',
        dataType: 'json',
        data: {
            salarySlipId: row.id
        },
        success: function (res) {
            if (!res || !res.success || !res.data) {
                payrollAlert('error', (res && res.message) || 'Unable to load salary slip overview.');
                return;
            }

            window.currentSalarySlipOverview = res.data;
            bindPreviewSalary(res.data.calculation, res.data.descriptions || {}, res.data.payments || []);
            $('#viewSalarySlipModal').modal('show');
        },
        error: function () {
            payrollAlert('error', 'Unable to load salary slip overview.');
        }
    });
}

function bindPreviewSalary(data, descriptions, payments) {
    descriptions = descriptions || {};
    payments = payments || [];
    const emp = data.employee || {};
    const period = data.period || {};
    const earn = data.earnings || {};
    const ded = data.deductions || {};
    const leave = data.leave || {};
    const attendance = data.attendance || {};
    const reimbursements = data.reimbursements || {};

    // Set summary cards
    $('#previewGross').text(money(earn.grossEarnings));
    $('#previewDeduction').text(money(ded.totalDeductions));
    $('#previewNetPay').text(money(data.netPay));
    $('#previewReimbursements').text(
        money(reimbursements.totalReimbursements || 0)
    );

    // Employee Info - Horizontal layout
    $('#previewEmployeeBody').html(`
        <div class="col-md-3">
            <div class="d-flex flex-column">
                <span class="text-muted small text-uppercase">Employee</span>
                <strong class="fs-6">${escapeHtml(emp.fullName)}</strong>
            </div>
        </div>
        <div class="col-md-2">
            <div class="d-flex flex-column">
                <span class="text-muted small text-uppercase">Code</span>
                <span>${escapeHtml(emp.employeeCode || '-')}</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="d-flex flex-column">
                <span class="text-muted small text-uppercase">Department</span>
                <span>${escapeHtml(emp.departmentName || '-')}</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="d-flex flex-column">
                <span class="text-muted small text-uppercase">Joining</span>
                <span>${formatDate(emp.joiningDate)}</span>
            </div>
        </div>
        <div class="col-md-2">
            <div class="d-flex flex-column">
                <span class="text-muted small text-uppercase">Paid Days</span>
                <span>${numberValue(period.paidDaysAfterLeave || 0)}</span>
            </div>
        </div>
    `);

    // Earnings
    const earningRows = (earn.rows || [])
        .filter(row => Number(row.amount || 0) > 0);

    if (earningRows.length) {
        $('#previewEarningsBody').html(
            earningRows.map(row => `
                <tr>
                    <td class="ps-0">${escapeHtml(row.label)}</td>
                    <td class="text-end pe-0 fw-semibold">${money(row.amount)}</td>
                </tr>
            ` + descriptionSubRow(row.label, descriptions)).join('') +
            `<tr class="border-top">
                <td class="ps-0 fw-bold">Total Earnings</td>
                <td class="text-end pe-0 fw-bold text-primary">${money(earn.grossEarnings)}</td>
            </tr>`
        );
    } else {
        $('#previewEarningsBody').html(`<tr><td colspan="2" class="text-center text-muted py-2">No earnings records</td></tr>`);
    }

    // Deductions
    let deductionRows = [
        ...(ded.rows || []),
        { label: 'Training Hold', amount: ded.trainingHoldDeduction },
        { label: 'Leave Deduction', amount: ded.leaveDeduction },
        { label: 'Half Day Deduction', amount: ded.halfDayDeduction },
        { label: 'Manual Deduction', amount: ded.manualDeduction },
        { label: 'Fixed Deduction', amount: ded.fixedEmployeeDeduction },
        { label: 'Advance Payment Deduction', amount: ded.advanceDeduction },
        { label: 'Loan Payment Deduction', amount: ded.loanDeduction }
    ].filter(row => Number(row.amount || 0) > 0);

    if (deductionRows.length) {
        $('#previewDeductionBody').html(
            deductionRows.map(row => `
                <tr>
                    <td class="ps-0">${escapeHtml(row.label)}</td>
                    <td class="text-end pe-0 fw-semibold">${money(row.amount)}</td>
                </tr>
            ` + descriptionSubRow(row.label, descriptions)).join('') +
            `<tr class="border-top">
                <td class="ps-0 fw-bold">Total Deductions</td>
                <td class="text-end pe-0 fw-bold text-danger">${money(ded.totalDeductions)}</td>
            </tr>`
        );
    } else {
        $('#previewDeductionBody').html(`<tr><td colspan="2" class="text-center text-muted py-2">No deductions</td></tr>`);
    }
    
    // Reimbursements
    const reimbursementRows = [];
    
    if (Number(reimbursements.expenseReimbursement || 0) > 0) {
        reimbursementRows.push({
            label: 'Expense Reimbursement',
            amount: reimbursements.expenseReimbursement
        });
    }
    
    if (Number(reimbursements.gstAmount || 0) > 0) {
        reimbursementRows.push({
            label: 'GST Reimbursement',
            amount: reimbursements.gstAmount
        });
    }
    
    if (reimbursementRows.length) {
    
        $('#previewReimbursementBody').html(
            reimbursementRows.map(row => `
                <tr>
                    <td class="ps-0">${escapeHtml(row.label)}</td>
                    <td class="text-end pe-0 fw-semibold text-success">
                        ${money(row.amount)}
                    </td>
                </tr>
            ` + descriptionSubRow(row.label, descriptions)).join('') +

            `<tr class="border-top">
                <td class="ps-0 fw-bold">
                    Total Reimbursements
                </td>
                <td class="text-end pe-0 fw-bold text-success">
                    ${money(reimbursements.totalReimbursements || 0)}
                </td>
            </tr>`
        );
    
    } else {
    
        $('#previewReimbursementBody').html(`
            <tr>
                <td colspan="2"
                    class="text-center text-muted py-2">
                    No reimbursements
                </td>
            </tr>
        `);
    
    }

    // Leave Summary
    const actualPaidLeave = Number(leave.approvedPaidLeaveDays || 0);
    const totalLeave = Number(leave.probationLeaveDays || 0) + Number(leave.approvedUnpaidLeaveDays || 0) + actualPaidLeave;

    const leaveRows = [
        { label: 'Total Leave Taken', value: totalLeave + ' Days' },
        { label: 'Probation Unpaid Leave', value: leave.probationLeaveDays + ' Days' },
        { label: 'Paid Leave Used', value: actualPaidLeave + ' Days' },
        { label: 'Unpaid Leave Used', value: leave.approvedUnpaidLeaveDays + ' Days' },
        { label: 'Half Day Count', value: attendance.halfDays || 0 }
    ].filter(row => parseFloat(row.value) > 0);

    if (leaveRows.length) {
        $('#previewLeaveBody').html(
            leaveRows.map(row => `
                <tr>
                    <td class="ps-0">${escapeHtml(row.label)}</td>
                    <td class="text-end pe-0 fw-semibold">${escapeHtml(row.value)}</td>
                </tr>
            `).join('')
        );
    } else {
        $('#previewLeaveBody').html(`<tr><td colspan="2" class="text-center text-muted py-2">No leave records</td></tr>`);
    }

    // Payment Details -- from the slip's own payrollSalarySlipPayments
    // rows; hidden entirely when nothing has been paid yet.
    if (payments.length) {
        $('#previewPaymentBody').html(
            payments.map(function (p) {
                const time = p.createdAt ? new Date(p.createdAt.replace(' ', 'T')).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '--';
                return `
                    <div class="border rounded p-2 mb-2">
                        <div><strong>Transaction Number:</strong> ${escapeHtml(p.transactionReference)}</div>
                        <div><strong>Payment Date:</strong> ${formatDate(p.transactionDate)}</div>
                        <div><strong>Payment Time:</strong> ${time}</div>
                        <div><strong>Amount Paid:</strong> ${money(p.paymentAmount)}</div>
                    </div>
                `;
            }).join('')
        );
        $('#previewPaymentCard').removeClass('d-none');
    } else {
        $('#previewPaymentCard').addClass('d-none');
    }
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

function statusBadge(status) {
   const map = {
      pending: 'warning',
      approved: 'success',
      rejected: 'danger'
   };

   const color = map[status] || 'secondary';

   return `
                    <span class="btn btn-sm btn-outline-${color}">
                        ${escapeHtml(status || '-')}
                    </span>
                `;
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

function renderSalarySlipApprovalRows(rows) {
   if (!rows.length) {
      $('#salarySlipApprovalBody').html('<tr><td colspan="9" class="text-center text-muted py-4">No salary slips found.</td></tr>');
      return;
   }

   $('#salarySlipApprovalBody').html(rows.map(function (row) {

      let viewButton = '';

      if (row.status === 'approved' && row.pdfPath) {
         viewButton = `<a href="${BASE_URL}/${escapeHtml(row.pdfPath)}" target="_blank"
                class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light" title="View PDF">
                <i class="ri-file-pdf-line"></i>
            </a>`;
      } else {
         viewButton = `<button type="button" class="btn btn-icon btn-sm btn-primary-light btn-wave waves-effect waves-light view-salary-slip"
                data-id="${row.id}" title="Overview">
                <i class="ri-eye-line"></i>
            </button>`;
      }

      // Fully paid (balance settled) -> Payment Details view instead of
      // Record Payment, using the same paidAmount/balanceAmount already
      // computed from payrollSalarySlipPayments.
      let paymentButton = '<span class="text-muted">--</span>';
      if (row.status === 'approved') {
         paymentButton = Number(row.balanceAmount || 0) <= 0.01
            ? `<button type="button" class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light payment-details-salary-slip" data-id="${row.id}" title="Payment Details"><i class="ri-file-list-3-line"></i></button>`
            : `<button type="button" class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light payment-salary-slip" data-id="${row.id}" title="Record Payment"><i class="ri-bank-card-line"></i></button>`;
      }

      let actions = '<span class="text-muted">--</span>';
      if (row.status === 'pending') {
         actions = `<div class="d-flex gap-1">
               <button type="button" class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light approve-salary-slip" data-id="${row.id}" title="Approve"><i class="ri-check-line"></i></button>
               <button type="button" class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light reject-salary-slip" data-id="${row.id}" title="Reject"><i class="ri-close-line"></i></button>
            </div>`;
      } else if (row.status === 'approved') {
         actions = `<button type="button" class="btn btn-icon btn-sm btn-primary-light btn-wave waves-effect waves-light send-salary-slip" data-id="${row.id}" title="Send Salary Slip"><i class="ri-mail-send-line"></i></button>`;
      }

      return `
            <tr>
                <td>
                    <div class="fw-semibold">${escapeHtml(row.fullName)}</div>
                    <div class="text-muted small">${escapeHtml(row.employeeCode || '')}</div>
                </td>
                <td>${formatDate(row.periodStart)} to ${formatDate(row.periodEnd)}</td>
                <td>${statusBadge(row.status)}</td>
                <td class="text-end">${money(row.netPay)}</td>
                <td class="text-end">${money(row.paidAmount)}</td>
                <td class="text-end">${money(row.balanceAmount)}</td>
                <td>${paymentButton}</td>
                <td>
                    ${viewButton}
                    ${row.rejectionRemark ? `<div class="small text-danger mt-1">${escapeHtml(row.rejectionRemark)}</div>` : ''}
                </td>
                <td class="text-end">${actions}</td>
            </tr>
        `;
   }).join(''));
}

function loadSalarySlipApprovals() {
   $('#salarySlipApprovalBody').html('<tr><td colspan="9" class="text-center text-muted py-4">Loading salary slips...</td></tr>');

   $.getJSON(API_BASE + '/payroll/getSalarySlipApprovals.php', {
      status: $('#filterStatus').val(),
      month: $('#filterMonth').val()
   }).done(function (res) {
      if (!res || !res.success) {
         payrollAlert('error', res && res.message ? res.message : 'Unable to load salary slips.');
         return;
      }

      salarySlipApprovalRows = res.data.salarySlips || [];
      renderSalarySlipApprovalRows(salarySlipApprovalRows);
   }).fail(function () {
      payrollAlert('error', 'Unable to load salary slips.');
   });
}

function approveSalarySlip() {
   const salarySlipId = Number($('#approveSalarySlipId').val() || 0);
   const netPay = Number($('#approveNetPayAmount').val() || 0);
   const payload = {
      salarySlipId,
      action: 'approve',
      paymentAmount: Number($('#approvePaymentAmount').val() || 0),
      paymentMode: $('#approvePaymentMode').val(),
      transactionReference: $('#approveTransactionReference').val().trim(),
      transactionDate: $('#approveTransactionDate').val(),
      remarks: $('#approvePaymentRemarks').val().trim()
   };

   if (
      !payload.salarySlipId ||
      payload.paymentAmount <= 0 ||
      !payload.paymentMode ||
      !payload.transactionReference ||
      !payload.transactionDate
   ) {
      payrollAlert('warning', 'Payment amount, mode, reference, and date are required.');
      return;
   }

   if (payload.paymentAmount > netPay) {
      payrollAlert('warning', 'Payment amount cannot be greater than net payable amount.');
      return;
   }

   Swal.fire({
      icon: 'question',
      title: 'Approve salary slip?',
      text: 'PDF will be saved, payment transaction will be recorded, and employee email will be sent.',
      showCancelButton: true,
      confirmButtonText: 'Approve',
      cancelButtonText: 'Cancel'
   }).then(function (result) {
      if (!result.isConfirmed) {
         return;
      }

      reviewSalarySlip(payload, function () {
         $('#approveSalarySlipModal').modal('hide');
      });
   });
}

function rejectSalarySlip() {
   const salarySlipId = Number($('#rejectSalarySlipId').val() || 0);
   const remark = $('#rejectRemark').val().trim();

   if (!salarySlipId || !remark) {
      payrollAlert('warning', 'Rejection remark is required.');
      return;
   }

   reviewSalarySlip({
      salarySlipId,
      action: 'reject',
      remark
   }, function () {
      $('#rejectSalarySlipModal').modal('hide');
   });
}

function reviewSalarySlip(payload, done) {
   $.ajax({
      url: API_BASE + '/payroll/reviewSalarySlipApproval.php',
      type: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify(payload),
      success: function (res) {
         payrollAlert(res && res.success ? 'success' : 'error', res && res.message ? res.message : 'Unable to review salary slip.');

         if (res && res.success) {
            done && done();
            loadSalarySlipApprovals();
         }
      },
      error: function () {
         payrollAlert('error', 'Unable to review salary slip.');
      }
   });
}

function saveSalarySlipPayment() {
   const payload = {
      salarySlipId: Number($('#paymentSalarySlipId').val() || 0),
      paymentAmount: Number($('#paymentAmount').val() || 0),
      paymentMode: $('#paymentMode').val(),
      transactionReference: $('#transactionReference').val().trim(),
      transactionDate: $('#transactionDate').val(),
      remarks: $('#paymentRemarks').val().trim()
   };
   const balance = Number($('#paymentBalanceAmount').val() || 0);

   if (!payload.salarySlipId || payload.paymentAmount <= 0 || !payload.paymentMode || !payload.transactionReference || !payload.transactionDate) {
      payrollAlert('warning', 'Payment amount, mode, reference, and date are required.');
      return;
   }

   if (payload.paymentAmount > balance) {
      payrollAlert('warning', 'Payment amount cannot be greater than balance amount.');
      return;
   }

   $.ajax({
      url: API_BASE + '/payroll/saveSalarySlipPayment.php',
      type: 'POST',
      contentType: 'application/json',
      dataType: 'json',
      data: JSON.stringify(payload),
      success: function (res) {
         payrollAlert(res && res.success ? 'success' : 'error', res && res.message ? res.message : 'Unable to save payment.');

         if (res && res.success) {
            $('#paymentSalarySlipModal').modal('hide');
            loadSalarySlipApprovals();
         }
      },
      error: function () {
         payrollAlert('error', 'Unable to save payment.');
      }
   });
}

// =====================================================
// SEND SALARY SLIP MAIL
// =====================================================

// Overview modal's own "Send Mail" button always sends the fuller
// "overview" email (same data the modal is currently showing).
$(document).on('click', '#sendSalarySlipMailBtn', function() {
    if (!window.currentSalarySlipRow) {
        payrollAlert('error', 'Unable to find salary slip data.');
        return;
    }

    confirmAndSendSalarySlip(window.currentSalarySlipRow, 'overview');
});

// Shared by both the row-level "Send Salary Slip" icon (minimal payment-info
// email) and the Overview modal's "Send Mail" button (full overview email)
// -- one confirm dialog, `type` picks which existing engine method sends it.
function confirmAndSendSalarySlip(row, type) {
    if (!row.employeeId || !row.emailAddress) {
        payrollAlert('warning', 'Employee email address is not available.');
        return;
    }

    Swal.fire({
        icon: 'question',
        title: 'Send Salary Slip Email?',
        html: `
            <div class="text-start">
                <p><strong>To:</strong> ${escapeHtml(row.emailAddress || 'N/A')}</p>
                <p><strong>Employee:</strong> ${escapeHtml(row.fullName || 'N/A')}</p>
                <p><strong>Period:</strong> ${formatDate(row.periodStart)} to ${formatDate(row.periodEnd)}</p>
                <p><strong>Net Pay:</strong> ${money(row.netPay || 0)}</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Send Email',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0b8ba8'
    }).then(function(result) {
        if (!result.isConfirmed) {
            return;
        }

        sendSalarySlipEmail(row, type);
    });
}

function sendSalarySlipEmail(row, type) {
    // Show loading state
    Swal.fire({
        title: 'Sending Email...',
        text: 'Please wait while we send the salary slip email.',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: function() {
            Swal.showLoading();
        }
    });

    const payload = {
        salarySlipId: row.id,
        type: type || 'payment'
    };

    $.ajax({
        url: API_BASE + '/payroll/sendSalarySlipEmail.php',
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify(payload),
        success: function(response) {
            if (response && response.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Email Sent!',
                    text: response.message || 'Salary slip has been sent to the employee.',
                    confirmButtonColor: '#0b8ba8'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed to Send Email',
                    text: response && response.message ? response.message : 'Unable to send email. Please try again.',
                    confirmButtonColor: '#dc2626'
                });
            }
        },
        error: function(xhr) {
            let errorMsg = 'Unable to send email. Please try again.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMsg = xhr.responseJSON.message;
            }
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: errorMsg,
                confirmButtonColor: '#dc2626'
            });
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
