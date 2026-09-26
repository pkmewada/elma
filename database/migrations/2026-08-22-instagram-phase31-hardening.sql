-- Phase 3.1: Production Hardening — admin error visibility for the
-- analytics sync (Task 5). Reuses the existing "each row carries its own
-- last-known-state" convention already used by instagramPosts.status /
-- instagramPosts.errorMessage rather than a new notification system or log
-- table.

ALTER TABLE instagramAccounts
    ADD COLUMN lastAnalyticsSyncAt DATETIME NULL DEFAULT NULL AFTER tokenExpiry,
    ADD COLUMN lastAnalyticsSyncError TEXT NULL AFTER lastAnalyticsSyncAt;
