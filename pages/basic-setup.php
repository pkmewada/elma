<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/basic-config.php';

$errorMessage = '';
$successMessage = '';
$roleMessage = '';
$termsMessage = '';
$departmentMessage = '';
$deductionMessage = '';
$expenseMessage = '';


$currentConfig = getBasicConfig();

$emailValue = $currentConfig['gmail_username'] ?? '';
$passwordValue = '';

$organizationRoles = $currentConfig['organizationRoles'] ?? [];
$departments = $currentConfig['departments'] ?? [];

$deductionTypes = $currentConfig['deductionTypes'] ?? [];
$expenseTypes = $currentConfig['expenseTypes'] ?? [];

$termsHtml = $currentConfig['terms_and_conditions_html'] ?? '';
$termsLastUpdated = $currentConfig['terms_last_updated'] ?? '';

/*
|--------------------------------------------------------------------------
| Reusable Helpers
|--------------------------------------------------------------------------
*/
function normalizeItems(string $input): array
{
    $input = trim($input);

    if ($input === '') {
        return [];
    }

    $input = str_replace(['/', '|', ';'], ',', $input);
    $input = preg_replace('/,+/', ',', $input);

    $items = explode(',', $input);
    $clean = [];

    foreach ($items as $item) {

        $item = trim($item);
        $item = preg_replace('/\s+/', ' ', $item);

        if ($item === '') {
            continue;
        }

        $item = ucwords(strtolower($item));
        $clean[] = $item;
    }

    return $clean;
}

function mergeUniqueItems(array $existing, array $new): array
{
    $addedCount = 0;

    foreach ($new as $item) {

        $exists = false;

        foreach ($existing as $old) {
            if (strtolower($old) === strtolower($item)) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $existing[] = $item;
            $addedCount++;
        }
    }

    natcasesort($existing);

    return [
        'items' => array_values($existing),
        'count' => $addedCount
    ];
}

function removeItem(array $items, string $delete): array
{
    return array_values(array_filter(
        $items,
        fn($item) => $item !== $delete
    ));
}

function saveSetupData(
    string $email,
    string $password,
    array $roles,
    array $departments,
    array $deductionTypes,
    array $expenseTypes,
    string $termsHtml,
    string $termsUpdated
): bool {
    return saveBasicConfig([
        'gmail_username' => $email,
        'gmail_app_password' => $password,
        'organizationRoles' => $roles,
        'departments' => $departments,
        'deductionTypes' => $deductionTypes,
        'expenseTypes' => $expenseTypes,
        'terms_and_conditions_html' => $termsHtml,
        'terms_last_updated' => $termsUpdated,
    ]);
}

