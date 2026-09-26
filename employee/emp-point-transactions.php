<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
?>
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
<link rel="stylesheet"
    href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
    href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex justify-content-between align-items-center">

            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">
                    Employee Point Management
                </h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>

                    <li class="breadcrumb-item active">
                        Point Transactions
                    </li>
                </ol>
            </div>
        </div>

        <!-- BALANCE CARDS -->
        <div class="row">

            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="fs-12 text-muted mb-1">
                            Monthly Allocation
                        </div>

                        <h4 class="fw-semibold mb-0" id="monthlyAllocationCard">
                            0
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="fs-12 text-muted mb-1">
                            Total Credits
                        </div>

                        <h4 class="fw-semibold text-success mb-0" id="creditsCard">
                            0
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="fs-12 text-muted mb-1">
                            Total Debits
                        </div>

                        <h4 class="fw-semibold text-danger mb-0" id="debitsCard">
                            0
                        </h4>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="fs-12 text-muted mb-1">
                            Current Balance
                        </div>

                        <h4 class="fw-semibold text-primary mb-0" id="balanceCard">
                            0
                        </h4>
                    </div>
                </div>
            </div>

        </div>

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

                            </div>
        
                            <!-- MIDDLE -->
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
                            My Point Transactions
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
                                        <th>Date</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Points</th>
                                        <th>Remarks</th>
                                        <th>Status</th>
        
                                    </tr>
        
                                </thead>
        
                                <tbody id="transactionTableBody">
        
                                    <tr>
        
                                        <td colspan="7"
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
<div class="modal fade" id="pointTransactionModal">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Add Point Transaction
                </h5>

                <button class="btn-close" data-bs-dismiss="modal"></button>

            </div>

            <div class="modal-body">

                <div class="mb-3">

                    <label class="form-label">
                        Employee
                    </label>

                    <select id="modalEmployeeId" class="form-select">
                        <option value="">Select Employee</option>
                    </select>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Category
                    </label>

                    <select id="categoryId" class="form-select">
                        <option value="">Select Category</option>
                    </select>

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Points
                    </label>

                    <input type="number"
                        class="form-control"
                        id="points"
                        min="1">

                </div>

                <div class="mb-3">

                    <label class="form-label">
                        Remarks
                    </label>

                    <textarea id="remarks"
                        class="form-control"
                        rows="3"></textarea>

                </div>

                <!-- PREVIEW -->
                <div class="p-3 border rounded bg-light">

                    <div class="d-flex justify-content-between mb-2">
                        <span>Transaction Type:</span>
                        <strong id="transactionTypePreview">
                            --
                        </strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Default Points:</span>
                        <strong id="defaultPointsPreview">
                            --
                        </strong>
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

        getTransactions:
            API_BASE + '/points/emp-getPointTransactions.php'
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

            renderBalanceCards(
                res.data.balance || {}
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
    // BALANCE CARDS
    // =========================================

    function renderBalanceCards(balance) {

        $('#monthlyAllocationCard').text(
            balance.monthlyAllocation || 0
        );

        $('#creditsCard').text(
            balance.credits || 0
        );

        $('#debitsCard').text(
            balance.debits || 0
        );

        $('#balanceCard').text(
            balance.balance || 0
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

                    <td colspan="7"
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

                            ${row.transactionDate || '--'}

                        </td>

                        <td>

                            ${row.categoryName || '--'}

                            <small class="text-muted">

                                (${row.categoryCode || '--'})

                            </small>

                        </td>

                        <td>

                            <span class="btn btn-outline-${
                                row.transactionType === 'Credit'
                                    ? 'success'
                                    : 'danger'
                            } btn-sm">

                                ${row.transactionType || '--'}

                            </span>

                        </td>

                        <td>

                            ${row.points || 0}

                        </td>

                        <td>

                            ${row.remarks || '--'}

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

                    </tr>
                `;
            });
        }

        // =========================================
        // DESTROY EXISTING
        // =========================================

        if ($.fn.DataTable.isDataTable('#transactions-datatable')) {

            table.destroy();
        }

        // =========================================
        // UPDATE BODY
        // =========================================

        $('#transactionTableBody').html(html);

        // =========================================
        // REINITIALIZE
        // =========================================

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
                    targets: [0],
                    orderable: false,
                    searchable: false
                }
            ],

            buttons: [
                {
                    extend: 'csvHtml5',
                    className: 'd-none buttons-csv',

                    exportOptions: {
                        columns: [0,1,2,3,4,5,6]
                    }
                },
                {
                    extend: 'pdfHtml5',
                    className: 'd-none buttons-pdf',

                    exportOptions: {
                        columns: [0,1,2,3,4,5,6]
                    }
                }
            ]
        });
    }

    // =========================================
    // SEARCH
    // =========================================

    $('#tableSearch').on('keyup', function() {

        if (table) {

            table.search(this.value).draw();
        }
    });

    // =========================================
    // EXPORT
    // =========================================

    $('.export-btn').on('click', function() {

        if (!table)
            return;

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