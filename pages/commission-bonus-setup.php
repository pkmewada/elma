<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

?>

<div class="main-content app-content">

    <div class="container-fluid">

        <!-- PAGE HEADER -->

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">

            <div>

                <h1 class="page-title fw-medium fs-18 mb-2">
                    Setup
                </h1>

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>

                    <li class="breadcrumb-item active">
                        Commission & Bonus Setup
                    </li>

                </ol>

            </div>

        </div>

        <div class="page-content pb-4">

            <div class="container-fluid px-0">

                <div class="row g-4">

                    <!-- CATEGORY MANAGEMENT -->

                    <div class="col-12">

                        <div class="card custom-card">

                            <div class="card-header d-flex justify-content-between align-items-center">

                                <h5 class="mb-0">
                                    Commission & Bonus Categories
                                </h5>

                                <button type="button"
                                    class="btn btn-primary"
                                    id="addCommissionCategoryBtn">

                                    <i class="ri-add-line"></i>
                                    Add Category

                                </button>

                            </div>

                            <div class="card-body">

                                <div id="setupWarning"
                                    class="alert alert-warning d-none mb-3">

                                    Changes in commission & bonus setup may affect payroll calculations and approvals.

                                </div>

                                <div class="table-responsive">

                                    <table class="table table-bordered text-nowrap w-100 align-middle mb-0"
                                        id="commissionCategoriesTable">

                                        <thead>

                                            <tr>

                                                <th>Category</th>
                                                <th>Code</th>
                                                <th>Type</th>
                                                <th>Default Amount</th>
                                                <th>Taxable</th>
                                                <th>Payroll</th>
                                                <th>Approval</th>
                                                <th>Actions</th>

                                            </tr>

                                        </thead>

                                        <tbody></tbody>

                                    </table>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- FINANCIAL RULES -->

                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Financial Rules
                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="monthlyBonusEnabled">

                                    <label class="form-check-label">
                                        Monthly Bonus Enabled
                                    </label>

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Max Bonus Limit
                                    </label>

                                    <input type="number"
                                        class="form-control"
                                        id="maxBonusLimit"
                                        min="0"
                                        step="0.01"
                                        value="0">

                                </div>

                                <div class="mb-3">

                                    <label class="form-label">
                                        Max Commission Limit
                                    </label>

                                    <input type="number"
                                        class="form-control"
                                        id="maxCommissionLimit"
                                        min="0"
                                        step="0.01"
                                        value="0">

                                </div>

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="payrollIntegration">

                                    <label class="form-check-label">
                                        Payroll Integration
                                    </label>

                                </div>

                                <div class="form-check form-switch">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="autoPayrollSync">

                                    <label class="form-check-label">
                                        Auto Payroll Sync
                                    </label>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- APPROVAL WORKFLOW -->

                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Approval Workflow
                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="mb-3">

                                    <label class="form-label">
                                        Approval Workflow
                                    </label>

                                    <select class="form-select"
                                        id="approvalWorkflow">

                                        <option value="Auto">
                                            Auto Approval
                                        </option>

                                        <option value="HR">
                                            HR Approval
                                        </option>

                                        <option value="Manager">
                                            Manager Approval
                                        </option>

                                        <option value="MultiLevel">
                                            Multi Level Approval
                                        </option>

                                    </select>

                                </div>

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="allowNegativeAdjustment">

                                    <label class="form-check-label">
                                        Allow Negative Adjustment
                                    </label>

                                </div>

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="requireRemarks">

                                    <label class="form-check-label">
                                        Require Remarks
                                    </label>

                                </div>

                                <div class="form-check form-switch">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="requireAttachment">

                                    <label class="form-check-label">
                                        Require Attachment
                                    </label>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- NOTIFICATION SETTINGS -->

                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">

                                <h5 class="mb-0">
                                    Notification Settings
                                </h5>

                            </div>

                            <div class="card-body">

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="notifyEmployeeOnApproval">

                                    <label class="form-check-label">
                                        Notify Employee On Approval
                                    </label>

                                </div>

                                <div class="form-check form-switch mb-3">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="notifyEmployeeOnRejection">

                                    <label class="form-check-label">
                                        Notify Employee On Rejection
                                    </label>

                                </div>

                                <div class="form-check form-switch">

                                    <input class="form-check-input"
                                        type="checkbox"
                                        id="notifyEmployeeOnPayrollSync">

                                    <label class="form-check-label">
                                        Notify Employee On Payroll Sync
                                    </label>

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
                                        Categories configured
                                    </li>

                                    <li id="progressRules">
                                        Financial rules configured
                                    </li>

                                    <li id="progressWorkflow">
                                        Approval workflow configured
                                    </li>

                                </ul>

                                <button type="button"
                                    class="btn btn-primary"
                                    id="saveCommissionSetupBtn">

                                    Save & Activate Commission Module

                                </button>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- CATEGORY MODAL -->

