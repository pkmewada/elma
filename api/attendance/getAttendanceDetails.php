<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {

    // =========================
    // VALIDATION
    // =========================
    $attendanceId =
        (int)(
            $_GET['attendanceId']
            ?? 0
        );

    if ($attendanceId <= 0) {

        throw new Exception(
            'Invalid attendance record'
        );
    }

    // =========================
    // ATTENDANCE DETAILS
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "SELECT

            ea.id,
            ea.employeeId,
            ea.attendanceDate,
            ea.punchInTime,
            ea.punchOutTime,
            ea.attendanceStatus,
            ea.remarks,

            eu.employeeCode,
            eu.fullName,
            eu.departmentName,
            eu.designationName,
            eu.accountStatus

         FROM employeeAttendance ea

         LEFT JOIN employeeusers eu
         ON eu.id = ea.employeeId

         WHERE ea.id=? AND eu.accountStatus = 'Active'

         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $attendanceId
    );

    mysqli_stmt_execute(
        $stmt
    );

    $record =
        mysqli_stmt_get_result($stmt)
            ->fetch_assoc();

    mysqli_stmt_close(
        $stmt
    );

    if (!$record) {

        throw new Exception(
            'Attendance record not found'
        );
    }

    echo json_encode([

        'success' => true,

        'data' => $record
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()
    ]);
}