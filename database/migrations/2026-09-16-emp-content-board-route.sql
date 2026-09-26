-- Route for the new employee "My Production Board" card/Kanban view
-- (employee/emp-content-board.php). Purely a second presentation of the
-- same data employee/emp-content-production.php already serves via the
-- unchanged api/social-content-production/emp-*.php endpoints -- no new
-- table, no new API, no workflow change. Same moduleName/permission shape
-- as /emp-content-production, sortOrder continues that route's sequence.
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/emp-content-board', '/employee/emp-content-board.php', 'My Production Board',
    'Employee Panel', 'admin', 0, 1, 1, 113
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/emp-content-board'
);

-- Same canView + canEdit grant /emp-content-production already has for
-- Video Editor (Start/Submit here are the same edits-of-their-own-task
-- actions, just triggered from a card instead of a table row).
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, canExport)
SELECT 'Video Editor', rm.id, 1, 0, 1, 0, 0, 0
FROM routesMaster rm
WHERE rm.routePath = '/emp-content-board'
  AND NOT EXISTS (
      SELECT 1 FROM rolePermissions rp
      WHERE rp.roleName = 'Video Editor' AND rp.routeId = rm.id
  );
