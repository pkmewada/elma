-- AI Configuration -- DB-backed Caption Area provider settings, admin
-- configurable from /ai-configuration instead of requiring an env var edit
-- + deploy after this migration.
--
-- Single active-row settings pattern, identical to instagramSettings
-- (ensureInstagramSettingsTable()/getInstagramSettingsRow() in
-- includes/InstagramAutomation.php) -- app-scoped, not per-client, latest
-- isActive row wins. anthropicApiKey is encrypted at rest with the
-- project's existing encryptSecret()/decryptSecret() (includes/Crypto.php,
-- AES-256-CBC via ENCRYPTION_KEY) -- the exact same mechanism
-- instagramSettings.metaAppSecret and instagramAccounts.accessToken
-- already use. No new encryption scheme.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE-TABLE migrations).

CREATE TABLE aiCaptionSettings (
  id INT NOT NULL AUTO_INCREMENT,
  provider VARCHAR(20) NOT NULL DEFAULT 'anthropic',
  anthropicApiKey TEXT NULL,
  anthropicModel VARCHAR(100) NULL,
  ollamaHost VARCHAR(255) NULL,
  ollamaModel VARCHAR(100) NULL,
  isActive TINYINT(1) NOT NULL DEFAULT 1,
  updatedBy INT NULL,
  createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
