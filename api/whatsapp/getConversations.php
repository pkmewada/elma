<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/whatsappAccess.php';

requireApiPermission([whatsappCallerRoute()], 'canView');

$scope = getLeadScopeEmployeeId();
$search = trim((string)($_GET['q'] ?? ''));

$where = ['c.isActive = 1'];
$types = '';
$params = [];

if ($scope !== 0) {
    // Mirror requireConversationAccess()'s ownership rule exactly: a linked
    // conversation is owned by its lead's assignee; an unlinked one by its
    // own assignedToId.
    $where[] = '(CASE WHEN c.leadId IS NOT NULL THEN l.assignedToId ELSE c.assignedToId END) = ?';
    $types .= 'i';
    $params[] = $scope;
}

if ($search !== '') {
    $where[] = '(c.customerName LIKE ? OR c.phoneNumber LIKE ? OR l.fullName LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql = '
    SELECT c.id, c.waId, c.phoneNumber, c.customerName, c.leadId, c.assignedToId, c.lastMessageAt,
           c.lastMessagePreview, c.unreadCount, l.fullName AS leadFullName, l.status AS leadStatus,
           eu.fullName AS assignedToName
    FROM whatsappConversations c
    LEFT JOIN leads l ON l.id = c.leadId
    LEFT JOIN employeeusers eu ON eu.id = COALESCE(l.assignedToId, c.assignedToId)
    WHERE ' . implode(' AND ', $where) . '
    ORDER BY c.lastMessageAt DESC, c.id DESC
    LIMIT 200
';

$stmt = mysqli_prepare($con, $sql);

if ($types !== '') {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $row['unreadCount'] = (int)$row['unreadCount'];
    $rows[] = $row;
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $rows,
    'permissions' => [
        'canSend' => hasActionPermission(whatsappCallerRoute(), 'send_whatsapp_message'),
        'canLinkLead' => $scope === 0,
    ],
]);
