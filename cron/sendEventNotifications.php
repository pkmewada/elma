<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

/*
|--------------------------------------------------------------------------
| Event Reminder Cron
|--------------------------------------------------------------------------
| Purpose:
| Send upcoming event / holiday reminder emails to employees
| Uses centralized logging via includes/mailer.php
| No manual insert logs here
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Today Date
|--------------------------------------------------------------------------
*/
$today = date('Y-m-d');

/*
|--------------------------------------------------------------------------
| Fetch Active Events Due for Reminder
|--------------------------------------------------------------------------
*/
$eventQuery = mysqli_query($con, "
    SELECT *
    FROM eventHolidayMaster
    WHERE status = 'active'
      AND mailEnabled = 1
      AND DATE_SUB(eventDate, INTERVAL reminderDays DAY) <= '{$today}'
      AND eventDate >= '{$today}'
    ORDER BY eventDate ASC
");

if (!$eventQuery) {
    exit("Failed to fetch events.\n");
}

/*
|--------------------------------------------------------------------------
| Fetch Employees
|--------------------------------------------------------------------------
*/
$employeeQuery = mysqli_query($con, "
    SELECT
        id,
        fullName,
        emailAddress
    FROM employeeusers
    WHERE emailAddress IS NOT NULL
      AND TRIM(emailAddress) <> ''
");

if (!$employeeQuery) {
    exit("Failed to fetch employees.\n");
}

$employees = [];

while ($row = mysqli_fetch_assoc($employeeQuery)) {
    $employees[] = $row;
}

/*
|--------------------------------------------------------------------------
| Counters
|--------------------------------------------------------------------------
*/
$totalEvents   = 0;
$totalAttempt  = 0;
$totalSent     = 0;
$totalSkipped  = 0;
$totalFailed   = 0;

/*
|--------------------------------------------------------------------------
| Process Events
|--------------------------------------------------------------------------
*/
while ($event = mysqli_fetch_assoc($eventQuery)) {

    $totalEvents++;

    $eventId       = (int)($event['id'] ?? 0);
    $eventTitle    = $event['eventTitle'] ?? '';
    $eventDate     = $event['eventDate'] ?? '';
    $eventType     = $event['eventType'] ?? 'event';
    $eventCategory = $event['eventCategory'] ?? '';
    $location      = $event['location'] ?? '';
    $description   = $event['description'] ?? '';

    foreach ($employees as $employee) {

        $employeeName = trim((string)($employee['fullName'] ?? 'Employee'));
        $employeeMail = trim((string)($employee['emailAddress'] ?? ''));

        if ($employeeMail === '') {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Protection
        |--------------------------------------------------------------------------
        | Skip if already sent successfully for same event + same recipient
        */
        $safeEmail = mysqli_real_escape_string($con, $employeeMail);

        $checkSql = mysqli_query($con, "
            SELECT id
            FROM eventMailLog
            WHERE moduleName = 'eventHoliday'
              AND referenceId = {$eventId}
              AND recipientEmail = '{$safeEmail}'
              AND status = 'sent'
            LIMIT 1
        ");

        if ($checkSql && mysqli_num_rows($checkSql) > 0) {
            $totalSkipped++;
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Send Mail
        |--------------------------------------------------------------------------
        | Logging handled inside mailer.php
        */
        $totalAttempt++;

        $sent = sendEventHolidayNotificationEmail(
            $eventId,
            $employeeMail,
            $employeeName,
            $eventTitle,
            $eventDate,
            $eventType,
            $eventCategory,
            $location,
            $description
        );

        if ($sent) {
            $totalSent++;
            echo "SENT: {$employeeMail} | {$eventTitle}\n";
        } else {
            $totalFailed++;
            echo "FAILED: {$employeeMail} | {$eventTitle}\n";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Final Summary
|--------------------------------------------------------------------------
*/
echo "\n---------------------------------\n";
echo "Event Notification Process Done\n";
echo "---------------------------------\n";
echo "Events Found   : {$totalEvents}\n";
echo "Attempts       : {$totalAttempt}\n";
echo "Sent           : {$totalSent}\n";
echo "Skipped        : {$totalSkipped}\n";
echo "Failed         : {$totalFailed}\n";
echo "Completed At   : " . date('Y-m-d H:i:s') . "\n";