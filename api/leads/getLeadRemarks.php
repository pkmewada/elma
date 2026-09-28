<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// Communication history for one lead (remarks + status reasons), newest
// first. View permission + lead in scope. Author shown by actor type.
requireApiPermission([LEAD_ADMIN_ROUTE, LEAD_EMPLOYEE_ROUTE, '/lead-follow-up-list', '/emp-follow-ups'], 'canView');

$leadId = (int)($_GET['leadId'] ?? 0);
requireLeadAccess($con, $leadId);

$remarkAuthor = actorNameSql('r.createdByType', 'ua', 'ea');
$statusAuthor = actorNameSql('sr.createdByType', 'ub', 'eb');

$stmt = mysqli_prepare(
    $con,
    "SELECT * FROM (
        SELECT r.id, 'remark' AS kind, r.remark, NULL AS status, r.createdAt, {$remarkAuthor} AS employeeName
        FROM leadRemarks r
        LEFT JOIN users ua ON ua.id = r.createdByCandidateId AND r.createdByType = 'admin'
        LEFT JOIN employeeusers ea ON ea.id = r.createdByCandidateId AND r.createdByType = 'employee'
        WHERE r.leadId = ?
        UNION ALL
        SELECT sr.id, 'status' AS kind, sr.remark, sr.status, sr.createdAt, {$statusAuthor} AS employeeName
        FROM leadStatusRemarks sr
        LEFT JOIN users ub ON ub.id = sr.createdByCandidateId AND sr.createdByType = 'admin'
        LEFT JOIN employeeusers eb ON eb.id = sr.createdByCandidateId AND sr.createdByType = 'employee'
        WHERE sr.leadId = ?
    ) history
    ORDER BY createdAt DESC, id DESC"
);

if (!$stmt) {
    leadJsonExit(500, 'Unable to load remarks.');
}

mysqli_stmt_bind_param($stmt, 'ii', $leadId, $leadId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'id' => (int)$row['id'],
        'kind' => $row['kind'],
        'remark' => $row['remark'],
        'status' => $row['status'] !== null ? (LEAD_STATUSES[$row['status']] ?? $row['status']) : null,
        'createdAt' => date('d M Y h:i A', strtotime($row['createdAt'])),
        'employeeName' => $row['employeeName'],
    ];
}
mysqli_stmt_close($stmt);

echo json_encode(['success' => true, 'data' => $data]);
