<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/otherGraphicContentEngine.php';

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

    $userId = (int)$_SESSION['userId'];
    $engine = new OtherGraphicContentEngine($con);

    // Standalone CRUD only, by business decision (2026-09-17): saving an
    // entry no longer creates/touches a socialContentProduction task.
    // SocialContentProductionEngine::createTaskForOther() and
    // getTaskByOtherContentId() are unchanged and still fully support the
    // handful of entries that already have a production task from before
    // this change -- those keep working in Production exactly as-is, this
    // endpoint just never calls either method anymore.
    $entry = $engine->saveEntry($input, $userId);
    $entry = $engine->completeEntry($entry['id'], $userId);

    echo json_encode(['success' => true, 'data' => $entry]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