/*
|--------------------------------------------------------------------------
| Save Gmail Configuration
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveGmailConfig'])) {

    $emailValue = trim((string) ($_POST['gmail_email'] ?? ''));
    $passwordValue = trim((string) ($_POST['gmail_app_password'] ?? ''));

    if ($emailValue === '' || $passwordValue === '') {

        $errorMessage = 'Please enter both Gmail email and app password.';

    } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {

        $errorMessage = 'Please enter a valid Gmail email address.';

    } else {

        $saved = saveSetupData(
            $emailValue,
            $passwordValue,
            $organizationRoles,
            $departments,
            $deductionTypes,
            $expenseTypes,
            $termsHtml,
            $termsLastUpdated
        );

        if ($saved) {
            $successMessage = 'Basic setup saved successfully.';
            $passwordValue = '';
        } else {
            $errorMessage = 'Unable to save setup right now. Please try again.';
        }
    }
}

/*
|--------------------------------------------------------------------------
| Add Organization Roles
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addOrganizationRole'])) {

    $roleInput = (string) ($_POST['role_name'] ?? '');

    if (trim($roleInput) === '') {

        $roleMessage = 'Please enter role names.';

    } else {

        $newRoles = normalizeItems($roleInput);
        $result = mergeUniqueItems($organizationRoles, $newRoles);

        $organizationRoles = $result['items'];

        $saved = saveSetupData(
            $currentConfig['gmail_username'] ?? '',
            $currentConfig['gmail_app_password'] ?? '',
            $organizationRoles,
            $departments,
            $deductionTypes,
            $expenseTypes,
            $termsHtml,
            $termsLastUpdated
        );

        $roleMessage = $saved
            ? $result['count'] . ' role(s) added successfully.'
            : 'Unable to save roles right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Add Departments
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addDepartment'])) {

    $departmentInput = (string) ($_POST['department_name'] ?? '');

    if (trim($departmentInput) === '') {

        $departmentMessage = 'Please enter department name.';

    } else {

        $newDepartments = normalizeItems($departmentInput);
        $result = mergeUniqueItems($departments, $newDepartments);

        $departments = $result['items'];

        $saved = saveSetupData(
            $currentConfig['gmail_username'] ?? '',
            $currentConfig['gmail_app_password'] ?? '',
            $organizationRoles,
            $departments,
            $deductionTypes,
            $expenseTypes,
            $termsHtml,
            $termsLastUpdated
        );

        $departmentMessage = $saved
            ? $result['count'] . ' department(s) added successfully.'
            : 'Unable to save department right now.';
    }
}

/*
|--------------------------------------------------------------------------
| Delete Role
|--------------------------------------------------------------------------
*/
if (isset($_GET['deleteRole'])) {

    $deleteRole = trim((string) $_GET['deleteRole']);

    $organizationRoles = removeItem($organizationRoles, $deleteRole);

    saveSetupData(
        $currentConfig['gmail_username'] ?? '',
        $currentConfig['gmail_app_password'] ?? '',
        $organizationRoles,
        $departments,
        $deductionTypes,
        $expenseTypes,
        $termsHtml,
        $termsLastUpdated
    );

    header('Location: basic-setup');
    exit;
}

/*
|--------------------------------------------------------------------------
| Delete Department
|--------------------------------------------------------------------------
*/
if (isset($_GET['deleteDepartment'])) {

    $deleteDepartment = trim((string) $_GET['deleteDepartment']);

    $departments = removeItem($departments, $deleteDepartment);

    saveSetupData(
        $currentConfig['gmail_username'] ?? '',
        $currentConfig['gmail_app_password'] ?? '',
        $organizationRoles,
        $departments,
        $deductionTypes,
        $expenseTypes,
        $termsHtml,
        $termsLastUpdated
    );

    header('Location: basic-setup');
    exit;
}

