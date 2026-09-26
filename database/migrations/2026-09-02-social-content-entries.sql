-- Social Data Entry — real backing table for filled content entries.
--
-- pages/social-data-entry.php was a frontend-only prototype (DUMMY_DB +
-- seeded pseudo-random generator, see its own header comment). This table
-- is the real "one row per filled (client, date, platform, feature) slot"
-- store the page's TODO(api) markers pointed at.
--
-- Planned slots themselves already live in clientCalendarPlans (see
-- includes/calendarEngine.php) — this table only records what was entered
-- for a slot that's been filled in.
--
-- This file runs once per environment, same as this repo's other
-- CREATE-TABLE migrations — re-running it will error, which is expected.

CREATE TABLE clientSocialContent (
  id INT NOT NULL AUTO_INCREMENT,
  clientId INT NOT NULL,
  platformId INT NOT NULL,
  featureId INT NOT NULL,
  contentDate DATE NOT NULL,
  title VARCHAR(150) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  caption TEXT NULL,
  referenceLink VARCHAR(255) NULL,
  remarks VARCHAR(120) NULL,
  createdBy INT NULL,
  updatedBy INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqClientSocialContentSlot (clientId, contentDate, platformId, featureId),
  KEY idxClientSocialContentClientDate (clientId, contentDate),
  CONSTRAINT fkClientSocialContentClient FOREIGN KEY (clientId) REFERENCES clientMaster(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fkClientSocialContentPlatform FOREIGN KEY (platformId) REFERENCES deliverablePlatforms(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fkClientSocialContentFeature FOREIGN KEY (featureId) REFERENCES deliverableFeatures(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
