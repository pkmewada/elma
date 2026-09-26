<?php

date_default_timezone_set('Asia/Kolkata');

class PayrollEngine
{
    private mysqli $con;

    private const DEFAULT_SETTINGS = [
        'payrollCycleType' => 'monthly',
        'cycleStartDay' => 1,
        'cycleEndDay' => 31,
        'paidDaysBasis' => 'calendar',
        'fixedPaidDays' => 30,
        'monthlyPaidLeaveDays' => 0,
        'monthlyPaidLeaveCarryForward' => 0,
        'monthlyPaidLeaveCarryForwardLimit' => 0,
        'enableBirthdayPaidLeave' => 1,
        'prorateSalaryFromJoiningDate' => 1,
        'enableProbationLeaveRule' => 1,
        'probationDays' => 30,
        'probationLeaveDeductionPercent' => 100,
        'enableNoticePeriodLeaveRule' => 0,
        'noticeLeaveDeductionPercent' => 100,
        'includePointPayrollDeduction' => 0,
        'pointDeductionThreshold' => 100,
        'pointDeductionPercent' => 10,
        'compoundPointDeduction' => 1,
        'includeProvidentFund' => 0,
        'providentFundPercent' => 12,
        'providentFundWageCeiling' => 15000,
        'providentFundBasis' => 'basic',
        'includeEsic' => 0,
        'esicEmployeePercent' => 0.75,
        'esicWageLimit' => 21000,
        'includeProfessionalTax' => 0,
        'professionalTaxAmount' => 0,
        'includeIncomeTaxTds' => 0,
        'incomeTaxTdsType' => 'fixed',
        'incomeTaxTdsAmount' => 0,
        'incomeTaxTdsPercent' => 0,
        'includeGst' => 0,
        'gstPercent' => 18,
        'salaryBase' => 'netSalary',
        'standardWorkingHours' => 8,
        'includeApprovedOvertime' => 1,
        'overtimeMultiplier' => 1.5,
        'includeManualDeductions' => 1,
        'includeFixedEmployeeDeduction' => 1,
        'includeAdvanceDeduction' => 1,
        'includeLoanDeduction' => 1,
        'includeApprovedExpenses' => 1,
        'includeSyncedCommissionBonus' => 1,
        'approvedPaidLeaveDeductionPercent' => 0,
        'approvedUnpaidLeaveDeductionPercent' => 100,
        'informedLeaveDeductionPercent' => 100,
        'uninformedLeaveDeductionPercent' => 100,
        'halfDayDeductionPercent' => 50,
        'absentAsUninformedLeave' => 1,
        'pendingLeaveAsInformed' => 1,
        'roundingRule' => 'nearest_rupee',
        'isActive' => 1,
        'enableTrainingHoldRule'=>0,
        'trainingHoldDays'=>0,
        'trainingAmountReleaseAfterDays'=>90,
    ];

    public function __construct(mysqli $con)
    {
        $this->con = $con;
    }

