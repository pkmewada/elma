-- Curated button/action registry for existing Admin and Employee pages.
-- Safe to run more than once. Existing action settings and explicit grants are preserved.

START TRANSACTION;

CREATE TEMPORARY TABLE bulkPermissionActions (
    routePath VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    actionKey VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
    actionLabel VARCHAR(150) NOT NULL,
    buttonSelector VARCHAR(255) DEFAULT NULL,
    apiEndpoint VARCHAR(255) DEFAULT NULL,
    httpMethod VARCHAR(10) DEFAULT NULL,
    sourcePermission ENUM('canView', 'canAdd', 'canEdit', 'canDelete', 'canApprove') NOT NULL,
    sortOrder INT NOT NULL DEFAULT 0,
    PRIMARY KEY (routePath, actionKey)
);

INSERT INTO bulkPermissionActions
    (routePath, actionKey, actionLabel, buttonSelector, apiEndpoint, httpMethod, sourcePermission, sortOrder)
VALUES
-- Lead management
('/addlead', 'add_lead', 'Add Lead', 'form button[type="submit"]', NULL, NULL, 'canAdd', 10),
('/leads', 'add_lead', 'Add Lead', '[data-bs-target="#addLeadModal"], #addLeadSubmitBtn', NULL, NULL, 'canAdd', 10),
('/leads', 'edit_lead', 'Edit Lead', '.edit-lead-btn', NULL, NULL, 'canEdit', 20),
('/leads', 'delete_lead', 'Delete Lead', '.delete-lead-btn, #confirmDeleteBtn', NULL, NULL, 'canDelete', 30),
('/leads', 'add_lead_remark', 'Add Lead Remark', '.remark-btn, #saveRemarkBtn', NULL, NULL, 'canEdit', 40),
('/leads', 'upload_lead_document', 'Upload Lead Document', '#uploadLeadDocumentBtn', NULL, NULL, 'canEdit', 50),
('/leads', 'update_lead_status', 'Update Lead Status', '#saveLeadStatusBtn', NULL, NULL, 'canEdit', 60),
('/leads', 'import_leads', 'Import Leads', '#importLeadSubmitBtn', NULL, NULL, 'canAdd', 70),
('/lead-setup', 'add_lead_category', 'Add Lead Category', '#addCategoryBtn, #saveCategoryBtn', NULL, NULL, 'canAdd', 10),
('/lead-setup', 'edit_lead_category', 'Edit Lead Category', '.edit-category-btn', NULL, NULL, 'canEdit', 20),
('/lead-setup', 'delete_lead_category', 'Delete Lead Category', '.delete-category-btn', NULL, NULL, 'canDelete', 30),
('/lead-setup', 'add_lead_plan', 'Add Lead Plan', '#addPlanBtn, #savePlanBtn', NULL, NULL, 'canAdd', 40),
('/lead-setup', 'edit_lead_plan', 'Edit Lead Plan', '.edit-plan-btn', NULL, NULL, 'canEdit', 50),
('/lead-setup', 'delete_lead_plan', 'Delete Lead Plan', '.delete-plan-btn', NULL, NULL, 'canDelete', 60),
('/lead-setup', 'save_lead_setup', 'Save Lead Setup', '#saveLeadSetupBtn', '/api/leads/saveLeadSetup.php', 'POST', 'canEdit', 70),

-- Candidate and onboarding
('/candidate-record', 'add_candidate', 'Add Candidate', '#addCandidateSubmitBtn', '/api/recruitment/addCandidate.php', 'POST', 'canAdd', 10),
('/candidate-record', 'edit_candidate', 'Edit Candidate', '.edit-candidate-btn, #editCandidateSubmitBtn', '/api/recruitment/updateCandidate.php', 'POST', 'canEdit', 20),
('/candidate-record', 'delete_candidate', 'Delete Candidate', '.delete-candidate-btn, #confirmDeleteBtn', '/api/recruitment/deleteCandidate.php', 'POST', 'canDelete', 30),
('/candidate-record', 'add_candidate_remark', 'Add Candidate Remark', '.remark-btn', '/api/recruitment/addCandidateRemark.php', 'POST', 'canEdit', 40),
('/onboarding-queue', 'submit_hr_review', 'Submit HR Review', '#submitHrReviewBtn', '/api/onboarding/submitHrReview.php', 'POST', 'canApprove', 10),
('/onboarding-queue', 'reject_hr_review', 'Reject HR Review', '#rejectReviewSubmitBtn', NULL, NULL, 'canApprove', 20),
('/onboarding-queue', 'final_verify_candidate', 'Final Verify Candidate', '#finalVerifyBtn', '/api/onboarding/finalVerifyCandidate.php', 'POST', 'canApprove', 30),
('/oboarding-lead', 'save_agreement_draft', 'Save Agreement Draft', '#saveAgreementBtn', '/api/onboarding/saveAgreementDraft.php', 'POST', 'canEdit', 10),
('/oboarding-lead', 'send_agreement', 'Send Agreement', '#sendAgreementBtn', '/api/onboarding/sendAgreement.php', 'POST', 'canApprove', 20),
('/oboarding-lead', 'review_agreement', 'Review Agreement', '#saveReviewBtn', '/api/onboarding/saveAgreementReview.php', 'POST', 'canApprove', 30),

