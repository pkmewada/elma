<?php
/*
|--------------------------------------------------------------------------
| Send to Automation — Phase 4.5
|--------------------------------------------------------------------------
|
| The one endpoint that triggers the Production Ready -> Automation
| handoff. Accepts ONLY productionId from the browser -- every other
| value used by the handoff (clientId, instagramAccountId, platformName,
| media path, caption, ...) is derived server-side inside
| SocialAutomationHandoffEngine, never trusted from the request.
|
| This file does not duplicate any eligibility/account/media/socialPosts
| logic -- it is a thin, permission- and CSRF-gated wrapper around the
| existing SocialAutomationHandoffEngine::resolveAndRegisterHandoff(),
| which already owns all of that (Phase 4.1-4.4). Mirrors the auth/CSRF
| pattern already used by manage-task.php in this same directory.
|
*/
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/permission-helper.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/SocialAutomationHandoffEngine.php';

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

// Same authority tier as Approve / Mark Ready on this route -- sending to
// Automation is gated by the existing canApprove permission, not a new
// permission type.
if (!hasRoutePermission('/social-content-production', 'canApprove')) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to send this task to Automation.']);
    exit;
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit;
    }

    // The ONLY value accepted from the browser. clientId, instagramAccountId,
    // platformName, media path, and caption are all resolved server-side by
    // the engine from the production record itself -- never from $input.
    $productionId = isset($input['productionId']) ? (int)$input['productionId'] : 0;

    if ($productionId <= 0) {
        echo json_encode(['success' => false, 'message' => 'A valid production task is required.']);
        exit;
    }

    $userId = (int)$_SESSION['userId'];
    $engine = new SocialAutomationHandoffEngine($con);
    $result = $engine->resolveAndRegisterHandoff($productionId, $userId);

    echo json_encode([
        'success' => (bool)$result['success'],
        'message' => (string)$result['message'],
        'data' => [
            'state' => $result['state'],
            'handoffId' => $result['handoffId'],
            'socialPostId' => $result['socialPostId'],
        ],
    ]);
} catch (Throwable $e) {
    // Never surface an internal exception/SQL message to the browser.
    echo json_encode(['success' => false, 'message' => 'Unable to send this task to Automation. Please try again.']);
}
