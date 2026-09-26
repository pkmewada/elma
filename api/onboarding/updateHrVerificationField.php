<?php

include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

requireApiPermission('/onboarding-queue', 'canApprove');

error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');




/*
|--------------------------------------------------------------------------
| Request Data
|--------------------------------------------------------------------------
*/
$employeeUserId = (int)($_POST['employeeUserId'] ?? 0);
$fieldName      = trim((string)($_POST['fieldName'] ?? ''));
$fieldValue     = trim((string)($_POST['fieldValue'] ?? ''));
$verifyStatus   = trim((string)($_POST['verifyStatus'] ?? 'Pending'));
$reviewRemark   = trim((string)($_POST['reviewRemark'] ?? ''));


$check = mysqli_query(
    $con,
    "SELECT id
     FROM employeeusers
     WHERE id = {$employeeUserId}
     LIMIT 1"
);

if (!$check || !mysqli_fetch_assoc($check)) {

    echo json_encode([
        'success' => false,
        'message' => 'Employee not found.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Basic Validation
|--------------------------------------------------------------------------
*/
if ($employeeUserId <= 0 || $fieldName === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Allowed Fields
|--------------------------------------------------------------------------
*/
$allowed = [

    // Personal
    'mobileNumber',
    'alternativeNumber',
    'emergencyContactNumber',
    'dateOfBirth',
    'gender',
    'maritalStatus',

    // Address
    'permanentAddress',
    'localAddress',
    'cityName',
    'stateName',
    'pinCode',

    // Social
    'linkedInProfile',
    'instagramProfile',

    // Employment
    'departmentName',

    // Bank
    'accountHolderName',
    'bankName',
    'accountNumber',
    'ifscCode',
    'branchName',

    // KYC
    'aadhaarNumber',
    'panNumber',

    // Documents
    'profilePhoto',
    'aadhaarFile',
    'panFile',
    'marksheet10File',
    'marksheet12File',
    'graduationFile',
    'bankPassbookFile'
];

if (!in_array($fieldName, $allowed, true)) {

    echo json_encode([
        'success' => false,
        'message' => 'Field not allowed.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Allowed Status
|--------------------------------------------------------------------------
*/
$verifyStatus = ucfirst(strtolower($verifyStatus));

$allowedStatuses = [
    'Pending',
    'Verified',
    'Rejected'
];

if (!in_array($verifyStatus, $allowedStatuses, true)) {
    $verifyStatus = 'Pending';
}

/*
|--------------------------------------------------------------------------
| Rejected Requires Remark
|--------------------------------------------------------------------------
*/
if (
    $verifyStatus === 'Rejected'
    && $reviewRemark === ''
) {
    echo json_encode([
        'success' => false,
        'message' => 'Remark is required for rejected items.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Clear Remark If Not Rejected
|--------------------------------------------------------------------------
*/
if ($verifyStatus !== 'Rejected') {
    $reviewRemark = '';
}

/*
|--------------------------------------------------------------------------
| Document Fields
|--------------------------------------------------------------------------
*/
$documentFields = [
    'profilePhoto',
    'aadhaarFile',
    'panFile',
    'marksheet10File',
    'marksheet12File',
    'graduationFile',
    'bankPassbookFile'
];

$isDocumentField = in_array(
    $fieldName,
    $documentFields,
    true
);

/*
|--------------------------------------------------------------------------
| Update employeeusers
|
| Only for editable text fields.
| Do NOT overwrite document paths.
|--------------------------------------------------------------------------
*/
if (!$isDocumentField) {

    $sql = "
        UPDATE employeeusers
        SET
            `$fieldName` = ?,
            updatedAt = NOW()
        WHERE id = ?
    ";

    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {

        echo json_encode([
            'success' => false,
            'message' => 'Prepare failed.'
        ]);
        exit;
    }

    mysqli_stmt_bind_param(
        $stmt,
        "si",
        $fieldValue,
        $employeeUserId
    );

    if (!mysqli_stmt_execute($stmt)) {

        echo json_encode([
            'success' => false,
            'message' => 'Unable to update record.'
        ]);
        exit;
    }

    mysqli_stmt_close($stmt);
}

/*
|--------------------------------------------------------------------------
| Save Verification Status + Remark
|--------------------------------------------------------------------------
*/
$fieldNameEsc    = mysqli_real_escape_string($con, $fieldName);
$verifyStatusEsc = mysqli_real_escape_string($con, $verifyStatus);
$reviewRemarkEsc = mysqli_real_escape_string($con, $reviewRemark);

$query = "
    INSERT INTO employeeProfileVerification
    (
        employeeUserId,
        fieldName,
        verifyStatus,
        reviewRemark
    )
    VALUES
    (
        {$employeeUserId},
        '{$fieldNameEsc}',
        '{$verifyStatusEsc}',
        '{$reviewRemarkEsc}'
    )

    ON DUPLICATE KEY UPDATE

        verifyStatus = VALUES(verifyStatus),
        reviewRemark = VALUES(reviewRemark),
        updatedAt = NOW()
";

if (!mysqli_query($con, $query)) {

    echo json_encode([
        'success' => false,
        'message' => 'Unable to save verification status.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Updated successfully.'
]);