-- Employee administration
('/employee-directory', 'edit_employee', 'Edit Employee', '.edit-profile-btn, #saveEmployeeEditBtn', '/api/onboarding/updateEmployeeDetailsByHr.php', 'POST', 'canEdit', 10),
('/employee-directory', 'change_employment_status', 'Change Employment Status', '.employment-select', '/api/employee/updateEmployeeEmploymentStatus.php', 'POST', 'canEdit', 20),

-- HR
('/event-holiday-management', 'add_event', 'Add Event', '[data-bs-target="#addEventHolidayModal"], #saveEventBtn', NULL, NULL, 'canAdd', 10),
('/event-holiday-management', 'edit_event', 'Edit Event', '.edit-record-btn, #updateEventBtn', NULL, NULL, 'canEdit', 20),
('/event-holiday-management', 'delete_event', 'Delete Event', '.delete-record-btn', NULL, NULL, 'canDelete', 30),
('/assets-management', 'add_asset', 'Add Asset', '[data-bs-target="#addAssetModal"], #saveAssetBtn', '/api/assets/addAsset.php', 'POST', 'canAdd', 10),
('/assets-management', 'assign_asset', 'Assign Asset', '[data-bs-target="#assignAssetModal"], .assign-btn, #assignBtn', '/api/assets/assignAsset.php', 'POST', 'canEdit', 20),
('/assets-management', 'return_asset', 'Return Asset', '.return-btn, #returnBtn', '/api/assets/returnAsset.php', 'POST', 'canEdit', 30),
('/assets-management', 'delete_asset', 'Delete Asset', '.delete-btn', '/api/assets/deleteAsset.php', 'POST', 'canDelete', 40),
('/leave-setup', 'add_leave_type', 'Add Leave Type', '#addLeaveTypeBtn', NULL, NULL, 'canAdd', 10),
('/leave-setup', 'edit_leave_type', 'Edit Leave Type', '.edit-leave-type', NULL, NULL, 'canEdit', 20),
('/leave-setup', 'delete_leave_type', 'Delete Leave Type', '.delete-leave-type', NULL, NULL, 'canDelete', 30),
('/leave-setup', 'save_leave_setup', 'Save Leave Setup', '#saveLeaveSetupBtn', '/api/leave/saveLeaveSetup.php', 'POST', 'canEdit', 40),
('/apply-leave', 'apply_leave', 'Apply Leave', '#submitLeaveBtn', '/api/leave/applyLeave.php', 'POST', 'canAdd', 10),
('/apply-leave', 'cancel_leave', 'Cancel Leave', '.cancelLeaveBtn', '/api/leave/cancelLeave.php', 'POST', 'canDelete', 20),
('/verify-leave', 'review_leave', 'Approve or Reject Leave', '.approveBtn, .rejectBtn', '/api/leave/updateLeaveStatus.php', 'POST', 'canApprove', 10),
('/attendance-management', 'edit_attendance', 'Edit Attendance', '.edit-attendance, #saveAttendanceBtn', '/api/attendance/updateAttendance.php', 'POST', 'canEdit', 10),
('/attendance-setup', 'add_break_type', 'Add Break Type', '#addBreakTypeBtn', NULL, NULL, 'canAdd', 10),
('/attendance-setup', 'edit_break_type', 'Edit Break Type', '.edit-break-type', NULL, NULL, 'canEdit', 20),
('/attendance-setup', 'delete_break_type', 'Delete Break Type', '.delete-break-type', NULL, NULL, 'canDelete', 30),
('/attendance-setup', 'save_attendance_setup', 'Save Attendance Setup', '#saveAttendanceSetupBtn', '/api/attendance/saveAttendanceSetup.php', 'POST', 'canEdit', 40),

