<?php

class AssetAssignmentModel
{
    private $con;

    public function __construct($db)
    {
        $this->con = $db;
    }

    /*
    |----------------------------------------------------------
    | Check if Asset Already Assigned
    |----------------------------------------------------------
    */
    public function isAlreadyAssigned(int $assetId): bool
    {
        $result = mysqli_query($this->con, "
            SELECT id 
            FROM assetAssignment 
            WHERE assetId = {$assetId} 
            AND status = 'assigned'
            LIMIT 1
        ");

        return mysqli_num_rows($result) > 0;
    }

    /*
    |----------------------------------------------------------
    | Assign Asset
    |----------------------------------------------------------
    | Returns the new assetAssignment.id on success (still truthy, so
    | existing `if ($assigned)`-style callers are unaffected), or false.
    */
    public function assignAsset(int $assetId, int $employeeId, array $details = [])
    {
        if ($this->isAlreadyAssigned($assetId)) {
            return false;
        }

        $quantity = (int)($details['quantity'] ?? 1);
        if ($quantity < 1) {
            $quantity = 1;
        }
        $purpose = trim((string)($details['purpose'] ?? '')) ?: null;
        $issueRemarks = trim((string)($details['issueRemarks'] ?? '')) ?: null;
        $expectedReturnDate = trim((string)($details['expectedReturnDate'] ?? '')) ?: null;

        $stmt = mysqli_prepare($this->con, "
            INSERT INTO assetAssignment
            (assetId, employeeId, quantity, purpose, issueRemarks, expectedReturnDate, assignedDate, status)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), 'assigned')
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'iiisss',
            $assetId,
            $employeeId,
            $quantity,
            $purpose,
            $issueRemarks,
            $expectedReturnDate
        );

        $assigned = mysqli_stmt_execute($stmt);
        $newAssignmentId = $assigned ? mysqli_insert_id($this->con) : 0;

        if ($assigned) {
            mysqli_query($this->con, "
                UPDATE assetMaster
                SET status = 'assigned'
                WHERE id = {$assetId}
            ");
        }

        return $assigned ? $newAssignmentId : false;
    }

    /*
    |----------------------------------------------------------
    | Return Asset (Close Entry)
    |----------------------------------------------------------
    */
    public function returnAsset(int $assetId, string $condition, string $remarks): bool
    {
        $allowedConditions = ['new', 'good', 'damaged'];
        if (!in_array($condition, $allowedConditions, true)) {
            $condition = 'good';
        }

        $stmt = mysqli_prepare($this->con, "
            UPDATE assetAssignment
            SET
                status = 'returned',
                actualReturnDate = NOW(),
                returnCondition = ?,
                remarks = ?
            WHERE assetId = ?
            AND status = 'assigned'
        ");

        mysqli_stmt_bind_param($stmt, 'ssi', $condition, $remarks, $assetId);

        $returned = mysqli_stmt_execute($stmt);

        if ($returned) {
            mysqli_query($this->con, "
                UPDATE assetMaster
                SET status = 'available'
                WHERE id = {$assetId}
            ");
        }

        return $returned;
    }
    /*
    |----------------------------------------------------------
    | Get Assignment History (per asset)
    |----------------------------------------------------------
    */
    public function getHistory(int $assetId)
    {
        return mysqli_query($this->con, "
            SELECT
                aa.*,
                e.fullName
            FROM assetAssignment aa
            LEFT JOIN employeeusers e
                ON e.id = aa.employeeId
            WHERE aa.assetId = {$assetId}
            ORDER BY aa.id DESC
        ");
    }

    /*
    |----------------------------------------------------------
    | Get Assignment History (per employee) -- "employee asset record"
    |----------------------------------------------------------
    */
    public function getHistoryByEmployee(int $employeeId)
    {
        return mysqli_query($this->con, "
            SELECT
                aa.*,
                a.assetCode,
                a.assetName,
                c.categoryName
            FROM assetAssignment aa
            INNER JOIN assetMaster a
                ON a.id = aa.assetId
            LEFT JOIN assetCategory c
                ON c.id = a.categoryId
            WHERE aa.employeeId = {$employeeId}
            ORDER BY aa.id DESC
        ");
    }
}