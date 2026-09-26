<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

$leadId =
    (int)($_GET['leadId'] ?? 0);

if ($leadId <= 0) {

    echo json_encode([
        'success' => false,
        'data' => []
    ]);

    exit;
}

$stmt = mysqli_prepare(
    $con,
    "
    SELECT

        lr.*,

        u.fullName AS employeeName

    FROM leadRemarks lr

    LEFT JOIN employeeusers u
        ON u.id = lr.createdByCandidateId

    WHERE lr.leadId = ?

    ORDER BY lr.createdAt DESC
    "
);

mysqli_stmt_bind_param(
    $stmt,
    "i",
    $leadId
);

mysqli_stmt_execute($stmt);

$result =
    mysqli_stmt_get_result($stmt);

$data = [];

while (
    $row = mysqli_fetch_assoc($result)
) {

    $data[] = [

        'id' =>
            (int)$row['id'],

        'remark' =>
            $row['remark'],

        'employeeName' =>
            $row['employeeName'] ?? 'Admin',

        'followUpDateTime' =>
            !empty($row['followUpDateTime'])
                ? date(
                    'd M Y h:i A',
                    strtotime(
                        $row['followUpDateTime']
                    )
                )
                : '',

        'createdAt' =>
            date(
                'd M Y h:i A',
                strtotime(
                    $row['createdAt']
                )
            )
    ];
}

mysqli_stmt_close($stmt);

echo json_encode([
    'success' => true,
    'data' => $data
]);