-- Payroll
('/overtime-management', 'add_overtime', 'Add Overtime', '[data-bs-target="#addOvertimeModal"], #addOvertimeForm button[type="submit"]', '/api/overtime/addOvertime.php', 'POST', 'canAdd', 10),
('/overtime-management', 'approve_overtime', 'Approve Overtime', '.approve-btn', '/api/overtime/approveOvertime.php', 'POST', 'canApprove', 20),
('/overtime-management', 'reject_overtime', 'Reject Overtime', '.reject-btn, #rejectForm button[type="submit"]', '/api/overtime/rejectOvertime.php', 'POST', 'canApprove', 30),
('/deduction-management', 'add_deduction', 'Add Deduction', '[data-bs-target="#addDeductionModal"]', '/api/payroll/addDeduction.php', 'POST', 'canAdd', 10),
('/deduction-management', 'edit_deduction', 'Edit Deduction', '.edit-deduction-btn', '/api/payroll/updateDeduction.php', 'POST', 'canEdit', 20),
('/deduction-management', 'delete_deduction', 'Delete Deduction', '.delete-deduction-btn, #confirmDeleteBtn', '/api/payroll/deleteDeduction.php', 'POST', 'canDelete', 30),
('/expense-management', 'add_expense', 'Add Expense', '[data-bs-target="#addExpenseModal"]', '/api/expense/addExpense.php', 'POST', 'canAdd', 10),
('/expense-management', 'edit_expense', 'Edit Expense', '.edit-expense-btn', '/api/expense/updateExpense.php', 'POST', 'canEdit', 20),
('/expense-management', 'delete_expense', 'Delete Expense', '.delete-expense-btn, #confirmDeleteBtn', '/api/expense/deleteExpense.php', 'POST', 'canDelete', 30),
('/expense-management', 'approve_expense', 'Approve Expense', '.approve-expense-btn', '/api/expense/approveExpense.php', 'POST', 'canApprove', 40),
('/expense-management', 'reject_expense', 'Reject Expense', '.reject-expense-btn', '/api/expense/rejectExpense.php', 'POST', 'canApprove', 50),
('/employee-point-setup', 'add_point_category', 'Add Point Category', '#addPointCategoryBtn', NULL, NULL, 'canAdd', 10),
('/employee-point-setup', 'edit_point_category', 'Edit Point Category', '.edit-point-category', NULL, NULL, 'canEdit', 20),
('/employee-point-setup', 'delete_point_category', 'Delete Point Category', '.delete-point-category', '/api/points/deletePointCategory.php', 'POST', 'canDelete', 30),
('/employee-point-setup', 'save_point_setup', 'Save Point Setup', '#savePointSetupBtn', '/api/points/savePointSetup.php', 'POST', 'canEdit', 40),
('/employee-point-transactions', 'add_point_transaction', 'Add Point Transaction', '#saveTransactionBtn', '/api/points/savePointTransaction.php', 'POST', 'canAdd', 10),
('/employee-point-transactions', 'edit_point_transaction', 'Edit Point Transaction', '.editTransactionBtn', '/api/points/updatePointTransaction.php', 'POST', 'canEdit', 20),
('/employee-point-transactions', 'delete_point_transaction', 'Delete Point Transaction', '.deleteTransactionBtn', '/api/points/deletePointTransaction.php', 'POST', 'canDelete', 30),
('/commission-bonus-setup', 'add_commission_category', 'Add Commission Category', '#addCommissionCategoryBtn', NULL, NULL, 'canAdd', 10),
('/commission-bonus-setup', 'edit_commission_category', 'Edit Commission Category', '.edit-commission-category', NULL, NULL, 'canEdit', 20),
('/commission-bonus-setup', 'delete_commission_category', 'Delete Commission Category', '.delete-commission-category', '/api/commission/deleteCommissionCategory.php', 'POST', 'canDelete', 30),
('/commission-bonus-setup', 'save_commission_setup', 'Save Commission Setup', '#saveCommissionSetupBtn', '/api/commission/saveCommissionSetup.php', 'POST', 'canEdit', 40),
('/employee-commission-bonus', 'add_commission_transaction', 'Add Commission Transaction', '#saveTransactionBtn', '/api/commission/saveCommissionTransaction.php', 'POST', 'canAdd', 10),
('/employee-commission-bonus', 'edit_commission_transaction', 'Edit Commission Transaction', '.editTransactionBtn', '/api/commission/updateCommissionTransaction.php', 'POST', 'canEdit', 20),
('/employee-commission-bonus', 'delete_commission_transaction', 'Delete Commission Transaction', '.deleteTransactionBtn', '/api/commission/deleteCommissionTransaction.php', 'POST', 'canDelete', 30),
('/employee-commission-bonus', 'approve_commission_transaction', 'Approve Commission Transaction', '.approveTransactionBtn', '/api/commission/approveCommissionTransaction.php', 'POST', 'canApprove', 40),
('/employee-commission-bonus', 'reject_commission_transaction', 'Reject Commission Transaction', '.rejectTransactionBtn', '/api/commission/rejectCommissionTransaction.php', 'POST', 'canApprove', 50),

