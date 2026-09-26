<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/db.php';
header('Content-Type: application/json; charset=UTF-8');

function respond(bool $success, string $message): void { echo json_encode(['success' => $success, 'message' => $message]); exit; }
function resolveCompanyId(): int { if (!empty($_SESSION['companyId'])) { return (int)$_SESSION['companyId']; } return !empty($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0; }
function cleanNonNegativeInt($value, int $default = 0): int { if ($value === '' || $value === null) { return $default; } return max(0, (int)$value); }

$companyId = resolveCompanyId();
if ($companyId <= 0) { respond(false, 'Invalid company context.'); }
$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) { respond(false, 'Invalid payload.'); }

$leaveSettings = is_array($payload['leaveSettings'] ?? null) ? $payload['leaveSettings'] : [];
$leaveTypes = is_array($payload['leaveTypes'] ?? null) ? $payload['leaveTypes'] : [];
if (count($leaveTypes) < 1) { respond(false, 'At least 1 leave type is required.'); }

$workingDaysIn = $leaveSettings['workingDays'] ?? [];
if (!is_array($workingDaysIn) || count($workingDaysIn) < 1) { respond(false, 'Working days are required.'); }
$allowedDays = ['mon','tue','wed','thu','fri','sat','sun'];
$workingDays = [];
foreach ($workingDaysIn as $day) { $day = strtolower(trim((string)$day)); if (in_array($day, $allowedDays, true)) { $workingDays[] = $day; } }
$workingDays = array_values(array_unique($workingDays));
if (count($workingDays) < 1) { respond(false, 'Working days are required.'); }

$weekendPolicy = ((string)($leaveSettings['weekendPolicy'] ?? 'exclude') === 'include') ? 'include' : 'exclude';
$sandwichRule = !empty($leaveSettings['sandwichRule']) ? 1 : 0;
$carryForward = !empty($leaveSettings['carryForward']) ? 1 : 0;
$carryForwardLimit = cleanNonNegativeInt($leaveSettings['carryForwardLimit'] ?? 0, 0);
if ($carryForward === 0) { $carryForwardLimit = 0; }
$maxLeavesPerRequest = cleanNonNegativeInt($leaveSettings['maxLeavesPerRequest'] ?? 0, 0);
$minNoticeDays = cleanNonNegativeInt($leaveSettings['minNoticeDays'] ?? 0, 0);

$normalizedLeaveTypes = [];
$seenCodes = [];
foreach ($leaveTypes as $index => $row) {
    if (!is_array($row)) { respond(false, 'Invalid leave type row.'); }
    $id = cleanNonNegativeInt($row['id'] ?? 0, 0);
    $name = trim((string)($row['name'] ?? ''));
    $code = strtoupper(trim((string)($row['code'] ?? '')));
    $isPaid = !empty($row['isPaid']) ? 1 : 0;
    $allocationType = ((string)($row['allocationType'] ?? 'yearly') === 'monthly') ? 'monthly' : 'yearly';
    $totalLeaves = cleanNonNegativeInt($row['totalLeaves'] ?? 0, 0);
    $isActive = !empty($row['isActive']) ? 1 : 0;
    if ($name === '' || $code === '') { respond(false, 'Leave type name and code are required.'); }
    if (isset($seenCodes[$code])) { respond(false, 'Duplicate leave code: ' . $code); }
    $seenCodes[$code] = true;
    $allowHalfDay = !empty($row['allowHalfDay']) ? 1 : 0;
    $maxConsecutiveDays = cleanNonNegativeInt($row['maxConsecutiveDays'] ?? 0, 0);
    $minServiceDays = cleanNonNegativeInt($row['minServiceDays'] ?? 0, 0);
    $applicableGender = in_array(($row['applicableGender'] ?? 'all'), ['all','male','female']) ? $row['applicableGender'] : 'all';
    $allowNegative = !empty($row['allowNegative']) ? 1 : 0;

    $normalizedLeaveTypes[] = [
        'id' => $id,
        'name' => $name,
        'code' => $code,
        'isPaid' => $isPaid,
        'allocationType' => $allocationType,
        'totalLeaves' => $totalLeaves,
        'isActive' => $isActive,

        // ✅ NEW
        'allowHalfDay' => $allowHalfDay,
        'maxConsecutiveDays' => $maxConsecutiveDays,
        'minServiceDays' => $minServiceDays,
        'applicableGender' => $applicableGender,
        'allowNegative' => $allowNegative
    ];
}

mysqli_begin_transaction($con);

