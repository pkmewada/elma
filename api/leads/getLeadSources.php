<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/leadAccess.php';

// All sources (active + inactive) with usage counts, for Lead Setup.
requireApiPermission(['/lead-setup'], 'canView');

$result = mysqli_query(
    $con,
    'SELECT s.id, s.sourceKey, s.sourceName, s.isSystem, s.isActive, COUNT(l.id) AS leadCount
     FROM leadSources s LEFT JOIN leads l ON l.sourceId = s.id
     GROUP BY s.id ORDER BY s.sortOrder ASC, s.sourceName ASC'
);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        'id' => (int)$row['id'],
        'sourceKey' => $row['sourceKey'],
        'sourceName' => $row['sourceName'],
        'isSystem' => (int)$row['isSystem'] === 1,
        'isActive' => (int)$row['isActive'] === 1,
        'leadCount' => (int)$row['leadCount'],
    ];
}

echo json_encode(['success' => true, 'data' => $data]);
