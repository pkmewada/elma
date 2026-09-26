# Google Business Profile Integration Foundation — Phase 14

**Project:** Modlus Social Media Automation  
**Phase:** 14 — Google Business Profile (GBP/GMB) Integration Foundation  
**Status:** FOUNDATION PLANNED — LIVE GOOGLE API ACCESS PENDING EXTERNAL PREREQUISITES  
**Date:** 2026-09-01

---

## 1. Purpose

This document is the source of truth for implementing the Google Business Profile integration foundation in Modlus.

The goal is to build the complete Modlus-side architecture now, in the same way that LinkedIn and Pinterest foundations were built, without waiting for a real Google Business Profile or Google Business Profile API approval.

The foundation must be production-oriented, secure, multi-client, and ready for live OAuth/API verification once the required Google prerequisites are available.

This phase does **not** implement publishing, review management, analytics, scheduling, or changes to `SocialPostEngine.php`.

---

## 2. Current external dependency

Google Business Profile APIs are not open to all users.

Current Google requirements state that an applicant requesting Business Profile API access must:

- Have a Google Account.
- Manage a Google Business Profile that is verified and active for 60+ days.
- The qualifying profile may belong to the applicant or to a client they manage.
- Have a website representing the business listed on the profile.
- Have a valid Google Cloud project.
- Request access through Google's Business Profile API access process.
- Use an email address that is an owner/manager on the qualifying Business Profile.

Google states that API access is reviewed after submission. API quota can be used to determine approval: 0 QPM means not approved; 300 QPM means approved.

**Important:** Modlus currently does not have a qualifying GBP available for live testing. This is an external dependency and must not block foundation implementation.

---

## 3. Architecture relationship to previous integrations

Use the existing LinkedIn and Pinterest integrations as the primary architectural templates.

### Reuse

- `includes/Crypto.php`
- `includes/Csrf.php`
- existing session authentication
- existing `db.php`
- existing `auth.php`
- existing `respond()` API response convention
- existing `saveActivityLog()`
- existing client selector on `pages/instagram-automation.php`
- existing `showToast()` convention
- existing client ownership/isolation patterns
- existing encrypted-token storage pattern

### Do not reuse vendor-specific transports

Do not reuse:

- `instagramGraphApiRequest()`
- LinkedIn-specific transport
- Pinterest-specific transport

Google requires its own OAuth/API transport because Google OAuth and Business Profile API request/response formats are vendor-specific.

---

## 4. Google Business Profile resource hierarchy

Do not model GBP as a simple social account + board.

Google Business Profile uses a hierarchy that must be represented correctly:

```text
Google Account
    ↓
Business Profile Account
    ↓
Location / Business Profile
```

Google documents four Business Profile account types:

- Personal
- Organization
- Location Group
- User Group

For a third-party partner/agency platform such as Modlus, Google documents an **Organization account** as the appropriate account type for the agency/partner model.

The implementation must therefore preserve:

- Google user identity
- Google Business Profile account ID
- Location ID
- Location name/display data
- account type where available
- selected location/resource relationship

Do not collapse these identifiers into one generic ID.

---

## 5. Multi-client architecture

Modlus is multi-client.

Use:

```text
clientId
```

everywhere appropriate.

Never introduce:

```text
companyId
```

The Google account connection belongs to a Modlus client.

The server must enforce ownership before:

- reading connected account data
- selecting/saving a location
- disconnecting
- refreshing tokens
- performing future Google API operations

Never trust a browser-posted `clientId` + resource combination.

Implement a dedicated guard equivalent to:

```php
googleBusinessProfileAccountBelongsToClient(mysqli $con, int $accountId, int $clientId): bool
```

The guard must query the database server-side.

---

## 6. Proposed database design

Create two additive tables.

### `googleBusinessProfileSettings`

Purpose: store the Google Cloud OAuth application configuration.

Suggested structure:

```sql
CREATE TABLE IF NOT EXISTS googleBusinessProfileSettings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    googleClientId VARCHAR(191) NOT NULL DEFAULT '',
    googleClientSecret TEXT NOT NULL,
    redirectUrl VARCHAR(255) NOT NULL DEFAULT '',
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,
    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Secret must be encrypted using existing `Crypto.php`.

### `googleBusinessProfileAccounts`

Suggested structure:

```sql
CREATE TABLE IF NOT EXISTS googleBusinessProfileAccounts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    clientId INT NULL DEFAULT NULL,
    createdBy INT UNSIGNED NOT NULL DEFAULT 0,

    googleUserId VARCHAR(191) NOT NULL DEFAULT '',
    googleUserEmail VARCHAR(191) NOT NULL DEFAULT '',

    googleAccountId VARCHAR(191) NOT NULL DEFAULT '',
    googleAccountName VARCHAR(255) NOT NULL DEFAULT '',
    googleAccountType VARCHAR(100) NOT NULL DEFAULT '',

    googleLocationId VARCHAR(191) NOT NULL DEFAULT '',
    googleLocationName VARCHAR(255) NOT NULL DEFAULT '',
    locationTitle VARCHAR(255) NOT NULL DEFAULT '',
    locationAddress TEXT NULL,

    accessToken TEXT NOT NULL,
    refreshToken TEXT NOT NULL,

    tokenExpiry DATETIME NULL DEFAULT NULL,

    status VARCHAR(20) NOT NULL DEFAULT 'connected',

    createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uqGoogleUserAccount (googleUserId, googleAccountId),
    KEY idxGoogleBusinessProfileClientId (clientId),

    CONSTRAINT fkGoogleBusinessProfileAccountsClient
        FOREIGN KEY (clientId)
        REFERENCES clientMaster (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

The exact column set may be adjusted after inspecting the current Google API response shapes. Do not invent fields that cannot be supported by the API.

Both access and refresh tokens must be encrypted.

---

## 7. OAuth model

Google Business Profile APIs require OAuth 2.0.

Required scope:

```text
https://www.googleapis.com/auth/business.manage
```

The deprecated:

```text
https://www.googleapis.com/auth/plus.business.manage
```

must not be used for new implementation.

Use authorization-code OAuth for the web application and request offline access so Modlus can refresh tokens and operate when the user is not present.

Expected flow:

```text
Modlus client selected
        ↓
googleBusinessProfileOauthStart.php
        ↓
Google OAuth authorization
        ↓
googleBusinessProfileOauthCallback.php
        ↓
validate OAuth state
        ↓
exchange authorization code
        ↓
store encrypted access/refresh tokens
        ↓
discover Business Profile accounts
        ↓
discover locations
        ↓
user selects appropriate location
        ↓
server-side re-verification
        ↓
persist selected account/location
        ↓
redirect back to Modlus settings page
```

OAuth state must:

- be generated using cryptographically secure randomness
- be stored in the server session
- be validated using `hash_equals()`
- be removed immediately after validation

Mirror the existing Instagram/LinkedIn/Pinterest pattern.

---

## 8. Token security

Reuse `Crypto.php`.

Never create a new encryption mechanism.

Required behavior:

- Google Client Secret encrypted at rest.
- Google Access Token encrypted at rest.
- Google Refresh Token encrypted at rest.
- Tokens never returned from display/settings APIs.
- Tokens never placed in URLs.
- Tokens never logged.
- Authorization codes never logged.
- Client secret never logged.
- Debug logging must redact OAuth credentials and authorization codes.
- API transport must send OAuth access token through the Authorization header.

Display helper should return only safe metadata, similar to:

```text
getLinkedinAccountForDisplay()
getPinterestAccountForDisplay()
```

---

## 9. Google API transport

Create a dedicated transport function, for example:

```php
googleBusinessProfileApiRequest(...)
```

It must:

- attach `Authorization: Bearer <accessToken>`
- use JSON where required
- handle HTTP status codes
- parse JSON responses
- return structured errors
- never expose tokens in errors/logs
- support token refresh when appropriate
- keep vendor-specific HTTP details isolated from API endpoints

Do not place Google API HTTP code directly inside individual endpoint files.

---

## 10. Account discovery

After OAuth, discover the Google Business Profile accounts available to the authenticated Google user.

Google documents Business Profile account resources and account types.

The foundation should retrieve the accessible account list and persist enough information to allow the Modlus operator to select the correct account.

For third-party/agency use, Organization accounts are relevant because Google documents them as containers for partner-managed locations/groups.

Do not assume the OAuth Google Account has only one Business Profile account.

---

## 11. Location discovery

After account discovery, retrieve locations belonging to the selected Business Profile account.

The UI must allow the operator to select a location.

The browser's submitted location ID/name must never be trusted by itself.

Before saving:

1. Verify the connected Google account belongs to the selected Modlus client.
2. Re-query Google using the authenticated token.
3. Confirm the selected account is actually accessible.
4. Confirm the selected location belongs to that account.
5. Save the server-verified location ID/name/data.

This mirrors Pinterest board server-side verification.

---

## 12. Location selection model

The foundation should support one selected Google Business Profile location per connected Modlus client initially.

Do not prematurely build bulk location management.

The data model should, however, retain the Google account ID and location ID so future phases can support multiple locations without redesigning OAuth storage.

---

## 13. Proposed files

### New

```text
includes/GoogleBusinessProfileAutomation.php

api/googleBusinessProfileOauthStart.php
api/googleBusinessProfileOauthCallback.php

api/getGoogleBusinessProfileSettings.php
api/saveGoogleBusinessProfileSettings.php

api/getGoogleBusinessProfileAccounts.php
api/getGoogleBusinessProfileLocations.php
api/saveGoogleBusinessProfileLocation.php

api/disconnectGoogleBusinessProfileAccount.php

database/migrations/2026-09-01-google-business-profile-integration-foundation.sql
```

The exact number of endpoints may be reduced if existing endpoint conventions allow safe consolidation, but keep responsibilities clear.

---

## 14. Existing page modification

Modify only:

```text
pages/instagram-automation.php
```

Additive-only.

Do not delete or rewrite existing Instagram/LinkedIn/Pinterest cards.

Add:

### Google Business Profile API Configuration

Fields:

- Google Client ID
- Google Client Secret
- Redirect URL
- Save Settings

### Google Business Profile Connection

Show:

- selected Modlus client
- connection status
- Google account
- Business Profile account
- selected location
- Connect
- Disconnect
- location selection/discovery when connected

Reuse:

- existing client selector
- CSRF token
- toast behavior
- existing card/layout conventions

---

## 15. Redirect URI

For local development, the callback can be:

```text
http://localhost/modlus-repo/modlus/api/googleBusinessProfileOauthCallback.php
```

For production, use the deployed HTTPS URL, for example:

```text
https://modlus.in/api/googleBusinessProfileOauthCallback.php
```

The final URI must exactly match the authorized redirect URI configured in Google Cloud.

Do not hardcode the production URL into the implementation if the existing architecture stores the redirect URL in settings.

---

## 16. Google Cloud prerequisites

Live validation will eventually require:

1. Google Account
2. Google Business Profile
3. Qualifying verified/active GBP for 60+ days
4. Business website listed on the GBP
5. Google Cloud project
6. Google Business Profile API access request
7. Approval
8. Business Profile APIs enabled
9. OAuth consent configuration
10. OAuth web client credentials
11. Authorized redirect URI
12. Live OAuth test

Google currently states there is **no sandbox environment** for Business Profile API calls. `validateOnly` can be used for supported requests that provide it, but it is not a substitute for full live integration testing.

Therefore the foundation must be designed so local DB/security tests do not require live Google API calls.

---

## 17. API access approval dependency

Do not mark the Google integration as live or verified until all of the following succeed against real Google infrastructure:

- OAuth authorization
- authorization-code exchange
- refresh-token flow
- Business Profile account discovery
- location discovery
- location selection
- server-side location re-verification
- reconnect
- disconnect

Until then documentation must state:

```text
CODE IMPLEMENTED — LIVE GOOGLE BUSINESS PROFILE VALIDATION PENDING
```

---

## 18. Future phases — explicitly out of scope

Do NOT implement these in Phase 14:

- Google Business Profile posts
- post publishing
- post scheduling
- review listing
- review replies
- review analytics
- performance/insights dashboards
- media/photo management
- Q&A
- notifications/webhooks
- `SocialPostEngine.php` integration
- automated posting

These should become separate phases after the foundation is live-verified.

Google's Business Profile APIs support posts, reviews, photos, location data, and performance functionality, but those capabilities should be added incrementally after OAuth and location management are proven.

---

## 19. Security and isolation tests

Required local tests:

1. Settings table self-heal.
2. Accounts table self-heal.
3. Settings save/get round-trip.
4. Client secret never returned by settings endpoint.
5. Account insert.
6. Reconnect/upsert behavior.
7. Access token encryption.
8. Refresh token encryption.
9. Display helper never returns either token.
10. OAuth state validation.
11. Ownership guard true for owning client.
12. Ownership guard false for non-owning client.
13. Location selection cannot be saved for another client's account.
14. Disconnect clears tokens.
15. Disconnect changes status appropriately.
16. No plaintext test credentials in logs.
17. No plaintext credentials in API response payloads.
18. PHP lint on every created/modified PHP file.

If only one `clientMaster` row exists locally, do not manufacture test data. Mark the cross-client test as SKIPPED, matching the established LinkedIn/Pinterest testing convention.

---

## 20. Token refresh behavior

Google OAuth must support offline access.

Implement a refresh helper such as:

```php
refreshGoogleBusinessProfileAccessToken(...)
```

When Google returns a new refresh token, store it.

Do not assume the old refresh token remains unchanged.

Persist the new access token and expiry.

Refresh failures must produce safe, user-readable errors without exposing credentials.

---

## 21. Documentation requirements

Update:

```text
docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md
```

Add:

```text
§27 — Phase 14 — Google Business Profile Integration Foundation
```

Include:

- architecture
- OAuth
- scope
- account/location hierarchy
- database
- security
- multi-client isolation
- tests
- skipped tests
- live status
- external Google prerequisites
- future publishing/review/analytics phases

Update roadmap §24:

```text
Pinterest — Foundation complete, Trial Access pending
LinkedIn — Foundation complete, live validation paused
Google Business Profile — Foundation current phase, live validation pending Google prerequisites
```

Do not remove or rewrite historical LinkedIn/Pinterest entries.

---

## 22. Implementation constraints

Strictly follow these:

- No `companyId`.
- Use `clientId`.
- No changes to Instagram implementation.
- No changes to Instagram Comments implementation.
- No changes to Facebook implementation.
- No changes to LinkedIn implementation.
- No changes to Pinterest implementation.
- No changes to `SocialPostEngine.php`.
- No new routing migration unless genuinely required by the existing architecture.
- Use existing `auth.php`.
- Use existing `db.php`.
- Use existing `Crypto.php`.
- Use existing `Csrf.php`.
- Use existing activity logging.
- Keep all changes additive and reversible.
- Do not claim live Google verification.
- Do not manufacture Google credentials.
- Do not create fake API responses that could be mistaken for live validation.

---

## 23. Exact implementation sequence

### Step 1
Inspect:

```text
includes/LinkedInAutomation.php
includes/PinterestAutomation.php
api/linkedinOauthStart.php
api/linkedinOauthCallback.php
api/getLinkedinSettings.php
api/saveLinkedinSettings.php
api/getLinkedinOrganizations.php
api/saveLinkedinOrganization.php
api/disconnectLinkedinAccount.php
api/getPinterestSettings.php
api/savePinterestSettings.php
api/getPinterestBoards.php
api/savePinterestBoard.php
api/disconnectPinterestAccount.php
pages/instagram-automation.php
includes/Crypto.php
includes/Csrf.php
includes/leadActivityLogger.php
docs/INSTAGRAM_AUTOMATION_PRODUCTION_STATE.md
```

Do not assume the current file structure; inspect before writing.

### Step 2
Implement:

```text
includes/GoogleBusinessProfileAutomation.php
```

Mirror the structure of LinkedIn/Pinterest where applicable, but adapt to Google's account → location model.

### Step 3
Implement OAuth start/callback.

### Step 4
Implement settings/account/location APIs.

### Step 5
Create migration.

### Step 6
Add additive UI cards.

### Step 7
Run `php -l` on all touched/created PHP files.

### Step 8
Run local DB/security tests.

### Step 9
Grep logs and response payloads for:

```text
accessToken
refreshToken
clientSecret
authorization_code
code=
```

and verify no credentials leak.

### Step 10
Update project documentation.

### Step 11
Stop.

Do not attempt live Google API calls unless real Google credentials and approved Business Profile API access are available.

---

## 24. Final implementation report format

After implementation, report:

- Files created
- Files modified
- Database changes
- OAuth flow
- Google scope
- Account/location discovery
- Token security
- Client isolation
- PHP lint results
- DB test results
- Security/leakage test results
- Skipped tests and reasons
- Live-testing status
- Exact Google prerequisites still pending
- Documentation updates
- Confirmation that publishing/reviews/analytics/SocialPostEngine were NOT implemented

---

## 25. Current source-of-truth external references

Use Google's current official documentation as the authority:

- Business Profile API prerequisites
- Business Profile API overview
- Basic setup
- OAuth implementation
- OAuth setup
- Accounts
- Locations
- API reference

Official documentation:

https://developers.google.com/my-business/content/prereqs
https://developers.google.com/my-business/content/overview
https://developers.google.com/my-business/content/basic-setup
https://developers.google.com/my-business/content/implement-oauth
https://developers.google.com/my-business/content/accounts
https://developers.google.com/my-business/content/locations
https://developers.google.com/my-business/ref_overview

Important current facts verified from Google's documentation on 2026-09-01:

- GBP API access requires approval.
- Applicant must manage a verified and active GBP for 60+ days.
- The qualifying GBP may belong to the applicant or a client they manage.
- The GBP must have a website representing the business.
- Third-party partners should use an Organization account.
- OAuth 2.0 is required.
- `https://www.googleapis.com/auth/business.manage` is the current supported scope.
- `plus.business.manage` is deprecated.
- Google does not provide a sandbox for GBP API calls.
- Google Cloud API access must be approved before the Business Profile APIs become available.
- Google provides separate APIs/resources for accounts, locations, reviews, posts, media, performance, and other capabilities.

---

## 26. STOP CONDITION

After the foundation implementation and local tests are complete, STOP.

Do not continue into Google Business Profile publishing.

The expected state is:

```text
Google Business Profile Foundation
        ↓
CODE IMPLEMENTED
        ↓
LOCAL TESTS PASS
        ↓
LIVE GOOGLE API ACCESS PENDING
        ↓
Verified + active GBP 60+ days
        ↓
Google API access approval
        ↓
LIVE OAUTH VALIDATION
        ↓
LIVE ACCOUNT/LOCATION VALIDATION
        ↓
Future Phase: Publishing / Reviews / Analytics
```

This document is intended to be handed to Claude/Codex as the implementation source of truth so the architecture, scope, security model, constraints, and stopping boundary do not need to be re-explained in a future conversation.
