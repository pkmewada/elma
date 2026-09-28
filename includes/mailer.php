<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/basic-config.php';
require_once __DIR__ . '/../includes/config.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/*
|--------------------------------------------------------------------------
| Candidate Mail Module (Upgraded with Centralized eventMailLog)
|--------------------------------------------------------------------------
| NOTE:
| UI / UX / Subjects / Mail Flow preserved
| Only logging upgraded to sendLoggedMail()
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Log Helpers (Fallback File Log)
|--------------------------------------------------------------------------
*/
function getMailLogPath(): string
{
    $logDirectory = dirname(__DIR__) . '/logs';

    if (!is_dir($logDirectory)) {
        mkdir($logDirectory, 0777, true);
    }

    return $logDirectory . '/mail.log';
}

function writeMailLog(string $message): void
{
    file_put_contents(
        getMailLogPath(),
        date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL,
        FILE_APPEND
    );
}

/*
|--------------------------------------------------------------------------
| Gmail Credentials
|--------------------------------------------------------------------------
*/
function getMailerCredentials(): array
{
    // CRM_SMTP_USERNAME / CRM_SMTP_APP_PASSWORD (environment or .env) win over
    // the Basic Setup values in storage/basic-config.json, so production can
    // keep the mail secret out of the application directory entirely.
    $config = getBasicConfig();
    $envUsername = trim((string)crmEnv('CRM_SMTP_USERNAME'));
    $envPassword = trim((string)crmEnv('CRM_SMTP_APP_PASSWORD'));

    if ($envUsername !== '' && $envPassword !== '') {
        return ['username' => $envUsername, 'password' => $envPassword];
    }

    return [
        'username' => trim((string)($config['gmail_username'] ?? '')),
        'password' => trim((string)($config['gmail_app_password'] ?? ''))
    ];
}

/*
|--------------------------------------------------------------------------
| Create Mailer
|--------------------------------------------------------------------------
*/
function createMailer(string $fromName = BRAND_NAME): PHPMailer
{
    $mailConfig = getMailerCredentials();

    if ($mailConfig['username'] === '' || $mailConfig['password'] === '') {
        throw new Exception('Gmail configuration missing.');
    }

    $mail = new PHPMailer(true);

    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = $mailConfig['username'];
    $mail->Password   = $mailConfig['password'];
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->CharSet = 'UTF-8';
    $mail->isHTML(true);

    $mail->setFrom($mailConfig['username'], $fromName);
    $mail->addReplyTo($mailConfig['username'], 'Support');

    return $mail;
}

/*
|--------------------------------------------------------------------------
| OTP Templates
|--------------------------------------------------------------------------
*/
function buildOtpHtmlTemplate(string $otp): string
{
    $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
    $brandName = htmlspecialchars(BRAND_NAME, ENT_QUOTES, 'UTF-8');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your Email - OTP Code</title>
</head>

<body style="margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0"
style="max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;">

<tr>
<td style="padding:36px;">

<div style="font-size:26px;font-weight:700;color:#111827;margin-bottom:24px;">
Verify Your Email 🔐
</div>

<p style="margin:0 0 16px;font-size:16px;line-height:1.7;">
Hello,
</p>

<p style="margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;">
Thank you for signing up with <strong>{$brandName}</strong>.
Use the verification code below to continue your secure login process.
</p>

<div style="margin:0 0 24px;padding:18px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;text-align:center;">

<div style="font-size:13px;color:#6b7280;margin-bottom:8px;font-weight:600;letter-spacing:1px;">
ONE TIME PASSWORD
</div>

<div style="font-size:34px;font-weight:700;letter-spacing:8px;color:#111827;">
{$safeOtp}
</div>

</div>

<div style="padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;">

<div style="font-weight:700;color:#1d4ed8;margin-bottom:8px;">
Security Note
</div>

<div style="font-size:15px;line-height:1.7;color:#1e3a8a;">
This OTP is valid for <strong>5 minutes</strong>.
Do not share this code with anyone.
</div>

</div>

<p style="margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;">
If you did not request this verification, you can safely ignore this email.
</p>

<p style="margin:0;font-size:15px;line-height:1.7;">
Regards,<br>
<strong>{$brandName} Team</strong>
</p>

</td>
</tr>

</table>

</body>
</html>
HTML;
}

function buildOtpTextTemplate(string $otp): string
{
    return "Hello,\n\n"
        . "Thank you for using " . BRAND_NAME . ".\n\n"
        . "Your OTP is {$otp}. Valid for 5 minutes.\n\n"
        . "If you did not request this, please ignore this email.\n\n"
        . "Regards,\n" . BRAND_NAME . " Team";
}

