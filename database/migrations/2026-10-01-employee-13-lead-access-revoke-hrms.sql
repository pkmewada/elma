-- Employee #13: grant full Lead Management access, revoke the whole
-- HRMS Panel menu (includes/emp-sidebar.php).
--
-- Uses the existing per-user override tables (userPermissionOverrides /
-- userActionPermissionOverrides), which win over rolePermissions in
-- includes/permission-helper.php. Rows are written in the same shape
-- api/permissions/saveEmployeePermissions.php saves from Permission Setup,
-- so they stay editable there. Idempotent (ON DUPLICATE KEY UPDATE).

-- 1) Lead Management: every flag on for all employee lead routes
--    (/emp-leads and any other /emp-*lead* route).
INSERT INTO userPermissionOverrides
    (userId, routeId, overrideType, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT 13, rm.id, 'grant', 1, 1, 1, 1, 1, 1, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath LIKE '/emp-%lead%'
ON DUPLICATE KEY UPDATE
    overrideType = 'grant',
    canView = 1, canAdd = 1, canEdit = 1, canDelete = 1, canApprove = 1, canExport = 1,
    updatedAt = NOW();

-- Special (non-CRUD) button actions on those lead routes. CRUD-typed
-- actions (add_lead, edit_lead, ...) already follow the route flags above.
INSERT INTO userActionPermissionOverrides (userId, actionId, canAccess)
SELECT 13, pa.id, 1
FROM permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
WHERE rm.routePath LIKE '/emp-%lead%'
  AND pa.permissionType = 'special'
  AND pa.isActive = 1
ON DUPLICATE KEY UPDATE canAccess = 1;

-- 2) HRMS Panel: every flag off. An override row with all zeros is an
--    explicit deny even if the role grants access.
INSERT INTO userPermissionOverrides
    (userId, routeId, overrideType, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT 13, rm.id, 'revoke', 0, 0, 0, 0, 0, 0, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath IN (
    '/emp-event-holiday',
    '/emp-apply-leave',
    '/emp-overtime-management',
    '/emp-deduction',
    '/emp-expense-management',
    '/emp-my-assets',
    '/emp-point-transactions',
    '/emp-commission-bonus',
    '/employee-attendance'
)
ON DUPLICATE KEY UPDATE
    overrideType = 'revoke',
    canView = 0, canAdd = 0, canEdit = 0, canDelete = 0, canApprove = 0, canExport = 0,
    updatedAt = NOW();

INSERT INTO userActionPermissionOverrides (userId, actionId, canAccess)
SELECT 13, pa.id, 0
FROM permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
WHERE rm.routePath IN (
    '/emp-event-holiday',
    '/emp-apply-leave',
    '/emp-overtime-management',
    '/emp-deduction',
    '/emp-expense-management',
    '/emp-my-assets',
    '/emp-point-transactions',
    '/emp-commission-bonus',
    '/employee-attendance'
)
  AND pa.permissionType = 'special'
ON DUPLICATE KEY UPDATE canAccess = 0;
