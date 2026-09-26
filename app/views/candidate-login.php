<?php
require_once __DIR__ . '/../../includes/config.php';

// Use ASSET_URL instead of hardcoded path
$base = ASSET_URL . '/';
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Login - Modlus</title>

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

                <div class="my-5 d-flex justify-content-center">
                    <a href="candidate-login">
                        <img src="<?= $base ?>assets/images/brand-logos/desktop-dark.png" class="desktop-dark"
                            alt="logo">
                    </a>
                </div>

                <div class="card custom-card my-4">

                    <div class="card-body p-5">

                        <p class="h4 mb-2 fw-semibold">Employee Sign In</p>

                        <p class="mb-4 text-muted fw-normal">
                            Access your onboarding portal
                        </p>

                        <?php if ($error !== ''): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <?php endif; ?>

                        <form method="post" action="">

                            <div class="row gy-3">

                                <div class="col-xl-12">
                                    <label class="form-label text-default">
                                        Email Address
                                    </label>

                                    <input type="email" class="form-control" name="email"
                                        placeholder="Enter Email Address"
                                        value="<?= htmlspecialchars($emailValue ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                        required>
                                </div>

                                <div class="col-xl-12">

                                    <label class="form-label text-default d-block">
                                        <a href="<?= BASE_URL ?>/candidate-forgot-password" class="float-end  link-danger op-5 fw-medium fs-12">Forget password ?</a>
                                        Password
                                    </label>

                                    <div class="position-relative">

                                        <input type="password" class="form-control" id="candidate-password"
                                            name="password" placeholder="Enter Password" required>

                                        <a href="javascript:void(0);" class="show-password-button text-muted"
                                            onclick="createpassword('candidate-password',this)">
                                            <i class="ri-eye-off-line align-middle"></i>
                                        </a>

                                    </div>
                                    
                                </div>

                            </div>

                            <!--<div class="alert alert-light mt-3 mb-0 fs-12">-->
                            <!--    Use the temporary password shared in your welcome email.-->
                            <!--    You will be asked to reset password after first login.-->
                            <!--</div>-->

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">
                                    Sign In
                                </button>
                            </div>

                        </form>

                        <div class="text-center mt-4">
                            <p class="text-muted mb-0 fs-12">
                                Need help? Contact HR Team
                            </p>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="<?= $base ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $base ?>assets/js/show-password.js"></script>

</body>

</html>
