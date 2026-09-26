<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Setup</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>

                    <li class="breadcrumb-item active">
                        Employee Point Setup
                    </li>
                </ol>
            </div>
        </div>

        <div class="page-content pb-4">
            <div class="container-fluid px-0">

                <div class="row g-4">

                    <!-- POINT CATEGORIES -->

                    <div class="col-12">

                        <div class="card custom-card">

                            <div class="card-header d-flex justify-content-between align-items-center">

                                <h5 class="mb-0">
                                    Point Categories
                                </h5>

                                <button type="button"
                                    class="btn btn-primary"
                                    id="addPointCategoryBtn">

                                    <i class="ri-add-line"></i>
                                    Add Point Category

                                </button>

                            </div>

                            <div class="card-body">

                                <div id="setupWarning"
                                    class="alert alert-warning d-none mb-3">

                                    Changing point setup may affect
                                    employee performance calculations.

                                </div>

                                <div class="table-responsive">

                                    <table class="table table-bordered text-nowrap w-100 align-middle mb-0"
                                        id="pointCategoriesTable">

                                        <thead>

                                            <tr>
                                                <th>Category</th>
                                                <th>Code</th>
                                                <th>Type</th>
                                                <th>Points</th>
                                                <th>Severity</th>
                                                <th>Warning</th>
                                                <th>Payroll Impact</th>
                                                <th>Actions</th>
                                            </tr>

                                        </thead>

                                        <tbody></tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- POINT RULES -->

                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Point Rules
                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="mb-3">

                                    <label class="form-label">
                                        Monthly Point Allocation
                                    </label>

                                    <input type="number"
                                        class="form-control"
                                        id="monthlyAllocation"
                                        min="0"
                                        value="100">

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Warning Threshold
                                    </label>

                                    <input type="number"
                                        class="form-control"
                                        id="warningThreshold"
                                        min="0"
                                        value="75">

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Payroll Threshold
                                    </label>

                                    <input type="number"
                                        class="form-control"
                                        id="payrollThreshold"
                                        min="0"
                                        value="50">

                                </div>

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="enableWarningMail">

                                    <label class="form-check-label">
                                        Enable Warning Mail
                                    </label>

                                </div>

                                <div class="form-check form-switch">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="enablePayrollImpact">

                                    <label class="form-check-label">
                                        Enable Payroll Impact
                                    </label>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- RESET RULES -->

                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Reset Rules
                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="autoResetMonthly">

                                    <label class="form-check-label">
                                        Auto Reset Monthly
                                    </label>

                                </div>

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="carryForward">

                                    <label class="form-check-label">
                                        Carry Forward Remaining Points
                                    </label>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Carry Forward Limit
                                    </label>

                                    <input type="number"
                                        class="form-control"
                                        id="carryForwardLimit"
                                        min="0"
                                        value="0">

                                </div>

                                <div>

                                    <label class="form-label">
                                        Approval Workflow
                                    </label>

                                    <select class="form-select"
                                        id="approvalWorkflow">

                                        <option value="hr">
                                            HR Only
                                        </option>

                                        <option value="manager">
                                            Reporting Manager
                                        </option>

                                        <option value="both">
                                            HR + Reporting Manager
                                        </option>

                                        <option value="auto">
                                            Auto Approved
                                        </option>

                                    </select>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- SETUP COMPLETION -->

                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Setup Completion
                                </h5>

                            </div>

                            <div class="card-body">

                                <ul class="mb-4"
                                    id="setupProgressList">

                                    <li id="progressCategories">
                                        Point categories added
                                    </li>

                                    <li id="progressRules">
                                        Point rules configured
                                    </li>

                                    <li id="progressWorkflow">
                                        Approval workflow configured
                                    </li>

                                </ul>

                                <button type="button"
                                    class="btn btn-primary"
                                    id="savePointSetupBtn">

                                    Save & Activate Point Module

                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </div>
        </div>

    </div>
</div>

<!-- POINT CATEGORY MODAL -->

