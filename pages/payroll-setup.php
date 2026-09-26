<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Payroll Setup</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Payroll Setup</li>
                </ol>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="setupWarning">
            Changing payroll constants may affect future salary-slip calculations.
        </div>

        <div class="row g-4">

            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Payroll Cycle</h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="payrollCycleType">Cycle Type</label>
                                <select id="payrollCycleType" class="form-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="custom">Custom Day Range</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="cycleStartDay">Start Day</label>
                                <input type="number" min="1" max="31" class="form-control" id="cycleStartDay" value="1">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label" for="cycleEndDay">End Day</label>
                                <input type="number" min="1" max="31" class="form-control" id="cycleEndDay" value="31">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="paidDaysBasis">Paid Days Basis</label>
                                <select id="paidDaysBasis" class="form-select">
                                    <option value="calendar">Actual calendar days</option>
                                    <option value="fixed_30">Fixed days</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="fixedPaidDays">Fixed Paid Days</label>
                                <input type="number" step="0.01" min="1" class="form-control" id="fixedPaidDays" value="30">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="monthlyPaidLeaveDays">Monthly Paid Leave</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="monthlyPaidLeaveDays" value="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="monthlyPaidLeaveCarryForwardLimit">Monthly Carry Forward Limit</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="monthlyPaidLeaveCarryForwardLimit" value="0">
                            </div>

                            <div class="col-md-6 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="monthlyPaidLeaveCarryForward">
                                    <label class="form-check-label" for="monthlyPaidLeaveCarryForward">Carry forward unused monthly paid leave</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="salaryBase">Salary Base</label>
                                <select id="salaryBase" class="form-select">
                                    <option value="netSalary">Employee Net Salary</option>
                                    <option value="grossSalary">Basic + HRA + Allowance</option>
                                    <option value="basicSalary">Basic Salary Only</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="standardWorkingHours">Standard Working Hours / Day</label>
                                <input type="number" step="0.01" min="1" class="form-control" id="standardWorkingHours" value="8">
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="prorateSalaryFromJoiningDate" checked>
                                    <label class="form-check-label" for="prorateSalaryFromJoiningDate">Prorate salary from joining date</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Earnings Rules</h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="overtimeMultiplier">Overtime Multiplier</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="overtimeMultiplier" value="1.5">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="roundingRule">Net Pay Rounding</label>
                                <select id="roundingRule" class="form-select">
                                    <option value="nearest_rupee">Nearest Rupee</option>
                                    <option value="two_decimal">Two Decimal</option>
                                    <option value="floor_rupee">Floor Rupee</option>
                                    <option value="ceil_rupee">Ceil Rupee</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeApprovedOvertime" checked>
                                    <label class="form-check-label" for="includeApprovedOvertime">Include approved overtime</label>
                                </div>

                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeApprovedExpenses" checked>
                                    <label class="form-check-label" for="includeApprovedExpenses">Include approved expense reimbursements</label>
                                </div>

                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeSyncedCommissionBonus" checked>
                                    <label class="form-check-label" for="includeSyncedCommissionBonus">Include synced commission / bonus</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Leave & Attendance Deductions</h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label" for="approvedPaidLeaveDeductionPercent">Approved Paid Leave Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="approvedPaidLeaveDeductionPercent" value="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="approvedUnpaidLeaveDeductionPercent">Approved Unpaid Leave Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="approvedUnpaidLeaveDeductionPercent" value="100">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="informedLeaveDeductionPercent">Informed Leave Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="informedLeaveDeductionPercent" value="100">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="uninformedLeaveDeductionPercent">Uninformed Leave Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="uninformedLeaveDeductionPercent" value="100">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="halfDayDeductionPercent">Half Day Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="halfDayDeductionPercent" value="50">
                            </div>

                            <div class="col-md-6 d-flex align-items-end">
                                <div>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="pendingLeaveAsInformed" checked>
                                        <label class="form-check-label" for="pendingLeaveAsInformed">Treat pending leave as informed</label>
                                    </div>

                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="absentAsUninformedLeave" checked>
                                        <label class="form-check-label" for="absentAsUninformedLeave">Treat attendance absent as uninformed leave</label>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableBirthdayPaidLeave" checked>
                                    <label class="form-check-label" for="enableBirthdayPaidLeave">Birthday leave is paid</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableProbationLeaveRule" checked>
                                    <label class="form-check-label" for="enableProbationLeaveRule">Apply probation leave rule</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="probationDays">Probation Days</label>
                                <input type="number" min="0" class="form-control" id="probationDays" value="30">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="probationLeaveDeductionPercent">Probation Leave Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="probationLeaveDeductionPercent" value="100">
                            </div>
                            
                            
                            <div class="col-md-12">
                                <hr />
                            
                                <h6 class="fw-semibold">Training Period Salary Hold Rule</h6>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableTrainingHoldRule" />
                            
                                    <label class="form-check-label" for="enableTrainingHoldRule"> Apply Training Salary Hold Rule </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label"> Training Days To Hold </label>
                            
                                <input type="number" min="0" class="form-control" id="trainingHoldDays" value="7" />
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label"> Release Held Salary After Days </label>
                            
                                <input type="number" min="1" class="form-control" id="trainingAmountReleaseAfterDays" value="90" />
                            </div>


                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enableNoticePeriodLeaveRule">
                                    <label class="form-check-label" for="enableNoticePeriodLeaveRule">Apply notice-period leave rule</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="noticeLeaveDeductionPercent">Notice Leave Deduction %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="noticeLeaveDeductionPercent" value="100">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Statutory & Tax Rules</h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeProvidentFund">
                                    <label class="form-check-label" for="includeProvidentFund">Include P.F.</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="providentFundBasis">P.F. Basis</label>
                                <select id="providentFundBasis" class="form-select">
                                    <option value="basic">Basic</option>
                                    <option value="gross">Gross Earnings</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="providentFundPercent">P.F. %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="providentFundPercent" value="12">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="providentFundWageCeiling">P.F. Wage Ceiling</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="providentFundWageCeiling" value="15000">
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeEsic">
                                    <label class="form-check-label" for="includeEsic">Include ESIC</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="esicEmployeePercent">ESIC Employee %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="esicEmployeePercent" value="0.75">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="esicWageLimit">ESIC Wage Limit</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="esicWageLimit" value="21000">
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeProfessionalTax">
                                    <label class="form-check-label" for="includeProfessionalTax">Include Professional Tax</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="professionalTaxAmount">Professional Tax Amount</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="professionalTaxAmount" value="0">
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeIncomeTaxTds">
                                    <label class="form-check-label" for="includeIncomeTaxTds">Include Income Tax / TDS</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="incomeTaxTdsType">TDS Type</label>
                                <select id="incomeTaxTdsType" class="form-select">
                                    <option value="fixed">Fixed Amount</option>
                                    <option value="percent">Percentage</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="incomeTaxTdsAmount">Monthly TDS Amount</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="incomeTaxTdsAmount" value="0">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="incomeTaxTdsPercent">TDS %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="incomeTaxTdsPercent" value="0">
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="includeGst">
                                    <label class="form-check-label" for="includeGst">Apply GST for consultants</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label" for="gstPercent">GST %</label>
                                <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="gstPercent" value="18">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Deduction Rules</h5>
                    </div>

                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="includeManualDeductions" checked>
                                <label class="form-check-label" for="includeManualDeductions">Include Deduction Management entries</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="includeFixedEmployeeDeduction" checked>
                                <label class="form-check-label" for="includeFixedEmployeeDeduction">Include fixed employee deduction amount</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="includeAdvanceDeduction" checked>
                                <label class="form-check-label" for="includeAdvanceDeduction">Include approved advance payment deduction</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="includeLoanDeduction" checked>
                                <label class="form-check-label" for="includeLoanDeduction">Include employee loan recovery deduction</label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="includePointPayrollDeduction">
                                <label class="form-check-label" for="includePointPayrollDeduction">Include employee point payroll deduction</label>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="pointDeductionThreshold">Point Deduction Threshold</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="pointDeductionThreshold" value="100">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="pointDeductionPercent">Deduction % Per Hit</label>
                                    <input type="number" step="0.01" min="0" max="100" class="form-control payroll-percent" id="pointDeductionPercent" value="10">
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="compoundPointDeduction" checked>
                                        <label class="form-check-label" for="compoundPointDeduction">Compound point deduction</label>
                                    </div>
                                </div>
                            </div>

                            <ul class="mb-0" id="setupProgressList">
                                <li id="progressCycle">Payroll cycle configured</li>
                                <li id="progressRates">Rates configured</li>
                                <li id="progressLeave">Leave rules configured</li>
                            </ul>
                        </div>

                        <div class="mt-4">
                            <button type="button" class="btn btn-primary" id="savePayrollSetupBtn">
                                Save & Activate Payroll Setup
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let hasUnsavedChanges = false;

