<?php session_start();

require_once __DIR__ . '/../../includes/emp-auth.php';
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

    $candidateId =
        (int)$_SESSION['candidateId'];

    // =========================
    // OWNERSHIP CHECK
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "SELECT id

         FROM employeeAttendance

         WHERE id=?
         AND employeeId=?

         LIMIT 1"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "ii",
        $attendanceId,
        $candidateId
    );

    mysqli_stmt_execute($stmt);

    $attendance =
        mysqli_stmt_get_result($stmt)
            ->fetch_assoc();

    mysqli_stmt_close($stmt);

    if (!$attendance) {

        throw new Exception(
            'Attendance record not found'
        );
    }

    // =========================
    // BREAKS
    // =========================
    $stmt = mysqli_prepare(

        $con,

        "SELECT

            abl.id,
            abl.breakStartTime,
            abl.breakEndTime,
            abl.breakDurationSeconds,

            abt.breakName,
            abt.breakCode

         FROM attendanceBreakLogs abl

         LEFT JOIN attendanceBreakTypes abt
         ON abt.id = abl.breakTypeId

         WHERE abl.attendanceId=?

         ORDER BY abl.breakStartTime ASC"
    );

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $attendanceId
    );

    mysqli_stmt_execute($stmt);

    $result =
        mysqli_stmt_get_result($stmt);

    $rows = [];
    $summary = [];

    while (
    $row =   mysqli_fetch_assoc($result)
    ) {
    
        $rows[] = $row;
    
        if (
            !isset(
                $summary[
                    $row['breakName']
                ]
            )
        ) {
    
            $summary[
                $row['breakName']
            ] = 0;
        }
    
        $summary[
            $row['breakName']
        ] +=
            (int)$row['breakDurationSeconds'];
    }

    mysqli_stmt_close($stmt);

    echo json_encode([

        'success' => true,
    
        'data' => $rows,
    
        'summary' => $summary
    ]);

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()
    ]);
}