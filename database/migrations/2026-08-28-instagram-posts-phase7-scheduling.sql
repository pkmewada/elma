-- Phase 7: Unified Scheduled Publishing
--
-- Additive columns on the existing instagramPosts table so a scheduled post
-- can target Instagram, Facebook, or both, with each platform's own
-- completion state tracked independently. Defaults are chosen so every
-- pre-Phase-7 row is read exactly as it always has been:
--   platforms      = 'instagram'      -> every existing row is Instagram-only,
--                                        unchanged from before this migration.
--   facebookStatus = 'not_applicable' -> Facebook was never selected for it.
--
-- status/instagramMediaId/errorMessage keep their exact pre-existing meaning
-- and are NOT altered by this migration. One new value, 'partial', becomes
-- valid for the existing `status` column (no schema change needed — it was
-- already a free-form VARCHAR(20)) to represent a dual-platform post where
-- one platform succeeded and the other failed.
--
-- Also self-healed at runtime by ensureInstagramPostsTable() /
-- instagramPostsEnsureColumn() in includes/InstagramAutomation.php, so this
-- file documents the change and is safe to run manually on an environment
-- that hasn't picked it up yet, but is not the only place it's applied.

ALTER TABLE instagramPosts
    ADD COLUMN platforms VARCHAR(30) NOT NULL DEFAULT 'instagram' AFTER instagramAccountId,
    ADD COLUMN facebookStatus VARCHAR(20) NOT NULL DEFAULT 'not_applicable' AFTER instagramMediaId,
    ADD COLUMN facebookPostId VARCHAR(64) NOT NULL DEFAULT '' AFTER facebookStatus,
    ADD COLUMN facebookErrorMessage TEXT NULL DEFAULT NULL AFTER facebookPostId;
