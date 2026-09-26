<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/graphicContentEngine.php';
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
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $userId = (int)$_SESSION['userId'];
    $engine = new GraphicContentEngine($con);
    $productionEngine = new SocialContentProductionEngine($con);

    // Editing an entry once its production task has moved past NEW would
    // silently rewrite the brief out from under a video editor who has
    // already started or submitted work -- same guard delete-entry.php
    // applies, just checked on edit here instead of on delete. Only
    // applies to edits (an id was supplied); creating a brand-new entry
    // has no production task yet.
    $editingId = isset($input['id']) ? (int)$input['id'] : 0;
    if ($editingId > 0) {
        $existingTask = $productionEngine->getTaskByGraphicContentId($editingId);
        if ($existingTask && $existingTask['status'] !== 'NEW') {
            throw new Exception('This entry is already in production (status: ' . $existingTask['status'] . ') and cannot be edited here. Resolve the production task first.');
        }
    }

    // Same "save -> complete -> hand off to Production" pattern
    // clientSocialContent/otherGraphicContent both established, in one
    // transaction, reusing the exact same production engine
    // (createTaskForGraphic() -- the only difference is which source table
    // it points at; every workflow step downstream of task creation is
    // identical).
    mysqli_begin_transaction($con);
    try {
        $entry = $engine->saveEntry($input, $userId);
        $entry = $engine->completeEntry($entry['id'], $userId);

        $task = $productionEngine->getTaskByGraphicContentId($entry['id']);
        if (!$task) {
            $productionEngine->createTaskForGraphic($entry['id'], $entry['deadlineAt'], $userId, 'admin', 'Auto-created on save.');
        }

        mysqli_commit($con);
    } catch (Exception $e) {
        mysqli_rollback($con);
        throw $e;
    }

    echo json_encode(['success' => true, 'data' => $entry]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
