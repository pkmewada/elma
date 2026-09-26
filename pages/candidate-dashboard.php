<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/basic-config.php';

if (empty($_SESSION['candidateId'])) {
    redirectTo('candidate-login');
    exit;
}

if (!empty($_SESSION['candidateForceReset'])) {
    redirectTo('candidate-reset-password');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Dashboard (Test)</title>
</head>

<body style="font-family:Arial,sans-serif;padding:40px;background:#f7f8fb;color:#111827;">

    <div style="max-width:760px;margin:0 auto;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:28px;">

        <h2 style="margin:0 0 12px;">
            Welcome to Candidate Dashboard
        </h2>

        <p style="margin:0 0 10px;">
            This is a temporary test dashboard page.
        </p>

        <p style="margin:0 0 18px;">

            Logged in as:

            <strong>
                <?php
                echo htmlspecialchars(
                    (string) ($_SESSION['candidateName'] ?? 'Candidate'),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </strong>

            (
            <?php
            echo htmlspecialchars(
                (string) ($_SESSION['candidateEmail'] ?? ''),
                ENT_QUOTES,
                'UTF-8'
            );
            ?>
            )

        </p>

        <a href="<?= BASE_URL ?>/candidate-waiting"
           style="display:inline-block;padding:10px 14px;background:#111827;color:#fff;text-decoration:none;border-radius:6px;margin-right:8px;">

            Go to Waiting

        </a>

        <a href="<?= BASE_URL ?>/candidate-logout"
           style="display:inline-block;padding:10px 14px;background:#dc2626;color:#fff;text-decoration:none;border-radius:6px;">

            Logout

        </a>

    </div>

</body>
</html>
