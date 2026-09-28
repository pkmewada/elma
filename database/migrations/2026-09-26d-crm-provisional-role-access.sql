-- Real Estate CRM — provisional employee role access (Phase 1).
--
-- CRM roles are employeeusers.designationName values (existing permission
-- model: userPermissionOverrides -> rolePermissions -> deny). Admin panel
-- users (`users` table) already have full access.
--
-- Deliberately minimal so both sales roles can log in and work their own
-- leads. No canDelete: lead delete has no ownership check yet (Phase 2
-- security fix). The full Admin / Sales Manager / Sales Executive matrix,
-- View All vs View Own, Assign/Reassign is Phase 2.
-- /emp-dashboard is always allowed (permission-helper isAlwaysAllowedRoute).

INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT r.roleName, rm.id, 1, 1, 1, 0, 0, 0, NOW(), NOW()
FROM routesMaster rm
CROSS JOIN (SELECT 'Sales Executive' AS roleName UNION ALL SELECT 'Sales Manager') r
WHERE rm.routePath = '/emp-leads'
AND rm.isActive = 1
AND NOT EXISTS (
    SELECT 1 FROM rolePermissions rp WHERE rp.roleName = r.roleName AND rp.routeId = rm.id
);

INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT 'Sales Manager', rm.id, 1, 0, 0, 0, 0, 0, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath IN ('/emp-lead-dashboard', '/emp-lead-activity')
AND rm.isActive = 1
AND NOT EXISTS (
    SELECT 1 FROM rolePermissions rp WHERE rp.roleName = 'Sales Manager' AND rp.routeId = rm.id
);
