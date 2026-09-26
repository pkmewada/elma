<?php

include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

header('Content-Type: application/json');

requireApiPermission('/onboarding-queue', 'canView');

$employeeUserId = (int)($_GET['employeeUserId'] ?? 0);
$departmentName = trim((string)($_GET['departmentName'] ?? ''));

if ($employeeUserId <= 0) {
    echo json_encode([
        'success' => false,
        'ready' => false,
        'message' => 'Invalid employee user id.'
    ]);
    exit;
}

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
        'ready' => false,
        'message' => 'Unable to check verification status.'
    ]);
    exit;
}

$row = mysqli_fetch_assoc($q);

$verifiedReady = ((int)($row['cnt'] ?? 0) >= $total);

/*
|--------------------------------------------------------------------------
| Department Required
|--------------------------------------------------------------------------
| Employee Code is not checked here anymore.
| It will be generated automatically in finalVerifyCandidate.php.
|--------------------------------------------------------------------------
*/
$metaReady = ($departmentName !== '');

$ready = $verifiedReady && $metaReady;

echo json_encode([
    'success' => true,
    'ready' => $ready,
    'verifiedReady' => $verifiedReady,
    'metaReady' => $metaReady,
    'departmentReady' => $departmentName !== '',
    'message' => $ready
        ? 'Ready for final verification.'
        : 'Required fields/documents or department are incomplete.'
]);