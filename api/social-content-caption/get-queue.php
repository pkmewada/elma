<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/socialContentCaptionEngine.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Same read-only attachment api/social-content-production/get-tasks.php
// already does — Caption Area needs to know whether a task has already
// been handed off to Automation (any socialContentAutomationHandoff row)
// so it can show "Sent To Automation" instead of a Send button that would
// just be rejected by the existing ALREADY_HANDED_OFF check. No new table,
// no new status; a plain read of the same handoff table.
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
    $filters = [
        'clientId' => isset($_GET['clientId']) ? (int)$_GET['clientId'] : 0,
        'platformId' => isset($_GET['platformId']) ? (int)$_GET['platformId'] : 0,
        'fromDate' => isset($_GET['fromDate']) ? trim($_GET['fromDate']) : '',
        'toDate' => isset($_GET['toDate']) ? trim($_GET['toDate']) : '',
    ];

    $engine = new SocialContentCaptionEngine($con);
    $queue = $engine->getQueue($filters);
    $queue = attachAutomationStatus($con, $queue);

    echo json_encode(['success' => true, 'data' => $queue]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
