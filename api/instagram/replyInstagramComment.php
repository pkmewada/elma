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
$message = trim((string)($_POST['message'] ?? ''));

if ($commentId <= 0) {
    respond(false, 'Invalid comment.');
}

if ($message === '' || strlen($message) > 2200) {
    respond(false, 'Please enter a reply between 1 and 2200 characters.');
}

$comment = getInstagramCommentById($con, $commentId);

if (!$comment) {
    respond(false, 'Comment not found.');
}

// The account used to reply is always the one the comment itself belongs
// to (never client-submitted) — this is what prevents replying to a
// comment through the wrong Instagram identity.
$account = getInstagramAccountById($con, (int)$comment['instagramAccountId']);

if (!$account) {
    respond(false, 'The Instagram account for this comment is no longer connected.');
}

try {
    replyToInstagramComment($account, $comment['instagramCommentId'], $message);
    markInstagramCommentReplied($con, $commentId);

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);
    saveActivityLog(
        $con,
        'InstagramAutomation',
        $commentId,
        'reply',
        'Replied to an Instagram comment for Client: ' . $clientLabel . '.'
    );

    respond(true, 'Reply sent.', [
        'comment' => getInstagramCommentById($con, $commentId),
    ]);
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
