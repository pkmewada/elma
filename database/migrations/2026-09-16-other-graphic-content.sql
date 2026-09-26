-- "Other Graphic Content" — a second, simpler raw-material source for the
-- existing Production Queue, for miscellaneous content requests (Blog, Bio,
-- Caption, YouTube Description, etc.) that don't fit clientSocialContent's
-- platform/feature/postType shape but still need to go through the exact
-- same assignment/status/review/history workflow every Social Content task
-- already uses.
--
-- Deliberately NOT modeled as a second production system: this migration
-- adds one new raw-material table (mirroring clientSocialContent's role,
-- not its columns) and widens socialContentProduction just enough to point
-- at EITHER source, mutually exclusively, per row. Every workflow method in
-- SocialContentProductionEngine.php (assign, start, submitProduction,
-- review/updateReviewStatus, markReady, history) already operates purely on
-- socialContentProduction.id/status and needed zero changes; only task
-- creation and the read-side JOINs are source-aware.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE/ALTER migrations).

CREATE TABLE otherGraphicContent (
  id INT NOT NULL AUTO_INCREMENT,
  clientId INT NOT NULL,
  contentDate DATE NOT NULL,
  deadlineDate DATE NOT NULL,
  editType VARCHAR(50) NOT NULL,
  title VARCHAR(150) NULL,
  hook TEXT NULL,
  reference TEXT NULL,
  notes TEXT NULL,
  contentDescription TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  createdBy INT NULL,
  updatedBy INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idxOtherGraphicContentClientDate (clientId, contentDate),
  CONSTRAINT fkOtherGraphicContentClient FOREIGN KEY (clientId) REFERENCES clientMaster(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- socialContentProduction: clientSocialContentId becomes optional (a task
-- now points at EITHER clientSocialContentId OR otherGraphicContentId, never
-- both — enforced in SocialContentProductionEngine, not by a DB CHECK
-- constraint, to keep this additive and simple). InnoDB unique keys already
-- allow any number of NULLs without conflicting, so existing Social Content
-- rows (clientSocialContentId set, otherGraphicContentId NULL) and new Other
-- Graphic Content rows (the reverse) coexist safely under both unique keys.
ALTER TABLE socialContentProduction
  MODIFY COLUMN clientSocialContentId INT NULL,
  ADD COLUMN otherGraphicContentId INT NULL AFTER clientSocialContentId,
  ADD COLUMN sourceType VARCHAR(10) NOT NULL DEFAULT 'social' AFTER otherGraphicContentId,
  ADD UNIQUE KEY uqSocialContentProductionOtherSource (otherGraphicContentId),
  ADD CONSTRAINT fkSocialContentProductionOtherSource FOREIGN KEY (otherGraphicContentId) REFERENCES otherGraphicContent(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Route — same pattern as every other Social Media page (admin-only,
-- unconditional access via getLoggedInUserType() === 'admin', no
-- rolePermissions row needed). sortOrder continues the group's existing
-- sequence (..., /social-content-production=212, /social-media-setup=213).
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/other-graphic-content', '/pages/other-graphic-content.php', 'Other Graphic Content',
    'Social Media', 'admin', 0, 1, 1, 214
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/other-graphic-content'
);
