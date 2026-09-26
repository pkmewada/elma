<?php

$error =
    $error ?? '';

$successMessage =
    $successMessage ?? '';

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

                                Reset Password

                            </h4>

                            <p class="text-muted fs-14">

                                Create a new secure password for your account.

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

                        <!-- Reset Password Form -->
                        <form
                            method="POST"
                            action=""
                            id="resetPasswordForm">

                            <!-- New Password -->
                            <div class="mb-4">

                                <label class="form-label">

                                    New Password

                                </label>

                                <div class="input-group">

                                    <input
                                        type="password"
                                        name="password"
                                        id="password"
                                        class="form-control form-control-lg"
                                        placeholder="Enter new password"
                                        required>

                                    <button
                                        type="button"
                                        class="btn btn-light border"
                                        id="togglePassword">

                                        <i class="ri-eye-line"></i>

                                    </button>

                                </div>

                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-4">

                                <label class="form-label">

                                    Confirm Password

                                </label>

                                <div class="input-group">

                                    <input
                                        type="password"
                                        name="confirmPassword"
                                        id="confirmPassword"
                                        class="form-control form-control-lg"
                                        placeholder="Confirm new password"
                                        required>

                                    <button
                                        type="button"
                                        class="btn btn-light border"
                                        id="toggleConfirmPassword">

                                        <i class="ri-eye-line"></i>

                                    </button>

                                </div>

                            </div>

                            <!-- Password Hint -->
                            <div class="mb-4">

                                <div class="alert alert-light border mb-0">

                                    <small class="text-muted">

                                        Password must contain at least 6 characters.

                                    </small>

                                </div>

                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid mb-3">

                                <button
                                    type="submit"
                                    class="btn btn-primary btn-lg">

                                    Reset Password

                                </button>

                            </div>

                            <!-- Back To Login -->
                            <div class="text-center">

                                <a
                                    href="login"
                                    class="text-muted fs-14">

                                    Back to Login

                                </a>

                            </div>

                        </form>

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
        | Toggle Password Visibility
        |--------------------------------------------------------------------------
        */

        function togglePasswordVisibility(
            inputId,
            buttonId
        ) {

            const input =
                document.getElementById(inputId);

            const button =
                document.getElementById(buttonId);

            const icon =
                button.querySelector('i');

            button.addEventListener(
                'click',
                function () {

                    const isPassword =
                        input.type === 'password';

                    input.type =
                        isPassword
                            ? 'text'
                            : 'password';

                    icon.className =
                        isPassword
                            ? 'ri-eye-off-line'
                            : 'ri-eye-line';

                }
            );

        }

        /*
        |--------------------------------------------------------------------------
        | Initialize Password Toggles
        |--------------------------------------------------------------------------
        */

        togglePasswordVisibility(
            'password',
            'togglePassword'
        );

        togglePasswordVisibility(
            'confirmPassword',
            'toggleConfirmPassword'
        );

    </script>

</body>

</html>