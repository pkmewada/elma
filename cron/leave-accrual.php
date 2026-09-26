<?php
require_once __DIR__ . '/../includes/db.php';

$year = date('Y');

$sql = "
SELECT lb.*, lt.totalLeaves
FROM leaveBalances lb
JOIN leaveTypes lt ON lt.id = lb.leaveTypeId
WHERE lt.allocationType = 'monthly'
AND lb.year = ?
";

$stmt = mysqli_prepare($con, $sql);
mysqli_stmt_bind_param($stmt, "i", $year);
mysqli_stmt_execute($stmt);

$res = mysqli_stmt_get_result($stmt);

while ($row = mysqli_fetch_assoc($res)) {

    $monthly = $row['totalLeaves'] / 12;

    $update = mysqli_prepare($con, "
        UPDATE leaveBalances
        SET totalLeaves = totalLeaves + ?,
            remainingLeaves = remainingLeaves + ?
        WHERE id = ?
    ");

    mysqli_stmt_bind_param(
        $update,
        "ddi",
        $monthly,
        $monthly,
        $row['id']
    );

    mysqli_stmt_execute($update);
}