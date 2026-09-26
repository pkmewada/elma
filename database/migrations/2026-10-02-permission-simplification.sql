-- Permission simplification (data only; no schema changes, no new tables).
--
-- 1. routesMaster becomes the single source of sidebar membership:
--    includes/sidebar-menu.php lists a route when it is active, not public,
--    isMenuVisible = 1, its layoutType matches the panel and its moduleName is
--    one of the sidebar groups. Items are ordered by sortOrder and labelled by
--    routeTitle, so this migration copies the former hardcoded sidebar arrays
--    (group, label, order) into those columns. Pages reached only by URL or
--    Central Search keep their own moduleName and stay out of the menu.
-- 2. layoutType = 'employee' for every route served from /employee/ (all of
--    those files use includes/emp-auth.php), so "Employee Pages" in
--    Permission Setup and the employee sidebar find them.
-- 3. Small security/data fixes: /signup no longer public, /oboarding-lead
--    isPublic NULL -> 0, two routes pointing at a missing file deactivated,
--    all-zero role rows removed (missing row = deny, so access is unchanged),
--    and the two standard onboarding actions unmapped from the gateway now
--    that their APIs check the page flag in code.
--
-- Run after 2026-10-01b-employee-lead-management-pages.sql. Safe to re-run.
-- Previous values for every route touched here are listed at the bottom.

START TRANSACTION;