/*
|--------------------------------------------------------------------------
| OTP Mail
|--------------------------------------------------------------------------
*/
function sendOtpEmail(string $toEmail, string $otp): bool
{
    return sendLoggedMail(
        'auth',
        0,
        'otp',
        $toEmail,
        $toEmail,
        'Verify your email - OTP Code',
        function () use ($toEmail, $otp) {

            $mail = createMailer(BRAND_NAME);
            $mail->addAddress($toEmail);
            $mail->Subject = 'Verify your email - OTP Code';
            $mail->Body = buildOtpHtmlTemplate($otp);
            $mail->AltBody = buildOtpTextTemplate($otp);

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Candidate Profile Received
|--------------------------------------------------------------------------
*/
function sendCandidateProfileReceivedEmail(
    string $toEmail,
    string $fullName
): bool {

    return sendLoggedMail(
        'candidate',
        0,
        'profileReceived',
        $toEmail,
        $fullName,
        'Profile Submitted Successfully',
        function () use ($toEmail, $fullName) {

            $mail = createMailer(BRAND_NAME);
            $mail->addAddress($toEmail);
            $mail->Subject = 'Profile Submitted Successfully';

            $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>

                <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table role='presentation' width='100%' cellspacing='0' cellpadding='0'
                style='max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                <tr>
                <td style='padding:36px;'>

                <div style='font-size:26px;font-weight:700;color:#111827;margin-bottom:22px;'>
                Profile Submitted Successfully ✅
                </div>

                <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                Hello {$fullName},
                </p>

                <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
                We have received your profile details and uploaded documents successfully.
                Thank you for completing your submission.
                </p>

                <div style='padding:18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                Current Status
                </div>

                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                Under HR Review
                </div>

                </div>

                <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                Our HR team is reviewing your submitted information. Once verification is completed, you will receive the next steps by email.
                </p>

                <p style='margin:0;font-size:15px;line-height:1.7;'>
                Regards,<br>
                <strong>" . htmlspecialchars(BRAND_NAME, ENT_QUOTES, 'UTF-8') . " Team</strong>
                </p>

                </td>
                </tr>

                </table>

                </body>
                </html>
                ";

            $mail->AltBody = "Profile received.";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Mail Log Helpers (Centralized eventMailLog)
|--------------------------------------------------------------------------
*/

function createMailLog(
    string $moduleName,
    int $referenceId,
    string $mailType,
    string $recipientEmail,
    string $recipientName,
    string $subjectLine
): int {

    global $con;

    $stmt = mysqli_prepare($con, "
        INSERT INTO eventMailLog
        (
            moduleName,
            referenceId,
            mailType,
            recipientEmail,
            recipientName,
            subjectLine,
            status,
            retryCount,
            createdAt
        )
        VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, NOW())
    ");

    mysqli_stmt_bind_param(
        $stmt,
        'sissss',
        $moduleName,
        $referenceId,
        $mailType,
        $recipientEmail,
        $recipientName,
        $subjectLine
    );

    mysqli_stmt_execute($stmt);

    return (int) mysqli_insert_id($con);
}

/*
|--------------------------------------------------------------------------
| Update Success
|--------------------------------------------------------------------------
*/
function markMailSent(int $logId): void
{
    global $con;

    $stmt = mysqli_prepare($con, "
        UPDATE eventMailLog
        SET
            status = 'sent',
            sentAt = NOW(),
            updatedAt = NOW()
        WHERE id = ?
    ");

    mysqli_stmt_bind_param($stmt, 'i', $logId);
    mysqli_stmt_execute($stmt);
}

/*
|--------------------------------------------------------------------------
| Update Failed
|--------------------------------------------------------------------------
*/
function markMailFailed(int $logId, string $errorMessage): void
{
    global $con;

    $stmt = mysqli_prepare($con, "
        UPDATE eventMailLog
        SET
            status = 'failed',
            retryCount = retryCount + 1,
            errorMessage = ?,
            updatedAt = NOW()
        WHERE id = ?
    ");

    mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $logId);
    mysqli_stmt_execute($stmt);
}

/*
|--------------------------------------------------------------------------
| Universal Send + Log Wrapper
|--------------------------------------------------------------------------
*/

// Local-dev safeguard: blocks every real mail send (all functions above
// route through sendLoggedMail()) whenever the app is running on
// localhost/127.0.0.1, so no salary/payroll (or any other) data is ever
// emailed to real recipients while testing locally. No-op in production,
// since HTTP_HOST there is never localhost.
function isLocalhostRequest(): bool
{
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $host = explode(':', $host)[0];

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function sendLoggedMail(
    string $moduleName,
    int $referenceId,
    string $mailType,
    string $recipientEmail,
    string $recipientName,
    string $subjectLine,
    callable $sendCallback
): bool {

    $logId = createMailLog(
        $moduleName,
        $referenceId,
        $mailType,
        $recipientEmail,
        $recipientName,
        $subjectLine
    );

    if (isLocalhostRequest()) {
        writeMailLog("BLOCKED (localhost testing) - {$moduleName}/{$mailType} to {$recipientEmail}: {$subjectLine}");
        markMailSent($logId);
        return true;
    }

    try {

        $result = $sendCallback();

        if ($result === true) {
            markMailSent($logId);
            return true;
        }

        markMailFailed($logId, 'Unknown sending failure');
        return false;

    } catch (Throwable $e) {

        markMailFailed($logId, $e->getMessage());
        return false;
    }
}

/*
|--------------------------------------------------------------------------
| Password Reset OTP Mail
|--------------------------------------------------------------------------
*/

function sendPasswordResetOtpEmail(
    string $toEmail,
    string $fullName,
    string $otp
): bool {

    $subject =
        'Password Reset Verification Code';

    $safeName =
        htmlspecialchars(
            $fullName,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeOtp =
        htmlspecialchars(
            $otp,
            ENT_QUOTES,
            'UTF-8'
        );

    $mail =
        createMailer(
            BRAND_NAME
        );

    $mail->addAddress(
        $toEmail
    );

    $mail->Subject =
        $subject;

    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>

    <head>

        <meta charset='UTF-8'>

        <meta
            name='viewport'
            content='width=device-width, initial-scale=1.0'>

    </head>

    <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

        <table
        width='100%'
        cellspacing='0'
        cellpadding='0'
        style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

            <tr>

                <td style='padding:36px;'>

                    <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                        Password Reset Verification
                    </div>

                    <p style='font-size:16px;line-height:1.7;'>
                        Hello {$safeName},
                    </p>

                    <p style='font-size:16px;line-height:1.7;color:#374151;'>
                        We received a request to reset your account password.
                        Please use the verification code below to continue.
                    </p>

                    <div style='margin:32px 0;text-align:center;'>

                        <div
                        style='display:inline-block;padding:18px 32px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;font-size:34px;font-weight:700;letter-spacing:10px;color:#1d4ed8;'>

                            {$safeOtp}

                        </div>

                    </div>

                    <div style='padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:24px;'>

                        <div style='font-weight:700;color:#111827;margin-bottom:8px;'>
                            Important Security Information
                        </div>

                        <div style='font-size:15px;line-height:1.7;color:#4b5563;'>

                            • This OTP is valid for 10 minutes.<br>

                            • Do not share this verification code with anyone.<br>

                            • If you did not request a password reset, please ignore this email.

                        </div>

                    </div>

                    <p style='font-size:15px;line-height:1.7;'>
                        Regards,<br>
                        <strong>" . htmlspecialchars(BRAND_NAME, ENT_QUOTES, 'UTF-8') . " Team</strong>
                    </p>

                </td>

            </tr>

        </table>

    </body>

    </html>
    ";

    $mail->AltBody =
        "Your password reset OTP is: {$safeOtp}";

    return $mail->send();
}

/*
|--------------------------------------------------------------------------
| Candidate Password Reset OTP Mail
|--------------------------------------------------------------------------
*/

function sendCandidatePasswordResetOtpEmail(
    string $toEmail,
    string $fullName,
    string $otp
): bool {

    $subject =
        'Candidate Password Reset Verification Code';

    $safeName =
        htmlspecialchars(
            $fullName,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeOtp =
        htmlspecialchars(
            $otp,
            ENT_QUOTES,
            'UTF-8'
        );

    $mail =
        createMailer(
            BRAND_NAME
        );

    $mail->addAddress(
        $toEmail
    );

    $mail->Subject =
        $subject;

    $mail->Body = "
    <!DOCTYPE html>
    <html lang='en'>

    <head>

        <meta charset='UTF-8'>

        <meta
            name='viewport'
            content='width=device-width, initial-scale=1.0'>

    </head>

    <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

        <table
        width='100%'
        cellspacing='0'
        cellpadding='0'
        style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

            <tr>

                <td style='padding:36px;'>

                    <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                        Candidate Password Reset
                    </div>

                    <p style='font-size:16px;line-height:1.7;'>
                        Hello {$safeName},
                    </p>

                    <p style='font-size:16px;line-height:1.7;color:#374151;'>
                        We received a request to reset your candidate portal password.
                        Please use the verification code below to continue.
                    </p>

                    <!-- OTP BOX -->
                    <div style='margin:32px 0;text-align:center;'>

                        <div
                        style='display:inline-block;padding:18px 32px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:12px;font-size:34px;font-weight:700;letter-spacing:10px;color:#1d4ed8;'>

                            {$safeOtp}

                        </div>

                    </div>

                    <!-- SECURITY INFO -->
                    <div style='padding:16px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;margin-bottom:24px;'>

                        <div style='font-weight:700;color:#111827;margin-bottom:8px;'>
                            Important Security Information
                        </div>

                        <div style='font-size:15px;line-height:1.7;color:#4b5563;'>

                            • This verification code is valid for 10 minutes.<br>

                            • Never share this OTP with anyone.<br>

                            • If you did not request password reset, you can safely ignore this email.

                        </div>

                    </div>

                    <p style='font-size:15px;line-height:1.7;'>
                        Regards,<br>
                        <strong>" . htmlspecialchars(BRAND_NAME, ENT_QUOTES, 'UTF-8') . " Team</strong>
                    </p>

                </td>

            </tr>

        </table>

    </body>

    </html>
    ";

    $mail->AltBody =
        "Your candidate password reset OTP is: {$safeOtp}";

    return $mail->send();
}

