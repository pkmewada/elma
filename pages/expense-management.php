<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../includes/auth.php';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/basic-config.php';

/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/

$config = getBasicConfig();

$expenseTypes = $config['expenseTypes'] ?? [];

$updateError = '';

/*
|--------------------------------------------------------------------------
| Fetch Expense Records
|--------------------------------------------------------------------------
*/

$result = null;

$selectQuery = "
    SELECT 
        ee.id,
        ee.employeeId,
        eu.fullName AS employeeName,
        eu.employmentStatus,

        ee.expenseType,
        ee.amount,
        ee.invoiceNumber,
        ee.invoiceImage,
        ee.expenseDate,
        ee.remark,
        ee.expenseStatus,
        ee.approvedBy,
        ee.approvedAt,
        ee.rejectedBy,
        ee.rejectedAt,
        ee.createdBy,
        ee.createdAt,
        ee.paymentStatus

    FROM employeeExpenses ee
    LEFT JOIN employeeusers eu 
ON eu.id = ee.employeeId
WHERE eu.employmentStatus = 'Active'

    ORDER BY ee.id DESC
";
$selectStmt = mysqli_prepare($con, $selectQuery);

if ($selectStmt) {
    mysqli_stmt_execute($selectStmt);
    $result = mysqli_stmt_get_result($selectStmt);
} else {
    $updateError = 'Unable to load expenses right now.';
}

/*
|--------------------------------------------------------------------------
| Fetch Active Employees
|--------------------------------------------------------------------------
*/

$employees = [];

$employeeQuery = "SELECT id, fullName FROM employeeusers WHERE employmentStatus = 'Active' ORDER BY fullName ASC";
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

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<!-- Prism CSS -->
<link rel="stylesheet" href="<?= ASSET_URL ?>/assets/libs/prismjs/themes/prism-coy.min.css">
<style>

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

.expense-table-filters .form-select,
#employeeFilter,
#expenseTypeFilter {
    min-width: 180px;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 13px;
}

/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-responsive::-webkit-scrollbar {
    display: none;
}

#expense-datatable td,
#expense-datatable th {
    white-space: nowrap;
    vertical-align: middle;
}

#expense-datatable small {
    display: inline;
    margin-left: 6px;
}

/*
|--------------------------------------------------------------------------
| Amount Badge
|--------------------------------------------------------------------------
*/

.expense-amount {
    font-weight: 600;
    color: rgb(var(--primary-rgb));
}

/*
|--------------------------------------------------------------------------
| Action Buttons
|--------------------------------------------------------------------------
*/

.expense-action-btn {
    transition: all 0.2s ease-in-out;
}

.expense-action-btn:hover {
    transform: translateY(-1px);
}

