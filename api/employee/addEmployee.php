<?php
/*
|--------------------------------------------------------------------------
| Add CRM employee account (admin only)
|--------------------------------------------------------------------------
| Replaces the removed recruitment -> onboarding -> joining pipeline as the
| way sales accounts (Sales Manager / Sales Executive) are created. The role
| is employeeusers.designationName, which the existing permission system
| resolves against rolePermissions.
|
| The account is created Active + Verified with a one-time temporary
| password (hash only; the plain value is returned once to the admin and
| never stored). On first login the employee is forced to set a new
| password (CandidateAuthController::login -> candidate-reset-password).
*/

include __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/Csrf.php';

header('Content-Type: application/json; charset=UTF-8');

function respondAddEmployee(int $statusCode, bool $success, string $message, array $data = []): void
{
    http_response_code($statusCode);
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondAddEmployee(405, false, 'Method not allowed.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respondAddEmployee(403, false, 'Your session could not be verified. Please refresh the page and try again.');
}

$fullName = trim((string)($_POST['fullName'] ?? ''));
$email = strtolower(trim((string)($_POST['emailAddress'] ?? '')));
$mobile = trim((string)($_POST['mobileNumber'] ?? ''));
$designation = trim((string)($_POST['designationName'] ?? ''));
$department = trim((string)($_POST['departmentName'] ?? ''));
$employeeCode = trim((string)($_POST['employeeCode'] ?? ''));
$joiningDate = trim((string)($_POST['joiningDate'] ?? ''));

$config = getBasicConfig();
$allowedRoles = $config['roles'] ?? [];

if ($fullName === '' || mb_strlen($fullName) > 150) {
    respondAddEmployee(422, false, 'Full name is required (max 150 characters).');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
    respondAddEmployee(422, false, 'A valid email address is required.');
}

if ($mobile !== '' && !preg_match('/^\+?[0-9 ]{7,20}$/', $mobile)) {
    respondAddEmployee(422, false, 'Mobile number must contain 7-20 digits.');
}

if (!in_array($designation, $allowedRoles, true)) {
    respondAddEmployee(422, false, 'Select a role configured in Basic Setup.');
}

if (mb_strlen($department) > 100 || mb_strlen($employeeCode) > 30) {
    respondAddEmployee(422, false, 'Department or employee code is too long.');
}

if ($joiningDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $joiningDate)) {
    respondAddEmployee(422, false, 'Invalid joining date.');
}

try {
    $checkStmt = mysqli_prepare($con, 'SELECT id FROM employeeusers WHERE emailAddress = ? LIMIT 1');
    mysqli_stmt_bind_param($checkStmt, 's', $email);
    mysqli_stmt_execute($checkStmt);
    mysqli_stmt_store_result($checkStmt);
    $emailTaken = mysqli_stmt_num_rows($checkStmt) > 0;
    mysqli_stmt_close($checkStmt);

    if ($emailTaken) {
        respondAddEmployee(409, false, 'An employee with this email already exists.');
    }

    $tempPassword = rtrim(strtr(base64_encode(random_bytes(9)), '+/', 'Ab'), '=');
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    $mobileValue = $mobile !== '' ? $mobile : null;
    $departmentValue = $department !== '' ? $department : null;
    $employeeCodeValue = $employeeCode !== '' ? $employeeCode : null;
    $joiningDateValue = $joiningDate !== '' ? $joiningDate : null;

    $insertStmt = mysqli_prepare(
        $con,
        "INSERT INTO employeeusers (
            employeeCode, fullName, emailAddress, mobileNumber,
            departmentName, designationName, joiningDate,
            passwordHash, isTempPassword, accountStatus, employmentStatus,
            profileStatus, joiningStatus
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, 'Active', 'Active', 'Verified', 'Confirmed')"
    );

    mysqli_stmt_bind_param(
        $insertStmt,
        'ssssssss',
        $employeeCodeValue,
        $fullName,
        $email,
        $mobileValue,
        $departmentValue,
        $designation,
        $joiningDateValue,
        $passwordHash
    );

    if (!mysqli_stmt_execute($insertStmt)) {
        if (mysqli_stmt_errno($insertStmt) === 1062) {
            respondAddEmployee(409, false, 'This employee code or email is already in use.');
        }

        throw new RuntimeException('Insert failed: ' . mysqli_stmt_error($insertStmt));
    }

    $employeeId = (int)mysqli_insert_id($con);
    mysqli_stmt_close($insertStmt);

    respondAddEmployee(200, true, 'Employee account created.', [
        'id' => $employeeId,
        'emailAddress' => $email,
        'tempPassword' => $tempPassword,
    ]);
} catch (Throwable $e) {
    error_log('addEmployee failed: ' . $e->getMessage());
    respondAddEmployee(500, false, 'Unable to create employee right now.');
}
