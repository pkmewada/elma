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
    // ATTENDANCE HISTORY
    // =========================
    $stmt = mysqli_prepare(
        $con,
        "SELECT

            id,
            attendanceDate,
            punchInTime,
            punchOutTime,
            totalWorkingSeconds,
            totalBreakSeconds,
            attendanceStatus

        FROM employeeAttendance

        WHERE employeeId =?

        ORDER BY attendanceDate DESC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $candidateId
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    $rows = [];

    while (
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ) {

        $rows[] = $row;
    }

    mysqli_stmt_close(
        $stmt
    );

    echo json_encode([

        'success' => true,

        'data' => $rows
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            'Failed to load attendance history'
    ]);
}