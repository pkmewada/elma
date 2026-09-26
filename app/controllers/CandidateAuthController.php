<?php
require_once __DIR__ . '/../../includes/auth-functions.php';
require_once __DIR__ . '/../models/CandidateModel.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/Csrf.php';

class CandidateAuthController
{
    private $candidateModel;

    public function __construct()
    {
        $this->candidateModel = new CandidateModel();
    }

    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $error = '';
        $emailValue = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $emailValue = trim(getPost('email'));
            $password   = trim(getPost('password'));

            if ($emailValue === '' || $password === '') {
                $error = 'Please enter both email and password.';
            } else {

                $candidate = $this->candidateModel->getCandidateByEmail($emailValue);

                if (!$candidate) {
                    $error = 'Invalid login credentials.';
                } elseif (!password_verify($password, (string) $candidate['passwordHash'])) {
                    $error = 'Invalid login credentials.';
                } elseif (($candidate['accountStatus'] ?? '') !== 'Active') {
                    $error = 'Account is inactive. Contact HR.';
                } else {

                    session_regenerate_id(true);
                    regenerateCsrfToken();

                    unset(
                        $_SESSION['userId'],
                        $_SESSION['companyId']
                    );

                    $status = trim((string) ($candidate['profileStatus'] ?? ''));
                    $accountStatus = trim((string) ($candidate['accountStatus'] ?? ''));
                    $isTempPassword = ((int) ($candidate['isTempPassword'] ?? 0) === 1);

                    $_SESSION['candidateId']            = (int) $candidate['id'];
                    $_SESSION['candidateName']          = (string) $candidate['fullName'];
                    $_SESSION['candidateEmail']         = (string) $candidate['emailAddress'];
                    $_SESSION['candidateProfileStatus'] = $status;
                    $_SESSION['authUserType']           = 'employee';

                    // Temp password users: only verified profile should go to reset page.
                    if ($isTempPassword) {
                        if ($status === 'Verified' || $status === 'Completed') {
                            $_SESSION['candidateForceReset'] = true;
                            redirectTo('candidate-reset-password');
                        }

                        $_SESSION['candidateForceReset'] = false;
                        redirectTo('candidate-profile');
                    }

                    $_SESSION['candidateForceReset'] = false;

                    // Permanent password users: verified/completed -> dashboard, otherwise waiting.
                    if ($status === 'Verified' || $status === 'Completed' || $accountStatus === 'Active') {
                        redirectTo('emp-dashboard');
                    }

                    redirectTo('candidate-waiting');
                }
            }
        }

        include __DIR__ . '/../views/candidate-login.php';
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Candidate Forgot Password
    |--------------------------------------------------------------------------
    */
    
    public function forgotPassword()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    
        $error = '';
    
        $successMessage = '';
    
        $emailValue = '';
    
        /*
        |--------------------------------------------------------------------------
        | Handle Form Submit
        |--------------------------------------------------------------------------
        */
    
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
            $emailValue =
                trim(
                    getPost('email')
                );
    
            /*
            |--------------------------------------------------------------------------
            | Validate Email
            |--------------------------------------------------------------------------
            */
    
            if ($emailValue === '') {
    
                $error =
                    'Please enter your email address.';
    
            }
    
            elseif (
    
                !filter_var(
                    $emailValue,
                    FILTER_VALIDATE_EMAIL
                )
    
            ) {
    
                $error =
                    'Please enter valid email address.';
    
            }
    
            else {
    
                /*
                |--------------------------------------------------------------------------
                | Fetch Candidate
                |--------------------------------------------------------------------------
                */
    
                $candidate =
                    $this->candidateModel
                        ->getCandidateByEmail(
                            $emailValue
                        );
    
                if (!$candidate) {
    
                    $error =
                        'No candidate account found with this email address.';
    
                }
    
                elseif (
    
                    ($candidate['accountStatus'] ?? '') !== 'Active'
    
                ) {
    
                    $error =
                        'Your account is inactive. Please contact HR.';
    
                }
    
                else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Generate OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $otp =
                        random_int(
                            1000,
                            9999
                        );
    
                    /*
                    |--------------------------------------------------------------------------
                    | OTP Expiry
                    |--------------------------------------------------------------------------
                    */
    
                    $otpExpiresAt =
                        date(
    
                            'Y-m-d H:i:s',
    
                            strtotime('+10 minutes')
    
                        );
    
                    /*
                    |--------------------------------------------------------------------------
                    | Save OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $updated =
                        $this->candidateModel
                            ->updateResetOtp(
    
                                $emailValue,
    
                                (string)$otp,
    
                                $otpExpiresAt
    
                            );
    
                    if (!$updated) {
    
                        $error =
                            'Unable to generate verification code. Please try again.';
    
                    }
    
                    else {
    
                        /*
                        |--------------------------------------------------------------------------
                        | Send Reset OTP Mail
                        |--------------------------------------------------------------------------
                        */
    
                        $mailSent =
                            sendCandidatePasswordResetOtpEmail(
    
                                $candidate['emailAddress'],
    
                                $candidate['fullName'],
    
                                (string)$otp
    
                            );
    
                        /*
                        |--------------------------------------------------------------------------
                        | Save Reset Email Session
                        |--------------------------------------------------------------------------
                        */
    
                        $_SESSION['candidateResetEmail'] =
                            $emailValue;
    
                        /*
                        |--------------------------------------------------------------------------
                        | Redirect Verify OTP
                        |--------------------------------------------------------------------------
                        */
    
                        redirectTo(
    
                            'candidate-verify-reset-otp?email=' .
    
                            urlencode($emailValue) .
    
                            '&email_sent=' .
    
                            ($mailSent ? '1' : '0')
    
                        );
    
                    }
    
                }
    
            }
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Load View
        |--------------------------------------------------------------------------
        */
    
        include __DIR__ . '/../views/candidate-forgot-password.php';
    }
    
    
    
    /*
    |--------------------------------------------------------------------------
    | Candidate Verify Reset OTP
    |--------------------------------------------------------------------------
    */
    
    public function verifyResetOtp()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    
        $error = '';
    
        $successMessage = '';
    
        $emailValue =
            getQuery(
                'email',
                $_SESSION['candidateResetEmail'] ?? ''
            );
    
        /*
        |--------------------------------------------------------------------------
        | Redirect If Email Missing
        |--------------------------------------------------------------------------
        */
    
        if ($emailValue === '') {
    
            redirectTo(
                'candidate-forgot-password'
            );
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Email Failed
        |--------------------------------------------------------------------------
        */
    
        if (
    
            $_SERVER['REQUEST_METHOD'] === 'GET' &&
    
            getQuery('email_sent') === '0'
    
        ) {
    
            $error =
                'Unable to send verification code email. Please resend OTP.';
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Handle Form Submit
        |--------------------------------------------------------------------------
        */
    
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
            $emailValue =
                trim(
                    getPost(
                        'email',
                        $emailValue
                    )
                );
    
            $action =
                trim(
                    getPost(
                        'action',
                        'verify'
                    )
                );
    
            /*
            |--------------------------------------------------------------------------
            | Resend OTP
            |--------------------------------------------------------------------------
            */
    
            if ($action === 'resend') {
    
                $candidate =
                    $this->candidateModel
                        ->getCandidateByEmail(
                            $emailValue
                        );
    
                if (!$candidate) {
    
                    $error =
                        'Candidate account not found.';
    
                }
    
                else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Generate OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $otp =
                        random_int(
                            1000,
                            9999
                        );
    
                    /*
                    |--------------------------------------------------------------------------
                    | Expiry
                    |--------------------------------------------------------------------------
                    */
    
                    $otpExpiresAt =
                        date(
    
                            'Y-m-d H:i:s',
    
                            strtotime('+10 minutes')
    
                        );
    
                    /*
                    |--------------------------------------------------------------------------
                    | Save OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $updated =
                        $this->candidateModel
                            ->updateResetOtp(
    
                                $emailValue,
    
                                (string)$otp,
    
                                $otpExpiresAt
    
                            );
    
                    if (!$updated) {
    
                        $error =
                            'Unable to resend verification code.';
    
                    }
    
                    else {
    
                        /*
                        |--------------------------------------------------------------------------
                        | Send Mail
                        |--------------------------------------------------------------------------
                        */
    
                        $mailSent =
                            sendCandidatePasswordResetOtpEmail(
    
                                $candidate['emailAddress'],
    
                                $candidate['fullName'],
    
                                (string)$otp
    
                            );
    
                        $successMessage =
    
                            $mailSent
    
                                ? 'A new verification code has been sent.'
    
                                : 'OTP regenerated but email delivery failed.';
    
                    }
    
                }
    
            }
    
            /*
            |--------------------------------------------------------------------------
            | Verify OTP
            |--------------------------------------------------------------------------
            */
    
            else {
    
                $otpCode =
    
                    getPost('otp1') .
                    getPost('otp2') .
                    getPost('otp3') .
                    getPost('otp4');
    
                /*
                |--------------------------------------------------------------------------
                | Validate OTP
                |--------------------------------------------------------------------------
                */
    
                if (
    
                    !preg_match(
                        '/^\d{4}$/',
                        $otpCode
                    )
    
                ) {
    
                    $error =
                        'Please enter valid 4 digit verification code.';
    
                }
    
                else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Verify Reset OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $isValid =
                        $this->candidateModel
                            ->verifyResetOtp(
    
                                $emailValue,
    
                                $otpCode
    
                            );
    
                    if (!$isValid) {
    
                        $error =
                            'Invalid or expired verification code.';
    
                    }
    
                    else {
    
                        /*
                        |--------------------------------------------------------------------------
                        | Create Reset Session
                        |--------------------------------------------------------------------------
                        */
    
                        $_SESSION['candidatePasswordResetVerified'] =
                            true;
    
                        $_SESSION['candidateResetEmail'] =
                            $emailValue;
    
                        /*
                        |--------------------------------------------------------------------------
                        | Redirect Reset Password
                        |--------------------------------------------------------------------------
                        */
    
                        redirectTo(
                            'candidate-reset-forgot-password'
                        );
    
                    }
    
                }
    
            }
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Load View
        |--------------------------------------------------------------------------
        */
    
        include __DIR__ . '/../views/candidate-verify-reset-otp.php';
    }
    
    
    /*
    |--------------------------------------------------------------------------
    | Candidate Reset Forgot Password
    |--------------------------------------------------------------------------
    */
    
    public function resetForgotPassword()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    
        /*
        |--------------------------------------------------------------------------
        | Validate Reset Session
        |--------------------------------------------------------------------------
        */
    
        if (
    
            empty($_SESSION['candidatePasswordResetVerified']) ||
    
            empty($_SESSION['candidateResetEmail'])
    
        ) {
    
            redirectTo(
                'candidate-forgot-password'
            );
    
        }
    
        $error = '';
    
        $successMessage = '';
    
        $emailValue =
            $_SESSION['candidateResetEmail'];
    
        /*
        |--------------------------------------------------------------------------
        | Handle Form Submit
        |--------------------------------------------------------------------------
        */
    
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
            $password =
                trim(
                    getPost('password')
                );
    
            $confirmPassword =
                trim(
                    getPost('confirmPassword')
                );
    
            /*
            |--------------------------------------------------------------------------
            | Validate Fields
            |--------------------------------------------------------------------------
            */
    
            if (
    
                $password === '' ||
    
                $confirmPassword === ''
    
            ) {
    
                $error =
                    'Please fill all required fields.';
    
            }
    
            /*
            |--------------------------------------------------------------------------
            | Password Length
            |--------------------------------------------------------------------------
            */
    
            elseif (
    
                strlen($password) < 6
    
            ) {
    
                $error =
                    'Password must contain at least 6 characters.';
    
            }
    
            /*
            |--------------------------------------------------------------------------
            | Password Match
            |--------------------------------------------------------------------------
            */
    
            elseif (
    
                $password !== $confirmPassword
    
            ) {
    
                $error =
                    'Passwords do not match.';
    
            }
    
            else {
    
                /*
                |--------------------------------------------------------------------------
                | Hash Password
                |--------------------------------------------------------------------------
                */
    
                $passwordHash =
                    password_hash(
    
                        $password,
    
                        PASSWORD_DEFAULT
    
                    );
    
                /*
                |--------------------------------------------------------------------------
                | Update Password
                |--------------------------------------------------------------------------
                */
    
                $updated =
                    $this->candidateModel
                        ->updateForgotPassword(
    
                            $emailValue,
    
                            $passwordHash
    
                        );
    
                if (!$updated) {
    
                    $error =
                        'Unable to reset password. Please try again.';
    
                }
    
                else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Clear Reset Sessions
                    |--------------------------------------------------------------------------
                    */
    
                    unset(
                        $_SESSION['candidatePasswordResetVerified']
                    );
    
                    unset(
                        $_SESSION['candidateResetEmail']
                    );
    
                    /*
                    |--------------------------------------------------------------------------
                    | Success Session
                    |--------------------------------------------------------------------------
                    */
    
                    $_SESSION['candidateSuccessMessage'] =
    
                        'Password reset successfully. Please login.';
    
                    /*
                    |--------------------------------------------------------------------------
                    | Redirect Login
                    |--------------------------------------------------------------------------
                    */
    
                    redirectTo(
                        'candidate-login'
                    );
    
                }
    
            }
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Load View
        |--------------------------------------------------------------------------
        */
    
        include __DIR__ . '/../views/candidate-reset-forgot-password.php';
    }
    

    public function resetPassword()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['candidateId'])) {
            redirectTo('candidate-login');
        }

        $candidateId = (int) $_SESSION['candidateId'];
        $candidate = $this->candidateModel->getCandidateById($candidateId);

        if (!$candidate) {
            session_destroy();
            redirectTo('candidate-login');
        }

        if ((int) ($candidate['isTempPassword'] ?? 0) !== 1) {
            $_SESSION['candidateForceReset'] = false;

            $status = trim((string) ($candidate['profileStatus'] ?? ''));
            if ($status === 'Verified' || $status === 'Completed') {
                redirectTo('candidate-dashboard');
            }

            redirectTo('candidate-waiting');
        }

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $currentPassword = trim(getPost('currentPassword'));
            $newPassword = trim(getPost('newPassword'));
            $confirmPassword = trim(getPost('confirmPassword'));

            if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
                $error = 'Please fill all password fields.';
            } elseif (!password_verify($currentPassword, (string) $candidate['passwordHash'])) {
                $error = 'Current password is incorrect.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'New password must be at least 8 characters.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'New password and confirm password do not match.';
            } elseif ($currentPassword === $newPassword) {
                $error = 'New password must be different from temporary password.';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updated = $this->candidateModel->resetCandidatePassword($candidateId, $newHash);

                if ($updated) {
                    $_SESSION['candidateForceReset'] = false;
                    redirectTo('candidate-dashboard');
                } else {
                    $error = 'Unable to reset password right now. Please try again.';
                }
            }
        }

        include __DIR__ . '/../views/candidate-reset-password.php';
    }

