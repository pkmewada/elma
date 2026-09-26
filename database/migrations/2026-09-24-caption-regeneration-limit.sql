-- Caption regeneration limit (max 3 regenerations per caption task) +
-- manual caption editing. Reuses the existing socialContentCaption table
-- -- one minimal counter column, no new table.
--
-- regenerationCount counts only successful *regenerations* (a
-- generateCaption() call where a caption row already existed -- i.e. the
-- UPDATE branch), never the first/initial generation (the INSERT branch),
-- and never a failed AI call (the counter is only ever incremented in the
-- same statement that persists a successful regeneration). Enforced
-- server-side in SocialContentCaptionEngine::generateCaption().
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other ALTER-TABLE migrations).

ALTER TABLE socialContentCaption
  ADD COLUMN regenerationCount INT NOT NULL DEFAULT 0 AFTER selectedCaption;
