<?php

/*
|--------------------------------------------------------------------------
| Advance Payment Engine
|--------------------------------------------------------------------------
|
| HR-recorded advance payments given to employees (pages/advance-payment.php).
| Each advance carries a "Payment Adjustment" mode (paymentAdjustment) that
| controls how/when PayrollEngine::getApprovedAdvanceDeduction() sweeps it:
|
|   - Not Adjusted    -> never deducted (default; outstanding indefinitely).
|   - Upcoming Salary -> full lump sum on the next applicable payroll run,
|                        same "sweep once" behavior this engine always had.
|   - Partial Payment -> a manually-configured recovery plan (NOT an
|                        auto-computed EMI): HR enters months + a monthly
|                        amount, and employeeAdvanceRepayments holds one row
|                        per due salary month, mirroring
|                        EmployeeLoanEngine/employeeLoanRepayments exactly
|                        (generated at approval time, Pending -> Deducted/
|                        Waived). The final installment is auto-capped to
|                        the remaining balance -- see generateRepaymentSchedule().
|
| salaryAdjustmentStatus (Not Adjusted/Adjusted) is a separate, pre-existing
| completion flag flipped by PayrollApprovalEngine::addPayment() once the
| full advance amount has actually been recovered -- unrelated to which
| adjustment mode was chosen.
|
| Lifecycle: Pending -> Approved -> Completed (deducted in a fully-paid
| salary slip, see PayrollApprovalEngine::addPayment()) or -> Cancelled.
| Only Pending advances can be edited or cancelled directly here; once
| Approved, cancellation must go through cancel() which still allows it
| (an approved-but-not-yet-deducted advance can be called off), but never
| once Completed (already baked into a paid salary slip).
|
*/

class AdvancePaymentEngine
{
    private $con;

    public const STATUSES = ['Pending', 'Approved', 'Completed', 'Cancelled'];
    public const ADJUSTMENT_TYPES = ['Not Adjusted', 'Upcoming Salary', 'Partial Payment'];

    // Guards against a mistaken/negligible monthly amount silently
    // generating an unreasonable number of installments -- "duration" is
    // only the *intended* period (max 12), not a hard cap on the schedule,
    // per spec section 4, but this keeps a data-entry mistake from
    // producing thousands of rows.
    private const MAX_PARTIAL_INSTALLMENTS = 60;

    public function __construct($con)
    {
        $this->con = $con;
    }

    public function getList($filters = [])
    {
        $where = ['1=1'];
        $params = [];
        $types = '';

        $employeeId = (int)($filters['employeeId'] ?? 0);
        if ($employeeId > 0) {
            $where[] = 'a.employeeId = ?';
            $params[] = $employeeId;
            $types .= 'i';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 'a.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(eu.fullName LIKE ? OR a.transactionNo LIKE ? OR a.referenceNo LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $types .= 'sss';
        }

        $sql = "
            SELECT
                a.id, a.employeeId, a.amount, a.paymentDate, a.transactionNo,
                a.paymentMode, a.referenceNo, a.remark, a.status, a.salaryAdjustmentStatus,
                a.paymentAdjustment, a.partialMonths, a.partialMonthlyAmount, a.partialFirstSalaryMonth,
                a.createdBy, a.approvedBy, a.approvedAt, a.createdAt, a.updatedAt,
                eu.fullName AS employeeName,
                creator.fullName AS createdByName,
                approver.fullName AS approvedByName,
                (SELECT COUNT(*) FROM employeeAdvanceRepayments r WHERE r.advanceId = a.id) AS totalInstallments,
                (SELECT COUNT(*) FROM employeeAdvanceRepayments r WHERE r.advanceId = a.id AND r.status = 'Deducted') AS paidInstallments,
                (SELECT COALESCE(SUM(deductedAmount), 0) FROM employeeAdvanceRepayments r WHERE r.advanceId = a.id AND r.status = 'Deducted') AS amountRecovered
            FROM employeeAdvancePayments a
            INNER JOIN employeeusers eu ON eu.id = a.employeeId
            LEFT JOIN users creator ON creator.id = a.createdBy
            LEFT JOIN users approver ON approver.id = a.approvedBy
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.paymentDate DESC, a.id DESC
        ";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            throw new Exception('Failed to load advance payments: ' . mysqli_error($this->con));
        }

