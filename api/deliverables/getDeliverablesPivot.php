<?php
/**
 * API: Get Deliverables Pivot Data
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/deliverableEngine.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $clientId = isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0;
    $month = isset($_GET['month']) ? trim($_GET['month']) : '';
    
    $engine = new DeliverableEngine($con);
    
    // Validate month
    if (!empty($month) && !$engine->validateMonth($month)) {
        echo json_encode(['success' => false, 'message' => 'Invalid month format']);
        exit;
    }
    
    $data = $engine->getDeliverables($clientId, $month);
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'totalClients' => count($data),
            'month' => $month
        ]
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}