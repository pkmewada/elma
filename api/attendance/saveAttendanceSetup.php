<?php
    require_once __DIR__ . '/../../includes/auth.php';
    require_once __DIR__ . '/../../includes/db.php';
    
    header('Content-Type: application/json; charset=UTF-8');
    
    // =========================
    // RESPONSE
    // =========================
    function respond(
        bool $success,
        string $message
    ): void {
    
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
    
        exit;
    }
    
    // =========================
    // CLEAN INTEGER
    // =========================
    function cleanNonNegativeInt(
        $value,
        int $default = 0
    ): int {
    
        if (
            $value === '' ||
            $value === null
        ) {
            return $default;
        }
    
        return max(
            0,
            (int)$value
        );
    }
    
    // =========================
    // CLEAN FLOAT
    // =========================
    function cleanNonNegativeFloat(
        $value,
        float $default = 0
    ): float {
    
        if (
            $value === '' ||
            $value === null
        ) {
            return $default;
        }
    
        return max(
            0,
            (float)$value
        );
    }
    
    // =========================
    // GET PAYLOAD
    // =========================
    $payload = json_decode(
        (string)file_get_contents('php://input'),
        true
    );
    
    if (!is_array($payload)) {
    
        respond(
            false,
            'Invalid payload.'
        );
    }
    
    // =========================
    // SETTINGS
    // =========================
    $attendanceSettings = is_array(
        $payload['attendanceSettings'] ?? null
    )
    
    ? $payload['attendanceSettings']
    
    : [];
    
    $breakTypes = is_array(
        $payload['breakTypes'] ?? null
    )
    
    ? $payload['breakTypes']
    
    : [];
    
    // =========================
    // VALIDATION
    // =========================
    if (
        count($breakTypes) < 1
    ) {
    
        respond(
            false,
            'At least 1 break type is required.'
        );
    }
    
    // =========================
    // WORKING DAYS
    // =========================
    $workingDaysIn =
        $attendanceSettings['workingDays'] ?? [];
    
    if (
        !is_array($workingDaysIn) ||
        count($workingDaysIn) < 1
    ) {
    
        respond(
            false,
            'Working days are required.'
        );
    }
    
    $allowedDays = [
        'mon',
        'tue',
        'wed',
        'thu',
        'fri',
        'sat',
        'sun'
    ];
    
    $workingDays = [];
    
    foreach ($workingDaysIn as $day) {
    
        $day = strtolower(
            trim((string)$day)
        );
    
        if (
            in_array(
                $day,
                $allowedDays,
                true
            )
        ) {
    
            $workingDays[] = $day;
        }
    }
    
    $workingDays = array_values(
        array_unique($workingDays)
    );
    
    if (
        count($workingDays) < 1
    ) {
    
        respond(
            false,
            'Working days are required.'
        );
    }
    
    // =========================
    // SETTINGS NORMALIZATION
    // =========================
    $officeStartTime =
        trim((string)(
            $attendanceSettings['officeStartTime']
            ?? ''
        ));
    
    $officeEndTime =
        trim((string)(
            $attendanceSettings['officeEndTime']
            ?? ''
        ));
    
    $totalWorkingHours =
        cleanNonNegativeFloat(
            $attendanceSettings['totalWorkingHours']
            ?? 0
        );
    
    $graceMinutes =
        cleanNonNegativeInt(
            $attendanceSettings['graceMinutes']
            ?? 0
        );
    
    $lateAfterMinutes =
        cleanNonNegativeInt(
            $attendanceSettings['lateAfterMinutes']
            ?? 0
        );
    
    $halfDayHours =
        cleanNonNegativeFloat(
            $attendanceSettings['halfDayHours']
            ?? 0
        );
    
    $overtimeAfterHours =
        cleanNonNegativeFloat(
            $attendanceSettings['overtimeAfterHours']
            ?? 0
        );
    
    $autoPunchOut =
        !empty(
            $attendanceSettings['autoPunchOut']
        ) ? 1 : 0;
    
    $autoPunchOutTime =
        trim((string)(
            $attendanceSettings['autoPunchOutTime']
            ?? ''
        ));
    
    $allowMultipleBreaks =
        !empty(
            $attendanceSettings['allowMultipleBreaks']
        ) ? 1 : 0;
        
    $autoBreakReminderEnabled =
        !empty(
            $attendanceSettings['autoBreakReminderEnabled']
        ) ? 1 : 0;    
    
    $weekendPolicy =
    (
        (string)(
            $attendanceSettings['weekendPolicy']
            ?? 'exclude'
        ) === 'include'
    )
    
    ? 'include'
    
    : 'exclude';
    
    // =========================
    // REQUIRED VALIDATION
    // =========================
    if (!$officeStartTime) {
    
        respond(
            false,
            'Office start time is required.'
        );
    }
    
    if (!$officeEndTime) {
    
        respond(
            false,
            'Office end time is required.'
        );
    }
    
    // =========================
    // BREAK TYPES
    // =========================
    $normalizedBreakTypes = [];
    
    $seenCodes = [];
    
    foreach ($breakTypes as $row) {
    
        if (!is_array($row)) {
    
            respond(
                false,
                'Invalid break type row.'
            );
        }
    
        $id = cleanNonNegativeInt(
            $row['id'] ?? 0
        );
    
        $breakName = trim((string)(
            $row['breakName'] ?? ''
        ));
    
        $breakCode = strtoupper(
            trim((string)(
                $row['breakCode'] ?? ''
            ))
        );
    
        $allowedMinutes =
            cleanNonNegativeInt(
                $row['allowedMinutes']
                ?? 0
            );
    
        $isPaid =
            !empty($row['isPaid'])
            ? 1
            : 0;
    
        $allowMultipleTimes =
            !empty($row['allowMultipleTimes'])
            ? 1
            : 0;
            
        $isScheduledBreak =
            !empty($row['isScheduledBreak'])
            ? 1
            : 0;
        
        $preferredStartTime =
            trim(
                (string)(
                    $row['preferredStartTime']
                    ?? ''
                )
            );
        
        if (
            $preferredStartTime === ''
        ) {
            $preferredStartTime = null;
        }
        
        if (
            $isScheduledBreak == 1 &&
            $preferredStartTime === null
        ) {
        
            respond(
                false,
                'Preferred time is required for scheduled break.'
            );
        }
    
        $isActive =
            !empty($row['isActive'])
            ? 1
            : 0;
    
        if (
            $breakName === '' ||
            $breakCode === ''
        ) {
    
            respond(
                false,
                'Break name and code are required.'
            );
        }
    
        if (
            isset($seenCodes[$breakCode])
        ) {
    
            respond(
                false,
                'Duplicate break code: ' .
                $breakCode
            );
        }
    
        $seenCodes[$breakCode] = true;
    
        $normalizedBreakTypes[] = [
    
            'id' => $id,
    
            'breakName' => $breakName,
    
            'breakCode' => $breakCode,
    
            'allowedMinutes' => $allowedMinutes,
    
            'isPaid' => $isPaid,
    
            'allowMultipleTimes' => $allowMultipleTimes,
    
            'isActive' => $isActive,
            
            'isScheduledBreak' => $isScheduledBreak,
            
            'preferredStartTime' => $preferredStartTime
        ];
    }
    
    
    // =========================
    // ONLY ONE SCHEDULED BREAK
    // =========================
    $scheduledBreakCount = 0;
    
    foreach ($normalizedBreakTypes as $bt) {
    
        if (
            $bt['isActive'] == 1 &&
            $bt['isScheduledBreak'] == 1
        ) {
    
            $scheduledBreakCount++;
        }
    }
    
    if ($scheduledBreakCount > 1) {
    
        respond(
            false,
            'Only one scheduled break is allowed.'
        );
    }
    
    // =========================
    // DB TRANSACTION
    // =========================
    mysqli_begin_transaction($con);
    
    try {
    
        // =========================
        // CHECK SETTINGS
        // =========================
        $check = mysqli_prepare(
            $con,
            'SELECT id FROM attendanceSettings LIMIT 1'
        );
    
        mysqli_stmt_execute($check);
    
        $exists = mysqli_stmt_get_result($check)
            ->fetch_assoc();
    
        mysqli_stmt_close($check);
    
        $workingDaysJson = json_encode(
            $workingDays
        );
    
        // =========================
        // UPDATE SETTINGS
        // =========================
        if ($exists) {
    
            $stmt = mysqli_prepare(
                $con,
                'UPDATE attendanceSettings SET
                    officeStartTime=?,
                    officeEndTime=?,
                    totalWorkingHours=?,
                    graceMinutes=?,
                    lateAfterMinutes=?,
                    halfDayHours=?,
                    overtimeAfterHours=?,
                    autoPunchOut=?,
                    autoPunchOutTime=?,
                    allowMultipleBreaks=?,
                    autoBreakReminderEnabled=?,
                    weekendPolicy=?,
                    workingDays=?,
                    setupCompleted=1
                WHERE id=?'
            );
    
            mysqli_stmt_bind_param(
                $stmt,
                'ssdiiddisiissi',
                $officeStartTime,
                $officeEndTime,
                $totalWorkingHours,
                $graceMinutes,
                $lateAfterMinutes,
                $halfDayHours,
                $overtimeAfterHours,
                $autoPunchOut,
                $autoPunchOutTime,
                $allowMultipleBreaks,
                $autoBreakReminderEnabled,
                $weekendPolicy,
                $workingDaysJson,
                $exists['id']
            );
        }
    
        // =========================
        // INSERT SETTINGS
        // =========================
        else {
    
            $stmt = mysqli_prepare(
                $con,
                'INSERT INTO attendanceSettings (
                    officeStartTime,
                    officeEndTime,
                    totalWorkingHours,
                    graceMinutes,
                    lateAfterMinutes,
                    halfDayHours,
                    overtimeAfterHours,
                    autoPunchOut,
                    autoPunchOutTime,
                    allowMultipleBreaks,
                    autoBreakReminderEnabled,
                    weekendPolicy,
                    workingDays,
                    setupCompleted
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1
                )'
            );
    
            mysqli_stmt_bind_param(
                $stmt,
                'ssdiiddisiiss',
                $officeStartTime,
                $officeEndTime,
                $totalWorkingHours,
                $graceMinutes,
                $lateAfterMinutes,
                $halfDayHours,
                $overtimeAfterHours,
                $autoPunchOut,
                $autoPunchOutTime,
                $allowMultipleBreaks,
                $autoBreakReminderEnabled,
                $weekendPolicy,
                $workingDaysJson
            );
        }
    
        mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        // =========================
        // PREPARE STATEMENTS
        // =========================
        $updateStmt = mysqli_prepare(
            $con,
            'UPDATE attendanceBreakTypes SET
                breakName=?,
                breakCode=?,
                allowedMinutes=?,
                isPaid=?,
                allowMultipleTimes=?,
                isScheduledBreak=?,
                preferredStartTime=?,
                isActive=?
            WHERE id=?'
        );
    
        $insertStmt = mysqli_prepare(
            $con,
            'INSERT INTO attendanceBreakTypes (
                breakName,
                breakCode,
                allowedMinutes,
                isPaid,
                allowMultipleTimes,
                isScheduledBreak,
                preferredStartTime,
                isActive
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?
            )'
        );
    
        // =========================
        // LOOP BREAK TYPES
        // =========================
        foreach ($normalizedBreakTypes as $bt) {
    
            // DELETE
            if (
                $bt['isActive'] == 0 &&
                $bt['id'] > 0
            ) {
    
                $del = mysqli_prepare(
                    $con,
                    'DELETE FROM attendanceBreakTypes
                     WHERE id=?'
                );
    
                mysqli_stmt_bind_param(
                    $del,
                    'i',
                    $bt['id']
                );
    
                mysqli_stmt_execute($del);
    
                mysqli_stmt_close($del);
    
                continue;
            }
    
            // UPDATE
            if ($bt['id'] > 0) {
    
                mysqli_stmt_bind_param(
                    $updateStmt,
                    'ssiiiisii',
                    $bt['breakName'],
                    $bt['breakCode'],
                    $bt['allowedMinutes'],
                    $bt['isPaid'],
                    $bt['allowMultipleTimes'],
                    $bt['isScheduledBreak'],
                    $bt['preferredStartTime'],
                    $bt['isActive'],
                    $bt['id']
                );
    
                mysqli_stmt_execute(
                    $updateStmt
                );
            }
    
            // INSERT
            else {
    
                mysqli_stmt_bind_param(
                    $insertStmt,
                    'ssiiiisi',
                    $bt['breakName'],
                    $bt['breakCode'],
                    $bt['allowedMinutes'],
                    $bt['isPaid'],
                    $bt['allowMultipleTimes'],
                    $bt['isScheduledBreak'],
                    $bt['preferredStartTime'],
                    $bt['isActive']
                );
    
                mysqli_stmt_execute(
                    $insertStmt
                );
            }
        }
    
        mysqli_stmt_close($updateStmt);
    
        mysqli_stmt_close($insertStmt);
    
        mysqli_commit($con);
    
        respond(
            true,
            'Attendance setup saved successfully.'
        );
    
    } catch (Throwable $e) {
    
        mysqli_rollback($con);
    
        respond(
            false,
            $e->getMessage()
        );
    }
?>