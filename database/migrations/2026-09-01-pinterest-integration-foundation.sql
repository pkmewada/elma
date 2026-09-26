-- Phase 13: Pinterest Integration Foundation
--
-- Adds the smallest schema addition needed to represent a Pinterest OAuth
-- connection and board selection, scoped to a Modlus client — mirrors
-- linkedinSettings/linkedinAccounts' shape (see
-- 2026-08-29-linkedin-integration-foundation.sql), with two additions
-- specific to Pinterest: a stored refreshToken (Pinterest access tokens
-- expire after 30 days and require a real refresh flow, unlike LinkedIn's
-- foundation-phase token) and a refreshTokenExpiry column (Pinterest's
-- continuous refresh token has its own 60-day rotating validity window).
-- No existing table, column, or route is modified. No companyId
-- introduced.
--
-- Also self-healed at runtime by ensurePinterestSettingsTable()/
-- ensurePinterestAccountsTable() in includes/PinterestAutomation.php — this
-- file documents the change explicitly and is safe to run manually on an
-- environment that hasn't picked it up yet (idempotent: CREATE TABLE IF
-- NOT EXISTS).

CREATE TABLE IF NOT EXISTS pinterestSettings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    pinterestClientId VARCHAR(191) NOT NULL DEFAULT '',
    pinterestClientSecret TEXT NOT NULL,
    redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pinterestAccounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NULL DEFAULT NULL,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    pinterestUserId VARCHAR(191) NOT NULL DEFAULT '',
    username VARCHAR(191) NOT NULL DEFAULT '',
    pinterestBoardId VARCHAR(191) NOT NULL DEFAULT '',
    boardName VARCHAR(191) NOT NULL DEFAULT '',
    accessToken TEXT NOT NULL,
    refreshToken TEXT NOT NULL,
    tokenExpiry DATETIME NULL DEFAULT NULL,
    refreshTokenExpiry DATETIME NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'connected',
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uqPinterestUserId (pinterestUserId),
    KEY idxPinterestClientId (clientId),
    CONSTRAINT fkPinterestAccountsClient FOREIGN KEY (clientId) REFERENCES clientMaster (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
