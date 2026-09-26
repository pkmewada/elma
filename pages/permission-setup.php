<?php

include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/basic-config.php';
require_once __DIR__ . '/../includes/permission-helper.php';

requireRoutePermission('/permission-setup', 'canView');

$config = getBasicConfig();
$roles = $config['organizationRoles'] ?? [];
$moduleNames = [];
$employees = [];

$moduleQuery = mysqli_query($con, "
    SELECT DISTINCT moduleName
    FROM routesMaster
    WHERE isActive = 1
    AND isPublic = 0
    ORDER BY moduleName ASC
");

while ($module = mysqli_fetch_assoc($moduleQuery)) {
    $moduleName = trim((string)($module['moduleName'] ?? ''));
    $moduleNames[$moduleName !== '' ? $moduleName : 'Other'] = true;
}

$moduleNames = array_keys($moduleNames);

$employeeQuery = mysqli_query($con, "
    SELECT
        id,
        fullName,
        emailAddress,
        designationName,
        departmentName
    FROM employeeusers
    WHERE employmentStatus = 'Active'
    ORDER BY fullName ASC
");

while ($employee = mysqli_fetch_assoc($employeeQuery)) {
    $employees[] = $employee;
}

/*
 * Role list = Basic Setup roles, plus any active employee designation that is
 * missing from Basic Setup (the resolver matches permissions on
 * employeeusers.designationName, so those must stay manageable and visible).
 */
$roleEmployeeCounts = [];

foreach ($employees as $employee) {
    $designation = trim((string)($employee['designationName'] ?? ''));

    if ($designation !== '') {
        $key = strtolower($designation);
        $roleEmployeeCounts[$key] = ($roleEmployeeCounts[$key] ?? 0) + 1;
    }
}

$configuredRoleKeys = [];

foreach ($roles as $role) {
    $configuredRoleKeys[strtolower(trim((string)$role))] = true;
}

$unconfiguredDesignations = [];

foreach ($employees as $employee) {
    $designation = trim((string)($employee['designationName'] ?? ''));
    $key = strtolower($designation);

    if ($designation !== '' && !isset($configuredRoleKeys[$key])) {
        $configuredRoleKeys[$key] = true;
        $unconfiguredDesignations[] = $designation;
        $roles[] = $designation;
    }
}

$successMessage = '';
$errorMessage = '';

$selectedRole = trim((string)($_POST['roleName'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveRolePermissions'])) {

    requireRoutePermission('/permission-setup', 'canEdit');

    $permissions = $_POST['permissions'] ?? [];
    $permissionActionIds = $_POST['permissionActionIds'] ?? [];
    $actionPermissions = $_POST['actionPermissions'] ?? [];

    if ($selectedRole === '') {
        $errorMessage = 'Please select role.';
    } else {

        foreach ($permissions as $routeId => $actions) {

            $routeId = (int)$routeId;

            $canView = isset($actions['canView']) ? 1 : 0;
            $canAdd = isset($actions['canAdd']) ? 1 : 0;
            $canEdit = isset($actions['canEdit']) ? 1 : 0;
            $canDelete = isset($actions['canDelete']) ? 1 : 0;
            $canApprove = isset($actions['canApprove']) ? 1 : 0;
            $canExport = 0;

            // Nothing granted: a missing row already means deny, so keep none.
            if (!$canView && !$canAdd && !$canEdit && !$canDelete && !$canApprove) {
                $stmt = $con->prepare("
                    DELETE FROM rolePermissions
                    WHERE roleName = ?
                    AND routeId = ?
                ");
                $stmt->bind_param("si", $selectedRole, $routeId);
                $stmt->execute();
                continue;
            }

            $stmt = $con->prepare("
                INSERT INTO rolePermissions
                (
                    roleName,
                    routeId,
                    canView,
                    canAdd,
                    canEdit,
                    canDelete,
                    canApprove,
                    canExport
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    canView = VALUES(canView),
                    canAdd = VALUES(canAdd),
                    canEdit = VALUES(canEdit),
                    canDelete = VALUES(canDelete),
                    canApprove = VALUES(canApprove),
                    canExport = VALUES(canExport)
            ");

            $stmt->bind_param(
                "siiiiiii",
                $selectedRole,
                $routeId,
                $canView,
                $canAdd,
                $canEdit,
                $canDelete,
                $canApprove,
                $canExport
            );

            $stmt->execute();
        }

        foreach ($permissionActionIds as $actionId) {
            $actionId = (int)$actionId;

            if ($actionId <= 0) {
                continue;
            }

            $canAccess = isset($actionPermissions[$actionId]) ? 1 : 0;

            if (!$canAccess) {
                $stmt = $con->prepare("
                    DELETE FROM roleActionPermissions
                    WHERE roleName = ?
                    AND actionId = ?
                ");
                $stmt->bind_param('si', $selectedRole, $actionId);
                $stmt->execute();
                $stmt->close();
                continue;
            }

            $stmt = $con->prepare("
                INSERT INTO roleActionPermissions (roleName, actionId, canAccess)
                SELECT ?, id, ?
                FROM permissionActions
                WHERE id = ?
                AND isActive = 1
                AND permissionType = 'special'
                ON DUPLICATE KEY UPDATE
                    canAccess = VALUES(canAccess)
            ");

            $stmt->bind_param('sii', $selectedRole, $canAccess, $actionId);
            $stmt->execute();
            $stmt->close();
        }

        $successMessage = 'Role permissions updated successfully.';
    }
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Permission Setup</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="<?= BASE_URL; ?>/dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Permission Setup</li>
                </ol>
            </div>
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

        <?php if ($unconfiguredDesignations): ?>
        <div class="alert alert-warning">
            Active employee designations not listed as roles in Basic Setup:
            <strong><?= htmlspecialchars(implode(', ', $unconfiguredDesignations), ENT_QUOTES, 'UTF-8'); ?></strong>.
            They can still be given permissions here; add them in Basic Setup (or correct the employee's
            designation) so there is one role list.
        </div>
        <?php endif; ?>

        <div class="card custom-card permission-card">
            <div class="card-body">

                <ul class="nav nav-tabs permission-tabs mb-3" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="rolePermissionsTab" data-bs-toggle="tab"
                            data-bs-target="#rolePermissionsPane" type="button" role="tab"
                            aria-controls="rolePermissionsPane" aria-selected="true">
                            Role Permissions
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="employeeExceptionsTab" data-bs-toggle="tab"
                            data-bs-target="#employeeExceptionsPane" type="button" role="tab"
                            aria-controls="employeeExceptionsPane" aria-selected="false">
                            Employee Exceptions
                        </button>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="rolePermissionsPane" role="tabpanel"
                        aria-labelledby="rolePermissionsTab">
                        <div class="row align-items-end g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Select Role</label>
                                <select id="roleName" class="form-select">
                                    <option value="">Select Role</option>

                                    <?php foreach ($roles as $role): ?>
                                    <?php
                                        $roleCount = $roleEmployeeCounts[strtolower(trim((string)$role))] ?? 0;
                                        $roleSuffix = in_array($role, $unconfiguredDesignations, true)
                                            ? ' (not in Basic Setup)'
                                            : '';
                                    ?>
                                    <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>"
                                        <?= $selectedRole === $role ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($role . ' · ' . $roleCount . ' active' . $roleSuffix, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Select Page Type</label>
                                <select id="roleLayoutType" class="form-select">
                                    <option value="">All Pages</option>
                                    <option value="admin">Admin Pages</option>
                                    <option value="employee">Employee Pages</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Select Module</label>
                                <select id="moduleName" class="form-select">
                                    <option value="">All Modules</option>

                                    <?php foreach ($moduleNames as $moduleName): ?>
                                    <option value="<?= htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <div class="text-muted small">
                                    Choose Employee Pages to manage employee-panel access.
                                </div>
                            </div>
                        </div>

                        <div id="permissionLoader" class="d-none py-4 text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="text-muted mt-2">Loading permissions...</div>
                        </div>

                        <div id="permissionResult">
                            <div class="alert alert-info mb-0">
                                Please select a role to manage permissions.
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="employeeExceptionsPane" role="tabpanel"
                        aria-labelledby="employeeExceptionsTab">
                        <div class="row align-items-end g-3 mb-3">
                            <div class="col-md-5">
                                <label class="form-label">Select Employee</label>
                                <select id="employeePermissionUser" class="form-select">
                                    <option value="">Select Employee</option>

                                    <?php foreach ($employees as $employee): ?>
                                    <?php
                                            $employeeLabel = trim((string)$employee['fullName']);
                                            $designation = trim((string)($employee['designationName'] ?? ''));

                                            if ($designation !== '') {
                                                $employeeLabel .= ' - ' . $designation;
                                            }
                                        ?>
                                    <option value="<?= (int)$employee['id']; ?>">
                                        <?= htmlspecialchars($employeeLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Select Module</label>
                                <select id="employeeModuleName" class="form-select">
                                    <option value="">All Modules</option>

                                    <?php foreach ($moduleNames as $moduleName): ?>
                                    <option value="<?= htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'); ?>">
                                        <?= htmlspecialchars($moduleName, ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

    
                        </div>

                        <div id="employeePermissionLoader" class="d-none py-4 text-center">
                            <div class="spinner-border text-primary" role="status"></div>
                            <div class="text-muted mt-2">Loading exceptions...</div>
                        </div>

                        <div id="employeePermissionResult">
                            <div class="alert alert-info mb-0">
                                Please select an employee to manage exceptions.
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<style>
.permission-card {
    border: 1px solid #eef0f4;
    box-shadow: none;
}

.permission-tabs .nav-link {
    font-weight: 600;
}

.permission-table-wrap {
    border: 1px solid #eef0f4;
    border-radius: 8px;
    overflow: hidden;
}

.permission-table thead th {
    background: #f8f9fb;
    font-size: 12px;
    text-transform: uppercase;
    color: #6c757d;
    font-weight: 600;
    border-bottom: 1px solid #eef0f4;
}

.permission-table td,
.permission-table th {
    padding: 12px 14px;
}

.permission-table tbody tr:hover {
    background: whitesmoke;
}

.permission-table .permission-module-row td {
    background: #eef3ff;
    color: #1f3a8a;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
}

.permission-table .permission-module-row:hover {
    background: #eef3ff;
}

.permission-table .form-check-input {
    cursor: pointer;
}

.employee-exception-wrap {
    border: 1px solid #eef0f4;
    border-radius: 8px;
    overflow: hidden;
}

.employee-exception-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px;
    background: #f8f9fb;
    border-bottom: 1px solid #eef0f4;
}

.employee-exception-summary strong {
    color: #1f2937;
}

.employee-exception-module {
    padding: 12px 14px;
    background: #eef3ff;
    color: #1f3a8a;
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    border-top: 1px solid #eef0f4;
}

.employee-exception-row {
    display: grid;
    grid-template-columns: minmax(220px, 1.2fr) minmax(270px, 1fr) 150px minmax(360px, 1.4fr);
    gap: 14px;
    align-items: center;
    padding: 14px;
    border-top: 1px solid #eef0f4;
}

.employee-exception-row.is-custom {
    background: #fbfcff;
}

.exception-route-title {
    font-weight: 700;
    color: #111827;
}

.exception-route-path,
.exception-role-label {
    color: #6b7280;
    font-size: 12px;
}

.exception-actions {
    display: grid;
    grid-template-columns: repeat(6, minmax(70px, 1fr));
    gap: 8px;
}

.exception-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 32px;
    margin: 0;
    color: #4b5563;
    font-size: 12px;
}

.exception-role-default {
    display: grid;
    grid-template-columns: repeat(5, minmax(48px, 1fr));
    gap: 6px;
}

.role-default-pill {
    display: inline-flex;
    justify-content: center;
    padding: 5px 7px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
}

.role-default-pill.is-allowed {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
}

.permission-save-bar {
    display: flex;
    justify-content: flex-end;
    padding: 14px;
    border-top: 1px solid #eef0f4;
}

.employee-exception-row .form-check-input:disabled {
    opacity: .45;
}

.employee-special-actions {
    grid-column: 1 / -1;
    padding-top: 12px;
    border-top: 1px dashed #dfe3e8;
}

.employee-special-action {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) minmax(160px, auto) minmax(150px, auto);
    gap: 14px;
    align-items: center;
    padding: 8px 0;
}

.permission-special-action-row td {
    background: #fcfcfd;
}

@media (max-width: 1200px) {
    .employee-exception-row {
        grid-template-columns: 1fr;
    }

    .employee-special-action {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {

    .exception-actions,
    .exception-role-default {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

.form-check-input {
    width: 1.4em;
    height: 1.4em;
    background-color: var(--custom-white);
    border: 1px solid grey !important;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {

    const roleSelect = document.getElementById('roleName');
    const roleLayoutSelect = document.getElementById('roleLayoutType');
    const moduleSelect = document.getElementById('moduleName');
    const resultBox = document.getElementById('permissionResult');
    const loader = document.getElementById('permissionLoader');
    const employeeSelect = document.getElementById('employeePermissionUser');
    const employeeModuleSelect = document.getElementById('employeeModuleName');
    const employeeResultBox = document.getElementById('employeePermissionResult');
    const employeeLoader = document.getElementById('employeePermissionLoader');
    const permissionActions = [{
            key: 'canView',
            label: 'View'
        },
        {
            key: 'canAdd',
            label: 'Add'
        },
        {
            key: 'canEdit',
            label: 'Edit'
        },
        {
            key: 'canDelete',
            label: 'Delete'
        },
        {
            key: 'canApprove',
            label: 'Approve'
        }
    ];

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function isAllowed(value) {
        return parseInt(value || 0, 10) === 1;
    }

    function checked(value) {
        return isAllowed(value) ? 'checked' : '';
    }

    function showToast(type, message) {
        if (window.showToast) {
            window.showToast(type, message);
            return;
        }

        employeeResultBox.insertAdjacentHTML(
            'afterbegin',
            `<div class="alert alert-${type === 'success' ? 'success' : 'danger'}">${escapeHtml(message)}</div>`
        );
    }

    function loadRolePermissions(roleName, moduleName, layoutType) {

        if (!roleName) {
            resultBox.innerHTML = `
                <div class="alert alert-info mb-0">
                    Please select a role to manage permissions.
                </div>
            `;
            return;
        }

        loader.classList.remove('d-none');
        resultBox.innerHTML = '';

        fetch('<?= BASE_URL; ?>/api/permissions/getRolePermission.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'roleName=' + encodeURIComponent(roleName) +
                    '&moduleName=' + encodeURIComponent(moduleName || '') +
                    '&layoutType=' + encodeURIComponent(layoutType || '')
            })
            .then(response => response.json())
            .then(data => {
                loader.classList.add('d-none');

                if (data.success) {
                    resultBox.innerHTML = data.html;
                } else {
                    resultBox.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        ${data.message || 'Unable to load permissions.'}
                    </div>
                `;
                }
            })
            .catch(() => {
                loader.classList.add('d-none');
                resultBox.innerHTML = `
                <div class="alert alert-danger mb-0">
                    Something went wrong while loading permissions.
                </div>
            `;
            });
    }

    function roleDefaultPills(item) {
        return permissionActions.map(function(action) {
            const roleKey = 'role' + action.key.charAt(0).toUpperCase() + action.key.slice(1);
            const allowed = isAllowed(item[roleKey]);

            return `
                <span class="role-default-pill ${allowed ? 'is-allowed' : ''}">
                    ${escapeHtml(action.label)}
                </span>
            `;
        }).join('');
    }

    function permissionAllCheckbox(routeId, hasCustomPermission) {
        return `
            <input type="hidden" name="permissionRoutes[]" value="${routeId}">
            <label class="exception-action fw-semibold">
                <input
                    type="checkbox"
                    class="form-check-input employee-permission-all"
                    data-route-id="${routeId}"
                    ${hasCustomPermission ? '' : 'disabled'}
                >
                <span>All</span>
            </label>
        `;
    }

    function permissionCheckbox(routeId, action, value, hasCustomPermission) {
        return `
            <label class="exception-action">
                <input
                    type="checkbox"
                    class="form-check-input employee-permission-check"
                    name="permissions[${routeId}][${action.key}]"
                    value="1"
                    ${checked(value)}
                    ${hasCustomPermission ? '' : 'disabled'}
                >
                <span>${escapeHtml(action.label)}</span>
            </label>
        `;
    }

    function refreshEmployeeAllCheckbox(row) {
        const actionChecks = Array.from(row.querySelectorAll('.employee-permission-check'));
        const allCheck = row.querySelector('.employee-permission-all');

        if (!allCheck) {
            return;
        }

        allCheck.checked = actionChecks.length > 0 && actionChecks.every(function(checkbox) {
            return checkbox.checked;
        });
    }

    function syncEmployeeExceptionRow(row) {
        const toggle = row.querySelector('.employee-custom-toggle');
        const enabled = toggle && toggle.checked;

        row.classList.toggle('is-custom', enabled);

        row.querySelectorAll('.employee-permission-check, .employee-permission-all').forEach(function(
        checkbox) {
            checkbox.disabled = !enabled;
        });

        refreshEmployeeAllCheckbox(row);
    }

    function syncEmployeeSpecialAction(actionRow) {
        const toggle = actionRow.querySelector('.employee-action-custom-toggle');
        const accessCheckbox = actionRow.querySelector('.employee-action-access');
        const enabled = toggle && toggle.checked;

        actionRow.classList.toggle('is-custom', enabled);

        if (accessCheckbox) {
            accessCheckbox.disabled = !enabled;
        }
    }

    function renderSpecialActions(actions) {
        if (!Array.isArray(actions) || !actions.length) {
            return '';
        }

        const actionRows = actions.map(function(action) {
            const actionId = parseInt(action.actionId || 0, 10);
            const hasOverride = isAllowed(action.hasOverride);
            const roleAllowed = isAllowed(action.roleCanAccess);
            const canAccess = hasOverride ? isAllowed(action.userCanAccess) : roleAllowed;

            return `
                <div class="employee-special-action" data-action-id="${actionId}">
                    <input type="hidden" name="permissionActionIds[]" value="${actionId}">
                    <div>
                        <div class="fw-semibold">${escapeHtml(action.actionLabel || '-')}</div>
                        <div class="text-muted small">
                            Role Default: ${roleAllowed ? 'Allowed' : 'Denied'}
                        </div>
                    </div>
                    <div class="form-check form-switch mb-0">
                        <input
                            class="form-check-input employee-action-custom-toggle"
                            type="checkbox"
                            name="actionPermissions[${actionId}][override]"
                            value="1"
                            ${hasOverride ? 'checked' : ''}
                        >
                        <label class="form-check-label">Custom Permission</label>
                    </div>
                    <label class="exception-action">
                        <input
                            type="checkbox"
                            class="form-check-input employee-action-access"
                            name="actionPermissions[${actionId}][canAccess]"
                            value="1"
                            ${canAccess ? 'checked' : ''}
                            ${hasOverride ? '' : 'disabled'}
                        >
                        <span>Allow Access</span>
                    </label>
                </div>
            `;
        }).join('');

        return `
            <div class="employee-special-actions">
                <div class="fw-semibold text-muted small mb-1">Special Actions</div>
                ${actionRows}
            </div>
        `;
    }

    function renderEmployeePermissions(data) {
        const employee = data.employee || {};
        const permissions = data.permissions || [];

        if (!permissions.length) {
            employeeResultBox.innerHTML = `
                <div class="alert alert-warning mb-0">
                    No routes found for this employee and module.
                </div>
            `;
            return;
        }

        const grouped = {};

        permissions.forEach(function(item) {
            const moduleName = item.moduleName || 'Other';

            if (!grouped[moduleName]) {
                grouped[moduleName] = [];
            }

            grouped[moduleName].push(item);
        });

        let html = `
            <form id="employeePermissionForm">
                <input type="hidden" name="employeeId" value="${parseInt(employee.id || 0, 10)}">
                <div class="employee-exception-wrap">
                    <div class="employee-exception-summary">
                        <div>
                            <strong>${escapeHtml(employee.fullName || '-')}</strong>
                            <div class="text-muted small">
                                ${escapeHtml(employee.emailAddress || '-')}
                            </div>
                        </div>
                        
                    </div>
        `;

        Object.keys(grouped).forEach(function(moduleName) {
            html += `
                <div class="employee-exception-module">
                    ${escapeHtml(moduleName)}
                </div>
            `;

            grouped[moduleName].forEach(function(item) {
                const routeId = parseInt(item.routeId, 10);
                const hasCustomPermission = isAllowed(item.hasOverride);
                const customActions = permissionActions.map(function(action) {
                    const userKey = 'user' + action.key.charAt(0).toUpperCase() + action
                        .key.slice(1);
                    const roleKey = 'role' + action.key.charAt(0).toUpperCase() + action
                        .key.slice(1);
                    const value = hasCustomPermission ? item[userKey] : item[roleKey];

                    return permissionCheckbox(routeId, action, value,
                        hasCustomPermission);
                }).join('');
                const specialActions = renderSpecialActions(item.specialActions || []);

                html += `
                    <div class="employee-exception-row ${hasCustomPermission ? 'is-custom' : ''}" data-route-id="${routeId}">
                        <div>
                            <div class="exception-route-title">${escapeHtml(item.routeName || '-')}</div>
                            <div class="exception-route-path">
                                ${escapeHtml(item.routePath || '-')}
                                ${item.layoutType ? ' · ' + escapeHtml(item.layoutType) : ''}
                            </div>
                        </div>

                        <div class="form-check form-switch mb-0 gy-1">
                            <input
                                class="toggle form-check-input employee-custom-toggle mb-3"
                                type="checkbox"
                                name="permissions[${routeId}][override]"
                                value="1"
                                ${hasCustomPermission ? 'checked' : ''}
                            >
                            <label class="form-check-label">Custom Permission</label>
                        </div>

                        <div class="exception-actions">
                            ${permissionAllCheckbox(routeId, hasCustomPermission)}
                            ${customActions}
                        </div>
                        ${specialActions}
                    </div>
                `;
            });
        });

        html += `
                    <div class="permission-save-bar">
                        <button type="submit" class="btn btn-primary" id="saveEmployeePermissionBtn">
                            <i class="ri-save-line me-1"></i>
                            Save Exceptions
                        </button>
                    </div>
                </div>
            </form>
        `;

        employeeResultBox.innerHTML = html;

        employeeResultBox.querySelectorAll('.employee-exception-row').forEach(function(row) {
            syncEmployeeExceptionRow(row);
        });

        employeeResultBox.querySelectorAll('.employee-special-action').forEach(function(actionRow) {
            syncEmployeeSpecialAction(actionRow);
        });
    }

    function loadEmployeePermissions(employeeId, moduleName) {
        if (!employeeId) {
            employeeResultBox.innerHTML = `
                <div class="alert alert-info mb-0">
                    Please select an employee to manage exceptions.
                </div>
            `;
            return;
        }

        employeeLoader.classList.remove('d-none');
        employeeResultBox.innerHTML = '';

        const params = new URLSearchParams({
            employeeId: employeeId,
            moduleName: moduleName || ''
        });

        fetch('<?= BASE_URL; ?>/api/permissions/getEmployeePermissions.php?' + params.toString())
            .then(response => response.json())
            .then(data => {
                employeeLoader.classList.add('d-none');

                if (data.success) {
                    renderEmployeePermissions(data.data);
                } else {
                    employeeResultBox.innerHTML = `
                        <div class="alert alert-danger mb-0">
                            ${escapeHtml(data.message || 'Unable to load employee exceptions.')}
                        </div>
                    `;
                }
            })
            .catch(() => {
                employeeLoader.classList.add('d-none');
                employeeResultBox.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        Something went wrong while loading employee exceptions.
                    </div>
                `;
            });
    }

    roleSelect.addEventListener('change', function() {
        loadRolePermissions(this.value, moduleSelect.value, roleLayoutSelect.value);
    });

    moduleSelect.addEventListener('change', function() {
        loadRolePermissions(roleSelect.value, this.value, roleLayoutSelect.value);
    });

    roleLayoutSelect.addEventListener('change', function() {
        loadRolePermissions(roleSelect.value, moduleSelect.value, this.value);
    });

    if (roleSelect.value) {
        loadRolePermissions(roleSelect.value, moduleSelect.value, roleLayoutSelect.value);
    }

    employeeSelect.addEventListener('change', function() {
        loadEmployeePermissions(this.value, employeeModuleSelect.value);
    });

    employeeModuleSelect.addEventListener('change', function() {
        loadEmployeePermissions(employeeSelect.value, this.value);
    });

    employeeResultBox.addEventListener('change', function(event) {
        const specialAction = event.target.closest('.employee-special-action');

        if (specialAction) {
            if (event.target.classList.contains('employee-action-custom-toggle')) {
                syncEmployeeSpecialAction(specialAction);
            }

            return;
        }

        const row = event.target.closest('.employee-exception-row');

        if (!row) {
            return;
        }

        if (event.target.classList.contains('employee-custom-toggle')) {
            syncEmployeeExceptionRow(row);
            return;
        }

        if (event.target.classList.contains('employee-permission-all')) {
            row.querySelectorAll('.employee-permission-check').forEach(function(checkbox) {
                checkbox.checked = event.target.checked;
            });

            refreshEmployeeAllCheckbox(row);
            return;
        }

        if (event.target.classList.contains('employee-permission-check')) {
            refreshEmployeeAllCheckbox(row);
        }
    });

    employeeResultBox.addEventListener('submit', function(event) {
        if (event.target.id !== 'employeePermissionForm') {
            return;
        }

        event.preventDefault();

        const button = document.getElementById('saveEmployeePermissionBtn');

        if (button) {
            button.disabled = true;
            button.innerHTML = 'Saving...';
        }

        fetch('<?= BASE_URL; ?>/api/permissions/saveEmployeePermissions.php', {
                method: 'POST',
                body: new FormData(event.target)
            })
            .then(response => response.json())
            .then(data => {
                showToast(
                    data.success ? 'success' : 'danger',
                    data.message || 'Unable to save employee exceptions.'
                );

                if (data.success) {
                    loadEmployeePermissions(employeeSelect.value, employeeModuleSelect.value);
                }
            })
            .catch(() => {
                showToast('danger', 'Server error occurred while saving employee exceptions.');
            })
            .finally(() => {
                if (button) {
                    button.disabled = false;
                    button.innerHTML = '<i class="ri-save-line me-1"></i> Save Exceptions';
                }
            });
    });

});

document.addEventListener('change', function(e) {

    if (!e.target.classList.contains('row-permission-check')) {
        return;
    }

    const row = e.target.closest('tr');

    if (!row) {
        return;
    }

    row.querySelectorAll('.permission-action-check').forEach(function(checkbox) {
        checkbox.checked = e.target.checked;
    });

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
