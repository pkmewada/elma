-- Social Content Production — output submission.
--
-- Before this migration, socialContentProduction had no way to record what
-- the editor actually handed back (a Google Drive link or an uploaded
-- file) — "submit" only flipped status with an optional free-text note.
--
-- Two columns, both nullable, both on socialContentProduction only — never
-- clientSocialContent, which remains the sole owner of raw-material fields
-- (title/caption/rawContent/etc, untouched by this migration).
--
-- submissionType + submissionUrl together hold the LATEST submission only
-- (overwritten on resubmission after a correction) — no version table.
-- The full sequence of past submissions stays reconstructable from
-- socialContentProductionHistory, whose 'submitted' remark records what
-- was submitted at each point in time; that table is unchanged here.
--
-- No submissionRemark column: the editor's note continues through the
-- existing socialContentProductionHistory.remark mechanism.
-- No submittedBy column: submittedAt already exists, and "submitted by" is
-- the task's already-known assignedEditorId.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other ALTER-based migrations).

ALTER TABLE socialContentProduction
    ADD COLUMN submissionType VARCHAR(10) NULL DEFAULT NULL AFTER dueAt,
    ADD COLUMN submissionUrl VARCHAR(500) NULL DEFAULT NULL AFTER submissionType;
