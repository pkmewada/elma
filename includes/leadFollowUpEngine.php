<?php

/*
|--------------------------------------------------------------------------
| Lead Follow Up Engine
|--------------------------------------------------------------------------
|
| Configurable Follow Up System for Lead Management.
|
| leadFollowUpSettings: admin-defined rules ("Day N after lead creation ->
| Follow Up #sequence -> type"). leadFollowUps: the concrete per-lead
| occurrences generated from those rules (see generateForLead(), called
| from api/leads/addLead.php right after a lead is inserted).
|
| Reuses leads/employeeusers for identity + assignment and leadsActivityLogs
| (via saveActivityLog()) for history -- no separate history system here.
| "Add Follow Up Note" reuses api/leads/saveLeadRemark.php directly from the
| frontend, so notes are not duplicated here either.
|
*/

require_once __DIR__ . '/leadActivityLogger.php';

class LeadFollowUpEngine
{
    private $con;

    public const TYPES = ['Call', 'WhatsApp', 'Email', 'Meeting'];

    public function __construct($con)
    {
        $this->con = $con;
    }

    /*
    |--------------------------------------------------------------------------
    | Settings (rules)
    |--------------------------------------------------------------------------
    */

    public function getSettings()
    {
        $result = mysqli_query(
            $this->con,
            "SELECT
                s.id, s.dayNumber, s.followUpSequence, s.followUpType, s.isActive,
                s.createdAt, s.updatedAt,
                u.fullName AS createdByName
            FROM leadFollowUpSettings s
            LEFT JOIN users u ON u.id = s.createdBy
            ORDER BY s.dayNumber ASC, s.followUpSequence ASC"
        );

        $settings = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['dayNumber'] = (int)$row['dayNumber'];
            $row['followUpSequence'] = (int)$row['followUpSequence'];
            $row['isActive'] = (int)$row['isActive'];
            $settings[] = $row;
        }

