<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/config.php';

$leadId =
    (int)($_GET['leadId'] ?? 0);

if ($leadId <= 0) {

    echo json_encode([
        'success' => false,
        'message' => 'Invalid Lead.',
        'data' => []
    ]);

    exit;
}

$stmt = mysqli_prepare(
    $con,
    "
    SELECT

        ld.id,

        ld.fileName,

        ld.originalFileName,

        ld.createdAt,

        eu.fullName AS employeeName

    FROM leadDocuments ld

    LEFT JOIN employeeusers eu
        ON eu.id = ld.uploadedByCandidateId

    WHERE ld.leadId = ?

    ORDER BY ld.id DESC
    "
);

if (!$stmt) {

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load documents.',
        'data' => []
    ]);

    exit;
}

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $leadId
);

mysqli_stmt_execute(
    $stmt
);

$result =
    mysqli_stmt_get_result(
        $stmt
    );

$data = [];

while (
    $row = mysqli_fetch_assoc(
        $result
    )
) {

    $fileUrl =
        UPLOAD_URL .
        '/leads/' .
        $leadId .
        '/' .
        $row['fileName'];

    $data[] = [

        'id' =>
            (int)$row['id'],

        'fileName' =>
            $row['originalFileName'],

        'employeeName' =>
            $row['employeeName'] ?? '-',

        'uploadedAt' =>
            date(
                'd M Y h:i A',
                strtotime(
                    $row['createdAt']
                )
            ),

        'viewUrl' =>
            $fileUrl,

        'downloadUrl' =>
            $fileUrl
    ];
}

mysqli_stmt_close(
    $stmt
);

echo json_encode([
    'success' => true,
    'data' => $data
]);