<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$error = '';
$fullName = '';
$email = '';
$phone = '';
$source = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $source = trim($_POST['source'] ?? '');
    $status = 'new';

    if ($fullName === '' || $email === '' || $phone === '' || $source === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = mysqli_prepare($con, 'INSERT INTO leads (fullName, email, phone, source, status) VALUES (?, ?, ?, ?, ?)');

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'sssss', $fullName, $email, $phone, $source, $status);

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                redirectTo('leads');
            }

            $error = 'Unable to save lead. Please try again.';
            mysqli_stmt_close($stmt);
        } else {
            $error = 'Unable to prepare lead insert. Please try again.';
        }
    }
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

        <div class="main-content app-content">
            <div class="container-fluid">

                <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h1 class="page-title fw-medium fs-18 mb-2">Add Lead</h1>
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="dashboard">Dashboard</a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Add Lead</li>
                        </ol>
                    </div>
                </div>

                <div class="row justify-content-center">
                    <div class="col-xxl-12 col-xl-12 col-lg-10">
                        <div class="card custom-card">
                            <div class="card-header justify-content-between">
                                <div class="card-title">
                                    Lead Details
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if ($error !== ''): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                                    </div>
                                <?php endif; ?>
                                <form method="POST" action="addlead">
                                    <div class="row gy-3">
                                        <div class="col-md-6">
                                            <label for="lead-full-name" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="lead-full-name" name="fullName" placeholder="Enter full name" value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lead-email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="lead-email" name="email" placeholder="Enter email address" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lead-phone" class="form-label">Phone</label>
                                            <input type="text" class="form-control" id="lead-phone" name="phone" placeholder="Enter phone number" value="<?php echo htmlspecialchars($phone, ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lead-source" class="form-label">Source</label>
                                            <input type="text" class="form-control" id="lead-source" name="source" placeholder="Instagram, Website, Referral..." value="<?php echo htmlspecialchars($source, ENT_QUOTES, 'UTF-8'); ?>" required>
                                        </div>
                                        <div class="col-12">
                                            <div class="d-flex justify-content-end gap-2 mt-2">
                                                <a href="dashboard" class="btn btn-light">Cancel</a>
                                                <button type="submit" class="btn btn-primary">Save Lead</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
