<?php
error_reporting(0);

header('Content-Type: application/json');

// Database connection
require_once __DIR__ . '/../../includes/db.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

const MAX_FILE_SIZE = 5242880; // 5MB
const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx'];
const ALLOWED_MIME = [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];
const PORTFOLIO_ROLES = ['Graphic Executive', 'Graphic Intern', 'Video Editor'];

// Fixed: Define UPLOAD_URL properly
if (!defined('UPLOAD_URL')) {
    define('UPLOAD_URL', 'https://modlus.in/uploads');
}

/*
|--------------------------------------------------------------------------
| Only POST Allowed
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method Not Allowed'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function response(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

function clean(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function isValidMobile(string $mobile): bool
{
    return preg_match('/^[6-9][0-9]{9}$/', $mobile) === 1;
}

function randomFileName(string $extension): string
{
    return bin2hex(random_bytes(16)) . '.' . $extension;
}

function uploadResume(array $file): string
{
    // Check for upload errors
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid upload.');
    }

    // Handle upload error codes
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('Resume size exceeds 5MB.');
        case UPLOAD_ERR_PARTIAL:
            throw new Exception('File was only partially uploaded.');
        case UPLOAD_ERR_NO_FILE:
            throw new Exception('No file was uploaded.');
        case UPLOAD_ERR_NO_TMP_DIR:
            throw new Exception('Missing temporary folder.');
        case UPLOAD_ERR_CANT_WRITE:
            throw new Exception('Failed to write file to disk.');
        case UPLOAD_ERR_EXTENSION:
            throw new Exception('File upload stopped by extension.');
        default:
            throw new Exception('Unknown upload error.');
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        throw new Exception('Resume size exceeds 5MB.');
    }

    // Validate file size is not zero
    if ($file['size'] === 0) {
        throw new Exception('Uploaded file is empty.');
    }

    // Get file extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, ALLOWED_EXTENSIONS, true)) {
        throw new Exception('Only PDF, DOC and DOCX are allowed.');
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new Exception('Unable to detect file type.');
    }
    
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, ALLOWED_MIME, true)) {
        throw new Exception('Invalid resume file type.');
    }

    // Create upload directory if it doesn't exist
    $uploadDir = __DIR__ . '/../../uploads/resumes/';
    
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Unable to create upload directory.');
        }
    }

    // Check if directory is writable
    if (!is_writable($uploadDir)) {
        throw new Exception('Upload directory is not writable.');
    }

    // Generate unique filename and move file
    $fileName = randomFileName($extension);
    $destination = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Unable to upload resume.');
    }

    return UPLOAD_URL . '/resumes/' . $fileName;
}

/*
|--------------------------------------------------------------------------
| Honeypot Protection
|--------------------------------------------------------------------------
*/

$website = clean($_POST['website'] ?? '');

if ($website !== '') {
    response(false, 'Spam detected.', [], 403);
}

/*
|--------------------------------------------------------------------------
| Form Submission Time
|--------------------------------------------------------------------------
*/

$formLoadedAt = (int)($_POST['formLoadedAt'] ?? 0);

if ($formLoadedAt > 0) {
    $seconds = (time() * 1000 - $formLoadedAt) / 1000;
    if ($seconds < 5) {
        response(false, 'Form submitted too quickly.', [], 429);
    }
}

/*
|--------------------------------------------------------------------------
| Collect Request Data
|--------------------------------------------------------------------------
*/

$fullName = clean($_POST['fullName'] ?? '');
$email = strtolower(clean($_POST['email'] ?? ''));
$phoneNumber = clean($_POST['phoneNumber'] ?? '');
$currentLocation = clean($_POST['currentLocation'] ?? '');
$appliedRole = clean($_POST['appliedRole'] ?? '');
$experienceYears = (int)($_POST['experienceYears'] ?? 0);
$expectedSalary = clean($_POST['expectedSalary'] ?? '');
$internalNotes = clean($_POST['internalNotes'] ?? '');
$portfolioUrl = trim($_POST['portfolioUrl'] ?? '');

$status = 'Open';
$employeeName = 'Career Portal';

/*
|--------------------------------------------------------------------------
| Required Validation
|--------------------------------------------------------------------------
*/

if ($fullName === '' || $email === '' || $phoneNumber === '' || 
    $currentLocation === '' || $appliedRole === '' || $expectedSalary === '') {
    response(false, 'Please fill all required fields.', [], 422);
}

/*
|--------------------------------------------------------------------------
| Name Validation
|--------------------------------------------------------------------------
*/

if (strlen($fullName) < 3 || strlen($fullName) > 100) {
    response(false, 'Please enter a valid name (3-100 characters).', [], 422);
}

if (!preg_match("/^[A-Za-z .'-]+$/", $fullName)) {
    response(false, 'Name contains invalid characters.', [], 422);
}

/*
|--------------------------------------------------------------------------
| Email Validation
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    response(false, 'Invalid email address.', [], 422);
}

/*
|--------------------------------------------------------------------------
| Mobile Validation
|--------------------------------------------------------------------------
*/

if (!isValidMobile($phoneNumber)) {
    response(false, 'Invalid mobile number. Must be a 10-digit Indian number starting with 6-9.', [], 422);
}

/*
|--------------------------------------------------------------------------
| Experience Validation
|--------------------------------------------------------------------------
*/

