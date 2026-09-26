<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

// Updates candidateRemarks follow-ups from the Candidate Record page.
requireApiPermission('/candidate-record', 'canEdit');

error_reporting(E_ALL);
ini_set('display_errors', 1);
$id = (int)($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

$allowed = ['Pending', 'Complete', 'Cancelled', 'Rescheduled'];

if ($id < 1 || !in_array($status, $allowed, true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid input'
    ]);
    exit;
}

$stmt = mysqli_prepare($con,
    "UPDATE candidateremarks SET status=? WHERE candidateId=?"
);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => mysqli_error($con)
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, "si", $status, $id);
mysqli_stmt_execute($stmt);

echo json_encode([
    'success' => true,
    'affectedRows' => mysqli_stmt_affected_rows($stmt),
    'id' => $id,
    'status' => $status
]);