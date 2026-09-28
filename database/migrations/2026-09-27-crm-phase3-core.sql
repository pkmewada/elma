-- Real Estate CRM — Phase 3 core model (CRM database only).
--
-- Projects portfolio, lead sources, real-estate pipeline, assignment,
-- manual follow-ups, actor-type identity. Additive except for the Modlus
-- service fields, which are removed in 2026-09-27b after the code no longer
-- references them. Deterministic/idempotent: guarded with IF NOT EXISTS /
-- information_schema checks so a re-run is a no-op.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. Projects (portfolio, not inventory)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id INT NOT NULL AUTO_INCREMENT,
    projectName VARCHAR(150) NOT NULL,
    developerName VARCHAR(150) NULL,
    location VARCHAR(255) NULL,
    description TEXT NULL,
    propertyType VARCHAR(50) NULL,
    configuration VARCHAR(255) NULL,
    pricing VARCHAR(255) NULL,
    amenities TEXT NULL,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdByType ENUM('admin','employee','system') NOT NULL DEFAULT 'system',
    createdById INT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_projects_name (projectName),
    KEY idx_projects_active (isActive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS projectDocuments (
    id INT NOT NULL AUTO_INCREMENT,
    projectId INT NOT NULL,
    documentType ENUM('image','brochure','floor_plan','price_list','other') NOT NULL,
    fileName VARCHAR(80) NOT NULL,
    originalFileName VARCHAR(255) NOT NULL,
    mimeType VARCHAR(100) NOT NULL,
    fileSize INT NOT NULL,
    uploadedByType ENUM('admin','employee','system') NOT NULL DEFAULT 'system',
    uploadedById INT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pd_project (projectId),
    CONSTRAINT fk_projectDocuments_project FOREIGN KEY (projectId) REFERENCES projects (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 2. Lead sources (one master; manual entry and future integrations use it)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS leadSources (
    id INT NOT NULL AUTO_INCREMENT,
    sourceKey VARCHAR(50) NOT NULL,
    sourceName VARCHAR(100) NOT NULL,
    isSystem TINYINT(1) NOT NULL DEFAULT 0,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    sortOrder INT NOT NULL DEFAULT 100,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_leadSources_key (sourceKey),
    UNIQUE KEY uq_leadSources_name (sourceName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO leadSources (sourceKey, sourceName, isSystem, sortOrder) VALUES
    ('meta_lead_ads', 'Meta Lead Ads', 1, 10),
    ('google_ads', 'Google Lead Forms / Ads', 1, 20),
    ('website', 'Website', 1, 30),
    ('call', 'Call', 1, 40),
    ('walk_in', 'Walk-in', 1, 50),
    ('referral', 'Referral', 1, 60),
    ('whatsapp', 'WhatsApp', 1, 70),
    ('offline', 'Offline', 1, 80),
    ('other', 'Other', 1, 90);

-- ---------------------------------------------------------------------------
-- 3. Leads: project, source, assignment, pipeline, updatedAt
-- ---------------------------------------------------------------------------
DROP PROCEDURE IF EXISTS crmAddColumn;
DELIMITER $$
CREATE PROCEDURE crmAddColumn(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
        SET @crmSql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE crmStmt FROM @crmSql; EXECUTE crmStmt; DEALLOCATE PREPARE crmStmt;
    END IF;
END$$
DELIMITER ;

CALL crmAddColumn('leads', 'projectId', 'projectId INT NULL AFTER countryCode');
CALL crmAddColumn('leads', 'sourceId', 'sourceId INT NULL AFTER projectId');
CALL crmAddColumn('leads', 'assignedToId', 'assignedToId INT NULL AFTER sourceId');
CALL crmAddColumn('leads', 'createdByType', "createdByType ENUM('admin','employee','system') NOT NULL DEFAULT 'system' AFTER createdByCandidateId");
CALL crmAddColumn('leads', 'updatedAt', 'updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER createdAt');

-- Pipeline: widen, map old values, then restrict to the approved set.
ALTER TABLE leads MODIFY status ENUM('open','interested','connected','converted','not_interested','not_connected',
    'new','contacted','follow_up','site_visit','negotiation','lost') NOT NULL DEFAULT 'new';
UPDATE leads SET status = CASE status
    WHEN 'open' THEN 'new'
    WHEN 'connected' THEN 'contacted'
    WHEN 'not_connected' THEN 'contacted'
    WHEN 'not_interested' THEN 'lost'
    ELSE status END
WHERE status IN ('open','connected','not_connected','not_interested');
UPDATE leadStatusRemarks SET status = CASE status
    WHEN 'open' THEN 'new' WHEN 'connected' THEN 'contacted' WHEN 'not_connected' THEN 'contacted' WHEN 'not_interested' THEN 'lost'
    ELSE status END;
ALTER TABLE leads MODIFY status ENUM('new','contacted','interested','follow_up','site_visit','negotiation','converted','lost') NOT NULL DEFAULT 'new';

-- Existing rows: the creating employee becomes the assignee; source text -> master.
UPDATE leads SET assignedToId = createdByCandidateId WHERE assignedToId IS NULL AND createdByCandidateId IS NOT NULL;
UPDATE leads SET createdByType = 'employee' WHERE createdByCandidateId IS NOT NULL AND createdByType = 'system';
UPDATE leads l LEFT JOIN leadSources s ON s.sourceName = l.source SET l.sourceId = COALESCE(s.id, (SELECT id FROM (SELECT id FROM leadSources WHERE sourceKey = 'other') o)) WHERE l.sourceId IS NULL;

-- Keys / foreign keys (guarded)
DROP PROCEDURE IF EXISTS crmAddConstraint;
DELIMITER $$
CREATE PROCEDURE crmAddConstraint(IN tbl VARCHAR(64), IN name VARCHAR(64), IN ddl TEXT)
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND CONSTRAINT_NAME = name)
       AND NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = name) THEN
        SET @crmSql = CONCAT('ALTER TABLE `', tbl, '` ADD ', ddl);
        PREPARE crmStmt FROM @crmSql; EXECUTE crmStmt; DEALLOCATE PREPARE crmStmt;
    END IF;
END$$
DELIMITER ;

CALL crmAddConstraint('leads', 'idx_leads_assigned', 'KEY idx_leads_assigned (assignedToId)');
CALL crmAddConstraint('leads', 'idx_leads_status', 'KEY idx_leads_status (status)');
CALL crmAddConstraint('leads', 'fk_leads_project', 'CONSTRAINT fk_leads_project FOREIGN KEY (projectId) REFERENCES projects (id) ON DELETE SET NULL');
CALL crmAddConstraint('leads', 'fk_leads_source', 'CONSTRAINT fk_leads_source FOREIGN KEY (sourceId) REFERENCES leadSources (id)');
CALL crmAddConstraint('leads', 'fk_leads_assigned', 'CONSTRAINT fk_leads_assigned FOREIGN KEY (assignedToId) REFERENCES employeeusers (id) ON DELETE SET NULL');

-- ---------------------------------------------------------------------------
-- 4. Follow-ups: one system (rule-generated + manual) in leadFollowUps
-- ---------------------------------------------------------------------------
CALL crmAddColumn('leadFollowUps', 'dueTime', 'dueTime TIME NULL AFTER dueDate');
CALL crmAddColumn('leadFollowUps', 'remark', 'remark TEXT NULL AFTER status');
CALL crmAddColumn('leadFollowUps', 'createdByType', "createdByType ENUM('admin','employee','system') NOT NULL DEFAULT 'system' AFTER remark");
CALL crmAddColumn('leadFollowUps', 'createdById', 'createdById INT NULL AFTER createdByType');
CALL crmAddColumn('leadFollowUps', 'resolvedByType', "resolvedByType ENUM('admin','employee','system') NULL AFTER resolvedByCandidateId");
ALTER TABLE leadFollowUps MODIFY followUpSequence INT NULL, MODIFY followUpType VARCHAR(30) NOT NULL DEFAULT 'Call';
CALL crmAddConstraint('leadFollowUps', 'fk_leadFollowUps_lead', 'CONSTRAINT fk_leadFollowUps_lead FOREIGN KEY (leadId) REFERENCES leads (id) ON DELETE CASCADE');

-- Old remark-based "scheduled calls" -> manual follow-ups (then the remark
-- columns are dropped in 2026-09-27b).
INSERT INTO leadFollowUps (leadId, settingId, followUpSequence, followUpType, dueDate, dueTime, status, remark, createdByType, createdById, resolvedAt)
SELECT lr.leadId, NULL, NULL, 'Call', DATE(lr.followUpDateTime), TIME(lr.followUpDateTime),
       IF(lr.followUpremark = 'close', 'Completed', 'Pending'), lr.remark, 'employee', lr.createdByCandidateId,
       IF(lr.followUpremark = 'close', lr.updatedAt, NULL)
FROM leadRemarks lr
WHERE lr.followUpDateTime IS NOT NULL
AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'leadRemarks' AND COLUMN_NAME = 'followUpDateTime')
AND NOT EXISTS (SELECT 1 FROM leadFollowUps f WHERE f.leadId = lr.leadId AND f.settingId IS NULL AND f.dueDate = DATE(lr.followUpDateTime));

-- ---------------------------------------------------------------------------
-- 5. Actor identity (admin users.id vs employee employeeusers.id)
-- ---------------------------------------------------------------------------
CALL crmAddColumn('leadsActivityLogs', 'actorType', "actorType ENUM('admin','employee','system','unknown') NOT NULL DEFAULT 'unknown' AFTER createdBy");
CALL crmAddColumn('leadRemarks', 'createdByType', "createdByType ENUM('admin','employee','system','unknown') NOT NULL DEFAULT 'unknown' AFTER createdByCandidateId");
CALL crmAddColumn('leadStatusRemarks', 'createdByType', "createdByType ENUM('admin','employee','system','unknown') NOT NULL DEFAULT 'unknown' AFTER createdByCandidateId");
CALL crmAddColumn('leadDocuments', 'uploadedByType', "uploadedByType ENUM('admin','employee','system','unknown') NOT NULL DEFAULT 'unknown' AFTER uploadedByCandidateId");

DROP PROCEDURE IF EXISTS crmAddColumn;
DROP PROCEDURE IF EXISTS crmAddConstraint;

-- ---------------------------------------------------------------------------
-- 6. Routes, permissions, sidebar
-- ---------------------------------------------------------------------------
INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/projects', '/pages/projects.php', 'Projects', 'Projects', 'admin', 0, 1, 1, 10, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/projects');
INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/emp-projects', '/employee/emp-projects.php', 'Projects', 'Projects', 'employee', 0, 1, 1, 10, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-projects');
INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/emp-follow-ups', '/employee/emp-follow-ups.php', 'Follow-ups', 'Lead Management', 'employee', 0, 1, 1, 25, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-follow-ups');

