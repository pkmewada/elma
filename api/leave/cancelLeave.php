<?php session_start();
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json; charset=UTF-8');

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



# ===============================
# 🔹 INPUT (SUPPORT JSON + POST)
# ===============================
$rawInput = json_decode(file_get_contents("php://input"), true);

$leaveId = 0;

if (!empty($rawInput)) {
    $leaveId = (int)($rawInput['leaveId'] ?? 0);
} else {
    $leaveId = (int)($_POST['leaveId'] ?? $_POST['id'] ?? 0);
}

$employeeId = $_SESSION['userId'];

if ($leaveId <= 0) {
    respond(false, 'Invalid leave request');
}

# ===============================
# 🔹 FETCH LEAVE RECORD
# ===============================
$stmt = mysqli_prepare($con, "
    SELECT id, status 
    FROM leaveApplications 
    WHERE id = ? AND employeeId = ?
    LIMIT 1
");

if (!$stmt) {
    respond(false, 'Failed to prepare query');
}

mysqli_stmt_bind_param($stmt, "ii", $leaveId, $employeeId);

if (!mysqli_stmt_execute($stmt)) {
    respond(false, 'Failed to fetch leave');
}

$result = mysqli_stmt_get_result($stmt);
$leave = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$leave) {
    respond(false, 'Leave not found');
}

# ===============================
# 🔹 STRICT VALIDATION
# ===============================
if (!in_array($leave['status'], ['pending'], true)) {
    respond(false, 'Only pending leaves can be cancelled');
}

# ===============================
# 🔹 SAFE ENUM VALUE (NO TYPO RISK)
# ===============================
$cancelStatus = 'cancelled'; // MUST MATCH ENUM EXACTLY

# ===============================
# 🔹 UPDATE STATUS
# ===============================
$updateStmt = mysqli_prepare($con, "
    UPDATE leaveApplications 
    SET status = ? 
    WHERE id = ? AND employeeId = ?
");

if (!$updateStmt) {
    respond(false, 'Failed to prepare update');
}

mysqli_stmt_bind_param(
    $updateStmt,
    "sii",
    $cancelStatus,
    $leaveId,
    $employeeId,
);

if (!mysqli_stmt_execute($updateStmt)) {
    respond(false, 'Database error: ' . mysqli_error($con));
}

# ===============================
# 🔹 VERIFY UPDATE (ANTI-BUG CHECK)
# ===============================
if (mysqli_stmt_affected_rows($updateStmt) <= 0) {
    respond(false, 'Leave cancellation failed');
}

mysqli_stmt_close($updateStmt);

# ===============================
# 🔹 SUCCESS RESPONSE
# ===============================
respond(true, 'Leave cancelled successfully', [
    'leaveId' => $leaveId,
    'status'  => $cancelStatus
]);
