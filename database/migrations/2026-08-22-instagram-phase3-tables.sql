-- Phase 3: Analytics / Comments / Webhooks architecture
--
-- All three tables follow the Phase 2.5 convention: clientId/instagramAccountId
-- are always resolved BEFORE a row is written (never guessed or deferred),
-- real FK constraints against clientMaster/instagramAccounts/instagramPosts.
--
-- instagramWebhookEvents deliberately keeps clientId/instagramAccountId
-- NULLABLE with ON DELETE SET NULL (not CASCADE) — its purpose is to safely
-- record every Meta webhook delivery for debugging, including ones Modlus
-- cannot resolve to a known account, and to keep that debugging history even
-- if the owning client/account is later deleted rather than losing it.
--
-- instagramComments.postId is nullable for the same "always store it" reason:
-- a comment can be on an Instagram post published outside Modlus (no local
-- instagramPosts row to point at). instagramMediaId (Meta's own post id) is
-- the reliable reference and is always present; postId is populated on a
-- best-effort match against instagramPosts.instagramMediaId.

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

-- Webhook subscription setup needs a verify token (Meta's GET handshake) —
-- global, same "one Meta App for the whole platform" scope as metaAppId/Secret.
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