<div class="modal fade"
    id="commissionCategoryModal"
    tabindex="-1"
    aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title"
                    id="commissionCategoryModalTitle">

                    Add Category

                </h5>

                <button type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"></button>

            </div>

            <form id="commissionCategoryForm">

                <div class="modal-body">

                    <input type="hidden"
                        id="commissionCategoryId"
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
                                maxlength="20"
                                placeholder="Enter category code"
                                required>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label">
                                Category Type
                            </label>

                            <select class="form-select"
                                id="categoryType">

                                <option value="Bonus">
                                    Bonus
                                </option>

                                <option value="Commission">
                                    Commission
                                </option>

                            </select>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label"
                                id="amountFieldLabel">
                        
                                Default Amount
                        
                            </label>
                        
                            <input type="number"
                                class="form-control"
                                id="defaultAmount"
                                min="0"
                                step="0.01"
                                value="0">
                        
                        </div>

                        <!-- ADVANCED -->

                        <div class="col-md-6">

                            <div class="form-check form-switch mt-4">

                                <input class="form-check-input"
                                    type="checkbox"
                                    id="taxable">

                                <label class="form-check-label">
                                    Taxable
                                </label>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-check form-switch mt-4">

                                <input class="form-check-input"
                                    type="checkbox"
                                    id="payrollApplicable">

                                <label class="form-check-label">
                                    Payroll Applicable
                                </label>

                            </div>

                        </div>

                        <div class="col-md-6">

                            <div class="form-check form-switch">

                                <input class="form-check-input"
                                    type="checkbox"
                                    id="requiresApproval">

                                <label class="form-check-label">
                                    Requires Approval
                                </label>

                            </div>

                        </div>

                        <div class="col-12">

                            <label class="form-label">
                                Description
                            </label>

                            <textarea class="form-control"
                                id="description"
                                rows="3"
                                placeholder="Enter description"></textarea>

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

var commissionCategoriesState = [];

