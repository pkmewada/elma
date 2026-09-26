-- Lead Management: configurable Follow Up System.
--
-- leadFollowUpSettings: admin-defined rules ("Day 1 -> Follow Up 1 -> Call").
-- leadFollowUps: the actual per-lead occurrences generated from those rules
-- when a lead is created (api/leads/addLead.php -> LeadFollowUpEngine::
-- generateForLead()). followUpSequence/followUpType are snapshotted onto
-- each row (not just a settingId FK) so editing/deleting a rule later never
-- rewrites history for leads already generated under the old rule set.
--
-- Reuses leads/leadRemarks/leadsActivityLogs as-is -- no changes to those
-- tables, no new history/notes system.

CREATE TABLE IF NOT EXISTS leadFollowUpSettings (
    id INT NOT NULL AUTO_INCREMENT,
    dayNumber INT NOT NULL,
    followUpSequence INT NOT NULL,
    followUpType VARCHAR(30) NOT NULL DEFAULT 'Call',
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdBy INT NOT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_lfus_active (isActive)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leadFollowUps (
    id INT NOT NULL AUTO_INCREMENT,
    leadId INT NOT NULL,
    settingId INT NULL,
    followUpSequence INT NOT NULL,
    followUpType VARCHAR(30) NOT NULL,
    dueDate DATE NOT NULL,
    status ENUM('Pending','Completed','Skipped') NOT NULL DEFAULT 'Pending',
    resolvedAt DATETIME NULL,
    resolvedByCandidateId INT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lfu_lead_setting (leadId, settingId),
    KEY idx_lfu_lead (leadId),
    KEY idx_lfu_due (dueDate),
    KEY idx_lfu_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default rules matching the worked example in the spec. Editable/removable
-- from the Follow Up Setup page like any other row -- this just avoids
-- shipping the page empty.
INSERT INTO leadFollowUpSettings (dayNumber, followUpSequence, followUpType, isActive, createdBy)
SELECT 1, 1, 'Call', 1, (SELECT id FROM users ORDER BY id ASC LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM leadFollowUpSettings);

INSERT INTO leadFollowUpSettings (dayNumber, followUpSequence, followUpType, isActive, createdBy)
SELECT 3, 2, 'Call', 1, (SELECT id FROM users ORDER BY id ASC LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM leadFollowUpSettings WHERE dayNumber = 3 AND followUpSequence = 2);

INSERT INTO leadFollowUpSettings (dayNumber, followUpSequence, followUpType, isActive, createdBy)
SELECT 7, 3, 'Call', 1, (SELECT id FROM users ORDER BY id ASC LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM leadFollowUpSettings WHERE dayNumber = 7 AND followUpSequence = 3);

INSERT INTO leadFollowUpSettings (dayNumber, followUpSequence, followUpType, isActive, createdBy)
SELECT 15, 4, 'Call', 1, (SELECT id FROM users ORDER BY id ASC LIMIT 1)
WHERE NOT EXISTS (SELECT 1 FROM leadFollowUpSettings WHERE dayNumber = 15 AND followUpSequence = 4);

-- Routes -- same moduleName ('Lead') as the rest of Lead Management so both
-- pages group together in Permission Setup / Route Setup.
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/lead-follow-up-setup', '/pages/lead-follow-up-setup.php', 'Follow Up Setup',
    'Lead', 'admin', 0, 1, 1, 23
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/lead-follow-up-setup'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/lead-follow-up-list', '/pages/lead-follow-up-list.php', 'Follow Up List',
    'Lead', 'admin', 0, 1, 1, 24
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/lead-follow-up-list'
);

-- Role access -- mirrors the Sales Executive grant already on /leads (the
-- sales team that works leads also works their follow-ups). Follow Up
-- Setup gets no extra role grants, matching /lead-setup's existing
-- admin-only precedent.
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove)
SELECT 'Sales Executive', rm.id, 1, 1, 1, 0, 0
FROM routesMaster rm
WHERE rm.routePath = '/lead-follow-up-list'
AND NOT EXISTS (
    SELECT 1 FROM rolePermissions rp
    WHERE rp.roleName = 'Sales Executive' AND rp.routeId = rm.id
);
