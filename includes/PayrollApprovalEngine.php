<?php

require_once __DIR__ . '/PayrollEngine.php';
require_once __DIR__ . '/CompanySettings.php';
require_once __DIR__ . '/SalarySlipRenderer.php';
require_once __DIR__ . '/mailer.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

class PayrollApprovalEngine
{
    private mysqli $con;
    private PayrollEngine $payrollEngine;

    public function __construct(mysqli $con)
    {
        $this->con = $con;
        $this->payrollEngine = new PayrollEngine($con);
    }

    public function ensureTables(): void
    {
        mysqli_query(
            $this->con,
            "CREATE TABLE IF NOT EXISTS payrollSalarySlips (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                employeeId INT UNSIGNED NOT NULL,
                periodStart DATE NOT NULL,
                periodEnd DATE NOT NULL,
                periodMonth VARCHAR(20) NOT NULL DEFAULT '',
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                calculationJson LONGTEXT NOT NULL,
                grossEarnings DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                totalDeductions DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                totalReimbursements DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                netPay DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                pdfPath VARCHAR(255) NOT NULL DEFAULT '',
                rejectionRemark TEXT NULL,
                version INT UNSIGNED NOT NULL DEFAULT 1,
                submittedBy INT UNSIGNED NOT NULL DEFAULT 0,
                submittedAt DATETIME NULL,
                reviewedBy INT UNSIGNED NOT NULL DEFAULT 0,
                reviewedAt DATETIME NULL,
                isActive TINYINT(1) NOT NULL DEFAULT 1,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updatedAt TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_payroll_slip_employee_period (employeeId, periodStart, periodEnd),
                INDEX idx_payroll_slip_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        mysqli_query(
            $this->con,
            "CREATE TABLE IF NOT EXISTS payrollSalarySlipPayments (
                id INT UNSIGNED NOT NULL AUTO_INCREMENT,
                salarySlipId INT UNSIGNED NOT NULL,
                paymentAmount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                paymentMode VARCHAR(40) NOT NULL DEFAULT '',
                transactionReference VARCHAR(120) NOT NULL DEFAULT '',
                transactionDate DATE NOT NULL,
                remarks TEXT NULL,
                createdBy INT UNSIGNED NOT NULL DEFAULT 0,
                createdAt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_payroll_payment_slip (salarySlipId)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }
    
    public function sendSalarySlipPreviewEmail(int $employeeId, string $periodStart, string $periodEnd): array
    {
        // Get employee details
        $employee = $this->getEmployeeDetails($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }
    
        $email = trim($employee['emailAddress'] ?? '');
        if ($email === '') {
            return ['success' => false, 'message' => 'Employee email address is not available.'];
        }
    
        // Get or create salary slip calculation
        $calculation = $this->payrollEngine->calculateSalarySlip($employeeId, $periodStart, $periodEnd);
        if (empty($calculation['success'])) {
            return [
                'success' => false,
                'message' => $calculation['message'] ?? 'Unable to calculate salary slip.',
            ];
        }
    
        $data = (array)$calculation['data'];
        $employeeName = trim($employee['fullName'] ?? 'Employee');
    
        // Use the central mailer function
        $mailSent = sendSalarySlipPreviewEmail(
            $employeeId,
            $email,
            $employeeName,
            $periodStart,
            $periodEnd,
            $data
        );
    
        return [
            'success' => $mailSent,
            'message' => $mailSent 
                ? 'Salary slip preview sent successfully to ' . htmlspecialchars($employeeName) . '.'
                : 'Unable to send email. Please check mail configuration.',
            'employeeName' => $employeeName,
            'email' => $email,
        ];
    }
    
    /**
 * Get employee details by ID
 * 
 * @param int $employeeId
 * @return array|null
 */
private function getEmployeeDetails(int $employeeId): ?array
{
    $stmt = mysqli_prepare(
        $this->con,
        "SELECT id, fullName, employeeCode, emailAddress, departmentName, designationName 
         FROM employeeusers 
         WHERE id = ? 
         AND employmentStatus = 'Active'
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);

    return $row ?: null;
}

    public function submitForApproval(int $employeeId, string $periodStart, string $periodEnd, int $submittedBy): array
    {
        $this->ensureTables();

        $existing = $this->getExistingSlip($employeeId, $periodStart, $periodEnd);

        if ($existing && in_array((string)$existing['status'], ['pending', 'approved'], true)) {
            return [
                'success' => false,
                'message' => (string)$existing['status'] === 'approved'
                    ? 'Salary slip is already approved for this employee and period.'
                    : 'Salary slip is already submitted for approval for this employee and period.',
            ];
        }

        $calculation = $this->payrollEngine->calculateSalarySlip($employeeId, $periodStart, $periodEnd);

        if (empty($calculation['success'])) {
            return [
                'success' => false,
                'message' => (string)($calculation['message'] ?? 'Unable to calculate salary slip.'),
            ];
        }

        $data = (array)$calculation['data'];
        $periodMonth = date('F', strtotime($periodStart));
        $calculationJson = json_encode($data, JSON_UNESCAPED_SLASHES);

        if ($calculationJson === false) {
            return [
                'success' => false,
                'message' => 'Unable to prepare salary slip snapshot.',
            ];
        }

        $gross = (float)($data['earnings']['grossEarnings'] ?? 0);
        $deductions = (float)($data['deductions']['totalDeductions'] ?? 0);
        $reimbursements = (float)($data['reimbursements']['totalReimbursements'] ?? 0);
        $netPay = (float)($data['netPay'] ?? 0);

        if ($existing && (string)$existing['status'] === 'rejected') {
            $stmt = mysqli_prepare(
                $this->con,
                "UPDATE payrollSalarySlips
                 SET status = 'pending',
                     calculationJson = ?,
                     grossEarnings = ?,
                     totalDeductions = ?,
                     totalReimbursements = ?,
                     netPay = ?,
                     pdfPath = '',
                     rejectionRemark = NULL,
                     version = version + 1,
                     submittedBy = ?,
                     submittedAt = NOW(),
                     reviewedBy = 0,
                     reviewedAt = NULL
                 WHERE id = ?"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'sddddii',
                $calculationJson,
                $gross,
                $deductions,
                $reimbursements,
                $netPay,
                $submittedBy,
                $existing['id']
            );
        } else {
            $stmt = mysqli_prepare(
                $this->con,
                "INSERT INTO payrollSalarySlips (
                    employeeId,
                    periodStart,
                    periodEnd,
                    periodMonth,
                    status,
                    calculationJson,
                    grossEarnings,
                    totalDeductions,
                    totalReimbursements,
                    netPay,
                    submittedBy,
                    submittedAt
                ) VALUES (?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, NOW())"
            );

            mysqli_stmt_bind_param(
                $stmt,
                'issssddddi',
                $employeeId,
                $periodStart,
                $periodEnd,
                $periodMonth,
                $calculationJson,
                $gross,
                $deductions,
                $reimbursements,
                $netPay,
                $submittedBy
            );
        }

        $saved = mysqli_stmt_execute($stmt);
        $slipId = $existing && (string)$existing['status'] === 'rejected'
            ? (int)$existing['id']
            : (int)mysqli_insert_id($this->con);
        mysqli_stmt_close($stmt);

        return [
            'success' => $saved,
            'message' => $saved
                ? 'Salary slip submitted for Super Admin approval.'
                : 'Unable to submit salary slip for approval.',
            'salarySlipId' => $slipId,
        ];
    }

    public function getExistingSlip(int $employeeId, string $periodStart, string $periodEnd): ?array
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM payrollSalarySlips
             WHERE employeeId = ?
             AND periodStart = ?
             AND periodEnd = ?
             AND isActive = 1
             ORDER BY id DESC
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'iss', $employeeId, $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    /**
     * Recalculates a still-pending slip from current live leave/attendance
     * data and updates its stored snapshot in place. A leave application
     * can legitimately be approved (or rejected/edited) after a slip was
     * submitted but before a reviewer acts on it -- without this, approval
     * would silently lock in whatever was true at submission time, even
     * though calculateSalarySlip() itself has always correctly reflected
     * the current data. No-op for a slip that is no longer 'pending':
     * approved/rejected slips are a historical record and are never
     * recalculated. Does not touch calculateSalarySlip()/PayrollEngine.php
     * or any payroll formula -- it only re-runs the existing calculation
     * at a later point in time and re-persists the result.
     */
    public function refreshPendingCalculation(int $salarySlipId): array
    {
        $this->ensureTables();
        $slip = $this->getSlip($salarySlipId);

        if (!$slip || (string)$slip['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Salary slip not found or no longer pending.'];
        }

        $calculation = $this->payrollEngine->calculateSalarySlip(
            (int)$slip['employeeId'],
            (string)$slip['periodStart'],
            (string)$slip['periodEnd']
        );

        if (empty($calculation['success'])) {
            return [
                'success' => false,
                'message' => (string)($calculation['message'] ?? 'Unable to recalculate salary slip.'),
            ];
        }

        $data = (array)$calculation['data'];
        $calculationJson = json_encode($data, JSON_UNESCAPED_SLASHES);

        if ($calculationJson === false) {
            return ['success' => false, 'message' => 'Unable to prepare salary slip snapshot.'];
        }

        $gross = (float)($data['earnings']['grossEarnings'] ?? 0);
        $deductions = (float)($data['deductions']['totalDeductions'] ?? 0);
        $reimbursements = (float)($data['reimbursements']['totalReimbursements'] ?? 0);
        $netPay = (float)($data['netPay'] ?? 0);

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE payrollSalarySlips
             SET calculationJson = ?,
                 grossEarnings = ?,
                 totalDeductions = ?,
                 totalReimbursements = ?,
                 netPay = ?
             WHERE id = ?
             AND status = 'pending'"
        );
        mysqli_stmt_bind_param($stmt, 'sddddi', $calculationJson, $gross, $deductions, $reimbursements, $netPay, $salarySlipId);
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return [
            'success' => (bool)$saved,
            'message' => $saved ? 'Salary slip recalculated.' : 'Unable to refresh salary slip.',
        ];
    }

