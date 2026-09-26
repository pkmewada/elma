<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/employeePointEngine.php';

header('Content-Type: application/json');

function respond($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

try {

    // =========================================
    // FILTERS
    // =========================================

    $employeeId = (int)($_GET['employeeId'] ?? 0);

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

            e.fullName,
            e.accountStatus
        FROM employeePointTransactions t

        LEFT JOIN employeePointCategories c
            ON c.id = t.categoryId

        LEFT JOIN employeeusers e
            ON e.id = t.employeeId

        WHERE t.isReverted = 0 AND e.accountStatus = 'Active'
    ";

    // OPTIONAL EMPLOYEE FILTER
    if ($employeeId) {
        $sql .= " AND t.employeeId = ?";
    }

    $sql .= " ORDER BY t.id DESC";

    // =========================================
    // PREPARE QUERY
    // =========================================

    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {
        throw new Exception('Failed to prepare query');
    }

    // =========================================
    // BIND FILTER
    // =========================================

    if ($employeeId) {

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeId
        );
    }

    // =========================================
    // EXECUTE
    // =========================================

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    // =========================================
    // BUILD RESPONSE
    // =========================================

    while ($row = mysqli_fetch_assoc($result)) {

        $transactions[] = [

            'id' => (int)$row['id'],

            'employeeId' => (int)$row['employeeId'],

            'employeeName' => $row['fullName'] ?? '',

            'categoryId' => (int)$row['categoryId'],

            'categoryName' => $row['categoryName'],

            'categoryCode' => $row['categoryCode'],

            'transactionType' => $row['transactionType'],

            'points' => (float)$row['points'],

            'remarks' => $row['remarks'],

            'transactionDate' => $row['transactionDate'],

            'approvalStatus' => $row['approvalStatus'],

            'createdAt' => $row['createdAt']
        ];
    }

    mysqli_stmt_close($stmt);

    // =========================================
    // POINT BALANCE
    // =========================================

    $balance = [];

    if ($employeeId) {

        $pointEngine = new EmployeePointEngine($con);

        $balance = $pointEngine->getEmployeePointBalance(
            $employeeId
        );
    }

    // =========================================
    // RESPONSE
    // =========================================

    respond(
        true,
        'Transactions loaded successfully',
        [
            'balance' => $balance,
            'transactions' => $transactions
        ]
    );

} catch (Exception $e) {

    respond(false, $e->getMessage());
}