/*
|--------------------------------------------------------------------------
| Save Terms & Conditions
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveTermsConditions'])) {

    $termsHtmlInput = trim((string) ($_POST['terms_conditions_html'] ?? ''));

    if ($termsHtmlInput === '') {

        $termsMessage = 'Please enter Terms & Conditions content.';

    } else {

        $termsHtml = $termsHtmlInput;
        $termsLastUpdated = date('Y-m-d H:i:s');

        $saved = saveSetupData(
            $currentConfig['gmail_username'] ?? '',
            $currentConfig['gmail_app_password'] ?? '',
            $organizationRoles,
            $departments,
            $deductionTypes,
            $expenseTypes,
            $termsHtml,
            $termsLastUpdated
        );

        if ($saved) {
            $termsMessage = 'Terms & Conditions updated successfully.';
        } else {
            $termsMessage = 'Unable to save Terms & Conditions right now.';
        }
    }
}



/*
|--------------------------------------------------------------------------
| Add Deduction Types
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addDeductionType'])) {

    $deductionInput = (string) ($_POST['deduction_type'] ?? '');

    if (trim($deductionInput) === '') {

        $deductionMessage = 'Please enter deduction type.';

    } else {

        $newItems = normalizeItems($deductionInput);

        $result = mergeUniqueItems($deductionTypes, $newItems);

        $deductionTypes = $result['items'];

        $saved = saveSetupData(
            $currentConfig['gmail_username'] ?? '',
            $currentConfig['gmail_app_password'] ?? '',
            $organizationRoles,
            $departments,
            $deductionTypes,
            $expenseTypes,
            $termsHtml,
            $termsLastUpdated
        );

        $deductionMessage = $saved
            ? $result['count'] . ' deduction type(s) added successfully.'
            : 'Unable to save deduction types.';
    }
}


/*
|--------------------------------------------------------------------------
| Add Expense Types
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['addExpenseType'])) {

    $expenseInput = (string) ($_POST['expense_type'] ?? '');

    if (trim($expenseInput) === '') {

        $expenseMessage = 'Please enter expense type.';

    } else {

        $newItems = normalizeItems($expenseInput);

        $result = mergeUniqueItems($expenseTypes, $newItems);

        $expenseTypes = $result['items'];

        $saved = saveSetupData(
            $currentConfig['gmail_username'] ?? '',
            $currentConfig['gmail_app_password'] ?? '',
            $organizationRoles,
            $departments,
            $deductionTypes,
            $expenseTypes,
            $termsHtml,
            $termsLastUpdated
        );

        $expenseMessage = $saved
            ? $result['count'] . ' expense type(s) added successfully.'
            : 'Unable to save expense types.';
    }
}


if (isset($_GET['deleteDeductionType'])) {

    $deleteItem = trim((string) $_GET['deleteDeductionType']);

    $deductionTypes = removeItem($deductionTypes, $deleteItem);

    saveSetupData(
        $currentConfig['gmail_username'] ?? '',
        $currentConfig['gmail_app_password'] ?? '',
        $organizationRoles,
        $departments,
        $deductionTypes,
        $expenseTypes,
        $termsHtml,
        $termsLastUpdated
    );

    header('Location: basic-setup');
    exit;
}


if (isset($_GET['deleteExpenseType'])) {

    $deleteItem = trim((string) $_GET['deleteExpenseType']);

    $expenseTypes = removeItem($expenseTypes, $deleteItem);

    saveSetupData(
        $currentConfig['gmail_username'] ?? '',
        $currentConfig['gmail_app_password'] ?? '',
        $organizationRoles,
        $departments,
        $deductionTypes,
        $expenseTypes,
        $termsHtml,
        $termsLastUpdated
    );

    header('Location: basic-setup');
    exit;
}




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
                    <li class="breadcrumb-item active">Basic Setup</li>
                </ol>
            </div>
        </div>

        <div class="page-content pb-4">
            <div class="container-fluid px-0">
                <div class="row g-4">

                    <!-- Gmail Setup -->
                    <div class="col-xl-4 col-lg-4">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Gmail Configuration</h5>
                            </div>

                            <div class="card-body">

                                <?php if ($successMessage !== ''): ?>
                                <div class="alert alert-success">
                                    <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>

                                <?php if ($errorMessage !== ''): ?>
                                <div class="alert alert-danger">
                                    <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>

                                <p class="text-muted mb-4">
                                    Use this form to set mail credentials without hardcoding values in code.
                                </p>

                                <form method="post">

                                    <div class="mb-3">
                                        <label class="form-label">Gmail Email</label>
                                        <input type="email" class="form-control" name="gmail_email"
                                            placeholder="name@gmail.com"
                                            value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Gmail App Password</label>
                                        <input type="password" class="form-control" name="gmail_app_password"
                                            placeholder="Enter app password" required>

                                        <small class="text-muted">
                                            Current password is hidden for security.
                                        </small>
                                    </div>

                                    <button type="submit" name="saveGmailConfig" class="btn btn-primary">
                                        Save Configuration
                                    </button>

                                </form>

                            </div>
                        </div>
                    </div>

                    <!-- Organization Role Setup -->
                    <div class="col-xl-4 col-lg-4">
                        <div class="card custom-card h-100">

                            <div class="card-header">
                                <h5 class="mb-0">Organization Role Setup</h5>
                            </div>

                            <div class="card-body">

                                <?php if ($roleMessage !== ''): ?>
                                <div class="alert alert-info">
                                    <?= htmlspecialchars($roleMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>

                                <p class="text-muted mb-4">
                                    Add roles that will appear in Candidate Record under Role Applied For.
                                </p>

                                <form method="post" class="mb-4">

                                    <div class="input-group">
                                        <input type="text" class="form-control" name="role_name"
                                            placeholder="HR Executive, Designer, Sales Manager" required>

                                        <button type="submit" name="addOrganizationRole" class="btn btn-primary">
                                            Add Role
                                        </button>
                                    </div>

                                </form>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">

                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Role Name</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php if (!empty($organizationRoles)): ?>
                                            <?php foreach ($organizationRoles as $index => $role): ?>
                                            <tr>
                                                <td><?= $index + 1; ?></td>
                                                <td><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?></td>
                                                <td>
                                                    <a href="?deleteRole=<?= urlencode($role); ?>"
                                                       class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-confirm"
                                                       data-message="Delete this role?"
                                                       data-title="Delete Role"
                                                       title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">
                                                    No roles added yet.
                                                </td>
                                            </tr>
                                            <?php endif; ?>

                                        </tbody>

                                    </table>
                                </div>

                            </div>

                        </div>
                    </div>

                    <!-- Department Management -->
                    <div class="col-xl-4 col-lg-4">
                        <div class="card custom-card h-100">

                            <div class="card-header">
                                <h5 class="mb-0">Department Management</h5>
                            </div>

                            <div class="card-body">

                                <?php if ($departmentMessage !== ''): ?>
                                <div class="alert alert-info">
                                    <?= htmlspecialchars($departmentMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>

                                <p class="text-muted mb-4">
                                    Add departments for employee onboarding.
                                </p>

                                <form method="post" class="mb-4">
                                    <div class="input-group">
                                        <input type="text" class="form-control" name="department_name"
                                            placeholder="HR, Sales, Accounts" required>

                                        <button type="submit" name="addDepartment" class="btn btn-primary">
                                            Add Department
                                        </button>
                                    </div>
                                </form>

                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">

                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Department</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody>

                                            <?php if (!empty($departments)): ?>
                                            <?php foreach ($departments as $index => $department): ?>
                                            <tr>
                                                <td><?= $index + 1; ?></td>
                                                <td><?= htmlspecialchars($department); ?></td>
                                                <td>
                                                    <a href="?deleteDepartment=<?= urlencode($department); ?>"
                                                       class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-confirm"
                                                       data-title="Delete Department"
                                                       data-message="Are you sure you want to delete this department?"
                                                       title="Delete">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                            <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center text-muted">
                                                    No departments added yet.
                                                </td>
                                            </tr>
                                            <?php endif; ?>

                                        </tbody>

                                    </table>
                                </div>

                            </div>

                        </div>
                    </div>
                    
                    <!-- Deduction Type Management -->
                    <div class="col-xl-4 col-lg-4">
                        <div class="card custom-card h-100">
                    
                            <div class="card-header">
                                <h5 class="mb-0">Deduction Type Management</h5>
                            </div>
                    
                            <div class="card-body">
                    
                                <?php if ($deductionMessage !== ''): ?>
                                <div class="alert alert-info">
                                    <?= htmlspecialchars($deductionMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                    
                                <p class="text-muted mb-4">
                                    Add deduction types for payroll deductions.
                                </p>
                    
                                <form method="post" class="mb-4">
                                    <div class="input-group">
                    
                                        <input type="text"
                                            class="form-control"
                                            name="deduction_type"
                                            placeholder="PF, ESI, Late Fine"
                                            required>
                    
                                        <button type="submit"
                                            name="addDeductionType"
                                            class="btn btn-primary">
                                            Add Type
                                        </button>
                    
                                    </div>
                                </form>
                    
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                    
                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Deduction Type</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>
                    
                                        <tbody>
                    
                                            <?php if (!empty($deductionTypes)): ?>
                                                <?php foreach ($deductionTypes as $index => $type): ?>
                                                <tr>
                                                    <td><?= $index + 1; ?></td>
                                                    <td><?= htmlspecialchars($type); ?></td>
                                                    <td>
                                                        <a href="?deleteDeductionType=<?= urlencode($type); ?>"
                                                           class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-confirm"
                                                           data-title="Delete Deduction Type"
                                                           data-message="Are you sure you want to delete this deduction type?"
                                                           title="Delete">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">
                                                        No deduction types added yet.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                    
                                        </tbody>
                    
                                    </table>
                                </div>
                    
                            </div>
                        </div>
                    </div>
                    
                    <!-- Expense Type Management -->
                    <div class="col-xl-4 col-lg-4">
                        <div class="card custom-card h-100">
                    
                            <div class="card-header">
                                <h5 class="mb-0">Expense Type Management</h5>
                            </div>
                    
                            <div class="card-body">
                    
                                <?php if ($expenseMessage !== ''): ?>
                                <div class="alert alert-info">
                                    <?= htmlspecialchars($expenseMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>
                    
                                <p class="text-muted mb-4">
                                    Add expense types for expense entries.
                                </p>
                    
                                <form method="post" class="mb-4">
                                    <div class="input-group">
                    
                                        <input type="text"
                                            class="form-control"
                                            name="expense_type"
                                            placeholder="Travel, Food, Petrol"
                                            required>
                    
                                        <button type="submit"
                                            name="addExpenseType"
                                            class="btn btn-primary">
                                            Add Type
                                        </button>
                    
                                    </div>
                                </form>
                    
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle mb-0">
                    
                                        <thead>
                                            <tr>
                                                <th width="60">#</th>
                                                <th>Expense Type</th>
                                                <th width="100">Action</th>
                                            </tr>
                                        </thead>
                    
                                        <tbody>
                    
                                            <?php if (!empty($expenseTypes)): ?>
                                                <?php foreach ($expenseTypes as $index => $type): ?>
                                                <tr>
                                                    <td><?= $index + 1; ?></td>
                                                    <td><?= htmlspecialchars($type); ?></td>
                                                    <td>
                                                        <a href="?deleteExpenseType=<?= urlencode($type); ?>"
                                                           class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-confirm"
                                                           data-title="Delete Expense Type"
                                                           data-message="Are you sure you want to delete this expense type?"
                                                           title="Delete">
                                                            <i class="ri-delete-bin-line"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="3" class="text-center text-muted">
                                                        No expense types added yet.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                    
                                        </tbody>
                    
                                    </table>
                                </div>
                    
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Conditions Setup -->
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-0">Overtime Setup</h5>
                            </div>
                            <div class="card-body">
                                <form id="overtimeForm">
                                    <input type="hidden" id="overtimeSettingId" name="id" value="">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">OT Type</label>
                                            <select class="form-select" name="otType" id="otType" required>
                                                <option value="">Select OT Type</option>
                                                <option value="daily">Daily</option>
                                                <option value="weekly">Weekly</option>
                                                <option value="holiday">Holiday</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Min Hours Required</label>
                                            <input type="number" step="0.01" class="form-control" name="minHoursRequired" id="minHoursRequired" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Max Hours Per Day</label>
                                            <input type="number" step="0.01" class="form-control" name="maxHoursPerDay" id="maxHoursPerDay" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Rate Type</label>
                                            <select class="form-select" name="rateType" id="rateType" required>
                                                <option value="">Select Rate Type</option>
                                                <option value="fixed">Fixed</option>
                                                <option value="multiplier">Multiplier</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Rate Value</label>
                                            <input type="number" step="0.01" class="form-control" name="rateValue" id="rateValue" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Rounding Rule</label>
                                            <select class="form-select" name="roundingRule" id="roundingRule" required>
                                                <option value="">Select Rounding Rule</option>
                                                <option value="15min">15min</option>
                                                <option value="30min">30min</option>
                                                <option value="exact">Exact</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Effective From</label>
                                            <input type="date" class="form-control" name="effectiveFrom" id="effectiveFrom" value="<?= date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-md-8 d-flex align-items-end gap-4 flex-wrap">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="1" id="autoApprove" name="autoApprove">
                                                <label class="form-check-label" for="autoApprove">Auto Approve</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="1" id="requiresManagerApproval" name="requiresManagerApproval" checked>
                                                <label class="form-check-label" for="requiresManagerApproval">Requires Manager Approval</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" value="1" id="requiresHrApproval" name="requiresHrApproval">
                                                <label class="form-check-label" for="requiresHrApproval">Requires HR Approval</label>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary mt-4">Save Overtime Settings</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Terms & Conditions Setup -->
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <h5 class="mb-0">Terms & Conditions Content</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($termsMessage !== ''): ?>
                                <div class="alert alert-info">
                                    <?= htmlspecialchars($termsMessage, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                                <?php endif; ?>

                                <p class="text-muted mb-3">
                                    Update the Terms & Conditions page content using the same editor style as Setup
                                    page.
                                </p>

                                <?php if ($termsLastUpdated !== ''): ?>
                                <p class="text-muted small mb-3">
                                    Last updated:
                                    <?= htmlspecialchars(date('F d, Y h:i A', strtotime($termsLastUpdated)), ENT_QUOTES, 'UTF-8'); ?>
                                </p>
                                <?php endif; ?>

                                <form method="post" id="termsEditorForm">
                                    <input type="hidden" name="terms_conditions_html" id="terms_conditions_html">

                                    <div id="terms-quill-editor" data-ui-editor="quill"
                                        data-placeholder="Write Terms & Conditions...">
                                        <?= $termsHtml; ?>
                                    </div>

                                    <button type="submit" name="saveTermsConditions" class="btn btn-primary mt-3">
                                        Save Terms & Conditions
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="editDepartmentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Department</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editDepartmentForm">
                <div class="modal-body">
                    <input type="hidden" id="editDepartmentOldName">
                    <label class="form-label">Department Name</label>
                    <input type="text" class="form-control" id="editDepartmentName" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
#termsEditorForm {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

#terms-quill-editor {
    min-height: 220px !important;
    max-height: 55vh !important;
    overflow-y: auto !important;
    border-radius: 6px;
}

#terms-quill-editor .ql-container {
    height: auto !important;
    min-height: 220px !important;
    max-height: 55vh !important;
    overflow-y: auto !important;
}

#terms-quill-editor .ql-editor {
    min-height: 220px !important;
    max-height: none !important;
    overflow-wrap: anywhere !important;
    word-break: break-word !important;
}


Swal.fire({
    title: 'Are you sure?',
    text: message,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!',
    cancelButtonText: 'No',
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6'
});
</style>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<script>
document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.delete-confirm').forEach(function(btn) {

        btn.addEventListener('click', function(e) {

            e.preventDefault();

            let url = this.getAttribute('href');
            let message = this.dataset.message || 'Are you sure?';

            Swal.fire({
                title: 'Confirm Delete',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true
            }).then((result) => {

                if (result.isConfirmed) {
                    window.location.href = url;
                }

            });

        });

    });

});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var form = document.getElementById('termsEditorForm');
    var hiddenInput = document.getElementById('terms_conditions_html');
    var editorHost = document.getElementById('terms-quill-editor');

    if (!form || !hiddenInput || !editorHost) {
        return;
    }

    function getEditorRoot() {
        return editorHost.querySelector('.ql-editor') || editorHost;
    }

    function applyEditorSizing() {
        var root = getEditorRoot();
        if (!root) return;

        // Auto grow up to max height, then keep internal scroll.
        var maxPx = Math.max(320, Math.floor(window.innerHeight * 0.55));

        root.style.height = 'auto';
        root.style.minHeight = '220px';
        root.style.maxHeight = maxPx + 'px';
        root.style.overflowY = 'auto';
        root.style.overflowWrap = 'anywhere';
        root.style.wordBreak = 'break-word';

        var needed = root.scrollHeight;
        root.style.height = Math.min(needed, maxPx) + 'px';

        var container = editorHost.querySelector('.ql-container');
        if (container) {
            container.style.height = 'auto';
            container.style.maxHeight = maxPx + 'px';
            container.style.overflowY = 'auto';
        }
    }

    applyEditorSizing();
    window.addEventListener('resize', applyEditorSizing);
    editorHost.addEventListener('input', applyEditorSizing, true);
    setTimeout(applyEditorSizing, 150);

    form.addEventListener('submit', function() {
        var editorRoot = editorHost.querySelector('.ql-editor');
        if (editorRoot) {
            hiddenInput.value = editorRoot.innerHTML.trim();
        } else {
            hiddenInput.value = editorHost.innerHTML.trim();
        }
    });
});

$(function() {
    function populateOvertimeForm(data) {
        if (!data) {
            return;
        }

        $('#overtimeSettingId').val(data.id || '');
        $('#otType').val(data.otType || '');
        $('#minHoursRequired').val(data.minHoursRequired || '');
        $('#maxHoursPerDay').val(data.maxHoursPerDay || '');
        $('#rateType').val(data.rateType || '');
        $('#rateValue').val(data.rateValue || '');
        $('#roundingRule').val(data.roundingRule || '');
        $('#effectiveFrom').val(data.effectiveFrom || '<?= date('Y-m-d'); ?>');
        $('#autoApprove').prop('checked', String(data.autoApprove) === '1');
        $('#requiresManagerApproval').prop('checked', String(data.requiresManagerApproval) === '1');
        $('#requiresHrApproval').prop('checked', String(data.requiresHrApproval) === '1');
    }

    $.ajax({
        url: '<?= BASE_URL ?>/app/controllers/OvertimeSetupController.php',
        method: 'POST',
        dataType: 'json',
        data: { action: 'getSettings' },
        success: function(res) {
            if (res && res.success) {
                populateOvertimeForm(res.data);
            }
        }
    });

    $('#overtimeForm').on('submit', function(e) {
        e.preventDefault();

        var formData = {
            action: 'saveSettings',
            id: $('#overtimeSettingId').val(),
            otType: $('#otType').val(),
            minHoursRequired: $('#minHoursRequired').val(),
            maxHoursPerDay: $('#maxHoursPerDay').val(),
            rateType: $('#rateType').val(),
            rateValue: $('#rateValue').val(),
            roundingRule: $('#roundingRule').val(),
            effectiveFrom: $('#effectiveFrom').val(),
            autoApprove: $('#autoApprove').is(':checked') ? 1 : 0,
            requiresManagerApproval: $('#requiresManagerApproval').is(':checked') ? 1 : 0,
            requiresHrApproval: $('#requiresHrApproval').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: '<?= BASE_URL ?>/app/controllers/OvertimeSetupController.php',
            method: 'POST',
            dataType: 'json',
            data: formData,
            success: function(res) {
                if (res && res.success) {
                    showToast(res.message, 'success');
                    $('#overtimeSettingId').val(''); // reset id (optional)
                } else {
                    showToast((res && res.message) ? res.message : 'Unable to save settings.', 'error');
                }
            },
            error: function() {
                showToast('Something went wrong while saving overtime settings.', 'error');
            }
        });
    });
});


</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