    public function ensureSettingsTable(): void
    {
        mysqli_query(
            $this->con,
            "CREATE TABLE IF NOT EXISTS payrollSettings (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                payrollCycleType VARCHAR(30) NOT NULL DEFAULT 'monthly',
                cycleStartDay TINYINT UNSIGNED NOT NULL DEFAULT 1,
                cycleEndDay TINYINT UNSIGNED NOT NULL DEFAULT 31,
                paidDaysBasis VARCHAR(30) NOT NULL DEFAULT 'calendar',
                fixedPaidDays DECIMAL(6,2) NOT NULL DEFAULT 30.00,
                monthlyPaidLeaveDays DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                monthlyPaidLeaveCarryForward TINYINT(1) NOT NULL DEFAULT 0,
                monthlyPaidLeaveCarryForwardLimit DECIMAL(6,2) NOT NULL DEFAULT 0.00,
                enableBirthdayPaidLeave TINYINT(1) NOT NULL DEFAULT 1,
                prorateSalaryFromJoiningDate TINYINT(1) NOT NULL DEFAULT 1,
                enableProbationLeaveRule TINYINT(1) NOT NULL DEFAULT 1,
                probationDays SMALLINT UNSIGNED NOT NULL DEFAULT 30,
                probationLeaveDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 100.00,
                enableNoticePeriodLeaveRule TINYINT(1) NOT NULL DEFAULT 0,
                noticeLeaveDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 100.00,
                includePointPayrollDeduction TINYINT(1) NOT NULL DEFAULT 0,
                pointDeductionThreshold DECIMAL(10,2) NOT NULL DEFAULT 100.00,
                pointDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 10.00,
                compoundPointDeduction TINYINT(1) NOT NULL DEFAULT 1,
                includeProvidentFund TINYINT(1) NOT NULL DEFAULT 0,
                providentFundPercent DECIMAL(8,2) NOT NULL DEFAULT 12.00,
                providentFundWageCeiling DECIMAL(12,2) NOT NULL DEFAULT 15000.00,
                providentFundBasis VARCHAR(30) NOT NULL DEFAULT 'basic',
                includeEsic TINYINT(1) NOT NULL DEFAULT 0,
                esicEmployeePercent DECIMAL(8,2) NOT NULL DEFAULT 0.75,
                esicWageLimit DECIMAL(12,2) NOT NULL DEFAULT 21000.00,
                includeProfessionalTax TINYINT(1) NOT NULL DEFAULT 0,
                professionalTaxAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                includeIncomeTaxTds TINYINT(1) NOT NULL DEFAULT 0,
                incomeTaxTdsType VARCHAR(30) NOT NULL DEFAULT 'fixed',
                incomeTaxTdsAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                incomeTaxTdsPercent DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                includeGst TINYINT(1) NOT NULL DEFAULT 0,
                gstPercent DECIMAL(8,2) NOT NULL DEFAULT 18.00,
                salaryBase VARCHAR(40) NOT NULL DEFAULT 'netSalary',
                standardWorkingHours DECIMAL(6,2) NOT NULL DEFAULT 8.00,
                includeApprovedOvertime TINYINT(1) NOT NULL DEFAULT 1,
                overtimeMultiplier DECIMAL(8,2) NOT NULL DEFAULT 1.50,
                includeManualDeductions TINYINT(1) NOT NULL DEFAULT 1,
                includeFixedEmployeeDeduction TINYINT(1) NOT NULL DEFAULT 1,
                includeApprovedExpenses TINYINT(1) NOT NULL DEFAULT 1,
                includeSyncedCommissionBonus TINYINT(1) NOT NULL DEFAULT 1,
                approvedPaidLeaveDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 0.00,
                approvedUnpaidLeaveDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 100.00,
                informedLeaveDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 100.00,
                uninformedLeaveDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 100.00,
                halfDayDeductionPercent DECIMAL(8,2) NOT NULL DEFAULT 50.00,
                absentAsUninformedLeave TINYINT(1) NOT NULL DEFAULT 1,
                pendingLeaveAsInformed TINYINT(1) NOT NULL DEFAULT 1,
                roundingRule VARCHAR(30) NOT NULL DEFAULT 'nearest_rupee',
                setupCompleted TINYINT(1) NOT NULL DEFAULT 0,
                isActive TINYINT(1) NOT NULL DEFAULT 1,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->ensureSettingsColumn('monthlyPaidLeaveDays', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00');
        $this->ensureSettingsColumn('monthlyPaidLeaveCarryForward', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('monthlyPaidLeaveCarryForwardLimit', 'DECIMAL(6,2) NOT NULL DEFAULT 0.00');
        $this->ensureSettingsColumn('enableBirthdayPaidLeave', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->ensureSettingsColumn('prorateSalaryFromJoiningDate', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->ensureSettingsColumn('enableProbationLeaveRule', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->ensureSettingsColumn('probationDays', 'SMALLINT UNSIGNED NOT NULL DEFAULT 30');
        $this->ensureSettingsColumn('probationLeaveDeductionPercent', 'DECIMAL(8,2) NOT NULL DEFAULT 100.00');
        $this->ensureSettingsColumn('enableNoticePeriodLeaveRule', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('noticeLeaveDeductionPercent', 'DECIMAL(8,2) NOT NULL DEFAULT 100.00');
        $this->ensureSettingsColumn('includePointPayrollDeduction', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('pointDeductionThreshold', 'DECIMAL(10,2) NOT NULL DEFAULT 100.00');
        $this->ensureSettingsColumn('pointDeductionPercent', 'DECIMAL(8,2) NOT NULL DEFAULT 10.00');
        $this->ensureSettingsColumn('compoundPointDeduction', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->ensureSettingsColumn('includeProvidentFund', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('providentFundPercent', 'DECIMAL(8,2) NOT NULL DEFAULT 12.00');
        $this->ensureSettingsColumn('providentFundWageCeiling', 'DECIMAL(12,2) NOT NULL DEFAULT 15000.00');
        $this->ensureSettingsColumn('providentFundBasis', "VARCHAR(30) NOT NULL DEFAULT 'basic'");
        $this->ensureSettingsColumn('includeEsic', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('esicEmployeePercent', 'DECIMAL(8,2) NOT NULL DEFAULT 0.75');
        $this->ensureSettingsColumn('esicWageLimit', 'DECIMAL(12,2) NOT NULL DEFAULT 21000.00');
        $this->ensureSettingsColumn('includeProfessionalTax', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('professionalTaxAmount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        $this->ensureSettingsColumn('includeIncomeTaxTds', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('incomeTaxTdsType', "VARCHAR(30) NOT NULL DEFAULT 'fixed'");
        $this->ensureSettingsColumn('incomeTaxTdsAmount', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00');
        $this->ensureSettingsColumn('incomeTaxTdsPercent', 'DECIMAL(8,2) NOT NULL DEFAULT 0.00');
        $this->ensureSettingsColumn('includeGst', 'TINYINT(1) NOT NULL DEFAULT 0');
        $this->ensureSettingsColumn('gstPercent', 'DECIMAL(8,2) NOT NULL DEFAULT 18.00');
        $this->ensureSettingsColumn('includeAdvanceDeduction', 'TINYINT(1) NOT NULL DEFAULT 1');
        $this->ensureSettingsColumn('includeLoanDeduction', 'TINYINT(1) NOT NULL DEFAULT 1');
    }

    public function getSettings(): array
    {
        $this->ensureSettingsTable();

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM payrollSettings
             WHERE isActive = 1
             ORDER BY id DESC
             LIMIT 1"
        );

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        if (!$row) {
            return self::DEFAULT_SETTINGS + ['setupCompleted' => 0];
        }

        return array_merge(self::DEFAULT_SETTINGS, $row);
    }

    public function saveSettings(array $settings): bool
    {
        $this->ensureSettingsTable();

        $normalized = $this->normalizeSettings($settings);
        $fieldTypes = $this->getSettingsFieldTypes();
        $columns = array_keys($fieldTypes);

        $check = mysqli_prepare(
            $this->con,
            "SELECT id
             FROM payrollSettings
             WHERE isActive = 1
             ORDER BY id DESC
             LIMIT 1"
        );

        mysqli_stmt_execute($check);
        $existing = mysqli_stmt_get_result($check)->fetch_assoc();
        mysqli_stmt_close($check);

        $values = [];
        $types = '';

        foreach ($fieldTypes as $field => $type) {
            $values[] = $normalized[$field];
            $types .= $type;
        }

        if ($existing) {
            $assignments = array_map(
                static fn(string $column): string => "{$column} = ?",
                $columns
            );
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE payrollSettings SET " .
                implode(', ', $assignments) .
                ", setupCompleted = 1 WHERE id = ?"
            );

            if (!$stmt) {
                return false;
            }

            $values[] = (int)$existing['id'];
            $types .= 'i';
            $this->bindParams($stmt, $types, $values);
        } else {
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO payrollSettings (" .
                implode(', ', $columns) .
                ", setupCompleted, isActive) VALUES ({$placeholders}, 1, 1)"
            );

            if (!$stmt) {
                return false;
            }

            $this->bindParams($stmt, $types, $values);
        }

        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $success;
    }

    private function getSettingsFieldTypes(): array
    {
        return [
            'payrollCycleType' => 's',
            'cycleStartDay' => 'i',
            'cycleEndDay' => 'i',
            'paidDaysBasis' => 's',
            'fixedPaidDays' => 'd',
            'monthlyPaidLeaveDays' => 'd',
            'monthlyPaidLeaveCarryForward' => 'i',
            'monthlyPaidLeaveCarryForwardLimit' => 'd',
            'enableBirthdayPaidLeave' => 'i',
            'prorateSalaryFromJoiningDate' => 'i',
            'enableProbationLeaveRule' => 'i',
            'probationDays' => 'i',
            'probationLeaveDeductionPercent' => 'd',
            'enableNoticePeriodLeaveRule' => 'i',
            'noticeLeaveDeductionPercent' => 'd',
            'includePointPayrollDeduction' => 'i',
            'pointDeductionThreshold' => 'd',
            'pointDeductionPercent' => 'd',
            'compoundPointDeduction' => 'i',
            'includeProvidentFund' => 'i',
            'providentFundPercent' => 'd',
            'providentFundWageCeiling' => 'd',
            'providentFundBasis' => 's',
            'includeEsic' => 'i',
            'esicEmployeePercent' => 'd',
            'esicWageLimit' => 'd',
            'includeProfessionalTax' => 'i',
            'professionalTaxAmount' => 'd',
            'includeIncomeTaxTds' => 'i',
            'incomeTaxTdsType' => 's',
            'incomeTaxTdsAmount' => 'd',
            'incomeTaxTdsPercent' => 'd',
            'includeGst' => 'i',
            'gstPercent' => 'd',
            'salaryBase' => 's',
            'standardWorkingHours' => 'd',
            'includeApprovedOvertime' => 'i',
            'overtimeMultiplier' => 'd',
            'includeManualDeductions' => 'i',
            'includeFixedEmployeeDeduction' => 'i',
            'includeAdvanceDeduction' => 'i',
            'includeLoanDeduction' => 'i',
            'includeApprovedExpenses' => 'i',
            'includeSyncedCommissionBonus' => 'i',
            'approvedPaidLeaveDeductionPercent' => 'd',
            'approvedUnpaidLeaveDeductionPercent' => 'd',
            'informedLeaveDeductionPercent' => 'd',
            'uninformedLeaveDeductionPercent' => 'd',
            'halfDayDeductionPercent' => 'd',
            'absentAsUninformedLeave' => 'i',
            'pendingLeaveAsInformed' => 'i',
            'roundingRule' => 's',
            'enableTrainingHoldRule'=>'i',
            'trainingHoldDays'=>'i',
            'trainingAmountReleaseAfterDays'=>'i',
        ];
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $values): bool
    {
        $params = [$types];

        foreach ($values as $key => $value) {
            $params[] = &$values[$key];
        }

        return mysqli_stmt_bind_param($stmt, ...$params);
    }
    
    private function isEmployeeInProbation(array $employee, array $settings, string $date): bool
    {
        if( empty($settings['enableProbationLeaveRule']) || empty($employee['joiningDate']) ){
            return false;
        }
    
        $joiningDate =  date( 'Y-m-d', strtotime($employee['joiningDate']) );
        $probationEnd = date('Y-m-d', strtotime($joiningDate . ' +'. ((int)$settings['probationDays']-1).' days'));
        return $date >= $joiningDate && $date <= $probationEnd;
    }

    public function calculateSalarySlip(
        int $employeeId,
        string $periodStart,
        string $periodEnd
    ): array {
        $settings = $this->getSettings();
        $employee = $this->getEmployee($employeeId);

        if (!$employee) {
            return [
                'success' => false,
                'message' => 'Employee not found.',
                'data' => [],
            ];
        }

        $periodDays = $this->getDateRangeDays($periodStart, $periodEnd);
        $payableDays = $this->getPayableDays($settings, $periodStart, $periodEnd);
        $monthlySalary = $this->getMonthlySalary($employee, $settings['salaryBase']);
        $dailyRate = $payableDays > 0 ? $monthlySalary / $payableDays : 0.0;
        $effectivePeriodStart = $this->getEffectivePeriodStart($employee, $settings, $periodStart, $periodEnd);
        $effectivePayableDays = $effectivePeriodStart !== null
            ? $this->getDateRangeDays($effectivePeriodStart, $periodEnd)
            : 0;
        $salaryAmount = !empty($settings['prorateSalaryFromJoiningDate'])
            ? min($monthlySalary, $dailyRate * $effectivePayableDays)
            : $monthlySalary;
            
            
        $trainingHold = $this->processTrainingHold($employee, $settings, $dailyRate, $periodStart, $periodEnd);
        
        $hourlyRate = (float)$settings['standardWorkingHours'] > 0
            ? $dailyRate / (float)$settings['standardWorkingHours']
            : 0.0;

        // Dates with ANY approved leave application (paid or unpaid, full or
        // half) overlapping them — their pay impact is already fully and
        // correctly computed by getLeaveSummary()/$leaveDeduction below. A
        // half-day leave still leaves an employeeAttendance row for the
        // half of the day actually worked, which getAttendanceSummary()
        // would otherwise also charge via $halfDayDeduction — see its
        // 'deductibleHalfDays' field for why that double-charge is avoided.
        $leaveCoveredDates = $this->getApprovedLeaveDates($employeeId, $periodStart, $periodEnd);
        $attendance = $this->getAttendanceSummary($employeeId, $periodStart, $periodEnd, $leaveCoveredDates);
        $leave = $this->getLeaveSummary($employeeId, $periodStart, $periodEnd, $settings, $employee);
        $overtime = !empty($settings['includeApprovedOvertime'])
            ? $this->getApprovedOvertimeHours($employeeId, $periodStart, $periodEnd)
            : 0.0;

        // $expenseAmount = !empty($settings['includeApprovedExpenses'])
        //     ? $this->getApprovedExpenseAmount($employeeId, $periodStart, $periodEnd)
        //     : 0.0;
        
        
        $salaryGeneratedAt = date('Y-m-d H:i:s');

        $expenseData = !empty($settings['includeApprovedExpenses'])
            ? $this->getApprovedUnpaidExpenses(
                $employeeId,
                $salaryGeneratedAt
            )
            : [
                'amount' => 0.0,
                'ids' => []
            ];
        
        $expenseAmount = (float)$expenseData['amount'];

        $commissionBonusAmount = !empty($settings['includeSyncedCommissionBonus'])
            ? $this->getSyncedCommissionBonusAmount($employeeId, $periodStart, $periodEnd)
            : 0.0;

        $manualDeductionAmount = !empty($settings['includeManualDeductions'])
            ? $this->getManualDeductionAmount($employeeId, $periodStart, $periodEnd)
            : 0.0;

        $fixedEmployeeDeduction = !empty($settings['includeFixedEmployeeDeduction'])
            ? (float)($employee['deductionAmount'] ?? 0)
            : 0.0;

        // Each advance is evaluated independently by its own Payment
        // Adjustment mode (Not Adjusted / Upcoming Salary / Partial
        // Payment) -- see getApprovedAdvanceDeduction() for the per-mode
        // breakdown.
        $advanceData = !empty($settings['includeAdvanceDeduction'])
            ? $this->getApprovedAdvanceDeduction($employeeId, $periodStart, $periodEnd)
            : ['amount' => 0.0, 'ids' => [], 'partialInstallmentIds' => []];
        $advanceDeductionAmount = (float)$advanceData['amount'];

        // Loan repayments are month-scoped (employeeLoanRepayments.salaryMonth),
        // same pattern as getTrainingReleaseAmount()/getSyncedCommissionBonusAmount().
        $loanData = !empty($settings['includeLoanDeduction'])
            ? $this->getPendingLoanRepayment($employeeId, $periodStart, $periodEnd)
            : ['amount' => 0.0, 'ids' => []];
        $loanDeductionAmount = (float)$loanData['amount'];

        // employeeAttendance-based half-day deduction is currently disabled
        // by business decision -- attendance data (halfDays/deductibleHalfDays)
        // is still computed above and returned for display on the salary
        // slip, it just no longer feeds into any deduction or payable-days
        // calculation. Leave-based deductions (paid/excess/unpaid/
        // probation/notice/informed/uninformed) are entirely unaffected.
        $halfDayDeduction = 0.0;

        // Broken into named, per-category amounts (rather than only a single
        // summed $leaveDeduction) so the salary slip can show HR exactly how
        // much of the leave deduction came from paid-leave-within-entitlement
        // (normally 0) versus excess/unpaid/other leave — see
        // getEarningsRows()'s sibling, the Deductions table in
        // pages/salary-slip.php, which renders these individually.
        $paidLeaveCoveredAmount = $dailyRate * (float)$leave['paidLeaveCoveredDays'] * ((float)$settings['approvedPaidLeaveDeductionPercent'] / 100);
        $excessPaidLeaveAmount = $dailyRate * (float)$leave['approvedPaidLeaveExcessDays'] * ((float)$settings['approvedUnpaidLeaveDeductionPercent'] / 100);
        $unpaidLeaveAmount = $dailyRate * (float)$leave['approvedUnpaidLeaveDays'] * ((float)$settings['approvedUnpaidLeaveDeductionPercent'] / 100);
        $probationLeaveAmount = $dailyRate * (float)$leave['probationLeaveDays'] * ((float)$settings['probationLeaveDeductionPercent'] / 100);
        $noticeLeaveAmount = $dailyRate * (float)$leave['noticeLeaveDays'] * ((float)$settings['noticeLeaveDeductionPercent'] / 100);
        $informedLeaveAmount = $dailyRate * (float)$leave['informedLeaveDays'] * ((float)$settings['informedLeaveDeductionPercent'] / 100);
        $uninformedLeaveAmount = $dailyRate * (float)$leave['uninformedLeaveDays'] * ((float)$settings['uninformedLeaveDeductionPercent'] / 100);

        $leaveDeduction = $paidLeaveCoveredAmount
            + $excessPaidLeaveAmount
            + $unpaidLeaveAmount
            + $probationLeaveAmount
            + $noticeLeaveAmount
            + $informedLeaveAmount
            + $uninformedLeaveAmount;

        $overtimeAmount = $hourlyRate
            * $overtime
            * (float)$settings['overtimeMultiplier'];

        $earningsRows = $this->getEarningsRows(
            $employee,
            $salaryAmount,
            $overtimeAmount,
            $commissionBonusAmount
        );
        $otherEarningsAmount = $this->getOtherEarningsTotal($employee);
        $baseGrossEarnings = $salaryAmount + $overtimeAmount + $commissionBonusAmount + $otherEarningsAmount;
        $statutory = $this->getStatutoryDeductions($employee, $settings, $earningsRows, $baseGrossEarnings);
        $reimbursementRows = $this->getReimbursementRows($expenseAmount, $statutory['gstAmount']);
        $totalReimbursements = array_sum(array_column($reimbursementRows, 'amount'));
        $pointDeduction = !empty($settings['includePointPayrollDeduction'])
            ? $this->getPointPayrollDeduction($employeeId, $periodStart, $periodEnd, $salaryAmount, $settings)
            : [
                'amount' => 0.0,
                'impactPoints' => 0.0,
                'hitCount' => 0,
            ];

        $paidDaysAfterLeave = max(
            0.0,
            $effectivePayableDays
            - (float)$leave['approvedPaidLeaveExcessDays']
            - (float)$leave['approvedUnpaidLeaveDays']
            - (float)$leave['probationLeaveDays']
            - (float)$leave['noticeLeaveDays']
            - (float)$leave['informedLeaveDays']
            - (float)$leave['uninformedLeaveDays']
            // employeeAttendance half-day deduction disabled -- see $halfDayDeduction above
        );

        $grossEarnings = $baseGrossEarnings;
        $totalDeductions = $leaveDeduction
            + $halfDayDeduction
            + $manualDeductionAmount
            + $fixedEmployeeDeduction
            + (float)$pointDeduction['amount']
            + ($trainingHold['deduction'] ?? 0)
            + (float)$statutory['totalDeductions']
            + $advanceDeductionAmount
            + $loanDeductionAmount;
            
            
        $trainingRelease = $this->getTrainingReleaseAmount($employeeId, $periodStart, $periodEnd);


        $totalReimbursements += $trainingRelease;    
            
        $netPay = $this->roundAmount(
            $grossEarnings - $totalDeductions + $totalReimbursements,
            (string)$settings['roundingRule']
        );

        return [
            'success' => true,
            'message' => 'Payroll calculated successfully.',
            'data' => [
                'employee' => $employee,
                'period' => [
                    'start' => $periodStart,
                    'end' => $periodEnd,
                    'effectiveStart' => $effectivePeriodStart,
                    'calendarDays' => $periodDays,
                    'payableDays' => $payableDays,
                    'effectivePayableDays' => $effectivePayableDays,
                    'paidDaysAfterLeave' => $this->roundAmount($paidDaysAfterLeave, 'two_decimal'),
                ],
                'rates' => [
                    'monthlySalary' => $this->roundAmount($monthlySalary, 'two_decimal'),
                    'dailyRate' => $this->roundAmount($dailyRate, 'two_decimal'),
                    'hourlyRate' => $this->roundAmount($hourlyRate, 'two_decimal'),
                ],
                'attendance' => $attendance,
                'leave' => $leave,
                'earnings' => [
                    'salaryAmount' => $this->roundAmount($salaryAmount, 'two_decimal'),
                    'baseMonthlySalary' => $this->roundAmount($monthlySalary, 'two_decimal'),
                    'joiningProrationAmount' => $this->roundAmount($monthlySalary - $salaryAmount, 'two_decimal'),
                    'rows' => $earningsRows,
                    'overtimeHours' => $this->roundAmount($overtime, 'two_decimal'),
                    'overtimeAmount' => $this->roundAmount($overtimeAmount, 'two_decimal'),
                    'commissionBonus' => $this->roundAmount($commissionBonusAmount, 'two_decimal'),
                    'otherEarningsAmount' => $this->roundAmount($otherEarningsAmount, 'two_decimal'),
                    'grossEarnings' => $this->roundAmount($grossEarnings, 'two_decimal'),
                ],
                'reimbursements' => [
                    'rows' => $reimbursementRows,
                    'expenseReimbursement' => $this->roundAmount($expenseAmount, 'two_decimal'),
                    'expenseIds' => $expenseData['ids'],
                    'gstAmount' => $this->roundAmount((float)$statutory['gstAmount'], 'two_decimal'),
                    'totalReimbursements' => $this->roundAmount($totalReimbursements, 'two_decimal'),
                ],
                'deductions' => [
                    'leaveDeduction' => $this->roundAmount($leaveDeduction, 'two_decimal'),
                    'paidLeaveCoveredAmount' => $this->roundAmount($paidLeaveCoveredAmount, 'two_decimal'),
                    'excessPaidLeaveAmount' => $this->roundAmount($excessPaidLeaveAmount, 'two_decimal'),
                    'unpaidLeaveAmount' => $this->roundAmount($unpaidLeaveAmount, 'two_decimal'),
                    'probationLeaveAmount' => $this->roundAmount($probationLeaveAmount, 'two_decimal'),
                    'noticeLeaveAmount' => $this->roundAmount($noticeLeaveAmount, 'two_decimal'),
                    'informedLeaveAmount' => $this->roundAmount($informedLeaveAmount, 'two_decimal'),
                    'uninformedLeaveAmount' => $this->roundAmount($uninformedLeaveAmount, 'two_decimal'),
                    'halfDayDeduction' => $this->roundAmount($halfDayDeduction, 'two_decimal'),
                    'manualDeduction' => $this->roundAmount($manualDeductionAmount, 'two_decimal'),
                    'fixedEmployeeDeduction' => $this->roundAmount($fixedEmployeeDeduction, 'two_decimal'),
                    'advanceDeduction' => $this->roundAmount($advanceDeductionAmount, 'two_decimal'),
                    'advanceIds' => $advanceData['ids'],
                    'advancePartialInstallmentIds' => $advanceData['partialInstallmentIds'],
                    'loanDeduction' => $this->roundAmount($loanDeductionAmount, 'two_decimal'),
                    'loanRepaymentIds' => $loanData['ids'],
                    'pointDeduction' => $this->roundAmount((float)$pointDeduction['amount'], 'two_decimal'),
                    'incomeTaxTds' => $this->roundAmount((float)$statutory['incomeTaxTds'], 'two_decimal'),
                    'professionalTax' => $this->roundAmount((float)$statutory['professionalTax'], 'two_decimal'),
                    'providentFund' => $this->roundAmount((float)$statutory['providentFund'], 'two_decimal'),
                    'esic' => $this->roundAmount((float)$statutory['esic'], 'two_decimal'),
                    'rows' => $statutory['rows'],
                    'trainingHoldDeduction' => $this->roundAmount($trainingHold['deduction'] ?? 0, 'two_decimal'),
                    'totalDeductions' => $this->roundAmount($totalDeductions, 'two_decimal'),
                    
                ],
                'netFormula' => [
                    'grossEarnings' => $this->roundAmount($grossEarnings, 'two_decimal'),
                    'totalDeductions' => $this->roundAmount($totalDeductions, 'two_decimal'),
                    'totalReimbursements' => $this->roundAmount($totalReimbursements, 'two_decimal'),
                    'netPay' => $netPay,
                ],
                'points' => [
                    'impactPoints' => $this->roundAmount((float)$pointDeduction['impactPoints'], 'two_decimal'),
                    'hitCount' => (int)$pointDeduction['hitCount'],
                    'deductionAmount' => $this->roundAmount((float)$pointDeduction['amount'], 'two_decimal'),
                ],
                'netPay' => $netPay,
                'settings' => $settings,
            ],
        ];
    }

    private function normalizeSettings(array $settings): array
    {
        $settings = array_merge(self::DEFAULT_SETTINGS, $settings);

        $cycleStartDay = min(31, max(1, (int)$settings['cycleStartDay']));
        $cycleEndDay = min(31, max(1, (int)$settings['cycleEndDay']));

        return [
            'payrollCycleType' => $this->allowedValue(
                $settings['payrollCycleType'],
                ['monthly', 'custom'],
                'monthly'
            ),
            'cycleStartDay' => $cycleStartDay,
            'cycleEndDay' => $cycleEndDay,
            'paidDaysBasis' => $this->allowedValue(
                $settings['paidDaysBasis'],
                ['calendar', 'fixed_30'],
                'calendar'
            ),
            'fixedPaidDays' => max(1, (float)$settings['fixedPaidDays']),
            'monthlyPaidLeaveDays' => max(0, (float)$settings['monthlyPaidLeaveDays']),
            'monthlyPaidLeaveCarryForward' => !empty($settings['monthlyPaidLeaveCarryForward']) ? 1 : 0,
            'monthlyPaidLeaveCarryForwardLimit' => max(0, (float)$settings['monthlyPaidLeaveCarryForwardLimit']),
            'enableBirthdayPaidLeave' => !empty($settings['enableBirthdayPaidLeave']) ? 1 : 0,
            'prorateSalaryFromJoiningDate' => !empty($settings['prorateSalaryFromJoiningDate']) ? 1 : 0,
            'enableProbationLeaveRule' => !empty($settings['enableProbationLeaveRule']) ? 1 : 0,
            'probationDays' => max(0, (int)$settings['probationDays']),
            'probationLeaveDeductionPercent' => $this->cleanPercent($settings['probationLeaveDeductionPercent']),
            'enableNoticePeriodLeaveRule' => !empty($settings['enableNoticePeriodLeaveRule']) ? 1 : 0,
            'noticeLeaveDeductionPercent' => $this->cleanPercent($settings['noticeLeaveDeductionPercent']),
            'includePointPayrollDeduction' => !empty($settings['includePointPayrollDeduction']) ? 1 : 0,
            'pointDeductionThreshold' => max(0, (float)$settings['pointDeductionThreshold']),
            'pointDeductionPercent' => $this->cleanPercent($settings['pointDeductionPercent']),
            'compoundPointDeduction' => !empty($settings['compoundPointDeduction']) ? 1 : 0,
            'includeProvidentFund' => !empty($settings['includeProvidentFund']) ? 1 : 0,
            'providentFundPercent' => $this->cleanPercent($settings['providentFundPercent']),
            'providentFundWageCeiling' => max(0, (float)$settings['providentFundWageCeiling']),
            'providentFundBasis' => $this->allowedValue(
                $settings['providentFundBasis'],
                ['basic', 'gross'],
                'basic'
            ),
            'includeEsic' => !empty($settings['includeEsic']) ? 1 : 0,
            'esicEmployeePercent' => $this->cleanPercent($settings['esicEmployeePercent']),
            'esicWageLimit' => max(0, (float)$settings['esicWageLimit']),
            'includeProfessionalTax' => !empty($settings['includeProfessionalTax']) ? 1 : 0,
            'professionalTaxAmount' => max(0, (float)$settings['professionalTaxAmount']),
            'includeIncomeTaxTds' => !empty($settings['includeIncomeTaxTds']) ? 1 : 0,
            'incomeTaxTdsType' => $this->allowedValue(
                $settings['incomeTaxTdsType'],
                ['fixed', 'percent'],
                'fixed'
            ),
            'incomeTaxTdsAmount' => max(0, (float)$settings['incomeTaxTdsAmount']),
            'incomeTaxTdsPercent' => $this->cleanPercent($settings['incomeTaxTdsPercent']),
            'includeGst' => !empty($settings['includeGst']) ? 1 : 0,
            'gstPercent' => $this->cleanPercent($settings['gstPercent']),
            'salaryBase' => $this->allowedValue(
                $settings['salaryBase'],
                ['netSalary', 'grossSalary', 'basicSalary'],
                'netSalary'
            ),
            'standardWorkingHours' => max(1, (float)$settings['standardWorkingHours']),
            'includeApprovedOvertime' => !empty($settings['includeApprovedOvertime']) ? 1 : 0,
            'overtimeMultiplier' => max(0, (float)$settings['overtimeMultiplier']),
            'includeManualDeductions' => !empty($settings['includeManualDeductions']) ? 1 : 0,
            'includeFixedEmployeeDeduction' => !empty($settings['includeFixedEmployeeDeduction']) ? 1 : 0,
            'includeAdvanceDeduction' => !empty($settings['includeAdvanceDeduction']) ? 1 : 0,
            'includeLoanDeduction' => !empty($settings['includeLoanDeduction']) ? 1 : 0,
            'includeApprovedExpenses' => !empty($settings['includeApprovedExpenses']) ? 1 : 0,
            'includeSyncedCommissionBonus' => !empty($settings['includeSyncedCommissionBonus']) ? 1 : 0,
            'approvedPaidLeaveDeductionPercent' => $this->cleanPercent($settings['approvedPaidLeaveDeductionPercent']),
            'approvedUnpaidLeaveDeductionPercent' => $this->cleanPercent($settings['approvedUnpaidLeaveDeductionPercent']),
            'informedLeaveDeductionPercent' => $this->cleanPercent($settings['informedLeaveDeductionPercent']),
            'uninformedLeaveDeductionPercent' => $this->cleanPercent($settings['uninformedLeaveDeductionPercent']),
            'halfDayDeductionPercent' => $this->cleanPercent($settings['halfDayDeductionPercent']),
            'absentAsUninformedLeave' => !empty($settings['absentAsUninformedLeave']) ? 1 : 0,
            'pendingLeaveAsInformed' => !empty($settings['pendingLeaveAsInformed']) ? 1 : 0,
            'roundingRule' => $this->allowedValue(
                $settings['roundingRule'],
                ['nearest_rupee', 'two_decimal', 'floor_rupee', 'ceil_rupee'],
                'nearest_rupee'
            ),
            'enableTrainingHoldRule' => !empty($settings['enableTrainingHoldRule']) ? 1 : 0,
            'trainingHoldDays' =>  max(0,(int)$settings['trainingHoldDays']),
            'trainingAmountReleaseAfterDays'=> max(1, (int)$settings['trainingAmountReleaseAfterDays']),
        ];
    }

    private function getEmployee(int $employeeId): ?array
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                id,
                employeeCode,
                fullName,
                departmentName,
                designationName,
                localAddress,
                cityName,
                panNumber,
                bankName,
                accountNumber,
                ifscCode,
                dateOfBirth,
                joiningDate,
                employmentStatus,
                employeeType,
                basicSalary,
                hraAmount,
                allowanceAmount,
                deductionAmount,
                netSalary,
                otherEarning1Amount,
                otherEarning2Amount,
                otherEarning3Amount,
                otherAllowanceAmount,
                paymentFrequency
             FROM employeeusers
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'i', $employeeId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $employee = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $employee ?: null;
    }

    private function getMonthlySalary(array $employee, string $salaryBase): float
    {
        $basic = (float)($employee['basicSalary'] ?? 0);
        $hra = (float)($employee['hraAmount'] ?? 0);
        $allowance = (float)($employee['allowanceAmount'] ?? 0);
        $net = (float)($employee['netSalary'] ?? 0);

        if ($salaryBase === 'basicSalary') {
            return $basic;
        }

        if ($salaryBase === 'grossSalary') {
            return $basic + $hra + $allowance;
        }

        if ($net > 0) {
            return $net;
        }

        return $basic + $hra + $allowance;
    }

    private function getPayableDays(array $settings, string $periodStart, string $periodEnd): float
    {
        if ((string)$settings['paidDaysBasis'] === 'fixed_30') {
            return (float)$settings['fixedPaidDays'];
        }

        return (float)$this->getDateRangeDays($periodStart, $periodEnd);
    }

    private function getDateRangeDays(string $periodStart, string $periodEnd): int
    {
        $start = new DateTime($periodStart);
        $end = new DateTime($periodEnd);

        return max(1, (int)$start->diff($end)->days + 1);
    }

    private function getEffectivePeriodStart(
        array $employee,
        array $settings,
        string $periodStart,
        string $periodEnd
    ): ?string {
        if (empty($settings['prorateSalaryFromJoiningDate']) || empty($employee['joiningDate'])) {
            return $periodStart;
        }

        $joiningDate = date('Y-m-d', strtotime((string)$employee['joiningDate']));

        if ($joiningDate > $periodEnd) {
            return null;
        }

        return $joiningDate > $periodStart ? $joiningDate : $periodStart;
    }

    private function getAttendanceSummary(
        int $employeeId,
        string $periodStart,
        string $periodEnd,
        array $leaveCoveredDates = []
    ): array {
        $summary = [
            'presentDays' => 0.0,
            'halfDays' => 0.0,
            // Same count as 'halfDays', minus any date that already has an
            // approved leave application covering it. A half-day leave
            // still leaves an employeeAttendance row for the half of the
            // day the employee actually worked, and that date's pay impact
            // is already fully accounted for by the leave-based deduction
            // (see getLeaveSummary()/$leaveDeduction) — deducting it again
            // here would charge the employee twice for the same half-day.
            // Only this field feeds the payroll deduction math; 'halfDays'
            // is left unchanged so the Attendance Summary panel keeps
            // showing the true attendance fact.
            'deductibleHalfDays' => 0.0,
            'absentDays' => 0.0,
            'lateDays' => 0.0,
            'workingSeconds' => 0,
        ];

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT attendanceDate, attendanceStatus, totalWorkingSeconds
             FROM employeeAttendance
             WHERE employeeId = ?
             AND attendanceDate BETWEEN ? AND ?"
        );

        if (!$stmt) {
            return $summary;
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $status = strtolower((string)($row['attendanceStatus'] ?? ''));
            $summary['workingSeconds'] += (int)($row['totalWorkingSeconds'] ?? 0);

            if ($status === 'half_day') {
                $summary['halfDays'] += 1;

                if (empty($leaveCoveredDates[(string)$row['attendanceDate']])) {
                    $summary['deductibleHalfDays'] += 1;
                }
            } elseif ($status === 'absent') {
                $summary['absentDays'] += 1;
            } elseif ($status === 'late') {
                $summary['lateDays'] += 1;
                $summary['presentDays'] += 1;
            } elseif ($status !== '') {
                $summary['presentDays'] += 1;
            }
        }

        mysqli_stmt_close($stmt);

        return $summary;
    }

    /**
     * Set of 'Y-m-d' dates (within the period) that have ANY approved leave
     * application overlapping them, regardless of paid/unpaid or full/half
     * type — used only to keep getAttendanceSummary()'s half-day deduction
     * from double-charging a date whose pay impact getLeaveSummary() already
     * accounts for. Not a second leave-balance system: it reuses
     * leaveApplications as-is and computes nothing about entitlement.
     */
    private function getApprovedLeaveDates(int $employeeId, string $periodStart, string $periodEnd): array
    {
        $dates = [];

        if ($this->tableExists('leaveApplicationDays')) {
            $stmt = mysqli_prepare(
                $this->con,
                "SELECT lad.leaveDate
                 FROM leaveApplicationDays lad
                 INNER JOIN leaveApplications la
                    ON la.id = lad.leaveApplicationId
                 WHERE la.employeeId = ?
                 AND la.status <> 'cancelled'
                 AND lad.status = 'approved'
                 AND lad.leaveDate BETWEEN ? AND ?"
            );

            if (!$stmt) {
                return $dates;
            }

            mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $dates[(string)$row['leaveDate']] = true;
            }

            mysqli_stmt_close($stmt);

            return $dates;
        }

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT fromDate, toDate
             FROM leaveApplications
             WHERE employeeId = ?
             AND status = 'approved'
             AND fromDate <= ?
             AND toDate >= ?"
        );

        if (!$stmt) {
            return $dates;
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodEnd, $periodStart);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $from = max(strtotime((string)$row['fromDate']), strtotime($periodStart));
            $to = min(strtotime((string)$row['toDate']), strtotime($periodEnd));

            if (!$from || !$to || $to < $from) {
                continue;
            }

            $cursor = new DateTime(date('Y-m-d', $from));
            $end = new DateTime(date('Y-m-d', $to));

            while ($cursor <= $end) {
                $dates[$cursor->format('Y-m-d')] = true;
                $cursor->modify('+1 day');
            }
        }

        mysqli_stmt_close($stmt);

        return $dates;
    }

    private function ensureSettingsColumn(string $column, string $definition): void
    {
        $safeColumn = mysqli_real_escape_string($this->con, $column);
        $result = mysqli_query($this->con, "SHOW COLUMNS FROM payrollSettings LIKE '{$safeColumn}'");
        $exists = $result && mysqli_fetch_assoc($result);

        if (!$exists) {
            mysqli_query($this->con, "ALTER TABLE payrollSettings ADD COLUMN {$column} {$definition}");
        }
    }

    private function getLeaveSummary(
        int $employeeId,
        string $periodStart,
        string $periodEnd,
        array $settings,
        array $employee
    ): array {
        $summary = [
            'approvedPaidLeaveDays' => 0.0,
            'birthdayPaidLeaveDays' => 0.0,
            'quotaPaidLeaveDays' => 0.0,
            'monthlyPaidLeaveDays' => 0.0,
            'paidLeaveCarryForwardDays' => 0.0,
            'paidLeaveAvailableDays' => 0.0,
            'paidLeaveCoveredDays' => 0.0,
            'approvedPaidLeaveExcessDays' => 0.0,
            'paidLeaveRemainingDays' => 0.0,
            'approvedUnpaidLeaveDays' => 0.0,
            'probationLeaveDays' => 0.0,
            'noticeLeaveDays' => 0.0,
            'informedLeaveDays' => 0.0,
            'uninformedLeaveDays' => 0.0,
        ];

        if ($this->tableExists('leaveApplicationDays')) {
            $stmt = mysqli_prepare(
                $this->con,
                "SELECT
                    lad.leaveDate,
                    lad.dayType,
                    lad.status,
                    COALESCE(lt.isPaid, 0) AS isPaid
                 FROM leaveApplicationDays lad
                 INNER JOIN leaveApplications la
                    ON la.id = lad.leaveApplicationId
                 LEFT JOIN leaveTypes lt
                    ON lt.id = la.leaveTypeId
                 WHERE la.employeeId = ?
                 AND la.status <> 'cancelled'
                 AND lad.leaveDate BETWEEN ? AND ?"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                while ($row = mysqli_fetch_assoc($result)) {
                    $status = strtolower((string)($row['status'] ?? ''));
                    $date = (string)$row['leaveDate'];
                    $days = (string)($row['dayType'] ?? 'full') === 'half' ? 0.5 : 1.0;

                    if ($status === 'approved') {
                        if ($this->isBirthdayLeave($date, $employee, $settings)) {
                            $summary['approvedPaidLeaveDays'] += $days;
                            $summary['birthdayPaidLeaveDays'] += $days;
                        } elseif ((int)($row['isPaid'] ?? 0) === 1) {
                            $summary['approvedPaidLeaveDays'] += $days;

                            if ($this->isProbationLeave($date, $employee, $settings)) {
                                $summary['probationLeaveDays'] += $days;
                            } elseif ($this->isNoticePeriodLeave($employeeId, $date, $settings)) {
                                $summary['noticeLeaveDays'] += $days;
                            } else {
                                $summary['quotaPaidLeaveDays'] += $days;
                            }
                        } else {
                            $summary['approvedUnpaidLeaveDays'] += $days;
                        }
                    } elseif ($status === 'pending' && !empty($settings['pendingLeaveAsInformed'])) {
                        $summary['informedLeaveDays'] += $days;
                    }
                }

                mysqli_stmt_close($stmt);
            }
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "SELECT
                    la.fromDate,
                    la.toDate,
                    la.totalDays,
                    la.status,
                    COALESCE(lt.isPaid, 0) AS isPaid
                 FROM leaveApplications la
                 LEFT JOIN leaveTypes lt
                    ON lt.id = la.leaveTypeId
                 WHERE la.employeeId = ?
                 AND la.fromDate <= ?
                 AND la.toDate >= ?"
            );

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodEnd, $periodStart);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $status = strtolower((string)($row['status'] ?? ''));
                $segments = $this->getLeaveDaySegments(
                    (string)$row['fromDate'],
                    (string)$row['toDate'],
                    $periodStart,
                    $periodEnd,
                    (float)($row['totalDays'] ?? 0)
                );

                foreach ($segments as $segment) {
                    $date = (string)$segment['date'];
                    $days = (float)$segment['days'];

                    if ($status === 'approved') {
                        if ($this->isBirthdayLeave($date, $employee, $settings)) {
                            $summary['approvedPaidLeaveDays'] += $days;
                            $summary['birthdayPaidLeaveDays'] += $days;
                        } elseif ((int)($row['isPaid'] ?? 0) === 1) {
                            $summary['approvedPaidLeaveDays'] += $days;

                            if ($this->isProbationLeave($date, $employee, $settings)) {
                                $summary['probationLeaveDays'] += $days;
                            } elseif ($this->isNoticePeriodLeave($employeeId, $date, $settings)) {
                                $summary['noticeLeaveDays'] += $days;
                            } else {
                                $summary['quotaPaidLeaveDays'] += $days;
                            }
                        } else {
                            $summary['approvedUnpaidLeaveDays'] += $days;
                        }
                    } elseif ($status === 'pending' && !empty($settings['pendingLeaveAsInformed'])) {
                        $summary['informedLeaveDays'] += $days;
                    }
                }
            }

            mysqli_stmt_close($stmt);
        }
        }

        if (!empty($settings['absentAsUninformedLeave'])) {
            $attendance = $this->getAttendanceSummary($employeeId, $periodStart, $periodEnd);
            $summary['uninformedLeaveDays'] += (float)$attendance['absentDays'];
        }

        $summary['monthlyPaidLeaveDays'] = $this->getMonthlyPaidLeaveAllowance($settings, $periodStart, $periodEnd);
        $summary['paidLeaveCarryForwardDays'] = !empty($settings['monthlyPaidLeaveCarryForward'])
            ? $this->getPaidLeaveCarryForwardDays($employeeId, $periodStart, $settings)
            : 0.0;
        $summary['paidLeaveAvailableDays'] = $summary['monthlyPaidLeaveDays']
            + $summary['paidLeaveCarryForwardDays'];
        $coveredQuotaPaidLeave = min(
            (float)$summary['quotaPaidLeaveDays'],
            (float)$summary['paidLeaveAvailableDays']
        );
        $summary['paidLeaveCoveredDays'] = $summary['birthdayPaidLeaveDays'] + $coveredQuotaPaidLeave;
        $summary['approvedPaidLeaveExcessDays'] = max(
            0.0,
            (float)$summary['quotaPaidLeaveDays'] - $coveredQuotaPaidLeave
        );
        $summary['paidLeaveRemainingDays'] = max(
            0.0,
            (float)$summary['paidLeaveAvailableDays'] - (float)$summary['quotaPaidLeaveDays']
        );

        return $summary;
    }

    private function getMonthlyPaidLeaveAllowance(
        array $settings,
        string $periodStart,
        string $periodEnd
    ): float {
        $monthlyPaidLeaveDays = (float)($settings['monthlyPaidLeaveDays'] ?? 0);

        if ($monthlyPaidLeaveDays <= 0) {
            return 0.0;
        }

        return round($monthlyPaidLeaveDays * $this->getCalendarMonthsInRange($periodStart, $periodEnd), 2);
    }

    private function getPaidLeaveCarryForwardDays(
        int $employeeId,
        string $periodStart,
        array $settings
    ): float {
        $monthlyPaidLeaveDays = (float)($settings['monthlyPaidLeaveDays'] ?? 0);

        if ($monthlyPaidLeaveDays <= 0) {
            return 0.0;
        }

        $start = new DateTime($periodStart);
        $yearStart = new DateTime($start->format('Y-01-01'));
        $previousMonthEnd = new DateTime($start->format('Y-m-01'));
        $previousMonthEnd->modify('-1 day');

        if ($previousMonthEnd < $yearStart) {
            return 0.0;
        }

        $previousAllowance = $monthlyPaidLeaveDays * $this->getCalendarMonthsInRange(
            $yearStart->format('Y-m-d'),
            $previousMonthEnd->format('Y-m-d')
        );
        $previousUsed = $this->getApprovedPaidLeaveDays(
            $employeeId,
            $yearStart->format('Y-m-d'),
            $previousMonthEnd->format('Y-m-d')
        );
        $carryForward = max(0.0, $previousAllowance - $previousUsed);
        $carryForwardLimit = (float)($settings['monthlyPaidLeaveCarryForwardLimit'] ?? 0);

        if ($carryForwardLimit > 0) {
            $carryForward = min($carryForward, $carryForwardLimit);
        }

        return round($carryForward, 2);
    }

    private function getApprovedPaidLeaveDays(
        int $employeeId,
        string $periodStart,
        string $periodEnd
    ): float {
        if ($this->tableExists('leaveApplicationDays')) {
            $stmt = mysqli_prepare(
                $this->con,
                "SELECT COALESCE(SUM(CASE WHEN lad.dayType = 'half' THEN 0.5 ELSE 1 END), 0) AS total
                 FROM leaveApplicationDays lad
                 INNER JOIN leaveApplications la
                    ON la.id = lad.leaveApplicationId
                 INNER JOIN leaveTypes lt
                    ON lt.id = la.leaveTypeId
                 WHERE la.employeeId = ?
                 AND la.status <> 'cancelled'
                 AND lad.status = 'approved'
                 AND COALESCE(lt.isPaid, 0) = 1
                 AND lad.leaveDate BETWEEN ? AND ?"
            );

            if (!$stmt) {
                return 0.0;
            }

            mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            mysqli_stmt_close($stmt);

            return round((float)($row['total'] ?? 0), 2);
        }

        $total = 0.0;
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                la.fromDate,
                la.toDate,
                la.totalDays
             FROM leaveApplications la
             INNER JOIN leaveTypes lt
                ON lt.id = la.leaveTypeId
             WHERE la.employeeId = ?
             AND la.status = 'approved'
             AND COALESCE(lt.isPaid, 0) = 1
             AND la.fromDate <= ?
             AND la.toDate >= ?"
        );

        if (!$stmt) {
            return 0.0;
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodEnd, $periodStart);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($result)) {
            $total += $this->getOverlapDays(
                (string)$row['fromDate'],
                (string)$row['toDate'],
                $periodStart,
                $periodEnd,
                (float)($row['totalDays'] ?? 0)
            );
        }

        mysqli_stmt_close($stmt);

        return round($total, 2);
    }

