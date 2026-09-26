<?php

/*
|--------------------------------------------------------------------------
| Deliverable Engine
|--------------------------------------------------------------------------
|
| Handles:
| - Platforms & Features
| - Client deliverables (pivot data)
| - Save/update planned counts
| - Client service-based platform permissions
*/

require_once __DIR__ . '/socialMediaSetupEngine.php';

class DeliverableEngine
{
    private $con;
    private $socialMediaSetupEngine;

    // lazy-loaded, once per instance (i.e. once per request) — see
    // isSocialFeatureAllowed(). Never re-queried per row.
    private $enabledFeatureConfig = null;

    public function __construct($con)
    {
        $this->con = $con;
        $this->socialMediaSetupEngine = new SocialMediaSetupEngine($con);
    }

    /**
     * Whether a platformId+featureId combination is allowed to participate
     * in Social Media planning. Backed by socialMediaFeatureConfig
     * (default-deny: no row = not allowed) via SocialMediaSetupEngine —
     * replaces the previous hardcoded platform/feature-name whitelist.
     * Shared by the Calendar Planner (getClientDeliverablesGrouped) and the
     * Social Media Data Entry page (SocialContentEngine::getPlan,
     * get-features.php) so the rule lives in exactly one place.
     */
    public function isSocialFeatureAllowed($platformId, $featureId)
    {
        if ($this->enabledFeatureConfig === null) {
            $this->enabledFeatureConfig = $this->socialMediaSetupEngine->getEnabledFeatureConfig();
        }
        return isset($this->enabledFeatureConfig[(int)$platformId . '_' . (int)$featureId]);
    }

    // ------------------------------------------------------------
    // Platform & Feature Helpers
    // ------------------------------------------------------------

    public function getPlatforms()
    {
        $platforms = [];
        $result = mysqli_query($this->con, "
            SELECT id, platformName 
            FROM deliverablePlatforms 
            WHERE isActive = 1 
            ORDER BY displayOrder ASC
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $platforms[] = ['id' => (int)$row['id'], 'name' => $row['platformName']];
        }
        return $platforms;
    }

    public function getPlatformNames()
    {
        $names = [];
        $result = mysqli_query($this->con, "
            SELECT platformName 
            FROM deliverablePlatforms 
            WHERE isActive = 1 
            ORDER BY displayOrder ASC
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $names[] = $row['platformName'];
        }
        return $names;
    }

