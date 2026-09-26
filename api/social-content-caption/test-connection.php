<?php
/*
|--------------------------------------------------------------------------
| AI Configuration — Test Connection
|--------------------------------------------------------------------------
|
| Backs the "Test Connection" button on the AI Configuration page. Thin
| wrapper only -- calls CaptionGeneratorFactory::make()->testConnection()
| exactly the same way Caption Area calls ->generate(); no separate
| provider logic lives here.
|
*/
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/AI/CaptionGeneratorFactory.php';

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
    $result = CaptionGeneratorFactory::make()->testConnection();
    echo json_encode(['success' => (bool) $result['success'], 'message' => (string) $result['message']]);
} catch (Throwable $e) {
    // e.g. "Unknown AI caption provider configured: xyz" -- already a safe,
    // no-secret message, but never trust an unexpected exception either.
    echo json_encode(['success' => false, 'message' => 'Unable to test the AI provider connection.']);
}
