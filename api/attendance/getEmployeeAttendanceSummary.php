<?php session_start();
require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {

    // =========================
    // EMPLOYEE
    // =========================
    $candidateId =
        (int)$_SESSION['candidateId'];

    // =========================
    // SUMMARY
    // =========================
    $stmt = mysqli_prepare(
        $con,
        "SELECT

            COUNT(
                CASE
                    WHEN attendanceStatus='present'
                    THEN 1
                END
            ) AS presentDays,

            COUNT(
                CASE
                    WHEN attendanceStatus='half_day'
                    THEN 1
                END
            ) AS halfDays,

            COALESCE(
                SUM(totalWorkingSeconds),
                0
            ) AS workingSeconds,

            COALESCE(
                SUM(totalBreakSeconds),
                0
            ) AS breakSeconds

        FROM employeeAttendance

        WHERE employeeId =?"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $candidateId
    );

    mysqli_stmt_execute(
        $stmt
    );

    $summary =
        mysqli_stmt_get_result($stmt)
            ->fetch_assoc();

    mysqli_stmt_close(
        $stmt
    );

    echo json_encode([

        'success' => true,

        'data' => [

            'presentDays' =>
                (int)(
                    $summary['presentDays']
                    ?? 0
                ),

            'halfDays' =>
                (int)(
                    $summary['halfDays']
                    ?? 0
                ),

            'workingSeconds' =>
                (int)(
                    $summary['workingSeconds']
                    ?? 0
                ),

            'breakSeconds' =>
                (int)(
                    $summary['breakSeconds']
                    ?? 0
                )
        ]
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            'Failed to load attendance summary'
    ]);
}