/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    .expense-table-filters {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-left: 0;
        margin-top: 0.5rem;
        width: 100%;
    }

    .expense-table-filters .form-select {
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
                    Expense Management
                </h1>

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="dashboard">
                            Dashboard
                        </a>
                    </li>

                    <li class="breadcrumb-item active" aria-current="page">
                        Expense Management
                    </li>

                </ol>

            </div>

            <div>

                <button type="button"
                    class="btn btn-primary btn-wave waves-effect waves-light"
                    data-bs-toggle="modal"
                    data-bs-target="#addExpenseModal">

                    <i class="ri-add-line align-middle me-1"></i>

                    Add Expense

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

                        <!-- EXPENSE TYPE FILTER -->
                        <select id="expenseTypeFilter"
                            class="form-select form-select-lg">

                            <option value="">
                                Expense Type
                            </option>

                            <?php foreach ($expenseTypes as $type): ?>

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
                            placeholder="Search expenses..."
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
                            Expense Records DataTable
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="expense-datatable" data-ui-table="mamix"  class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Employee</th>
                                        <th>Expense Type</th>
                                        <th>Amount</th>
                                        <th>Invoice No.</th>
                                        <th>Invoice</th>
                                        <th>Expense Date</th>
                                        <th>Remark</th>
                                        <th>Status</th>
                                        <th>Payment Status</th>
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
                                        <td data-type="<?= htmlspecialchars($row['expenseType']); ?>">
                                            <?= htmlspecialchars($row['expenseType']); ?>
                                        </td>
                                       <!-- AMOUNT -->
                                        <td>
                                            <span class="expense-amount">
                                                ₹<?= number_format((float)$row['amount'], 2); ?>
                                            </span>
                                        </td>
                                        
                                        <!-- INVOICE NUMBER -->
                                        <td>
                                            <?= htmlspecialchars($row['invoiceNumber'] ?: '-'); ?>
                                        </td>
                                        
                                        <!-- INVOICE IMAGE -->
                                        <td>
                                            <?php if (!empty($row['invoiceImage'])): ?>
                                        
                                                        <a href="<?= BASE_URL ?>/uploads/expenses/<?= urlencode($row['invoiceImage']); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                                                    View Bill
                                                </a>
                                            <?php else: ?>
                                            <?php endif; ?>
                                        </td>
                                        <!-- DATE -->
                                        <td>
                                            <?= htmlspecialchars(
                                                date('d M Y', strtotime($row['expenseDate']))
                                            ); ?>
                                        </td>
                                        <!-- REMARK -->
                                        <td>
                                            <?= htmlspecialchars($row['remark'] ?: '-'); ?>
                                        </td>
                                        
                                        <!-- STATUS -->
                                        <td>

                                            <?php if ($row['expenseStatus'] === 'Approved'): ?>
                                        
                                                <span class="btn btn-outline-success btn-sm">
                                                    Approved
                                                </span>
                                        
                                            <?php elseif ($row['expenseStatus'] === 'Rejected'): ?>
                                        
                                                <span class="btn btn-outline-danger btn-sm">
                                                    Rejected
                                                </span>
                                        
                                            <?php else: ?>
                                        
                                                <span class="btn btn-outline-warning btn-sm">
                                                    Pending
                                                </span>
                                        
                                            <?php endif; ?>
                                        
                                        </td>
                                        
                                        <!-- PAYMENT STATUS -->
                                        <td>
                                        
                                            <?php if ($row['paymentStatus'] === 'paid'): ?>
                                        
                                                <span class="btn btn-outline-success btn-sm">
                                                    Paid
                                                </span>
                                        
                                            <?php elseif ($row['paymentStatus'] === 'unpaid'): ?>
                                        
                                                <span class="btn btn-outline-warning btn-sm">
                                                    Unpaid
                                                </span>
                                        
                                            <?php else: ?>
                                        
                                                <span class="btn btn-outline-secondary btn-sm">
                                                    -
                                                </span>
                                        
                                            <?php endif; ?>
                                        
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

                                            <?php if ($row['expenseStatus'] === 'Pending'): ?>
                                        
                                                <div class="d-flex flex-wrap gap-1">
                                        
                                                    <!-- APPROVE -->
                                                    <button type="button"
                                                        class="btn btn-sm btn-success approve-expense-btn"
                                                        data-id="<?= (int)$row['id']; ?>">
                                        
                                                        Approve
                                        
                                                    </button>
                                        
                                                    <!-- REJECT -->
                                                    <button type="button"
                                                        class="btn btn-sm btn-danger reject-expense-btn"
                                                        data-id="<?= (int)$row['id']; ?>">
                                        
                                                        Reject
                                        
                                                    </button>
                                        
                                                    <!-- EDIT -->
                                                    <button type="button"
                                                        class="btn btn-sm btn-info edit-expense-btn"
                                                        data-id="<?= (int)$row['id']; ?>">
                                        
                                                        Edit
                                        
                                                    </button>
                                        
                                                    <!-- DELETE -->
                                                    <button type="button"
                                                        class="btn btn-sm btn-outline-danger delete-expense-btn"
                                                        data-id="<?= (int)$row['id']; ?>">
                                        
                                                        Delete
                                        
                                                    </button>
                                        
                                                </div>
                                        
                                            <?php elseif ($row['expenseStatus'] === 'Approved'): ?>
                                        
                                                <div class="d-flex flex-column">
                                        
                                                   <span class="btn btn-outline-success btn-sm">
                                                        Approved
                                                    </span>
                                        
                                                    <?php if (!empty($row['approvedBy'])): ?>
                                        
                                                        <small class="text-muted mt-1">
                                        
                                                            By:
                                                            <?= htmlspecialchars($row['approvedBy']); ?>
                                        
                                                        </small>
                                        
                                                    <?php endif; ?>
                                        
                                                </div>
                                        
                                            <?php elseif ($row['expenseStatus'] === 'Rejected'): ?>
                                        
                                                <div class="d-flex flex-column">
                                        
                                                    <span class="btn btn-outline-danger btn-sm">
                                                        Rejected
                                                    </span>
                                        
                                                    <?php if (!empty($row['rejectedBy'])): ?>
                                        
                                                        <small class="text-muted mt-1">
                                        
                                                            By:
                                                            <?= htmlspecialchars($row['rejectedBy']); ?>
                                        
                                                        </small>
                                        
                                                    <?php endif; ?>
                                        
                                                </div>
                                        
                                            <?php endif; ?>
                                        
                                        
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


 <!-- ADD / EDIT EXPENSE MODAL -->
<div class="modal fade"
    id="addExpenseModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <!-- HEADER -->
            <div class="modal-header">

                <h5 class="modal-title"
                    id="addExpenseModalLabel">

                    Add Expense

                </h5>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Close">
                </button>

            </div>

            <!-- FORM -->
            <form id="addExpenseForm" novalidate>

                <!-- ID -->
                <input type="hidden"
                    id="expenseId"
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

                        <!-- EXPENSE TYPE -->
                        <div class="col-md-6">

                            <label class="form-label text-default">
                                Expense Type
                            </label>

                            <select class="form-select"
                                id="expenseType"
                                name="expenseType"
                                required>

                                <option value="">
                                    Select Expense Type
                                </option>

                                <?php foreach ($expenseTypes as $type): ?>

                                <option
                                    value="<?= htmlspecialchars($type); ?>">

                                    <?= htmlspecialchars($type); ?>

                                </option>

                                <?php endforeach; ?>

                            </select>

                            <div class="invalid-feedback">
                                Expense type is required.
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
                        
                        <!-- INVOICE NUMBER -->
                        <div class="col-md-6">
                            <label class="form-label text-default">
                                Invoice / Bill Number
                            </label>
                            <input type="text"
                                class="form-control"
                                id="invoiceNumber"
                                name="invoiceNumber"
                                placeholder="Enter Invoice Number">
                        </div>
                        <!-- INVOICE IMAGE -->
                        <div class="col-md-6">
                            <label class="form-label text-default">
                                Invoice / Bill Image
                            </label>
                        
                            <input type="file" class="form-control"  id="invoiceImage" name="invoiceImage" accept=".jpg,.jpeg,.png,.pdf">
                            <small class="text-muted">
                                JPG, PNG or PDF (Max 5MB)
                            </small>
                        </div>

                        <!-- DATE -->
                        <div class="col-md-6">
                            <label class="form-label text-default">
                                Expense Date
                            </label>
                            <input type="date" class="form-control" id="expenseDate"  name="expenseDate" value="<?= date('Y-m-d'); ?>" required>
                            <div class="invalid-feedback">
                                Expense date is required.
                            </div>
                        </div>

                        <!-- REMARK -->
                        <div class="col-12">
                            <label class="form-label text-default">
                                Remark
                            </label>
                            <textarea class="form-control"  id="remark" name="remark" rows="3" placeholder="Enter Remark"></textarea>
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
                        id="saveExpenseBtn">

                        <span class="spinner-border spinner-border-sm me-2 d-none"
                            id="saveExpenseSpinner"
                            role="status"
                            aria-hidden="true">
                        </span>

                        <span id="saveExpenseText">

                            Save Expense

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

                    Delete Expense

                </h6>

                <button aria-label="Close"
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body text-start">

                <h6>
                    Are you sure you want to delete this expense?
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {

/*
|--------------------------------------------------------------------------
| API URLs
|--------------------------------------------------------------------------
*/

const addExpenseApiUrl = API_BASE + '/expense/addExpense.php';

const updateExpenseApiUrl = API_BASE + '/expense/updateExpense.php';

const deleteExpenseApiUrl = API_BASE + '/expense/deleteExpense.php';

const getExpenseApiUrl = API_BASE + '/expense/getExpense.php';

const approveExpenseApiUrl = API_BASE + '/expense/approveExpense.php';

const rejectExpenseApiUrl = API_BASE + '/expense/rejectExpense.php';

/*
|--------------------------------------------------------------------------
| DataTable
|--------------------------------------------------------------------------
*/

const table = $('#expense-datatable').DataTable(
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
                targets: 11,
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
    })
);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

    const employeeFilter = $('#employeeFilter').val();

    const expenseTypeFilter = $('#expenseTypeFilter').val();

    const rowNode = $(table.row(dataIndex).node());

    if (employeeFilter) {

        const rowEmployee = rowNode.find('td[data-employee]').data('employee');

        if (rowEmployee !== employeeFilter) {
            return false;
        }
    }

    if (expenseTypeFilter) {

        const rowType = rowNode.find('td[data-type]').data('type');

        if (rowType !== expenseTypeFilter) {
            return false;
        }
    }

    return true;
});

