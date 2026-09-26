<?php

require_once __DIR__ . '/../../includes/auth-functions.php';

class OvertimeSetupModel
{
    private $con;

    public function __construct()
    {
        global $con;
        $this->con = $con;
    }

    public function getActiveSettings()
    {
        $sql = "SELECT id, companyId, otType, minHoursRequired, maxHoursPerDay, rateType, rateValue, roundingRule, autoApprove, requiresManagerApproval, requiresHrApproval, effectiveFrom, status, createdAt, updatedAt
                FROM overtimeSettings
                WHERE status = 'active'
                ORDER BY effectiveFrom DESC, id DESC
                LIMIT 1";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            return null;
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    public function saveSettings($data)
    {
        $id = isset($data['id']) ? (int) $data['id'] : 0;

        if ($id > 0) {
            $sql = "UPDATE overtimeSettings
                    SET otType = ?, minHoursRequired = ?, maxHoursPerDay = ?, rateType = ?, rateValue = ?,
                        roundingRule = ?, autoApprove = ?, requiresManagerApproval = ?, requiresHrApproval = ?,
                        effectiveFrom = ?, status = ?, updatedAt = NOW()
                    WHERE id = ? AND companyId = ?";

            $stmt = mysqli_prepare($this->con, $sql);
            if (!$stmt) {
                return false;
            }

            mysqli_stmt_bind_param(
                $stmt,
                'isddsdsiisss',
                $data['otType'],
                $data['minHoursRequired'],
                $data['maxHoursPerDay'],
                $data['rateType'],
                $data['rateValue'],
                $data['roundingRule'],
                $data['autoApprove'],
                $data['requiresManagerApproval'],
                $data['requiresHrApproval'],
                $data['effectiveFrom'],
                $data['status'],
                $id,
                $data['companyId']
            );

            $ok = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $ok;
        }

        $deactivateSql = "UPDATE overtimeSettings SET status = 'inactive' WHERE companyId = ? AND status = 'active'";
        $deactivateStmt = mysqli_prepare($this->con, $deactivateSql);
        mysqli_stmt_bind_param($deactivateStmt, 'i', $data['companyId']);
        mysqli_stmt_execute($deactivateStmt);
        mysqli_stmt_close($deactivateStmt);

        $sql = "INSERT INTO overtimeSettings
                (companyId, otType, minHoursRequired, maxHoursPerDay, rateType, rateValue, roundingRule, autoApprove, requiresManagerApproval, requiresHrApproval, effectiveFrom, status, createdAt, updatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param(
            $stmt,
            'isdddssiiiss',
            $data['companyId'],
            $data['otType'],
            $data['minHoursRequired'],
            $data['maxHoursPerDay'],
            $data['rateType'],
            $data['rateValue'],
            $data['roundingRule'],
            $data['autoApprove'],
            $data['requiresManagerApproval'],
            $data['requiresHrApproval'],
            $data['effectiveFrom'],
            $data['status']
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return $ok;
    }
}
