-- Phase 8: Naming refactor — routesMaster updates for the unified module.
--
-- Deliberately UPDATE, never DELETE+INSERT: routesMaster rows are referenced
-- by id from rolePermissions (per-role canView/canEdit/etc grants). Updating
-- routePath/pageFile/routeTitle in place preserves the existing routeId, so
-- every role's existing permission grant for these pages carries over
-- automatically. A delete-then-recreate would issue a new routeId and
-- silently revoke access for every role until someone re-grants it.
--
-- /instagram-automation (the Meta connect/settings page) keeps its route
-- path and pageFile unchanged — only its routeTitle changes. That page's
-- OAuth flow (api/instagramOauthCallback.php) redirects back to this exact
-- path by name; renaming the path would require touching the OAuth
-- callback, which this refactor explicitly does not touch.
--
-- Idempotent: safe to run more than once — after the first run, the WHERE
-- clauses below no longer match anything.

UPDATE routesMaster
SET routePath = '/social-create-post',
    pageFile = '/pages/social-create-post.php',
    routeTitle = 'Create Social Post'
WHERE routePath = '/instagram-create-post';

UPDATE routesMaster
SET routePath = '/social-posts',
    pageFile = '/pages/social-posts.php',
    routeTitle = 'Social Posts'
WHERE routePath = '/instagram-scheduled-posts';

UPDATE routesMaster
SET routeTitle = 'Social Media Automation'
WHERE routePath = '/instagram-automation';
