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
    public function generateForLead($leadId, $leadCreatedAt, $actorId)
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

        $stmt = mysqli_prepare(
            $this->con,
            "INSERT IGNORE INTO leadFollowUps
            (leadId, settingId, followUpSequence, followUpType, dueDate)
            VALUES (?, ?, ?, ?, ?)"
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
                "Follow ups scheduled ({$generated})",
                null,
                ['count' => $generated]
            );
        }

        return $generated;
    }

    /*
    |--------------------------------------------------------------------------
    | Follow Up List
    |--------------------------------------------------------------------------
    */

    /**
     * $filters: status, dateFrom, dateTo, search, leadStatus, scopeCandidateId
     * (non-admin users only ever see their own leads' follow-ups -- same
     * rule api/leads/getScheduledCalls.php already applies).
     *
     * leadStatus restricts by leads.status (open/interested/connected/
     * not_connected -- converted and not_interested leads no longer need
     * active follow-ups). Defaults to that same restriction when no
     * specific value is passed, so it always excludes irrelevant leads.
     *
     * A Pending row is also excluded when the same lead already has an
     * open manually-scheduled call (leadRemarks.followUpremark = 'open')
     * for that exact due date -- the salesperson has already taken over
     * that occurrence via Schedule Call, so it shouldn't also appear as a
     * separate actionable generated follow-up. The row itself is never
     * touched/deleted, only left out of this list.
     */
    public function getFollowUpList($filters = [])
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, ['Pending', 'Completed', 'Skipped'], true)) {
            $where[] = 'f.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $leadStatuses = ['open', 'interested', 'connected', 'not_connected'];
        $leadStatus = trim((string)($filters['leadStatus'] ?? ''));
        if ($leadStatus !== '' && in_array($leadStatus, $leadStatuses, true)) {
            $where[] = 'l.status = ?';
            $params[] = $leadStatus;
            $types .= 's';
        } else {
            $where[] = "l.status IN ('open', 'interested', 'connected', 'not_connected')";
        }

        $where[] = "NOT EXISTS (
            SELECT 1 FROM leadRemarks sr
            WHERE sr.leadId = f.leadId
            AND sr.followUpremark = 'open'
            AND sr.followUpDateTime IS NOT NULL
            AND DATE(sr.followUpDateTime) = f.dueDate
        )";

        $dateFrom = trim((string)($filters['dateFrom'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'f.dueDate >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        $dateTo = trim((string)($filters['dateTo'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'f.dueDate <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(l.fullName LIKE ? OR l.phone LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $types .= 'ss';
        }

        $scopeCandidateId = (int)($filters['scopeCandidateId'] ?? 0);
        if ($scopeCandidateId > 0) {
            $where[] = 'l.createdByCandidateId = ?';
            $params[] = $scopeCandidateId;
            $types .= 'i';
        }

        $sql = "
            SELECT
                f.id, f.leadId, f.followUpSequence, f.followUpType, f.dueDate,
                f.status, f.resolvedAt,
                l.fullName AS leadName, l.phone, l.source,
                eu.fullName AS assignedToName
            FROM leadFollowUps f
            INNER JOIN leads l ON l.id = f.leadId
            LEFT JOIN employeeusers eu ON eu.id = l.createdByCandidateId
            WHERE " . implode(' AND ', $where) . "
            ORDER BY f.dueDate ASC, f.followUpSequence ASC
        ";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            throw new Exception('Failed to load follow ups: ' . mysqli_error($this->con));
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
            $row['followUpSequence'] = (int)$row['followUpSequence'];
            $row['assignedToName'] = $row['assignedToName'] ?? 'Admin';
            $list[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $list;
    }

    /*
    |--------------------------------------------------------------------------
    | Status transitions
    |--------------------------------------------------------------------------
    */

    /**
     * @throws Exception on validation failure
     */
    public function updateStatus($followUpId, $status, $actorId)
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

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE leadFollowUps
            SET status = ?, resolvedAt = NOW(), resolvedByCandidateId = ?
            WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'sii', $status, $actorId, $followUpId);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update follow up: ' . $error);
        }
        mysqli_stmt_close($stmt);

        saveActivityLog(
            $this->con,
            'Lead',
            (int)$existing['leadId'],
            $status === 'Completed' ? 'FOLLOWUP_COMPLETE' : 'FOLLOWUP_SKIP',
            "Follow up #{$existing['followUpSequence']} ({$existing['followUpType']}) marked {$status}",
            ['status' => $existing['status']],
            ['status' => $status]
        );

        return true;
    }

    private function getFollowUpById($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id, leadId, followUpSequence, followUpType, status FROM leadFollowUps WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }
}
