<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Add a remark (communication note) and optionally schedule a follow-up
|--------------------------------------------------------------------------
| POST {leadId, remark, followUpDateTime?} + CSRF (gateway) + canEdit + lead
| in scope. Remarks are append-only; a follow-up becomes a manual row in the
| single follow-up table (leadFollowUps).
*/
requireLeadPost();
requireLeadPermission('canEdit');

$leadId = (int)($_POST['leadId'] ?? 0);
$lead = requireLeadAccess($con, $leadId);

$remark = trim((string)($_POST['remark'] ?? ''));
$followUp = parseFollowUpDateTime((string)($_POST['followUpDateTime'] ?? ''));

if ($remark === '' && !$followUp) {
    leadJsonExit(422, 'Enter a remark or a follow-up date.');
}

if (mb_strlen($remark) > 2000) {
    leadJsonExit(422, 'Remark is too long.');
}

$remarkId = null;
$followUpId = null;

mysqli_begin_transaction($con);

try {
    if ($remark !== '') {
        $remarkId = createLeadRemark($con, $leadId, $remark);
        saveActivityLog($con, 'Lead', $leadId, 'REMARK', 'Remark added : ' . $lead['fullName'], null, ['remarkId' => $remarkId, 'remark' => $remark]);
    }

    if ($followUp) {
        $followUpId = createManualFollowUp($con, $leadId, $followUp[0], $followUp[1], $remark);
    }

    mysqli_commit($con);
} catch (Throwable $e) {
    mysqli_rollback($con);
    error_log('saveLeadRemark failed: ' . $e->getMessage());
    leadJsonExit(500, 'Failed to save remark.');
}

echo json_encode([
    'success' => true,
    'message' => $followUp ? 'Saved and follow-up scheduled.' : 'Remark saved.',
    'data' => ['remarkId' => $remarkId, 'followUpId' => $followUpId],
]);
