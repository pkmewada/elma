<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.',
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit();
}

$fullName = trim((string) ($_POST['fullName'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phoneNumber = trim((string) ($_POST['phoneNumber'] ?? ''));
$currentLocation = trim((string) ($_POST['currentLocation'] ?? ''));
$appliedRole = trim((string) ($_POST['appliedRole'] ?? ''));
$experienceYears = (int) ($_POST['experienceYears'] ?? 0);
$expectedSalary = trim((string) ($_POST['expectedSalary'] ?? ''));
$internalNotes = trim((string) ($_POST['internalNotes'] ?? ''));
$status = 'open';
$employeeName = 'Admin'; // For now, hardcoded

// Handle file upload
$resumeFile = '';
if (isset($_FILES['resumeFile']) && $_FILES['resumeFile']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['resumeFile']['tmp_name'];
    $fileName = $_FILES['resumeFile']['name'];
    $fileSize = $_FILES['resumeFile']['size'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    // Documents keep the existing extension-only check. Images additionally
    // get a real MIME check (same finfo pattern already used for profile
    // photo uploads) so a renamed file can't pass as an image resume.
    $allowedDocumentExtensions = ['pdf', 'doc', 'docx'];
    $allowedImageMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    $isValidFile = false;
    if (in_array($fileExtension, $allowedDocumentExtensions, true)) {
        $isValidFile = true;
    } elseif (in_array($fileExtension, $allowedImageExtensions, true)) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $fileTmpPath);
        finfo_close($fileInfo);
        $isValidFile = isset($allowedImageMimeTypes[$mimeType]);
    }

    if ($isValidFile && $fileSize <= 5242880) { // 5MB
        $uploadFileDir = __DIR__ . '/../../uploads/resumes/';
        if (!is_dir($uploadFileDir)) {
            mkdir($uploadFileDir, 0755, true);
        }
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $dest_path = $uploadFileDir . $newFileName;

        if (move_uploaded_file($fileTmpPath, $dest_path)) {
            $resumeFile = UPLOAD_URL . '/resumes/' . $newFileName;
        }
    }
}

if ($fullName === '' || $email === '' || $phoneNumber === '' || $currentLocation === '' || $appliedRole === '' || $expectedSalary === '' || $resumeFile === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'All required fields must be filled, including resume upload.',
    ]);
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid email address.',
    ]);
    exit();
}

if (!preg_match('/^[0-9]{10}$/', $phoneNumber)) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please enter a valid 10-digit phone number.',
    ]);
    exit();
}

$insertStmt = mysqli_prepare($con, 'INSERT INTO candidateRecord (fullName, email, phoneNumber, currentLocation, appliedRole, experienceYears, expectedSalary, resumeFile, internalNotes, status, employeeName) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

if (!$insertStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add candidate.',
    ]);
    exit();
}

mysqli_stmt_bind_param($insertStmt, 'sssssisssss', $fullName, $email, $phoneNumber, $currentLocation, $appliedRole, $experienceYears, $expectedSalary, $resumeFile, $internalNotes, $status, $employeeName);
$inserted = mysqli_stmt_execute($insertStmt);
$newCandidateId = mysqli_insert_id($con);
mysqli_stmt_close($insertStmt);

if (!$inserted || $newCandidateId <= 0) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to add candidate.',
    ]);
    exit();
}

$selectStmt = mysqli_prepare($con, 'SELECT id, fullName, email, phoneNumber, currentLocation, appliedRole, experienceYears, expectedSalary, resumeFile, internalNotes, status, createdAt, employeeName FROM candidateRecord WHERE id = ? LIMIT 1');

if (!$selectStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Candidate added, but failed to load candidate details.',
    ]);
    exit();
}

mysqli_stmt_bind_param($selectStmt, 'i', $newCandidateId);
mysqli_stmt_execute($selectStmt);
$result = mysqli_stmt_get_result($selectStmt);
$candidate = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($selectStmt);

if (!$candidate) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Candidate added, but failed to load candidate details.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Candidate added successfully',
    'data' => [
        'id' => (int) $candidate['id'],
        'fullName' => $candidate['fullName'],
        'email' => $candidate['email'],
        'phoneNumber' => $candidate['phoneNumber'],
        'currentLocation' => $candidate['currentLocation'],
        'appliedRole' => $candidate['appliedRole'],
        'experienceYears' => (int) $candidate['experienceYears'],
        'expectedSalary' => $candidate['expectedSalary'],
        'resumeFile' => $candidate['resumeFile'],
        'internalNotes' => $candidate['internalNotes'],
        'status' => $candidate['status'],
        'createdDate' => date('d M Y h:i A', strtotime((string) $candidate['createdAt'])),
        'employeeName' => $candidate['employeeName'],
    ],
]);
?>