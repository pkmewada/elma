<?php

/*
|--------------------------------------------------------------------------
| Social Calendar Planning Engine
|--------------------------------------------------------------------------
|
| NEW capability (Phase 2 of Social Media Setup) — there was previously no
| automatic calendar date generation; pages/calendar.php only supported
| manual Flatpickr multi-date selection.
|
| generatePlans() computes SUGGESTED dates for one or more clients' month
| and returns them as a preview. It NEVER writes to clientCalendarPlans —
| persistence remains exclusively CalendarEngine::saveCalendarPlan()'s job,
| called later from the existing Calendar UI once a manager has reviewed
| (and optionally adjusted) the suggestion. This engine only reads, via
| DeliverableEngine (effective plannedCount) and CalendarEngine (existing
| saved dates + cross-client workload).
|
| Safety rule (additive-only): for a given client+platform+feature, this
| engine only ever ADDS dates to close the gap between the effective
| plannedCount and however many dates already exist. It never removes or
| replaces an existing date. If a slot is already fully (or over-)planned,
| generation is a no-op for that slot.
|
| Even monthly distribution + load balancing: new dates for a slot are
| chosen by splitting the eligible date pool (valid dates minus this slot's
| existing dates) into as many equal-sized, date-ordered buckets as dates
| are still needed. One bucket per needed date, spread start-to-end across
| the month, is what prevents "first N valid dates get picked" clustering.
| Within a bucket, the date that is farthest (in days) from this slot's
| nearest already-selected date (existing dates + dates already generated
| earlier in the same call) wins; a load tie-break applies when spacing is
| equal, including the common case of a slot's very first pick, where
| nothing has been selected yet and load is the only signal (identical to
| the original least-loaded-wins behavior). This is what avoids an
| avoidable 3-in-a-row cluster when a bucket actually offers a choice,
| while a single-candidate bucket (no choice at all) still yields
| consecutive dates when the month is dense enough to require it. One
| loadMap is built from ALL clients/platforms/features already planned for
| the target month, then shared and updated across every client processed
| in the same generatePlans() call — so multiple clients generated together
| still spread out around each other's load, not just around the calendar.
|
| If a slot's requiredCount exceeds how many valid dates the month
| actually has, the target is capped at the valid date pool size — every
| eligible date gets used once, never duplicated to manufacture extra
| "unique" dates that don't exist.
|
| Weekend exclusion is read from socialMediaPlanningRules.excludeWeekend.
| Holiday exclusion is NOT configurable — it is an always-on rule against
| eventholidaymaster (eventType='holiday' AND status='active'), honoring
| eventEndDate as a range (eventDate <= date <= COALESCE(eventEndDate, eventDate)).
|
*/

require_once __DIR__ . '/deliverableEngine.php';
require_once __DIR__ . '/calendarEngine.php';
require_once __DIR__ . '/socialMediaSetupEngine.php';

class SocialCalendarPlanningEngine
{
    private $con;
    private $deliverableEngine;
    private $calendarEngine;
    private $socialMediaSetupEngine;

    public function __construct($con)
    {
        $this->con = $con;
        $this->deliverableEngine = new DeliverableEngine($con);
        $this->calendarEngine = new CalendarEngine($con);
        $this->socialMediaSetupEngine = new SocialMediaSetupEngine($con);
    }