try {

    // ================================
    // ✅ SAVE SETTINGS
    // ================================
    $check = mysqli_prepare($con, 'SELECT id FROM leaveSettings WHERE companyId = ? LIMIT 1');
    mysqli_stmt_bind_param($check, 'i', $companyId);
    mysqli_stmt_execute($check);
    $exists = mysqli_stmt_get_result($check)->fetch_assoc();
    mysqli_stmt_close($check);

    $workingDaysJson = json_encode($workingDays);

    if ($exists) {
        $stmt = mysqli_prepare($con, 'UPDATE leaveSettings SET workingDays=?, weekendPolicy=?, sandwichRule=?, carryForward=?, carryForwardLimit=?, maxLeavesPerRequest=?, minNoticeDays=?, setupCompleted=1 WHERE companyId=?');
        mysqli_stmt_bind_param($stmt, 'ssiiiiii', $workingDaysJson, $weekendPolicy, $sandwichRule, $carryForward, $carryForwardLimit, $maxLeavesPerRequest, $minNoticeDays, $companyId);
    } else {
        $stmt = mysqli_prepare($con, 'INSERT INTO leaveSettings (companyId, workingDays, weekendPolicy, sandwichRule, carryForward, carryForwardLimit, maxLeavesPerRequest, minNoticeDays, setupCompleted) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)');
        mysqli_stmt_bind_param($stmt, 'issiiiii', $companyId, $workingDaysJson, $weekendPolicy, $sandwichRule, $carryForward, $carryForwardLimit, $maxLeavesPerRequest, $minNoticeDays);
    }

    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);


    // ================================
    // ✅ PREPARE STATEMENTS FIRST (IMPORTANT)
    // ================================
    $updateStmt = mysqli_prepare($con, '
        UPDATE leaveTypes 
        SET name=?, code=?, isPaid=?, allocationType=?, totalLeaves=?, isActive=?, 
    allowHalfDay=?, maxConsecutiveDays=?, minServiceDays=?, applicableGender=?, allowNegative=?
        WHERE id=? AND companyId=?
    ');

    $insertStmt = mysqli_prepare($con, '
        INSERT INTO leaveTypes 
        (companyId, name, code, isPaid, allocationType, totalLeaves, isActive,
        allowHalfDay, maxConsecutiveDays, minServiceDays, applicableGender, allowNegative)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ');

    if (!$updateStmt || !$insertStmt) {
        throw new Exception('Statement prepare failed');
    }


    // ================================
    // ✅ SINGLE LOOP (CORRECT LOGIC)
    // ================================
    foreach ($normalizedLeaveTypes as $lt) {

        // 🔥 DELETE
        if ($lt['isActive'] == 0 && $lt['id'] > 0) {
            $del = mysqli_prepare($con, 'DELETE FROM leaveTypes WHERE id=? AND companyId=?');
            mysqli_stmt_bind_param($del, 'ii', $lt['id'], $companyId);
            mysqli_stmt_execute($del);
            mysqli_stmt_close($del);
            continue;
        }

        // 🔄 UPDATE
        if ($lt['id'] > 0) {
           mysqli_stmt_bind_param(
                $updateStmt,
                'ssisiiiiisiii',
                $lt['name'],                // s
                $lt['code'],                // s
                $lt['isPaid'],              // i
                $lt['allocationType'],      // s
                $lt['totalLeaves'],         // i
                $lt['isActive'],            // i
                $lt['allowHalfDay'],        // i
                $lt['maxConsecutiveDays'],  // i
                $lt['minServiceDays'],      // i
                $lt['applicableGender'],    // s
                $lt['allowNegative'],       // i
                $lt['id'],                  // i
                $companyId                  // i
            );
            mysqli_stmt_execute($updateStmt);
            if (!mysqli_stmt_execute($updateStmt)) {
    throw new Exception(mysqli_stmt_error($updateStmt));
}
        }

        // ➕ INSERT
        else {
           mysqli_stmt_bind_param(
                $insertStmt,
                'issisiiiiisi',
                $companyId,
                $lt['name'],
                $lt['code'],
                $lt['isPaid'],
                $lt['allocationType'],
                $lt['totalLeaves'],
                $lt['isActive'],
                $lt['allowHalfDay'],
                $lt['maxConsecutiveDays'],
                $lt['minServiceDays'],
                $lt['applicableGender'],
                $lt['allowNegative']
            );
            mysqli_stmt_execute($insertStmt);
        }
    }
    
    mysqli_stmt_close($updateStmt);
    mysqli_stmt_close($insertStmt);

    mysqli_commit($con);

    respond(true, 'Leave setup saved successfully');

} catch (Throwable $e) {

    mysqli_rollback($con);

    respond(false, $e->getMessage());
}
    respond(true, 'Leave setup saved and activated successfully.');

?>
