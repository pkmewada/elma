<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

// Project list (+ document counts). Employees: active projects only.
requireProjectView();

$where = projectsActiveOnly() ? 'WHERE p.isActive = 1' : '';

$result = mysqli_query(
    $con,
    "SELECT p.id, p.projectName, p.developerName, p.location, p.description, p.propertyType,
            p.configuration, p.pricing, p.amenities, p.isActive, p.createdAt, p.updatedAt,
            (SELECT COUNT(*) FROM projectDocuments d WHERE d.projectId = p.id) AS documentCount,
            (SELECT COUNT(*) FROM leads l WHERE l.projectId = p.id) AS leadCount
     FROM projects p {$where}
     ORDER BY p.isActive DESC, p.projectName ASC"
);

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['id'] = (int)$row['id'];
    $row['isActive'] = (int)$row['isActive'] === 1;
    $row['documentCount'] = (int)$row['documentCount'];
    $row['leadCount'] = (int)$row['leadCount'];
    $data[] = $row;
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'propertyTypes' => PROJECT_PROPERTY_TYPES,
    'documentTypes' => PROJECT_DOCUMENT_TYPES,
    'permissions' => [
        'canAdd' => hasRoutePermission(PROJECT_ADMIN_ROUTE, 'canAdd'),
        'canEdit' => hasRoutePermission(PROJECT_ADMIN_ROUTE, 'canEdit'),
        'canDelete' => hasRoutePermission(PROJECT_ADMIN_ROUTE, 'canDelete'),
    ],
]);
