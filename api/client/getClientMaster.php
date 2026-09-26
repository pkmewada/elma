<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadEngine.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

requireApiPermission(['/client-onboarding', '/emp-client-onboarding'], 'canView');

$leadEngine = new LeadEngine($con);

$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

echo json_encode([
    'data' => $leadEngine->getClientMasterList($status, $search)
]);