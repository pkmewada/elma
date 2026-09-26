<?php

/*
|--------------------------------------------------------------------------
| Other Graphic Content Engine
|--------------------------------------------------------------------------
|
| Backs pages/other-graphic-content.php — raw material for miscellaneous
| content requests (Blog, Bio, Caption, YouTube Description, etc.) that
| don't fit clientSocialContent's platform/feature/postType shape. Mirrors
| SocialContentEngine's own getEntries()/saveEntry()/deleteEntry() pattern
| (same "complete on save, hand off to Production" flow — see
| api/other-graphic-content/save-entry.php), minus the calendar-plan
| concept, which doesn't apply here: Other Graphic Content has no platform,
| feature, or planned slot, just a client, a date, a deadline, and an edit
| type picked from a fixed list.
|
*/

class OtherGraphicContentEngine
{
    private $con;

    // column => bind type, in the order they're selected/inserted
    private const CONTENT_COLUMNS = [
        'editType' => 's',
        'title' => 's',
        'hook' => 's',
        'reference' => 's',
        'notes' => 's',
        'contentDescription' => 's',
        'status' => 's',
    ];

    public const EDIT_TYPES = [
        'Blog',
        'Short Video and Reels',
        'Long Video and Reels',
        'Bio',
        'Caption',
        'Notes',
        'Youtube Description',
        'Others',
    ];

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * @param array $filters clientId, fromDate, toDate ('YYYY-MM-DD')
     */
    public function getEntries($filters = [])
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['clientId'])) {
            $where[] = 'o.clientId = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['fromDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['fromDate'])) {
            $where[] = 'o.contentDate >= ?';
            $types .= 's';
            $params[] = $filters['fromDate'];
        }
        if (!empty($filters['toDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['toDate'])) {
            $where[] = 'o.contentDate <= ?';
            $types .= 's';
            $params[] = $filters['toDate'];
        }

        $sql = "SELECT o.id, o.clientId, o.contentDate, o.deadlineDate, o.editType, o.title, o.hook,
                       o.reference, o.notes, o.contentDescription, o.status,
                       o.createdBy, o.updatedBy, o.createdAt, o.updatedAt,
                       cm.clientCode, l.fullName AS clientName,
                       eu.fullName AS createdByName
                FROM otherGraphicContent o
                INNER JOIN clientMaster cm ON cm.id = o.clientId
                INNER JOIN leads l ON l.id = cm.leadId
                LEFT JOIN users eu ON eu.id = o.createdBy
                WHERE " . implode(' AND ', $where) . "
                ORDER BY o.contentDate DESC, o.id DESC";

        $stmt = mysqli_prepare($this->con, $sql);
        if ($types !== '') {
            $bindParams = [$stmt, $types];
            foreach ($params as $key => $val) {
                $bindParams[] = &$params[$key];
            }
            call_user_func_array('mysqli_stmt_bind_param', $bindParams);
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
     * Insert or update one entry. $data['id'] present => update.
     * @throws Exception on validation failure
     */
    public function saveEntry($data, $userId)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $clientId = (int)($data['clientId'] ?? 0);
        $contentDate = trim($data['contentDate'] ?? '');
        $deadlineDate = trim($data['deadlineDate'] ?? '');

        if ($clientId <= 0) {
            throw new Exception('Client is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $contentDate)) {
            throw new Exception('A valid date is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $deadlineDate)) {
            throw new Exception('A valid deadline date is required.');
        }

        $content = [];
        foreach (self::CONTENT_COLUMNS as $col => $type) {
            $content[$col] = trim((string)($data[$col] ?? ''));
        }
        if (!in_array($content['editType'], self::EDIT_TYPES, true)) {
            throw new Exception('A valid edit type is required.');
        }
        if ($content['status'] === '' || !in_array($content['status'], ['draft', 'ready'], true)) {
            $content['status'] = 'draft';
        }
        // 'ready' is only ever reached through completeEntry() -- mirrors
        // SocialContentEngine::saveEntry()'s own guard exactly.
        if ($content['status'] === 'ready') {
            $existing = $id > 0 ? $this->getEntryById($id) : null;
            $currentStatus = $existing['status'] ?? null;
            if ($currentStatus !== 'ready') {
                $content['status'] = $currentStatus ?? 'draft';
            }
        }

        if ($content['title'] === '' && $content['contentDescription'] === '') {
            throw new Exception('Provide at least a title or content description.');
        }


        

        $fields = [
            'clientId' => ['i', $clientId],
            'contentDate' => ['s', $contentDate],
            'deadlineDate' => ['s', $deadlineDate],
        ];
        foreach (self::CONTENT_COLUMNS as $col => $type) {
            $fields[$col] = [$type, $content[$col]];
        }

        if ($id > 0) {
            $fields['updatedBy'] = ['i', $userId];
            $setClause = implode(', ', array_map(fn($col) => "$col = ?", array_keys($fields)));
            $sql = "UPDATE otherGraphicContent SET $setClause, updatedAt = NOW() WHERE id = ?";
            $fields['id'] = ['i', $id];
            $this->run($sql, $fields);
        } else {
            $fields['createdBy'] = ['i', $userId];
            $fields['updatedBy'] = ['i', $userId];
            $columns = array_keys($fields);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO otherGraphicContent (" . implode(', ', $columns) . ", createdAt, updatedAt)
                    VALUES ($placeholders, NOW(), NOW())";
            $this->run($sql, $fields);
            $id = mysqli_insert_id($this->con);
        }

        return $this->getEntryById($id);
    }

    /**
     * Marks an entry READY -- mirrors SocialContentEngine::completeEntry()
     * exactly. Idempotent, safe to call on every save.
     */
    public function completeEntry($id, $userId)
    {
        $id = (int)$id;
        $entry = $this->getEntryById($id);
        if (!$entry) {
            throw new Exception('Content entry not found.');
        }

        if ($entry['status'] === 'ready') {
            return $entry;
        }

        $title = trim((string)($entry['title'] ?? ''));
        $description = trim((string)($entry['contentDescription'] ?? ''));
        if ($title === '' && $description === '') {
            throw new Exception('Add a title or content description before completing this entry.');
        }

        $stmt = mysqli_prepare($this->con, "UPDATE otherGraphicContent SET status = 'ready', updatedBy = ?, updatedAt = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $userId, $id);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to complete entry: ' . $error);
        }
        mysqli_stmt_close($stmt);

        return $this->getEntryById($id);
    }

    public function deleteEntry($id)
    {
        $id = (int)$id;
        if ($id <= 0) {
            throw new Exception('Invalid entry id.');
        }

        $stmt = mysqli_prepare($this->con, "DELETE FROM otherGraphicContent WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $success = mysqli_stmt_execute($stmt);
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if (!$success || $affected === 0) {
            throw new Exception('Entry not found.');
        }

        return true;
    }

    public function getEntryById($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare($this->con, "SELECT o.id, o.clientId, o.contentDate, o.deadlineDate, o.editType, o.title, o.hook,
                       o.reference, o.notes, o.contentDescription, o.status,
                       o.createdBy, o.updatedBy, o.createdAt, o.updatedAt,
                       cm.clientCode, l.fullName AS clientName
                FROM otherGraphicContent o
                INNER JOIN clientMaster cm ON cm.id = o.clientId
                INNER JOIN leads l ON l.id = cm.leadId
                WHERE o.id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->castEntry($row) : null;
    }

    // runs an INSERT/UPDATE built from a ['column' => ['type', value]] map,
    // binding params by reference -- avoids hand-counting a type string
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
        $row['createdBy'] = $row['createdBy'] !== null ? (int)$row['createdBy'] : null;
        $row['updatedBy'] = $row['updatedBy'] !== null ? (int)$row['updatedBy'] : null;
        return $row;
    }
}
