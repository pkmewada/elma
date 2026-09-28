-- Real Estate CRM — permissionActions for existing functionality (Phase 2).
--
-- 1. Special actions (granted per role in Roles & Permissions):
--    /emp-leads  view-all-leads  -> employee sees every lead, not only own
--                                   (checked by includes/leadAccess.php).
--                                   Granted to Sales Manager by default;
--                                   revocable. Not granted to Sales Executive.
--    /emp-leads  import_leads    -> CSV import (checked by importLeads.php
--    /leads      import_leads       + the Import button on the lead pages).
--
-- 2. API gateway mappings (api-gateway.php enforces canView + the action for
--    the mapped route). Only single-route admin APIs are mapped: lead APIs
--    serve both /leads and /emp-leads and the gateway resolves one route per
--    endpoint, so those stay enforced inside each endpoint (leadAccess.php).
--
-- Idempotent: rows are keyed by (route, actionKey).

INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder, createdAt, updatedAt)
SELECT rm.id, a.actionKey, a.actionLabel, a.permissionType, NULL, a.apiEndpoint, a.httpMethod, 1, a.sortOrder, NOW(), NOW()
FROM (
    SELECT '/emp-leads' AS routePath, 'view-all-leads' AS actionKey, 'View All Leads' AS actionLabel, 'special' AS permissionType, NULL AS apiEndpoint, NULL AS httpMethod, 10 AS sortOrder
    UNION ALL SELECT '/emp-leads', 'import_leads', 'Import Leads', 'special', NULL, NULL, 20
    UNION ALL SELECT '/leads', 'import_leads', 'Import Leads', 'special', NULL, NULL, 20

    UNION ALL SELECT '/employee-directory', 'add_employee', 'Add Employee', 'canAdd', '/api/employee/addEmployee.php', 'POST', 10
    UNION ALL SELECT '/employee-directory', 'edit_employee', 'Edit Employee Details', 'canEdit', '/api/employee/updateEmployeeDetailsByHr.php', 'POST', 20
    UNION ALL SELECT '/employee-directory', 'change_employment_status', 'Change Employment Status', 'canEdit', '/api/employee/updateEmployeeEmploymentStatus.php', 'POST', 30
    UNION ALL SELECT '/permission-setup', 'save_employee_permissions', 'Save Employee Permissions', 'canEdit', '/api/permissions/saveEmployeePermissions.php', 'POST', 10
    UNION ALL SELECT '/company-setup', 'save_company_setup', 'Save Company Setup', 'canEdit', '/api/company/saveCompanySetup.php', 'POST', 10
    UNION ALL SELECT '/basic-setup', 'manage_departments', 'Manage Departments', 'canEdit', '/api/company/manageDepartments.php', 'POST', 10
    UNION ALL SELECT '/lead-setup', 'save_lead_setup', 'Save Lead Setup', 'canEdit', '/api/leads/saveLeadSetup.php', 'POST', 10
    UNION ALL SELECT '/lead-follow-up-setup', 'save_follow_up_rule', 'Save Follow Up Rule', 'canEdit', '/api/leads/saveLeadFollowUpSetting.php', 'POST', 10
    UNION ALL SELECT '/lead-follow-up-setup', 'delete_follow_up_rule', 'Delete Follow Up Rule', 'canDelete', '/api/leads/deleteLeadFollowUpSetting.php', 'POST', 20
) a
INNER JOIN routesMaster rm ON rm.routePath = a.routePath AND rm.isActive = 1
WHERE NOT EXISTS (
    SELECT 1 FROM permissionActions pa WHERE pa.routeId = rm.id AND pa.actionKey = a.actionKey
);

-- Sales Manager monitors the whole team's leads (client scope) -> grant
-- View All Leads. Sales Executive intentionally has no grant (own leads only).
INSERT INTO roleActionPermissions (roleName, actionId, canAccess, createdAt, updatedAt)
SELECT 'Sales Manager', pa.id, 1, NOW(), NOW()
FROM permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
WHERE rm.routePath = '/emp-leads' AND pa.actionKey = 'view-all-leads'
AND NOT EXISTS (
    SELECT 1 FROM roleActionPermissions rap WHERE rap.roleName = 'Sales Manager' AND rap.actionId = pa.id
);

-- 3. UI button mapping (includes/button-permission-client.php disables the
--    matching buttons, incl. DataTable rows rendered later): the Delete
--    button on My Leads follows the role's canDelete. The server enforces the
--    same rule in api/leads/deleteLead.php.
INSERT INTO permissionActions (routeId, actionKey, actionLabel, permissionType, buttonSelector, apiEndpoint, httpMethod, isActive, sortOrder, createdAt, updatedAt)
SELECT rm.id, 'delete_lead', 'Delete Lead', 'canDelete', '.delete-lead-btn', NULL, NULL, 1, 30, NOW(), NOW()
FROM routesMaster rm
WHERE rm.routePath = '/emp-leads' AND rm.isActive = 1
AND NOT EXISTS (
    SELECT 1 FROM permissionActions pa WHERE pa.routeId = rm.id AND pa.actionKey = 'delete_lead'
);
