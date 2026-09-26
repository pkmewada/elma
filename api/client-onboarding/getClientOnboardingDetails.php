<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireApiPermission(['/client-onboarding', '/emp-client-onboarding'], 'canView');

$clientId = isset($_POST['clientId']) ? (int)$_POST['clientId'] : 0;

if (!$clientId) {
    echo json_encode(['success' => false, 'message' => 'Invalid client ID']);
    exit;
}

$leadEngine = new LeadEngine($con);
$details = $leadEngine->getClientOnboardingDetails($clientId);

echo json_encode(['success' => true, 'data' => $details]);