<?php

function salarySlipText($value): string
{
    return htmlspecialchars(trim((string)($value ?? '')), ENT_QUOTES, 'UTF-8');
}

function salarySlipValue(array $source, array $keys, string $fallback = '--'): string
{
    foreach ($keys as $key) {
        if (isset($source[$key]) && trim((string)$source[$key]) !== '') {
            return (string)$source[$key];
        }
    }

    return $fallback;
}

function salarySlipAmount($value): string
{
    return 'Rs. ' . number_format((float)$value, 2);
}

function salarySlipNumber($value): string
{
    return number_format((float)$value, 2);
}

function salarySlipDate($value): string
{
    if (empty($value)) {
        return '--';
    }

    $timestamp = strtotime((string)$value);

    return $timestamp === false ? (string)$value : date('d-m-Y', $timestamp);
}

function salarySlipMonthLabel(array $period): string
{
    $timestamp = strtotime((string)($period['start'] ?? ''));

    if ($timestamp === false) {
        return 'Payslip';
    }

    return 'Payslip for the month of ' . date('F, Y', $timestamp);
}

function salarySlipAmountInWords($amount): string
{
    $amount = (int)round((float)$amount);

    if ($amount === 0) {
        return 'Rupees Zero Only';
    }

    $words = [
        0 => '',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen',
        20 => 'Twenty',
        30 => 'Thirty',
        40 => 'Forty',
        50 => 'Fifty',
        60 => 'Sixty',
        70 => 'Seventy',
        80 => 'Eighty',
        90 => 'Ninety',
    ];

    $twoDigitWords = static function (int $number) use ($words): string {
        if ($number < 21) {
            return $words[$number];
        }

        return trim($words[(int)(floor($number / 10) * 10)] . ' ' . $words[$number % 10]);
    };

    $threeDigitWords = static function (int $number) use ($twoDigitWords, $words): string {
        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;
        $result = $hundreds > 0 ? $words[$hundreds] . ' Hundred' : '';

        if ($remainder > 0) {
            $result .= ($result !== '' ? ' ' : '') . $twoDigitWords($remainder);
        }

        return trim($result);
    };

    $parts = [];
    $crore = intdiv($amount, 10000000);
    $amount %= 10000000;
    $lakh = intdiv($amount, 100000);
    $amount %= 100000;
    $thousand = intdiv($amount, 1000);
    $amount %= 1000;

    if ($crore > 0) {
        $parts[] = $threeDigitWords($crore) . ' Crore';
    }

    if ($lakh > 0) {
        $parts[] = $threeDigitWords($lakh) . ' Lakh';
    }

    if ($thousand > 0) {
        $parts[] = $threeDigitWords($thousand) . ' Thousand';
    }

    if ($amount > 0) {
        $parts[] = $threeDigitWords($amount);
    }

    return 'Rupees ' . implode(' ', $parts) . ' Only';
}

function salarySlipRowsHtml(array $rows, bool $includeMaster = false): string
{
    $html = '';

    foreach ($rows as $row) {
        $label = salarySlipText($row['label'] ?? '');
        $amount = salarySlipAmount($row['amount'] ?? $row['value'] ?? 0);
        $html .= '<tr><td>' . $label . '</td>';

        if ($includeMaster) {
            $html .= '<td class="amount">' . salarySlipAmount($row['master'] ?? 0) . '</td>';
        }

        $html .= '<td class="amount">' . $amount . '</td></tr>';
    }

    return $html;
}

function salarySlipDeductionRows(array $data): array
{
    $deductions = $data['deductions'] ?? [];
    $points = $data['points'] ?? [];
    $rows = is_array($deductions['rows'] ?? null) ? $deductions['rows'] : [];

    $rows[] = [
        'label' => 'Leave Deduction',
        'amount' => $deductions['leaveDeduction'] ?? 0,
    ];
    $rows[] = [
        'label' => 'Half Day Deduction',
        'amount' => $deductions['halfDayDeduction'] ?? 0,
    ];
    $rows[] = [
        'label' => 'Manual Deduction',
        'amount' => $deductions['manualDeduction'] ?? 0,
    ];
    $rows[] = [
        'label' => 'Fixed Employee Deduction',
        'amount' => $deductions['fixedEmployeeDeduction'] ?? 0,
    ];
    // Unlike the other rows above (always shown, even at Rs. 0 -- existing
    // PDF behavior, unchanged here), Advance/Loan rows only appear when
    // this employee actually has an applicable amount for this period --
    // most employees have neither, and a permanent "Advance Payment: Rs.
    // 0.00" line would be noise on every slip.
    if ((float)($deductions['advanceDeduction'] ?? 0) > 0) {
        $rows[] = [
            'label' => 'Advance Payment Deduction',
            'amount' => $deductions['advanceDeduction'],
        ];
    }
    if ((float)($deductions['loanDeduction'] ?? 0) > 0) {
        $rows[] = [
            'label' => 'Loan Payment Deduction',
            'amount' => $deductions['loanDeduction'],
        ];
    }
    $rows[] = [
        'label' => 'Point Deduction (' . salarySlipNumber($points['impactPoints'] ?? 0) . ' pts)',
        'amount' => $deductions['pointDeduction'] ?? 0,
    ];

    return $rows;
}

