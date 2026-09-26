<?php

class EmployeePointEngine
{
    private $con;

    // =========================================
    // CONSTRUCTOR
    // =========================================
    public function __construct($con)
    {
        $this->con = $con;
    }

    // =========================================
    // GET POINT SETTINGS
    // =========================================
    public function getSettings()
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT * 
             FROM employeePointSettings 
             LIMIT 1"
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $settings = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $settings ?: [];
    }

    // =========================================
    // GET ALL ACTIVE CATEGORIES
    // =========================================
    public function getCategories()
    {
        $categories = [];

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM employeePointCategories
             WHERE isActive = 1
             ORDER BY categoryName ASC"
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $categories[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $categories;
    }

    // =========================================
    // GET SINGLE CATEGORY
    // =========================================
    public function getCategory($categoryId)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM employeePointCategories
             WHERE id = ?
             AND isActive = 1
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $categoryId,
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $category = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $category ?: null;
    }

    // =========================================
    // GET TOTAL CREDIT POINTS
    // =========================================
    public function getCreditPoints($employeeId)
    {
        $total = 0;

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT SUM(points) as total
             FROM employeePointTransactions
             WHERE employeeId = ?
             AND transactionType = 'Credit'
             AND approvalStatus = 'Approved'
             AND isReverted = 0"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeId
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if ($row && $row['total']) {
            $total = (float)$row['total'];
        }

        return $total;
    }

    // =========================================
    // GET TOTAL DEBIT POINTS
    // =========================================
    public function getDebitPoints($employeeId)
    {
        $total = 0;

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT SUM(points) as total
             FROM employeePointTransactions
             WHERE employeeId = ?
             AND transactionType = 'Debit'
             AND approvalStatus = 'Approved'
             AND isReverted = 0"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeId
        );

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        if ($row && $row['total']) {
            $total = (float)$row['total'];
        }

        return $total;
    }

    // =========================================
    // GET EMPLOYEE POINT BALANCE
    // =========================================
    public function getEmployeePointBalance($employeeId)
    {
        // SETTINGS
        $settings = $this->getSettings();

        // MONTHLY ALLOCATION
        $monthlyAllocation = isset($settings['monthlyAllocation'])
            ? (float)$settings['monthlyAllocation']
            : 0;

        // TOTAL CREDITS
        $credits = $this->getCreditPoints($employeeId);

        // TOTAL DEBITS
        $debits = $this->getDebitPoints($employeeId);

        // FINAL BALANCE
        $balance = $monthlyAllocation + $credits - $debits;

        return [
            'monthlyAllocation' => $monthlyAllocation,
            'credits' => $credits,
            'debits' => $debits,
            'balance' => $balance
        ];
    }

    // =========================================
    // VALIDATE TRANSACTION
    // =========================================
    public function validateTransaction($payload)
    {
        // REQUIRED EMPLOYEE
        if (empty($payload['employeeId'])) {
            return [
                'success' => false,
                'message' => 'Employee is required'
            ];
        }

        // REQUIRED CATEGORY
        if (empty($payload['categoryId'])) {
            return [
                'success' => false,
                'message' => 'Category is required'
            ];
        }

        // VALID CATEGORY
        $category = $this->getCategory($payload['categoryId']);

        if (!$category) {
            return [
                'success' => false,
                'message' => 'Invalid category selected'
            ];
        }

        // VALID POINTS
        if (
            !isset($payload['points']) ||
            !is_numeric($payload['points']) ||
            $payload['points'] <= 0
        ) {
            return [
                'success' => false,
                'message' => 'Invalid points value'
            ];
        }

        return [
            'success' => true,
            'category' => $category
        ];
    }

    // =========================================
    // CHECK THRESHOLDS
    // =========================================
    public function checkThresholds($employeeId)
    {
        $settings = $this->getSettings();

        $balanceData = $this->getEmployeePointBalance($employeeId);

        $balance = $balanceData['balance'];

        $warningThreshold = isset($settings['warningThreshold'])
            ? (float)$settings['warningThreshold']
            : 0;

        $payrollThreshold = isset($settings['payrollThreshold'])
            ? (float)$settings['payrollThreshold']
            : 0;

        return [
            'balance' => $balance,

            'warningTriggered' =>
                $warningThreshold > 0 &&
                $balance <= $warningThreshold,

            'payrollTriggered' =>
                $payrollThreshold > 0 &&
                $balance <= $payrollThreshold
        ];
    }
}