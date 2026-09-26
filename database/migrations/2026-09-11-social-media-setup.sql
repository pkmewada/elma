-- Social Media Setup — feature planning configuration + calendar planning rules.
--
-- socialMediaFeatureConfig replaces the previously hardcoded PHP whitelist
-- that lived in includes/deliverableEngine.php
-- (getSocialFeatureWhitelist() / isSocialFeatureAllowed()).
--
-- DEFAULT-DENY: a platformId+featureId combination is allowed for Social
-- Media planning only if a row exists here with isEnabled = 1. No row at
-- all means "not allowed" — a newly added deliverableFeatures row never
-- automatically becomes part of Social Media production; an administrator
-- must explicitly enable it via Social Media Setup.
--
-- socialMediaPlanningRules is a single-row settings table (same shape as
-- companysettings/leavesettings) holding calendar-generation rules.
-- Holiday exclusion is NOT a setting here — it is an always-on rule
-- enforced in code (SocialCalendarPlanningEngine) against
-- eventholidaymaster, so there is nothing to configure/seed for it.
--
-- This file runs once per environment, same as this repo's other
-- CREATE-TABLE migrations — re-running it will error on the CREATE TABLE
-- statements, which is expected. The seed INSERTs use INSERT IGNORE so
-- they alone are safe to re-run if ever copy-pasted separately.

CREATE TABLE socialMediaFeatureConfig (
  id INT NOT NULL AUTO_INCREMENT,
  platformId INT NOT NULL,
  featureId INT NOT NULL,
  isEnabled TINYINT(1) NOT NULL DEFAULT 1,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uqSocialMediaFeatureConfig (platformId, featureId),
  CONSTRAINT fkSocialMediaFeatureConfigPlatform FOREIGN KEY (platformId) REFERENCES deliverablePlatforms(id) ON DELETE CASCADE,
  CONSTRAINT fkSocialMediaFeatureConfigFeature FOREIGN KEY (featureId) REFERENCES deliverableFeatures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE socialMediaPlanningRules (
  id INT NOT NULL AUTO_INCREMENT,
  excludeWeekend TINYINT(1) NOT NULL DEFAULT 1,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- single global rules row (excludeWeekend = 1 -> Sat/Sun excluded by default)
INSERT INTO socialMediaPlanningRules (id, excludeWeekend) VALUES (1, 1);

-- Seed: the 9 Platform+Feature combinations the old hardcoded whitelist
-- actually matched against live data. Resolved by NAME via subquery
-- (never by literal id) so this migration is safe to run unmodified on any
-- environment (dev/staging/production) regardless of that environment's
-- actual auto-increment values.
--
-- NOTE: two originally-intended combinations from the old whitelist —
-- Instagram "Reels" and GMB "Posting" — are deliberately NOT seeded here,
-- because no such deliverableFeatures row exists in the master data today
-- (verified against live data before writing this migration). If/when
-- those feature rows are added, enable them via Social Media Setup.
--
-- UPDATE (2026-09-12): deliverableFeatures.featureName for Instagram/
-- Facebook Post+Stories was later normalized to drop the redundant
-- platform prefix ("Instagram Post" -> "Post", etc. — see
-- 2026-09-12-normalize-social-media-feature-names.sql). This seed's
-- resolution is ID-independent already (by name+platform, not literal id),
-- but a literal "featureName = 'Instagram Post'" match would silently seed
-- zero rows for that combination on any environment whose master data
-- already has the normalized name (e.g. a fresh install seeded from a
-- current schema dump, rather than a full historical migration replay).
-- Matching EITHER spelling keeps this seed correct regardless of whether
-- the rename has already happened in a given environment. This is still
-- always paired with its exact platformName in the same AND clause — a
-- bare `featureName = 'Post'` is never used, since "Post" now legitimately
-- exists under more than one platform.
INSERT IGNORE INTO socialMediaFeatureConfig (platformId, featureId, isEnabled)
SELECT dp.id, df.id, 1
FROM deliverablePlatforms dp
INNER JOIN deliverableFeatures df ON df.platformId = dp.id
WHERE (dp.platformName = 'Instagram' AND df.featureName IN ('Instagram Post', 'Post'))
   OR (dp.platformName = 'Instagram' AND df.featureName IN ('Instagram Stories', 'Stories'))
   OR (dp.platformName = 'Facebook'  AND df.featureName IN ('Facebook Post', 'Post'))
   OR (dp.platformName = 'Facebook'  AND df.featureName IN ('Facebook Stories', 'Stories'))
   OR (dp.platformName = 'YouTube'   AND df.featureName = 'Community Post')
   OR (dp.platformName = 'YouTube'   AND df.featureName = 'Long Videos')
   OR (dp.platformName = 'YouTube'   AND df.featureName = 'Shorts')
   OR (dp.platformName = 'LinkedIn'  AND df.featureName = 'Posting')
   OR (dp.platformName = 'Pinterest' AND df.featureName = 'Posting');
