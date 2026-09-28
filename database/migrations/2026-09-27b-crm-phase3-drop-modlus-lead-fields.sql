-- Real Estate CRM — Phase 3: remove the Modlus service fields from leads.
--
-- Run AFTER 2026-09-27-crm-phase3-core.sql and after the code no longer
-- references them (verified by a full reference sweep, 2026-09-28):
--   leads.orgName, leads.categoryId, leads.planId, leads.source (text;
--   replaced by leads.sourceId -> leadSources)
--   leadRemarks.followUpDateTime / followUpremark (remark-based "scheduled
--   calls"; migrated into leadFollowUps by the core migration)
--   leadCategories, leadPlans (service catalogue), leadConversions (price /
--   quotation conversion; Converted now = status + reason remark)
-- Idempotent: every drop is guarded by information_schema.

DROP PROCEDURE IF EXISTS crmDropIfExists;
DELIMITER $$
CREATE PROCEDURE crmDropIfExists(IN tbl VARCHAR(64), IN kind VARCHAR(20), IN name VARCHAR(64))
BEGIN
    IF kind = 'fk' AND EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND CONSTRAINT_NAME = name AND CONSTRAINT_TYPE = 'FOREIGN KEY') THEN
        SET @crmSql = CONCAT('ALTER TABLE `', tbl, '` DROP FOREIGN KEY `', name, '`');
        PREPARE s FROM @crmSql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
    IF kind = 'column' AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = name) THEN
        SET @crmSql = CONCAT('ALTER TABLE `', tbl, '` DROP COLUMN `', name, '`');
        PREPARE s FROM @crmSql; EXECUTE s; DEALLOCATE PREPARE s;
    END IF;
END$$
DELIMITER ;

CALL crmDropIfExists('leads', 'fk', 'fk_leads_category');
CALL crmDropIfExists('leads', 'fk', 'fk_leads_plan');
CALL crmDropIfExists('leads', 'column', 'categoryId');
CALL crmDropIfExists('leads', 'column', 'planId');
CALL crmDropIfExists('leads', 'column', 'orgName');
CALL crmDropIfExists('leads', 'column', 'source');
CALL crmDropIfExists('leadRemarks', 'column', 'followUpDateTime');
CALL crmDropIfExists('leadRemarks', 'column', 'followUpremark');

DROP PROCEDURE IF EXISTS crmDropIfExists;

DROP TABLE IF EXISTS leadConversions;
DROP TABLE IF EXISTS leadPlans;
DROP TABLE IF EXISTS leadCategories;

-- Source is now required for every lead.
UPDATE leads SET sourceId = (SELECT id FROM (SELECT id FROM leadSources WHERE sourceKey = 'other') o) WHERE sourceId IS NULL;
-- (the FK must be dropped to change nullability, then restored)
SET @crmHasFk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leads' AND CONSTRAINT_NAME = 'fk_leads_source');
SET @crmSql = IF(@crmHasFk > 0, 'ALTER TABLE leads DROP FOREIGN KEY fk_leads_source', 'DO 0');
PREPARE s FROM @crmSql; EXECUTE s; DEALLOCATE PREPARE s;
ALTER TABLE leads MODIFY sourceId INT NOT NULL;
ALTER TABLE leads ADD CONSTRAINT fk_leads_source FOREIGN KEY (sourceId) REFERENCES leadSources (id);
