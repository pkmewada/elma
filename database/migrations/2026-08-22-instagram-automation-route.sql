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