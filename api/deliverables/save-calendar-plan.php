<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/calendarEngine.php';

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
    if (!isset($input['client_id']) || !isset($input['plans'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data']);
        exit;
    }

    $clientId = (int)$input['client_id'];
    $plans = $input['plans'];

    // client existence check — never trust a client-supplied id without
    // verifying it against clientMaster first (mirrors generate-calendar-plan.php)
    $clientStmt = mysqli_prepare($con, 'SELECT id FROM clientMaster WHERE id = ?');
    mysqli_stmt_bind_param($clientStmt, 'i', $clientId);
    mysqli_stmt_execute($clientStmt);
    $clientFound = mysqli_stmt_get_result($clientStmt)->fetch_assoc();
    mysqli_stmt_close($clientStmt);
    if (!$clientFound) {
        echo json_encode(['success' => false, 'message' => 'Client not found.']);
        exit;
    }

    $engine = new CalendarEngine($con);
    $saved = $engine->saveCalendarPlan($clientId, $plans);

    echo json_encode([
        'success' => true,
        'message' => 'Calendar plan saved successfully'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}