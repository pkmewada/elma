<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/InstagramAutomation.php';

/*
|--------------------------------------------------------------------------
| Phase 3: Analytics
|--------------------------------------------------------------------------
| Every row here is written with a clientId + instagramAccountId already
| resolved from the account being synced (§11 of docs/instagram-automation-
| flow.md) — never derive it from session/UI state. postId is nullable:
| NULL = account-level metric, set = post-level metric.
*/

function ensureInstagramInsightsTable(mysqli $con): void
{
    mysqli_query(
        $con,
        "CREATE TABLE IF NOT EXISTS instagramInsights (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            clientId INT NOT NULL,
            instagramAccountId INT UNSIGNED NOT NULL,
            postId INT UNSIGNED NULL DEFAULT NULL,
            metricName VARCHAR(50) NOT NULL,
            metricValue BIGINT NOT NULL DEFAULT 0,
            period VARCHAR(20) NOT NULL DEFAULT 'day',
            capturedAt DATE NOT NULL,
            createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idxInsightsClientId (clientId),
            KEY idxInsightsAccountId (instagramAccountId),
            KEY idxInsightsPostId (postId),
            KEY idxInsightsMetricDate (metricName, capturedAt)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * One row per (account-or-post, metric, day). Checks for an existing row for
 * that day and updates it rather than relying on a DB-level UNIQUE constraint
 * — MySQL treats NULL (postId) as distinct in unique keys, which would let
 * duplicate account-level rows through on a re-sync of the same day.
 */
function saveInstagramInsightMetric(mysqli $con, array $data): bool
{
    ensureInstagramInsightsTable($con);

    $clientId = (int)($data['clientId'] ?? 0);
    $accountId = (int)($data['instagramAccountId'] ?? 0);
    $postId = isset($data['postId']) && $data['postId'] !== null ? (int)$data['postId'] : null;
    $metricName = (string)($data['metricName'] ?? '');
    $metricValue = (int)($data['metricValue'] ?? 0);
    $period = (string)($data['period'] ?? 'day');
    $capturedAt = (string)($data['capturedAt'] ?? date('Y-m-d'));

    if ($clientId <= 0 || $accountId <= 0 || $metricName === '') {
        return false;
    }

    $stmt = mysqli_prepare(
        $con,
        "SELECT id FROM instagramInsights
         WHERE instagramAccountId = ? AND postId <=> ? AND metricName = ? AND period = ? AND capturedAt = ?
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'iisss', $accountId, $postId, $metricName, $period, $capturedAt);
    mysqli_stmt_execute($stmt);
    $existing = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);

    if ($existing) {
        $existingId = (int)$existing['id'];
        $stmt = mysqli_prepare($con, "UPDATE instagramInsights SET metricValue = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'ii', $metricValue, $existingId);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }

    $stmt = mysqli_prepare(
        $con,
        "INSERT INTO instagramInsights (clientId, instagramAccountId, postId, metricName, metricValue, period, capturedAt)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    mysqli_stmt_bind_param($stmt, 'iiisiss', $clientId, $accountId, $postId, $metricName, $metricValue, $period, $capturedAt);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $ok;
}

/**
 * Ids of every currently-usable connected account, across all clients — the
 * analytics sync cron loops these one at a time, resolving clientId fresh
 * per account via getInstagramAccountById() rather than bulk-decrypting.
 */
function getConnectedInstagramAccountIds(mysqli $con): array
{
    ensureInstagramAccountsTable($con);

    $result = mysqli_query(
        $con,
        "SELECT id FROM instagramAccounts WHERE status = 'connected' AND (tokenExpiry IS NULL OR tokenExpiry > NOW())"
    );
    $ids = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $ids[] = (int)$row['id'];
    }

    return $ids;
}

/**
 * Recently published posts for one specific account — used by the analytics
 * sync to know which posts to fetch post-level insights for. Scoped to
 * instagramAccountId (not just clientId) since a client can have multiple
 * accounts and post-level metrics must never be attributed to the wrong one.
 */
function getRecentPublishedInstagramPosts(mysqli $con, int $accountId, int $limit = 20): array
{
    $stmt = mysqli_prepare(
        $con,
        "SELECT id, mediaType, instagramMediaId
         FROM socialPosts
         WHERE instagramAccountId = ? AND status = 'published' AND instagramMediaId != ''
         ORDER BY publishedAt DESC
         LIMIT ?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $accountId, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $posts = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $posts[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $posts;
}

/**
 * Records the outcome of the most recent analytics sync attempt for one
 * account — admin error visibility (Task 5 of Phase 3.1). Reuses the same
 * "each row carries its own last-known-state" convention as
 * socialPosts.status/errorMessage rather than a separate log table.
 * $errorMessage = null on success (clears any prior error).
 */
function markInstagramAccountAnalyticsSync(mysqli $con, int $accountId, ?string $errorMessage): void
{
    $stmt = mysqli_prepare(
        $con,
        "UPDATE instagramAccounts SET lastAnalyticsSyncAt = NOW(), lastAnalyticsSyncError = ? WHERE id = ?"
    );
    mysqli_stmt_bind_param($stmt, 'si', $errorMessage, $accountId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function getInstagramInsights(mysqli $con, int $clientId, ?int $accountId = null, ?int $postId = null, int $days = 30): array
{
    ensureInstagramInsightsTable($con);

    $where = ['clientId = ?', 'capturedAt >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'];
    $types = 'ii';
    $params = [$clientId, $days];

    if ($accountId !== null) {
        $where[] = 'instagramAccountId = ?';
        $types .= 'i';
        $params[] = $accountId;
    }

    if ($postId !== null) {
        $where[] = 'postId = ?';
        $types .= 'i';
        $params[] = $postId;
    }

    $whereSql = implode(' AND ', $where);
    $stmt = mysqli_prepare(
        $con,
        "SELECT * FROM instagramInsights WHERE {$whereSql} ORDER BY capturedAt DESC, metricName ASC"
    );
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Account-level metrics: follower count comes from the account object
 * itself, not the /insights edge; reach/profile_views come from /insights.
 * Both routed through instagramGraphApiRequest() — no separate curl call.
 */
function fetchInstagramAccountInsights(array $account): array
{
    $profile = instagramGraphApiRequest(
        'https://graph.facebook.com/v19.0/' . $account['instagramUserId'],
        [
            'fields' => 'followers_count,media_count',
            'access_token' => $account['accessToken'],
        ],
        'GET'
    );

    $metrics = [];

    if (isset($profile['followers_count'])) {
        $metrics['followers_count'] = (int)$profile['followers_count'];
    }

    if (isset($profile['media_count'])) {
        $metrics['media_count'] = (int)$profile['media_count'];
    }

    $insightMetrics = fetchInstagramInsightsResilient(
        $account,
        $account['instagramUserId'] . '/insights',
        ['reach', 'profile_views', 'website_clicks', 'total_interactions'],
        ['period' => 'day']
    );

    return array_merge($metrics, $insightMetrics);
}

/**
 * Post-level metrics. Meta's available metric set differs by media type
 * (video/reel supports 'plays', image/carousel doesn't) — callers pass the
 * post's mediaType so we only request metrics Meta will actually accept.
 * 'impressions' is intentionally NOT requested here — Meta has been
 * deprecating it for media-level insights in favor of 'reach'; the
 * resilient per-metric fallback below means it's safe to add back for
 * accounts where it's still available without risking every other metric.
 */
function fetchInstagramPostInsights(array $account, string $mediaId, string $mediaType): array
{
    $metricNames = $mediaType === 'reel'
        ? ['reach', 'plays', 'likes', 'comments', 'saved', 'shares']
        : ['reach', 'likes', 'comments', 'saved', 'shares'];

    return fetchInstagramInsightsResilient($account, $mediaId . '/insights', $metricNames, []);
}

/**
 * Meta's /insights edge fails the ENTIRE request if even one requested
 * metric is invalid/unsupported for that object (deprecated metric, wrong
 * media type, etc.) — a single bad name would otherwise lose every other
 * metric for that account/post. This tries the full batch first (fast
 * path — works whenever every metric is supported), and only on failure
 * falls back to fetching each metric individually, skipping ones that
 * error rather than fabricating a value for them. A transient network
 * failure (InstagramTransientApiException) is NOT retried per-metric here
 * — it's rethrown so the caller's existing transient-vs-permanent handling
 * applies once, not once per metric.
 */
function fetchInstagramInsightsResilient(array $account, string $edge, array $metricNames, array $extraParams): array
{
    $baseParams = array_merge($extraParams, ['access_token' => $account['accessToken']]);
    $url = 'https://graph.facebook.com/v19.0/' . $edge;

    try {
        $response = instagramGraphApiRequest(
            $url,
            array_merge($baseParams, ['metric' => implode(',', $metricNames)]),
            'GET'
        );

        return extractInstagramInsightValues($response);
    } catch (InstagramTransientApiException $e) {
        throw $e;
    } catch (Throwable $e) {
        $metrics = [];

        foreach ($metricNames as $metricName) {
            try {
                $single = instagramGraphApiRequest(
                    $url,
                    array_merge($baseParams, ['metric' => $metricName]),
                    'GET'
                );
                $metrics = array_merge($metrics, extractInstagramInsightValues($single));
            } catch (Throwable $innerException) {
                // This specific metric isn't available for this object —
                // skip it. Never invent/default a value for it.
                continue;
            }
        }

        return $metrics;
    }
}

/**
 * Admin-wide rollup for the main CRM dashboard widget — deliberately NOT
 * client-scoped (unlike every other Instagram query in this module) since
 * it's a read-only summary card, not a stored/attributed record. Never used
 * for anything beyond display; every underlying row still carries its own
 * clientId + instagramAccountId (§8 of docs/instagram-automation-flow.md).
 */
function getInstagramDashboardSummary(mysqli $con): array
{
    ensureInstagramInsightsTable($con);
    ensureInstagramAccountsTable($con);

    $summary = [
        'connectedAccounts' => 0,
        'totalFollowers' => 0,
        'totalReachToday' => 0,
        'totalInteractionsToday' => 0,
    ];

    $accountsResult = mysqli_query($con, "SELECT COUNT(*) AS total FROM instagramAccounts WHERE status = 'connected'");
    $summary['connectedAccounts'] = (int)(mysqli_fetch_assoc($accountsResult)['total'] ?? 0);

    $followersResult = mysqli_query(
        $con,
        "SELECT i.metricValue
         FROM instagramInsights i
         INNER JOIN (
             SELECT instagramAccountId, MAX(capturedAt) AS maxDate
             FROM instagramInsights
             WHERE metricName = 'followers_count' AND postId IS NULL
             GROUP BY instagramAccountId
         ) latest ON latest.instagramAccountId = i.instagramAccountId AND latest.maxDate = i.capturedAt
         WHERE i.metricName = 'followers_count' AND i.postId IS NULL"
    );

    while ($row = mysqli_fetch_assoc($followersResult)) {
        $summary['totalFollowers'] += (int)$row['metricValue'];
    }

    $today = date('Y-m-d');
    $stmt = mysqli_prepare(
        $con,
        "SELECT metricName, SUM(metricValue) AS total
         FROM instagramInsights
         WHERE postId IS NULL AND capturedAt = ? AND metricName IN ('reach', 'total_interactions')
         GROUP BY metricName"
    );
    mysqli_stmt_bind_param($stmt, 's', $today);
    mysqli_stmt_execute($stmt);
    $todayResult = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($todayResult)) {
        if ($row['metricName'] === 'reach') {
            $summary['totalReachToday'] = (int)$row['total'];
        } elseif ($row['metricName'] === 'total_interactions') {
            $summary['totalInteractionsToday'] = (int)$row['total'];
        }
    }

    mysqli_stmt_close($stmt);

    return $summary;
}

function extractInstagramInsightValues(array $response): array
{
    $metrics = [];

    foreach (($response['data'] ?? []) as $metric) {
        $name = (string)($metric['name'] ?? '');
        $values = $metric['values'] ?? [];
        $latest = end($values);

        if ($name !== '' && $latest !== false && isset($latest['value'])) {
            $metrics[$name] = (int)$latest['value'];
        }
    }

    return $metrics;
}
