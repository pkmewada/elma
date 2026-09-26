<?php

class LeaveEngine
{
    private $con;
    private $companyId;

    public function __construct($con, $companyId)
    {
        $this->con = $con;
        $this->companyId = $companyId;
    }

    // =============================
    // LOAD SETTINGS
    // =============================
    public function getSettings()
    {
        $stmt = mysqli_prepare($this->con, "SELECT * FROM leaveSettings WHERE companyId=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $this->companyId);
        mysqli_stmt_execute($stmt);

        $res = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        return $res ?: [];
    }

    // =============================
    // LOAD LEAVE TYPE
    // =============================
    public function getLeaveType($leaveTypeId)
    {
        $stmt = mysqli_prepare($this->con, "SELECT * FROM leaveTypes WHERE id=? AND companyId=? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $leaveTypeId, $this->companyId);
        mysqli_stmt_execute($stmt);

        $res = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);

        return $res ?: null;
    }

    // =============================
    // CALCULATE DAYS
    // =============================
    public function calculateDays($from, $to, $settings)
    {
        $workingDays = json_decode($settings['workingDays'] ?? '[]', true);
        $weekendPolicy = $settings['weekendPolicy'] ?? 'exclude';

        $start = new DateTime($from);
        $end = new DateTime($to);
        $end->modify('+1 day');

        $total = 0;

        while ($start < $end) {
            $day = strtolower($start->format('D')); // mon,tue

            if (in_array($day, $workingDays)) {
                $total++;
            } else {
                if ($weekendPolicy === 'include') {
                    $total++;
                }
            }

            $start->modify('+1 day');
        }

        return $total;
    }

    // =============================
    // MAIN VALIDATION
    // =============================
    public function validate($payload)
    {
        $leaveType = $this->getLeaveType($payload['leaveTypeId']);

        if (!$leaveType || $leaveType['isActive'] != 1) {
            return ['success' => false, 'message' => 'Invalid leave type'];
        }

        $settings = $this->getSettings();

        // =========================
        // CALCULATE DAYS
        // =========================
        $days = $this->calculateDays(
            $payload['fromDate'],
            $payload['toDate'],
            $settings
        );

        if ($days <= 0) {
            return ['success' => false, 'message' => 'Invalid leave duration'];
        }

        // =========================
        // MAX CONSECUTIVE
        // =========================
        if ($leaveType['maxConsecutiveDays'] > 0 &&
            $days > $leaveType['maxConsecutiveDays']) {
            return [
                'success' => false,
                'message' => 'Max ' . $leaveType['maxConsecutiveDays'] . ' days allowed'
            ];
        }

        // =========================
        // GENDER CHECK
        // =========================
        if ($leaveType['applicableGender'] !== 'all') {

            $emp = mysqli_fetch_assoc(mysqli_query($this->con,
                "SELECT gender FROM employees WHERE id=" . (int)$_SESSION['userId']
            ));

            if ($emp && $emp['gender'] !== $leaveType['applicableGender']) {
                return ['success' => false, 'message' => 'Leave not allowed for your gender'];
            }
        }

        return [
            'success' => true,
            'days' => $days,
            'leaveType' => $leaveType,
            'settings' => $settings
        ];
    }
}