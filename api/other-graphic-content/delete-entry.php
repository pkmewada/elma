<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/otherGraphicContentEngine.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

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
    $id = isset($input['id']) ? (int)$input['id'] : 0;

    // Same production-dependency guard as api/social-content/delete-entry.php:
    // otherGraphicContent -> socialContentProduction cascades on delete, so
    // once a task has moved past NEW, deleting the source here would
    // silently destroy real production history/work.
    $productionEngine = new SocialContentProductionEngine($con);
    $task = $productionEngine->getTaskByOtherContentId($id);
    if ($task && $task['status'] !== 'NEW') {
        throw new Exception('This entry is already in production (status: ' . $task['status'] . ') and cannot be cleared here. Resolve the production task first.');
    }

    $engine = new OtherGraphicContentEngine($con);
    $engine->deleteEntry($id);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