    public function listSlips(string $status = '', string $month = ''): array
    {
        $this->ensureTables();

        $where = ['ps.isActive = 1'];
        $types = '';
        $params = [];

        if ($status !== '') {
            $where[] = 'ps.status = ?';
            $types .= 's';
            $params[] = $status;
        }

        if ($month !== '') {
            $where[] = "DATE_FORMAT(ps.periodStart, '%Y-%m') = ?";
            $types .= 's';
            $params[] = $month;
        }

        $sql = "
            SELECT
                ps.*,
                eu.fullName,
                eu.employeeCode,
                eu.departmentName,
                eu.designationName,
                eu.emailAddress,
                COALESCE(payments.paidAmount, 0) AS paidAmount
            FROM payrollSalarySlips ps
            INNER JOIN employeeusers eu ON eu.id = ps.employeeId
            LEFT JOIN (
                SELECT salarySlipId, SUM(paymentAmount) AS paidAmount
                FROM payrollSalarySlipPayments
                GROUP BY salarySlipId
            ) payments ON payments.salarySlipId = ps.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY ps.submittedAt DESC, ps.id DESC
        ";

        $stmt = mysqli_prepare($this->con, $sql);

        if ($types !== '') {
            $this->bindParams($stmt, $types, $params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $row['balanceAmount'] = max(0, (float)$row['netPay'] - (float)$row['paidAmount']);
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function listEmployeeMonthStatus(string $periodStart, string $periodEnd): array
    {
        $this->ensureTables();

        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                eu.id AS employeeId,
                eu.fullName,
                eu.employeeCode,
                eu.departmentName,
                eu.designationName,
                ps.id AS salarySlipId,
                ps.status,
                ps.netPay,
                ps.pdfPath,
                ps.rejectionRemark,
                ps.submittedAt,
                ps.reviewedAt
             FROM employeeusers eu
             LEFT JOIN payrollSalarySlips ps
                ON ps.employeeId = eu.id
                AND ps.periodStart = ?
                AND ps.periodEnd = ?
                AND ps.isActive = 1
             WHERE eu.employmentStatus = 'Active'
             ORDER BY eu.fullName ASC"
        );

        mysqli_stmt_bind_param($stmt, 'ss', $periodStart, $periodEnd);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $row['status'] = $row['status'] ?: 'not_created';
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $rows;
    }

    public function approveSlip(int $salarySlipId, int $reviewedBy, bool $sendEmail = true): array
    {
        $this->ensureTables();
        $slip = $this->getSlip($salarySlipId);

        if (!$slip) {
            return ['success' => false, 'message' => 'Salary slip not found.'];
        }

        if ((string)$slip['status'] === 'approved') {
            return ['success' => false, 'message' => 'Salary slip is already approved.'];
        }

        if ((string)$slip['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending salary slips can be approved.'];
        }

        $data = json_decode((string)$slip['calculationJson'], true);

        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Salary slip snapshot is invalid.'];
        }

        $pdfPath = $this->saveSalarySlipPdf($slip, $data);

        if ($pdfPath === '') {
            return ['success' => false, 'message' => 'Unable to save approved salary slip PDF.'];
        }

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE payrollSalarySlips
             SET status = 'approved',
                 pdfPath = ?,
                 rejectionRemark = NULL,
                 reviewedBy = ?,
                 reviewedAt = NOW()
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, 'sii', $pdfPath, $reviewedBy, $salarySlipId);
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $mailSent = $saved && $sendEmail
            ? $this->sendApprovedSalarySlipEmail($salarySlipId, $slip, $pdfPath)
            : false;

        return [
            'success' => $saved,
            'message' => $saved
                ? ($mailSent ? 'Salary slip approved and emailed to employee.' : 'Salary slip approved, but email could not be sent.')
                : 'Unable to approve salary slip.',
            'pdfPath' => $pdfPath,
            'mailSent' => $mailSent,
        ];
    }

