<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/employeePointEngine.php';
require_once __DIR__ . '/../../includes/mailer.php';

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
    // AUTH
    // =========================================

    $userId = $_SESSION['userId'] ?? 0;

    if (!$userId) {
        respond(false, 'Unauthorized access');
    }

    // =========================================
    // LOAD ENGINE
    // =========================================

    $pointEngine = new EmployeePointEngine($con);

    // =========================================
    // INPUTS
    // =========================================

    $employeeId = (int)($_POST['employeeId'] ?? 0);

    $categoryId = (int)($_POST['categoryId'] ?? 0);

    $points = (float)($_POST['points'] ?? 0);

    $remarks = trim($_POST['remarks'] ?? '');

    // =========================================
    // VALIDATION
    // =========================================

    $validation = $pointEngine->validateTransaction([
        'employeeId' => $employeeId,
        'categoryId' => $categoryId,
        'points' => $points
    ]);

    if (!$validation['success']) {
        respond(false, $validation['message']);
    }

    // =========================================
    // CATEGORY
    // =========================================

    $category = $validation['category'];

    $transactionType = $category['transactionType'];

    // =========================================
    // APPROVAL STATUS
    // =========================================

    $approvalStatus = 'Approved';

    // =========================================
    // TRANSACTION DATE
    // =========================================

    $transactionDate = date('Y-m-d');

    // =========================================
    // START DB TRANSACTION
    // =========================================

    mysqli_begin_transaction($con);

    // =========================================
    // INSERT TRANSACTION
    // =========================================

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO employeePointTransactions
        (
            employeeId,
            categoryId,
            transactionType,
            points,
            remarks,
            transactionDate,
            approvalStatus,
            createdBy
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, ?
        )"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "iisdsssi",
        $employeeId,
        $categoryId,
        $transactionType,
        $points,
        $remarks,
        $transactionDate,
        $approvalStatus,
        $userId
    );

    $execute = mysqli_stmt_execute($stmt);

    if (!$execute) {
        throw new Exception('Failed to save transaction');
    }

    $transactionId = mysqli_insert_id($con);

    mysqli_stmt_close($stmt);

    // =========================================
    // UPDATED BALANCE
    // =========================================

    $balance = $pointEngine->getEmployeePointBalance(
        $employeeId
    );

    // =========================================
    // THRESHOLD CHECK
    // =========================================

    $thresholds = $pointEngine->checkThresholds(
        $employeeId
    );
    
    // =========================================
    // COMMIT
    // =========================================

    mysqli_commit($con);
    
    // =========================================
    // SEND MAIL
    // =========================================
    
    $employeeStmt = mysqli_prepare(
        $con,
        "SELECT fullName, emailAddress
         FROM employeeusers
         WHERE id = ?
         LIMIT 1"
    );
    
    mysqli_stmt_bind_param(
        $employeeStmt,
        "i",
        $employeeId
    );
    
    mysqli_stmt_execute($employeeStmt);
    
    $employeeResult = mysqli_stmt_get_result($employeeStmt);
    
    $employee = mysqli_fetch_assoc($employeeResult);
    
    mysqli_stmt_close($employeeStmt);
    
    if (
        $employee &&
        !empty($employee['emailAddress'])
    ) {
    
        sendPointTransactionEmail(
    
            $transactionId,
    
            $employee['emailAddress'],
    
            $employee['fullName'],
    
            $transactionType,
    
            $category['categoryName'],
    
            $points,
    
            $remarks
        );
    }

    

    // =========================================
    // RESPONSE
    // =========================================

    respond(
        true,
        'Point transaction saved successfully',
        [
            'transactionId' => $transactionId,
            'balance' => $balance,
            'thresholds' => $thresholds
        ]
    );

} catch (Exception $e) {

    mysqli_rollback($con);

    respond(false, $e->getMessage());
}