public function profile()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['candidateId'])) {
        redirectTo('candidate-login');
    }

    if (!empty($_SESSION['candidateForceReset'])) {
        redirectTo('candidate-reset-password');
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $candidateId = (int) $_SESSION['candidateId'];

        /*
        |--------------------------------------------------------------------------
        | Collect + Format Inputs
        |--------------------------------------------------------------------------
        */

        $data = [

            'mobileNumber'            => preg_replace('/\D/', '', getPost('mobileNumber')),
            'alternativeNumber'      => preg_replace('/\D/', '', getPost('alternativeNumber')),
            'emergencyContactNumber' => preg_replace('/\D/', '', getPost('emergencyContactNumber')),

            'dateOfBirth' => trim(getPost('dateOfBirth')),

            'gender'         => ucwords(strtolower(trim(getPost('gender')))),
            'maritalStatus'  => ucwords(strtolower(trim(getPost('maritalStatus')))),

            'linkedInProfile'  => trim(getPost('linkedInProfile')),
            'instagramProfile'=> trim(getPost('instagramProfile')),

            'permanentAddress' => ucwords(strtolower(trim(getPost('permanentAddress')))),
            'localAddress'     => ucwords(strtolower(trim(getPost('localAddress')))),

            'cityName'  => ucwords(strtolower(trim(getPost('cityName')))),
            'stateName' => ucwords(strtolower(trim(getPost('stateName')))),

            'pinCode' => preg_replace('/\D/', '', getPost('pinCode')),

            'accountHolderName' => ucwords(strtolower(trim(getPost('accountHolderName')))),
            'bankName'          => ucwords(strtolower(trim(getPost('bankName')))),
            'accountNumber'     => preg_replace('/\D/', '', getPost('accountNumber')),
            'ifscCode'          => strtoupper(trim(getPost('ifscCode'))),
            'branchName'        => ucwords(strtolower(trim(getPost('branchName')))),

            'aadhaarNumber' => preg_replace('/\D/', '', getPost('aadhaarNumber')),
            'panNumber'     => strtoupper(trim(getPost('panNumber')))
        ];

        /*
        |--------------------------------------------------------------------------
        | Backend Validation
        |--------------------------------------------------------------------------
        */

        if (!preg_match('/^[6-9]\d{9}$/', $data['mobileNumber'])) {
            $error = 'Invalid mobile number.';
        }

        elseif (!preg_match('/^\d{12}$/', $data['aadhaarNumber'])) {
            $error = 'Invalid Aadhaar number.';
        }

        elseif (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $data['panNumber'])) {
            $error = 'Invalid PAN number.';
        }

        elseif (!preg_match('/^\d{6}$/', $data['pinCode'])) {
            $error = 'Invalid PIN code.';
        }

        /*
        |--------------------------------------------------------------------------
        | Save Profile
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $saved = $this->candidateModel->updateCandidateProfile(
                $candidateId,
                $data
            );

            if ($saved) {

                if (function_exists('sendCandidateProfileReceivedEmail')) {

                    sendCandidateProfileReceivedEmail(
                        $_SESSION['candidateEmail'],
                        $_SESSION['candidateName']
                    );
                }

                redirectTo('candidate-waiting');
            }

            $error = 'Unable to submit profile. Please try again.';
        }
    }

    include __DIR__ . '/../views/candidate-profile.php';
}
    public function logout()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    
        redirectTo('candidate-login');
        exit;
    }
}

?>