$(function() {
    bindPayrollSetupEvents();
    loadPayrollSetup();
    updatePayrollProgress();
});

function bindPayrollSetupEvents() {
    $(document).on('change keyup', 'input, select', function() {
        hasUnsavedChanges = true;
        $('#setupWarning').removeClass('d-none');
        updatePayrollProgress();
    });

    $(document).on('input', 'input[type="number"]', function() {
        const min = Number($(this).attr('min') || 0);
        const max = $(this).attr('max') !== undefined ? Number($(this).attr('max')) : null;
        let value = Number($(this).val() || 0);

        if (value < min) {
            value = min;
        }

        if (max !== null && value > max) {
            value = max;
        }

        $(this).val(value);
    });

    $('#savePayrollSetupBtn').on('click', savePayrollSetup);

    window.addEventListener('beforeunload', function(e) {
        if (!hasUnsavedChanges) {
            return;
        }

        e.preventDefault();
        e.returnValue = '';
    });
}

function collectPayrollPayload() {
    return {
        payrollSettings: {
            payrollCycleType: $('#payrollCycleType').val(),
            cycleStartDay: Number($('#cycleStartDay').val() || 1),
            cycleEndDay: Number($('#cycleEndDay').val() || 31),
            paidDaysBasis: $('#paidDaysBasis').val(),
            fixedPaidDays: Number($('#fixedPaidDays').val() || 30),
            monthlyPaidLeaveDays: Number($('#monthlyPaidLeaveDays').val() || 0),
            monthlyPaidLeaveCarryForward: $('#monthlyPaidLeaveCarryForward').is(':checked') ? 1 : 0,
            monthlyPaidLeaveCarryForwardLimit: Number($('#monthlyPaidLeaveCarryForwardLimit').val() || 0),
            enableBirthdayPaidLeave: $('#enableBirthdayPaidLeave').is(':checked') ? 1 : 0,
            prorateSalaryFromJoiningDate: $('#prorateSalaryFromJoiningDate').is(':checked') ? 1 : 0,
            enableProbationLeaveRule: $('#enableProbationLeaveRule').is(':checked') ? 1 : 0,
            probationDays: Number($('#probationDays').val() || 0),
            probationLeaveDeductionPercent: Number($('#probationLeaveDeductionPercent').val() || 0),
            enableNoticePeriodLeaveRule: $('#enableNoticePeriodLeaveRule').is(':checked') ? 1 : 0,
            noticeLeaveDeductionPercent: Number($('#noticeLeaveDeductionPercent').val() || 0),
            includePointPayrollDeduction: $('#includePointPayrollDeduction').is(':checked') ? 1 : 0,
            pointDeductionThreshold: Number($('#pointDeductionThreshold').val() || 0),
            pointDeductionPercent: Number($('#pointDeductionPercent').val() || 0),
            compoundPointDeduction: $('#compoundPointDeduction').is(':checked') ? 1 : 0,
            includeProvidentFund: $('#includeProvidentFund').is(':checked') ? 1 : 0,
            providentFundBasis: $('#providentFundBasis').val(),
            providentFundPercent: Number($('#providentFundPercent').val() || 0),
            providentFundWageCeiling: Number($('#providentFundWageCeiling').val() || 0),
            includeEsic: $('#includeEsic').is(':checked') ? 1 : 0,
            esicEmployeePercent: Number($('#esicEmployeePercent').val() || 0),
            esicWageLimit: Number($('#esicWageLimit').val() || 0),
            includeProfessionalTax: $('#includeProfessionalTax').is(':checked') ? 1 : 0,
            professionalTaxAmount: Number($('#professionalTaxAmount').val() || 0),
            includeIncomeTaxTds: $('#includeIncomeTaxTds').is(':checked') ? 1 : 0,
            incomeTaxTdsType: $('#incomeTaxTdsType').val(),
            incomeTaxTdsAmount: Number($('#incomeTaxTdsAmount').val() || 0),
            incomeTaxTdsPercent: Number($('#incomeTaxTdsPercent').val() || 0),
            includeGst: $('#includeGst').is(':checked') ? 1 : 0,
            gstPercent: Number($('#gstPercent').val() || 0),
            salaryBase: $('#salaryBase').val(),
            standardWorkingHours: Number($('#standardWorkingHours').val() || 8),
            includeApprovedOvertime: $('#includeApprovedOvertime').is(':checked') ? 1 : 0,
            overtimeMultiplier: Number($('#overtimeMultiplier').val() || 0),
            includeManualDeductions: $('#includeManualDeductions').is(':checked') ? 1 : 0,
            includeFixedEmployeeDeduction: $('#includeFixedEmployeeDeduction').is(':checked') ? 1 : 0,
            includeAdvanceDeduction: $('#includeAdvanceDeduction').is(':checked') ? 1 : 0,
            includeLoanDeduction: $('#includeLoanDeduction').is(':checked') ? 1 : 0,
            includeApprovedExpenses: $('#includeApprovedExpenses').is(':checked') ? 1 : 0,
            includeSyncedCommissionBonus: $('#includeSyncedCommissionBonus').is(':checked') ? 1 : 0,
            approvedPaidLeaveDeductionPercent: Number($('#approvedPaidLeaveDeductionPercent').val() || 0),
            approvedUnpaidLeaveDeductionPercent: Number($('#approvedUnpaidLeaveDeductionPercent').val() || 0),
            informedLeaveDeductionPercent: Number($('#informedLeaveDeductionPercent').val() || 0),
            uninformedLeaveDeductionPercent: Number($('#uninformedLeaveDeductionPercent').val() || 0),
            halfDayDeductionPercent: Number($('#halfDayDeductionPercent').val() || 0),
            absentAsUninformedLeave: $('#absentAsUninformedLeave').is(':checked') ? 1 : 0,
            pendingLeaveAsInformed: $('#pendingLeaveAsInformed').is(':checked') ? 1 : 0,
            enableTrainingHoldRule: $('#enableTrainingHoldRule') .is(':checked') ? 1 : 0,
            trainingHoldDays: Number($('#trainingHoldDays').val() || 0),
            trainingAmountReleaseAfterDays: Number($('#trainingAmountReleaseAfterDays').val() || 90),
            roundingRule: $('#roundingRule').val()
        }
    };
}

