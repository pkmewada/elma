<?php

include __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

header('Content-Type: application/json');

requireApiPermission('/permission-setup', 'canView');

$roleName = trim((string)($_POST['roleName'] ?? ''));
$moduleName = trim((string)($_POST['moduleName'] ?? ''));
$layoutType = trim((string)($_POST['layoutType'] ?? ''));

if (!in_array($layoutType, ['admin', 'employee'], true)) {
    $layoutType = '';
}

if ($roleName === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Role is required.'
    ]);
    exit;
}

$routes = [];

$routeSql = "
    SELECT
        id,
        routePath,
        routeTitle,
        moduleName,
        sortOrder
    FROM routesMaster
    WHERE isActive = 1
    AND isPublic = 0
";
$routeBindTypes = '';
$routeBindValues = [];

if ($layoutType !== '') {
    $routeSql .= ' AND layoutType = ?';
    $routeBindTypes .= 's';
    $routeBindValues[] = $layoutType;
}

if ($moduleName !== '') {
    if ($moduleName === 'Other') {
        $routeSql .= " AND (moduleName IS NULL OR TRIM(moduleName) = '')";
    } else {
        $routeSql .= " AND TRIM(moduleName) = ?";
        $routeBindTypes .= 's';
        $routeBindValues[] = $moduleName;
    }
}

$routeSql .= " ORDER BY moduleName ASC, sortOrder ASC, routeTitle ASC";

$routeStmt = $con->prepare($routeSql);

if ($routeBindTypes !== '') {
    $routeStmt->bind_param($routeBindTypes, ...$routeBindValues);
}

$routeStmt->execute();
$routeQuery = $routeStmt->get_result();

while ($row = mysqli_fetch_assoc($routeQuery)) {
    $routes[] = $row;
}

$rolePermissions = [];

$stmt = $con->prepare("
    SELECT
        routeId,
        canView,
        canAdd,
        canEdit,
        canDelete,
        canApprove
    FROM rolePermissions
    WHERE roleName = ?
");

$stmt->bind_param("s", $roleName);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $rolePermissions[(int)$row['routeId']] = $row;
}

$routeActions = [];
$actionSql = "
    SELECT
        pa.id,
        pa.routeId,
        pa.actionKey,
        pa.actionLabel,
        COALESCE(rap.canAccess, 0) AS canAccess
    FROM permissionActions pa
    INNER JOIN routesMaster rm ON rm.id = pa.routeId
    LEFT JOIN roleActionPermissions rap
        ON rap.actionId = pa.id
        AND rap.roleName = ?
    WHERE pa.isActive = 1
    AND pa.permissionType = 'special'
    AND rm.isActive = 1
    AND rm.isPublic = 0
";
$actionBindTypes = 's';
$actionBindValues = [$roleName];

if ($layoutType !== '') {
    $actionSql .= ' AND rm.layoutType = ?';
    $actionBindTypes .= 's';
    $actionBindValues[] = $layoutType;
}

if ($moduleName !== '') {
    if ($moduleName === 'Other') {
        $actionSql .= " AND (rm.moduleName IS NULL OR TRIM(rm.moduleName) = '')";
    } else {
        $actionSql .= " AND TRIM(rm.moduleName) = ?";
        $actionBindTypes .= 's';
        $actionBindValues[] = $moduleName;
    }
}

$actionSql .= ' ORDER BY pa.sortOrder ASC, pa.actionLabel ASC';
$actionStmt = $con->prepare($actionSql);
$actionStmt->bind_param($actionBindTypes, ...$actionBindValues);

$actionStmt->execute();
$actionResult = $actionStmt->get_result();

while ($action = $actionResult->fetch_assoc()) {
    $routeActions[(int)$action['routeId']][] = $action;
}

$actionStmt->close();

ob_start();
?>