-- ---------------------------------------------------------------------------
-- 1. Sidebar metadata (group = moduleName, label = routeTitle, order = sortOrder)
-- ---------------------------------------------------------------------------
CREATE TEMPORARY TABLE menuRoutes (
    routePath VARCHAR(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY,
    moduleName VARCHAR(100) NOT NULL,
    routeTitle VARCHAR(150) NOT NULL,
    sortOrder INT NOT NULL
);

INSERT INTO menuRoutes (routePath, moduleName, routeTitle, sortOrder) VALUES
-- Admin panel
('/dashboard', 'CRM', 'Dashboard', 10),
('/lead-dashboard', 'Lead Management', 'Lead Dashboard', 10),
('/leads', 'Lead Management', 'Lead Records', 20),
('/oboarding-lead', 'Lead Management', 'Onboarding Lead', 30),
('/lead-activity', 'Lead Management', 'Lead Activity', 40),
('/client-onboarding', 'Lead Management', 'Client Onboarding', 50),
('/client-deliverable', 'Lead Management', 'Client Deliverable', 60),
('/candidate-record', 'HRMS', 'Candidate Record', 10),
('/onboarding-queue', 'HRMS', 'Onboarding Queue', 20),
('/employee-directory', 'HRMS', 'Employee Directory', 30),
('/event-holiday-management', 'HRMS', 'Events & Holidays', 40),
('/assets-management', 'HRMS', 'Assets', 50),
('/apply-leave', 'HRMS', 'Leave', 60),
('/overtime-management', 'HRMS', 'Overtime', 70),
('/deduction-management', 'HRMS', 'Deduction', 80),
('/advance-payment', 'HRMS', 'Advance Payment', 90),
('/employee-loan', 'HRMS', 'Employee Loan', 100),
('/expense-management', 'HRMS', 'Expenses', 110),
('/salary-slip', 'HRMS', 'Salary Slip', 120),
('/salary-slip-approval', 'HRMS', 'Salary Slip Approval', 130),
('/employee-point-transactions', 'HRMS', 'Employee Point', 140),
('/employee-commission-bonus', 'HRMS', 'Commission Bonus', 150),
('/attendance-management', 'HRMS', 'Attendance', 160),
('/permission-setup', 'HRMS', 'Permission', 170),
('/setup', 'Setup', 'Master Setup', 10),
('/basic-setup', 'Setup', 'Basic Setup', 20),
('/company-setup', 'Setup', 'Company Setup', 30),
('/leave-setup', 'Setup', 'Leave Setup', 40),
('/attendance-setup', 'Setup', 'Attendance Setup', 50),
('/payroll-setup', 'Setup', 'Payroll Setup', 60),
('/employee-point-setup', 'Setup', 'Employee Point Setup', 70),
('/commission-bonus-setup', 'Setup', 'Commission Bonus Setup', 80),
('/lead-setup', 'Setup', 'Lead Setup', 90),
('/social-media-setup', 'Setup', 'Social Media Setup', 100),
('/route-setup', 'Setup', 'Route / Page Setup', 110),
('/calendar', 'Social Media', 'Calendar', 10),
('/social-data-entry', 'Social Media', 'Social Media Data', 20),
('/other-graphic-content', 'Social Media', 'Other Content', 30),
('/graphic-content', 'Social Media', 'Other Graphic Content', 40),
('/social-content-production', 'Social Media', 'Social Content Production', 50),
('/social-caption-area', 'Social Media', 'Caption Area', 60),
('/ai-configuration', 'Social Media', 'AI Configuration', 70),
('/reviews', 'Social Media', 'Reviews', 80),
('/social-automation', 'Automation', 'Automation Queue', 10),
('/instagram-automation', 'Automation', 'Social Media Automation', 20),
('/social-create-post', 'Automation', 'Create Social Post', 30),
('/social-posts', 'Automation', 'Social Posts', 40),
('/instagram-comments', 'Automation', 'Instagram Comments', 50),
('/instagram-analytics', 'Automation', 'Instagram Analytics', 60),
-- Employee panel
('/emp-dashboard', 'Employee Panel', 'Dashboard', 10),
('/emp-content-production', 'Employee Panel', 'Content Production', 20),
('/emp-content-board', 'Employee Panel', 'Production Board', 30),
('/emp-lead-dashboard', 'Lead Management', 'Lead Dashboard', 10),
('/emp-leads', 'Lead Management', 'Lead Records', 20),
('/emp-onboarding-lead', 'Lead Management', 'Onboarding Lead', 30),
('/emp-lead-activity', 'Lead Management', 'Lead Activity', 40),
('/emp-client-onboarding', 'Lead Management', 'Client Onboarding', 50),
('/emp-event-holiday', 'HRMS', 'Events & Holidays', 10),
('/emp-apply-leave', 'HRMS', 'Apply Leave', 20),
('/emp-overtime-management', 'HRMS', 'Overtime Management', 30),
('/emp-deduction', 'HRMS', 'Deduction', 40),
('/emp-expense-management', 'HRMS', 'Expense Management', 50),
('/emp-my-assets', 'HRMS', 'My Assets', 60),
('/emp-point-transactions', 'HRMS', 'Point', 70),
('/emp-commission-bonus', 'HRMS', 'Commission Bonus', 80),
('/employee-attendance', 'HRMS', 'Attendance', 90);

UPDATE routesMaster rm
INNER JOIN menuRoutes mr ON mr.routePath = rm.routePath
SET
    rm.moduleName = mr.moduleName,
    rm.routeTitle = mr.routeTitle,
    rm.sortOrder = mr.sortOrder,
    rm.isMenuVisible = 1;

DROP TEMPORARY TABLE menuRoutes;

-- Not in any menu, but its old module name now equals a sidebar group; keep it
-- URL/search-only (it is opened from the Social Content Production page).
UPDATE routesMaster SET moduleName = 'Social Media Overview' WHERE routePath = '/social-overview';

-- ---------------------------------------------------------------------------
-- 2. Employee audience
-- ---------------------------------------------------------------------------
UPDATE routesMaster SET layoutType = 'employee' WHERE pageFile LIKE '/employee/%';

-- ---------------------------------------------------------------------------
-- 3. Route fixes
-- ---------------------------------------------------------------------------
-- Admin accounts may only be created by a logged-in admin (see AuthController::signup()).
UPDATE routesMaster SET isPublic = 0 WHERE routePath = '/signup';

UPDATE routesMaster SET isPublic = 0 WHERE routePath = '/oboarding-lead' AND isPublic IS NULL;

-- Both point at employee/oboarding-leads.php, which does not exist.
UPDATE routesMaster SET isActive = 0 WHERE routePath IN ('/oboarding-leads', '/oboarding-leads.php');

-- ---------------------------------------------------------------------------
-- 4. Permission data
-- ---------------------------------------------------------------------------
-- An all-zero row resolves exactly like a missing row (deny).
DELETE FROM rolePermissions
WHERE canView = 0 AND canAdd = 0 AND canEdit = 0 AND canDelete = 0 AND canApprove = 0
  AND COALESCE(canExport, 0) = 0;

DELETE FROM roleActionPermissions WHERE canAccess = 0;

-- saveAgreementDraft.php / saveAgreementReview.php now call
-- requireApiPermission(['/oboarding-lead', '/emp-onboarding-lead'], ...). The
-- gateway mapping could only point at the admin route and would 403 employees.
UPDATE permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
SET pa.apiEndpoint = NULL, pa.httpMethod = NULL
WHERE rm.routePath = '/oboarding-lead'
  AND pa.actionKey IN ('save_agreement_draft', 'review_agreement')
  AND pa.permissionType <> 'special';

-- send_agreement stays a Special Action, registered on BOTH onboarding pages.
-- sendAgreement.php checks it with requireApiActionPermission() against the
-- caller's own page, so the endpoint is no longer mapped to the admin route
-- (the gateway would have judged employees against /oboarding-lead).
UPDATE permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
SET pa.apiEndpoint = NULL, pa.httpMethod = NULL
WHERE rm.routePath = '/oboarding-lead'
  AND pa.actionKey = 'send_agreement';

INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, isActive, sortOrder)
SELECT rm.id, 'send_agreement', 'Send Agreement', 'special', '#sendAgreementBtn', 1, 20
FROM routesMaster rm
WHERE rm.routePath = '/emp-onboarding-lead'
ON DUPLICATE KEY UPDATE permissionActions.id = permissionActions.id;

