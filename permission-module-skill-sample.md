---
name: modlus-permission-module
description: Use when planning, reviewing, or implementing the Modlus permission module. Keeps the project permission flow consistent, simple, role-first, and compatible with the existing PHP/MySQL codebase.
---

# Modlus Permission Module Skill

Use this skill whenever working on the Modlus permission module, route access, role permissions, employee-specific permissions, menu visibility, button access, or API protection.

## Core Rule

Do not over-engineer the permission module.

The project permission model must stay understandable for a non-technical admin:

1. Role permission is the default access.
2. Employee custom permission is only an exception.
3. If an employee has a custom permission row for a route, that row is final.
4. If no employee custom row exists, use the employee role/designation permission.
5. If no permission exists, deny by default.

Avoid grant/revoke wording in the UI. Use:

- Role Default
- Custom Permission

## Actions

Supported permission actions for the planned module:

- View
- Add
- Edit
- Delete
- Approve
- Special Actions

Do not add Export as a permission action. Export buttons in this project are client-side table utilities and should not be part of the permission model unless Varun explicitly changes this decision later.

## Existing Tables To Preserve

Keep and reuse:

- routesMaster
- rolePermissions
- userPermissionOverrides

Do not replace the routing system unless explicitly requested.

Current route access is driven by:

- routes.php
- includes/permission-helper.php
- routesMaster.routePath
- rolePermissions
- userPermissionOverrides

## Recommended Future Table For Special Buttons

For special buttons such as Import Leads, Send Agreement, Assign Asset, Verify Candidate, or Download Template, add a small action-level permission table instead of adding more columns to rolePermissions.

Recommended concept:

```sql
permissionActions
id
routeId
actionKey
actionLabel
isActive
sortOrder
```

Examples:

```text
/leads -> import -> Import Leads
/onboarding-queue -> verify -> Verify Candidate
/assets-management -> assign -> Assign Asset
/candidate-record -> send_agreement -> Send Agreement
```

Then store action grants in simple role/user action permission tables, or a compact compatible structure decided during implementation.

## Important Project Decision

Keep page-level permission separate from button/action permission.

Page-level examples:

```text
/leads -> View
/employee-directory -> View
/expense-management -> View
```

Button/action examples:

```text
/leads -> Import Leads
/assets-management -> Assign Asset
/expense-management -> Approve Expense
```

## UI Flow

Permission screen should be module-first, not a giant table.

Recommended layout:

```text
Permission Module

Tabs:
- Role Permissions
- Employee Exceptions

Role Permissions:
Select Role -> Select Module -> Set Page and Action access

Employee Exceptions:
Select Employee -> Show Role Default -> Enable Custom Permission only if needed
```

Show modules such as:

- Dashboard
- Lead Management
- HRMS
- Payroll
- Setup
- Employee Panel

Do not show all 60+ routes at once unless the user intentionally opens an advanced view.

## Sidebar Rule

Sidebars must eventually be permission-aware.

If a route has no View permission, do not show it in the menu.

The future sidebar should use routesMaster.isMenuVisible, routesMaster.layoutType, moduleName, sortOrder, and hasRoutePermission(routePath, 'canView').

## API Rule

Every protected API endpoint must eventually have an explicit permission check.

Examples:

```php
requireRoutePermission('/leads', 'canAdd');      // add lead
requireRoutePermission('/leads', 'canEdit');     // update lead
requireRoutePermission('/leads', 'canDelete');   // delete lead
requireActionPermission('/leads', 'import');     // import leads
```

Never rely only on hiding buttons. API endpoints must enforce the same permission.

## Current Project Issues To Remember

- routes.php already checks canView centrally.
- includes/sidebar.php and includes/emp-sidebar.php are hard-coded and do not currently hide unauthorized routes.
- Many API endpoints use auth.php or emp-auth.php but do not use permission-helper.php.
- Some APIs use manual session checks instead of shared auth helpers.
- userPermissionOverrides currently has overrideType grant/revoke, but the desired UI rule is simpler: custom row wins exactly.
- rolePermissions and userPermissionOverrides include canExport, but Export should not be used in the future permission UI.
- routesMaster has at least one active route with isPublic NULL and one old .php-style route; clean route data before relying on it for menus.

## Implementation Order

When implementation is requested, use this order:

1. Clean permission resolution in permission-helper.php without changing unrelated logic.
2. Hide canExport from permission UI and ignore it in new checks.
3. Make employee custom permission row final when present.
4. Create module-first permission UI for role defaults.
5. Create employee exception UI that shows inherited role defaults.
6. Make sidebars permission-aware.
7. Add API-level permission checks module by module.
8. Add special action permission only for buttons that do not fit View/Add/Edit/Delete/Approve.

## Safety Rules

- Do not rewrite the whole project.
- Do not remove existing tables without migration approval.
- Do not change live behavior casually.
- Keep changes incremental and test each module.
- Prefer prepared statements.
- Return JSON from AJAX/API endpoints.
- Keep local and live URLs compatible with BASE_URL.

