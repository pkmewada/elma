<?php

include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/employeeInfoEngine.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';

?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">



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

        </div>

        <!-- SUMMARY CARDS -->

        <div class="row">

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Total Pending
                        </div>

                        <h4 class="fw-semibold text-warning mb-0" id="pendingAmountCard">

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

                        <h4 class="fw-semibold text-success mb-0" id="approvedAmountCard">

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

                        <h4 class="fw-semibold text-info mb-0" id="payrollSyncedCard">

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

                        <h4 class="fw-semibold text-primary mb-0" id="paidAmountCard">

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

                                        <button type="button" class="btn btn-outline-primary dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">

                                            Export

                                        </button>

                                        <ul class="dropdown-menu">

                                            <li>

                                                <a class="dropdown-item export-btn" data-type="csv"
                                                    href="javascript:void(0);">

                                                    CSV

                                                </a>

                                            </li>

                                            <li>

                                                <a class="dropdown-item export-btn" data-type="pdf"
                                                    href="javascript:void(0);">

                                                    PDF

                                                </a>

                                            </li>

                                        </ul>

                                    </div>

                                </div>

                            </div>

                            <!-- CENTER SPACE -->
                            <div class="flex-fill"></div>

                            <!-- RIGHT -->
                            <div class="d-flex">

                                <input id="tableSearch" class="form-control form-control-sm"
                                    placeholder="Search transactions..." autocomplete="off">

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

                            <table id="transactions-datatable" data-ui-table="mamix"
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
                                    </tr>

                                </thead>

                                <tbody id="transactionTableBody">

                                    <tr>

                                        <td colspan="11" class="text-center text-muted">

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

        getTransactions: API_BASE + '/commission/emp-getCommissionTransactions.php'
    };

    // =========================================
    // DATATABLE
    // =========================================

    let table = null;

    // =========================================
    // LOAD TRANSACTIONS
    // =========================================

    function loadTransactions() {

        $.getJSON(API.getTransactions, function(res) {

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

        if (status === 'approved') {
            return 'success';
        }

        if (status === 'rejected') {
            return 'danger';
        }

        if (status === 'paid') {
            return 'success';
        }

        if (status === 'synced') {
            return 'info';
        }

        return 'warning';
    }

    // =========================================
    // RENDER TABLE
    // =========================================

    function renderTransactionTable(rows) {

        let html = '';

        if (!rows.length) {

            html = `
                <tr>

                    <td colspan="10"
                        class="text-center text-muted">

                        No records found

                    </td>

                </tr>
            `;

        } else {

            rows.forEach((row, i) => {

                html += `
                    <tr>

                        <td>
                            ${i + 1}
                        </td>

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

            columnDefs: [{
                targets: 0,
                orderable: false,
                searchable: false
            }],

            buttons: [{
                    extend: 'csvHtml5',
                    className: 'd-none buttons-csv',

                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    className: 'd-none buttons-pdf',

                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]
                    }
                }
            ]
        });
    }

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
    // INIT
    // =========================================

    loadTransactions();

});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>