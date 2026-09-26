<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/socialContentCaptionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $productionId = isset($_GET['productionId']) ? (int)$_GET['productionId'] : 0;
    if ($productionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'A valid production task is required.']);
        exit;
    }

    $engine = new SocialContentCaptionEngine($con);
    $task = $engine->getTaskWithCaption($productionId);

    echo json_encode(['success' => true, 'data' => $task]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
