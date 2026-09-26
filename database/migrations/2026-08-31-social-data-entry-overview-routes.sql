-- Social Media Data Entry suite — route for the new Overview page, plus a
-- fix-up for the Data Entry page's route row.
--
-- Both pages are frontend-only prototypes (pages/social-data-entry.php,
-- pages/social-overview.php) with no backing tables or API endpoints yet.
--
-- Bug fix: the /social-data-entry row was inserted by hand from an earlier
-- inline SQL suggestion that set layoutType = 'default'. routesMaster.
-- layoutType is ENUM('admin','employee','public') with no 'default' member,
-- so MySQL silently stored '' instead of erroring. This restores 'admin'
-- (the value every other authenticated-admin page in this module uses).
UPDATE routesMaster
SET layoutType = 'admin'
WHERE routePath = '/social-data-entry'
  AND layoutType = '';

-- New route: /social-overview (Client Health Matrix + Pending Queue).
-- Same moduleName as /calendar and /social-data-entry so it groups with
-- them in Permission Setup / Route Setup.
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/social-overview', '/pages/social-overview.php', 'Social Media Overview',
    'Social Media', 'admin', 0, 1, 1, 211
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/social-overview'
);
