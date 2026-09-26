<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../../includes/db.php';
include __DIR__ . '/../../includes/mailer.php';

header('Content-Type: application/json');

/* Validate Request */
$employeeUserId = (int)($_POST['employeeUserId'] ?? 0);
if ($employeeUserId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid employee.']);
    exit;
}

/* Fetch Employee Details */
$stmt = mysqli_prepare($con, "
    SELECT id, fullName, emailAddress
    FROM employeeusers
    WHERE id = ?
    LIMIT 1
");
mysqli_stmt_bind_param($stmt, "i", $employeeUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$employee = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$employee || empty($employee['emailAddress'])) {
    echo json_encode([
        'success' => false,
        'message' => $employee ? 'Employee email address not found.' : 'Employee not found.'
    ]);
    exit;
}

/* Field Labels */
$fieldLabels = [
    'mobileNumber' => 'Mobile Number',
    'alternativeNumber' => 'Alternative Number',
    'emergencyContactNumber' => 'Emergency Contact Number',
    'dateOfBirth' => 'Date Of Birth',
    'gender' => 'Gender',
    'maritalStatus' => 'Marital Status',
    'permanentAddress' => 'Permanent Address',
    'localAddress' => 'Local Address',
    'cityName' => 'City',
    'stateName' => 'State',
    'pinCode' => 'PIN Code',
    'linkedInProfile' => 'LinkedIn Profile',
    'instagramProfile' => 'Instagram Profile',
    'employeeCode' => 'Employee Code',
    'departmentName' => 'Department',
    'accountHolderName' => 'Account Holder Name',
    'bankName' => 'Bank Name',
    'accountNumber' => 'Account Number',
    'ifscCode' => 'IFSC Code',
    'branchName' => 'Branch Name',
    'aadhaarNumber' => 'Aadhaar Number',
    'panNumber' => 'PAN Number',
    'profilePhoto' => 'Profile Photo',
    'aadhaarFile' => 'Aadhaar Card',
    'panFile' => 'PAN Card',
    'marksheet10File' => 'Previews Company Document',
    'marksheet12File' => '12th Marksheet',
    'graduationFile' => 'Graduation Certificate',
    'bankPassbookFile' => 'Bank Passbook'
];

/* Fetch Rejected Items */
$stmt = mysqli_prepare($con, "
    SELECT fieldName, reviewRemark
    FROM employeeProfileVerification
    WHERE employeeUserId = ?
      AND verifyStatus = 'Rejected'
    ORDER BY fieldName ASC
");
mysqli_stmt_bind_param($stmt, "i", $employeeUserId);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$rejectedItems = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rejectedItems[] = [
        'field' => $fieldLabels[$row['fieldName']] ?? $row['fieldName'],
        'remark' => trim((string)($row['reviewRemark'] ?? ''))
    ];
}
mysqli_stmt_close($stmt);

/* Only send email if there are rejected items */
if (!empty($rejectedItems)) {

    $mailSent = sendProfileCorrectionRequiredEmail(
        (int)$employee['id'],
        (string)$employee['emailAddress'],
        (string)$employee['fullName'],
        $rejectedItems
    );

    if (!$mailSent) {
        echo json_encode([
            'success' => false,
            'message' => 'Unable to send correction email.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Review submitted and correction email sent successfully.'
    ]);
    exit;
}

/* No rejected items — nothing to send */
echo json_encode([
    'success' => true,
    'message' => 'Review submitted successfully. No rejected items found.'
]);
exit;
?>