-- Employee panel
('/emp-dashboard', 'punch_in', 'Punch In', 'button[onclick="handlePunchIn()"]', '/api/attendance/punchIn.php', 'POST', 'canAdd', 10),
('/emp-dashboard', 'punch_out', 'Punch Out', 'button[onclick="handlePunchOut()"]', '/api/attendance/punchOut.php', 'POST', 'canEdit', 20),
('/emp-dashboard', 'start_break', 'Start Break', 'button[onclick="handleStartBreak()"]', '/api/attendance/startBreak.php', 'POST', 'canAdd', 30),
('/emp-dashboard', 'end_break', 'End Break', 'button[onclick="handleEndBreak()"]', '/api/attendance/endBreak.php', 'POST', 'canEdit', 40),
('/emp-event-holiday', 'add_event', 'Add Event', '#saveEventBtn', '/api/holidays/addEventHoliday.php', 'POST', 'canAdd', 10),
('/emp-event-holiday', 'edit_event', 'Edit Event', '.edit-record-btn, #updateEventBtn', '/api/holidays/updateEventHoliday.php', 'POST', 'canEdit', 20),
('/emp-event-holiday', 'delete_event', 'Delete Event', '.delete-record-btn', '/api/holidays/deleteEventHoliday.php', 'POST', 'canDelete', 30),
('/emp-apply-leave', 'apply_leave', 'Apply Leave', '#submitLeaveBtn', '/api/leave/emp-applyLeave.php', 'POST', 'canAdd', 10),
('/emp-apply-leave', 'cancel_leave', 'Cancel Leave', '.cancelLeaveBtn', '/api/leave/emp-cancelLeave.php', 'POST', 'canDelete', 20),
('/emp-overtime-management', 'add_overtime', 'Add Overtime', '[data-bs-target="#addOvertimeModal"], #saveOvertimeBtn', '/api/overtime/emp-addOvertime.php', 'POST', 'canAdd', 10),
('/emp-overtime-management', 'edit_overtime', 'Edit Overtime', '.edit-btn', '/api/overtime/emp-updateOvertime.php', 'POST', 'canEdit', 20),
('/emp-overtime-management', 'delete_overtime', 'Delete Overtime', '.delete-btn', '/api/overtime/emp-deleteOvertime.php', 'POST', 'canDelete', 30),
('/emp-expense-management', 'add_expense', 'Add Expense', '[data-bs-target="#addExpenseModal"]', '/api/expense/emp-addExpense.php', 'POST', 'canAdd', 10),
('/emp-expense-management', 'edit_expense', 'Edit Expense', '.edit-expense-btn', '/api/expense/emp-updateExpense.php', 'POST', 'canEdit', 20),
('/emp-expense-management', 'delete_expense', 'Delete Expense', '.delete-expense-btn, #confirmDeleteBtn', '/api/expense/emp-deleteExpense.php', 'POST', 'canDelete', 30),
('/employee-profile', 'edit_profile', 'Edit Profile', '#saveEmployeeProfileBtn', '/api/employee/updateEmployeeProfile.php', 'POST', 'canEdit', 10),
('/emp-leads', 'add_lead', 'Add Lead', '#addLeadSubmitBtn', '/api/leads/addLead.php', 'POST', 'canAdd', 10),
('/emp-leads', 'edit_lead', 'Edit Lead', '.edit-lead-btn', '/api/leads/updateLead.php', 'POST', 'canEdit', 20),
('/emp-leads', 'delete_lead', 'Delete Lead', '.delete-lead-btn, #confirmDeleteBtn', '/api/leads/deleteLead.php', 'POST', 'canDelete', 30),
('/emp-leads', 'add_lead_remark', 'Add Lead Remark', '.remark-btn, #saveRemarkBtn', '/api/leads/saveLeadRemark.php', 'POST', 'canEdit', 40),
('/emp-leads', 'upload_lead_document', 'Upload Lead Document', '#uploadLeadDocumentBtn', '/api/leads/uploadLeadDocument.php', 'POST', 'canEdit', 50),
('/emp-leads', 'update_lead_status', 'Update Lead Status', '#saveLeadStatusBtn', '/api/leads/updateLeadStatus.php', 'POST', 'canEdit', 60),
('/emp-leads', 'import_leads', 'Import Leads', '#importLeadSubmitBtn', '/api/leads/importLeads.php', 'POST', 'canAdd', 70);

