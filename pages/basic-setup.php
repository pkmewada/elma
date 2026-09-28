<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/basic-config.php';
require_once __DIR__ . '/../includes/Csrf.php';

$errorMessage = '';
$successMessage = '';
$roleMessage = '';
$departmentMessage = '';


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
// GET delete links carry the session CSRF token (validated here; routes.php
// only checks POST).
$deleteLinkTokenValid = validateCsrfToken(is_string($_GET['csrfToken'] ?? null) ? $_GET['csrfToken'] : null);

if (isset($_GET['deleteRole']) && $deleteLinkTokenValid) {

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
if (isset($_GET['deleteDepartment']) && $deleteLinkTokenValid) {

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

                                <form method="post"><?php require_once __DIR__ . '/../includes/Csrf.php'; echo getCsrfInput(); ?>

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

                                <form method="post" class="mb-4"><?php require_once __DIR__ . '/../includes/Csrf.php'; echo getCsrfInput(); ?>

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
                                                    <a href="?deleteRole=<?= urlencode($role); ?>&amp;csrfToken=<?= urlencode(generateCsrfToken()); ?>"
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

                                <form method="post" class="mb-4"><?php require_once __DIR__ . '/../includes/Csrf.php'; echo getCsrfInput(); ?>
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
                                                    <a href="?deleteDepartment=<?= urlencode($department); ?>&amp;csrfToken=<?= urlencode(generateCsrfToken()); ?>"
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
