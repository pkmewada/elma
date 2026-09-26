<?php
/*
|--------------------------------------------------------------------------
| Generate Calendar Plan — PREVIEW ONLY
|--------------------------------------------------------------------------
|
| Computes suggested calendar dates via SocialCalendarPlanningEngine and
| returns them. Does NOT write to clientCalendarPlans — persistence stays
| exclusively CalendarEngine::saveCalendarPlan()'s job (existing "Save Plan"
| action), called separately once a manager has reviewed/adjusted the
| suggestion returned here.
|
| Accepts either a single client:
|   { "clientId": 12, "month": "2026-09" }
| or a batch:
|   { "clientIds": [12, 18, 25], "month": "2026-09" }
*/

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/socialCalendarPlanningEngine.php';

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
        $input = [];
    }

    $month = isset($input['month']) ? trim($input['month']) : '';
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing month. Expected YYYY-MM']);
        exit;
    }

    $clientIds = [];
    if (isset($input['clientIds']) && is_array($input['clientIds'])) {
        $clientIds = $input['clientIds'];
    } elseif (isset($input['clientId'])) {
        $clientIds = [$input['clientId']];
    }

    $clientIds = array_values(array_unique(array_filter(array_map('intval', $clientIds), function ($id) {
        return $id > 0;
    })));

    if (empty($clientIds)) {
        echo json_encode(['success' => false, 'message' => 'At least one valid clientId is required']);
        exit;
    }

    // client existence check — never trust a client-supplied id without
    // verifying it against clientMaster first
    $placeholders = implode(',', array_fill(0, count($clientIds), '?'));
    $types = str_repeat('i', count($clientIds));
    $stmt = mysqli_prepare($con, "SELECT id FROM clientMaster WHERE id IN ($placeholders)");
    mysqli_stmt_bind_param($stmt, $types, ...$clientIds);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $foundIds = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $foundIds[] = (int)$row['id'];
    }
    mysqli_stmt_close($stmt);

    $missingIds = array_values(array_diff($clientIds, $foundIds));
    if (!empty($missingIds)) {
        echo json_encode(['success' => false, 'message' => 'Unknown clientId(s): ' . implode(', ', $missingIds)]);
        exit;
    }

    $engine = new SocialCalendarPlanningEngine($con);
    $plans = $engine->generatePlans($clientIds, $month);

    // single-client requests keep the flatter historical response shape;
    // batch requests get the full per-client array
    if (isset($input['clientId']) && !isset($input['clientIds'])) {
        echo json_encode(['success' => true, 'data' => $plans[0] ?? ['clientId' => $clientIds[0], 'month' => $month, 'plans' => []]]);
    } else {
        echo json_encode(['success' => true, 'data' => ['month' => $month, 'clients' => $plans]]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
