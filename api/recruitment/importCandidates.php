<?php
header('Content-Type: application/json');
error_reporting(0);

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

function response($success, $message, $data = []) {
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

// Check if file uploaded
if (!isset($_FILES['candidateCsvFile']) || $_FILES['candidateCsvFile']['error'] === UPLOAD_ERR_NO_FILE) {
    response(false, 'No CSV file uploaded.');
}

$file = $_FILES['candidateCsvFile'];

// Validate file type
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if ($extension !== 'csv') {
    response(false, 'Only CSV files are allowed.');
}

// Open CSV file
$handle = fopen($file['tmp_name'], 'r');
if (!$handle) {
    response(false, 'Unable to read CSV file.');
}

// Get headers
$headers = fgetcsv($handle);
if (!$headers) {
    fclose($handle);
    response(false, 'Invalid CSV format.');
}

// Trim headers and convert to lowercase
$headers = array_map('strtolower', array_map('trim', $headers));

// Expected columns
$expectedColumns = ['fullname', 'email', 'phonenumber', 'currentlocation', 'appliedrole', 'experienceyears', 'expectedsalary'];

// Validate headers
foreach ($expectedColumns as $col) {
    if (!in_array($col, $headers)) {
        fclose($handle);
        response(false, "CSV missing required column: $col");
    }
}

$imported = 0;
$errors = [];
$rowNumber = 1;

while (($row = fgetcsv($handle)) !== false) {
    $rowNumber++;
    
    // Map row data to columns
    $data = array_combine($headers, $row);
    
    // Skip empty rows
    if (empty(array_filter($data))) {
        continue;
    }
    
    // Clean data
    $fullName = trim($data['fullname'] ?? '');
    $email = strtolower(trim($data['email'] ?? ''));
    $phoneNumber = trim($data['phonenumber'] ?? '');
    $currentLocation = trim($data['currentlocation'] ?? '');
    $appliedRole = trim($data['appliedrole'] ?? '');
    $experienceYears = (int)($data['experienceyears'] ?? 0);
    $expectedSalary = trim($data['expectedsalary'] ?? '');
    
    // Validate required fields
    if (empty($fullName) || empty($email) || empty($phoneNumber)) {
        $errors[] = "Row $rowNumber: Missing required fields (fullName, email, phoneNumber)";
        continue;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Row $rowNumber: Invalid email format: $email";
        continue;
    }
    
    // Validate phone
    if (!preg_match('/^[6-9][0-9]{9}$/', $phoneNumber)) {
        $errors[] = "Row $rowNumber: Invalid phone number: $phoneNumber (must be 10 digits starting with 6-9)";
        continue;
    }
    
    // Check for duplicate email
    $checkStmt = mysqli_prepare($con, "SELECT id FROM candidateRecord WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "s", $email);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    if (mysqli_num_rows($checkResult) > 0) {
        $errors[] = "Row $rowNumber: Duplicate email: $email";
        mysqli_stmt_close($checkStmt);
        continue;
    }
    mysqli_stmt_close($checkStmt);
    
    // Check for duplicate phone
    $checkStmt = mysqli_prepare($con, "SELECT id FROM candidateRecord WHERE phoneNumber = ? LIMIT 1");
    mysqli_stmt_bind_param($checkStmt, "s", $phoneNumber);
    mysqli_stmt_execute($checkStmt);
    $checkResult = mysqli_stmt_get_result($checkStmt);
    if (mysqli_num_rows($checkResult) > 0) {
        $errors[] = "Row $rowNumber: Duplicate phone number: $phoneNumber";
        mysqli_stmt_close($checkStmt);
        continue;
    }
    mysqli_stmt_close($checkStmt);
    
    // Insert candidate
    $status = 'open';
    $employeeName = 'Career Portal';
    $internalNotes = 'Imported via CSV';
    $resumeFile = '';
    $portfolioUrl = '';
    
    $insertQuery = "
    INSERT INTO candidateRecord
    (fullName, email, phoneNumber, currentLocation, appliedRole, 
     experienceYears, expectedSalary, resumeFile, internalNotes, 
     status, employeeName, portfolioUrl)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = mysqli_prepare($con, $insertQuery);
    mysqli_stmt_bind_param(
        $stmt,
        "sssssissssss",
        $fullName,
        $email,
        $phoneNumber,
        $currentLocation,
        $appliedRole,
        $experienceYears,
        $expectedSalary,
        $resumeFile,
        $internalNotes,
        $status,
        $employeeName,
        $portfolioUrl
    );
    
    if (mysqli_stmt_execute($stmt)) {
        $imported++;
    } else {
        $errors[] = "Row $rowNumber: Database error - " . mysqli_error($con);
    }
    mysqli_stmt_close($stmt);
}

fclose($handle);

$message = "Successfully imported $imported candidates.";
if (!empty($errors)) {
    $message .= " Errors in " . count($errors) . " rows.";
}

response(true, $message, ['imported' => $imported, 'errors' => $errors]);
?>