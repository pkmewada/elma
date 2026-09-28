<?php

/*
|--------------------------------------------------------------------------
| Lead Dashboard Engine
|--------------------------------------------------------------------------
|
| Read-only analytics for pages/lead-dashboard.php, computed entirely from
| existing tables -- leads, leadFollowUps (includes/leadFollowUpEngine.php's
| data), employeeusers and leadsActivityLogs. No new tables, no separate
| aggregation store.
|
| Filter scoping (deliberate, see getDashboardData()):
| - employeeId / source narrow every metric below.
| - dateFrom/dateTo scope "activity in a period" metrics: New Leads, the
|   Lead Trend chart, both Follow Up counts/charts and per-employee
|   follow-up counts (all bucketed on their own date: leads.createdAt for
|   leads, leadFollowUps.dueDate for follow ups).
| - Total Leads, Converted Leads, the Status Distribution chart and each
|   employee's "Assigned Leads" count stay lifetime totals ("all leads",
|   as specified) under the employee/source scope -- they represent the
|   current pipeline, not a windowed count.
| - The Recent Follow Up table is a live activity feed (latest updated
|   rows), scoped by employee/source but not by the date range, same as
|   any "recent activity" widget.
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
        $dateFrom = trim((string)($filters['dateFrom'] ?? ''));
        $dateTo = trim((string)($filters['dateTo'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            throw new Exception('A valid date range is required.');
        }

        return [
            'summary' => $this->getSummary($employeeId, $source, $dateFrom, $dateTo),
            'leadTrend' => $this->getLeadTrend($employeeId, $source, $dateFrom, $dateTo),
            'statusDistribution' => $this->getStatusDistribution($employeeId, $source),
            'followUpPerformance' => $this->getFollowUpPerformance($employeeId, $source, $dateFrom, $dateTo),
            'employeePerformance' => $this->getEmployeePerformance($employeeId, $source, $dateFrom, $dateTo),
            'recentFollowUps' => $this->getRecentFollowUps($employeeId, $source),
        ];
    }

    private function getSummary($employeeId, $source, $dateFrom, $dateTo)
    {
        $totalLeads = $this->countLeads($employeeId, $source, null, null, null);
        $newLeads = $this->countLeads($employeeId, $source, null, $dateFrom, $dateTo);
        $convertedLeads = $this->countLeads($employeeId, $source, 'converted', null, null);
        $pendingFollowUps = $this->countFollowUps($employeeId, $source, 'Pending', $dateFrom, $dateTo);
        $completedFollowUps = $this->countFollowUps($employeeId, $source, 'Completed', $dateFrom, $dateTo);

        return [
            'totalLeads' => $totalLeads,
            'newLeads' => $newLeads,
            'pendingFollowUps' => $pendingFollowUps,
            'completedFollowUps' => $completedFollowUps,
            'convertedLeads' => $convertedLeads,
        ];
    }

    private function getLeadTrend($employeeId, $source, $dateFrom, $dateTo)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, null, $dateFrom, $dateTo);

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

    private function getStatusDistribution($employeeId, $source)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, null, null, null);

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

    private function getFollowUpPerformance($employeeId, $source, $dateFrom, $dateTo)
    {
        return [
            'Pending' => $this->countFollowUps($employeeId, $source, 'Pending', $dateFrom, $dateTo),
            'Completed' => $this->countFollowUps($employeeId, $source, 'Completed', $dateFrom, $dateTo),
            'Skipped' => $this->countFollowUps($employeeId, $source, 'Skipped', $dateFrom, $dateTo),
        ];
    }

    private function getEmployeePerformance($employeeId, $source, $dateFrom, $dateTo)
    {
        [$leadWhere, $leadParams, $leadTypes] = $this->leadWhere($employeeId, $source, null, null, null);

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

    private function getRecentFollowUps($employeeId, $source)
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
            WHERE " . implode(' AND ', $where) . "
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
                'employeeName' => $row['employeeName'] ?? 'Admin',
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
    private function leadWhere($employeeId, $source, $status, $dateFrom, $dateTo)
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

    private function countLeads($employeeId, $source, $status, $dateFrom, $dateTo)
    {
        [$where, $params, $types] = $this->leadWhere($employeeId, $source, $status, $dateFrom, $dateTo);

        $stmt = mysqli_prepare($this->con, "SELECT COUNT(*) AS c FROM leads l WHERE $where");
        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return (int)($row['c'] ?? 0);
    }

    private function countFollowUps($employeeId, $source, $status, $dateFrom, $dateTo)
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
}
