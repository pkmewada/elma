<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {

    $selectedDate = $_GET['date'] ?? date('Y-m-d');

    // ==========================================
    // ACTIVE EMPLOYEES
    // ==========================================
    $stmt = mysqli_prepare(

        $con,

        "SELECT COUNT(*)

         FROM employeeusers

         WHERE employmentStatus='active'"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($con));
    }

    mysqli_stmt_execute($stmt);

    mysqli_stmt_bind_result(
        $stmt,
        $activeEmployees
    );

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    // ==========================================
    // ATTENDANCE SUMMARY
    // ==========================================
    $stmt = mysqli_prepare(

        $con,

        "SELECT

            SUM(attendanceStatus IN ('present','half_day','in_progress')) AS presentToday,

            SUM(attendanceStatus='half_day') AS halfDay,

            SUM(attendanceStatus='in_progress') AS activeToday

         FROM employeeAttendance

         WHERE attendanceDate=?"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($con));
    }

    mysqli_stmt_bind_param(

        $stmt,

        "s",

        $selectedDate
    );

    mysqli_stmt_execute($stmt);

    mysqli_stmt_bind_result(

        $stmt,

        $presentToday,

        $halfDay,

        $activeToday
    );

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    // ==========================================
    // APPROVED FULL DAY LEAVE
    // (Employee should not have attendance today)
    // ==========================================
    $stmt = mysqli_prepare(

        $con,

        "SELECT COUNT(DISTINCT la.employeeId)

         FROM leaveApplicationDays lad

         INNER JOIN leaveApplications la
            ON la.id = lad.leaveApplicationId

         INNER JOIN employeeusers eu
            ON eu.id = la.employeeId

         LEFT JOIN employeeAttendance ea
            ON ea.employeeId = la.employeeId
            AND ea.attendanceDate = ?

         WHERE

            eu.employmentStatus='active'

            AND la.status <> 'cancelled'

            AND lad.status='approved'

            AND lad.dayType='full'

            AND lad.leaveDate = ?

            AND ea.id IS NULL"
    );

    if (!$stmt) {
        throw new Exception(mysqli_error($con));
    }

    mysqli_stmt_bind_param(

        $stmt,

        "ss",

        $selectedDate,

        $selectedDate
    );

    mysqli_stmt_execute($stmt);

    mysqli_stmt_bind_result(

        $stmt,

        $onLeave
    );

    mysqli_stmt_fetch($stmt);

    mysqli_stmt_close($stmt);

    // ==========================================
    // FINAL COUNTS
    // ==========================================
    $activeEmployees = (int)$activeEmployees;
    $presentToday    = (int)$presentToday;
    $halfDay         = (int)$halfDay;
    $activeToday     = (int)$activeToday;
    $onLeave         = (int)$onLeave;

    $absent = max(

        0,

        $activeEmployees -

        $presentToday -

        $onLeave
    );

    // ==========================================
    // RESPONSE
    // ==========================================
    echo json_encode([

        'success' => true,

        'data' => [

            'presentToday'   => $presentToday,

            'halfDay'        => $halfDay,

            'activeToday'    => $activeToday,

            'onLeave'        => $onLeave,

            'absent'         => $absent,

            'activeEmployees'=> $activeEmployees

        ]

    ]);

} catch (Exception $e) {

    error_log(
        'Attendance Summary Error : ' .
        $e->getMessage()
    );

    echo json_encode([

        'success' => false,

        'message' => 'Failed to load attendance summary'

    ]);
}
