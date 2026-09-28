-- Real Estate CRM — Phase 5: Reports route.
--
-- No new tables. The Dashboard (KPIs, pipeline/source/project/executive
-- charts) reuses the existing /dashboard, /lead-dashboard and
-- /emp-lead-dashboard routes (already registered) with an extended
-- includes/leadDashboardEngine.php -- nothing to migrate there.
--
-- Admin-only by default (no rolePermissions rows), same precedent as
-- /lead-setup and /route-setup -- can be granted to Sales Manager later
-- from the existing Roles & Permissions page if needed.

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt
)
SELECT
    '/reports', '/pages/reports.php', 'Reports',
    'Reports', 'admin', 0, 1, 1, 10, NOW(), NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/reports'
);
