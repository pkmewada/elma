<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/SocialContentProductionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Phase 4.5: attaches each task's existing socialContentAutomationHandoff
// status (if any) so the UI can show "Sent to Automation" / a failed/pending
// state instead of the action button, even after a page reload. Read-only,
// additive to this API's response shape only -- does not touch
// SocialContentProductionEngine.php, and does not re-derive or duplicate
// any eligibility logic (SocialAutomationHandoffEngine remains the sole
// authority on whether a task IS eligible to be sent).
function attachAutomationStatus(mysqli $con, array $tasks): array
{
    $ids = array_column($tasks, 'id');
    if (empty($ids)) {
        return $tasks;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $types = str_repeat('i', count($ids));
    $stmt = mysqli_prepare(
        $con,
        "SELECT productionId, status, socialPostId, errorMessage
         FROM socialContentAutomationHandoff
         WHERE productionId IN ($placeholders)"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$ids);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $byProductionId = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $byProductionId[(int)$row['productionId']] = $row;
    }
    mysqli_stmt_close($stmt);

    foreach ($tasks as &$task) {
        $handoff = $byProductionId[(int)$task['id']] ?? null;
        $task['automationStatus'] = $handoff ? $handoff['status'] : null;
        $task['automationSocialPostId'] = $handoff && $handoff['socialPostId'] !== null ? (int)$handoff['socialPostId'] : null;
        $task['automationErrorMessage'] = $handoff ? $handoff['errorMessage'] : null;
    }
    unset($task);

    return $tasks;
}

try {
    $engine = new SocialContentProductionEngine($con);

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id > 0) {
        $task = $engine->getTask($id);
        if (!$task) {
            echo json_encode(['success' => false, 'message' => 'Production task not found.']);
            exit;
        }
        [$task] = attachAutomationStatus($con, [$task]);
        echo json_encode(['success' => true, 'data' => $task]);
        exit;
    }

    $filters = [
        'status' => isset($_GET['status']) ? trim($_GET['status']) : '',
        'editorId' => isset($_GET['editorId']) ? (int)$_GET['editorId'] : 0,
        'clientId' => isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0,
        'platformId' => isset($_GET['platformId']) ? (int)$_GET['platformId'] : 0,
        'source' => isset($_GET['source']) ? trim($_GET['source']) : '',
        'fromDate' => isset($_GET['fromDate']) ? trim($_GET['fromDate']) : '',
        'toDate' => isset($_GET['toDate']) ? trim($_GET['toDate']) : '',
        'overdue' => isset($_GET['overdue']) && $_GET['overdue'] === '1',
    ];

    $tasks = $engine->listForManager($filters);
    $tasks = attachAutomationStatus($con, $tasks);
    echo json_encode(['success' => true, 'data' => $tasks]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
