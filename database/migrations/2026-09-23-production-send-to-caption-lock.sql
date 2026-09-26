-- "Send to Caption" explicit handoff + post-handoff lock for Social
-- Content production tasks.
--
-- NULL = not yet sent to Caption Area (production/review remain editable,
-- exactly as before this migration). NOT NULL = sent -- the task is now
-- locked against further assign/start/submit/review/markReady edits
-- (enforced server-side in SocialContentProductionEngine::lockTask()/
-- lockOwnTask(), the shared gate every mutating method already funnels
-- through) and is the point at which the task becomes visible to Caption
-- Area (SocialContentCaptionEngine::getQueue()/assertEligible() now also
-- require this to be set, alongside the existing reviewStatus check).
--
-- Deliberately a single nullable timestamp column, not a new status value
-- in socialContentProduction.status's own lifecycle -- no new workflow
-- state was introduced, and there is intentionally no "un-send" column/
-- action to go with it.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other ALTER-TABLE migrations).

ALTER TABLE socialContentProduction
  ADD COLUMN sentToCaptionAt DATETIME NULL DEFAULT NULL AFTER reviewStatus;

-- Backfill: any task that already has a socialContentCaption row was
-- effectively sent under the old (automatic, reviewStatus-only) rule
-- before this lock/gate existed. Without this, those real, already-in-
-- progress-or-completed tasks would silently vanish from Caption Area's
-- queue (which now also requires sentToCaptionAt) and would incorrectly
-- stay unlocked/editable in Production -- exactly the "affect already
-- completed records incorrectly" this feature must avoid. Grandfathered
-- to the caption row's own createdAt, not NOW(), so their history stays
-- accurate.
UPDATE socialContentProduction p
INNER JOIN socialContentCaption cap ON cap.productionId = p.id
SET p.sentToCaptionAt = cap.createdAt
WHERE p.sentToCaptionAt IS NULL;