UPDATE routesMaster SET routeTitle = 'Lead Sources', sortOrder = 11, updatedAt = NOW() WHERE routePath = '/lead-setup';
UPDATE routesMaster SET routeTitle = 'Company', updatedAt = NOW() WHERE routePath = '/company-setup';
UPDATE routesMaster SET sortOrder = 30 WHERE routePath = '/emp-lead-dashboard';
UPDATE routesMaster SET sortOrder = 40 WHERE routePath = '/emp-lead-activity';

-- Both sales roles: read active projects; work their own follow-ups.
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT r.roleName, rm.id, 1, 0, 0, 0, 0, 0, NOW(), NOW()
FROM routesMaster rm CROSS JOIN (SELECT 'Sales Executive' AS roleName UNION ALL SELECT 'Sales Manager') r
WHERE rm.routePath = '/emp-projects'
AND NOT EXISTS (SELECT 1 FROM rolePermissions rp WHERE rp.roleName = r.roleName AND rp.routeId = rm.id);
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, canExport, createdAt, updatedAt)
SELECT r.roleName, rm.id, 1, 1, 1, 0, 0, 0, NOW(), NOW()
FROM routesMaster rm CROSS JOIN (SELECT 'Sales Executive' AS roleName UNION ALL SELECT 'Sales Manager') r
WHERE rm.routePath = '/emp-follow-ups'
AND NOT EXISTS (SELECT 1 FROM rolePermissions rp WHERE rp.roleName = r.roleName AND rp.routeId = rm.id);

