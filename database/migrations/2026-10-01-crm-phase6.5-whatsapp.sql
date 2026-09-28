-- Real Estate CRM — Phase 6.5: WhatsApp CRM Chat (Meta WhatsApp Cloud API).
--
-- Reuses the Phase 6 integration settings/secret/log infrastructure (adds
-- 'whatsapp' as a 4th provider on the existing integrationSettings table --
-- no second config system) and the existing lead access/activity/sidebar
-- conventions. Two new tables only: whatsappConversations, whatsappMessages.
--
-- Additive/idempotent throughout.

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. integrationSettings.provider: add 'whatsapp' (guarded ENUM widen)
-- ---------------------------------------------------------------------------
SET @crmHasWa = (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'integrationSettings' AND COLUMN_NAME = 'provider'
    AND COLUMN_TYPE LIKE '%whatsapp%'
);
SET @crmSql = IF(@crmHasWa = 0,
    "ALTER TABLE integrationSettings MODIFY COLUMN provider ENUM('meta','google','website','whatsapp') NOT NULL",
    'DO 0');
PREPARE crmStmt FROM @crmSql; EXECUTE crmStmt; DEALLOCATE PREPARE crmStmt;

INSERT IGNORE INTO integrationSettings (provider, isEnabled) VALUES ('whatsapp', 0);

-- ---------------------------------------------------------------------------
-- 2. whatsappConversations
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsappConversations (
    id INT NOT NULL AUTO_INCREMENT,
    leadId INT NULL,
    waId VARCHAR(30) NOT NULL COMMENT 'Meta wa_id -- canonical WhatsApp identifier, digits only, as sent by Meta',
    phoneNumber VARCHAR(30) NOT NULL COMMENT 'Display phone number as received from Meta -- never assumed +91',
    customerName VARCHAR(150) NULL,
    assignedToId INT NULL COMMENT 'Direct assignment for an unlinked conversation; once linked the lead''s own assignedToId is used for access',
    lastMessageAt DATETIME NULL,
    lastMessagePreview VARCHAR(255) NULL,
    unreadCount INT NOT NULL DEFAULT 0,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_whatsappConversations_waId (waId),
    KEY idx_whatsappConversations_lead (leadId),
    KEY idx_whatsappConversations_assignee (assignedToId),
    KEY idx_whatsappConversations_phone (phoneNumber),
    KEY idx_whatsappConversations_lastMessage (lastMessageAt),
    CONSTRAINT fk_whatsappConversations_lead FOREIGN KEY (leadId) REFERENCES leads (id) ON DELETE SET NULL,
    CONSTRAINT fk_whatsappConversations_assignee FOREIGN KEY (assignedToId) REFERENCES employeeusers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 3. whatsappMessages
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS whatsappMessages (
    id INT NOT NULL AUTO_INCREMENT,
    conversationId INT NOT NULL,
    metaMessageId VARCHAR(120) NULL COMMENT 'Meta wamid; NULL momentarily while an outbound send is pending',
    direction ENUM('inbound','outbound') NOT NULL,
    messageType VARCHAR(20) NOT NULL DEFAULT 'text',
    messageText TEXT NULL,
    mediaId VARCHAR(120) NULL COMMENT 'Meta media id (inbound) or the id returned after an outbound media upload',
    mediaPath VARCHAR(255) NULL COMMENT 'Local private storage path once media is downloaded/uploaded',
    status ENUM('pending','sent','delivered','read','failed','received') NOT NULL DEFAULT 'pending',
    errorCode VARCHAR(50) NULL,
    errorMessage VARCHAR(255) NULL,
    sentByType ENUM('admin','employee','system') NULL COMMENT 'Who sent an outbound message; NULL for inbound',
    sentById INT NULL,
    sentAt DATETIME NULL,
    deliveredAt DATETIME NULL,
    readAt DATETIME NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_whatsappMessages_metaId (metaMessageId),
    KEY idx_whatsappMessages_conversation (conversationId, createdAt),
    CONSTRAINT fk_whatsappMessages_conversation FOREIGN KEY (conversationId) REFERENCES whatsappConversations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- 4. Routes (moduleName='Lead Management' -- group already exists in both
--    sidebars, zero includes/sidebar-menu.php changes needed)
-- ---------------------------------------------------------------------------
INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/whatsapp', '/pages/whatsapp.php', 'WhatsApp', 'Lead Management', 'admin', 0, 1, 1, 50, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/whatsapp');

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/emp-whatsapp', '/employee/emp-whatsapp.php', 'WhatsApp', 'Lead Management', 'employee', 0, 1, 1, 30, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/emp-whatsapp');

-- Provisional role access for /emp-whatsapp, mirroring /emp-leads exactly
-- (Sales Executive / Sales Manager both get canView+canAdd+canEdit; data
-- scope itself still comes from getLeadScopeEmployeeId(), same as every
-- other lead API -- this is not a second permission system).
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove, createdAt, updatedAt)
SELECT r.roleName, rm.id, 1, 1, 1, 0, 0, NOW(), NOW()
FROM (SELECT 'Sales Executive' AS roleName UNION ALL SELECT 'Sales Manager') r
CROSS JOIN routesMaster rm
WHERE rm.routePath = '/emp-whatsapp'
AND NOT EXISTS (SELECT 1 FROM rolePermissions rp WHERE rp.roleName = r.roleName AND rp.routeId = rm.id);

-- 'send_whatsapp_message' special action, one row per route (admin route
-- exists so /integrations-style admin-only APIs aren't required for a basic
-- send -- admin always bypasses via hasActionPermission's admin shortcut).
INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder, createdAt, updatedAt)
SELECT rm.id, 'send_whatsapp_message', 'Send WhatsApp Message', 'special', '.whatsapp-send-btn', NULL, NULL, 1, 10, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath IN ('/whatsapp', '/emp-whatsapp')
AND NOT EXISTS (SELECT 1 FROM permissionActions pa WHERE pa.routeId = rm.id AND pa.actionKey = 'send_whatsapp_message');

-- Grant it by default to both employee roles (sending is a basic action,
-- same tier as canEdit on /emp-leads -- ownership/scope is what actually
-- restricts which conversations they can use it on).
INSERT INTO roleActionPermissions (roleName, actionId, canAccess, createdAt, updatedAt)
SELECT r.roleName, pa.id, 1, NOW(), NOW()
FROM (SELECT 'Sales Executive' AS roleName UNION ALL SELECT 'Sales Manager') r
CROSS JOIN permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
WHERE rm.routePath = '/emp-whatsapp' AND pa.actionKey = 'send_whatsapp_message'
AND NOT EXISTS (SELECT 1 FROM roleActionPermissions rap WHERE rap.roleName = r.roleName AND rap.actionId = pa.id);