-- Add missing actions. For existing actions, fill only missing metadata so manual corrections remain final.
INSERT INTO permissionActions
    (routeId, actionKey, actionLabel, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder)
SELECT
    rm.id,
    bpa.actionKey,
    bpa.actionLabel,
    bpa.buttonSelector,
    bpa.apiEndpoint,
    bpa.httpMethod,
    1,
    bpa.sortOrder
FROM bulkPermissionActions bpa
INNER JOIN routesMaster rm ON rm.routePath = bpa.routePath AND rm.isActive = 1
ON DUPLICATE KEY UPDATE
    actionLabel = IF(permissionActions.actionLabel = '', VALUES(actionLabel), permissionActions.actionLabel),
    buttonSelector = IF(
        permissionActions.buttonSelector IS NULL OR permissionActions.buttonSelector = '',
        VALUES(buttonSelector),
        permissionActions.buttonSelector
    ),
    apiEndpoint = IF(
        permissionActions.apiEndpoint IS NULL OR permissionActions.apiEndpoint = '',
        VALUES(apiEndpoint),
        permissionActions.apiEndpoint
    ),
    httpMethod = IF(
        permissionActions.httpMethod IS NULL OR permissionActions.httpMethod = '',
        VALUES(httpMethod),
        permissionActions.httpMethod
    );

-- New role action rows inherit the existing standard page permission.
INSERT INTO roleActionPermissions (roleName, actionId, canAccess)
SELECT
    rp.roleName,
    pa.id,
    CASE bpa.sourcePermission
        WHEN 'canAdd' THEN rp.canAdd
        WHEN 'canEdit' THEN rp.canEdit
        WHEN 'canDelete' THEN rp.canDelete
        WHEN 'canApprove' THEN rp.canApprove
        ELSE rp.canView
    END
FROM bulkPermissionActions bpa
INNER JOIN routesMaster rm ON rm.routePath = bpa.routePath
INNER JOIN permissionActions pa ON pa.routeId = rm.id AND pa.actionKey = bpa.actionKey
INNER JOIN rolePermissions rp ON rp.routeId = rm.id
WHERE pa.permissionType = 'special'
ON DUPLICATE KEY UPDATE canAccess = roleActionPermissions.canAccess;

-- Existing employee exceptions also remain final for newly registered actions.
INSERT INTO userActionPermissionOverrides (userId, actionId, canAccess)
SELECT
    upo.userId,
    pa.id,
    CASE bpa.sourcePermission
        WHEN 'canAdd' THEN upo.canAdd
        WHEN 'canEdit' THEN upo.canEdit
        WHEN 'canDelete' THEN upo.canDelete
        WHEN 'canApprove' THEN upo.canApprove
        ELSE upo.canView
    END
FROM bulkPermissionActions bpa
INNER JOIN routesMaster rm ON rm.routePath = bpa.routePath
INNER JOIN permissionActions pa ON pa.routeId = rm.id AND pa.actionKey = bpa.actionKey
INNER JOIN userPermissionOverrides upo ON upo.routeId = rm.id
WHERE pa.permissionType = 'special'
ON DUPLICATE KEY UPDATE canAccess = userActionPermissionOverrides.canAccess;

DROP TEMPORARY TABLE bulkPermissionActions;

COMMIT;
