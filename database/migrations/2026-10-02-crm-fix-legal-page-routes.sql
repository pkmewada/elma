-- Real Estate CRM — fix: public legal pages (/privacy-policy, /terms-of-service,
-- /data-deletion) unreachable.
--
-- Root cause: 2026-09-26c-crm-deactivate-modlus-routes.sql deactivated these 3
-- routes along with the rest of the Modlus route sweep (isActive = 0), on the
-- understanding they'd be re-enabled once rewritten for this CRM. They WERE
-- rewritten in Phase 6, and 2026-09-30-crm-phase6-integrations.sql does
-- reactivate them -- but that reactivation is bundled inside a migration
-- named/scoped for "integrations", so an environment that applied migrations
-- up to Phase 3/5 and then skipped the integrations migration (reasonably
-- assuming it only matters once Meta/Google/WhatsApp are configured) is left
-- with these 3 routes still isActive = 0. getRouteByPath() (includes/
-- permission-helper.php) requires isActive = 1, so routes.php returns a plain
-- 404 for all three URLs -- not a login redirect, not a permission error, not
-- a code bug in routes.php/the router itself (verified: the router's isPublic
-- check and pageFile resolution are correct once the row is active).
--
-- This migration does not depend on 2026-09-30 having run: it unconditionally
-- repairs the 3 rows (UPDATE) and self-heals if they are missing entirely
-- (INSERT ... WHERE NOT EXISTS, same values as the original baseline insert),
-- so it is safe to run standalone against any production DB state as the fix
-- for "legal pages return 404 / are not publicly reachable".
--
-- Additive/idempotent; safe to run multiple times.

SET NAMES utf8mb4;

-- 1. Repair any existing rows that are inactive, non-public, or point at the
--    wrong file (belt-and-braces -- only these 3 exact paths are touched).
UPDATE routesMaster
SET isActive = 1, isPublic = 1, layoutType = 'public', pageFile = CONCAT('/pages/', SUBSTRING(routePath, 2), '.php'), updatedAt = NOW()
WHERE routePath IN ('/privacy-policy', '/terms-of-service', '/data-deletion');

-- 2. Self-heal if a route is missing entirely (never created on this DB).
INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/privacy-policy', '/pages/privacy-policy.php', 'Privacy Policy', 'Public', 'public', 1, 0, 1, 999, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/privacy-policy');

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/terms-of-service', '/pages/terms-of-service.php', 'Terms of Service', 'Public', 'public', 1, 0, 1, 1000, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/terms-of-service');

INSERT INTO routesMaster (routePath, pageFile, routeTitle, moduleName, layoutType, isPublic, isMenuVisible, isActive, sortOrder, createdAt, updatedAt)
SELECT '/data-deletion', '/pages/data-deletion.php', 'Data Deletion Instructions', 'Public', 'public', 1, 0, 1, 1001, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM routesMaster WHERE routePath = '/data-deletion');