    private function getCalendarMonthsInRange(string $periodStart, string $periodEnd): int
    {
        $start = new DateTime($periodStart);
        $end = new DateTime($periodEnd);
        $start->modify('first day of this month');
        $end->modify('first day of this month');

        return max(
            1,
            (((int)$end->format('Y') - (int)$start->format('Y')) * 12)
            + ((int)$end->format('n') - (int)$start->format('n'))
            + 1
        );
    }

    private function getLeaveDaySegments(
        string $fromDate,
        string $toDate,
        string $periodStart,
        string $periodEnd,
        float $storedTotalDays
    ): array {
        $from = max(strtotime($fromDate), strtotime($periodStart));
        $to = min(strtotime($toDate), strtotime($periodEnd));

        if (!$from || !$to || $to < $from) {
            return [];
        }

        $fullDays = $this->getDateRangeDays($fromDate, $toDate);
        $dayValue = $fullDays > 0 && $storedTotalDays > 0
            ? round($storedTotalDays / $fullDays, 2)
            : 1.0;
        $dayValue = max(0.0, min(1.0, $dayValue));
        $segments = [];
        $cursor = new DateTime(date('Y-m-d', $from));
        $end = new DateTime(date('Y-m-d', $to));

        while ($cursor <= $end) {
            $segments[] = [
                'date' => $cursor->format('Y-m-d'),
                'days' => $dayValue,
            ];
            $cursor->modify('+1 day');
        }

        return $segments;
    }