$(function () {

/*
|--------------------------------------------------------------------------
| Load Existing Setup
|--------------------------------------------------------------------------
*/

function loadCommissionSetup() {

    $.ajax({

        url: API_BASE + '/commission/getCommissionSetup.php',

        type: 'GET',

        dataType: 'json',

        success: function (response) {

            if (!response.success) {

                showToast(
                    'danger',
                    response.message || 'Unable to load setup.'
                );

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

            $('#monthlyBonusEnabled').prop(
                'checked',
                parseInt(
                    settings.monthlyBonusEnabled || 0
                ) === 1
            );

            $('#maxBonusLimit').val(
                settings.maxBonusLimit || 0
            );

            $('#maxCommissionLimit').val(
                settings.maxCommissionLimit || 0
            );

            $('#payrollIntegration').prop(
                'checked',
                parseInt(
                    settings.payrollIntegration || 0
                ) === 1
            );

            $('#autoPayrollSync').prop(
                'checked',
                parseInt(
                    settings.autoPayrollSync || 0
                ) === 1
            );

            $('#approvalWorkflow').val(
                settings.approvalWorkflow || 'HR'
            );

            $('#allowNegativeAdjustment').prop(
                'checked',
                parseInt(
                    settings.allowNegativeAdjustment || 0
                ) === 1
            );

            $('#requireRemarks').prop(
                'checked',
                parseInt(
                    settings.requireRemarks || 0
                ) === 1
            );

            $('#requireAttachment').prop(
                'checked',
                parseInt(
                    settings.requireAttachment || 0
                ) === 1
            );

            $('#notifyEmployeeOnApproval').prop(
                'checked',
                parseInt(
                    settings.notifyEmployeeOnApproval || 0
                ) === 1
            );

            $('#notifyEmployeeOnRejection').prop(
                'checked',
                parseInt(
                    settings.notifyEmployeeOnRejection || 0
                ) === 1
            );

            $('#notifyEmployeeOnPayrollSync').prop(
                'checked',
                parseInt(
                    settings.notifyEmployeeOnPayrollSync || 0
                ) === 1
            );

            /*
            |--------------------------------------------------------------------------
            | Categories
            |--------------------------------------------------------------------------
            */

            commissionCategoriesState =
                categories;

            renderTable();
        },

        error: function () {

            showToast(
                'danger',
                'Unable to load commission setup.'
            );
        }
    });
}

/*
|--------------------------------------------------------------------------
| Reset Category Form
|--------------------------------------------------------------------------
*/

function resetCommissionCategoryForm() {

    $('#commissionCategoryId').val('');

    $('#categoryName').val('');

    $('#categoryCode').val('');

    $('#categoryType').val('Bonus');

    $('#defaultAmount').val(0);

    $('#taxable').prop('checked', true);

    $('#payrollApplicable').prop('checked', true);

    $('#requiresApproval').prop('checked', true);

    $('#description').val('');

    $('#commissionCategoryModalTitle').text(
        'Add Category'
    );
    
    updateCategoryAmountLabel();
}


/*
|--------------------------------------------------------------------------
| Handle Category Type Change
|--------------------------------------------------------------------------
*/

function updateCategoryAmountLabel() {

    const type =
        $('#categoryType').val();

    if (
        type.toLowerCase() === 'commission'
    ) {

        $('#amountFieldLabel').text(
            'Commission Percentage (%)'
        );

    } else {

        $('#amountFieldLabel').text(
            'Default Amount'
        );
    }
}

$('#categoryType').on(
    'change',
    function () {

        updateCategoryAmountLabel();
    }
);

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
                ${item.categoryType || ''}
            </td>

            <td>
                ${
                    item.categoryType &&
                    item.categoryType.toLowerCase() === 'commission'
                
                        ? parseFloat(
                            item.commissionPercentage || 0
                          ).toFixed(2) + '%'
                
                        : '₹ ' + parseFloat(
                            item.defaultAmount || 0
                          ).toFixed(2)
                }
            </td>

            <td>
                ${badge(
                    parseInt(item.taxable || 0) === 1
                )}
            </td>

            <td>
                ${badge(
                    parseInt(item.payrollApplicable || 0) === 1
                )}
            </td>

            <td>
                ${badge(
                    parseInt(item.requiresApproval || 0) === 1
                )}
            </td>

            <td>

                <div class="d-flex gap-1">
            
                    <a href="javascript:void(0);"
                        class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light edit-commission-category"
                        data-index="${index}"
                        title="Edit">
            
                        <i class="ri-edit-line"></i>
            
                    </a>
            
                    <a href="javascript:void(0);"
                        class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-commission-category"
                        data-index="${index}"
                        title="Delete">
            
                        <i class="ri-delete-bin-line"></i>
            
                    </a>
            
                </div>
            
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
        $('#commissionCategoriesTable tbody');

    tbody.empty();

    let activeCount = 0;

    commissionCategoriesState.forEach(function (
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

                    No categories added yet.

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
            commissionCategoriesState.filter(function (x) {

                return (
                    parseInt(
                        x.isActive ?? 1
                    ) === 1
                );

            }).length > 0
                ? '✓ '
                : ''
        ) +
        'Categories configured'
    );

    $('#progressRules').text(
        (
            Number(
                $('#maxBonusLimit').val()
            ) >= 0
                ? '✓ '
                : ''
        ) +
        'Financial rules configured'
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

$('#addCommissionCategoryBtn').on(
    'click',
    function () {

        resetCommissionCategoryForm();

        $('#commissionCategoryModal').modal('show');
    }
);

/*
|--------------------------------------------------------------------------
| Edit Category
|--------------------------------------------------------------------------
*/

$(document).on(
    'click',
    '.edit-commission-category',
    function () {

        const index =
            $(this).data('index');

        const item =
            commissionCategoriesState[index];

        $('#commissionCategoryId').val(index);

        $('#categoryName').val(
            item.categoryName || ''
        );

        $('#categoryCode').val(
            item.categoryCode || ''
        );

        $('#categoryType').val(
            item.categoryType || 'Bonus'
        );
        
        updateCategoryAmountLabel();

        $('#defaultAmount').val(

            item.categoryType &&
            item.categoryType.toLowerCase() === 'commission'
        
                ? (
                    item.commissionPercentage || 0
                  )
        
                : (
                    item.defaultAmount || 0
                  )
        );

        $('#taxable').prop(
            'checked',
            parseInt(
                item.taxable || 0
            ) === 1
        );

        $('#payrollApplicable').prop(
            'checked',
            parseInt(
                item.payrollApplicable || 0
            ) === 1
        );

        $('#requiresApproval').prop(
            'checked',
            parseInt(
                item.requiresApproval || 0
            ) === 1
        );

        $('#description').val(
            item.description || ''
        );

        $('#commissionCategoryModalTitle').text(
            'Edit Category'
        );

        $('#commissionCategoryModal').modal('show');
    }
);

/*
|--------------------------------------------------------------------------
| Save Category
|--------------------------------------------------------------------------
*/

$('#commissionCategoryForm').on(
    'submit',
    function (e) {

        e.preventDefault();

        const index =
            $('#commissionCategoryId').val();

        const item = {

            id:
                index !== ''
                    ? (
                        commissionCategoriesState[index].id || 0
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

            categoryType:
                $('#categoryType').val(),

            defaultAmount:
                $('#categoryType').val().toLowerCase() === 'bonus'
                    ? (
                        parseFloat(
                            $('#defaultAmount').val()
                        ) || 0
                    )
                    : 0,
            
            commissionPercentage:
                $('#categoryType').val().toLowerCase() === 'commission'
                    ? (
                        parseFloat(
                            $('#defaultAmount').val()
                        ) || 0
                    )
                    : null,

            taxable:
                $('#taxable').is(':checked')
                    ? 1
                    : 0,

            payrollApplicable:
                $('#payrollApplicable').is(':checked')
                    ? 1
                    : 0,

            requiresApproval:
                $('#requiresApproval').is(':checked')
                    ? 1
                    : 0,

            description:
                $.trim(
                    $('#description').val()
                ),

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

        if (
            item.categoryType.toLowerCase() === 'bonus' &&
            item.defaultAmount < 0
        ) {
        
            showToast(
                'warning',
                'Invalid bonus amount.'
            );
        
            return;
        }
        
        if (
            item.categoryType.toLowerCase() === 'commission'
        ) {
        
            if (
                item.commissionPercentage <= 0
            ) {
        
                showToast(
                    'warning',
                    'Invalid commission percentage.'
                );
        
                return;
            }
        
            if (
                item.commissionPercentage > 100
            ) {
        
                showToast(
                    'warning',
                    'Commission percentage cannot exceed 100.'
                );
        
                return;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Code
        |--------------------------------------------------------------------------
        */

        let duplicate = false;

        commissionCategoriesState.forEach(
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

            commissionCategoriesState[index] =
                item;

        } else {

            commissionCategoriesState.push(item);
        }

        renderTable();

        $('#commissionCategoryModal').modal(
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
    '.delete-commission-category',
    function () {

        const index =
            $(this).data('index');

        const item =
            commissionCategoriesState[index];

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
            | Frontend Only
            |--------------------------------------------------------------------------
            */

            commissionCategoriesState[
                index
            ].isActive = 0;

            renderTable();

            showToast(
                'success',
                'Category removed successfully.'
            );
        });
    }
);

/*
|--------------------------------------------------------------------------
| Save Setup
|--------------------------------------------------------------------------
*/

$('#saveCommissionSetupBtn').on(
    'click',
    function () {

        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            Number(
                $('#maxBonusLimit').val()
            ) < 0
        ) {

            showToast(
                'warning',
                'Invalid max bonus limit.'
            );

            return;
        }

        if (
            Number(
                $('#maxCommissionLimit').val()
            ) < 0
        ) {

            showToast(
                'warning',
                'Invalid max commission limit.'
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Payload
        |--------------------------------------------------------------------------
        */

        const payload = {

            categories:
                commissionCategoriesState,

            settings: {

                monthlyBonusEnabled:
                    $('#monthlyBonusEnabled')
                    .is(':checked')
                        ? 1
                        : 0,

                maxBonusLimit:
                    Number(
                        $('#maxBonusLimit')
                        .val()
                    ),

                maxCommissionLimit:
                    Number(
                        $('#maxCommissionLimit')
                        .val()
                    ),

                payrollIntegration:
                    $('#payrollIntegration')
                    .is(':checked')
                        ? 1
                        : 0,

                autoPayrollSync:
                    $('#autoPayrollSync')
                    .is(':checked')
                        ? 1
                        : 0,

                approvalWorkflow:
                    $('#approvalWorkflow')
                    .val(),

                allowNegativeAdjustment:
                    $('#allowNegativeAdjustment')
                    .is(':checked')
                        ? 1
                        : 0,

                requireRemarks:
                    $('#requireRemarks')
                    .is(':checked')
                        ? 1
                        : 0,

                requireAttachment:
                    $('#requireAttachment')
                    .is(':checked')
                        ? 1
                        : 0,

                notifyEmployeeOnApproval:
                    $('#notifyEmployeeOnApproval')
                    .is(':checked')
                        ? 1
                        : 0,

                notifyEmployeeOnRejection:
                    $('#notifyEmployeeOnRejection')
                    .is(':checked')
                        ? 1
                        : 0,

                notifyEmployeeOnPayrollSync:
                    $('#notifyEmployeeOnPayrollSync')
                    .is(':checked')
                        ? 1
                        : 0
            }
        };

        /*
        |--------------------------------------------------------------------------
        | Save AJAX
        |--------------------------------------------------------------------------
        */

        const btn =
            $('#saveCommissionSetupBtn');

        btn.prop('disabled', true);

        btn.html(`
            <span class="spinner-border spinner-border-sm me-2"></span>
            Saving...
        `);

        $.ajax({

            url:
                API_BASE +
                '/commission/saveCommissionSetup.php',

            type: 'POST',

            data: JSON.stringify(payload),

            contentType: 'application/json',

            dataType: 'json',

            success: function (response) {

                if (response.success) {

                    showToast(
                        'success',
                        response.message ||
                        'Commission setup saved successfully.'
                    );

                    loadCommissionSetup();

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
                    Save & Activate Commission Module
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

$('#maxBonusLimit, #approvalWorkflow').on(
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

loadCommissionSetup();

});

</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>