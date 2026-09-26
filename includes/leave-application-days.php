<?php

require_once __DIR__ . '/leave-balance.php';

function leaveDayTableExists(mysqli $con): bool
{
    $result = mysqli_query($con, "SHOW TABLES LIKE 'leaveApplicationDays'");

    return $result && (bool)mysqli_fetch_row($result);
}

function normalizeLeaveWorkingDays($workingDays): array
{
    $days = is_array($workingDays)
        ? $workingDays
        : json_decode((string)$workingDays, true);

    if (!is_array($days)) {
        return [];
    }

    $normalized = [];
    foreach ($days as $day) {
        $day = strtolower(trim((string)$day));
        if (in_array($day, ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], true)) {
            $normalized[] = $day;
        }
    }

    return array_values(array_unique($normalized));
}

function calculateLeaveDateRows(
    string $fromDate,
    string $toDate,
    string $dayType,
    array $workingDays,
    string $weekendPolicy
): array {
    $rows = [];
    $start = new DateTime($fromDate);
    $end = new DateTime($toDate);
    $effectiveDayType = $dayType === 'half' ? 'half' : 'full';

    while ($start <= $end) {
        $day = strtolower($start->format('D'));

        if (in_array($day, $workingDays, true) || $weekendPolicy === 'include') {
            $rows[] = [
                'leaveDate' => $start->format('Y-m-d'),
                'dayType' => $effectiveDayType,
            ];
        }

        $start->modify('+1 day');
    }

    if ($effectiveDayType === 'half' && count($rows) > 1) {
        return array_slice($rows, 0, 1);
    }

    return $rows;
}

function getLeaveDateRowsTotal(array $dateRows): float
{
    $total = 0.0;

    foreach ($dateRows as $row) {
        $total += ($row['dayType'] ?? 'full') === 'half' ? 0.5 : 1.0;
    }

    return $total;
}

function insertLeaveApplicationDays(
    mysqli $con,
    int $leaveApplicationId,
    array $dateRows,
    string $status = 'pending',
    ?int $reviewedBy = null,
    ?string $reviewedAt = null
): void {
    if (!$dateRows) {
        throw new Exception('No valid leave dates found');
    }

    if (!in_array($status, ['pending', 'approved', 'rejected'], true)) {
        $status = 'pending';
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO leaveApplicationDays
         (leaveApplicationId, leaveDate, dayType, status, reviewedBy, reviewedAt)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare leave day insert');
    }

    foreach ($dateRows as $row) {
        $leaveDate = (string)$row['leaveDate'];
        $dayType = (string)($row['dayType'] ?? 'full');
        $reviewUser = $reviewedBy;
        $reviewTime = $reviewedAt;

        mysqli_stmt_bind_param(
            $stmt,
            'isssis',
            $leaveApplicationId,
            $leaveDate,
            $dayType,
            $status,
            $reviewUser,
            $reviewTime
        );

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to create leave day rows');
        }
    }

    mysqli_stmt_close($stmt);
}

function ensureLeaveApplicationDays(
    mysqli $con,
    array $leave,
    array $settings
): void {
    if (!leaveDayTableExists($con)) {
        return;
    }

    $leaveId = (int)($leave['id'] ?? 0);
    if ($leaveId <= 0) {
        return;
    }

    $checkStmt = mysqli_prepare(
        $con,
        "SELECT COUNT(*) AS total FROM leaveApplicationDays WHERE leaveApplicationId = ?"
    );

    if (!$checkStmt) {
        throw new Exception('Failed to check leave day rows');
    }

    mysqli_stmt_bind_param($checkStmt, 'i', $leaveId);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($checkStmt);

    if ((int)($row['total'] ?? 0) > 0) {
        return;
    }

    $parentStatus = strtolower((string)($leave['status'] ?? 'pending'));
    if ($parentStatus === 'cancelled') {
        return;
    }

    $dateRows = calculateLeaveDateRows(
        (string)$leave['fromDate'],
        (string)$leave['toDate'],
        strtolower((string)($leave['dayType'] ?? 'full')),
        normalizeLeaveWorkingDays($settings['workingDays'] ?? []),
        (string)($settings['weekendPolicy'] ?? 'exclude') === 'include' ? 'include' : 'exclude'
    );

    $status = in_array($parentStatus, ['pending', 'approved', 'rejected'], true)
        ? $parentStatus
        : 'pending';
    $reviewedAt = in_array($status, ['approved', 'rejected'], true) ? date('Y-m-d H:i:s') : null;

    insertLeaveApplicationDays($con, $leaveId, $dateRows, $status, null, $reviewedAt);
}