function validatePayrollPayload(payload) {
    const settings = payload.payrollSettings;

    if (settings.cycleStartDay < 1 || settings.cycleStartDay > 31) {
        return 'Cycle start day must be between 1 and 31.';
    }

    if (settings.cycleEndDay < 1 || settings.cycleEndDay > 31) {
        return 'Cycle end day must be between 1 and 31.';
    }

    if (settings.fixedPaidDays <= 0) {
        return 'Fixed paid days must be greater than 0.';
    }

    if (settings.standardWorkingHours <= 0) {
        return 'Standard working hours must be greater than 0.';
    }

    if (settings.monthlyPaidLeaveDays < 0 || settings.monthlyPaidLeaveCarryForwardLimit < 0) {
        return 'Monthly paid leave fields must be 0 or greater.';
    }

    if (settings.probationDays < 0 || settings.pointDeductionThreshold < 0) {
        return 'Probation days and point threshold must be 0 or greater.';
    }
    
    if(settings.trainingHoldDays < 0){
        return 'Training hold days cannot be negative';
    }


    if(settings.trainingAmountReleaseAfterDays <=0){
        return 'Training release days should be greater than 0';
    }

    if (
        settings.providentFundWageCeiling < 0 ||
        settings.esicWageLimit < 0 ||
        settings.professionalTaxAmount < 0 ||
        settings.incomeTaxTdsAmount < 0
    ) {
        return 'Statutory tax amounts and limits must be 0 or greater.';
    }

    if (!['basic', 'gross'].includes(settings.providentFundBasis)) {
        return 'Please select a valid P.F. basis.';
    }

    if (!['fixed', 'percent'].includes(settings.incomeTaxTdsType)) {
        return 'Please select a valid TDS type.';
    }

    if (
        settings.probationLeaveDeductionPercent < 0 ||
        settings.probationLeaveDeductionPercent > 100 ||
        settings.noticeLeaveDeductionPercent < 0 ||
        settings.noticeLeaveDeductionPercent > 100 ||
        settings.pointDeductionPercent < 0 ||
        settings.pointDeductionPercent > 100 ||
        settings.providentFundPercent < 0 ||
        settings.providentFundPercent > 100 ||
        settings.esicEmployeePercent < 0 ||
        settings.esicEmployeePercent > 100 ||
        settings.incomeTaxTdsPercent < 0 ||
        settings.incomeTaxTdsPercent > 100 ||
        settings.gstPercent < 0 ||
        settings.gstPercent > 100
    ) {
        return 'Deduction percentages must be between 0 and 100.';
    }

    return '';
}

