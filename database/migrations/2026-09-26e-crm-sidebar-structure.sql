-- Real Estate CRM — sidebar structure (Phase 1).
--
-- Menu groups are defined in includes/sidebar-menu.php and keyed by
-- routesMaster.moduleName. This regroups/relabels the kept routes into the
-- CRM navigation:
--   Dashboard | Lead Management (All Leads, Follow-ups, Lead Dashboard,
--   Lead Activity) | Employees (Employee Directory, Roles & Permissions) |
--   Settings (Basic, Company, Lead, Follow Up, Routes)
-- Projects / Reports / Integrations groups exist and appear once their
-- routes are added (Phases 4-6).

UPDATE routesMaster SET moduleName = 'Employees', routeTitle = 'Employee Directory', sortOrder = 10, updatedAt = NOW()
WHERE routePath = '/employee-directory';

UPDATE routesMaster SET moduleName = 'Employees', routeTitle = 'Roles & Permissions', sortOrder = 20, updatedAt = NOW()
WHERE routePath = '/permission-setup';

UPDATE routesMaster SET routeTitle = 'All Leads', sortOrder = 10, updatedAt = NOW()
WHERE routePath = '/leads';

UPDATE routesMaster SET routeTitle = 'Follow-ups', sortOrder = 20, updatedAt = NOW()
WHERE routePath = '/lead-follow-up-list';

UPDATE routesMaster SET sortOrder = 30, updatedAt = NOW()
WHERE routePath = '/lead-dashboard';

UPDATE routesMaster SET sortOrder = 40, updatedAt = NOW()
WHERE routePath = '/lead-activity';

UPDATE routesMaster SET routeTitle = 'My Leads', sortOrder = 20, updatedAt = NOW()
WHERE routePath = '/emp-leads';

UPDATE routesMaster SET routeTitle = 'Employee Login', updatedAt = NOW()
WHERE routePath = '/candidate-login';