        return $settings;
    }

    /**
     * @throws Exception on validation failure
     */
    public function saveSetting($data, $userId)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $dayNumber = (int)($data['dayNumber'] ?? 0);
        $followUpSequence = (int)($data['followUpSequence'] ?? 0);
        $followUpType = trim((string)($data['followUpType'] ?? ''));
        $isActive = !empty($data['isActive']) ? 1 : 0;

        if ($dayNumber <= 0) {
            throw new Exception('Day number must be a positive number of days after lead creation.');
        }
        if ($followUpSequence <= 0) {
            throw new Exception('Follow up number must be a positive number.');
        }
        if (!in_array($followUpType, self::TYPES, true)) {
            throw new Exception('Invalid follow up type.');
        }

        if ($id > 0) {
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE leadFollowUpSettings
                SET dayNumber = ?, followUpSequence = ?, followUpType = ?, isActive = ?
                WHERE id = ?"
            );
            mysqli_stmt_bind_param($stmt, 'iisii', $dayNumber, $followUpSequence, $followUpType, $isActive, $id);
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO leadFollowUpSettings
                (dayNumber, followUpSequence, followUpType, isActive, createdBy)
                VALUES (?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($stmt, 'iisii', $dayNumber, $followUpSequence, $followUpType, $isActive, $userId);
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save follow up rule: ' . $error);
        }

        $savedId = $id > 0 ? $id : mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return $savedId;
    }

    public function deleteSetting($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new Exception('Invalid rule id.');
        }

        $stmt = mysqli_prepare($this->con, "DELETE FROM leadFollowUpSettings WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if (!$success || $affected === 0) {
            throw new Exception('Rule not found.');
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Generation (hooked from api/leads/addLead.php after a lead is created)
    |--------------------------------------------------------------------------
    */

    /**
     * One row per currently-active rule, due date = lead's creation date +
     * dayNumber days. Snapshots followUpSequence/followUpType onto the row
     * so later edits/deletes of the rule never change already-generated
     * follow-ups. INSERT IGNORE + the (leadId, settingId) unique key make
     * this safe to call more than once for the same lead.
     */
    public function generateForLead($leadId, $leadCreatedAt)
    {
        $leadId = (int)$leadId;
        if ($leadId <= 0) {
            return 0;
        }

        $createdDate = date('Y-m-d', strtotime((string)$leadCreatedAt));

        $rules = mysqli_query(
            $this->con,
            "SELECT id, dayNumber, followUpSequence, followUpType
            FROM leadFollowUpSettings
            WHERE isActive = 1"
        );

        // Rule-generated rows are created by the system (not the user who saved the lead).
        $stmt = mysqli_prepare(
            $this->con,
            "INSERT IGNORE INTO leadFollowUps
            (leadId, settingId, followUpSequence, followUpType, dueDate, createdByType)
            VALUES (?, ?, ?, ?, ?, 'system')"
        );

        $generated = 0;

        while ($rule = mysqli_fetch_assoc($rules)) {
            $settingId = (int)$rule['id'];
            $sequence = (int)$rule['followUpSequence'];
            $type = $rule['followUpType'];
            $dueDate = date('Y-m-d', strtotime($createdDate . ' + ' . (int)$rule['dayNumber'] . ' days'));

            mysqli_stmt_bind_param($stmt, 'iiiss', $leadId, $settingId, $sequence, $type, $dueDate);

            if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                $generated++;
            }
        }

        mysqli_stmt_close($stmt);

        if ($generated > 0) {
            saveActivityLog(
                $this->con,
                'Lead',
                $leadId,
                'FOLLOWUP_GENERATED',
                "Follow ups scheduled from Follow Up Setup rules ({$generated})",
                null,
                ['count' => $generated]
            );
        }

        return $generated;
    }

    /*
    |--------------------------------------------------------------------------
    | Follow-up views
    |--------------------------------------------------------------------------
    | today     Pending, due today
    | upcoming  Pending, due after today
    | overdue   Pending, due before today
    | completed Completed (most recent first)
    | all       Every status/date (Reports "Follow-up Report" only) --
    |            dateFrom/dateTo/status/projectId in $filters narrow it further.
    | Pending follow-ups of Converted / Lost leads are not actionable and are
    | left out of the pending views (the rows themselves are kept).
    */

    const VIEWS = ['today', 'upcoming', 'overdue', 'completed'];

    private function viewCondition($view)
    {
        switch ($view) {
            case 'today':
                return "f.status = 'Pending' AND f.dueDate = CURDATE() AND l.status NOT IN ('converted', 'lost')";
            case 'upcoming':
                return "f.status = 'Pending' AND f.dueDate > CURDATE() AND l.status NOT IN ('converted', 'lost')";
            case 'overdue':
                return "f.status = 'Pending' AND f.dueDate < CURDATE() AND l.status NOT IN ('converted', 'lost')";
            case 'completed':
                return "f.status = 'Completed'";
            case 'all':
                return '1=1';
        }

        throw new Exception('Invalid follow-up view.');
    }

    /**
     * $filters: view, leadId, search, scopeEmployeeId (0 = all leads; else
     * only leads assigned to that employee - same rule as the lead APIs).
     * Reports-only additions (view=all): status, dateFrom, dateTo (on
     * f.dueDate), projectId.
     */
    public function getFollowUpList($filters = [])
    {
        $view = (string)($filters['view'] ?? 'today');
        $leadId = (int)($filters['leadId'] ?? 0);

        // A single lead's follow-ups: every status, chronological.
        $where = [$leadId > 0 ? '1=1' : $this->viewCondition($view)];
        $params = [];
        $types = '';

        if ($leadId > 0) {
            $where[] = 'f.leadId = ?';
            $params[] = $leadId;
            $types .= 'i';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(l.fullName LIKE ? OR l.phone LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        $scopeEmployeeId = (int)($filters['scopeEmployeeId'] ?? 0);
        if ($scopeEmployeeId !== 0) {
            $where[] = 'l.assignedToId = ?';
            $params[] = $scopeEmployeeId;
            $types .= 'i';
        } elseif ((int)($filters['employeeId'] ?? 0) > 0) {
            // Reports only: a full-scope caller (admin / view-all-leads)
            // choosing one salesperson. Restricted callers ignore this --
            // scopeEmployeeId above already pins them to themselves.
            $where[] = 'l.assignedToId = ?';
            $params[] = (int)$filters['employeeId'];
            $types .= 'i';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['Pending', 'Completed', 'Skipped'], true)) {
            $where[] = 'f.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $projectId = (int)($filters['projectId'] ?? 0);
        if ($projectId > 0) {
            $where[] = 'l.projectId = ?';
            $params[] = $projectId;
            $types .= 'i';
        }

        $dateFrom = (string)($filters['dateFrom'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'f.dueDate >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        $dateTo = (string)($filters['dateTo'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'f.dueDate <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }

        $order = ($view === 'completed' && $leadId <= 0)
            ? 'f.resolvedAt DESC, f.id DESC'
            : 'f.dueDate ASC, f.dueTime ASC, f.id ASC';

        $createdBy = actorNameSql('f.createdByType', 'cua', 'cea');
        $resolvedBy = actorNameSql('f.resolvedByType', 'rua', 'rea');

        $sql = "
            SELECT
                f.id, f.leadId, f.followUpSequence, f.followUpType, f.dueDate, f.dueTime,
                f.status, f.remark, f.resolvedAt, f.settingId,
                l.fullName AS leadName, l.phone, l.countryCode, l.status AS leadStatus,
                p.projectName, eu.fullName AS assignedToName,
                {$createdBy} AS createdByName,
                IF(f.resolvedAt IS NULL, NULL, {$resolvedBy}) AS completedByName
            FROM leadFollowUps f
            INNER JOIN leads l ON l.id = f.leadId
            LEFT JOIN projects p ON p.id = l.projectId
            LEFT JOIN employeeusers eu ON eu.id = l.assignedToId
            LEFT JOIN users cua ON cua.id = f.createdById AND f.createdByType = 'admin'
            LEFT JOIN employeeusers cea ON cea.id = f.createdById AND f.createdByType = 'employee'
            LEFT JOIN users rua ON rua.id = f.resolvedByCandidateId AND f.resolvedByType = 'admin'
            LEFT JOIN employeeusers rea ON rea.id = f.resolvedByCandidateId AND f.resolvedByType = 'employee'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$order}
            LIMIT 2000
        ";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            throw new Exception('Failed to load follow ups.');
        }

        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['leadId'] = (int)$row['leadId'];
            $row['followUpSequence'] = $row['followUpSequence'] !== null ? (int)$row['followUpSequence'] : null;
            $row['isManual'] = $row['settingId'] === null;
            $row['assignedToName'] = $row['assignedToName'] ?? 'Unassigned';
            unset($row['settingId']);
            $list[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $list;
    }

    /** Count per view within the same scope (for the tab badges). */
    public function getViewCounts($scopeEmployeeId)
    {
        $counts = [];

        foreach (self::VIEWS as $view) {
            $sql = 'SELECT COUNT(*) AS total FROM leadFollowUps f INNER JOIN leads l ON l.id = f.leadId WHERE ' . $this->viewCondition($view);

            if ((int)$scopeEmployeeId !== 0) {
                $sql .= ' AND l.assignedToId = ' . (int)$scopeEmployeeId;
            }

            $row = mysqli_fetch_assoc(mysqli_query($this->con, $sql));
            $counts[$view] = (int)($row['total'] ?? 0);
        }

        return $counts;
    }

    /*
    |--------------------------------------------------------------------------
    | Status transitions
    |--------------------------------------------------------------------------
    */

    /**
     * @throws Exception on validation failure
     */
    public function updateStatus($followUpId, $status, $remark = '')
    {
        $followUpId = (int)$followUpId;
        if ($followUpId <= 0 || !in_array($status, ['Completed', 'Skipped'], true)) {
            throw new Exception('Invalid follow up status update.');
        }

        $existing = $this->getFollowUpById($followUpId);
        if (!$existing) {
            throw new Exception('Follow up not found.');
        }

        if ($existing['status'] === $status) {
            return true;
        }

        $actor = getCurrentActor();
        $remark = trim((string)$remark);
        $newRemark = $remark !== ''
            ? trim(($existing['remark'] ? $existing['remark'] . "\n" : '') . $remark)
            : $existing['remark'];

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE leadFollowUps
            SET status = ?, remark = ?, resolvedAt = NOW(), resolvedByCandidateId = ?, resolvedByType = ?
            WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ssisi', $status, $newRemark, $actor['id'], $actor['type'], $followUpId);

        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update follow up.');
        }
        mysqli_stmt_close($stmt);

        $label = $existing['followUpSequence'] !== null
            ? "Follow up #{$existing['followUpSequence']} ({$existing['followUpType']})"
            : 'Follow up of ' . date('d M Y', strtotime($existing['dueDate']));

        saveActivityLog(
            $this->con,
            'Lead',
            (int)$existing['leadId'],
            $status === 'Completed' ? 'FOLLOWUP_COMPLETE' : 'FOLLOWUP_SKIP',
            "{$label} marked {$status}",
            ['status' => $existing['status']],
            ['status' => $status, 'remark' => $remark !== '' ? $remark : null]
        );

        return true;
    }

    private function getFollowUpById($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare(
            $this->con,
            'SELECT id, leadId, followUpSequence, followUpType, dueDate, status, remark FROM leadFollowUps WHERE id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}
