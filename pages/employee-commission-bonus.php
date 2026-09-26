<?php

include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

?>
<link rel="stylesheet"
    href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
    href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
    
    
    
<style>
    .table-responsive::-webkit-scrollbar {
    display: none;
}

#transactions-datatable td,
#transactions-datatable th {
    white-space: nowrap;
    vertical-align: middle;
}

#transactions-datatable small {
    display: inline;
    margin-left: 6px;
}

#employeeSelect {
    min-width: 180px;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 13px;
}

@media (max-width: 768px) {

    .page-header-breadcrumb {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem !important;
    }

}
</style>
<div class="main-content app-content">

    <div class="container-fluid">

        <!-- HEADER -->

        <div class="my-4 page-header-breadcrumb d-flex justify-content-between align-items-center">

            <div>

                <h1 class="page-title fw-medium fs-18 mb-2">
                    Employee Commission & Bonus
                </h1>

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>

                    <li class="breadcrumb-item active">
                        Commission Transactions
                    </li>

                </ol>

            </div>

            <button class="btn btn-primary btn-wave"
                data-bs-toggle="modal"
                data-bs-target="#commissionTransactionModal">

                <i class="ri-add-line me-1"></i>
                Add Transaction

            </button>

        </div>

        <!-- SUMMARY CARDS -->

        <div class="row">

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Total Pending
                        </div>

                        <h4 class="fw-semibold text-warning mb-0"
                            id="pendingAmountCard">

                            ₹ 0

                        </h4>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Approved Amount
                        </div>

                        <h4 class="fw-semibold text-success mb-0"
                            id="approvedAmountCard">

                            ₹ 0

                        </h4>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Payroll Synced
                        </div>

                        <h4 class="fw-semibold text-info mb-0"
                            id="payrollSyncedCard">

                            ₹ 0

                        </h4>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Paid Amount
                        </div>

                        <h4 class="fw-semibold text-primary mb-0"
                            id="paidAmountCard">

                            ₹ 0

                        </h4>

                    </div>

                </div>

            </div>

        </div>

        <!-- TRANSACTION TABLE -->

        <!-- FILTER CARD -->
<div class="row">

    <div class="col-xl-12">

        <div class="card custom-card">

            <div class="card-body p-3">

                <div class="d-flex align-items-center justify-content-between">

                    <!-- LEFT -->
                    <div class="d-flex align-items-center gap-2">

                        <!-- EXPORT -->
                        <div class="btn-list">

                            <div class="btn-group">

                                <button type="button"
                                    class="btn btn-outline-primary dropdown-toggle"
                                    data-bs-toggle="dropdown"
                                    aria-expanded="false">

                                    Export

                                </button>

                                <ul class="dropdown-menu">

                                    <li>

                                        <a class="dropdown-item export-btn"
                                            data-type="csv"
                                            href="javascript:void(0);">

                                            CSV

                                        </a>

                                    </li>

                                    <li>

                                        <a class="dropdown-item export-btn"
                                            data-type="pdf"
                                            href="javascript:void(0);">

                                            PDF

                                        </a>

                                    </li>

                                </ul>

                            </div>

                        </div>

                        <!-- EMPLOYEE FILTER -->
                        <select id="employeeSelect"
                            class="form-select form-select-lg">

                            <option value="">
                                All Employees
                            </option>

                        </select>

                    </div>

                    <!-- CENTER SPACE -->
                    <div class="flex-fill"></div>

                    <!-- RIGHT -->
                    <div class="d-flex">

                        <input id="tableSearch"
                            class="form-control form-control-sm"
                            placeholder="Search transactions..."
                            autocomplete="off">

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- DATATABLE -->
<div class="row">

    <div class="col-xl-12">

        <div class="card custom-card">

            <div class="card-header justify-content-between">

                <div class="card-title">
                    Employee Transactions DataTable
                </div>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table id="transactions-datatable"
                        data-ui-table="mamix"
                        class="table table-hover text-nowrap">

                        <thead>

                            <tr>

                                <th>SNo</th>
                                <th>Transaction ID</th>
                                <th>Employee</th>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Approval</th>
                                <th>Payroll</th>
                                <th>Remarks</th>
                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody id="transactionTableBody">

                            <tr>

                                <td colspan="11"
                                    class="text-center text-muted">

                                    No records found

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

    </div>

