<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

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

.status-chip-success {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
}

.status-chip-info {
    color: rgb(var(--info-rgb));
    border-color: rgba(var(--info-rgb), 0.3);
}

.status-chip-danger {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), 0.3);
}

#otStatusFilter {
    min-width: 180px;
}

.table-responsive::-webkit-scrollbar {
    display: none;
}

#overtimeTable td,
#overtimeTable th {
    white-space: nowrap;
    vertical-align: middle;
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 d-flex justify-content-between align-items-center">
            <h1 class="page-title fw-medium fs-18 mb-2">Overtime Management</h1>

            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addOvertimeModal">
                <i class="ri-add-line me-1"></i> Add Overtime
            </button>
        </div>

        <!-- FILTER CARD -->
        <div class="card custom-card mb-3">
        
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
        
                                    <li>
        
                                        <a class="dropdown-item export-btn"
                                            data-type="print"
                                            href="javascript:void(0);">
        
                                            Print
        
                                        </a>
        
                                    </li>
        
                                </ul>
        
                            </div>
        
                        </div>
        
                        <!-- STATUS FILTER -->
                        <select id="otStatusFilter"
                            class="form-select form-select-lg">
        
                            <option value="">
                                Status
                            </option>
        
                            <option value="pending">
                                Pending
                            </option>
        
                            <option value="approved">
                                Approved
                            </option>
        
                            <option value="rejected">
                                Rejected
                            </option>
        
                        </select>
        
                    </div>
        
                    <!-- CENTER SPACE -->
                    <div class="flex-fill"></div>
        
                    <!-- RIGHT -->
                    <div>
        
                        <input id="otTableSearch"
                            class="form-control form-control-sm"
                            placeholder="Search..."
                            autocomplete="off">
        
                    </div>
        
                </div>
        
            </div>
        
        </div>

        <!-- TABLE -->
        <div class="card custom-card">
            <div class="card-body">
                <table id="overtimeTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee</th>
                            <th>Date</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Total</th>
                            <th>OT</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- MODAL -->
<div class="modal fade" id="addOvertimeModal">
    <div class="modal-dialog">
        <div class="modal-content">

            <form id="addOvertimeForm">

                <div class="modal-header">
                    <h5>Add Overtime</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <select name="employeeId" id="otEmployee" class="form-select mb-2" required></select>

                    <input type="date" name="date" class="form-control mb-2" required>
                    <input type="time" name="startTime" class="form-control mb-2" required>
                    <input type="time" name="endTime" class="form-control mb-2" required>

                    <textarea name="reason" class="form-control" placeholder="Reason"></textarea>

                </div>

                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>

            </form>

        </div>
    </div>
</div>

