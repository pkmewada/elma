<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/config.php';

function h(?string $value): string


{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function candidateColumnExists(mysqli $con, string $column): bool
{
    $stmt = mysqli_prepare($con, "
        SELECT COUNT(*) AS count
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND LOWER(TABLE_NAME) = LOWER('candidateRecord')
          AND LOWER(COLUMN_NAME) = LOWER(?)
    ");

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 's', $column);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return ((int) ($row['count'] ?? 0)) > 0;
}

function renderNotice(string $title, string $message): void
{
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>' . h($title) . '</title>';
    echo '<style>body{margin:0;font-family:"Segoe UI",Tahoma,sans-serif;background:#f4f6f8;color:#1f2937}.container{max-width:680px;margin:56px auto;background:#fff;padding:32px 36px;border-radius:8px;box-shadow:0 8px 25px rgba(0,0,0,.08)}h1{margin:0 0 12px;font-size:26px;color:#111827}.notice{border:1px solid #e5e7eb;border-radius:8px;padding:16px;background:#f9fafb;line-height:1.6}@media(max-width:640px){.container{margin:18px;padding:24px}}</style>';
    echo '</head><body><main class="container"><h1>' . h($title) . '</h1><div class="notice">' . h($message) . '</div></main></body></html>';
    exit();
}

function getAcknowledgedStatusValue(mysqli $con): string
{
    $statusType = '';
    $stmt = mysqli_prepare($con, "
        SELECT COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND LOWER(TABLE_NAME) = LOWER('candidateRecord')
          AND LOWER(COLUMN_NAME) = LOWER('acknowledgmentStatus')
        LIMIT 1
    ");

    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        $statusType = strtolower((string) ($row['COLUMN_TYPE'] ?? ''));
        mysqli_stmt_close($stmt);
    }

    return strpos($statusType, "'acknowledged'") !== false ? 'acknowledged' : 'completed';
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    renderNotice('Invalid Access', 'Invalid or expired acknowledgment link.');
}

$stmt = mysqli_prepare($con, "
    SELECT id, fullName, phoneNumber, email, appliedRole, finalSalary, joiningDate, acknowledgmentStatus
    FROM candidateRecord
    WHERE acknowledgmentToken = ?
    LIMIT 1
");

if (!$stmt) {
    renderNotice('Temporary Error', 'We could not load this acknowledgment right now. Please contact HR.');
}

mysqli_stmt_bind_param($stmt, 's', $token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$candidate = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$candidate) {
    renderNotice('Invalid Link', 'Invalid or expired acknowledgment link.');
}

if (in_array((string) $candidate['acknowledgmentStatus'], ['completed', 'acknowledged'], true)) {
    renderNotice('Already Submitted', 'This acknowledgment has already been submitted. Please contact HR if you need help.');
}

$errors = [];
$submitted = false;
$formValues = [
    'fullName' => (string) ($candidate['fullName'] ?? ''),
    'phone' => (string) ($candidate['phoneNumber'] ?? ''),
    'email' => (string) ($candidate['email'] ?? ''),
    'role' => (string) ($candidate['appliedRole'] ?? ''),
    'position' => (string) ($candidate['appliedRole'] ?? ''),
    'salary' => (string) ($candidate['finalSalary'] ?? ''),
    'joiningDate' => (string) ($candidate['joiningDate'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($formValues as $field => $_) {
        $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $formValues['govtIdType'] = trim((string) ($_POST['govtIdType'] ?? ''));
    $signatureData = trim((string) ($_POST['signatureData'] ?? ''));

    /* ----------------------------------------------------------
       Basic Validation
    ---------------------------------------------------------- */

    if ($formValues['fullName'] === '') {
        $errors[] = 'Full name is required.';
    }

    if (!preg_match('/^[0-9]{10}$/', $formValues['phone'])) {
        $errors[] = 'Please enter a valid 10-digit phone number.';
    }

    if (!filter_var($formValues['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    foreach (
        [
            'role'        => 'Role',
            'position'    => 'Position',
            'salary'      => 'Salary',
            'joiningDate' => 'Joining date'
        ] as $field => $label
    ) {
        if ($formValues[$field] === '') {
            $errors[] = $label . ' is required.';
        }
    }

    if ($formValues['govtIdType'] === '') {
        $errors[] = 'Government ID type is required.';
    }

    /* ----------------------------------------------------------
       File Validation
    ---------------------------------------------------------- */

    if (
        !isset($_FILES['govtIdFile']) ||
        $_FILES['govtIdFile']['error'] !== UPLOAD_ERR_OK
    ) {
        $errors[] = 'Government ID file is required.';
    }

    $signatureBinary = false;

    if ($signatureData === '') {
        $errors[] = 'Signature is required.';
    } elseif (!preg_match('/^data:image\/png;base64,(.+)$/', $signatureData, $signatureMatches)) {
        $errors[] = 'Invalid signature data.';
    } else {
        $signatureBinary = base64_decode(str_replace(' ', '+', $signatureMatches[1]), true);

        if ($signatureBinary === false || @getimagesizefromstring($signatureBinary) === false) {
            $errors[] = 'Invalid signature data.';
        }
    }

    $govtIdPath = '';
    $signaturePath = '';

    /* ----------------------------------------------------------
       Upload Files
    ---------------------------------------------------------- */

    if (!$errors) {

        $uploadDir = dirname(__DIR__) . '/uploads/acknowledgment/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $allowedGovt = ['jpg', 'jpeg', 'png', 'pdf'];
        $govtExt = strtolower(pathinfo($_FILES['govtIdFile']['name'], PATHINFO_EXTENSION));

        if (!in_array($govtExt, $allowedGovt, true)) {
            $errors[] = 'Invalid Government ID file format.';
        }

        if (!$errors) {

            $govtFileName = 'govt_' . time() . '_' . mt_rand(1000, 9999) . '.' . $govtExt;
            $signFileName = 'sign_' . time() . '_' . mt_rand(1000, 9999) . '.png';

            $govtFullPath = $uploadDir . $govtFileName;
            $signFullPath = $uploadDir . $signFileName;

            move_uploaded_file($_FILES['govtIdFile']['tmp_name'], $govtFullPath);
            file_put_contents($signFullPath, $signatureBinary);

            $govtIdPath   = UPLOAD_URL . '/acknowledgment/' . $govtFileName;
            $signaturePath = UPLOAD_URL . '/acknowledgment/' . $signFileName;
        }
    }

    /* ----------------------------------------------------------
       Save Record
    ---------------------------------------------------------- */

    if (!$errors) {

        $acknowledgedStatusValue = getAcknowledgedStatusValue($con);
        $hasPositionColumn = candidateColumnExists($con, 'position');
        $candidateId = (int) $candidate['id'];

        if ($hasPositionColumn) {

            $update = mysqli_prepare($con, "
                UPDATE candidateRecord
                SET fullName = ?,
                    phoneNumber = ?,
                    email = ?,
                    appliedRole = ?,
                    position = ?,
                    finalSalary = ?,
                    joiningDate = ?,
                    govtIdType = ?,
                    govtIdFile = ?,
                    signatureFile = ?,
                    acknowledgmentStatus = '{$acknowledgedStatusValue}',
                    acknowledgmentSubmittedAt = NOW()
                WHERE id = ?
                AND acknowledgmentStatus NOT IN ('completed', 'acknowledged')
            ");

            if ($update) {
                mysqli_stmt_bind_param(
                    $update,
                    'ssssssssssi',
                    $formValues['fullName'],
                    $formValues['phone'],
                    $formValues['email'],
                    $formValues['role'],
                    $formValues['position'],
                    $formValues['salary'],
                    $formValues['joiningDate'],
                    $formValues['govtIdType'],
                    $govtIdPath,
                    $signaturePath,
                    $candidateId
                );
            }

        } else {

            $positionNote = 'Acknowledgment position: ' . $formValues['position'];

            $update = mysqli_prepare($con, "
                UPDATE candidateRecord
                SET fullName = ?,
                    phoneNumber = ?,
                    email = ?,
                    appliedRole = ?,
                    finalSalary = ?,
                    joiningDate = ?,
                    govtIdType = ?,
                    govtIdFile = ?,
                    signatureFile = ?,
                    internalNotes = TRIM(
                        CONCAT(
                            COALESCE(internalNotes, ''),
                            IF(COALESCE(internalNotes, '') = '', '', CHAR(10)),
                            ?
                        )
                    ),
                    acknowledgmentStatus = '{$acknowledgedStatusValue}',
                    acknowledgmentSubmittedAt = NOW()
                WHERE id = ?
                AND acknowledgmentStatus NOT IN ('completed', 'acknowledged')
            ");

            if ($update) {
                mysqli_stmt_bind_param(
                    $update,
                    'ssssssssssi',
                    $formValues['fullName'],
                    $formValues['phone'],
                    $formValues['email'],
                    $formValues['role'],
                    $formValues['salary'],
                    $formValues['joiningDate'],
                    $formValues['govtIdType'],
                    $govtIdPath,
                    $signaturePath,
                    $positionNote,
                    $candidateId
                );
            }
        }

        /* ----------------------------------------------------------
           Final Execute
        ---------------------------------------------------------- */

        if (!isset($update) || !$update) {

            $errors[] = 'Unable to submit acknowledgment right now. Please contact HR.';

        } else {

            $submitted = mysqli_stmt_execute($update);
            $affectedRows = mysqli_stmt_affected_rows($update);

            mysqli_stmt_close($update);

            if (!$submitted) {

                $errors[] = 'Unable to submit acknowledgment right now. Please contact HR.';

            } elseif ($affectedRows < 1) {

                renderNotice(
                    'Already Submitted',
                    'This acknowledgment has already been submitted. Please contact HR if you need help.'
                );

            } else {

                sendAcknowledgmentSubmittedEmail(
                    $formValues['email'],
                    $formValues['fullName']
                );

                renderNotice(
                    'Acknowledgment Completed',
                    'Thank you. Your acknowledgment has been submitted successfully.'
                );
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Acknowledgment</title>
    <style>
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: #f4f6f8;
            color: #1f2937;
        }

        .container {
            max-width: 820px;
            margin: 56px auto;
            background: #fff;
            padding: 32px 40px;
            border-radius: 8px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin: 0 0 8px;
            font-size: 26px;
            font-weight: 650;
            color: #111827;
        }

        .subtitle {
            margin-bottom: 22px;
            color: #5f6b7a;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 14px;
            margin-bottom: 6px;
            color: #4b5563;
            font-weight: 600;
        }

        input {
            padding: 10px 12px;
            border: 1px solid #d8dde6;
            border-radius: 8px;
            font-size: 14px;
        }

        input:focus {
            border-color: #4f46e5;
            outline: 0;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12);
        }

        .full-width {
            grid-column: span 2;
        }

        .alert {
            margin-bottom: 18px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #991b1b;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 14px;
            line-height: 1.6;
        }

        .submit-btn {
            margin-top: 24px;
            width: 100%;
            padding: 12px;
            border: 0;
            background: #111827;
            color: #fff;
            font-size: 15px;
            border-radius: 8px;
            cursor: pointer;
        }

        .signature-pad {
            width: 100%;
            height: 180px;
            border: 1px solid #d8dde6;
            border-radius: 8px;
            background: #fff;
            cursor: crosshair;
            touch-action: none;
        }

        .signature-help {
            margin: 6px 0 0;
            color: #6b7280;
            font-size: 13px;
        }

        .clear-signature-btn {
            align-self: flex-start;
            margin-top: 8px;
            padding: 7px 12px;
            border: 0;
            border-radius: 6px;
            background: #6b7280;
            color: #fff;
            cursor: pointer;
        }

        /* Government ID Type - Bootstrap Enhanced Style */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #495057;
            margin-bottom: 8px;
        }

        #govtIdType.form-select {
            height: 45px;
            padding: 0.75rem 1rem;
            font-size: 15px;
            font-weight: 500;
            border: 1px solid #ced4da;
            border-radius: 10px;
            background-color: #fff;
            color: #212529;
            transition: all 0.25s ease-in-out;
            box-shadow: none;
        }

        #govtIdType.form-select:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
            outline: none;
        }

        #govtIdType.form-select:hover {
            border-color: #86b7fe;
        }

        #govtIdType option {
            font-size: 15px;
            padding: 10px;
        }

        /* Required label star (optional) */
        .form-group label::after {
            content: " *";
            color: #dc3545;
        }

        @media (max-width: 640px) {
            .container {
                margin: 18px;
                padding: 24px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: span 1;
            }
        }
    </style>
</head>

<body>
    <main class="container">
        <h1>Complete Your Details</h1>
        <div class="subtitle">
            Confirm your information so HR can complete your onboarding acknowledgment.
        </div>

        <?php if ($errors): ?>
        <div class="alert">
            <?php foreach ($errors as $error): ?>
            <div><?php echo h($error); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form id="acknowledgmentForm" method="POST" enctype="multipart/form-data">
            <div class="form-grid">
                <div class="form-group">
                    <label for="fullName">Full Name</label>
                    <input id="fullName" type="text" name="fullName" value="<?php echo h($formValues['fullName']); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input id="phone" type="text" name="phone" value="<?php echo h($formValues['phone']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input id="email" type="email" name="email" value="<?php echo h($formValues['email']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="role">Role</label>
                    <input id="role" type="text" name="role" value="<?php echo h($formValues['role']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="position">Position</label>
                    <input id="position" type="text" name="position" value="<?php echo h($formValues['position']); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="salary">Salary</label>
                    <input id="salary" type="text" name="salary" value="<?php echo h($formValues['salary']); ?>"
                        required>
                </div>

                <div class="form-group">
                    <label for="joiningDate">Joining Date</label>
                    <input id="joiningDate" type="date" name="joiningDate"
                        value="<?php echo h($formValues['joiningDate']); ?>" required>
                </div>

                <div class="form-group">
                    <label for="govtIdType">Government ID Type</label>
                    <select id="govtIdType" class="form-select form-select-lg" name="govtIdType" required>
                        <option value="">Select ID Type</option>
                        <option value="Aadhar">Aadhar</option>
                        <option value="Voter ID">Voter ID</option>
                        <option value="Passport">Passport</option>
                        <option value="Driving License">Driving License</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="govtIdFile">Upload Government ID</label>
                    <input id="govtIdFile" type="file" name="govtIdFile" accept=".jpg,.jpeg,.png,.pdf" required>
                </div>

                <div class="form-group">
                    <label for="signaturePad">Signature</label>
                    <canvas id="signaturePad" class="signature-pad"></canvas>
                    <p class="signature-help">Please sign using your mouse, touch screen, or stylus.</p>
                    <button id="clearSignature" class="clear-signature-btn" type="button">Clear</button>
                    <input id="signatureData" type="hidden" name="signatureData">
                </div>
            </div>

            <button type="submit" class="submit-btn">Complete Acknowledgment</button>
        </form>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>
    <script>
        const form = document.getElementById('acknowledgmentForm');
        const canvas = document.getElementById('signaturePad');
        const signatureData = document.getElementById('signatureData');
        const signaturePad = new SignaturePad(canvas, {
            backgroundColor: 'rgb(255, 255, 255)',
            penColor: '#1f2937',
            minWidth: 0.3,
            maxWidth: 1,
            throttle: 8,
            velocityFilterWeight: 0.85
        });

        const resizeCanvas = () => {
            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            canvas.width = canvas.offsetWidth * ratio;
            canvas.height = 180 * ratio;
            canvas.getContext('2d').scale(ratio, ratio);
            signaturePad.clear();
        };

        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);

        document.getElementById('clearSignature').addEventListener('click', () => {
            signaturePad.clear();
            signatureData.value = '';
        });

        form.addEventListener('submit', (event) => {
            if (signaturePad.isEmpty()) {
                event.preventDefault();
                window.alert('Please provide your signature.');
                return;
            }

            signatureData.value = signaturePad.toDataURL('image/png');
        });
    </script>
</body>

</html>
