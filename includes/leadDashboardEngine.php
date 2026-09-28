<?php

/*
|--------------------------------------------------------------------------
| Lead Dashboard Engine
|--------------------------------------------------------------------------
|
| Read-only analytics for the CRM dashboard (pages/dashboard.php,
| pages/lead-dashboard.php, employee/emp-lead-dashboard.php, and the compact
| widget on employee/emp-dashboard.php), computed entirely from existing
| tables -- leads, leadFollowUps, leadSources, projects, employeeusers and
| leadsActivityLogs. No new tables, no separate aggregation store.
|
| Filter scoping (deliberate, see getDashboardData()):
| - employeeId / source / projectId narrow every metric below.
| - dateFrom/dateTo scope "activity in a period" metrics: New Leads, the
|   Lead Trend chart, both Follow Up counts/charts and per-employee
|   follow-up counts (all bucketed on their own date: leads.createdAt for
|   leads, leadFollowUps.dueDate for follow ups).
| - Total/Converted/Lost/Site Visit/Unassigned Leads, Today's/Overdue
|   Follow-ups, Status/Source/Project distributions and each employee's
|   "Assigned Leads" count stay lifetime totals ("all leads") under the
|   employee/source/project scope -- they represent the current pipeline,
|   not a windowed count. Today's/Overdue follow-ups additionally exclude
|   Converted/Lost leads, same rule as includes/leadFollowUpEngine.php's
|   "today"/"overdue" views, since those are no longer actionable.
| - Each "by X" breakdown (bySource/byProject/byEmployee) omits its own
|   dimension from the filter it applies (e.g. bySource ignores the Source
|   filter) so selecting a single source doesn't collapse its own chart to
|   one bar; the other filters still narrow it.
| - The Recent Follow Up table is a live activity feed (latest updated
|   rows), scoped by employee/source/project but not by the date range,
|   same as any "recent activity" widget.
|
*/

class LeadDashboardEngine
{
    private $con;

    public function __construct($con)
    {
        $this->con = $con;
    }

    /*
    |--------------------------------------------------------------------------
    | Lookups (for filter dropdowns + the status chart's labels)
    |--------------------------------------------------------------------------
    */

    /**
     * Every value leads.status can hold, read straight from the column
     * definition -- not a hand-maintained list, so a future ALTER TABLE
     * that adds/renames a status is picked up automatically.
     */
    public function getStatusValues()
    {
        $result = mysqli_query(
            $this->con,
            "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'leads'
            AND COLUMN_NAME = 'status'"
        );
        $row = mysqli_fetch_assoc($result);
        $columnType = $row['COLUMN_TYPE'] ?? '';

        if (!preg_match_all("/'([^']+)'/", $columnType, $matches)) {
            return [];
        }

        return $matches[1];
    }

    public function getSources()
    {
        $result = mysqli_query(
            $this->con,
            "SELECT sourceName AS source FROM leadSources
            WHERE isActive = 1
            ORDER BY sortOrder ASC, sourceName ASC"
        );

        $sources = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $sources[] = $row['source'];
        }

