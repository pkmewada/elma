<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/mailer.php';
require_once __DIR__ . '/../../includes/permission-helper.php';

header('Content-Type: application/json; charset=UTF-8');

// Confirming joining creates the employee login, so it is an approval.
requireApiPermission('/onboarding-queue', 'canApprove');
error_reporting(E_ALL);
ini_set('display_errors', 1);
$id = (int) ($_POST['id'] ?? 0);
$joiningStatus = trim((string) ($_POST['joiningStatus'] ?? ''));

/*
|--------------------------------------------------------------------------
| Validate Request
|--------------------------------------------------------------------------
*/
if ($id <= 0 || !in_array($joiningStatus, ['Open', 'Confirmed'], true)) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Update Joining Status
|--------------------------------------------------------------------------
*/
$stmt = mysqli_prepare($con, "
    UPDATE candidateRecord
    SET joiningStatus = ?
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => 'Unable to prepare query.'
    ]);
    exit;
}

mysqli_stmt_bind_param($stmt, 'si', $joiningStatus, $id);

$updated = mysqli_stmt_execute($stmt);

if (!$updated) {
    echo json_encode([
        'success' => false,
        'message' => 'Update failed.'
    ]);
    exit;
}

mysqli_stmt_close($stmt);

/*
|--------------------------------------------------------------------------
| If Confirmed -> Create Employee Login + Send Welcome Mail
|--------------------------------------------------------------------------
*/
if (strcasecmp($joiningStatus, 'Confirmed') === 0) {

    $candidateStmt = mysqli_prepare($con, "
        SELECT id, fullName, email, phoneNumber, appliedRole, joiningDate
        FROM candidateRecord
        WHERE id = ?
        LIMIT 1
    ");

    if ($candidateStmt) {

        mysqli_stmt_bind_param($candidateStmt, 'i', $id);
        mysqli_stmt_execute($candidateStmt);

        $result = mysqli_stmt_get_result($candidateStmt);
        $candidateRow = mysqli_fetch_assoc($result);

        mysqli_stmt_close($candidateStmt);

        if ($candidateRow && !empty($candidateRow['email'])) {

            $email = trim($candidateRow['email']);
            $fullName = trim($candidateRow['fullName']);
            $phoneNumber = trim((string) $candidateRow['phoneNumber']);
            $designation = trim((string) $candidateRow['appliedRole']);
            $joiningDateValue = trim((string) $candidateRow['joiningDate']);

            /*
            |--------------------------------------------------------------------------
            | Check Existing Employee User
            |--------------------------------------------------------------------------
            */
            $checkStmt = mysqli_prepare($con, "
                SELECT id
                FROM employeeusers
                WHERE emailAddress = ?
                LIMIT 1
            ");

            $alreadyExists = false;

            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 's', $email);
                mysqli_stmt_execute($checkStmt);
                $checkResult = mysqli_stmt_get_result($checkStmt);

                if (mysqli_fetch_assoc($checkResult)) {
                    $alreadyExists = true;
                }

                mysqli_stmt_close($checkStmt);
            }

            /*
            |--------------------------------------------------------------------------
            | Create Employee Account If Not Exists
            |--------------------------------------------------------------------------
            */
            if (!$alreadyExists) {

                $tempPassword = 'Temp@' . rand(1000, 9999);
                $passwordHash = password_hash($tempPassword, PASSWORD_DEFAULT);

                $insertStmt = mysqli_prepare($con, "
                    INSERT INTO employeeusers (
                        fullName,
                        emailAddress,
                        mobileNumber,
                        designationName,
                        joiningDate,
                        passwordHash,
                        tempPassword,
                        isTempPassword,
                        accountStatus,
                        profileStatus,
                        joiningStatus,
                        candidateRecordId
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'Active', 'Incomplete', 'Confirmed', ?)
                ");

                if ($insertStmt) {

                    mysqli_stmt_bind_param(
                        $insertStmt,
                        'sssssssi',
                        $fullName,
                        $email,
                        $phoneNumber,
                        $designation,
                        $joiningDateValue,
                        $passwordHash,
                        $tempPassword,
                        $id
                    );

                    $created = mysqli_stmt_execute($insertStmt);

                    mysqli_stmt_close($insertStmt);

                    /*
                    |--------------------------------------------------------------------------
                    | Send Welcome Mail
                    |--------------------------------------------------------------------------
                    */
                    if (!$created) {

                        error_log(
                            'Employee account creation failed for candidate ID: ' . $id .
                            ' | DB Error: ' . mysqli_error($con)
                        );
                    
                    } else {
                    
                        error_log(
                            'Employee account created successfully for: ' . $email
                        );
                    
                        if (function_exists('sendWelcomeEmail')) {
                    
                            $mailSent = sendWelcomeEmail(
                                $email,
                                $fullName,
                                $tempPassword
                            );
                    
                            if ($mailSent) {
                    
                                error_log(
                                    'Welcome email sent successfully to: ' . $email
                                );
                    
                            } else {
                    
                                error_log(
                                    'Welcome email failed for: ' . $email
                                );
                            }
                    
                        } else {
                    
                            error_log(
                                'sendWelcomeEmail() function not found.'
                            );
                        }
                    }
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Final Success Response
|--------------------------------------------------------------------------
*/
echo json_encode([
    'success' => true,
    'message' => 'Joining status updated successfully.'
]);
exit;