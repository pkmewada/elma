<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/basic-config.php';

$config = getBasicConfig();

$deductionTypes = $config['deductionTypes'] ?? [];

$updateError = '';

/*
|--------------------------------------------------------------------------
| Fetch Deduction Records
|--------------------------------------------------------------------------
*/

$result = null;

$selectQuery = "
    SELECT
        id,
        employeeId,
        employeeName,
        deductionType,
        amount,
        deductionDate,
        remark,
        createdBy,
        createdAt
    FROM employeeDeductions
    ORDER BY id DESC
";

$selectStmt = mysqli_prepare($con, $selectQuery);

if ($selectStmt) {

    mysqli_stmt_execute($selectStmt);

    $result = mysqli_stmt_get_result($selectStmt);

} else {

    $updateError = 'Unable to load deductions right now.';
}

/*
|--------------------------------------------------------------------------
| Fetch Active Employees
|--------------------------------------------------------------------------
*/

$employees = [];

$employeeQuery = "
    SELECT
        id,
        fullName
    FROM employeeusers
    WHERE employmentStatus = 'Active'
    ORDER BY fullName ASC
";

$employeeStmt = mysqli_prepare($con, $employeeQuery);

if ($employeeStmt) {

    mysqli_stmt_execute($employeeStmt);

    $employeeResult = mysqli_stmt_get_result($employeeStmt);

    if ($employeeResult) {

        while ($row = mysqli_fetch_assoc($employeeResult)) {

            $employees[] = $row;
        }
    }

    mysqli_stmt_close($employeeStmt);

} else {

    $updateError = 'Unable to load employees right now.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>

<link rel="stylesheet"
    href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet"
    href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

<!-- Prism CSS -->
<link rel="stylesheet"
      href="<?= ASSET_URL ?>/assets/libs/prismjs/themes/prism-coy.min.css">

<style>

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

.deduction-table-filters .form-select,
#employeeFilter,
#deductionTypeFilter {
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

    .modal-lg {
        margin: 0.5rem;
        max-width: calc(100vw - 1rem);
    }
}
/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-responsive::-webkit-scrollbar {
    display: none;
}

#deduction-datatable td,
#deduction-datatable th {
    white-space: nowrap;
    vertical-align: middle;
}

#deduction-datatable small {
    display: inline;
    margin-left: 6px;
}

/*
|--------------------------------------------------------------------------
| Amount Badge
|--------------------------------------------------------------------------
*/

.deduction-amount {
    font-weight: 600;
    color: rgb(var(--danger-rgb));
}

/*
|--------------------------------------------------------------------------
| Action Buttons
|--------------------------------------------------------------------------
*/

.deduction-action-btn {
    transition: all 0.2s ease-in-out;
}

.deduction-action-btn:hover {
    transform: translateY(-1px);
}

/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    .deduction-table-filters {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-left: 0;
        margin-top: 0.5rem;
        width: 100%;
    }

    .deduction-table-filters .form-select {
        min-width: auto;
        width: 100%;
    }

    .page-header-breadcrumb {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem !important;
    }

    .modal-lg {
        margin: 0.5rem;
        max-width: calc(100vw - 1rem);
    }
}

