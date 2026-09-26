<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/Csrf.php';
require_once __DIR__ . '/../../includes/InstagramAutomation.php';
require_once __DIR__ . '/../../includes/InstagramComments.php';
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

$commentId = (int)($_POST['commentId'] ?? 0);
$hide = (string)($_POST['hide'] ?? '1') !== '0';

if ($commentId <= 0) {
    respond(false, 'Invalid comment.');
}

$comment = getInstagramCommentById($con, $commentId);

if (!$comment) {
    respond(false, 'Comment not found.');
}

$account = getInstagramAccountById($con, (int)$comment['instagramAccountId']);

if (!$account) {
    respond(false, 'The Instagram account for this comment is no longer connected.');
}

try {
    hideInstagramComment($account, $comment['instagramCommentId'], $hide);
    markInstagramCommentHidden($con, $commentId, $hide);

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    saveActivityLog(
        $con,
        'InstagramAutomation',
        $commentId,
        $hide ? 'hide' : 'unhide',
        ($hide ? 'Hid' : 'Unhid') . ' an Instagram comment for Client: ' . $clientLabel . '.'
    );

    respond(true, $hide ? 'Comment hidden.' : 'Comment unhidden.', [
        'comment' => getInstagramCommentById($con, $commentId),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
