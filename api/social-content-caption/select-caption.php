<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/socialContentCaptionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $productionId = isset($input['productionId']) ? (int)$input['productionId'] : 0;
    $option = isset($input['option']) ? trim((string)$input['option']) : '';
    $userId = (int)$_SESSION['userId'];

    $engine = new SocialContentCaptionEngine($con);
    $caption = $engine->selectCaption($productionId, $option, $userId);

    echo json_encode(['success' => true, 'data' => $caption]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
