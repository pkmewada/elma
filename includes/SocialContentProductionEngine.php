<?php

/*
|--------------------------------------------------------------------------
| Social Content Production Engine
|--------------------------------------------------------------------------
|
| The "manufacturing" stage between clientSocialContent (raw material) and
| socialPosts (final dispatch, handled entirely by SocialPostEngine.php —
| this engine never touches it). One row in socialContentProduction tracks
| the production workflow for one clientSocialContent entry; every
| assignment/status change is appended to socialContentProductionHistory,
| never overwritten.
|
| Status lifecycle:
|   NEW -> ASSIGNED -> IN_PROGRESS -> SUBMITTED -> APPROVED -> PRODUCTION_READY
|                                         |  ^
|                                         v  |
|                                     CORRECTION
|
| sendToCaption() references SocialContentCaptionEngine::
| ELIGIBLE_REVIEW_STATUSES only (that engine's existing, public approved-
| state constant — never a new/duplicated list, never an instance of that
| class or one of its queries). socialContentCaptionEngine.php already
| require_once's this file, so this is a deliberate, safe circular
| require: both classes only reference each other inside method bodies,
| never at parse time, and require_once's own cycle-breaking means
| whichever file loads first still finishes defining its class normally.
|
*/

require_once __DIR__ . '/socialContentCaptionEngine.php';

class SocialContentProductionEngine
{
    private $con;

    private const TRANSITIONS = [
        'NEW' => ['ASSIGNED'],
        'ASSIGNED' => ['IN_PROGRESS', 'ASSIGNED'],
        'IN_PROGRESS' => ['SUBMITTED'],
        'SUBMITTED' => ['APPROVED', 'CORRECTION'],
        'CORRECTION' => ['IN_PROGRESS', 'CORRECTION'],
        // APPROVED/PRODUCTION_READY -> CORRECTION: a task can be rejected
        // (reviewStatus='Not Approved') even after it was already
        // approved/marked ready, not only while still SUBMITTED — see
        // updateReviewStatus()'s own comment. Reuses the existing
        // CORRECTION status; nothing new was added to this lifecycle.
        'APPROVED' => ['PRODUCTION_READY', 'CORRECTION'],
        'PRODUCTION_READY' => ['CORRECTION'],
    ];

    // statuses a task can be (re)assigned from
    private const ASSIGNABLE_STATUSES = ['NEW', 'ASSIGNED', 'IN_PROGRESS', 'CORRECTION'];