$('#employeeFilter, #expenseTypeFilter').on('change', function() {

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

function resetExpenseForm() {

    $('#addExpenseForm')[0].reset();

    $('#expenseId').val('');

    $('#expenseDate').val('<?= date('Y-m-d'); ?>');

    $('#addExpenseModalLabel').text('Add Expense');

    $('#saveExpenseText').text('Save Expense');

}

/*
|--------------------------------------------------------------------------
| Open Add Modal
|--------------------------------------------------------------------------
*/

$('[data-bs-target="#addExpenseModal"]').on('click', function() {

    resetExpenseForm();

});

/*
|--------------------------------------------------------------------------
| Edit Expense
|--------------------------------------------------------------------------
*/

$(document).on('click', '.edit-expense-btn', function() {

    const id = $(this).data('id');
    $.ajax({
        url: getExpenseApiUrl,
        type: 'GET',
        data: {
            id: id
        },
        dataType: 'json',
        success: function(response) {
            if (!response.success) {

                showToast('danger', response.message ||
                    'Unable to load expense.');
                return;
            }
            const data = response.data;
                $('#expenseId').val(data.id);
                $('#employeeId').val(data.employeeId);
                $('#expenseType').val(data.expenseType);
                $('#amount').val(data.amount);
                $('#invoiceNumber').val(data.invoiceNumber);
                $('#expenseDate').val(data.expenseDate);
                $('#remark').val(data.remark);
                $('#addExpenseModalLabel').text('Edit Expense');
                $('#saveExpenseText').text('Update Expense');
                $('#addExpenseModal').modal('show');
            },
        error: function() {
            showToast('danger', 'Unable to fetch expense details.');
        }
    });
});

/*
|--------------------------------------------------------------------------
| Add / Update Expense
|--------------------------------------------------------------------------
*/

$('#addExpenseForm').on('submit', function(e) {

    e.preventDefault();

    const form = this;

    const isEdit = $('#expenseId').val() !== '';

    const apiUrl = isEdit
        ? updateExpenseApiUrl
        : addExpenseApiUrl;

    let isValid = true;

    $(form).find('.is-invalid').removeClass('is-invalid');

    if (!$('#employeeId').val()) {

        $('#employeeId').addClass('is-invalid');

        isValid = false;
    }

    if (!$('#expenseType').val()) {

        $('#expenseType').addClass('is-invalid');

        isValid = false;
    }

    if (!$('#amount').val()) {

        $('#amount').addClass('is-invalid');

        isValid = false;
    }

    if (!$('#expenseDate').val()) {

        $('#expenseDate').addClass('is-invalid');

        isValid = false;
    }

    if (!isValid) {

        showToast('danger',
            'Please fill all required fields.');

        return;
    }

    const formData = new FormData(form);

    const submitBtn = $('#saveExpenseBtn');

    const submitSpinner = $('#saveExpenseSpinner');

    const submitText = $('#saveExpenseText');

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
                ? 'Update Expense'
                : 'Save Expense'
            );

        }

    });

});



