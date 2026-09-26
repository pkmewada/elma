<?php
require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

header('Content-Type: application/json');

try {
    $editorId = (int)$_SESSION['candidateId'];
    $engine = new SocialContentProductionEngine($con);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $task = $engine->getTask($id);
        if (!$task || $task['assignedEditorId'] !== $editorId) {
            echo json_encode(['success' => false, 'message' => 'Task not found, or not assigned to you.']);
            exit;
        }
        echo json_encode(['success' => true, 'data' => $task]);
        exit;
    }

    $filters = [
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'fromDate' => isset($_GET['fromDate']) ? trim($_GET['fromDate']) : '',
        'toDate' => isset($_GET['toDate']) ? trim($_GET['toDate']) : '',
        'overdue' => isset($_GET['overdue']) && $_GET['overdue'] === '1',
    ];

    // editorId is always forced server-side — never taken from the request
    $tasks = $engine->listForEditor($editorId, $filters);
    echo json_encode(['success' => true, 'data' => $tasks]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