<div class="modal fade"
    id="pointCategoryModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title"
                    id="pointCategoryModalTitle">

                    Add Point Category

                </h5>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"></button>

            </div>

            <form id="pointCategoryForm">

                <div class="modal-body">

                    <input type="hidden"
                        id="pointCategoryId"
                        value="">

                    <div class="row g-3">

                        <!-- BASIC -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Category Name
                            </label>

                            <input type="text"
                                class="form-control"
                                id="categoryName"
                                placeholder="Enter category name"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Category Code
                            </label>

                            <input type="text"
                                class="form-control"
                                id="categoryCode"
                                maxlength="10"
                                placeholder="Enter category code"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Transaction Type
                            </label>

                            <select class="form-select"
                                id="transactionType">

                                <option value="Credit">
                                    Credit
                                </option>

                                <option value="Debit">
                                    Debit
                                </option>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Default Points
                            </label>

                            <input type="number"
                                class="form-control"
                                id="defaultPoints"
                                min="0"
                                value="0">

                        </div>

                        <!-- ADVANCED -->

                        <div class="col-md-6">

                            <label class="form-label">
                                Severity Level
                            </label>

                            <select class="form-select"
                                id="severityLevel">

                                <option value="Low">
                                    Low
                                </option>

                                <option value="Medium">
                                    Medium
                                </option>

                                <option value="High">
                                    High
                                </option>

                                <option value="Critical">
                                    Critical
                                </option>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <div class="form-check form-switch mt-4">

                                <input class="form-check-input"
                                    type="checkbox"
                                    id="autoWarning">

                                <label class="form-check-label">
                                    Auto Warning
                                </label>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-check form-switch">

                                <input class="form-check-input"
                                    type="checkbox"
                                    id="payrollImpact">

                                <label class="form-check-label">
                                    Payroll Impact
                                </label>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button type="submit"
                        class="btn btn-primary">

                        Save

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>

var pointCategoriesState = [];

