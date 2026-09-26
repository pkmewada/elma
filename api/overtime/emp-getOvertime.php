<?php

require_once __DIR__ . '/../../includes/emp-auth.php';

require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function respond(
    bool $success,
    string $message,
    array $data = []
): void {

    echo json_encode([

        'success' => $success,

        'message' => $message,

        'data' => $data

    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Validate Employee Session
|--------------------------------------------------------------------------
*/

$employeeId =
    (int)($_SESSION['candidateId'] ?? 0);

if ($employeeId <= 0) {

    respond(
        false,
        'Invalid employee session.'
    );
}

/*
|--------------------------------------------------------------------------
| Fetch Employee Overtime Requests
|--------------------------------------------------------------------------
*/

$query = "

    SELECT

        ot.id,

        ot.employeeId,

        e.fullName AS employeeName,

        ot.date,

        ot.startTime,

        ot.endTime,

        ot.totalHours,

        ot.calculatedOtHours AS otHours,

        ot.status,

        ot.createdAt

    FROM overtimeRequests ot

    INNER JOIN employeeusers e
        ON e.id = ot.employeeId

    WHERE

        ot.employeeId = ?

    ORDER BY ot.id DESC

";

$stmt =
    mysqli_prepare(
        $con,
        $query
    );

if (!$stmt) {

    respond(
        false,
        'Unable to prepare query.'
    );
}

/*
|--------------------------------------------------------------------------
| Bind Employee ID
|--------------------------------------------------------------------------
*/

mysqli_stmt_bind_param(
    $stmt,
    'i',
    $employeeId
);

/*
|--------------------------------------------------------------------------
| Execute Query
|--------------------------------------------------------------------------
*/

mysqli_stmt_execute(
    $stmt
);

$result =
    mysqli_stmt_get_result(
        $stmt
    );

$data = [];

/*
|--------------------------------------------------------------------------
| Format Response Data
|--------------------------------------------------------------------------
*/

while ($row = mysqli_fetch_assoc($result)) {

    $data[] = [

        'id' =>

            (int)$row['id'],

        'employeeId' =>

            (int)$row['employeeId'],

        'employeeName' =>

            $row['employeeName'] ?? 'Unknown',

        'date' =>

            $row['date'] ?? '',

        'startTime' =>

            !empty($row['startTime'])

                ? date(
                    'h:i A',
                    strtotime($row['startTime'])
                )

                : '',

        'endTime' =>

            !empty($row['endTime'])

                ? date(
                    'h:i A',
                    strtotime($row['endTime'])
                )

                : '',

        'totalHours' =>

            number_format(
                (float)$row['totalHours'],
                2
            ),

        'otHours' =>

            number_format(
                (float)$row['otHours'],
                2
            ),

        'status' =>

            strtolower(
                $row['status'] ?? 'pending'
            ),

        'createdAt' =>

            $row['createdAt'] ?? ''

    ];

}

/*
|--------------------------------------------------------------------------
| Close Statement
|--------------------------------------------------------------------------
*/

mysqli_stmt_close(
    $stmt
);

/*
|--------------------------------------------------------------------------
| Final Response
|--------------------------------------------------------------------------
*/

respond(

    true,

    'Overtime fetched successfully.',

    $data

);