-- API Directory Reorganization: permissionActions.apiEndpoint path fix-up
--
-- The physical /api/*.php files were reorganized into module subfolders
-- (see the sibling api/ directory move performed in this same change set).
-- api-gateway.php (routed via .htaccess: RewriteRule ^api/.+\.php$
-- api-gateway.php) matches the incoming request path against
-- permissionActions.apiEndpoint to decide whether an action-level
-- permission check applies. Any row whose apiEndpoint still holds the
-- OLD flat path would silently stop matching after the physical move --
-- api-gateway.php's own fallback behavior for an unmapped endpoint is to
-- skip the permission check entirely (see its comment: "Unmapped APIs
-- pass through without a permission check"), which would be a real
-- authorization regression, not just a broken link.
--
-- This migration corrects every existing apiEndpoint value that matches
-- one of the moved files' OLD flat path to its NEW subfolder path. It is
-- idempotent (each UPDATE only touches rows still holding the exact old
-- value, so re-running it after it has already applied is a no-op) and
-- additive/corrective only -- no schema change, no new tables, no rows
-- added or removed, only existing apiEndpoint string values corrected.
--
-- Also updates database/migrations/2026-06-20-bulk-button-action-registry.sql
-- in place (source-of-truth fix for any future fresh install running that
-- migration from scratch) -- this file is the companion fix for any
-- environment where that migration has already run and inserted rows.

START TRANSACTION;

UPDATE permissionActions SET apiEndpoint = '/api/assets/addAsset.php' WHERE apiEndpoint = '/api/addAsset.php';
UPDATE permissionActions SET apiEndpoint = '/api/assets/deleteAsset.php' WHERE apiEndpoint = '/api/deleteAsset.php';
UPDATE permissionActions SET apiEndpoint = '/api/assets/getAsset.php' WHERE apiEndpoint = '/api/getAsset.php';
UPDATE permissionActions SET apiEndpoint = '/api/assets/getAssetHistory.php' WHERE apiEndpoint = '/api/getAssetHistory.php';
UPDATE permissionActions SET apiEndpoint = '/api/assets/getAvailableAssets.php' WHERE apiEndpoint = '/api/getAvailableAssets.php';
UPDATE permissionActions SET apiEndpoint = '/api/assets/assignAsset.php' WHERE apiEndpoint = '/api/assignAsset.php';
UPDATE permissionActions SET apiEndpoint = '/api/assets/returnAsset.php' WHERE apiEndpoint = '/api/returnAsset.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceBreakHistory.php' WHERE apiEndpoint = '/api/getAttendanceBreakHistory.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceBreakHistoryAdmin.php' WHERE apiEndpoint = '/api/getAttendanceBreakHistoryAdmin.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceDetails.php' WHERE apiEndpoint = '/api/getAttendanceDetails.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceManagementListing.php' WHERE apiEndpoint = '/api/getAttendanceManagementListing.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceManagementSummary.php' WHERE apiEndpoint = '/api/getAttendanceManagementSummary.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceSetup.php' WHERE apiEndpoint = '/api/getAttendanceSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getAttendanceState.php' WHERE apiEndpoint = '/api/getAttendanceState.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/saveAttendanceSetup.php' WHERE apiEndpoint = '/api/saveAttendanceSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/updateAttendance.php' WHERE apiEndpoint = '/api/updateAttendance.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/punchIn.php' WHERE apiEndpoint = '/api/punchIn.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/punchOut.php' WHERE apiEndpoint = '/api/punchOut.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/startBreak.php' WHERE apiEndpoint = '/api/startBreak.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/endBreak.php' WHERE apiEndpoint = '/api/endBreak.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getBreakTypes.php' WHERE apiEndpoint = '/api/getBreakTypes.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getEmployeeAttendanceAnalytics.php' WHERE apiEndpoint = '/api/getEmployeeAttendanceAnalytics.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getEmployeeAttendanceHistory.php' WHERE apiEndpoint = '/api/getEmployeeAttendanceHistory.php';
UPDATE permissionActions SET apiEndpoint = '/api/attendance/getEmployeeAttendanceSummary.php' WHERE apiEndpoint = '/api/getEmployeeAttendanceSummary.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/addCandidate.php' WHERE apiEndpoint = '/api/addCandidate.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/addCandidateRemark.php' WHERE apiEndpoint = '/api/addCandidateRemark.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/deleteCandidate.php' WHERE apiEndpoint = '/api/deleteCandidate.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/downloadCandidateImportTemplate.php' WHERE apiEndpoint = '/api/downloadCandidateImportTemplate.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/getCandidateRemarks.php' WHERE apiEndpoint = '/api/getCandidateRemarks.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/importCandidates.php' WHERE apiEndpoint = '/api/importCandidates.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/updateCandidate.php' WHERE apiEndpoint = '/api/updateCandidate.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/updateCandidateStatus.php' WHERE apiEndpoint = '/api/updateCandidateStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/recruitment/verifyCandidate.php' WHERE apiEndpoint = '/api/verifyCandidate.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/finalVerifyCandidate.php' WHERE apiEndpoint = '/api/finalVerifyCandidate.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/checkVerificationReady.php' WHERE apiEndpoint = '/api/checkVerificationReady.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/getEmployeeVerificationData.php' WHERE apiEndpoint = '/api/getEmployeeVerificationData.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/submitHrReview.php' WHERE apiEndpoint = '/api/submitHrReview.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/updateHrVerificationField.php' WHERE apiEndpoint = '/api/updateHrVerificationField.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/getEmployeeDetails.php' WHERE apiEndpoint = '/api/getEmployeeDetails.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/updateEmployeeDetailsByHr.php' WHERE apiEndpoint = '/api/updateEmployeeDetailsByHr.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/getJoiningList.php' WHERE apiEndpoint = '/api/getJoiningList.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/updateJoiningStatus.php' WHERE apiEndpoint = '/api/updateJoiningStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/acceptAgreement.php' WHERE apiEndpoint = '/api/acceptAgreement.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/getAgreementByLeadId.php' WHERE apiEndpoint = '/api/getAgreementByLeadId.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/getAgreementSubmission.php' WHERE apiEndpoint = '/api/getAgreementSubmission.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/saveAgreementDraft.php' WHERE apiEndpoint = '/api/saveAgreementDraft.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/saveAgreementReview.php' WHERE apiEndpoint = '/api/saveAgreementReview.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/sendAgreement.php' WHERE apiEndpoint = '/api/sendAgreement.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/saveOnboardingForm.php' WHERE apiEndpoint = '/api/saveOnboardingForm.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/sendOnboardingForm.php' WHERE apiEndpoint = '/api/sendOnboardingForm.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/updateOnboardingReviewStatus.php' WHERE apiEndpoint = '/api/updateOnboardingReviewStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/onboarding/resendOtp.php' WHERE apiEndpoint = '/api/resendOtp.php';
UPDATE permissionActions SET apiEndpoint = '/api/employee/getEmployees.php' WHERE apiEndpoint = '/api/getEmployees.php';
UPDATE permissionActions SET apiEndpoint = '/api/employee/updateEmployeeEmploymentStatus.php' WHERE apiEndpoint = '/api/updateEmployeeEmploymentStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/employee/updateEmployeeProfile.php' WHERE apiEndpoint = '/api/updateEmployeeProfile.php';
UPDATE permissionActions SET apiEndpoint = '/api/permissions/getRolePermission.php' WHERE apiEndpoint = '/api/getRolePermission.php';
UPDATE permissionActions SET apiEndpoint = '/api/permissions/getEmployeePermissions.php' WHERE apiEndpoint = '/api/getEmployeePermissions.php';
UPDATE permissionActions SET apiEndpoint = '/api/permissions/saveEmployeePermissions.php' WHERE apiEndpoint = '/api/saveEmployeePermissions.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/applyLeave.php' WHERE apiEndpoint = '/api/applyLeave.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/cancelLeave.php' WHERE apiEndpoint = '/api/cancelLeave.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/getAllLeaves.php' WHERE apiEndpoint = '/api/getAllLeaves.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/getLeaveBalance.php' WHERE apiEndpoint = '/api/getLeaveBalance.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/getLeaveSetup.php' WHERE apiEndpoint = '/api/getLeaveSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/getMyLeaves.php' WHERE apiEndpoint = '/api/getMyLeaves.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/saveLeaveSetup.php' WHERE apiEndpoint = '/api/saveLeaveSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/updateLeaveStatus.php' WHERE apiEndpoint = '/api/updateLeaveStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/emp-applyLeave.php' WHERE apiEndpoint = '/api/emp-applyLeave.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/emp-cancelLeave.php' WHERE apiEndpoint = '/api/emp-cancelLeave.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/emp-getLeaveBalance.php' WHERE apiEndpoint = '/api/emp-getLeaveBalance.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/emp-getLeaveSetup.php' WHERE apiEndpoint = '/api/emp-getLeaveSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/leave/emp-getMyLeaves.php' WHERE apiEndpoint = '/api/emp-getMyLeaves.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/addOvertime.php' WHERE apiEndpoint = '/api/addOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/approveOvertime.php' WHERE apiEndpoint = '/api/approveOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/getOvertime.php' WHERE apiEndpoint = '/api/getOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/rejectOvertime.php' WHERE apiEndpoint = '/api/rejectOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/emp-addOvertime.php' WHERE apiEndpoint = '/api/emp-addOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/emp-deleteOvertime.php' WHERE apiEndpoint = '/api/emp-deleteOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/emp-getOvertime.php' WHERE apiEndpoint = '/api/emp-getOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/overtime/emp-updateOvertime.php' WHERE apiEndpoint = '/api/emp-updateOvertime.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/addExpense.php' WHERE apiEndpoint = '/api/addExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/approveExpense.php' WHERE apiEndpoint = '/api/approveExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/deleteExpense.php' WHERE apiEndpoint = '/api/deleteExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/getExpense.php' WHERE apiEndpoint = '/api/getExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/rejectExpense.php' WHERE apiEndpoint = '/api/rejectExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/updateExpense.php' WHERE apiEndpoint = '/api/updateExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/emp-addExpense.php' WHERE apiEndpoint = '/api/emp-addExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/emp-deleteExpense.php' WHERE apiEndpoint = '/api/emp-deleteExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/emp-getExpense.php' WHERE apiEndpoint = '/api/emp-getExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/expense/emp-updateExpense.php' WHERE apiEndpoint = '/api/emp-updateExpense.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/approveCommissionTransaction.php' WHERE apiEndpoint = '/api/approveCommissionTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/deleteCommissionCategory.php' WHERE apiEndpoint = '/api/deleteCommissionCategory.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/deleteCommissionTransaction.php' WHERE apiEndpoint = '/api/deleteCommissionTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/getCommissionCategories.php' WHERE apiEndpoint = '/api/getCommissionCategories.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/getCommissionEmployees.php' WHERE apiEndpoint = '/api/getCommissionEmployees.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/getCommissionSetup.php' WHERE apiEndpoint = '/api/getCommissionSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/getCommissionTransactions.php' WHERE apiEndpoint = '/api/getCommissionTransactions.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/rejectCommissionTransaction.php' WHERE apiEndpoint = '/api/rejectCommissionTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/saveCommissionSetup.php' WHERE apiEndpoint = '/api/saveCommissionSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/saveCommissionTransaction.php' WHERE apiEndpoint = '/api/saveCommissionTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/syncCommissionPayroll.php' WHERE apiEndpoint = '/api/syncCommissionPayroll.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/updateCommissionTransaction.php' WHERE apiEndpoint = '/api/updateCommissionTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/commission/emp-getCommissionTransactions.php' WHERE apiEndpoint = '/api/emp-getCommissionTransactions.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/deletePointCategory.php' WHERE apiEndpoint = '/api/deletePointCategory.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/deletePointTransaction.php' WHERE apiEndpoint = '/api/deletePointTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/getPointCategories.php' WHERE apiEndpoint = '/api/getPointCategories.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/getPointEmployees.php' WHERE apiEndpoint = '/api/getPointEmployees.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/getPointSetup.php' WHERE apiEndpoint = '/api/getPointSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/getPointTransactions.php' WHERE apiEndpoint = '/api/getPointTransactions.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/savePointSetup.php' WHERE apiEndpoint = '/api/savePointSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/savePointTransaction.php' WHERE apiEndpoint = '/api/savePointTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/updatePointTransaction.php' WHERE apiEndpoint = '/api/updatePointTransaction.php';
UPDATE permissionActions SET apiEndpoint = '/api/points/emp-getPointTransactions.php' WHERE apiEndpoint = '/api/emp-getPointTransactions.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/calculatePayrollPreview.php' WHERE apiEndpoint = '/api/calculatePayrollPreview.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/getPayrollSetup.php' WHERE apiEndpoint = '/api/getPayrollSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/savePayrollSetup.php' WHERE apiEndpoint = '/api/savePayrollSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/addDeduction.php' WHERE apiEndpoint = '/api/addDeduction.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/deleteDeduction.php' WHERE apiEndpoint = '/api/deleteDeduction.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/getDeduction.php' WHERE apiEndpoint = '/api/getDeduction.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/updateDeduction.php' WHERE apiEndpoint = '/api/updateDeduction.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/downloadSalarySlip.php' WHERE apiEndpoint = '/api/downloadSalarySlip.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/getSalarySlipApprovals.php' WHERE apiEndpoint = '/api/getSalarySlipApprovals.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/getSalarySlipMonthStatus.php' WHERE apiEndpoint = '/api/getSalarySlipMonthStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/reviewSalarySlipApproval.php' WHERE apiEndpoint = '/api/reviewSalarySlipApproval.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/saveSalarySlipPayment.php' WHERE apiEndpoint = '/api/saveSalarySlipPayment.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/sendSalarySlipPreview.php' WHERE apiEndpoint = '/api/sendSalarySlipPreview.php';
UPDATE permissionActions SET apiEndpoint = '/api/payroll/submitSalarySlipApproval.php' WHERE apiEndpoint = '/api/submitSalarySlipApproval.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/addLead.php' WHERE apiEndpoint = '/api/addLead.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/deleteLead.php' WHERE apiEndpoint = '/api/deleteLead.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/downloadLeadImportTemplate.php' WHERE apiEndpoint = '/api/downloadLeadImportTemplate.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/getFollowUpByDate.php' WHERE apiEndpoint = '/api/getFollowUpByDate.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/getLeadDocuments.php' WHERE apiEndpoint = '/api/getLeadDocuments.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/getLeadMasterData.php' WHERE apiEndpoint = '/api/getLeadMasterData.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/getLeadRemarks.php' WHERE apiEndpoint = '/api/getLeadRemarks.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/getLeadSetup.php' WHERE apiEndpoint = '/api/getLeadSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/importLeads.php' WHERE apiEndpoint = '/api/importLeads.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/saveLeadConversion.php' WHERE apiEndpoint = '/api/saveLeadConversion.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/saveLeadRemark.php' WHERE apiEndpoint = '/api/saveLeadRemark.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/saveLeadSetup.php' WHERE apiEndpoint = '/api/saveLeadSetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/saveLeadStatusRemark.php' WHERE apiEndpoint = '/api/saveLeadStatusRemark.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/updateFollowUpStatus.php' WHERE apiEndpoint = '/api/updateFollowUpStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/updateLead.php' WHERE apiEndpoint = '/api/updateLead.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/updateLeadStatus.php' WHERE apiEndpoint = '/api/updateLeadStatus.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/uploadLeadDocument.php' WHERE apiEndpoint = '/api/uploadLeadDocument.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/closeFollowup.php' WHERE apiEndpoint = '/api/closeFollowup.php';
UPDATE permissionActions SET apiEndpoint = '/api/leads/getScheduledCalls.php' WHERE apiEndpoint = '/api/getScheduledCalls.php';
UPDATE permissionActions SET apiEndpoint = '/api/client/getClientMaster.php' WHERE apiEndpoint = '/api/getClientMaster.php';
UPDATE permissionActions SET apiEndpoint = '/api/client/getClients.php' WHERE apiEndpoint = '/api/getClients.php';
UPDATE permissionActions SET apiEndpoint = '/api/client-onboarding/getClientOnboardingDetails.php' WHERE apiEndpoint = '/api/getClientOnboardingDetails.php';
UPDATE permissionActions SET apiEndpoint = '/api/client-onboarding/submitClientForm.php' WHERE apiEndpoint = '/api/submitClientForm.php';
UPDATE permissionActions SET apiEndpoint = '/api/client-onboarding/uploadClientFiles.php' WHERE apiEndpoint = '/api/uploadClientFiles.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/getDeliverableStructure.php' WHERE apiEndpoint = '/api/getDeliverableStructure.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/getDeliverablesPivot.php' WHERE apiEndpoint = '/api/getDeliverablesPivot.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/saveDeliverableBulk.php' WHERE apiEndpoint = '/api/saveDeliverableBulk.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/saveDeliverableCell.php' WHERE apiEndpoint = '/api/saveDeliverableCell.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/get-client-calendar-plan.php' WHERE apiEndpoint = '/api/get-client-calendar-plan.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/get-clients-calendar.php' WHERE apiEndpoint = '/api/get-clients-calendar.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/save-calendar-plan.php' WHERE apiEndpoint = '/api/save-calendar-plan.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/getCalendarLogs.php' WHERE apiEndpoint = '/api/getCalendarLogs.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/get-features.php' WHERE apiEndpoint = '/api/get-features.php';
UPDATE permissionActions SET apiEndpoint = '/api/deliverables/get-platforms.php' WHERE apiEndpoint = '/api/get-platforms.php';
UPDATE permissionActions SET apiEndpoint = '/api/holidays/addEventHoliday.php' WHERE apiEndpoint = '/api/addEventHoliday.php';
UPDATE permissionActions SET apiEndpoint = '/api/holidays/deleteEventHoliday.php' WHERE apiEndpoint = '/api/deleteEventHoliday.php';
UPDATE permissionActions SET apiEndpoint = '/api/holidays/getEventHolidayById.php' WHERE apiEndpoint = '/api/getEventHolidayById.php';
UPDATE permissionActions SET apiEndpoint = '/api/holidays/updateEventHoliday.php' WHERE apiEndpoint = '/api/updateEventHoliday.php';
UPDATE permissionActions SET apiEndpoint = '/api/holidays/getUpcomingEventCount.php' WHERE apiEndpoint = '/api/getUpcomingEventCount.php';
UPDATE permissionActions SET apiEndpoint = '/api/company/getCompanySetup.php' WHERE apiEndpoint = '/api/getCompanySetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/company/saveCompanySetup.php' WHERE apiEndpoint = '/api/saveCompanySetup.php';
UPDATE permissionActions SET apiEndpoint = '/api/company/manageDepartments.php' WHERE apiEndpoint = '/api/manageDepartments.php';
UPDATE permissionActions SET apiEndpoint = '/api/social-media/getSocialPosts.php' WHERE apiEndpoint = '/api/getSocialPosts.php';
UPDATE permissionActions SET apiEndpoint = '/api/social-media/saveSocialPost.php' WHERE apiEndpoint = '/api/saveSocialPost.php';
UPDATE permissionActions SET apiEndpoint = '/api/social-media/deleteSocialPost.php' WHERE apiEndpoint = '/api/deleteSocialPost.php';
UPDATE permissionActions SET apiEndpoint = '/api/social-media/publishSocialPostNow.php' WHERE apiEndpoint = '/api/publishSocialPostNow.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/getInstagramComments.php' WHERE apiEndpoint = '/api/getInstagramComments.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/getInstagramDashboardSummary.php' WHERE apiEndpoint = '/api/getInstagramDashboardSummary.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/getInstagramInsights.php' WHERE apiEndpoint = '/api/getInstagramInsights.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/getInstagramSettings.php' WHERE apiEndpoint = '/api/getInstagramSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/getInstagramWebhookEvents.php' WHERE apiEndpoint = '/api/getInstagramWebhookEvents.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/hideInstagramComment.php' WHERE apiEndpoint = '/api/hideInstagramComment.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/instagramOauthCallback.php' WHERE apiEndpoint = '/api/instagramOauthCallback.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/instagramOauthStart.php' WHERE apiEndpoint = '/api/instagramOauthStart.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/instagramWebhook.php' WHERE apiEndpoint = '/api/instagramWebhook.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/replyInstagramComment.php' WHERE apiEndpoint = '/api/replyInstagramComment.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/saveInstagramSettings.php' WHERE apiEndpoint = '/api/saveInstagramSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/instagram/disconnectInstagramAccount.php' WHERE apiEndpoint = '/api/disconnectInstagramAccount.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/disconnectLinkedinAccount.php' WHERE apiEndpoint = '/api/disconnectLinkedinAccount.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/getLinkedinOrganizations.php' WHERE apiEndpoint = '/api/getLinkedinOrganizations.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/getLinkedinSettings.php' WHERE apiEndpoint = '/api/getLinkedinSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/linkedinOauthCallback.php' WHERE apiEndpoint = '/api/linkedinOauthCallback.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/linkedinOauthStart.php' WHERE apiEndpoint = '/api/linkedinOauthStart.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/saveLinkedinOrganization.php' WHERE apiEndpoint = '/api/saveLinkedinOrganization.php';
UPDATE permissionActions SET apiEndpoint = '/api/linkedin/saveLinkedinSettings.php' WHERE apiEndpoint = '/api/saveLinkedinSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/disconnectPinterestAccount.php' WHERE apiEndpoint = '/api/disconnectPinterestAccount.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/getPinterestBoards.php' WHERE apiEndpoint = '/api/getPinterestBoards.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/getPinterestSettings.php' WHERE apiEndpoint = '/api/getPinterestSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/pinterestOauthCallback.php' WHERE apiEndpoint = '/api/pinterestOauthCallback.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/pinterestOauthStart.php' WHERE apiEndpoint = '/api/pinterestOauthStart.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/savePinterestBoard.php' WHERE apiEndpoint = '/api/savePinterestBoard.php';
UPDATE permissionActions SET apiEndpoint = '/api/pinterest/savePinterestSettings.php' WHERE apiEndpoint = '/api/savePinterestSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/disconnectGoogleBusinessProfileAccount.php' WHERE apiEndpoint = '/api/disconnectGoogleBusinessProfileAccount.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/getGoogleBusinessProfileAccounts.php' WHERE apiEndpoint = '/api/getGoogleBusinessProfileAccounts.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/getGoogleBusinessProfileLocations.php' WHERE apiEndpoint = '/api/getGoogleBusinessProfileLocations.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/getGoogleBusinessProfileSettings.php' WHERE apiEndpoint = '/api/getGoogleBusinessProfileSettings.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/googleBusinessProfileOauthCallback.php' WHERE apiEndpoint = '/api/googleBusinessProfileOauthCallback.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/googleBusinessProfileOauthStart.php' WHERE apiEndpoint = '/api/googleBusinessProfileOauthStart.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/saveGoogleBusinessProfileLocation.php' WHERE apiEndpoint = '/api/saveGoogleBusinessProfileLocation.php';
UPDATE permissionActions SET apiEndpoint = '/api/google-business-profile/saveGoogleBusinessProfileSettings.php' WHERE apiEndpoint = '/api/saveGoogleBusinessProfileSettings.php';

COMMIT;
