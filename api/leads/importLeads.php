<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (!isLoggedIn()) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.'
    ]);

    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}

$userType = getLoggedInUserType();
$permissionRoute = $userType === 'employee' ? '/emp-leads' : '/leads';

requireActionPermission($permissionRoute, 'import_leads');

/*
|--------------------------------------------------------------------------
| Static Values
|--------------------------------------------------------------------------
| Later change these IDs as per your database.
|--------------------------------------------------------------------------
*/

$staticCategoryId = 3;
$staticPlanId = 3;
$status = 'open';

$employeeId = (int)($_POST['employeeId'] ?? 0);

if ($userType === 'employee') {
    $employeeId = getLoggedInUserId();
}

if ($employeeId <= 0) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please select employee.'
    ]);

    exit;
}

if (
    empty($_FILES['leadCsvFile'])
    || $_FILES['leadCsvFile']['error'] !== UPLOAD_ERR_OK
) {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please upload a valid CSV file.'
    ]);

    exit;
}

$fileName = $_FILES['leadCsvFile']['name'];
$fileTmpPath = $_FILES['leadCsvFile']['tmp_name'];
$fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExt !== 'csv') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Only CSV file is allowed.'
    ]);

    exit;
}

$handle = fopen($fileTmpPath, 'r');

if (!$handle) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to read CSV file.'
    ]);

    exit;
}

$header = fgetcsv($handle);

$requiredColumns = [
    'fullName',
    'email',
    'phone',
    'source',
    'orgName'
];

$header = array_map('trim', $header ?: []);

foreach ($requiredColumns as $column) {
    if (!in_array($column, $header, true)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSV format. Required columns: fullName, email, phone, source'
        ]);

        fclose($handle);
        exit;
    }
}

$columnMap = array_flip($header);

// Country/countryCode are optional columns -- old-style CSVs (just the
// original 5 columns) keep importing exactly as before, defaulting to
// India/+91 rather than being left without any country information.
$hasCountryColumn = isset($columnMap['country']);
$hasCountryCodeColumn = isset($columnMap['countryCode']);

$insertStmt = mysqli_prepare(
    $con,
    "
    INSERT INTO leads
    (
        fullName,
        email,
        phone,
        country,
        countryCode,
        source,
        orgName,
        categoryId,
        planId,
        status,
        createdByCandidateId
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
    )
    "
);

if (!$insertStmt) {
    fclose($handle);

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Failed to prepare import query.'
    ]);

    exit;
}

$imported = 0;
$skipped = 0;
$errors = [];

while (($row = fgetcsv($handle)) !== false) {

    $fullName = trim($row[$columnMap['fullName']] ?? '');
    $email = trim($row[$columnMap['email']] ?? '');
    $phone = trim($row[$columnMap['phone']] ?? '');
    $source = trim($row[$columnMap['source']] ?? '');
    $orgName = trim($row[$columnMap['orgName']] ?? '');

    $country = $hasCountryColumn ? trim($row[$columnMap['country']] ?? '') : '';
    $countryCode = $hasCountryCodeColumn ? trim($row[$columnMap['countryCode']] ?? '') : '';

    if ($country === '') {
        $country = 'India';
    }
    if ($countryCode === '') {
        $countryCode = '+91';
    } elseif ($countryCode[0] !== '+') {
        $countryCode = '+' . $countryCode;
    }

    if (
        $fullName === ''
        || $email === ''
        || $phone === ''
        || $source === ''
        || $orgName === ''
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !preg_match('/^\+\d{1,4}$/', $countryCode)
        || !preg_match('/^[0-9]{6,15}$/', $phone)
    ) {
        $skipped++;
        continue;
    }

    mysqli_stmt_bind_param(
        $insertStmt,
        'sssssssiisi',
        $fullName,
        $email,
        $phone,
        $country,
        $countryCode,
        $source,
        $orgName,
        $staticCategoryId,
        $staticPlanId,
        $status,
        $employeeId
    );

    if (mysqli_stmt_execute($insertStmt)) {
        $imported++;
    } else {
        $skipped++;
        $errors[] = $email;
    }
}

mysqli_stmt_close($insertStmt);
fclose($handle);


/*
|--------------------------------------------------------------------------
| Activity Logger
|--------------------------------------------------------------------------
*/


if ($imported > 0) {

    $assignedEmployee = (string)$employeeId;
    $employeeStmt = mysqli_prepare(
        $con,
        'SELECT fullName FROM employeeusers WHERE id = ? LIMIT 1'
    );

    if ($employeeStmt) {
        mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
        mysqli_stmt_execute($employeeStmt);
        $employeeRow = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
        $assignedEmployee = $employeeRow['fullName'] ?? $assignedEmployee;
        mysqli_stmt_close($employeeStmt);
    }


    saveActivityLog(

        $con,

        "Lead",

        null,

        "IMPORT",

        "Bulk lead import completed",

        null,

        [

            "fileName" =>
                $fileName,


            "assignedEmployee" =>
                $assignedEmployee,


            "imported" =>
                $imported,


            "skipped" =>
                $skipped,


            "errors" =>
                $errors,


            "categoryId" =>
                $staticCategoryId,


            "planId" =>
                $staticPlanId,


            "status" =>
                $status

        ]

    );

}

echo json_encode([
    'success' => true,
    'message' => $imported . ' leads imported successfully. ' . $skipped . ' skipped.',
    'data' => [
        'imported' => $imported,
        'skipped' => $skipped,
        'errors' => $errors
    ]
]);
