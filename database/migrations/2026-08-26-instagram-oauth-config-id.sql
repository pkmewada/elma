-- Facebook Login for Business: adds a Configuration ID field to the
-- existing Instagram Meta API settings so instagramOauthStart.php can pass
-- `config_id` (instead of `scope`) on the Meta authorize dialog when one is
-- configured. Backward-compatible: existing rows get an empty string,
-- which keeps the legacy scope-based OAuth flow working unchanged.
--
-- Also applied automatically at runtime by
-- includes/InstagramAutomation.php::ensureInstagramSettingsTable() for any
-- environment that hasn't run this migration yet (same self-healing
-- pattern already used for webhookVerifyToken/lastAnalyticsSyncAt) — running
-- this file explicitly is optional but keeps schema history in sync with
-- the other dated migrations in this folder.
--
-- Safe to run once. Re-running will fail on "Duplicate column name",
-- which just means it's already applied.

ALTER TABLE instagramSettings
    ADD COLUMN metaConfigId VARCHAR(191) NOT NULL DEFAULT '' AFTER metaAppId;
