<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function apiResponse(bool $success, string $message, int $code = 200, array $data = []): void
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);
    exit();
}

function getPayload(): array
{
    $rawInput = file_get_contents('php://input');
    $payload = json_decode((string) $rawInput, true);
    return is_array($payload) ? $payload : $_POST;
}

function buildAcknowledgmentLink(string $token): string
{
    return rtrim(BASE_URL, '/') . '/acknowledgment?token=' . urlencode($token);
}

if (empty($_SESSION['userId'])) {
    apiResponse(false, 'Unauthorized access.', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apiResponse(false, 'Method not allowed.', 405);
}

$payload = getPayload();
$id = (int) ($payload['id'] ?? 0);
$reviewStatus = trim((string) ($payload['reviewStatus'] ?? ''));
$reviewRemark = trim((string) ($payload['reviewRemark'] ?? ''));
$allowedReviewStatuses = ['inReview', 'verified', 'rejected'];

if ($id <= 0 || !in_array($reviewStatus, $allowedReviewStatuses, true)) {
    apiResponse(false, 'Invalid review status request.', 422);
}

$stmt = mysqli_prepare($con, "
    SELECT id, fullName, email, status, acknowledgmentStatus,
        acknowledgmentToken, appliedRole, finalSalary,
        joiningDate, acknowledgmentSubmittedAt, resubmissionCount, signatureFile
    FROM candidateRecord
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    apiResponse(false, 'Unable to fetch candidate.', 500);
}

mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$candidate = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$candidate) {
    apiResponse(false, 'Candidate not found.', 404);
}

if ((string) $candidate['status'] !== 'convert') {
    apiResponse(false, 'Only converted candidates are allowed in onboarding queue.', 422);
}

$ackStatus = (string) ($candidate['acknowledgmentStatus'] ?? 'pending');
$isAcknowledged = in_array($ackStatus, ['acknowledged', 'completed'], true);

if (!$isAcknowledged) {
    apiResponse(false, 'Waiting for candidate submission.', 423);
}

if ($reviewStatus === 'verified') {
    $verifyStmt = mysqli_prepare($con, "
        UPDATE candidateRecord
        SET reviewStatus = 'verified',
            reviewedAt = NOW(),
            updatedAt = NOW()
        WHERE id = ?
    ");

    if (!$verifyStmt) {
        apiResponse(false, 'Unable to verify candidate.', 500);
    }

    mysqli_stmt_bind_param($verifyStmt, 'i', $id);
    $ok = mysqli_stmt_execute($verifyStmt);
    mysqli_stmt_close($verifyStmt);

    if (!$ok) {
        apiResponse(false, 'Verification failed.', 500);
    }

    if (!empty($candidate['email'])) {

    $candidatePdfData = [
        'id' => (int) $candidate['id'],
        'fullName' => (string) $candidate['fullName'],
        'email' => (string) $candidate['email'],
        'appliedRole' => (string) $candidate['appliedRole'],
        'finalSalary' => (string) $candidate['finalSalary'],
        'joiningDate' => (string) $candidate['joiningDate'],
        'signatureFile' => (string) $candidate['signatureFile'],
        'acknowledgmentSubmittedAt' => (string) $candidate['acknowledgmentSubmittedAt']
    ];

    $pdfPath = generateCandidateAcknowledgmentPdf($candidatePdfData);

    sendOnboardingVerifiedEmail(
        (string) $candidate['email'],
        (string) $candidate['fullName'],
        (string) $candidate['appliedRole'],
        (string) $candidate['finalSalary'],
        (string) $candidate['joiningDate'],
        $pdfPath
    );
}

    apiResponse(true, 'Candidate verified successfully.', 200, [
        'id' => $id,
        'reviewStatus' => 'verified',
    ]);
}

if ($reviewStatus === 'rejected') {
    if ($reviewRemark === '') {
        apiResponse(false, 'Review remark is required.', 422);
    }

    $token = trim((string) ($candidate['acknowledgmentToken'] ?? ''));
    if ($token === '') {
        $token = bin2hex(random_bytes(32));
        $tokenStmt = mysqli_prepare($con, "UPDATE candidateRecord SET acknowledgmentToken = ? WHERE id = ?");
        if ($tokenStmt) {
            mysqli_stmt_bind_param($tokenStmt, 'si', $token, $id);
            mysqli_stmt_execute($tokenStmt);
            mysqli_stmt_close($tokenStmt);
        }
    }

    $rejectStmt = mysqli_prepare($con, "
        UPDATE candidateRecord
        SET acknowledgmentStatus = 'pending',
            acknowledgmentSubmittedAt = NULL,
            govtIdType = NULL,
            govtIdFile = NULL,
            signatureFile = NULL,
            reviewStatus = 'inReview',
            reviewRemark = NULL,
            reviewedAt = NULL,
            updatedAt = NOW(),
            resubmissionCount = COALESCE(resubmissionCount, 0) + 1
        WHERE id = ?
    ");

    if (!$rejectStmt) {
        apiResponse(false, 'Unable to reset candidate submission.', 500);
    }

    mysqli_stmt_bind_param($rejectStmt, 'i', $id);
    $ok = mysqli_stmt_execute($rejectStmt);
    mysqli_stmt_close($rejectStmt);

    if (!$ok) {
        apiResponse(false, 'Rejection reset failed.', 500);
    }

    if (!empty($candidate['email']) && $token !== '') {
        sendRejectionResubmissionEmail(
            (string) $candidate['email'],
            (string) $candidate['fullName'],
            $reviewRemark,
            buildAcknowledgmentLink($token)
        );
    }

    apiResponse(true, 'Candidate marked for resubmission.', 200, [
        'id' => $id,
        'reviewStatus' => 'inReview',
        'acknowledgmentStatus' => 'pending',
        'resubmissionCount' => ((int) $candidate['resubmissionCount']) + 1,
    ]);
}

$reviewStmt = mysqli_prepare($con, "
    UPDATE candidateRecord
    SET reviewStatus = ?,
        updatedAt = NOW()
    WHERE id = ?
");

if (!$reviewStmt) {
    apiResponse(false, 'Unable to update review status.', 500);
}

mysqli_stmt_bind_param($reviewStmt, 'si', $reviewStatus, $id);
$ok = mysqli_stmt_execute($reviewStmt);
mysqli_stmt_close($reviewStmt);

if (!$ok) {
    apiResponse(false, 'Failed to update review status.', 500);
}

apiResponse(true, 'Review status updated.', 200, [
    'id' => $id,
    'reviewStatus' => $reviewStatus,
]);