function savePayrollSetup() {
    const payload = collectPayrollPayload();
    const error = validatePayrollPayload(payload);

    if (error) {
        window.showToast && window.showToast('warning', error);
        return;
    }

    const $btn = $('#savePayrollSetupBtn');

    $btn
        .prop('disabled', true)
        .html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');

    $.ajax({
        url: API_BASE + '/payroll/savePayrollSetup.php',
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify(payload),
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Invalid server response.'
            );

            if (res && res.success) {
                hasUnsavedChanges = false;
                $('#setupWarning').addClass('d-none');
                loadPayrollSetup();
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to save payroll setup.');
        },
        complete: function() {
            $btn
                .prop('disabled', false)
                .text('Save & Activate Payroll Setup');
        }
    });
}

function numericSetting(settings, key, fallback) {
    return settings[key] === undefined || settings[key] === null || settings[key] === ''
        ? fallback
        : Number(settings[key]);
}

function bindPayrollState(settings) {
    $('#payrollCycleType').val(settings.payrollCycleType || 'monthly');
    $('#cycleStartDay').val(Number(settings.cycleStartDay || 1));
    $('#cycleEndDay').val(Number(settings.cycleEndDay || 31));
    $('#paidDaysBasis').val(settings.paidDaysBasis || 'calendar');
    $('#fixedPaidDays').val(Number(settings.fixedPaidDays || 30));
    $('#monthlyPaidLeaveDays').val(Number(settings.monthlyPaidLeaveDays || 0));
    $('#monthlyPaidLeaveCarryForward').prop('checked', Number(settings.monthlyPaidLeaveCarryForward || 0) === 1);
    $('#monthlyPaidLeaveCarryForwardLimit').val(Number(settings.monthlyPaidLeaveCarryForwardLimit || 0));
    $('#enableBirthdayPaidLeave').prop('checked', Number(settings.enableBirthdayPaidLeave || 0) === 1);
    $('#prorateSalaryFromJoiningDate').prop('checked', Number(settings.prorateSalaryFromJoiningDate || 0) === 1);
    $('#enableProbationLeaveRule').prop('checked', Number(settings.enableProbationLeaveRule || 0) === 1);
    $('#probationDays').val(numericSetting(settings, 'probationDays', 30));
    $('#probationLeaveDeductionPercent').val(numericSetting(settings, 'probationLeaveDeductionPercent', 100));
    $('#enableNoticePeriodLeaveRule').prop('checked', Number(settings.enableNoticePeriodLeaveRule || 0) === 1);
    $('#noticeLeaveDeductionPercent').val(numericSetting(settings, 'noticeLeaveDeductionPercent', 100));
    $('#includePointPayrollDeduction').prop('checked', Number(settings.includePointPayrollDeduction || 0) === 1);
    $('#pointDeductionThreshold').val(numericSetting(settings, 'pointDeductionThreshold', 100));
    $('#pointDeductionPercent').val(numericSetting(settings, 'pointDeductionPercent', 10));
    $('#compoundPointDeduction').prop('checked', Number(settings.compoundPointDeduction || 0) === 1);
    $('#includeProvidentFund').prop('checked', Number(settings.includeProvidentFund || 0) === 1);
    $('#providentFundBasis').val(settings.providentFundBasis || 'basic');
    $('#providentFundPercent').val(numericSetting(settings, 'providentFundPercent', 12));
    $('#providentFundWageCeiling').val(numericSetting(settings, 'providentFundWageCeiling', 15000));
    $('#includeEsic').prop('checked', Number(settings.includeEsic || 0) === 1);
    $('#esicEmployeePercent').val(numericSetting(settings, 'esicEmployeePercent', 0.75));
    $('#esicWageLimit').val(numericSetting(settings, 'esicWageLimit', 21000));
    $('#includeProfessionalTax').prop('checked', Number(settings.includeProfessionalTax || 0) === 1);
    $('#professionalTaxAmount').val(numericSetting(settings, 'professionalTaxAmount', 0));
    $('#includeIncomeTaxTds').prop('checked', Number(settings.includeIncomeTaxTds || 0) === 1);
    $('#incomeTaxTdsType').val(settings.incomeTaxTdsType || 'fixed');
    $('#incomeTaxTdsAmount').val(numericSetting(settings, 'incomeTaxTdsAmount', 0));
    $('#incomeTaxTdsPercent').val(numericSetting(settings, 'incomeTaxTdsPercent', 0));
    $('#includeGst').prop('checked', Number(settings.includeGst || 0) === 1);
    $('#gstPercent').val(numericSetting(settings, 'gstPercent', 18));
    $('#salaryBase').val(settings.salaryBase || 'netSalary');
    $('#standardWorkingHours').val(Number(settings.standardWorkingHours || 8));
    $('#overtimeMultiplier').val(Number(settings.overtimeMultiplier || 1.5));
    $('#roundingRule').val(settings.roundingRule || 'nearest_rupee');

    $('#includeApprovedOvertime').prop('checked', Number(settings.includeApprovedOvertime || 0) === 1);
    $('#includeManualDeductions').prop('checked', Number(settings.includeManualDeductions || 0) === 1);
    $('#includeAdvanceDeduction').prop('checked', Number(settings.includeAdvanceDeduction ?? 1) === 1);
    $('#includeLoanDeduction').prop('checked', Number(settings.includeLoanDeduction ?? 1) === 1);
    $('#includeFixedEmployeeDeduction').prop('checked', Number(settings.includeFixedEmployeeDeduction || 0) === 1);
    $('#includeApprovedExpenses').prop('checked', Number(settings.includeApprovedExpenses || 0) === 1);
    $('#includeSyncedCommissionBonus').prop('checked', Number(settings.includeSyncedCommissionBonus || 0) === 1);

    $('#approvedPaidLeaveDeductionPercent').val(Number(settings.approvedPaidLeaveDeductionPercent || 0));
    $('#approvedUnpaidLeaveDeductionPercent').val(Number(settings.approvedUnpaidLeaveDeductionPercent || 100));
    $('#informedLeaveDeductionPercent').val(Number(settings.informedLeaveDeductionPercent || 100));
    $('#uninformedLeaveDeductionPercent').val(Number(settings.uninformedLeaveDeductionPercent || 100));
    $('#halfDayDeductionPercent').val(Number(settings.halfDayDeductionPercent || 50));
    $('#absentAsUninformedLeave').prop('checked', Number(settings.absentAsUninformedLeave || 0) === 1);
    $('#pendingLeaveAsInformed').prop('checked', Number(settings.pendingLeaveAsInformed || 0) === 1);
    
    $('#enableTrainingHoldRule').prop('checked', Number(settings.enableTrainingHoldRule || 0) ===1);
    $('#trainingHoldDays').val( Number(settings.trainingHoldDays || 0));
    $('#trainingAmountReleaseAfterDays').val(Number(settings.trainingAmountReleaseAfterDays || 90));

    updatePayrollProgress();
}

function loadPayrollSetup() {
    $.getJSON(API_BASE + '/payroll/getPayrollSetup.php')
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load payroll setup.');
                return;
            }

            bindPayrollState(res.data.payrollSettings || {});
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load payroll setup.');
        });
}

function updatePayrollProgress() {
    $('#progressCycle').text(
        ($('#cycleStartDay').val() && $('#cycleEndDay').val())
            ? '✓ Payroll cycle configured'
            : 'Payroll cycle configured'
    );

    $('#progressRates').text(
        Number($('#standardWorkingHours').val() || 0) > 0
            ? '✓ Rates configured'
            : 'Rates configured'
    );

    $('#progressLeave').text('✓ Leave rules configured');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
