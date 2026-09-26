<?php
/*
|--------------------------------------------------------------------------
| AI Configuration — save settings
|--------------------------------------------------------------------------
|
| Thin wrapper around includes/AiCaptionConfig.php's saveAiCaptionConfig() —
| all validation/encryption/persistence logic lives there, not here. A
| blank anthropicApiKey means "keep the currently stored key" (never
| revealed back to the browser to be round-tripped).
|
*/
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/AiCaptionConfig.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

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
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
        exit;
    }

    $userId = (int) $_SESSION['userId'];
    $data = saveAiCaptionConfig(
        [
            'provider' => $input['provider'] ?? '',
            'anthropicApiKey' => $input['anthropicApiKey'] ?? '',
            'anthropicModel' => $input['anthropicModel'] ?? '',
            'ollamaHost' => $input['ollamaHost'] ?? '',
            'ollamaModel' => $input['ollamaModel'] ?? '',
        ],
        $userId
    );

    // Never logs the key itself, or even whether one was submitted this
    // call -- only that a save happened and which provider is now active.
    saveActivityLog($con, 'AiCaptionConfig', 0, 'update', 'Updated AI Configuration (provider: ' . $data['provider'] . ').');

    echo json_encode(['success' => true, 'message' => 'AI configuration saved.', 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to save AI configuration.']);
}
