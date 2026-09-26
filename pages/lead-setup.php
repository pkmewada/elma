<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <!-- =======================================
         PAGE HEADER
    ======================================= -->

    <div class="container-fluid">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h4 class="mb-1">Lead Setup</h4>

                <p class="text-muted mb-0">Configure lead categories and plans.</p>
            </div>

            <button type="button" class="btn btn-primary" id="saveLeadSetupBtn">
                <i class="ri-save-line me-1"></i>
                Save Configuration
            </button>
        </div>

        <div class="row g-4">
            <!-- ==========================
             LEAD CATEGORIES
            ========================== -->

            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Lead Categories</h5>

                            <button type="button" class="btn btn-sm btn-primary" id="addCategoryBtn">
                                <i class="ri-add-line me-1"></i>
                                Add Category
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Category Name</th>

                                        <th>Code</th>

                                        <th>Status</th>

                                        <th width="120">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="leadCategoriesTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================
             LEAD PLANS
            ========================== -->

            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Lead Plans</h5>

                            <button type="button" class="btn btn-sm btn-primary" id="addPlanBtn">
                                <i class="ri-add-line me-1"></i>
                                Add Plan
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>

                                        <th>Plan Name</th>

                                        <th>Plan Code</th>

                                        <th>Status</th>

                                        <th width="120">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="leadPlansTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ==========================
             FOLLOW UP SETUP (moved from the former lead-follow-up-setup
             page -- same leadFollowUpSettings data/engine, no separate page)
            ========================== -->

            <div class="col-lg-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Follow Up Setup</h5>

                            <button type="button" class="btn btn-sm btn-primary" id="addFollowUpRuleBtn">
                                <i class="ri-add-line me-1"></i>
                                Add Follow Up Rule
                            </button>
                        </div>
                    </div>

                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Define how many days after a lead is created each follow up should be due. New leads automatically get these follow ups scheduled on creation.
                        </p>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Day After Lead Created</th>
                                        <th>Follow Up Number</th>
                                        <th>Action Type</th>
                                        <th>Status</th>
                                        <th width="120">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="followUpRulesTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== FOLLOW UP RULE MODAL ==================== -->
    <div class="modal fade" id="followUpRuleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="followUpRuleModalTitle">Add Follow Up Rule</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="followUpRuleId" />

                    <div class="mb-3">
                        <label class="form-label"> Day After Lead Created </label>
                        <input type="number" min="1" class="form-control" id="followUpDayNumber" placeholder="e.g. 1" />
                        <div class="form-text">Number of days after the lead is created that this follow up is due.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Follow Up Number </label>
                        <input type="number" min="1" class="form-control" id="followUpSequence" placeholder="e.g. 1" />
                        <div class="form-text">Order of this follow up (1st, 2nd, 3rd...).</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Action Type </label>
                        <select class="form-select" id="followUpType">
                            <option value="Call">Call</option>
                            <option value="WhatsApp">WhatsApp</option>
                            <option value="Email">Email</option>
                            <option value="Meeting">Meeting</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label"> Status </label>
                        <select class="form-select" id="followUpRuleStatus">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveFollowUpRuleBtn">Save Rule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- =======================================
         CATEGORY MODAL
    ======================================= -->

    <div class="modal fade" id="leadCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadCategoryModalTitle">Add Category</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="categoryId" />

                    <div class="mb-3">
                        <label class="form-label"> Category Name </label>

                        <input type="text" class="form-control" id="categoryName" placeholder="Enter Category Name" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Category Code </label>

                        <input type="text" class="form-control" id="categoryCode" placeholder="Enter Category Code" />
                    </div>

                    <div>
                        <label class="form-label"> Status </label>

                        <select class="form-select" id="categoryStatus">
                            <option value="Active">Active</option>

                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="saveCategoryBtn">Save Category</button>
                </div>
            </div>
        </div>
    </div>

    <!-- =======================================
         PLAN MODAL
    ======================================= -->

    <div class="modal fade" id="leadPlanModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="leadPlanModalTitle">Add Plan</h5>

                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" id="planId" />

                    <div class="mb-3">
                        <label class="form-label"> Category </label>

                        <select class="form-select" id="planCategoryId"></select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Plan Name </label>

                        <input type="text" class="form-control" placeholder="Enter Plan Name" id="planName" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label"> Plan Code </label>

                        <input type="text" class="form-control" id="planCode" placeholder="Enter Plan Code" />
                    </div>

                    <div>
                        <label class="form-label"> Status </label>

                        <select class="form-select" id="planStatus">
                            <option value="Active">Active</option>

                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" id="savePlanBtn">Save Plan</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>

