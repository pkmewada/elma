<?php
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/auth-functions.php';
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../../includes/sendOtp.php';
require_once __DIR__ . '/../../includes/Csrf.php';

class AuthController
{
    private $userModel;
    private const OTP_VALID_MINUTES = 5;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $error = '';
        $emailValue = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emailValue = getPost('email');
            $password = getPost('password');

            if ($emailValue === '' || $password === '') {
                $error = 'Please enter both email and password.';
            } else {
                $user = $this->userModel->getUserByEmail($emailValue);

                if ($user && password_verify($password, $user['password'])) {
                    if ($user['otp']) {
                        redirectTo('verifyotp?email=' . urlencode($emailValue));
                    } else {
                        session_regenerate_id(true);
                        regenerateCsrfToken();

                        unset(
                            $_SESSION['candidateId'],
                            $_SESSION['candidateName'],
                            $_SESSION['candidateEmail'],
                            $_SESSION['candidateProfileStatus'],
                            $_SESSION['candidateForceReset']
                        );

                        $_SESSION['userId'] = $user['id'];
                        $_SESSION['companyId'] = $user['id'];
                        $_SESSION['authUserType'] = 'admin';
                        redirectTo('dashboard');
                    }
                } else {
                    $error = 'Invalid email or password.';
                }
            }
        }

        // Include view
        include __DIR__ . '/../views/login.php';
    }

    public function signup()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Every users row is a full admin, so only a logged-in admin may create one.
        if (empty($_SESSION['userId'])) {
            redirectTo('login');
        }

        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fullName = getPost('name');
            $email = getPost('email');
            $password = getPost('password');
            $confirmPassword = getPost('confirmPassword');

            if ($fullName === '' || $email === '' || $password === '' || $confirmPassword === '') {
                $error = 'Please complete all required fields.';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $otp = generateOtp();

                if ($this->userModel->createUser($fullName, $email, $passwordHash, $otp)) {
                    $otpExpiresAt = date('Y-m-d H:i:s', time() + (self::OTP_VALID_MINUTES * 60));
                    $this->userModel->updateOtp($email, $otp, $otpExpiresAt);
                    $isSent = sendOtpEmail($email, $otp);

                    if ($this->shouldReturnJson()) {
                        if ($isSent) {
                            $this->sendJsonResponse(true, 'OTP sent successfully', ['email' => $email]);
                        }

                        $this->sendJsonResponse(false, 'Unable to send OTP. Try again later.', isDevMode() ? ['email' => $email, 'otp' => $otp] : ['email' => $email]);
                    }

                    redirectTo('verifyotp?email=' . urlencode($email) . '&email_sent=' . ($isSent ? '1' : '0'));
                } else {
                    $error = 'Unable to register. Please try again.';

                    if ($this->shouldReturnJson()) {
                        $this->sendJsonResponse(false, $error);
                    }
                }
            }
        }

        // Include view
        include __DIR__ . '/../views/signup.php';
    }
    
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
    
            } elseif (!filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
    
                $error =
                    'Please enter a valid email address.';
    
            } else {
    
                /*
                |--------------------------------------------------------------------------
                | Check User Exists
                |--------------------------------------------------------------------------
                */
    
                $user =
                    $this->userModel
                        ->getUserForPasswordReset(
                            $emailValue
                        );
    
                if (!$user) {
    
                    $error =
                        'No account found with this email address.';
    
                } else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Generate OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $otp =
                        generateOtp();
    
                    $otpExpiresAt =
                        date(
    
                            'Y-m-d H:i:s',
    
                            time() + (
                                self::OTP_VALID_MINUTES * 60
                            )
    
                        );
    
                    /*
                    |--------------------------------------------------------------------------
                    | Save OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $otpUpdated =
                        $this->userModel
                            ->updateOtp(
    
                                $emailValue,
    
                                $otp,
    
                                $otpExpiresAt
    
                            );
    
                    if (!$otpUpdated) {
    
                        $error =
                            'Unable to generate verification code. Please try again.';
    
                    } else {
    
                        /*
                        |--------------------------------------------------------------------------
                        | Send Reset OTP Mail
                        |--------------------------------------------------------------------------
                        */
    
                        $mailSent =
                            sendPasswordResetOtpEmail(
    
                                $emailValue,
    
                                $user['fullName'],
    
                                $otp
    
                            );
    
                        /*
                        |--------------------------------------------------------------------------
                        | Save Reset Email Session
                        |--------------------------------------------------------------------------
                        */
    
                        $_SESSION['resetEmail'] =
                            $emailValue;
    
                        /*
                        |--------------------------------------------------------------------------
                        | Redirect OTP Verify Page
                        |--------------------------------------------------------------------------
                        */
    
                        redirectTo(
    
                            'verifyresetotp?email=' .
    
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
    
        include __DIR__ . '/../views/forgotpassword.php';
    }
    

    public function verifyOtp()
    {
        $error = '';
        $successMessage = '';
        $emailValue = getQuery('email');

        if ($emailValue === '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            redirectTo('signup');
        }

        if (!isDevMode() && $_SERVER['REQUEST_METHOD'] === 'GET' && getQuery('email_sent') === '0') {
            $error = 'Failed to send OTP email. Please try resending or contact support.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emailValue = getPost('email', $emailValue);
            $action = getPost('action', 'verify');

            if ($action === 'resend') {
                if ($emailValue === '') {
                    $error = 'Unable to resend OTP because the email address is missing.';
                } else {
                    $otp = generateOtp();
                    $otpExpiresAt = date('Y-m-d H:i:s', time() + (self::OTP_VALID_MINUTES * 60));

                    if ($this->userModel->updateOtp($emailValue, $otp, $otpExpiresAt)) {
                        $isSent = sendOtpEmail($emailValue, $otp);
                        $successMessage = $isSent
                            ? 'A new 4-digit OTP has been sent to your email address.'
                            : (isDevMode()
                                ? 'OTP regenerated, but email delivery failed. Use this debug OTP: ' . $otp
                                : 'OTP regenerated, but email delivery failed. Please try again later.');
                    } else {
                        $error = 'Unable to resend OTP right now. Please try again.';
                    }
                }
            } else {
                $otpCode = getPost('otp1') . getPost('otp2') . getPost('otp3') . getPost('otp4');

                if ($emailValue === '' || $otpCode === '') {
                    $error = 'Please enter the verification code and make sure the email is included in the URL.';
                } elseif (!preg_match('/^\d{4}$/', $otpCode)) {
                    $error = 'Invalid OTP format. Please enter a 4-digit OTP.';
                } else {
                    if (isDevMode()) {
                        // DEV MODE OTP BYPASS: ignore actual OTP matching and allow verification
                        // when any 4-digit OTP is submitted.
                        if ($this->userModel->markUserVerified($emailValue)) {
                            redirectTo('login');
                        } else {
                            $error = 'Unable to verify this account right now. Please try again.';
                        }
                    } elseif ($this->userModel->verifyOtp($emailValue, $otpCode)) {
                        redirectTo('login');
                    } else {
                        $error = 'Invalid verification code. Please try again.';
                    }
                }
            }
        }

        // Include view
        include __DIR__ . '/../views/verifyotp.php';
    }
    
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
                $_SESSION['resetEmail'] ?? ''
            );
    
        /*
        |--------------------------------------------------------------------------
        | Redirect If Email Missing
        |--------------------------------------------------------------------------
        */
    
        if ($emailValue === '') {
    
            redirectTo(
                'forgotpassword'
            );
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Email Delivery Failed
        |--------------------------------------------------------------------------
        */
    
        if (
    
            !isDevMode() &&
    
            $_SERVER['REQUEST_METHOD'] === 'GET' &&
    
            getQuery('email_sent') === '0'
    
        ) {
    
            $error =
                'Failed to send verification code. Please resend OTP.';
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Handle Form Submit
        |--------------------------------------------------------------------------
        */
    
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
            $emailValue =
                getPost(
                    'email',
                    $emailValue
                );
    
            $action =
                getPost(
                    'action',
                    'verify'
                );
    
            /*
            |--------------------------------------------------------------------------
            | Resend OTP
            |--------------------------------------------------------------------------
            */
    
            if ($action === 'resend') {
    
                $user =
                    $this->userModel
                        ->getUserForPasswordReset(
                            $emailValue
                        );
    
                if (!$user) {
    
                    $error =
                        'User account not found.';
    
                } else {
    
                    $otp =
                        generateOtp();
    
                    $otpExpiresAt =
                        date(
    
                            'Y-m-d H:i:s',
    
                            time() + (
                                self::OTP_VALID_MINUTES * 60
                            )
    
                        );
    
                    $updated =
                        $this->userModel
                            ->updateOtp(
    
                                $emailValue,
    
                                $otp,
    
                                $otpExpiresAt
    
                            );
    
                    if (!$updated) {
    
                        $error =
                            'Unable to resend verification code.';
    
                    } else {
    
                        $mailSent =
                            sendPasswordResetOtpEmail(
    
                                $emailValue,
    
                                $user['fullName'],
    
                                $otp
    
                            );
    
                        $successMessage =
    
                            $mailSent
    
                                ? 'A new verification code has been sent to your email address.'
    
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
    
                    $emailValue === '' ||
    
                    $otpCode === ''
    
                ) {
    
                    $error =
                        'Please enter verification code.';
    
                }
    
                elseif (
    
                    !preg_match(
                        '/^\d{4}$/',
                        $otpCode
                    )
    
                ) {
    
                    $error =
                        'Please enter valid 4 digit OTP.';
    
                }
    
                else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Verify Reset OTP
                    |--------------------------------------------------------------------------
                    */
    
                    $isValid =
                        $this->userModel
                            ->verifyResetOtp(
    
                                $emailValue,
    
                                $otpCode
    
                            );
    
                    if (!$isValid) {
    
                        $error =
                            'Invalid or expired verification code.';
    
                    } else {
    
                        /*
                        |--------------------------------------------------------------------------
                        | Create Reset Verified Session
                        |--------------------------------------------------------------------------
                        */
    
                        $_SESSION['passwordResetVerified'] =
                            true;
    
                        $_SESSION['resetEmail'] =
                            $emailValue;
    
                        /*
                        |--------------------------------------------------------------------------
                        | Redirect Reset Password
                        |--------------------------------------------------------------------------
                        */
    
                        redirectTo(
                            'resetpassword'
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
    
        include __DIR__ . '/../views/verifyresetotp.php';
    }
    
    public function resetPassword()
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
    
            empty($_SESSION['passwordResetVerified']) ||
    
            empty($_SESSION['resetEmail'])
    
        ) {
    
            redirectTo(
                'forgotpassword'
            );
    
        }
    
        $error = '';
    
        $successMessage = '';
    
        $emailValue =
            $_SESSION['resetEmail'];
    
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
            | Validate Password
            |--------------------------------------------------------------------------
            */
    
            if (
    
                $password === '' ||
    
                $confirmPassword === ''
    
            ) {
    
                $error =
                    'Please fill all required fields.';
    
            }
    
            elseif (strlen($password) < 6) {
    
                $error =
                    'Password must be at least 6 characters long.';
    
            }
    
            elseif ($password !== $confirmPassword) {
    
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
                    $this->userModel
                        ->updatePassword(
    
                            $emailValue,
    
                            $passwordHash
    
                        );
    
                if (!$updated) {
    
                    $error =
                        'Unable to reset password. Please try again.';
    
                } else {
    
                    /*
                    |--------------------------------------------------------------------------
                    | Clear Sessions
                    |--------------------------------------------------------------------------
                    */
    
                    unset(
                        $_SESSION['passwordResetVerified']
                    );
    
                    unset(
                        $_SESSION['resetEmail']
                    );
    
                    /*
                    |--------------------------------------------------------------------------
                    | Success Session
                    |--------------------------------------------------------------------------
                    */
    
                    $_SESSION['successMessage'] =
    
                        'Password reset successfully. Please login.';
    
                    /*
                    |--------------------------------------------------------------------------
                    | Redirect Login
                    |--------------------------------------------------------------------------
                    */
    
                    redirectTo(
                        'login'
                    );
    
                }
    
            }
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Load View
        |--------------------------------------------------------------------------
        */
    
        include __DIR__ . '/../views/resetpassword.php';
    }
    
    
    
    

    private function shouldReturnJson(): bool
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        $format = getPost('format', getQuery('format'));

        return stripos($acceptHeader, 'application/json') !== false
            || strtolower($requestedWith) === 'xmlhttprequest'
            || strtolower((string) $format) === 'json';
    }

    private function sendJsonResponse(bool $success, string $message, array $extra = []): void
    {
        header('Content-Type: application/json');
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message,
        ], $extra));
        exit();
    }
}
