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
        // Real Estate CRM navigation. A group with no active, visible,
        // permitted routes is not rendered, so Projects / Reports /
        // Integrations stay hidden until their pages are registered.
        'admin' => [
            'CRM' => ['category' => 'CRM', 'label' => 'Dashboard', 'icon' => 'ti ti-layout-dashboard'],
            'Lead Management' => ['category' => 'CRM', 'label' => 'Lead Management', 'icon' => 'ti ti-user-search'],
            'Projects' => ['category' => 'CRM', 'label' => 'Projects', 'icon' => 'ti ti-building'],
            'Employees' => ['category' => 'CRM', 'label' => 'Employees', 'icon' => 'ti ti-users'],
            'Reports' => ['category' => 'CRM', 'label' => 'Reports', 'icon' => 'ti ti-report-analytics'],
            'Integrations' => ['category' => 'CRM', 'label' => 'Integrations', 'icon' => 'ti ti-plug-connected'],
            'Setup' => ['category' => 'Settings', 'label' => 'Settings', 'icon' => 'ti ti-settings'],
        ],
        'employee' => [
            'Employee Panel' => ['category' => 'CRM', 'label' => 'Dashboard', 'icon' => 'ti ti-layout-dashboard'],
            'Lead Management' => ['category' => 'CRM', 'label' => 'Lead Management', 'icon' => 'ti ti-user-search'],
            'Projects' => ['category' => 'CRM', 'label' => 'Projects', 'icon' => 'ti ti-building'],
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
