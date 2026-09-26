<?php

$error =
    $error ?? '';

$successMessage =
    $successMessage ?? '';

$emailValue =
    $emailValue ?? '';

?>

<!DOCTYPE html>

<html lang="en">

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
    <title> Forgot Password - Modlus </title>
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

                                Forgot Password

                            </h4>

                            <p class="text-muted fs-14">

                                Enter your registered email address to receive a verification code.

                            </p>

                        </div>

                        <!-- Error Message -->
                        <?php if (!empty($error)) : ?>

                            <div class="alert alert-danger">

                                <?= htmlspecialchars($error); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Success Message -->
                        <?php if (!empty($successMessage)) : ?>

                            <div class="alert alert-success">

                                <?= htmlspecialchars($successMessage); ?>

                            </div>

                        <?php endif; ?>

                        <!-- Forgot Password Form -->
                        <form
                            method="POST"
                            action="">

                            <!-- Email -->
                            <div class="mb-4">

                                <label
                                    class="form-label">

                                    Email Address

                                </label>

                                <input
                                    type="email"
                                    name="email"
                                    class="form-control form-control-lg"
                                    placeholder="Enter your email address"
                                    value="<?= htmlspecialchars($emailValue); ?>"
                                    required>

                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid mb-3">

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-lg">

                                    Send Verification Code

                                </button>

                            </div>

                            <!-- Back To Login -->
                            <div class="text-center">

                                <p class="mb-0 fs-14">

                                    Remember your password?

                                    <a
                                        href="login"
                                        class="text-primary fw-semibold">

                                        Back to Login

                                    </a>

                                </p>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="<?= ASSET_URL ?>/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

</body>

</html>