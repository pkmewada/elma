<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$todayMonthDay = date('m-d');
$currentYear   = date('Y');

/*
|--------------------------------------------------------------------------
| Fetch Employees Due Today
|--------------------------------------------------------------------------
*/
$sql = mysqli_query($con, "
    SELECT id, fullName, emailAddress, dateOfBirth, joiningDate
    FROM employeeusers
    WHERE emailAddress IS NOT NULL
    AND emailAddress != ''
    AND (
        DATE_FORMAT(dateOfBirth,'%m-%d') = '{$todayMonthDay}'
        OR DATE_FORMAT(joiningDate,'%m-%d') = '{$todayMonthDay}'
    )
");

while ($row = mysqli_fetch_assoc($sql)) {

    $employeeId = (int)$row['id'];
    $email      = trim($row['emailAddress']);
    $fullName   = $row['fullName'];

    /*
    |--------------------------------------------------------------------------
    | Birthday Mail
    |--------------------------------------------------------------------------
    */
    if (!empty($row['dateOfBirth']) &&
        date('m-d', strtotime($row['dateOfBirth'])) === $todayMonthDay) {

        $already = mysqli_query($con, "
            SELECT id
            FROM eventMailLog
            WHERE eventId = {$employeeId}
            AND mailType = 'birthday'
            AND recipientEmail = '" . mysqli_real_escape_string($con, $email) . "'
            AND DATE(sentAt) = CURDATE()
            LIMIT 1
        ");

        if (mysqli_num_rows($already) === 0) {

            $sent = sendBirthdayWishEmail((int)$row['id'],$row['email'],$row['fullName']);

            $status = $sent ? 'sent' : 'failed';

            mysqli_query($con, "
                INSERT INTO eventMailLog (
                    eventId,
                    mailType,
                    recipientEmail,
                    subjectLine,
                    sentStatus
                ) VALUES (
                    {$employeeId},
                    'birthday',
                    '" . mysqli_real_escape_string($con, $email) . "',
                    'Happy Birthday - Modlus',
                    '{$status}'
                )
            ");
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Anniversary Mail
    |--------------------------------------------------------------------------
    */
    if (!empty($row['joiningDate']) &&
        date('m-d', strtotime($row['joiningDate'])) === $todayMonthDay) {

        $years = $currentYear - date('Y', strtotime($row['joiningDate']));

        $already = mysqli_query($con, "
            SELECT id
            FROM eventMailLog
            WHERE eventId = {$employeeId}
            AND mailType = 'anniversary'
            AND recipientEmail = '" . mysqli_real_escape_string($con, $email) . "'
            AND DATE(sentAt) = CURDATE()
            LIMIT 1
        ");

        if (mysqli_num_rows($already) === 0) {

            $sent = sendWorkAnniversaryEmail((int)$row['id'], $row['email'], $row['fullName'],$years);

            $status = $sent ? 'sent' : 'failed';

            mysqli_query($con, "
                INSERT INTO eventMailLog (
                    eventId,
                    mailType,
                    recipientEmail,
                    subjectLine,
                    sentStatus
                ) VALUES (
                    {$employeeId},
                    'anniversary',
                    '" . mysqli_real_escape_string($con, $email) . "',
                    'Happy Work Anniversary - Modlus',
                    '{$status}'
                )
            ");
        }
    }
}

echo 'Celebration mails processed.';