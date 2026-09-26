<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_POST['token'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$token = trim($_POST['token']);

$leadEngine = new LeadEngine($con);

$agreement = $leadEngine->getAgreementByToken($token);

if (!$agreement) {
    echo json_encode([
        'success' => false,
        'message' => 'Agreement not found.'
    ]);
    exit;
}

$agreementId = (int)$agreement['id'];

// Validate required fields
$signatoryName = trim($_POST['signatoryName'] ?? '');
$signatureData = $_POST['signature'] ?? '';

if (empty($signatoryName) || empty($signatureData)) {
    echo json_encode([
        'success' => false,
        'message' => 'Signatory name and signature are required.'
    ]);
    exit;
}

if (!isset($_FILES['businessDocument']) || $_FILES['businessDocument']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode([
        'success' => false,
        'message' => 'Business document upload failed.'
    ]);
    exit;
}

// Handle file upload
$uploadDir = __DIR__ . '/../../uploads/onboarding/documents/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$businessFile = $_FILES['businessDocument'];
$businessFileName = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $businessFile['name']);
$businessFilePath = $uploadDir . $businessFileName;

if (!move_uploaded_file($businessFile['tmp_name'], $businessFilePath)) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save business document.'
    ]);
    exit;
}

// Handle signature (Base64 -> PNG)
$signatureDir = __DIR__ . '/../../uploads/onboarding/signatures/';
if (!is_dir($signatureDir)) mkdir($signatureDir, 0755, true);

$signatureData = preg_replace('/^data:image\/png;base64,/', '', $signatureData);
$signatureData = str_replace(' ', '+', $signatureData);
$signatureFileName = 'sign_' . time() . '.png';
$signatureFilePath = $signatureDir . $signatureFileName;

if (!file_put_contents($signatureFilePath, base64_decode($signatureData))) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save signature.'
    ]);
    exit;
}

// Save submission in database
$submissionSaved = $leadEngine->saveAgreementSubmission(
    $agreementId,
    $signatoryName,
    $businessFileName,
    $signatureFileName
);

if (!$submissionSaved) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to save submission.'
    ]);
    exit;
}

// Mark agreement submitted
$leadEngine->markAgreementSubmitted($agreementId);

echo json_encode([
    'success' => true,
    'message' => 'Agreement submitted successfully.'
]);