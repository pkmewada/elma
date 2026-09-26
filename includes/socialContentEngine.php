<?php

/*
|--------------------------------------------------------------------------
| Social Content Engine
|--------------------------------------------------------------------------
|
| Backs pages/social-data-entry.php (Add Entry) and pages/social-overview.php
| (Fill Now) — both fill in content for the same kind of row (one planned
| slot: client x date x platform x feature), just via two different forms,
| so they share one table with a superset of both forms' columns.
|
| - getPlan(): which (platform, feature) slots are planned for a client on
|   which dates, read from clientCalendarPlans.selectedDates (the same
|   table includes/calendarEngine.php writes).
| - getEntries()/saveEntry()/deleteEntry(): the actual filled-in content
|   for those slots, stored in clientSocialContent.
|
*/

require_once __DIR__ . '/deliverableEngine.php';

class SocialContentEngine
{
    private $con;
    private $deliverableEngine;

    // column => bind type, in the order they're selected/inserted
    private const CONTENT_COLUMNS = [
        'title' => 's',
        'status' => 's',
        'caption' => 's',
        'referenceLink' => 's',
        'remarks' => 's',
        'socialMediaHandle' => 's',
        'postType' => 's',
        'rawContent' => 's',
        'songUrl' => 's',
        'ideaReference' => 's',
        'contentDescription' => 's',
    ];

    public function __construct($con)
    {
        $this->con = $con;
        $this->deliverableEngine = new DeliverableEngine($con);
    }

