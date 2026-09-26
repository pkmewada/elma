-- Automation Queue — read-only view over the existing Production ->
-- Automation handoff (socialContentAutomationHandoff + socialPosts).
--
-- No new table: the queue is rendered entirely by
-- SocialAutomationHandoffEngine::listQueue(), a join across tables that
-- already exist. This migration only adds the route.
--
-- Route — same admin-only pattern every other Social Media/Automation page
-- uses (unconditional access via getLoggedInUserType() === 'admin', no
-- rolePermissions row needed). moduleName='Automation' groups it with
-- /instagram-automation (sortOrder 90), /social-create-post, /social-posts
-- -- sortOrder 85 places it just before /instagram-automation as the
-- pipeline's entry point.
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other CREATE-TABLE/route
-- migrations).

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/social-automation', '/pages/social-automation.php', 'Automation Queue',
    'Automation', 'admin', 0, 1, 1, 85
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/social-automation'
);