-- Until now the employee page's Send Agreement was gated by Approve on
-- /emp-onboarding-lead; carry exactly those grants to the new action
-- (same pattern as 2026-06-20-bulk-button-action-registry.sql).
INSERT INTO roleActionPermissions (roleName, actionId, canAccess)
SELECT rp.roleName, pa.id, 1
FROM rolePermissions rp
INNER JOIN routesMaster rm ON rm.id = rp.routeId AND rm.routePath = '/emp-onboarding-lead'
INNER JOIN permissionActions pa ON pa.routeId = rm.id AND pa.actionKey = 'send_agreement'
WHERE rp.canView = 1 AND rp.canApprove = 1
ON DUPLICATE KEY UPDATE canAccess = roleActionPermissions.canAccess;

INSERT INTO userActionPermissionOverrides (userId, actionId, canAccess)
SELECT upo.userId, pa.id, IF(upo.canView = 1 AND upo.canApprove = 1, 1, 0)
FROM userPermissionOverrides upo
INNER JOIN routesMaster rm ON rm.id = upo.routeId AND rm.routePath = '/emp-onboarding-lead'
INNER JOIN permissionActions pa ON pa.routeId = rm.id AND pa.actionKey = 'send_agreement'
ON DUPLICATE KEY UPDATE canAccess = userActionPermissionOverrides.canAccess;

COMMIT;

