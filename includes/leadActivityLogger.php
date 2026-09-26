<?php


function saveActivityLog(
    $con,
    $module,
    $recordId,
    $action,
    $description,
    $oldData = null,
    $newData = null
){

    $userId =
        $_SESSION['candidateId']
        ?? $_SESSION['userId']
        ?? 0;


    $ip =
        $_SERVER['REMOTE_ADDR'] ?? null;


    $stmt = mysqli_prepare(
        $con,
        "
        INSERT INTO leadsActivityLogs
        (
            moduleName,
            recordId,
            actionType,
            description,
            oldData,
            newData,
            createdBy,
            ipAddress
        )
        VALUES
        (?,?,?,?,?,?,?,?)
        "
    );


    $oldJson =
        $oldData
        ? json_encode($oldData)
        : null;


    $newJson =
        $newData
        ? json_encode($newData)
        : null;


    mysqli_stmt_bind_param(
        $stmt,
        "sissssis",
        $module,
        $recordId,
        $action,
        $description,
        $oldJson,
        $newJson,
        $userId,
        $ip
    );


    mysqli_stmt_execute($stmt);


    mysqli_stmt_close($stmt);

}