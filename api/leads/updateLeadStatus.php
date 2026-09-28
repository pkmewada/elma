<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Change pipeline status
|--------------------------------------------------------------------------
| POST (JSON) {id, status, remark?} + CSRF (gateway) + canEdit + lead in
| scope. Converted / Lost require a remark (reason). The remark is kept in
| leadStatusRemarks (history) and the change in the activity log.
*/
requireLeadPost();
requireLeadPermission('canEdit');

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$id = (int)($payload['id'] ?? 0);
$status = trim((string)($payload['status'] ?? ''));
$remark = trim((string)($payload['remark'] ?? ''));

$lead = requireLeadAccess($con, $id);

if (!isset(LEAD_STATUSES[$status])) {
    leadJsonExit(422, 'Invalid lead status.');
}

if (in_array($status, LEAD_CLOSING_STATUSES, true) && $remark === '') {
    leadJsonExit(422, 'Please enter a remark / reason for ' . LEAD_STATUSES[$status] . '.');
}

if (mb_strlen($remark) > 2000) {
    leadJsonExit(422, 'Remark is too long.');
}

$oldStatus = (string)$lead['status'];

if ($oldStatus === $status && $remark === '') {
    echo json_encode(['success' => true, 'message' => 'Status unchanged.', 'data' => ['id' => $id, 'status' => $status]]);
    exit;
}

mysqli_begin_transaction($con);

try {
    $stmt = mysqli_prepare($con, 'UPDATE leads SET status = ?, updatedAt = NOW() WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($remark !== '') {
        $actor = getCurrentActor();
        $stmt = mysqli_prepare($con, 'INSERT INTO leadStatusRemarks (leadId, status, remark, createdByCandidateId, createdByType) VALUES (?, ?, ?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'issis', $id, $status, $remark, $actor['id'], $actor['type']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    if ($oldStatus !== $status) {
        saveActivityLog(
            $con,
            'Lead',
            $id,
            $status === 'converted' ? 'CONVERTED' : ($status === 'lost' ? 'LOST' : 'STATUS'),
            sprintf('Status changed from %s to %s : %s', LEAD_STATUSES[$oldStatus] ?? $oldStatus, LEAD_STATUSES[$status], $lead['fullName']),
            ['status' => $oldStatus],
            ['status' => $status, 'remark' => $remark !== '' ? $remark : null]
        );
    }

    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    error_log('updateLeadStatus failed: ' . $e->getMessage());
    leadJsonExit(500, 'Failed to update lead status.');
}

echo json_encode([
    'success' => true,
    'message' => 'Lead status updated to ' . LEAD_STATUSES[$status] . '.',
    'data' => ['id' => $id, 'status' => $status],
]);
