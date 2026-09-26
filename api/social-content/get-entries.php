<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/socialContentEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $clientId = isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0;
    $month = isset($_GET['month']) ? trim($_GET['month']) : '';

    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo json_encode(['success' => false, 'message' => 'Invalid month']);
        exit;
    }

    $engine = new SocialContentEngine($con);
    $entries = $engine->getEntries($clientId, $month);

    echo json_encode(['success' => true, 'data' => $entries]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
