<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

requireApiPermission('/permission-setup', 'canEdit');

$employeeId = (int)($_POST['employeeId'] ?? 0);
$permissionRoutes = $_POST['permissionRoutes'] ?? [];
$permissions = $_POST['permissions'] ?? [];
$permissionActionIds = $_POST['permissionActionIds'] ?? [];
$actionPermissions = $_POST['actionPermissions'] ?? [];

if ($employeeId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee ID.']);
    exit;
}

if (!is_array($permissionRoutes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid route data.']);
    exit;
}

if (!is_array($permissions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid permission data.']);
    exit;
}

if (!is_array($permissionActionIds) || !is_array($actionPermissions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid button permission data.']);
    exit;
}

// Role defaults are compared so an exception identical to the role is not
// stored: the employee then keeps following future role changes.
$roleStmt = $con->prepare("SELECT TRIM(designationName) AS roleName FROM employeeusers WHERE id = ? LIMIT 1");
$roleStmt->bind_param('i', $employeeId);
$roleStmt->execute();
$employeeRoleName = (string)($roleStmt->get_result()->fetch_assoc()['roleName'] ?? '');
$roleStmt->close();

$matchedRoleDefaults = 0;

mysqli_begin_transaction($con);

try {
    foreach ($permissionRoutes as $routeId) {
        $routeId = (int)$routeId;

        if ($routeId <= 0) {
            continue;
        }

        $perm = $permissions[$routeId] ?? [];
        $hasOverride = is_array($perm) && isset($perm['override']) && (int)$perm['override'] === 1;

        if ($hasOverride) {
            $roleDefaultStmt = $con->prepare("
                SELECT canView, canAdd, canEdit, canDelete, canApprove
                FROM rolePermissions
                WHERE roleName = ?
                AND routeId = ?
                LIMIT 1
            ");
            $roleDefaultStmt->bind_param('si', $employeeRoleName, $routeId);
            $roleDefaultStmt->execute();
            $roleDefault = $roleDefaultStmt->get_result()->fetch_assoc() ?: [];
            $roleDefaultStmt->close();

            $sameAsRole = true;

            foreach (['canView', 'canAdd', 'canEdit', 'canDelete', 'canApprove'] as $flag) {
                if ((isset($perm[$flag]) ? 1 : 0) !== (int)($roleDefault[$flag] ?? 0)) {
                    $sameAsRole = false;
                    break;
                }
            }

            if ($sameAsRole) {
                $hasOverride = false;
                $matchedRoleDefaults++;
            }
        }

        if (!$hasOverride) {
            $deleteStmt = mysqli_prepare($con, "
                DELETE FROM userPermissionOverrides
                WHERE userId = ?
                AND routeId = ?
            ");

            if (!$deleteStmt) {
                throw new Exception('Delete statement prepare failed.');
            }

            mysqli_stmt_bind_param($deleteStmt, 'ii', $employeeId, $routeId);
            mysqli_stmt_execute($deleteStmt);
            mysqli_stmt_close($deleteStmt);

            continue;
        }

        $canView = isset($perm['canView']) ? 1 : 0;
        $canAdd = isset($perm['canAdd']) ? 1 : 0;
        $canEdit = isset($perm['canEdit']) ? 1 : 0;
        $canDelete = isset($perm['canDelete']) ? 1 : 0;
        $canApprove = isset($perm['canApprove']) ? 1 : 0;

        $stmt = mysqli_prepare($con, "
            INSERT INTO userPermissionOverrides
                (
                    userId,
                    routeId,
                    overrideType,
                    canView,
                    canAdd,
                    canEdit,
                    canDelete,
                    canApprove,
                    canExport,
                    createdAt,
                    updatedAt
                )
            VALUES
                (?, ?, 'grant', ?, ?, ?, ?, ?, 0, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                overrideType = 'grant',
                canView = VALUES(canView),
                canAdd = VALUES(canAdd),
                canEdit = VALUES(canEdit),
                canDelete = VALUES(canDelete),
                canApprove = VALUES(canApprove),
                canExport = 0,
                updatedAt = NOW()
        ");

        if (!$stmt) {
            throw new Exception('Insert statement prepare failed.');
        }

        mysqli_stmt_bind_param(
            $stmt,
            'iiiiiii',
            $employeeId,
            $routeId,
            $canView,
            $canAdd,
            $canEdit,
            $canDelete,
            $canApprove
        );

        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    foreach ($permissionActionIds as $actionId) {
        $actionId = (int)$actionId;

        if ($actionId <= 0) {
            continue;
        }

        $actionPermission = $actionPermissions[$actionId] ?? [];
        $hasOverride = is_array($actionPermission)
            && isset($actionPermission['override'])
            && (int)$actionPermission['override'] === 1;

        if ($hasOverride) {
            $roleActionStmt = $con->prepare("
                SELECT canAccess
                FROM roleActionPermissions
                WHERE roleName = ?
                AND actionId = ?
                LIMIT 1
            ");
            $roleActionStmt->bind_param('si', $employeeRoleName, $actionId);
            $roleActionStmt->execute();
            $roleCanAccess = (int)($roleActionStmt->get_result()->fetch_assoc()['canAccess'] ?? 0);
            $roleActionStmt->close();

            if ((isset($actionPermission['canAccess']) ? 1 : 0) === $roleCanAccess) {
                $hasOverride = false;
                $matchedRoleDefaults++;
            }
        }

        if (!$hasOverride) {
            $deleteStmt = $con->prepare("
                DELETE FROM userActionPermissionOverrides
                WHERE userId = ?
                AND actionId = ?
            ");

            $deleteStmt->bind_param('ii', $employeeId, $actionId);
            $deleteStmt->execute();
            $deleteStmt->close();
            continue;
        }

        $canAccess = isset($actionPermission['canAccess']) ? 1 : 0;
        $stmt = $con->prepare("
            INSERT INTO userActionPermissionOverrides (userId, actionId, canAccess)
            SELECT ?, id, ?
            FROM permissionActions
            WHERE id = ?
            AND isActive = 1
            AND permissionType = 'special'
            ON DUPLICATE KEY UPDATE
                canAccess = VALUES(canAccess)
        ");

        $stmt->bind_param('iii', $employeeId, $canAccess, $actionId);
        $stmt->execute();
        $stmt->close();
    }

    mysqli_commit($con);

    $message = 'Employee permissions updated successfully.';

    if ($matchedRoleDefaults > 0) {
        $message .= ' ' . $matchedRoleDefaults
            . ' custom setting(s) matched the role default and now follow the role.';
    }

    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (Throwable $e) {
    mysqli_rollback($con);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save permissions.'
    ]);
}