-- ---------------------------------------------------------------------------
-- Rollback (values before this migration, captured from the local database).
-- Deleted all-zero rolePermissions / roleActionPermissions rows need no
-- rollback: a missing row already resolves to deny.
-- ---------------------------------------------------------------------------
-- UPDATE routesMaster SET routeTitle = 'Signup', moduleName = 'Auth', layoutType = 'admin', isPublic = 1, isMenuVisible = 0, sortOrder = 2, isActive = 1 WHERE routePath = '/signup';
-- UPDATE routesMaster SET routeTitle = 'Automation Queue', moduleName = 'Automation', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 85, isActive = 1 WHERE routePath = '/social-automation';
-- UPDATE routesMaster SET routeTitle = 'Social Media Automation', moduleName = 'Automation', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 90, isActive = 1 WHERE routePath = '/instagram-automation';
-- UPDATE routesMaster SET routeTitle = 'Create Social Post', moduleName = 'Automation', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 91, isActive = 1 WHERE routePath = '/social-create-post';
-- UPDATE routesMaster SET routeTitle = 'Social Posts', moduleName = 'Automation', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 92, isActive = 1 WHERE routePath = '/social-posts';
-- UPDATE routesMaster SET routeTitle = 'Instagram Comments', moduleName = 'Automation', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 93, isActive = 1 WHERE routePath = '/instagram-comments';
-- UPDATE routesMaster SET routeTitle = 'Instagram Analytics', moduleName = 'Automation', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 94, isActive = 1 WHERE routePath = '/instagram-analytics';
-- UPDATE routesMaster SET routeTitle = 'Candidate Record', moduleName = 'Candidate', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 40, isActive = 1 WHERE routePath = '/candidate-record';
-- UPDATE routesMaster SET routeTitle = 'Onboarding Queue', moduleName = 'Candidate', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 41, isActive = 1 WHERE routePath = '/onboarding-queue';
-- UPDATE routesMaster SET routeTitle = 'Candidate Onboarding Lead', moduleName = 'Candidate Onboarding Lead', layoutType = 'employee', isPublic = 1, isMenuVisible = 1, sortOrder = 206, isActive = 1 WHERE routePath = '/oboarding-leads.php';
-- UPDATE routesMaster SET routeTitle = 'Client Deliverable', moduleName = 'Client Deliverable', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 0, isActive = 1 WHERE routePath = '/client-deliverable';
-- UPDATE routesMaster SET routeTitle = 'Client Onboarding', moduleName = 'Client Onboarding', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 0, isActive = 1 WHERE routePath = '/client-onboarding';
-- UPDATE routesMaster SET routeTitle = 'Dashboard', moduleName = 'Dashboard', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 10, isActive = 1 WHERE routePath = '/dashboard';
-- UPDATE routesMaster SET routeTitle = 'Employee Directory', moduleName = 'Employee', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 50, isActive = 1 WHERE routePath = '/employee-directory';
-- UPDATE routesMaster SET routeTitle = 'Employee Dashboard', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 100, isActive = 1 WHERE routePath = '/emp-dashboard';
-- UPDATE routesMaster SET routeTitle = 'Employee Event Holiday', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 101, isActive = 1 WHERE routePath = '/emp-event-holiday';
-- UPDATE routesMaster SET routeTitle = 'Employee Apply Leave', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 102, isActive = 1 WHERE routePath = '/emp-apply-leave';
-- UPDATE routesMaster SET routeTitle = 'Employee Overtime', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 103, isActive = 1 WHERE routePath = '/emp-overtime-management';
-- UPDATE routesMaster SET routeTitle = 'Employee Deduction', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 104, isActive = 1 WHERE routePath = '/emp-deduction';
-- UPDATE routesMaster SET routeTitle = 'Employee Expense', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 105, isActive = 1 WHERE routePath = '/emp-expense-management';
-- UPDATE routesMaster SET routeTitle = 'Employee Point Transactions', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 106, isActive = 1 WHERE routePath = '/emp-point-transactions';
-- UPDATE routesMaster SET routeTitle = 'Employee Commission Bonus', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 107, isActive = 1 WHERE routePath = '/emp-commission-bonus';
-- UPDATE routesMaster SET routeTitle = 'Employee Profile', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 1, isMenuVisible = 0, sortOrder = 108, isActive = 1 WHERE routePath = '/employee-profile';
-- UPDATE routesMaster SET routeTitle = 'Employee Attendance', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 109, isActive = 1 WHERE routePath = '/employee-attendance';
-- UPDATE routesMaster SET routeTitle = 'Employee Leads', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 110, isActive = 1 WHERE routePath = '/emp-leads';
-- UPDATE routesMaster SET routeTitle = 'Onboarding Leads', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 111, isActive = 1 WHERE routePath = '/oboarding-leads';
-- UPDATE routesMaster SET routeTitle = 'My Production Tasks', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 112, isActive = 1 WHERE routePath = '/emp-content-production';
-- UPDATE routesMaster SET routeTitle = 'My Production Board', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 113, isActive = 1 WHERE routePath = '/emp-content-board';
-- UPDATE routesMaster SET routeTitle = 'My Assets', moduleName = 'Employee Panel', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 1002, isActive = 1 WHERE routePath = '/emp-my-assets';
-- UPDATE routesMaster SET routeTitle = 'Event Holiday Management', moduleName = 'HR', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 60, isActive = 1 WHERE routePath = '/event-holiday-management';
-- UPDATE routesMaster SET routeTitle = 'Assets Management', moduleName = 'HR', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 61, isActive = 1 WHERE routePath = '/assets-management';
-- UPDATE routesMaster SET routeTitle = 'Leave Setup', moduleName = 'HR', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 62, isActive = 1 WHERE routePath = '/leave-setup';
-- UPDATE routesMaster SET routeTitle = 'Apply Leave', moduleName = 'HR', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 63, isActive = 1 WHERE routePath = '/apply-leave';
-- UPDATE routesMaster SET routeTitle = 'Attendance Management', moduleName = 'HR', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 65, isActive = 1 WHERE routePath = '/attendance-management';
-- UPDATE routesMaster SET routeTitle = 'Salary Slip', moduleName = 'HRMS', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 84, isActive = 1 WHERE routePath = '/salary-slip';
-- UPDATE routesMaster SET routeTitle = 'Salary Slip Approval', moduleName = 'HRMS', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 85, isActive = 1 WHERE routePath = '/salary-slip-approval';
-- UPDATE routesMaster SET routeTitle = 'Lead Activity', moduleName = 'Lead', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 0, isActive = 1 WHERE routePath = '/lead-activity';
-- UPDATE routesMaster SET routeTitle = 'Leads', moduleName = 'Lead', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 21, isActive = 1 WHERE routePath = '/leads';
-- UPDATE routesMaster SET routeTitle = 'Lead Setup', moduleName = 'Lead', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 22, isActive = 1 WHERE routePath = '/lead-setup';
-- UPDATE routesMaster SET routeTitle = 'Lead Dashboard', moduleName = 'Lead', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 25, isActive = 1 WHERE routePath = '/lead-dashboard';
-- UPDATE routesMaster SET routeTitle = 'Onboarding Lead', moduleName = 'Onboarding Lead', layoutType = 'admin', isPublic = NULL, isMenuVisible = 1, sortOrder = 206, isActive = 1 WHERE routePath = '/oboarding-lead';
-- UPDATE routesMaster SET routeTitle = 'Overtime Management', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 70, isActive = 1 WHERE routePath = '/overtime-management';
-- UPDATE routesMaster SET routeTitle = 'Deduction Management', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 71, isActive = 1 WHERE routePath = '/deduction-management';
-- UPDATE routesMaster SET routeTitle = 'Expense Management', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 72, isActive = 1 WHERE routePath = '/expense-management';
-- UPDATE routesMaster SET routeTitle = 'Employee Point Setup', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 73, isActive = 1 WHERE routePath = '/employee-point-setup';
-- UPDATE routesMaster SET routeTitle = 'Employee Point Transactions', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 74, isActive = 1 WHERE routePath = '/employee-point-transactions';
-- UPDATE routesMaster SET routeTitle = 'Commission Bonus Setup', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 75, isActive = 1 WHERE routePath = '/commission-bonus-setup';
-- UPDATE routesMaster SET routeTitle = 'Employee Commission Bonus', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 76, isActive = 1 WHERE routePath = '/employee-commission-bonus';
-- UPDATE routesMaster SET routeTitle = 'Advance Payment', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 77, isActive = 1 WHERE routePath = '/advance-payment';
-- UPDATE routesMaster SET routeTitle = 'Employee Loan', moduleName = 'Payroll', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 78, isActive = 1 WHERE routePath = '/employee-loan';
-- UPDATE routesMaster SET routeTitle = 'Organization Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 0, isActive = 1 WHERE routePath = '/company-setup';
-- UPDATE routesMaster SET routeTitle = 'Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 30, isActive = 1 WHERE routePath = '/setup';
-- UPDATE routesMaster SET routeTitle = 'Basic Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 31, isActive = 1 WHERE routePath = '/basic-setup';
-- UPDATE routesMaster SET routeTitle = 'Attendance Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 32, isActive = 1 WHERE routePath = '/attendance-setup';
-- UPDATE routesMaster SET routeTitle = 'Permission Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 33, isActive = 1 WHERE routePath = '/permission-setup';
-- UPDATE routesMaster SET routeTitle = 'Route / Page Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 34, isActive = 1 WHERE routePath = '/route-setup';
-- UPDATE routesMaster SET routeTitle = 'Payroll Setup', moduleName = 'Setup', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 85, isActive = 1 WHERE routePath = '/payroll-setup';
-- UPDATE routesMaster SET routeTitle = 'Calendar', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 0, isActive = 1 WHERE routePath = '/calendar';
-- UPDATE routesMaster SET routeTitle = 'Reviews', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 0, isActive = 1 WHERE routePath = '/reviews';
-- UPDATE routesMaster SET routeTitle = 'Social Media Data Entry', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 210, isActive = 1 WHERE routePath = '/social-data-entry';
-- UPDATE routesMaster SET routeTitle = 'Social Media Overview', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 211, isActive = 1 WHERE routePath = '/social-overview';
-- UPDATE routesMaster SET routeTitle = 'Content Production', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 212, isActive = 1 WHERE routePath = '/social-content-production';
-- UPDATE routesMaster SET routeTitle = 'Social Media Setup', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 213, isActive = 1 WHERE routePath = '/social-media-setup';
-- UPDATE routesMaster SET routeTitle = 'Other Graphic Content', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 214, isActive = 1 WHERE routePath = '/other-graphic-content';
-- UPDATE routesMaster SET routeTitle = 'Other Graphic Content', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 215, isActive = 1 WHERE routePath = '/graphic-content';
-- UPDATE routesMaster SET routeTitle = 'Caption Area', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 216, isActive = 1 WHERE routePath = '/social-caption-area';
-- UPDATE routesMaster SET routeTitle = 'AI Configuration', moduleName = 'Social Media', layoutType = 'admin', isPublic = 0, isMenuVisible = 1, sortOrder = 217, isActive = 1 WHERE routePath = '/ai-configuration';
-- UPDATE permissionActions pa INNER JOIN routesMaster rm ON rm.id = pa.routeId SET pa.apiEndpoint = '/api/onboarding/saveAgreementDraft.php', pa.httpMethod = 'POST' WHERE rm.routePath = '/oboarding-lead' AND pa.actionKey = 'save_agreement_draft';
-- UPDATE permissionActions pa INNER JOIN routesMaster rm ON rm.id = pa.routeId SET pa.apiEndpoint = '/api/onboarding/saveAgreementReview.php', pa.httpMethod = 'POST' WHERE rm.routePath = '/oboarding-lead' AND pa.actionKey = 'review_agreement';
-- UPDATE permissionActions pa INNER JOIN routesMaster rm ON rm.id = pa.routeId SET pa.apiEndpoint = '/api/onboarding/sendAgreement.php', pa.httpMethod = 'POST' WHERE rm.routePath = '/oboarding-lead' AND pa.actionKey = 'send_agreement';
-- DELETE pa FROM permissionActions pa INNER JOIN routesMaster rm ON rm.id = pa.routeId WHERE rm.routePath = '/emp-onboarding-lead' AND pa.actionKey = 'send_agreement'; -- also remove its roleActionPermissions / userActionPermissionOverrides rows first
