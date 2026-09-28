-- Real Estate CRM — Phase 6: Lead Capture Integrations.
--
-- All external leads (Meta / Google / Website) go through ONE creation path
-- (includes/leadAccess.php::createLeadFromSource(), built on the same
-- createLeadCore() that api/leads/addLead.php uses) so they appear in the
-- existing Dashboard/Reports/Leads page automatically -- no separate
-- integration tables feed those screens.
--
-- 1. leads: externalSource/externalLeadId for webhook/retry idempotency
--    (source + external id, NOT a global phone/email dedupe -- that already
--    exists in addLead.php/createLeadCore() and is reused, not duplicated).
-- 2. integrationSettings: one row per provider (meta/google/website).
--    Secrets are Crypto-encrypted (includes/Crypto.php, first real use).
-- 3. integrationFormMappings: optional Meta/Google form -> project/assignee.
-- 4. integrationLogs: append-only, no raw payloads/tokens -- see
--    includes/integrationAccess.php::logIntegrationEvent().
--
-- Additive/idempotent throughout.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. leads: external identity for idempotent webhook capture
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crmAddColumn;
DELIMITER $$
CREATE PROCEDURE crmAddColumn(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @crmSql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE crmStmt FROM @crmSql; EXECUTE crmStmt; DEALLOCATE PREPARE crmStmt;
    END IF;
END$$
DELIMITER ;

CALL crmAddColumn('leads', 'externalSource', "externalSource VARCHAR(30) NULL AFTER sourceId");
CALL crmAddColumn('leads', 'externalLeadId', "externalLeadId VARCHAR(191) NULL AFTER externalSource");

DROP PROCEDURE IF EXISTS crmAddColumn;

-- MySQL/MariaDB unique indexes treat each NULL as distinct, so manual leads
-- (both columns NULL) are never affected by this constraint.
SET @crmHasUq = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND INDEX_NAME = 'uq_leads_external');
SET @crmSql = IF(@crmHasUq = 0, 'ALTER TABLE leads ADD UNIQUE KEY uq_leads_external (externalSource, externalLeadId)', 'DO 0');
PREPARE crmStmt FROM @crmSql; EXECUTE crmStmt; DEALLOCATE PREPARE crmStmt;

-- ---------------------------------------------------------------------------
-- 2. Integration settings (one row per provider; secrets encrypted)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS integrationSettings (
    id INT NOT NULL AUTO_INCREMENT,
    provider ENUM('meta','google','website') NOT NULL,
    isEnabled TINYINT(1) NOT NULL DEFAULT 0,
    configJson TEXT NULL COMMENT 'Non-secret settings (page id, form ids, default source id etc), JSON',
    secretEncrypted TEXT NULL COMMENT 'Provider secret(s) as JSON, encrypted with includes/Crypto.php',
    lastSuccessAt DATETIME NULL,
    lastErrorAt DATETIME NULL,
    lastErrorMessage VARCHAR(255) NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integrationSettings_provider (provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO integrationSettings (provider, isEnabled) VALUES ('meta', 0), ('google', 0), ('website', 0);

-- ---------------------------------------------------------------------------
-- 3. Form -> project/assignee mapping (Meta / Google only; Website has no forms)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS integrationFormMappings (
    id INT NOT NULL AUTO_INCREMENT,
    provider ENUM('meta','google') NOT NULL,
    externalFormId VARCHAR(100) NOT NULL,
    externalPageId VARCHAR(100) NULL,
    formLabel VARCHAR(150) NULL COMMENT 'Admin-facing note, e.g. form/campaign name',
    projectId INT NULL,
    defaultAssigneeId INT NULL,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integrationFormMappings (provider, externalFormId),
    KEY idx_integrationFormMappings_project (projectId),
    CONSTRAINT fk_integrationFormMappings_project FOREIGN KEY (projectId) REFERENCES projects (id) ON DELETE SET NULL,
    CONSTRAINT fk_integrationFormMappings_assignee FOREIGN KEY (defaultAssigneeId) REFERENCES employeeusers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Integration log (append-only; no raw payloads/tokens -- see
--    includes/integrationAccess.php::logIntegrationEvent())
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS integrationLogs (
    id INT NOT NULL AUTO_INCREMENT,
    provider VARCHAR(20) NOT NULL,
    externalId VARCHAR(191) NULL,
    eventType VARCHAR(40) NOT NULL,
    leadId INT NULL,
    status ENUM('received','duplicate','rejected','created','error') NOT NULL,
    message VARCHAR(255) NULL,
    payloadSummary VARCHAR(500) NULL COMMENT 'Normalized fields actually used (name/phone/email/form); never raw payload or tokens',
    receivedAt DATETIME NOT NULL,
    processedAt DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_integrationLogs_provider (provider, receivedAt),
    KEY idx_integrationLogs_lead (leadId),
    CONSTRAINT fk_integrationLogs_lead FOREIGN KEY (leadId) REFERENCES leads (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 5. Route, sidebar (Integrations group already defined, admin-only), permission
-- ---------------------------------------------------------------------------
INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/integrations', '/pages/integrations.php', 'Integrations', 'Integrations', 'admin', 0, 1, 1, 10, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/integrations');

-- Special action for the settings/mapping-save + enable/disable buttons
-- (admin always passes; not granted to any role by default, same precedent
-- as every other admin-only settings page).
INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder, createdAt, updatedAt)
SELECT rm.id, 'manage_integrations', 'Manage Integrations', 'special', '.integration-save-btn', NULL, NULL, 1, 10, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath = '/integrations'
AND NOT EXISTS (SELECT 1 FROM permissionActions pa WHERE pa.routeId = rm.id AND pa.actionKey = 'manage_integrations');

-- ---------------------------------------------------------------------------
-- 6. Legal pages required for Meta App Review -- rewritten for Elma Real
--    Estate (pages/{privacy-policy,terms-of-service,data-deletion}.php),
--    reactivated. Public, so no permission row needed.
-- ---------------------------------------------------------------------------
UPDATE routesMaster SET isActive = 1, updatedAt = NOW()
WHERE routePath IN ('/privacy-policy', '/terms-of-service', '/data-deletion');
