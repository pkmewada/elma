<?php

error_reporting(E_ALL);

ini_set('display_errors', 1);

include __DIR__ . '/../includes/emp-auth.php';

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/basic-config.php';

include __DIR__ . '/../includes/employeeInfoEngine.php';

/*
|--------------------------------------------------------------------------
| Config
|--------------------------------------------------------------------------
*/

$config = getBasicConfig();

$deductionTypes =
    $config['deductionTypes'] ?? [];

/*
|--------------------------------------------------------------------------
| Employee Engine
|--------------------------------------------------------------------------
*/

$employeeEngine =
    new EmployeeInfoEngine($con);

$currentEmployee =
    $employeeEngine->getCurrentEmployee();

/*
|--------------------------------------------------------------------------
| Fetch Employee Deductions
|--------------------------------------------------------------------------
*/

$deductions =
    $employeeEngine->getEmployeeDeductions(
        $currentEmployee['id']
    );

?>
<?php include __DIR__ . '/../includes/emp-header.php'; ?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

<link rel="stylesheet" href="<?= ASSET_URL ?>/assets/libs/prismjs/themes/prism-coy.min.css">

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

<?php include __DIR__ . '/../includes/emp-sidebar.php'; ?>

<div class="main-content app-content">

    <div class="container-fluid">

        <!-- PAGE HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">

            <div>

                <h1 class="page-title fw-medium fs-18 mb-2">

                    My Deductions

                </h1>

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">

                        <a href="dashboard">

                            Dashboard

                        </a>

                    </li>

                    <li class="breadcrumb-item active">

                        My Deductions

                    </li>

                </ol>

            </div>

        </div>

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

                                <!-- DEDUCTION TYPE FILTER -->
                                <select id="deductionTypeFilter" class="form-select form-select-lg">

                                    <option value="">
                                        Deduction Type
                                    </option>

                                    <?php foreach ($deductionTypes as $type): ?>

                                    <option value="<?= htmlspecialchars($type); ?>">

                                        <?= htmlspecialchars($type); ?>

                                    </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>

                            <!-- AUTO SPACE -->
                            <div class="flex-fill"></div>

                            <!-- RIGHT -->
                            <div class="d-flex">

                                <input id="tableSearch" class="form-control form-control-sm"
                                    placeholder="Search deductions..." autocomplete="off">

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

                            Deduction Records

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table id="deduction-datatable" data-ui-table="mamix" class="table table-hover text-nowrap">

                                <thead>

                                    <tr>

                                        <th>SNo</th>

                                        <th>Deduction Type</th>

                                        <th>Amount</th>

                                        <th>Deduction Date</th>

                                        <th>Remark</th>

                                        <th>Added By</th>

                                        <th>Created At</th>

                                    </tr>

                                </thead>

                                <tbody>

                                    <?php if (!empty($deductions)): ?>

                                    <?php $sno = 1; ?>

                                    <?php foreach ($deductions as $row): ?>

                                    <tr>

                                        <!-- SNO -->
                                        <td>

                                            <?= $sno++ ?>

                                        </td>

                                        <!-- TYPE -->
                                        <td data-type="<?= htmlspecialchars($row['deductionType']) ?>">

                                            <?= htmlspecialchars($row['deductionType']) ?>

                                        </td>

                                        <!-- AMOUNT -->
                                        <td>

                                            <span class="deduction-amount">

                                                ₹<?= number_format((float)$row['amount'], 2) ?>

                                            </span>

                                        </td>

                                        <!-- DATE -->
                                        <td>

                                            <?= date(
                                                        'd M Y',
                                                        strtotime($row['deductionDate'])
                                                    ) ?>

                                        </td>

                                        <!-- REMARK -->
                                        <td>

                                            <?= htmlspecialchars(
                                                        $row['remark'] ?: '-'
                                                    ) ?>

                                        </td>

                                        <!-- CREATED BY -->
                                        <td>

                                            <?= htmlspecialchars(
                                                        $row['createdBy'] ?: '-'
                                                    ) ?>

                                        </td>

                                        <!-- CREATED -->
                                        <td>

                                            <?= date(
                                                        'd M Y h:i A',
                                                        strtotime($row['createdAt'])
                                                    ) ?>

                                        </td>

                                    </tr>

                                    <?php endforeach; ?>

                                    <?php else: ?>

                                    <tr>

                                        <td colspan="7" class="text-center text-muted py-5">

                                            No deductions found

                                        </td>

                                    </tr>

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
    | DataTable
    |--------------------------------------------------------------------------
    */

    const table =
        $('#deduction-datatable').DataTable(

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

                columnDefs: [{
                    targets: 0,
                    orderable: false,
                    searchable: false
                }],

                buttons: [

                    {
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',

                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
                        }
                    },

                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',

                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6]
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

    $.fn.dataTable.ext.search.push(

        function(settings, data, dataIndex) {

            const deductionTypeFilter =
                $('#deductionTypeFilter').val();

            const rowNode =
                $(table.row(dataIndex).node());

            if (deductionTypeFilter) {

                const rowType =
                    rowNode.find('td[data-type]').data('type');

                if (rowType !== deductionTypeFilter) {

                    return false;
                }
            }

            return true;
        }
    );

    $('#deductionTypeFilter').on('change', function() {

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

});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>