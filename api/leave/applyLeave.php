<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leave-application-days.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$loggedInUserId = (int)($_SESSION['userId'] ?? 0);

if ($loggedInUserId <= 0) {
    respond(false, 'Invalid session');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input)) {
    respond(false, 'Invalid request payload');
}

$employeeId = (int)($input['employeeId'] ?? 0);
$leaveTypeId = (int)($input['leaveTypeId'] ?? 0);
$fromDate = trim((string)($input['fromDate'] ?? ''));
$toDate = trim((string)($input['toDate'] ?? ''));
$dayType = strtolower(trim((string)($input['dayType'] ?? 'full')));
$reason = trim((string)($input['reason'] ?? ''));

if ($toDate === '') {
    $toDate = $fromDate;
}

if (!in_array($dayType, ['full', 'half'], true)) {
    $dayType = 'full';
}

if ($employeeId <= 0 || $leaveTypeId <= 0 || $fromDate === '') {
    respond(false, 'All fields are required');
}

$fromDateObj = DateTime::createFromFormat('Y-m-d', $fromDate);
$toDateObj = DateTime::createFromFormat('Y-m-d', $toDate);

if (
    !$fromDateObj ||
    !$toDateObj ||
    $fromDateObj->format('Y-m-d') !== $fromDate ||
    $toDateObj->format('Y-m-d') !== $toDate
) {
    respond(false, 'Invalid date format');
}

if ($fromDate > $toDate) {
    respond(false, 'Invalid date range');
}

$employeeStmt = mysqli_prepare(
    $con,
    "SELECT gender FROM employeeusers WHERE id=? AND employmentStatus='Active' LIMIT 1"
);

if (!$employeeStmt) {
    respond(false, 'Failed to validate employee');
}

mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
mysqli_stmt_execute($employeeStmt);

$employeeRes = mysqli_stmt_get_result($employeeStmt);
$employee = mysqli_fetch_assoc($employeeRes);
mysqli_stmt_close($employeeStmt);

if (!$employee) {
    respond(false, 'Invalid employee selected');
}

$settingsStmt = mysqli_prepare(
    $con,
    "SELECT workingDays, weekendPolicy, sandwichRule, maxLeavesPerRequest
     FROM leaveSettings
     WHERE setupCompleted = 1
     ORDER BY id DESC
     LIMIT 1"
);

if (!$settingsStmt) {
    respond(false, 'Failed to load settings');
}

mysqli_stmt_execute($settingsStmt);
$settingsRes = mysqli_stmt_get_result($settingsStmt);
$settings = mysqli_fetch_assoc($settingsRes);
mysqli_stmt_close($settingsStmt);

if (!$settings) {
    respond(false, 'Leave setup not configured');
}

$workingDays = normalizeLeaveWorkingDays($settings['workingDays'] ?? []);
$weekendPolicy = $settings['weekendPolicy'] === 'include' ? 'include' : 'exclude';

$typeStmt = mysqli_prepare(
    $con,
    "SELECT maxConsecutiveDays, applicableGender, allowNegative, allowHalfDay
     FROM leaveTypes
     WHERE id=? AND isActive = 1
     LIMIT 1"
);

if (!$typeStmt) {
    respond(false, 'Failed to load leave type');
}

mysqli_stmt_bind_param($typeStmt, 'i', $leaveTypeId);
mysqli_stmt_execute($typeStmt);

$typeRes = mysqli_stmt_get_result($typeStmt);
$type = mysqli_fetch_assoc($typeRes);
mysqli_stmt_close($typeStmt);

if (!$type) {
    respond(false, 'Invalid leave type');
}

$dateRows = calculateLeaveDateRows($fromDate, $toDate, $dayType, $workingDays, $weekendPolicy);
$totalDays = getLeaveDateRowsTotal($dateRows);

if ($totalDays <= 0) {
    respond(false, 'No valid leave days selected');
}

if ($dayType === 'half') {
    if ($fromDate !== $toDate) {
        respond(false, 'Half day leave can be applied for a single date only');
    }

    if ((int)$type['allowHalfDay'] !== 1) {
        respond(false, 'Half day leave is not allowed for selected leave type');
    }
}

if (
    (int)$settings['maxLeavesPerRequest'] > 0 &&
    $totalDays > (int)$settings['maxLeavesPerRequest']
) {
    respond(false, 'Exceeds maximum leaves per request');
}

// $today = new DateTime(date('Y-m-d'));

// if ($fromDateObj >= $today && (int)$settings['minNoticeDays'] > 0) {
//     $diffDays = (int)$today->diff($fromDateObj)->format('%r%a');

//     if ($diffDays < (int)$settings['minNoticeDays']) {
//         respond(false, 'Apply leave in advance');
//     }
// }

