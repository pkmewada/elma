<?php
/*
|--------------------------------------------------------------------------
| Project portfolio access (thin layer over permission-helper.php)
|--------------------------------------------------------------------------
| Admin page /projects: canView/canAdd/canEdit/canDelete as configured
| (admins: full). Employee page /emp-projects: read-only, active projects
| only (Sales Manager + Sales Executive get canView by default).
| Project documents are private files served by downloadProjectDocument.php.
*/
require_once __DIR__ . '/permission-helper.php';
require_once __DIR__ . '/privateFiles.php';
require_once __DIR__ . '/leadActivityLogger.php';

const PROJECT_ADMIN_ROUTE = '/projects';
const PROJECT_EMPLOYEE_ROUTE = '/emp-projects';

const PROJECT_PROPERTY_TYPES = ['Residential', 'Commercial', 'Plot / Land', 'Villa / Row House', 'Mixed Use'];

const PROJECT_DOCUMENT_TYPES = [
    'image' => 'Project Image',
    'brochure' => 'Brochure',
    'floor_plan' => 'Floor Plan',
    'price_list' => 'Price List',
    'other' => 'Other Document',
];

function projectJsonExit(int $statusCode, string $message, array $data = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message, 'data' => $data]);
    exit;
}

function requireProjectPost(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        projectJsonExit(405, 'Method not allowed.');
    }

    if (isRequestBodyTooLarge()) {
        projectJsonExit(413, 'Upload is too large. Maximum file size is ' . formatPrivateFileLimit() . '.');
    }
}

/** Read access: admin page or the employee page (layout-appropriate). */
function requireProjectView(): void
{
    requireApiPermission(
        getLoggedInUserType() === 'employee' ? PROJECT_EMPLOYEE_ROUTE : PROJECT_ADMIN_ROUTE,
        'canView'
    );
}

/** Management actions exist only on the admin page's permissions. */
function requireProjectManage(string $action): void
{
    requireApiPermission(PROJECT_ADMIN_ROUTE, $action);
}

/** Employees only ever see active projects. */
function projectsActiveOnly(): bool
{
    return getLoggedInUserType() !== 'admin' && !hasRoutePermission(PROJECT_ADMIN_ROUTE, 'canView');
}

function requireProject(mysqli $con, int $projectId): array
{
    if ($projectId <= 0) {
        projectJsonExit(422, 'Invalid project.');
    }

    $stmt = mysqli_prepare($con, 'SELECT * FROM projects WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $projectId);
    mysqli_stmt_execute($stmt);
    $project = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$project || (projectsActiveOnly() && (int)$project['isActive'] !== 1)) {
        projectJsonExit(404, 'Project not found.');
    }

    return $project;
}

function projectDocumentDirectory(int $projectId): string
{
    return 'project-documents/' . $projectId;
}
