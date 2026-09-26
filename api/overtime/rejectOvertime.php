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

$id = (int) ($_POST['id'] ?? 0);
$remarks = trim($_POST['remarks'] ?? '');

if ($id <= 0) {
    respond(false, 'Invalid request ID');
}

if ($remarks === '') {
    respond(false, 'Remarks required');
}

////////////////////////////////////////////////////////////
// UPDATE (ONLY IF NOT ALREADY REJECTED)
////////////////////////////////////////////////////////////
$sql = "UPDATE overtimeRequests 
        SET status='rejected', remarks=? 
        WHERE id=? AND status!='rejected'";

$stmt = mysqli_prepare($con, $sql);

if (!$stmt) {
    respond(false, 'Failed to prepare query');
}

mysqli_stmt_bind_param($stmt, 'si', $remarks, $id);

$ok = mysqli_stmt_execute($stmt);

if (!$ok || mysqli_stmt_affected_rows($stmt) === 0) {
    mysqli_stmt_close($stmt);
    respond(false, 'Already rejected or failed');
}

mysqli_stmt_close($stmt);

////////////////////////////////////////////////////////////
// FETCH USER FOR EMAIL
////////////////////////////////////////////////////////////
$emailSql = "
    SELECT e.emailAddress AS email, e.fullName, ot.date
    FROM overtimeRequests ot
    JOIN employeeusers e ON e.id = ot.employeeId
    WHERE ot.id=?
";

$stmtE = mysqli_prepare($con, $emailSql);

if ($stmtE) {
    mysqli_stmt_bind_param($stmtE, 'i', $id);
    mysqli_stmt_execute($stmtE);

    $resE = mysqli_stmt_get_result($stmtE);
    $user = mysqli_fetch_assoc($resE);

    mysqli_stmt_close($stmtE);

    if ($user && !empty($user['email'])) {
        sendOvertimeRejectedEmail(
            $id,
            $user['email'],
            $user['fullName'],
            $user['date'],
            $remarks
        );
    }
}

////////////////////////////////////////////////////////////
// FETCH UPDATED ROW
////////////////////////////////////////////////////////////
$sql2 = "
    SELECT 
        ot.id,
        e.fullName AS employeeName,
        ot.date,
        ot.startTime,
        ot.endTime,
        ot.totalHours,
        ot.calculatedOtHours AS otHours,
        ot.status,
        ot.remarks
    FROM overtimeRequests ot
    INNER JOIN employeeusers e ON e.id = ot.employeeId
    WHERE ot.id = ?
";

$stmt2 = mysqli_prepare($con, $sql2);

if (!$stmt2) {
    respond(false, 'Failed to fetch updated data');
}

mysqli_stmt_bind_param($stmt2, 'i', $id);
mysqli_stmt_execute($stmt2);

$res = mysqli_stmt_get_result($stmt2);
$row = mysqli_fetch_assoc($res);

mysqli_stmt_close($stmt2);

if ($row) {
    $row['totalHours'] = number_format((float)$row['totalHours'], 2);
    $row['otHours'] = number_format((float)$row['otHours'], 2);
}

respond(true, 'Overtime rejected', $row);