    // Production Queue "Review" classification — a manager-facing approval
    // disposition, tracked separately from the status lifecycle above (a
    // task can sit at status=SUBMITTED/APPROVED/etc. while its reviewStatus
    // records who has signed off on the actual output). Only reviewable once
    // an editor has actually submitted something.
    private const REVIEW_STATUSES = [
        'Open', 'Approved By Team', 'Approved By Client',
        'Approval Pending From Client', 'Not Approved', 'Not For Use',
    ];
    private const REVIEW_STATUSES_REQUIRING_REMARK = ['Not Approved', 'Not For Use'];
    private const REVIEWABLE_STATUSES = ['SUBMITTED', 'CORRECTION', 'APPROVED', 'PRODUCTION_READY'];

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * Send one clientSocialContent row into production.
     * @throws Exception if the source doesn't exist, or a task already exists for it
     */
    public function createTask($clientSocialContentId, $performedBy, $performedByType, $remark = null)
    {
        $clientSocialContentId = (int)$clientSocialContentId;
        if ($clientSocialContentId <= 0) {
            throw new Exception('A content entry is required.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id, contentDate FROM clientSocialContent WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $clientSocialContentId);
        mysqli_stmt_execute($stmt);
        $sourceEntry = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$sourceEntry) {
            throw new Exception('Content entry not found.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE clientSocialContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $clientSocialContentId);
        mysqli_stmt_execute($stmt);
        $clash = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($clash) {
            throw new Exception('This entry has already been sent to production.');
        }

        $createdBy = (int)$performedBy;
        $status = 'NEW';
        // TAT = the day before the planned content date, 5:00 PM — never
        // pushed forward if that already falls in the past; an already-late
        // task is meant to show as overdue immediately, not get a fresh clock.
        $dueAt = date('Y-m-d', strtotime($sourceEntry['contentDate'] . ' -1 day')) . ' 17:00:00';
        $stmt = mysqli_prepare($this->con, 'INSERT INTO socialContentProduction (clientSocialContentId, status, dueAt, createdBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, NOW(), NOW())');
        mysqli_stmt_bind_param($stmt, 'issi', $clientSocialContentId, $status, $dueAt, $createdBy);
        if (!mysqli_stmt_execute($stmt)) {
            $errno = mysqli_errno($this->con);
            mysqli_stmt_close($stmt);
            if ($errno === 1062) {
                // A concurrent request already created the task for this
                // entry between the clash check above and this insert —
                // uqSocialContentProductionSource caught it; same friendly
                // message as the normal clash check, not a raw DB error.
                throw new Exception('This entry has already been sent to production.');
            }
            throw new Exception('Failed to create production task: ' . mysqli_error($this->con));
        }
        $id = mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        $this->logHistory($id, 'created', null, 'NEW', $remark, $performedBy, $performedByType);

        return $this->getTask($id);
    }

    /**
     * Looks up the production task for a given source entry, if one exists —
     * used for idempotent "ensure a task exists" callers (e.g. Complete
     * Entry) so they never need to duplicate createTask()'s own clash check.
     */
    public function getTaskByContentId($clientSocialContentId)
    {
        $clientSocialContentId = (int)$clientSocialContentId;
        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE clientSocialContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $clientSocialContentId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->getTask((int)$row['id']) : null;
    }

    /**
     * Send one otherGraphicContent row into production. Mirrors createTask()
     * exactly (same clash check, same history event, same NEW starting
     * state) — the two differences are which source table is checked/
     * referenced, and dueAt: Other Graphic Content collects its own
     * Deadline Date directly from the user (there is no contentDate-1day
     * TAT formula to apply here, since there's no calendar-plan concept
     * behind this source), so dueAt is just that date at 17:00, unchanged
     * afterward, same as createTask()'s own "never pushed forward" rule.
     *
     * @throws Exception if the source doesn't exist, or a task already exists for it
     */
    public function createTaskForOther($otherGraphicContentId, $deadlineDate, $performedBy, $performedByType, $remark = null)
    {
        $otherGraphicContentId = (int)$otherGraphicContentId;
        if ($otherGraphicContentId <= 0) {
            throw new Exception('A content entry is required.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id, deadlineDate FROM otherGraphicContent WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $otherGraphicContentId);
        mysqli_stmt_execute($stmt);
        $sourceEntry = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$sourceEntry) {
            throw new Exception('Content entry not found.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE otherGraphicContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $otherGraphicContentId);
        mysqli_stmt_execute($stmt);
        $clash = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($clash) {
            throw new Exception('This entry has already been sent to production.');
        }

        $createdBy = (int)$performedBy;
        $status = 'NEW';
        $sourceType = 'other';
        $deadline = trim((string)$deadlineDate) ?: $sourceEntry['deadlineDate'];
        $dueAt = date('Y-m-d', strtotime($deadline)) . ' 17:00:00';
        $stmt = mysqli_prepare($this->con, 'INSERT INTO socialContentProduction (otherGraphicContentId, sourceType, status, dueAt, createdBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
        mysqli_stmt_bind_param($stmt, 'isssi', $otherGraphicContentId, $sourceType, $status, $dueAt, $createdBy);
        if (!mysqli_stmt_execute($stmt)) {
            $errno = mysqli_errno($this->con);
            mysqli_stmt_close($stmt);
            if ($errno === 1062) {
                // Same race as createTask() above, caught by
                // uqSocialContentProductionOtherSource instead.
                throw new Exception('This entry has already been sent to production.');
            }
            throw new Exception('Failed to create production task: ' . mysqli_error($this->con));
        }
        $id = mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        $this->logHistory($id, 'created', null, 'NEW', $remark, $performedBy, $performedByType);

        return $this->getTask($id);
    }

    /**
     * Mirrors getTaskByContentId(), for the otherGraphicContent source.
     */
    public function getTaskByOtherContentId($otherGraphicContentId)
    {
        $otherGraphicContentId = (int)$otherGraphicContentId;
        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE otherGraphicContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $otherGraphicContentId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->getTask((int)$row['id']) : null;
    }

    /**
     * Send one graphicContent row into production ("Other Graphic Content",
     * the new/independent module — not otherGraphicContent/"Other Content",
     * which no longer creates tasks). Mirrors createTaskForOther() exactly
     * (same clash check, same history event, same NEW starting state) —
     * the two differences: which source table is checked/referenced, and
     * dueAt, which here is the user's own Deadline Date/Time used directly
     * (that field already carries a time component, unlike Other Content's
     * date-only deadline, so no 17:00 default is applied).
     *
     * @throws Exception if the source doesn't exist, or a task already exists for it
     */
    public function createTaskForGraphic($graphicContentId, $deadlineAt, $performedBy, $performedByType, $remark = null)
    {
        $graphicContentId = (int)$graphicContentId;
        if ($graphicContentId <= 0) {
            throw new Exception('A content entry is required.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id, deadlineAt FROM graphicContent WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $graphicContentId);
        mysqli_stmt_execute($stmt);
        $sourceEntry = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$sourceEntry) {
            throw new Exception('Content entry not found.');
        }

        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE graphicContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $graphicContentId);
        mysqli_stmt_execute($stmt);
        $clash = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if ($clash) {
            throw new Exception('This entry has already been sent to production.');
        }

        $createdBy = (int)$performedBy;
        $status = 'NEW';
        $sourceType = 'graphic';
        $deadline = trim((string)$deadlineAt) ?: $sourceEntry['deadlineAt'];
        $dueAt = date('Y-m-d H:i:s', strtotime($deadline));
        $stmt = mysqli_prepare($this->con, 'INSERT INTO socialContentProduction (graphicContentId, sourceType, status, dueAt, createdBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
        mysqli_stmt_bind_param($stmt, 'isssi', $graphicContentId, $sourceType, $status, $dueAt, $createdBy);
        if (!mysqli_stmt_execute($stmt)) {
            $errno = mysqli_errno($this->con);
            mysqli_stmt_close($stmt);
            if ($errno === 1062) {
                // Same race as createTask()/createTaskForOther() above,
                // caught by uqSocialContentProductionGraphicSource instead.
                throw new Exception('This entry has already been sent to production.');
            }
            throw new Exception('Failed to create production task: ' . mysqli_error($this->con));
        }
        $id = mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        $this->logHistory($id, 'created', null, 'NEW', $remark, $performedBy, $performedByType);

        return $this->getTask($id);
    }

    /**
     * Mirrors getTaskByContentId()/getTaskByOtherContentId(), for the
     * graphicContent source.
     */
    public function getTaskByGraphicContentId($graphicContentId)
    {
        $graphicContentId = (int)$graphicContentId;
        $stmt = mysqli_prepare($this->con, 'SELECT id FROM socialContentProduction WHERE graphicContentId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $graphicContentId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->getTask((int)$row['id']) : null;
    }

    /**
     * Assign or reassign a task to a Video Editor.
     * @throws Exception on invalid state or invalid editor
     */
    public function assign($id, $editorId, $performedBy, $performedByType, $dueAt = null, $remark = null)
    {
        $editorId = (int)$editorId;
        if ($editorId <= 0) {
            throw new Exception('An editor must be selected.');
        }

        $task = $this->lockTask($id);
        if (!in_array($task['status'], self::ASSIGNABLE_STATUSES, true)) {
            throw new Exception('This task can no longer be (re)assigned in its current status.');
        }

        $stmt = mysqli_prepare($this->con, "SELECT id FROM employeeusers WHERE id = ? AND employmentStatus = 'Active' AND (designationName = 'Video Editor' OR designationName = 'Graphic Executive')");
        mysqli_stmt_bind_param($stmt, 'i', $editorId);
        mysqli_stmt_execute($stmt);
        $editor = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$editor) {
            throw new Exception('Selected editor is not a valid, active Video Editor or Graphic Executive.');
        }

        $oldStatus = $task['status'];
        $newStatus = $oldStatus === 'NEW' ? 'ASSIGNED' : $oldStatus;
        $action = $task['assignedEditorId'] === null ? 'assigned' : 'reassigned';

        $dueAtValue = $dueAt ? trim($dueAt) : null;

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET assignedEditorId = ?, status = ?, assignedAt = NOW(), dueAt = COALESCE(?, dueAt), updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'issi', $editorId, $newStatus, $dueAtValue, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to assign task: ' . mysqli_error($this->con));
        }
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType);

        return $this->getTask($task['id']);
    }

    /**
     * Manager sets/updates the due date without changing status or assignment.
     */
    public function setDueAt($id, $dueAt, $performedBy, $performedByType, $remark = null)
    {
        $task = $this->lockTask($id);
        if ($task['status'] === 'PRODUCTION_READY') {
            throw new Exception('This task is already production-ready.');
        }

        $dueAtValue = $dueAt ? trim($dueAt) : null;
        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET dueAt = ?, updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $dueAtValue, $task['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], 'due_updated', $task['status'], $task['status'], $remark, $performedBy, $performedByType);

        return $this->getTask($task['id']);
    }

    /**
     * Editor starts work. Ownership-checked: $editorId must be the assignee.
     */
    public function start($id, $editorId)
    {
        $task = $this->lockOwnTask($id, $editorId);
        $this->transition($task, 'IN_PROGRESS', 'started', null, $editorId, 'employee');
        return $this->getTask($task['id']);
    }

    /**
     * Editor submits the finished production — a Google Drive link or an
     * uploaded file. Ownership-checked. A submission is mandatory: this is
     * the only path from IN_PROGRESS to SUBMITTED, and it hard-rejects an
     * empty/invalid one regardless of what the caller sent.
     *
     * submissionType/submissionUrl on the row always hold the LATEST
     * submission (overwritten on resubmission after a correction — no
     * version table). The history remark below records what was submitted
     * at THIS point in time, so the full sequence stays visible in
     * socialContentProductionHistory even though the live columns don't.
     *
     * @param string $submissionType 'drive' | 'media'
     * @throws Exception on invalid state, ownership mismatch, or missing/invalid submission
     */
    public function submitProduction($id, $editorId, $submissionType, $submissionUrl, $remark = null)
    {
        $task = $this->lockOwnTask($id, $editorId);

        $submissionType = trim((string)$submissionType);
        $submissionUrl = trim((string)$submissionUrl);
        if (!in_array($submissionType, ['drive', 'media'], true) || $submissionUrl === '') {
            throw new Exception('A Google Drive link or an uploaded file is required before submitting.');
        }

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET submissionType = ?, submissionUrl = ? WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'ssi', $submissionType, $submissionUrl, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save submission: ' . $error);
        }
        mysqli_stmt_close($stmt);

        $historyRemark = 'Submitted via ' . ($submissionType === 'drive' ? 'Google Drive link' : 'uploaded media') . ": $submissionUrl";
        $remark = trim((string)$remark);
        if ($remark !== '') {
            $historyRemark .= "\nNote: $remark";
        }

        $this->transition($task, 'SUBMITTED', 'submitted', $historyRemark, $editorId, 'employee');

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET submittedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $task['id']);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $this->getTask($task['id']);
    }

    /**
     * Manager reviews a submitted task.
     * @param string $decision 'approve' | 'request_correction'
     * @throws Exception if not SUBMITTED, or correction requested without a remark
     */
    public function review($id, $decision, $performedBy, $performedByType, $remark = null)
    {
        $task = $this->lockTask($id);
        if ($task['status'] !== 'SUBMITTED') {
            throw new Exception('Only submitted work can be reviewed.');
        }

        if ($decision === 'approve') {
            $this->transition($task, 'APPROVED', 'approved', $remark, $performedBy, $performedByType);
            $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET approvedAt = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $task['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } elseif ($decision === 'request_correction') {
            $remark = trim((string)$remark);
            if ($remark === '') {
                throw new Exception('A correction remark is required.');
            }
            $this->transition($task, 'CORRECTION', 'correction_requested', $remark, $performedBy, $performedByType);
        } else {
            throw new Exception('Invalid review decision.');
        }

        return $this->getTask($task['id']);
    }

    /**
     * Production Queue's "Review" classification — set from the Production
     * Output modal once an editor has submitted work. Deliberately a
     * separate dimension from $status above (Open/Approved By Team/
     * Approved By Client/Approval Pending From Client/Not Approved/Not For
     * Use), not a new set of statuses grafted into the existing lifecycle.
     *
     * "Approved By Team"/"Approved By Client" both mean the content is
     * ready for the next step, so — only when the task is still SUBMITTED —
     * this bridges into the exact same SUBMITTED -> APPROVED transition the
     * old approve() path used, so Mark Ready / Send to Automation (both
     * unchanged, both downstream of status=APPROVED) keep working exactly
     * as before. "Not Approved" mirrors the old request-correction decision
     * the same way (SUBMITTED -> CORRECTION), so the editor's existing
     * CORRECTION -> IN_PROGRESS -> SUBMITTED resubmission loop is
     * unaffected. "Approval Pending From Client" and "Not For Use" don't
     * correspond to any existing status, so they only ever update
     * reviewStatus — no invented status is added to the main lifecycle.
     *
     * @throws Exception on an invalid status, a task with nothing submitted
     *   yet, or a missing remark for Not Approved/Not For Use
     */
    public function updateReviewStatus($id, $reviewStatus, $performedBy, $performedByType, $remark = null)
    {
        $task = $this->lockTask($id);
        if (!in_array($reviewStatus, self::REVIEW_STATUSES, true)) {
            throw new Exception('Invalid review status.');
        }
        if (!in_array($task['status'], self::REVIEWABLE_STATUSES, true)) {
            throw new Exception('This task has no submitted output to review yet.');
        }

        $remark = $remark !== null ? trim((string)$remark) : '';
        if (in_array($reviewStatus, self::REVIEW_STATUSES_REQUIRING_REMARK, true) && $remark === '') {
            throw new Exception('A remark is required for this status.');
        }

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET reviewStatus = ?, updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $reviewStatus, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update review status: ' . $error);
        }
        mysqli_stmt_close($stmt);

        $historyRemark = 'Review status set to "' . $reviewStatus . '"' . ($remark !== '' ? ": $remark" : '');
        $this->logHistory($task['id'], 'review_status_updated', null, null, $historyRemark, $performedBy, $performedByType);

        // Tracks what actually happened to the main status lifecycle (if
        // anything), so the caller can give accurate feedback instead of a
        // generic "Saved."
        //
        // Bug fixed here: "Not Approved" only ever bridged from
        // status=SUBMITTED, so rejecting a task that had already moved on
        // to APPROVED or PRODUCTION_READY silently left reviewStatus and
        // status inconsistent (e.g. "Production Ready" + "Not Approved" at
        // the same time, with no way back into rework). A rejection is now
        // honored from any of those three statuses, all bridging to the
        // same existing CORRECTION status the SUBMITTED case already used
        // — no new status added, and the existing CORRECTION ->
        // IN_PROGRESS -> SUBMITTED resubmission loop is unaffected.
        //
        // The approve bridge is unchanged: it only ever fires from
        // SUBMITTED, since re-"approving" a task that's already
        // APPROVED/PRODUCTION_READY has no meaningful transition to make.
        $bridgeable = in_array($reviewStatus, ['Approved By Team', 'Approved By Client', 'Not Approved'], true);
        $rejectableStatuses = ['SUBMITTED', 'APPROVED', 'PRODUCTION_READY'];

        if ($reviewStatus === 'Not Approved' && in_array($task['status'], $rejectableStatuses, true)) {
            $this->transition($task, 'CORRECTION', 'correction_requested', $remark, $performedBy, $performedByType);
            $reviewMessage = 'Review status updated — task status changed to Correction.';
        } elseif ($bridgeable && $reviewStatus !== 'Not Approved' && $task['status'] === 'SUBMITTED') {
            $this->transition($task, 'APPROVED', 'approved', $remark !== '' ? $remark : null, $performedBy, $performedByType);
            $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET approvedAt = NOW() WHERE id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $task['id']);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $reviewMessage = 'Review status updated — task status changed to Approved.';
        } elseif ($bridgeable) {
            $reviewMessage = 'Review status updated. No workflow status change was made (task is already "' . $task['status'] . '").';
        } else {
            $reviewMessage = 'Review status updated.';
        }

        $result = $this->getTask($task['id']);
        $result['reviewMessage'] = $reviewMessage;
        return $result;
    }

    /**
     * Manager marks approved work as production-ready. Terminal for this phase —
     * does NOT create/touch anything in socialPosts.
     */
    public function markReady($id, $performedBy, $performedByType)
    {
        $task = $this->lockTask($id);
        $this->transition($task, 'PRODUCTION_READY', 'production_ready', null, $performedBy, $performedByType);
        return $this->getTask($task['id']);
    }

    /**
     * The explicit Production Queue -> Caption Area handoff. Social
     * Content only (sourceType='social' — Caption Area's own scope);
     * requires the task's reviewStatus to already be in
     * SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES — referenced
     * directly (a require_once for that one public constant only, no
     * instance use, no query duplication) rather than hard-coding a
     * second copy of the same two values, per that engine's own
     * established approved-state definition.
     *
     * Also folds in the former separate "Mark Ready" step: transitions
     * APPROVED -> PRODUCTION_READY here (a no-op if already there) before
     * locking the task. SocialAutomationHandoffEngine::checkEligibility()/
     * listQueue() still hard-require status=PRODUCTION_READY for a social
     * task to ever reach Automation — that gate is untouched — this just
     * removes the need for a manager to click a second button to satisfy
     * it, since once sentToCaptionAt is set below, lockTask() blocks any
     * further status change here for good.
     *
     * lockTask() already rejects a task that was sent previously (its own
     * sentToCaptionAt-set check) — this method never needs its own
     * "already sent" branch. There is deliberately no reverse/un-send
     * method: setting sentToCaptionAt here is the one and only writer of
     * that column.
     *
     * @throws Exception if the task doesn't exist, was already sent
     *   (via lockTask()), isn't Social Content, or isn't at an approved
     *   review status
     */
    public function sendToCaption($id, $performedBy, $performedByType)
    {
        $task = $this->lockTask($id);

        // sourceType/reviewStatus aren't part of lockTask()'s own minimal
        // SELECT (id/status/assignedEditorId/sentToCaptionAt) -- re-fetch
        // the full task once eligibility needs more than that.
        $full = $this->getTask($task['id']);
        if (!$full || $full['sourceType'] !== 'social') {
            throw new Exception('Only Social Content can be sent to Caption Area.');
        }
        if (!in_array($full['reviewStatus'], SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES, true)) {
            throw new Exception('This content is not currently approved (review status: ' . $full['reviewStatus'] . ').');
        }

        if ($full['status'] === 'APPROVED') {
            $this->transition($full, 'PRODUCTION_READY', 'production_ready', null, $performedBy, $performedByType);
        }

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET sentToCaptionAt = NOW() WHERE id = ? AND sentToCaptionAt IS NULL');
        mysqli_stmt_bind_param($stmt, 'i', $task['id']);
        if (!mysqli_stmt_execute($stmt) || mysqli_stmt_affected_rows($stmt) === 0) {
            mysqli_stmt_close($stmt);
            throw new Exception('This task has already been sent to Caption Area.');
        }
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], 'sent_to_caption', null, null, null, $performedBy, $performedByType);

        return $this->getTask($task['id']);
    }

    public function getTask($id)
    {
        $id = (int)$id;
        $sql = "SELECT
                    p.id, p.clientSocialContentId, p.otherGraphicContentId, p.sourceType, p.graphicContentId,
                    p.assignedEditorId, p.status, p.reviewStatus, p.sentToCaptionAt,
                    p.assignedAt, p.dueAt, p.submissionType, p.submissionUrl, p.submittedAt, p.approvedAt,
                    p.createdBy, p.createdAt, p.updatedAt,
                    COALESCE(c.clientId, o.clientId, g.clientId) AS clientId,
                    c.platformId, c.featureId,
                    COALESCE(c.contentDate, o.contentDate, g.contentDate) AS contentDate,
                    COALESCE(c.title, o.title, g.contentName) AS title,
                    c.rawContent, c.caption, c.contentDescription, c.songUrl, c.ideaReference, c.referenceLink,
                    c.socialMediaHandle, c.postType, c.remarks AS contentRemarks,
                    o.editType, o.hook, o.reference AS otherReference, o.notes AS otherNotes,
                    o.contentDescription AS otherContentDescription, o.deadlineDate,
                    g.editType AS graphicEditType, g.priority AS graphicPriority,
                    g.rawContent AS graphicRawContent, g.songUrl AS graphicSongUrl,
                    g.reference AS graphicReference, g.notes AS graphicNotes,
                    g.contentDescription AS graphicContentDescription, g.deadlineAt AS graphicDeadlineAt,
                    cm.clientCode, l.fullName AS clientName,
                    dp.platformName, df.featureName,
                    e.fullName AS editorName
                FROM socialContentProduction p
                LEFT JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                LEFT JOIN otherGraphicContent o ON o.id = p.otherGraphicContentId
                LEFT JOIN graphicContent g ON g.id = p.graphicContentId
                LEFT JOIN clientMaster cm ON cm.id = COALESCE(c.clientId, o.clientId, g.clientId)
                LEFT JOIN leads l ON l.id = cm.leadId
                LEFT JOIN deliverablePlatforms dp ON dp.id = c.platformId
                LEFT JOIN deliverableFeatures df ON df.id = c.featureId
                LEFT JOIN employeeusers e ON e.id = p.assignedEditorId
                WHERE p.id = ?";
        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            // Fails here specifically when the otherGraphicContent/
            // graphicContent tables or socialContentProduction's
            // sourceType/otherGraphicContentId/graphicContentId columns
            // don't exist yet -- i.e. this environment's database hasn't
            // had every database/migrations/*-*-content.sql applied. A
            // clear exception (caught by the API layer's existing
            // try/catch) beats an uncaught TypeError with a raw stack trace.
            throw new Exception('Query prepare failed: ' . mysqli_error($this->con));
        }
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            return null;
        }

        $row['history'] = $this->getHistory($id);
        return $this->castTask($row);
    }

