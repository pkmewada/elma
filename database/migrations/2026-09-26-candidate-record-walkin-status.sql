-- HRMS Candidate Record: adds a "Walk-In" status value so a candidate can
-- be marked Walk-In from the existing status dropdown/update flow. This is
-- a status on candidateRecord itself -- unrelated to the separate
-- walkInCandidates table/engine (pages/walkin-candidates.php), which is
-- left untouched.

ALTER TABLE candidateRecord
    MODIFY status ENUM('open', 'interested', 'convert', 'in_progress', 'not_interested', 'walk_in')
        COLLATE utf8mb4_unicode_ci DEFAULT 'open';
