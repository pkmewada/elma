<?php

/*
|--------------------------------------------------------------------------
| Graphic Content Engine (UI label "Other Graphic Content")
|--------------------------------------------------------------------------
|
| Backs pages/graphic-content.php — a new, independent raw-material source
| for Production, NOT a reuse of otherGraphicContentEngine.php/"Other
| Content" (that module was disconnected from Production on 2026-09-17;
| this one is a fresh identity that IS connected). Mirrors
| OtherGraphicContentEngine's own getEntries()/saveEntry()/completeEntry()/
| deleteEntry() pattern line-for-line (same validation shape, same
| "status can only reach 'ready' via completeEntry(), idempotently" guard,
| same bind-by-reference run() helper) — this codebase's own established
| per-module-engine convention, not a shared base class.
|
*/

class GraphicContentEngine
{
    private $con;

    // column => bind type, in the order they're selected/inserted
    private const CONTENT_COLUMNS = [
        'contentName' => 's',
        'editType' => 's',
        'priority' => 's',
        'rawContent' => 's',
        'songUrl' => 's',
        'reference' => 's',
        'notes' => 's',
        'contentDescription' => 's',
        'status' => 's',
    ];

    public const EDIT_TYPES = [
        'Post',
        'PPT',
        'Reels',
        'Video',
        'Image',
        'Invitation',
        'Brochure',
        'Banner',
        'Logo',
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High'];

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
            $where[] = 'g.clientId = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['fromDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['fromDate'])) {
            $where[] = 'g.contentDate >= ?';
            $types .= 's';
            $params[] = $filters['fromDate'];
        }
        if (!empty($filters['toDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['toDate'])) {
            $where[] = 'g.contentDate <= ?';
            $types .= 's';
            $params[] = $filters['toDate'];
        }

        $sql = "SELECT g.id, g.clientId, g.contentDate, g.deadlineAt, g.contentName, g.editType,
                       g.priority, g.rawContent, g.songUrl, g.reference, g.notes, g.contentDescription, g.status,
                       g.createdBy, g.updatedBy, g.createdAt, g.updatedAt,
                       cm.clientCode, l.fullName AS clientName,
                       eu.fullName AS createdByName
                FROM graphicContent g
                INNER JOIN clientMaster cm ON cm.id = g.clientId
                INNER JOIN leads l ON l.id = cm.leadId
                LEFT JOIN users eu ON eu.id = g.createdBy
                WHERE " . implode(' AND ', $where) . "
                ORDER BY g.contentDate DESC, g.id DESC";

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
        $deadlineAt = trim($data['deadlineAt'] ?? '');

        if ($clientId <= 0) {
            throw new Exception('Client is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $contentDate)) {
            throw new Exception('A valid date is required.');
        }
        // Accepts 'YYYY-MM-DD HH:MM' or 'YYYY-MM-DD HH:MM:SS' -- normalized
        // to full datetime below before it's ever bound/stored.
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(:\d{2})?$/', $deadlineAt)) {
            throw new Exception('A valid deadline date and time is required.');
        }
        $deadlineAt = date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $deadlineAt)));

        $content = [];
        foreach (self::CONTENT_COLUMNS as $col => $type) {
            $content[$col] = trim((string)($data[$col] ?? ''));
        }
        if ($content['contentName'] === '') {
            throw new Exception('Content name is required.');
        }
        if (!in_array($content['editType'], self::EDIT_TYPES, true)) {
            throw new Exception('A valid edit type is required.');
        }
        if (!in_array($content['priority'], self::PRIORITIES, true)) {
            $content['priority'] = 'Medium';
        }
        if ($content['status'] === '' || !in_array($content['status'], ['draft', 'ready'], true)) {
            $content['status'] = 'draft';
        }
        // 'ready' is only ever reached through completeEntry() -- mirrors
        // OtherGraphicContentEngine::saveEntry()'s own guard exactly.
        if ($content['status'] === 'ready') {
            $existing = $id > 0 ? $this->getEntryById($id) : null;
            $currentStatus = $existing['status'] ?? null;
            if ($currentStatus !== 'ready') {
                $content['status'] = $currentStatus ?? 'draft';
            }
        }

        $fields = [
            'clientId' => ['i', $clientId],
            'contentDate' => ['s', $contentDate],
            'deadlineAt' => ['s', $deadlineAt],
        ];
        foreach (self::CONTENT_COLUMNS as $col => $type) {
            $fields[$col] = [$type, $content[$col]];
        }

        if ($id > 0) {
            $fields['updatedBy'] = ['i', $userId];
            $setClause = implode(', ', array_map(fn($col) => "$col = ?", array_keys($fields)));
            $sql = "UPDATE graphicContent SET $setClause, updatedAt = NOW() WHERE id = ?";
            $fields['id'] = ['i', $id];
            $this->run($sql, $fields);
        } else {
            $fields['createdBy'] = ['i', $userId];
            $fields['updatedBy'] = ['i', $userId];
            $columns = array_keys($fields);
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $sql = "INSERT INTO graphicContent (" . implode(', ', $columns) . ", createdAt, updatedAt)
                    VALUES ($placeholders, NOW(), NOW())";
            $this->run($sql, $fields);
            $id = mysqli_insert_id($this->con);
        }

        return $this->getEntryById($id);
    }

    /**
     * Marks an entry READY -- mirrors OtherGraphicContentEngine::completeEntry()
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

        $stmt = mysqli_prepare($this->con, "UPDATE graphicContent SET status = 'ready', updatedBy = ?, updatedAt = NOW() WHERE id = ?");
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

        $stmt = mysqli_prepare($this->con, "DELETE FROM graphicContent WHERE id = ?");
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
        $stmt = mysqli_prepare($this->con, "SELECT g.id, g.clientId, g.contentDate, g.deadlineAt, g.contentName, g.editType,
                       g.priority, g.rawContent, g.songUrl, g.reference, g.notes, g.contentDescription, g.status,
                       g.createdBy, g.updatedBy, g.createdAt, g.updatedAt,
                       cm.clientCode, l.fullName AS clientName
                FROM graphicContent g
                INNER JOIN clientMaster cm ON cm.id = g.clientId
                INNER JOIN leads l ON l.id = cm.leadId
                WHERE g.id = ?");
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
