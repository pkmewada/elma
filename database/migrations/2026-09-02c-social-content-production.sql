-- Social Content Production — foundation for the "manufacturing" stage
-- between raw content entry (clientSocialContent) and publishing (socialPosts).
--
-- This phase is intentionally self-contained: no row here ever writes to
-- socialPosts / SocialPostEngine / Instagram / Facebook. PRODUCTION_READY is
-- a business state only — the controlled handoff to Social Media Automation
-- is a separate future phase.
--
-- socialContentProduction: one row per clientSocialContent entry that has
--   been sent to production (enforced by the UNIQUE key below).
-- socialContentProductionHistory: append-only log of every assignment/
--   status change, so correction cycles are never lost.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE-TABLE migrations).

CREATE TABLE socialContentProduction (
  id INT NOT NULL AUTO_INCREMENT,
  clientSocialContentId INT NOT NULL,
  assignedEditorId INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'NEW',
  assignedAt DATETIME NULL,
  dueAt DATETIME NULL,
  submittedAt DATETIME NULL,
  approvedAt DATETIME NULL,
  createdBy INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqSocialContentProductionSource (clientSocialContentId),
  KEY idxSocialContentProductionEditor (assignedEditorId),
  KEY idxSocialContentProductionStatus (status),
  CONSTRAINT fkSocialContentProductionSource FOREIGN KEY (clientSocialContentId) REFERENCES clientSocialContent(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fkSocialContentProductionEditor FOREIGN KEY (assignedEditorId) REFERENCES employeeusers(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE socialContentProductionHistory (
  id INT NOT NULL AUTO_INCREMENT,
  productionId INT NOT NULL,
  action VARCHAR(30) NOT NULL,
  oldStatus VARCHAR(20) NULL,
  newStatus VARCHAR(20) NULL,
  remark TEXT NULL,
  performedBy INT NOT NULL,
  performedByType VARCHAR(10) NOT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idxSocialContentProductionHistoryProduction (productionId),
  CONSTRAINT fkSocialContentProductionHistoryProduction FOREIGN KEY (productionId) REFERENCES socialContentProduction(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Routes — one admin page for the Social Media Manager, one employee-portal
-- page for the Video Editor. Mirrors 2026-08-31-social-data-entry-overview-routes.sql.
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/social-content-production', '/pages/social-content-production.php', 'Content Production',
    'Social Media', 'admin', 0, 1, 1, 212
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/social-content-production'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/emp-content-production', '/employee/emp-content-production.php', 'My Production Tasks',
    'Employee Panel', 'admin', 0, 1, 1, 112
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/emp-content-production'
);

-- Video Editor needs canView (routes.php enforces this on every page load)
-- and canEdit (start/submit are treated as edits of their own task).
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, canExport)
SELECT 'Video Editor', rm.id, 1, 0, 1, 0, 0, 0
FROM routesMaster rm
WHERE rm.routePath = '/emp-content-production'
  AND NOT EXISTS (
      SELECT 1 FROM rolePermissions rp
      WHERE rp.roleName = 'Video Editor' AND rp.routeId = rm.id
  );
