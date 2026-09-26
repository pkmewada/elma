<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {

    // =========================
    // INPUTS
    // =========================
    $attendanceId =
        (int)(
            $_POST['attendanceId']
            ?? 0
        );

    $attendanceDate =
        trim(
            $_POST['attendanceDate']
            ?? ''
        );

    $punchInTime =
        trim(
            $_POST['punchInTime']
            ?? ''
        );

    $punchOutTime =
        trim(
            $_POST['punchOutTime']
            ?? ''
        );

    $attendanceStatus =
        trim(
            $_POST['attendanceStatus']
            ?? ''
        );

    $remarks =
        trim(
            $_POST['remarks']
            ?? ''
        );

    if ($attendanceId <= 0) {

        throw new Exception(
            'Invalid attendance record'
        );
    }

    // =========================
    // EXISTING RECORD
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "SELECT *

         FROM employeeAttendance

         WHERE id=?

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

    $attendance =
        mysqli_stmt_get_result($stmt)
            ->fetch_assoc();

    mysqli_stmt_close(
        $stmt
    );

    if (!$attendance) {

        throw new Exception(
            'Attendance record not found'
        );
    }

    // =========================
    // BREAK SECONDS
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "SELECT

            COALESCE(
                SUM(
                    breakDurationSeconds
                ),
                0
            ) AS totalBreakSeconds

         FROM attendanceBreakLogs

         WHERE attendanceId=?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $attendanceId
    );

    mysqli_stmt_execute(
        $stmt
    );

    $breakData =
        mysqli_stmt_get_result($stmt)
            ->fetch_assoc();

    mysqli_stmt_close(
        $stmt
    );

    $totalBreakSeconds =
        (int)(
            $breakData['totalBreakSeconds']
            ?? 0
        );

    // =========================
    // WORKING SECONDS
    // =========================
    $totalWorkingSeconds = 0;

    if (
        !empty($punchInTime) &&
        !empty($punchOutTime)
    ) {

        $start =
            strtotime(
                $attendanceDate .
                ' ' .
                $punchInTime
            );

        $end =
            strtotime(
                $attendanceDate .
                ' ' .
                $punchOutTime
            );

        $attendanceSeconds =
            max(
                0,
                $end - $start
            );

        $totalWorkingSeconds =
            max(
                0,
                $attendanceSeconds -
                $totalBreakSeconds
            );
    }

    // =========================
    // OVERTIME
    // =========================
    $overtimeSeconds = 0;

    // =========================
    // UPDATE
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "UPDATE employeeAttendance SET

            attendanceDate=?,
            punchInTime=?,
            punchOutTime=?,
            attendanceStatus=?,
            remarks=?,

            totalBreakSeconds=?,
            totalWorkingSeconds=?,
            overtimeSeconds=?

         WHERE id=?"
    );

    mysqli_stmt_bind_param(

        $stmt,

        "sssssiiii",

        $attendanceDate,
        $punchInTime,
        $punchOutTime,
        $attendanceStatus,
        $remarks,

        $totalBreakSeconds,
        $totalWorkingSeconds,
        $overtimeSeconds,

        $attendanceId
    );

    $success =
        mysqli_stmt_execute(
            $stmt
        );

    mysqli_stmt_close(
        $stmt
    );

    if (!$success) {

        throw new Exception(
            'Failed to update attendance'
        );
    }

    echo json_encode([

        'success' => true,

        'message' =>
            'Attendance updated successfully'
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()
    ]);
}