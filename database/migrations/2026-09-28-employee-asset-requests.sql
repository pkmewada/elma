-- Employee Asset Management: adds a request layer in front of the existing
-- Asset Management module (assetMaster / assetAssignment / AssetModel /
-- AssetAssignmentModel), unchanged. An employee requests an available
-- asset; Admin/HR approves (which creates the assignment via the existing
-- AssetAssignmentModel::assignAsset(), same as a direct admin assignment)
-- or rejects (history kept, nothing else touched).
--
-- status uses the same lowercase pending/approved/rejected convention as
-- overtimeRequests.status -- the closest existing "employee requests,
-- admin approves/rejects" table in this schema.

CREATE TABLE IF NOT EXISTS employeeAssetRequests (
    id INT NOT NULL AUTO_INCREMENT,
    employeeId INT NOT NULL,
    assetId INT NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    purpose VARCHAR(255) NULL,
    expectedReturnDate DATE NULL,
    remarks TEXT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reviewedBy INT NULL,
    reviewedAt DATETIME NULL,
    rejectionRemark TEXT NULL,
    assignmentId INT NULL COMMENT 'assetAssignment.id created on approval, for traceability',
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ear_employee (employeeId),
    KEY idx_ear_asset (assetId),
    KEY idx_ear_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route: employee-facing "My Assets" page, same shape as the existing
-- /emp-expense-management route.
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/emp-my-assets', '/employee/emp-my-assets.php', 'My Assets',
    'Employee Panel', 'admin', 0, 1, 1, 1002
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/emp-my-assets'
);

-- Every existing employee role can view it -- unlike expense management
-- this isn't role-restricted, any employee may have assets assigned.
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove)
SELECT DISTINCT rp.roleName, rm.id, 1, 1, 0, 0, 0
FROM rolePermissions rp
CROSS JOIN routesMaster rm
WHERE rm.routePath = '/emp-my-assets'
AND rp.roleName <> 'Admin'
AND NOT EXISTS (
    SELECT 1 FROM rolePermissions existing
    WHERE existing.roleName = rp.roleName AND existing.routeId = rm.id
);
