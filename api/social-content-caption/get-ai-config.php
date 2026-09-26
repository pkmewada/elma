<?php
/*
|--------------------------------------------------------------------------
| AI Configuration — read current settings (safe shape only)
|--------------------------------------------------------------------------
|
| Backs the AI Configuration page's form population. Never returns the
| API key itself, only hasAnthropicApiKey -- see
| includes/AiCaptionConfig.php's getAiCaptionConfigForDisplay().
|
*/
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/AiCaptionConfig.php';

if (!isset($_SESSION['userId'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    echo json_encode(['success' => true, 'data' => getAiCaptionConfigForDisplay()]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to load AI configuration.']);
}
