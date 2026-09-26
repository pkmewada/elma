-- Instagram Automation — Combined Production Deployment Script
--
-- Concatenates, in dependency order, every migration that ships the
-- Instagram Automation module. Source files (each still exists standalone
-- in this folder and is safe to run individually if you prefer):
--   1. 2026-08-22-instagram-automation-route.sql
--   2. 2026-08-22-instagram-automation-tables.sql
--   3. 2026-08-22-instagram-posts-tables.sql
--   4. 2026-08-22-instagram-multi-client.sql
--   5. 2026-08-22-instagram-phase3-tables.sql
--   6. 2026-08-22-instagram-phase31-hardening.sql
--
-- Run once against the target database. The CREATE TABLE / route INSERT
-- statements are idempotent (IF NOT EXISTS / WHERE NOT EXISTS) and safe to
-- re-run, but the ALTER TABLE statements (steps 4 and 6) are NOT — running
-- this script twice will fail on "Duplicate column name" once those columns
-- already exist. That failure is expected/safe: it means this script has
-- already been applied.
--
-- Prerequisites:
--   - routesMaster and clientMaster tables must already exist (they do in
--     any current Modlus install; this module adds no new dependency on
--     top of what's already deployed).
--
-- Recommended: take a database backup before running this in production.

-- ============================================================
-- 1) Route: /instagram-automation (settings page)
-- ============================================================
INSERT INTO routesMaster (
    routePath,
    pageFile,
    routeTitle,
    moduleName,
    layoutType,
    isPublic,
    isMenuVisible,
    isActive,
    sortOrder
)
SELECT
    '/instagram-automation',
    '/pages/instagram-automation.php',
    'Instagram Automation',
    'Automation',
    'admin',
    0,
    1,
    1,
    90
WHERE NOT EXISTS (
    SELECT 1
    FROM routesMaster
    WHERE routePath = '/instagram-automation'
);

-- ============================================================
-- 2) Core tables: instagramSettings, instagramAccounts
-- ============================================================
CREATE TABLE IF NOT EXISTS instagramSettings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    metaAppId VARCHAR(191) NOT NULL DEFAULT '',
    metaAppSecret TEXT NOT NULL,
    redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS instagramAccounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    instagramUserId VARCHAR(64) NOT NULL DEFAULT '',
    facebookPageId VARCHAR(64) NOT NULL DEFAULT '',
    username VARCHAR(180) NOT NULL DEFAULT '',
    accessToken TEXT NOT NULL,
    tokenExpiry DATETIME NULL DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'connected',
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniqInstagramUserId (instagramUserId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- 3) Posts table + routes: /instagram-create-post, /instagram-scheduled-posts
-- ============================================================
CREATE TABLE IF NOT EXISTS instagramPosts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    mediaType VARCHAR(20) NOT NULL DEFAULT 'image',
    mediaUrl TEXT NOT NULL,
    caption TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    scheduledAt DATETIME NULL DEFAULT NULL,
    publishedAt DATETIME NULL DEFAULT NULL,
    instagramMediaId VARCHAR(64) NOT NULL DEFAULT '',
    errorMessage TEXT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idxStatusScheduledAt (status, scheduledAt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/instagram-create-post', '/pages/instagram-create-post.php', 'Create Instagram Post',
    'Automation', 'admin', 0, 1, 1, 91
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/instagram-create-post'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/instagram-scheduled-posts', '/pages/instagram-scheduled-posts.php', 'Instagram Posts',
    'Automation', 'admin', 0, 1, 1, 92
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/instagram-scheduled-posts'
);

-- ============================================================
-- 4) Phase 2.5: Multi-client architecture (NOT re-runnable — ALTER TABLE)
-- ============================================================
ALTER TABLE instagramAccounts
    ADD COLUMN clientId INT NULL DEFAULT NULL AFTER createdBy,
    ADD INDEX idxInstagramAccountsClientId (clientId);

ALTER TABLE instagramPosts
    ADD COLUMN clientId INT NULL DEFAULT NULL AFTER createdBy,
    ADD COLUMN instagramAccountId INT UNSIGNED NULL DEFAULT NULL AFTER clientId,
    ADD INDEX idxInstagramPostsClientId (clientId),
    ADD INDEX idxInstagramPostsAccountId (instagramAccountId);

