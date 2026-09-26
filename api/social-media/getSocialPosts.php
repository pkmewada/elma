<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ]);

    exit;
}

$status = trim((string)($_GET['status'] ?? ''));
$allowedStatuses = ['draft', 'scheduled', 'publishing', 'published', 'partial', 'failed'];

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$clientId = (int)($_GET['clientId'] ?? 0) ?: null;

try {
    respond(true, 'Social posts loaded successfully.', [
        'posts' => getSocialPosts($con, $status, $clientId),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
