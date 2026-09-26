-- Employee-side copies of the admin Lead Management pages, shown in the
-- employee sidebar's Lead Management group (includes/emp-sidebar.php):
--   /emp-lead-dashboard     -> Lead Dashboard
--   /emp-onboarding-lead    -> Onboarding Lead   (agreement draft/send = canEdit, review = canApprove)
--   /emp-lead-activity      -> Lead Activity
--   /emp-client-onboarding  -> Client Onboarding (form save/send = canEdit)
-- /emp-leads (Lead Records) already exists.
--
-- No rolePermissions rows: nobody gets these by default. Employee #13 is
-- granted full access below via userPermissionOverrides; others can be
-- granted from Permission Setup.

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder)
SELECT '/emp-lead-dashboard', '/employee/emp-lead-dashboard.php', 'Lead Dashboard', 'Lead Management', 'employee', 0, 1, 1, 10
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-lead-dashboard');

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder)
SELECT '/emp-onboarding-lead', '/employee/emp-onboarding-lead.php', 'Onboarding Lead', 'Lead Management', 'employee', 0, 1, 1, 30
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-onboarding-lead');

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder)
SELECT '/emp-lead-activity', '/employee/emp-lead-activity.php', 'Lead Activity', 'Lead Management', 'employee', 0, 1, 1, 40
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-lead-activity');

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder)
SELECT '/emp-client-onboarding', '/employee/emp-client-onboarding.php', 'Client Onboarding', 'Lead Management', 'employee', 0, 1, 1, 50
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-client-onboarding');

-- Employee #13: full access to all five Lead Management menu items.
INSERT INTO userPermissionOverrides
    (userId, routeId, overrideType, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT 13, rm.id, 'grant', 1, 1, 1, 1, 1, 1, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath IN (
    '/emp-lead-dashboard',
    '/emp-leads',
    '/emp-onboarding-lead',
    '/emp-lead-activity',
    '/emp-client-onboarding'
)
ON DUPLICATE KEY UPDATE
    overrideType = 'grant',
    canView = 1, canAdd = 1, canEdit = 1, canDelete = 1, canApprove = 1, canExport = 1,
    updatedAt = NOW();
