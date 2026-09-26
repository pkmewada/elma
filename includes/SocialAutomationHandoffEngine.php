<?php

require_once __DIR__ . '/SocialContentProductionEngine.php';
require_once __DIR__ . '/InstagramAutomation.php'; // read-only reuse of instagramAccountBelongsToClient() — not modified
require_once __DIR__ . '/leadActivityLogger.php'; // reuse of the existing saveActivityLog() — no new logging system
require_once __DIR__ . '/socialContentCaptionEngine.php'; // read-only reuse of getCaptionByProductionId() — Caption Area owns generate/select, this engine only reads the selected result

/*
|--------------------------------------------------------------------------
| Social Automation Handoff Engine
|--------------------------------------------------------------------------
|
| Owns exactly the PRODUCTION_READY -> Automation boundary (Architecture
| Lock, approved).
|
| Phase 4.1: eligibility validation (task exists, status is
| PRODUCTION_READY), platform eligibility validation (Instagram/Facebook
| only, resolved by name from deliverablePlatforms, never by hardcoded id),
| and the idempotent handoff-row creation primitive.
|
| Phase 4.2: automatic destination-account resolution, folded into
| checkEligibility() per the locked 0/1/2+ rule — no account selection UI,
| no mapping table. Reuses instagramAccounts (already client-scoped) and
| the existing instagramAccountBelongsToClient() guard.
|
| Phase 4.3 (this change): resolveMedia() — inspects the task's latest
| submission (server upload or Drive link) and reports whether it is
| usable as Automation media. V1 scope is image/JPEG only, per the
| Architecture Lock. Kept separate from checkEligibility() on purpose —
| media validity is an orthogonal concern from platform/account
| eligibility, not folded into that method's return shape. The production
| file itself is never moved, copied, or deleted; no Drive download, no
| Drive API call.
|
| Phase 4.4 (this change): resolveAndRegisterHandoff() now performs the
| complete Production -> socialPosts handoff (ordered-write, no DB
| transaction -- see its own doc comment). It creates a real socialPosts
| row via the EXISTING, unmodified saveSocialPost(), called in insert mode
| only. The handoff row's own status ('pending' -> 'sent'/'failed') is the
| source of truth for whether that actually succeeded.
|
| None of these phases touch SocialPostEngine.php, InstagramAutomation.php,
| FacebookPublisher.php, cron/instagramScheduler.php, or any schema. From
| here on, an existing, entirely unmodified cron/instagramScheduler.php run
| is what actually publishes the created socialPosts row.
|
| Deliberately NOT implemented here (later phases):
|   - the API endpoint and UI button (Phase 4.5)
|
| Phase 8 (this change): Caption Area wiring. checkEligibility() now also
| requires a 'selected' row in socialContentCaption (read-only, via the
| existing SocialContentCaptionEngine::getCaptionByProductionId() — never
| re-queries that table directly) before a task is eligible, and
| resolveAndRegisterHandoff() now sends that selected caption to
| saveSocialPost() instead of the raw Data-Entry caption
| (clientSocialContent.caption via $task['caption']). This only ever
| affects sourceType='social' tasks — Other Content/Other Graphic Content
| tasks have no platformName and were already blocked by the platform
| check above, so this check is placed after it on purpose.
|
| Phase 9 (this change): listQueue() — a read-only join across this
| engine's own handoff table, socialPosts, and Production/Caption, for the
| new /social-automation queue page. No new table, no new engine; this
| engine already owns the Production<->Automation boundary a queue view
| sits on top of.
|
| Dependency direction is one-way: this engine reads
| SocialContentProductionEngine::getTask() (read-only, never mutates
| production data); SocialContentProductionEngine has no knowledge of this
| engine. This engine never writes to socialContentProduction, socialPosts,
| or any publishing table.
|
*/

class SocialAutomationHandoffEngine
{
    private $con;

