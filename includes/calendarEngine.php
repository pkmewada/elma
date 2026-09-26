<?php

/*
|--------------------------------------------------------------------------
| Calendar Engine
|--------------------------------------------------------------------------
|
| All queries now use the renamed camelCase columns:
| - clientId, platformId, featureId, selectedDates, month, isEdited, createdAt, updatedAt
| - month stored as YYYY-MM (e.g., "2026-08")
|
*/

require_once __DIR__ . '/deliverableEngine.php';

class CalendarEngine
{
    private $con;
    private $deliverableEngine;

    public function __construct($con)
    {
        $this->con = $con;
        $this->deliverableEngine = new DeliverableEngine($con);
    }

    /**
     * Get calendar data for clients
     *
     * @param int|null $clientId
     * @param int|null $platformId
     * @param int      $monthIndex (0 = current month, negative = past, positive = future)
     * @return array
     */
    public function getCalendarData($clientId = null, $platformId = null, $monthIndex = 0)
    {
        $currentDate = new DateTime();
        if ($monthIndex !== 0) {
            $currentDate->modify("{$monthIndex} months");
        }
        $yearMonth = $currentDate->format('Y-m'); // e.g., "2026-08"

        $clients = $this->getClients($clientId);

        foreach ($clients as &$client) {
            $platforms = $this->getClientDeliverables($client['id'], $platformId, $yearMonth);
            $client['platforms'] = $platforms;

            $total = 0;
            foreach ($platforms as $p) {
                foreach ($p['features'] as $f) {
                    $total += $f['plannedCount'];
                }
            }
            $client['total_deliverables'] = $total;

            // Get saved calendar plans for this client and month (YYYY-MM)
            $savedPlans = $this->getSavedPlans($client['id'], $yearMonth);
            $client['saved_plans'] = $savedPlans;
        }

        return [
            'data' => $clients,
            'month' => $yearMonth, // YYYY-MM
            'filters' => $this->getFilterOptions()
        ];
    }

