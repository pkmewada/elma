<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireApiPermission(['/client-onboarding', '/emp-client-onboarding'], 'canEdit');

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['formId'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit;
}

$leadEngine = new LeadEngine($con);
$result = $leadEngine->sendOnboardingFormToClient($input['formId'], getLoggedInUserId());

echo json_encode($result);