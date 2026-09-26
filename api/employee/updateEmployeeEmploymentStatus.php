<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

$id = (int) ($_POST['id'] ?? 0);
$employmentStatus = trim((string) ($_POST['employmentStatus'] ?? ''));

if ($id <= 0 || !in_array($employmentStatus, ['Active', 'Deactive'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

$accountStatus = $employmentStatus === 'Active' ? 'Active' : 'Deactive';

$stmt = mysqli_prepare($con, "
    UPDATE employeeusers
    SET
        employmentStatus = ?,
        accountStatus = ?,
        updatedAt = NOW()
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare update.'
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'ssi', $employmentStatus, $accountStatus, $id);
$ok = mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

if (!$ok) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to update employee status.'
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Employee status updated successfully.',
    'data' => [
        'employmentStatus' => $employmentStatus,
        'accountStatus' => $accountStatus
    ]
]);
