<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);

include __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

function respond($success, $message = '', $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function postValue($key)
{
    return trim((string)($_POST[$key] ?? ''));
}

function nullableDate($value)
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function nullableText($value)
{
    $value = trim((string)$value);
    return $value === '' ? null : $value;
}

function nullableAmount($value)
{
    $value = trim((string)$value);
    return $value === '' ? 0 : (float)$value;
}

function employeeFieldExists(mysqli $con, string $field, string $value, int $excludeId): bool
{
    $allowedFields = ['emailAddress', 'userName'];

    if (!in_array($field, $allowedFields, true) || trim($value) === '') {
        return false;
    }

    $sql = "SELECT id FROM employeeusers WHERE {$field} = ? AND id <> ? LIMIT 1";
    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {
        return false;
    }

    mysqli_stmt_bind_param($stmt, 'si', $value, $excludeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $exists = $result && mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return (bool)$exists;
}

$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    respond(false, 'Invalid employee ID.');
}

$mobileNumber = postValue('mobileNumber');
$alternativeNumber = postValue('alternativeNumber');
$emergencyContactNumber = postValue('emergencyContactNumber');
$userName = postValue('userName');
$emailAddress = postValue('emailAddress');
$pinCode = postValue('pinCode');
$aadhaarNumber = postValue('aadhaarNumber');
$panNumber = postValue('panNumber');

if ($mobileNumber !== '' && !preg_match('/^[0-9]{10}$/', $mobileNumber)) {
    respond(false, 'Mobile number must be 10 digits.');
}

if ($alternativeNumber !== '' && !preg_match('/^[0-9]{10}$/', $alternativeNumber)) {
    respond(false, 'Alternative number must be 10 digits.');
}

if ($emergencyContactNumber !== '' && !preg_match('/^[0-9]{10}$/', $emergencyContactNumber)) {
    respond(false, 'Emergency contact number must be 10 digits.');
}

if ($emailAddress !== '' && !filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email address.');
}

if (employeeFieldExists($con, 'userName', $userName, $id)) {
    respond(false, 'Username is already used by another employee.');
}

if (employeeFieldExists($con, 'emailAddress', $emailAddress, $id)) {
    respond(false, 'Email address is already used by another employee.');
}

if ($pinCode !== '' && !preg_match('/^[0-9]{6}$/', $pinCode)) {
    respond(false, 'Pin code must be 6 digits.');
}

if ($aadhaarNumber !== '' && !preg_match('/^[0-9]{12}$/', $aadhaarNumber)) {
    respond(false, 'Aadhaar number must be 12 digits.');
}

if ($panNumber !== '' && !preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', strtoupper($panNumber))) {
    respond(false, 'Invalid PAN number format.');
}

$fields = [
    'fullName' => postValue('fullName'),
    'userName' => nullableText($userName),
    'emailAddress' => nullableText($emailAddress),
    'mobileNumber' => $mobileNumber,
    'alternativeNumber' => $alternativeNumber,
    'emergencyContactNumber' => $emergencyContactNumber,
    'dateOfBirth' => nullableDate(postValue('dateOfBirth')),
    'gender' => postValue('gender'),
    'maritalStatus' => postValue('maritalStatus'),
    'linkedInProfile' => postValue('linkedInProfile'),
    'instagramProfile' => postValue('instagramProfile'),
    'permanentAddress' => postValue('permanentAddress'),
    'localAddress' => postValue('localAddress'),
    'cityName' => postValue('cityName'),
    'stateName' => postValue('stateName'),
    'pinCode' => $pinCode,
    'departmentName' => postValue('departmentName'),
    'designationName' => postValue('designationName'),
    'joiningDate' => nullableDate(postValue('joiningDate')),
    'employeeType' => postValue('employeeType'),
    'reportingManager' => postValue('reportingManager'),
    'basicSalary' => nullableAmount(postValue('basicSalary')),
    'hraAmount' => nullableAmount(postValue('hraAmount')),
    'allowanceAmount' => nullableAmount(postValue('allowanceAmount')),
    'deductionAmount' => nullableAmount(postValue('deductionAmount')),
    'netSalary' => nullableAmount(postValue('netSalary')),
    'otherEarning1Amount' => nullableAmount(postValue('otherEarning1Amount')),
    'otherEarning2Amount' => nullableAmount(postValue('otherEarning2Amount')),
    'otherEarning3Amount' => nullableAmount(postValue('otherEarning3Amount')),
    'otherAllowanceAmount' => nullableAmount(postValue('otherAllowanceAmount')),
    'paymentFrequency' => postValue('paymentFrequency'),
    'nextIncrementDate' => nullableDate(postValue('nextIncrementDate')),
    'accountHolderName' => postValue('accountHolderName'),
    'bankName' => postValue('bankName'),
    'accountNumber' => postValue('accountNumber'),
    'ifscCode' => strtoupper(postValue('ifscCode')),
    'branchName' => postValue('branchName'),
    'aadhaarNumber' => $aadhaarNumber,
    'panNumber' => strtoupper($panNumber),
    'joiningStatus' => postValue('joiningStatus'),
    'aboutMe' => postValue('aboutMe'),
    'skills' => postValue('skills'),
    'hrRemark' => postValue('hrRemark')
];

$stmt = mysqli_prepare($con, "
    UPDATE employeeusers
    SET
        fullName = ?,
        userName = ?,
        emailAddress = ?,
        mobileNumber = ?,
        alternativeNumber = ?,
        emergencyContactNumber = ?,
        dateOfBirth = ?,
        gender = ?,
        maritalStatus = ?,
        linkedInProfile = ?,
        instagramProfile = ?,
        permanentAddress = ?,
        localAddress = ?,
        cityName = ?,
        stateName = ?,
        pinCode = ?,
        departmentName = ?,
        designationName = ?,
        joiningDate = ?,
        employeeType = ?,
        reportingManager = ?,
        basicSalary = ?,
        hraAmount = ?,
        allowanceAmount = ?,
        deductionAmount = ?,
        netSalary = ?,
        otherEarning1Amount = ?,
        otherEarning2Amount = ?,
        otherEarning3Amount = ?,
        otherAllowanceAmount = ?,
        paymentFrequency = ?,
        nextIncrementDate = ?,
        accountHolderName = ?,
        bankName = ?,
        accountNumber = ?,
        ifscCode = ?,
        branchName = ?,
        aadhaarNumber = ?,
        panNumber = ?,
        joiningStatus = ?,
        aboutMe = ?,
        skills = ?,
        hrRemark = ?,
        updatedAt = NOW()
    WHERE id = ?
");

if (!$stmt) {
    respond(false, 'Unable to prepare update query: ' . mysqli_error($con));
}

$types = str_repeat('s', 21) . str_repeat('d', 9) . str_repeat('s', 13) . 'i';

mysqli_stmt_bind_param(
    $stmt,
    $types,

    $fields['fullName'],
    $fields['userName'],
    $fields['emailAddress'],
    $fields['mobileNumber'],
    $fields['alternativeNumber'],
    $fields['emergencyContactNumber'],
    $fields['dateOfBirth'],
    $fields['gender'],
    $fields['maritalStatus'],
    $fields['linkedInProfile'],
    $fields['instagramProfile'],
    $fields['permanentAddress'],
    $fields['localAddress'],
    $fields['cityName'],
    $fields['stateName'],
    $fields['pinCode'],
    $fields['departmentName'],
    $fields['designationName'],
    $fields['joiningDate'],
    $fields['employeeType'],
    $fields['reportingManager'],

    $fields['basicSalary'],
    $fields['hraAmount'],
    $fields['allowanceAmount'],
    $fields['deductionAmount'],
    $fields['netSalary'],
    $fields['otherEarning1Amount'],
    $fields['otherEarning2Amount'],
    $fields['otherEarning3Amount'],
    $fields['otherAllowanceAmount'],

    $fields['paymentFrequency'],
    $fields['nextIncrementDate'],
    $fields['accountHolderName'],
    $fields['bankName'],
    $fields['accountNumber'],
    $fields['ifscCode'],
    $fields['branchName'],
    $fields['aadhaarNumber'],
    $fields['panNumber'],
    $fields['joiningStatus'],
    $fields['aboutMe'],
    $fields['skills'],
    $fields['hrRemark'],

    $id
);

$updated = mysqli_stmt_execute($stmt);

if (!$updated) {
    $error = mysqli_stmt_error($stmt) ?: mysqli_error($con);
    respond(false, 'Unable to update employee details: ' . $error);
}

mysqli_stmt_close($stmt);

respond(true, 'Employee details updated successfully.');
