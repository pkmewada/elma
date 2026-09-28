<?php
/*
|--------------------------------------------------------------------------
| Lead API access rules + CRM lead model helpers
|--------------------------------------------------------------------------
| Thin layer over includes/permission-helper.php (no second authorization
| system): route permissions come from rolePermissions /
| userPermissionOverrides, special actions from permissionActions.
|
|   Admin (users)                         -> every lead, may assign.
|   Employee with 'view-all-leads'        -> every lead.
|   Employee with 'assign_lead'           -> may assign / reassign.
|   Any other employee                    -> only leads assigned to them
|                                            (leads.assignedToId).
|
| A lead outside the caller's scope is reported as "not found". CSRF for
| every non-GET API request is enforced centrally by api-gateway.php.
*/

require_once __DIR__ . '/permission-helper.php';
require_once __DIR__ . '/privateFiles.php';
require_once __DIR__ . '/leadActivityLogger.php';
require_once __DIR__ . '/leadFollowUpEngine.php';

const LEAD_ADMIN_ROUTE = '/leads';
const LEAD_EMPLOYEE_ROUTE = '/emp-leads';

/** Approved real-estate pipeline (system-defined), in display order. */
const LEAD_STATUSES = [
    'new' => 'New',
    'contacted' => 'Contacted',
    'interested' => 'Interested',
    'follow_up' => 'Follow-up',
    'site_visit' => 'Site Visit',
    'negotiation' => 'Negotiation',
    'converted' => 'Converted',
    'lost' => 'Lost',
];

/** Statuses that close a lead and need a remark / reason. */
const LEAD_CLOSING_STATUSES = ['converted', 'lost'];

/** Employee roles (designationName) a lead can be assigned to. */
const LEAD_ASSIGNABLE_ROLES = ['Sales Executive', 'Sales Manager'];

function leadJsonExit(int $statusCode, string $message, array $data = []): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message, 'data' => $data]);
    exit;
}

/** JSON 405 unless POST; JSON 413 when the body exceeded post_max_size. */
function requireLeadPost(): void
{
    if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        leadJsonExit(405, 'Method not allowed.');
    }

    if (isRequestBodyTooLarge()) {
        leadJsonExit(413, 'Upload is too large. Maximum file size is ' . formatPrivateFileLimit() . '.');
    }
}

/** Login + route permission on the caller's own lead page. */
function requireLeadPermission(string $action = 'canView'): void
{
    requireApiPermission(
        getLoggedInUserType() === 'employee' ? LEAD_EMPLOYEE_ROUTE : LEAD_ADMIN_ROUTE,
        $action
    );
}

function leadCallerRoute(): string
{
    return getLoggedInUserType() === 'employee' ? LEAD_EMPLOYEE_ROUTE : LEAD_ADMIN_ROUTE;
}

function canAccessAllLeads(): bool
{
    if (getLoggedInUserType() === 'admin') {
        return true;
    }

    return getLoggedInUserType() === 'employee'
        && hasActionPermission(LEAD_EMPLOYEE_ROUTE, 'view-all-leads');
}

function canAssignLeads(): bool
{
    if (getLoggedInUserType() === 'admin') {
        return true;
    }

    return getLoggedInUserType() === 'employee'
        && hasRoutePermission(LEAD_EMPLOYEE_ROUTE, 'canEdit')
        && hasActionPermission(LEAD_EMPLOYEE_ROUTE, 'assign_lead');
}

/** 0 = no restriction, otherwise the employee id the caller is limited to. */
function getLeadScopeEmployeeId(): int
{
    return canAccessAllLeads() ? 0 : (int)($_SESSION['candidateId'] ?? -1);
}

