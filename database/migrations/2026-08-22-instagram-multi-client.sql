-- Phase 2.5: Multi-Client Instagram Account Architecture Upgrade
--
-- Adds clientId to instagramAccounts (one client can connect one or more
-- Instagram Business accounts) and clientId + instagramAccountId to
-- instagramPosts (every post now belongs to a specific client AND a
-- specific one of that client's connected accounts, instead of always
-- publishing through "whichever account connected most recently").
--
-- Migration safety: both columns are added NULL (not NOT NULL). This repo's
-- local dev copies of instagramAccounts/instagramPosts are empty, but a
-- deployed environment may already have rows from Phase 1/2 single-account
-- testing. Those legacy rows are preserved as-is with clientId = NULL
-- ("unassigned") rather than guessed/auto-mapped to a client — the
-- application layer requires a real clientId + instagramAccountId on every
-- NEW write going forward, and the UI/API for both tables treat clientId
-- IS NULL rows as not belonging to any client's view. If a real deployment
-- has pre-existing rows that should be attributed to a specific client, that
-- is a manual one-time UPDATE an operator runs after reviewing the data —
-- this migration does not attempt to guess it.
--
-- This file runs once per environment, same as this repo's other
-- ALTER-based migrations (e.g. 2026-06-23-payroll-setup.sql) — re-running
-- it will error on duplicate column/constraint names, which is expected.

ALTER TABLE instagramAccounts
    ADD COLUMN clientId INT NULL DEFAULT NULL AFTER createdBy,
    ADD INDEX idxInstagramAccountsClientId (clientId);

ALTER TABLE instagramPosts
    ADD COLUMN clientId INT NULL DEFAULT NULL AFTER createdBy,
    ADD COLUMN instagramAccountId INT UNSIGNED NULL DEFAULT NULL AFTER clientId,
    ADD INDEX idxInstagramPostsClientId (clientId),
    ADD INDEX idxInstagramPostsAccountId (instagramAccountId);

-- Real FK constraints, matching the current convention for client-scoped
-- tables (clientCalendarPlans: clientId -> clientmaster(id) ON DELETE CASCADE)
-- rather than the older unconstrained-int style used by pre-Calendar tables.
ALTER TABLE instagramAccounts
    ADD CONSTRAINT fkInstagramAccountsClient
    FOREIGN KEY (clientId) REFERENCES clientMaster(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE instagramPosts
    ADD CONSTRAINT fkInstagramPostsClient
    FOREIGN KEY (clientId) REFERENCES clientMaster(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fkInstagramPostsAccount
    FOREIGN KEY (instagramAccountId) REFERENCES instagramAccounts(id)
    ON DELETE CASCADE ON UPDATE CASCADE;