    /**
     * Compute a generation preview for one or more clients in one month.
     * Read-only — never calls saveCalendarPlan().
     *
     * @param int[]  $clientIds
     * @param string $month 'YYYY-MM'
     * @return array [['clientId'=>, 'month'=>, 'plans'=>[
     *                  ['platformId'=>, 'featureId'=>, 'requiredCount'=>,
     *                   'existingDates'=>[], 'generatedDates'=>[], 'finalDates'=>[]],
     *               ...]], ...]
     */
    public function generatePlans(array $clientIds, $month)
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new Exception('Invalid month format. Expected YYYY-MM');
        }

        $clientIds = array_values(array_unique(array_map('intval', $clientIds)));
        sort($clientIds); // deterministic processing order regardless of input order

        $validDates = $this->buildValidDatePool($month);
        $loadMap = $this->calendarEngine->getMonthWorkload($month);
        foreach ($validDates as $d) {
            if (!isset($loadMap[$d])) {
                $loadMap[$d] = 0;
            }
        }

        $results = [];

        foreach ($clientIds as $clientId) {
            $platforms = $this->deliverableEngine->getClientDeliverablesGrouped($clientId, $month);
            $savedPlans = $this->calendarEngine->getSavedPlans($clientId, $month);

            $clientPlans = [];

            foreach ($platforms as $platform) {
                $platformId = (int)$platform['platform_id'];

                foreach ($platform['features'] as $feature) {
                    $featureId = (int)$feature['feature_id'];
                    $requiredCount = (int)$feature['plannedCount'];

                    $key = $platformId . '_' . $featureId;
                    $existingDates = isset($savedPlans[$key]) ? array_values(array_unique($savedPlans[$key]['dates'])) : [];
                    sort($existingDates);

                    // Cap the target at how many valid dates the month actually
                    // has -- requiring more unique dates than exist is not a
                    // failure, it just means "use every eligible date".
                    $targetCount = min($requiredCount, count($validDates));
                    $remainingNeeded = $targetCount - count($existingDates);
                    $generatedDates = [];

                    if ($remainingNeeded > 0) {
                        $candidatePool = array_values(array_diff($validDates, $existingDates));
                        $generatedDates = $this->pickEvenlySpreadDates($candidatePool, $remainingNeeded, $loadMap, $existingDates);
                    }

                    $finalDates = array_values(array_unique(array_merge($existingDates, $generatedDates)));
                    sort($finalDates);

                    $clientPlans[] = [
                        'platformId' => $platformId,
                        'featureId' => $featureId,
                        'requiredCount' => $requiredCount,
                        'existingDates' => $existingDates,
                        'generatedDates' => $generatedDates,
                        'finalDates' => $finalDates,
                    ];
                }
            }

            $results[] = [
                'clientId' => $clientId,
                'month' => $month,
                'plans' => $clientPlans,
            ];
        }

        return $results;
    }

    /**
     * Every calendar date in the month, minus weekends (if configured),
     * minus active holiday dates/ranges (always), and minus dates already
     * in the past (always — matches the existing manual Flatpickr picker's
     * own `minDate: new Date()` constraint on pages/calendar.php, so a
     * suggestion generated partway through the current month never offers
     * an already-passed date the UI would silently refuse anyway).
     */
    private function buildValidDatePool($month)
    {
        $rules = $this->socialMediaSetupEngine->getPlanningRules();
        $excludeWeekend = (int)$rules['excludeWeekend'] === 1;
        $holidayRanges = $this->getActiveHolidayRanges($month);
        $today = date('Y-m-d');

        $start = new DateTime($month . '-01');
        $end = (clone $start)->modify('last day of this month');

        $dates = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $dateStr = $cursor->format('Y-m-d');
            $isoWeekday = (int)$cursor->format('N'); // 1=Mon ... 6=Sat, 7=Sun

            $isPast = $dateStr < $today;
            $isWeekend = ($isoWeekday === 6 || $isoWeekday === 7);
            $isHoliday = $this->isHoliday($dateStr, $holidayRanges);

            if (!$isPast && !($excludeWeekend && $isWeekend) && !$isHoliday) {
                $dates[] = $dateStr;
            }

            $cursor->modify('+1 day');
        }

        return $dates;
    }

    /**
     * Active holiday ranges (start, end) that could overlap this month.
     * eventEndDate is honored as a range end; a single-day holiday has
     * eventEndDate = NULL, treated as end = eventDate.
     */
    private function getActiveHolidayRanges($month)
    {
        $monthStart = $month . '-01';

        $stmt = mysqli_prepare($this->con, "
            SELECT eventDate, eventEndDate
            FROM eventholidaymaster
            WHERE eventType = 'holiday'
              AND status = 'active'
              AND eventDate <= LAST_DAY(?)
              AND COALESCE(eventEndDate, eventDate) >= ?
        ");
        if (!$stmt) {
            throw new Exception('Failed to load holiday ranges: ' . mysqli_error($this->con));
        }
        mysqli_stmt_bind_param($stmt, 'ss', $monthStart, $monthStart);
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to load holiday ranges: ' . $error);
        }
        $result = mysqli_stmt_get_result($stmt);

        $ranges = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $ranges[] = [$row['eventDate'], $row['eventEndDate'] ?: $row['eventDate']];
        }
        mysqli_stmt_close($stmt);

        return $ranges;
    }

    private function isHoliday($dateStr, array $ranges)
    {
        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            if ($dateStr >= $rangeStart && $dateStr <= $rangeEnd) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pick $count dates out of $candidatePool (already date-ascending, and
     * already excludes this slot's existing dates) so the picks are spread
     * start-to-end across the pool rather than consuming its earliest
     * entries. $candidatePool is split into $count equal-sized (+/-1),
     * date-ordered buckets — the bucket/distribution constraint is unchanged
     * from before.
     *
     * Within a bucket, the winner is now chosen by: (1) the largest gap in
     * days to the nearest already-selected date for this same slot — both
     * $alreadySelected (this slot's existing dates) and dates already
     * picked earlier in this same call — so a bucket that happens to offer
     * a choice avoids sitting right next to a date already used; (2) lower
     * shared $loadMap load, as the tie-break when spacing is equal (this is
     * also the whole comparison whenever nothing has been selected for this
     * slot yet, e.g. the very first pick of a fresh slot — identical to the
     * previous behavior); (3) earliest date in the bucket as the final,
     * deterministic tie-break (natural array order — no randomness).
     *
     * A bucket with only one candidate still has no real choice to make —
     * consecutive picks stay expected and acceptable there, exactly as the
     * bucket-based foundation already implies for high-density months.
     *
     * $loadMap is mutated in place so callers processing multiple
     * slots/clients in one generatePlans() call keep seeing an up-to-date
     * shared workload signal.
     */
    private function pickEvenlySpreadDates(array $candidatePool, $count, array &$loadMap, array $alreadySelected = [])
    {
        $n = count($candidatePool);
        if ($count <= 0 || $n === 0) {
            return [];
        }

        // Fewer (or exactly as many) candidates than needed -- take every
        // one of them; there is no "which" to choose, only "all of them".
        if ($count >= $n) {
            foreach ($candidatePool as $date) {
                $loadMap[$date] = ($loadMap[$date] ?? 0) + 1;
            }
            return $candidatePool;
        }

        $selectedSoFar = $alreadySelected;
        $picked = [];

        for ($i = 0; $i < $count; $i++) {
            $segStart = (int) floor($i * $n / $count);
            $segEnd = (int) floor(($i + 1) * $n / $count) - 1;

            $best = null;
            $bestGap = -1;
            $bestLoad = null;
            for ($j = $segStart; $j <= $segEnd; $j++) {
                $date = $candidatePool[$j];
                $load = $loadMap[$date] ?? 0;
                $gap = $this->nearestGapDays($date, $selectedSoFar);

                if ($best === null || $gap > $bestGap || ($gap === $bestGap && $load < $bestLoad)) {
                    $best = $date;
                    $bestGap = $gap;
                    $bestLoad = $load;
                }
            }

            $picked[] = $best;
            $selectedSoFar[] = $best;
            $loadMap[$best] = ($loadMap[$best] ?? 0) + 1;
        }

        return $picked;
    }

    /**
     * Smallest day-distance from $date to any date already selected for
     * this same slot. No prior selection at all (a fresh slot's very first
     * pick) means there is nothing to space against yet, so it returns
     * PHP_INT_MAX -- spacing is a non-factor and load decides instead.
     */
    private function nearestGapDays($date, array $selected)
    {
        if (empty($selected)) {
            return PHP_INT_MAX;
        }

        $target = strtotime($date);
        $best = PHP_INT_MAX;
        foreach ($selected as $s) {
            $diff = (int) abs(($target - strtotime($s)) / 86400);
            if ($diff < $best) {
                $best = $diff;
            }
        }

        return $best;
    }
}
