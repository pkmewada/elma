<?php

date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../includes/db.php';

require_once __DIR__ . '/../includes/AttendanceEngine.php';


// =========================
// START CRON LOG
// =========================

$logDirectory =
    __DIR__ . '/../logs';


if (!is_dir($logDirectory)) {

    mkdir(
        $logDirectory,
        0755,
        true
    );

}


$logFile =
    $logDirectory . '/autoPunchOut.log';


file_put_contents(
    $logFile,
    "============================\n".
    date('Y-m-d H:i:s') .
    " - Cron Started\n",
    FILE_APPEND
);


// =========================
// RUN ENGINE
// =========================

try {

    $attendanceEngine =
        new AttendanceEngine($con);


    $response =
        $attendanceEngine
        ->autoPunchOutForgottenAttendance();


    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') .
        " - Response: " .
        json_encode($response) .
        "\n\n",
        FILE_APPEND
    );


    echo json_encode($response);


}
catch(Exception $e)
{

    file_put_contents(
        $logFile,
        date('Y-m-d H:i:s') .
        " - ERROR: " .
        $e->getMessage() .
        "\n\n",
        FILE_APPEND
    );


    echo json_encode([

        'success'=>false,

        'message'=>$e->getMessage()

    ]);

}

?>