ALTER TABLE instagramAccounts
    ADD CONSTRAINT fkInstagramAccountsClient
    FOREIGN KEY (clientId) REFERENCES clientMaster(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE instagramPosts
    ADD CONSTRAINT fkInstagramPostsClient
    FOREIGN KEY (clientId) REFERENCES clientMaster(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
    ADD CONSTRAINT fkInstagramPostsAccount
    FOREIGN KEY (instagramAccountId) REFERENCES instagramAccounts(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================
-- 5) Phase 3: Analytics / Comments / Webhooks
-- ============================================================
CREATE TABLE IF NOT EXISTS instagramInsights (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NOT NULL,
    instagramAccountId INT UNSIGNED NOT NULL,
    postId INT UNSIGNED NULL DEFAULT NULL,
    metricName VARCHAR(50) NOT NULL,
    metricValue BIGINT NOT NULL DEFAULT 0,
    period VARCHAR(20) NOT NULL DEFAULT 'day',
    capturedAt DATE NOT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idxInsightsClientId (clientId),
    KEY idxInsightsAccountId (instagramAccountId),
    KEY idxInsightsPostId (postId),
    KEY idxInsightsMetricDate (metricName, capturedAt),
    CONSTRAINT fkInstagramInsightsClient FOREIGN KEY (clientId) REFERENCES clientMaster(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fkInstagramInsightsAccount FOREIGN KEY (instagramAccountId) REFERENCES instagramAccounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fkInstagramInsightsPost FOREIGN KEY (postId) REFERENCES instagramPosts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS instagramComments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NOT NULL,
    instagramAccountId INT UNSIGNED NOT NULL,
    postId INT UNSIGNED NULL DEFAULT NULL,
    instagramMediaId VARCHAR(64) NOT NULL DEFAULT '',
    instagramCommentId VARCHAR(64) NOT NULL,
    parentCommentId VARCHAR(64) NULL DEFAULT NULL,
    username VARCHAR(180) NOT NULL DEFAULT '',
    commentText TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'visible',
    repliedAt DATETIME NULL DEFAULT NULL,
    hiddenAt DATETIME NULL DEFAULT NULL,
    commentedAt DATETIME NULL DEFAULT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniqInstagramCommentId (instagramCommentId),
    KEY idxCommentsClientId (clientId),
    KEY idxCommentsAccountId (instagramAccountId),
    KEY idxCommentsPostId (postId),
    CONSTRAINT fkInstagramCommentsClient FOREIGN KEY (clientId) REFERENCES clientMaster(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fkInstagramCommentsAccount FOREIGN KEY (instagramAccountId) REFERENCES instagramAccounts(id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fkInstagramCommentsPost FOREIGN KEY (postId) REFERENCES instagramPosts(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS instagramWebhookEvents (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NULL DEFAULT NULL,
    instagramAccountId INT UNSIGNED NULL DEFAULT NULL,
    eventType VARCHAR(50) NOT NULL DEFAULT '',
    payload LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'received',
    errorMessage TEXT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processedAt DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idxWebhookClientId (clientId),
    KEY idxWebhookAccountId (instagramAccountId),
    KEY idxWebhookStatus (status),
    KEY idxWebhookEventType (eventType),
    CONSTRAINT fkInstagramWebhookClient FOREIGN KEY (clientId) REFERENCES clientMaster(id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fkInstagramWebhookAccount FOREIGN KEY (instagramAccountId) REFERENCES instagramAccounts(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE instagramSettings
    ADD COLUMN webhookVerifyToken VARCHAR(191) NOT NULL DEFAULT '' AFTER redirectUrl;

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/instagram-comments', '/pages/instagram-comments.php', 'Instagram Comments',
    'Automation', 'admin', 0, 1, 1, 93
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/instagram-comments'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/instagram-analytics', '/pages/instagram-analytics.php', 'Instagram Analytics',
    'Automation', 'admin', 0, 1, 1, 94
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/instagram-analytics'
);

-- ============================================================
-- 6) Phase 3.1: Production hardening (NOT re-runnable — ALTER TABLE)
-- ============================================================
ALTER TABLE instagramAccounts
    ADD COLUMN lastAnalyticsSyncAt DATETIME NULL DEFAULT NULL AFTER tokenExpiry,
    ADD COLUMN lastAnalyticsSyncError TEXT NULL AFTER lastAnalyticsSyncAt;
