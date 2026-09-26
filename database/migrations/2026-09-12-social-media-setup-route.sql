-- Route for the new Social Media Setup page (Phase 3 of Social Media Setup).
-- Same moduleName as /calendar, /social-data-entry, /social-content-production
-- so it groups with them in Permission Setup / Route Setup. sortOrder
-- continues that group's existing sequence (0, 210, 211, 212 -> 213).
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/social-media-setup', '/pages/social-media-setup.php', 'Social Media Setup',
    'Social Media', 'admin', 0, 1, 1, 213
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/social-media-setup'
);
