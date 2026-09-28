<?php

/**
 * Current actor for audit fields. Admin (users.id) and employee
 * (employeeusers.id) ids overlap, so the type is always stored with the id.
 * No session (CLI, cron, future webhooks) = system.
 *
 * @return array{type: string, id: int}
 */
function getCurrentActor(): array
{
    $authUserType = (string)($_SESSION['authUserType'] ?? '');

    if ($authUserType === 'employee' && !empty($_SESSION['candidateId'])) {
        return ['type' => 'employee', 'id' => (int)$_SESSION['candidateId']];
    }

    if (!empty($_SESSION['userId'])) {
        return ['type' => 'admin', 'id' => (int)$_SESSION['userId']];
    }

    if (!empty($_SESSION['candidateId'])) {
        return ['type' => 'employee', 'id' => (int)$_SESSION['candidateId']];
    }

    return ['type' => 'system', 'id' => 0];
}

/**
 * SQL expression for an actor's display name. Needs LEFT JOINs:
 *   users <adminAlias> ON <adminAlias>.id = <idColumn> AND <typeColumn> = 'admin'
 *   employeeusers <empAlias> ON <empAlias>.id = <idColumn> AND <typeColumn> = 'employee'
 */
function actorNameSql(string $typeColumn, string $adminAlias, string $employeeAlias): string
{
    return "CASE {$typeColumn}
        WHEN 'admin' THEN CONCAT('Admin: ', COALESCE({$adminAlias}.fullName, 'Admin'))
        WHEN 'employee' THEN COALESCE({$employeeAlias}.fullName, 'Employee')
        WHEN 'system' THEN 'System'
        ELSE 'Unknown' END";
}

function saveActivityLog(
    $con,
    $module,
    $recordId,
    $action,
    $description,
    $oldData = null,
    $newData = null
) {
    $actor = getCurrentActor();
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO leadsActivityLogs
            (moduleName, recordId, actionType, description, oldData, newData, createdBy, actorType, ipAddress)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $oldJson = $oldData ? json_encode($oldData) : null;
    $newJson = $newData ? json_encode($newData) : null;

    mysqli_stmt_bind_param(
        $stmt,
        'sissssiss',
        $module,
        $recordId,
        $action,
        $description,
        $oldJson,
        $newJson,
        $actor['id'],
        $actor['type'],
        $ip
    );

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
