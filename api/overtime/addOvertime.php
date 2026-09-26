<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

function respond($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$employeeId = (int) ($_POST['employeeId'] ?? 0);
$date = $_POST['date'] ?? '';
$startTime = $_POST['startTime'] ?? '';
$endTime = $_POST['endTime'] ?? '';
$reason = trim($_POST['reason'] ?? '');

if (!$employeeId || !$date || !$startTime || !$endTime) {
    respond(false, 'All required fields are missing');
}

if ($startTime >= $endTime) {
    respond(false, 'End time must be greater than start time');
}

////////////////////////////////////////////////////////////
// GET EMPLOYEE
////////////////////////////////////////////////////////////
$empSql = "SELECT fullName FROM employeeusers WHERE id=?";
$empStmt = mysqli_prepare($con, $empSql);

if (!$empStmt) {
    respond(false, 'Failed to fetch employee');
}

mysqli_stmt_bind_param($empStmt, 'i', $employeeId);
mysqli_stmt_execute($empStmt);
$empRes = mysqli_stmt_get_result($empStmt);
$emp = mysqli_fetch_assoc($empRes);
mysqli_stmt_close($empStmt);

if (!$emp) {
    respond(false, 'Invalid employee');
}

$employeeName = $emp['fullName'];

////////////////////////////////////////////////////////////
// CALCULATE HOURS
////////////////////////////////////////////////////////////
$start = strtotime($startTime);
$end = strtotime($endTime);

$totalHours = round(($end - $start) / 3600, 2);

////////////////////////////////////////////////////////////
// GET ACTIVE SETTINGS (NO companyId)
////////////////////////////////////////////////////////////
$setSql = "SELECT * FROM overtimeSettings 
           WHERE status='active' 
           ORDER BY effectiveFrom DESC LIMIT 1";

$setRes = mysqli_query($con, $setSql);
$settings = $setRes ? mysqli_fetch_assoc($setRes) : null;

$otHours = 0;

if ($settings && $totalHours >= $settings['minHoursRequired']) {

    $otHours = $totalHours;

    // max cap
    if ($settings['maxHoursPerDay'] > 0) {
        $otHours = min($otHours, $settings['maxHoursPerDay']);
    }

    // rounding
    switch ($settings['roundingRule']) {
        case '15min':
            $otHours = round($otHours * 4) / 4;
            break;
        case '30min':
            $otHours = round($otHours * 2) / 2;
            break;
        default:
            $otHours = round($otHours, 2);
    }
}

////////////////////////////////////////////////////////////
// STATUS
////////////////////////////////////////////////////////////
$status = (!empty($settings['autoApprove']) && $settings['autoApprove'] == 1)
    ? 'approved'
    : 'pending';

////////////////////////////////////////////////////////////
// INSERT
////////////////////////////////////////////////////////////
$insertSql = "
INSERT INTO overtimeRequests
(employeeId, date, startTime, endTime, totalHours, calculatedOtHours, reason, status)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($con, $insertSql);

if (!$stmt) {
    respond(false, 'Failed to prepare insert');
}

mysqli_stmt_bind_param(
    $stmt,
    'isssddss',
    $employeeId,
    $date,
    $startTime,
    $endTime,
    $totalHours,
    $otHours,
    $reason,
    $status
);

$ok = mysqli_stmt_execute($stmt);

if (!$ok) {
    mysqli_stmt_close($stmt);
    respond(false, 'Failed to save overtime');
}

$insertId = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

////////////////////////////////////////////////////////////
// SEND EMAIL (AFTER SUCCESS)
////////////////////////////////////////////////////////////
$emailSql = "SELECT emailAddress AS email, fullName FROM employeeusers WHERE id=?";
$stmtE = mysqli_prepare($con, $emailSql);

mysqli_stmt_bind_param($stmtE, 'i', $employeeId);
mysqli_stmt_execute($stmtE);
$resE = mysqli_stmt_get_result($stmtE);
$user = mysqli_fetch_assoc($resE);
mysqli_stmt_close($stmtE);

if ($user && !empty($user['email'])) {
    sendOvertimeAppliedEmail(
        $insertId,
        $user['email'],
        $user['fullName'],
        $date,
        $totalHours
    );
}

////////////////////////////////////////////////////////////
// RESPONSE
////////////////////////////////////////////////////////////
$data = [
    'id' => $insertId,
    'employeeName' => $employeeName,
    'date' => $date,
    'startTime' => $startTime,
    'endTime' => $endTime,
    'totalHours' => number_format($totalHours, 2),
    'otHours' => number_format($otHours, 2),
    'status' => $status
];

respond(true, 'Overtime added successfully', $data);