    public function getPlatformIdByName($name)
    {
        $stmt = mysqli_prepare($this->con, "
            SELECT id FROM deliverablePlatforms 
            WHERE platformName = ? AND isActive = 1 LIMIT 1
        ");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 's', $name);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row ? (int)$row['id'] : null;
    }

    public function getFeatureIdByName($featureName, $platformId)
    {
        $stmt = mysqli_prepare($this->con, "
            SELECT id FROM deliverableFeatures 
            WHERE featureName = ? AND platformId = ? AND isActive = 1 LIMIT 1
        ");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'si', $featureName, $platformId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row ? (int)$row['id'] : null;
    }

    public function getPlatformNameById($id)
    {
        $stmt = mysqli_prepare($this->con, "SELECT platformName FROM deliverablePlatforms WHERE id = ? AND isActive = 1 LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row ? $row['platformName'] : null;
    }

    public function getFeatureNameById($id)
    {
        $stmt = mysqli_prepare($this->con, "SELECT featureName FROM deliverableFeatures WHERE id = ? AND isActive = 1 LIMIT 1");
        if (!$stmt) return null;
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);
        return $row ? $row['featureName'] : null;
    }

    // ------------------------------------------------------------
    // Structure for UI (dropdowns)
    // ------------------------------------------------------------

    public function getDeliverableStructure()
    {
        $platforms = $this->getPlatformNames();
        $platformFeatureMap = [];
        $subFeatures = [];

        $result = mysqli_query($this->con, "
            SELECT 
                dp.platformName,
                df.featureName
            FROM deliverableFeatures df
            INNER JOIN deliverablePlatforms dp ON dp.id = df.platformId
            WHERE df.parentFeatureId IS NULL AND df.isActive = 1
            ORDER BY dp.displayOrder, df.displayOrder
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $platform = $row['platformName'];
            $feature = $row['featureName'];
            if (!isset($platformFeatureMap[$platform])) {
                $platformFeatureMap[$platform] = [];
            }
            $platformFeatureMap[$platform][] = $feature;
        }

        return [
            'platforms' => $platforms,
            'platformFeatureMap' => $platformFeatureMap,
            'subFeatures' => $subFeatures,
            'platformIdMap' => $this->getPlatformIdMap(),
            'featureIdMap' => $this->getFeatureIdMap()
        ];
    }

    public function getPlatformIdMap()
    {
        $map = [];
        $result = mysqli_query($this->con, "SELECT id, platformName FROM deliverablePlatforms WHERE isActive = 1");
        while ($row = mysqli_fetch_assoc($result)) {
            $map[$row['platformName']] = (int)$row['id'];
        }
        return $map;
    }

    public function getFeatureIdMap()
    {
        $map = [];
        $result = mysqli_query($this->con, "
            SELECT df.id, df.featureName, df.platformId
            FROM deliverableFeatures df
            WHERE df.isActive = 1
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $map[$row['platformId']][$row['featureName']] = (int)$row['id'];
        }
        return $map;
    }

    // ------------------------------------------------------------
    // Client Methods
    // ------------------------------------------------------------

    public function getClients()
    {
        $data = [];
        $result = mysqli_query($this->con, "
            SELECT cm.id, cm.clientCode, l.fullName
            FROM clientMaster cm
            INNER JOIN leads l ON l.id = cm.leadId
            ORDER BY l.fullName ASC
        ");
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        return $data;
    }

    public function getClientsWithFilter($clientId = null)
    {
        $sql = "
            SELECT cm.id AS clientMasterId, cm.clientCode, l.fullName AS clientName
            FROM clientMaster cm
            INNER JOIN leads l ON l.id = cm.leadId
            WHERE 1=1
        ";
        if ($clientId > 0) {
            $sql .= " AND cm.id = " . (int)$clientId;
        }
        $sql .= " ORDER BY l.fullName ASC";
        $result = mysqli_query($this->con, $sql);
        if (!$result) return [];
        $clients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[$row['clientMasterId']] = $row;
        }
        return $clients;
    }

    // ------------------------------------------------------------
    // Service-to-Platform Mapping & Permissions
    // ------------------------------------------------------------

    private function getServiceToPlatformsMap()
    {
        return [
            'socialMedia' => ['Instagram', 'Facebook'],
            'youtube'     => ['YouTube'],
            'pinterest'   => ['Pinterest'],
            'twitter'     => ['Twitter'],
            'gmb'         => ['GMB'],
            'googleAds'   => ['Google Ads'],
            'metaAds'     => ['Facebook', 'Instagram'],
            'videos'      => ['Videos']
        ];
    }

    /**
     * Get allowed platform IDs for a client based on selected services.
     */
    public function getClientAllowedPlatforms($clientId)
    {
        $allowedPlatformIds = [];

        // Fetch latest onboarding form
        $stmt = mysqli_prepare($this->con, "
            SELECT selectedServices 
            FROM clientOnboardingForms 
            WHERE clientMasterId = ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        if (!$stmt) return $allowedPlatformIds;
        mysqli_stmt_bind_param($stmt, 'i', $clientId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if (!$row || empty($row['selectedServices'])) {
            return $allowedPlatformIds;
        }

        $selectedServices = json_decode($row['selectedServices'], true);
        if (!is_array($selectedServices) || empty($selectedServices)) {
            return $allowedPlatformIds;
        }

        $map = $this->getServiceToPlatformsMap();
        $platformNames = [];
        foreach ($selectedServices as $service) {
            if (isset($map[$service])) {
                $platformNames = array_merge($platformNames, $map[$service]);
            }
        }
        $platformNames = array_unique($platformNames);
        if (empty($platformNames)) return $allowedPlatformIds;

        // Convert names to IDs
        $placeholders = implode(',', array_fill(0, count($platformNames), '?'));
        $types = str_repeat('s', count($platformNames));
        $stmt = mysqli_prepare($this->con, "
            SELECT id FROM deliverablePlatforms 
            WHERE platformName IN ($placeholders) AND isActive = 1
        ");
        if (!$stmt) return $allowedPlatformIds;
        mysqli_stmt_bind_param($stmt, $types, ...$platformNames);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($result)) {
            $allowedPlatformIds[] = (int)$row['id'];
        }
        mysqli_stmt_close($stmt);

        return $allowedPlatformIds;
    }

    // ------------------------------------------------------------
    // Get Deliverables (Pivot Data)
    // ------------------------------------------------------------

    public function getDeliverables($clientId = null, $month = '')
    {
        $clients = $this->getClientsWithFilter($clientId);
        if (empty($clients)) return [];

        // Fetch every planned row (all months) for these clients so we can
        // resolve the effective value per platform/feature below.
        $sql = "
            SELECT
                cd.clientMasterId,
                cd.platformId,
                cd.featureId,
                cd.plannedCount,
                cd.month,
                dp.platformName,
                df.featureName
            FROM clientDeliverables cd
            INNER JOIN deliverablePlatforms dp ON dp.id = cd.platformId
            INNER JOIN deliverableFeatures df ON df.id = cd.featureId
            WHERE 1=1
        ";
        if ($clientId > 0) {
            $sql .= " AND cd.clientMasterId = " . (int)$clientId;
        }

        $result = mysqli_query($this->con, $sql);
        if (!$result) return [];

        // Group rows by clientMasterId + platform_feature key so we can pick
        // the effective value (exact month, or the original setup as a fallback).
        $grouped = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $key = $row['platformName'] . '_' . $row['featureName'];
            $grouped[$row['clientMasterId']][$key][] = $row;
        }

        $pivotData = [];
        foreach ($clients as $id => $client) {
            $row = [
                'clientMasterId'    => $id,
                'clientCode'        => $client['clientCode'],
                'clientName'        => $client['clientName'],
                'allowedPlatformIds' => $this->getClientAllowedPlatforms($id)
            ];

            if (!empty($month) && isset($grouped[$id])) {
                foreach ($grouped[$id] as $key => $rows) {
                    $value = $this->resolveEffectiveDeliverable($rows, $month);
                    if ($value !== null) {
                        $row[$key] = $value;
                    }
                }
            }

            $pivotData[] = $row;
        }

        return $pivotData;
    }

    /**
     * Same resolution rule as getDeliverables(), but for a single client and
     * grouped by platform (with icon) — the shape the calendar page needs.
     */
    public function getClientDeliverablesGrouped($clientId, $month, $platformId = null)
        {
            $sql = "
                SELECT
                    cd.platformId,
                    cd.featureId,
                    cd.plannedCount,
                    cd.month,
                    dp.platformName,
                    dp.icon,
                    dp.displayOrder AS platformOrder,
                    df.featureName,
                    df.displayOrder AS featureOrder
                FROM clientDeliverables cd
                INNER JOIN deliverablePlatforms dp ON dp.id = cd.platformId
                INNER JOIN deliverableFeatures df ON df.id = cd.featureId
                WHERE cd.clientMasterId = " . (int)$clientId;

            if ($platformId) {
                $sql .= " AND cd.platformId = " . (int)$platformId;
            }

            $result = mysqli_query($this->con, $sql);

            if (!$result) {
                return [];
            }

            $grouped = [];

            while ($row = mysqli_fetch_assoc($result)) {

                // Skip platform/feature combinations not enabled in
                // socialMediaFeatureConfig (see isSocialFeatureAllowed()).
                if (!$this->isSocialFeatureAllowed($row['platformId'], $row['featureId'])) {
                    continue;
                }

                $key = $row['platformId'] . '_' . $row['featureId'];

                $grouped[$key][] = $row;
            }

            $platformMap = [];

            foreach ($grouped as $featureRows) {

                $value = $this->resolveEffectiveDeliverable($featureRows, $month);

                if ($value === null) {
                    continue;
                }

                $first = $featureRows[0];

                $pid = $first['platformId'];

                if (!isset($platformMap[$pid])) {
                    $platformMap[$pid] = [
                        'platform_id' => $pid,
                        'platform_name' => $first['platformName'],
                        'icon' => $first['icon'] ?: 'ri-apps-line',
                        'displayOrder' => (int)$first['platformOrder'],
                        'features' => []
                    ];
                }

                $platformMap[$pid]['features'][] = [
                    'feature_id' => $first['featureId'],
                    'feature_name' => $first['featureName'],
                    'plannedCount' => $value,
                    'displayOrder' => (int)$first['featureOrder']
                ];
            }

            $platforms = array_values($platformMap);

            // Sort platforms
            usort($platforms, function ($a, $b) {
                return $a['displayOrder'] <=> $b['displayOrder'];
            });

            foreach ($platforms as &$p) {

                // Sort features
                usort($p['features'], function ($a, $b) {
                    return $a['displayOrder'] <=> $b['displayOrder'];
                });

                foreach ($p['features'] as &$f) {
                    unset($f['displayOrder']);
                }

                unset($p['displayOrder']);
            }

            return $platforms;
        }

    /**
     * A deliverable is set up once and carries forward to every later month.
     * A specific month keeps its own value if it was explicitly edited;
     * otherwise it falls back to the earliest (original setup) value.
     */
    private function resolveEffectiveDeliverable($rows, $month)
    {
        $exact = null;
        $earliest = null;
        foreach ($rows as $r) {
            if ($r['month'] === $month) {
                $exact = $r;
            }
            if ($earliest === null || $r['month'] < $earliest['month']) {
                $earliest = $r;
            }
        }

        if ($exact) {
            return (int)$exact['plannedCount'];
        }
        if ($earliest && $earliest['month'] <= $month) {
            return (int)$earliest['plannedCount'];
        }
        return null;
    }

    // ------------------------------------------------------------
    // Save Single Cell
    // ------------------------------------------------------------

    public function saveDeliverableCell($data)
    {
        $clientMasterId = (int)$data['clientMasterId'];
        $plannedCount = (int)$data['plannedCount'];
        $month = trim($data['month'] ?? date('Y-m'));

        if ($clientMasterId <= 0) {
            return ['success' => false, 'message' => 'Missing clientMasterId'];
        }

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            if (preg_match('/^\d{4}$/', $month)) {
                $month = $month . '-01';
            } else {
                return ['success' => false, 'message' => 'Invalid month format. Expected YYYY-MM'];
            }
        }

        // Get IDs (from payload or by name)
        $platformId = isset($data['platformId']) ? (int)$data['platformId'] : 0;
        $featureId  = isset($data['featureId']) ? (int)$data['featureId'] : 0;

        if ($platformId === 0 || $featureId === 0) {
            $platformName = trim($data['platform'] ?? '');
            $featureName  = trim($data['feature'] ?? '');
            if (empty($platformName) || empty($featureName)) {
                return ['success' => false, 'message' => 'Missing platform or feature'];
            }
            $platformId = $this->getPlatformIdByName($platformName);
            if (!$platformId) {
                return ['success' => false, 'message' => 'Invalid platform: ' . $platformName];
            }
            $featureId = $this->getFeatureIdByName($featureName, $platformId);
            if (!$featureId) {
                return ['success' => false, 'message' => 'Invalid feature: ' . $featureName . ' for platform ' . $platformName];
            }
        }

        // ---- PERMISSION CHECK ----
        $allowedPlatforms = $this->getClientAllowedPlatforms($clientMasterId);
        if (!in_array($platformId, $allowedPlatforms)) {
            return ['success' => false, 'message' => 'This platform is not enabled for this client'];
        }

        // Check existing record
        $stmt = mysqli_prepare($this->con, "
            SELECT id FROM clientDeliverables 
            WHERE clientMasterId = ? AND platformId = ? AND featureId = ? AND month = ?
        ");
        mysqli_stmt_bind_param($stmt, 'iiis', $clientMasterId, $platformId, $featureId, $month);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $existing = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($existing) {
            $stmt = mysqli_prepare($this->con, "
                UPDATE clientDeliverables 
                SET plannedCount = ?, updatedAt = NOW() 
                WHERE id = ?
            ");
            mysqli_stmt_bind_param($stmt, 'ii', $plannedCount, $existing['id']);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success
                ? ['success' => true, 'message' => 'Updated successfully']
                : ['success' => false, 'message' => 'Update failed: ' . mysqli_error($this->con)];
        } else {
            $stmt = mysqli_prepare($this->con, "
                INSERT INTO clientDeliverables 
                (clientMasterId, platformId, featureId, plannedCount, month)
                VALUES (?, ?, ?, ?, ?)
            ");
            mysqli_stmt_bind_param($stmt, 'iiiis', $clientMasterId, $platformId, $featureId, $plannedCount, $month);
            $success = mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            return $success
                ? ['success' => true, 'message' => 'Inserted successfully']
                : ['success' => false, 'message' => 'Insert failed: ' . mysqli_error($this->con)];
        }
    }

    // ------------------------------------------------------------
    // Utility Methods
    // ------------------------------------------------------------

    public function validateMonth($month) {
        return preg_match('/^\d{4}-\d{2}$/', $month) === 1;
    }

    public function getCurrentMonth() {
        return date('Y-m');
    }

    public function getLastMonths($count = 12) {
        $months = [];
        $current = new DateTime();
        for ($i = 0; $i < $count; $i++) {
            $date = clone $current;
            $date->modify("-$i months");
            $months[] = [
                'value' => $date->format('Y-m'),
                'label' => $date->format('F Y')
            ];
        }
        return $months;
    }

    public function getPlatformIcon($platform) {
        $icons = [
            'Instagram'  => 'instagram-line',
            'LinkedIn'   => 'linkedin-box-line',
            'YouTube'    => 'youtube-line',
            'Telegram'   => 'telegram-line',
            'Facebook'   => 'facebook-circle-line',
            'Pinterest'  => 'pinterest-line',
            'GMB'        => 'google-line',
            'SEO'        => 'search-line',
            'Twitter'    => 'twitter-x-line',
            'Google Ads' => 'google-ads-fill',
            'Videos'     => 'video-line'
        ];
        return $icons[$platform] ?? 'service-line';
    }

    public function getPlatformColor($index) {
        $colors = [
            '#F8E8F0', '#E8F0FA', '#FDE8E8', '#E8F4FA',
            '#6f9bed', '#FDE8EE', '#FDE8E5', '#E8EEF8',
            '#E8E6FF', '#E0F7FA', '#FFF3E0'
        ];
        return $colors[$index % count($colors)];
    }
}