<form method="post">
    <input type="hidden" name="roleName" value="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="permission-table-wrap">
        <table class="table table-hover align-middle mb-0 permission-table">
            <thead>
                <tr>
                    <th>Page</th>
                    <th class="text-center">All</th>
                    <th class="text-center">View</th>
                    <th class="text-center">Add</th>
                    <th class="text-center">Edit</th>
                    <th class="text-center">Delete</th>
                    <th class="text-center">Approve</th>
                </tr>
            </thead>

            <tbody>
                <?php $currentModule = null; ?>
                <?php foreach ($routes as $route): ?>
                    <?php
                        $routeId = (int)$route['id'];
                        $moduleName = trim((string)($route['moduleName'] ?? ''));
                        $moduleLabel = $moduleName !== '' ? $moduleName : 'Other';
                        $permission = $rolePermissions[$routeId] ?? [];

                        $canView = !empty($permission['canView']);
                        $canAdd = !empty($permission['canAdd']);
                        $canEdit = !empty($permission['canEdit']);
                        $canDelete = !empty($permission['canDelete']);
                        $canApprove = !empty($permission['canApprove']);
                        $specialActions = $routeActions[$routeId] ?? [];

                        $allChecked = $canView && $canAdd && $canEdit && $canDelete && $canApprove;
                    ?>

                    <?php if ($currentModule !== $moduleLabel): ?>
                        <?php $currentModule = $moduleLabel; ?>

                        <tr class="permission-module-row">
                            <td colspan="7">
                                <?= htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </td>
                        </tr>
                    <?php endif; ?>

                    <tr>
                        <td>
                            <input type="hidden" name="permissions[<?= $routeId; ?>][routeId]" value="<?= $routeId; ?>">

                            <div class="fw-semibold">
                                <?= htmlspecialchars($route['routeTitle'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                            <div class="text-muted small">
                                <?= htmlspecialchars($route['moduleName'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                                ·
                                <?= htmlspecialchars($route['routePath'], ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        </td>

                        <td class="text-center">
                            <input
                                type="checkbox"
                                class="form-check-input row-permission-check"
                                title="Select all permissions for this page"
                                <?= $allChecked ? 'checked' : ''; ?>
                            >
                        </td>

                        <td class="text-center">
                            <input
                                type="checkbox"
                                class="form-check-input permission-action-check"
                                name="permissions[<?= $routeId; ?>][canView]"
                                value="1"
                                <?= $canView ? 'checked' : ''; ?>
                            >
                        </td>

                        <td class="text-center">
                            <input
                                type="checkbox"
                                class="form-check-input permission-action-check"
                                name="permissions[<?= $routeId; ?>][canAdd]"
                                value="1"
                                <?= $canAdd ? 'checked' : ''; ?>
                            >
                        </td>

                        <td class="text-center">
                            <input
                                type="checkbox"
                                class="form-check-input permission-action-check"
                                name="permissions[<?= $routeId; ?>][canEdit]"
                                value="1"
                                <?= $canEdit ? 'checked' : ''; ?>
                            >
                        </td>

                        <td class="text-center">
                            <input
                                type="checkbox"
                                class="form-check-input permission-action-check"
                                name="permissions[<?= $routeId; ?>][canDelete]"
                                value="1"
                                <?= $canDelete ? 'checked' : ''; ?>
                            >
                        </td>

                        <td class="text-center">
                            <input
                                type="checkbox"
                                class="form-check-input permission-action-check"
                                name="permissions[<?= $routeId; ?>][canApprove]"
                                value="1"
                                <?= $canApprove ? 'checked' : ''; ?>
                            >
                        </td>
                    </tr>

                    <?php if ($specialActions): ?>
                        <tr class="permission-special-action-row">
                            <td colspan="7">
                                <div class="d-flex flex-wrap align-items-center gap-3">
                                    <span class="fw-semibold text-muted small">Special Actions</span>

                                    <?php foreach ($specialActions as $specialAction): ?>
                                        <?php $actionId = (int)$specialAction['id']; ?>
                                        <input
                                            type="hidden"
                                            name="permissionActionIds[]"
                                            value="<?= $actionId; ?>"
                                        >
                                        <label class="d-inline-flex align-items-center gap-2 mb-0">
                                            <input
                                                type="checkbox"
                                                class="form-check-input"
                                                name="actionPermissions[<?= $actionId; ?>]"
                                                value="1"
                                                <?= !empty($specialAction['canAccess']) ? 'checked' : ''; ?>
                                            >
                                            <span>
                                                <?= htmlspecialchars($specialAction['actionLabel'], ENT_QUOTES, 'UTF-8'); ?>
                                            </span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button type="submit" name="saveRolePermissions" class="btn btn-primary mt-3">
        <i class="ri-save-line me-1"></i>
        Save Permissions
    </button>
</form>

<?php

$html = ob_get_clean();

echo json_encode([
    'success' => true,
    'html' => $html
]);
exit;
