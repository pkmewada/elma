-- Adds a manager-facing review/approval classification to Production Queue,
-- separate from the main production status lifecycle (NEW..PRODUCTION_READY).
-- Purely additive: one new column with a safe default, no table redesign,
-- no new table. The full remark trail for "Not Approved"/"Not For Use"
-- continues to live in the existing append-only socialContentProductionHistory
-- (via SocialContentProductionEngine::logHistory()), matching the same
-- "latest-on-the-row, trail-in-history" pattern submissionType/submissionUrl
-- already established in 2026-09-02d-social-content-production-submission.sql.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other ALTER-TABLE migrations).

ALTER TABLE socialContentProduction
  ADD COLUMN reviewStatus VARCHAR(40) NOT NULL DEFAULT 'Open' AFTER status;