let leadCategoriesState = [];
let leadPlansState = [];

$(document).ready(function () {

    loadLeadSetup();

});

/*
|--------------------------------------------------------------------------
| Load Lead Setup
|--------------------------------------------------------------------------
*/
function loadLeadSetup() {

    $.ajax({

        url: API_BASE + '/leads/getLeadSetup.php',

        type: 'GET',

        dataType: 'json',

        success: function (response) {

            if (!response.success) {

                showToast(
                    'error',
                    response.message
                );

                return;
            }

            leadCategoriesState =
                response.data
                    .leadCategories || [];

            leadPlansState =
                response.data
                    .leadPlans || [];

            renderCategories();

            renderPlans();

            populateCategoryDropdown();
        },

        error: function () {

            showToast(
                'error',
                'Unable to load lead setup.'
            );
        }
    });
}

/*
|--------------------------------------------------------------------------
| Render Categories
|--------------------------------------------------------------------------
*/
function renderCategories() {

    let html = '';

    if (
        !leadCategoriesState.length
    ) {

        html = `
            <tr>
                <td colspan="4"
                    class="text-center text-muted">

                    No categories found

                </td>
            </tr>
        `;

    } else {

        leadCategoriesState.forEach(
            function (category) {

                html += `
                <tr>

                    <td>
                        ${category.categoryName}
                    </td>

                    <td>
                        ${category.categoryCode}
                    </td>

                    <td>

                        <span class="
                            btn
                            btn-sm
                            ${category.status === 'Active'
                                ? 'btn-outline-success'
                                : 'btn-outline-danger'}
                        ">
                            ${category.status}
                        </span>

                    </td>

                    <td>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary edit-category-btn"
                            data-id="${category.id}">

                            <i class="ri-pencil-line"></i>

                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger delete-category-btn"
                            data-id="${category.id}">

                            <i class="ri-delete-bin-line"></i>

                        </button>

                    </td>

                </tr>
                `;
            }
        );
    }

    $('#leadCategoriesTableBody')
        .html(html);
}

/*
|--------------------------------------------------------------------------
| Render Plans
|--------------------------------------------------------------------------
*/
function renderPlans() {

    let html = '';

    if (
        !leadPlansState.length
    ) {

        html = `
            <tr>
                <td colspan="5"
                    class="text-center text-muted">

                    No plans found

                </td>
            </tr>
        `;

    } else {

        leadPlansState.forEach(
            function (plan) {

                html += `
                <tr>

                    <td>
                        ${plan.categoryName}
                    </td>

                    <td>
                        ${plan.planName}
                    </td>

                    <td>
                        ${plan.planCode}
                    </td>

                    <td>

                        <span class="
                            btn
                            btn-sm
                            ${plan.status === 'Active'
                                ? 'btn-outline-success'
                                : 'btn-outline-danger'}
                        ">
                            ${plan.status}
                        </span>

                    </td>

                    <td>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-primary edit-plan-btn"
                            data-id="${plan.id}">

                            <i class="ri-pencil-line"></i>

                        </button>

                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger delete-plan-btn"
                            data-id="${plan.id}">

                            <i class="ri-delete-bin-line"></i>

                        </button>

                    </td>

                </tr>
                `;
            }
        );
    }

    $('#leadPlansTableBody')
        .html(html);
}

/*
|--------------------------------------------------------------------------
| Populate Category Dropdown
|--------------------------------------------------------------------------
*/
function populateCategoryDropdown() {

    let html =
        '<option value="">Select Category</option>';

    leadCategoriesState.forEach(
        function (category) {

            html += `
                <option value="${category.id}">
                    ${category.categoryName}
                </option>
            `;
        }
    );

    $('#planCategoryId')
        .html(html);
}


/*
|--------------------------------------------------------------------------
| Add Category
|--------------------------------------------------------------------------
*/

                                                                      
 $(document).on(                                                            


'click',
'#addCategoryBtn',
function () {

    $('#leadCategoryModalTitle')
        .text('Add Category');

    $('#categoryId')
        .val('');

    $('#categoryName')
        .val('');

    $('#categoryCode')
        .val('');

    $('#categoryStatus')
        .val('Active');

    $('#leadCategoryModal')
        .modal('show');
}


);

/*
|--------------------------------------------------------------------------
| Save Category
|--------------------------------------------------------------------------
*/

