<?php

require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../app/models/AssetAssignmentModel.php';

header('Content-Type: application/json');

function respond(bool $success, string $message, array $data = []): void
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Employee identity comes from the session only -- never trust a
// frontend-supplied employeeId, same rule as api/expense/emp-getExpense.php.
$employeeId = (int)($_SESSION['candidateId'] ?? 0);

if ($employeeId <= 0) {
    respond(false, 'Invalid employee session.');
}

$assignmentModel = new AssetAssignmentModel($con);
$result = $assignmentModel->getHistoryByEmployee($employeeId);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = $row;
}

respond(true, 'Assets fetched successfully.', $data);
