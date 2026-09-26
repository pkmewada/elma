-- Public legal/documentation pages required for Meta App publishing:
-- Privacy Policy, Terms of Service, and Data Deletion Instructions.
--
-- isPublic = 1 so routes.php's existing auth guard is bypassed for these
-- routes specifically (the same mechanism already used by /login,
-- /terms-and-conditions, /public-record, etc.) — no global authentication
-- change. layoutType = 'public' matches the existing convention for
-- standalone pages that render their own full HTML document.
--
-- No new tables, no schema change — this only adds rows to the existing
-- routesMaster table.

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/privacy-policy', '/pages/privacy-policy.php', 'Privacy Policy',
    'Public', 'public', 1, 0, 1, 999
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/privacy-policy'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/terms-of-service', '/pages/terms-of-service.php', 'Terms of Service',
    'Public', 'public', 1, 0, 1, 1000
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/terms-of-service'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/data-deletion', '/pages/data-deletion.php', 'Data Deletion Instructions',
    'Public', 'public', 1, 0, 1, 1001
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/data-deletion'
);