</style>

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">

    <div class="container-fluid">

        <!-- PAGE HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">

            <div>

                <h1 class="page-title fw-medium fs-18 mb-2">
                    Deduction Management
                </h1>

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="dashboard">
                            Dashboard
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Deduction Management
                    </li>

                </ol>

            </div>

            <div>

                <button type="button"
                    class="btn btn-primary btn-wave waves-effect waves-light"
                    data-bs-toggle="modal"
                    data-bs-target="#addDeductionModal">

                    <i class="ri-add-line align-middle me-1"></i>

                    Add Deduction

                </button>

            </div>

        </div>

        <!-- ERROR -->
        <?php if ($updateError !== ''): ?>

        <div class="alert alert-danger" role="alert">

            <?= htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8'); ?>

        </div>

        <?php endif; ?>

        <!-- FILTERS -->
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
                                <select id="employeeFilter"
                                    class="form-select form-select-lg">
        
                                    <option value="">
                                        Employee
                                    </option>
        
                                    <?php foreach ($employees as $employee): ?>
        
                                    <option
                                        value="<?= htmlspecialchars($employee['fullName']); ?>">
        
                                        <?= htmlspecialchars($employee['fullName']); ?>
        
                                    </option>
        
                                    <?php endforeach; ?>
        
                                </select>
        
                                <!-- DEDUCTION TYPE FILTER -->
                                <select id="deductionTypeFilter"
                                    class="form-select form-select-lg">
        
                                    <option value="">
                                        Deduction Type
                                    </option>
        
                                    <?php foreach ($deductionTypes as $type): ?>
        
                                    <option
                                        value="<?= htmlspecialchars($type); ?>">
        
                                        <?= htmlspecialchars($type); ?>
        
                                    </option>
        
                                    <?php endforeach; ?>
        
                                </select>
        
                            </div>
        
                            <!-- AUTO SPACE -->
                            <div class="flex-fill"></div>
        
                            <!-- RIGHT -->
                            <div class="d-flex">
        
                                <input id="tableSearch"
                                    class="form-control form-control-sm"
                                    placeholder="Search deductions..."
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

                            Deduction Records DataTable

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table id="deduction-datatable"
                                data-ui-table="mamix"
                                class="table table-hover text-nowrap">

                                <thead>

                                    <tr>

                                        <th>SNo</th>

                                        <th>Employee</th>

                                        <th>Deduction Type</th>

                                        <th>Amount</th>

                                        <th>Deduction Date</th>

                                        <th>Remark</th>

                                        <th>Added By</th>

                                        <th>Created At</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if ($result && mysqli_num_rows($result) > 0): ?>

                                    <?php $sno = 1; ?>

                                    <?php while ($row = mysqli_fetch_assoc($result)): ?>

                                    <tr>

                                        <!-- SNO -->
                                        <td>

                                            <?= $sno++; ?>

                                        </td>

                                        <!-- EMPLOYEE -->
                                        <td data-employee="<?= htmlspecialchars($row['employeeName']); ?>">

                                            <?= htmlspecialchars($row['employeeName']); ?>

                                        </td>

                                        <!-- TYPE -->
                                        <td data-type="<?= htmlspecialchars($row['deductionType']); ?>">

                                            <?= htmlspecialchars($row['deductionType']); ?>

                                        </td>

                                        <!-- AMOUNT -->
                                        <td>

                                            <span class="deduction-amount">

                                                ₹<?= number_format((float)$row['amount'], 2); ?>

                                            </span>

                                        </td>

                                        <!-- DATE -->
                                        <td>

                                            <?= htmlspecialchars(
                                                date('d M Y', strtotime($row['deductionDate']))
                                            ); ?>

                                        </td>

                                        <!-- REMARK -->
                                        <td>

                                            <?= htmlspecialchars($row['remark'] ?: '-'); ?>

                                        </td>

                                        <!-- USER -->
                                        <td>

                                            <?= htmlspecialchars($row['createdBy'] ?: '-'); ?>

                                        </td>

                                        <!-- CREATED -->
                                        <td>

                                            <?= htmlspecialchars(
                                                date('d M Y h:i A', strtotime($row['createdAt']))
                                            ); ?>

                                        </td>

                                        <!-- ACTIONS -->
                                       <td>
                                            <div class="d-flex flex-wrap gap-1">
                                        
                                                <!-- EDIT -->
                                                <a href="javascript:void(0);"
                                                    class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light deduction-action-btn edit-deduction-btn"
                                                    data-id="<?= (int)$row['id']; ?>"
                                                    title="Edit">
                                        
                                                    <i class="ri-edit-line"></i>
                                        
                                                </a>
                                        
                                                <!-- DELETE -->
                                                <a href="javascript:void(0);"
                                                    class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light deduction-action-btn delete-deduction-btn"
                                                    data-id="<?= (int)$row['id']; ?>"
                                                    title="Delete">
                                        
                                                    <i class="ri-delete-bin-line"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>

                                    <?php endwhile; ?>

                                    <?php endif; ?>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


        <!-- ADD / EDIT DEDUCTION MODAL -->