function getLeaveSettingsForDayRows(mysqli $con): array
{
    $stmt = mysqli_prepare(
        $con,
        "SELECT workingDays, weekendPolicy
         FROM leaveSettings
         WHERE setupCompleted = 1
         ORDER BY id DESC
         LIMIT 1"
    );

    if (!$stmt) {
        throw new Exception('Failed to load leave settings');
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $settings = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if (!$settings) {
        throw new Exception('Leave setup not configured');
    }

    $settings['workingDays'] = normalizeLeaveWorkingDays($settings['workingDays'] ?? []);
    $settings['weekendPolicy'] = (string)($settings['weekendPolicy'] ?? 'exclude') === 'include'
        ? 'include'
        : 'exclude';

    return $settings;
}

function calculateLeaveParentStatus(mysqli $con, int $leaveApplicationId): string
{
    $stmt = mysqli_prepare(
        $con,
        "SELECT
            COUNT(*) AS total,
            SUM(status = 'pending') AS pendingDays,
            SUM(status = 'approved') AS approvedDays,
            SUM(status = 'rejected') AS rejectedDays
         FROM leaveApplicationDays
         WHERE leaveApplicationId = ?"
    );

    if (!$stmt) {
        throw new Exception('Failed to calculate leave summary');
    }

    mysqli_stmt_bind_param($stmt, 'i', $leaveApplicationId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    $total = (int)($row['total'] ?? 0);
    $pending = (int)($row['pendingDays'] ?? 0);
    $approved = (int)($row['approvedDays'] ?? 0);
    $rejected = (int)($row['rejectedDays'] ?? 0);

    if ($total <= 0 || $pending === $total) {
        return 'pending';
    }

    if ($approved === $total) {
        return 'approved';
    }

    if ($rejected === $total) {
        return 'rejected';
    }

    return 'partial';
}

function updateLeaveParentSummaryStatus(mysqli $con, int $leaveApplicationId): string
{
    $status = calculateLeaveParentStatus($con, $leaveApplicationId);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE leaveApplications
         SET status = ?
         WHERE id = ? AND status <> 'cancelled'"
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare parent status update');
    }

    mysqli_stmt_bind_param($stmt, 'si', $status, $leaveApplicationId);

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('Failed to update parent leave status');
    }

    mysqli_stmt_close($stmt);

    return $status;
}

function getApprovedLeaveUnits(mysqli $con, int $employeeId, int $leaveTypeId): float
{
    if (leaveDayTableExists($con)) {
        $stmt = mysqli_prepare(
            $con,
            "SELECT COALESCE(SUM(CASE WHEN lad.dayType = 'half' THEN 0.5 ELSE 1 END), 0) AS approvedUnits
             FROM leaveApplicationDays lad
             INNER JOIN leaveApplications la
                ON la.id = lad.leaveApplicationId
             WHERE la.employeeId = ?
             AND la.leaveTypeId = ?
             AND la.status <> 'cancelled'
             AND lad.status = 'approved'"
        );
    } else {
        $stmt = mysqli_prepare(
            $con,
            "SELECT COALESCE(SUM(totalDays), 0) AS approvedUnits
             FROM leaveApplications
             WHERE employeeId = ?
             AND leaveTypeId = ?
             AND status = 'approved'"
        );
    }

    if (!$stmt) {
        throw new Exception('Failed to calculate approved leave units');
    }

    mysqli_stmt_bind_param($stmt, 'ii', $employeeId, $leaveTypeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return round((float)($row['approvedUnits'] ?? 0), 2);
}

function recalculateLeaveBalance(mysqli $con, int $employeeId, int $leaveTypeId): array
{
    $balance = getOrCreateBalance($con, $employeeId, $leaveTypeId);
    $totalAllocated = (float)($balance['totalAllocated'] ?? 0);
    $usedLeaves = getApprovedLeaveUnits($con, $employeeId, $leaveTypeId);
    $remainingLeaves = round($totalAllocated - $usedLeaves, 2);

    $stmt = mysqli_prepare(
        $con,
        "UPDATE leaveBalances
         SET usedLeaves = ?, remainingLeaves = ?
         WHERE employeeId = ? AND leaveTypeId = ?"
    );

    if (!$stmt) {
        throw new Exception('Failed to prepare balance recalculation');
    }

    mysqli_stmt_bind_param(
        $stmt,
        'ddii',
        $usedLeaves,
        $remainingLeaves,
        $employeeId,
        $leaveTypeId
    );

    if (!mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        throw new Exception('Failed to recalculate leave balance');
    }

    mysqli_stmt_close($stmt);

    return [
        'totalAllocated' => $totalAllocated,
        'usedLeaves' => $usedLeaves,
        'remainingLeaves' => $remainingLeaves,
    ];
}

function assertLeaveBalanceAllowsApproval(mysqli $con, array $leave): void
{
    if ((int)($leave['allowNegative'] ?? 0) === 1) {
        return;
    }

    $balance = getOrCreateBalance(
        $con,
        (int)$leave['employeeId'],
        (int)$leave['leaveTypeId']
    );

    $approvedUnits = getApprovedLeaveUnits(
        $con,
        (int)$leave['employeeId'],
        (int)$leave['leaveTypeId']
    );

    $totalAllocated = (float)($balance['totalAllocated'] ?? 0);

    if (($totalAllocated - $approvedUnits) < -0.0001) {
        throw new Exception('Insufficient leave balance');
    }
}
