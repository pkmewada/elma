<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

requireApiPermission('/permission-setup', 'canView');

$employeeId = (int)($_GET['employeeId'] ?? $_POST['employeeId'] ?? 0);
$selectedModuleName = trim((string)($_GET['moduleName'] ?? $_POST['moduleName'] ?? ''));

if ($employeeId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee ID.'
    ]);
    exit;
}

$employeeStmt = $con->prepare("
    SELECT
        id,
        fullName,
        emailAddress,
        designationName,
        departmentName
    FROM employeeusers
    WHERE id = ?
    LIMIT 1
");

$employeeStmt->bind_param('i', $employeeId);
$employeeStmt->execute();
$employee = $employeeStmt->get_result()->fetch_assoc();
$employeeStmt->close();

if (!$employee) {
    echo json_encode([
        'success' => false,
        'message' => 'Employee not found.'
    ]);
    exit;
}

$roleName = trim((string)$employee['designationName']);
$routeFilterSql = '';
$bindTypes = 'si';
$bindValues = [
    $roleName,
    $employeeId
];

if ($selectedModuleName !== '') {
    if ($selectedModuleName === 'Other') {
        $routeFilterSql = " AND (r.moduleName IS NULL OR TRIM(r.moduleName) = '')";
    } else {
        $routeFilterSql = " AND TRIM(r.moduleName) = ?";
        $bindTypes .= 's';
        $bindValues[] = $selectedModuleName;
    }
}

$sql = "
    SELECT
        r.id AS routeId,
        r.routeTitle AS routeName,
        r.routePath,
        r.moduleName,
        r.layoutType,

        COALESCE(rp.canView, 0) AS roleCanView,
        COALESCE(rp.canAdd, 0) AS roleCanAdd,
        COALESCE(rp.canEdit, 0) AS roleCanEdit,
        COALESCE(rp.canDelete, 0) AS roleCanDelete,
        COALESCE(rp.canApprove, 0) AS roleCanApprove,

        uo.overrideType,
        uo.canView AS userCanView,
        uo.canAdd AS userCanAdd,
        uo.canEdit AS userCanEdit,
        uo.canDelete AS userCanDelete,
        uo.canApprove AS userCanApprove

    FROM routesMaster r
    LEFT JOIN rolePermissions rp
        ON rp.routeId = r.id
        AND rp.roleName = ?
    LEFT JOIN userPermissionOverrides uo
        ON uo.routeId = r.id
        AND uo.userId = ?
    WHERE r.isActive = 1
    AND r.isPublic = 0
    -- Employees can only open employee pages; keep any existing exception on
    -- another page visible so it can still be reviewed and switched off.
    AND (r.layoutType = 'employee' OR uo.id IS NOT NULL)
    {$routeFilterSql}
    ORDER BY r.moduleName ASC, r.sortOrder ASC, r.routeTitle ASC
";

$stmt = $con->prepare($sql);
$stmt->bind_param($bindTypes, ...$bindValues);
$stmt->execute();
$result = $stmt->get_result();

$permissions = [];
$actions = ['View', 'Add', 'Edit', 'Delete', 'Approve'];

while ($row = $result->fetch_assoc()) {
    $hasOverride = $row['overrideType'] !== null;

    foreach ($actions as $action) {
        $roleKey = 'roleCan' . $action;
        $userKey = 'userCan' . $action;
        $finalKey = 'finalCan' . $action;

        $row[$finalKey] = $hasOverride
            ? (int)($row[$userKey] ?? 0)
            : (int)($row[$roleKey] ?? 0);
    }

    $row['hasOverride'] = $hasOverride ? 1 : 0;

    $permissions[] = $row;
}

$stmt->close();

$actionSql = "
    SELECT
        pa.id AS actionId,
        pa.routeId,
        pa.actionKey,
        pa.actionLabel,
        COALESCE(rap.canAccess, 0) AS roleCanAccess,
        uap.canAccess AS userCanAccess
    FROM permissionActions pa
    INNER JOIN routesMaster r ON r.id = pa.routeId
    LEFT JOIN roleActionPermissions rap
        ON rap.actionId = pa.id
        AND rap.roleName = ?
    LEFT JOIN userActionPermissionOverrides uap
        ON uap.actionId = pa.id
        AND uap.userId = ?
    WHERE pa.isActive = 1
    AND pa.permissionType = 'special'
    AND r.isActive = 1
    AND r.isPublic = 0
    {$routeFilterSql}
    ORDER BY pa.sortOrder ASC, pa.actionLabel ASC
";

$actionStmt = $con->prepare($actionSql);
$actionStmt->bind_param($bindTypes, ...$bindValues);
$actionStmt->execute();
$actionResult = $actionStmt->get_result();
$actionsByRoute = [];

while ($action = $actionResult->fetch_assoc()) {
    $action['hasOverride'] = $action['userCanAccess'] !== null ? 1 : 0;
    $actionsByRoute[(int)$action['routeId']][] = $action;
}

$actionStmt->close();

foreach ($permissions as &$permission) {
    $routeId = (int)$permission['routeId'];
    $permission['specialActions'] = $actionsByRoute[$routeId] ?? [];
}

unset($permission);

echo json_encode([
    'success' => true,
    'data' => [
        'employee' => $employee,
        'permissions' => $permissions
    ]
]);
