<?php

class CommissionBonusEngine
{

    /*
    |--------------------------------------------------------------------------
    | Get Global Settings
    |--------------------------------------------------------------------------
    */

    public static function getSettings($con)
    {
        $sql = "SELECT *
                FROM commissionBonusSettings
                ORDER BY id DESC
                LIMIT 1";

        $result = mysqli_query($con, $sql);

        return mysqli_fetch_assoc($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Save / Update Settings
    |--------------------------------------------------------------------------
    */

    public static function saveSettings($con, $data)
    {
        $existing = self::getSettings($con);

        /*
        |--------------------------------------------------------------------------
        | Update Existing
        |--------------------------------------------------------------------------
        */

        if ($existing) {

            $sql = "UPDATE commissionBonusSettings SET

                        monthlyBonusEnabled = ?,
                        approvalWorkflow = ?,
                        payrollIntegration = ?,
                        autoPayrollSync = ?,
                        allowNegativeAdjustment = ?,
                        maxBonusLimit = ?,
                        maxCommissionLimit = ?,
                        requireRemarks = ?,
                        requireAttachment = ?,
                        notifyEmployeeOnApproval = ?,
                        notifyEmployeeOnRejection = ?,
                        notifyEmployeeOnPayrollSync = ?,
                        updatedBy = ?

                    WHERE id = ?";

            $stmt = mysqli_prepare($con, $sql);

            mysqli_stmt_bind_param(

                $stmt,

                "isiiiddiiiiiii",

                $data['monthlyBonusEnabled'],
                $data['approvalWorkflow'],
                $data['payrollIntegration'],
                $data['autoPayrollSync'],
                $data['allowNegativeAdjustment'],
                $data['maxBonusLimit'],
                $data['maxCommissionLimit'],
                $data['requireRemarks'],
                $data['requireAttachment'],
                $data['notifyEmployeeOnApproval'],
                $data['notifyEmployeeOnRejection'],
                $data['notifyEmployeeOnPayrollSync'],
                $data['updatedBy'],
                $existing['id']
            );

            return mysqli_stmt_execute($stmt);

        } else {

            /*
            |--------------------------------------------------------------------------
            | Insert New
            |--------------------------------------------------------------------------
            */

            $sql = "INSERT INTO commissionBonusSettings (

                        monthlyBonusEnabled,
                        approvalWorkflow,
                        payrollIntegration,
                        autoPayrollSync,
                        allowNegativeAdjustment,
                        maxBonusLimit,
                        maxCommissionLimit,
                        requireRemarks,
                        requireAttachment,
                        notifyEmployeeOnApproval,
                        notifyEmployeeOnRejection,
                        notifyEmployeeOnPayrollSync,
                        createdBy

                    ) VALUES (

                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?

                    )";

            $stmt = mysqli_prepare($con, $sql);

            mysqli_stmt_bind_param(

                $stmt,

                "isiiiddiiiiii",

                $data['monthlyBonusEnabled'],
                $data['approvalWorkflow'],
                $data['payrollIntegration'],
                $data['autoPayrollSync'],
                $data['allowNegativeAdjustment'],
                $data['maxBonusLimit'],
                $data['maxCommissionLimit'],
                $data['requireRemarks'],
                $data['requireAttachment'],
                $data['notifyEmployeeOnApproval'],
                $data['notifyEmployeeOnRejection'],
                $data['notifyEmployeeOnPayrollSync'],
                $data['createdBy']
            );

            return mysqli_stmt_execute($stmt);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Get Categories
    |--------------------------------------------------------------------------
    */

    public static function getCategories($con)
    {
        $sql = "SELECT *
                FROM commissionBonusCategories
                WHERE isReverted = 0
                ORDER BY id DESC";

        $result = mysqli_query($con, $sql);

        $categories = [];

        while ($row = mysqli_fetch_assoc($result)) {

            $categories[] = $row;
        }

        return $categories;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Category By ID
    |--------------------------------------------------------------------------
    */

    public static function getCategoryById(
        $con,
        $id
    ) {

        $sql = "SELECT *
                FROM commissionBonusCategories
                WHERE id = ?
                LIMIT 1";

        $stmt = mysqli_prepare(
            $con,
            $sql
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $id
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        return mysqli_fetch_assoc($result);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Category Code
    |--------------------------------------------------------------------------
    */

    public static function generateCategoryCode($name)
    {
        $code = strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]/',
                '',
                substr($name, 0, 5)
            )
        );

        return "CB-" .
            $code .
            "-" .
            rand(100, 999);
    }

   /*
    |--------------------------------------------------------------------------
    | Validate Category
    |--------------------------------------------------------------------------
    */
    
    public static function validateCategory($data)
    {
        $errors = [];
    
        if (empty($data['categoryName'])) {
    
            $errors[] =
                "Category name is required";
        }
    
        if (empty($data['categoryCode'])) {
    
            $errors[] =
                "Category code is required";
        }
    
        if (empty($data['categoryType'])) {
    
            $errors[] =
                "Category type is required";
        }
    
        /*
        |--------------------------------------------------------------------------
        | Bonus Validation
        |--------------------------------------------------------------------------
        */
    
        if (
            strtolower($data['categoryType']) === 'bonus'
        ) {
    
            if (
                isset($data['defaultAmount']) &&
                floatval($data['defaultAmount']) < 0
            ) {
    
                $errors[] =
                    "Bonus amount cannot be negative";
            }
        }
    
        /*
        |--------------------------------------------------------------------------
        | Commission Validation
        |--------------------------------------------------------------------------
        */
    
        if (
            strtolower($data['categoryType']) === 'commission'
        ) {
    
            if (
                !isset($data['commissionPercentage']) ||
                floatval($data['commissionPercentage']) <= 0
            ) {
    
                $errors[] =
                    "Commission percentage is required";
            }
    
            if (
                floatval($data['commissionPercentage']) > 100
            ) {
    
                $errors[] =
                    "Commission percentage cannot exceed 100";
            }
        }
    
        return $errors;
    }
    
        /*
    |--------------------------------------------------------------------------
    | Save Category
    |--------------------------------------------------------------------------
    */
    
    public static function saveCategory(
        $con,
        $data
    ) {
    
        $sql = "INSERT INTO commissionBonusCategories (
    
                    categoryName,
                    categoryCode,
                    categoryType,
                    defaultAmount,
                    commissionPercentage,
                    taxable,
                    payrollApplicable,
                    requiresApproval,
                    description,
                    createdBy
    
                ) VALUES (
    
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    
                )";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "sssddiiisi",
    
            $data['categoryName'],
            $data['categoryCode'],
            $data['categoryType'],
            $data['defaultAmount'],
            $data['commissionPercentage'],
            $data['taxable'],
            $data['payrollApplicable'],
            $data['requiresApproval'],
            $data['description'],
            $data['createdBy']
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
       /*
    |--------------------------------------------------------------------------
    | Update Category
    |--------------------------------------------------------------------------
    */
    
    public static function updateCategory(
        $con,
        $id,
        $data
    ) {
    
        $sql = "UPDATE commissionBonusCategories SET
    
                    categoryName = ?,
                    categoryType = ?,
                    defaultAmount = ?,
                    commissionPercentage = ?,
                    taxable = ?,
                    payrollApplicable = ?,
                    requiresApproval = ?,
                    description = ?,
                    updatedBy = ?
    
                WHERE id = ?";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "ssddiiisii",
    
            $data['categoryName'],
            $data['categoryType'],
            $data['defaultAmount'],
            $data['commissionPercentage'],
            $data['taxable'],
            $data['payrollApplicable'],
            $data['requiresApproval'],
            $data['description'],
            $data['updatedBy'],
            $id
        );
    
        return mysqli_stmt_execute($stmt);
    }
        
        
        
       /*
    |--------------------------------------------------------------------------
    | Save Transaction
    |--------------------------------------------------------------------------
    */
    
    public static function saveTransaction(
        $con,
        $data
    ) {
    
        $sql = "INSERT INTO employeeCommissionTransactions (
    
                    transactionCode,
                    employeeId,
                    categoryId,
                    transactionType,
                    amount,
                    baseAmount,
                    commissionPercentage,
                    remarks,
                    attachment,
                    effectiveMonth,
                    payrollStatus,
                    approvalStatus,
                    createdBy
    
                ) VALUES (
    
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    
                )";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "siisdddsssssi",
    
            $data['transactionCode'],
            $data['employeeId'],
            $data['categoryId'],
            $data['transactionType'],
            $data['amount'],
            $data['baseAmount'],
            $data['commissionPercentage'],
            $data['remarks'],
            $data['attachment'],
            $data['effectiveMonth'],
            $data['payrollStatus'],
            $data['approvalStatus'],
            $data['createdBy']
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
        /*
    |--------------------------------------------------------------------------
    | Update Transaction
    |--------------------------------------------------------------------------
    */
    
    public static function updateTransaction(
        $con,
        $transactionId,
        $data
    ) {
    
        $sql = "UPDATE employeeCommissionTransactions SET
    
                    employeeId = ?,
                    categoryId = ?,
                    transactionType = ?,
                    amount = ?,
                    baseAmount = ?,
                    commissionPercentage = ?,
                    remarks = ?,
                    attachment = ?,
                    effectiveMonth = ?,
                    updatedBy = ?,
                    updatedAt = NOW()
    
                WHERE id = ?";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "iisdddsssii",
    
            $data['employeeId'],
            $data['categoryId'],
            $data['transactionType'],
            $data['amount'],
            $data['baseAmount'],
            $data['commissionPercentage'],
            $data['remarks'],
            $data['attachment'],
            $data['effectiveMonth'],
            $data['updatedBy'],
            $transactionId
        );
    
        return mysqli_stmt_execute($stmt);
    }

    /*
    |--------------------------------------------------------------------------
    | Soft Delete Category
    |--------------------------------------------------------------------------
    */

    public static function deleteCategory(
        $con,
        $id,
        $revertedBy
    ) {

        $sql = "UPDATE commissionBonusCategories SET

                    isReverted = 1,
                    revertedBy = ?,
                    revertedAt = NOW()

                WHERE id = ?";

        $stmt = mysqli_prepare(
            $con,
            $sql
        );

        mysqli_stmt_bind_param(

            $stmt,

            "ii",

            $revertedBy,
            $id
        );

        return mysqli_stmt_execute($stmt);
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Transaction Code
    |--------------------------------------------------------------------------
    */

    public static function generateTransactionCode()
    {
        return 'CBT-' .
            date('Y') .
            '-' .
            rand(100000, 999999);
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Transaction
    |--------------------------------------------------------------------------
    */

    public static function validateTransaction($data)
    {
        $errors = [];

        if (
            empty($data['employeeId'])
        ) {

            $errors[] =
                'Employee is required';
        }

        if (
            empty($data['categoryId'])
        ) {

            $errors[] =
                'Category is required';
        }

        if (
            !isset($data['amount']) ||
            floatval($data['amount']) <= 0
        ) {

            $errors[] =
                'Invalid amount';
        }

        return $errors;
    }

    /*
    |--------------------------------------------------------------------------
    | Get Transaction By ID
    |--------------------------------------------------------------------------
    */

    public static function getTransactionById(
        $con,
        $transactionId
    ) {

        $sql = "SELECT *
                FROM employeeCommissionTransactions
                WHERE id = ?
                LIMIT 1";

        $stmt = mysqli_prepare(
            $con,
            $sql
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $transactionId
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        return mysqli_fetch_assoc($result);
    }

    
    /*
    |--------------------------------------------------------------------------
    | Soft Delete Transaction
    |--------------------------------------------------------------------------
    */

    public static function deleteTransaction(
        $con,
        $transactionId,
        $revertedBy
    ) {

        $sql = "UPDATE employeeCommissionTransactions SET

                    isReverted = 1,
                    revertedBy = ?,
                    revertedAt = NOW()

                WHERE id = ?";

        $stmt = mysqli_prepare(
            $con,
            $sql
        );

        mysqli_stmt_bind_param(

            $stmt,

            "ii",

            $revertedBy,
            $transactionId
        );

        return mysqli_stmt_execute($stmt);
    }

    /*
    |--------------------------------------------------------------------------
    | Get Transactions
    |--------------------------------------------------------------------------
    */

    public static function getTransactions(
        $con,
        $employeeId = null
    ) {

        $where =
            "t.isReverted = 0 AND e.accountStatus = 'Active'";

        if (!empty($employeeId)) {

            $where .=
                " AND t.employeeId = " .
                intval($employeeId);
        }

        $sql = "
            SELECT

                t.*,

                e.fullName AS employeeName,

                c.categoryName,
                c.categoryCode,
                c.categoryType,
                e.accountStatus

            FROM employeeCommissionTransactions t

            LEFT JOIN employeeusers e
                ON e.id = t.employeeId

            LEFT JOIN commissionBonusCategories c
                ON c.id = t.categoryId

            WHERE {$where}

            ORDER BY t.id DESC
        ";

        $result =
            mysqli_query($con, $sql);

        $rows = [];

        while (
            $row = mysqli_fetch_assoc($result)
        ) {

            $rows[] = $row;
        }

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Calculate Summary
    |--------------------------------------------------------------------------
    */

    public static function calculateSummary(
        $transactions
    ) {

        $summary = [

            'pending' => 0,
            'approved' => 0,
            'synced' => 0,
            'paid' => 0
        ];

        foreach ($transactions as $row) {

            $amount =
                floatval($row['amount']);

            /*
            |--------------------------------------------------------------------------
            | Approval Status
            |--------------------------------------------------------------------------
            */

            if (
                strtolower(
                    $row['approvalStatus']
                ) === 'pending'
            ) {

                $summary['pending'] +=
                    $amount;
            }

            if (
                strtolower(
                    $row['approvalStatus']
                ) === 'approved'
            ) {

                $summary['approved'] +=
                    $amount;
            }

            /*
            |--------------------------------------------------------------------------
            | Payroll Status
            |--------------------------------------------------------------------------
            */

            if (
                strtolower(
                    $row['payrollStatus']
                ) === 'synced'
            ) {

                $summary['synced'] +=
                    $amount;
            }

            if (
                strtolower(
                    $row['payrollStatus']
                ) === 'paid'
            ) {

                $summary['paid'] +=
                    $amount;
            }
        }

        return $summary;
    }
    
     /*
    |--------------------------------------------------------------------------
    | Approve Transaction Flow
    |--------------------------------------------------------------------------
    */
    
    
    public static function approveTransaction(
        $con,
        $transactionId,
        $approvedBy
    )
    {
        $sql = "
            UPDATE employeeCommissionTransactions
            SET
    
                approvalStatus = 'Approved',
                approvedBy = ?,
                approvedAt = NOW(),
                updatedBy = ?
    
            WHERE id = ?
        ";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "iii",
            $approvedBy,
            $approvedBy,
            $transactionId
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
    
     /*
    |--------------------------------------------------------------------------
    | Reject Transaction Flow
    |--------------------------------------------------------------------------
    */
    
    
    public static function rejectTransaction(
        $con,
        $transactionId,
        $rejectedBy,
        $reason = null
    )
    {
        $sql = "
            UPDATE employeeCommissionTransactions
            SET
    
                approvalStatus = 'Rejected',
                rejectionReason = ?,
                updatedBy = ?
    
            WHERE id = ?
        ";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "sii",
            $reason,
            $rejectedBy,
            $transactionId
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
    /*
    |--------------------------------------------------------------------------
    | Sync Transaction To Payroll
    |--------------------------------------------------------------------------
    */
    
    public static function syncToPayroll(
        $con,
        $transactionId,
        $updatedBy
    ) {
    
        $sql = "
            UPDATE employeeCommissionTransactions
            SET
    
                payrollStatus = 'Synced',
                updatedBy = ?,
                updatedAt = NOW()
    
            WHERE id = ?
        ";
    
        $stmt = mysqli_prepare(
            $con,
            $sql
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $updatedBy,
            $transactionId
        );
    
        return mysqli_stmt_execute($stmt);
    }
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
    
}