    public function sendApprovedSlipEmail(int $salarySlipId): bool
    {
        $slip = $this->getSlip($salarySlipId);

        if (!$slip || (string)$slip['status'] !== 'approved' || trim((string)$slip['pdfPath']) === '') {
            return false;
        }

        return $this->sendApprovedSalarySlipEmail($salarySlipId, $slip, (string)$slip['pdfPath']);
    }

    /**
     * "Send Salary Slip" icon (Action column) -- a minimal email carrying
     * only the recorded payment information as text, with the existing
     * approved PDF attached. Never recalculates; uses the same
     * payrollSalarySlipPayments rows the Payment column already reads.
     */
    public function sendPaymentInfoEmail(int $salarySlipId): array
    {
        $overview = $this->getSlipOverview($salarySlipId);

        if (empty($overview['success'])) {
            return $overview;
        }

        $slip = $overview['slip'];

        if ($slip['status'] !== 'approved' || trim((string)$slip['pdfPath']) === '') {
            return ['success' => false, 'message' => 'Salary slip must be approved with a generated PDF before sending.'];
        }

        $email = trim((string)$slip['emailAddress']);

        if ($email === '') {
            return ['success' => false, 'message' => 'Employee email address is not available.'];
        }

        $sent = sendSalarySlipPaymentInfoEmail(
            $salarySlipId,
            $email,
            (string)$slip['fullName'],
            $slip['periodStart'],
            $slip['periodEnd'],
            $overview['payments'],
            dirname(__DIR__) . '/' . ltrim((string)$slip['pdfPath'], '/')
        );

        return [
            'success' => $sent,
            'message' => $sent ? 'Salary slip emailed successfully.' : 'Unable to send email. Please check mail configuration.',
        ];
    }

