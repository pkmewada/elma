-- Lead Management: Lead Dashboard / Sales Analytics page.
--
-- No new tables -- this migration only registers the route. All analytics
-- are computed on the fly from existing leads / leadFollowUps /
-- leadsActivityLogs (see includes/leadDashboardEngine.php).
--
-- Admin-only by default (no rolePermissions rows), same precedent as
-- /lead-setup -- an admin can grant specific roles access later from the
-- existing Permission Setup page if needed.

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/lead-dashboard', '/pages/lead-dashboard.php', 'Lead Dashboard',
    'Lead', 'admin', 0, 1, 1, 25
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/lead-dashboard'
);