$(function () {

/*
|--------------------------------------------------------------------------
| Load Existing Setup
|--------------------------------------------------------------------------
*/

function loadPointSetup() {

    $.ajax({

        url: API_BASE + '/points/getPointSetup.php',

        type: 'GET',

        dataType: 'json',

        success: function (response) {

            if (!response.success) {
                return;
            }

            const settings =
                response.data.settings || {};

            const categories =
                response.data.categories || [];

            /*
            |--------------------------------------------------------------------------
            | Fill Settings
            |--------------------------------------------------------------------------
            */

            $('#monthlyAllocation').val(
                settings.monthlyAllocation || 100
            );

            $('#warningThreshold').val(
                settings.warningThreshold || 75
            );

            $('#payrollThreshold').val(
                settings.payrollThreshold || 100
            );

            $('#enableWarningMail').prop(
                'checked',
                parseInt(
                    settings.enableWarningMail || 0
                ) === 1
            );

            $('#enablePayrollImpact').prop(
                'checked',
                parseInt(
                    settings.enablePayrollImpact || 0
                ) === 1
            );

            $('#autoResetMonthly').prop(
                'checked',
                parseInt(
                    settings.autoResetMonthly || 0
                ) === 1
            );

            $('#carryForward').prop(
                'checked',
                parseInt(
                    settings.carryForward || 0
                ) === 1
            );

            $('#carryForwardLimit').val(
                settings.carryForwardLimit || 0
            );

            $('#approvalWorkflow').val(
                settings.approvalWorkflow || ''
            );

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            pointCategoriesState = categories;

            renderTable();
        },

        error: function () {

            showToast(
                'danger',
                'Unable to load point setup.'
            );
        }
    });
}

/*
|--------------------------------------------------------------------------
| Reset Modal Form
|--------------------------------------------------------------------------
*/

function resetPointCategoryForm() {

    $('#pointCategoryId').val('');

    $('#categoryName').val('');

    $('#categoryCode').val('');

    $('#transactionType').val('Credit');

    $('#defaultPoints').val(0);

    $('#severityLevel').val('Low');

    $('#autoWarning').prop('checked', false);

    $('#payrollImpact').prop('checked', false);

    $('#pointCategoryModalTitle').text(
        'Add Point Category'
    );
}

/*
|--------------------------------------------------------------------------
| Badge Helper
|--------------------------------------------------------------------------
*/

function badge(flag) {

    return flag
        ? '<span class="badge bg-success-transparent">Yes</span>'
        : '<span class="badge bg-danger-transparent">No</span>';
}

/*
|--------------------------------------------------------------------------
| Render Table Row
|--------------------------------------------------------------------------
*/

function renderRow(item, index) {

    return `
        <tr data-index="${index}">

            <td>
                ${item.categoryName || ''}
            </td>

            <td>
                ${item.categoryCode || ''}
            </td>

            <td>
                ${item.transactionType || ''}
            </td>

            <td>
                ${item.defaultPoints || 0}
            </td>

            <td>
                ${item.severityLevel || ''}
            </td>

            <td>
                ${badge(
                    parseInt(item.autoWarning || 0) === 1
                )}
            </td>

            <td>
                ${badge(
                    parseInt(item.payrollImpact || 0) === 1
                )}
            </td>

            <td>

                <a href="javascript:void(0);"
                    class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light edit-point-category"
                    data-index="${index}"
                    title="Edit">
            
                    <i class="ri-edit-line"></i>
            
                </a>
            
                <a href="javascript:void(0);"
                    class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-point-category"
                    data-index="${index}"
                    title="Delete">
            
                    <i class="ri-delete-bin-line"></i>
            
                </a>
            
            </td>

        </tr>
    `;
}

/*
|--------------------------------------------------------------------------
| Render Table
|--------------------------------------------------------------------------
*/

function renderTable() {

    const tbody =
        $('#pointCategoriesTable tbody');

    tbody.empty();

    let activeCount = 0;

    pointCategoriesState.forEach(function (
        item,
        index
    ) {

        if (
            parseInt(item.isActive ?? 1) === 1
        ) {

            activeCount++;

            tbody.append(
                renderRow(item, index)
            );
        }

    });

    if (activeCount === 0) {

        tbody.html(`
            <tr>
                <td colspan="8"
                    class="text-center text-muted py-4">

                    No point categories added yet.

                </td>
            </tr>
        `);
    }

    updateProgress();
}

/*
|--------------------------------------------------------------------------
| Update Setup Progress
|--------------------------------------------------------------------------
*/

function updateProgress() {

    $('#progressCategories').text(
        (
            pointCategoriesState.filter(function (x) {

                return (
                    parseInt(
                        x.isActive ?? 1
                    ) === 1
                );

            }).length > 0
                ? '✓ '
                : ''
        ) +
        'Point categories added'
    );

    $('#progressRules').text(
        (
            Number(
                $('#monthlyAllocation').val()
            ) > 0
                ? '✓ '
                : ''
        ) +
        'Point rules configured'
    );

    $('#progressWorkflow').text(
        (
            $('#approvalWorkflow').val()
                ? '✓ '
                : ''
        ) +
        'Approval workflow configured'
    );
}

/*
|--------------------------------------------------------------------------
| Open Add Modal
|--------------------------------------------------------------------------
*/

$('#addPointCategoryBtn').on(
    'click',
    function () {

        resetPointCategoryForm();

        $('#pointCategoryModal').modal('show');
    }
);

/*
|--------------------------------------------------------------------------
| Edit Category
|--------------------------------------------------------------------------
*/

$(document).on(
    'click',
    '.edit-point-category',
    function () {

        const index =
            $(this).data('index');

        const item =
            pointCategoriesState[index];

        $('#pointCategoryId').val(index);

        $('#categoryName').val(
            item.categoryName || ''
        );

        $('#categoryCode').val(
            item.categoryCode || ''
        );

        $('#transactionType').val(
            item.transactionType || 'Credit'
        );

        $('#defaultPoints').val(
            item.defaultPoints || 0
        );

        $('#severityLevel').val(
            item.severityLevel || 'Low'
        );

        $('#autoWarning').prop(
            'checked',
            parseInt(
                item.autoWarning || 0
            ) === 1
        );

        $('#payrollImpact').prop(
            'checked',
            parseInt(
                item.payrollImpact || 0
            ) === 1
        );

        $('#pointCategoryModalTitle').text(
            'Edit Point Category'
        );

        $('#pointCategoryModal').modal('show');
    }
);

/*
|--------------------------------------------------------------------------
| Save Category
|--------------------------------------------------------------------------
*/

$('#pointCategoryForm').on(
    'submit',
    function (e) {

        e.preventDefault();

        const index =
            $('#pointCategoryId').val();

        const item = {

            id:
                index !== ''
                    ? (
                        pointCategoriesState[index].id || 0
                    )
                    : 0,

            categoryName:
                $.trim(
                    $('#categoryName').val()
                ),

            categoryCode:
                $.trim(
                    $('#categoryCode').val()
                ).toUpperCase(),

            transactionType:
                $('#transactionType').val(),

            defaultPoints:
                Number(
                    $('#defaultPoints').val()
                ),

            severityLevel:
                $('#severityLevel').val(),

            autoWarning:
                $('#autoWarning').is(':checked')
                    ? 1
                    : 0,

            payrollImpact:
                $('#payrollImpact').is(':checked')
                    ? 1
                    : 0,

            isActive: 1
        };

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (!item.categoryName) {

            showToast(
                'warning',
                'Category name is required.'
            );

            return;
        }

        if (!item.categoryCode) {

            showToast(
                'warning',
                'Category code is required.'
            );

            return;
        }

        if (item.defaultPoints < 0) {

            showToast(
                'warning',
                'Invalid default points.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Category Code
        |--------------------------------------------------------------------------
        */

        let duplicate = false;

        pointCategoriesState.forEach(
            function (row, i) {

                if (
                    i != index &&
                    row.categoryCode ===
                        item.categoryCode &&
                    parseInt(
                        row.isActive ?? 1
                    ) === 1
                ) {

                    duplicate = true;
                }
            }
        );

        if (duplicate) {

            showToast(
                'warning',
                'Duplicate category code.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Add / Update
        |--------------------------------------------------------------------------
        */

        if (index !== '') {

            pointCategoriesState[index] =
                item;

        } else {

            pointCategoriesState.push(item);
        }

        renderTable();

        $('#pointCategoryModal').modal(
            'hide'
        );

        showToast(
            'success',
            'Category saved successfully.'
        );
    }
);

/*
|--------------------------------------------------------------------------
| Delete Category
|--------------------------------------------------------------------------
*/

$(document).on(
    'click',
    '.delete-point-category',
    function () {

        const index =
            $(this).data('index');

        const item =
            pointCategoriesState[index];

        Swal.fire({

            title: 'Are you sure?',

            text:
                "You won't be able to revert this!",

            icon: 'warning',

            showCancelButton: true,

            confirmButtonColor: '#3085d6',

            cancelButtonColor: '#d33',

            confirmButtonText:
                'Yes, delete it!'

        }).then(function (result) {

            if (!result.isConfirmed) {

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Unsaved Frontend Category
            |--------------------------------------------------------------------------
            */

            if (
                !item.id ||
                item.id == 0
            ) {

                pointCategoriesState[
                    index
                ].isActive = 0;

                renderTable();

                showToast(
                    'success',
                    'Category removed.'
                );

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Delete From Database
            |--------------------------------------------------------------------------
            */

            $.ajax({

                url:
                    API_BASE +
                    '/points/deletePointCategory.php',

                type: 'POST',

                data: {
                    id: item.id
                },

                dataType: 'json',

                success: function (response) {

                    if (response.success) {

                        pointCategoriesState[
                            index
                        ].isActive = 0;

                        renderTable();

                        showToast(
                            'success',
                            response.message
                        );

                    } else {

                        showToast(
                            'danger',
                            response.message ||
                            'Unable to delete category.'
                        );
                    }
                },

                error: function () {

                    showToast(
                        'danger',
                        'Server error while deleting category.'
                    );
                }
            });
        });
    }
);

/*
|--------------------------------------------------------------------------
| Save Setup
|--------------------------------------------------------------------------
*/

$('#savePointSetupBtn').on(
    'click',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        const monthlyAllocation =
            Number(
                $('#monthlyAllocation').val()
            );

        if (monthlyAllocation <= 0) {

            showToast(
                'warning',
                'Monthly allocation must be greater than zero.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Payload
        |--------------------------------------------------------------------------
        */

        const payload = {

            pointCategories:
                pointCategoriesState,

            pointSettings: {

                monthlyAllocation:
                    monthlyAllocation,

                warningThreshold:
                    Number(
                        $('#warningThreshold')
                        .val()
                    ),

                payrollThreshold:
                    Number(
                        $('#payrollThreshold')
                        .val()
                    ),

                enableWarningMail:
                    $('#enableWarningMail')
                    .is(':checked')
                        ? 1
                        : 0,

                enablePayrollImpact:
                    $('#enablePayrollImpact')
                    .is(':checked')
                        ? 1
                        : 0,

                autoResetMonthly:
                    $('#autoResetMonthly')
                    .is(':checked')
                        ? 1
                        : 0,

                carryForward:
                    $('#carryForward')
                    .is(':checked')
                        ? 1
                        : 0,

                carryForwardLimit:
                    Number(
                        $('#carryForwardLimit')
                        .val()
                    ),

                approvalWorkflow:
                    $('#approvalWorkflow')
                    .val()
            }
        };

        /*
        |--------------------------------------------------------------------------
        | Save AJAX
        |--------------------------------------------------------------------------
        */

        const btn =
            $('#savePointSetupBtn');

        btn.prop('disabled', true);

        btn.html(`
            <span class="spinner-border spinner-border-sm me-2"></span>
            Saving...
        `);

        $.ajax({

            url:
                API_BASE +
                '/points/savePointSetup.php',

            type: 'POST',

            data: JSON.stringify(payload),

            contentType: 'application/json',

            dataType: 'json',

            success: function (response) {

                if (response.success) {

                    showToast(
                        'success',
                        response.message
                    );

                    loadPointSetup();

                } else {

                    showToast(
                        'danger',
                        response.message ||
                        'Unable to save setup.'
                    );
                }
            },

            error: function () {

                showToast(
                    'danger',
                    'Server error occurred.'
                );
            },

            complete: function () {

                btn.prop('disabled', false);

                btn.html(`
                    <i class="ti ti-device-floppy me-1"></i>
                    Save Point Setup
                `);
            }
        });
    }
);

/*
|--------------------------------------------------------------------------
| Live Progress Tracking
|--------------------------------------------------------------------------
*/

$('#monthlyAllocation, #approvalWorkflow').on(
    'keyup change',
    function () {

        updateProgress();
    }
);

/*
|--------------------------------------------------------------------------
| Initial Load
|--------------------------------------------------------------------------
*/

loadPointSetup();

});

</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>