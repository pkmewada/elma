<?php

/*
|--------------------------------------------------------------------------
| Walk-in Candidate Engine
|--------------------------------------------------------------------------
|
| Office walk-in interview records (pages/walkin-candidates.php). A
| standalone log of who walked in for an interview -- separate from the
| existing employeeusers/candidate onboarding pipeline, since a walk-in
| may never actually be hired.
|
*/

class WalkInCandidateEngine
{
    private $con;

    public const STATUSES = ['Scheduled', 'Completed', 'Rejected', 'Selected'];

    public function __construct($con)
    {
        $this->con = $con;
    }

    public function getList($filters = [])
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 'w.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(w.candidateName LIKE ? OR w.phone LIKE ? OR w.positionApplied LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        $dateFrom = trim((string)($filters['dateFrom'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'w.interviewDate >= ?';
            $params[] = $dateFrom;
            $types .= 's';
        }

        $dateTo = trim((string)($filters['dateTo'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'w.interviewDate <= ?';
            $params[] = $dateTo;
            $types .= 's';
        }

        $sql = "
            SELECT
                w.id, w.candidateName, w.phone, w.email, w.positionApplied,
                w.interviewDate, w.interviewTime, w.source, w.status, w.remarks,
                w.handledByCandidateId, w.createdAt, w.updatedAt,
                eu.fullName AS handledByName
            FROM walkInCandidates w
            LEFT JOIN employeeusers eu ON eu.id = w.handledByCandidateId
            WHERE " . implode(' AND ', $where) . "
            ORDER BY w.interviewDate DESC, w.id DESC
        ";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            throw new Exception('Failed to load walk-in candidates: ' . mysqli_error($this->con));
        }

        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['handledByCandidateId'] = $row['handledByCandidateId'] !== null ? (int)$row['handledByCandidateId'] : null;
            $row['handledByName'] = $row['handledByName'] ?? '—';
            $list[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $list;
    }

    /**
     * @throws Exception on validation failure
     */
    public function saveCandidate($data, $userId)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $candidateName = trim((string)($data['candidateName'] ?? ''));
        $phone = trim((string)($data['phone'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $positionApplied = trim((string)($data['positionApplied'] ?? ''));
        $interviewDate = trim((string)($data['interviewDate'] ?? ''));
        $interviewTime = trim((string)($data['interviewTime'] ?? ''));
        $source = trim((string)($data['source'] ?? ''));
        $status = trim((string)($data['status'] ?? 'Scheduled'));
        $remarks = trim((string)($data['remarks'] ?? ''));
        $handledByCandidateId = (int)($data['handledByCandidateId'] ?? 0);

        if ($candidateName === '') {
            throw new Exception('Candidate name is required.');
        }
        if ($phone === '') {
            throw new Exception('Phone number is required.');
        }
        if ($positionApplied === '') {
            throw new Exception('Position applied for is required.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $interviewDate)) {
            throw new Exception('A valid interview date is required.');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'Scheduled';
        }

        $interviewTimeValue = $interviewTime !== '' ? $interviewTime : null;
        $emailValue = $email !== '' ? $email : null;
        $sourceValue = $source !== '' ? $source : null;
        $remarksValue = $remarks !== '' ? $remarks : null;
        $handledByValue = $handledByCandidateId > 0 ? $handledByCandidateId : null;

        if ($id > 0) {
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE walkInCandidates
                SET candidateName = ?, phone = ?, email = ?, positionApplied = ?,
                    interviewDate = ?, interviewTime = ?, source = ?, status = ?,
                    remarks = ?, handledByCandidateId = ?
                WHERE id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssssii',
                $candidateName,
                $phone,
                $emailValue,
                $positionApplied,
                $interviewDate,
                $interviewTimeValue,
                $sourceValue,
                $status,
                $remarksValue,
                $handledByValue,
                $id
            );
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO walkInCandidates
                (candidateName, phone, email, positionApplied, interviewDate,
                 interviewTime, source, status, remarks, handledByCandidateId, createdBy)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'sssssssssii',
                $candidateName,
                $phone,
                $emailValue,
                $positionApplied,
                $interviewDate,
                $interviewTimeValue,
                $sourceValue,
                $status,
                $remarksValue,
                $handledByValue,
                $userId
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save walk-in candidate: ' . $error);
        }

        $savedId = $id > 0 ? $id : mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return $savedId;
    }

    /**
     * @throws Exception on validation failure
     */
    public function updateStatus($id, $status)
    {
        $id = (int)$id;
        $status = trim((string)$status);

        if ($id <= 0 || !in_array($status, self::STATUSES, true)) {
            throw new Exception('Invalid status update.');
        }

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE walkInCandidates SET status = ? WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'si', $status, $id);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update status: ' . $error);
        }
        $affected = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);

        if ($affected === 0) {
            throw new Exception('Candidate record not found.');
        }

        return true;
    }
}