    /**
     * @param array $filters status, editorId, clientId, month ('YYYY-MM'), overdue (bool)
     */
    public function listForManager($filters = [])
    {
        return $this->listTasks($filters, null);
    }

    /**
     * @param int $editorId hard server-side scope — always applied, never optional
     */
    public function listForEditor($editorId, $filters = [])
    {
        return $this->listTasks($filters, (int)$editorId);
    }

    public function getHistory($productionId)
    {
        $productionId = (int)$productionId;
        $stmt = mysqli_prepare($this->con, "SELECT h.id, h.productionId, h.action, h.oldStatus, h.newStatus, h.remark,
                                                    h.performedBy, h.performedByType, h.createdAt,
                                                    COALESCE(u.fullName, eu.fullName) AS performedByName
                                             FROM socialContentProductionHistory h
                                             LEFT JOIN users u ON u.id = h.performedBy AND h.performedByType = 'admin'
                                             LEFT JOIN employeeusers eu ON eu.id = h.performedBy AND h.performedByType = 'employee'
                                             WHERE h.productionId = ?
                                             ORDER BY h.id ASC");
        mysqli_stmt_bind_param($stmt, 'i', $productionId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $history = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['productionId'] = (int)$row['productionId'];
            $row['performedBy'] = (int)$row['performedBy'];
            $history[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $history;
    }

    /**
     * Phase 6: server-side operational summary for the manager queue —
     * status breakdown, an overdue count, and per-active-Video-Editor
     * workload. Never trust client-side counts of an already-filtered
     * table; this always re-queries the database directly. Accepts the
     * same clientId/platformId/month filters listTasks() does (not
     * status/editorId/overdue -- those are the dimensions being counted).
     *
     * Other Graphic Content / Other Content: statusCounts/overdueCount/
     * unassignedCount stay scoped to sourceType='social' -- identical in
     * meaning and value to before either source existed, since every
     * pre-existing row already was 'social'. otherStatusCounts/
     * otherOverdueCount/otherUnassignedCount are the same breakdown for
     * sourceType='other' (Other Content, disconnected from new-task
     * creation since 2026-09-17 but its historical tasks still count here).
     * graphicStatusCounts/graphicOverdueCount/graphicUnassignedCount are the
     * same breakdown again for sourceType='graphic' (the new, connected
     * "Other Graphic Content" module) -- added alongside, never replacing,
     * the other two. editorWorkload is the one exception: it combines all
     * three sources into the same per-editor counts, per this feature's own
     * spec ("Only include the additional source counts" — not a second
     * workload table).
     *
     * @param array $filters clientId, platformId, month ('YYYY-MM')
     */
    public function getProductionSummary($filters = [])
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if (!empty($filters['clientId'])) {
            $where[] = 'COALESCE(c.clientId, o.clientId, g.clientId) = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['platformId'])) {
            // Platform is a Social Content-only concept -- an active
            // platform filter naturally excludes Other Content/Other
            // Graphic Content rows (c IS NULL for them), which is correct.
            $where[] = 'c.platformId = ?';
            $types .= 'i';
            $params[] = (int)$filters['platformId'];
        }
        $this->applyDateRangeFilter($where, $types, $params, $filters);
        $whereSql = implode(' AND ', $where);

        $emptyStatusCounts = [
            'NEW' => 0, 'ASSIGNED' => 0, 'IN_PROGRESS' => 0, 'SUBMITTED' => 0,
            'CORRECTION' => 0, 'APPROVED' => 0, 'PRODUCTION_READY' => 0,
        ];
        $statusCounts = $emptyStatusCounts;
        $otherStatusCounts = $emptyStatusCounts;
        $graphicStatusCounts = $emptyStatusCounts;

        $sql = "SELECT p.sourceType, p.status, COUNT(*) AS total
                FROM socialContentProduction p
                LEFT JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                LEFT JOIN otherGraphicContent o ON o.id = p.otherGraphicContentId
                LEFT JOIN graphicContent g ON g.id = p.graphicContentId
                WHERE $whereSql
                GROUP BY p.sourceType, p.status";
        $stmt = mysqli_prepare($this->con, $sql);
        $this->bindIfAny($stmt, $types, $params);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to load status counts: ' . $error);
        }
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            if (!array_key_exists($row['status'], $emptyStatusCounts)) {
                continue;
            }
            if ($row['sourceType'] === 'other') {
                $otherStatusCounts[$row['status']] = (int)$row['total'];
            } elseif ($row['sourceType'] === 'graphic') {
                $graphicStatusCounts[$row['status']] = (int)$row['total'];
            } else {
                $statusCounts[$row['status']] = (int)$row['total'];
            }
        }
        mysqli_stmt_close($stmt);

        $overdueSql = "SELECT p.sourceType, COUNT(*) AS total
                FROM socialContentProduction p
                LEFT JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                LEFT JOIN otherGraphicContent o ON o.id = p.otherGraphicContentId
                LEFT JOIN graphicContent g ON g.id = p.graphicContentId
                WHERE $whereSql
                AND p.dueAt IS NOT NULL AND p.dueAt < NOW()
                AND p.status NOT IN ('APPROVED','PRODUCTION_READY')
                GROUP BY p.sourceType";
        $stmt = mysqli_prepare($this->con, $overdueSql);
        $this->bindIfAny($stmt, $types, $params);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to load overdue count: ' . $error);
        }
        // $overdueSql GROUPs BY p.sourceType, so this can return up to
        // three rows (social + other + graphic) -- all must be read and
        // split, same as the status-count query above. A single
        // mysqli_fetch_assoc() call here would silently take whichever
        // source happens to come back first as the ENTIRE count and leave
        // the other sources' counts unset.
        $result = mysqli_stmt_get_result($stmt);
        $overdueCount = 0;
        $otherOverdueCount = 0;
        $graphicOverdueCount = 0;
        while ($row = mysqli_fetch_assoc($result)) {
            if ($row['sourceType'] === 'other') {
                $otherOverdueCount = (int)$row['total'];
            } elseif ($row['sourceType'] === 'graphic') {
                $graphicOverdueCount = (int)$row['total'];
            } else {
                $overdueCount = (int)$row['total'];
            }
        }
        mysqli_stmt_close($stmt);