    /**
     * Planned slots for a month, keyed by date ('YYYY-MM-DD'). Only slots
     * whose platform+feature are enabled in socialMediaFeatureConfig are
     * returned — the same business rule the Calendar Planner enforces when
     * the plan is created (DeliverableEngine::isSocialFeatureAllowed()).
     *
     * $clientId = 0 means "all clients" — merges every client's plan in one query.
     *
     * 'editable' comes straight from clientCalendarPlans.isEdited (the
     * "Edited" toggle on the Calendar Planner): 'yes' -> 1, 'no' -> 0.
     *
     * @return array ['YYYY-MM-DD' => [['clientId'=>, 'platformId'=>, 'featureId'=>, 'editable'=>], ...]]
     */
    public function getPlan($clientId, $month)
    {
        $clientId = (int)$clientId;

        if ($clientId > 0) {
            $sql = "SELECT clientId, platformId, featureId, selectedDates, isEdited
                    FROM clientCalendarPlans
                    WHERE month = ? AND clientId = ?";
            $stmt = mysqli_prepare($this->con, $sql);
            mysqli_stmt_bind_param($stmt, 'si', $month, $clientId);
        } else {
            $sql = "SELECT clientId, platformId, featureId, selectedDates, isEdited
                    FROM clientCalendarPlans
                    WHERE month = ?";
            $stmt = mysqli_prepare($this->con, $sql);
            mysqli_stmt_bind_param($stmt, 's', $month);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $plan = [];
        while ($row = mysqli_fetch_assoc($result)) {
            if (!$this->deliverableEngine->isSocialFeatureAllowed($row['platformId'], $row['featureId'])) {
                continue;
            }

            $dates = $row['selectedDates'] ? (json_decode($row['selectedDates'], true) ?: []) : [];
            $editable = ($row['isEdited'] === 'yes') ? 1 : 0;
            foreach ($dates as $date) {
                $plan[$date][] = [
                    'clientId' => (int)$row['clientId'],
                    'platformId' => (int)$row['platformId'],
                    'featureId' => (int)$row['featureId'],
                    'editable' => $editable
                ];
            }
        }
        mysqli_stmt_close($stmt);

        return $plan;
    }

    /**
     * Filled entries for a month. $clientId = 0 means "all clients".
     */
    public function getEntries($clientId, $month)
    {
        $clientId = (int)$clientId;
        $monthStart = $month . '-01';
        $selectList = $this->selectList();

        if ($clientId > 0) {
            $sql = "SELECT $selectList
                    FROM clientSocialContent
                    WHERE contentDate >= ? AND contentDate < DATE_ADD(?, INTERVAL 1 MONTH) AND clientId = ?
                    ORDER BY contentDate";
            $stmt = mysqli_prepare($this->con, $sql);
            mysqli_stmt_bind_param($stmt, 'ssi', $monthStart, $monthStart, $clientId);
        } else {
            $sql = "SELECT $selectList
                    FROM clientSocialContent
                    WHERE contentDate >= ? AND contentDate < DATE_ADD(?, INTERVAL 1 MONTH)
                    ORDER BY contentDate";
            $stmt = mysqli_prepare($this->con, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $monthStart, $monthStart);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $entries = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $entries[] = $this->castEntry($row);
        }
        mysqli_stmt_close($stmt);

        return $entries;
    }

    /**
     * Insert or update one entry. $data['id'] present => update (identifies
     * the exact row by id, never by client/date/platform/feature). Content
     * columns are all optional at this layer (the two forms that feed this
     * each require their own subset client-side) — the one hard rule is that
     * an entry needs at least a title or some raw content, so it can't be
     * entirely blank.
     *
     * Multiple independent rows may legitimately share the same
     * client/date/platform/feature — clientSocialContent.id is the only
     * identity a record has. The Calendar plan is a minimum requirement, not
     * a uniqueness rule: there is deliberately no clash/duplicate check here
     * (removed — see database/migrations/2026-09-12-remove-social-content-unique-slot.sql
     * for why the matching DB unique key was also dropped).
     *
     * @throws Exception on validation failure
     */
    public function saveEntry($data, $userId)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $clientId = (int)($data['clientId'] ?? 0);
        $platformId = (int)($data['platformId'] ?? 0);
        $featureId = (int)($data['featureId'] ?? 0);
        $contentDate = trim($data['contentDate'] ?? '');

        if ($clientId <= 0 || $platformId <= 0 || $featureId <= 0) {
            throw new Exception('Client, platform and feature are required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $contentDate)) {
            throw new Exception('A valid date is required.');
        }

        // build the content column values, trimmed, defaulting status to 'draft'
        $content = [];
        foreach (self::CONTENT_COLUMNS as $col => $type) {
            $content[$col] = trim((string)($data[$col] ?? ''));
        }
        if ($content['status'] === '' || !in_array($content['status'], ['draft', 'ready', 'scheduled', 'posted'], true)) {
            $content['status'] = 'draft';
        }

        // 'ready' is only ever reached through completeEntry() — a normal
        // save can never newly transition an entry into it. If the entry is
        // already ready (e.g. editing something unrelated on it) this is a
        // no-op, not a downgrade; the row's own current status is preserved.
        if ($content['status'] === 'ready') {
            $existing = $id > 0 ? $this->getEntryById($id) : null;
            $currentStatus = $existing['status'] ?? null;
            if ($currentStatus !== 'ready') {
                $content['status'] = $currentStatus ?? 'draft';
            }
        }

        if ($content['title'] === '' && $content['rawContent'] === '') {
            throw new Exception('Provide at least a title or raw content.');
        }

        // fields => ['type', value], in the exact order they'll be bound
        $fields = [
            'clientId' => ['i', $clientId],
            'platformId' => ['i', $platformId],
            'featureId' => ['i', $featureId],
            'contentDate' => ['s', $contentDate],
        ];
        foreach (self::CONTENT_COLUMNS as $col => $type) {
            $fields[$col] = [$type, $content[$col]];
        }

        if ($id > 0) {
            $fields['updatedBy'] = ['i', $userId];
            $setClause = implode(', ', array_map(fn($col) => "$col = ?", array_keys($fields)));
            $sql = "UPDATE clientSocialContent SET $setClause, updatedAt = NOW() WHERE id = ?";
            $fields['id'] = ['i', $id];
            $this->run($sql, $fields);
        } else {
            $fields['createdBy'] = ['i', $userId];
            $fields['updatedBy'] = ['i', $userId];
            $columns = array_keys($fields);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO clientSocialContent (" . implode(', ', $columns) . ", createdAt, updatedAt)
                    VALUES ($placeholders, NOW(), NOW())";
            $this->run($sql, $fields);
            $id = mysqli_insert_id($this->con);
        }

        return $this->getEntryById($id);
    }

    /**
     * Marks an entry READY — the only path that should ever precede creating
     * a production task. There is no separate manual "Complete Entry" step
     * anymore: api/social-content/save-entry.php calls this automatically,
     * in the same transaction, right after every successful saveEntry(). A
     * plain saveEntry() call itself still never sets status to 'ready' on
     * its own (see the guard above) — this method remains the one place
     * that transition happens, it is just invoked automatically now instead
     * of from a user-clicked button.
     *
     * Idempotent: if the entry is already ready/scheduled/posted, this is a
     * no-op that just returns the current row — safe to call on every save,
     * retry, or refresh without ever re-triggering anything.
     *
     * @throws Exception if the entry doesn't exist, or fails the same
     *   minimum-content bar saveEntry() already enforces at save time
     */
    public function completeEntry($id, $userId)
    {
        $id = (int)$id;
        $entry = $this->getEntryById($id);
        if (!$entry) {
            throw new Exception('Content entry not found.');
        }

        if (in_array($entry['status'], ['ready', 'scheduled', 'posted'], true)) {
            return $entry;
        }

        $title = trim((string)($entry['title'] ?? ''));
        $rawContent = trim((string)($entry['rawContent'] ?? ''));
        if ($title === '' && $rawContent === '') {
            throw new Exception('Add a title or raw content before completing this entry.');
        }

        $stmt = mysqli_prepare($this->con, "UPDATE clientSocialContent SET status = 'ready', updatedBy = ?, updatedAt = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $id);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to complete entry: ' . $error);
        }
        mysqli_stmt_close($stmt);

        return $this->getEntryById($id);
    }

    /**
     * Analytics summary + daily chart data for a date range, built entirely
     * from getPlan()/getEntries() — no separate query path, no new business
     * rules. "Completed" reuses the exact same rule pages/social-data-entry.php
     * already renders with (STATUS_LABEL.pending = 'Not Filled'): a planned
     * slot counts as completed once ANY clientSocialContent row exists for
     * its client/date/platform/feature, regardless of that row's own status;
     * zero matching rows = pending. $clientId = 0 means "all clients".
     *
     * @return array ['totalContent'=>int, 'completedContent'=>int, 'pendingContent'=>int,
     *   'totalClients'=>int, 'chartData'=>[['date'=>'d M','total'=>,'completed'=>,'pending'=>], ...]]
     */
    public function getAnalytics($clientId, $startDate, $endDate)
    {
        $clientId = (int)$clientId;

        $plan = [];
        $entryKeys = [];

        $cursor = new DateTime(substr($startDate, 0, 7) . '-01');
        $endCursor = new DateTime(substr($endDate, 0, 7) . '-01');

        while ($cursor <= $endCursor) {
            $month = $cursor->format('Y-m');

            foreach ($this->getPlan($clientId, $month) as $date => $slots) {
                if ($date >= $startDate && $date <= $endDate) {
                    $plan[$date] = $slots;
                }
            }

            foreach ($this->getEntries($clientId, $month) as $entry) {
                if ($entry['contentDate'] >= $startDate && $entry['contentDate'] <= $endDate) {
                    $key = $entry['clientId'] . '|' . $entry['contentDate'] . '|' . $entry['platformId'] . '|' . $entry['featureId'];
                    $entryKeys[$key] = true;
                }
            }

            $cursor->modify('+1 month');
        }

        ksort($plan);

        $totalContent = 0;
        $completedContent = 0;
        $clientsSeen = [];
        $chartData = [];

        foreach ($plan as $date => $slots) {
            $dayTotal = count($slots);
            $dayCompleted = 0;

            foreach ($slots as $slot) {
                $clientsSeen[$slot['clientId']] = true;
                $key = $slot['clientId'] . '|' . $date . '|' . $slot['platformId'] . '|' . $slot['featureId'];
                if (isset($entryKeys[$key])) {
                    $dayCompleted++;
                }
            }

            $totalContent += $dayTotal;
            $completedContent += $dayCompleted;

            $chartData[] = [
                'date' => date('d M', strtotime($date)),
                'total' => $dayTotal,
                'completed' => $dayCompleted,
                'pending' => $dayTotal - $dayCompleted,
            ];
        }

        return [
            'totalContent' => $totalContent,
            'completedContent' => $completedContent,
            'pendingContent' => $totalContent - $completedContent,
            'totalClients' => count($clientsSeen),
            'chartData' => $chartData,
        ];
    }

    public function deleteEntry($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new Exception('Invalid entry id.');
        }

        $stmt = mysqli_prepare($this->con, "DELETE FROM clientSocialContent WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if (!$success || $affected === 0) {
            throw new Exception('Entry not found.');
        }

        return true;
    }

