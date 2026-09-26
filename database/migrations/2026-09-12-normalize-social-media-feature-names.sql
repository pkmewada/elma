-- Normalizes redundant platform-prefixed feature names in the shared
-- deliverableFeatures master data.
--
-- Business identity for Social Media planning/data-entry/production is
-- (platformId, featureId) everywhere already (see
-- includes/deliverableEngine.php::isSocialFeatureAllowed(), which keys
-- purely by "$platformId_$featureId" against socialMediaFeatureConfig) --
-- featureName/platformName are presentation-only display labels, never
-- used for business matching in the runtime Social Media flow (audited
-- across includes/deliverableEngine.php, socialContentEngine.php,
-- socialMediaSetupEngine.php, socialCalendarPlanningEngine.php,
-- calendarEngine.php, and every api/deliverables + api/social-content* +
-- api/social-content-production* endpoint — no WHERE/match on a literal
-- feature name was found in that flow). This is therefore a pure display
-- cleanup, not a business-logic change.
--
-- "Instagram Post" -> "Post" (etc.) so the Setup UI naturally reads
-- "Instagram > Post / Stories", "Facebook > Post / Stories" instead of
-- repeating the platform name inside the feature label. Duplicate feature
-- LABELS across different platforms (two rows both named "Post") are
-- valid and expected — identity stays platformId+featureId, not the
-- string "Post" — so every UPDATE below is scoped to its own exact
-- platformName via JOIN, never a bare `featureName = '...'` that could
-- match the wrong platform's row.
--
-- IDs are never changed and no row is deleted/recreated: existing FKs
-- (clientDeliverables.featureId, clientCalendarPlans.featureId,
-- clientSocialContent.featureId, socialMediaFeatureConfig.featureId) all
-- reference featureId, which this migration does not touch.
--
-- Idempotent: each WHERE only matches the OLD name, so re-running this
-- after it has already applied (or against master data that was already
-- normalized some other way, e.g. a fresh dump) matches zero rows and is
-- a safe no-op. Plain DDL/DML, same convention as this repo's other
-- structural migrations.

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Strategies'
WHERE dp.platformName = 'Instagram' AND df.featureName = 'Instagram Strategies';

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Reports'
WHERE dp.platformName = 'Instagram' AND df.featureName = 'Instagram (Reports)';

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Post'
WHERE dp.platformName = 'Instagram' AND df.featureName = 'Instagram Post';

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Stories'
WHERE dp.platformName = 'Instagram' AND df.featureName = 'Instagram Stories';

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Post'
WHERE dp.platformName = 'Facebook' AND df.featureName = 'Facebook Post';

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Stories'
WHERE dp.platformName = 'Facebook' AND df.featureName = 'Facebook Stories';

UPDATE deliverableFeatures df
INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
SET df.featureName = 'Reels'
WHERE dp.platformName = 'Facebook' AND df.featureName = 'Facebook Reels';

-- Deliberately NOT renamed (platform name is not redundantly embedded):
-- YouTube "Community Post", "Long Videos", "Shorts"; LinkedIn/Pinterest
-- "Posting"; every other existing feature name across all platforms.