        // Editor workload: every active Video Editor/Graphic Executive,
        // including those with zero assigned tasks right now (LEFT JOIN) --
        // inactive/non-editor employeeusers rows are excluded entirely,
        // never just hidden. Filtering happens INSIDE the subquery so a
        // non-matching production simply doesn't exist for this purpose --
        // the outer LEFT JOIN from employeeusers then naturally gives every
        // active editor a row (zero counts if none of their work matches
        // the filter), rather than dropping one who has real but
        // non-matching work. Combines all three sources into the same
        // counts (no sourceType split here, per this feature's own spec) --
        // the subquery's own LEFT JOINs to all three source tables just
        // exist so $whereSql's COALESCE/platform conditions still resolve;
        // none of c/o/g is otherwise read by this query.
        $workloadSql = "SELECT
                    e.id AS editorId, e.fullName AS editorName,
                    SUM(CASE WHEN p.status = 'ASSIGNED' THEN 1 ELSE 0 END) AS assignedCount,
                    SUM(CASE WHEN p.status = 'IN_PROGRESS' THEN 1 ELSE 0 END) AS inProgressCount,
                    SUM(CASE WHEN p.status = 'SUBMITTED' THEN 1 ELSE 0 END) AS submittedCount,
                    SUM(CASE WHEN p.status = 'CORRECTION' THEN 1 ELSE 0 END) AS correctionCount,
                    SUM(CASE WHEN p.dueAt IS NOT NULL AND p.dueAt < NOW() AND p.status NOT IN ('APPROVED','PRODUCTION_READY') THEN 1 ELSE 0 END) AS overdueCount
                FROM employeeusers e
                LEFT JOIN (
                    SELECT p.id, p.assignedEditorId, p.status, p.dueAt
                    FROM socialContentProduction p
                    LEFT JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                    LEFT JOIN otherGraphicContent o ON o.id = p.otherGraphicContentId
                    LEFT JOIN graphicContent g ON g.id = p.graphicContentId
                    WHERE $whereSql
                ) p ON p.assignedEditorId = e.id
                WHERE e.employmentStatus = 'Active' AND (e.designationName = 'Video Editor' OR e.designationName = 'Graphic Executive')
                GROUP BY e.id, e.fullName
                ORDER BY e.fullName";
        $stmt = mysqli_prepare($this->con, $workloadSql);
        $this->bindIfAny($stmt, $types, $params);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to load editor workload: ' . $error);
        }
        $result = mysqli_stmt_get_result($stmt);
        $editorWorkload = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $editorWorkload[] = [
                'editorId' => (int)$row['editorId'],
                'editorName' => $row['editorName'],
                'assignedCount' => (int)$row['assignedCount'],
                'inProgressCount' => (int)$row['inProgressCount'],
                'submittedCount' => (int)$row['submittedCount'],
                'correctionCount' => (int)$row['correctionCount'],
                'overdueCount' => (int)$row['overdueCount'],
            ];
        }
        mysqli_stmt_close($stmt);

        return [
            'statusCounts' => $statusCounts,
            'overdueCount' => $overdueCount,
            'unassignedCount' => $statusCounts['NEW'],
            'otherStatusCounts' => $otherStatusCounts,
            'otherOverdueCount' => $otherOverdueCount,
            'otherUnassignedCount' => $otherStatusCounts['NEW'],
            'graphicStatusCounts' => $graphicStatusCounts,
            'graphicOverdueCount' => $graphicOverdueCount,
            'graphicUnassignedCount' => $graphicStatusCounts['NEW'],
            'editorWorkload' => $editorWorkload,
        ];
    }

    /**
     * Phase 6: lets a caller OUTSIDE this engine (specifically
     * SocialAutomationHandoffEngine, which owns the PRODUCTION_READY ->
     * Automation boundary) append one append-only history row for an event
     * that happened to a production task without going through this
     * engine's own status-transition machinery -- the automation handoff
     * doesn't change socialContentProduction.status, so transition()'s
     * validation doesn't apply. This is the one, narrow, intentional
     * exception to "only this engine writes its own history": the
     * dependency direction stays one-way (the caller pushes an event in;
     * this engine never reaches out to or knows about the caller), so the
     * existing isolation between Production and Automation is preserved.
     */
    public function recordExternalEvent($productionId, $action, $remark, $performedBy, $performedByType)
    {
        $productionId = (int)$productionId;
        if ($productionId <= 0) {
            return;
        }

        $this->logHistory($productionId, $action, null, null, $remark, $performedBy, $performedByType);
    }

    // Shared by getProductionSummary()'s three queries (status counts,
    // overdue count, editor workload). The $stmt-validity check has to run
    // before the empty-$types early return -- a query that needs no bound
    // params still gets executed right after this call, so a false $stmt
    // (mysqli_prepare() failed, almost always a schema mismatch -- see the
    // getTask()/listTasks() comments above) must be caught here regardless
    // of whether there was anything to bind.
    private function bindIfAny($stmt, $types, $params)
    {
        if (!$stmt) {
            throw new Exception('Query prepare failed: ' . mysqli_error($this->con));
        }
        if ($types === '') {
            return;
        }
        $bindParams = [$stmt, $types];
        foreach ($params as $key => $val) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
    }

    // Shared by listTasks() and getProductionSummary() — both filter the
    // same underlying clientSocialContent.contentDate (replaces the old
    // month = DATE_FORMAT(c.contentDate,'%Y-%m') filter with an inclusive
    // date range on the same column, never createdAt/updatedAt/dueAt).
    // Either bound alone is valid: fromDate-only means "on/after", toDate-only
    // means "on/before".
    private function applyDateRangeFilter(array &$where, &$types, array &$params, $filters)
    {
        if (!empty($filters['fromDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['fromDate'])) {
            $where[] = 'COALESCE(c.contentDate, o.contentDate, g.contentDate) >= ?';
            $types .= 's';
            $params[] = $filters['fromDate'];
        }
        if (!empty($filters['toDate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['toDate'])) {
            $where[] = 'COALESCE(c.contentDate, o.contentDate, g.contentDate) <= ?';
            $types .= 's';
            $params[] = $filters['toDate'];
        }
    }

    // --- internal helpers ---------------------------------------------

    private function listTasks($filters, $forceEditorId)
    {
        $where = ['1=1'];
        $types = '';
        $params = [];

        if ($forceEditorId !== null) {
            $where[] = 'p.assignedEditorId = ?';
            $types .= 'i';
            $params[] = $forceEditorId;
        } elseif (!empty($filters['editorId'])) {
            $where[] = 'p.assignedEditorId = ?';
            $types .= 'i';
            $params[] = (int)$filters['editorId'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'p.status = ?';
            $types .= 's';
            $params[] = $filters['status'];
        }
        if (!empty($filters['clientId'])) {
            $where[] = 'COALESCE(c.clientId, o.clientId, g.clientId) = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['platformId'])) {
            // Platform only exists on the Social Content side -- when this
            // filter is active, Other Content/Other Graphic Content rows
            // (c IS NULL) never match, which is correct: they have no
            // platform to filter by.
            $where[] = 'c.platformId = ?';
            $types .= 'i';
            $params[] = (int)$filters['platformId'];
        }
        if (!empty($filters['source']) && in_array($filters['source'], ['social', 'other', 'graphic'], true)) {
            $where[] = 'p.sourceType = ?';
            $types .= 's';
            $params[] = $filters['source'];
        }
        $this->applyDateRangeFilter($where, $types, $params, $filters);
        if (!empty($filters['overdue'])) {
            $where[] = "p.dueAt IS NOT NULL AND p.dueAt < NOW() AND p.status NOT IN ('APPROVED','PRODUCTION_READY')";
        }

        $sql = "SELECT
                    p.id, p.clientSocialContentId, p.otherGraphicContentId, p.sourceType, p.graphicContentId,
                    p.assignedEditorId, p.status, p.reviewStatus, p.sentToCaptionAt,
                    p.assignedAt, p.dueAt, p.submissionType, p.submissionUrl, p.submittedAt, p.approvedAt,
                    p.createdBy, p.createdAt, p.updatedAt,
                    COALESCE(c.clientId, o.clientId, g.clientId) AS clientId,
                    c.platformId, c.featureId,
                    COALESCE(c.contentDate, o.contentDate, g.contentDate) AS contentDate,
                    COALESCE(c.title, o.title, g.contentName) AS title,
                    c.rawContent, c.caption, c.contentDescription, c.songUrl, c.ideaReference, c.referenceLink,
                    c.socialMediaHandle, c.postType, c.remarks AS contentRemarks,
                    o.editType, o.hook, o.reference AS otherReference, o.notes AS otherNotes,
                    o.contentDescription AS otherContentDescription, o.deadlineDate,
                    g.editType AS graphicEditType, g.priority AS graphicPriority,
                    g.rawContent AS graphicRawContent, g.songUrl AS graphicSongUrl,
                    g.reference AS graphicReference, g.notes AS graphicNotes,
                    g.contentDescription AS graphicContentDescription, g.deadlineAt AS graphicDeadlineAt,
                    cm.clientCode, l.fullName AS clientName,
                    dp.platformName, df.featureName,
                    e.fullName AS editorName,
                    (SELECT h.remark FROM socialContentProductionHistory h
                       WHERE h.productionId = p.id AND h.remark IS NOT NULL AND h.remark <> ''
                       ORDER BY h.id DESC LIMIT 1) AS lastRemark
                FROM socialContentProduction p
                LEFT JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                LEFT JOIN otherGraphicContent o ON o.id = p.otherGraphicContentId
                LEFT JOIN graphicContent g ON g.id = p.graphicContentId
                LEFT JOIN clientMaster cm ON cm.id = COALESCE(c.clientId, o.clientId, g.clientId)
                LEFT JOIN leads l ON l.id = cm.leadId
                LEFT JOIN deliverablePlatforms dp ON dp.id = c.platformId
                LEFT JOIN deliverableFeatures df ON df.id = c.featureId
                LEFT JOIN employeeusers e ON e.id = p.assignedEditorId
                WHERE " . implode(' AND ', $where) . "
                ORDER BY (p.dueAt IS NULL), p.dueAt ASC, p.createdAt DESC";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            // Same schema-mismatch failure mode as getTask() above -- see
            // that comment. Without this check, a missing migration turns
            // into an uncaught TypeError from mysqli_stmt_bind_param()
            // instead of a clean, catchable Exception.
            throw new Exception('Query prepare failed: ' . mysqli_error($this->con));
        }
        if ($types !== '') {
            $bindParams = [$stmt, $types];
            foreach ($params as $key => $val) {
                $bindParams[] = &$params[$key];
            }
            call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to load production tasks: ' . $error);
        }
        $result = mysqli_stmt_get_result($stmt);

        $tasks = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $tasks[] = $this->castTask($row);
        }
        mysqli_stmt_close($stmt);

        return $tasks;
    }

    // fetches the task row for update, throwing if it doesn't exist —
    // callers then validate/perform the transition.
    //
    // Send-to-Caption lock: every mutating method (assign/setDueAt/review/
    // updateReviewStatus/markReady) funnels through this one gate, so the
    // lock only needs to be checked here, once, rather than in each of
    // them individually. A task with sentToCaptionAt set is not editable
    // through any of those methods again -- there is deliberately no
    // "un-send" action to reverse this.
    private function lockTask($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare($this->con, 'SELECT id, status, assignedEditorId, sentToCaptionAt FROM socialContentProduction WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            throw new Exception('Production task not found.');
        }
        if ($row['sentToCaptionAt'] !== null) {
            throw new Exception('This task has been sent to Caption Area and can no longer be edited here.');
        }
        return $row;
    }

    // same as lockTask(), but also re-verifies the task belongs to $editorId —
    // never trusts a bare id from an editor's request
    private function lockOwnTask($id, $editorId)
    {
        $id = (int)$id;
        $editorId = (int)$editorId;
        $stmt = mysqli_prepare($this->con, 'SELECT id, status, assignedEditorId, sentToCaptionAt FROM socialContentProduction WHERE id = ? AND assignedEditorId = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $id, $editorId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$row) {
            throw new Exception('Task not found, or not assigned to you.');
        }
        if ($row['sentToCaptionAt'] !== null) {
            throw new Exception('This task has been sent to Caption Area and can no longer be edited here.');
        }
        return $row;
    }

    private function transition($task, $newStatus, $action, $remark, $performedBy, $performedByType)
    {
        $oldStatus = $task['status'];
        $allowed = self::TRANSITIONS[$oldStatus] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            throw new Exception("Cannot move from $oldStatus to $newStatus.");
        }

        $stmt = mysqli_prepare($this->con, 'UPDATE socialContentProduction SET status = ?, updatedAt = NOW() WHERE id = ?');
        mysqli_stmt_bind_param($stmt, 'si', $newStatus, $task['id']);
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to update status: ' . mysqli_error($this->con));
        }
        mysqli_stmt_close($stmt);

        $this->logHistory($task['id'], $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType);
    }

    private function logHistory($productionId, $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType)
    {
        $remark = $remark !== null ? trim((string)$remark) : null;
        if ($remark === '') {
            $remark = null;
        }
        $performedBy = (int)$performedBy;

        $stmt = mysqli_prepare($this->con, 'INSERT INTO socialContentProductionHistory
            (productionId, action, oldStatus, newStatus, remark, performedBy, performedByType, createdAt)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        mysqli_stmt_bind_param($stmt, 'issssis', $productionId, $action, $oldStatus, $newStatus, $remark, $performedBy, $performedByType);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function castTask($row)
    {
        $row['id'] = (int)$row['id'];
        // clientSocialContentId/otherGraphicContentId/platformId/featureId
        // are now genuinely nullable -- a task is sourced from exactly one
        // of the two content tables (sourceType says which), and
        // platformId/featureId only ever exist on the Social Content side.
        $row['clientSocialContentId'] = $row['clientSocialContentId'] !== null ? (int)$row['clientSocialContentId'] : null;
        $row['otherGraphicContentId'] = isset($row['otherGraphicContentId']) && $row['otherGraphicContentId'] !== null ? (int)$row['otherGraphicContentId'] : null;
        $row['assignedEditorId'] = $row['assignedEditorId'] !== null ? (int)$row['assignedEditorId'] : null;
        $row['clientId'] = $row['clientId'] !== null ? (int)$row['clientId'] : null;
        $row['platformId'] = isset($row['platformId']) && $row['platformId'] !== null ? (int)$row['platformId'] : null;
        $row['featureId'] = isset($row['featureId']) && $row['featureId'] !== null ? (int)$row['featureId'] : null;
        $row['createdBy'] = $row['createdBy'] !== null ? (int)$row['createdBy'] : null;
        return $row;
    }
}
