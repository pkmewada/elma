<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/AttendanceEngine.php';

header('Content-Type: application/json');

$attendanceEngine = new AttendanceEngine($con);

try {

    $selectedDate = $_GET['date'] ?? date('Y-m-d');

    // =========================
    // ATTENDANCE LISTING
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "SELECT

            eu.id AS employeeId,

            eu.employeeCode,

            eu.fullName,

            eu.departmentName,

            eu.designationName,

            eu.profilePhoto,

            eu.accountStatus,

            eu.employmentStatus,

            ea.id,

            ea.attendanceDate,

            ea.punchInTime,

            ea.punchOutTime,

            ea.totalWorkingSeconds,

            ea.totalBreakSeconds,

            ea.attendanceStatus,

            leaveInfo.leaveId,

            leaveInfo.dayType

        FROM employeeusers eu

        LEFT JOIN employeeAttendance ea

            ON ea.employeeId = eu.id

            AND ea.attendanceDate = ?

        LEFT JOIN (
            SELECT
                la.employeeId,
                la.id AS leaveId,
                lad.dayType
            FROM leaveApplicationDays lad
            INNER JOIN leaveApplications la
                ON la.id = lad.leaveApplicationId
            WHERE lad.leaveDate = ?
            AND lad.status = 'approved'
            AND la.status <> 'cancelled'
        ) leaveInfo
            ON leaveInfo.employeeId = eu.id

        WHERE

            eu.employmentStatus = 'active'

        ORDER BY

            eu.fullName ASC"
    );

    if (!$stmt) {

        throw new Exception(

            'Failed to prepare attendance listing query : ' .

            mysqli_error($con)
        );
    }

    mysqli_stmt_bind_param(

        $stmt,

        "ss",

        $selectedDate,

        $selectedDate
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {

        // =====================================
        // DETERMINE FINAL ATTENDANCE STATUS
        // =====================================

        if (!empty($row['attendanceStatus'])) {

            // Keep existing attendance status

        } elseif (

            !empty($row['leaveId']) &&
            $row['dayType'] === 'full'

        ) {

            $row['attendanceStatus'] = 'leave';

        } else {

            $row['attendanceStatus'] = 'absent';
        }

        // =====================================
        // DEFAULT VALUES
        // =====================================

        $row['attendanceDate'] = $row['attendanceDate'] ?: $selectedDate;

        $row['punchInTime'] = $row['punchInTime'] ?: null;

        $row['punchOutTime'] = $row['punchOutTime'] ?: null;

        $row['totalWorkingSeconds'] = (int)($row['totalWorkingSeconds'] ?? 0);

        $row['totalBreakSeconds'] = (int)($row['totalBreakSeconds'] ?? 0);

        // =====================================
        // LIVE WORKING HOURS
        // =====================================

        if (

            $row['attendanceStatus'] === 'in_progress' &&
            !empty($row['id'])

        ) {

            $row['totalBreakSeconds'] =
                $attendanceEngine->getLiveBreakSeconds(

                    $row['id'],

                    $selectedDate
                );

            $row['totalWorkingSeconds'] =
                $attendanceEngine->getLiveWorkingSeconds(

                    $row
                );
        }

        // =====================================
        // DISPLAY STATUS
        // =====================================

        switch ($row['attendanceStatus']) {

            case 'present':
                $row['displayStatus'] = 'Present';
                break;

            case 'in_progress':
                $row['displayStatus'] = 'Currently Working';
                break;

            case 'half_day':
                $row['displayStatus'] = 'Half Day';
                break;

            case 'leave':
                $row['displayStatus'] = 'On Leave';
                break;

            case 'absent':
                $row['displayStatus'] = 'Absent';
                break;

            default:
                $row['displayStatus'] = 'Unknown';
        }

        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    echo json_encode([

        'success' => true,

        'data' => $rows

    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage()

    ]);
}
