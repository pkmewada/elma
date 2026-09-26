-- HRMS Payroll Enhancement: Advance Payment + Employee Loan Management.
--
-- Extends the existing payroll architecture (PayrollEngine::
-- calculateSalarySlip(), payrollSettings toggles, the expense-style
-- approval->finalize-on-full-payment pattern already used for
-- employeeExpenses) rather than inventing a new one. No changes to
-- leaves/attendance/leads/existing salary components.
--
-- Advance is a single lump-sum deduction, swept into the next payroll run
-- once approved (no installment plan -- there's no "advance repayment
-- schedule" requirement, unlike loans). Loan is a fixed monthly schedule,
-- one employeeLoanRepayments row per due month, matching the same
-- month-scoped pattern already used by employeeTrainingHoldSalary
-- (releasePayrollMonth) and employeeCommissionTransactions (effectiveMonth).

-- ==================================================
-- 1. Advance Payments
-- ==================================================
CREATE TABLE IF NOT EXISTS employeeAdvancePayments (
    id INT NOT NULL AUTO_INCREMENT,
    employeeId INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    paymentDate DATE NOT NULL,
    transactionNo VARCHAR(100) NULL,
    paymentMode VARCHAR(50) NULL,
    referenceNo VARCHAR(100) NULL,
    remark TEXT NULL,
    status ENUM('Pending', 'Approved', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    salaryAdjustmentStatus ENUM('Not Adjusted', 'Adjusted') NOT NULL DEFAULT 'Not Adjusted',
    createdBy INT NOT NULL,
    approvedBy INT NULL,
    approvedAt DATETIME NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_eap_employee (employeeId),
    KEY idx_eap_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- 2. Employee Loans
-- ==================================================
CREATE TABLE IF NOT EXISTS employeeLoans (
    id INT NOT NULL AUTO_INCREMENT,
    employeeId INT NOT NULL,
    loanAmount DECIMAL(12,2) NOT NULL,
    startDate DATE NOT NULL,
    duration INT NOT NULL COMMENT 'months, 1-12',
    monthlyDeduction DECIMAL(12,2) NOT NULL,
    remark TEXT NULL,
    status ENUM('Pending', 'Approved', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    createdBy INT NOT NULL,
    approvedBy INT NULL,
    approvedAt DATETIME NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_el_employee (employeeId),
    KEY idx_el_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- 3. Loan Repayment Schedule
-- ==================================================
CREATE TABLE IF NOT EXISTS employeeLoanRepayments (
    id INT NOT NULL AUTO_INCREMENT,
    loanId INT NOT NULL,
    employeeId INT NOT NULL,
    salaryMonth VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    deductionAmount DECIMAL(12,2) NOT NULL,
    status ENUM('Pending', 'Deducted', 'Waived') NOT NULL DEFAULT 'Pending',
    deductedAt DATETIME NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_elr_loan (loanId),
    KEY idx_elr_employee_month (employeeId, salaryMonth),
    KEY idx_elr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- 4. Payroll Settings: deduction toggles
-- ==================================================
-- Named to match the existing includeXxx convention already used by every
-- other toggle on this table (includeManualDeductions,
-- includeApprovedExpenses, includeFixedEmployeeDeduction, ...), not the
-- advanceEnabled/loanEnabled names from the initial brief.
ALTER TABLE payrollSettings
    ADD COLUMN includeAdvanceDeduction TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN includeLoanDeduction TINYINT(1) NOT NULL DEFAULT 1;

-- ==================================================
-- 5. Routes -- admin-only by default, matching the existing
--    /deduction-management and /payroll-setup precedent (payroll-sensitive
--    pages carry no rolePermissions grants; only admins see them until
--    granted explicitly via Permission Setup).
-- ==================================================
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/advance-payment', '/pages/advance-payment.php', 'Advance Payment',
    'Payroll', 'admin', 0, 1, 1, 77
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/advance-payment'
);

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/employee-loan', '/pages/employee-loan.php', 'Employee Loan',
    'Payroll', 'admin', 0, 1, 1, 78
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/employee-loan'
);
