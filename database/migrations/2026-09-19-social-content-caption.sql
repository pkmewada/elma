-- Caption Area — Phase 1 foundation.
--
-- Sits between Production approval and (future) Automation:
--   Social Content Production -> Approved Content -> Caption Area ->
--   Caption Selected -> Future Automation
--
-- Scope: Social Content only (socialContentProduction.sourceType='social').
-- Other Content / Other Graphic Content are explicitly out of scope for
-- this module and are never queried by it.
--
-- No AI integration in this phase -- captionOptionOne/captionOptionTwo are
-- filled with static placeholder text by the engine, never by an external
-- API call. See includes/socialContentCaptionEngine.php.
--
-- One row per production task (UNIQUE(productionId)), mirroring the same
-- "one row per source" pattern socialContentProduction's own
-- clientSocialContentId/otherGraphicContentId/graphicContentId columns
-- already use.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE-TABLE migrations).

CREATE TABLE socialContentCaption (
  id INT NOT NULL AUTO_INCREMENT,
  productionId INT NOT NULL,
  prompt TEXT NULL,
  captionOptionOne TEXT NULL,
  captionOptionTwo TEXT NULL,
  selectedCaption TEXT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  createdBy INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqSocialContentCaptionProduction (productionId),
  CONSTRAINT fkSocialContentCaptionProduction FOREIGN KEY (productionId) REFERENCES socialContentProduction(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Route — same admin-only pattern every other Social Media page uses
-- (unconditional access via getLoggedInUserType() === 'admin', no
-- rolePermissions row needed). sortOrder continues the group's existing
-- sequence (..., /other-graphic-content=214, /graphic-content=215).
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/social-caption-area', '/pages/social-caption-area.php', 'Caption Area',
    'Social Media', 'admin', 0, 1, 1, 216
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/social-caption-area'
);
