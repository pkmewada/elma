<?php

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

$maxRetry = 3;

$sql = mysqli_query($con, "
    SELECT *
    FROM systemMailLog
    WHERE status = 'failed'
    AND retryCount < {$maxRetry}
    ORDER BY id ASC
    LIMIT 25
");

if (!$sql) {
    exit('Query failed');
}

while ($row = mysqli_fetch_assoc($sql)) {

    $logId          = (int)$row['id'];
    $recipientEmail = $row['recipientEmail'];
    $recipientName  = $row['recipientName'];
    $subjectLine    = $row['subjectLine'];

    try {

        $mail = createMailer('Modlus HRMS');

        $mail->addAddress($recipientEmail);
        $mail->Subject = $subjectLine;

        $mail->Body = "
            <p>Hello {$recipientName},</p>
            <p>This email is being re-delivered.</p>
            <p>Please ignore if already received.</p>
            <p>Regards,<br>Modlus HR Team</p>
        ";

        $mail->AltBody = "Mail retry delivery.";

        $mail->send();

        markMailSent($logId);

    } catch (Throwable $e) {

        increaseRetryCount($logId, $e->getMessage());
    }
}