if ($experienceYears < 0 || $experienceYears > 50) {
    response(false, 'Invalid experience selected (0-50 years).', [], 422);
}

/*
|--------------------------------------------------------------------------
| Salary Validation
|--------------------------------------------------------------------------
*/

if (strlen($expectedSalary) > 50) {
    response(false, 'Invalid expected salary.', [], 422);
}

/*
|--------------------------------------------------------------------------
| Portfolio Validation
|--------------------------------------------------------------------------
*/

if (in_array($appliedRole, PORTFOLIO_ROLES, true)) {
    if ($portfolioUrl === '') {
        response(false, 'Portfolio URL is required for this role.', [], 422);
    }

    if (!filter_var($portfolioUrl, FILTER_VALIDATE_URL)) {
        response(false, 'Please enter a valid portfolio URL.', [], 422);
    }
} else {
    $portfolioUrl = '';
}

/*
|--------------------------------------------------------------------------
| Check Database Connection
|--------------------------------------------------------------------------
*/

if (!isset($con) || !$con) {
    response(false, 'Database connection failed.', [], 500);
}

/*
|--------------------------------------------------------------------------
| Duplicate Email Check
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare($con, "SELECT id FROM candidateRecord WHERE email = ? LIMIT 1");

if (!$stmt) {
    response(false, 'Database error: ' . mysqli_error($con), [], 500);
}

mysqli_stmt_bind_param($stmt, "s", $email);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    mysqli_stmt_close($stmt);
    response(false, 'You have already applied using this email address.', [], 409);
}
mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Duplicate Mobile Check
|--------------------------------------------------------------------------
*/

$stmt = mysqli_prepare($con, "SELECT id FROM candidateRecord WHERE phoneNumber = ? LIMIT 1");

if (!$stmt) {
    response(false, 'Database error: ' . mysqli_error($con), [], 500);
}

mysqli_stmt_bind_param($stmt, "s", $phoneNumber);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) > 0) {
    mysqli_stmt_close($stmt);
    response(false, 'You have already applied using this mobile number.', [], 409);
}
mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| Resume Upload
|--------------------------------------------------------------------------
*/

if (!isset($_FILES['resumeFile']) || $_FILES['resumeFile']['error'] === UPLOAD_ERR_NO_FILE) {
    response(false, 'Resume is required.', [], 422);
}

try {
    $resumeFile = uploadResume($_FILES['resumeFile']);
} catch (Exception $e) {
    response(false, $e->getMessage(), [], 422);
}

/*
|--------------------------------------------------------------------------
| Insert Candidate
|--------------------------------------------------------------------------
*/

$insertQuery = "
INSERT INTO candidateRecord
(
    fullName, email, phoneNumber, currentLocation, 
    appliedRole, experienceYears, expectedSalary, 
    resumeFile, internalNotes, status, employeeName, portfolioUrl
)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
";

$stmt = mysqli_prepare($con, $insertQuery);

if (!$stmt) {
    response(false, 'Unable to prepare database query: ' . mysqli_error($con), [], 500);
}

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

$insert = mysqli_stmt_execute($stmt);

if (!$insert) {
    $error = mysqli_error($con);
    mysqli_stmt_close($stmt);
    response(false, 'Unable to save candidate: ' . $error, [], 500);
}

$newCandidateId = mysqli_insert_id($con);
mysqli_stmt_close($stmt);

if ($newCandidateId === 0) {
    response(false, 'Failed to create candidate record.', [], 500);
}

/*
|--------------------------------------------------------------------------
| Load Inserted Candidate
|--------------------------------------------------------------------------
*/

$selectQuery = "
SELECT id, fullName, email, phoneNumber, currentLocation,
       appliedRole, experienceYears, expectedSalary, resumeFile,
       portfolioUrl, internalNotes, status, employeeName, createdAt
FROM candidateRecord
WHERE id = ?
LIMIT 1
";

$stmt = mysqli_prepare($con, $selectQuery);

if (!$stmt) {
    response(false, 'Unable to fetch candidate: ' . mysqli_error($con), [], 500);
}

mysqli_stmt_bind_param($stmt, "i", $newCandidateId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$candidate = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$candidate) {
    response(false, 'Candidate saved but unable to fetch record.', [], 500);
}

/*
|--------------------------------------------------------------------------
| Success Response
|--------------------------------------------------------------------------
*/

response(
    true,
    'Application submitted successfully.',
    [
        'id' => (int)$candidate['id'],
        'fullName' => $candidate['fullName'],
        'email' => $candidate['email'],
        'phoneNumber' => $candidate['phoneNumber'],
        'currentLocation' => $candidate['currentLocation'],
        'appliedRole' => $candidate['appliedRole'],
        'experienceYears' => (int)$candidate['experienceYears'],
        'expectedSalary' => $candidate['expectedSalary'],
        'resumeFile' => $candidate['resumeFile'],
        'portfolioUrl' => $candidate['portfolioUrl'],
        'internalNotes' => $candidate['internalNotes'],
        'status' => $candidate['status'],
        'employeeName' => $candidate['employeeName'],
        'createdDate' => date('d M Y h:i A', strtotime($candidate['createdAt']))
    ]
);

/*
|--------------------------------------------------------------------------
| END OF FILE
|--------------------------------------------------------------------------
*/
?>