    private function isBirthdayLeave(string $date, array $employee, array $settings): bool
    {
        if (empty($settings['enableBirthdayPaidLeave']) || empty($employee['dateOfBirth'])) {
            return false;
        }

        return date('m-d', strtotime($date)) === date('m-d', strtotime((string)$employee['dateOfBirth']));
    }
    
    
    private function processTrainingHold( array $employee, array $settings, float $dailyRate, string $periodStart, string $periodEnd)
    {
        if(
            empty($settings['enableTrainingHoldRule'])
        ){
            return [
                'deduction'=>0
            ];
        }
    
        $joining = $employee['joiningDate'] ?? null;
        
        if(
            !$joining ||
            $joining < $periodStart ||
            $joining > $periodEnd
        ){
            return [
                'deduction'=>0
            ];
        }
    
        $days = (int)($settings['trainingHoldDays'] ?? 0);
        
        if($days <= 0){
            return [
                'deduction'=>0
            ];
        }
    
        $amount = $dailyRate * $days;
    
        $releaseDate = date(
            'Y-m-d',
            strtotime(
                $joining .
                " +" .
                ($settings['trainingAmountReleaseAfterDays'] ?? 90) .
                " days"
            )
        );
    
        // Check if record already exists
        $checkStmt = mysqli_prepare(
            $this->con,
            "SELECT id FROM employeeTrainingHoldSalary 
             WHERE employeeId = ? 
             AND status IN ('Pending', 'Released')
             LIMIT 1"
        );
        
        if($checkStmt){
            mysqli_stmt_bind_param($checkStmt, 'i', $employee['id']);
            mysqli_stmt_execute($checkStmt);
            $checkResult = mysqli_stmt_get_result($checkStmt);
            $exists = mysqli_fetch_assoc($checkResult);
            mysqli_stmt_close($checkStmt);
            
            if($exists){
                return [
                    'deduction' => $amount
                ];
            }
        }
    
        // Insert hold record
        $holdTo = date('Y-m-d', strtotime($joining . ' +' . ($days - 1) . ' days'));
        
        $insertStmt = mysqli_prepare(
            $this->con,
            "INSERT INTO employeeTrainingHoldSalary 
            (employeeId, holdFromDate, holdToDate, holdDays, holdAmount, releaseDate, status)
            VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
        );
        
        if($insertStmt){
            mysqli_stmt_bind_param(
                $insertStmt,
                'issdds',
                $employee['id'],
                $joining,
                $holdTo,
                $days,
                $amount,
                $releaseDate
            );
            mysqli_stmt_execute($insertStmt);
            mysqli_stmt_close($insertStmt);
        }
    
        return [
            "deduction" => $amount
        ];
    }
    