        if ($types !== '') {
            mysqli_stmt_bind_param($stmt, $types, ...$params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $list = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['employeeId'] = (int)$row['employeeId'];
            $row['amount'] = (float)$row['amount'];
            $row['partialMonths'] = $row['partialMonths'] !== null ? (int)$row['partialMonths'] : null;
            $row['partialMonthlyAmount'] = $row['partialMonthlyAmount'] !== null ? (float)$row['partialMonthlyAmount'] : null;
            $row['totalInstallments'] = (int)$row['totalInstallments'];
            $row['paidInstallments'] = (int)$row['paidInstallments'];
            $row['amountRecovered'] = (float)$row['amountRecovered'];
            $row['balanceAmount'] = round($row['amount'] - $row['amountRecovered'], 2);
            $row['createdByName'] = $row['createdByName'] ?? 'Admin';
            $row['approvedByName'] = $row['approvedByName'] ?? null;
            $list[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $list;
    }

    private function getById($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare($this->con, "SELECT * FROM employeeAdvancePayments WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    /**
     * @throws Exception on validation failure
     */
    public function saveAdvance($data, $userId)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $employeeId = (int)($data['employeeId'] ?? 0);
        $amount = (float)($data['amount'] ?? 0);
        $paymentDate = trim((string)($data['paymentDate'] ?? ''));
        $transactionNo = trim((string)($data['transactionNo'] ?? ''));
        $paymentMode = trim((string)($data['paymentMode'] ?? ''));
        $referenceNo = trim((string)($data['referenceNo'] ?? ''));
        $remark = trim((string)($data['remark'] ?? ''));

        if ($employeeId <= 0) {
            throw new Exception('Employee is required.');
        }
        if ($amount <= 0) {
            throw new Exception('Amount must be greater than 0.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $paymentDate)) {
            throw new Exception('A valid payment date is required.');
        }

        [$paymentAdjustment, $partialMonths, $partialMonthlyAmount, $partialFirstSalaryMonth] =
            $this->validateAdjustmentConfig($data, $amount);

        if ($id > 0) {
            $existing = $this->getById($id);
            if (!$existing) {
                throw new Exception('Advance payment not found.');
            }
            // Only Pending advances can be edited -- matches "Edit (only
            // before approval)" from the spec. This also covers the
            // Payment Adjustment config itself: it can only be set/changed
            // before approval, never after a repayment schedule has been
            // generated or an installment finalized.
            if ($existing['status'] !== 'Pending') {
                throw new Exception('Only pending advance payments can be edited.');
            }

            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE employeeAdvancePayments
                SET employeeId = ?, amount = ?, paymentDate = ?, transactionNo = ?,
                    paymentMode = ?, referenceNo = ?, remark = ?,
                    paymentAdjustment = ?, partialMonths = ?, partialMonthlyAmount = ?,
                    partialFirstSalaryMonth = ?, adjustmentUpdatedBy = ?, adjustmentUpdatedAt = NOW()
                WHERE id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'idssssssidsii',
                $employeeId,
                $amount,
                $paymentDate,
                $transactionNo,
                $paymentMode,
                $referenceNo,
                $remark,
                $paymentAdjustment,
                $partialMonths,
                $partialMonthlyAmount,
                $partialFirstSalaryMonth,
                $userId,
                $id
            );
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO employeeAdvancePayments
                (employeeId, amount, paymentDate, transactionNo, paymentMode, referenceNo, remark,
                 status, createdBy, paymentAdjustment, partialMonths, partialMonthlyAmount,
                 partialFirstSalaryMonth, adjustmentUpdatedBy, adjustmentUpdatedAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, NOW())"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'idsssssisidsi',
                $employeeId,
                $amount,
                $paymentDate,
                $transactionNo,
                $paymentMode,
                $referenceNo,
                $remark,
                $userId,
                $paymentAdjustment,
                $partialMonths,
                $partialMonthlyAmount,
                $partialFirstSalaryMonth,
                $userId
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save advance payment: ' . $error);
        }

        $savedId = $id > 0 ? $id : mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return $savedId;
    }

    /**
     * Validates the "Payment Adjustment" mode and, for Partial Payment,
     * its manual recovery-plan config (section 5 of the spec). Returns
     * [paymentAdjustment, partialMonths, partialMonthlyAmount, partialFirstSalaryMonth]
     * -- the latter three are null for any mode other than Partial Payment.
     *
     * @throws Exception on validation failure
     */
    private function validateAdjustmentConfig(array $data, float $amount): array
    {
        $paymentAdjustment = trim((string)($data['paymentAdjustment'] ?? 'Not Adjusted'));
        if ($paymentAdjustment === '') {
            $paymentAdjustment = 'Not Adjusted';
        }
        if (!in_array($paymentAdjustment, self::ADJUSTMENT_TYPES, true)) {
            throw new Exception('Invalid payment adjustment type.');
        }

        if ($paymentAdjustment !== 'Partial Payment') {
            return [$paymentAdjustment, null, null, null];
        }

        $months = (int)($data['partialMonths'] ?? 0);
        $monthlyAmount = (float)($data['partialMonthlyAmount'] ?? 0);
        $firstSalaryMonth = trim((string)($data['partialFirstSalaryMonth'] ?? ''));

        if ($months < 1 || $months > 12) {
            throw new Exception('Number of months must be between 1 and 12.');
        }
        if ($monthlyAmount <= 0) {
            throw new Exception('Monthly deduction amount must be greater than 0.');
        }
        // "Must not exceed the Advance Amount" -- checked against the full
        // amount here (at configuration time the recovered balance always
        // equals the full amount); the schedule generator additionally caps
        // every individual installment to whatever balance remains.
        if ($monthlyAmount > $amount) {
            throw new Exception('Monthly deduction amount must not exceed the advance amount.');
        }
        if (!preg_match('/^\d{4}-\d{2}$/', $firstSalaryMonth)) {
            throw new Exception('A valid first salary month (YYYY-MM) is required.');
        }
        if (ceil($amount / $monthlyAmount) > self::MAX_PARTIAL_INSTALLMENTS) {
            throw new Exception('Monthly deduction amount is too small to recover the advance within a reasonable number of installments.');
        }

        return [$paymentAdjustment, $months, $monthlyAmount, $firstSalaryMonth];
    }

    /**
     * @throws Exception on validation failure
     */
    public function approve($id, $approvedBy)
    {
        $advance = $this->getById($id);
        if (!$advance) {
            throw new Exception('Advance payment not found.');
        }
        if ($advance['status'] !== 'Pending') {
            throw new Exception('Only pending advance payments can be approved.');
        }

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE employeeAdvancePayments
            SET status = 'Approved', approvedBy = ?, approvedAt = NOW()
            WHERE id = ?"
        );
        $id = (int)$id;
        mysqli_stmt_bind_param($stmt, 'ii', $approvedBy, $id);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to approve advance payment: ' . $error);
        }
        mysqli_stmt_close($stmt);

        if ($advance['paymentAdjustment'] === 'Partial Payment') {
            $this->generateRepaymentSchedule($advance);
        }

        return true;
    }

    /**
     * Generates the Partial Payment recovery schedule -- one
     * employeeAdvanceRepayments row per due salary month, starting from
     * partialFirstSalaryMonth. Each installment is min(monthlyAmount,
     * remainingBalance), so the schedule naturally stops the moment the
     * full advance is recovered (no trailing zero-value rows) and the
     * final installment is automatically reduced to whatever remains --
     * see spec sections 3/4. Idempotent, same guard as
     * EmployeeLoanEngine::generateRepaymentSchedule().
     */
    private function generateRepaymentSchedule(array $advance): void
    {
        $advanceId = (int)$advance['id'];

        $existingCheck = mysqli_prepare($this->con, "SELECT id FROM employeeAdvanceRepayments WHERE advanceId = ? LIMIT 1");
        mysqli_stmt_bind_param($existingCheck, 'i', $advanceId);
        mysqli_stmt_execute($existingCheck);
        $already = mysqli_fetch_assoc(mysqli_stmt_get_result($existingCheck));
        mysqli_stmt_close($existingCheck);

        if ($already) {
            return;
        }

        $employeeId = (int)$advance['employeeId'];
        $monthlyAmount = (float)$advance['partialMonthlyAmount'];
        $remaining = round((float)$advance['amount'], 2);

        $stmt = mysqli_prepare(
            $this->con,
            "INSERT INTO employeeAdvanceRepayments
            (advanceId, employeeId, salaryMonth, scheduledAmount, status)
            VALUES (?, ?, ?, ?, 'Pending')"
        );

        $cursor = new DateTime((string)$advance['partialFirstSalaryMonth'] . '-01');
        $installments = 0;

        while ($remaining > 0.004 && $installments < self::MAX_PARTIAL_INSTALLMENTS) {
            $salaryMonth = $cursor->format('Y-m');
            $installmentAmount = min($monthlyAmount, $remaining);

            mysqli_stmt_bind_param($stmt, 'iisd', $advanceId, $employeeId, $salaryMonth, $installmentAmount);
            mysqli_stmt_execute($stmt);

            $remaining = round($remaining - $installmentAmount, 2);
            $cursor->modify('+1 month');
            $installments++;
        }

        mysqli_stmt_close($stmt);
    }

    /**
     * Partial Payment repayment schedule for one advance -- same shape as
     * EmployeeLoanEngine::getRepaymentSchedule().
     */
    public function getRepaymentSchedule($advanceId)
    {
        $advanceId = (int)$advanceId;
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id, advanceId, employeeId, salaryMonth, scheduledAmount, deductedAmount, status, deductedAt
            FROM employeeAdvanceRepayments
            WHERE advanceId = ?
            ORDER BY salaryMonth ASC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $advanceId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $schedule = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['scheduledAmount'] = (float)$row['scheduledAmount'];
            $row['deductedAmount'] = $row['deductedAmount'] !== null ? (float)$row['deductedAmount'] : null;
            $schedule[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $schedule;
    }

    /**
     * @throws Exception on validation failure
     */
    public function cancel($id)
    {
        $advance = $this->getById($id);
        if (!$advance) {
            throw new Exception('Advance payment not found.');
        }
        // Once actually deducted from a paid salary slip, it's part of
        // payroll history and can no longer be cancelled -- matches "No
        // hard delete... maintain history."
        if ($advance['status'] === 'Completed') {
            throw new Exception('A completed advance payment cannot be cancelled.');
        }
        if ($advance['status'] === 'Cancelled') {
            throw new Exception('Advance payment is already cancelled.');
        }

        $id = (int)$id;
        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE employeeAdvancePayments SET status = 'Cancelled' WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'i', $id);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to cancel advance payment: ' . $error);
        }
        mysqli_stmt_close($stmt);

        // Waive any Partial Payment installments that haven't been deducted
        // yet -- keeps the row (history), just stops it from ever being
        // swept into a future payroll run. Mirrors EmployeeLoanEngine::cancel().
        mysqli_query(
            $this->con,
            "UPDATE employeeAdvanceRepayments SET status = 'Waived' WHERE advanceId = $id AND status = 'Pending'"
        );

        return true;
    }
}
