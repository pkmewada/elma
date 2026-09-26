<?php
require_once __DIR__ . '/../../includes/config.php';

$base =
    ASSET_URL . '/';

$error =
    $error ?? '';

$successMessage =
    $successMessage ?? '';

$emailValue =
    $emailValue ?? '';

?>

<!DOCTYPE html>

<html lang="en" dir="ltr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        Verify Reset OTP - Modlus
    </title>

    <link
        rel="icon"
        href="<?= $base ?>assets/images/brand-logos/favicon.ico">

    <script>

        window.AUTH_ASSET_BASE =
            "<?= $base ?>assets/";

    </script>

    <script src="<?= $base ?>assets/js/authentication-main.js"></script>

    <!-- Bootstrap CSS -->
    <link
        href="<?= $base ?>assets/libs/bootstrap/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- App CSS -->
    <link
        href="<?= $base ?>assets/css/styles.css"
        rel="stylesheet">

    <!-- Icons CSS -->
    <link
        href="<?= $base ?>assets/css/icons.css"
        rel="stylesheet">

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

<body class="authentication-background">

    <div class="container">

        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">

            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">

                <!-- Logo -->
                <div class="my-5 d-flex justify-content-center">

                    <a href="candidate-login">

                        <img
                            src="<?= $base ?>assets/images/brand-logos/desktop-dark.png"
                            class="desktop-dark"
                            alt="logo">

                    </a>

                </div>

                <!-- Card -->
                <div class="card custom-card my-4">

                    <div class="card-body p-5">

                        <!-- Heading -->
                        <p class="h4 mb-2 fw-semibold">

                            Verify Reset Code

                        </p>

                        <p class="mb-2 text-muted fw-normal">

                            Enter the 4-digit verification code sent to your registered email address.

                        </p>

                        <?php if ($emailValue !== '') : ?>

                            <div class="mb-4 text-primary fw-medium">

                                <?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Error -->
                        <?php if ($error !== '') : ?>

                            <div class="alert alert-danger">

                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Success -->
                        <?php if ($successMessage !== '') : ?>

                            <div class="alert alert-success">

                                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Verify OTP Form -->
                        <form
                            method="POST"
                            action=""
                            id="candidateVerifyOtpForm">

                            <!-- Hidden Fields -->
                            <input
                                type="hidden"
                                name="email"
                                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>">

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

                            <!-- Submit -->
                            <div class="d-grid mt-4">

                                <button
                                    type="submit"
                                    class="btn btn-primary">

                                    Verify Code

                                </button>

                            </div>

                        </form>

                        <!-- Resend OTP -->
                        <form
                            method="POST"
                            action=""
                            class="text-center mt-4">

                            <input
                                type="hidden"
                                name="email"
                                value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>">

                            <input
                                type="hidden"
                                name="action"
                                value="resend">

                            <p class="text-muted mb-2 fs-13">

                                Didn't receive the verification code?

                            </p>

                            <button
                                type="submit"
                                class="btn btn-link p-0 text-primary fw-semibold text-decoration-none">

                                Resend Verification Code

                            </button>

                        </form>

                        <!-- Back To Login -->
                        <div class="text-center mt-4">

                            <a
                                href="candidate-login"
                                class="text-muted fs-13">

                                Back to Login

                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="<?= $base ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

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