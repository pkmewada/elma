<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireApiPermission(['/client-onboarding', '/emp-client-onboarding'], 'canEdit');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['clientId'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$leadEngine = new LeadEngine($con);
$result = $leadEngine->saveOnboardingForm($input, getLoggedInUserId());

// Get the form ID
if ($result['success']) {
    $clientId = (int)$input['clientId'];
    $stmt = mysqli_prepare($con, "
        SELECT id FROM clientOnboardingForms 
        WHERE clientMasterId = ? 
        ORDER BY id DESC LIMIT 1
    ");
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $resultSet = mysqli_stmt_get_result($stmt);
        $form = mysqli_fetch_assoc($resultSet);
        if ($form) {
            $result['formId'] = (int)$form['id'];
        }
        mysqli_stmt_close($stmt);
    }
}

echo json_encode($result);