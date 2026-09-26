<?php
require_once __DIR__ . '/../includes/db.php';

$currentYear = date('Y');
$nextYear = $currentYear + 1;

$sql = "
SELECT lb.*, ls.carryForward, ls.carryForwardLimit
FROM leaveBalances lb
JOIN leaveSettings ls ON ls.companyId = lb.companyId
WHERE lb.year = ?
";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $currentYear);
mysqli_stmt_execute($stmt);

$res = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($res)) {

    if ((int)$row['carryForward'] !== 1) continue;

    $carry = min(
        $row['remainingLeaves'],
        (int)$row['carryForwardLimit']
    );

    $insert = mysqli_prepare($con, "
        INSERT INTO leaveBalances 
        (companyId, employeeId, leaveTypeId, year, totalLeaves, usedLeaves, remainingLeaves)
        VALUES (?, ?, ?, ?, ?, 0, ?)
    ");

    mysqli_stmt_bind_param(
        $insert,
        "iiiiid",
        $row['companyId'],
        $row['employeeId'],
        $row['leaveTypeId'],
        $nextYear,
        $row['totalLeaves'],
        $carry
    );

    mysqli_stmt_execute($insert);
}