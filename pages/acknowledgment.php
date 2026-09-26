<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
$token = trim((string) ($_GET['token'] ?? ''));
$candidate = null;
$state = 'invalid';
$message = 'Invalid or expired acknowledgment link.';

if ($token !== '') {
    $stmt = mysqli_prepare($con, "
        SELECT id, fullName, appliedRole, finalSalary, joiningDate, acknowledgmentStatus
        FROM candidateRecord
        WHERE acknowledgmentToken = ?
        LIMIT 1
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 's', $token);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $candidate = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);
    }

    if ($candidate && in_array((string) $candidate['acknowledgmentStatus'], ['completed', 'acknowledged'], true)) {
        $state = 'completed';
        $message = 'This acknowledgment has already been submitted. Please contact HR if you need help.';
    } elseif ($candidate) {
        $state = 'pending';
        $message = '';
    }
}

$safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Acknowledgment</title>
    <style>
    body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, sans-serif;
        background: #f4f6f8;
        color: #1f2937;
    }

    .container {
        max-width: 760px;
        margin: 56px auto;
        background: #fff;
        padding: 32px 36px;
        border-radius: 8px;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
    }

    .badge {
        display: inline-block;
        background: #eef2ff;
        color: #4f46e5;
        padding: 5px 10px;
        border-radius: 6px;
        font-size: 12px;
        margin-bottom: 12px;
        font-weight: 600;
    }

    h1 {
        margin: 0 0 10px;
        font-size: 26px;
        font-weight: 650;
        color: #111827;
    }

    .subtitle {
        font-size: 14px;
        color: #5f6b7a;
        margin-bottom: 22px;
        line-height: 1.6;
    }

    .summary {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
        margin-bottom: 22px;
    }

    .summary-item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        background: #f9fafb;
    }

    .summary-label {
        font-size: 12px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .summary-value {
        font-size: 14px;
        font-weight: 600;
        color: #111827;
    }

    .terms-box {
        max-height: 220px;
        overflow-y: auto;
        background: #fafafa;
        border: 1px solid #e5e7eb;
        padding: 16px;
        border-radius: 8px;
        font-size: 14px;
        color: #444;
        line-height: 1.6;
    }

    .terms-link {
        margin-top: 15px;
        padding: 12px;
        background: #f9fafb;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        font-size: 13px;
        color: #555;
    }

    .terms-link a {
        color: #4f46e5;
        font-weight: 600;
        text-decoration: none;
    }

    .checkbox-area {
        margin-top: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .checkbox-area input {
        width: 16px;
        height: 16px;
    }

    .btn {
        margin-top: 25px;
        width: 100%;
        padding: 12px;
        border: 0;
        border-radius: 8px;
        font-size: 15px;
        background: #111827;
        color: #fff;
        cursor: pointer;
        transition: 0.2s;
    }

    .btn:disabled {
        background: #b6bcc7;
        cursor: not-allowed;
    }

    .notice {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 16px;
        background: #f9fafb;
        line-height: 1.6;
    }

    @media (max-width: 640px) {
        .container {
            margin: 18px;
            padding: 24px;
        }

        .summary {
            grid-template-columns: 1fr;
        }
    }
    </style>
</head>

<body>
    <main class="container">
        <div class="badge">Candidate Acknowledgment</div>
        <h1>Terms & Conditions</h1>

        <?php if ($state !== 'pending'): ?>
        <div class="notice">
            <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php else: ?>
        <div class="subtitle">
            Please review the offer summary and accept the terms before completing your onboarding acknowledgment.
        </div>

        <div class="summary">
            <div class="summary-item">
                <div class="summary-label">Candidate</div>
                <div class="summary-value"><?php echo htmlspecialchars((string) $candidate['fullName'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Role</div>
                <div class="summary-value"><?php echo htmlspecialchars((string) $candidate['appliedRole'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Salary</div>
                <div class="summary-value"><?php echo htmlspecialchars((string) $candidate['finalSalary'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Joining Date</div>
                <div class="summary-value">
                    <?php echo !empty($candidate['joiningDate']) ? htmlspecialchars(date('d M Y', strtotime((string) $candidate['joiningDate'])), ENT_QUOTES, 'UTF-8') : 'To be confirmed'; ?>
                </div>
            </div>
        </div>

        <div class="terms-box">
            <p>By proceeding, you confirm that all information provided by you is accurate and complete.</p>
            <p>You agree to comply with company policies, the code of conduct, and employment terms.</p>
            <p>Any misrepresentation of information may lead to withdrawal of the offer or termination of the hiring process.</p>
            <p>Your employment is subject to document verification and background checks.</p>
            <p>The company may revise policies as required by business, legal, or compliance needs.</p>
        </div>

        <div class="terms-link">
            Review the full policy document:
            <a href="<?= BASE_URL ?>/terms-and-conditions" target="_blank" rel="noopener">
                View Full Terms & Conditions
            </a>
        </div>

        <div class="checkbox-area">
            <input type="checkbox" id="acceptCheckbox">
            <label for="acceptCheckbox">I accept all Terms & Conditions</label>
        </div>

        <button id="proceedBtn" class="btn" disabled>Proceed to Complete Details</button>
        <?php endif; ?>
    </main>

    <?php if ($state === 'pending'): ?>
    <script>
    const checkbox = document.getElementById('acceptCheckbox');
    const button = document.getElementById('proceedBtn');

    checkbox.addEventListener('change', function() {
        button.disabled = !this.checked;
    });

       button.addEventListener('click', function() {
        window.location.href = '<?= BASE_URL ?>/acknowledgment-form?token=<?= urlencode($safeToken) ?>';
    });
    </script>
    <?php endif; ?>
</body>

</html>
