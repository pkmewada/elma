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

if ($id <= 0) {
    respond(false, 'Invalid request ID');
}

////////////////////////////////////////////////////////////
// UPDATE STATUS (ONLY IF NOT ALREADY APPROVED)
////////////////////////////////////////////////////////////
$sql = "UPDATE overtimeRequests 
        SET status='approved' 
        WHERE id=? AND status!='approved'";

$stmt = mysqli_prepare($con, $sql);

if (!$stmt) {
    respond(false, 'Failed to prepare query');
}

mysqli_stmt_bind_param($stmt, 'i', $id);
$ok = mysqli_stmt_execute($stmt);

if (!$ok || mysqli_stmt_affected_rows($stmt) === 0) {
    mysqli_stmt_close($stmt);
    respond(false, 'Already approved or failed');
}

mysqli_stmt_close($stmt);

////////////////////////////////////////////////////////////
// FETCH DATA FOR EMAIL
////////////////////////////////////////////////////////////
$emailSql = "
    SELECT e.emailAddress AS email, e.fullName, ot.date, ot.calculatedOtHours
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
        sendOvertimeApprovedEmail(
            $id,
            $user['email'],
            $user['fullName'],
            $user['date'],
            $user['calculatedOtHours']
        );
    }
}

////////////////////////////////////////////////////////////
// FETCH UPDATED ROW (FOR TABLE)
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
        ot.status
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

respond(true, 'Overtime approved', $row);