<div class="modal fade"
    id="addDeductionModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">

                <h5 class="modal-title"
                    id="addDeductionModalLabel">

                    Add Deduction

                </h5>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
                </button>

            </div>

            <!-- FORM -->
            <form id="addDeductionForm" novalidate>

                <!-- ID -->
                <input type="hidden"
                    id="deductionId"
                    name="id"
                    value="">

                <div class="modal-body"
                    style="max-height: 70vh; overflow-y: auto;">

                    <div class="row g-3">

                        <!-- EMPLOYEE -->
                        <div class="col-md-6">

                            <label class="form-label text-default">
                                Employee
                            </label>

                            <select class="form-select"
                                id="employeeId"
                                name="employeeId"
                                required>

                                <option value="">
                                    Select Employee
                                </option>

                                <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?= (int)$employee['id']; ?>"
                                    data-name="<?= htmlspecialchars($employee['fullName']); ?>">

                                    <?= htmlspecialchars($employee['fullName']); ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="invalid-feedback">
                                Employee is required.
                            </div>

                        </div>

                        <!-- DEDUCTION TYPE -->
                        <div class="col-md-6">

                            <label class="form-label text-default">
                                Deduction Type
                            </label>

                            <select class="form-select"
                                id="deductionType"
                                name="deductionType"
                                required>

                                <option value="">
                                    Select Deduction Type
                                </option>

                                <?php foreach ($deductionTypes as $type): ?>

                                <option
                                    value="<?= htmlspecialchars($type); ?>">

                                    <?= htmlspecialchars($type); ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="invalid-feedback">
                                Deduction type is required.
                            </div>

                        </div>

                        <!-- AMOUNT -->
                        <div class="col-md-6">

                            <label class="form-label text-default">
                                Amount
                            </label>

                            <input type="number"
                                step="0.01"
                                class="form-control"
                                id="amount"
                                name="amount"
                                placeholder="Enter Amount"
                                required>

                            <div class="invalid-feedback">
                                Amount is required.
                            </div>

                        </div>

                        <!-- DATE -->
                        <div class="col-md-6">

                            <label class="form-label text-default">
                                Deduction Date
                            </label>

                            <input type="date"
                                class="form-control"
                                id="deductionDate"
                                name="deductionDate"
                                value="<?= date('Y-m-d'); ?>"
                                required>

                            <div class="invalid-feedback">
                                Deduction date is required.
                            </div>

                        </div>

                        <!-- REMARK -->
                        <div class="col-12">

                            <label class="form-label text-default">
                                Remark
                            </label>

                            <textarea class="form-control"
                                id="remark"
                                name="remark"
                                rows="3"
                                placeholder="Enter Remark"></textarea>

                        </div>

                    </div>

                </div>

                <!-- FOOTER -->
                <div class="modal-footer">

                    <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button type="submit"
                        class="btn btn-primary"
                        id="saveDeductionBtn">

                        <span class="spinner-border spinner-border-sm me-2 d-none"
                            id="saveDeductionSpinner"
                            role="status"
                            aria-hidden="true">
                        </span>

                        <span id="saveDeductionText">

                            Save Deduction

                        </span>

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- DELETE CONFIRM MODAL -->
<div class="modal fade"
    id="deleteConfirmModal"
    data-bs-effect="effect-super-scaled">

    <div class="modal-dialog modal-dialog-centered text-center"
        role="document">

        <div class="modal-content modal-content-demo">

            <div class="modal-header">

                <h6 class="modal-title">

                    Delete Deduction

                </h6>

                <button aria-label="Close"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body text-start">

                <h6>
                    Are you sure you want to delete this deduction?
                </h6>

                <p class="text-muted mb-0">
                    This action cannot be undone.
                </p>

            </div>

            <div class="modal-footer">

                <button class="btn btn-danger"
                    id="confirmDeleteBtn">

                    Delete

                </button>

                <button class="btn btn-light"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

            </div>

        </div>

    </div>

</div>

                    <?php if ($selectStmt) {
    mysqli_stmt_close($selectStmt);
} ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script>
$(function() {

/*
|--------------------------------------------------------------------------
| API URLs
|--------------------------------------------------------------------------
*/

const addDeductionApiUrl = API_BASE + '/payroll/addDeduction.php';

const updateDeductionApiUrl = API_BASE + '/payroll/updateDeduction.php';

const deleteDeductionApiUrl = API_BASE + '/payroll/deleteDeduction.php';

const getDeductionApiUrl = API_BASE + '/payroll/getDeduction.php';

/*
|--------------------------------------------------------------------------
| DataTable
|--------------------------------------------------------------------------
*/

const table = $('#deduction-datatable').DataTable(
    window.ModlusUI.withDataTableDefaults({

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
                targets: 8,
                orderable: false,
                searchable: false
            }
        ],

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
            }
        ]
    })
);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

    const employeeFilter = $('#employeeFilter').val();

    const deductionTypeFilter = $('#deductionTypeFilter').val();

    const rowNode = $(table.row(dataIndex).node());

    if (employeeFilter) {

        const rowEmployee = rowNode.find('td[data-employee]').data('employee');

        if (rowEmployee !== employeeFilter) {
            return false;
        }
    }

    if (deductionTypeFilter) {

        const rowType = rowNode.find('td[data-type]').data('type');

        if (rowType !== deductionTypeFilter) {
            return false;
        }
    }

    return true;
});

$('#employeeFilter, #deductionTypeFilter').on('change', function() {

    table.draw();

});

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$('#tableSearch').on('keyup', function() {

    table.search(this.value).draw();

});

/*
|--------------------------------------------------------------------------
| Export
|--------------------------------------------------------------------------
*/

$('.export-btn').on('click', function() {

    const type = $(this).data('type');

    if (type === 'csv') {

        table.buttons('.buttons-csv').trigger();

    } else if (type === 'pdf') {

        table.buttons('.buttons-pdf').trigger();
    }

});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function escapeHtml(value) {

    return $('<div>').text(value || '').html();

}