function renderSalarySlipHtml(array $data, array $company = []): string
{
    $employee = $data['employee'] ?? [];
    $period = $data['period'] ?? [];
    $earnings = $data['earnings'] ?? [];
    $deductions = $data['deductions'] ?? [];
    $reimbursements = $data['reimbursements'] ?? [];
    $netFormula = $data['netFormula'] ?? [];

    $companyName = salarySlipValue(
        $company,
        ['companyName', 'company_name', 'organization_name', 'organizationName'],
        'Company Name'
    );
    $companyAddress = salarySlipValue($company, ['companyAddress', 'company_address', 'address'], '');
    $companyLogo = salarySlipValue($company, ['companyLogoDataUri', 'companyLogoUrl', 'company_logo', 'companyLogo', 'logo'], '');
    // Salary slips show HR's contact address here, not the general company
    // email (companySettings.email) used elsewhere -- display-only change,
    // companySettings.email itself is untouched.
    $companyMeta = array_values(array_filter([
        salarySlipValue($company, ['gstNumber'], '') !== '' ? 'GST: ' . salarySlipValue($company, ['gstNumber'], '') : '',
        salarySlipValue($company, ['panNumber'], '') !== '' ? 'PAN: ' . salarySlipValue($company, ['panNumber'], '') : '',
        salarySlipValue($company, ['cinNumber'], '') !== '' ? 'CIN: ' . salarySlipValue($company, ['cinNumber'], '') : '',
        salarySlipValue($company, ['phone'], '') !== '' ? 'Phone: ' . salarySlipValue($company, ['phone'], '') : '',
        'HR ID: hr@mqlus.in',
    ]));

    $leftSummary = [
        'Employee Name' => salarySlipValue($employee, ['fullName']),
        'Designation' => salarySlipValue($employee, ['designationName']),
        'Employee ID' => salarySlipValue($employee, ['employeeCode', 'id']),
        'Date of Joining' => salarySlipDate($employee['joiningDate'] ?? ''),
        'Department' => salarySlipValue($employee, ['departmentName']),
        'Location' => salarySlipValue($employee, ['cityName', 'localAddress']),
    ];
    $rightSummary = [
        'PAN' => salarySlipValue($employee, ['panNumber']),
        'Bank Name' => salarySlipValue($employee, ['bankName']),
        'Bank A/C No.' => salarySlipValue($employee, ['accountNumber']),
        'P.F. A/C Number' => salarySlipValue($employee, ['pfAccountNumber'], ''),
        'UAN Number' => salarySlipValue($employee, ['uanNumber'], ''),
        'Days Worked' => salarySlipNumber($period['paidDaysAfterLeave'] ?? $period['effectivePayableDays'] ?? 0),
        'Pay Date' => date('d-m-Y'),
    ];

    $summaryHtml = '';
    $summaryKeys = array_keys($leftSummary);
    $rightKeys = array_keys($rightSummary);
    $maxRows = max(count($summaryKeys), count($rightKeys));

    for ($i = 0; $i < $maxRows; $i++) {
        $leftKey = $summaryKeys[$i] ?? '';
        $rightKey = $rightKeys[$i] ?? '';
        $summaryHtml .= '<tr>'
            . '<th>' . salarySlipText($leftKey) . '</th>'
            . '<td>' . salarySlipText($leftSummary[$leftKey] ?? '') . '</td>'
            . '<th>' . salarySlipText($rightKey) . '</th>'
            . '<td>' . salarySlipText($rightSummary[$rightKey] ?? '') . '</td>'
            . '</tr>';
    }

    $earningsRows = is_array($earnings['rows'] ?? null) ? $earnings['rows'] : [];
    // Only the editable "Other Earning"/"Other Allowances" rows (see
    // PayrollEngine::getEarningsRows()) are hidden at zero -- every other
    // earnings row (Basic, HRA, Special Allowance, ...) keeps its existing
    // always-shown behavior, unchanged.
    $hideIfZeroLabels = ['Other Earning 1', 'Other Earning 2', 'Other Earning 3', 'Other Allowances'];
    $earningsRows = array_values(array_filter(
        $earningsRows,
        static fn($row) => !in_array($row['label'] ?? '', $hideIfZeroLabels, true) || (float)($row['amount'] ?? 0) > 0
    ));
    $deductionRows = salarySlipDeductionRows($data);
    $reimbursementRows = is_array($reimbursements['rows'] ?? null) ? $reimbursements['rows'] : [];
    $logoHtml = $companyLogo !== ''
        ? '<div class="logo-frame"><img src="' . salarySlipText($companyLogo) . '" alt="Company Logo" class="logo"></div>'
        : '<div class="logo-frame"><span>[COMPANY LOGO]</span></div>';

    return '<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color: #000; font-size: 10px; }
        .sheet { border: 2px solid #111; width: 100%; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #444; padding: 3px 5px; line-height: 1.25; }
        th { font-weight: bold; text-align: left; }
        .header td { border-bottom: 1px solid #111; height: 74px; }
        .company-name { font-size: 16px; font-weight: bold; }
        .company-address { font-size: 10px; margin-top: 8px; }
        .company-meta { font-size: 9px; margin-top: 5px; }
        .logo-cell { width: 28%; text-align: center; vertical-align: middle; font-weight: bold; padding: 0; }
        .logo-frame { width: 100%; height: 74px; line-height: 74px; text-align: center; overflow: hidden; }
        .logo-frame span { display: inline-block; vertical-align: middle; line-height: normal; }
        .logo { display: inline-block; max-height: 62px; max-width: 190px; width: auto; height: auto; vertical-align: middle; }
        .title { text-align: center; font-weight: bold; }
        .section { font-weight: bold; background: #f2f2f2; }
        .amount { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .net-row th, .net-row td { font-weight: bold; }
        .tiny { font-size: 9px; }
        .no-border { border: 0; }
    </style>
</head>
<body>
<div class="sheet">
    <table class="header">
        <tr>
            <td>
                <div class="company-name">' . salarySlipText($companyName) . '</div>
                <div class="company-address">' . salarySlipText($companyAddress) . '</div>
                <div class="company-meta">' . salarySlipText(implode(' | ', $companyMeta)) . '</div>
            </td>
            <td class="logo-cell">' . $logoHtml . '</td>
        </tr>
    </table>

    <table>
        <tr><td class="title" colspan="4">' . salarySlipText(salarySlipMonthLabel($period)) . '</td></tr>
        <tr><td class="section" colspan="4">Employee Pay Summary</td></tr>
        ' . $summaryHtml . '
    </table>

    <table>
        <tr>
            <th>EARNINGS</th>
            <th class="center">Master</th>
            <th class="center">Earnings</th>
            <th>Deductions</th>
            <th class="center">Amount</th>
        </tr>
        <tr>
            <td colspan="3" class="no-border" style="padding:0;border-left:1px solid #444;">
                <table>' . salarySlipRowsHtml($earningsRows, true) . '</table>
            </td>
            <td colspan="2" class="no-border" style="padding:0;border-right:1px solid #444;">
                <table>' . salarySlipRowsHtml($deductionRows) . '</table>
            </td>
        </tr>
        <tr class="net-row">
            <th>Gross Earnings</th>
            <td></td>
            <td class="amount">' . salarySlipAmount($earnings['grossEarnings'] ?? 0) . '</td>
            <th>Total Deductions</th>
            <td class="amount">' . salarySlipAmount($deductions['totalDeductions'] ?? 0) . '</td>
        </tr>
    </table>

    <table>
        <tr><td class="section" colspan="3">REIMBURSEMENTS</td></tr>
        <tr><th>Particulars</th><th class="center">Master</th><th class="center">Earnings</th></tr>
        ' . salarySlipRowsHtml($reimbursementRows, true) . '
        <tr class="net-row"><th>Total Reimbursements</th><td></td><td class="amount">' . salarySlipAmount($reimbursements['totalReimbursements'] ?? 0) . '</td></tr>
    </table>

    <table>
        <tr><th>NETPAY</th><th class="center">AMOUNT</th></tr>
        <tr><td>Gross Earnings</td><td class="amount">' . salarySlipAmount($netFormula['grossEarnings'] ?? $earnings['grossEarnings'] ?? 0) . '</td></tr>
        <tr><td>Total Deductions</td><td class="amount">' . salarySlipAmount($netFormula['totalDeductions'] ?? $deductions['totalDeductions'] ?? 0) . '</td></tr>
        <tr><td>Total Reimbursements</td><td class="amount">' . salarySlipAmount($netFormula['totalReimbursements'] ?? $reimbursements['totalReimbursements'] ?? 0) . '</td></tr>
        <tr class="net-row"><th>Total Net Payable</th><td class="amount">' . salarySlipAmount($data['netPay'] ?? 0) . '</td></tr>
        <tr class="net-row"><th colspan="2" class="center">Total Net Payable ' . salarySlipAmount($data['netPay'] ?? 0) . ' (' . salarySlipText(salarySlipAmountInWords($data['netPay'] ?? 0)) . ')</th></tr>
        <tr><td colspan="2" class="center tiny">**Total Net Payable = Gross Earnings - Total Deductions + Total Reimbursements</td></tr>
        <tr><td colspan="2" class="center tiny">L.O.P. Days: ' . salarySlipNumber(max(0, (float)($period['effectivePayableDays'] ?? 0) - (float)($period['paidDaysAfterLeave'] ?? 0))) . '</td></tr>
    </table>
</div>
</body>
</html>';
}
