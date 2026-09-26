<?php
include __DIR__ . '/../includes/emp-auth.php';
require_once __DIR__ . '/../includes/db.php';
?>

<?php include __DIR__ . '/../includes/emp-header.php'; ?>
<?php include __DIR__ . '/../includes/emp-sidebar.php'; ?>

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

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#addOvertimeModal"
            >
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
<!-- ADD OVERTIME MODAL -->
<div class="modal fade"
     id="addOvertimeModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form id="addOvertimeForm">

                <!-- Modal Header -->
                <div class="modal-header">

                    <h5 class="modal-title">
                        Add Overtime
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <!-- Modal Body -->
                <div class="modal-body">

                    <!-- Overtime Date -->
                    <div class="mb-3">

                        <label class="form-label">
                            Overtime Date
                        </label>

                        <input type="date"
                               name="date"
                               class="form-control"
                               required>

                    </div>

                    <!-- Start Time -->
                    <div class="mb-3">

                        <label class="form-label">
                            Start Time
                        </label>

                        <input type="time"
                               name="startTime"
                               class="form-control"
                               required>

                    </div>

                    <!-- End Time -->
                    <div class="mb-3">

                        <label class="form-label">
                            End Time
                        </label>

                        <input type="time"
                               name="endTime"
                               class="form-control"
                               required>

                    </div>

                    <!-- Reason -->
                    <div class="mb-0">

                        <label class="form-label">
                            Reason
                        </label>

                        <textarea name="reason"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Enter overtime reason"></textarea>

                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="saveOvertimeBtn">

                        <span class="save-text">
                            Save Overtime
                        </span>

                        <span class="spinner-border spinner-border-sm d-none save-spinner"
                              role="status"
                              aria-hidden="true">
                        </span>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- EDIT OVERTIME MODAL -->
<div class="modal fade"
     id="editOvertimeModal"
     tabindex="-1"
     aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form id="editOvertimeForm">

                <!-- Hidden ID -->
                <input type="hidden"
                       name="id"
                       id="editId">

                <!-- Modal Header -->
                <div class="modal-header">

                    <h5 class="modal-title">
                        Edit Overtime
                    </h5>

                    <button type="button"
                            class="btn-close"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <!-- Modal Body -->
                <div class="modal-body">

                    <!-- Date -->
                    <div class="mb-3">

                        <label class="form-label">
                            Overtime Date
                        </label>

                        <input type="date"
                               name="date"
                               id="editDate"
                               class="form-control"
                               required>

                    </div>

                    <!-- Start Time -->
                    <div class="mb-3">

                        <label class="form-label">
                            Start Time
                        </label>

                        <input type="time"
                               name="startTime"
                               id="editStartTime"
                               class="form-control"
                               required>

                    </div>

                    <!-- End Time -->
                    <div class="mb-3">

                        <label class="form-label">
                            End Time
                        </label>

                        <input type="time"
                               name="endTime"
                               id="editEndTime"
                               class="form-control"
                               required>

                    </div>

                    <!-- Reason -->
                    <div class="mb-0">

                        <label class="form-label">
                            Reason
                        </label>

                        <textarea name="reason"
                                  id="editReason"
                                  class="form-control"
                                  rows="4"
                                  placeholder="Enter overtime reason"></textarea>

                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">

                    <button type="button"
                            class="btn btn-light"
                            data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button type="submit"
                            class="btn btn-primary"
                            id="updateOvertimeBtn">

                        <span class="update-text">
                            Update Overtime
                        </span>

                        <span class="spinner-border spinner-border-sm d-none update-spinner"
                              role="status"
                              aria-hidden="true">
                        </span>

                    </button>

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

