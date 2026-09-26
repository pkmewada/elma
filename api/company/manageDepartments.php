<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/basic-config.php';

header('Content-Type: application/json; charset=UTF-8');

$action = trim((string)($_POST['action'] ?? $_GET['action'] ?? 'list'));
$config = getBasicConfig();
$departments = $config['departments'] ?? [];

$normalize = static function(string $name): string {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    return ucwords(strtolower($name));
};

$saveDepartments = static function(array $departmentsToSave, array $config) {

    $config['departments'] = array_values($departmentsToSave);

    return saveBasicConfig($config);
};

if ($action === 'active_list') {
    $active = array_values(array_filter($departments, static function($d){
        return strcasecmp((string)($d['status'] ?? 'Active'), 'Active') === 0;
    }));
    echo json_encode(['success' => true, 'data' => $active]);
    exit;
}

if ($action === 'list') {
    echo json_encode(['success' => true, 'data' => array_values($departments)]);
    exit;
}

if ($action === 'add') {
    $name = $normalize((string)($_POST['name'] ?? ''));
    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Department name is required.']);
        exit;
    }

    foreach ($departments as $dep) {
        if (strcasecmp((string)($dep['name'] ?? ''), $name) === 0) {
            echo json_encode(['success' => false, 'message' => 'Department already exists.']);
            exit;
        }
    }

    $departments[] = ['name' => $name, 'status' => 'Active'];

    if (!$saveDepartments($departments, $config)) {
        echo json_encode(['success' => false, 'message' => 'Unable to save department.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Department added successfully.']);
    exit;
}

if ($action === 'edit') {
    $oldName = trim((string)($_POST['oldName'] ?? ''));
    $newName = $normalize((string)($_POST['newName'] ?? ''));

    if ($oldName === '' || $newName === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid department name.']);
        exit;
    }

    foreach ($departments as $dep) {
        if (strcasecmp((string)($dep['name'] ?? ''), $newName) === 0 && strcasecmp((string)($dep['name'] ?? ''), $oldName) !== 0) {
            echo json_encode(['success' => false, 'message' => 'Department already exists.']);
            exit;
        }
    }

    $updated = false;
    foreach ($departments as &$dep) {
        if (strcasecmp((string)($dep['name'] ?? ''), $oldName) === 0) {
            $dep['name'] = $newName;
            $updated = true;
            break;
        }
    }
    unset($dep);

    if (!$updated) {
        echo json_encode(['success' => false, 'message' => 'Department not found.']);
        exit;
    }

    if (!$saveDepartments($departments, $config)) {
        echo json_encode(['success' => false, 'message' => 'Unable to update department.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Department updated successfully.']);
    exit;
}

if ($action === 'toggle_status') {
    $name = trim((string)($_POST['name'] ?? ''));
    $status = trim((string)($_POST['status'] ?? 'Active'));
    $status = strcasecmp($status, 'Inactive') === 0 ? 'Inactive' : 'Active';

    if ($name === '') {
        echo json_encode(['success' => false, 'message' => 'Department name is required.']);
        exit;
    }

    $updated = false;
    foreach ($departments as &$dep) {
        if (strcasecmp((string)($dep['name'] ?? ''), $name) === 0) {
            $dep['status'] = $status;
            $updated = true;
            break;
        }
    }
    unset($dep);

    if (!$updated) {
        echo json_encode(['success' => false, 'message' => 'Department not found.']);
        exit;
    }

    if (!$saveDepartments($departments, $config)) {
        echo json_encode(['success' => false, 'message' => 'Unable to update status.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Department status updated successfully.']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action.']);
?>