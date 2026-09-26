-- Renames the reviewStatus value 'Approved By Varun' -> 'Approved By Team'
-- (business decision: a role-neutral, team-based approval label instead of
-- naming one person). Data-only -- no schema change, socialContentProduction
-- .reviewStatus stays VARCHAR(40), same column, same six-value set otherwise.
--
-- Scoped to the live reviewStatus column only. socialContentProductionHistory
-- rows that recorded the old label in their free-text remark (e.g. 'Review
-- status set to "Approved By Varun"') are left exactly as they were written --
-- that table is an append-only audit log of what was said at the time and is
-- never rewritten (same rule this project already applies everywhere else).
--
-- This file runs once per environment; re-running it is harmless (the WHERE
-- clause simply matches zero rows the second time).

UPDATE socialContentProduction
SET reviewStatus = 'Approved By Team'
WHERE reviewStatus = 'Approved By Varun';
