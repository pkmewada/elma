<?php
require_once __DIR__ . '/../../includes/config.php';

// Use ASSET_URL instead of hardcoded path
$base = ASSET_URL . '/';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Forgot Password - Modlus</title>

    <link rel="icon" href="<?= $base ?>assets/images/brand-logos/favicon.ico">

    <script>
    window.AUTH_ASSET_BASE = "<?= $base ?>assets/";
    </script>
    <script src="<?= $base ?>assets/js/authentication-main.js"></script>

    <link href="<?= $base ?>assets/libs/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= $base ?>assets/css/styles.css" rel="stylesheet">
    <link href="<?= $base ?>assets/css/icons.css" rel="stylesheet">

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

                            Forgot Password

                        </p>

                        <p class="mb-4 text-muted fw-normal">

                            Enter your registered email address to receive a verification code.

                        </p>

                        <!-- Error Message -->
                        <?php if ($error !== '') : ?>

                            <div class="alert alert-danger">

                                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Success Message -->
                        <?php if ($successMessage !== '') : ?>

                            <div class="alert alert-success">

                                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Forgot Password Form -->
                        <form
                            method="POST"
                            action="">

                            <div class="row gy-3">

                                <!-- Email -->
                                <div class="col-xl-12">

                                    <label class="form-label text-default">

                                        Email Address

                                    </label>

                                    <input
                                        type="email"
                                        class="form-control"
                                        name="email"
                                        placeholder="Enter Email Address"
                                        value="<?= htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>"
                                        required>

                                </div>

                            </div>

                            <!-- Submit -->
                            <div class="d-grid mt-4">

                                <button
                                    type="submit"
                                    class="btn btn-primary">

                                    Send Verification Code

                                </button>

                            </div>

                        </form>

                        <!-- Back To Login -->
                        <div class="text-center mt-4">

                            <p class="text-muted mb-0 fs-13">

                                Remember your password?

                                <a
                                    href="candidate-login"
                                    class="text-primary fw-semibold">

                                    Back to Login

                                </a>

                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="<?= $base ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>