    private const ELIGIBLE_PLATFORMS = ['instagram', 'facebook'];

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * Read-only: is this production task allowed to enter Automation right
     * now, and if so, which account would it publish through? Never
     * mutates anything — safe to call as often as needed (e.g. to decide
     * what a future "Send to Automation" button should show).
     *
     * Checks, in order: task exists -> status is exactly PRODUCTION_READY
     * -> platform (by name, not id) is Instagram or Facebook -> no handoff
     * already registered for this task -> exactly one active connected
     * Instagram account exists for the task's client (Phase 4.2). Zero or
     * multiple active accounts block eligibility — never guessed, never
     * chosen by newest/handle text.
     */
    public function checkEligibility($productionId)
    {
        $productionId = (int)$productionId;

        if ($productionId <= 0) {
            return $this->ineligible('NOT_FOUND', 'Production task not found.');
        }

        $task = (new SocialContentProductionEngine($this->con))->getTask($productionId);
        if (!$task) {
            return $this->ineligible('NOT_FOUND', 'Production task not found.');
        }

        if ($task['status'] !== 'PRODUCTION_READY') {
            return $this->ineligible('INVALID_STATUS', 'Production task is not ready for automation.', $task);
        }

        $platformName = strtolower(trim((string)($task['platformName'] ?? '')));
        if (!in_array($platformName, self::ELIGIBLE_PLATFORMS, true)) {
            return $this->ineligible('UNSUPPORTED_PLATFORM', 'This platform is not currently supported by Social Automation.', $task);
        }

        // A 'sent' or 'pending' handoff blocks eligibility as before. A
        // 'failed' handoff is retryable — it falls through so the caption/
        // account/media checks below run again, and registerHandoff() reuses
        // this same row (via existingHandoffId) instead of inserting a second
        // one, since productionId stays UNIQUE. Checked before the caption
        // gate below on purpose: a task that already succeeded (or is
        // in-flight) must keep reporting ALREADY_HANDED_OFF regardless of
        // what socialContentCaption looks like right now — no behavior
        // change for tasks handed off before this caption gate existed.
        $existing = $this->findExistingHandoff($productionId);
        if ($existing && $existing['status'] !== 'failed') {
            return [
                'eligible' => false,
                'state' => 'ALREADY_HANDED_OFF',
                'message' => 'Automation handoff is already registered for this production task.',
                'clientId' => (int)$task['clientId'],
                'platformName' => $task['platformName'],
                'task' => $task,
                'existingHandoffId' => (int)$existing['id'],
                'resolvedAccountId' => null,
                'selectedCaption' => null,
            ];
        }
        $retryHandoffId = $existing ? (int)$existing['id'] : null;

        // Audit finding: reviewStatus is independently editable even once a
        // task is PRODUCTION_READY (SocialContentProductionEngine's own
        // REVIEWABLE_STATUSES includes PRODUCTION_READY) and was never
        // re-checked here — a task marked "Not Approved"/"Not For Use"/
        // "Approval Pending From Client" after its caption was already
        // selected stayed fully eligible and would still publish. Reuses
        // SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES (the exact
        // same list Caption Area itself already gates on), not a new list.
        if (!in_array((string)($task['reviewStatus'] ?? ''), SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES, true)) {
            return $this->ineligible('NOT_APPROVED', 'This content is not currently approved (review status: ' . $task['reviewStatus'] . '). Resolve the review status before sending to Automation.', $task);
        }

        // Placed after the platform/already-handed-off checks on purpose: only
        // sourceType='social' tasks ever reach this point (Other Content/Other
        // Graphic Content have no platformName and are already blocked above),
        // which is exactly Caption Area's own scope — so this check can never
        // misfire against a source Caption Area doesn't cover. Read-only reuse
        // of SocialContentCaptionEngine; this engine never writes to
        // socialContentCaption.
        $caption = (new SocialContentCaptionEngine($this->con))->getCaptionByProductionId($productionId);
        if (!$caption || $caption['status'] !== 'selected' || trim((string)$caption['selectedCaption']) === '') {
            return $this->ineligible('CAPTION_NOT_SELECTED', 'Select a caption in Caption Area before sending to Automation.', $task);
        }
        $selectedCaption = (string)$caption['selectedCaption'];

        // clientId is derived from the production record itself (via
        // clientSocialContent, already resolved inside getTask()) — never
        // accepted from a caller/request.
        $clientId = (int)$task['clientId'];
        $resolution = $this->resolveActiveAccount($clientId);

        if (!$resolution['resolved']) {
            return [
                'eligible' => false,
                'state' => $resolution['state'],
                'message' => $resolution['message'],
                'clientId' => $clientId,
                'platformName' => $task['platformName'],
                'task' => $task,
                'existingHandoffId' => $retryHandoffId,
                'resolvedAccountId' => null,
                'selectedCaption' => $selectedCaption,
            ];
        }

        // Defense in depth: re-verify ownership through the existing,
        // unmodified guard even though resolveActiveAccount() already
        // scoped its own query by clientId.
        if (!instagramAccountBelongsToClient($this->con, $resolution['accountId'], $clientId)) {
            return [
                'eligible' => false,
                'state' => 'NO_ACTIVE_ACCOUNT',
                'message' => 'No connected Instagram account is available for this client. Connect an account before sending to Automation.',
                'clientId' => $clientId,
                'platformName' => $task['platformName'],
                'task' => $task,
                'existingHandoffId' => $retryHandoffId,
                'resolvedAccountId' => null,
                'selectedCaption' => $selectedCaption,
            ];
        }

        return [
            'eligible' => true,
            'state' => 'ELIGIBLE',
            'message' => $retryHandoffId ? 'Ready to retry Automation.' : 'Ready to be handed off to Automation.',
            'clientId' => $clientId,
            'platformName' => $task['platformName'],
            'task' => $task,
            'existingHandoffId' => $retryHandoffId,
            'resolvedAccountId' => $resolution['accountId'],
            'selectedCaption' => $selectedCaption,
        ];
    }