</div>

<!-- TRANSACTION MODAL -->

<div class="modal fade"
    id="commissionTransactionModal">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Add Commission Transaction
                </h5>

                <button class="btn-close"
                    data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <input type="hidden"
                    id="transactionId">

                <div class="row g-3">

                    <!-- EMPLOYEE -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Employee
                        </label>

                        <select id="modalEmployeeId"
                            class="form-select">

                            <option value="">
                                Select Employee
                            </option>

                        </select>

                    </div>
                    
                    <!-- TRANSACTION TYPE -->

                    <div class="col-md-6">
                    
                        <label class="form-label">
                            Type
                        </label>
                    
                        <select id="transactionType"
                            class="form-select">
                    
                            <option value="">
                                Select Type
                            </option>
                    
                            <option value="Bonus">
                                Bonus
                            </option>
                    
                            <option value="Commission">
                                Commission
                            </option>
                    
                        </select>
                    
                    </div>

                    <!-- CATEGORY -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Category
                        </label>

                        <select id="categoryId"
                            class="form-select">

                            <option value="">
                                Select Category
                            </option>

                        </select>

                    </div>

                    <!-- AMOUNT -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Amount
                        </label>

                        <input type="number"
                            class="form-control"
                            id="amount"
                            min="1"
                            step="0.01">

                    </div>
                    
                    
                    <!-- COMMISSION CALCULATION -->

                    <div class="col-md-6 d-none"
                        id="commissionCalculationWrapper">
                    
                        <div class="border rounded p-3 bg-light">
                    
                            <div class="d-flex justify-content-between mb-2">
                    
                                <span>
                                    Commission Percentage:
                                </span>
                    
                                <strong id="commissionPercentagePreview">
                                    0%
                                </strong>
                    
                            </div>
                    
                            <div class="d-flex justify-content-between">
                    
                                <span>
                                    Final Commission:
                                </span>
                    
                                <strong id="finalCommissionPreview">
                                    ₹ 0
                                </strong>
                    
                            </div>
                    
                        </div>
                    
                    </div>

                    <!-- EFFECTIVE MONTH -->

                    <div class="col-md-6">

                        <label class="form-label">
                            Effective Month
                        </label>

                        <input type="month"
                            class="form-control"
                            id="effectiveMonth">

                    </div>

                    <!-- ATTACHMENT -->

                    <div class="col-12">

                        <label class="form-label">
                            Attachment
                        </label>

                        <input type="file"
                            class="form-control"
                            id="attachment">

                    </div>

                    <!-- REMARKS -->

                    <div class="col-12">

                        <label class="form-label">
                            Remarks
                        </label>

                        <textarea id="remarks"
                            class="form-control"
                            rows="3"></textarea>

                    </div>

                    <!-- PREVIEW -->

                    <div class="col-12">

                        <div class="p-3 border rounded bg-light">

                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Category Type:
                                </span>

                                <strong id="categoryTypePreview">
                                    --
                                </strong>

                            </div>

                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Taxable:
                                </span>

                                <strong id="taxablePreview">
                                    --
                                </strong>

                            </div>

                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Payroll Applicable:
                                </span>

                                <strong id="payrollPreview">
                                    --
                                </strong>

                            </div>

                            <div class="d-flex justify-content-between">

                                <span>
                                    Requires Approval:
                                </span>

                                <strong id="approvalPreview">
                                    --
                                </strong>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="modal-footer">

                <button class="btn btn-light"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button class="btn btn-primary"
                    id="saveTransactionBtn">

                    Save Transaction

                </button>

            </div>

        </div>

    </div>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

