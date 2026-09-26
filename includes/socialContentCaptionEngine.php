<?php

require_once __DIR__ . '/SocialContentProductionEngine.php';
require_once __DIR__ . '/AI/CaptionGeneratorFactory.php';

/*
|--------------------------------------------------------------------------
| Social Content Caption Engine — Phase 1 foundation
|--------------------------------------------------------------------------
|
| Sits between Production approval and (future) Automation:
|   Social Content Production -> Approved Content -> Caption Area ->
|   Caption Selected -> Future Automation
|
| Social Content only (sourceType='social') -- Other Content and Other
| Graphic Content are out of scope and never touched by this engine.
|
| Read-only composition with SocialContentProductionEngine (this engine
| never writes to socialContentProduction, never duplicates its query
| logic) -- mirrors the same one-way dependency direction
| SocialAutomationHandoffEngine already established for a different
| downstream concern.
|
| Phase 2: generateCaption() calls the provider-independent AI abstraction
| (includes/AI/CaptionGeneratorFactory.php) instead of writing placeholder
| text. This file never talks to a concrete AI provider (e.g. Anthropic)
| directly -- only to CaptionGeneratorInterface via the factory. Every
| other concern (eligibility, schema, select/save flow) is unchanged from
| Phase 1.
|
| Phase 3 (Production Queue refinements): a task is only eligible here
| once SocialContentProductionEngine::sendToCaption() has explicitly set
| socialContentProduction.sentToCaptionAt -- reviewStatus being approved
| is necessary but no longer sufficient on its own (checked in getQueue()
| and assertEligible()). Pre-existing tasks that already had a caption row
| before this changed were grandfathered via a one-time migration backfill
| (database/migrations/2026-09-23-production-send-to-caption-lock.sql),
| not touched by this file.
|
*/

class SocialContentCaptionEngine
{
    private $con;

    // Only these two reviewStatus values make a Social Content task
    // eligible for captioning. Everything else (Open, Approval Pending
    // From Client, Not Approved, Not For Use) is excluded.
    public const ELIGIBLE_REVIEW_STATUSES = ['Approved By Team', 'Approved By Client'];

    public const STATUSES = ['pending', 'selected'];

    // Regenerations only -- the initial generation (no caption row yet)
    // never counts against this. Enforced in generateCaption() itself,
    // not just hidden/disabled in the UI.
    public const MAX_REGENERATIONS = 3;

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * Eligible Social Content tasks (approved review status only) plus
     * each one's caption status, if any. Built on top of
     * SocialContentProductionEngine::listForManager() -- never re-queries
     * socialContentProduction/clientSocialContent directly, so Production's
     * own read logic is never duplicated.
     *
     * @param array $filters clientId, platformId, fromDate, toDate (same
     *   shape listForManager() already accepts) -- status/editorId/source/
     *   overdue are intentionally not exposed here, since this queue is
     *   already scoped to a fixed reviewStatus set and a fixed source.
     */
    public function getQueue($filters = [])
    {
        $productionEngine = new SocialContentProductionEngine($this->con);
        $allowedFilters = array_intersect_key($filters, array_flip(['clientId', 'platformId', 'fromDate', 'toDate']));
        $tasks = $productionEngine->listForManager(array_merge($allowedFilters, ['source' => 'social']));

        $eligible = array_values(array_filter($tasks, static function ($task) {
            return in_array($task['reviewStatus'], SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES, true)
                && !empty($task['sentToCaptionAt']);
        }));

        if (!$eligible) {
            return [];
        }

        $ids = array_map(static fn($t) => (int)$t['id'], $eligible);
        $captions = $this->getCaptionsByProductionIds($ids);

        foreach ($eligible as &$task) {
            $caption = $captions[(int)$task['id']] ?? null;
            // null = no caption row yet ("Caption Not Started" in the UI);
            // otherwise the row's own status ('pending'/'selected').
            $task['captionStatus'] = $caption ? $caption['status'] : null;
        }
        unset($task);

        return $eligible;
    }