    /**
     * Phase 4.4: the full Production -> socialPosts handoff. This is the
     * method a future "Send to Automation" action (Phase 4.5) is expected
     * to call. Ordered-write approach, deliberately NOT wrapped in a
     * database transaction (Architecture Lock, Transaction Verification):
     * saveSocialPost() unconditionally calls ensureSocialPostsTable(),
     * which can run DDL, and DDL implicitly commits an open transaction in
     * MySQL/MariaDB — a transaction here would be silently defeated.
     *
     *   1-2. checkEligibility() -- task/status/platform/not-already-handed-
     *        off/account resolution (Phase 4.1/4.2, unchanged).
     *   3.   resolveMedia() -- Phase 4.3, unchanged.
     *   4.   registerHandoff() -- creates the 'pending' row (Phase 4.1's
     *        idempotent primitive, unchanged; UNIQUE(productionId) is the
     *        real concurrency guard, not this method).
     *   5-6. call the EXISTING saveSocialPost() in insert mode, then check
     *        the returned id -- mysqli_report(MYSQLI_REPORT_OFF) is global,
     *        so a failed insert returns 0 rather than throwing.
     *   7-8. mark the handoff 'failed' (with a stored reason) or 'sent'
     *        (with the real socialPostId) accordingly. Never marked 'sent'
     *        without a confirmed, real socialPosts row.
     *
     * registerHandoff() itself keeps its original signature and behavior
     * for direct/manual use — not redesigned by this change.
     */
    public function resolveAndRegisterHandoff($productionId, $createdBy)
    {
        $eligibility = $this->checkEligibility($productionId);

        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'state' => $eligibility['state'],
                'message' => $eligibility['message'],
                'handoffId' => $eligibility['existingHandoffId'],
                'socialPostId' => null,
            ];
        }

        $media = $this->resolveMedia($productionId);
        if (!$media['success']) {
            return [
                'success' => false,
                'state' => $media['state'],
                'message' => $media['message'],
                'handoffId' => null,
                'socialPostId' => null,
            ];
        }

        $registration = $this->registerHandoff($productionId, $eligibility['resolvedAccountId'], $createdBy);
        if (!$registration['success']) {
            // ALREADY_HANDED_OFF (a concurrent request won the UNIQUE
            // constraint) or a genuine registration failure -- either way,
            // no socialPosts row is ever created past this point.
            return [
                'success' => false,
                'state' => $registration['state'],
                'message' => $registration['message'],
                'handoffId' => $registration['handoffId'],
                'socialPostId' => null,
            ];
        }

        $handoffId = $registration['handoffId'];
        $task = $eligibility['task'];
        $platform = strtolower(trim((string)$task['platformName']));

        $socialPostId = 0;
        $saveError = null;

        try {
            $socialPostId = (int)saveSocialPost(
                $this->con,
                [
                    'clientId' => (int)$task['clientId'],
                    'instagramAccountId' => $eligibility['resolvedAccountId'],
                    'mediaType' => 'image',
                    'mediaPaths' => [$media['mediaPath']],
                    // Caption Area's selected caption, not the raw Data-Entry
                    // caption — checkEligibility() already required this to be
                    // non-empty and 'selected' before eligible=true was ever
                    // returned.
                    'caption' => (string)($eligibility['selectedCaption'] ?? ''),
                    'status' => 'scheduled',
                    'scheduledAt' => date('Y-m-d H:i:s'), // immediate -- same convention api/social-media/saveSocialPost.php uses when no future time is given; the existing scheduler picks it up on its next run, unchanged
                    'platforms' => [$platform],
                ],
                (int)$createdBy
            );
        } catch (Throwable $e) {
            // saveSocialPost() should not normally throw, but never assume
            // the PHP call completing means success -- handle it the same
            // as a returned-zero failure.
            $socialPostId = 0;
            $saveError = $e->getMessage();
        }

        if ($socialPostId <= 0) {
            $message = $saveError !== null
                ? 'Failed to create the automation post: ' . $saveError
                : 'Failed to create the automation post.';
            $this->markHandoffFailed($handoffId, $message);

            // Phase 4.7: this action was previously completely invisible to
            // the existing activity log -- reuses saveActivityLog() as-is,
            // no new logging system. Never logs a token/secret.
            saveActivityLog($this->con, 'SocialAutomationHandoff', $productionId, 'handoff_failed', 'Send to Automation failed for production task #' . $productionId . ' (handoff #' . $handoffId . '): ' . $message);

            // Phase 6: also visible in the task's OWN history timeline (the
            // manager's detail modal), not just the site-wide activity log.
            // One-way call into SocialContentProductionEngine's own public
            // method -- that engine still never reaches back into this one.
            (new SocialContentProductionEngine($this->con))->recordExternalEvent(
                $productionId,
                'automation_handoff_failed',
                $message,
                $createdBy,
                'admin'
            );

            return [
                'success' => false,
                'state' => 'FAILED',
                'message' => $message,
                'handoffId' => $handoffId,
                'socialPostId' => null,
            ];
        }

        $this->markHandoffSent($handoffId, $socialPostId);

        saveActivityLog($this->con, 'SocialAutomationHandoff', $productionId, 'handoff_sent', 'Production task #' . $productionId . ' sent to Automation (handoff #' . $handoffId . ', socialPosts #' . $socialPostId . ', platform: ' . $platform . ').');

        // Phase 6: complete the task's own history lifecycle -- see the
        // matching comment on the failure path above.
        (new SocialContentProductionEngine($this->con))->recordExternalEvent(
            $productionId,
            'automation_handoff_sent',
            'Sent to Automation (' . $platform . '), socialPosts #' . $socialPostId . '.',
            $createdBy,
            'admin'
        );

        return [
            'success' => true,
            'state' => 'SENT',
            'message' => 'Sent to Automation.',
            'handoffId' => $handoffId,
            'socialPostId' => $socialPostId,
        ];
    }

    /**
     * Read-only queue view for the /social-automation page.
     *
     * Root-cause fix: this used to be FROM socialContentAutomationHandoff,
     * so a task only ever appeared here *after* someone manually clicked
     * "Send to Automation" on the Production Queue -- caption selection
     * alone never made anything show up here, contradicting the intended
     * flow (Caption Selected -> Automation Queue, automatically). Now
     * driven FROM socialContentProduction itself: a task appears the
     * moment it satisfies the exact same membership checkEligibility()
     * already enforces (sourceType='social', status='PRODUCTION_READY',
     * platform in self::ELIGIBLE_PLATFORMS) AND EITHER a 'selected'
     * caption exists OR a handoff already exists -- mirrored here, not
     * re-derived differently, and no new status/table was introduced to
     * express it. The "OR a handoff already exists" half specifically
     * preserves the two real handoffs created before this caption gate
     * existed (still 'scheduled', no caption row at all) -- without it,
     * those would silently vanish from the queue, which would be a
     * regression, not a fix. The handoff/socialPosts join is LEFT (a task
     * can be eligible but not yet sent), and UNIQUE(productionId) on
     * socialContentAutomationHandoff already guarantees at most one
     * handoff per task, so this can never duplicate a row for
     * already-handed-off content.
     *
     * A task with no handoff yet reports status 'eligible' (not a stored
     * value -- purely "no handoff row exists"), and its media thumbnail is
     * resolved via this engine's own resolveMedia() (same method
     * resolveAndRegisterHandoff() uses) rather than sp.mediaUrl, since no
     * socialPosts row exists yet to read it from.
     *
     * @param array $filters clientId, status ('eligible'|'failed'|
     *   'scheduled'|'publishing'|'published'|'partial'), fromDate/toDate
     *   (matched against the content's own contentDate, same convention
     *   SocialContentProductionEngine::listForManager() uses)
     */
    public function listQueue($filters = [])
    {
        $eligiblePlatforms = self::ELIGIBLE_PLATFORMS;
        $platformPlaceholders = implode(',', array_fill(0, count($eligiblePlatforms), '?'));

        // Same audit-fix reasoning as checkEligibility(): a not-yet-sent row
        // only counts as "eligible" (offering a Send action) while its
        // reviewStatus is still approved — mirrored via the same reused
        // list, not a new one. Already-handed-off rows (h.id IS NOT NULL)
        // stay visible regardless, same as they already do for caption
        // state, so a later reviewStatus change never hides a real,
        // already-sent/scheduled/published post.
        $approvedReviewStatuses = SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES;
        $reviewStatusPlaceholders = implode(',', array_fill(0, count($approvedReviewStatuses), '?'));

        $where = [
            "p.sourceType = 'social'",
            "p.status = 'PRODUCTION_READY'",
            "LOWER(dp.platformName) IN ($platformPlaceholders)",
            "((cap.status = 'selected' AND p.reviewStatus IN ($reviewStatusPlaceholders)) OR h.id IS NOT NULL)",
        ];
        $types = str_repeat('s', count($eligiblePlatforms)) . str_repeat('s', count($approvedReviewStatuses));
        $params = array_merge($eligiblePlatforms, $approvedReviewStatuses);

        if (!empty($filters['clientId'])) {
            $where[] = 'c.clientId = ?';
            $types .= 'i';
            $params[] = (int)$filters['clientId'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'eligible') {
                // Not a stored value -- "no handoff row exists yet".
                $where[] = 'h.id IS NULL';
            } else {
                $where[] = 'COALESCE(sp.status, h.status) = ?';
                $types .= 's';
                $params[] = (string)$filters['status'];
            }
        }
        if (!empty($filters['fromDate'])) {
            $where[] = 'c.contentDate >= ?';
            $types .= 's';
            $params[] = (string)$filters['fromDate'];
        }
        if (!empty($filters['toDate'])) {
            $where[] = 'c.contentDate <= ?';
            $types .= 's';
            $params[] = (string)$filters['toDate'];
        }

        $sql = "SELECT
                    p.id AS productionId, p.approvedAt,
                    h.id AS handoffId, h.socialPostId,
                    h.status AS handoffStatus, h.errorMessage AS handoffErrorMessage, h.createdAt AS handoffCreatedAt,
                    sp.status AS postStatus, sp.scheduledAt, sp.publishedAt, sp.mediaUrl, sp.errorMessage AS postErrorMessage,
                    cap.selectedCaption,
                    c.clientId, c.contentDate, c.postType, c.title,
                    cm.clientCode, l.fullName AS clientName,
                    dp.platformName
                FROM socialContentProduction p
                INNER JOIN clientSocialContent c ON c.id = p.clientSocialContentId
                LEFT JOIN socialContentCaption cap ON cap.productionId = p.id
                LEFT JOIN socialContentAutomationHandoff h ON h.productionId = p.id
                LEFT JOIN socialPosts sp ON sp.id = h.socialPostId
                LEFT JOIN clientMaster cm ON cm.id = c.clientId
                LEFT JOIN leads l ON l.id = cm.leadId
                LEFT JOIN deliverablePlatforms dp ON dp.id = c.platformId
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE(h.createdAt, p.approvedAt) DESC, p.id DESC";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
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
            throw new Exception('Failed to load the automation queue: ' . $error);
        }
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $productionId = (int)$row['productionId'];
            $socialPostId = $row['socialPostId'] !== null ? (int)$row['socialPostId'] : null;

            if ($socialPostId !== null) {
                // Already sent -- the real media that was actually used.
                $mediaUrls = $row['mediaUrl'] ? decodeSocialPostMediaPaths($row['mediaUrl']) : [];
            } else {
                // Not sent yet -- best-effort preview via the same
                // resolveMedia() resolveAndRegisterHandoff() itself calls.
                // A resolution failure (e.g. a Drive link, or PNG) is not
                // an error here -- it just means no thumbnail, same as any
                // other row with no media; the real, actionable message
                // still surfaces from Send to Automation itself if clicked.
                $media = $this->resolveMedia($productionId);
                $mediaUrls = $media['success'] ? [$media['mediaUrl']] : [];
            }

            $rows[] = [
                'handoffId' => $row['handoffId'] !== null ? (int)$row['handoffId'] : null,
                'productionId' => $productionId,
                'socialPostId' => $socialPostId,
                'status' => (string)($row['postStatus'] ?? $row['handoffStatus'] ?? 'eligible'),
                'errorMessage' => $row['postErrorMessage'] ?: $row['handoffErrorMessage'],
                'clientCode' => $row['clientCode'],
                'clientName' => $row['clientName'],
                'platformName' => $row['platformName'],
                'contentDate' => $row['contentDate'],
                'postType' => $row['postType'],
                'title' => $row['title'],
                'selectedCaption' => $row['selectedCaption'],
                'mediaUrl' => $mediaUrls,
                'scheduledAt' => $row['scheduledAt'],
                'publishedAt' => $row['publishedAt'],
                'handoffCreatedAt' => $row['handoffCreatedAt'],
            ];
        }
        mysqli_stmt_close($stmt);

        return $rows;
    }

    private function markHandoffFailed($handoffId, $message)
    {
        $handoffId = (int)$handoffId;
        $stmt = mysqli_prepare($this->con, "UPDATE socialContentAutomationHandoff SET status = 'failed', errorMessage = ?, updatedAt = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $message, $handoffId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    private function markHandoffSent($handoffId, $socialPostId)
    {
        $handoffId = (int)$handoffId;
        $socialPostId = (int)$socialPostId;
        $stmt = mysqli_prepare($this->con, "UPDATE socialContentAutomationHandoff SET status = 'sent', socialPostId = ?, errorMessage = NULL, updatedAt = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $socialPostId, $handoffId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    /**
     * Resolves the single, unambiguous destination account for a client,
     * per the Architecture Lock's account-resolution rule:
     *   0 active accounts        -> block (NO_ACTIVE_ACCOUNT)
     *   exactly 1 active account -> auto-resolve
     *   2+ active accounts       -> block (MULTIPLE_ACTIVE_ACCOUNTS)
     * "Active" matches getInstagramAccountById()'s own predicate exactly
     * (status='connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())),
     * so a resolved account is guaranteed usable at actual publish time
     * too. Never picks by "newest", never matches on socialMediaHandle.
     */
    private function resolveActiveAccount($clientId)
    {
        $clientId = (int)$clientId;

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id FROM instagramAccounts
             WHERE clientId = ? AND status = 'connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())"
        );
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $accountIds = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $accountIds[] = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);

        if (count($accountIds) === 0) {
            return [
                'resolved' => false,
                'state' => 'NO_ACTIVE_ACCOUNT',
                'message' => 'No connected Instagram account is available for this client. Connect an account before sending to Automation.',
                'accountId' => null,
            ];
        }

        if (count($accountIds) > 1) {
            return [
                'resolved' => false,
                'state' => 'MULTIPLE_ACTIVE_ACCOUNTS',
                'message' => 'This client has more than one connected Instagram account. An administrator must resolve the account configuration before automation can proceed.',
                'accountId' => null,
            ];
        }

        return [
            'resolved' => true,
            'state' => 'RESOLVED',
            'message' => 'Account resolved.',
            'accountId' => $accountIds[0],
        ];
    }

    /**
     * Phase 4.3: inspects the production task's latest submission and
     * reports whether it is usable as Automation media, and if so, its
     * resolved public URL. Re-fetches and re-checks the task itself rather
     * than trusting a caller's earlier checkEligibility() result. Never
     * moves, copies, or deletes the production file; never calls a Drive
     * API. V1 scope, per the Architecture Lock: image/JPEG only.
     *
     * @return array{success:bool,state:string,message:string,mediaType:?string,mediaUrl:?string}
     */
    public function resolveMedia($productionId)
    {
        $productionId = (int)$productionId;

        if ($productionId <= 0) {
            return $this->mediaResult(false, 'NOT_FOUND', 'Production task not found.');
        }

        $task = (new SocialContentProductionEngine($this->con))->getTask($productionId);
        if (!$task) {
            return $this->mediaResult(false, 'NOT_FOUND', 'Production task not found.');
        }

        if ($task['status'] !== 'PRODUCTION_READY') {
            return $this->mediaResult(false, 'INVALID_STATUS', 'Production task is not ready for automation.');
        }

        $submissionType = (string)($task['submissionType'] ?? '');
        $submissionUrl = trim((string)($task['submissionUrl'] ?? ''));

        if ($submissionType === 'drive') {
            // Same pattern api/social-content-production/emp-submit-production.php
            // already validates at submission time — re-checked here rather
            // than trusted blindly.
            if (!preg_match('#^https://(drive|docs)\.google\.com/#i', $submissionUrl)) {
                return $this->mediaResult(false, 'INVALID_DRIVE_URL', 'The stored Drive link is not a valid Google Drive/Docs URL.');
            }

            // Locked decision (Architecture Lock, Decision #5): a Drive
            // share link returns Drive's HTML viewer to an unauthenticated
            // fetch, not raw image bytes — Meta's Graph API image_url fetch
            // requires a direct, publicly fetchable media URL. No download,
            // no Drive API integration — this is published manually.
            return $this->mediaResult(false, 'MANUAL_PUBLISH_REQUIRED', 'This item was submitted as a Google Drive link and cannot be published automatically. Publish it manually via the Social Post Composer.');
        }

        if ($submissionType === 'media') {
            if ($submissionUrl === '') {
                return $this->mediaResult(false, 'MISSING_SUBMISSION', 'No production file is on record for this task.');
            }

            $relativePath = ltrim($submissionUrl, '/');
            $fullPath = dirname(__DIR__) . '/' . $relativePath;

            if (!is_file($fullPath)) {
                return $this->mediaResult(false, 'MISSING_FILE', 'The submitted production file could not be found on disk.');
            }

            // Real content-type detection only — never the file extension,
            // never a browser-supplied type.
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = $finfo ? finfo_file($finfo, $fullPath) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            if ($mimeType === 'image/jpeg') {
                return $this->mediaResult(true, 'RESOLVED', 'Media resolved.', 'image', BASE_URL . '/' . $relativePath, $relativePath);
            }

            if ($mimeType === 'image/png') {
                // Verified against includes/InstagramAutomation.php's own
                // saveInstagramMediaFile(): "Meta's Content Publishing API
                // only accepts JPEG for image_url-based posts (image and
                // carousel) — PNG uploads would pass our own validation but
                // fail silently at Meta's end." Rejected here, not
                // converted — no image-processing system exists in this
                // codebase and none is being added for this.
                return $this->mediaResult(false, 'UNSUPPORTED_IMAGE_FORMAT', 'This production file is a PNG. Only JPEG images can currently be sent to Automation.');
            }

            return $this->mediaResult(false, 'NOT_AN_IMAGE', 'The submitted file is not a supported image (its real content type is not JPEG).');
        }

        return $this->mediaResult(false, 'MISSING_SUBMISSION', 'No production submission is on record for this task.');
    }

    private function mediaResult($success, $state, $message, $mediaType = null, $mediaUrl = null, $mediaPath = null)
    {
        return [
            'success' => $success,
            'state' => $state,
            'message' => $message,
            'mediaType' => $mediaType,
            'mediaUrl' => $mediaUrl,   // absolute, informational
            'mediaPath' => $mediaPath, // relative -- the form saveSocialPost()'s mediaPaths expects (scheduler prepends BASE_URL itself at publish time)
        ];
    }

    /**
     * The idempotent handoff-row creation primitive. Not wired to any
     * automatic Phase 4.1 flow — Phase 4.1 has no legitimate source for
     * $instagramAccountId (account resolution is Phase 4.2), so nothing in
     * this phase calls this method with real request data. It exists now,
     * fully working, so later phases only need to supply a resolved
     * account id — no new locking logic to design then.
     *
     * Always re-validates eligibility itself — never trusts a caller's
     * earlier checkEligibility() result, since state can change between
     * the two calls.
     *
     * Concurrency: relies on the UNIQUE(productionId) database constraint,
     * not a SELECT-then-INSERT check — two racing requests both attempt the
     * INSERT; exactly one succeeds. mysqli_report(MYSQLI_REPORT_OFF) is set
     * globally (includes/db.php), so a failed statement does not throw —
     * the duplicate-key case (errno 1062) is detected explicitly via
     * mysqli_errno() and treated as "already handed off", not an error.
     *
     * @throws Exception if $instagramAccountId is not a real, positive id —
     *   this method must never silently accept a fabricated/zero account.
     */
    public function registerHandoff($productionId, $instagramAccountId, $createdBy)
    {
        $productionId = (int)$productionId;
        $instagramAccountId = (int)$instagramAccountId;
        $createdBy = (int)$createdBy;

        if ($instagramAccountId <= 0) {
            throw new Exception('A resolved Instagram account is required before a handoff can be registered.');
        }

        $eligibility = $this->checkEligibility($productionId);
        if (!$eligibility['eligible']) {
            return [
                'success' => false,
                'state' => $eligibility['state'],
                'message' => $eligibility['message'],
                'handoffId' => $eligibility['existingHandoffId'],
            ];
        }

        // Retry: checkEligibility() found a 'failed' row for this task and
        // let it through (see there) — reuse it in place instead of
        // inserting a second one, since productionId stays UNIQUE.
        if ($eligibility['existingHandoffId']) {
            $retryHandoffId = (int)$eligibility['existingHandoffId'];
            $status = 'pending';
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE socialContentAutomationHandoff SET instagramAccountId = ?, status = ?, errorMessage = NULL, socialPostId = NULL, createdBy = ?, updatedAt = NOW() WHERE id = ? AND productionId = ?"
            );
            mysqli_stmt_bind_param($stmt, 'isiii', $instagramAccountId, $status, $createdBy, $retryHandoffId, $productionId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            return [
                'success' => true,
                'state' => 'RETRYING',
                'message' => 'Automation handoff retry registered.',
                'handoffId' => $retryHandoffId,
            ];
        }

        $status = 'pending';
        $stmt = mysqli_prepare(
            $this->con,
            'INSERT INTO socialContentAutomationHandoff (productionId, instagramAccountId, status, createdBy, createdAt) VALUES (?, ?, ?, ?, NOW())'
        );
        mysqli_stmt_bind_param($stmt, 'iisi', $productionId, $instagramAccountId, $status, $createdBy);
        $executed = mysqli_stmt_execute($stmt);

        if (!$executed) {
            $errno = mysqli_errno($this->con);
            mysqli_stmt_close($stmt);

            if ($errno === 1062) {
                // A concurrent request won the race — not an error, just
                // the outcome the UNIQUE constraint exists to guarantee.
                $existing = $this->findExistingHandoff($productionId);
                return [
                    'success' => false,
                    'state' => 'ALREADY_HANDED_OFF',
                    'message' => 'Automation handoff is already registered for this production task.',
                    'handoffId' => $existing ? (int)$existing['id'] : null,
                ];
            }

            return [
                'success' => false,
                'state' => 'FAILED',
                'message' => 'Failed to register the automation handoff. Please try again.',
                'handoffId' => null,
            ];
        }

        $handoffId = (int)mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return [
            'success' => true,
            'state' => 'CREATED',
            'message' => 'Automation handoff registered.',
            'handoffId' => $handoffId,
        ];
    }

    private function findExistingHandoff($productionId)
    {
        $productionId = (int)$productionId;
        $stmt = mysqli_prepare($this->con, 'SELECT id, status, socialPostId FROM socialContentAutomationHandoff WHERE productionId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $productionId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    private function ineligible($state, $message, $task = null)
    {
        return [
            'eligible' => false,
            'state' => $state,
            'message' => $message,
            'clientId' => $task ? (int)$task['clientId'] : null,
            'platformName' => $task ? $task['platformName'] : null,
            'task' => $task,
            'existingHandoffId' => null,
            'resolvedAccountId' => null,
            'selectedCaption' => null,
        ];
    }
}
