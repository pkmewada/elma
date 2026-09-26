<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';

// ============================================
// DEBUG - Log everything
// ============================================
error_log("=== uploadClientFiles.php CALLED ===");
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

$formId = isset($_POST['formId']) ? (int)$_POST['formId'] : 0;
$token = isset($_POST['token']) ? $_POST['token'] : '';

error_log("formId: " . $formId);
error_log("token: " . $token);

if (!$formId || !$token) {
    error_log("ERROR: Missing formId or token");
    echo json_encode(['success' => false, 'message' => 'Missing formId or token']);
    exit;
}

if (empty($_FILES)) {
    error_log("ERROR: No files uploaded");
    echo json_encode(['success' => false, 'message' => 'No files uploaded']);
    exit;
}

$leadEngine = new LeadEngine($con);
$result = $leadEngine->uploadClientFiles($formId, $token, $_FILES);

error_log("Result: " . print_r($result, true));
echo json_encode($result);