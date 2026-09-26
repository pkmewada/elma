<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';
require_once __DIR__ . '/../../includes/leadActivityLogger.php';

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

try {
    requireValidCsrfToken();
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}

$postId = (int)($_POST['postId'] ?? 0);

if ($postId <= 0) {
    respond(false, 'Invalid social post.');
}

$post = getSocialPostById($con, $postId);

if (!$post) {
    respond(false, 'Social post not found.');
}

if (!in_array($post['status'], ['draft', 'scheduled', 'failed'], true)) {
    respond(false, 'Published or in-progress posts cannot be deleted.');
}

try {
    $ok = deleteSocialPostRecord($con, $postId);

    if ($ok) {
        $clientLabel = getInstagramClientLabel($con, $post['clientId'] !== null ? (int)$post['clientId'] : null);
        saveActivityLog($con, 'InstagramAutomation', $postId, 'delete', 'Deleted a social post for Client: ' . $clientLabel . '.');
    }

    respond($ok, $ok ? 'Social post deleted.' : 'Unable to delete social post.');
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
