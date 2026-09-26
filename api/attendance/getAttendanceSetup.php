<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

// =========================
// RESPONSE
// =========================
function respond(
    bool $success,
    string $message,
    array $data = []
): void {

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

try {

    // =========================
    // SETTINGS
    // =========================
    $settingsQuery = mysqli_query(
        $con,
        "SELECT *
         FROM attendanceSettings
         LIMIT 1"
    );

    $settings = mysqli_fetch_assoc(
        $settingsQuery
    );

    if (!$settings) {

        respond(
            true,
            'No attendance setup found.',
            [
                'attendanceSettings' => [],
                'breakTypes' => []
            ]
        );
    }

    // =========================
    // BREAK TYPES
    // =========================
    $breakTypes = [];

    $breakQuery = mysqli_query(
        $con,
        "SELECT
            id,
            breakName,
            breakCode,
            allowedMinutes,
            isPaid,
            allowMultipleTimes,
            isScheduledBreak,
            preferredStartTime,
            isActive
        
        FROM attendanceBreakTypes

         ORDER BY breakName ASC"
    );

    while (
        $row = mysqli_fetch_assoc(
            $breakQuery
        )
    ) {

        $breakTypes[] = [

            'id' => (int)$row['id'],

            'breakName' => $row['breakName'],

            'breakCode' => $row['breakCode'],

            'allowedMinutes' => (int)$row['allowedMinutes'],

            'isPaid' => (int)$row['isPaid'],

            'allowMultipleTimes' => (int)$row['allowMultipleTimes'],

            'isScheduledBreak' => (int)($row['isScheduledBreak'] ?? 0),
            
            'preferredStartTime' => $row['preferredStartTime'] ?? '',
            
            'isActive' => (int)$row['isActive']
        ];
    }

    // =========================
    // WORKING DAYS
    // =========================
    $workingDays = [];

    if (!empty($settings['workingDays'])) {

        $decodedDays = json_decode(
            $settings['workingDays'],
            true
        );

        if (is_array($decodedDays)) {

            $workingDays = $decodedDays;
        }
    }

    // =========================
    // RESPONSE DATA
    // =========================
    $attendanceSettings = [

        'officeStartTime' =>
            $settings['officeStartTime'] ?? '',

        'officeEndTime' =>
            $settings['officeEndTime'] ?? '',

        'totalWorkingHours' =>
            (float)(
                $settings['totalWorkingHours']
                ?? 0
            ),

        'graceMinutes' =>
            (int)(
                $settings['graceMinutes']
                ?? 0
            ),

        'lateAfterMinutes' =>
            (int)(
                $settings['lateAfterMinutes']
                ?? 0
            ),

        'halfDayHours' =>
            (float)(
                $settings['halfDayHours']
                ?? 0
            ),

        'overtimeAfterHours' =>
            (float)(
                $settings['overtimeAfterHours']
                ?? 0
            ),

        'autoPunchOut' =>
            (int)(
                $settings['autoPunchOut']
                ?? 0
            ),

        'autoPunchOutTime' =>
            $settings['autoPunchOutTime'] ?? '',

        'allowMultipleBreaks' =>
            (int)(
                $settings['allowMultipleBreaks']
                ?? 0
            ),
            
        'autoBreakReminderEnabled' => 
                (int)(
                    $settings['autoBreakReminderEnabled'] 
                    ?? 0),    

        'weekendPolicy' =>
            $settings['weekendPolicy']
            ?? 'exclude',

        'workingDays' =>
            $workingDays
    ];

    // =========================
    // SUCCESS RESPONSE
    // =========================
    respond(
        true,
        'Attendance setup loaded successfully.',
        [
            'attendanceSettings' => $attendanceSettings,
            'breakTypes' => $breakTypes
        ]
    );

} catch (Throwable $e) {

    respond(
        false,
        $e->getMessage()
    );
}


?>