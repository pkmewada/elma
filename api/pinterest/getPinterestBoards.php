<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/PinterestAutomation.php';

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

$clientId = (int)($_GET['clientId'] ?? 0);

if ($clientId <= 0) {
    respond(false, 'Please select a client.');
}

$account = getPinterestAccountByClientId($con, $clientId);

if (!$account) {
    respond(false, 'No connected Pinterest account for this client.');
}

try {
    $boards = fetchPinterestBoards($account['accessToken']);

    respond(true, 'Pinterest boards loaded successfully.', ['boards' => $boards]);
} catch (PinterestPermissionException $e) {
    // Not a bug — this app/token does not yet have boards:read access
    // approved. Surfaced distinctly so it reads as an external dependency,
    // not a broken feature (see docs Pinterest Foundation section).
    respond(false, 'Pinterest has not yet granted this app access to discover boards. Details: ' . $e->getMessage());
} catch (Throwable $e) {
    respond(false, $e->getMessage());
}
