-- HRMS Payroll Enhancement: Advance Payment -- Payment Adjustment + Partial
-- Payment (manual-amount recovery plan).
--
-- Extends employeeAdvancePayments (2026-09-19-advance-payment-loan-management.sql)
-- rather than redesigning it. "Payment Adjustment" (paymentAdjustment) is a
-- distinct concept from the existing salaryAdjustmentStatus column: the
-- latter is a completion flag flipped by PayrollApprovalEngine::addPayment()
-- once an advance is fully recovered (kept as-is), the former is the mode
-- HR explicitly picks -- Not Adjusted / Upcoming Salary / Partial Payment.
--
-- Partial Payment reuses the exact employeeLoanRepayments shape (one row
-- per due salary month, Pending -> Deducted/Waived) via a new
-- employeeAdvanceRepayments table, generated at approval time just like
-- EmployeeLoanEngine::generateRepaymentSchedule(). It is NOT the loan
-- table -- advances and loans stay fully independent, per-advance/per-loan.
--
-- Existing rows all default to paymentAdjustment = 'Not Adjusted' (no prior
-- explicit configuration existed), so no advance starts deducting because
-- of this migration alone -- HR must opt each one in.

ALTER TABLE employeeAdvancePayments
    ADD COLUMN paymentAdjustment ENUM('Not Adjusted', 'Upcoming Salary', 'Partial Payment')
        NOT NULL DEFAULT 'Not Adjusted' AFTER salaryAdjustmentStatus,
    ADD COLUMN partialMonths TINYINT UNSIGNED NULL COMMENT 'Intended repayment period, 1-12 (not a hard cap on installment count)' AFTER paymentAdjustment,
    ADD COLUMN partialMonthlyAmount DECIMAL(12,2) NULL COMMENT 'HR-entered recovery amount per month, never auto-calculated' AFTER partialMonths,
    ADD COLUMN partialFirstSalaryMonth VARCHAR(7) NULL COMMENT 'YYYY-MM, first installment month' AFTER partialMonthlyAmount,
    ADD COLUMN adjustmentUpdatedBy INT NULL COMMENT 'Audit: who last configured the Payment Adjustment' AFTER partialFirstSalaryMonth,
    ADD COLUMN adjustmentUpdatedAt DATETIME NULL COMMENT 'Audit: when the Payment Adjustment was last configured' AFTER adjustmentUpdatedBy,
    ADD KEY idx_eap_payment_adjustment (paymentAdjustment);

CREATE TABLE IF NOT EXISTS employeeAdvanceRepayments (
    id INT NOT NULL AUTO_INCREMENT,
    advanceId INT NOT NULL,
    employeeId INT NOT NULL,
    salaryMonth VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    scheduledAmount DECIMAL(12,2) NOT NULL,
    deductedAmount DECIMAL(12,2) NULL,
    status ENUM('Pending', 'Deducted', 'Waived') NOT NULL DEFAULT 'Pending',
    deductedAt DATETIME NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ear_advance (advanceId),
    KEY idx_ear_employee_month (employeeId, salaryMonth),
    KEY idx_ear_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
