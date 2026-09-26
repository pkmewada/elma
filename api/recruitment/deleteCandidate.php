<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (empty($_SESSION['userId'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access.',
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
    ]);
    exit();
}

$rawInput = file_get_contents('php://input');
$payload = json_decode((string) $rawInput, true);

if (!is_array($payload)) {
    $payload = $_POST;
}

$id = (int) ($payload['id'] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid candidate ID.',
    ]);
    exit();
}

$deleteStmt = mysqli_prepare($con, 'DELETE FROM candidateRecord WHERE id = ?');

if (!$deleteStmt) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete candidate.',
    ]);
    exit();
}

mysqli_stmt_bind_param($deleteStmt, 'i', $id);
$deleted = mysqli_stmt_execute($deleteStmt);
mysqli_stmt_close($deleteStmt);

if (!$deleted) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete candidate.',
    ]);
    exit();
}

echo json_encode([
    'success' => true,
    'message' => 'Candidate deleted successfully',
]);
?>