$(function () {

    /*
    |--------------------------------------------------------------------------
    | Datatable Instance
    |--------------------------------------------------------------------------
    */

    let table;

    /*
    |--------------------------------------------------------------------------
    | Toast Helper
    |--------------------------------------------------------------------------
    */

    function showToast(type, message) {

        if (window.showToast) {

            window.showToast(type, message);

        } else {

            console.log(message);

        }

    }

    /*
    |--------------------------------------------------------------------------
    | Reload Datatable
    |--------------------------------------------------------------------------
    */

    function reloadTable() {

        table.ajax.reload(
            null,
            false
        );

    }

    /*
    |--------------------------------------------------------------------------
    | Initialize DataTable
    |--------------------------------------------------------------------------
    */

    table = $('#overtimeTable').DataTable({

        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",

        pageLength: 10,

        responsive: true,

        ordering: false,

        buttons: [

            /*
            |--------------------------------------------------------------------------
            | CSV Export
            |--------------------------------------------------------------------------
            */

            {
                extend: 'csvHtml5',

                className: 'd-none buttons-csv',

                exportOptions: {

                    columns: [0,1,2,3,4,5,6]

                }

            },

            /*
            |--------------------------------------------------------------------------
            | PDF Export
            |--------------------------------------------------------------------------
            */

            {
                extend: 'pdfHtml5',

                className: 'd-none buttons-pdf',

                exportOptions: {

                    columns: [0,1,2,3,4,5,6]

                }

            },

            /*
            |--------------------------------------------------------------------------
            | Print Export
            |--------------------------------------------------------------------------
            */

            {
                extend: 'print',

                className: 'd-none buttons-print',

                exportOptions: {

                    columns: [0,1,2,3,4,5,6]

                }

            }

        ],

        /*
        |--------------------------------------------------------------------------
        | AJAX Source
        |--------------------------------------------------------------------------
        */

        ajax: {

            url: API_BASE + '/overtime/emp-getOvertime.php',

            dataSrc: function (response) {

                return response.data || [];

            }

        },

        /*
        |--------------------------------------------------------------------------
        | Table Columns
        |--------------------------------------------------------------------------
        */

        columns: [

            /*
            |--------------------------------------------------------------------------
            | Serial Number
            |--------------------------------------------------------------------------
            */

            {
                data: null
            },

            /*
            |--------------------------------------------------------------------------
            | Overtime Date
            |--------------------------------------------------------------------------
            */

            {
                data: 'date'
            },

            /*
            |--------------------------------------------------------------------------
            | Start Time
            |--------------------------------------------------------------------------
            */

            {
                data: 'startTime'
            },

            /*
            |--------------------------------------------------------------------------
            | End Time
            |--------------------------------------------------------------------------
            */

            {
                data: 'endTime'
            },

            /*
            |--------------------------------------------------------------------------
            | Total Hours
            |--------------------------------------------------------------------------
            */

            {
                data: 'totalHours'
            },

            /*
            |--------------------------------------------------------------------------
            | OT Hours
            |--------------------------------------------------------------------------
            */

            {
                data: 'otHours'
            },

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            {
                data: 'status',

                render: function (data) {

                    let statusClass =

                        data === 'approved'

                            ? 'status-chip-success'

                            : data === 'pending'

                                ? 'status-chip-info'

                                : 'status-chip-danger';

                    return `

                        <span class="status-chip ${statusClass}">

                            ${data}

                        </span>

                    `;

                }

            },

            /*
            |--------------------------------------------------------------------------
            | Actions
            |--------------------------------------------------------------------------
            */

            {
                data: null,

                orderable: false,

                searchable: false,

                render: function (data) {

                    /*
                    |--------------------------------------------------------------------------
                    | Allow Edit/Delete Only for Pending
                    |--------------------------------------------------------------------------
                    */

                    if (data.status !== 'pending') {

                        return '-';

                    }

                    return `

                        <div class="d-flex align-items-center gap-1">

                            <a href="javascript:void(0);"
                               class="btn btn-icon btn-sm btn-outline-primary edit-btn"
                               data-id="${data.id}"
                               title="Edit">

                                <i class="ri-edit-line"></i>

                            </a>

                            <a href="javascript:void(0);"
                               class="btn btn-icon btn-sm btn-outline-danger delete-btn"
                               data-id="${data.id}"
                               title="Delete">

                                <i class="ri-delete-bin-line"></i>

                            </a>

                        </div>

                    `;

                }

            }

        ],

        /*
        |--------------------------------------------------------------------------
        | Serial Number Render
        |--------------------------------------------------------------------------
        */

        drawCallback: function () {

            let api = this.api();

            api.column(0, {

                search: 'applied',

                order: 'applied'

            }).nodes().each(function (cell, index) {

                cell.innerHTML = index + 1;

            });

        }

    });

    /*
    |--------------------------------------------------------------------------
    | Status Filter
    |--------------------------------------------------------------------------
    */

    $('#otStatusFilter').on('change', function () {

        table
            .column(6)
            .search(this.value)
            .draw();

    });

    /*
    |--------------------------------------------------------------------------
    | Global Search
    |--------------------------------------------------------------------------
    */

    $('#otTableSearch').on('keyup', function () {

        table
            .search(this.value)
            .draw();

    });

    /*
    |--------------------------------------------------------------------------
    | Export Actions
    |--------------------------------------------------------------------------
    */

    $('.export-btn').on('click', function () {

        let type = $(this).data('type');

        if (type === 'csv') {

            table.buttons('.buttons-csv').trigger();

        }

        else if (type === 'pdf') {

            table.buttons('.buttons-pdf').trigger();

        }

        else if (type === 'print') {

            table.buttons('.buttons-print').trigger();

        }

    });

    /*
    |--------------------------------------------------------------------------
    | Add Overtime Request
    |--------------------------------------------------------------------------
    */

    $('#addOvertimeForm').submit(function (e) {

        e.preventDefault();

        let form = $(this);

        let submitBtn =
            form.find('button[type="submit"]');

        submitBtn.prop(
            'disabled',
            true
        );

        $.post(

            API_BASE + '/overtime/emp-addOvertime.php',

            form.serialize(),

            function (response) {

                if (!response.success) {

                    showToast(
                        'danger',
                        response.message
                    );

                    return;

                }

                /*
                |--------------------------------------------------------------------------
                | Reset Form
                |--------------------------------------------------------------------------
                */

                $('#addOvertimeModal')
                    .modal('hide');

                form[0].reset();

                /*
                |--------------------------------------------------------------------------
                | Reload Table
                |--------------------------------------------------------------------------
                */

                reloadTable();

                showToast(
                    'success',
                    'Overtime added successfully.'
                );

            },

            'json'

        ).always(function () {

            submitBtn.prop(
                'disabled',
                false
            );

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Open Edit Modal
    |--------------------------------------------------------------------------
    */

    $(document).on(

        'click',

        '.edit-btn',

        function () {

            let data =
                table.row(
                    $(this).closest('tr')
                ).data();

            /*
            |--------------------------------------------------------------------------
            | Fill Modal Fields
            |--------------------------------------------------------------------------
            */

            $('#editId').val(
                data.id
            );

            $('#editDate').val(
                data.rawDate || data.date
            );

            $('#editStartTime').val(
                data.rawStartTime
            );

            $('#editEndTime').val(
                data.rawEndTime
            );

            $('#editReason').val(
                data.reason || ''
            );

            /*
            |--------------------------------------------------------------------------
            | Open Modal
            |--------------------------------------------------------------------------
            */

            $('#editOvertimeModal')
                .modal('show');

        }

    );

    /*
    |--------------------------------------------------------------------------
    | Update Overtime
    |--------------------------------------------------------------------------
    */

    $('#editOvertimeForm').submit(function (e) {

        e.preventDefault();

        let form = $(this);

        let submitBtn =
            form.find('button[type="submit"]');

        submitBtn.prop(
            'disabled',
            true
        );

        $.post(

            API_BASE + '/overtime/emp-updateOvertime.php',

            form.serialize(),

            function (response) {

                if (!response.success) {

                    showToast(
                        'danger',
                        response.message
                    );

                    return;

                }

                /*
                |--------------------------------------------------------------------------
                | Close Modal
                |--------------------------------------------------------------------------
                */

                $('#editOvertimeModal')
                    .modal('hide');

                /*
                |--------------------------------------------------------------------------
                | Reload Table
                |--------------------------------------------------------------------------
                */

                reloadTable();

                showToast(
                    'success',
                    'Overtime updated successfully.'
                );

            },

            'json'

        ).always(function () {

            submitBtn.prop(
                'disabled',
                false
            );

        });

    });

    /*
    |--------------------------------------------------------------------------
    | Delete Overtime
    |--------------------------------------------------------------------------
    */

    $(document).on(

        'click',

        '.delete-btn',

        function () {

            let id =
                $(this).data('id');

            Swal.fire({

                title: 'Delete Overtime?',

                text: 'This action cannot be undone.',

                icon: 'warning',

                showCancelButton: true,

                confirmButtonText: 'Delete',

                cancelButtonText: 'Cancel'

            }).then((result) => {

                if (!result.isConfirmed) {

                    return;

                }

                $.post(

                    API_BASE + '/overtime/emp-deleteOvertime.php',

                    {
                        id: id
                    },

                    function (response) {

                        if (!response.success) {

                            showToast(
                                'danger',
                                response.message
                            );

                            return;

                        }

                        /*
                        |--------------------------------------------------------------------------
                        | Reload Table
                        |--------------------------------------------------------------------------
                        */

                        reloadTable();

                        showToast(
                            'success',
                            'Overtime deleted successfully.'
                        );

                    },

                    'json'

                );

            });

        }

    );

});

</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
