-- Lead Record: Country -> Country Code -> Contact Number.
-- Additive only -- existing `phone` column and its data are untouched, so
-- existing leads keep displaying/working exactly as before. `country` and
-- `countryCode` are new, nullable columns; NULL for every pre-existing lead
-- (no destructive backfill/migration).

ALTER TABLE leads
    ADD COLUMN country VARCHAR(100) NULL AFTER phone,
    ADD COLUMN countryCode VARCHAR(10) NULL AFTER country;