    private function getTrainingReleaseAmount( int $employeeId, string $periodStart, string $periodEnd): float {
        if(
            !$this->tableExists('employeeTrainingHoldSalary')
        ){
            return 0.0;
        }
    
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT 
                id,
                holdAmount
             FROM employeeTrainingHoldSalary
             WHERE employeeId = ?
             AND status = 'Pending'
             AND releaseDate BETWEEN ? AND ?"
        );
    
        if(!$stmt){
            return 0.0;
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'iss',
            $employeeId,
            $periodStart,
            $periodEnd
        );
    
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    
        $amount = 0.0;
        $releaseIds = [];
    
        while(
            $row = mysqli_fetch_assoc($result)
        ){
            $amount += (float)$row['holdAmount'];
            $releaseIds[] = (int)$row['id'];
        }
    
        mysqli_stmt_close($stmt);
    
        if(!empty($releaseIds)){
            $ids = implode(',', $releaseIds);
            mysqli_query(
                $this->con,
                "UPDATE employeeTrainingHoldSalary
                 SET status='Released'
                 WHERE id IN ($ids)"
            );
        }
    
        return $amount;
    }
    
    private function isProbationLeave(string $date, array $employee, array $settings): bool
    {
        if (empty($settings['enableProbationLeaveRule'])
            || empty($employee['joiningDate'])
            || (int)$settings['probationDays'] <= 0
        ) {
            return false;
        }

        $joiningDate = date('Y-m-d', strtotime((string)$employee['joiningDate']));
        $probationEnd = date(
            'Y-m-d',
            strtotime($joiningDate . ' +' . max(0, (int)$settings['probationDays'] - 1) . ' days')
        );

        return $date >= $joiningDate && $date <= $probationEnd;
    }

    private function isNoticePeriodLeave(int $employeeId, string $date, array $settings): bool
    {
        if (empty($settings['enableNoticePeriodLeaveRule']) || !$this->tableExists('resignRequests')) {
            return false;
        }

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id
             FROM resignRequests
             WHERE userId = ?
             AND status = 'Approved'
             AND startDate <= ?
             AND COALESCE(lastWorkingDate, DATE_ADD(startDate, INTERVAL GREATEST(noticeDays - 1, 0) DAY)) >= ?
             LIMIT 1"
        );

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $date, $date);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (bool)$exists;
    }

    private function tableExists(string $tableName): bool
    {
        $safeTable = mysqli_real_escape_string($this->con, $tableName);
        $result = mysqli_query($this->con, "SHOW TABLES LIKE '{$safeTable}'");

        return $result && (bool)mysqli_fetch_row($result);
    }

    private function getOverlapDays(
        string $fromDate,
        string $toDate,
        string $periodStart,
        string $periodEnd,
        float $storedTotalDays
    ): float {
        $from = max(strtotime($fromDate), strtotime($periodStart));
        $to = min(strtotime($toDate), strtotime($periodEnd));

        if (!$from || !$to || $to < $from) {
            return 0.0;
        }

        $overlapDays = ((int)(($to - $from) / 86400)) + 1;
        $fullDays = $this->getDateRangeDays($fromDate, $toDate);

        if ($storedTotalDays > 0 && $fullDays > 0) {
            return round($storedTotalDays * ($overlapDays / $fullDays), 2);
        }

        return (float)$overlapDays;
    }

    private function getApprovedOvertimeHours(int $employeeId, string $periodStart, string $periodEnd): float
    {
        return $this->sumSingleValue(
            "SELECT COALESCE(SUM(calculatedOtHours), 0) AS total
             FROM overtimeRequests
             WHERE employeeId = ?
             AND status = 'approved'
             AND date BETWEEN ? AND ?",
            $employeeId,
            $periodStart,
            $periodEnd
        );
    }

    private function getManualDeductionAmount(int $employeeId, string $periodStart, string $periodEnd): float
    {
        return $this->sumSingleValue(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM employeeDeductions
             WHERE employeeId = ?
             AND deductionDate BETWEEN ? AND ?",
            $employeeId,
            $periodStart,
            $periodEnd
        );
    }

    private function getApprovedExpenseAmount(int $employeeId, string $periodStart, string $periodEnd): float
    {
        return $this->sumSingleValue(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM employeeExpenses
             WHERE employeeId = ?
             AND expenseStatus = 'Approved'
             AND expenseDate BETWEEN ? AND ?",
            $employeeId,
            $periodStart,
            $periodEnd
        );
    }

    private function getSyncedCommissionBonusAmount(int $employeeId, string $periodStart, string $periodEnd): float
    {
        $startMonth = substr($periodStart, 0, 7);
        $endMonth = substr($periodEnd, 0, 7);

        return $this->sumSingleValue(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM employeeCommissionTransactions
             WHERE employeeId = ?
             AND approvalStatus = 'Approved'
             AND payrollStatus IN ('Synced', 'Paid')
             AND isReverted = 0
             AND effectiveMonth BETWEEN ? AND ?",
            $employeeId,
            $startMonth,
            $endMonth
        );
    }

    private function getEarningsRows(
        array $employee,
        float $salaryAmount,
        float $overtimeAmount,
        float $commissionBonusAmount
    ): array {
        $basicMaster = (float)($employee['basicSalary'] ?? 0);
        $hraMaster = (float)($employee['hraAmount'] ?? 0);
        $allowanceMaster = (float)($employee['allowanceAmount'] ?? 0);
        $masterTotal = $basicMaster + $hraMaster + $allowanceMaster;
        $factor = $masterTotal > 0 ? min(1.0, $salaryAmount / $masterTotal) : 0.0;

        $rows = [
            [
                'label' => 'Basic',
                'master' => $this->roundAmount($basicMaster, 'two_decimal'),
                'amount' => $this->roundAmount($basicMaster * $factor, 'two_decimal'),
            ],
            [
                'label' => 'House Rent Allowance',
                'master' => $this->roundAmount($hraMaster, 'two_decimal'),
                'amount' => $this->roundAmount($hraMaster * $factor, 'two_decimal'),
            ],
            [
                'label' => 'Special Allowance',
                'master' => $this->roundAmount($allowanceMaster, 'two_decimal'),
                'amount' => $this->roundAmount($allowanceMaster * $factor, 'two_decimal'),
            ],
            [
                'label' => 'Statutory Bonus',
                'master' => 0.0,
                'amount' => 0.0,
            ],
            [
                'label' => 'LTA Allowance',
                'master' => 0.0,
                'amount' => 0.0,
            ],
        ];

        // Keeps the displayed rows reconciling to $salaryAmount even though
        // Basic/HRA/Special Allowance are only proportionally scaled from
        // their master values (see $factor above) -- unrelated to the new
        // editable "Other Earning"/"Other Allowances" rows below, which are
        // genuinely independent amounts, not a balancing figure.
        $componentTotal = array_sum(array_column($rows, 'amount'));
        $salaryBalance = max(0.0, $salaryAmount - $componentTotal);

        $rows[] = [
            'label' => 'Balance Adjustment',
            'master' => $this->roundAmount($salaryBalance, 'two_decimal'),
            'amount' => $this->roundAmount($salaryBalance, 'two_decimal'),
        ];
        $rows[] = [
            'label' => 'Overtime',
            'master' => $this->roundAmount($overtimeAmount, 'two_decimal'),
            'amount' => $this->roundAmount($overtimeAmount, 'two_decimal'),
        ];
        $rows[] = [
            'label' => 'Commission / Bonus',
            'master' => $this->roundAmount($commissionBonusAmount, 'two_decimal'),
            'amount' => $this->roundAmount($commissionBonusAmount, 'two_decimal'),
        ];

        // Genuinely editable per-employee amounts (employeeusers.other*
        // Amount) -- additive on top of $salaryAmount, same pattern as
        // overtime/commission above. See calculateSalarySlip()'s
        // $otherEarningsTotal for where these are added to gross pay.
        $rows[] = [
            'label' => 'Other Earning 1',
            'master' => $this->roundAmount((float)($employee['otherEarning1Amount'] ?? 0), 'two_decimal'),
            'amount' => $this->roundAmount((float)($employee['otherEarning1Amount'] ?? 0), 'two_decimal'),
        ];
        $rows[] = [
            'label' => 'Other Earning 2',
            'master' => $this->roundAmount((float)($employee['otherEarning2Amount'] ?? 0), 'two_decimal'),
            'amount' => $this->roundAmount((float)($employee['otherEarning2Amount'] ?? 0), 'two_decimal'),
        ];
        $rows[] = [
            'label' => 'Other Earning 3',
            'master' => $this->roundAmount((float)($employee['otherEarning3Amount'] ?? 0), 'two_decimal'),
            'amount' => $this->roundAmount((float)($employee['otherEarning3Amount'] ?? 0), 'two_decimal'),
        ];
        $rows[] = [
            'label' => 'Other Allowances',
            'master' => $this->roundAmount((float)($employee['otherAllowanceAmount'] ?? 0), 'two_decimal'),
            'amount' => $this->roundAmount((float)($employee['otherAllowanceAmount'] ?? 0), 'two_decimal'),
        ];

        return $rows;
    }

    private function getOtherEarningsTotal(array $employee): float
    {
        return (float)($employee['otherEarning1Amount'] ?? 0)
            + (float)($employee['otherEarning2Amount'] ?? 0)
            + (float)($employee['otherEarning3Amount'] ?? 0)
            + (float)($employee['otherAllowanceAmount'] ?? 0);
    }

    private function getStatutoryDeductions(
        array $employee,
        array $settings,
        array $earningsRows,
        float $grossEarnings
    ): array {
        $basicAmount = $this->findRowAmount($earningsRows, 'Basic');
        $providentFundBasis = (string)$settings['providentFundBasis'] === 'gross'
            ? $grossEarnings
            : $basicAmount;
        $pfCeiling = (float)($settings['providentFundWageCeiling'] ?? 0);

        if ($pfCeiling > 0) {
            $providentFundBasis = min($providentFundBasis, $pfCeiling);
        }

        $providentFund = !empty($settings['includeProvidentFund'])
            ? $providentFundBasis * ((float)$settings['providentFundPercent'] / 100)
            : 0.0;

        $esicEligible = (float)($settings['esicWageLimit'] ?? 0) <= 0
            || $grossEarnings <= (float)$settings['esicWageLimit'];
        $esic = !empty($settings['includeEsic']) && $esicEligible
            ? $grossEarnings * ((float)$settings['esicEmployeePercent'] / 100)
            : 0.0;

        $professionalTax = !empty($settings['includeProfessionalTax'])
            ? (float)$settings['professionalTaxAmount']
            : 0.0;

        $incomeTaxTds = 0.0;

        if (!empty($settings['includeIncomeTaxTds'])) {
            $incomeTaxTds = (string)$settings['incomeTaxTdsType'] === 'percent'
                ? $grossEarnings * ((float)$settings['incomeTaxTdsPercent'] / 100)
                : (float)$settings['incomeTaxTdsAmount'];
        }

        $gstAmount = $this->shouldApplyGst($employee, $settings)
            ? $grossEarnings * ((float)$settings['gstPercent'] / 100)
            : 0.0;

        $rows = [
            [
                'label' => 'Income Tax Deduction',
                'amount' => $this->roundAmount($incomeTaxTds, 'two_decimal'),
            ],
            [
                'label' => 'Profession Tax',
                'amount' => $this->roundAmount($professionalTax, 'two_decimal'),
            ],
            [
                'label' => 'P.F.',
                'amount' => $this->roundAmount($providentFund, 'two_decimal'),
            ],
            [
                'label' => 'ESIC',
                'amount' => $this->roundAmount($esic, 'two_decimal'),
            ],
        ];

        $totalDeductions = $incomeTaxTds + $professionalTax + $providentFund + $esic;

        return [
            'rows' => $rows,
            'incomeTaxTds' => $incomeTaxTds,
            'professionalTax' => $professionalTax,
            'providentFund' => $providentFund,
            'esic' => $esic,
            'gstAmount' => $gstAmount,
            'totalDeductions' => $totalDeductions,
        ];
    }

    private function getReimbursementRows(float $expenseAmount, float $gstAmount): array
    {
        return [
            [
                'label' => 'Reimbursement 1',
                'master' => $this->roundAmount($expenseAmount, 'two_decimal'),
                'amount' => $this->roundAmount($expenseAmount, 'two_decimal'),
            ],
            [
                'label' => 'GST',
                'master' => $this->roundAmount($gstAmount, 'two_decimal'),
                'amount' => $this->roundAmount($gstAmount, 'two_decimal'),
            ],
        ];
    }

    private function findRowAmount(array $rows, string $label): float
    {
        foreach ($rows as $row) {
            if ((string)($row['label'] ?? '') === $label) {
                return (float)($row['amount'] ?? 0);
            }
        }

        return 0.0;
    }

    private function shouldApplyGst(array $employee, array $settings): bool
    {
        if (empty($settings['includeGst'])) {
            return false;
        }

        $employeeType = strtolower((string)($employee['employeeType'] ?? ''));
        $gstTypes = ['consultant', 'contractor', 'vendor', 'freelancer'];

        foreach ($gstTypes as $type) {
            if (strpos($employeeType, $type) !== false) {
                return true;
            }
        }

        return false;
    }

    private function getPointPayrollDeduction(
        int $employeeId,
        string $periodStart,
        string $periodEnd,
        float $salaryAmount,
        array $settings
    ): array {
        $threshold = (float)($settings['pointDeductionThreshold'] ?? 0);
        $percent = (float)($settings['pointDeductionPercent'] ?? 0);

        if ($salaryAmount <= 0 || $threshold <= 0 || $percent <= 0) {
            return [
                'amount' => 0.0,
                'impactPoints' => 0.0,
                'hitCount' => 0,
            ];
        }

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT t.points
             FROM employeePointTransactions t
             INNER JOIN employeePointCategories c
                ON c.id = t.categoryId
             WHERE t.employeeId = ?
             AND t.transactionType = 'Debit'
             AND t.approvalStatus = 'Approved'
             AND t.isReverted = 0
             AND COALESCE(c.payrollImpact, 0) = 1
             AND t.transactionDate BETWEEN ? AND ?
             ORDER BY t.transactionDate ASC, t.id ASC"
        );

        if (!$stmt) {
            return [
                'amount' => 0.0,
                'impactPoints' => 0.0,
                'hitCount' => 0,
            ];
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $impactPoints = 0.0;
        $entryCount = 0;
        $firstThresholdEntry = null;

        while ($row = mysqli_fetch_assoc($result)) {
            $points = max(0.0, (float)($row['points'] ?? 0));

            if ($points <= 0) {
                continue;
            }

            $entryCount++;
            $impactPoints += $points;

            if ($firstThresholdEntry === null && $impactPoints >= $threshold) {
                $firstThresholdEntry = $entryCount;
            }
        }

        mysqli_stmt_close($stmt);

        if ($firstThresholdEntry === null) {
            return [
                'amount' => 0.0,
                'impactPoints' => $impactPoints,
                'hitCount' => 0,
            ];
        }

        $hitCount = $entryCount - $firstThresholdEntry + 1;
        $rate = $percent / 100;
        $amount = !empty($settings['compoundPointDeduction'])
            ? $salaryAmount - ($salaryAmount * pow(1 - $rate, $hitCount))
            : min($salaryAmount, $salaryAmount * $rate * $hitCount);

        return [
            'amount' => round(max(0.0, $amount), 2),
            'impactPoints' => round($impactPoints, 2),
            'hitCount' => $hitCount,
        ];
    }

    private function sumSingleValue(
        string $sql,
        int $employeeId,
        string $periodStart,
        string $periodEnd
    ): float {
        $stmt = mysqli_prepare($this->con, $sql);

        if (!$stmt) {
            return 0.0;
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return (float)($row['total'] ?? 0);
    }

    private function allowedValue($value, array $allowed, string $default): string
    {
        $value = trim((string)$value);

        return in_array($value, $allowed, true) ? $value : $default;
    }

    private function cleanPercent($value): float
    {
        return min(100, max(0, (float)$value));
    }

    private function roundAmount(float $amount, string $roundingRule)
    {
        if ($roundingRule === 'floor_rupee') {
            return floor($amount);
        }

        if ($roundingRule === 'ceil_rupee') {
            return ceil($amount);
        }

        if ($roundingRule === 'nearest_rupee') {
            return round($amount);
        }

        return round($amount, 2);
    }
    
    
    private function getApprovedUnpaidExpenses(int $employeeId, string $salaryGeneratedAt ): array 
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                id,
                amount
            FROM employeeExpenses
            WHERE employeeId = ?
              AND expenseStatus = 'Approved'
              AND paymentStatus = 'unpaid'
              AND approvedAt <= ?
            ORDER BY approvedAt ASC"
        );
    
        if (!$stmt) {
            return [
                'amount' => 0.0,
                'ids' => []
            ];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'is',
            $employeeId,
            $salaryGeneratedAt
        );
    
        mysqli_stmt_execute($stmt);
    
        $result = mysqli_stmt_get_result($stmt);
    
        $totalAmount = 0.0;
        $expenseIds = [];
    
        while ($row = mysqli_fetch_assoc($result)) {
    
            $totalAmount += (float)$row['amount'];
            $expenseIds[] = (int)$row['id'];
    
        }
    
        mysqli_stmt_close($stmt);
    
        return [
            'amount' => $totalAmount,
            'ids'    => $expenseIds
        ];
    }

    /**
     * Each Approved advance is evaluated independently by its own
     * "Payment Adjustment" mode (AdvancePaymentEngine::ADJUSTMENT_TYPES):
     *
     *   - Not Adjusted    -> contributes 0, not touched at all.
     *   - Upcoming Salary -> full amount, swept in one shot, same "sweep
     *                        whatever's outstanding" shape as
     *                        getApprovedUnpaidExpenses() above. Returned
     *                        under 'ids' (kept as the existing key name,
     *                        since PayrollApprovalEngine::addPayment()
     *                        already marks whatever is in 'advanceIds' as
     *                        Completed on full payment).
     *   - Partial Payment -> only the employeeAdvanceRepayments
     *                        installment(s) whose salaryMonth falls in
     *                        this payroll period, same month-scoped
     *                        matching as getPendingLoanRepayment() below.
     *                        Returned separately under
     *                        'partialInstallmentIds' since finalizing one
     *                        doesn't complete the advance by itself -- see
     *                        PayrollApprovalEngine::addPayment().
     *
     * Read-only: nothing here marks an advance or installment as consumed.
     * That only happens once the resulting salary slip is actually
     * approved AND fully paid -- see PayrollApprovalEngine::addPayment().
     * Doing it here instead (i.e. on every calculation, including a mere
     * preview) would risk marking an advance as recovered before any
     * salary was actually paid out.
     */
    private function getApprovedAdvanceDeduction(int $employeeId, string $periodStart, string $periodEnd): array
    {
        $totalAmount = 0.0;
        $upcomingIds = [];
        $partialInstallmentIds = [];

        $upcomingStmt = mysqli_prepare(
            $this->con,
            "SELECT id, amount
            FROM employeeAdvancePayments
            WHERE employeeId = ?
            AND status = 'Approved'
            AND paymentAdjustment = 'Upcoming Salary'
            ORDER BY paymentDate ASC"
        );

        if ($upcomingStmt) {
            mysqli_stmt_bind_param($upcomingStmt, 'i', $employeeId);
            mysqli_stmt_execute($upcomingStmt);
            $result = mysqli_stmt_get_result($upcomingStmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $totalAmount += (float)$row['amount'];
                $upcomingIds[] = (int)$row['id'];
            }

            mysqli_stmt_close($upcomingStmt);
        }

        $startMonth = substr($periodStart, 0, 7);
        $endMonth = substr($periodEnd, 0, 7);

        $partialStmt = mysqli_prepare(
            $this->con,
            "SELECT r.id, r.scheduledAmount
            FROM employeeAdvanceRepayments r
            INNER JOIN employeeAdvancePayments a ON a.id = r.advanceId
            WHERE r.employeeId = ?
            AND r.status = 'Pending'
            AND r.salaryMonth BETWEEN ? AND ?
            AND a.status = 'Approved'
            AND a.paymentAdjustment = 'Partial Payment'
            ORDER BY r.salaryMonth ASC"
        );

        if ($partialStmt) {
            mysqli_stmt_bind_param($partialStmt, 'iss', $employeeId, $startMonth, $endMonth);
            mysqli_stmt_execute($partialStmt);
            $result = mysqli_stmt_get_result($partialStmt);

            while ($row = mysqli_fetch_assoc($result)) {
                $totalAmount += (float)$row['scheduledAmount'];
                $partialInstallmentIds[] = (int)$row['id'];
            }

            mysqli_stmt_close($partialStmt);
        }

        return [
            'amount' => $totalAmount,
            'ids' => $upcomingIds,
            'partialInstallmentIds' => $partialInstallmentIds,
        ];
    }

    /**
     * Pending loan repayment installments whose salaryMonth falls within
     * this payroll period -- same month-scoped matching already used by
     * getSyncedCommissionBonusAmount() (effectiveMonth) and
     * getTrainingReleaseAmount() (releaseDate). Read-only for the same
     * reason as getApprovedAdvanceDeduction() above -- rows are only
     * marked 'Deducted' once the slip is approved and fully paid, in
     * PayrollApprovalEngine::addPayment().
     */
    private function getPendingLoanRepayment(int $employeeId, string $periodStart, string $periodEnd): array
    {
        $startMonth = substr($periodStart, 0, 7);
        $endMonth = substr($periodEnd, 0, 7);

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id, deductionAmount
            FROM employeeLoanRepayments
            WHERE employeeId = ?
            AND status = 'Pending'
            AND salaryMonth BETWEEN ? AND ?
            ORDER BY salaryMonth ASC"
        );

        if (!$stmt) {
            return ['amount' => 0.0, 'ids' => []];
        }

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $startMonth, $endMonth);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $totalAmount = 0.0;
        $ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $totalAmount += (float)$row['deductionAmount'];
            $ids[] = (int)$row['id'];
        }

        mysqli_stmt_close($stmt);

        return ['amount' => $totalAmount, 'ids' => $ids];
    }
}
