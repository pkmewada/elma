<?php
include __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';

header('Content-Type: application/json');

function buildEmployeeFolderName($fullName, $id)
{
    $fullName = preg_replace(
        '/[^a-zA-Z0-9 ]/',
        '',
        (string)$fullName
    );

    $parts = preg_split(
        '/\s+/',
        trim($fullName)
    );

    if (!$parts || empty($parts[0])) {
        return 'employee_' . $id;
    }

    $folder = strtolower(
        array_shift($parts)
    );

    foreach ($parts as $part) {
        $folder .= ucfirst(
            strtolower($part)
        );
    }

    return $folder . '_' . $id;
}

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid employee ID'
    ]);
    exit;
}

$stmt = mysqli_prepare($con, "
    SELECT
        id,
        employeeCode,
        fullName,
        userName,
        emailAddress,
        mobileNumber,
        alternativeNumber,
        emergencyContactNumber,
        dateOfBirth,
        gender,
        maritalStatus,
        linkedInProfile,
        instagramProfile,
        permanentAddress,
        localAddress,
        cityName,
        stateName,
        pinCode,
        departmentName,
        designationName,
        joiningDate,
        employmentStatus,
        employeeType,
        reportingManager,
        basicSalary,
        hraAmount,
        allowanceAmount,
        deductionAmount,
        netSalary,
        otherEarning1Amount,
        otherEarning2Amount,
        otherEarning3Amount,
        otherAllowanceAmount,
        paymentFrequency,
        nextIncrementDate,
        accountHolderName,
        bankName,
        accountNumber,
        ifscCode,
        branchName,
        aadhaarNumber,
        panNumber,
        accountStatus,
        profileStatus,
        hrRemark,
        aboutMe,
        skills,
        verifiedBy,
        verifiedAt,
        joiningStatus,
        candidateRecordId,
        createdAt,
        updatedAt,
        profilePhoto,
        aadhaarFile,
        panFile,
        marksheet10File,
        marksheet12File,
        graduationFile,
        bankPassbookFile
    FROM employeeusers
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare employee query'
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

if (!$row) {
    echo json_encode([
        'success' => false,
        'message' => 'Employee not found'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Folder Path Concept
|--------------------------------------------------------------------------
| Employee folder is generated from:
| buildEmployeeFolderName(fullName, id)
|
| Example:
| Full Name: Varun Sharma
| ID: 15
| Folder: varunSharma_15
|
| Documents/profile photo are stored as file names only.
| Final URL = folderPath + fileName
|--------------------------------------------------------------------------
*/

$folderName = buildEmployeeFolderName(
    $row['fullName'] ?? '',
    (int)$row['id']
);

$row['folderName'] = $folderName;

$row['folderPath'] =
    rtrim(BASE_URL, '/') .
    '/uploads/candidates/' .
    $folderName .
    '/';

/*
|--------------------------------------------------------------------------
| Keep original file names clean
|--------------------------------------------------------------------------
*/

$row['profilePhoto'] = trim((string)($row['profilePhoto'] ?? ''));
$row['aadhaarFile'] = trim((string)($row['aadhaarFile'] ?? ''));
$row['panFile'] = trim((string)($row['panFile'] ?? ''));
$row['marksheet10File'] = trim((string)($row['marksheet10File'] ?? ''));
$row['marksheet12File'] = trim((string)($row['marksheet12File'] ?? ''));
$row['graduationFile'] = trim((string)($row['graduationFile'] ?? ''));
$row['bankPassbookFile'] = trim((string)($row['bankPassbookFile'] ?? ''));

echo json_encode([
    'success' => true,
    'data' => $row
]);