    private function getEntryById($id)
    {
        $selectList = $this->selectList();
        $stmt = mysqli_prepare($this->con, "SELECT $selectList FROM clientSocialContent WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->castEntry($row) : null;
    }

    private function selectList()
    {
        return implode(', ', array_merge(
            ['id', 'clientId', 'platformId', 'featureId', 'contentDate'],
            array_keys(self::CONTENT_COLUMNS),
            ['createdBy', 'updatedBy', 'createdAt', 'updatedAt']
        ));
    }

    // runs an INSERT/UPDATE built from a ['column' => ['type', value]] map,
    // binding params by reference — avoids hand-counting a type string
    private function run($sql, $fields)
    {
        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            throw new Exception('Query prepare failed: ' . mysqli_error($this->con));
        }

        $types = implode('', array_map(fn($f) => $f[0], $fields));
        $params = [$stmt, $types];
        foreach (array_keys($fields) as $col) {
            $params[] = &$fields[$col][1];
        }
        call_user_func_array('mysqli_stmt_bind_param', $params);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save entry: ' . $error);
        }
        mysqli_stmt_close($stmt);
    }

    private function castEntry($row)
    {
        $row['id'] = (int)$row['id'];
        $row['clientId'] = (int)$row['clientId'];
        $row['platformId'] = (int)$row['platformId'];
        $row['featureId'] = (int)$row['featureId'];
        $row['createdBy'] = $row['createdBy'] !== null ? (int)$row['createdBy'] : null;
        $row['updatedBy'] = $row['updatedBy'] !== null ? (int)$row['updatedBy'] : null;
        return $row;
    }
}
