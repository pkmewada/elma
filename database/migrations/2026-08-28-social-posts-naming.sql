-- Phase 8: Naming refactor — instagramPosts -> socialPosts
--
-- The table stores Instagram-only, Facebook-only, and Instagram+Facebook
-- posts alike since Phase 6/7 — the old name is now misleading. A plain
-- RENAME TABLE is used deliberately: it is an atomic metadata operation
-- that preserves every row, every column, every value, every index, the
-- primary key, all defaults, and all timestamps. Nothing is dropped,
-- copied, or re-created, so there is no data-loss window and no downtime
-- beyond the rename itself.
--
-- No column, data semantics, or unrelated schema change is made here.
-- No companyId is introduced.
--
-- This is also self-healed at runtime by ensureSocialPostsTable() in
-- includes/InstagramAutomation.php (same RENAME TABLE, guarded by
-- SHOW TABLES checks) — this file documents the change explicitly and is
-- safe to run manually on an environment that hasn't picked it up yet.

RENAME TABLE instagramPosts TO socialPosts;
