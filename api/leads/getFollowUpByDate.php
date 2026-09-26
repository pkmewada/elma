<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
$response = [
    'success' => false,
    'data' => [],
    'message' => 'Invalid request.'
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

$followUpDate = trim($_POST['followUpDate'] ?? '');

if ($followUpDate === '') {
    $response['message'] = 'Date is required.';
    echo json_encode($response);
    exit;
}

/* Validate date format YYYY-MM-DD */
$dateObj = DateTime::createFromFormat('Y-m-d', $followUpDate);

if (!$dateObj || $dateObj->format('Y-m-d') !== $followUpDate) {
    $response['message'] = 'Invalid date format.';
    echo json_encode($response);
    exit;
}

/*
Expected table:
candidateRemarks

Used columns:
candidateId
remark
followUpType
followUpDateTime
status (optional)
createdAt

Candidate table:
candidateRecord
*/

$sql = "
SELECT
    cr.id,
    cr.fullName,
    cr.phoneNumber,
    cr.email,
    cr.currentLocation,
    cr.appliedRole,
    cr.experienceYears,
    cr.expectedSalary,

    r.followUpType,
    r.followUpDateTime,
    r.remark,
    r.candidateId,

    COALESCE(r.status, 'Pending') AS followUpStatus

FROM candidateRemarks r

INNER JOIN candidateRecord cr
    ON cr.id = r.candidateId

WHERE
    r.followUpDateTime IS NOT NULL
    AND DATE(r.followUpDateTime) = ?

    AND r.followUpType IN ('Follow-up', 'Interview')

ORDER BY r.followUpDateTime ASC
";

$stmt = mysqli_prepare($con, $sql);

if (!$stmt) {
    $response['message'] = 'Unable to prepare query.';
    echo json_encode($response);
    exit;
}

mysqli_stmt_bind_param($stmt, 's', $followUpDate);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$data = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {

        $data[] = [
            'id' => (int) $row['id'],
            'fullName' => (string) $row['fullName'],
            'phoneNumber' => (string) $row['phoneNumber'],
            'email' => (string) $row['email'],
            'currentLocation' => (string) $row['currentLocation'],
            'appliedRole' => (string) $row['appliedRole'],
            'experienceYears' => (string) $row['experienceYears'],
            'expectedSalary' => (string) $row['expectedSalary'],
            'followUpType' => (string) $row['followUpType'],
            'followUpDateTime' => (string) $row['followUpDateTime'],
            'remark' => (string) $row['remark'],
            'followUpStatus' => (string) $row['followUpStatus'],
            'remarkId' => (int) $row['id'],
            'candidateId' => (int)$row['candidateId'],
        ];
    }
}

mysqli_stmt_close($stmt);

$response['success'] = true;
$response['data'] = $data;
$response['message'] = count($data) > 0 ? 'Records found.' : 'No records found.';

echo json_encode($response);