function resetDeductionForm() {

    $('#addDeductionForm')[0].reset();

    $('#deductionId').val('');

    $('#deductionDate').val('<?= date('Y-m-d'); ?>');

    $('#addDeductionModalLabel').text('Add Deduction');

    $('#saveDeductionText').text('Save Deduction');

}

/*
|--------------------------------------------------------------------------
| Open Add Modal
|--------------------------------------------------------------------------
*/

$('[data-bs-target="#addDeductionModal"]').on('click', function() {

    resetDeductionForm();

});

/*
|--------------------------------------------------------------------------
| Edit Deduction
|--------------------------------------------------------------------------
*/

$(document).on('click', '.edit-deduction-btn', function() {

    const id = $(this).data('id');

    $.ajax({

        url: getDeductionApiUrl,

        type: 'GET',

        data: {
            id: id
        },

        dataType: 'json',

        success: function(response) {

            if (!response.success) {

                showToast('danger', response.message ||
                    'Unable to load deduction.');

                return;
            }

            const data = response.data;

            $('#deductionId').val(data.id);

            $('#employeeId').val(data.employeeId);

            $('#deductionType').val(data.deductionType);

            $('#amount').val(data.amount);

            $('#deductionDate').val(data.deductionDate);

            $('#remark').val(data.remark);

            $('#addDeductionModalLabel').text('Edit Deduction');

            $('#saveDeductionText').text('Update Deduction');

            $('#addDeductionModal').modal('show');

        },

        error: function() {

            showToast('danger', 'Unable to fetch deduction details.');

        }

    });

});

/*
|--------------------------------------------------------------------------
| Add / Update Deduction
|--------------------------------------------------------------------------
*/

$('#addDeductionForm').on('submit', function(e) {

    e.preventDefault();

    const form = this;

    const isEdit = $('#deductionId').val() !== '';

    const apiUrl = isEdit
        ? updateDeductionApiUrl
        : addDeductionApiUrl;

    let isValid = true;

    $(form).find('.is-invalid').removeClass('is-invalid');

    if (!$('#employeeId').val()) {

        $('#employeeId').addClass('is-invalid');

        isValid = false;
    }

    if (!$('#deductionType').val()) {

        $('#deductionType').addClass('is-invalid');

        isValid = false;
    }

    if (!$('#amount').val()) {

        $('#amount').addClass('is-invalid');

        isValid = false;
    }

    if (!$('#deductionDate').val()) {

        $('#deductionDate').addClass('is-invalid');

        isValid = false;
    }

    if (!isValid) {

        showToast('danger',
            'Please fill all required fields.');

        return;
    }

    const formData = new FormData(form);

    const submitBtn = $('#saveDeductionBtn');

    const submitSpinner = $('#saveDeductionSpinner');

    const submitText = $('#saveDeductionText');

    submitBtn.prop('disabled', true);

    submitSpinner.removeClass('d-none');

    submitText.text(isEdit ? 'Updating...' : 'Saving...');

    $.ajax({

        url: apiUrl,

        type: 'POST',

        data: formData,

        processData: false,

        contentType: false,

        dataType: 'json',

        success: function(response) {

            if (response.success) {

                showToast('success', response.message);

                setTimeout(function() {

                    location.reload();

                }, 500);

            } else {

                showToast('danger',
                    response.message ||
                    'Something went wrong.');
            }

        },

        error: function(xhr) {

            let msg = 'Server error.';

            if (
                xhr.responseJSON &&
                xhr.responseJSON.message
            ) {
                msg = xhr.responseJSON.message;
            }

            showToast('danger', msg);

        },

        complete: function() {

            submitBtn.prop('disabled', false);

            submitSpinner.addClass('d-none');

            submitText.text(
                isEdit
                ? 'Update Deduction'
                : 'Save Deduction'
            );

        }

    });

});

/*
|--------------------------------------------------------------------------
| Delete Deduction
|--------------------------------------------------------------------------
*/

let deleteDeductionId = 0;

$(document).on('click', '.delete-deduction-btn', function() {

    deleteDeductionId = $(this).data('id');

    $('#deleteConfirmModal').modal('show');

});

$('#confirmDeleteBtn').on('click', function() {

    $.ajax({

        url: deleteDeductionApiUrl,

        type: 'POST',

        data: {
            id: deleteDeductionId
        },

        dataType: 'json',

        success: function(response) {

            if (response.success) {

                $('#deleteConfirmModal').modal('hide');

                const row = $(
                    '.delete-deduction-btn[data-id="' +
                    deleteDeductionId +
                    '"]'
                ).closest('tr');

                table.row(row).remove().draw(false);

                showToast('success',
                    response.message);

            } else {

                showToast('danger',
                    response.message ||
                    'Failed to delete deduction.');
            }

        },

        error: function() {

            showToast('danger',
                'Unable to delete deduction.');

        }

    });

});

});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
