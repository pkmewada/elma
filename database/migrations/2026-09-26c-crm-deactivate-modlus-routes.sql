-- Real Estate CRM — Phase 1 route cleanup (CRM database only).
--
-- Step 1 of the reversible cleanup: switch off every inherited Modlus route
-- that is outside the CRM scope. Rows are kept (isActive = 0), so any route
-- can be restored with a single UPDATE. The sidebar and router both ignore
-- inactive rows, so these pages disappear from navigation and return 404.
--
-- Scope removed: Social Media / Automation / Instagram / GBP reviews & QR,
-- Agreements / Client Onboarding / Deliverables / Acknowledgments,
-- Recruitment / Candidates / Walk-ins / Onboarding Queue, HRMS business
-- modules (payroll, attendance, leave, overtime, loans, advances, expenses,
-- points, commission, assets, events), duplicate/test pages.

UPDATE routesMaster SET isActive = 0, updatedAt = NOW()
WHERE routePath IN (
    -- Auth / duplicates
    '/signup',
    '/addlead',

    -- Social Media / Automation
    '/calendar',
    '/social-data-entry',
    '/other-graphic-content',
    '/graphic-content',
    '/social-content-production',
    '/social-caption-area',
    '/ai-configuration',
    '/reviews',
    '/social-overview',
    '/social-automation',
    '/instagram-automation',
    '/social-create-post',
    '/social-posts',
    '/instagram-comments',
    '/instagram-analytics',
    '/social-media-setup',
    '/qrcode',
    '/generate-review',
    '/emp-content-production',
    '/emp-content-board',

    -- Agreements / Client Onboarding / Deliverables / Acknowledgments
    '/oboarding-lead',
    '/client-onboarding',
    '/client-deliverable',
    '/agreement',
    '/agreement-success',
    '/acknowledgment',
    '/acknowledgment-form',
    '/terms-and-conditions',
    '/client-onboarding-form',
    '/client-thankyou',
    '/emp-onboarding-lead',
    '/emp-client-onboarding',
    '/oboarding-leads',
    '/oboarding-leads.php',

    -- Recruitment / Candidates
    '/candidate-record',
    '/walkin-candidates',
    '/onboarding-queue',
    '/public-record',

    -- HRMS business modules (admin)
    '/verify-leave',
    '/event-holiday-management',
    '/assets-management',
    '/apply-leave',
    '/overtime-management',
    '/deduction-management',
    '/advance-payment',
    '/employee-loan',
    '/expense-management',
    '/salary-slip',
    '/salary-slip-approval',
    '/employee-point-transactions',
    '/employee-commission-bonus',
    '/attendance-management',
    '/leave-setup',
    '/attendance-setup',
    '/payroll-setup',
    '/employee-point-setup',
    '/commission-bonus-setup',

    -- HRMS business modules (employee)
    '/emp-event-holiday',
    '/emp-apply-leave',
    '/emp-overtime-management',
    '/emp-deduction',
    '/emp-expense-management',
    '/emp-my-assets',
    '/emp-point-transactions',
    '/emp-commission-bonus',
    '/employee-attendance',

    -- Modlus-branded public legal pages: files kept, re-enabled once
    -- rewritten for the CRM client (needed for Meta Lead Ads, Phase 6).
    '/privacy-policy',
    '/terms-of-service',
    '/data-deletion'
);

-- Follow Up pages were registered under moduleName 'Lead', which is not a
-- sidebar group, so they never appeared in the menu. Move them into the
-- Lead Management group.
UPDATE routesMaster SET moduleName = 'Lead Management', sortOrder = 20, updatedAt = NOW()
WHERE routePath = '/lead-follow-up-list';

UPDATE routesMaster SET moduleName = 'Setup', sortOrder = 12, updatedAt = NOW()
WHERE routePath = '/lead-follow-up-setup';

-- UI component gallery: keep reachable for developers, hide from menu.
UPDATE routesMaster SET isMenuVisible = 0, updatedAt = NOW()
WHERE routePath = '/setup';

-- Permission mappings for deactivated routes: none are seeded in the CRM
-- database (rolePermissions / userPermissionOverrides / permissionActions
-- start empty), so there is nothing to revoke here. The delete below keeps
-- this migration safe if any are added before it runs.
DELETE rp FROM rolePermissions rp
INNER JOIN routesMaster rm ON rm.id = rp.routeId
WHERE rm.isActive = 0;

DELETE upo FROM userPermissionOverrides upo
INNER JOIN routesMaster rm ON rm.id = upo.routeId
WHERE rm.isActive = 0;

UPDATE permissionActions pa
INNER JOIN routesMaster rm ON rm.id = pa.routeId
SET pa.isActive = 0
WHERE rm.isActive = 0;
