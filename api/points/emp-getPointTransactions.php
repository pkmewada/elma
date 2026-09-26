<?php

require_once __DIR__ . '/../../includes/emp-auth.php';

require_once __DIR__ . '/../../includes/db.php';

require_once __DIR__ . '/../../includes/employeePointEngine.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function respond(
    $success,
    $message = '',
    $data = []
) {

    echo json_encode([

        'success' => $success,

        'message' => $message,

        'data' => $data

    ]);

    exit;
}

try {

    // =========================================
    // LOGGED EMPLOYEE
    // =========================================

    $employeeId =
        (int)($_SESSION['candidateId'] ?? 0);

    if ($employeeId <= 0) {

        respond(
            false,
            'Invalid employee session'
        );
    }

    // =========================================
    // LOAD TRANSACTIONS
    // =========================================

    $transactions = [];

    $sql = "

        SELECT

            t.id,
            t.employeeId,
            t.categoryId,
            t.transactionType,
            t.points,
            t.remarks,
            t.transactionDate,
            t.approvalStatus,
            t.createdAt,

            c.categoryName,
            c.categoryCode,

            e.fullName

        FROM employeePointTransactions t

        LEFT JOIN employeePointCategories c
            ON c.id = t.categoryId

        LEFT JOIN employeeusers e
            ON e.id = t.employeeId

        WHERE

            t.employeeId = ?
            AND t.isReverted = 0

        ORDER BY t.id DESC

    ";

    // =========================================
    // PREPARE
    // =========================================

    $stmt = mysqli_prepare(
        $con,
        $sql
    );

    if (!$stmt) {

        throw new Exception(
            'Failed to prepare query'
        );
    }

    // =========================================
    // BIND
    // =========================================

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $employeeId
    );

    // =========================================
    // EXECUTE
    // =========================================

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    // =========================================
    // BUILD RESPONSE
    // =========================================

    while (
        $row = mysqli_fetch_assoc($result)
    ) {

        $transactions[] = [

            'id' =>
                (int)$row['id'],

            'employeeId' =>
                (int)$row['employeeId'],

            'employeeName' =>
                $row['fullName'] ?? '',

            'categoryId' =>
                (int)$row['categoryId'],

            'categoryName' =>
                $row['categoryName'] ?? '',

            'categoryCode' =>
                $row['categoryCode'] ?? '',

            'transactionType' =>
                $row['transactionType'] ?? '',

            'points' =>
                (float)$row['points'],

            'remarks' =>
                $row['remarks'] ?? '',

            'transactionDate' =>

                !empty($row['transactionDate'])

                    ? date(
                        'd M Y',
                        strtotime(
                            $row['transactionDate']
                        )
                    )

                    : '--',

            'approvalStatus' =>
                $row['approvalStatus'] ?? 'Pending',

            'createdAt' =>

                !empty($row['createdAt'])

                    ? date(
                        'd M Y',
                        strtotime(
                            $row['createdAt']
                        )
                    )

                    : '--'
        ];
    }

    mysqli_stmt_close($stmt);

    // =========================================
    // POINT BALANCE
    // =========================================

    $pointEngine =
        new EmployeePointEngine($con);

    $balance =
        $pointEngine->getEmployeePointBalance(
            $employeeId
        );

    // =========================================
    // RESPONSE
    // =========================================

    respond(

        true,

        'Transactions loaded successfully',

        [

            'balance' =>
                $balance,

            'transactions' =>
                $transactions
        ]
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}