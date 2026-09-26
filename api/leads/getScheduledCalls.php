<?php

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$date = trim((string)($_GET['date'] ?? ''));


$isAdmin =
    !empty($_SESSION['userId']);


$candidateId =
    (int)($_SESSION['candidateId'] ?? 0);



$sql = "
SELECT

    lr.id,

    lr.remark,

    lr.followUpDateTime,

    l.id AS leadId,

    l.fullName AS leadName,

    l.phone,

    lr.followUpremark AS status,

    eu.fullName AS employeeName

FROM leadRemarks lr

INNER JOIN leads l
    ON l.id = lr.leadId

LEFT JOIN employeeusers eu
    ON eu.id = lr.createdByCandidateId

WHERE 1=1 AND lr.followUpremark = 'open'
";



/*
|--------------------------------------------------------------------------
| DATE FILTER LOGIC
|--------------------------------------------------------------------------
|
| No date selected:
|   - show pending followups till today
|   - only OPEN leads
|
| Date selected:
|   - show that date followups
|
*/


$params = [];
$types  = "";


if ($date === '') {


    $sql .= "
        AND DATE(lr.followUpDateTime) <= CURDATE()
        AND l.status = 'open'
    ";


} else {


    $sql .= "
        AND DATE(lr.followUpDateTime) = ?
    ";


    $params[] = $date;
    $types .= "s";

}



if (!$isAdmin) {


    $sql .= "
        AND lr.createdByCandidateId = ?
    ";


    $params[] = $candidateId;
    $types .= "i";

}



$sql .= "
ORDER BY lr.followUpDateTime ASC
";



$stmt = mysqli_prepare($con, $sql);



if (!$stmt) {


    echo json_encode([
        'success' => false,
        'message' => 'Unable to load follow ups.',
        'data' => []
    ]);


    exit;
}



if (!empty($params)) {


    mysqli_stmt_bind_param(
        $stmt,
        $types,
        ...$params
    );

}



mysqli_stmt_execute($stmt);


$result =
    mysqli_stmt_get_result($stmt);



$data = [];



while ($row = mysqli_fetch_assoc($result)) {


    $data[] = [


        'leadId' =>
            (int)$row['leadId'],


        'leadName' =>
            $row['leadName'],


        'phone' =>
            $row['phone'],


        'employeeName' =>
            $row['employeeName'] ?? 'Admin',


        'status' =>
            ucfirst(
                $row['status']
            ),


        'remark' =>
            $row['remark'],


        'followUpDate' =>
            date(
                'd M Y',
                strtotime(
                    $row['followUpDateTime']
                )
            ),


        'followUpTime' =>
            date(
                'h:i A',
                strtotime(
                    $row['followUpDateTime']
                )
            )

    ];

}



mysqli_stmt_close($stmt);



echo json_encode([
    'success' => true,
    'data' => $data
]);