<?php

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/basic-config.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

header('Content-Type: application/json; charset=UTF-8');

requireApiPermission('/onboarding-queue', 'canApprove');

/*
|--------------------------------------------------------------------------
| Generate Employee Code
|--------------------------------------------------------------------------
| Format: EMP-YYMM-ID
| Example: EMP-2606-001
|--------------------------------------------------------------------------
*/
function generateEmployeeCodeFromId($employeeUserId)
{
    $prefix = 'EMP';

    $year = date('y');   // 26
    $month = date('m');  // 06

    $idNumber = (int)$employeeUserId;

    return $prefix . '-' . $year . '-' . $month . '-' . $idNumber;
}

/*
|--------------------------------------------------------------------------
| Request Data
|--------------------------------------------------------------------------
*/
$employeeUserId = (int) ($_POST['employeeUserId'] ?? 0);

if ($employeeUserId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee user id.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Employee Department From employeeusers
|--------------------------------------------------------------------------
| Department should already be saved using updateHrVerificationField.php
|--------------------------------------------------------------------------
*/
$empStmt = mysqli_prepare($con, "
    SELECT id, departmentName
    FROM employeeusers
    WHERE id = ?
    LIMIT 1
");

if (!$empStmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare employee check.'
    ]);
    exit;
}

mysqli_stmt_bind_param($empStmt, 'i', $employeeUserId);
mysqli_stmt_execute($empStmt);

$empResult = mysqli_stmt_get_result($empStmt);
$empRow = mysqli_fetch_assoc($empResult);

mysqli_stmt_close($empStmt);

if (!$empRow) {
    echo json_encode([
        'success' => false,
        'message' => 'Employee not found.'
    ]);
    exit;
}

$departmentName = trim((string) ($empRow['departmentName'] ?? ''));

