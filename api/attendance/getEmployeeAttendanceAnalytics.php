<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

try {

    $employeeId =
        (int)(
            $_GET['employeeId']
            ?? 0
        );

    $fromDate =
        trim(
            $_GET['fromDate']
            ?? ''
        );

    $toDate =
        trim(
            $_GET['toDate']
            ?? ''
        );

    if (
        $employeeId <= 0
    ) {

        throw new Exception(
            'Employee required'
        );
    }

    if (
        empty($fromDate)
        ||
        empty($toDate)
    ) {

        throw new Exception(
            'Date range required'
        );
    }

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

         WHERE employeeId=?

         AND attendanceDate
         BETWEEN ? AND ?

         ORDER BY attendanceDate ASC"
    );

    mysqli_stmt_bind_param(

        $stmt,

        "iss",

        $employeeId,
        $fromDate,
        $toDate
    );

    mysqli_stmt_execute(
        $stmt
    );

    $result =
        mysqli_stmt_get_result(
            $stmt
        );

    $rows = [];

    $summary = [

        'workingSeconds' => 0,

        'breakSeconds' => 0,

        // Same per-status classification as
        // PayrollEngine::getAttendanceSummary() -- 'late' and
        // 'in_progress' (punched in, not yet punched out) both count
        // towards presentDays there, and 'late' is additionally its own
        // count here. Previously this endpoint only matched the literal
        // 'present' status, so late/in_progress days were silently
        // dropped from the Present count, and absentDays/lateDays were
        // never computed here at all.
        'presentDays' => 0,

        'halfDays' => 0,

        'absentDays' => 0,

        'lateDays' => 0,

        'leaveDays' => 0
    ];

    while (
        $row =
            mysqli_fetch_assoc(
                $result
            )
    ) {

        $row['breakStartTime'] = null;
        $row['breakEndTime'] = null;
        $row['breaks'] = [];

        $rows[] = $row;

        $summary[
            'workingSeconds'
        ] +=
            (int)$row[
                'totalWorkingSeconds'
            ];

        $summary[
            'breakSeconds'
        ] +=
            (int)$row[
                'totalBreakSeconds'
            ];

        $status = strtolower((string)($row['attendanceStatus'] ?? ''));

        if ($status === 'half_day') {

            $summary['halfDays']++;

        } elseif ($status === 'absent') {

            $summary['absentDays']++;

        } elseif ($status === 'late') {

            $summary['lateDays']++;
            $summary['presentDays']++;

        } elseif ($status !== '') {

            // 'present', 'in_progress', or any other non-empty status
            $summary['presentDays']++;
        }
    }

    // Break Start/End per day for the details table -- earliest start and
    // latest end across that day's break logs (same attendanceBreakLogs
    // table getAttendanceBreakHistoryAdmin.php reads). totalBreakSeconds
    // above already comes from employeeAttendance and stays the source of
    // truth for the actual break DURATION (it is AttendanceEngine's
    // SUM(breakDurationSeconds), correct even with gaps between multiple
    // breaks); this only adds a start/end range for display.
    $breakRangeStmt = mysqli_prepare(
        $con,
        "SELECT
            abl.attendanceId,
            MIN(abl.breakStartTime) AS breakStartTime,
            MAX(abl.breakEndTime) AS breakEndTime,
            MAX(abl.breakEndTime IS NULL) AS hasOpenBreak
         FROM attendanceBreakLogs abl
         INNER JOIN employeeAttendance ea ON ea.id = abl.attendanceId
         WHERE ea.employeeId = ?
         AND ea.attendanceDate BETWEEN ? AND ?
         GROUP BY abl.attendanceId"
    );

    mysqli_stmt_bind_param($breakRangeStmt, 'iss', $employeeId, $fromDate, $toDate);
    mysqli_stmt_execute($breakRangeStmt);
    $breakRangeResult = mysqli_stmt_get_result($breakRangeStmt);

    $breakRangeByAttendanceId = [];

    while ($breakRow = mysqli_fetch_assoc($breakRangeResult)) {
        $breakRangeByAttendanceId[(int)$breakRow['attendanceId']] = $breakRow;
    }

    mysqli_stmt_close($breakRangeStmt);

    foreach ($rows as &$rowRef) {
        $attendanceId = (int)$rowRef['id'];

        if (isset($breakRangeByAttendanceId[$attendanceId])) {
            $breakRange = $breakRangeByAttendanceId[$attendanceId];

            $rowRef['breakStartTime'] = $breakRange['breakStartTime'];

            // A still-open break (no end time yet) is always the LAST one
            // that day -- only one break can be active at a time (see
            // AttendanceEngine's activeBreak checks) -- so MAX(breakEndTime)
            // over the other, already-closed breaks would otherwise show a
            // stale "ended" time for a break that hasn't actually ended.
            $rowRef['breakEndTime'] = $breakRange['hasOpenBreak'] ? null : $breakRange['breakEndTime'];
        }
    }
    unset($rowRef);

    $breakDetailsStmt = mysqli_prepare(
        $con,
        "SELECT
            abl.attendanceId,
            abl.breakStartTime,
            abl.breakEndTime,
            abt.breakName,
            abt.breakCode
         FROM attendanceBreakLogs abl
         LEFT JOIN attendanceBreakTypes abt ON abt.id = abl.breakTypeId
         INNER JOIN employeeAttendance ea ON ea.id = abl.attendanceId
         WHERE ea.employeeId = ?
         AND ea.attendanceDate BETWEEN ? AND ?
         ORDER BY abl.attendanceId ASC, abl.breakStartTime ASC, abl.id ASC"
    );

    mysqli_stmt_bind_param($breakDetailsStmt, 'iss', $employeeId, $fromDate, $toDate);
    mysqli_stmt_execute($breakDetailsStmt);
    $breakDetailsResult = mysqli_stmt_get_result($breakDetailsStmt);

    $breaksByAttendanceId = [];

    while ($break = mysqli_fetch_assoc($breakDetailsResult)) {
        $breaksByAttendanceId[(int)$break['attendanceId']][] = [
            'breakName' => $break['breakName'],
            'breakCode' => $break['breakCode'],
            'startTime' => $break['breakStartTime'],
            'endTime' => $break['breakEndTime'],
        ];
    }

    mysqli_stmt_close($breakDetailsStmt);

    foreach ($rows as &$rowRef) {
        $rowRef['breaks'] = $breaksByAttendanceId[(int)$rowRef['id']] ?? [];
    }
    unset($rowRef);

    // Approved leave days in the range -- same child-date source
    // PayrollEngine::getApprovedLeaveDates() uses for payroll.
    $leaveStmt = mysqli_prepare(
        $con,
        "SELECT lad.leaveDate
         FROM leaveApplicationDays lad
         INNER JOIN leaveApplications la
            ON la.id = lad.leaveApplicationId
         WHERE la.employeeId = ?
         AND la.status <> 'cancelled'
         AND lad.status = 'approved'
         AND lad.leaveDate BETWEEN ? AND ?"
    );

    mysqli_stmt_bind_param($leaveStmt, 'iss', $employeeId, $fromDate, $toDate);
    mysqli_stmt_execute($leaveStmt);
    $leaveResult = mysqli_stmt_get_result($leaveStmt);

    $leaveDates = [];

    while ($leaveRow = mysqli_fetch_assoc($leaveResult)) {
        $leaveDates[(string)$leaveRow['leaveDate']] = true;
    }

    mysqli_stmt_close($leaveStmt);

    $summary['leaveDays'] = count($leaveDates);

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
