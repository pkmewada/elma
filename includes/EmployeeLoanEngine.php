<?php

/*
|--------------------------------------------------------------------------
| Employee Loan Engine
|--------------------------------------------------------------------------
|
| Employee loans repaid through salary (pages/employee-loan.php). Mirrors
| AdvancePaymentEngine's lifecycle (Pending -> Approved -> Completed /
| Cancelled) plus a fixed monthly repayment schedule (employeeLoanRepayments),
| one row per due month -- the same "row per payroll month" shape already
| used by employeeTrainingHoldSalary/employeeCommissionTransactions.
|
| The schedule is generated once, at approval time (not at creation), so a
| loan that's still Pending never has repayment rows competing for salary
| deductions. Interest was NOT added -- nothing in the existing payroll
| architecture computes interest anywhere, and the brief only asked for it
| "if existing payroll supports it."
|
*/

class EmployeeLoanEngine
{
    private $con;

    public const STATUSES = ['Pending', 'Approved', 'Completed', 'Cancelled'];

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
            $where[] = 'l.employeeId = ?';
            $params[] = $employeeId;
            $types .= 'i';
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 'l.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = 'eu.fullName LIKE ?';
            $params[] = '%' . $search . '%';
            $types .= 's';
        }

        $sql = "
            SELECT
                l.id, l.employeeId, l.loanAmount, l.startDate, l.duration,
                l.monthlyDeduction, l.remark, l.status,
                l.createdBy, l.approvedBy, l.approvedAt, l.createdAt, l.updatedAt,
                eu.fullName AS employeeName,
                creator.fullName AS createdByName,
                approver.fullName AS approvedByName,
                (SELECT COUNT(*) FROM employeeLoanRepayments r WHERE r.loanId = l.id) AS totalInstallments,
                (SELECT COUNT(*) FROM employeeLoanRepayments r WHERE r.loanId = l.id AND r.status = 'Deducted') AS paidInstallments,
                (SELECT COALESCE(SUM(deductionAmount), 0) FROM employeeLoanRepayments r WHERE r.loanId = l.id AND r.status = 'Deducted') AS amountRecovered
            FROM employeeLoans l
            INNER JOIN employeeusers eu ON eu.id = l.employeeId
            LEFT JOIN users creator ON creator.id = l.createdBy
            LEFT JOIN users approver ON approver.id = l.approvedBy
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.startDate DESC, l.id DESC
        ";

        $stmt = mysqli_prepare($this->con, $sql);
        if (!$stmt) {
            throw new Exception('Failed to load loans: ' . mysqli_error($this->con));
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
            $row['loanAmount'] = (float)$row['loanAmount'];
            $row['duration'] = (int)$row['duration'];
            $row['monthlyDeduction'] = (float)$row['monthlyDeduction'];
            $row['totalInstallments'] = (int)$row['totalInstallments'];
            $row['paidInstallments'] = (int)$row['paidInstallments'];
            $row['amountRecovered'] = (float)$row['amountRecovered'];
            $row['balanceAmount'] = round($row['loanAmount'] - $row['amountRecovered'], 2);
            $row['createdByName'] = $row['createdByName'] ?? 'Admin';
            $row['approvedByName'] = $row['approvedByName'] ?? null;
            $list[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $list;
    }

    public function getRepaymentSchedule($loanId)
    {
        $loanId = (int)$loanId;
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id, loanId, employeeId, salaryMonth, deductionAmount, status, deductedAt
            FROM employeeLoanRepayments
            WHERE loanId = ?
            ORDER BY salaryMonth ASC"
        );
        mysqli_stmt_bind_param($stmt, 'i', $loanId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $schedule = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $row['deductionAmount'] = (float)$row['deductionAmount'];
            $schedule[] = $row;
        }
        mysqli_stmt_close($stmt);

        return $schedule;
    }

    private function getById($id)
    {
        $id = (int)$id;
        $stmt = mysqli_prepare($this->con, "SELECT * FROM employeeLoans WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    /**
     * @throws Exception on validation failure
     */
    public function saveLoan($data, $userId)
    {
        $id = isset($data['id']) ? (int)$data['id'] : 0;
        $employeeId = (int)($data['employeeId'] ?? 0);
        $loanAmount = (float)($data['loanAmount'] ?? 0);
        $startDate = trim((string)($data['startDate'] ?? ''));
        $duration = (int)($data['duration'] ?? 0);
        $monthlyDeduction = (float)($data['monthlyDeduction'] ?? 0);
        $remark = trim((string)($data['remark'] ?? ''));

        if ($employeeId <= 0) {
            throw new Exception('Employee is required.');
        }
        if ($loanAmount <= 0) {
            throw new Exception('Loan amount must be greater than 0.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) {
            throw new Exception('A valid loan start date is required.');
        }
        if ($duration < 1 || $duration > 12) {
            throw new Exception('Duration must be between 1 and 12 months.');
        }
        // Defaults to an even split when left blank -- editable so HR can
        // round the installment (e.g. 60000/12 = 5000 exactly, but an
        // uneven amount rounds up here and the last installment absorbs
        // the remainder in generateRepaymentSchedule()).
        if ($monthlyDeduction <= 0) {
            $monthlyDeduction = round($loanAmount / $duration, 2);
        }

        if ($id > 0) {
            $existing = $this->getById($id);
            if (!$existing) {
                throw new Exception('Loan not found.');
            }
            if ($existing['status'] !== 'Pending') {
                throw new Exception('Only pending loans can be edited.');
            }

            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE employeeLoans
                SET employeeId = ?, loanAmount = ?, startDate = ?, duration = ?,
                    monthlyDeduction = ?, remark = ?
                WHERE id = ?"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'idsidsi',
                $employeeId,
                $loanAmount,
                $startDate,
                $duration,
                $monthlyDeduction,
                $remark,
                $id
            );
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO employeeLoans
                (employeeId, loanAmount, startDate, duration, monthlyDeduction, remark, status, createdBy)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?)"
            );
            mysqli_stmt_bind_param(
                $stmt,
                'idsidsi',
                $employeeId,
                $loanAmount,
                $startDate,
                $duration,
                $monthlyDeduction,
                $remark,
                $userId
            );
        }

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to save loan: ' . $error);
        }

        $savedId = $id > 0 ? $id : mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return $savedId;
    }

    /**
     * Approves the loan and generates its repayment schedule (one row per
     * month starting from startDate's month). Idempotent: if repayment
     * rows already exist for this loan (e.g. a retried request), it won't
     * duplicate them.
     *
     * @throws Exception on validation failure
     */
    public function approve($id, $approvedBy)
    {
        $loan = $this->getById($id);
        if (!$loan) {
            throw new Exception('Loan not found.');
        }
        if ($loan['status'] !== 'Pending') {
            throw new Exception('Only pending loans can be approved.');
        }

        $id = (int)$id;
        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE employeeLoans
            SET status = 'Approved', approvedBy = ?, approvedAt = NOW()
            WHERE id = ?"
        );
        mysqli_stmt_bind_param($stmt, 'ii', $approvedBy, $id);

        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_error($this->con);
            mysqli_stmt_close($stmt);
            throw new Exception('Failed to approve loan: ' . $error);
        }
        mysqli_stmt_close($stmt);

        $this->generateRepaymentSchedule($loan);

        return true;
    }

    private function generateRepaymentSchedule(array $loan)
    {
        $loanId = (int)$loan['id'];

        $existingCheck = mysqli_prepare($this->con, "SELECT id FROM employeeLoanRepayments WHERE loanId = ? LIMIT 1");
        mysqli_stmt_bind_param($existingCheck, 'i', $loanId);
        mysqli_stmt_execute($existingCheck);
        $already = mysqli_fetch_assoc(mysqli_stmt_get_result($existingCheck));
        mysqli_stmt_close($existingCheck);

        if ($already) {
            return;
        }

        $employeeId = (int)$loan['employeeId'];
        $duration = (int)$loan['duration'];
        $monthlyDeduction = (float)$loan['monthlyDeduction'];
        $loanAmount = (float)$loan['loanAmount'];

        // Absorb any rounding remainder (monthlyDeduction * duration vs.
        // loanAmount) into the last installment so the schedule always
        // sums to exactly the loan amount.
        $scheduled = round($monthlyDeduction * $duration, 2);
        $remainder = round($loanAmount - $scheduled, 2);

        $stmt = mysqli_prepare(
            $this->con,
            "INSERT INTO employeeLoanRepayments
            (loanId, employeeId, salaryMonth, deductionAmount, status)
            VALUES (?, ?, ?, ?, 'Pending')"
        );

        $cursor = new DateTime((string)$loan['startDate']);

        for ($i = 0; $i < $duration; $i++) {
            $salaryMonth = $cursor->format('Y-m');
            $amount = $monthlyDeduction;
            if ($i === $duration - 1) {
                $amount = round($monthlyDeduction + $remainder, 2);
            }

            mysqli_stmt_bind_param($stmt, 'iisd', $loanId, $employeeId, $salaryMonth, $amount);
            mysqli_stmt_execute($stmt);

            $cursor->modify('+1 month');
        }

        mysqli_stmt_close($stmt);
    }

    /**
     * @throws Exception on validation failure
     */
    public function cancel($id)
    {
        $loan = $this->getById($id);
        if (!$loan) {
            throw new Exception('Loan not found.');
        }
        if ($loan['status'] === 'Completed') {
            throw new Exception('A completed loan cannot be cancelled.');
        }
        if ($loan['status'] === 'Cancelled') {
            throw new Exception('Loan is already cancelled.');
        }

        $id = (int)$id;

        $stmt = mysqli_prepare($this->con, "UPDATE employeeLoans SET status = 'Cancelled' WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            throw new Exception('Failed to cancel loan.');
        }

        // Waive any repayment installments that haven't been deducted yet
        // -- keeps the row (history), just stops it from ever being swept
        // into a future payroll run.
        mysqli_query(
            $this->con,
            "UPDATE employeeLoanRepayments SET status = 'Waived' WHERE loanId = $id AND status = 'Pending'"
        );

        return true;
    }
}