    /**
     * Overview modal's "Send Mail" button -- the fuller email, built from
     * the exact same snapshot + traced descriptions + payments the modal
     * itself displays (see getSlipOverview()), so the email can never show
     * different numbers than the screen it was sent from.
     */
    public function sendOverviewEmail(int $salarySlipId): array
    {
        $overview = $this->getSlipOverview($salarySlipId);

        if (empty($overview['success'])) {
            return $overview;
        }

        $slip = $overview['slip'];
        $email = trim((string)$slip['emailAddress']);

        if ($email === '') {
            return ['success' => false, 'message' => 'Employee email address is not available.'];
        }

        $pdfFullPath = trim((string)$slip['pdfPath']) !== ''
            ? dirname(__DIR__) . '/' . ltrim((string)$slip['pdfPath'], '/')
            : '';

        $sent = sendSalarySlipOverviewEmail(
            $salarySlipId,
            $email,
            (string)$slip['fullName'],
            $slip['periodStart'],
            $slip['periodEnd'],
            $overview['calculation'],
            $overview['descriptions'],
            $overview['payments'],
            $pdfFullPath
        );

        return [
            'success' => $sent,
            'message' => $sent ? 'Salary slip overview emailed successfully.' : 'Unable to send email. Please check mail configuration.',
        ];
    }

    public function rejectSlip(int $salarySlipId, int $reviewedBy, string $remark): array
    {
        $this->ensureTables();
        $remark = trim($remark);

        if ($remark === '') {
            return ['success' => false, 'message' => 'Rejection remark is required.'];
        }

        $slip = $this->getSlip($salarySlipId);

        if (!$slip) {
            return ['success' => false, 'message' => 'Salary slip not found.'];
        }

        if ((string)$slip['status'] !== 'pending') {
            return ['success' => false, 'message' => 'Only pending salary slips can be rejected.'];
        }

        $stmt = mysqli_prepare(
            $this->con,
            "UPDATE payrollSalarySlips
             SET status = 'rejected',
                 rejectionRemark = ?,
                 reviewedBy = ?,
                 reviewedAt = NOW()
             WHERE id = ?"
        );

        mysqli_stmt_bind_param($stmt, 'sii', $remark, $reviewedBy, $salarySlipId);
        $saved = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return [
            'success' => $saved,
            'message' => $saved ? 'Salary slip rejected with remark.' : 'Unable to reject salary slip.',
        ];
    }

    public function addPayment(array $payload, int $createdBy): array
    {
        $this->ensureTables();
    
        $salarySlipId = (int)($payload['salarySlipId'] ?? 0);
        $amount = (float)($payload['paymentAmount'] ?? 0);
        $mode = trim((string)($payload['paymentMode'] ?? ''));
        $reference = trim((string)($payload['transactionReference'] ?? ''));
        $date = trim((string)($payload['transactionDate'] ?? ''));
        $remarks = trim((string)($payload['remarks'] ?? ''));
    
        if (
            $salarySlipId <= 0 ||
            $amount <= 0 ||
            $mode === '' ||
            $reference === '' ||
            $date === ''
        ) {
            return [
                'success' => false,
                'message' => 'Payment amount, mode, reference, and date are required.'
            ];
        }
    
        if (strtotime($date) === false) {
            return [
                'success' => false,
                'message' => 'Invalid payment date.'
            ];
        }
    
        $slip = $this->getSlip($salarySlipId);
    
        if (!$slip || (string)$slip['status'] !== 'approved') {
            return [
                'success' => false,
                'message' => 'Payment can be recorded only for approved salary slips.'
            ];
        }
    
        $paidAmount = $this->getPaidAmount($salarySlipId);
    
        $balanceAmount = max(
            0,
            (float)$slip['netPay'] - $paidAmount
        );
    
        if ($amount > $balanceAmount) {
            return [
                'success' => false,
                'message' => 'Payment amount cannot be greater than balance amount.'
            ];
        }
    
        if ($this->hasDuplicatePaymentReference($salarySlipId, $reference)) {
            return [
                'success' => false,
                'message' => 'This payment transaction reference is already recorded for this salary slip.'
            ];
        }
    
        $stmt = mysqli_prepare(
            $this->con,
            "INSERT INTO payrollSalarySlipPayments (
                salarySlipId,
                paymentAmount,
                paymentMode,
                transactionReference,
                transactionDate,
                remarks,
                createdBy
            ) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            'idssssi',
            $salarySlipId,
            $amount,
            $mode,
            $reference,
            $date,
            $remarks,
            $createdBy
        );
    
