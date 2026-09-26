-- Add a new "Connected" lead status.
-- Additive only -- widens the existing status ENUM, default/existing values
-- untouched, no data backfill needed. Placed between 'interested' and
-- 'converted' to reflect where it sits in the lead lifecycle.

ALTER TABLE leads
    MODIFY status ENUM('open', 'interested', 'connected', 'converted', 'not_interested', 'not_connected') NOT NULL DEFAULT 'open';