/** Loads the lead and aborts with 404 when missing or outside the caller's scope. */
function requireLeadAccess(mysqli $con, int $leadId): array
{
    if ($leadId <= 0) {
        leadJsonExit(422, 'Invalid lead ID.');
    }

    $stmt = mysqli_prepare(
        $con,
        'SELECT l.id, l.fullName, l.phone, l.countryCode, l.status, l.projectId, l.sourceId, l.assignedToId,
                p.projectName, eu.fullName AS assignedToName
         FROM leads l
         LEFT JOIN projects p ON p.id = l.projectId
         LEFT JOIN employeeusers eu ON eu.id = l.assignedToId
         WHERE l.id = ? LIMIT 1'
    );
    mysqli_stmt_bind_param($stmt, 'i', $leadId);
    mysqli_stmt_execute($stmt);
    $lead = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    $scopeEmployeeId = getLeadScopeEmployeeId();

    if (!$lead || ($scopeEmployeeId !== 0 && (int)($lead['assignedToId'] ?? 0) !== $scopeEmployeeId)) {
        leadJsonExit(404, 'Lead not found.');
    }

    return $lead;
}

/** Active employees in a sales role, for assignment dropdowns. */
function getAssignableEmployees(mysqli $con): array
{
    $placeholders = implode(',', array_fill(0, count(LEAD_ASSIGNABLE_ROLES), '?'));
    $stmt = mysqli_prepare(
        $con,
        "SELECT id, fullName, designationName FROM employeeusers
         WHERE accountStatus = 'Active' AND (employmentStatus = 'Active' OR employmentStatus IS NULL)
         AND designationName IN ($placeholders)
         ORDER BY fullName ASC"
    );
    $roles = LEAD_ASSIGNABLE_ROLES;
    mysqli_stmt_bind_param($stmt, str_repeat('s', count($roles)), ...$roles);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $employees = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $employees[] = ['id' => (int)$row['id'], 'fullName' => $row['fullName'], 'role' => $row['designationName']];
    }
    mysqli_stmt_close($stmt);

    return $employees;
}

/** Validated assignee (active sales employee) or null for "unassigned". */
function resolveAssignee(mysqli $con, int $employeeId): ?array
{
    if ($employeeId <= 0) {
        return null;
    }

    foreach (getAssignableEmployees($con) as $employee) {
        if ($employee['id'] === $employeeId) {
            return $employee;
        }
    }

    leadJsonExit(422, 'Selected salesperson is not an active sales employee.');
}

/**
 * Assignee for a new lead: employees without assign permission always get
 * the lead themselves; assigners may pick any sales employee or leave it
 * unassigned.
 */
function resolveNewLeadAssignee(mysqli $con, int $requestedEmployeeId): ?array
{
    if (!canAssignLeads()) {
        return getLoggedInUserType() === 'employee'
            ? ['id' => (int)$_SESSION['candidateId'], 'fullName' => (string)($_SESSION['candidateName'] ?? '')]
            : null;
    }

    return resolveAssignee($con, $requestedEmployeeId);
}

/** Active project (id + name) or null when none selected. */
function resolveLeadProject(mysqli $con, int $projectId, bool $allowInactiveId = false, int $currentProjectId = 0): ?array
{
    if ($projectId <= 0) {
        return null;
    }

    $stmt = mysqli_prepare($con, 'SELECT id, projectName, isActive FROM projects WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $projectId);
    mysqli_stmt_execute($stmt);
    $project = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    // An inactive project may stay on a lead that already had it, but cannot be newly chosen.
    if (!$project || ((int)$project['isActive'] !== 1 && !($allowInactiveId && $projectId === $currentProjectId))) {
        leadJsonExit(422, 'Select an active project.');
    }

    return ['id' => (int)$project['id'], 'projectName' => $project['projectName']];
}

function resolveLeadSource(mysqli $con, int $sourceId): array
{
    $stmt = mysqli_prepare($con, 'SELECT id, sourceKey, sourceName FROM leadSources WHERE id = ? AND isActive = 1 LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $sourceId);
    mysqli_stmt_execute($stmt);
    $source = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$source) {
        leadJsonExit(422, 'Select a valid lead source.');
    }

    return ['id' => (int)$source['id'], 'sourceKey' => $source['sourceKey'], 'sourceName' => $source['sourceName']];
}

/** Normalises "YYYY-MM-DDTHH:MM" / "YYYY-MM-DD HH:MM[:SS]" into [date, time] or aborts. */
function parseFollowUpDateTime(string $value): ?array
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $parsed = DateTime::createFromFormat('Y-m-d\TH:i', $value)
        ?: DateTime::createFromFormat('Y-m-d H:i:s', $value)
        ?: DateTime::createFromFormat('Y-m-d H:i', $value);

    if (!$parsed) {
        leadJsonExit(422, 'Invalid follow-up date/time.');
    }

    return [$parsed->format('Y-m-d'), $parsed->format('H:i:s')];
}