$(document).on(                                                            


'click',
'#saveCategoryBtn',
function () {

    let id =
        $('#categoryId').val();

    let categoryName =
        $('#categoryName')
            .val()
            .trim();

    let categoryCode =
        $('#categoryCode')
            .val()
            .trim();

    let status =
        $('#categoryStatus')
            .val();

    if (!categoryName) {

        showToast(
            'error',
            'Category name is required.'
        );

        return;
    }

    if (!categoryCode) {

        showToast(
            'error',
            'Category code is required.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Code Check
    |--------------------------------------------------------------------------
    */
    let duplicate =
        leadCategoriesState.some(
            c =>

                c.categoryCode
                    .toLowerCase()
                ===
                categoryCode
                    .toLowerCase()

                &&

                String(c.id)
                !==
                String(id)
        );

    if (duplicate) {

        showToast(
            'error',
            'Category code already exists.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Update
    |--------------------------------------------------------------------------
    */
    if (id) {

        let category =
            leadCategoriesState.find(
                c =>
                    String(c.id)
                    ===
                    String(id)
            );

        if (category) {

            category.categoryName =
                categoryName;

            category.categoryCode =
                categoryCode;

            category.status =
                status;
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | Add New
        |--------------------------------------------------------------------------
        */
        leadCategoriesState.push({

            id:
                'new_' +
                Date.now(),

            categoryName:
                categoryName,

            categoryCode:
                categoryCode,

            status:
                status
        });
    }

    renderCategories();

    populateCategoryDropdown();

    $('#leadCategoryModal')
        .modal('hide');
}


);

/*
|--------------------------------------------------------------------------
| Edit Category
|--------------------------------------------------------------------------
*/

$(document).on(                                                            


'click',
'.edit-category-btn',
function () {

    let id =
        $(this)
            .data('id');

    let category =
        leadCategoriesState.find(
            c =>
                String(c.id)
                ===
                String(id)
        );

    if (!category) {
        return;
    }

    $('#leadCategoryModalTitle')
        .text('Edit Category');

    $('#categoryId')
        .val(category.id);

    $('#categoryName')
        .val(category.categoryName);

    $('#categoryCode')
        .val(category.categoryCode);

    $('#categoryStatus')
        .val(category.status);

    $('#leadCategoryModal')
        .modal('show');
}


);

/*
|--------------------------------------------------------------------------
| Delete Category
|--------------------------------------------------------------------------
*/

$(document).on(                                                            


'click',
'.delete-category-btn',
function () {

    let id =
        $(this)
            .data('id');

    if (
        !confirm(
            'Delete this category and all associated plans?'
        )
    ) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Remove Category
    |--------------------------------------------------------------------------
    */
    leadCategoriesState =
        leadCategoriesState.filter(
            c =>
                String(c.id)
                !==
                String(id)
        );

    /*
    |--------------------------------------------------------------------------
    | Remove Plans
    |--------------------------------------------------------------------------
    */
    leadPlansState =
        leadPlansState.filter(
            p =>
                String(p.categoryId)
                !==
                String(id)
        );

    renderCategories();

    renderPlans();

    populateCategoryDropdown();
}


);




/*
|--------------------------------------------------------------------------
| Add Plan                                                                   
|--------------------------------------------------------------------------
*/
$(document).on(                                                            


'click',
'#addPlanBtn',
function () {

    if (
        !leadCategoriesState.length
    ) {

        showToast(
            'error',
            'Please create a category first.'
        );

        return;
    }

    $('#leadPlanModalTitle')
        .text('Add Plan');

    $('#planId')
        .val('');

    $('#planCategoryId')
        .val('');

    $('#planName')
        .val('');

    $('#planCode')
        .val('');

    $('#planStatus')
        .val('Active');

    $('#leadPlanModal')
        .modal('show');
}


);

/*
|--------------------------------------------------------------------------
| Save Plan                                                                   
|--------------------------------------------------------------------------
*/
$(document).on(                                                            


'click',
'#savePlanBtn',
function () {

    let id =
        $('#planId').val();

    let categoryId =
        $('#planCategoryId').val();

    let planName =
        $('#planName')
            .val()
            .trim();

    let planCode =
        $('#planCode')
            .val()
            .trim();

    let status =
        $('#planStatus').val();

    if (!categoryId) {

        showToast(
            'error',
            'Please select category.'
        );

        return;
    }

    if (!planName) {

        showToast(
            'error',
            'Plan name is required.'
        );

        return;
    }

    if (!planCode) {

        showToast(
            'error',
            'Plan code is required.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Duplicate Plan Code Check
    |--------------------------------------------------------------------------
    */
    let duplicate =
        leadPlansState.some(
            p =>

                p.planCode
                    .toLowerCase()
                ===
                planCode
                    .toLowerCase()

                &&

                String(p.id)
                !==
                String(id)
        );

    if (duplicate) {

        showToast(
            'error',
            'Plan code already exists.'
        );

        return;
    }

    let category =
        leadCategoriesState.find(
            c =>
                String(c.id)
                ===
                String(categoryId)
        );

    if (!category) {

        showToast(
            'error',
            'Selected category not found.'
        );

        return;
    }

    /*
    |--------------------------------------------------------------------------
    | Edit Existing
    |--------------------------------------------------------------------------
    */
    if (id) {

        let plan =
            leadPlansState.find(
                p =>
                    String(p.id)
                    ===
                    String(id)
            );

        if (plan) {

            plan.categoryId =
                categoryId;

            plan.categoryName =
                category.categoryName;

            plan.planName =
                planName;

            plan.planCode =
                planCode;

            plan.status =
                status;
        }

    } else {

        /*
        |--------------------------------------------------------------------------
        | Add New
        |--------------------------------------------------------------------------
        */
        leadPlansState.push({

            id:
                'new_' +
                Date.now(),

            categoryId:
                categoryId,

            categoryName:
                category.categoryName,

            planName:
                planName,

            planCode:
                planCode,

            status:
                status
        });
    }

    renderPlans();

    $('#leadPlanModal')
        .modal('hide');
}


);

/*
|--------------------------------------------------------------------------
| Edit Plan                                                                   
|--------------------------------------------------------------------------
*/
$(document).on(                                                            


'click',
'.edit-plan-btn',
function () {

    let id =
        $(this)
            .data('id');

    let plan =
        leadPlansState.find(
            p =>
                String(p.id)
                ===
                String(id)
        );

    if (!plan) {
        return;
    }

    $('#leadPlanModalTitle')
        .text('Edit Plan');

    $('#planId')
        .val(plan.id);

    $('#planCategoryId')
        .val(plan.categoryId);

    $('#planName')
        .val(plan.planName);

    $('#planCode')
        .val(plan.planCode);

    $('#planStatus')
        .val(plan.status);

    $('#leadPlanModal')
        .modal('show');
}


);

/*
|--------------------------------------------------------------------------
| Delete Plan                                                                   
|--------------------------------------------------------------------------
*/
$(document).on(                                                            


'click',
'.delete-plan-btn',
function () {

    let id =
        $(this)
            .data('id');

    if (
        !confirm(
            'Delete this plan?'
        )
    ) {
        return;
    }

    leadPlansState =
        leadPlansState.filter(
            p =>
                String(p.id)
                !==
                String(id)
        );

    renderPlans();
}


);


/*
|--------------------------------------------------------------------------
| Save Lead Setup                                                            
|--------------------------------------------------------------------------
*/                                                                         
$(document).on(                                                           


'click',
'#saveLeadSetupBtn',
function () {

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */
    if (
        !leadCategoriesState.length
    ) {

        showToast(
            'error',
            'Please add at least one category.'
        );

        return;
    }

    if (
        !leadPlansState.length
    ) {

        showToast(
            'error',
            'Please add at least one plan.'
        );

        return;
    }

    let $btn = $(this);

    $btn
        .prop('disabled', true)
        .html(
            '<span class="spinner-border spinner-border-sm me-2"></span>Saving...'
        );

    $.ajax({

        url:
            API_BASE +
            '/leads/saveLeadSetup.php',

        type: 'POST',

        contentType:
            'application/json',

        dataType: 'json',

        data: JSON.stringify({

            leadCategories:
                leadCategoriesState,

            leadPlans:
                leadPlansState
        }),

        success: function (
            response
        ) {

            if (
                response.success
            ) {

                showToast(
                    'success',
                    response.message
                );

                /*
                |--------------------------------------------------------------------------
                | Reload Latest Data
                |--------------------------------------------------------------------------
                */
                loadLeadSetup();

            } else {

                showToast(
                    'error',
                    response.message
                );
            }
        },

        error: function () {

            showToast(
                'error',
                'Unable to save lead setup.'
            );
        },

        complete: function () {

            $btn
                .prop(
                    'disabled',
                    false
                )
                .html(
                    '<i class="ri-save-line me-1"></i> Save Configuration'
                );
        }
    });
}


);

</script>

<!-- Follow Up Setup CRUD -- unchanged, moved here instead of its own page -->
<script src="<?= ASSET_URL ?>/assets/js/lead-follow-up-setup.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
