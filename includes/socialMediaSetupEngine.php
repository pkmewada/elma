<?php

/*
|--------------------------------------------------------------------------
| Social Media Setup Engine
|--------------------------------------------------------------------------
|
| Owns the two Social Media Setup configuration tables:
| - socialMediaFeatureConfig: which platformId+featureId combinations are
|   allowed to participate in Social Media planning (default-deny — see
|   getEnabledFeatureConfig()). Replaces the old hardcoded whitelist that
|   used to live in DeliverableEngine.
| - socialMediaPlanningRules: single-row calendar generation settings
|   (currently just excludeWeekend). Holiday exclusion is NOT stored here —
|   it is an always-on rule enforced in SocialCalendarPlanningEngine
|   against eventholidaymaster, so there is nothing to configure for it.
|
| DeliverableEngine consumes getEnabledFeatureConfig() to answer
| isSocialFeatureAllowed() — this is the single place that reads
| socialMediaFeatureConfig from the database.
|
*/

class SocialMediaSetupEngine
{
    private $con;

    public function __construct($con)
    {
        $this->con = $con;
    }

    /**
     * Every active platform/feature from the master data, with its current
     * enabled state (false when no socialMediaFeatureConfig row exists —
     * default-deny). Shape matches what a Setup UI grid needs: grouped by
     * platform, in display order. No platform/feature name or id is
     * hardcoded — this is built entirely from deliverablePlatforms /
     * deliverableFeatures.
     *
     * @return array [['platformId'=>, 'platformName'=>, 'icon'=>, 'features'=>
     *                 [['featureId'=>, 'featureName'=>, 'isEnabled'=>bool], ...]], ...]
     */
    public function getFeatureConfig()
    {
        $sql = "SELECT dp.id AS platformId, dp.platformName, dp.icon, dp.displayOrder AS platformOrder,
                       df.id AS featureId, df.featureName, df.displayOrder AS featureOrder,
                       c.isEnabled
                FROM deliverablePlatforms dp
                INNER JOIN deliverableFeatures df ON df.platformId = dp.id AND df.isActive = 1
                LEFT JOIN socialMediaFeatureConfig c ON c.platformId = dp.id AND c.featureId = df.id
                WHERE dp.isActive = 1
                ORDER BY dp.displayOrder, df.displayOrder";
        $result = mysqli_query($this->con, $sql);
        if (!$result) return [];

        $platformMap = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $pid = (int)$row['platformId'];
            if (!isset($platformMap[$pid])) {
                $platformMap[$pid] = [
                    'platformId' => $pid,
                    'platformName' => $row['platformName'],
                    'icon' => $row['icon'] ?: 'ri-apps-line',
                    'displayOrder' => (int)$row['platformOrder'],
                    'features' => []
                ];
            }
            $platformMap[$pid]['features'][] = [
                'featureId' => (int)$row['featureId'],
                'featureName' => $row['featureName'],
                'isEnabled' => $row['isEnabled'] !== null && (int)$row['isEnabled'] === 1
            ];
        }

        $platforms = array_values($platformMap);
        usort($platforms, function ($a, $b) { return $a['displayOrder'] <=> $b['displayOrder']; });
        foreach ($platforms as &$p) {
            unset($p['displayOrder']);
        }

        return $platforms;
    }

    /**
     * Only the enabled combinations, as a fast lookup map. This is what
     * DeliverableEngine::isSocialFeatureAllowed() consumes — loaded once
     * per DeliverableEngine instance (i.e. once per request), never once
     * per row.
     *
     * @return array<string,bool> '<platformId>_<featureId>' => true
     */
    public function getEnabledFeatureConfig()
    {
        $map = [];
        $result = mysqli_query($this->con, "
            SELECT platformId, featureId FROM socialMediaFeatureConfig WHERE isEnabled = 1
        ");
        if (!$result) return $map;
        while ($row = mysqli_fetch_assoc($result)) {
            $map[$row['platformId'] . '_' . $row['featureId']] = true;
        }
        return $map;
    }

    /**
     * Enable/disable one or more platform+feature combinations.
     *
     * @param array $items [['platformId'=>, 'featureId'=>, 'isEnabled'=> 0|1], ...]
     * @return array ['success'=>bool, 'message'=>string]
     */
    public function saveFeatureConfig(array $items)
    {
        if (empty($items)) {
            return ['success' => false, 'message' => 'No items provided'];
        }

        mysqli_begin_transaction($this->con);
        try {
            $stmt = mysqli_prepare($this->con, "
                INSERT INTO socialMediaFeatureConfig (platformId, featureId, isEnabled)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE isEnabled = VALUES(isEnabled), updatedAt = NOW()
            ");

            foreach ($items as $item) {
                $platformId = (int)($item['platformId'] ?? 0);
                $featureId = (int)($item['featureId'] ?? 0);
                $isEnabled = !empty($item['isEnabled']) ? 1 : 0;

                if ($platformId <= 0 || $featureId <= 0) {
                    throw new Exception('Invalid platformId/featureId in feature config payload');
                }

                if (!$this->featureBelongsToPlatform($platformId, $featureId)) {
                    throw new Exception("Feature $featureId does not belong to platform $platformId");
                }

                mysqli_stmt_bind_param($stmt, 'iii', $platformId, $featureId, $isEnabled);
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to save feature config: ' . mysqli_error($this->con));
                }
            }
            mysqli_stmt_close($stmt);

            mysqli_commit($this->con);
            return ['success' => true, 'message' => 'Feature configuration saved'];
        } catch (Exception $e) {
            mysqli_rollback($this->con);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function featureBelongsToPlatform($platformId, $featureId)
    {
        $stmt = mysqli_prepare($this->con, "
            SELECT id FROM deliverableFeatures WHERE id = ? AND platformId = ? AND isActive = 1 LIMIT 1
        ");
        mysqli_stmt_bind_param($stmt, 'ii', $featureId, $platformId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return (bool)$row;
    }

    /**
     * @return array ['excludeWeekend' => 0|1]
     */
    public function getPlanningRules()
    {
        $result = mysqli_query($this->con, "SELECT excludeWeekend FROM socialMediaPlanningRules ORDER BY id LIMIT 1");
        $row = $result ? mysqli_fetch_assoc($result) : null;

        return [
            'excludeWeekend' => $row ? (int)$row['excludeWeekend'] : 1
        ];
    }

    /**
     * @return array ['success'=>bool, 'message'=>string]
     */
    public function savePlanningRules($excludeWeekend)
    {
        $excludeWeekend = !empty($excludeWeekend) ? 1 : 0;

        $existing = mysqli_query($this->con, "SELECT id FROM socialMediaPlanningRules ORDER BY id LIMIT 1");
        $row = $existing ? mysqli_fetch_assoc($existing) : null;

        if ($row) {
            $stmt = mysqli_prepare($this->con, "UPDATE socialMediaPlanningRules SET excludeWeekend = ?, updatedAt = NOW() WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'ii', $excludeWeekend, $row['id']);
        } else {
            $stmt = mysqli_prepare($this->con, "INSERT INTO socialMediaPlanningRules (excludeWeekend) VALUES (?)");
            mysqli_stmt_bind_param($stmt, 'i', $excludeWeekend);
        }

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success
            ? ['success' => true, 'message' => 'Planning rules saved']
            : ['success' => false, 'message' => 'Failed to save planning rules: ' . mysqli_error($this->con)];
    }
}
