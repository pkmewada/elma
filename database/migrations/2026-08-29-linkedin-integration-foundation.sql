-- Phase 12: LinkedIn Integration Foundation
--
-- Adds the smallest schema addition needed to represent a LinkedIn OAuth
-- connection and organization selection, scoped to a Modlus client — the
-- existing instagramAccounts/socialPosts tables could not cleanly represent
-- this (different identifiers: LinkedIn member id + organization id vs
-- Instagram user id + Facebook Page id; different token/auth shape) so a
-- dedicated pair of tables is used, mirroring instagramSettings/
-- instagramAccounts' existing shape exactly. No existing table, column, or
-- route is modified. No companyId introduced.
--
-- Also self-healed at runtime by ensureLinkedinSettingsTable()/
-- ensureLinkedinAccountsTable() in includes/LinkedInAutomation.php — this
-- file documents the change explicitly and is safe to run manually on an
-- environment that hasn't picked it up yet (idempotent: CREATE TABLE IF
-- NOT EXISTS).

CREATE TABLE IF NOT EXISTS linkedinSettings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    linkedinClientId VARCHAR(191) NOT NULL DEFAULT '',
    linkedinClientSecret TEXT NOT NULL,
    redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS linkedinAccounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NULL DEFAULT NULL,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    linkedinMemberId VARCHAR(191) NOT NULL DEFAULT '',
    memberName VARCHAR(191) NOT NULL DEFAULT '',
    linkedinOrganizationId VARCHAR(191) NOT NULL DEFAULT '',
    organizationName VARCHAR(191) NOT NULL DEFAULT '',
    accessToken TEXT NOT NULL,
    tokenExpiry DATETIME NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'connected',
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uqLinkedinMemberId (linkedinMemberId),
    KEY idxLinkedinClientId (clientId),
    CONSTRAINT fkLinkedinAccountsClient FOREIGN KEY (clientId) REFERENCES clientMaster (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