-- Special action: assign / reassign leads (admins always; Sales Manager by default).
INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder, createdAt, updatedAt)
SELECT rm.id, 'assign_lead', 'Assign / Reassign Lead', 'special', '.assign-lead-btn', NULL, NULL, 1, 15, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath IN ('/emp-leads', '/leads') AND rm.isActive = 1
AND NOT EXISTS (SELECT 1 FROM permissionActions pa WHERE pa.routeId = rm.id AND pa.actionKey = 'assign_lead');
INSERT INTO roleActionPermissions (roleName, actionId, canAccess, createdAt, updatedAt)
SELECT 'Sales Manager', pa.id, 1, NOW(), NOW()
FROM permissionActions pa INNER JOIN routesMaster rm ON rm.id = pa.routeId
WHERE rm.routePath = '/emp-leads' AND pa.actionKey = 'assign_lead'
AND NOT EXISTS (SELECT 1 FROM roleActionPermissions rap WHERE rap.roleName = 'Sales Manager' AND rap.actionId = pa.id);

-- Gateway mappings for the new single-route admin APIs.
INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder, createdAt, updatedAt)
SELECT rm.id, a.actionKey, a.actionLabel, a.permissionType, NULL, a.apiEndpoint, 'POST', 1, a.sortOrder, NOW(), NOW()
FROM (
    SELECT '/projects' AS routePath, 'save_project' AS actionKey, 'Save Project' AS actionLabel, 'canEdit' AS permissionType, '/api/projects/saveProject.php' AS apiEndpoint, 10 AS sortOrder
    UNION ALL SELECT '/projects', 'set_project_status', 'Activate / Deactivate Project', 'canEdit', '/api/projects/setProjectStatus.php', 20
    UNION ALL SELECT '/projects', 'upload_project_document', 'Upload Project Document', 'canEdit', '/api/projects/uploadProjectDocument.php', 30
    UNION ALL SELECT '/projects', 'delete_project_document', 'Delete Project Document', 'canDelete', '/api/projects/deleteProjectDocument.php', 40
    UNION ALL SELECT '/lead-setup', 'save_lead_source', 'Save Lead Source', 'canEdit', '/api/leads/saveLeadSource.php', 20
) a
INNER JOIN routesMaster rm ON rm.routePath = a.routePath AND rm.isActive = 1
WHERE NOT EXISTS (SELECT 1 FROM permissionActions pa WHERE pa.routeId = rm.id AND pa.actionKey = a.actionKey);

-- Lead Setup no longer saves categories/plans.
UPDATE permissionActions SET isActive = 0 WHERE actionKey = 'save_lead_setup';
