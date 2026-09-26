CREATE TABLE IF NOT EXISTS companySettings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    companyName VARCHAR(180) NOT NULL DEFAULT '',
    legalName VARCHAR(180) NOT NULL DEFAULT '',
    addressLine1 VARCHAR(255) NOT NULL DEFAULT '',
    addressLine2 VARCHAR(255) NOT NULL DEFAULT '',
    city VARCHAR(120) NOT NULL DEFAULT '',
    stateName VARCHAR(120) NOT NULL DEFAULT '',
    pincode VARCHAR(20) NOT NULL DEFAULT '',
    country VARCHAR(120) NOT NULL DEFAULT 'India',
    phone VARCHAR(40) NOT NULL DEFAULT '',
    email VARCHAR(160) NOT NULL DEFAULT '',
    website VARCHAR(180) NOT NULL DEFAULT '',
    gstNumber VARCHAR(30) NOT NULL DEFAULT '',
    panNumber VARCHAR(20) NOT NULL DEFAULT '',
    cinNumber VARCHAR(40) NOT NULL DEFAULT '',
    companyLogo VARCHAR(255) NOT NULL DEFAULT '',
    setupCompleted TINYINT(1) NOT NULL DEFAULT 0,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
    '/company-setup',
    '/pages/company-setup.php',
    'Company Setup',
    'Setup',
    'admin',
    0,
    1,
    1,
    63
WHERE NOT EXISTS (
    SELECT 1
    FROM routesMaster
    WHERE routePath = '/company-setup'
);
