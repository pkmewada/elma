<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function jsonResponse(bool $success, string $message, int $statusCode = 200, array $data = []): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit();
}

function getRequestPayload(): array
{
    if (!empty($_FILES)) {
        return $_POST;
    }

    $rawInput = file_get_contents('php://input');
    $payload = json_decode((string) $rawInput, true);

    return is_array($payload) ? $payload : $_POST;
}

function getCandidateById(mysqli $con, int $id): ?array
{
    $stmt = mysqli_prepare($con, 'SELECT id, email, status FROM candidateRecord WHERE id = ? LIMIT 1');

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $candidate = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $candidate ?: null;
}

function buildAcknowledgmentLink(string $token): string
{
    return rtrim(BASE_URL, '/') . '/acknowledgment?token=' . urlencode($token);
}

if (empty($_SESSION['userId'])) {
    jsonResponse(false, 'Unauthorized access.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method not allowed.', 405);
}

$payload = getRequestPayload();

$id = (int) ($payload['id'] ?? 0);
$status = trim((string) ($payload['status'] ?? ''));
$allowedStatuses = ['open', 'interested', 'convert', 'in_progress', 'not_interested', 'walk_in', 'higher_budget'];
$lockedStatuses = ['convert', 'not_interested'];

if ($id <= 0 || !in_array($status, $allowedStatuses, true)) {
    jsonResponse(false, 'Invalid status update request.', 422);
}

$candidate = getCandidateById($con, $id);

if (!$candidate) {
    jsonResponse(false, 'Candidate not found.', 404);
}

if (in_array((string) $candidate['status'], $lockedStatuses, true)) {
    jsonResponse(false, 'Status is locked for this candidate.', 423, [
        'id' => $id,
        'status' => $candidate['status'],
    ]);
}

if ($status === 'not_interested') {
    $remark = trim((string) ($payload['remark'] ?? ''));

    if ($remark === '') {
        jsonResponse(false, 'Remark is required.', 422);
    }

    $stmt = mysqli_prepare($con, '
        UPDATE candidateRecord
        SET status = ?, internalNotes = ?
        WHERE id = ?
    ');

    if (!$stmt) {
        jsonResponse(false, 'DB error.', 500);
    }

    mysqli_stmt_bind_param($stmt, 'ssi', $status, $remark, $id);
    $updated = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    jsonResponse($updated, $updated ? 'Marked as not interested.' : 'Failed to update status.', $updated ? 200 : 500, [
        'id' => $id,
        'status' => $status,
    ]);
}

if ($status !== 'convert') {
    $stmt = mysqli_prepare($con, 'UPDATE candidateRecord SET status = ? WHERE id = ?');

    if (!$stmt) {
        jsonResponse(false, 'DB error.', 500);
    }

    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $updated = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    jsonResponse($updated, $updated ? 'Status updated successfully.' : 'Failed to update status.', $updated ? 200 : 500, [
        'id' => $id,
        'status' => $status,
    ]);
}

$remark = trim((string) ($payload['remark'] ?? ''));
$finalSalary = trim((string) ($payload['finalSalary'] ?? ''));
$joiningDate = trim((string) ($payload['joiningDate'] ?? ''));

if ($remark === '' || $finalSalary === '' || $joiningDate === '') {
    jsonResponse(false, 'Remark, salary, and joining date are required for conversion.', 422);
}

if (!isset($_FILES['cvFile']) || $_FILES['cvFile']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(false, 'CV file is required.', 422);
}

$fileTmpPath = $_FILES['cvFile']['tmp_name'];
$fileName = $_FILES['cvFile']['name'];
$fileSize = (int) $_FILES['cvFile']['size'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if ($fileExtension !== 'pdf') {
    jsonResponse(false, 'Only PDF files are allowed.', 422);
}

if ($fileSize > 5242880) {
    jsonResponse(false, 'Max file size is 5MB.', 422);
}

$uploadDir = __DIR__ . '/../../uploads/resumes/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$newFileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
$targetPath = $uploadDir . $newFileName;

if (!move_uploaded_file($fileTmpPath, $targetPath)) {
    jsonResponse(false, 'File upload failed.', 500);
}

$cvPath = UPLOAD_URL . '/resumes/' . $newFileName;
$token = bin2hex(random_bytes(32));

$stmt = mysqli_prepare($con, "
    UPDATE candidateRecord
    SET status = ?,
        remark = ?,
        finalSalary = ?,
        joiningDate = ?,
        cvFile = ?,
        reviewStatus = 'inReview',
        acknowledgmentToken = ?,
        acknowledgmentStatus = 'pending',
        acknowledgmentSubmittedAt = NULL,
        updatedAt = NOW()
    WHERE id = ?
");

if (!$stmt) {
    jsonResponse(false, 'DB prepare failed.', 500);
}

mysqli_stmt_bind_param($stmt, 'ssssssi', $status, $remark, $finalSalary, $joiningDate, $cvPath, $token, $id);
$updated = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$updated) {
    jsonResponse(false, 'Failed to convert candidate.', 500);
}

$mailSent = false;

if (!empty($candidate['email'])) {
    $mailSent = sendAcknowledgmentEmail((string) $candidate['email'], buildAcknowledgmentLink($token));
}

jsonResponse(true, $mailSent ? 'Candidate converted and acknowledgment email sent.' : 'Candidate converted, but acknowledgment email could not be sent.', 200, [
    'id' => $id,
    'status' => $status,
    'acknowledgmentStatus' => 'pending',
    'mailSent' => $mailSent,
]);
