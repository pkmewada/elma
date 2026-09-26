-- AI Configuration page -- read-only status view over the existing
-- CaptionGeneratorFactory/CaptionGeneratorInterface provider architecture.
-- No new table: provider/model/API-key-configured status is resolved live
-- from environment variables (MODLUS_AI_CAPTION_PROVIDER, ANTHROPIC_API_KEY,
-- MODLUS_OLLAMA_HOST, MODLUS_OLLAMA_MODEL), never stored here.
--
-- Route -- same admin-only pattern every other Social Media page uses
-- (unconditional access via getLoggedInUserType() === 'admin', no
-- rolePermissions row needed). sortOrder continues the group's existing
-- sequence (..., /social-caption-area=216).
--
-- This file runs once per environment; re-running it will error, which is
-- expected (same convention as this repo's other route migrations).

INSERT INTO routesMaster (
    routePath, pageFile, routeTitle, moduleName, layoutType,
    isPublic, isMenuVisible, isActive, sortOrder
)
SELECT
    '/ai-configuration', '/pages/ai-configuration.php', 'AI Configuration',
    'Social Media', 'admin', 0, 1, 1, 217
WHERE NOT EXISTS (
    SELECT 1 FROM routesMaster WHERE routePath = '/ai-configuration'
);