    private function getCaptionsByProductionIds(array $productionIds)
    {
        $productionIds = array_values(array_unique(array_map('intval', $productionIds)));
        if (!$productionIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productionIds), '?'));
        $types = str_repeat('i', count($productionIds));
        $stmt = mysqli_prepare($this->con, "SELECT id, productionId, status FROM socialContentCaption WHERE productionId IN ($placeholders)");
        $bindParams = [$stmt, $types];
        foreach ($productionIds as $key => $val) {
            $bindParams[] = &$productionIds[$key];
        }
        call_user_func_array('mysqli_stmt_bind_param', $bindParams);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $byProductionId = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $byProductionId[(int)$row['productionId']] = $row;
        }
        mysqli_stmt_close($stmt);

        return $byProductionId;
    }

    public function getCaptionByProductionId($productionId)
    {
        $productionId = (int)$productionId;
        $stmt = mysqli_prepare($this->con, 'SELECT id, productionId, prompt, captionOptionOne, captionOptionTwo, selectedCaption, regenerationCount, status, createdBy, createdAt, updatedAt FROM socialContentCaption WHERE productionId = ?');
        mysqli_stmt_bind_param($stmt, 'i', $productionId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ? $this->castCaption($row) : null;
    }

    /**
     * Full content detail for the Caption modal — the production task
     * itself (client/platform/posting-type/title/brief), read unchanged
     * via SocialContentProductionEngine::getTask(), plus this task's own
     * caption row (if any). Throws if the task doesn't exist, isn't Social
     * Content, or isn't at an eligible reviewStatus -- the modal never
     * opens for content that shouldn't be captioned yet, regardless of
     * what the browser requests.
     *
     * @throws Exception
     */
    public function getTaskWithCaption($productionId)
    {
        $task = $this->assertEligible($productionId);
        $task['caption'] = $this->getCaptionByProductionId($productionId);

        return $task;
    }

    /**
     * Calls the AI abstraction (CaptionGeneratorFactory) with structured
     * content context + the admin's prompt, then upserts the two returned
     * options for this production task. Regenerating (calling this again
     * on an already-'selected' row) resets status back to 'pending' and
     * clears selectedCaption, since a fresh generation invalidates
     * whichever option was previously picked -- the admin must explicitly
     * select again.
     *
     * The AI call happens before any database write. If it throws for any
     * reason (not configured, provider error, timeout, invalid/empty
     * response), this method throws too and no row is inserted or
     * updated -- a failed generation never touches a previously saved
     * caption.
     *
     * @throws Exception if the task isn't eligible, the prompt is blank,
     *   or generation fails (message is safe to show the admin directly)
     */
    public function generateCaption($productionId, $prompt, $userId)
    {
        $productionId = (int)$productionId;
        $task = $this->assertEligible($productionId);

        $prompt = trim((string)$prompt);
        if ($prompt === '') {
            throw new Exception('Enter a prompt before generating a caption.');
        }

        $existing = $this->getCaptionByProductionId($productionId);

        // Regeneration limit -- checked before the AI call, not just
        // hidden/disabled client-side. Only a *regeneration* (a row
        // already exists) is counted/limited; the very first generation
        // for a task is never blocked here.
        if ($existing && (int)$existing['regenerationCount'] >= self::MAX_REGENERATIONS) {
            throw new Exception('Caption regeneration limit reached.');
        }

        $context = $this->buildAiContext($task);
        $options = CaptionGeneratorFactory::make()->generate($context, $prompt);

        $optionOne = $options['optionOne'];
        $optionTwo = $options['optionTwo'];
        $status = 'pending';
        $userId = (int)$userId;

        if ($existing) {
            // Count only this successful regeneration -- reaching this
            // statement means the AI call above already succeeded.
            $stmt = mysqli_prepare($this->con, "UPDATE socialContentCaption SET prompt = ?, captionOptionOne = ?, captionOptionTwo = ?, selectedCaption = NULL, regenerationCount = regenerationCount + 1, status = ?, updatedAt = NOW() WHERE productionId = ?");
            mysqli_stmt_bind_param($stmt, 'ssssi', $prompt, $optionOne, $optionTwo, $status, $productionId);
        } else {
            $stmt = mysqli_prepare($this->con, "INSERT INTO socialContentCaption (productionId, prompt, captionOptionOne, captionOptionTwo, status, createdBy, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            mysqli_stmt_bind_param($stmt, 'issssi', $productionId, $prompt, $optionOne, $optionTwo, $status, $userId);
        }
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to generate caption: ' . $error);
        }
        mysqli_stmt_close($stmt);

        return $this->getCaptionByProductionId($productionId);
    }

    /**
     * Manual edit of the already-selected caption text -- no AI call, no
     * effect on regenerationCount (that only ever tracks
     * generateCaption() regenerations, a separate concern). Requires a
     * caption row that is already status='selected'; keeps status
     * 'selected' and simply overwrites selectedCaption with the admin's
     * own edited text, which is what Send to Automation/Automation Queue
     * already read (SocialAutomationHandoffEngine reads
     * socialContentCaption.selectedCaption unchanged).
     *
     * @throws Exception if the task isn't eligible, nothing is selected
     *   yet, or the edited text is blank
     */
    public function updateSelectedCaption($productionId, $text, $userId)
    {
        $productionId = (int)$productionId;
        $this->assertEligible($productionId);

        $text = trim((string)$text);
        if ($text === '') {
            throw new Exception('Caption text cannot be empty.');
        }

        $caption = $this->getCaptionByProductionId($productionId);
        if (!$caption || $caption['status'] !== 'selected') {
            throw new Exception('Select a caption before editing it.');
        }

        $stmt = mysqli_prepare($this->con, "UPDATE socialContentCaption SET selectedCaption = ?, updatedAt = NOW() WHERE productionId = ?");
        mysqli_stmt_bind_param($stmt, 'si', $text, $productionId);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save caption: ' . $error);
        }
        mysqli_stmt_close($stmt);

        return $this->getCaptionByProductionId($productionId);
    }

    /**
     * Admin picks option 'one' or 'two' (from the pair generateCaption()
     * already saved). Copies that option's text into selectedCaption and
     * marks status='selected' -- this is the only place status ever
     * becomes 'selected'. Does not touch socialContentProduction or
     * trigger anything in Automation; this content is simply now ready
     * for a future phase to pick up.
     *
     * @throws Exception if the task isn't eligible, no caption has been
     *   generated yet, or the chosen option is blank
     */
    public function selectCaption($productionId, $option, $userId)
    {
        $productionId = (int)$productionId;
        $this->assertEligible($productionId);

        if (!in_array($option, ['one', 'two'], true)) {
            throw new Exception('Invalid caption option.');
        }

        $caption = $this->getCaptionByProductionId($productionId);
        if (!$caption) {
            throw new Exception('Generate a caption before selecting one.');
        }

        $selected = $option === 'one' ? $caption['captionOptionOne'] : $caption['captionOptionTwo'];
        if ($selected === null || trim((string)$selected) === '') {
            throw new Exception('That caption option is empty.');
        }

        $status = 'selected';
        $stmt = mysqli_prepare($this->con, "UPDATE socialContentCaption SET selectedCaption = ?, status = ?, updatedAt = NOW() WHERE productionId = ?");
        mysqli_stmt_bind_param($stmt, 'ssi', $selected, $status, $productionId);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to select caption: ' . $error);
        }
        mysqli_stmt_close($stmt);

        return $this->getCaptionByProductionId($productionId);
    }

    // Re-verifies eligibility server-side on every mutating call -- never
    // trusts that a task id came from this engine's own getQueue() output.
    // Returns the task (from SocialContentProductionEngine::getTask(),
    // unchanged) so callers that also need its content fields don't have
    // to fetch it twice.
    private function assertEligible($productionId)
    {
        $productionId = (int)$productionId;
        if ($productionId <= 0) {
            throw new Exception('A production task is required.');
        }

        $task = (new SocialContentProductionEngine($this->con))->getTask($productionId);
        if (!$task) {
            throw new Exception('Production task not found.');
        }
        if ($task['sourceType'] !== 'social') {
            throw new Exception('Caption Area only supports Social Content.');
        }
        if (!in_array($task['reviewStatus'], self::ELIGIBLE_REVIEW_STATUSES, true)) {
            throw new Exception('This content has not been approved yet.');
        }
        // Explicit Production Queue -> Caption Area handoff
        // (SocialContentProductionEngine::sendToCaption()) — a task no
        // longer becomes visible/actionable here automatically just by
        // reaching an approved reviewStatus.
        if (empty($task['sentToCaptionAt'])) {
            throw new Exception('This content has not been sent to Caption Area yet.');
        }

        return $task;
    }

    // Maps a task (from SocialContentProductionEngine::getTask(), Social
    // Content fields only -- this engine never handles any other source)
    // to the structured context CaptionGeneratorInterface::generate()
    // expects. Posting Type (Post/Story) is derived the same way the rest
    // of this module already does; Content Format is the stored postType
    // value itself -- see docs/SOCIAL_CONTENT_PRODUCTION_FOUNDATION.md's
    // "Post Type classification" section on why that column already *is*
    // the content format, with Posting Type only ever derived from it.
    private function buildAiContext(array $task)
    {
        $postType = trim((string)($task['postType'] ?? ''));
        $postingType = '';
        if ($postType !== '') {
            $postingType = (stripos($postType, 'story') !== false) ? 'Story' : 'Post';
        }

        return [
            'clientName' => (string)($task['clientName'] ?? ''),
            'businessCategory' => $this->getBusinessCategory($task['clientId'] ?? null),
            'platform' => (string)($task['platformName'] ?? ''),
            'postingType' => $postingType,
            'contentFormat' => $postType,
            'contentPurpose' => (string)($task['featureName'] ?? ''),
            'contentTitle' => (string)($task['title'] ?? ''),
            'rawContent' => (string)($task['rawContent'] ?? ''),
            'contentDescription' => (string)($task['contentDescription'] ?? ''),
            'contentNote' => (string)($task['contentRemarks'] ?? ''),
            'reference' => (string)($task['referenceLink'] ?? ''),
        ];
    }

    // AI-context enrichment only -- read-only, additive lookup scoped to
    // this engine (never touches SocialContentProductionEngine::getTask(),
    // which every other Production/Caption/Automation consumer already
    // relies on unchanged). Reuses the existing leads.categoryId ->
    // leadcategories.categoryName link already used for lead
    // classification elsewhere in this app -- no new column, no new
    // table. Returns '' (omitted from the prompt, same as any other blank
    // context field) when the client's lead has no category assigned,
    // which is common today since this field isn't populated for every
    // client.
    private function getBusinessCategory($clientId)
    {
        $clientId = (int)$clientId;
        if ($clientId <= 0) {
            return '';
        }

        $stmt = mysqli_prepare(
            $this->con,
            'SELECT lc.categoryName
             FROM clientMaster cm
             INNER JOIN leads l ON l.id = cm.leadId
             LEFT JOIN leadcategories lc ON lc.id = l.categoryId
             WHERE cm.id = ?'
        );
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return trim((string)($row['categoryName'] ?? ''));
    }

    private function castCaption($row)
    {
        $row['id'] = (int)$row['id'];
        $row['productionId'] = (int)$row['productionId'];
        $row['createdBy'] = $row['createdBy'] !== null ? (int)$row['createdBy'] : null;
        $row['regenerationCount'] = (int)($row['regenerationCount'] ?? 0);
        $row['regenerationsRemaining'] = max(0, self::MAX_REGENERATIONS - $row['regenerationCount']);
        return $row;
    }
}
