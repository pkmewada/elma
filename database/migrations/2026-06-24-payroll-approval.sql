CREATE TABLE IF NOT EXISTS payrollSalarySlips (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    employeeId INT UNSIGNED NOT NULL,
    periodStart DATE NOT NULL,
    periodEnd DATE NOT NULL,
    periodMonth VARCHAR(20) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    calculationJson LONGTEXT NOT NULL,
    grossEarnings DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    totalDeductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    totalReimbursements DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    netPay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    pdfPath VARCHAR(255) NOT NULL DEFAULT '',
    rejectionRemark TEXT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    submittedBy INT UNSIGNED NOT NULL DEFAULT 0,
    submittedAt DATETIME NULL,
    reviewedBy INT UNSIGNED NOT NULL DEFAULT 0,
    reviewedAt DATETIME NULL,
    isActive TINYINT(1) NOT NULL DEFAULT 1,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_payroll_slip_employee_period (employeeId, periodStart, periodEnd),
    INDEX idx_payroll_slip_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payrollSalarySlipPayments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    salarySlipId INT UNSIGNED NOT NULL,
    paymentAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paymentMode VARCHAR(40) NOT NULL DEFAULT '',
    transactionReference VARCHAR(120) NOT NULL DEFAULT '',
    transactionDate DATE NOT NULL,
    remarks TEXT NULL,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_payroll_payment_slip (salarySlipId)
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
    '/salary-slip-approval',
    '/pages/salary-slip-approval.php',
    'Salary Slip Approval',
    'HRMS',
    'admin',
    0,
    1,
    1,
    85
WHERE NOT EXISTS (
    SELECT 1
    FROM routesMaster
    WHERE routePath = '/salary-slip-approval'
);