/*
|--------------------------------------------------------------------------
| Approve Expense
|--------------------------------------------------------------------------
*/

$(document).on(
    'click',
    '.approve-expense-btn',
    function () {

        const id = $(this).data('id');

        Swal.fire({

            title: 'Approve Expense?',

            text: 'This expense will be approved.',

            icon: 'question',

            showCancelButton: true,

            confirmButtonText: 'Approve',

            confirmButtonColor: '#198754'

        }).then(function(result) {

            if (!result.isConfirmed) {
                return;
            }

            $.ajax({

                url: approveExpenseApiUrl,

                type: 'POST',

                data: {
                    id: id
                },

                dataType: 'json',

                success: function(response) {

                    if (response.success) {

                        showToast(
                            'success',
                            response.message
                        );

                        setTimeout(function() {

                            location.reload();

                        }, 500);

                    } else {

                        showToast(
                            'danger',
                            response.message
                        );
                    }
                },

                error: function() {

                    showToast(
                        'danger',
                        'Unable to approve expense.'
                    );
                }

            });

        });

    }
);

/*
|--------------------------------------------------------------------------
| Reject Expense
|--------------------------------------------------------------------------
*/

$(document).on(
    'click',
    '.reject-expense-btn',
    function () {

        const id = $(this).data('id');

        Swal.fire({

            title: 'Reject Expense?',

            text: 'This expense will be rejected.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText: 'Reject',

            confirmButtonColor: '#dc3545'

        }).then(function(result) {

            if (!result.isConfirmed) {
                return;
            }

            $.ajax({

                url: rejectExpenseApiUrl,

                type: 'POST',

                data: {
                    id: id
                },

                dataType: 'json',

                success: function(response) {

                    if (response.success) {

                        showToast(
                            'success',
                            response.message
                        );

                        setTimeout(function() {

                            location.reload();

                        }, 500);

                    } else {

                        showToast(
                            'danger',
                            response.message
                        );
                    }
                },

                error: function() {

                    showToast(
                        'danger',
                        'Unable to reject expense.'
                    );
                }

            });

        });

    }
);

/*
|--------------------------------------------------------------------------
| Delete Expense
|--------------------------------------------------------------------------
*/

let deleteExpenseId = 0;

$(document).on('click', '.delete-expense-btn', function() {

    deleteExpenseId = $(this).data('id');

    $('#deleteConfirmModal').modal('show');

});

$('#confirmDeleteBtn').on('click', function() {

    $.ajax({

        url: deleteExpenseApiUrl,

        type: 'POST',

        data: {
            id: deleteExpenseId
        },

        dataType: 'json',

        success: function(response) {

            if (response.success) {

                $('#deleteConfirmModal').modal('hide');

                const row = $(
                    '.delete-expense-btn[data-id="' +
                    deleteExpenseId +
                    '"]'
                ).closest('tr');

                table.row(row).remove().draw(false);

                showToast('success',
                    response.message);

            } else {

                showToast('danger',
                    response.message ||
                    'Failed to delete expense.');
            }

        },

        error: function() {

            showToast('danger',
                'Unable to delete expense.');

        }

    });

});

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
