<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/projectAccess.php';

/*
| Add (no id) or edit (id) a project. POST + CSRF (gateway) + canAdd / canEdit
| on /projects. Multi-value fields (configuration, amenities) are plain
| comma-separated text - the simplest pattern the app already uses.
*/
requireProjectPost();

$id = (int)($_POST['id'] ?? 0);
requireProjectManage($id > 0 ? 'canEdit' : 'canAdd');

$fields = [
    'projectName' => [150, true],
    'developerName' => [150, false],
    'location' => [255, false],
    'description' => [5000, false],
    'propertyType' => [50, false],
    'configuration' => [255, false],
    'pricing' => [255, false],
    'amenities' => [2000, false],
];

$values = [];
foreach ($fields as $name => [$maxLength, $required]) {
    $value = trim((string)($_POST[$name] ?? ''));
    if ($required && $value === '') {
        projectJsonExit(422, 'Project name is required.');
    }
    if (mb_strlen($value) > $maxLength) {
        projectJsonExit(422, "{$name} is too long (max {$maxLength} characters).");
    }
    $values[$name] = $value !== '' ? $value : null;
}

if ($values['propertyType'] !== null && !in_array($values['propertyType'], PROJECT_PROPERTY_TYPES, true)) {
    projectJsonExit(422, 'Select a valid property type.');
}

// Normalise comma lists ("2BHK,  3BHK" -> "2BHK, 3BHK").
foreach (['configuration', 'amenities'] as $listField) {
    if ($values[$listField] !== null) {
        $items = array_values(array_unique(array_filter(array_map('trim', explode(',', $values[$listField])), 'strlen')));
        $values[$listField] = $items ? implode(', ', $items) : null;
    }
}

$old = $id > 0 ? requireProject($con, $id) : null;
$actor = getCurrentActor();

if ($id > 0) {
    $stmt = mysqli_prepare(
        $con,
        'UPDATE projects SET projectName = ?, developerName = ?, location = ?, description = ?, propertyType = ?, configuration = ?, pricing = ?, amenities = ? WHERE id = ?'
    );
    mysqli_stmt_bind_param($stmt, 'ssssssssi', $values['projectName'], $values['developerName'], $values['location'], $values['description'],
        $values['propertyType'], $values['configuration'], $values['pricing'], $values['amenities'], $id);
} else {
    $stmt = mysqli_prepare(
        $con,
        'INSERT INTO projects (projectName, developerName, location, description, propertyType, configuration, pricing, amenities, createdByType, createdById)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_stmt_bind_param($stmt, 'sssssssssi', $values['projectName'], $values['developerName'], $values['location'], $values['description'],
        $values['propertyType'], $values['configuration'], $values['pricing'], $values['amenities'], $actor['type'], $actor['id']);
}

if (!mysqli_stmt_execute($stmt)) {
    $duplicate = mysqli_stmt_errno($stmt) === 1062;
    error_log('saveProject failed: ' . mysqli_stmt_error($stmt));
    projectJsonExit($duplicate ? 409 : 500, $duplicate ? 'A project with this name already exists.' : 'Unable to save project.');
}

$projectId = $id > 0 ? $id : (int)mysqli_insert_id($con);
mysqli_stmt_close($stmt);

saveActivityLog($con, 'Project', $projectId, $id > 0 ? 'UPDATE' : 'CREATE',
    ($id > 0 ? 'Project updated : ' : 'Project created : ') . $values['projectName'],
    $old ? array_intersect_key($old, $fields) : null,
    $values
);

echo json_encode(['success' => true, 'message' => $id > 0 ? 'Project updated.' : 'Project added.', 'data' => ['id' => $projectId]]);
