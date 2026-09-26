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
    <title> Modlus - Admin Setup </title>
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

<body class="authentication-background">



    <div class="container">
        <div class="row justify-content-center align-items-center authentication authentication-basic h-100">
            <div class="col-xxl-4 col-xl-5 col-lg-5 col-md-6 col-sm-8 col-12">
                <div class="my-5 d-flex justify-content-center"> 
                    <a href="index.html"> 
                        <img src="<?= $base ?>assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark"> 
                    </a> 
                </div>
                <div class="card custom-card my-4">
                    <div class="card-body p-5">
                        <p class="h4 mb-2 fw-semibold">Sign In</p>
                        <p class="mb-4 text-muted fw-normal">Welcome back to Modlus!</p>
                        <?php if ($error !== ''): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?= BASE_URL ?>/login">
                            <div class="row gy-3">
                                <div class="col-xl-12">
                                    <label for="signin-username" class="form-label text-default">Email Address</label>
                                    <input type="email" class="form-control" id="signin-username" name="email" placeholder="Enter Email ID" value="<?php echo htmlspecialchars($emailValue, ENT_QUOTES, 'UTF-8'); ?>" required>
                                </div>
                                <div class="col-xl-12 mb-2">
                                    <label for="signin-password" class="form-label text-default d-block">Password
                                        <a href="<?= BASE_URL ?>/forgotpassword" class="float-end  link-danger op-5 fw-medium fs-12">Forget password ?</a>
                                    </label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control" id="signin-password" name="password" placeholder="Password" required>
                                        <a href="javascript:void(0);" class="show-password-button text-muted" onclick="createpassword('signin-password',this)" id="button-addon2"><i class="ri-eye-off-line align-middle"></i></a>
                                    </div>
                                    <div class="mt-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" value="" id="defaultCheck1">
                                            <label class="form-check-label text-muted fw-normal fs-12" for="defaultCheck1">
                                                Remember password ?
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!--<div class="text-center my-3 authentication-barrier">-->
                            <!--    <span class="op-4 fs-11">OR SignIn With</span>-->
                            <!--</div>-->
                            <!--<div class="d-flex align-items-center justify-content-between gap-3 mb-3 flex-wrap">-->
                            <!--    <button type="button" class="btn btn-light btn-w-lg border d-flex align-items-center justify-content-center flex-fill">-->
                            <!--        <span class="avatar avatar-xs">-->
                            <!--            <img src="<?= $base ?>assets/images/media/apps/google.png" alt="">-->
                            <!--        </span>-->
                            <!--        <span class="lh-1 ms-2 fs-13 text-default fw-medium">Google</span>-->
                            <!--    </button>-->
                            <!--    <button type="button" class="btn btn-light btn-w-lg border d-flex align-items-center justify-content-center flex-fill">-->
                            <!--        <span class="avatar avatar-xs">-->
                            <!--            <img src="<?= $base ?>assets/images/media/apps/facebook.png" alt="">-->
                            <!--        </span>-->
                            <!--        <span class="lh-1 ms-2 fs-13 text-default fw-medium">Facebook</span>-->
                            <!--    </button>-->
                            <!--</div>-->
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Sign In</button>
                            </div>
                        </form>
                        <div class="text-center">
                            <p class="text-muted mt-3 mb-0"><a href="/../candidate-login" class="btn btn-outline-info btn-wave waves-effect waves-light">Employee Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Bootstrap JS -->
    <script src="<?= $base ?>assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

    <!-- Show Password JS -->
    <script src="<?= $base ?>assets/js/show-password.js"></script>

</body>

</html>

