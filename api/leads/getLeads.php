<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// View permission; employees without 'view-all-leads' only get leads
// assigned to them. Only full-scope users may filter by salesperson.
requireLeadPermission('canView');

$status = trim((string)($_GET['status'] ?? ''));
$employeeFilter = trim((string)($_GET['employeeId'] ?? ''));
$projectFilter = (int)($_GET['projectId'] ?? 0);
$sourceFilter = (int)($_GET['sourceId'] ?? 0);
$dateFrom = trim((string)($_GET['dateFrom'] ?? ''));
$dateTo = trim((string)($_GET['dateTo'] ?? ''));

if ($status !== '' && !isset(LEAD_STATUSES[$status])) {
    leadJsonExit(422, 'Invalid lead status filter.');
}

foreach ([$dateFrom, $dateTo] as $dateValue) {
    if ($dateValue !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
        leadJsonExit(422, 'Invalid date filter.');
    }
}

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    leadJsonExit(422, 'From Date cannot be after To Date.');
}

$where = [];
$params = [];
$types = '';
$scopeEmployeeId = getLeadScopeEmployeeId();

if ($scopeEmployeeId !== 0) {
    $where[] = 'l.assignedToId = ?';
    $params[] = $scopeEmployeeId;
    $types .= 'i';
} elseif ($employeeFilter === 'unassigned') {
    $where[] = 'l.assignedToId IS NULL';
} elseif ((int)$employeeFilter > 0) {
    $where[] = 'l.assignedToId = ?';
    $params[] = (int)$employeeFilter;
    $types .= 'i';
}

if ($status !== '') {
    $where[] = 'l.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($projectFilter > 0) {
    $where[] = 'l.projectId = ?';
    $params[] = $projectFilter;
    $types .= 'i';
}

if ($sourceFilter > 0) {
    $where[] = 'l.sourceId = ?';
    $params[] = $sourceFilter;
    $types .= 'i';
}

if ($dateFrom !== '') {
    $where[] = 'DATE(l.createdAt) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $where[] = 'DATE(l.createdAt) <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = mysqli_prepare(
    $con,
    "SELECT
        l.id, l.fullName, l.email, l.phone, l.country, l.countryCode, l.status,
        l.projectId, p.projectName,
        l.sourceId, s.sourceName AS source,
        l.assignedToId, eu.fullName AS assignedToName,
        l.createdAt, l.updatedAt,
        (SELECT IF(f.dueTime IS NULL, f.dueDate, CONCAT(f.dueDate, ' ', f.dueTime))
         FROM leadFollowUps f
         WHERE f.leadId = l.id AND f.status = 'Pending'
         ORDER BY f.dueDate ASC, f.dueTime ASC LIMIT 1) AS nextFollowUp,
        (SELECT sr.remark FROM leadStatusRemarks sr
         WHERE sr.leadId = l.id AND sr.status = l.status
         ORDER BY sr.id DESC LIMIT 1) AS reason
     FROM leads l
     LEFT JOIN projects p ON p.id = l.projectId
     LEFT JOIN leadSources s ON s.id = l.sourceId
     LEFT JOIN employeeusers eu ON eu.id = l.assignedToId
     {$whereSql}
     ORDER BY l.id DESC"
);

if (!$stmt) {
    leadJsonExit(500, 'Unable to load leads.');
}

if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$leads = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['id'] = (int)$row['id'];
    $row['projectId'] = $row['projectId'] !== null ? (int)$row['projectId'] : null;
    $row['sourceId'] = $row['sourceId'] !== null ? (int)$row['sourceId'] : null;
    $row['assignedToId'] = $row['assignedToId'] !== null ? (int)$row['assignedToId'] : null;
    $leads[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $leads,
]);
