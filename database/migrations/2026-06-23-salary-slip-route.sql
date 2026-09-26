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
    '/salary-slip',
    '/pages/salary-slip.php',
    'Salary Slip',
    'HRMS',
    'admin',
    0,
    1,
    1,
    84
WHERE NOT EXISTS (
    SELECT 1
    FROM routesMaster
    WHERE routePath = '/salary-slip'
);
