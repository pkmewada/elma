<?php

include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/permission-helper.php';

requireRoutePermission('/route-setup', 'canView');

if (!isLoggedInUserSuperAdmin()) {
    header('Location: ' . BASE_URL . '/permission-denied?from=' . urlencode('/route-setup'));
    exit;
}

$projectRoot = realpath(dirname(__DIR__));
$allowedLayouts = ['admin', 'employee', 'public'];
$allowedHttpMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'ANY'];
$permissionTypeLabels = [
    'canAdd' => 'Add',
    'canEdit' => 'Edit',
    'canDelete' => 'Delete',
    'canApprove' => 'Approve',
    'special' => 'Special Action',
];
$allowedPermissionTypes = array_keys($permissionTypeLabels);
$successMessage = '';
$errorMessage = '';
$editingRoute = null;
$editingPermissionAction = null;
$permissionActions = [];

function routeSetupValue(array $source, string $key, string $default = ''): string
{
    return trim((string)($source[$key] ?? $default));
}

function routeSetupNormalizePath(string $routePath): string
{
    $routePath = '/' . ltrim(trim($routePath), '/');

    return rtrim($routePath, '/') ?: '/';
}

function routeSetupValidatePageFile(string $pageFile, string $projectRoot): ?string
{
    $pageFile = '/' . ltrim(trim($pageFile), '/');

    if (!preg_match('/\.php$/i', $pageFile)) {
        return 'Page file must be a PHP file.';
    }

    if (strpos($pageFile, '..') !== false) {
        return 'Page file path is invalid.';
    }

    $fullPath = realpath($projectRoot . $pageFile);

    if ($fullPath === false || strpos($fullPath, $projectRoot) !== 0 || !is_file($fullPath)) {
        return 'Page file does not exist inside this project.';
    }

    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = routeSetupValue($_POST, 'action', 'save');

    if ($action === 'save') {
        $routeId = (int)($_POST['routeId'] ?? 0);
        $routeTitle = routeSetupValue($_POST, 'routeTitle');
        $routePath = routeSetupNormalizePath(routeSetupValue($_POST, 'routePath'));
        $pageFile = '/' . ltrim(routeSetupValue($_POST, 'pageFile'), '/');
        $moduleName = routeSetupValue($_POST, 'moduleName');
        $layoutType = routeSetupValue($_POST, 'layoutType', 'admin');
        $sortOrder = (int)($_POST['sortOrder'] ?? 0);
        $isPublic = isset($_POST['isPublic']) ? 1 : 0;
        $isMenuVisible = isset($_POST['isMenuVisible']) ? 1 : 0;
        $isActive = isset($_POST['isActive']) ? 1 : 0;

        if ($routeTitle === '') {
            $errorMessage = 'Page title is required.';
        } elseif (!preg_match('/^\/[a-z0-9][a-z0-9\-\/]*$/', $routePath)) {
            $errorMessage = 'Route path must start with / and use lowercase letters, numbers, dashes, or slashes.';
        } elseif (strpos($routePath, '.php') !== false) {
            $errorMessage = 'Route path should be clean, without .php.';
        } elseif ($pageFile === '/') {
            $errorMessage = 'Page file is required.';
        } elseif (!in_array($layoutType, $allowedLayouts, true)) {
            $errorMessage = 'Invalid layout type.';
        } else {
            $fileError = routeSetupValidatePageFile($pageFile, $projectRoot);

            if ($fileError !== null) {
                $errorMessage = $fileError;
            }
        }

        if ($errorMessage === '') {
            $duplicateStmt = $con->prepare("
                SELECT id
                FROM routesMaster
                WHERE routePath = ?
                AND id <> ?
                LIMIT 1
            ");

            $duplicateStmt->bind_param('si', $routePath, $routeId);
            $duplicateStmt->execute();
            $duplicate = $duplicateStmt->get_result()->fetch_assoc();

            if ($duplicate) {
                $errorMessage = 'This route path already exists.';
            }
        }

        if ($errorMessage === '') {
            if ($routePath === '/route-setup') {
                $layoutType = 'admin';
                $isPublic = 0;
                $isMenuVisible = 1;
                $isActive = 1;
            }

            $moduleName = $moduleName !== '' ? $moduleName : null;

            if ($routeId > 0) {
                $stmt = $con->prepare("
                    UPDATE routesMaster
                    SET
                        routePath = ?,
                        pageFile = ?,
                        routeTitle = ?,
                        moduleName = ?,
                        layoutType = ?,
                        isPublic = ?,
                        isMenuVisible = ?,
                        isActive = ?,
                        sortOrder = ?
                    WHERE id = ?
                    LIMIT 1
                ");

                $stmt->bind_param(
                    'sssssiiiii',
                    $routePath,
                    $pageFile,
                    $routeTitle,
                    $moduleName,
                    $layoutType,
                    $isPublic,
                    $isMenuVisible,
                    $isActive,
                    $sortOrder,
                    $routeId
                );
                $stmt->execute();

                $successMessage = 'Route updated successfully.';
            } else {
                $stmt = $con->prepare("
                    INSERT INTO routesMaster
                        (
                            routePath,
                            pageFile,
                            routeTitle,
                            moduleName,
                            layoutType,
                            isPublic,
                            isMenuVisible,
                            isActive,
                            sortOrder
                        )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    'sssssiiii',
                    $routePath,
                    $pageFile,
                    $routeTitle,
                    $moduleName,
                    $layoutType,
                    $isPublic,
                    $isMenuVisible,
                    $isActive,
                    $sortOrder
                );
                $stmt->execute();

                $successMessage = 'Route created successfully.';
            }
        }
    } elseif ($action === 'savePermissionAction') {
        $routeId = (int)($_POST['routeId'] ?? 0);
        $permissionActionId = (int)($_POST['permissionActionId'] ?? 0);
        $actionLabel = routeSetupValue($_POST, 'actionLabel');
        $actionKey = strtolower(routeSetupValue($_POST, 'actionKey'));
        $permissionType = routeSetupValue($_POST, 'permissionType', 'special');
        $buttonSelector = routeSetupValue($_POST, 'buttonSelector');
        $apiEndpoint = routeSetupValue($_POST, 'apiEndpoint');
        $httpMethod = strtoupper(routeSetupValue($_POST, 'httpMethod'));
        $sortOrder = (int)($_POST['actionSortOrder'] ?? 0);
        $isActive = isset($_POST['actionIsActive']) ? 1 : 0;

        if ($apiEndpoint !== '') {
            $apiEndpoint = '/' . ltrim($apiEndpoint, '/');
        } else {
            $httpMethod = '';
        }

        if ($routeId <= 0) {
            $errorMessage = 'Please select a valid route.';
        } elseif ($actionLabel === '') {
            $errorMessage = 'Button action label is required.';
        } elseif (!preg_match('/^[a-z][a-z0-9_]*$/', $actionKey)) {
            $errorMessage = 'Action key must use lowercase letters, numbers, and underscores.';
        } elseif (!in_array($permissionType, $allowedPermissionTypes, true)) {
            $errorMessage = 'Please select a valid permission type.';
        } elseif ($permissionActionId <= 0 && $permissionType !== 'special') {
            // Add/Edit/Delete/Approve are the page checkboxes in Permission Setup;
            // new registrations are only for genuinely separate operations.
            $errorMessage = 'Only Special Actions can be registered. Standard operations use the page Add/Edit/Delete/Approve permission.';
        } elseif (strlen($buttonSelector) > 255) {
            $errorMessage = 'Button selector cannot be longer than 255 characters.';
        } elseif (
            $apiEndpoint !== ''
            && (
                strpos($apiEndpoint, '..') !== false
                || !preg_match('#^/api/[a-zA-Z0-9._/-]+\.php$#', $apiEndpoint)
            )
        ) {
            $errorMessage = 'API endpoint must be a valid /api/example.php path.';
        } elseif ($apiEndpoint !== '' && !in_array($httpMethod, $allowedHttpMethods, true)) {
            $errorMessage = 'Please select a valid HTTP method.';
        }

        if ($errorMessage === '' && $apiEndpoint !== '') {
            $apiRoot = realpath($projectRoot . '/api');
            $apiFile = realpath($projectRoot . $apiEndpoint);

            if (
                $apiRoot === false
                || $apiFile === false
                || strpos($apiFile, $apiRoot . DIRECTORY_SEPARATOR) !== 0
                || !is_file($apiFile)
            ) {
                $errorMessage = 'The selected API endpoint file does not exist.';
            }
        }

        if ($errorMessage === '') {
            $routeStmt = $con->prepare("
                SELECT id
                FROM routesMaster
                WHERE id = ?
                LIMIT 1
            ");
            $routeStmt->bind_param('i', $routeId);
            $routeStmt->execute();
            $routeExists = $routeStmt->get_result()->fetch_assoc();
            $routeStmt->close();

            if (!$routeExists) {
                $errorMessage = 'Selected route was not found.';
            }
        }

        if ($errorMessage === '' && $permissionActionId > 0) {
            $existingActionStmt = $con->prepare("
                SELECT actionKey
                FROM permissionActions
                WHERE id = ?
                AND routeId = ?
                LIMIT 1
            ");
            $existingActionStmt->bind_param('ii', $permissionActionId, $routeId);
            $existingActionStmt->execute();
            $existingAction = $existingActionStmt->get_result()->fetch_assoc();
            $existingActionStmt->close();

            if (!$existingAction) {
                $errorMessage = 'Button action was not found for this route.';
            } else {
                $actionKey = (string)$existingAction['actionKey'];
            }
        }

        if ($errorMessage === '') {
            $duplicateStmt = $con->prepare("
                SELECT id
                FROM permissionActions
                WHERE routeId = ?
                AND actionKey = ?
                AND id <> ?
                LIMIT 1
            ");
            $duplicateStmt->bind_param('isi', $routeId, $actionKey, $permissionActionId);
            $duplicateStmt->execute();
            $duplicateAction = $duplicateStmt->get_result()->fetch_assoc();
            $duplicateStmt->close();

            if ($duplicateAction) {
                $errorMessage = 'This action key already exists for the selected route.';
            }
        }

        if ($errorMessage === '' && $apiEndpoint !== '') {
            $endpointStmt = $con->prepare("
                SELECT id
                FROM permissionActions
                WHERE apiEndpoint = ?
                AND id <> ?
                AND (
                    httpMethod = ?
                    OR httpMethod = 'ANY'
                    OR ? = 'ANY'
                )
                LIMIT 1
            ");
            $endpointStmt->bind_param(
                'siss',
                $apiEndpoint,
                $permissionActionId,
                $httpMethod,
                $httpMethod
            );
            $endpointStmt->execute();
            $duplicateEndpoint = $endpointStmt->get_result()->fetch_assoc();
            $endpointStmt->close();

            if ($duplicateEndpoint) {
                $errorMessage = 'This API endpoint and method are already assigned to another action.';
            }
        }

        if ($errorMessage === '') {
            if ($permissionActionId > 0) {
                $stmt = $con->prepare("
                    UPDATE permissionActions
                    SET
                        actionKey = ?,
                        actionLabel = ?,
                        permissionType = ?,
                        buttonSelector = ?,
                        apiEndpoint = ?,
                        httpMethod = ?,
                        isActive = ?,
                        sortOrder = ?
                    WHERE id = ?
                    AND routeId = ?
                    LIMIT 1
                ");
                $stmt->bind_param(
                    'ssssssiiii',
                    $actionKey,
                    $actionLabel,
                    $permissionType,
                    $buttonSelector,
                    $apiEndpoint,
                    $httpMethod,
                    $isActive,
                    $sortOrder,
                    $permissionActionId,
                    $routeId
                );
                $stmt->execute();
                $stmt->close();

                $successMessage = 'Button/API mapping updated successfully.';
            } else {
                $stmt = $con->prepare("
                    INSERT INTO permissionActions
                        (
                            routeId,
                            actionKey,
                            actionLabel,
                            permissionType,
                            buttonSelector,
                            apiEndpoint,
                            httpMethod,
                            isActive,
                            sortOrder
                        )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    'issssssii',
                    $routeId,
                    $actionKey,
                    $actionLabel,
                    $permissionType,
                    $buttonSelector,
                    $apiEndpoint,
                    $httpMethod,
                    $isActive,
                    $sortOrder
                );
                $stmt->execute();
                $stmt->close();

                $successMessage = 'Button/API mapping created successfully.';
            }
        }
    }
}

$editId = (int)($_GET['edit'] ?? 0);

if ($editId > 0) {
    $editStmt = $con->prepare("
        SELECT
            id,
            routePath,
            pageFile,
            routeTitle,
            moduleName,
            layoutType,
            isPublic,
            isMenuVisible,
            isActive,
            sortOrder
        FROM routesMaster
        WHERE id = ?
        LIMIT 1
    ");

    $editStmt->bind_param('i', $editId);
    $editStmt->execute();
    $editingRoute = $editStmt->get_result()->fetch_assoc() ?: null;

    if ($editingRoute) {
        $actionStmt = $con->prepare("
            SELECT
                id,
                actionKey,
                actionLabel,
                permissionType,
                buttonSelector,
                apiEndpoint,
                httpMethod,
                isActive,
                sortOrder
            FROM permissionActions
            WHERE routeId = ?
            ORDER BY sortOrder ASC, actionLabel ASC
        ");
        $actionStmt->bind_param('i', $editId);
        $actionStmt->execute();
        $actionResult = $actionStmt->get_result();

        while ($permissionAction = $actionResult->fetch_assoc()) {
            $permissionActions[] = $permissionAction;
        }

        $actionStmt->close();

        $actionEditId = (int)($_GET['actionEdit'] ?? 0);

        if ($actionEditId > 0) {
            $editActionStmt = $con->prepare("
                SELECT
                    id,
                    actionKey,
                    actionLabel,
                    permissionType,
                    buttonSelector,
                    apiEndpoint,
                    httpMethod,
                    isActive,
                    sortOrder
                FROM permissionActions
                WHERE id = ?
                AND routeId = ?
                LIMIT 1
            ");
            $editActionStmt->bind_param('ii', $actionEditId, $editId);
            $editActionStmt->execute();
            $editingPermissionAction = $editActionStmt->get_result()->fetch_assoc() ?: null;
            $editActionStmt->close();
        }
    }
}

$modules = [];
$moduleResult = mysqli_query($con, "
    SELECT DISTINCT moduleName
    FROM routesMaster
    WHERE moduleName IS NOT NULL
    AND TRIM(moduleName) <> ''
    ORDER BY moduleName ASC
");

while ($module = mysqli_fetch_assoc($moduleResult)) {
    $modules[] = $module['moduleName'];
}

$routes = [];
$routeResult = mysqli_query($con, "
    SELECT
        id,
        routePath,
        pageFile,
        routeTitle,
        moduleName,
        layoutType,
        isPublic,
        isMenuVisible,
        isActive,
        sortOrder,
        (
            SELECT COUNT(*)
            FROM permissionActions pa
            WHERE pa.routeId = routesMaster.id
            AND pa.isActive = 1
        ) AS actionCount
    FROM routesMaster
    ORDER BY layoutType ASC, moduleName ASC, sortOrder ASC, routeTitle ASC
");

while ($route = mysqli_fetch_assoc($routeResult)) {
    $routes[] = $route;
}

$formRoute = $editingRoute ?: [
    'id' => 0,
    'routePath' => '',
    'pageFile' => '',
    'routeTitle' => '',
    'moduleName' => '',
    'layoutType' => 'admin',
    'isPublic' => 0,
    'isMenuVisible' => 1,
    'isActive' => 1,
    'sortOrder' => 0,
];

$formPermissionAction = $editingPermissionAction ?: [
    'id' => 0,
    'actionKey' => '',
    'actionLabel' => '',
    'permissionType' => 'special',
    'buttonSelector' => '',
    'apiEndpoint' => '',
    'httpMethod' => '',
    'isActive' => 1,
    'sortOrder' => 0,
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Route / Page Setup</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL; ?>/dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Route / Page Setup</li>
                </ol>
            </div>

            <?php if ($editingRoute): ?>
                <a href="<?= BASE_URL; ?>/route-setup" class="btn btn-light">
                    <i class="ri-add-line me-1"></i>
                    New Route
                </a>
            <?php endif; ?>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage !== ''): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xxl-12 col-xl-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <div class="card-title">
                            <?= $editingRoute ? 'Edit Route' : 'Add Route'; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="post"><?php require_once __DIR__ . '/../includes/Csrf.php'; echo getCsrfInput(); ?>
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="routeId" value="<?= (int)$formRoute['id']; ?>">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Page Title</label>
                                    <input
                                        type="text"
                                        name="routeTitle"
                                        class="form-control"
                                        value="<?= htmlspecialchars((string)$formRoute['routeTitle'], ENT_QUOTES, 'UTF-8'); ?>"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Module Name</label>
                                    <input
                                        type="text"
                                        name="moduleName"
                                        class="form-control"
                                        list="routeModuleList"
                                        value="<?= htmlspecialchars((string)($formRoute['moduleName'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="Example: Lead Management"
                                    >
                                    <datalist id="routeModuleList">
                                        <?php foreach ($modules as $moduleName): ?>
                                            <option value="<?= htmlspecialchars((string)$moduleName, ENT_QUOTES, 'UTF-8'); ?>"></option>
                                        <?php endforeach; ?>
                                    </datalist>
                                    <div class="form-text">
                                        Sidebar group. Admin menu: CRM, Lead Management, HRMS, Setup, Social Media, Automation.
                                        Employee menu: Employee Panel, Lead Management, HRMS. The page title is the menu label
                                        and Sort Order sets its position.
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Route Path</label>
                                    <input
                                        type="text"
                                        name="routePath"
                                        class="form-control"
                                        value="<?= htmlspecialchars((string)$formRoute['routePath'], ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="/lead-record"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Page File</label>
                                    <input
                                        type="text"
                                        name="pageFile"
                                        class="form-control"
                                        value="<?= htmlspecialchars((string)$formRoute['pageFile'], ENT_QUOTES, 'UTF-8'); ?>"
                                        placeholder="/pages/lead-record.php"
                                        required
                                    >
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Layout Type</label>
                                    <select name="layoutType" class="form-select">
                                        <?php foreach ($allowedLayouts as $layout): ?>
                                            <option value="<?= $layout; ?>"
                                                <?= $formRoute['layoutType'] === $layout ? 'selected' : ''; ?>>
                                                <?= ucfirst($layout); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Sort Order</label>
                                    <input
                                        type="number"
                                        name="sortOrder"
                                        class="form-control"
                                        value="<?= (int)$formRoute['sortOrder']; ?>"
                                    >
                                </div>

                                <div class="col-12">
                                    <div class="d-flex flex-wrap gap-4">
                                        <label class="form-check mb-0">
                                            <input
                                                type="checkbox"
                                                name="isMenuVisible"
                                                value="1"
                                                class="form-check-input"
                                                <?= (int)$formRoute['isMenuVisible'] === 1 ? 'checked' : ''; ?>
                                            >
                                            <span class="form-check-label">Show in menu when allowed</span>
                                        </label>

                                        <label class="form-check mb-0">
                                            <input
                                                type="checkbox"
                                                name="isPublic"
                                                value="1"
                                                class="form-check-input"
                                                <?= (int)$formRoute['isPublic'] === 1 ? 'checked' : ''; ?>
                                            >
                                            <span class="form-check-label">Public route</span>
                                        </label>

                                        <label class="form-check mb-0">
                                            <input
                                                type="checkbox"
                                                name="isActive"
                                                value="1"
                                                class="form-check-input"
                                                <?= (int)$formRoute['isActive'] === 1 ? 'checked' : ''; ?>
                                            >
                                            <span class="form-check-label">Active</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary mt-3">
                                <i class="ri-save-line me-1"></i>
                                <?= $editingRoute ? 'Update Route' : 'Save Route'; ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($editingRoute): ?>
            <div class="row">
                <div class="col-xxl-12 col-xl-12">
                    <div class="card custom-card">
                        <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <div class="card-title">Button / API Mappings</div>
                                <div class="text-muted small mt-1">
                                    <?= htmlspecialchars((string)$editingRoute['routeTitle'], ENT_QUOTES, 'UTF-8'); ?>
                                    ·
                                    <?= htmlspecialchars((string)$editingRoute['routePath'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <?php if ($editingPermissionAction): ?>
                                <a
                                    href="<?= BASE_URL; ?>/route-setup?edit=<?= (int)$editingRoute['id']; ?>"
                                    class="btn btn-sm btn-light"
                                >
                                    Add New Mapping
                                </a>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <div class="alert alert-info">
                                Normal Add, Edit, Delete and Approve operations need no registration: grant them with the page checkboxes in Permission Setup and check them in the API with requireApiPermission(). Register a Special Action only for an operation that needs its own permission (for example Import Leads or Assign Asset).
                            </div>

                            <form method="post" action="<?= BASE_URL; ?>/route-setup?edit=<?= (int)$editingRoute['id']; ?>"><?php require_once __DIR__ . '/../includes/Csrf.php'; echo getCsrfInput(); ?>
                                <input type="hidden" name="action" value="savePermissionAction">
                                <input type="hidden" name="routeId" value="<?= (int)$editingRoute['id']; ?>">
                                <input type="hidden" name="permissionActionId" value="<?= (int)$formPermissionAction['id']; ?>">

                                <div class="row g-3 align-items-end">
                                    <div class="col-lg-3 col-md-6">
                                        <label for="actionLabel" class="form-label">Button Label</label>
                                        <input
                                            type="text"
                                            id="actionLabel"
                                            name="actionLabel"
                                            class="form-control"
                                            value="<?= htmlspecialchars((string)$formPermissionAction['actionLabel'], ENT_QUOTES, 'UTF-8'); ?>"
                                            placeholder="Example: Assign Asset"
                                            required
                                        >
                                        <div class="form-text">Write Exact Button Label Like "Assign Asset" Only.</div>
                                    </div>

                                    <div class="col-lg-3 col-md-6">
                                        <label for="actionKey" class="form-label">Action Key</label>
                                        <input type="text" id="actionKey" name="actionKey" class="form-control" value="<?= htmlspecialchars((string)$formPermissionAction['actionKey'], ENT_QUOTES, 'UTF-8'); ?>"
                                            placeholder="Example: assign_asset" pattern="[a-z][a-z0-9_]*" <?= $editingPermissionAction ? 'readonly' : ''; ?>  required>
                                        <div class="form-text">
                                            <?= $editingPermissionAction
                                                ? 'The action key cannot be changed after creation.'
                                                : 'Lowercase letters, numbers, and underscores only.'; ?>
                                        </div>
                                    </div>

                                    <div class="col-lg-2 col-md-6">
                                        <label for="permissionType" class="form-label">Permission Type</label>
                                        <select id="permissionType" name="permissionType" class="form-select" required>
                                            <?php foreach ($permissionTypeLabels as $permissionType => $permissionLabel): ?>
                                                <?php
                                                    // New registrations are Special Actions only; an existing
                                                    // standard mapping keeps its own type.
                                                    if (
                                                        $permissionType !== 'special'
                                                        && (string)($formPermissionAction['permissionType'] ?? 'special') !== $permissionType
                                                    ) {
                                                        continue;
                                                    }
                                                ?>
                                                <option
                                                    value="<?= htmlspecialchars($permissionType, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?= (string)($formPermissionAction['permissionType'] ?? 'special') === $permissionType ? 'selected' : ''; ?>
                                                >
                                                    <?= htmlspecialchars($permissionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Existing standard mappings use the page checkbox directly.</div>
                                    </div>

                                    <div class="col-lg-2 col-md-6">
                                        <label for="buttonSelector" class="form-label">Button Selector <span class="text-muted">(optional)</span></label>
                                        <input type="text" id="buttonSelector" name="buttonSelector"
                                            class="form-control" value="<?= htmlspecialchars((string)($formPermissionAction['buttonSelector'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            placeholder="Example: #addOvertimeBtn or .assign-asset-btn">
                                        <div class="form-text">Leave blank to match the exact visible button label.</div>
                                    </div>

                                    <div class="col-lg-1 col-md-6">
                                        <label for="actionSortOrder" class="form-label">Sort Order</label>
                                        <input type="number" id="actionSortOrder" name="actionSortOrder" class="form-control"
                                            value="<?= (int)$formPermissionAction['sortOrder']; ?>">
                                    </div>

                                    <div class="col-lg-1 col-md-6">
                                        <label class="form-check mb-2">
                                            <input type="checkbox" name="actionIsActive" value="1" class="form-check-input"
                                                <?= (int)$formPermissionAction['isActive'] === 1 ? 'checked' : ''; ?>
                                            >
                                            <span class="form-check-label">Active</span>
                                        </label>
                                    </div>

                                    <div class="col-lg-8 col-md-8">
                                        <label for="apiEndpoint" class="form-label">Protected API Endpoint <span class="text-muted">(optional)</span></label>
                                        <input
                                            type="text"
                                            id="apiEndpoint"
                                            name="apiEndpoint"
                                            class="form-control"
                                            value="<?= htmlspecialchars((string)($formPermissionAction['apiEndpoint'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            placeholder="Example: /api/leads/deleteLead.php"
                                        >
                                        <div class="form-text">When configured, the central gateway protects this endpoint automatically.</div>
                                    </div>

                                    <div class="col-lg-4 col-md-4">
                                        <label for="httpMethod" class="form-label">HTTP Method</label>
                                        <select id="httpMethod" name="httpMethod" class="form-select">
                                            <option value="">Select Method</option>
                                            <?php foreach ($allowedHttpMethods as $method): ?>
                                                <option
                                                    value="<?= $method; ?>"
                                                    <?= (string)($formPermissionAction['httpMethod'] ?? '') === $method ? 'selected' : ''; ?>
                                                >
                                                    <?= $method; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary mt-3">
                                    <i class="ri-save-line me-1"></i>
                                    <?= $editingPermissionAction ? 'Update Mapping' : 'Add Mapping'; ?>
                                </button>
                            </form>

                            <div class="table-responsive mt-4">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Button Label</th>
                                            <th>Action Key</th>
                                            <th>Permission Type</th>
                                            <th>Button Match</th>
                                            <th>API Guard</th>
                                            <th>Sort Order</th>
                                            <th>Status</th>
                                            <th class="text-end">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!$permissionActions): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted py-4">
                                                    No button or API mappings registered for this route.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($permissionActions as $permissionAction): ?>
                                                <tr>
                                                    <td class="fw-semibold">
                                                        <?= htmlspecialchars((string)$permissionAction['actionLabel'], ENT_QUOTES, 'UTF-8'); ?>
                                                    </td>
                                                    <td>
                                                        <code><?= htmlspecialchars((string)$permissionAction['actionKey'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                    </td>
                                                    <td>
                                                        <?php
                                                            $permissionType = (string)($permissionAction['permissionType'] ?? 'special');
                                                            $permissionLabel = $permissionTypeLabels[$permissionType] ?? 'Special Action';
                                                        ?>
                                                        <span class="badge <?= $permissionType === 'special' ? 'bg-warning-transparent' : 'bg-primary-transparent'; ?>">
                                                            <?= htmlspecialchars($permissionLabel, ENT_QUOTES, 'UTF-8'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if (trim((string)($permissionAction['buttonSelector'] ?? '')) !== ''): ?>
                                                            <code><?= htmlspecialchars((string)$permissionAction['buttonSelector'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                        <?php else: ?>
                                                            <span class="text-muted">Exact label</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (trim((string)($permissionAction['apiEndpoint'] ?? '')) !== ''): ?>
                                                            <code><?= htmlspecialchars((string)$permissionAction['apiEndpoint'], ENT_QUOTES, 'UTF-8'); ?></code>
                                                            <span class="badge bg-info-transparent ms-1">
                                                                <?= htmlspecialchars((string)$permissionAction['httpMethod'], ENT_QUOTES, 'UTF-8'); ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">Not mapped</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= (int)$permissionAction['sortOrder']; ?></td>
                                                    <td>
                                                        <span class="badge <?= (int)$permissionAction['isActive'] === 1 ? 'bg-success-transparent' : 'bg-danger-transparent'; ?>">
                                                            <?= (int)$permissionAction['isActive'] === 1 ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-end">
                                                        <a
                                                            href="<?= BASE_URL; ?>/route-setup?edit=<?= (int)$editingRoute['id']; ?>&actionEdit=<?= (int)$permissionAction['id']; ?>"
                                                            class="btn btn-sm btn-light"
                                                            title="Edit button action"
                                                        >
                                                            <i class="ri-edit-line"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xxl-12 col-xl-12">
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="card-title">Registered Routes</div>
                        <span class="badge bg-light text-muted"><?= count($routes); ?> routes</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Page</th>
                                        <th>Module</th>
                                        <th>Layout</th>
                                        <th class="text-center">Menu</th>
                                        <th class="text-center">Public</th>
                                        <th class="text-center">Action Mappings</th>
                                        <th class="text-center">Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($routes as $route): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">
                                                    <?= htmlspecialchars((string)$route['routeTitle'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= htmlspecialchars((string)$route['routePath'], ENT_QUOTES, 'UTF-8'); ?>
                                                    <br>
                                                    <?= htmlspecialchars((string)$route['pageFile'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars((string)($route['moduleName'] ?: 'Other'), ENT_QUOTES, 'UTF-8'); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info-transparent">
                                                    <?= htmlspecialchars((string)$route['layoutType'], ENT_QUOTES, 'UTF-8'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?= (int)$route['isMenuVisible'] === 1 ? 'Yes' : 'No'; ?>
                                            </td>
                                            <td class="text-center">
                                                <?= (int)$route['isPublic'] === 1 ? 'Yes' : 'No'; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary-transparent">
                                                    <?= (int)$route['actionCount']; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge <?= (int)$route['isActive'] === 1 ? 'bg-success-transparent' : 'bg-danger-transparent'; ?>">
                                                    <?= (int)$route['isActive'] === 1 ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <a
                                                    href="<?= BASE_URL; ?>/route-setup?edit=<?= (int)$route['id']; ?>"
                                                    class="btn btn-sm btn-light"
                                                >
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
