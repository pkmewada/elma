<?php

date_default_timezone_set('Asia/Kolkata');

class AttendanceEngine
{
    private $con;

    // =============================
    // CONSTRUCTOR
    // =============================
    public function __construct($con)
    {
        $this->con = $con;
    }

    // =============================
    // LOAD ATTENDANCE SETTINGS
    // =============================
    public function getSettings()
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM attendanceSettings
             LIMIT 1"
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();

        mysqli_stmt_close($stmt);

        return $result ?: [];
    }

    // =============================
    // GET TODAY ATTENDANCE
    // =============================
    public function getTodayAttendance($employeeId)
    {
        $currentDate = date('Y-m-d');

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM employeeAttendance
             WHERE employeeId=?
             AND attendanceDate=?
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "is",
            $employeeId,
            $currentDate
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();

        mysqli_stmt_close($stmt);

        return $result ?: null;
    }

    // =============================
    // GET ACTIVE BREAK
    // =============================
    public function getActiveBreak($attendanceId)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM attendanceBreakLogs
             WHERE attendanceId=?
             AND breakEndTime IS NULL
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $attendanceId
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();

        mysqli_stmt_close($stmt);

        return $result ?: null;
    }

    // =============================
    // VALIDATE BREAK TYPE
    // =============================
    public function validateBreakType($breakTypeId)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM attendanceBreakTypes
             WHERE id=?
             AND isActive=1
             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $breakTypeId
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();

        mysqli_stmt_close($stmt);

        return $result ?: null;
    }

    // =============================
    // CHECK PUNCHED IN
    // =============================
    public function isPunchedIn($employeeId)
    {
        $attendance =
            $this->getTodayAttendance(
                $employeeId
            );

        if (!$attendance) {
            return false;
        }

        return (
            !empty($attendance['punchInTime']) &&
            empty($attendance['punchOutTime'])
        );
    }


    // =============================
    // GET ATTENDANCE STATE
    // =============================
    public function getAttendanceState($employeeId)
    {
        
        $scheduledBreakReminder =
        $this->getScheduledBreakReminder(
            $employeeId
        );
        
        // =========================
        // LOAD TODAY ATTENDANCE
        // =========================
        $attendance =
            $this->getTodayAttendance(
                $employeeId
            );
    
        // =========================
        // NOT PUNCHED IN
        // =========================
        if (!$attendance) {
    
            return [

                'state' => 'not_punched_in',
            
                'attendance' => null,
            
                'workingSeconds' => 0,
            
                'breakSeconds' => 0,
            
                'activeBreak' => null,
            
                'scheduledBreakReminder' =>
                    $scheduledBreakReminder
            ];
        }
    
        // =========================
        // COMPLETED ATTENDANCE
        // =========================
        if (
            !empty($attendance['punchOutTime'])
        ) {
    
            return [
    
                'state' => 'completed',
    
                'attendance' => $attendance,
    
                // Final values already saved
                // during punch out
                'workingSeconds' =>
                   (int)$attendance['totalWorkingSeconds'],
    
                'breakSeconds' =>
                    (int)$attendance['totalBreakSeconds'],
    
                'activeBreak' => null,
                
                'scheduledBreakReminder' =>
                    $scheduledBreakReminder
            ];
        }
    
        // =========================
        // ACTIVE BREAK
        // =========================
        $activeBreak =
            $this->getActiveBreak(
                $attendance['id']
            );
    
        // =========================
        // LIVE CALCULATIONS
        // =========================
        $workingSeconds =
            $this->getCurrentWorkingSeconds(
                $attendance
            );
    
        $breakSeconds =
            $this->getCurrentBreakSeconds(
                $attendance['id']
            );
    
        // =========================
        // ON BREAK
        // =========================
        if ($activeBreak) {
    
            return [
    
                'state' => 'on_break',
    
                'attendance' => $attendance,
    
                'workingSeconds' =>
                    $workingSeconds,
    
                'breakSeconds' =>
                    $breakSeconds,
    
                'activeBreak' =>
                    $activeBreak,
                    
                'scheduledBreakReminder' =>
                    $scheduledBreakReminder
            ];
        }
    
        // =========================
        // ACTIVE WORKING SESSION
        // =========================
        return [
    
            'state' => 'punched_in',
    
            'attendance' => $attendance,
    
            'workingSeconds' =>
                $workingSeconds,
    
            'breakSeconds' =>
                $breakSeconds,
    
            'activeBreak' => null,
            
            'scheduledBreakReminder' =>
                $scheduledBreakReminder
        ];
    }

    
    // =============================
    // CALCULATE SECONDS
    // =============================
    private function calculateSeconds(
        $startTime,
        $endTime
    )
    {
        $start =
            strtotime($startTime);
    
        $end =
            strtotime($endTime);
    
        return max(
            0,
            $end - $start
        );
    }
    
    
    // =============================
    // GET CURRENT WORKING SECONDS
    // =============================
    private function getCurrentWorkingSeconds($attendance)
    {
        if (!$attendance) {
            return 0;
        }
    
        $punchInTimestamp =
            strtotime(
                $attendance['attendanceDate'] . ' ' .
                $attendance['punchInTime']
            );
    
        // =========================
        // END TIME
        // =========================
        if (!empty($attendance['punchOutTime'])) {
    
            $endTimestamp =
                strtotime(
                    $attendance['attendanceDate'] . ' ' .
                    $attendance['punchOutTime']
                );
        }
        else {
    
            $endTimestamp = time();
        }
    
        // =========================
        // FREEZE WHILE ON BREAK
        // =========================
        $activeBreak =
            $this->getActiveBreak(
                $attendance['id']
            );
    
        if ($activeBreak) {
    
            $endTimestamp =
                strtotime(
                    $attendance['attendanceDate'] . ' ' .
                    $activeBreak['breakStartTime']
                );
        }
    
        // =========================
        // TOTAL ATTENDANCE SECONDS
        // =========================
        $attendanceSeconds =
            max(
                0,
                $endTimestamp -
                $punchInTimestamp
            );
    
        // =========================
        // COMPLETED BREAK SECONDS
        // =========================
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                SUM(breakDurationSeconds) AS totalBreakSeconds
             FROM attendanceBreakLogs
             WHERE attendanceId=?"
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $attendance['id']
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();
    
        mysqli_stmt_close($stmt);
    
        $totalBreakSeconds =
            (int)(
                $result['totalBreakSeconds']
                ?? 0
            );
    
        return max(
            0,
            $attendanceSeconds -
            $totalBreakSeconds
        );
    }

    // =============================
    // GET CURRENT BREAK SECONDS
    // =============================
    private function getCurrentBreakSeconds($attendanceId)
    {
        $activeBreak =
            $this->getActiveBreak(
                $attendanceId
            );
    
        if (!$activeBreak) {
            return 0;
        }
    
        return $this->calculateSeconds(
            $activeBreak['breakStartTime'],
            date('H:i:s')
        );
    }
    
    // =============================
    // CHECK SCHEDULED BREAK
    // =============================
    private function getScheduledBreakReminder(
        $employeeId
    )
    {
        $settings =
            $this->getSettings();
    
        if (
            empty(
                $settings[
                    'autoBreakReminderEnabled'
                ]
            )
        ) {
    
            return null;
        }
    
        $attendance =
            $this->getTodayAttendance(
                $employeeId
            );
    
        if (!$attendance) {
    
            return null;
        }
    
        if (
            empty(
                $attendance['punchInTime']
            )
            ||
            !empty(
                $attendance['punchOutTime']
            )
        ) {
    
            return null;
        }
    
        // =========================
        // ACTIVE BREAK
        // =========================
        $activeBreak =
            $this->getActiveBreak(
                $attendance['id']
            );
    
        if ($activeBreak) {
    
            return null;
        }
    
        // =========================
        // SCHEDULED BREAK
        // =========================
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT
    
                id,
                breakName,
                preferredStartTime
    
             FROM attendanceBreakTypes
    
             WHERE isActive=1
             AND isScheduledBreak=1
    
             LIMIT 1"
        );
    
        mysqli_stmt_execute($stmt);
    
        $scheduledBreak =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();
    
        mysqli_stmt_close($stmt);
    
        if (!$scheduledBreak) {
    
            return null;
        }
    
        // =========================
        // ALREADY TAKEN
        // =========================
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT id
    
             FROM attendanceBreakLogs
    
             WHERE attendanceId=?
             AND breakTypeId=?
    
             LIMIT 1"
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "ii",
    
            $attendance['id'],
            $scheduledBreak['id']
        );
    
        mysqli_stmt_execute($stmt);
    
        $alreadyTaken =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();
    
        mysqli_stmt_close($stmt);
    
        if ($alreadyTaken) {
    
            return null;
        }
    
        // =========================
        // TIME WINDOW
        // =========================
        $now =
            strtotime(
                date('H:i:s')
            );
    
        $preferred =
            strtotime(
                $scheduledBreak[
                    'preferredStartTime'
                ]
            );
    
        $windowEnd =
            strtotime(
                '+30 minutes',
                $preferred
            );
    
        if (
            $now < $preferred
            ||
            $now > $windowEnd
        ) {
    
            return null;
        }
    
        return [
    
            'breakTypeId' =>
                (int)$scheduledBreak['id'],
    
            'breakName' =>
                $scheduledBreak['breakName'],
    
            'preferredStartTime' =>
                $scheduledBreak['preferredStartTime']
        ];
    }

    // =============================
    // PUNCH IN
    // =============================
    public function punchIn($employeeId)
    {
        // =========================
        // VALIDATE SETTINGS
        // =========================
        $settings =
            $this->getSettings();

        if (!$settings) {

            return [
                'success' => false,
                'message' => 'Attendance setup not configured'
            ];
        }

        // =========================
        // ALREADY PUNCHED IN
        // =========================
        if (
            $this->isPunchedIn($employeeId)
        ) {

            return [
                'success' => false,
                'message' => 'Already punched in'
            ];
        }

        // =========================
        // ALREADY COMPLETED
        // =========================
        $todayAttendance =
            $this->getTodayAttendance(
                $employeeId
            );

        if (
            $todayAttendance &&
            !empty($todayAttendance['punchOutTime'])
        ) {

            return [
                'success' => false,
                'message' => 'Attendance already completed for today'
            ];
        }

        // =========================
        // CURRENT VALUES
        // =========================
        $attendanceDate =
            date('Y-m-d');

        $punchInTime =
            date('H:i:s');

        // =========================
        // INSERT ATTENDANCE
        // =========================
        $stmt = mysqli_prepare(
            $this->con,
            "INSERT INTO employeeAttendance (

                employeeId,
                attendanceDate,
                punchInTime,
                attendanceStatus

            ) VALUES (

                ?,
                ?,
                ?,
                'in_progress'

            )"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "iss",
            $employeeId,
            $attendanceDate,
            $punchInTime
        );

        $success =
            mysqli_stmt_execute($stmt);

        // =========================
        // DUPLICATE HANDLING
        // =========================
        if (
            !$success &&
            mysqli_errno($this->con) == 1062
        ) {

            mysqli_stmt_close($stmt);

            return [
                'success' => false,
                'message' => 'Already punched in'
            ];
        }

        mysqli_stmt_close($stmt);

        if (!$success) {

            return [
                'success' => false,
                'message' => 'Failed to punch in'
            ];
        }

        return [
            'success' => true,
            'message' => 'Punch in Successful',
            'punchInTime' => $punchInTime
        ];
    }

    // =============================
    // START BREAK
    // =============================
    public function startBreak(
        $employeeId,
        $breakTypeId
    )
    {
        // =========================
        // VALIDATE BREAK TYPE
        // =========================
        $breakType =
            $this->validateBreakType(
                $breakTypeId
            );

        if (!$breakType) {

            return [
                'success' => false,
                'message' => 'Invalid break type'
            ];
        }

        // =========================
        // GET ATTENDANCE
        // =========================
        $attendance =
            $this->getTodayAttendance(
                $employeeId
            );

        if (!$attendance) {

            return [
                'success' => false,
                'message' => 'Attendance not found'
            ];
        }

        // =========================
        // VALIDATE PUNCH STATUS
        // =========================
        if (
            empty($attendance['punchInTime'])
        ) {

            return [
                'success' => false,
                'message' => 'Punch in required'
            ];
        }

        if (
            !empty($attendance['punchOutTime'])
        ) {

            return [
                'success' => false,
                'message' => 'Attendance already completed'
            ];
        }

        mysqli_begin_transaction(
            $this->con
        );

        try {

            // =====================
            // ACTIVE BREAK CHECK
            // =====================
            $activeBreak =
                $this->getActiveBreak(
                    $attendance['id']
                );

            if ($activeBreak) {

                mysqli_rollback(
                    $this->con
                );

                return [
                    'success' => false,
                    'message' => 'Break already active'
                ];
            }

            // =====================
            // START BREAK
            // =====================
            $breakStartTime =
                date('H:i:s');

            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO attendanceBreakLogs (

                    attendanceId,
                    breakTypeId,
                    breakStartTime

                ) VALUES (

                    ?,
                    ?,
                    ?

                )"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "iis",
                $attendance['id'],
                $breakTypeId,
                $breakStartTime
            );

            $success =
                mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);

            if (!$success) {

                mysqli_rollback(
                    $this->con
                );

                return [
                    'success' => false,
                    'message' => 'Failed to start break'
                ];
            }

            mysqli_commit(
                $this->con
            );

            return [
                'success' => true,
                'message' => 'Break started',
                'breakStartTime' => $breakStartTime,
                'breakType' => [
                    'id' => $breakType['id'],
                    'breakName' => $breakType['breakName']
                ]
            ];

        } catch (Exception $e) {

            mysqli_rollback(
                $this->con
            );

            // FIX #2: Log the exception
            error_log(
                'AttendanceEngine::startBreak() failed: ' .
                $e->getMessage() .
                ' in ' . $e->getFile() .
                ' on line ' . $e->getLine()
            );

            return [
                'success' => false,
                'message' => 'Failed to start break'
            ];
        }
    }

    // =============================
    // END BREAK
    // =============================
    public function endBreak($employeeId)
    {
        // =========================
        // GET ATTENDANCE
        // =========================
        $attendance =
            $this->getTodayAttendance(
                $employeeId
            );

        if (!$attendance) {

            return [
                'success' => false,
                'message' => 'Attendance not found'
            ];
        }

        mysqli_begin_transaction(
            $this->con
        );

        try {

            // =====================
            // ACTIVE BREAK
            // =====================
            $activeBreak =
                $this->getActiveBreak(
                    $attendance['id']
                );

            if (!$activeBreak) {

                mysqli_rollback(
                    $this->con
                );

                return [
                    'success' => false,
                    'message' => 'No active break found'
                ];
            }

            // =====================
            // BREAK DETAILS
            // =====================
            $breakEndTime =
                date('H:i:s');

            $breakDurationSeconds =
                $this->calculateSeconds(
                    $activeBreak['breakStartTime'],
                    $breakEndTime
                );

            // =====================
            // UPDATE BREAK
            // =====================
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE attendanceBreakLogs SET

                    breakEndTime=?,
                    breakDurationSeconds=?

                 WHERE id=?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sii",
                $breakEndTime,
                $breakDurationSeconds,
                $activeBreak['id']
            );

            $success =
                mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);

            if (!$success) {

                mysqli_rollback(
                    $this->con
                );

                return [
                    'success' => false,
                    'message' => 'Failed to end break'
                ];
            }

            mysqli_commit(
                $this->con
            );

            return [
                'success' => true,
                'message' => 'Break ended',
                'breakEndTime' => $breakEndTime,
                'breakDurationSeconds' => $breakDurationSeconds
            ];

        } catch (Exception $e) {

            mysqli_rollback(
                $this->con
            );

            // FIX #2: Log the exception
            error_log(
                'AttendanceEngine::endBreak() failed: ' .
                $e->getMessage() .
                ' in ' . $e->getFile() .
                ' on line ' . $e->getLine()
            );

            return [
                'success' => false,
                'message' => 'Failed to end break'
            ];
        }
    }
    
    
    // =============================
    // CHECK APPROVED HALF DAY LEAVE
    // =============================
    private function hasApprovedHalfDayLeave(
        $employeeId,
        $attendanceDate
    )
    {
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT lad.id

             FROM leaveApplicationDays lad

             INNER JOIN leaveApplications la
                ON la.id = lad.leaveApplicationId

             WHERE la.employeeId=?

             AND la.status <> 'cancelled'

             AND lad.status='approved'

             AND lad.dayType='half'

             AND lad.leaveDate = ?
    
             LIMIT 1"
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "is",
    
            $employeeId,
            $attendanceDate
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();
    
        mysqli_stmt_close($stmt);
    
        return !empty($result);
    }

    // =============================
    // PUNCH OUT
    // =============================
    public function punchOut($employeeId)
    {
        // =========================
        // GET SETTINGS
        // =========================
        $settings =
            $this->getSettings();

        if (!$settings) {

            return [
                'success' => false,
                'message' => 'Attendance setup not configured'
            ];
        }

        // =========================
        // GET ATTENDANCE
        // =========================
        $attendance =
            $this->getTodayAttendance(
                $employeeId
            );

        if (!$attendance) {

            return [
                'success' => false,
                'message' => 'Attendance not found'
            ];
        }

        // =========================
        // VALIDATE PUNCH STATUS
        // =========================
        if (
            empty($attendance['punchInTime'])
        ) {

            return [
                'success' => false,
                'message' => 'Punch in required'
            ];
        }

        if (
            !empty($attendance['punchOutTime'])
        ) {

            return [
                'success' => false,
                'message' => 'Already punched out'
            ];
        }

        mysqli_begin_transaction(
            $this->con
        );

        try {

            // =====================
            // ACTIVE BREAK
            // =====================
            $activeBreak =
                $this->getActiveBreak(
                    $attendance['id']
                );

            // =====================
            // AUTO END BREAK
            // =====================
            if ($activeBreak) {

                $breakEndTime =
                    date('H:i:s');

                $breakDurationSeconds =
                $this->calculateSeconds(
                    $activeBreak['breakStartTime'],
                    $breakEndTime
                );

                $stmt = mysqli_prepare(
                    $this->con,
                    "UPDATE attendanceBreakLogs SET

                        breakEndTime=?,
                        breakDurationSeconds=?

                     WHERE id=?"
                );

                mysqli_stmt_bind_param(
                    $stmt,
                    "sii",
                    $breakEndTime,
                    $breakDurationSeconds,
                    $activeBreak['id']
                );

                mysqli_stmt_execute($stmt);

                mysqli_stmt_close($stmt);
            }

            // =====================
            // TOTAL BREAK INTO SECONDS
            // =====================
            $stmt = mysqli_prepare(
                $this->con,
                "SELECT
                    SUM(breakDurationSeconds)
                        AS totalBreakSeconds

                 FROM attendanceBreakLogs

                 WHERE attendanceId=?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "i",
                $attendance['id']
            );

            mysqli_stmt_execute($stmt);

            $breakResult =
                mysqli_stmt_get_result($stmt)
                    ->fetch_assoc();

            mysqli_stmt_close($stmt);

            $totalBreakSeconds =
                (int)(
                    $breakResult['totalBreakSeconds']
                    ?? 0
                );

            // =====================
            // WORKING CALCULATION
            // =====================
            $punchOutTime =
                date('H:i:s');

            $totalWorkingSeconds =
                $this->calculateSeconds(
                    $attendance['punchInTime'],
                    $punchOutTime
                );

            $netWorkingSeconds =
                max(
                    0,
                    $totalWorkingSeconds -
                    $totalBreakSeconds
                );
                
            // =====================
            // EARLY PUNCH OUT VALIDATION
            // =====================
            if (
                !empty($settings['restrictEarlyPunchOut']) &&
                $settings['restrictEarlyPunchOut'] == 1
            ) {
            
                $currentTime =
                    strtotime(
                        $attendance['attendanceDate'] . ' ' .
                        $punchOutTime
                    );
            
                $officeEndTime =
                    strtotime(
                        $attendance['attendanceDate'] . ' ' .
                        $settings['officeEndTime']
                    );
            
                if ($currentTime < $officeEndTime) {
            
                    if (
                        empty($settings['requireApprovedHalfDay']) ||
                        $settings['requireApprovedHalfDay'] != 1
                    ) {
            
                        mysqli_rollback(
                            $this->con
                        );
            
                        return [
            
                            'success' => false,
            
                            'message' =>
                                'Early Punch Out is not allowed.'
                        ];
                    }
            
                    $hasHalfDayLeave =
                        $this->hasApprovedHalfDayLeave(
            
                            $employeeId,
            
                            $attendance['attendanceDate']
                        );
            
                    if (!$hasHalfDayLeave) {
            
                        mysqli_rollback(
                            $this->con
                        );
            
                        return [
            
                            'success' => false,
            
                            'message' =>
                                'Early punch out is not allowed. Please get your Half-Day leave approved first.'
                        ];
                    }
            
                    if ((float)$settings['minimumHalfDayHours'] <= 0) {

                        mysqli_rollback($this->con);
                    
                        return [
                    
                            'success'=>false,
                    
                            'message'=>'Invalid Half Day configuration.'
                    
                        ];
                    }
            
                    $requiredWorkingSeconds =
                        ((float)$settings['minimumHalfDayHours']) * 3600;
            
                    if (
                        $netWorkingSeconds <
                        $requiredWorkingSeconds
                    ) {
            
                        mysqli_rollback(
                            $this->con
                        );
            
                        return [
            
                            'success' => false,
            
                            'message' =>
                                'You must complete at least ' .
                                $settings['minimumHalfDayHours'] .
                                ' working hours before Punch Out.'
                        ];
                    }
                }
            }

            // =====================
            // ATTENDANCE STATUS
            // =====================
            $attendanceStatus = 'present';
            
            $halfDaySeconds =
                ((float)$settings['halfDayHours']) * 3600;
            
            if ($netWorkingSeconds < $halfDaySeconds) {
            
                $attendanceStatus =
                    'half_day';
            }

            // =====================
            // UPDATE ATTENDANCE
            // =====================
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE employeeAttendance SET

                    punchOutTime=?,
                    attendanceStatus=?,
                    totalBreakSeconds=?,
                    totalWorkingSeconds=?

                 WHERE id=?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "ssiii",
                $punchOutTime,
                $attendanceStatus,
                $totalBreakSeconds,
                $netWorkingSeconds,
                $attendance['id']
            );

            $success =
                mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);

            if (!$success) {

                mysqli_rollback(
                    $this->con
                );

                return [
                    'success' => false,
                    'message' => 'Failed to punch out'
                ];
            }

            mysqli_commit(
                $this->con
            );

            return [
                'success' => true,
                'message' => 'Punch out Successful',
                'punchOutTime' => $punchOutTime,
                'attendanceStatus' => $attendanceStatus,
                'totalBreakSeconds' => $totalBreakSeconds,
                'totalWorkingSeconds' => $netWorkingSeconds
            ];

        } catch (Exception $e) {

            mysqli_rollback(
                $this->con
            );

            // FIX #2: Log the exception
            error_log(
                'AttendanceEngine::punchOut() failed: ' .
                $e->getMessage() .
                ' in ' . $e->getFile() .
                ' on line ' . $e->getLine()
            );

            return [
                'success' => false,
                'message' => 'Failed to punch out'
            ];
        }
    }
    
    
    
    // =============================
    // CHECK HOLIDAY
    // =============================
    private function isHoliday($attendanceDate)
    {
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT id
    
             FROM eventHolidayMaster
    
             WHERE eventType='holiday'
    
             AND status='active'
    
             AND ? BETWEEN eventDate
             AND COALESCE(eventEndDate,eventDate)
    
             LIMIT 1"
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "s",
    
            $attendanceDate
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();
    
        mysqli_stmt_close($stmt);
    
        return !empty($result);
    }
    
    
    // =============================
    // CHECK SUNDAY
    // =============================
    private function isSunday($attendanceDate)
    {
        return (
            date(
                'w',
                strtotime($attendanceDate)
            ) == 0
        );
    }
    
    // =============================
    // CHECK APPROVED LEAVE
    // =============================
    private function hasApprovedLeave(
        $employeeId,
        $attendanceDate
    )
    {
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT lad.id

             FROM leaveApplicationDays lad

             INNER JOIN leaveApplications la
                ON la.id = lad.leaveApplicationId

             WHERE la.employeeId=?

             AND la.status <> 'cancelled'

             AND lad.status='approved'

             AND lad.leaveDate = ?
    
             LIMIT 1"
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "is",
    
            $employeeId,
            $attendanceDate
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();
    
        mysqli_stmt_close($stmt);
    
        return !empty($result);
    }
    
    // =============================
    // CREATE ABSENT ATTENDANCE
    // =============================
    private function createAbsentAttendance(
        $employeeId,
        $attendanceDate
    )
    {
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "INSERT INTO employeeAttendance (
    
                employeeId,
                attendanceDate,
                attendanceStatus,
                totalWorkingSeconds,
                totalBreakSeconds
    
            ) VALUES (
    
                ?,
                ?,
                'absent',
                0,
                0
    
            )"
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "is",
    
            $employeeId,
            $attendanceDate
        );
    
        $success =
            mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        if (!$success) {

            throw new Exception(
                'Failed to create absent attendance.'
            );
        }
        
        return true;
    }
    
    
    // =============================
    // AUTO MARK ABSENT
    // =============================
    public function markAutoAbsent()
    {
        // =========================
        // LOAD SETTINGS
        // =========================
        $settings =
            $this->getSettings();
    
        if (
            !$settings ||
            empty($settings['autoMarkAbsent'])
        ) {
            return;
        }
    
        // =========================
        // AUTO ABSENT TIME
        // =========================
        $currentTime =
            strtotime(
                date('H:i:s')
            );
    
        $autoAbsentTime =
            strtotime(
                $settings['autoAbsentTime']
            );
    
        if (
            $currentTime <
            $autoAbsentTime
        ) {
            return;
        }
    
        // =========================
        // ATTENDANCE DATE
        // =========================
        $attendanceDate =
            date('Y-m-d');
    
        // =========================
        // SKIP SUNDAY
        // =========================
        if (
            $this->isSunday(
                $attendanceDate
            )
        ) {
            return;
        }
    
        // =========================
        // SKIP HOLIDAY
        // =========================
        if (
            $this->isHoliday(
                $attendanceDate
            )
        ) {
            return;
        }
    
        // =========================
        // LOAD ACTIVE EMPLOYEES
        // =========================
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT id
    
             FROM employeeUsers
    
             WHERE employmentStatus='active'"
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        mysqli_begin_transaction(
            $this->con
        );
    
        try {
    
            while (
                $employee =
                    mysqli_fetch_assoc(
                        $result
                    )
            ) {
    
                // =====================
                // APPROVED LEAVE
                // =====================
                if (
                    $this->hasApprovedLeave(
                        $employee['id'],
                        $attendanceDate
                    )
                ) {
                    continue;
                }
    
                // =====================
                // ATTENDANCE EXISTS
                // =====================
                if (
                    $this->getTodayAttendance(
                        $employee['id']
                    )
                ) {
                    continue;
                }
    
                // =====================
                // CREATE ABSENT
                // =====================
                $this->createAbsentAttendance(
    
                    $employee['id'],
    
                    $attendanceDate
                );
            }
    
            mysqli_stmt_close($stmt);
    
            mysqli_commit(
                $this->con
            );
    
        } catch (Exception $e) {
    
            mysqli_stmt_close($stmt);
    
            mysqli_rollback(
                $this->con
            );
    
            // FIX #2: Log the exception
            error_log(
                'AttendanceEngine::markAutoAbsent() failed: ' .
                $e->getMessage() .
                ' in ' . $e->getFile() .
                ' on line ' . $e->getLine()
            );
        }
    }
    
    // =============================
    // AUTO PUNCH OUT FORGOTTEN ATTENDANCE
    // =============================
    public function autoPunchOutForgottenAttendance()
    {
        // =========================
        // AUTO PUNCH OUT TIME
        // =========================
        $autoPunchOutTime = '19:30:00';

        $currentTime =
            strtotime(
                date('H:i:s')
            );

        if (
            $currentTime <
            strtotime($autoPunchOutTime)
        ) {
            return [
                'success' => false,
                'message' => 'Auto punch out time not reached yet'
            ];
        }

        // =========================
        // LOAD SETTINGS
        // =========================
        $settings =
            $this->getSettings();

        if (!$settings) {

            return [
                'success' => false,
                'message' => 'Attendance setup not configured'
            ];
        }

        $halfDaySeconds =
            ((float)$settings['halfDayHours']) * 3600;

        // =========================
        // LOAD FORGOTTEN PUNCH-INS
        // =========================
        $stmt = mysqli_prepare(

            $this->con,

            "SELECT *

             FROM employeeAttendance

             WHERE attendanceStatus='in_progress'

             AND punchInTime IS NOT NULL

             AND punchOutTime IS NULL"
        );

        mysqli_stmt_execute($stmt);

        $result =
            mysqli_stmt_get_result($stmt);

        $attendanceRows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $attendanceRows[] = $row;
        }

        mysqli_stmt_close($stmt);

        $processed = [];
        $failed = [];

        // =========================
        // PROCESS EACH RECORD
        // =========================
        foreach ($attendanceRows as $attendance) {

            mysqli_begin_transaction(
                $this->con
            );

            try {

                $closed =
                    $this->closeForgottenAttendance(
                        $attendance,
                        $autoPunchOutTime,
                        $halfDaySeconds,
                        'Auto punched out by system (forgot to punch out)'
                    );

                mysqli_commit(
                    $this->con
                );

                $processed[] = [
                    'attendanceId' => $attendance['id'],
                    'employeeId' => $attendance['employeeId'],
                    'punchOutTime' => $autoPunchOutTime,
                    'attendanceStatus' => $closed['attendanceStatus'],
                    'totalWorkingSeconds' => $closed['totalWorkingSeconds'],
                    'totalBreakSeconds' => $closed['totalBreakSeconds']
                ];

            } catch (Exception $e) {

                mysqli_rollback(
                    $this->con
                );

                error_log(
                    'AttendanceEngine::autoPunchOutForgottenAttendance() failed for attendanceId ' .
                    $attendance['id'] . ': ' .
                    $e->getMessage()
                );

                $failed[] = [
                    'attendanceId' => $attendance['id'],
                    'employeeId' => $attendance['employeeId'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Auto punch out completed',
            'totalFound' => count($attendanceRows),
            'totalProcessed' => count($processed),
            'totalFailed' => count($failed),
            'processed' => $processed,
            'failed' => $failed
        ];
    }

    // =============================
    // CLOSE ONE FORGOTTEN PUNCH-IN
    // =============================
    // Shared by autoPunchOutForgottenAttendance() (cron) and
    // adminPunchOutForgottenAttendance(). The caller owns the transaction.
    private function closeForgottenAttendance(
        array $attendance,
        $autoPunchOutTime,
        $halfDaySeconds,
        $remarks
    )
    {
        // =====================
        // AUTO END ACTIVE BREAK
        // =====================
        $activeBreak =
            $this->getActiveBreak(
                $attendance['id']
            );

        if ($activeBreak) {

            $breakDurationSeconds =
                $this->calculateSeconds(
                    $activeBreak['breakStartTime'],
                    $autoPunchOutTime
                );

            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE attendanceBreakLogs SET

                    breakEndTime=?,
                    breakDurationSeconds=?

                 WHERE id=?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                "sii",
                $autoPunchOutTime,
                $breakDurationSeconds,
                $activeBreak['id']
            );

            mysqli_stmt_execute($stmt);

            mysqli_stmt_close($stmt);
        }

        // =====================
        // TOTAL BREAK INTO SECONDS
        // =====================
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                SUM(breakDurationSeconds)
                    AS totalBreakSeconds

             FROM attendanceBreakLogs

             WHERE attendanceId=?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $attendance['id']
        );

        mysqli_stmt_execute($stmt);

        $breakResult =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();

        mysqli_stmt_close($stmt);

        $totalBreakSeconds =
            (int)(
                $breakResult['totalBreakSeconds']
                ?? 0
            );

        // =====================
        // WORKING CALCULATION
        // =====================
        $totalWorkingSeconds =
            $this->calculateSeconds(
                $attendance['punchInTime'],
                $autoPunchOutTime
            );

        $netWorkingSeconds =
            max(
                0,
                $totalWorkingSeconds -
                $totalBreakSeconds
            );

        // =====================
        // ATTENDANCE STATUS
        // =====================
        $attendanceStatus = 'present';

        if ($netWorkingSeconds < $halfDaySeconds) {

            $attendanceStatus =
                'half_day';
        }

        // =====================
        // UPDATE ATTENDANCE
        // =====================
        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE employeeAttendance SET

                punchOutTime=?,
                attendanceStatus=?,
                totalBreakSeconds=?,
                totalWorkingSeconds=?,
                remarks=?

             WHERE id=?"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "ssiisi",
            $autoPunchOutTime,
            $attendanceStatus,
            $totalBreakSeconds,
            $netWorkingSeconds,
            $remarks,
            $attendance['id']
        );

        $success =
            mysqli_stmt_execute($stmt);

        mysqli_stmt_close($stmt);

        if (!$success) {

            throw new Exception(
                'Failed to update attendance'
            );
        }

        return [
            'attendanceStatus' => $attendanceStatus,
            'totalWorkingSeconds' => $netWorkingSeconds,
            'totalBreakSeconds' => $totalBreakSeconds
        ];
    }

    // =============================
    // ADMIN PUNCH OUT (FORGOTTEN)
    // =============================
    // Admin closes one forgotten 'in_progress' record from the Attendance
    // Analytics table: punch out at 07:00 PM, same calculation as the cron.
    public function adminPunchOutForgottenAttendance($attendanceId)
    {
        $punchOutTime = '19:00:00';

        $settings =
            $this->getSettings();

        if (!$settings) {

            return [
                'success' => false,
                'message' => 'Attendance setup not configured'
            ];
        }

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *

             FROM employeeAttendance

             WHERE id=?

             AND attendanceStatus='in_progress'

             AND punchInTime IS NOT NULL

             AND punchOutTime IS NULL

             LIMIT 1"
        );

        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $attendanceId
        );

        mysqli_stmt_execute($stmt);

        $attendance =
            mysqli_stmt_get_result($stmt)
                ->fetch_assoc();

        mysqli_stmt_close($stmt);

        if (!$attendance) {

            return [
                'success' => false,
                'message' => 'This attendance is not pending punch out'
            ];
        }

        // Today's employee may still be working until 07:00 PM.
        if (
            $attendance['attendanceDate'] === date('Y-m-d') &&
            time() < strtotime($punchOutTime)
        ) {
            return [
                'success' => false,
                'message' => "Today's attendance can be closed after 07:00 PM"
            ];
        }

        mysqli_begin_transaction(
            $this->con
        );

        try {

            $closed =
                $this->closeForgottenAttendance(
                    $attendance,
                    $punchOutTime,
                    ((float)$settings['halfDayHours']) * 3600,
                    'Punched out by admin at 07:00 PM (forgot to punch out)'
                );

            mysqli_commit(
                $this->con
            );

        } catch (Exception $e) {

            mysqli_rollback(
                $this->con
            );

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'message' => 'Punch out marked at 07:00 PM',
            'data' => $closed
        ];
    }

    // =============================
    // LIVE BREAK SECONDS
    // =============================
    public function getLiveBreakSeconds($attendanceId, $attendanceDate)
    {
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT
    
                breakStartTime,
                breakEndTime,
                breakDurationSeconds
    
             FROM attendanceBreakLogs
    
             WHERE attendanceId=?"
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $attendanceId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        mysqli_stmt_close($stmt);
    
        $totalBreakSeconds = 0;
    
        while ($break = mysqli_fetch_assoc($result)) {
    
            if (!empty($break['breakEndTime'])) {
    
                $totalBreakSeconds +=
                    (int)$break['breakDurationSeconds'];
            } else {
    
                $totalBreakSeconds +=
                    max(
                        0,
                        strtotime(date('Y-m-d H:i:s')) -
                        strtotime(
                            $attendanceDate . ' ' .
                            $break['breakStartTime']
                        )
                    );
            }
        }
    
        return $totalBreakSeconds;
    }
    
    // =============================
    // LIVE WORKING SECONDS
    // =============================
    public function getLiveWorkingSeconds($attendance)
    {
        if (
    
            empty($attendance['punchInTime'])
    
        ) {
    
            return 0;
        }
    
        $workingSeconds =
    
            strtotime(
                date('Y-m-d H:i:s')
            )
    
            -
    
            strtotime(
                $attendance['attendanceDate'] .
                ' ' .
                $attendance['punchInTime']
            );
    
        $breakSeconds =
            $this->getLiveBreakSeconds(
    
                $attendance['id'],
    
                $attendance['attendanceDate']
            );
    
        return max(
    
            0,
    
            $workingSeconds -
    
            $breakSeconds
        );
    }
}

?>
