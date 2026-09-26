-- "Other Graphic Content" (new, independent identity) — a THIRD raw-material
-- source for the existing Production Queue, alongside clientSocialContent
-- ('social') and otherGraphicContent ('other', since 2026-09-17 UI-labelled
-- "Other Content" and disconnected from Production per that day's business
-- requirement change). This module is NOT the old one: new table, new
-- engine, new route/page, own form shape (Content Name, Priority, Raw
-- Content, Song URL alongside Edit Type/Reference/Notes/Content
-- Description) -- and unlike "Other Content", it DOES feed Production,
-- exactly like clientSocialContent/otherGraphicContent already do.
--
-- Every workflow method in SocialContentProductionEngine.php (assign,
-- start, submitProduction, review/updateReviewStatus, markReady, history)
-- already operates purely on socialContentProduction.id/status and needs
-- zero changes to support a third source -- only task creation and the
-- read-side JOINs are source-aware (mirrors the otherGraphicContent
-- migration's own note on this exactly).
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE/ALTER migrations).

CREATE TABLE graphicContent (
  id INT NOT NULL AUTO_INCREMENT,
  clientId INT NOT NULL,
  contentDate DATE NOT NULL,
  deadlineAt DATETIME NOT NULL,
  contentName VARCHAR(150) NOT NULL,
  editType VARCHAR(50) NOT NULL,
  priority VARCHAR(10) NOT NULL DEFAULT 'Medium',
  rawContent TEXT NULL,
  songUrl VARCHAR(255) NULL,
  reference VARCHAR(255) NULL,
  notes TEXT NULL,
  contentDescription TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  createdBy INT NULL,
  updatedBy INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idxGraphicContentClientDate (clientId, contentDate),
  CONSTRAINT fkGraphicContentClient FOREIGN KEY (clientId) REFERENCES clientMaster(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- socialContentProduction: one more nullable FK slot, additive only --
-- clientSocialContentId/otherGraphicContentId/sourceType are all untouched.
-- A task's three source-id columns stay exactly one-set-two-null, enforced
-- in the engine (createTask()/createTaskForOther()/createTaskForGraphic()
-- each only ever set their own column), same as the two-source invariant
-- already documented on the otherGraphicContent migration.
ALTER TABLE socialContentProduction
  ADD COLUMN graphicContentId INT NULL AFTER sourceType,
  ADD UNIQUE KEY uqSocialContentProductionGraphicSource (graphicContentId),
  ADD CONSTRAINT fkSocialContentProductionGraphicSource FOREIGN KEY (graphicContentId) REFERENCES graphicContent(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- Route — same admin-only pattern every other Social Media page uses
-- (unconditional access via getLoggedInUserType() === 'admin', no
-- rolePermissions row needed). sortOrder continues the group's existing
-- sequence (..., /social-content-production=212, /social-media-setup=213,
-- /other-graphic-content=214).
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/graphic-content', '/pages/graphic-content.php', 'Other Graphic Content',
    'Social Media', 'admin', 0, 1, 1, 215
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/graphic-content'
);