if ($departmentName === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Department is required.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Department From Basic Config
|--------------------------------------------------------------------------
*/
$config = getBasicConfig();

$departments = $config['departments'] ?? [];

$isDepartmentAllowed = false;

foreach ($departments as $dep) {

    // If config is simple string array
    if (is_string($dep)) {

        if (strcasecmp(trim($dep), $departmentName) === 0) {
            $isDepartmentAllowed = true;
            break;
        }
    }

    // If config is structured array
    elseif (is_array($dep)) {

        $depName = trim((string) ($dep['name'] ?? ''));
        $depStatus = trim((string) ($dep['status'] ?? 'Active'));

        if (
            strcasecmp($depName, $departmentName) === 0 &&
            strcasecmp($depStatus, 'Active') === 0
        ) {
            $isDepartmentAllowed = true;
            break;
        }
    }
}

if (!$isDepartmentAllowed) {

    echo json_encode([
        'success' => false,
        'message' => 'Selected department is not active.'
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Required Verification Fields
|--------------------------------------------------------------------------
*/
$required = [
    'mobileNumber',
    'dateOfBirth',
    'gender',
    'permanentAddress',
    'cityName',
    'stateName',
    'pinCode',
    'accountHolderName',
    'bankName',
    'accountNumber',
    'ifscCode',
    'aadhaarNumber',
    'panNumber',
    'profilePhoto',
    'aadhaarFile',
    'panFile',
    'marksheet10File',
    'bankPassbookFile'
];

$total = count($required);

$escapedFields = array_map(static function ($field) use ($con) {
    return "'" . mysqli_real_escape_string($con, $field) . "'";
}, $required);

$q = mysqli_query($con, "
    SELECT COUNT(*) AS cnt
    FROM employeeProfileVerification
    WHERE employeeUserId = {$employeeUserId}
      AND verifyStatus = 'Verified'
      AND fieldName IN (" . implode(',', $escapedFields) . ")
");

if (!$q) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to validate verification status.'
    ]);
    exit;
}

$row = mysqli_fetch_assoc($q);

if ((int) ($row['cnt'] ?? 0) < $total) {
    echo json_encode([
        'success' => false,
        'message' => 'All required fields and documents must be verified first.'
    ]);
    exit;
}

$mailSent = false;
$shouldSendMail = false;
$mailTo = '';
$mailName = '';
$mailTempPassword = '';

mysqli_begin_transaction($con);

try {

    /*
    |--------------------------------------------------------------------------
    | Lock Employee Row
    |--------------------------------------------------------------------------
    */
    $lockStmt = mysqli_prepare($con, "
        SELECT 
            id,
            fullName,
            emailAddress,
            profileStatus,
            employeeCode,
            departmentName,
            isTempPassword,
            tempPassword
        FROM employeeusers
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");

    if (!$lockStmt) {
        throw new Exception('Unable to lock candidate row.');
    }

    mysqli_stmt_bind_param($lockStmt, 'i', $employeeUserId);
    mysqli_stmt_execute($lockStmt);

    $lockedResult = mysqli_stmt_get_result($lockStmt);
    $user = mysqli_fetch_assoc($lockedResult);

    mysqli_stmt_close($lockStmt);

    if (!$user || empty($user['emailAddress'])) {
        throw new Exception('Candidate email not found. Cannot send login credentials.');
    }

    /*
    |--------------------------------------------------------------------------
    | Idempotent Guard
    | Do not regenerate password or resend mail if already finalized.
    |--------------------------------------------------------------------------
    */
    $alreadyFinalized = (
        ((string) ($user['profileStatus'] ?? '') === 'Verified') &&
        ((int) ($user['isTempPassword'] ?? 0) === 1) &&
        trim((string) ($user['tempPassword'] ?? '')) !== ''
    );

    if ($alreadyFinalized) {
        mysqli_commit($con);

        echo json_encode([
            'success' => true,
            'message' => 'Final verification already completed. Existing credentials remain active.',
            'mailSent' => false,
            'employeeCode' => $user['employeeCode'] ?? ''
        ]);
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Employee Code Only If Empty
    |--------------------------------------------------------------------------
    */
    $existingEmployeeCode = trim((string) ($user['employeeCode'] ?? ''));

    if ($existingEmployeeCode !== '') {

        $employeeCode = $existingEmployeeCode;

    } else {

        $employeeCode = generateEmployeeCodeFromId($employeeUserId);

        /*
        |--------------------------------------------------------------------------
        | Safety Duplicate Check
        |--------------------------------------------------------------------------
        */
        $employeeCodeEsc = mysqli_real_escape_string($con, $employeeCode);

        $dupQ = mysqli_query($con, "
            SELECT id
            FROM employeeusers
            WHERE employeeCode = '{$employeeCodeEsc}'
              AND id <> '{$employeeUserId}'
            LIMIT 1
        ");

        if ($dupQ && mysqli_fetch_assoc($dupQ)) {
            throw new Exception('Generated Employee Code already exists. Please contact admin.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Create Temporary Password
    |--------------------------------------------------------------------------
    */
    $tempPassword = 'Temp@' . random_int(1000, 9999);
    $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

    /*
    |--------------------------------------------------------------------------
    | Final Verify Employee
    |--------------------------------------------------------------------------
    */
    $updateStmt = mysqli_prepare($con, "
        UPDATE employeeusers
        SET
            profileStatus = 'Verified',
            accountStatus = 'Active',
            employeeCode = ?,
            departmentName = ?,
            passwordHash = ?,
            tempPassword = ?,
            isTempPassword = 1,
            updatedAt = NOW()
        WHERE id = ?
        LIMIT 1
    ");

    if (!$updateStmt) {
        throw new Exception('Unable to update user credentials.');
    }

    mysqli_stmt_bind_param(
        $updateStmt,
        'ssssi',
        $employeeCode,
        $departmentName,
        $passwordHash,
        $tempPassword,
        $employeeUserId
    );

    $updated = mysqli_stmt_execute($updateStmt);

    mysqli_stmt_close($updateStmt);

    if (!$updated) {
        throw new Exception('Unable to finalize verification.');
    }

    $shouldSendMail = true;
    $mailTo = (string) $user['emailAddress'];
    $mailName = (string) $user['fullName'];
    $mailTempPassword = $tempPassword;

    mysqli_commit($con);

} catch (Throwable $e) {

    mysqli_rollback($con);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Send Final Verification Email
|--------------------------------------------------------------------------
*/
if ($shouldSendMail && function_exists('sendFinalVerificationAccessEmail')) {
    $mailSent = sendFinalVerificationAccessEmail(
        $mailTo,
        $mailName,
        $mailTempPassword
    );
}

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => $mailSent
        ? 'Final verification completed. Login credentials sent by email.'
        : 'Final verification completed, but email sending failed.',
    'mailSent' => $mailSent,
    'employeeCode' => $employeeCode
]);