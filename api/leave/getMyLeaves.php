<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

# ===============================
# 🔹 RESPONSE HELPER
# ===============================
function respond(bool $success, string $message, array $data = []): void {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

$employeeId = $_SESSION['userId'] ?? 0;


# ===============================
# 🔹 FETCH LEAVES
# ===============================
$stmt = mysqli_prepare($con, "
    SELECT 
        la.id,
        la.leaveTypeId,
        lt.name AS leaveTypeName,
        lt.code AS leaveTypeCode,
        la.fromDate,
        la.toDate,
        la.totalDays,
        la.dayType,
        la.reason,
        la.status,
        la.createdAt
    FROM leaveApplications la  
    LEFT JOIN leaveTypes lt ON lt.id = la.leaveTypeId
    LEFT JOIN employeeusers e ON e.id = la.employeeId
    WHERE la.employeeId = ? AND e.accountStatus = 'Active'
    ORDER BY la.id DESC
");

if (!$stmt) {
    respond(false, 'Failed to fetch leave data');
}

mysqli_stmt_bind_param($stmt, "i",  $employeeId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$leaves = [];

while ($row = mysqli_fetch_assoc($result)) {

    $leaves[] = [
        "id" => (int)$row["id"],
        "leaveTypeId" => (int)$row["leaveTypeId"],
        "leaveTypeName" => $row["leaveTypeName"] ?? '',
        "leaveTypeCode" => strtoupper($row["leaveTypeCode"] ?? ''),
        "fromDate" => $row["fromDate"],
        "toDate" => $row["toDate"],
        "totalDays" => (float)$row["totalDays"],
        "dayType" => $row["dayType"] ?? 'full',
        "reason" => $row["reason"] ?? '',
        "status" => $row["status"] ?? 'pending',
        "createdAt" => $row["createdAt"]
    ];
}

mysqli_stmt_close($stmt);

# ===============================
# 🔹 RESPONSE
# ===============================
respond(true, 'Leave data fetched successfully', [
    'leaves' => $leaves
]);
