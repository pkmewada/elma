-- HRMS Candidate Record: adds a "Higher Budget" status value to the
-- existing candidateRecord status dropdown/update flow (same pattern as
-- the Walk-In status added in 2026-09-26-candidate-record-walkin-status.sql).

ALTER TABLE candidateRecord
    MODIFY status ENUM('open', 'interested', 'convert', 'in_progress', 'not_interested', 'walk_in', 'higher_budget')
        COLLATE utf8mb4_unicode_ci DEFAULT 'open';