if (
    (int)$type['maxConsecutiveDays'] > 0 &&
    $totalDays > (int)$type['maxConsecutiveDays']
) {
    respond(false, 'Exceeds max consecutive limit');
}

$employeeGender = strtolower((string)($employee['gender'] ?? 'all'));
$applicableGender = strtolower((string)($type['applicableGender'] ?? 'all'));

if ($applicableGender !== 'all' && $applicableGender !== $employeeGender) {
    respond(false, 'Leave not allowed for selected employee gender');
}

$overlapStmt = mysqli_prepare(
    $con,
    "SELECT id
     FROM leaveApplications
     WHERE employeeId=?
       AND status IN ('pending','approved','partial')
       AND fromDate <= ?
       AND toDate >= ?
     LIMIT 1"
);

if (!$overlapStmt) {
    respond(false, 'Failed to validate leave overlap');
}

mysqli_stmt_bind_param($overlapStmt, 'iss', $employeeId, $toDate, $fromDate);
mysqli_stmt_execute($overlapStmt);

$overlapRes = mysqli_stmt_get_result($overlapStmt);

if (mysqli_fetch_assoc($overlapRes)) {
    mysqli_stmt_close($overlapStmt);
    respond(false, 'You already have a leave in this date range');
}

mysqli_stmt_close($overlapStmt);

$balance = getOrCreateBalance($con, $employeeId, $leaveTypeId);

if ((int)$type['allowNegative'] !== 1 && (float)$balance['remainingLeaves'] < $totalDays) {
    respond(false, 'Insufficient leave balance');
}

$leaveId = 0;
$legacyCompanyId = 0;

mysqli_begin_transaction($con);

try {
    $insertStmt = mysqli_prepare(
        $con,
        "INSERT INTO leaveApplications
         (companyId, employeeId, leaveTypeId, fromDate, toDate, totalDays, dayType, reason)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$insertStmt) {
        throw new Exception('Failed to prepare leave application');
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        'iiissdss',
        $legacyCompanyId,
        $employeeId,
        $leaveTypeId,
        $fromDate,
        $toDate,
        $totalDays,
        $dayType,
        $reason
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        throw new Exception('Failed to apply leave');
    }

    $leaveId = mysqli_insert_id($con);
    mysqli_stmt_close($insertStmt);

    insertLeaveApplicationDays(
        $con,
        $leaveId,
        $dateRows,
        'pending'
    );

    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    respond(false, $e->getMessage());
}

$mailSent = false;

$mailStmt = mysqli_prepare(
    $con,
    "SELECT
        eu.emailAddress,
        eu.fullName,
        lt.name AS leaveTypeName
     FROM leaveApplications la
     LEFT JOIN employeeusers eu ON eu.id = la.employeeId
     LEFT JOIN leaveTypes lt ON lt.id = la.leaveTypeId
     WHERE la.id=? LIMIT 1"
);

if ($mailStmt) {
    mysqli_stmt_bind_param($mailStmt, 'i', $leaveId);
    mysqli_stmt_execute($mailStmt);

    $mailRes = mysqli_stmt_get_result($mailStmt);
    $mailRow = mysqli_fetch_assoc($mailRes);
    mysqli_stmt_close($mailStmt);

    $employeeEmail = trim((string)($mailRow['emailAddress'] ?? ''));
    $employeeName = (string)($mailRow['fullName'] ?? '');
    $leaveTypeName = (string)($mailRow['leaveTypeName'] ?? '');

    try {
        if ($employeeEmail === '') {
            writeMailLog("LEAVE APPLY ERROR: Email missing for LeaveID {$leaveId}");
        } else {
            writeMailLog("LEAVE APPLY: Sending mail to {$employeeEmail}");

            $mailSent = sendLeaveAppliedEmail(
                $leaveId,
                $employeeEmail,
                $employeeName,
                $leaveTypeName,
                $fromDate,
                $toDate,
                $dayType
            );

            if ($mailSent) {
                writeMailLog("LEAVE APPLY SUCCESS: Mail sent to {$employeeEmail}");
            } else {
                writeMailLog("LEAVE APPLY FAILED: Mail function returned false | {$employeeEmail}");
            }
        }
    } catch (Throwable $e) {
        writeMailLog("LEAVE APPLY EXCEPTION: " . $e->getMessage());
    }
} else {
    writeMailLog("LEAVE APPLY ERROR: Failed to load mail data for LeaveID {$leaveId}");
}

respond(true, 'Leave applied successfully', [
    'leaveId' => $leaveId,
    'totalDays' => $totalDays,
    'dayType' => $dayType,
    'mailSent' => $mailSent
]);
