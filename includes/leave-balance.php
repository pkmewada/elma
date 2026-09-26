<?php

function getOrCreateBalance(
    $con,
    $employeeId,
    $leaveTypeId
) {

    /*
    |--------------------------------------------------------------------------
    | Existing Balance
    |--------------------------------------------------------------------------
    */

    $stmt = mysqli_prepare(

        $con,

        "

        SELECT *

        FROM leaveBalances

        WHERE

            employeeId = ?
            AND leaveTypeId = ?

        LIMIT 1

        "
    );

    if (!$stmt) {

        return [

            'totalAllocated' => 0,

            'usedLeaves' => 0,

            'remainingLeaves' => 0
        ];
    }

    mysqli_stmt_bind_param(

        $stmt,

        "ii",

        $employeeId,
        $leaveTypeId
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $balance =
        mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    /*
    |--------------------------------------------------------------------------
    | Return Existing
    |--------------------------------------------------------------------------
    */

    if ($balance) {

        return $balance;
    }

    /*
    |--------------------------------------------------------------------------
    | Fetch Leave Type Allocation
    |--------------------------------------------------------------------------
    */

    $typeStmt = mysqli_prepare(

        $con,

        "

        SELECT

            totalLeaves

        FROM leaveTypes

        WHERE id = ?

        LIMIT 1

        "
    );

    if (!$typeStmt) {

        return [

            'totalAllocated' => 0,

            'usedLeaves' => 0,

            'remainingLeaves' => 0
        ];
    }

    mysqli_stmt_bind_param(

        $typeStmt,

        "i",

        $leaveTypeId
    );

    mysqli_stmt_execute($typeStmt);

    $typeResult =
        mysqli_stmt_get_result($typeStmt);

    $type =
        mysqli_fetch_assoc($typeResult);

    mysqli_stmt_close($typeStmt);

    $total =
        (float)(
            $type['totalLeaves'] ?? 0
        );

    /*
    |--------------------------------------------------------------------------
    | Create Initial Balance
    |--------------------------------------------------------------------------
    */

    $insertStmt = mysqli_prepare(

        $con,

        "

        INSERT INTO leaveBalances (

            employeeId,
            leaveTypeId,

            totalAllocated,
            usedLeaves,
            remainingLeaves

        ) VALUES (

            ?, ?, ?, 0, ?

        )

        "
    );

    if (!$insertStmt) {

        return [

            'totalAllocated' => 0,

            'usedLeaves' => 0,

            'remainingLeaves' => 0
        ];
    }

    mysqli_stmt_bind_param(

        $insertStmt,

        "iidd",

        $employeeId,
        $leaveTypeId,

        $total,
        $total
    );

    mysqli_stmt_execute($insertStmt);

    mysqli_stmt_close($insertStmt);

    /*
    |--------------------------------------------------------------------------
    | Return New Balance
    |--------------------------------------------------------------------------
    */

    return [

        'totalAllocated' => $total,

        'usedLeaves' => 0,

        'remainingLeaves' => $total
    ];
}