        return $sources;
    }

    /** Active projects, for the dashboard's Project filter. */
    public function getProjects()
    {
        $result = mysqli_query(
            $this->con,
            "SELECT id, projectName FROM projects WHERE isActive = 1 ORDER BY projectName ASC"
        );

        $projects = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $projects[] = ['id' => (int)$row['id'], 'projectName' => $row['projectName']];
        }

        return $projects;
    }

    /**
     * Only employees who have leads assigned (leads.assignedToId) --
     * the same assignment relation every existing Lead Management screen
     * uses, not the full HR employee directory.
     */
    public function getAssignedEmployees()
    {
        $result = mysqli_query(
            $this->con,
            "SELECT DISTINCT eu.id, eu.fullName
            FROM employeeusers eu
            INNER JOIN leads l ON l.assignedToId = eu.id
            ORDER BY eu.fullName ASC"
        );

        $employees = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $employees[] = ['id' => (int)$row['id'], 'fullName' => $row['fullName']];
        }

        return $employees;
    }

    /*
    |--------------------------------------------------------------------------
    | Dashboard payload
    |--------------------------------------------------------------------------
    */

    public function getDashboardData($filters)
    {
        $employeeId = (int)($filters['employeeId'] ?? 0);
        $source = trim((string)($filters['source'] ?? ''));
        $projectId = (int)($filters['projectId'] ?? 0);
        $dateFrom = trim((string)($filters['dateFrom'] ?? ''));
        $dateTo = trim((string)($filters['dateTo'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            throw new Exception('A valid date range is required.');
        }

        return [
            'summary' => $this->getSummary($employeeId, $source, $projectId, $dateFrom, $dateTo),
            'leadTrend' => $this->getLeadTrend($employeeId, $source, $projectId, $dateFrom, $dateTo),
            'statusDistribution' => $this->getStatusDistribution($employeeId, $source, $projectId),
            'bySource' => $this->getBySource($employeeId, $projectId),
            'byProject' => $this->getByProject($employeeId, $source),
            'followUpPerformance' => $this->getFollowUpPerformance($employeeId, $source, $projectId, $dateFrom, $dateTo),
            'employeePerformance' => $this->getEmployeePerformance($employeeId, $source, $projectId, $dateFrom, $dateTo),
            'recentFollowUps' => $this->getRecentFollowUps($employeeId, $source, $projectId),
        ];
    }

    private function getSummary($employeeId, $source, $projectId, $dateFrom, $dateTo)
    {
        $totalLeads = $this->countLeads($employeeId, $source, $projectId, null, null, null);
        $newLeads = $this->countLeads($employeeId, $source, $projectId, null, $dateFrom, $dateTo);
        $convertedLeads = $this->countLeads($employeeId, $source, $projectId, 'converted', null, null);
        $lostLeads = $this->countLeads($employeeId, $source, $projectId, 'lost', null, null);
        $siteVisits = $this->countLeads($employeeId, $source, $projectId, 'site_visit', null, null);
        $unassignedLeads = $this->countUnassignedLeads($source, $projectId);
        $pendingFollowUps = $this->countFollowUps($employeeId, $source, $projectId, 'Pending', $dateFrom, $dateTo);
        $completedFollowUps = $this->countFollowUps($employeeId, $source, $projectId, 'Completed', $dateFrom, $dateTo);
        $todayFollowUps = $this->countActiveFollowUpsByDate($employeeId, $source, $projectId, '=');
        $overdueFollowUps = $this->countActiveFollowUpsByDate($employeeId, $source, $projectId, '<');

        return [
            'totalLeads' => $totalLeads,
            'newLeads' => $newLeads,
            'todayFollowUps' => $todayFollowUps,
            'overdueFollowUps' => $overdueFollowUps,
            'siteVisits' => $siteVisits,
            'convertedLeads' => $convertedLeads,
            'lostLeads' => $lostLeads,
            'pendingFollowUps' => $pendingFollowUps,
            'completedFollowUps' => $completedFollowUps,
            'unassignedLeads' => $unassignedLeads,
            'conversionRate' => $totalLeads > 0 ? round($convertedLeads / $totalLeads * 100, 1) : 0,
        ];
    }

    private function getLeadTrend($employeeId, $source, $projectId, $dateFrom, $dateTo)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, $projectId, null, $dateFrom, $dateTo);

        $sql = "SELECT DATE(l.createdAt) AS d, COUNT(*) AS c
                FROM leads l
                WHERE $where
                GROUP BY DATE(l.createdAt)";

        $counts = [];
        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[$row['d']] = (int)$row['c'];
        }
        mysqli_stmt_close($stmt);

        // fill every day in range so the line doesn't skip gaps
        $trend = [];
        $cursor = new DateTime($dateFrom);
        $end = new DateTime($dateTo);
        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $trend[] = [
                'date' => $cursor->format('d M'),
                'count' => $counts[$key] ?? 0,
            ];
            $cursor->modify('+1 day');
        }

        return $trend;
    }

    private function getStatusDistribution($employeeId, $source, $projectId)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, $projectId, null, null, null);

        $sql = "SELECT l.status, COUNT(*) AS c
                FROM leads l
                WHERE $where
                GROUP BY l.status";

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $counts = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $counts[$row['status']] = (int)$row['c'];
        }
        mysqli_stmt_close($stmt);

        $distribution = [];
        foreach ($this->getStatusValues() as $status) {
            $distribution[] = ['status' => $status, 'count' => $counts[$status] ?? 0];
        }

        return $distribution;
    }

    /** Leads by Source -- ignores the Source filter itself (see file header). */
    private function getBySource($employeeId, $projectId)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, '', $projectId, null, null, null);

        $sql = "SELECT COALESCE(s.sourceName, 'Unknown') AS label, COUNT(*) AS c
                FROM leads l
                LEFT JOIN leadSources s ON s.id = l.sourceId
                WHERE $where
                GROUP BY label
                ORDER BY c DESC";

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = ['label' => $row['label'], 'count' => (int)$row['c']];
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    /** Leads by Project -- ignores the Project filter itself (see file header). */
    private function getByProject($employeeId, $source)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, 0, null, null, null);

        $sql = "SELECT COALESCE(p.projectName, 'No Project') AS label, COUNT(*) AS c
                FROM leads l
                LEFT JOIN projects p ON p.id = l.projectId
                WHERE $where
                GROUP BY label
                ORDER BY c DESC
                LIMIT 15";

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = ['label' => $row['label'], 'count' => (int)$row['c']];
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    private function getFollowUpPerformance($employeeId, $source, $projectId, $dateFrom, $dateTo)
    {
        return [
            'Pending' => $this->countFollowUps($employeeId, $source, $projectId, 'Pending', $dateFrom, $dateTo),
            'Completed' => $this->countFollowUps($employeeId, $source, $projectId, 'Completed', $dateFrom, $dateTo),
            'Skipped' => $this->countFollowUps($employeeId, $source, $projectId, 'Skipped', $dateFrom, $dateTo),
        ];
    }

    /** Leads by Sales Executive (+ their follow-up load in the selected range). */
    private function getEmployeePerformance($employeeId, $source, $projectId, $dateFrom, $dateTo)
    {
        [$leadWhere, $leadParams, $leadTypes] = $this->leadWhere($employeeId, $source, $projectId, null, null, null);

        $sql = "
            SELECT
                eu.id, eu.fullName,
                COUNT(DISTINCT l.id) AS assignedLeads,
                COUNT(DISTINCT CASE WHEN f.status = 'Pending' AND f.dueDate BETWEEN ? AND ? THEN f.id END) AS pendingFollowUps,
                COUNT(DISTINCT CASE WHEN f.status = 'Completed' AND f.dueDate BETWEEN ? AND ? THEN f.id END) AS completedFollowUps
            FROM leads l
            INNER JOIN employeeusers eu ON eu.id = l.assignedToId
            LEFT JOIN leadFollowUps f ON f.leadId = l.id
            WHERE $leadWhere
            GROUP BY eu.id, eu.fullName
            ORDER BY assignedLeads DESC
            LIMIT 20
        ";

        $params = array_merge([$dateFrom, $dateTo, $dateFrom, $dateTo], $leadParams);
        $types = 'ssss' . $leadTypes;

        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'employeeId' => (int)$row['id'],
                'employeeName' => $row['fullName'],
                'assignedLeads' => (int)$row['assignedLeads'],
                'pendingFollowUps' => (int)$row['pendingFollowUps'],
                'completedFollowUps' => (int)$row['completedFollowUps'],
            ];
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    private function getRecentFollowUps($employeeId, $source, $projectId)
    {
        [$leadWhere, $params, $types] = $this->leadWhere($employeeId, $source, $projectId, null, null, null);

        $sql = "
            SELECT
                f.id, f.followUpSequence, f.followUpType, f.dueDate, f.status, f.updatedAt,
                l.id AS leadId, l.fullName AS leadName,
                eu.fullName AS employeeName,
                (
                    SELECT al.description
                    FROM leadsActivityLogs al
                    WHERE al.moduleName = 'Lead' AND al.recordId = l.id
                    ORDER BY al.createdAt DESC, al.id DESC
                    LIMIT 1
                ) AS lastAction
            FROM leadFollowUps f
            INNER JOIN leads l ON l.id = f.leadId
            LEFT JOIN employeeusers eu ON eu.id = l.assignedToId
            WHERE $leadWhere
            ORDER BY f.updatedAt DESC, f.id DESC
            LIMIT 15
        ";

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = [
                'leadId' => (int)$row['leadId'],
                'leadName' => $row['leadName'],
                'employeeName' => $row['employeeName'] ?? 'Unassigned',
                'dueDate' => $row['dueDate'],
                'followUpSequence' => (int)$row['followUpSequence'],
                'followUpType' => $row['followUpType'],
                'status' => $row['status'],
                'lastAction' => $row['lastAction'] ?? '—',
            ];
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    /*
    |--------------------------------------------------------------------------
    | Shared WHERE builders + count helpers
    |--------------------------------------------------------------------------
    */

    /**
     * @return array [whereSql, params, types]
     */
    private function leadWhere($employeeId, $source, $projectId, $status, $dateFrom, $dateTo)
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        if ($employeeId > 0) {
            $where[] = 'l.assignedToId = ?';
            $params[] = $employeeId;
            $types .= 'i';
        }
        if ($source !== '') {
            $where[] = 'l.sourceId IN (SELECT id FROM leadSources WHERE sourceName = ?)';
            $params[] = $source;
            $types .= 's';
        }
        if ($projectId > 0) {
            $where[] = 'l.projectId = ?';
            $params[] = $projectId;
            $types .= 'i';
        }
        if ($status !== null) {
            $where[] = 'l.status = ?';
            $params[] = $status;
            $types .= 's';
        }
        if ($dateFrom !== null && $dateTo !== null) {
            $where[] = 'DATE(l.createdAt) BETWEEN ? AND ?';
            $params[] = $dateFrom;
            $params[] = $dateTo;
            $types .= 'ss';
        }

        return [implode(' AND ', $where), $params, $types];
    }

    private function countLeads($employeeId, $source, $projectId, $status, $dateFrom, $dateTo)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, $projectId, $status, $dateFrom, $dateTo);

        $stmt = mysqli_prepare($this->con, "SELECT COUNT(*) AS c FROM leads l WHERE $where");
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }

    /** Unassigned leads are never in an employee's own scope, so no employeeId param. */
    private function countUnassignedLeads($source, $projectId)
    {
        $where = ['l.assignedToId IS NULL'];
        $params = [];
        $types = '';

        if ($source !== '') {
            $where[] = 'l.sourceId IN (SELECT id FROM leadSources WHERE sourceName = ?)';
            $params[] = $source;
            $types .= 's';
        }
        if ($projectId > 0) {
            $where[] = 'l.projectId = ?';
            $params[] = $projectId;
            $types .= 'i';
        }

        $stmt = mysqli_prepare($this->con, 'SELECT COUNT(*) AS c FROM leads l WHERE ' . implode(' AND ', $where));
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }

    private function countFollowUps($employeeId, $source, $projectId, $status, $dateFrom, $dateTo)
    {
        $where = ['f.status = ?', 'f.dueDate BETWEEN ? AND ?'];
        $params = [$status, $dateFrom, $dateTo];
        $types = 'sss';

        if ($employeeId > 0) {
            $where[] = 'l.assignedToId = ?';
            $params[] = $employeeId;
            $types .= 'i';
        }
        if ($source !== '') {
            $where[] = 'l.sourceId IN (SELECT id FROM leadSources WHERE sourceName = ?)';
            $params[] = $source;
            $types .= 's';
        }
        if ($projectId > 0) {
            $where[] = 'l.projectId = ?';
            $params[] = $projectId;
            $types .= 'i';
        }

        $sql = "SELECT COUNT(*) AS c
                FROM leadFollowUps f
                INNER JOIN leads l ON l.id = f.leadId
                WHERE " . implode(' AND ', $where);

        $stmt = mysqli_prepare($this->con, $sql);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }

    /**
     * Today's ($operator '=') / Overdue ($operator '<') Pending follow-ups,
     * excluding Converted/Lost leads -- same rule as
     * includes/leadFollowUpEngine.php's "today"/"overdue" views.
     */
    private function countActiveFollowUpsByDate($employeeId, $source, $projectId, $operator)
    {
        $where = ["f.status = 'Pending'", "f.dueDate $operator CURDATE()", "l.status NOT IN ('converted', 'lost')"];
        $params = [];
        $types = '';

        if ($employeeId > 0) {
            $where[] = 'l.assignedToId = ?';
            $params[] = $employeeId;
            $types .= 'i';
        }
        if ($source !== '') {
            $where[] = 'l.sourceId IN (SELECT id FROM leadSources WHERE sourceName = ?)';
            $params[] = $source;
            $types .= 's';
        }
        if ($projectId > 0) {
            $where[] = 'l.projectId = ?';
            $params[] = $projectId;
            $types .= 'i';
        }

        $sql = "SELECT COUNT(*) AS c
                FROM leadFollowUps f
                INNER JOIN leads l ON l.id = f.leadId
                WHERE " . implode(' AND ', $where);

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }
}
