<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';

// Check if it's a file upload or JSON request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES) && count($_FILES) > 0) {
    // Handle file upload submission
    $formId = isset($_POST['formId']) ? (int)$_POST['formId'] : 0;
    $token = isset($_POST['token']) ? $_POST['token'] : '';
    
    if (!$formId || !$token) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $leadEngine = new LeadEngine($con);
    $result = $leadEngine->submitClientFormDataWithFiles($_POST, $_FILES, $formId, $token);
    
    echo json_encode($result);
} else {
    // Handle JSON submission (existing)
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['token']) || !isset($input['formId'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $leadEngine = new LeadEngine($con);
    $result = $leadEngine->submitClientFormData($input);
    
    echo json_encode($result);
}