<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/pdf_acknowledgment.php';
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
    $config = getBasicConfig();

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
function createMailer(string $fromName = 'MQlus HRMS'): PHPMailer
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
Thank you for signing up with <strong>MQlusCRM</strong>.
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
<strong>MQlusTeam</strong>
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
        . "Thank you for signing up with MQlusCRM.\n\n"
        . "Your OTP is {$otp}. Valid for 5 minutes.\n\n"
        . "If you did not request this, please ignore this email.\n\n"
        . "Regards,\nMQlusTeam";
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

            $mail = createMailer('MQlusCRM');
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
| Welcome Mail
|--------------------------------------------------------------------------
*/
function sendWelcomeEmail(
    string $toEmail,
    string $fullName,
    string $tempPassword = 'Temp@12345'
): bool {

    return sendLoggedMail(
        'candidate',
        0,
        'welcome',
        $toEmail,
        $fullName,
        'Welcome to MQlus- Candidate Portal Access',
        function () use ($toEmail, $fullName, $tempPassword) {

            $safeName     = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeEmail    = htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
            $safePassword = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

            $loginUrl = BASE_URL . '/candidate-login';
            $termsUrl = BASE_URL . '/terms-and-conditions';

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = 'Welcome to MQlus- Candidate Portal Access';

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

            <div style='font-size:26px;font-weight:700;color:#111827;margin-bottom:24px;'>
            Welcome to MQlus🎉
            </div>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Hello {$safeName},
            </p>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Congratulations and welcome to <strong>Modlus</strong>.
            Your joining process has been confirmed successfully.
            </p>

            <p style='margin:0 0 20px;font-size:16px;line-height:1.7;'>
            Your candidate portal access has been created.
            Please use the login credentials below:
            </p>

            <table width='100%' cellspacing='0' cellpadding='0'
            style='border-collapse:collapse;margin:0 0 24px;'>

            <tr>
            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:180px;'>
            <strong>Login Email</strong>
            </td>
            <td style='padding:12px;border:1px solid #e5e7eb;'>
            {$safeEmail}
            </td>
            </tr>

            <tr>
            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
            <strong>Temporary Password</strong>
            </td>
            <td style='padding:12px;border:1px solid #e5e7eb;'>
            {$safePassword}
            </td>
            </tr>

            </table>

            <div style='margin:0 0 24px;'>

            <a href='{$loginUrl}'
            style='display:inline-block;padding:14px 24px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>

            Login to Candidate Portal

            </a>

            </div>

            <div style='padding:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;margin-bottom:24px;'>

            <div style='font-weight:700;color:#9a3412;margin-bottom:8px;'>
            Important Instructions
            </div>

            <ul style='margin:0;padding-left:18px;color:#7c2d12;line-height:1.7;'>
            <li>Use the temporary password for your first login.</li>
            <li>Password reset is mandatory on first login before proceeding.</li>
            <li>After login, complete your profile details and submit required information.</li>
            <li>Your submitted profile will be reviewed by HR.</li>
            <li>Review terms here:
            <a href='{$termsUrl}' style='color:#9a3412;'>Terms & Conditions</a></li>
            <li>Keep credentials confidential and secure.</li>
            </ul>

            </div>

            <p style='margin:0;font-size:15px;line-height:1.7;'>
            Regards,<br>
            <strong>MQlus HR Team</strong>
            </p>

            </td>
            </tr>

            </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Hello {$fullName}, Welcome to Modlus. Login Email: {$toEmail}, Temporary Password: {$tempPassword}";

            return $mail->send();
        }
    );
}


