<?php

require_once __DIR__ . '/permission-helper.php';

/*
 * Sidebar menu built from routesMaster (the single page/menu registry).
 *
 * A route is listed when it is active, not public, isMenuVisible = 1, its
 * layoutType matches the panel and its moduleName is one of the panel's groups
 * below. Items are ordered by sortOrder and labelled by routeTitle; canView is
 * still checked by the sidebar itself. Only the group presentation (heading,
 * label, icon, order) lives here. New pages need no edit in this file.
 */
function getSidebarMenuGroups(string $layoutType): array
{
    global $con;

    $groupDefinitions = [
        'admin' => [
            'CRM' => ['category' => 'CRM', 'label' => 'CRM Panel', 'icon' => 'ti ti-layout-dashboard'],
            'Lead Management' => ['category' => 'LMS', 'label' => 'Lead Management', 'icon' => 'ti ti-user-search'],
            'HRMS' => ['category' => 'HRMS', 'label' => 'HRMS Panel', 'icon' => 'ti ti-users'],
            'Setup' => ['category' => 'Setup', 'label' => 'Setup', 'icon' => 'ti ti-settings'],
            'Social Media' => ['category' => 'Social Media', 'label' => 'Social Media', 'icon' => 'ti ti-world'],
            'Automation' => ['category' => 'Automation', 'label' => 'Automation', 'icon' => 'ti ti-settings-automation'],
        ],
        'employee' => [
            'Employee Panel' => ['category' => 'EMPLOYEE', 'label' => 'Employee Panel', 'icon' => 'ti ti-user-circle'],
            'Lead Management' => ['category' => 'LMS', 'label' => 'Lead Management', 'icon' => 'ti ti-user-search'],
            'HRMS' => ['category' => 'HRMS', 'label' => 'HRMS Panel', 'icon' => 'ti ti-users'],
        ],
    ];

    $groups = [];

    foreach ($groupDefinitions[$layoutType] ?? [] as $moduleName => $group) {
        $groups[$moduleName] = $group + ['items' => []];
    }

    if (!$groups) {
        return [];
    }

    $stmt = $con->prepare("
        SELECT routePath, routeTitle, moduleName
        FROM routesMaster
        WHERE isActive = 1
        AND isPublic = 0
        AND isMenuVisible = 1
        AND layoutType = ?
        ORDER BY sortOrder ASC, routeTitle ASC
    ");

    $stmt->bind_param('s', $layoutType);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($route = $result->fetch_assoc()) {
        $moduleName = trim((string)($route['moduleName'] ?? ''));

        if (!isset($groups[$moduleName])) {
            continue;
        }

        $groups[$moduleName]['items'][] = [
            'route' => ltrim((string)$route['routePath'], '/'),
            'label' => (string)$route['routeTitle'],
        ];
    }

    $stmt->close();

    return array_values($groups);
}
