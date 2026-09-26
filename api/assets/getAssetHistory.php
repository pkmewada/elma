<?php
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'data' => []
];

try {

    // =========================================================
    // ✅ VALIDATION
    // =========================================================
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid request method');
    }

    $assetId = (int)($_GET['assetId'] ?? 0);

    if ($assetId <= 0) {
        throw new Exception('Invalid asset ID');
    }

    // =========================================================
    // ✅ FETCH HISTORY
    // =========================================================
    $sql = "
        SELECT 
            aa.id,
            aa.assetId,
            aa.employeeId,
            eu.fullName AS employeeName,
            aa.assignedDate,
            aa.expectedReturnDate,
            aa.actualReturnDate,
            aa.status,
            aa.remarks,
            aa.createdAt,
            aa.updatedAt
        FROM assetAssignment aa
        LEFT JOIN employeeusers eu 
            ON aa.employeeId = eu.id
        WHERE aa.assetId = ?
        ORDER BY aa.id DESC
    ";

    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {
        throw new Exception('Database error');
    }

    mysqli_stmt_bind_param($stmt, 'i', $assetId);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    $history = [];

    while ($row = mysqli_fetch_assoc($result)) {

        // =========================================================
        // ✅ FORMAT STATUS LABEL (OPTIONAL BUT CLEAN)
        // =========================================================
        $statusLabel = $row['status'] === 'assigned' ? 'Assigned' : 'Returned';

        $history[] = [
            'id' => (int)$row['id'],
            'assetId' => (int)$row['assetId'],
            'employeeId' => (int)$row['employeeId'],
            'employeeName' => $row['employeeName'] ?? '-',

            'assignedDate' => $row['assignedDate'],
            'expectedReturnDate' => $row['expectedReturnDate'],
            'actualReturnDate' => $row['actualReturnDate'],

            'status' => $statusLabel,
            'remarks' => $row['remarks'],

            'createdAt' => date('d M Y h:i A', strtotime($row['createdAt'])),
            'updatedAt' => $row['updatedAt']
        ];
    }

    mysqli_stmt_close($stmt);

    // =========================================================
    // ✅ RESPONSE
    // =========================================================
    $response['success'] = true;
    $response['data'] = $history;

} catch (Exception $e) {

    $response['message'] = $e->getMessage();
}

echo json_encode($response);