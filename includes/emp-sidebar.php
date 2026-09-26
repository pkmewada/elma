<?php

require_once __DIR__ . '/permission-helper.php';
require_once __DIR__ . '/sidebar-menu.php';

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
$basePath = rtrim($basePath, '/');

$normalize = static function ($path): string {
    $path = rtrim((string)($path ?? ''), '/');

    return $path === '' ? '/' : $path;
};

$isActiveRoute = static function (string $route) use ($currentPath, $basePath, $normalize): bool {
    $current = $normalize($currentPath);
    $target = $normalize($basePath . '/' . ltrim($route, '/'));

    return $current === $target;
};

$canViewRoute = static function (string $route): bool {
    return hasRoutePermission('/' . ltrim($route, '/'), 'canView');
};

// Menu items come from routesMaster (see includes/sidebar-menu.php).
$menuGroups = getSidebarMenuGroups('employee');

$canRenderMenuRoute = $canViewRoute;

$renderMenuGroup = static function (array $group) use ($canRenderMenuRoute, $isActiveRoute): void {
    $visibleItems = array_values(array_filter(
        $group['items'],
        static fn(array $item): bool => $canRenderMenuRoute($item['route'])
    ));

    if (!$visibleItems) {
        return;
    }

    $isOpen = false;

    foreach ($visibleItems as $item) {
        if ($isActiveRoute($item['route'])) {
            $isOpen = true;
            break;
        }
    }

    $label = htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8');
    $icon = htmlspecialchars($group['icon'], ENT_QUOTES, 'UTF-8');
    ?>
    <li class="slide has-sub <?= $isOpen ? 'open' : ''; ?>">
        <a href="javascript:void(0);"
           class="side-menu__item <?= $isOpen ? 'active' : ''; ?>">
            <i class="<?= $icon; ?> side-menu__icon"></i>
            <span class="side-menu__label"><?= $label; ?></span>
            <i class="ri-arrow-down-s-line side-menu__angle"></i>
        </a>

        <ul class="slide-menu child1" <?= $isOpen ? 'style="display:block;"' : ''; ?>>
            <li class="slide side-menu__label1">
                <a href="javascript:void(0)"><?= $label; ?></a>
            </li>

            <?php foreach ($visibleItems as $item): ?>
                <?php
                    $route = trim((string)$item['route'], '/');
                    $itemLabel = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
                    $routeUrl = htmlspecialchars($route, ENT_QUOTES, 'UTF-8');
                    $itemActive = $isActiveRoute($route);
                ?>
                <li class="slide">
                    <a href="<?= $routeUrl; ?>"
                       class="side-menu__item <?= $itemActive ? 'active' : ''; ?>">
                        <?= $itemLabel; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </li>
    <?php
};
?>
<!-- Start::app-sidebar -->
<aside class="app-sidebar sticky" id="sidebar">

    <!-- Start::main-sidebar-header -->
    <div class="main-sidebar-header">
        <a href="emp-dashboard" class="header-logo">
            <img src="<?= ASSET_URL ?>/assets/images/brand-logos/desktop-logo.png" alt="logo" class="desktop-logo">
            <img src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="logo" class="toggle-dark">
            <img src="<?= ASSET_URL ?>/assets/images/brand-logos/desktop-dark.png" alt="logo" class="desktop-dark">
            <img src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-logo.png" alt="logo" class="toggle-logo">
        </a>
    </div>
    <!-- End::main-sidebar-header -->

    <!-- Start::main-sidebar -->
    <div class="main-sidebar" id="sidebar-scroll">
        <nav class="main-menu-container nav nav-pills flex-column sub-open">
            <div class="slide-left" id="slide-left">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M13.293 6.293 7.586 12l5.707 5.707 1.414-1.414L10.414 12l4.293-4.293z"></path>
                </svg>
            </div>

            <ul class="main-menu">
                <?php $currentCategory = ''; ?>
                <?php foreach ($menuGroups as $group): ?>
                    <?php
                        $visibleItems = array_values(array_filter(
                            $group['items'],
                            static fn(array $item): bool => $canRenderMenuRoute($item['route'])
                        ));

                        if (!$visibleItems) {
                            continue;
                        }

                        if ($currentCategory !== $group['category']):
                            $currentCategory = $group['category'];
                    ?>
                            <li class="slide__category">
                                <span class="category-name">
                                    <?= htmlspecialchars($currentCategory, ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </li>
                    <?php endif; ?>

                    <?php $renderMenuGroup($group); ?>
                <?php endforeach; ?>

                <li class="slide__category"><span class="category-name">Account</span></li>

                <li class="slide has-sub">
                    <a href="javascript:void(0);" class="side-menu__item">
                        <i class="ti ti-user-circle side-menu__icon"></i>
                        <span class="side-menu__label">Account</span>
                        <i class="ri-arrow-down-s-line side-menu__angle"></i>
                    </a>
                    <ul class="slide-menu child1">
                        <li class="slide side-menu__label1">
                            <a href="javascript:void(0)">Account</a>
                        </li>
                        <li class="slide">
                            <a href="emp-logout" class="side-menu__item">Logout</a>
                        </li>
                    </ul>
                </li>
            </ul>

            <div class="slide-right" id="slide-right">
                <svg xmlns="http://www.w3.org/2000/svg" fill="#7b8191" width="24" height="24" viewBox="0 0 24 24">
                    <path d="M10.707 17.707 16.414 12l-5.707-5.707-1.414 1.414L13.586 12l-4.293 4.293z"></path>
                </svg>
            </div>
        </nav>
    </div>
    <!-- End::main-sidebar -->

</aside>
<!-- End::app-sidebar -->