$(function() {

    // =========================================
    // API
    // =========================================

    const API = {

        getEmployees:
            API_BASE + '/commission/getCommissionEmployees.php',

        getCategories:
            API_BASE + '/commission/getCommissionCategories.php',

        getTransactions:
            API_BASE + '/commission/getCommissionTransactions.php',

        saveTransaction:
            API_BASE + '/commission/saveCommissionTransaction.php',

        updateTransaction:
            API_BASE + '/commission/updateCommissionTransaction.php',

        deleteTransaction:
            API_BASE + '/commission/deleteCommissionTransaction.php',
            
        approveTransaction:
            API_BASE + '/commission/approveCommissionTransaction.php',
        
        rejectTransaction:
            API_BASE + '/commission/rejectCommissionTransaction.php',
            
        syncPayroll:
            API_BASE + '/commission/syncCommissionPayroll.php',
    };

    // =========================================
    // GLOBAL STATE
    // =========================================

    let employees = [];

    let categories = [];

    let selectedEmployeeId = '';

    let isEditMode = false;

    let editingTransactionId = null;
    
    let isSubmittingTransaction = false;
    
    
    // =========================================
    // DATATABLE
    // =========================================
    
    let table = null;
    

    // =========================================
    // LOAD EMPLOYEES
    // =========================================

    function loadEmployees() {

        $.getJSON(API.getEmployees, function(res) {

            if (!res.success) {

                return showToast(
                    'danger',
                    'Failed to load employees'
                );
            }

            employees = res.data || [];

            let filterOptions = `
                <option value="">
                    All Employees
                </option>
            `;

            let modalOptions = `
                <option value="">
                    Select Employee
                </option>
            `;

            employees.forEach(emp => {

                filterOptions += `
                    <option value="${emp.id}">
                        ${emp.fullName}
                    </option>
                `;

                modalOptions += `
                    <option value="${emp.id}">
                        ${emp.fullName}
                    </option>
                `;
            });

            $('#employeeSelect').html(
                filterOptions
            );

            $('#modalEmployeeId').html(
                modalOptions
            );

        }).fail(function() {

            showToast(
                'danger',
                'Unable to load employees'
            );

        });

    }

    // =========================================
    // LOAD CATEGORIES
    // =========================================

    function loadCategories() {

        $.getJSON(API.getCategories, function(res) {

            if (!res.success) {

                return showToast(
                    'danger',
                    'Failed to load categories'
                );
            }

            categories =
                res.data.categories || [];

            let options = `
                <option value="">
                    Select Category
                </option>
            `;

            categories.forEach(cat => {

                options += `
                    <option value="${cat.id}">
                        ${cat.categoryName}
                    </option>
                `;
            });

            $('#categoryId').html(options);

        }).fail(function() {

            showToast(
                'danger',
                'Unable to load categories'
            );

        });

    }
    
    /*
    |--------------------------------------------------------------------------
    | Render Categories By Type
    |--------------------------------------------------------------------------
    */
    
    function renderCategoriesByType(type) {
    
        let options = `
            <option value="">
                Select Category
            </option>
        `;
    
        if (!type) {
    
            $('#categoryId').html(options);
    
            return;
        }
    
        categories.forEach(cat => {
    
            if (
                (cat.categoryType || '').toLowerCase()
                === type.toLowerCase()
            ) {
    
                options += `
                    <option value="${cat.id}">
                        ${cat.categoryName}
                    </option>
                `;
            }
        });
    
        $('#categoryId').html(options);
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Transaction Type Change
    |--------------------------------------------------------------------------
    */
    
    $('#transactionType').on(
        'change',
        function () {
    
            let type = $(this).val();
    
            renderCategoriesByType(type);
    
            $('#amount').val('');
    
            $('#categoryTypePreview').text('--');
    
            $('#commissionCalculationWrapper')
                .addClass('d-none');
    
            if (
                type.toLowerCase() === 'commission'
            ) {
    
                $('label[for="amount"], #amount')
                    .prev('.form-label')
                    .text('Base Amount');
    
            } else {
    
                $('label[for="amount"], #amount')
                    .prev('.form-label')
                    .text('Amount');
            }
        }
    );

    // =========================================
    // LOAD TRANSACTIONS
    // =========================================

    function loadTransactions(employeeId = '') {

        let url = API.getTransactions;

        if (employeeId) {

            url += '?employeeId=' + employeeId;
        }

        $.getJSON(url, function(res) {

            if (!res.success) {

                return showToast(
                    'danger',
                    res.message
                );
            }

            renderSummaryCards(
                res.data.summary || {}
            );

            renderTransactionTable(
                res.data.transactions || []
            );

        }).fail(function() {

            showToast(
                'danger',
                'Failed to load transactions'
            );

        });

    }

    // =========================================
    // RENDER SUMMARY
    // =========================================

    function renderSummaryCards(summary) {

        $('#pendingAmountCard').text(
            '₹ ' + (summary.pending || 0)
        );

        $('#approvedAmountCard').text(
            '₹ ' + (summary.approved || 0)
        );

        $('#payrollSyncedCard').text(
            '₹ ' + (summary.synced || 0)
        );

        $('#paidAmountCard').text(
            '₹ ' + (summary.paid || 0)
        );
    }

    // =========================================
    // STATUS COLOR
    // =========================================

    function getStatusColor(status) {

        status = (status || '').toLowerCase();

        if (status === 'approved')
            return 'success';

        if (status === 'rejected')
            return 'danger';

        if (status === 'paid')
            return 'success';

        if (status === 'synced')
            return 'info';

        return 'warning';
    }
    
    
    // =========================================
    // RESET SUBMIT BUTTON
    // =========================================
    
    function resetSubmitButton() {
    
        isSubmittingTransaction = false;
    
        $('#saveTransactionBtn')
            .prop('disabled', false)
            .html('Save Transaction');
    }

   // =========================================
    // RENDER TABLE
    // =========================================
    
    function renderTransactionTable(rows) {
    
        let html = '';
    
        if (!rows.length) {
    
            html = `
                <tr>
                    <td colspan="11"
                        class="text-center text-muted">
    
                        No records found
    
                    </td>
                </tr>
            `;
    
        } else {
    
            rows.forEach((row, i) => {
    
                /*
                |--------------------------------------------------------------------------
                | Dynamic Actions
                |--------------------------------------------------------------------------
                */
    
                let actions = '';
    
                const approvalStatus =
                    (row.approvalStatus || '')
                    .toLowerCase();
    
                const payrollStatus =
                    (row.payrollStatus || '')
                    .toLowerCase();
    
               /*
                |--------------------------------------------------------------------------
                | Pending Actions
                |--------------------------------------------------------------------------
                */
                
                if (
                    approvalStatus === 'pending'
                ) {
                
                    actions += `
                
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light approveTransactionBtn"
                            data-id="${row.id}"
                            title="Approve">
                
                            <i class="ri-check-line"></i>
                
                        </a>
                
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-warning-light btn-wave waves-effect waves-light rejectTransactionBtn"
                            data-id="${row.id}"
                            title="Reject">
                
                            <i class="ri-close-line"></i>
                
                        </a>
                
                    `;
                }
                
                /*
                |--------------------------------------------------------------------------
                | Edit Allowed
                |--------------------------------------------------------------------------
                */
                
                if (
                    payrollStatus !== 'paid'
                ) {
                
                    actions += `
                
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-primary-light btn-wave waves-effect waves-light editTransactionBtn"
                
                            data-id="${row.id}"
                
                            data-employee="${row.employeeId}"
                
                            data-category="${row.categoryId}"
                
                            data-amount="${row.amount}"
                
                            data-remarks="${row.remarks || ''}"
                
                            data-month="${row.effectiveMonth || ''}"
                
                            title="Edit">
                
                            <i class="ri-edit-line"></i>
                
                        </a>
                
                    `;
                }
                
                /*
                |--------------------------------------------------------------------------
                | Payroll Sync
                |--------------------------------------------------------------------------
                */
                
                if (
                    approvalStatus === 'approved' &&
                    payrollStatus === 'pending'
                ) {
                
                    actions += `
                
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light syncPayrollBtn"
                            data-id="${row.id}"
                            title="Sync Payroll">
                
                            <i class="ri-refresh-line"></i>
                
                        </a>
                
                    `;
                }
                
                /*
                |--------------------------------------------------------------------------
                | Delete Allowed
                |--------------------------------------------------------------------------
                */
                
                if (
                    payrollStatus !== 'paid'
                ) {
                
                    actions += `
                
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light deleteTransactionBtn"
                            data-id="${row.id}"
                            title="Delete">
                
                            <i class="ri-delete-bin-line"></i>
                
                        </a>
                
                    `;
                }
    
                /*
                |--------------------------------------------------------------------------
                | Table Row
                |--------------------------------------------------------------------------
                */
    
                html += `
                    <tr>
    
                        <td>${i + 1}</td>
    
                        <td>
                            ${row.transactionCode || '--'}
                        </td>
    
                        <td>
                            ${row.employeeName || '--'}
                        </td>
    
                        <td>
                            ${row.createdAt || '--'}
                        </td>
    
                        <td>
    
                            ${row.categoryName || '--'}
    
                            <div class="fs-11 text-muted">
    
                                ${row.categoryCode || '--'}
    
                            </div>
    
                        </td>
    
                        <td>

                            <span class="btn btn-outline-primary btn-sm">
                        
                                ${row.categoryType || '--'}
                        
                            </span>
                        
                        </td>
                        
                        <td>
                        
                            ₹ ${parseFloat(
                                row.amount || 0
                            ).toFixed(2)}
                        
                        </td>
                        
                        <td>
                        
                            <span class="btn btn-outline-${
                                getStatusColor(
                                    row.approvalStatus
                                )
                            } btn-sm">
                        
                                ${row.approvalStatus || '--'}
                        
                            </span>
                        
                        </td>
                        
                        <td>
                        
                            <span class="btn btn-outline-${
                                getStatusColor(
                                    row.payrollStatus
                                )
                            } btn-sm">
                        
                                ${row.payrollStatus || '--'}
                        
                            </span>
                        
                        </td>
    
                        <td>
    
                            ${row.remarks || '--'}
    
                        </td>
    
                        <td>
    
                            <div class="d-flex flex-wrap gap-1">
    
                                ${actions}
    
                            </div>
    
                        </td>
    
                    </tr>
                `;
            });
    
        }
    
      // =========================================
        // RELOAD DATATABLE
        // =========================================
        
        if ($.fn.DataTable.isDataTable('#transactions-datatable')) {
        
            table.clear().destroy();
        
        }
        
        $('#transactionTableBody').html(html);
        
       table = $('#transactions-datatable').DataTable({

        drawCallback: function() {
        
                    let api = this.api();
        
                    api.column(0, {
                        search: 'applied',
                        order: 'applied'
                    }).nodes().each(function(cell, i) {
        
                        cell.innerHTML = i + 1;
        
                    });
        
                },
        
                order: [],
        
                pageLength: 10,
        
                dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
        
                columnDefs: [
                    {
                        targets: 0,
                        orderable: false,
                        searchable: false
                    },
                    {
                        targets: 10,
                        orderable: false,
                        searchable: false
                    }
                ],
        
                buttons: [
                    {
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',
        
                        exportOptions: {
                            columns: [0,1,2,3,4,5,6,7,8,9]
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',
        
                        exportOptions: {
                            columns: [0,1,2,3,4,5,6,7,8,9]
                        }
                    }
                ]
            });
    }

    // =========================================
    // CATEGORY CHANGE
    // =========================================

    $('#categoryId').on('change', function() {

        let categoryId = $(this).val();

        let category = categories.find(
            c => c.id == categoryId
        );

        if (!category) {

            $('#categoryTypePreview').text('--');

            $('#taxablePreview').text('--');

            $('#payrollPreview').text('--');

            $('#approvalPreview').text('--');

            return;
        }

        $('#categoryTypePreview').text(
            category.categoryType || '--'
        );

        $('#taxablePreview').text(
            parseInt(category.taxable || 0) === 1
                ? 'Yes'
                : 'No'
        );

        $('#payrollPreview').text(
            parseInt(category.payrollApplicable || 0) === 1
                ? 'Yes'
                : 'No'
        );

        $('#approvalPreview').text(
            parseInt(category.requiresApproval || 0) === 1
                ? 'Yes'
                : 'No'
        );

        /*
        |--------------------------------------------------------------------------
        | Bonus Auto Amount
        |--------------------------------------------------------------------------
        */
        
        if (
            (category.categoryType || '').toLowerCase()
            === 'bonus'
        ) {
        
            $('#amount').val(
                category.defaultAmount || 0
            );
        
            $('#commissionCalculationWrapper')
                .addClass('d-none');
        
        } else {
        
            $('#amount').val('');
        
            $('#commissionCalculationWrapper')
                .removeClass('d-none');
        
            $('#commissionPercentagePreview').text(
        
                parseFloat(
                    category.commissionPercentage || 0
                ).toFixed(2) + '%'
            );
        
            $('#finalCommissionPreview').text(
                '₹ 0'
            );
        }

    });
    
    
    /*
    |--------------------------------------------------------------------------
    | Real-Time Commission Calculation
    |--------------------------------------------------------------------------
    */
    
    $('#amount').on(
        'keyup change',
        function () {
    
            let categoryId =
                $('#categoryId').val();
    
            let category = categories.find(
                c => c.id == categoryId
            );
    
            if (!category) {
                return;
            }
    
            if (
                (category.categoryType || '').toLowerCase()
                !== 'commission'
            ) {
                return;
            }
    
            let baseAmount =
                parseFloat(
                    $('#amount').val()
                ) || 0;
    
            let percentage =
                parseFloat(
                    category.commissionPercentage || 0
                );
    
            let finalCommission =
                (baseAmount * percentage) / 100;
    
            $('#finalCommissionPreview').text(
                '₹ ' + finalCommission.toFixed(2)
            );
        }
    );

    // =========================================
    // EMPLOYEE FILTER
    // =========================================

    $('#employeeSelect').on('change', function() {

        selectedEmployeeId = $(this).val();

        loadTransactions(
            selectedEmployeeId
        );

    });
    
    
    // =========================================
    // SEARCH
    // =========================================
    
    $('#tableSearch').on('keyup', function() {
    
        table.search(this.value).draw();
    
    });
    
    // =========================================
    // EXPORT
    // =========================================
    
    $('.export-btn').on('click', function() {
    
        const type = $(this).data('type');
    
        if (type === 'csv') {
    
            table.buttons('.buttons-csv').trigger();
    
        } else if (type === 'pdf') {
    
            table.buttons('.buttons-pdf').trigger();
    
        }
    
    });

    // =========================================
    // EDIT TRANSACTION
    // =========================================

    $(document).on(
        'click',
        '.editTransactionBtn',
        function() {

            isEditMode = true;

            editingTransactionId =
                $(this).data('id');

            $('#transactionId').val(
                editingTransactionId
            );

            $('#modalEmployeeId').val(
                $(this).data('employee')
            );
            
            
            let category = categories.find(
                c => c.id == $(this).data('category')
            );
            
            if (category) {
            
                $('#transactionType')
                    .val(category.categoryType)
                    .trigger('change');
            }
            

            $('#categoryId')
                .val($(this).data('category'))
                .trigger('change');

            $('#amount').val(
                $(this).data('amount')
            );

            $('#remarks').val(
                $(this).data('remarks')
            );

            $('#effectiveMonth').val(
                $(this).data('month')
            );

            $('.modal-title').text(
                'Edit Commission Transaction'
            );

            $('#commissionTransactionModal')
                .modal('show');

        }
    );

    // =========================================
    // SAVE TRANSACTION
    // =========================================

    $('#saveTransactionBtn').on('click', function() {
        
        if (isSubmittingTransaction) {
            return;
        }
        
        isSubmittingTransaction = true;
        
        $('#saveTransactionBtn')
            .prop('disabled', true)
            .html(`
                <span class="spinner-border spinner-border-sm me-1"></span>
                Saving...
            `);

        let formData = new FormData();

        formData.append(
            'transactionId',
            editingTransactionId
        );

        formData.append(
            'employeeId',
            $('#modalEmployeeId').val()
        );

        formData.append(
            'categoryId',
            $('#categoryId').val()
        );

        formData.append(
            'amount',
            $('#amount').val()
        );

        formData.append(
            'effectiveMonth',
            $('#effectiveMonth').val()
        );

        formData.append(
            'remarks',
            $('#remarks').val().trim()
        );

        if ($('#attachment')[0].files[0]) {

            formData.append(
                'attachment',
                $('#attachment')[0].files[0]
            );
        }

        // VALIDATION

        if (!$('#modalEmployeeId').val()) {

            resetSubmitButton();
        
            return showToast(
                'warning',
                'Select employee'
            );
        }

        if (!$('#categoryId').val()) {

            resetSubmitButton();
        
            return showToast(
                'warning',
                'Select category'
            );
        }

        if (
            !$('#amount').val() ||
            $('#amount').val() <= 0
        ) {
        
            resetSubmitButton();
        
            return showToast(
                'warning',
                'Invalid amount'
            );
        }

        $.ajax({

            url: isEditMode
                ? API.updateTransaction
                : API.saveTransaction,

            type: 'POST',

            data: formData,

            processData: false,

            contentType: false,

            dataType: 'json',

            success: function(res) {
                
                resetSubmitButton();

                if (!res.success) {

                    return showToast(
                        'danger',
                        res.message
                    );
                }

                showToast(
                    'success',
                    res.message
                );

                $('#commissionTransactionModal')
                    .modal('hide');

                resetTransactionForm();

                loadTransactions(
                    $('#employeeSelect').val()
                );

            },

            error: function() {
                
                resetSubmitButton();

                showToast(
                    'danger',
                    'Server error'
                );

            }

        });

    });

    // =========================================
    // DELETE TRANSACTION
    // =========================================

    $(document).on(
        'click',
        '.deleteTransactionBtn',
        function() {

            let transactionId =
                $(this).data('id');

            Swal.fire({

                title: 'Delete Transaction?',

                text:
                    'This transaction will be reverted.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonColor: '#d33',

                confirmButtonText: 'Delete'

            }).then(result => {

                if (!result.isConfirmed)
                    return;

                $.ajax({

                    url: API.deleteTransaction,

                    type: 'POST',

                    data: {
                        transactionId: transactionId
                    },

                    dataType: 'json',

                    success: function(res) {
                        
                        if (!res.success) {

                            return showToast(
                                'danger',
                                res.message
                            );
                        }

                        showToast(
                            'success',
                            res.message
                        );

                        loadTransactions(
                            $('#employeeSelect').val()
                        );

                    },

                    error: function() {
                        
                        showToast(
                            'danger',
                            'Failed to delete transaction'
                        );

                    }

                });

            });

        }
    );
    
    
    // =========================================
    // APPROVE TRANSACTION
    // =========================================
    
    $(document).on(
        'click',
        '.approveTransactionBtn',
        function() {
    
            let transactionId =
                $(this).data('id');
    
            Swal.fire({
    
                title:
                    'Approve Transaction?',
    
                text:
                    'This transaction will become payroll eligible.',
    
                icon: 'question',
    
                showCancelButton: true,
    
                confirmButtonText:
                    'Approve',
    
                confirmButtonColor:
                    '#198754'
    
            }).then(result => {
    
                if (!result.isConfirmed)
                    return;
    
                $.ajax({
    
                    url:
                        API.approveTransaction,
    
                    type: 'POST',
    
                    data: {
    
                        transactionId:
                            transactionId
                    },
    
                    dataType: 'json',
    
                    success: function(res) {
    
                        if (!res.success) {
    
                            return showToast(
                                'danger',
                                res.message
                            );
                        }
    
                        showToast(
                            'success',
                            res.message
                        );
    
                        loadTransactions(
                            $('#employeeSelect').val()
                        );
    
                    },
    
                    error: function(xhr) {
    
                        console.log(xhr.responseText);
    
                        showToast(
                            'danger',
                            'Approval failed.'
                        );
    
                    }
                });
    
            });
    
        }
    );
        
        
        // =========================================
    // REJECT TRANSACTION
    // =========================================
    
    $(document).on(
        'click',
        '.rejectTransactionBtn',
        function() {
    
            let transactionId =
                $(this).data('id');
    
            Swal.fire({
    
                title:
                    'Reject Transaction',
    
                input: 'textarea',
    
                inputLabel:
                    'Rejection Reason',
    
                inputPlaceholder:
                    'Enter rejection reason...',
    
                showCancelButton: true,
    
                confirmButtonText:
                    'Reject',
    
                confirmButtonColor:
                    '#d33'
    
            }).then(result => {
    
                if (!result.isConfirmed)
                    return;
    
                $.ajax({
    
                    url:
                        API.rejectTransaction,
    
                    type: 'POST',
    
                    data: {
    
                        transactionId:
                            transactionId,
    
                        reason:
                            result.value || ''
                    },
    
                    dataType: 'json',
    
                    success: function(res) {
                        
                        if (!res.success) {
    
                            return showToast(
                                'danger',
                                res.message
                            );
                        }
    
                        showToast(
                            'success',
                            res.message
                        );
    
                        loadTransactions(
                            $('#employeeSelect').val()
                        );
    
                    },
    
                    error: function(xhr) {
    
                        console.log(xhr.responseText);
    
                        showToast(
                            'danger',
                            'Rejection failed.'
                        );
    
                    }
                });
    
            });
    
        }
    );
    
    
    // =========================================
    // SYNC PAYROLL
    // =========================================
    
    $(document).on(
        'click',
        '.syncPayrollBtn',
        function() {
    
            let transactionId =
                $(this).data('id');
    
            Swal.fire({
    
                title:
                    'Sync To Payroll?',
    
                text:
                    'This transaction will become payroll eligible.',
    
                icon: 'question',
    
                showCancelButton: true,
    
                confirmButtonText:
                    'Sync Payroll',
    
                confirmButtonColor:
                    '#0dcaf0'
    
            }).then(result => {
    
                if (!result.isConfirmed)
                    return;
    
                $.ajax({
    
                    url:
                        API.syncPayroll,
    
                    type: 'POST',
    
                    data: {
    
                        transactionId:
                            transactionId
                    },
    
                    dataType: 'json',
    
                    success: function(res) {
    
                        if (!res.success) {
    
                            return showToast(
                                'danger',
                                res.message
                            );
                        }
    
                        showToast(
                            'success',
                            res.message
                        );
    
                        loadTransactions(
                            $('#employeeSelect').val()
                        );
    
                    },
    
                    error: function(xhr) {
    
                        console.log(
                            xhr.responseText
                        );
    
                        showToast(
                            'danger',
                            'Payroll sync failed.'
                        );
    
                    }
                });
    
            });
    
        }
    );
    

    // =========================================
    // RESET FORM
    // =========================================

    function resetTransactionForm() {

        $('#transactionId').val('');

        $('#modalEmployeeId').val('');

        $('#categoryId').val('');

        $('#amount').val('');

        $('#remarks').val('');

        $('#effectiveMonth').val('');

        $('#attachment').val('');

        $('#categoryTypePreview').text('--');

        $('#taxablePreview').text('--');

        $('#payrollPreview').text('--');

        $('#approvalPreview').text('--');
        
        $('#transactionType').val('');
        
        $('#commissionCalculationWrapper')
            .addClass('d-none');

        isEditMode = false;

        editingTransactionId = null;

        $('.modal-title').text(
            'Add Commission Transaction'
        );
    }

    // =========================================
    // MODAL CLOSE RESET
    // =========================================

    $('#commissionTransactionModal').on(
        'hidden.bs.modal',
        function() {

            resetTransactionForm();
        }
    );

    // =========================================
    // INIT
    // =========================================

    loadEmployees();

    loadCategories();

    loadTransactions();

});

</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>