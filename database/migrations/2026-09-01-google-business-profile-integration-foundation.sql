-- Phase 14: Google Business Profile Integration Foundation
--
-- Adds the smallest schema addition needed to represent a Google Business
-- Profile OAuth connection and account/location selection, scoped to a
-- Modlus client — see docs/GMB_INTEGRATION_FOUNDATION_PHASE_14.md (source
-- of truth for this phase) and mirrors the LinkedIn/Pinterest foundation
-- tables' shape (2026-08-29-linkedin-integration-foundation.sql,
-- 2026-09-01-pinterest-integration-foundation.sql), with one structural
-- addition: Google's resource hierarchy (Google user -> Business Profile
-- account -> location) is three levels deep, not two, so
-- googleBusinessProfileAccounts stores account and location identifiers
-- as separate, non-collapsed columns (googleAccountId/googleAccountName/
-- googleAccountType and googleLocationId/googleLocationName/
-- locationTitle/locationAddress) rather than a single selected-resource
-- pair. The unique key is (googleUserId, googleAccountId) rather than the
-- vendor user id alone, because a single Google identity may legitimately
-- manage more than one Business Profile account across different Modlus
-- clients (an agency scenario) -- see the module's own doc comments in
-- includes/GoogleBusinessProfileAutomation.php for the upsert logic this
-- requires. No existing table, column, or route is modified. No
-- companyId introduced.
--
-- Also self-healed at runtime by ensureGoogleBusinessProfileSettingsTable()/
-- ensureGoogleBusinessProfileAccountsTable() in
-- includes/GoogleBusinessProfileAutomation.php — this file documents the
-- change explicitly and is safe to run manually on an environment that
-- hasn't picked it up yet (idempotent: CREATE TABLE IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS googleBusinessProfileSettings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    googleClientId VARCHAR(191) NOT NULL DEFAULT '',
    googleClientSecret TEXT NOT NULL,
    redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS googleBusinessProfileAccounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NULL DEFAULT NULL,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,

    googleUserId VARCHAR(191) NOT NULL DEFAULT '',
    googleUserEmail VARCHAR(191) NOT NULL DEFAULT '',

    googleAccountId VARCHAR(191) NOT NULL DEFAULT '',
    googleAccountName VARCHAR(255) NOT NULL DEFAULT '',
    googleAccountType VARCHAR(100) NOT NULL DEFAULT '',

    googleLocationId VARCHAR(191) NOT NULL DEFAULT '',
    googleLocationName VARCHAR(255) NOT NULL DEFAULT '',
    locationTitle VARCHAR(255) NOT NULL DEFAULT '',
    locationAddress TEXT NULL,

    accessToken TEXT NOT NULL,
    refreshToken TEXT NOT NULL,

    tokenExpiry DATETIME NULL DEFAULT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'connected',

    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uqGoogleUserAccount (googleUserId, googleAccountId),
    KEY idxGoogleBusinessProfileClientId (clientId),

    CONSTRAINT fkGoogleBusinessProfileAccountsClient
        FOREIGN KEY (clientId)
        REFERENCES clientMaster (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