    /**
     * Get active clients
     */
    private function getClients($clientId = null)
    {
        $sql = "SELECT DISTINCT 
                    cm.id, 
                    cm.leadId,
                    l.fullName as client_name,
                    l.email,
                    l.phone
                FROM clientMaster cm
                JOIN leads l ON cm.leadId = l.id
                WHERE cm.status = 'active'";
        if ($clientId) {
            $sql .= " AND cm.id = " . (int)$clientId;
        }
        $result = mysqli_query($this->con, $sql);
        if (!$result) return [];
        $clients = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $clients[] = $row;
        }
        return $clients;
    }

    /**
     * Get deliverables (platform + feature + plannedCount) for a client.
     * Delegates to DeliverableEngine so the "set up once, carries forward"
     * rule matches the client-deliverable page exactly.
     */
    private function getClientDeliverables($clientId, $platformId, $yearMonth)
    {
        return $this->deliverableEngine->getClientDeliverablesGrouped($clientId, $yearMonth, $platformId);
    }

    /**
     * Get saved calendar plans (includes isEdited). Public so
     * SocialCalendarPlanningEngine can read existing dates for one client
     * before generating suggestions for the remaining gap.
     */
    public function getSavedPlans($clientId, $month)
    {
        $sql = "SELECT platformId, featureId, selectedDates, removedDates, isEdited
                FROM clientCalendarPlans
                WHERE clientId = " . (int)$clientId . "
                  AND month = '" . mysqli_real_escape_string($this->con, $month) . "'";
        $result = mysqli_query($this->con, $sql);
        if (!$result) return [];

        $plans = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $key = $row['platformId'] . '_' . $row['featureId'];
            $plans[$key] = [
                'dates' => $row['selectedDates'] ? (json_decode($row['selectedDates'], true) ?: []) : [],
                'removedDates' => $row['removedDates'] ? (json_decode($row['removedDates'], true) ?: []) : [],
                'isEdited' => $row['isEdited'] // 'yes' or 'no'
            ];
        }
        return $plans;
    }

    /**
     * Get a single client's deliverables + saved calendar dates for one month.
     * Used to refresh the Plan modal when the month is switched.
     */
    public function getClientCalendarPlan($clientId, $month, $platformId = null)
    {
        return [
            'platforms' => $this->getClientDeliverables($clientId, $platformId, $month),
            'saved_plans' => $this->getSavedPlans($clientId, $month)
        ];
    }

        /**
         * Log calendar activity
         */
        private function logActivity($clientId, $platformId, $featureId, $month, $action, $oldDates, $newDates, $oldIsEdited = null, $newIsEdited = null)
        {
            $userId = isset($_SESSION['userId']) ? (int)$_SESSION['userId'] : 0;
            $oldDatesJson = $oldDates !== null ? json_encode($oldDates) : null;
            $newDatesJson = json_encode($newDates);
            $stmt = mysqli_prepare($this->con, "
                INSERT INTO clientCalendarActivityLog 
                (clientId, platformId, featureId, month, action, oldDates, newDates, oldIsEdited, newIsEdited, userId, createdAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            if (!$stmt) return;
            mysqli_stmt_bind_param($stmt, 'iiissssssi', $clientId, $platformId, $featureId, $month, $action, $oldDatesJson, $newDatesJson, $oldIsEdited, $newIsEdited, $userId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
            
    
    
    
    
    
    
    

    /*
    * Save calendar plan (handles isEdited)
    */
   public function saveCalendarPlan($clientId, $plans)
    {
        if (empty($plans)) return true;
    
        mysqli_begin_transaction($this->con);
    
        try {
            foreach ($plans as $plan) {
                $platformId = (int)$plan['platformId'];
                $featureId  = (int)$plan['featureId'];
                $newDates   = $plan['dates'];
                $month      = trim($plan['month']);
                $newIsEdited = isset($plan['isEdited']) ? trim($plan['isEdited']) : 'yes';
                if (!in_array($newIsEdited, ['yes', 'no'])) {
                    $newIsEdited = 'yes';
                }
    
                // Fetch existing record to get old values
                $checkSql = "SELECT id, selectedDates, isEdited 
                             FROM clientCalendarPlans 
                             WHERE clientId = ? AND platformId = ? AND featureId = ? AND month = ?";
                $stmt = mysqli_prepare($this->con, $checkSql);
                mysqli_stmt_bind_param($stmt, 'iiis', $clientId, $platformId, $featureId, $month);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $existing = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
    
                if ($existing) {
                    // Old values
                    $oldDates = $existing['selectedDates'] ? (json_decode($existing['selectedDates'], true) ?: []) : [];
                    $oldIsEdited = $existing['isEdited'];

                    // Dates that were dropped in this edit (shown as a red capsule)
                    $removedDates = array_values(array_diff($oldDates, $newDates));

                    // Update
                    $datesJson = json_encode($newDates);
                    $removedDatesJson = json_encode($removedDates);
                    $sql = "UPDATE clientCalendarPlans
                            SET selectedDates = ?, removedDates = ?, isEdited = ?, updatedAt = NOW()
                            WHERE id = ?";
                    $stmt = mysqli_prepare($this->con, $sql);
                    mysqli_stmt_bind_param($stmt, 'sssi', $datesJson, $removedDatesJson, $newIsEdited, $existing['id']);
                    $success = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
    
                    if ($success) {
                        // Log update
                        $this->logActivity($clientId, $platformId, $featureId, $month, 'UPDATE', $oldDates, $newDates, $oldIsEdited, $newIsEdited);
                    }
                } else {
                    // Insert
                    $datesJson = json_encode($newDates);
                    $sql = "INSERT INTO clientCalendarPlans 
                            (clientId, platformId, featureId, selectedDates, month, isEdited, createdAt, updatedAt) 
                            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
                    $stmt = mysqli_prepare($this->con, $sql);
                    mysqli_stmt_bind_param($stmt, 'iiisss', $clientId, $platformId, $featureId, $datesJson, $month, $newIsEdited);
                    $success = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
    
                    if ($success) {
                        // Log insert
                        $this->logActivity($clientId, $platformId, $featureId, $month, 'INSERT', null, $newDates, null, $newIsEdited);
                    }
                }
            }
    
            mysqli_commit($this->con);
            return true;
        } catch (Exception $e) {
            mysqli_rollback($this->con);
            throw $e;
        }
    }

    /**
     * Every client's already-planned dates for a month, flattened into a
     * single per-date load count — used by SocialCalendarPlanningEngine to
     * build its cross-client load map. Pure read, no filtering by whitelist
     * (the load map's purpose is raw workload, not planning eligibility).
     *
     * @return array<string,int> 'YYYY-MM-DD' => number of already-planned slots
     */
    public function getMonthWorkload($month)
    {
        $stmt = mysqli_prepare($this->con, "SELECT selectedDates FROM clientCalendarPlans WHERE month = ?");
        mysqli_stmt_bind_param($stmt, 's', $month);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $loadMap = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $dates = $row['selectedDates'] ? (json_decode($row['selectedDates'], true) ?: []) : [];
            foreach ($dates as $date) {
                $loadMap[$date] = ($loadMap[$date] ?? 0) + 1;
            }
        }
        mysqli_stmt_close($stmt);

        return $loadMap;
    }

    /**
     * Get filter options (clients and platforms)
     */
    public function getFilterOptions()
    {
        $filters = [];

        $result = mysqli_query($this->con, "
            SELECT cm.id, l.fullName as client_name 
            FROM clientMaster cm 
            JOIN leads l ON cm.leadId = l.id 
            WHERE cm.status = 'active' 
            ORDER BY l.fullName
        ");
        $filters['clients'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $filters['clients'][] = [
                'id' => (int)$row['id'],
                'client_name' => $row['client_name']
            ];
        }

        $result = mysqli_query($this->con, "
            SELECT id, platformName, icon 
            FROM deliverablePlatforms 
            WHERE isActive = 1 
            ORDER BY displayOrder
        ");
        $filters['platforms'] = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $filters['platforms'][] = [
                'id' => (int)$row['id'],
                'platformName' => $row['platformName'],
                'icon' => $row['icon']
            ];
        }

        return $filters;
    }
}