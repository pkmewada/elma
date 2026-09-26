-- HRMS Enhancement Phase: additional payroll earning fields, asset
-- assignment extension fields, and a new Walk-in Candidates table.
--
-- No changes to leads/leadFollowUps/leaveApplications/employeeAttendance/
-- payrollSalarySlips -- everything here extends existing tables or adds
-- one genuinely new table for functionality that doesn't exist yet.

-- ==================================================
-- 1. Payroll: additional editable earning fields
-- ==================================================
-- Extends the existing basicSalary/hraAmount/allowanceAmount pattern
-- already on employeeusers (see includes/PayrollEngine.php). These feed
-- PayrollEngine::getMonthlySalary()/getEarningsRows() additively, so they
-- only affect NEWLY generated salary slips -- approved/submitted slips are
-- frozen JSON snapshots (payrollSalarySlips.calculationJson) and are
-- never recalculated.
-- Run once per environment (MySQL has no ADD COLUMN IF NOT EXISTS -- that's
-- a MariaDB-only extension). If re-running against an environment that
-- already has these columns, drop the already-applied ADD COLUMN lines
-- first.
ALTER TABLE employeeusers
    ADD COLUMN otherEarning1Amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER netSalary,
    ADD COLUMN otherEarning2Amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER otherEarning1Amount,
    ADD COLUMN otherEarning3Amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER otherEarning2Amount,
    ADD COLUMN otherAllowanceAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER otherEarning3Amount;

-- ==================================================
-- 2. Asset Management: extend assetAssignment
-- ==================================================
-- expectedReturnDate already exists on assetAssignment but was never
-- written by AssetAssignmentModel::assignAsset() -- wired up in code, no
-- column needed for it. remarks already exists and is used for return-time
-- remarks (AssetAssignmentModel::returnAsset()) -- kept as-is; issueRemarks
-- is new and separate so issue-time and return-time notes don't overwrite
-- each other. returnCondition was previously collected by the UI
-- (conditionStatus select) but silently dropped -- api/assets/returnAsset.php
-- called returnAsset($assetId, $condition, $remarks) against a 2-arg method
-- that ignored the 3rd argument.
ALTER TABLE assetAssignment
    ADD COLUMN quantity INT NOT NULL DEFAULT 1 AFTER employeeId,
    ADD COLUMN purpose VARCHAR(255) NULL AFTER quantity,
    ADD COLUMN issueRemarks TEXT NULL AFTER purpose,
    ADD COLUMN returnCondition ENUM('new','good','damaged') NULL AFTER remarks;

-- ==================================================
-- 3. Walk-in Candidate Management (new)
-- ==================================================
CREATE TABLE IF NOT EXISTS walkInCandidates (
    id INT NOT NULL AUTO_INCREMENT,
    candidateName VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    positionApplied VARCHAR(150) NOT NULL,
    interviewDate DATE NOT NULL,
    interviewTime TIME NULL,
    source VARCHAR(100) NULL,
    status ENUM('Scheduled', 'Completed', 'Rejected', 'Selected') NOT NULL DEFAULT 'Scheduled',
    remarks TEXT NULL,
    handledByCandidateId INT NULL,
    createdBy INT NOT NULL,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wic_status (status),
    KEY idx_wic_interview_date (interviewDate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ==================================================
-- 4. Route: Walk-in Candidates
-- ==================================================
-- Same module as /candidate-record so it groups with recruitment in
-- Permission Setup / Route Setup.
INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/walkin-candidates', '/pages/walkin-candidates.php', 'Walk-in Candidates',
    'Candidate', 'admin', 0, 1, 1, 47
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/walkin-candidates'
);

-- Role access mirrors /candidate-record's existing "Hr Executive" grant
-- (the existing recruitment permission pattern).
INSERT INTO rolePermissions (roleName, routeId, canView, canAdd, canEdit, canDelete, canApprove)
SELECT 'Hr Executive', rm.id, 1, 1, 1, 0, 0
FROM routesMaster rm
WHERE rm.routePath = '/walkin-candidates'
AND NOT EXISTS (
    SELECT 1 FROM rolePermissions rp
    WHERE rp.roleName = 'Hr Executive' AND rp.routeId = rm.id
);
