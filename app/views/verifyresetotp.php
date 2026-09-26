<?php

$error =
    $error ?? '';

$successMessage =
    $successMessage ?? '';

$emailValue =
    $emailValue ?? '';

?>

<?php

require_once __DIR__ . '/../../includes/config.php';

// Use ASSET_URL instead of hardcoded path
$base = ASSET_URL . '/';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr" data-nav-layout="vertical" data-vertical-style="overlay" data-theme-mode="light" data-header-styles="light" data-menu-styles="light" data-toggled="close">

<head>

    <!-- Meta Data -->
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <title>Reset Password | Modlus HRMS </title>
    <meta name="Description" content="Bootstrap Responsive Admin Web Dashboard HTML5 Template">
    <meta name="Author" content="Modlus Technologies Private Limited">
<meta name="keywords" content="dashboard template,dashboard html,bootstrap admin,dashboard admin,admin template,sales dashboard,crypto dashboard,projects dashboard,html template,html,html css,admin dashboard template,html css bootstrap,dashboard html css,pos system,bootstrap dashboard">
    <!-- Favicon -->
    <link rel="icon" href="<?= $base ?>assets/images/brand-logos/favicon.ico" type="image/x-icon">

    <!-- Main Theme Js -->
    <script>window.AUTH_ASSET_BASE = "<?= $base ?>assets/";</script>
    <script src="<?= $base ?>assets/js/authentication-main.js"></script>

    <!-- Bootstrap Css -->
    <link id="style" href="<?= $base ?>assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet" >

    <!-- Style Css -->
    <link href="<?= $base ?>assets/css/styles.css" rel="stylesheet" >

    <!-- Icons Css -->
    <link href="<?= $base ?>assets/css/icons.css" rel="stylesheet" >
    <style>

        .otp-input {

            width: 60px;

            height: 60px;

            text-align: center;

            font-size: 24px;

            font-weight: 600;

            border-radius: 12px;

        }
        
        .authentication .form-control {
            padding-inline-end: 0rem!important;
            }

    </style>
   
</head>

<body>

    <div class="container-fluid authentication-background">

        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">

            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">

                <div class="card custom-card my-4 border">

                    <div class="card-body p-5">

                        <!-- Logo -->
                        <div class="text-center mb-4">

                            <a href="login">

                                <img
                                    src="<?= ASSET_URL ?>/assets/images/brand-logos/desktop-logo.png"
                                    alt="logo"
                                    class="authentication-brand desktop-logo">

                            </a>

                        </div>

                        <!-- Heading -->
                        <div class="text-center mb-4">

                            <h4 class="fw-semibold mb-2">

                                Verify Reset Code

                            </h4>

                            <p class="text-muted fs-14">

                                Enter the 4-digit verification code sent to your email address.

                            </p>

                            <?php if (!empty($emailValue)) : ?>

                                <div class="fw-medium text-primary mt-2">

                                    <?= htmlspecialchars($emailValue); ?>

                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- Error -->
                        <?php if (!empty($error)) : ?>

                            <div class="alert alert-danger">

                                <?= htmlspecialchars($error); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Success -->
                        <?php if (!empty($successMessage)) : ?>

                            <div class="alert alert-success">

                                <?= htmlspecialchars($successMessage); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Verify OTP Form -->
                        <form
                            method="POST"
                            action=""
                            id="verifyOtpForm">

                            <!-- Hidden Fields -->
                            <input
                                type="hidden"
                                name="email"
                                value="<?= htmlspecialchars($emailValue); ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="verify">

                            <!-- OTP Inputs -->
                            <div class="d-flex justify-content-center gap-2 mb-4">

                                <input
                                    type="text"
                                    maxlength="1"
                                    name="otp1"
                                    class="form-control otp-input"
                                    inputmode="numeric"
                                    required>

                                <input
                                    type="text"
                                    maxlength="1"
                                    name="otp2"
                                    class="form-control otp-input"
                                    inputmode="numeric"
                                    required>

                                <input
                                    type="text"
                                    maxlength="1"
                                    name="otp3"
                                    class="form-control otp-input"
                                    inputmode="numeric"
                                    required>

                                <input
                                    type="text"
                                    maxlength="1"
                                    name="otp4"
                                    class="form-control otp-input"
                                    inputmode="numeric"
                                    required>

                            </div>

                            <!-- Verify Button -->
                            <div class="d-grid mb-3">

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-lg">

                                    Verify Code

                                </button>

                            </div>

                        </form>

                        <!-- Resend OTP -->
                        <form
                            method="POST"
                            action=""
                            class="text-center">

                            <input
                                type="hidden"
                                name="email"
                                value="<?= htmlspecialchars($emailValue); ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="resend">

                            <p class="mb-2 fs-14 text-muted">

                                Didn’t receive the code?

                            </p>

                            <button
                                type="submit"
                                class="btn btn-link text-primary fw-semibold p-0">

                                Resend Verification Code

                            </button>

                        </form>

                        <!-- Back To Login -->
                        <div class="text-center mt-4">

                            <a
                                href="login"
                                class="text-muted fs-14">

                                Back to Login

                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="<?= ASSET_URL ?>/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <script>

        /*
        |--------------------------------------------------------------------------
        | OTP Auto Focus
        |--------------------------------------------------------------------------
        */

        const otpInputs =
            document.querySelectorAll('.otp-input');

        otpInputs.forEach((input, index) => {

            input.addEventListener('input', function () {

                this.value =
                    this.value.replace(/\D/g, '');

                if (

                    this.value.length === 1 &&
                    otpInputs[index + 1]

                ) {

                    otpInputs[index + 1].focus();

                }

            });

            input.addEventListener('keydown', function (e) {

                if (

                    e.key === 'Backspace' &&
                    !this.value &&
                    otpInputs[index - 1]

                ) {

                    otpInputs[index - 1].focus();

                }

            });

        });

    </script>

</body>

</html>