        $saved = mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        if ($saved) {
    
            // Calculate updated paid amount after this payment
            $totalPaidAmount = $this->getPaidAmount($salarySlipId);
    
            $remainingBalance = max(
                0,
                (float)$slip['netPay'] - $totalPaidAmount
            );
    
            // Mark expenses as paid only after salary is fully paid
            if ($remainingBalance <= 0) {
    
                $calculation = json_decode(
                    (string)$slip['calculationJson'],
                    true
                );
    
                $expenseIds = $calculation['reimbursements']['expenseIds'] ?? [];
    
                if (!empty($expenseIds) && is_array($expenseIds)) {

                    $expenseIds = array_map('intval', $expenseIds);
                    $expenseIds = array_filter($expenseIds);

                    if (!empty($expenseIds)) {

                        $idList = implode(',', $expenseIds);

                        mysqli_query(
                            $this->con,
                            "UPDATE employeeExpenses
                             SET paymentStatus = 'paid'
                             WHERE id IN ($idList)
                             AND paymentStatus = 'unpaid'"
                        );
                    }
                }

                // Mark the specific advances this slip actually deducted
                // as Completed -- same "only once fully paid" finalization
                // point as expenses above, not at calculation/preview time
                // (see PayrollEngine::getApprovedAdvanceDeduction()). Only
                // "Upcoming Salary" advances ever appear in advanceIds --
                // they're swept in one shot, so being deducted at all means
                // fully recovered.
                $advanceIds = $calculation['deductions']['advanceIds'] ?? [];

                if (!empty($advanceIds) && is_array($advanceIds)) {

                    $advanceIds = array_filter(array_map('intval', $advanceIds));

                    if (!empty($advanceIds)) {

                        $idList = implode(',', $advanceIds);

                        mysqli_query(
                            $this->con,
                            "UPDATE employeeAdvancePayments
                             SET status = 'Completed', salaryAdjustmentStatus = 'Adjusted'
                             WHERE id IN ($idList)
                             AND status = 'Approved'"
                        );
                    }
                }

                // Mark the specific Partial Payment advance installments
                // this slip actually deducted, then close out any advance
                // whose whole schedule is now Deducted -- same pattern as
                // the loan-repayment block below, kept separate because
                // partial advances live in their own table
                // (employeeAdvanceRepayments) and only complete once the
                // full balance is recovered, not on the first installment.
                $advancePartialIds = $calculation['deductions']['advancePartialInstallmentIds'] ?? [];

                if (!empty($advancePartialIds) && is_array($advancePartialIds)) {

                    $advancePartialIds = array_filter(array_map('intval', $advancePartialIds));

                    if (!empty($advancePartialIds)) {

                        $idList = implode(',', $advancePartialIds);

                        mysqli_query(
                            $this->con,
                            "UPDATE employeeAdvanceRepayments
                             SET status = 'Deducted', deductedAmount = scheduledAmount, deductedAt = NOW()
                             WHERE id IN ($idList)
                             AND status = 'Pending'"
                        );

                        $advanceIdsResult = mysqli_query(
                            $this->con,
                            "SELECT DISTINCT advanceId FROM employeeAdvanceRepayments WHERE id IN ($idList)"
                        );

                        while ($advanceIdsResult && ($advanceRow = mysqli_fetch_assoc($advanceIdsResult))) {

                            $advanceId = (int)$advanceRow['advanceId'];

                            $remaining = mysqli_query(
                                $this->con,
                                "SELECT COUNT(*) AS c FROM employeeAdvanceRepayments
                                 WHERE advanceId = $advanceId AND status = 'Pending'"
                            );
                            $remainingCount = $remaining ? (int)(mysqli_fetch_assoc($remaining)['c'] ?? 0) : 1;

                            if ($remainingCount === 0) {
                                mysqli_query(
                                    $this->con,
                                    "UPDATE employeeAdvancePayments
                                     SET status = 'Completed', salaryAdjustmentStatus = 'Adjusted'
                                     WHERE id = $advanceId AND status = 'Approved'"
                                );
                            }
                        }
                    }
                }

                // Mark the specific loan installments this slip actually
                // deducted, then close out any loan whose whole schedule
                // is now Deducted.
                $loanRepaymentIds = $calculation['deductions']['loanRepaymentIds'] ?? [];

                if (!empty($loanRepaymentIds) && is_array($loanRepaymentIds)) {

                    $loanRepaymentIds = array_filter(array_map('intval', $loanRepaymentIds));

                    if (!empty($loanRepaymentIds)) {

                        $idList = implode(',', $loanRepaymentIds);

                        mysqli_query(
                            $this->con,
                            "UPDATE employeeLoanRepayments
                             SET status = 'Deducted', deductedAt = NOW()
                             WHERE id IN ($idList)
                             AND status = 'Pending'"
                        );

                        $loanIdsResult = mysqli_query(
                            $this->con,
                            "SELECT DISTINCT loanId FROM employeeLoanRepayments WHERE id IN ($idList)"
                        );

                        while ($loanIdsResult && ($loanRow = mysqli_fetch_assoc($loanIdsResult))) {

                            $loanId = (int)$loanRow['loanId'];

                            $remaining = mysqli_query(
                                $this->con,
                                "SELECT COUNT(*) AS c FROM employeeLoanRepayments
                                 WHERE loanId = $loanId AND status = 'Pending'"
                            );
                            $remainingCount = $remaining ? (int)(mysqli_fetch_assoc($remaining)['c'] ?? 0) : 1;

                            if ($remainingCount === 0) {
                                mysqli_query(
                                    $this->con,
                                    "UPDATE employeeLoans SET status = 'Completed'
                                     WHERE id = $loanId AND status = 'Approved'"
                                );
                            }
                        }
                    }
                }
            }
        }
    
