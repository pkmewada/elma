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