/** Manual follow-up in the single follow-up table (leadFollowUps). */
function createManualFollowUp(mysqli $con, int $leadId, string $dueDate, string $dueTime, string $remark, string $type = 'Call'): int
{
    $actor = getCurrentActor();
    $remarkValue = $remark !== '' ? $remark : null;

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO leadFollowUps (leadId, settingId, followUpSequence, followUpType, dueDate, dueTime, status, remark, createdByType, createdById)
         VALUES (?, NULL, NULL, ?, ?, ?, 'Pending', ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'isssssi', $leadId, $type, $dueDate, $dueTime, $remarkValue, $actor['type'], $actor['id']);
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    saveActivityLog($con, 'Lead', $leadId, 'FOLLOWUP_ADDED', 'Follow-up scheduled for ' . date('d M Y h:i A', strtotime($dueDate . ' ' . $dueTime)), null, [
        'followUpId' => $id, 'dueDate' => $dueDate, 'dueTime' => $dueTime, 'remark' => $remarkValue,
    ]);

    return $id;
}

/** Remark in leadRemarks with the actor type (never inferred from the id). */
function createLeadRemark(mysqli $con, int $leadId, string $remark): int
{
    $actor = getCurrentActor();

    $stmt = mysqli_prepare($con, 'INSERT INTO leadRemarks (leadId, remark, createdByCandidateId, createdByType) VALUES (?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isis', $leadId, $remark, $actor['id'], $actor['type']);
    mysqli_stmt_execute($stmt);
    $id = (int)mysqli_insert_id($con);
    mysqli_stmt_close($stmt);

    return $id;
}

/*
|--------------------------------------------------------------------------
| Shared lead creation (manual entry + every external integration)
|--------------------------------------------------------------------------
| ONE path for the actual INSERT + activity trail + follow-up rule
| generation. api/leads/addLead.php (manual entry) and
| createLeadFromSource() (Meta/Google/Website -- see
| includes/integrationAccess.php) both call createLeadCore(); neither
| duplicates this logic. $actor is passed explicitly (not read from the
| session) so integration calls can pass ['type' => 'system', 'id' => 0].
*/

/**
 * @param array $lead fullName, email(?), phone, country, countryCode,
 *   projectId(?), projectName(?), sourceId, sourceName, assignedToId(?),
 *   assigneeName(?), status, remark, followUp(?) = [date, time]
 * @throws Throwable on failure (caller decides the response)
 */
function createLeadCore(mysqli $con, array $lead, array $actor): int
{
    $createdByCandidateId = $actor['type'] === 'employee' ? $actor['id'] : null;
    $emailValue = ($lead['email'] ?? '') !== '' ? $lead['email'] : null;
    $projectId = $lead['projectId'] ?? null;
    $assignedToId = $lead['assignedToId'] ?? null;
    $remark = trim((string)($lead['remark'] ?? ''));

    mysqli_begin_transaction($con);

    try {
        $stmt = mysqli_prepare(
            $con,
            'INSERT INTO leads (fullName, email, phone, country, countryCode, projectId, sourceId, assignedToId, status, createdByCandidateId, createdByType, externalSource, externalLeadId)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        mysqli_stmt_bind_param(
            $stmt,
            'sssssiiisisss',
            $lead['fullName'], $emailValue, $lead['phone'], $lead['country'], $lead['countryCode'],
            $projectId, $lead['sourceId'], $assignedToId, $lead['status'], $createdByCandidateId, $actor['type'],
            $lead['externalSource'], $lead['externalLeadId']
        );

        if (!mysqli_stmt_execute($stmt)) {
            throw new RuntimeException('Insert failed: ' . mysqli_stmt_error($stmt));
        }

        $leadId = (int)mysqli_insert_id($con);
        mysqli_stmt_close($stmt);

        saveActivityLog($con, 'Lead', $leadId, 'CREATE', ($lead['createLabel'] ?? 'New lead created') . ' : ' . $lead['fullName'], null, [
            'fullName' => $lead['fullName'], 'phone' => $lead['countryCode'] . ' ' . $lead['phone'], 'email' => $emailValue,
            'project' => $lead['projectName'] ?? null, 'source' => $lead['sourceName'],
            'status' => LEAD_STATUSES[$lead['status']] ?? $lead['status'], 'assignedTo' => $lead['assigneeName'] ?? 'Unassigned',
        ]);

        if ($assignedToId) {
            saveActivityLog($con, 'Lead', $leadId, 'ASSIGN', 'Lead assigned to ' . ($lead['assigneeName'] ?? ''), null, ['assignedToId' => $assignedToId]);
        }

        if ($remark !== '') {
            createLeadRemark($con, $leadId, $remark);
            saveActivityLog($con, 'Lead', $leadId, 'REMARK', 'Remark added : ' . $lead['fullName'], null, ['remark' => $remark]);
        }

        if (!empty($lead['followUp'])) {
            createManualFollowUp($con, $leadId, $lead['followUp'][0], $lead['followUp'][1], $remark);
        }

        (new LeadFollowUpEngine($con))->generateForLead($leadId, date('Y-m-d H:i:s'));

        mysqli_commit($con);
    } catch (Throwable $e) {
        mysqli_rollback($con);
        throw $e;
    }

    return $leadId;
}

