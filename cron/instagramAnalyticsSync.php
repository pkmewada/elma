<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the CLI.');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/InstagramAutomation.php';
require_once __DIR__ . '/../includes/InstagramInsights.php';

date_default_timezone_set('Asia/Kolkata');

/*
|--------------------------------------------------------------------------
| Separate lock/log from cron/instagramScheduler.php — this cron runs on
| its own (much coarser — daily is recommended) schedule and must never be
| blocked by, or block, the publishing scheduler.
|--------------------------------------------------------------------------
*/

function instagramAnalyticsSyncLog(string $message): void
{
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
    file_put_contents(__DIR__ . '/instagramAnalyticsSync.log', $line, FILE_APPEND);
    echo $line;
}

$lockHandle = fopen(__DIR__ . '/.instagramAnalyticsSync.lock', 'c');

if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
    instagramAnalyticsSyncLog('Another instagramAnalyticsSync run is already in progress. Exiting.');
    exit(0);
}

register_shutdown_function(static function () use ($lockHandle) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
});

$today = date('Y-m-d');
$accountIds = getConnectedInstagramAccountIds($con);

if (!$accountIds) {
    instagramAnalyticsSyncLog('No connected Instagram accounts found. Skipping run.');
    exit(0);
}

$accountsSynced = 0;
$postsSynced = 0;
$accountsFailed = 0;

foreach ($accountIds as $accountId) {
    $account = getInstagramAccountById($con, $accountId);

    if (!$account) {
        continue;
    }

    $clientLabel = getInstagramClientLabel($con, $account['clientId']);

    try {
        $accountMetrics = fetchInstagramAccountInsights($account);

        foreach ($accountMetrics as $metricName => $metricValue) {
            saveInstagramInsightMetric($con, [
                'clientId' => $account['clientId'],
                'instagramAccountId' => $account['id'],
                'postId' => null,
                'metricName' => $metricName,
                'metricValue' => $metricValue,
                'period' => 'day',
                'capturedAt' => $today,
            ]);
        }

        $accountsSynced++;
        markInstagramAccountAnalyticsSync($con, $account['id'], null);
        instagramAnalyticsSyncLog(
            'Account #' . $account['id'] . ' (Client: ' . $clientLabel . ') synced ' . count($accountMetrics) . ' account-level metrics.'
        );
    } catch (Throwable $e) {
        $accountsFailed++;
        markInstagramAccountAnalyticsSync($con, $account['id'], $e->getMessage());
        instagramAnalyticsSyncLog(
            'Account #' . $account['id'] . ' (Client: ' . $clientLabel . ') account-level sync failed: ' . $e->getMessage()
        );
        // Don't attempt post-level sync for an account whose account-level
        // call already failed (e.g. invalid token) — move on to the next account.
        continue;
    }

    $recentPosts = getRecentPublishedInstagramPosts($con, $account['id'], 20);

    foreach ($recentPosts as $post) {
        try {
            $postMetrics = fetchInstagramPostInsights($account, (string)$post['instagramMediaId'], (string)$post['mediaType']);

            foreach ($postMetrics as $metricName => $metricValue) {
                saveInstagramInsightMetric($con, [
                    'clientId' => $account['clientId'],
                    'instagramAccountId' => $account['id'],
                    'postId' => (int)$post['id'],
                    'metricName' => $metricName,
                    'metricValue' => $metricValue,
                    'period' => 'lifetime',
                    'capturedAt' => $today,
                ]);
            }

            $postsSynced++;
        } catch (Throwable $e) {
            instagramAnalyticsSyncLog(
                'Post #' . $post['id'] . ' (Client: ' . $clientLabel . ') post-level sync failed: ' . $e->getMessage()
            );
        }
    }
}

instagramAnalyticsSyncLog(
    'Sync run complete. Accounts synced: ' . $accountsSynced . ', failed: ' . $accountsFailed . ', posts synced: ' . $postsSynced . '.'
);
