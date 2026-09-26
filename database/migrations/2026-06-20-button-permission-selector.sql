ALTER TABLE permissionActions
    ADD COLUMN IF NOT EXISTS buttonSelector VARCHAR(255) DEFAULT NULL AFTER actionLabel;