/**
 * External lead capture (Meta / Google / Website) -- the ONLY entry point
 * those integrations use. Idempotent: a duplicate (provider, externalId)
 * retry returns the original lead instead of creating a second one, so
 * provider webhook retries are always safe (see includes/leadAccess.php's
 * uq_leads_external unique key). A phone-number collision with an existing
 * lead from any channel is treated the same way -- the existing lead is
 * reused, never silently duplicated -- because this CRM's normal manual
 * duplicate rule (api/leads/addLead.php) already keys leads by phone; an
 * unattended integration must respect that same rule rather than create a
 * second lead the provider's own dedupe (only source+externalId) wouldn't
 * catch, e.g. the same person filling a Meta and a Google form.
 *
 * $lead: fullName, phone, country, countryCode, email(?), sourceId,
 *   projectId(?), assignedToId(?), remark(?), createLabel (activity text,
 *   e.g. "Lead created from Meta Lead Ads").
 *
 * @return array{leadId:int, duplicate:bool, reason:?string}
 */
function createLeadFromSource(mysqli $con, string $externalSource, ?string $externalLeadId, array $lead): array
{
    if ($externalLeadId !== null && $externalLeadId !== '') {
        $stmt = mysqli_prepare($con, 'SELECT id FROM leads WHERE externalSource = ? AND externalLeadId = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ss', $externalSource, $externalLeadId);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existing) {
            return ['leadId' => (int)$existing['id'], 'duplicate' => true, 'reason' => 'Already processed (external id)'];
        }
    }

    if ((string)($lead['phone'] ?? '') !== '') {
        $stmt = mysqli_prepare($con, 'SELECT id FROM leads WHERE phone = ? AND countryCode = ? LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'ss', $lead['phone'], $lead['countryCode']);
        mysqli_stmt_execute($stmt);
        $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if ($existing) {
            return ['leadId' => (int)$existing['id'], 'duplicate' => true, 'reason' => 'Phone already exists on lead #' . $existing['id']];
        }
    }

    $lead['status'] = 'new';
    $lead['externalSource'] = $externalSource;
    $lead['externalLeadId'] = $externalLeadId !== '' ? $externalLeadId : null;

    $leadId = createLeadCore($con, $lead, ['type' => 'system', 'id' => 0]);

    return ['leadId' => $leadId, 'duplicate' => false, 'reason' => null];
}

/*
|--------------------------------------------------------------------------
| Private lead files (documents)
|--------------------------------------------------------------------------
*/

function getLeadFileDirectory(int $leadId): string
{
    return getPrivateStorageRoot() . '/lead-documents/' . $leadId;
}

function storeLeadFile(array $file, int $leadId): string
{
    return storePrivateFile($file, 'lead-documents/' . $leadId, PRIVATE_FILE_TYPES_PDF, 'leadJsonExit')['fileName'];
}
