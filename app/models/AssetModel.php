<?php

class AssetModel
{
    private $con;

    public function __construct($db)
    {
        $this->con = $db;
    }

    /*
    |----------------------------------------------------------
    | Generate Asset Code (AST-0001)
    |----------------------------------------------------------
    */
    public function generateAssetCode(): string
    {
        $result = mysqli_query($this->con, "
            SELECT MAX(id) as lastId FROM assetMaster
        ");

        $row = mysqli_fetch_assoc($result);
        $nextId = ((int)$row['lastId']) + 1;

        return 'AST-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    /*
    |----------------------------------------------------------
    | Get All Assets (with assignment)
    |----------------------------------------------------------
    */
    public function getAllAssets()
    {
        $sql = "
            SELECT
                a.*,
                c.categoryName,
                e.fullName AS assignedTo,
                aa.employeeId AS assignedEmployeeId
            FROM assetMaster a
            LEFT JOIN assetCategory c ON c.id = a.categoryId
            LEFT JOIN assetAssignment aa 
                ON aa.assetId = a.id AND aa.status = 'assigned'
            LEFT JOIN employeeusers e 
                ON e.id = aa.employeeId
            ORDER BY a.id DESC
        ";

        return mysqli_query($this->con, $sql);
    }

    /*
    |----------------------------------------------------------
    | Create Asset
    |----------------------------------------------------------
    */
    public function createAsset($data): bool
    {
        $assetCode = $this->generateAssetCode();

        $stmt = mysqli_prepare($this->con, "
            INSERT INTO assetMaster 
            (assetCode, assetName, categoryId, brand, serialNumber, status, conditionStatus)
            VALUES (?, ?, ?, ?, ?, 'available', 'good')
        ");

        mysqli_stmt_bind_param(
            $stmt,
            'ssiss',
            $assetCode,
            $data['assetName'],
            $data['categoryId'],
            $data['brand'],
            $data['serialNumber']
        );

        return mysqli_stmt_execute($stmt);
    }

    /*
    |----------------------------------------------------------
    | Update Asset Status
    |----------------------------------------------------------
    */
    public function updateStatus(int $assetId, string $status): bool
    {
        $stmt = mysqli_prepare($this->con, "
            UPDATE assetMaster 
            SET status = ? 
            WHERE id = ?
        ");

        mysqli_stmt_bind_param($stmt, 'si', $status, $assetId);

        return mysqli_stmt_execute($stmt);
    }

    /*
    |----------------------------------------------------------
    | Update Asset Condition (set when a return is closed)
    |----------------------------------------------------------
    */
    public function updateConditionStatus(int $assetId, string $condition): bool
    {
        $allowed = ['new', 'good', 'damaged'];
        if (!in_array($condition, $allowed, true)) {
            $condition = 'good';
        }

        $stmt = mysqli_prepare($this->con, "
            UPDATE assetMaster
            SET conditionStatus = ?
            WHERE id = ?
        ");

        mysqli_stmt_bind_param($stmt, 'si', $condition, $assetId);

        return mysqli_stmt_execute($stmt);
    }

    /*
    |----------------------------------------------------------
    | Delete Asset
    |----------------------------------------------------------
    */
    public function deleteAsset(int $assetId): bool
    {
        $stmt = mysqli_prepare($this->con, "
            DELETE FROM assetMaster WHERE id = ?
        ");

        mysqli_stmt_bind_param($stmt, 'i', $assetId);

        return mysqli_stmt_execute($stmt);
    }
}