/*
|--------------------------------------------------------------------------
| Final Verification Access Mail
|--------------------------------------------------------------------------
*/
function sendFinalVerificationAccessEmail(
    string $toEmail,
    string $fullName,
    string $tempPassword
): bool {

    return sendLoggedMail(
        'candidate',
        0,
        'finalAccess',
        $toEmail,
        $fullName,
        'Onboarding Verified - Candidate Login Access',
        function () use ($toEmail, $fullName, $tempPassword) {

            $safeName     = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeEmail    = htmlspecialchars($toEmail, ENT_QUOTES, 'UTF-8');
            $safePassword = htmlspecialchars($tempPassword, ENT_QUOTES, 'UTF-8');

            $loginUrl = BASE_URL . '/candidate-login';
            $termsUrl = BASE_URL . '/terms-and-conditions';

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = 'Onboarding Verified - Candidate Login Access';

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

            <div style='font-size:26px;font-weight:700;color:#111827;margin-bottom:24px;'>
            Onboarding Verification Completed ✅
            </div>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Hello {$safeName},
            </p>

            <p style='margin:0 0 18px;font-size:16px;line-height:1.7;'>
            Your onboarding profile has been verified by HR.
            Your candidate login access is now active.
            </p>

            <table width='100%' cellspacing='0' cellpadding='0'
            style='border-collapse:collapse;margin:0 0 24px;'>

            <tr>
            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:180px;'>
            <strong>Login Email</strong>
            </td>
            <td style='padding:12px;border:1px solid #e5e7eb;'>
            {$safeEmail}
            </td>
            </tr>

            <tr>
            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
            <strong>Temporary Password</strong>
            </td>
            <td style='padding:12px;border:1px solid #e5e7eb;'>
            {$safePassword}
            </td>
            </tr>

            </table>

            <div style='margin:0 0 24px;'>

            <a href='{$loginUrl}'
            style='display:inline-block;padding:14px 24px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>

            Login to Candidate Portal

            </a>

            </div>

            <div style='padding:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;margin-bottom:24px;'>

            <div style='font-weight:700;color:#9a3412;margin-bottom:8px;'>
            Important Note
            </div>

            <div style='color:#7c2d12;line-height:1.7;'>
            Password reset is mandatory after first login before continuing.
            <br>
            <a href='{$termsUrl}' style='color:#9a3412;'>View Terms & Conditions</a>
            </div>

            </div>

            <p style='margin:0;font-size:15px;line-height:1.7;'>
            Regards,<br>
            <strong>MQlus HR Team</strong>
            </p>

            </td>
            </tr>

            </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Hello {$fullName}, Your onboarding has been verified. Login Email: {$toEmail}";

            return $mail->send();
        }
    );
}
/*
|--------------------------------------------------------------------------
| Acknowledgment Mail
|--------------------------------------------------------------------------
*/
function sendAcknowledgmentEmail(string $toEmail, string $link): bool
{
    return sendLoggedMail(
        'candidate',
        0,
        'acknowledgment',
        $toEmail,
        $toEmail,
        'Complete Your Offer Acknowledgment',
        function () use ($toEmail, $link) {

            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = 'Complete Your Offer Acknowledgment';

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
            Congratulations 🎉
            </div>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Hello,
            </p>

            <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
            We are pleased to inform you that you have been selected.
            Please complete your offer acknowledgment process to proceed further.
            </p>

            <div style='padding:18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

            <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
            Next Step Required
            </div>

            <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
            Kindly review and submit your acknowledgment using the secure link below.
            </div>

            </div>

            <div style='margin:0 0 24px;'>

            <a href='{$safeLink}'
            style='display:inline-block;padding:14px 24px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>

            Complete Acknowledgment

            </a>

            </div>

            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
            Please complete this process promptly to avoid delays in your onboarding journey.
            </p>

            <p style='margin:0;font-size:15px;line-height:1.7;'>
            Regards,<br>
            <strong>MQlus HR Team</strong>
            </p>

            </td>
            </tr>

            </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Congratulations! You have been selected. Complete your acknowledgment here: {$link}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Acknowledgment Submitted Mail
|--------------------------------------------------------------------------
*/
function sendAcknowledgmentSubmittedEmail(
    string $toEmail,
    string $fullName
): bool {

    return sendLoggedMail(
        'candidate',
        0,
        'acknowledgmentSubmitted',
        $toEmail,
        $fullName,
        'Acknowledgment Received',
        function () use ($toEmail, $fullName) {

            $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = 'Acknowledgment Received';

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
            Acknowledgment Received ✅
            </div>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Hello {$safeName},
            </p>

            <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
            We have successfully received your onboarding acknowledgment submission.
            Thank you for completing this step.
            </p>

            <div style='padding:18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

            <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
            Current Status
            </div>

            <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
            Submitted Successfully • Under HR Review
            </div>

            </div>

            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
            Our HR team will review your submitted details shortly. You will receive the next update through email.
            </p>

            <p style='margin:0;font-size:15px;line-height:1.7;'>
            Regards,<br>
            <strong>MQlus HR Team</strong>
            </p>

            </td>
            </tr>

            </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Hello {$fullName}, We have received your onboarding acknowledgment submission. Status: Under HR Review.";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Verified Mail + PDF
|--------------------------------------------------------------------------
*/
function sendOnboardingVerifiedEmail(
    string $toEmail,
    string $fullName,
    string $role,
    string $salary,
    string $joiningDate,
    string $pdfPath = ''
): bool {

    return sendLoggedMail(
        'candidate',
        0,
        'verified',
        $toEmail,
        $fullName,
        'Congratulations - Onboarding Verified',
        function () use (
            $toEmail,
            $fullName,
            $role,
            $salary,
            $joiningDate,
            $pdfPath
        ) {

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);

            if ($pdfPath !== '' && file_exists($pdfPath)) {
                $mail->addAttachment($pdfPath, 'Candidate_Acknowledgment.pdf');
            }

            $mail->Subject = 'Congratulations - Onboarding Verified';

           $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>

                <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table role='presentation' width='100%' cellspacing='0' cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                <tr>
                <td style='padding:36px;'>

                <div style='font-size:26px;font-weight:700;color:#111827;margin-bottom:22px;'>
                Congratulations 🎉
                </div>

                <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                Hello {$fullName},
                </p>

                <p style='margin:0 0 20px;font-size:16px;line-height:1.7;color:#374151;'>
                Your onboarding details have been verified successfully.
                We are pleased to welcome you to Modlus.
                </p>

                <table width='100%' cellspacing='0' cellpadding='0'
                style='border-collapse:collapse;margin:0 0 24px;'>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:180px;'>
                <strong>Role</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$role}
                </td>
                </tr>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                <strong>Salary</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$salary}
                </td>
                </tr>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                <strong>Joining Date</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$joiningDate}
                </td>
                </tr>

                </table>

                <div style='padding:18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                Important Note
                </div>

                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                Your acknowledgment letter has been attached with this email.
                Please keep it safe for future reference.
                </div>

                </div>

                <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                We wish you a successful and rewarding journey with Modlus.
                </p>

                <p style='margin:0;font-size:15px;line-height:1.7;'>
                Regards,<br>
                <strong>MQlus HR Team</strong>
                </p>

                </td>
                </tr>

                </table>

                </body>
                </html>
                ";

             $mail->AltBody =
                 "Congratulations {$fullName}! Your onboarding is verified. Role: {$role}, Salary: {$salary}, Joining Date: {$joiningDate}.";

             return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Rejection Mail
|--------------------------------------------------------------------------
*/
function sendRejectionResubmissionEmail(
    string $toEmail,
    string $fullName,
    string $reason,
    string $link
): bool {

    return sendLoggedMail(
        'candidate',
        0,
        'rejectedResubmit',
        $toEmail,
        $fullName,
        'Action Required - Resubmit Onboarding Details',
        function () use ($toEmail, $fullName, $reason, $link) {

            $safeName   = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeReason = htmlspecialchars($reason, ENT_QUOTES, 'UTF-8');
            $safeLink   = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = 'Action Required - Resubmit Onboarding Details';

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
            Action Required ⚠️
            </div>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Hello {$safeName},
            </p>

            <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
            Your onboarding submission requires corrections before approval.
            Please review the issue below and resubmit your details.
            </p>

            <div style='padding:18px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:24px;'>

            <div style='font-weight:700;color:#b91c1c;margin-bottom:8px;'>
            Reason for Resubmission
            </div>

            <div style='font-size:15px;line-height:1.7;color:#991b1b;'>
            {$safeReason}
            </div>

            </div>

            <div style='margin:0 0 24px;'>

            <a href='{$safeLink}'
            style='display:inline-block;padding:14px 24px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>

            Resubmit Details

            </a>

            </div>

            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
            Please complete the correction and resubmission process at the earliest to avoid delays.
            </p>

            <p style='margin:0;font-size:15px;line-height:1.7;'>
            Regards,<br>
            <strong>MQlus HR Team</strong>
            </p>

            </td>
            </tr>

            </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Hello {$fullName}, Reason: {$reason}. Resubmit here: {$link}";

            return $mail->send();
        }
    );
}




function sendProfileCorrectionRequiredEmail(
    int $employeeUserId,
    string $toEmail,
    string $fullName,
    array $rejectedItems
): bool {

    $subject = 'Action Required - Profile Corrections Needed';

    return sendLoggedMail(

        'candidate',

        $employeeUserId,

        'profileCorrection',

        $toEmail,

        $fullName,

        $subject,

        function () use (
            $toEmail,
            $fullName,
            $rejectedItems,
            $subject
        ) {

            $mail = createMailer('MQlus HRMS');

            $mail->addAddress($toEmail);

            $mail->Subject = $subject;

            $rows = '';

            foreach ($rejectedItems as $item) {

                $field  = htmlspecialchars($item['field']);
                $remark = htmlspecialchars($item['remark']);

                $rows .= "
                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                        {$field}
                    </td>

                    <td style='padding:12px;border:1px solid #e5e7eb;color:#dc2626;'>
                        {$remark}
                    </td>
                </tr>";
            }

            $mail->Body = "
            <!DOCTYPE html>
            <html>
            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;'>

            <table width='100%' style='max-width:700px;margin:auto;background:#fff;border-radius:14px;border:1px solid #e5e7eb;'>

                <tr>
                    <td style='padding:36px;'>

                        <h2 style='margin-top:0;color:#dc2626;'>
                            Profile Corrections Required
                        </h2>

                        <p>
                            Hello {$fullName},
                        </p>

                        <p>
                            During HR verification, some profile details/documents require correction before approval.
                        </p>

                        <table width='100%' style='border-collapse:collapse;margin-top:20px;'>

                            <tr>
                                <th style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;'>
                                    Item
                                </th>

                                <th style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;text-align:left;'>
                                    HR Remark
                                </th>
                            </tr>

                            {$rows}

                        </table>

                        <p style='margin-top:24px;'>
                            Please login to the candidate portal and update the above information.
                        </p>

                        <p>
                            Regards,<br>
                            <strong>MQlus HR Team</strong>
                        </p>

                    </td>
                </tr>

            </table>

            </body>
            </html>";

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

            $mail = createMailer('MQlus HRMS');
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
                <strong>MQlus HR Team</strong>
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
| Event / Holiday Notification Mail
|--------------------------------------------------------------------------
*/
function sendEventHolidayNotificationEmail(
    int $eventId,
    string $toEmail,
    string $employeeName,
    string $eventTitle,
    string $eventDate,
    string $eventType,
    string $eventCategory = '',
    string $location = '',
    string $description = ''
): bool {

    $subject = ucfirst($eventType) . ' Reminder - ' . $eventTitle;

    return sendLoggedMail(
        'eventHoliday',
        $eventId,
        'eventReminder',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $eventTitle,
            $eventDate,
            $eventType,
            $eventCategory,
            $location,
            $description,
            $subject
        ) {

            $safeName     = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');
            $safeTitle    = htmlspecialchars($eventTitle, ENT_QUOTES, 'UTF-8');
            $safeType     = ucfirst(htmlspecialchars($eventType, ENT_QUOTES, 'UTF-8'));
            $safeCategory = htmlspecialchars($eventCategory, ENT_QUOTES, 'UTF-8');
            $safeLocation = htmlspecialchars($location, ENT_QUOTES, 'UTF-8');
            $safeDesc     = nl2br(htmlspecialchars($description, ENT_QUOTES, 'UTF-8'));

            $formattedDate = date('d M Y', strtotime($eventDate));

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;

            $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>

                <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table role='presentation' width='100%' cellspacing='0' cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                <tr>
                <td style='padding:36px;'>

                <div style='font-size:26px;font-weight:700;color:#111827;margin-bottom:24px;'>
                Upcoming {$safeType} 🎉
                </div>

                <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                Hello {$safeName},
                </p>

                <p style='margin:0 0 22px;font-size:16px;line-height:1.7;color:#374151;'>
                We are excited to remind you about an upcoming <strong>{$safeType}</strong> at Modlus.
                Please find the details below.
                </p>

                <table width='100%' cellspacing='0' cellpadding='0'
                style='border-collapse:collapse;margin:0 0 24px;'>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:180px;'>
                <strong>Event Title</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$safeTitle}
                </td>
                </tr>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                <strong>Date</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$formattedDate}
                </td>
                </tr>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                <strong>Category</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$safeCategory}
                </td>
                </tr>

                <tr>
                <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                <strong>Location</strong>
                </td>
                <td style='padding:12px;border:1px solid #e5e7eb;'>
                {$safeLocation}
                </td>
                </tr>

                </table>

                <div style='padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                Event Information
                </div>

                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                {$safeDesc}
                </div>

                </div>

                <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                We look forward to your presence and hope you enjoy the celebration.
                </p>

                <p style='margin:0;font-size:15px;line-height:1.7;'>
                Regards,<br>
                <strong>MQlus HR Team</strong>
                </p>

                </td>
                </tr>

                </table>

                </body>
                </html>
                ";

            $mail->AltBody =
                "Upcoming {$eventType}: {$eventTitle} on {$formattedDate}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Birthday Wish Mail
|--------------------------------------------------------------------------
*/
function sendBirthdayWishEmail(
    int $employeeId,
    string $toEmail,
    string $fullName
): bool {

    $subject = 'Happy Birthday 🎉';

    return sendLoggedMail(
        'employee',
        $employeeId,
        'birthday',
        $toEmail,
        $fullName,
        $subject,

        function () use ($toEmail, $fullName, $subject) {

            $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;

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

            <div style='font-size:28px;font-weight:700;color:#111827;margin-bottom:22px;'>
            Happy Birthday 🎂
            </div>

            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
            Hello {$safeName},
            </p>

            <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
            Today is your special day, and we want to celebrate you with warm wishes from the entire MQlusfamily.
            </p>

            <div style='padding:18px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;margin-bottom:24px;'>

            <div style='font-weight:700;color:#c2410c;margin-bottom:8px;'>
            Best Wishes
            </div>

            <div style='font-size:15px;line-height:1.7;color:#9a3412;'>
            Wishing you happiness, success, good health, and many joyful moments in the year ahead.
            May your day be filled with smiles and celebration.
            </div>

            </div>

            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
            Thank you for being a valued part of our team.
            </p>

            <p style='margin:0;font-size:15px;line-height:1.7;'>
            Regards,<br>
            <strong>MQlus HR Team</strong>
            </p>

            </td>
            </tr>

            </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Hello {$fullName}, Happy Birthday!";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Work Anniversary Mail
|--------------------------------------------------------------------------
*/
function sendWorkAnniversaryEmail(
    int $employeeId,
    string $toEmail,
    string $fullName,
    int $years
): bool {

    $subject = 'Happy Work Anniversary 🎉';

    return sendLoggedMail(
        'employee',
        $employeeId,
        'anniversary',
        $toEmail,
        $fullName,
        $subject,

        function () use ($toEmail, $fullName, $years, $subject) {

            $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;

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

                <div style='font-size:28px;font-weight:700;color:#111827;margin-bottom:22px;'>
                Happy Work Anniversary 🎉
                </div>

                <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                Hello {$safeName},
                </p>

                <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
                Congratulations on completing 
                <strong>{$years} year(s)</strong> with Modlus.
                </p>

                <div style='padding:18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                Thank You For Your Contribution
                </div>

                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                Your dedication, commitment, and hard work have played an important role in our journey.
                We truly appreciate your continued support and valuable contribution to the team.
                </div>

                </div>

                <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                We look forward to achieving many more milestones together.
                </p>

                <p style='margin:0;font-size:15px;line-height:1.7;'>
                Regards,<br>
                <strong>MQlus HR Team</strong>
                </p>

                </td>
                </tr>

                </table>

                </body>
                </html>
                ";

            $mail->AltBody =
                "Hello {$fullName}, Congratulations on completing {$years} year(s) with Modlus.";

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
| Leave Application Mail
|--------------------------------------------------------------------------
*/

function sendLeaveAppliedEmail(
    int $leaveId,
    string $toEmail,
    string $employeeName,
    string $leaveType,
    string $fromDate,
    string $toDate,
    string $dayType
): bool {

    $subject = 'Leave Request Submitted';

    return sendLoggedMail(

        'leave',

        $leaveId,

        'leaveApplied',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $leaveType,
            $fromDate,
            $toDate,
            $dayType,
            $subject
        
        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeType =
                htmlspecialchars(
                    $leaveType,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedFrom =
                date(
                    'd M Y',
                    strtotime($fromDate)
                );

            $formattedTo =
                date(
                    'd M Y',
                    strtotime($toDate)
                );
                
            $formattedDayType = $dayType === 'half'
                ? 'Half Day'
                : 'Full Day';

            $mail = createMailer('MQlus HRMS');

            $mail->addAddress($toEmail);
            
            // 👇 ADD THIS FOR CC ACKNOWLEDGEMENT
            $ccEmails = [
                'varun.mqlus@gmail.com',
                'udayjeswani123@gmail.com',
                'hr@mqlus.in'
            ];
            
            foreach ($ccEmails as $cc) {
                if (!empty($cc)) {
                    $mail->addCC($cc);
                }
            }
            
            $mail->Subject = $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <head>
                <meta charset='UTF-8'>
                <meta name='viewport'
                content='width=device-width, initial-scale=1.0'>
            </head>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table
                role='presentation'
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                Leave Request Submitted
                            </div>

                            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='margin:0 0 20px;font-size:16px;line-height:1.7;color:#374151;'>
                                Your leave request has been submitted successfully and is currently under review.
                            </p>

                            <table
                            width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Leave Type</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeType}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>From Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedFrom}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>To Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedTo}
                                    </td>
                                </tr>
                                
                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Leave Duration</strong>
                                    </td>
                                
                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedDayType}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                                    Current Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                                    Pending Approval
                                </div>

                            </div>

                            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                                You will receive another notification once HR reviews your request.
                            </p>

                            <p style='margin:0;font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Leave Request Submitted | {$leaveType} | {$formattedFrom} to {$formattedTo}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Leave Approved Mail
|--------------------------------------------------------------------------
*/

function sendLeaveApprovedEmail(
    int $leaveId,
    string $toEmail,
    string $employeeName,
    string $leaveType,
    string $fromDate,
    string $toDate
): bool {

    $subject = 'Leave Approved';

    return sendLoggedMail(

        'leave',

        $leaveId,

        'leaveApproved',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $leaveType,
            $fromDate,
            $toDate,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeType =
                htmlspecialchars(
                    $leaveType,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedFrom =
                date(
                    'd M Y',
                    strtotime($fromDate)
                );

            $formattedTo =
                date(
                    'd M Y',
                    strtotime($toDate)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;color:#16a34a;margin-bottom:24px;'>
                                Leave Approved
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your leave request has been approved successfully.
                            </p>

                            <table
                            width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Leave Type</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeType}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>From Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedFrom}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>To Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedTo}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#166534;margin-bottom:8px;'>
                                    Approval Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#166534;'>
                                    Approved Successfully
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                We wish you a smooth and relaxing leave period.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Leave Request Submitted | {$leaveType} | {$formattedDayType} | {$formattedFrom} to {$formattedTo}";

            return $mail->send();
        }
    );
}


/*
|--------------------------------------------------------------------------
| Leave Rejected Mail
|--------------------------------------------------------------------------
*/

function sendLeaveRejectedEmail(
    int $leaveId,
    string $toEmail,
    string $employeeName,
    string $leaveType,
    string $fromDate,
    string $toDate
): bool {

    $subject = 'Leave Rejected';

    return sendLoggedMail(

        'leave',

        $leaveId,

        'leaveRejected',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $leaveType,
            $fromDate,
            $toDate,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeType =
                htmlspecialchars(
                    $leaveType,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedFrom =
                date(
                    'd M Y',
                    strtotime($fromDate)
                );

            $formattedTo =
                date(
                    'd M Y',
                    strtotime($toDate)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;color:#dc2626;margin-bottom:24px;'>
                                Leave Rejected
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your leave request has been rejected.
                            </p>

                            <table
                            width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Leave Type</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeType}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>From Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedFrom}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>To Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedTo}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#b91c1c;margin-bottom:8px;'>
                                    Request Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#991b1b;'>
                                    Leave Request Rejected
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                Please contact HR if you need clarification regarding this request.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Leave Rejected | {$leaveType} | {$formattedFrom} to {$formattedTo}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| OVERTIME MODULE MAILS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Overtime Applied Mail
|--------------------------------------------------------------------------
*/

function sendOvertimeAppliedEmail(
    int $otId,
    string $toEmail,
    string $employeeName,
    string $date,
    string $totalHours
): bool {

    $subject = 'Overtime Request Submitted';

    return sendLoggedMail(

        'overtime',

        $otId,

        'otApplied',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $date,
            $totalHours,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($date)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
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
                <meta name='viewport'
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
                                Overtime Request Submitted
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your overtime request has been submitted successfully and is currently under review.
                            </p>

                            <table
                            width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Overtime Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedDate}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Total Hours</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;'>
                                        {$totalHours}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                                    Current Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                                    Pending Approval
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                You will receive another notification once HR reviews your overtime request.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Overtime Request Submitted | {$formattedDate} | {$totalHours} Hours";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Overtime Approved Mail
|--------------------------------------------------------------------------
*/

function sendOvertimeApprovedEmail(
    int $otId,
    string $toEmail,
    string $employeeName,
    string $date,
    string $otHours
): bool {

    $subject = 'Overtime Approved';

    return sendLoggedMail(

        'overtime',

        $otId,

        'otApproved',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $date,
            $otHours,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($date)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;color:#16a34a;margin-bottom:24px;'>
                                Overtime Approved
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your overtime request has been approved successfully.
                            </p>

                            <table
                            width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Overtime Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedDate}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Approved OT Hours</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#16a34a;font-weight:700;'>
                                        {$otHours}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#166534;margin-bottom:8px;'>
                                    Approval Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#166534;'>
                                    Approved Successfully
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                Your approved overtime will now proceed further in payroll workflow.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Overtime Approved | {$formattedDate} | {$otHours} Hours";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Overtime Rejected Mail
|--------------------------------------------------------------------------
*/

function sendOvertimeRejectedEmail(
    int $otId,
    string $toEmail,
    string $employeeName,
    string $date,
    string $remarks
): bool {

    $subject = 'Overtime Rejected';

    return sendLoggedMail(

        'overtime',

        $otId,

        'otRejected',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $date,
            $remarks,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeRemarks =
                htmlspecialchars(
                    $remarks,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($date)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;color:#dc2626;margin-bottom:24px;'>
                                Overtime Rejected
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your overtime request has been rejected.
                            </p>

                            <table
                            width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Overtime Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedDate}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Reason</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#dc2626;'>
                                        {$safeRemarks}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#b91c1c;margin-bottom:8px;'>
                                    Request Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#991b1b;'>
                                    Overtime Request Rejected
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                Please contact HR if you need clarification regarding this request.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Overtime Rejected | {$formattedDate}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| EXPENSE MANAGEMENT MODULE MAILS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Expense Created Mail
|--------------------------------------------------------------------------
*/

function sendExpenseCreatedEmail(
    int $expenseId,
    string $toEmail,
    string $employeeName,
    string $expenseType,
    float $amount,
    string $expenseDate,
    string $invoiceNumber = '',
    string $remarks = ''
): bool {

    $subject = 'Expense Submitted Successfully';

    return sendLoggedMail(
        'expense',
        $expenseId,
        'expenseCreated',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $expenseType,
            $amount,
            $expenseDate,
            $invoiceNumber,
            $remarks,
            $subject
        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeExpenseType =
                htmlspecialchars(
                    $expenseType,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeInvoice =
                htmlspecialchars(
                    $invoiceNumber,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeRemarks =
                htmlspecialchars(
                    $remarks,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($expenseDate)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                Expense Submitted Successfully
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your expense request has been submitted successfully.
                            </p>

                            <table width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Expense Type</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeExpenseType}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Amount</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#16a34a;font-weight:700;'>
                                        ₹ {$formattedAmount}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Expense Date</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$formattedDate}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Invoice Number</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeInvoice}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Remarks</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeRemarks}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                                    Current Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                                    Expense Submitted Successfully
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                Please contact HR if you have any questions regarding this expense request.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Expense Submitted | {$expenseType} | Amount: ₹ {$formattedAmount}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Expense Updated Mail
|--------------------------------------------------------------------------
*/

function sendExpenseUpdatedEmail(
    int $expenseId,
    string $toEmail,
    string $employeeName,
    string $expenseType,
    float $amount,
    string $expenseDate,
    string $invoiceNumber = '',
    string $remarks = ''
): bool {

    $subject = 'Expense Updated Successfully';

    return sendLoggedMail(
        'expense',
        $expenseId,
        'expenseUpdated',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $expenseType,
            $amount,
            $expenseDate,
            $invoiceNumber,
            $remarks,
            $subject
        ) {

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($expenseDate)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <div style='font-family:Arial;padding:30px;background:#f5f7fb;'>

                <div style='max-width:700px;background:#fff;border-radius:12px;padding:30px;margin:auto;border:1px solid #e5e7eb;'>

                    <h2 style='margin-top:0;color:#111827;'>
                        Expense Updated Successfully
                    </h2>

                    <p>Hello {$employeeName},</p>

                    <p>
                        Your expense request has been updated successfully.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Type</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$expenseType}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Amount</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                ₹ {$formattedAmount}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Date</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$formattedDate}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Invoice Number</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$invoiceNumber}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Remarks</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$remarks}
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:24px;color:#6b7280;'>
                        Please contact HR if you have any concerns.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>MQlus HR Team</strong>
                    </p>

                </div>

            </div>";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Expense Deleted Mail
|--------------------------------------------------------------------------
*/

function sendExpenseDeletedEmail(
    int $expenseId,
    string $toEmail,
    string $employeeName,
    string $expenseType,
    float $amount
): bool {

    $subject = 'Expense Removed';

    return sendLoggedMail(
        'expense',
        $expenseId,
        'expenseDeleted',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $expenseType,
            $amount,
            $subject
        ) {

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <div style='font-family:Arial;padding:30px;background:#f5f7fb;'>

                <div style='max-width:700px;background:#fff;border-radius:12px;padding:30px;margin:auto;border:1px solid #e5e7eb;'>

                    <h2 style='margin-top:0;color:#dc2626;'>
                        Expense Removed
                    </h2>

                    <p>Hello {$employeeName},</p>

                    <p>
                        An expense request has been removed/reverted by HR.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Type</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$expenseType}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Amount</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;color:#dc2626;font-weight:bold;'>
                                ₹ {$formattedAmount}
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:24px;color:#6b7280;'>
                        If you believe this action was incorrect,
                        please contact HR.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>MQlus HR Team</strong>
                    </p>

                </div>

            </div>";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Expense Approved Mail
|--------------------------------------------------------------------------
*/

function sendExpenseApprovedEmail(

    int $expenseId,

    string $employeeEmail,

    string $employeeName,

    string $expenseType,

    float $amount,

    string $expenseDate,

    string $approvedBy

): bool {

    $subject =
        'Expense Approved Successfully';

    return sendLoggedMail(

        'expense',

        $expenseId,

        'expenseApproved',

        $employeeEmail,

        $employeeName,

        $subject,

        function () use (

            $employeeEmail,

            $employeeName,

            $expenseType,

            $amount,

            $expenseDate,

            $approvedBy,

            $subject

        ) {

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($expenseDate)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $employeeEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <div style='font-family:Arial;padding:30px;background:#f5f7fb;'>

                <div style='max-width:700px;background:#fff;border-radius:12px;padding:30px;margin:auto;border:1px solid #e5e7eb;'>

                    <h2 style='margin-top:0;color:#16a34a;'>
                        Expense Approved
                    </h2>

                    <p>Hello {$employeeName},</p>

                    <p>
                        Your expense request has been approved successfully.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Type</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$expenseType}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Amount</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;color:#16a34a;font-weight:bold;'>
                                ₹ {$formattedAmount}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Date</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$formattedDate}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Approved By</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$approvedBy}
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:24px;color:#6b7280;'>
                        The approved expense will now proceed further in workflow.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>MQlus HR Team</strong>
                    </p>

                </div>

            </div>";

            return $mail->send();
        }
    );
}
/*
|--------------------------------------------------------------------------
| Expense Rejected Mail
|--------------------------------------------------------------------------
*/

function sendExpenseRejectedEmail(

    int $expenseId,

    string $employeeEmail,

    string $employeeName,

    string $expenseType,

    float $amount,

    string $expenseDate,

    string $invoiceNumber,

    string $remark,

    string $rejectedBy

): bool {

    $subject =
        'Expense Rejected';

    return sendLoggedMail(

        'expense',

        $expenseId,

        'expenseRejected',

        $employeeEmail,

        $employeeName,

        $subject,

        function () use (

            $employeeEmail,

            $employeeName,

            $expenseType,

            $amount,

            $expenseDate,

            $invoiceNumber,

            $remark,

            $rejectedBy,

            $subject

        ) {

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $formattedDate =
                date(
                    'd M Y',
                    strtotime($expenseDate)
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $employeeEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <div style='font-family:Arial;padding:30px;background:#f5f7fb;'>

                <div style='max-width:700px;background:#fff;border-radius:12px;padding:30px;margin:auto;border:1px solid #e5e7eb;'>

                    <h2 style='margin-top:0;color:#dc2626;'>
                        Expense Rejected
                    </h2>

                    <p>Hello {$employeeName},</p>

                    <p>
                        Your expense request has been rejected.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Type</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$expenseType}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Amount</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;color:#dc2626;font-weight:bold;'>
                                ₹ {$formattedAmount}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Expense Date</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$formattedDate}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Invoice Number</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$invoiceNumber}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Rejected By</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$rejectedBy}
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:24px;color:#6b7280;'>
                        Please contact HR/Admin for clarification.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>MQlus HR Team</strong>
                    </p>

                </div>

            </div>";

            return $mail->send();
        }
    );
}
/*
|--------------------------------------------------------------------------
| Point Transaction Created Mail
|--------------------------------------------------------------------------
*/

function sendPointTransactionEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionType,
    string $categoryName,
    float $points,
    string $remarks = ''
): bool {

    $subject = 'Point Transaction Update';

    return sendLoggedMail(
        'employeePoint',
        $transactionId,
        'pointTransaction',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $transactionType,
            $categoryName,
            $points,
            $remarks,
            $subject
        ) {

            $safeName = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');

            $safeType = htmlspecialchars($transactionType, ENT_QUOTES, 'UTF-8');

            $safeCategory = htmlspecialchars($categoryName, ENT_QUOTES, 'UTF-8');

            $safeRemarks = htmlspecialchars($remarks, ENT_QUOTES, 'UTF-8');

            $mail = createMailer('MQlus HRMS');

            $mail->addAddress($toEmail);

            $mail->Subject = $subject;

            $badgeColor =
                strtolower($transactionType) === 'credit'
                    ? '#16a34a'
                    : '#dc2626';

            $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>

                <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table role='presentation'
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:680px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                <tr>
                <td style='padding:36px;'>

                <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                Employee Point Update
                </div>

                <p style='font-size:16px;line-height:1.7;'>
                Hello {$safeName},
                </p>

                <p style='font-size:16px;line-height:1.7;color:#374151;'>
                A point transaction has been added to your account.
                Please find the details below.
                </p>

                <table width='100%'
                cellspacing='0'
                cellpadding='0'
                style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:180px;'>
                    <strong>Transaction Type</strong>
                    </td>

                    <td style='padding:12px;border:1px solid #e5e7eb;color:{$badgeColor};font-weight:700;'>
                    {$safeType}
                    </td>
                </tr>

                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                    <strong>Category</strong>
                    </td>

                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                    {$safeCategory}
                    </td>
                </tr>

                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                    <strong>Points</strong>
                    </td>

                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;'>
                    {$points}
                    </td>
                </tr>

                <tr>
                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                    <strong>Remarks</strong>
                    </td>

                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                    {$safeRemarks}
                    </td>
                </tr>

                </table>

                <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                If you have any concerns regarding this transaction,
                please contact HR.
                </p>

                <p style='font-size:15px;line-height:1.7;'>
                Regards,<br>
                <strong>MQlus HR Team</strong>
                </p>

                </td>
                </tr>

                </table>

                </body>
                </html>
            ";

            $mail->AltBody =
                "Point Transaction: {$transactionType} | Category: {$categoryName} | Points: {$points}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Point Transaction Updated Mail
|--------------------------------------------------------------------------
*/

function sendPointTransactionUpdatedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionType,
    string $categoryName,
    float $points,
    string $remarks = ''
): bool {

    $subject = 'Point Transaction Updated';

    return sendLoggedMail(
        'employeePoint',
        $transactionId,
        'pointTransactionUpdated',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $transactionType,
            $categoryName,
            $points,
            $remarks,
            $subject
        ) {

            $mail = createMailer('MQlus HRMS');

            $mail->addAddress($toEmail);

            $mail->Subject = $subject;

            $badgeColor =
                strtolower($transactionType) === 'credit'
                    ? '#16a34a'
                    : '#dc2626';

            $mail->Body = "
            <div style='font-family:Arial;padding:30px;background:#f5f7fb;'>

                <div style='max-width:700px;background:#fff;border-radius:12px;padding:30px;margin:auto;border:1px solid #e5e7eb;'>

                    <h2 style='margin-top:0;color:#111827;'>
                        Point Transaction Updated
                    </h2>

                    <p>Hello {$employeeName},</p>

                    <p>
                        Your employee point transaction has been updated by HR.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Transaction Type</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;color:{$badgeColor};font-weight:bold;'>
                                {$transactionType}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Category</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$categoryName}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Points</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$points}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Remarks</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$remarks}
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:24px;color:#6b7280;'>
                        Please contact HR if you have any concerns.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>MQlus HR Team</strong>
                    </p>

                </div>

            </div>";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Point Transaction Deleted Mail
|--------------------------------------------------------------------------
*/

function sendPointTransactionDeletedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $categoryName,
    float $points
): bool {

    $subject = 'Point Transaction Removed';

    return sendLoggedMail(
        'employeePoint',
        $transactionId,
        'pointTransactionDeleted',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $categoryName,
            $points,
            $subject
        ) {

            $mail = createMailer('MQlus HRMS');

            $mail->addAddress($toEmail);

            $mail->Subject = $subject;

            $mail->Body = "
            <div style='font-family:Arial;padding:30px;background:#f5f7fb;'>

                <div style='max-width:700px;background:#fff;border-radius:12px;padding:30px;margin:auto;border:1px solid #e5e7eb;'>

                    <h2 style='margin-top:0;color:#111827;'>
                        Point Transaction Removed
                    </h2>

                    <p>Hello {$employeeName},</p>

                    <p>
                        A previous employee point transaction has been removed/reverted by HR.
                    </p>

                    <table style='width:100%;border-collapse:collapse;margin-top:20px;'>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Category</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$categoryName}
                            </td>
                        </tr>

                        <tr>
                            <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                <strong>Points</strong>
                            </td>

                            <td style='padding:12px;border:1px solid #e5e7eb;'>
                                {$points}
                            </td>
                        </tr>

                    </table>

                    <p style='margin-top:24px;color:#6b7280;'>
                        If you believe this action was incorrect,
                        please contact HR.
                    </p>

                    <p>
                        Regards,<br>
                        <strong>MQlus HR Team</strong>
                    </p>

                </div>

            </div>";

            return $mail->send();
        }
    );
}


/*
|--------------------------------------------------------------------------
| COMMISSION / BONUS MODULE MAILS
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Transaction Created Mail
|--------------------------------------------------------------------------
*/

function sendCommissionTransactionCreatedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionCode,
    string $categoryName,
    string $categoryType,
    float $amount,
    string $effectiveMonth,
    string $remarks = ''
): bool {

    $subject = 'Commission / Bonus Transaction Created';

    return sendLoggedMail(
        'commissionBonus',
        $transactionId,
        'transactionCreated',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $transactionCode,
            $categoryName,
            $categoryType,
            $amount,
            $effectiveMonth,
            $remarks,
            $subject
        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCode =
                htmlspecialchars(
                    $transactionCode,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCategory =
                htmlspecialchars(
                    $categoryName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeType =
                htmlspecialchars(
                    $categoryType,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeRemarks =
                htmlspecialchars(
                    $remarks,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeMonth =
                htmlspecialchars(
                    $effectiveMonth,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
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
                <meta name='viewport'
                content='width=device-width, initial-scale=1.0'>
            </head>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table role='presentation'
                width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                Commission / Bonus Transaction Added
                            </div>

                            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='margin:0 0 20px;font-size:16px;line-height:1.7;color:#374151;'>
                                A commission / bonus transaction has been added to your account.
                            </p>

                            <table width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Transaction Code</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCode}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Category</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCategory}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Type</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeType}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Amount</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;font-weight:700;color:#16a34a;'>
                                        ₹ {$formattedAmount}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Effective Month</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeMonth}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Remarks</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeRemarks}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                                    Current Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                                    Pending Approval
                                </div>

                            </div>

                            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                                The transaction will be processed after approval workflow completion.
                            </p>

                            <p style='margin:0;font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Commission / Bonus Transaction Added | {$transactionCode} | Amount: ₹ {$formattedAmount}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Transaction Approved Mail
|--------------------------------------------------------------------------
*/

function sendCommissionTransactionApprovedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionCode,
    float $amount
): bool {

    $subject = 'Commission / Bonus Approved';

    return sendLoggedMail(
        'commissionBonus',
        $transactionId,
        'transactionApproved',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $transactionCode,
            $amount,
            $subject
        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCode =
                htmlspecialchars(
                    $transactionCode,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                Commission / Bonus Approved
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your commission / bonus transaction has been approved successfully.
                            </p>

                            <table width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Transaction Code</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCode}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Approved Amount</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#16a34a;font-weight:700;'>
                                        ₹ {$formattedAmount}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#166534;margin-bottom:8px;'>
                                    Payroll Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#166534;'>
                                    Awaiting payroll synchronization.
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                The approved amount will be processed according to payroll workflow.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Commission / Bonus Approved | {$transactionCode} | Amount: ₹ {$formattedAmount}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Transaction Rejected Mail
|--------------------------------------------------------------------------
*/

function sendCommissionTransactionRejectedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionCode,
    string $reason = ''
): bool {

    $subject = 'Commission / Bonus Rejected';

    return sendLoggedMail(
        'commissionBonus',
        $transactionId,
        'transactionRejected',
        $toEmail,
        $employeeName,
        $subject,

        function () use (
            $toEmail,
            $employeeName,
            $transactionCode,
            $reason,
            $subject
        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCode =
                htmlspecialchars(
                    $transactionCode,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeReason =
                htmlspecialchars(
                    $reason,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;color:#dc2626;margin-bottom:24px;'>
                                Commission / Bonus Rejected
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your commission / bonus transaction request has been rejected.
                            </p>

                            <table width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Transaction Code</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCode}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Reason</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#dc2626;'>
                                        {$safeReason}
                                    </td>
                                </tr>

                            </table>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                Please contact HR for further clarification if required.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Commission / Bonus Rejected | {$transactionCode}";

            return $mail->send();
        }
    );
}


/*
|--------------------------------------------------------------------------
| Payroll Synced Mail
|--------------------------------------------------------------------------
*/

function sendCommissionPayrollSyncedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionCode,
    float $amount
): bool {

    $subject = 'Commission / Bonus Synced To Payroll';

    return sendLoggedMail(

        'commissionBonus',

        $transactionId,

        'payrollSynced',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $transactionCode,
            $amount,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCode =
                htmlspecialchars(
                    $transactionCode,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                Payroll Sync Completed
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your approved commission / bonus transaction has been synced successfully to payroll.
                            </p>

                            <table width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Transaction Code</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCode}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Amount</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#16a34a;font-weight:700;'>
                                        ₹ {$formattedAmount}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#166534;margin-bottom:8px;'>
                                    Payroll Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#166534;'>
                                    Synced Successfully
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                The amount will now be processed within the payroll cycle.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Commission / Bonus Payroll Synced | {$transactionCode} | Amount: ₹ {$formattedAmount}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Transaction Deleted Mail
|--------------------------------------------------------------------------
*/

function sendCommissionTransactionDeletedEmail(
    int $transactionId,
    string $toEmail,
    string $employeeName,
    string $transactionCode,
    string $categoryName,
    float $amount
): bool {

    $subject = 'Commission / Bonus Transaction Removed';

    return sendLoggedMail(

        'commissionBonus',

        $transactionId,

        'transactionDeleted',

        $toEmail,

        $employeeName,

        $subject,

        function () use (

            $toEmail,
            $employeeName,
            $transactionCode,
            $categoryName,
            $amount,
            $subject

        ) {

            $safeName =
                htmlspecialchars(
                    $employeeName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCode =
                htmlspecialchars(
                    $transactionCode,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $safeCategory =
                htmlspecialchars(
                    $categoryName,
                    ENT_QUOTES,
                    'UTF-8'
                );

            $formattedAmount =
                number_format(
                    $amount,
                    2
                );

            $mail =
                createMailer(
                    'MQlus HRMS'
                );

            $mail->addAddress(
                $toEmail
            );

            $mail->Subject =
                $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>

            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table width='100%'
                cellspacing='0'
                cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;color:#dc2626;margin-bottom:24px;'>
                                Commission / Bonus Transaction Removed
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                A commission / bonus transaction has been removed/reverted by HR.
                            </p>

                            <table width='100%'
                            cellspacing='0'
                            cellpadding='0'
                            style='border-collapse:collapse;margin-top:20px;margin-bottom:24px;'>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;width:220px;'>
                                        <strong>Transaction Code</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCode}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Category</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;'>
                                        {$safeCategory}
                                    </td>
                                </tr>

                                <tr>
                                    <td style='padding:12px;border:1px solid #e5e7eb;background:#f9fafb;'>
                                        <strong>Amount</strong>
                                    </td>

                                    <td style='padding:12px;border:1px solid #e5e7eb;color:#dc2626;font-weight:700;'>
                                        ₹ {$formattedAmount}
                                    </td>
                                </tr>

                            </table>

                            <div style='padding:16px;background:#fef2f2;border:1px solid #fecaca;border-radius:10px;margin-bottom:24px;'>

                                <div style='font-weight:700;color:#b91c1c;margin-bottom:8px;'>
                                    Transaction Status
                                </div>

                                <div style='font-size:15px;line-height:1.7;color:#991b1b;'>
                                    Reverted / Removed Successfully
                                </div>

                            </div>

                            <p style='font-size:15px;color:#4b5563;line-height:1.7;'>
                                Please contact HR if you have any questions regarding this action.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus HR Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Commission / Bonus Transaction Removed | {$transactionCode} | Amount: ₹ {$formattedAmount}";

            return $mail->send();
        }
    );
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
            'MQlus HRMS'
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
                        <strong>MQlus HR Team</strong>
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
            'MQlus HRMS'
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
                        <strong>MQlus HR Team</strong>
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


/*
|--------------------------------------------------------------------------
| Send Agreement Mail For the Client
|--------------------------------------------------------------------------
*/

function sendAgreementEmail(
    int $leadId,
    string $toEmail,
    string $fullName,
    string $agreementLink
    ): bool {
    
    $subject =
        'Action Required: Review Your Onboarding Agreement';
    
    return sendLoggedMail(
    
        'onboardingAgreement',
    
        $leadId,
    
        'agreementSent',
    
        $toEmail,
    
        $fullName,
    
        $subject,
    
        function () use (
    
            $toEmail,
            $fullName,
            $agreementLink,
            $subject
    
        ) {
    
            $safeName =
                htmlspecialchars(
                    $fullName,
                    ENT_QUOTES,
                    'UTF-8'
                );
    
            $safeLink =
                htmlspecialchars(
                    $agreementLink,
                    ENT_QUOTES,
                    'UTF-8'
                );
    
            $mail =
                createMailer(
                    'MQlus Client Success Team'
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
                <meta name='viewport'
                    content='width=device-width, initial-scale=1.0'>
            </head>
    
            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>
    
                <table
                    role='presentation'
                    width='100%'
                    cellspacing='0'
                    cellpadding='0'
                    style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>
    
                    <tr>
                        <td style='padding:36px;'>
    
                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                Client Onboarding Agreement Ready 📄
                            </div>
    
                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>
    
                            <div style='padding:16px;background:#ecfdf5;border:1px solid #a7f3d0;border-radius:10px;margin:20px 0;'>
    
                                <div style='font-weight:700;color:#065f46;margin-bottom:8px;'>
                                    Welcome to MQlus
                                </div>
    
                                <div style='font-size:15px;color:#065f46;line-height:1.7;'>
                                    Thank you for choosing MQlus.
    
                                    We are excited to begin this journey with you.
    
                                    Once your agreement is reviewed and accepted,
                                    our team will initiate the onboarding process
                                    and coordinate the next steps for your project
                                    or service engagement.
                                </div>
    
                            </div>
    
                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
    
                                Your onboarding agreement is now ready for review.
    
                                This document contains the agreed scope of work,
                                commercial terms, pricing, deliverables, timelines
                                and onboarding details for your project or service.
    
                                Please review the document carefully and confirm
                                your acceptance so we can proceed with the onboarding process.
    
                            </p>
    
                            <div style='padding:18px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin:24px 0;'>
    
                                <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                                    Action Required
                                </div>
    
                                <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
    
                                    Review your onboarding agreement and confirm
                                    your acceptance using the secure link below.
    
                                    Once accepted, our team will begin the onboarding
                                    process and coordinate the next steps with you.
    
                                </div>
    
                            </div>
    
                            <div style='margin-bottom:28px;'>
    
                                <a
                                    href='{$safeLink}'
                                    style='display:inline-block;padding:14px 24px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>
    
                                    Review & Accept Agreement
    
                                </a>
    
                            </div>
    
                            <div style='padding:16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;margin-bottom:24px;'>
    
                                <div style='font-weight:700;color:#9a3412;margin-bottom:8px;'>
                                    Important Information
                                </div>
    
                                <ul style='margin:0;padding-left:18px;color:#7c2d12;line-height:1.7;'>
    
                                    <li>Please review all pricing, deliverables and service terms carefully.</li>
    
                                    <li>Acceptance of this agreement is required before project onboarding can begin.</li>
    
                                    <li>This secure agreement link is unique to your organization.</li>
    
                                    <li>Please do not share this link with unauthorized persons.</li>
    
                                </ul>
    
                            </div>
    
                            <p style='font-size:15px;line-height:1.7;color:#4b5563;'>
    
                                If you have any questions regarding the agreement,
                                scope of work, pricing or onboarding process,
                                please contact our Client Success Team before accepting.
    
                            </p>
    
                            <p style='font-size:15px;line-height:1.7;'>
    
                                Regards,<br>
    
                                <strong>MQlus Client Success Team</strong>
    
                            </p>
    
                        </td>
                    </tr>
    
                </table>
    
            </body>
    
            </html>
            ";
    
            $mail->AltBody =
                'Review your onboarding agreement and confirm acceptance: ' .
                $agreementLink;
    
            return $mail->send();
        }
    );
}


/*
|--------------------------------------------------------------------------
| Send Agreement Completed Mail
|--------------------------------------------------------------------------
*/
function sendAgreementCompletedMail(
    int $leadId,
    string $toEmail,
    string $fullName,
    string $signedAgreementUrl,
    string $signedAgreementPath = ''
): bool {

    $subject = 'Your Client Onboarding Agreement is Approved';

    return sendLoggedMail(
        'onboardingAgreement',
        $leadId,
        'agreementApproved',
        $toEmail,
        $fullName,
        $subject,
        function () use (
            $toEmail,
            $fullName,
            $signedAgreementUrl,
            $signedAgreementPath,
            $subject
        ) {

            $safeName =
                htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

            $safeUrl =
                htmlspecialchars($signedAgreementUrl, ENT_QUOTES, 'UTF-8');

            $mail =
                createMailer('MQlus Client Success Team');

            $mail->addAddress($toEmail);

            if (
                $signedAgreementPath !== ''
                && file_exists($signedAgreementPath)
            ) {
                $mail->addAttachment(
                    $signedAgreementPath,
                    'Signed_Onboarding_Agreement.pdf'
                );
            }

            $mail->Subject = $subject;

            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>
            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>

                <table width='100%' cellspacing='0' cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>

                    <tr>
                        <td style='padding:36px;'>

                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;color:#111827;'>
                                Agreement Approved Successfully
                            </div>

                            <p style='font-size:16px;line-height:1.7;'>
                                Hello {$safeName},
                            </p>

                            <p style='font-size:16px;line-height:1.7;color:#374151;'>
                                Your client onboarding agreement has been reviewed and approved successfully.
                                The signed agreement PDF is attached with this email for your records.
                            </p>

                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin:24px 0;'>
                                <div style='font-weight:700;color:#166534;margin-bottom:8px;'>
                                    Current Status
                                </div>
                                <div style='font-size:15px;line-height:1.7;color:#166534;'>
                                    Approved • Onboarding Completed
                                </div>
                            </div>

                            <div style='margin:0 0 24px;'>
                                <a href='{$safeUrl}'
                                style='display:inline-block;padding:14px 24px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>
                                    View Signed Agreement PDF
                                </a>
                            </div>

                            <p style='font-size:15px;line-height:1.7;color:#4b5563;'>
                                Please keep this signed agreement safely for future reference.
                            </p>

                            <p style='font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus Client Success Team</strong>
                            </p>

                        </td>
                    </tr>

                </table>

            </body>
            </html>
            ";

            $mail->AltBody =
                "Your onboarding agreement is approved. Signed agreement PDF: {$signedAgreementUrl}";

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Send Agreement Correction Required Mail
|--------------------------------------------------------------------------
*/
function sendAgreementCorrectionMail(
    int $leadId,
    string $toEmail,
    string $fullName,
    string $agreementLink,
    string $remark
): bool {
    $subject = 'Action Required: Agreement Correction Needed';

    return sendLoggedMail(
        'onboardingAgreement',
        $leadId,
        'agreementCorrection',
        $toEmail,
        $fullName,
        $subject,
        function() use ($toEmail, $fullName, $agreementLink, $remark, $subject) {
            $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeLink = htmlspecialchars($agreementLink, ENT_QUOTES, 'UTF-8');
            $safeRemark = nl2br(htmlspecialchars($remark, ENT_QUOTES, 'UTF-8'));

            $mail = createMailer('MQlus HRMS');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;

            $mail->Body = "
            <html>
            <body style='margin:0;padding:24px;font-family:Arial,sans-serif;'>
                <h2>Hello {$safeName},</h2>
                <p>Your onboarding agreement requires corrections:</p>
                <blockquote style='padding:10px;border-left:4px solid #f87171;background:#fee2e2;'>{$safeRemark}</blockquote>
                <p>Please review and update your agreement:</p>
                <a href='{$safeLink}' style='padding:10px 20px;background:#f97316;color:#fff;text-decoration:none;border-radius:6px;'>Review Agreement</a>
                <p>Thank you!</p>
            </body>
            </html>";

            $mail->AltBody = "Agreement correction required: {$remark}. Review here: {$agreementLink}";
            return $mail->send();
        }
    );
}



/*
|--------------------------------------------------------------------------
| Salary Slip Preview Email
|--------------------------------------------------------------------------
*/

function sendSalarySlipPreviewEmail(
    int $employeeId,
    string $toEmail,
    string $employeeName,
    string $periodStart,
    string $periodEnd,
    array $previewData
): bool {

    $subject = 'Salary Slip Preview - ' . date('M Y', strtotime($periodStart));

    return sendLoggedMail(
        'payroll',
        $employeeId,
        'salarySlipPreview',
        $toEmail,
        $employeeName,
        $subject,
        function () use (
            $toEmail,
            $employeeName,
            $periodStart,
            $periodEnd,
            $previewData,
            $subject
        ) {
            $mail = createMailer('MQlus Payroll');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = buildSalarySlipPreviewHtml($employeeName, $periodStart, $periodEnd, $previewData);
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Send Salary Slip (Payment Info + PDF) -- the "Send Salary Slip" icon
|--------------------------------------------------------------------------
| Minimal by design: just the recorded payment(s) as text and the existing
| approved PDF attached, per the payment column it mirrors. $payments is
| the slip's own payrollSalarySlipPayments rows (possibly more than one).
*/

function sendSalarySlipPaymentInfoEmail(
    int $salarySlipId,
    string $toEmail,
    string $employeeName,
    string $periodStart,
    string $periodEnd,
    array $payments,
    string $pdfFullPath
): bool {

    $month = date('F Y', strtotime($periodStart));
    $subject = 'Salary Slip - ' . $month;
    $safeName = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');

    return sendLoggedMail(
        'payroll',
        $salarySlipId,
        'salarySlipPaymentInfo',
        $toEmail,
        $employeeName,
        $subject,
        function () use ($toEmail, $safeName, $month, $payments, $pdfFullPath, $subject) {
            $mail = createMailer('MQlus Payroll');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);

            $body = '<p>Dear ' . $safeName . ',</p>'
                . '<p>Your salary slip for ' . htmlspecialchars($month, ENT_QUOTES, 'UTF-8') . ' is attached.</p>';

            if (!empty($payments)) {
                foreach ($payments as $payment) {
                    $body .= '<p>'
                        . 'Transaction Number: ' . htmlspecialchars((string)$payment['transactionReference'], ENT_QUOTES, 'UTF-8') . '<br>'
                        . 'Payment Date: ' . htmlspecialchars(date('d M Y', strtotime((string)$payment['transactionDate'])), ENT_QUOTES, 'UTF-8') . '<br>'
                        . 'Payment Time: ' . htmlspecialchars(date('H:i', strtotime((string)$payment['createdAt'])), ENT_QUOTES, 'UTF-8') . '<br>'
                        . 'Amount: Rs. ' . number_format((float)$payment['paymentAmount'], 2)
                        . '</p>';
                }
            }

            $body .= '<p>Regards,<br>Payroll Team</p>';

            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            if (is_file($pdfFullPath)) {
                $mail->addAttachment($pdfFullPath, basename($pdfFullPath));
            }

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Build Salary Slip Preview HTML
|--------------------------------------------------------------------------
*/

function buildSalarySlipPreviewHtml(
    string $employeeName,
    string $periodStart,
    string $periodEnd,
    array $data,
    array $descriptions = [],
    array $payments = [],
    string $title = 'Salary Slip Preview'
): string {

    // Row label -> traceSlipDescriptions() key (PayrollApprovalEngine).
    // A label with no matching key, or no description found, renders with
    // no extra line -- never a guessed reason.
    $descriptionKeyByLabel = [
        'Manual Deduction' => 'manualDeduction',
        'Advance Payment Deduction' => 'advanceDeduction',
        'Loan Payment Deduction' => 'loanDeduction',
        'Leave Deduction' => 'leaveDeduction',
        'Commission / Bonus' => 'commissionBonus',
        'Overtime' => 'overtime',
        'Expense Reimbursement' => 'expenseReimbursement',
    ];

    $descriptionRow = function (string $label) use ($descriptionKeyByLabel, $descriptions): string {
        $key = $descriptionKeyByLabel[$label] ?? null;
        $text = $key !== null ? trim((string)($descriptions[$key] ?? '')) : '';

        if ($text === '') {
            return '';
        }

        return '<tr><td colspan="2" style="padding:0 0 6px 0; font-size:12px; color:#64748b; border-bottom:1px solid #f1f5f9;">Reason: '
            . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    };

    $earnings = $data['earnings'] ?? [];
    $deductions = $data['deductions'] ?? [];
    $leave = $data['leave'] ?? [];
    $attendance = $data['attendance'] ?? [];
    $period = $data['period'] ?? [];

    $grossEarnings = (float)($earnings['grossEarnings'] ?? 0);
    $totalDeductions = (float)($deductions['totalDeductions'] ?? 0);
    $netPay = (float)($data['netPay'] ?? 0);
    $safeName = htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8');

    $periodLabel = date('M Y', strtotime($periodStart));

    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; background: #f8fafc; padding: 20px; }
            .container { max-width: 700px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.06); }
            .header { background: linear-gradient(135deg, #0b8ba8, #0d9ec0); color: #fff; padding: 24px 30px; text-align: center; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 700; }
            .header p { margin: 4px 0 0; opacity: 0.85; font-size: 14px; }
            .content { padding: 30px; }
            .greeting { font-size: 16px; margin-bottom: 20px; }
            .employee-info { background: #f8fafc; padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; border-left: 4px solid #0b8ba8; }
            .employee-info table { width: 100%; border-collapse: collapse; }
            .employee-info td { padding: 4px 8px; font-size: 14px; }
            .employee-info .label { color: #64748b; font-weight: 500; }
            .employee-info .value { font-weight: 600; }
            .summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 24px; }
            .summary-card { background: #f8fafc; padding: 14px; border-radius: 8px; text-align: center; border: 1px solid #e2e8f0; }
            .summary-card .amount { font-size: 20px; font-weight: 700; }
            .summary-card .label { font-size: 12px; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; }
            .summary-card.gross .amount { color: #0b8ba8; }
            .summary-card.deduction .amount { color: #dc2626; }
            .summary-card.net .amount { color: #16a34a; }
            .section-title { font-size: 15px; font-weight: 700; color: #0f172a; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin: 24px 0 12px 0; }
            table.details { width: 100%; border-collapse: collapse; }
            table.details td { padding: 6px 0; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
            table.details tr:last-child td { border-bottom: none; }
            table.details .label { color: #475569; }
            table.details .value { font-weight: 600; text-align: right; }
            table.details .total-row td { font-weight: 700; border-top: 2px solid #0b8ba8; padding-top: 8px; }
            .footer { text-align: center; padding: 20px 30px; color: #94a3b8; font-size: 12px; border-top: 1px solid #e2e8f0; background: #fafbfc; }
            @media (max-width: 480px) {
                .summary-cards { grid-template-columns: 1fr; }
                .employee-info table td { display: block; padding: 2px 0; }
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>
                <p>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</p>
            </div>
            <div class="content">
                <div class="greeting">
                    Dear <strong>' . $safeName . '</strong>,
                </div>
                <p style="color:#475569; margin-bottom:20px;">
                    Please find below the preview of your salary slip for the period 
                    <strong>' . htmlspecialchars(date('d M Y', strtotime($periodStart)), ENT_QUOTES, 'UTF-8') . 
                    ' to ' . htmlspecialchars(date('d M Y', strtotime($periodEnd)), ENT_QUOTES, 'UTF-8') . '</strong>.
                </p>

                <div class="employee-info">
                    <table>
                        <tr>
                            <td class="label">Employee</td>
                            <td class="value">' . $safeName . '</td>
                            <td class="label">Period</td>
                            <td class="value">' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</td>
                        </tr>
                    </table>
                </div>

                <div class="summary-cards">
                    <div class="summary-card gross">
                        <div class="amount">Rs. ' . number_format($grossEarnings, 2) . '</div>
                        <div class="label">Gross Pay</div>
                    </div>
                    <div class="summary-card deduction">
                        <div class="amount">Rs. ' . number_format($totalDeductions, 2) . '</div>
                        <div class="label">Deductions</div>
                    </div>
                    <div class="summary-card net">
                        <div class="amount">Rs. ' . number_format($netPay, 2) . '</div>
                        <div class="label">Net Pay</div>
                    </div>
                </div>';

    // Earnings Section
    $earningRows = $earnings['rows'] ?? [];
    $earningRows = array_filter($earningRows, fn($r) => (float)($r['amount'] ?? 0) > 0);

    if (!empty($earningRows)) {
        $html .= '<div class="section-title">📈 Earnings</div>
        <table class="details">';
        foreach ($earningRows as $row) {
            $html .= '<tr>
                <td class="label">' . htmlspecialchars($row['label'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>
                <td class="value">Rs. ' . number_format((float)($row['amount'] ?? 0), 2) . '</td>
            </tr>';
            $html .= $descriptionRow((string)($row['label'] ?? ''));
        }
        $html .= '<tr class="total-row">
            <td class="label">Total Earnings</td>
            <td class="value">Rs. ' . number_format($grossEarnings, 2) . '</td>
        </tr></table>';
    }

    // Deductions Section
    $deductionRows = array_merge(
        $deductions['rows'] ?? [],
        [
            ['label' => 'Training Hold', 'amount' => $deductions['trainingHoldDeduction'] ?? 0],
            ['label' => 'Leave Deduction', 'amount' => $deductions['leaveDeduction'] ?? 0],
            ['label' => 'Half Day Deduction', 'amount' => $deductions['halfDayDeduction'] ?? 0],
            ['label' => 'Manual Deduction', 'amount' => $deductions['manualDeduction'] ?? 0],
            ['label' => 'Fixed Deduction', 'amount' => $deductions['fixedEmployeeDeduction'] ?? 0],
            ['label' => 'Advance Payment Deduction', 'amount' => $deductions['advanceDeduction'] ?? 0],
            ['label' => 'Loan Payment Deduction', 'amount' => $deductions['loanDeduction'] ?? 0],
        ]
    );
    $deductionRows = array_filter($deductionRows, fn($r) => (float)($r['amount'] ?? 0) > 0);

    if (!empty($deductionRows)) {
        $html .= '<div class="section-title">📉 Deductions</div>
        <table class="details">';
        foreach ($deductionRows as $row) {
            $html .= '<tr>
                <td class="label">' . htmlspecialchars($row['label'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>
                <td class="value">Rs. ' . number_format((float)($row['amount'] ?? 0), 2) . '</td>
            </tr>';
            $html .= $descriptionRow((string)($row['label'] ?? ''));
        }
        $html .= '<tr class="total-row">
            <td class="label">Total Deductions</td>
            <td class="value">Rs. ' . number_format($totalDeductions, 2) . '</td>
        </tr></table>';
    }

    // Leave Summary
    $actualPaidLeave = (float)($leave['approvedPaidLeaveDays'] ?? 0);
    $probationLeave = (float)($leave['probationLeaveDays'] ?? 0);
    $unpaidLeave = (float)($leave['approvedUnpaidLeaveDays'] ?? 0);
    $halfDays = (int)($attendance['halfDays'] ?? 0);

    $leaveRows = [];
    if ($probationLeave > 0) $leaveRows[] = ['label' => 'Probation Unpaid Leave', 'value' => $probationLeave . ' Days'];
    if ($actualPaidLeave > 0) $leaveRows[] = ['label' => 'Paid Leave Used', 'value' => $actualPaidLeave . ' Days'];
    if ($unpaidLeave > 0) $leaveRows[] = ['label' => 'Unpaid Leave Used', 'value' => $unpaidLeave . ' Days'];
    if ($halfDays > 0) $leaveRows[] = ['label' => 'Half Day Count', 'value' => $halfDays];

    if (!empty($leaveRows)) {
        $html .= '<div class="section-title">📅 Leave Summary</div>
        <table class="details">';
        foreach ($leaveRows as $row) {
            $html .= '<tr>
                <td class="label">' . htmlspecialchars($row['label'] ?? '-', ENT_QUOTES, 'UTF-8') . '</td>
                <td class="value">' . htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8') . '</td>
            </tr>';
        }
        $html .= '</table>';
    }

    // Payment Details -- from the slip's own payrollSalarySlipPayments
    // rows (existing payment table); shows every record rather than
    // guessing at a single transaction when more than one exists.
    if (!empty($payments)) {
        $html .= '<div class="section-title">💳 Payment Details</div>
        <table class="details">';
        foreach ($payments as $payment) {
            $paymentTime = !empty($payment['createdAt']) ? date('H:i', strtotime((string)$payment['createdAt'])) : '--';
            $html .= '<tr><td colspan="2" style="padding:8px 0; border-bottom:1px solid #f1f5f9;">'
                . '<div style="font-size:14px;"><strong>Transaction Number:</strong> ' . htmlspecialchars((string)($payment['transactionReference'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div style="font-size:14px;"><strong>Payment Date:</strong> ' . htmlspecialchars(date('d M Y', strtotime((string)($payment['transactionDate'] ?? ''))), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div style="font-size:14px;"><strong>Payment Time:</strong> ' . htmlspecialchars($paymentTime, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div style="font-size:14px;"><strong>Amount:</strong> Rs. ' . number_format((float)($payment['paymentAmount'] ?? 0), 2) . '</div>'
                . '</td></tr>';
        }
        $html .= '</table>';
    }

    $html .= '
                <div style="text-align:center; padding:16px; background:#f0fdf4; border-radius:8px; margin-top:24px; border:1px solid #bbf7d0;">
                    <strong style="font-size:18px; color:#16a34a;">Net Payable: Rs. ' . number_format($netPay, 2) . '</strong>
                </div>
                <p style="color:#94a3b8; font-size:12px; margin-top:16px; text-align:center;">
                    This is a system-generated preview. Please contact HR for any discrepancies.
                </p>
            </div>
            <div class="footer">
                <p>&copy; ' . date('Y') . ' MQlus. All rights reserved.</p>
                <p style="margin-top:4px;">This email is confidential and intended solely for the addressee.</p>
            </div>
        </div>
    </body>
    </html>';

    return $html;
}

/*
|--------------------------------------------------------------------------
| Send Salary Slip Overview Email -- the Overview modal's "Send Mail" button
|--------------------------------------------------------------------------
| Builds from the slip's saved calculation snapshot (never recalculates),
| plus the same traced descriptions and payment rows the modal displays --
| reuses buildSalarySlipPreviewHtml() rather than a second template.
*/

function sendSalarySlipOverviewEmail(
    int $salarySlipId,
    string $toEmail,
    string $employeeName,
    string $periodStart,
    string $periodEnd,
    array $calculation,
    array $descriptions,
    array $payments,
    string $pdfFullPath = ''
): bool {

    $subject = 'Salary Slip Overview - ' . date('M Y', strtotime($periodStart));

    return sendLoggedMail(
        'payroll',
        $salarySlipId,
        'salarySlipOverview',
        $toEmail,
        $employeeName,
        $subject,
        function () use (
            $toEmail,
            $employeeName,
            $periodStart,
            $periodEnd,
            $calculation,
            $descriptions,
            $payments,
            $pdfFullPath,
            $subject
        ) {
            $mail = createMailer('MQlus Payroll');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = buildSalarySlipPreviewHtml(
                $employeeName,
                $periodStart,
                $periodEnd,
                $calculation,
                $descriptions,
                $payments,
                'Salary Slip Overview'
            );
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $mail->Body));

            if ($pdfFullPath !== '' && is_file($pdfFullPath)) {
                $mail->addAttachment($pdfFullPath, basename($pdfFullPath));
            }

            return $mail->send();
        }
    );
}

/*
|--------------------------------------------------------------------------
| Send Onboarding Form Email to Client
|--------------------------------------------------------------------------
*/

function sendOnboardingFormEmail(
    int $leadId,
    string $toEmail,
    string $fullName,
    string $formLink,
    string $clientCode,
    array $services = [],
    string $type = 'full'
): bool {
    
    $subject = $type === 'full' 
        ? "Client Onboarding Form - {$clientCode}"
        : "New Services Added - {$clientCode}";

    return sendLoggedMail(
        'onboardingForm',
        $leadId,
        $type === 'full' ? 'formSent' : 'newServicesAdded',
        $toEmail,
        $fullName,
        $subject,
        function() use ($toEmail, $fullName, $formLink, $clientCode, $subject, $type, $services) {
            
            $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
            $safeFormLink = htmlspecialchars($formLink, ENT_QUOTES, 'UTF-8');
            $safeClientCode = htmlspecialchars($clientCode, ENT_QUOTES, 'UTF-8');
            
            $serviceLabels = [
                'socialMedia' => 'Social Media (IG | FB)',
                'youtube' => 'YouTube',
                'pinterest' => 'Pinterest',
                'twitter' => 'Twitter (X)',
                'gmb' => 'Google My Business',
                'googleAds' => 'Google Ads',
                'metaAds' => 'Meta Ads (FB + IG)',
                'videos' => 'Videos'
            ];
            
            $serviceList = '';
            foreach ($services as $service) {
                $label = $serviceLabels[$service] ?? $service;
                $serviceList .= "<li style='padding:6px 0;border-bottom:1px solid #f0f0f0;'><span style='color:#16a34a;font-weight:bold;'>✓</span> {$label}</li>";
            }
            
            $mail = createMailer('MQlus Client Success Team');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            
            if ($type === 'full') {
                $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                
                <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>
                
                    <table role='presentation' width='100%' cellspacing='0' cellpadding='0'
                    style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>
                    
                        <tr>
                            <td style='padding:36px;'>
                    
                                <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                    Client Onboarding Form 📋
                                </div>
                    
                                <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                                    Dear <strong>{$safeName}</strong>,
                                </p>
                    
                                <p style='margin:0 0 18px;font-size:16px;line-height:1.7;color:#374151;'>
                                    We are excited to welcome you aboard! To complete your onboarding process, please fill out the form below with the required credentials and information.
                                </p>
                    
                                <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin:20px 0;'>
                                    <div style='font-weight:700;color:#065f46;margin-bottom:8px;'>
                                        Client Code: <span style='font-weight:700;'>{$safeClientCode}</span>
                                    </div>
                                    <div style='font-size:15px;color:#065f46;line-height:1.7;'>
                                        Please use this code for future reference.
                                    </div>
                                </div>
                    
                                <div style='padding:18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin:24px 0;'>
                                    <div style='font-weight:700;color:#0f172a;margin-bottom:10px;'>
                                        Services to Configure
                                    </div>
                                    <ul style='margin:0;padding-left:18px;color:#334155;line-height:1.7;list-style:none;'>
                                        {$serviceList}
                                    </ul>
                                </div>
                    
                                <div style='margin:0 0 24px;'>
                                    <a href='{$safeFormLink}'
                                    style='display:inline-block;padding:14px 32px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>
                                        📝 Fill Onboarding Form
                                    </a>
                                </div>
                    
                                <div style='padding:16px;background:#fef9e7;border:1px solid #fde68a;border-radius:10px;margin-bottom:24px;'>
                                    <div style='font-weight:700;color:#92400e;margin-bottom:8px;'>
                                        ⏰ Link Expiry
                                    </div>
                                    <div style='font-size:15px;line-height:1.7;color:#78350f;'>
                                        This secure link will expire in 7 days. Please complete the form at your earliest convenience.
                                    </div>
                                </div>
                    
                                <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                                    If you have any questions or need assistance, please don't hesitate to contact our support team.
                                </p>
                    
                                <p style='margin:0;font-size:15px;line-height:1.7;'>
                                    Regards,<br>
                                    <strong>MQlus Client Success Team</strong>
                                </p>
                    
                            </td>
                        </tr>
                    
                    </table>
                
                </body>
                </html>
                ";
            } else {
                // Partial email - New services only
                $mail->Body = "
                <!DOCTYPE html>
                <html lang='en'>
                <head>
                    <meta charset='UTF-8'>
                    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                </head>
                
                <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>
                
                    <table role='presentation' width='100%' cellspacing='0' cellpadding='0'
                    style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>
                    
                        <tr>
                            <td style='padding:36px;'>
                    
                                <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                    New Services Added 🆕
                                </div>
                    
                                <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                                    Dear <strong>{$safeName}</strong>,
                                </p>
                    
                                <div style='padding:16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;margin:20px 0;'>
                                    <div style='font-weight:700;color:#1d4ed8;margin-bottom:8px;'>
                                        📌 Note
                                    </div>
                                    <div style='font-size:15px;line-height:1.7;color:#1e3a8a;'>
                                        You already have an active onboarding. We've added new services to your account. Please provide credentials only for the new services listed below.
                                    </div>
                                </div>
                    
                                <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin:20px 0;'>
                                    <div style='font-weight:700;color:#065f46;margin-bottom:8px;'>
                                        Client Code: <span style='font-weight:700;'>{$safeClientCode}</span>
                                    </div>
                                </div>
                    
                                <div style='padding:18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin:24px 0;'>
                                    <div style='font-weight:700;color:#0f172a;margin-bottom:10px;'>
                                        New Services to Configure
                                    </div>
                                    <ul style='margin:0;padding-left:18px;color:#334155;line-height:1.7;list-style:none;'>
                                        {$serviceList}
                                    </ul>
                                </div>
                    
                                <div style='margin:0 0 24px;'>
                                    <a href='{$safeFormLink}'
                                    style='display:inline-block;padding:14px 32px;background:#111827;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;'>
                                        📝 Configure New Services
                                    </a>
                                </div>
                    
                                <div style='padding:16px;background:#fef9e7;border:1px solid #fde68a;border-radius:10px;margin-bottom:24px;'>
                                    <div style='font-weight:700;color:#92400e;margin-bottom:8px;'>
                                        ⏰ Link Expiry
                                    </div>
                                    <div style='font-size:15px;line-height:1.7;color:#78350f;'>
                                        This secure link will expire in 7 days. Please complete the form at your earliest convenience.
                                    </div>
                                </div>
                    
                                <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                                    If you have any questions or need assistance, please don't hesitate to contact our support team.
                                </p>
                    
                                <p style='margin:0;font-size:15px;line-height:1.7;'>
                                    Regards,<br>
                                    <strong>MQlus Client Success Team</strong>
                                </p>
                    
                            </td>
                        </tr>
                    
                    </table>
                
                </body>
                </html>
                ";
            }
            
            $mail->AltBody = $type === 'full' 
                ? "Client Onboarding Form - {$safeClientCode}\n\nDear {$safeName},\n\nPlease complete your onboarding form: {$safeFormLink}\n\nServices: " . implode(', ', $services)
                : "New Services Added - {$safeClientCode}\n\nDear {$safeName},\n\nPlease configure the new services: {$safeFormLink}\n\nNew Services: " . implode(', ', $services);

            return $mail->send();
        }
    );
}


/*
|--------------------------------------------------------------------------
| Send Service Added Notification (No Form)
|--------------------------------------------------------------------------
*/

function sendServiceAddedNotification(
    int $leadId,
    string $toEmail,
    string $fullName,
    string $clientCode,
    array $newServices
): bool {
    
    $serviceLabels = [
        'socialMedia' => 'Social Media (IG | FB)',
        'youtube' => 'YouTube',
        'pinterest' => 'Pinterest',
        'twitter' => 'Twitter (X)',
        'gmb' => 'Google My Business',
        'googleAds' => 'Google Ads',
        'metaAds' => 'Meta Ads (FB + IG)',
        'videos' => 'Videos'
    ];
    
    $serviceList = '';
    foreach ($newServices as $service) {
        $label = $serviceLabels[$service] ?? $service;
        $serviceList .= "<li style='padding:6px 0;border-bottom:1px solid #f0f0f0;'><span style='color:#16a34a;font-weight:bold;'>✓</span> {$label}</li>";
    }
    
    $subject = "New Service Added - {$clientCode}";
    $safeName = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');
    $safeClientCode = htmlspecialchars($clientCode, ENT_QUOTES, 'UTF-8');

    return sendLoggedMail(
        'onboardingForm',
        $leadId,
        'serviceAddedNotification',
        $toEmail,
        $fullName,
        $subject,
        function() use ($toEmail, $fullName, $clientCode, $subject, $serviceList, $safeName, $safeClientCode) {
            
            $mail = createMailer('MQlus Client Success Team');
            $mail->addAddress($toEmail);
            $mail->Subject = $subject;
            
            $mail->Body = "
            <!DOCTYPE html>
            <html lang='en'>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            </head>
            
            <body style='margin:0;padding:24px;background:#f4f6f8;font-family:Arial,sans-serif;color:#111827;'>
            
                <table role='presentation' width='100%' cellspacing='0' cellpadding='0'
                style='max-width:700px;margin:0 auto;background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;'>
                
                    <tr>
                        <td style='padding:36px;'>
                
                            <div style='font-size:26px;font-weight:700;margin-bottom:24px;'>
                                New Service Added 📢
                            </div>
                
                            <p style='margin:0 0 16px;font-size:16px;line-height:1.7;'>
                                Dear <strong>{$safeName}</strong>,
                            </p>
                
                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin:20px 0;'>
                                <div style='font-weight:700;color:#065f46;margin-bottom:8px;'>
                                    ✅ Good News!
                                </div>
                                <div style='font-size:15px;line-height:1.7;color:#065f46;'>
                                    We've added a new service to your account. This service does not require any credentials, so no action is needed from your side.
                                </div>
                            </div>
                
                            <div style='padding:16px;background:#ecfdf5;border:1px solid #bbf7d0;border-radius:10px;margin:20px 0;'>
                                <div style='font-weight:700;color:#065f46;margin-bottom:8px;'>
                                    Client Code: <span style='font-weight:700;'>{$safeClientCode}</span>
                                </div>
                            </div>
                
                            <div style='padding:18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;margin:24px 0;'>
                                <div style='font-weight:700;color:#0f172a;margin-bottom:10px;'>
                                    New Service Added
                                </div>
                                <ul style='margin:0;padding-left:18px;color:#334155;line-height:1.7;list-style:none;'>
                                    {$serviceList}
                                </ul>
                            </div>
                
                            <p style='margin:0 0 14px;font-size:15px;line-height:1.7;color:#4b5563;'>
                                Our team will take care of the rest. You don't need to do anything.
                            </p>
                
                            <p style='margin:0;font-size:15px;line-height:1.7;'>
                                Regards,<br>
                                <strong>MQlus Client Success Team</strong>
                            </p>
                
                        </td>
                    </tr>
                
                </table>
            
            </body>
            </html>
            ";
            
            $mail->AltBody = "New Service Added - {$safeClientCode}\n\nDear {$safeName},\n\nWe've added a new service to your account. No action required from your side.\n\nNew Service: " . implode(', ', $newServices);

            return $mail->send();
        }
    );
}