<div class="modal fade" id="rejectModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form id="rejectForm">

                <input type="hidden" id="rejectId" name="id">

                <div class="modal-header">
                    <h5>Reject Overtime</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <textarea name="remarks" class="form-control" placeholder="Enter reason" required></textarea>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-danger">Reject</button>
                </div>

            </form>

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
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {

    let table;

    ////////////////////////////////////////////////////////////
    // TOAST
    ////////////////////////////////////////////////////////////

    function showToast(type, msg) {

        window.showToast
            ? window.showToast(type, msg)
            : console.log(msg);

    }

    ////////////////////////////////////////////////////////////
    // UPDATE ROW
    ////////////////////////////////////////////////////////////

    function updateRow(id, newData) {

        table.rows().every(function() {

            let d = this.data();

            if (d.id == id) {

                this.data({
                    ...d,
                    ...newData
                }).invalidate();

            }

        });

        table.draw(false);

    }

    ////////////////////////////////////////////////////////////
    // DATATABLE (REAL API)
    ////////////////////////////////////////////////////////////

    table = $('#overtimeTable').DataTable({

        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",

        pageLength: 10,

        responsive: true,

        buttons: [
            {
                extend: 'csvHtml5',
                className: 'd-none buttons-csv',

                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7]
                }
            },
            {
                extend: 'pdfHtml5',
                className: 'd-none buttons-pdf',

                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7]
                }
            },
            {
                extend: 'print',
                className: 'd-none buttons-print',

                exportOptions: {
                    columns: [0,1,2,3,4,5,6,7]
                }
            }
        ],

        ajax: {

            url: API_BASE + '/overtime/getOvertime.php',

            dataSrc: function(res) {

                return res.data || [];

            }

        },

        columns: [

            {
                data: null
            },

            {
                data: 'employeeName'
            },

            {
                data: 'date'
            },

            {
                data: 'startTime'
            },

            {
                data: 'endTime'
            },

            {
                data: 'totalHours'
            },

            {
                data: 'otHours'
            },

            {
                data: 'status',

                render: function(d) {

                    let cls =
                        d == 'approved'
                            ? 'status-chip-success'
                            : d == 'pending'
                                ? 'status-chip-info'
                                : 'status-chip-danger';

                    return `
                        <span class="status-chip ${cls}">
                            ${d}
                        </span>
                    `;

                }
            },

            {
                data: null,

                orderable: false,

                searchable: false,

                render: function(d) {

                    if (d.status != 'pending') {

                        return '-';

                    }

                    return `
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-outline-success btn-wave waves-effect waves-light approve-btn"
                            data-id="${d.id}"
                            title="Approve">
                            <i class="ri-check-line"></i>
                        </a>
                    
                        <a href="javascript:void(0);"
                            class="btn btn-icon btn-sm btn-outline-danger btn-wave waves-effect waves-light reject-btn"
                            data-id="${d.id}"
                            title="Reject">
                            <i class="ri-close-line"></i>
                        </a>
                    `;
                }
            }

        ],

        drawCallback: function() {

            let api = this.api();

            api.column(0, {
                search: 'applied',
                order: 'applied'
            }).nodes().each(function(cell, i) {

                cell.innerHTML = i + 1;

            });

        }

    });

    ////////////////////////////////////////////////////////////
    // FILTERS
    ////////////////////////////////////////////////////////////

    $('#otStatusFilter').on('change', function() {

        table
            .column(7)
            .search(this.value)
            .draw();

    });

    $('#otTableSearch').on('keyup', function() {

        table
            .search(this.value)
            .draw();

    });

    ////////////////////////////////////////////////////////////
    // EXPORT
    ////////////////////////////////////////////////////////////

    $('.export-btn').on('click', function() {

        const type = $(this).data('type');

        if (type === 'csv') {

            table.buttons('.buttons-csv').trigger();

        } else if (type === 'pdf') {

            table.buttons('.buttons-pdf').trigger();

        } else if (type === 'print') {

            table.buttons('.buttons-print').trigger();

        }

    });

    ////////////////////////////////////////////////////////////
    // LOAD EMPLOYEES (REAL)
    ////////////////////////////////////////////////////////////

    $('#addOvertimeModal').on(
        'show.bs.modal',
        function() {

            $.get(
                API_BASE + '/employee/getEmployees.php',

                function(res) {

                    if (!res.success)
                        return;

                    let html = `
                        <option value="">
                            Select Employee
                        </option>
                    `;

                    res.data.forEach(e => {

                        html += `
                            <option value="${e.id}">
                                ${e.fullName}
                            </option>
                        `;

                    });

                    $('#otEmployee').html(html);

                },

                'json'
            );

        }
    );

    ////////////////////////////////////////////////////////////
    // ADD OVERTIME (REAL API)
    ////////////////////////////////////////////////////////////

    $('#addOvertimeForm').submit(function(e) {

        e.preventDefault();

        $.post(

            API_BASE + '/overtime/addOvertime.php',

            $(this).serialize(),

            function(res) {

                if (!res.success) {

                    showToast(
                        'danger',
                        res.message
                    );

                    return;

                }

                $('#addOvertimeModal')
                    .modal('hide');

                $('#addOvertimeForm')[0]
                    .reset();

                table.ajax.reload(
                    null,
                    false
                );

                showToast(
                    'success',
                    'Overtime added'
                );

            },

            'json'
        );

    });

    ////////////////////////////////////////////////////////////
    // APPROVE
    ////////////////////////////////////////////////////////////

    $(document).on(
        'click',
        '.approve-btn',
        function() {

            let id = $(this).data('id');

            $.post(

                API_BASE + '/overtime/approveOvertime.php',

                {
                    id
                },

                function(res) {

                    if (!res.success) {

                        showToast(
                            'danger',
                            res.message
                        );

                        return;

                    }

                    updateRow(
                        id,
                        res.data
                    );

                    showToast(
                        'success',
                        'Approved'
                    );

                },

                'json'
            );

        }
    );

    ////////////////////////////////////////////////////////////
    // REJECT OPEN MODAL
    ////////////////////////////////////////////////////////////

    $(document).on(
        'click',
        '.reject-btn',
        function() {

            $('#rejectId').val(
                $(this).data('id')
            );

            $('#rejectModal')
                .modal('show');

        }
    );

    ////////////////////////////////////////////////////////////
    // REJECT SUBMIT
    ////////////////////////////////////////////////////////////

    $('#rejectForm').submit(function(e) {

        e.preventDefault();

        $.post(

            API_BASE + '/overtime/rejectOvertime.php',

            $(this).serialize(),

            function(res) {

                if (!res.success) {

                    showToast(
                        'danger',
                        res.message
                    );

                    return;

                }

                $('#rejectModal')
                    .modal('hide');

                updateRow(
                    res.data.id,
                    res.data
                );

                showToast(
                    'success',
                    'Rejected'
                );

            },

            'json'
        );

    });

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>