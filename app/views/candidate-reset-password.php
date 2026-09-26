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
    <title>Reset Password - Candidate</title>
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
            <div class="col-xxl-5 col-xl-6 col-lg-6 col-md-8 col-sm-10 col-12">
                <div class="my-5 d-flex justify-content-center">
                    <a href="candidate-reset-password">
                        <img src="<?= $base ?>assets/images/brand-logos/desktop-dark.png" class="desktop-dark" alt="logo">
                    </a>
                </div>
                <div class="card custom-card my-4">
                    <div class="card-body p-5">
                        <p class="h4 mb-2 fw-semibold">Reset Password</p>
                        <p class="mb-4 text-muted fw-normal">
                            Reset is mandatory before proceeding.
                        </p>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="d-grid mt-3">
                                <a href="candidate-waiting" class="btn btn-primary">Continue</a>
                            </div>
                        <?php else: ?>
                            <form method="post" action="candidate-reset-password">
                                <div class="row gy-3">
                                    <div class="col-12">
                                        <label class="form-label text-default">Current (Temporary) Password</label>
                                        <input type="password" class="form-control" name="currentPassword" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-default">New Password</label>
                                        <input type="password" class="form-control" name="newPassword" minlength="8" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label text-default">Confirm New Password</label>
                                        <input type="password" class="form-control" name="confirmPassword" minlength="8" required>
                                    </div>
                                </div>
                                <div class="alert alert-light mt-3 mb-0 fs-12">
                                    You cannot access onboarding process until this password reset is completed.
                                </div>
                                <div class="d-grid mt-4">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= $base ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