        return [
            'success' => $saved,
            'message' => $saved
                ? 'Payment transaction recorded successfully.'
                : 'Unable to record payment transaction.',
        ];
    }

    public function getSlip(int $salarySlipId): ?array
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT
                ps.*,
                eu.fullName,
                eu.employeeCode,
                eu.emailAddress,
                eu.departmentName,
                eu.designationName
             FROM payrollSalarySlips ps
             INNER JOIN employeeusers eu ON eu.id = ps.employeeId
             WHERE ps.id = ?
             AND ps.isActive = 1
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'i', $salarySlipId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    /**
     * Single source of truth for the approval "Overview" modal and both
     * salary-slip emails -- never recalculates. Reads the slip's saved
     * calculationJson snapshot as-is (historical amounts stay exactly as
     * approved) and only adds two things on top of it, purely for display:
     *
     *   - descriptions: the actual remark/reason from the existing source
     *     record behind a component (employeeDeductions, employeeAdvancePayments,
     *     employeeLoans, employeeCommissionTransactions, overtimeRequests,
     *     employeeExpenses, leaveApplications) -- traced using the exact
     *     same filter each component's own PayrollEngine method used, so a
     *     component with no matching source data simply gets no description
     *     rather than a guessed one.
     *   - payments: the slip's own payrollSalarySlipPayments rows (existing
     *     payment table, no new payment records).
     */
    public function getSlipOverview(int $salarySlipId): array
    {
        $slip = $this->getSlip($salarySlipId);

        if (!$slip) {
            return ['success' => false, 'message' => 'Salary slip not found.'];
        }

        $data = json_decode((string)$slip['calculationJson'], true);

        if (!is_array($data)) {
            return ['success' => false, 'message' => 'Salary slip snapshot is invalid.'];
        }

        $employeeId = (int)$slip['employeeId'];
        $periodStart = (string)$slip['periodStart'];
        $periodEnd = (string)$slip['periodEnd'];

        return [
            'success' => true,
            'slip' => [
                'id' => (int)$slip['id'],
                'employeeId' => $employeeId,
                'fullName' => $slip['fullName'],
                'employeeCode' => $slip['employeeCode'],
                'departmentName' => $slip['departmentName'],
                'designationName' => $slip['designationName'],
                'emailAddress' => $slip['emailAddress'],
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
                'status' => $slip['status'],
                'netPay' => (float)$slip['netPay'],
                'pdfPath' => $slip['pdfPath'],
            ],
            'calculation' => $data,
            'descriptions' => $this->traceSlipDescriptions($employeeId, $periodStart, $periodEnd, $data),
            'payments' => $this->getSlipPayments($salarySlipId),
        ];
    }

    /**
     * @return array<string,string> component key => human-readable
     * description built from the actual source record(s). Missing keys
     * mean no source data exists for that component.
     */
    private function traceSlipDescriptions(int $employeeId, string $periodStart, string $periodEnd, array $data): array
    {
        $descriptions = [];
        $deductions = (array)($data['deductions'] ?? []);
        $earnings = (array)($data['earnings'] ?? []);

        // Manual Deduction -- employeeDeductions, same filter as
        // PayrollEngine::getManualDeductionAmount().
        if ((float)($deductions['manualDeduction'] ?? 0) > 0) {
            $rows = $this->fetchRows(
                "SELECT deductionType, amount, remark
                 FROM employeeDeductions
                 WHERE employeeId = ? AND deductionDate BETWEEN ? AND ?",
                'iss',
                [$employeeId, $periodStart, $periodEnd]
            );
            $parts = [];
            foreach ($rows as $row) {
                $label = trim((string)$row['deductionType']) ?: 'Deduction';
                $remark = trim((string)($row['remark'] ?? ''));
                $parts[] = $label . ' (Rs. ' . number_format((float)$row['amount'], 2) . ')' . ($remark !== '' ? ': ' . $remark : '');
            }
            if ($parts) {
                $descriptions['manualDeduction'] = implode('; ', $parts);
            }
        }

        // Advance Payment Deduction -- both the lump-sum (advanceIds) and
        // Partial Payment (advancePartialInstallmentIds) shapes from
        // PayrollEngine::getApprovedAdvanceDeduction().
        $advanceIds = array_filter(array_map('intval', (array)($deductions['advanceIds'] ?? [])));
        $partialInstallmentIds = array_filter(array_map('intval', (array)($deductions['advancePartialInstallmentIds'] ?? [])));

        if ($advanceIds || $partialInstallmentIds) {
            $advanceRemarks = [];

            if ($advanceIds) {
                foreach ($this->fetchRows(
                    "SELECT remark FROM employeeAdvancePayments WHERE id IN (" . implode(',', $advanceIds) . ")",
                    '',
                    []
                ) as $row) {
                    $remark = trim((string)($row['remark'] ?? ''));
                    if ($remark !== '') {
                        $advanceRemarks[] = $remark;
                    }
                }
            }

            if ($partialInstallmentIds) {
                foreach ($this->fetchRows(
                    "SELECT ap.remark
                     FROM employeeAdvanceRepayments ar
                     INNER JOIN employeeAdvancePayments ap ON ap.id = ar.advanceId
                     WHERE ar.id IN (" . implode(',', $partialInstallmentIds) . ")",
                    '',
                    []
                ) as $row) {
                    $remark = trim((string)($row['remark'] ?? ''));
                    if ($remark !== '') {
                        $advanceRemarks[] = $remark;
                    }
                }
            }

            if ($advanceRemarks) {
                $descriptions['advanceDeduction'] = implode('; ', array_unique($advanceRemarks));
            }
        }

        // Loan Payment Deduction -- via PayrollEngine::getPendingLoanRepayment()'s loanRepaymentIds.
        $loanRepaymentIds = array_filter(array_map('intval', (array)($deductions['loanRepaymentIds'] ?? [])));
        if ($loanRepaymentIds) {
            $loanRemarks = [];
            foreach ($this->fetchRows(
                "SELECT el.remark
                 FROM employeeLoanRepayments elr
                 INNER JOIN employeeLoans el ON el.id = elr.loanId
                 WHERE elr.id IN (" . implode(',', $loanRepaymentIds) . ")",
                '',
                []
            ) as $row) {
                $remark = trim((string)($row['remark'] ?? ''));
                if ($remark !== '') {
                    $loanRemarks[] = $remark;
                }
            }
            if ($loanRemarks) {
                $descriptions['loanDeduction'] = implode('; ', array_unique($loanRemarks));
            }
        }

        // Bonus / Commission -- PayrollEngine renders these as one combined
        // "Commission / Bonus" row (earnings.commissionBonus), sourced from
        // employeeCommissionTransactions (transactionType), same filter as
        // PayrollEngine::getSyncedCommissionBonusAmount().
        if ((float)($earnings['commissionBonus'] ?? 0) > 0) {
            $startMonth = substr($periodStart, 0, 7);
            $endMonth = substr($periodEnd, 0, 7);
            $rows = $this->fetchRows(
                "SELECT transactionType, amount, remarks
                 FROM employeeCommissionTransactions
                 WHERE employeeId = ?
                 AND approvalStatus = 'Approved'
                 AND payrollStatus IN ('Synced', 'Paid')
                 AND isReverted = 0
                 AND effectiveMonth BETWEEN ? AND ?",
                'iss',
                [$employeeId, $startMonth, $endMonth]
            );
            $parts = [];
            foreach ($rows as $row) {
                $remark = trim((string)($row['remarks'] ?? ''));
                $parts[] = (string)$row['transactionType'] . ': Rs. ' . number_format((float)$row['amount'], 2) . ($remark !== '' ? ' - ' . $remark : '');
            }
            if ($parts) {
                $descriptions['commissionBonus'] = implode('; ', $parts);
            }
        }

        // Overtime -- overtimeRequests, same filter as PayrollEngine::getApprovedOvertimeHours().
        if ((float)($earnings['overtimeAmount'] ?? 0) > 0) {
            $rows = $this->fetchRows(
                "SELECT date, calculatedOtHours, reason
                 FROM overtimeRequests
                 WHERE employeeId = ? AND status = 'approved' AND date BETWEEN ? AND ?",
                'iss',
                [$employeeId, $periodStart, $periodEnd]
            );
            $parts = [];
            foreach ($rows as $row) {
                $reason = trim((string)($row['reason'] ?? ''));
                if ($reason !== '') {
                    $parts[] = date('d M', strtotime((string)$row['date'])) . ': ' . $reason;
                }
            }
            if ($parts) {
                $descriptions['overtime'] = implode('; ', $parts);
            }
        }

        // Expense Reimbursement -- via PayrollEngine::getApprovedUnpaidExpenses()'s expenseIds.
        $expenseIds = array_filter(array_map('intval', (array)($data['reimbursements']['expenseIds'] ?? [])));
        if ($expenseIds) {
            $remarks = [];
            foreach ($this->fetchRows(
                "SELECT remark FROM employeeExpenses WHERE id IN (" . implode(',', $expenseIds) . ")",
                '',
                []
            ) as $row) {
                $remark = trim((string)($row['remark'] ?? ''));
                if ($remark !== '') {
                    $remarks[] = $remark;
                }
            }
            if ($remarks) {
                $descriptions['expenseReimbursement'] = implode('; ', $remarks);
            }
        }

        // Leave-related deductions -- approved leave applications overlapping
        // the period, same overlap rule as PayrollEngine::getLeaveSummary().
        if ((float)($deductions['leaveDeduction'] ?? 0) > 0) {
            $rows = $this->fetchRows(
                "SELECT
                    MIN(lad.leaveDate) AS fromDate,
                    MAX(lad.leaveDate) AS toDate,
                    la.reason,
                    lt.name AS leaveTypeName
                 FROM leaveApplications la
                 INNER JOIN leaveApplicationDays lad ON lad.leaveApplicationId = la.id
                 LEFT JOIN leaveTypes lt ON lt.id = la.leaveTypeId
                 WHERE la.employeeId = ? AND la.status <> 'cancelled'
                 AND lad.status = 'approved'
                 AND lad.leaveDate BETWEEN ? AND ?
                 GROUP BY la.id, la.reason, lt.name",
                'iss',
                [$employeeId, $periodStart, $periodEnd]
            );
            $parts = [];
            foreach ($rows as $row) {
                $reason = trim((string)($row['reason'] ?? ''));
                if ($reason === '') {
                    continue;
                }
                $label = trim((string)($row['leaveTypeName'] ?? '')) ?: 'Leave';
                $parts[] = $label . ' (' . date('d M', strtotime((string)$row['fromDate'])) . ' - ' . date('d M', strtotime((string)$row['toDate'])) . '): ' . $reason;
            }
            if ($parts) {
                $descriptions['leaveDeduction'] = implode('; ', $parts);
            }
        }

        return $descriptions;
    }

    private function getSlipPayments(int $salarySlipId): array
    {
        return $this->fetchRows(
            "SELECT id, paymentAmount, paymentMode, transactionReference, transactionDate, remarks, createdAt
             FROM payrollSalarySlipPayments
             WHERE salarySlipId = ?
             ORDER BY id ASC",
            'i',
            [$salarySlipId]
        );
    }

    /**
     * Small prepared-statement SELECT helper shared by the tracing methods
     * above -- $types/$params empty means no placeholders (used for the
     * "WHERE id IN (...)" lookups, where ids are already validated ints
     * interpolated directly rather than bound as a variable-length list).
     */
    private function fetchRows(string $sql, string $types, array $params): array
    {
        $stmt = mysqli_prepare($this->con, $sql);

        if (!$stmt) {
            return [];
        }

        if ($types !== '') {
            $this->bindParams($stmt, $types, $params);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        mysqli_stmt_close($stmt);

        return $rows;
    }

    private function saveSalarySlipPdf(array $slip, array $data): string
    {
        $monthFolder = date('F', strtotime((string)$slip['periodStart']));
        $uploadDir = dirname(__DIR__) . '/uploads/payroll/' . $monthFolder . '/';

        if (
            !is_dir($uploadDir) &&
            !mkdir($uploadDir, 0755, true) &&
            !is_dir($uploadDir)
        ) {
            return '';
        }

        $employeeName = $this->sanitizeFilePart((string)($slip['fullName'] ?? 'Employee'));
        $filename = $employeeName . '_' . $monthFolder . '_Salaryslip.pdf';
        $relativePath = 'uploads/payroll/' . $monthFolder . '/' . $filename;
        $fullPath = $uploadDir . $filename;

        $html = renderSalarySlipHtml($data, getCompanySettings($this->con));
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return file_put_contents($fullPath, $dompdf->output()) === false ? '' : $relativePath;
    }

    private function sendApprovedSalarySlipEmail(int $salarySlipId, array $slip, string $pdfPath): bool
    {
        $email = trim((string)($slip['emailAddress'] ?? ''));

        if ($email === '') {
            return false;
        }

        $employeeName = trim((string)($slip['fullName'] ?? 'Employee'));
        $month = date('F Y', strtotime((string)$slip['periodStart']));
        $fullPdfPath = dirname(__DIR__) . '/' . ltrim($pdfPath, '/');

        return sendLoggedMail(
            'payroll',
            $salarySlipId,
            'salarySlipApproved',
            $email,
            $employeeName,
            'Salary Slip - ' . $month,
            function () use ($email, $employeeName, $month, $fullPdfPath) {
                $mail = createMailer('MQlus Payroll');
                $mail->addAddress($email, $employeeName);
                $mail->Subject = 'Salary Slip - ' . $month;
                $mail->Body = '<p>Dear ' . htmlspecialchars($employeeName, ENT_QUOTES, 'UTF-8') . ',</p>'
                    . '<p>Your salary slip for ' . htmlspecialchars($month, ENT_QUOTES, 'UTF-8') . ' is attached.</p>'
                    . '<p>Regards,<br>Payroll Team</p>';
                $mail->AltBody = 'Your salary slip for ' . $month . ' is attached.';

                if (is_file($fullPdfPath)) {
                    $mail->addAttachment($fullPdfPath, basename($fullPdfPath));
                }

                return $mail->send();
            }
        );
    }

    private function getPaidAmount(int $salarySlipId): float
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT COALESCE(SUM(paymentAmount), 0) AS paidAmount
             FROM payrollSalarySlipPayments
             WHERE salarySlipId = ?"
        );

        mysqli_stmt_bind_param($stmt, 'i', $salarySlipId);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : ['paidAmount' => 0];
        mysqli_stmt_close($stmt);

        return (float)($row['paidAmount'] ?? 0);
    }

    private function hasDuplicatePaymentReference(int $salarySlipId, string $reference): bool
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id
             FROM payrollSalarySlipPayments
             WHERE salarySlipId = ?
             AND transactionReference = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, 'is', $salarySlipId, $reference);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $exists = $result && mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        return (bool)$exists;
    }

    private function sanitizeFilePart(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9]+/', '_', trim($value));
        $value = trim((string)$value, '_');

        return $value === '' ? 'Employee' : $value;
    }

    private function bindParams(mysqli_stmt $stmt, string $types, array $values): bool
    {
        $params = [$types];

        foreach ($values as $key => $value) {
            $params[] = &$values[$key];
        }

        return call_user_func_array([$stmt, 'bind_param'], $params);
    }
}
