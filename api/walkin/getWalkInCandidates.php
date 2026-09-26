<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/walkInCandidateEngine.php';

$filters = [
    'status' => trim((string)($_GET['status'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
    'dateFrom' => trim((string)($_GET['dateFrom'] ?? '')),
    'dateTo' => trim((string)($_GET['dateTo'] ?? '')),
];

try {
    $engine = new WalkInCandidateEngine($con);
    echo json_encode(['success' => true, 'data' => $engine->getList($filters)]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
