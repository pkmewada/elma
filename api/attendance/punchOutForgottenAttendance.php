<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/AttendanceEngine.php';

header('Content-Type: application/json');

// Admin closes one forgotten 'in_progress' attendance (employee forgot to
// punch out) at 07:00 PM -- see AttendanceEngine::adminPunchOutForgottenAttendance().
try {

    $attendanceId =
        (int)(
            $_POST['attendanceId']
            ?? 0
        );

    if ($attendanceId <= 0) {

        throw new Exception(
            'Invalid attendance record'
        );
    }

    $engine =
        new AttendanceEngine($con);

    echo json_encode(
        $engine->adminPunchOutForgottenAttendance(
            $attendanceId
        )
    );

} catch (Exception $e) {

    echo json_encode([

        'success' => false,

        'message' =>
            $e->getMessage()
    ]);
}
