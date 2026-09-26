<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../includes/emp-auth.php';
require_once __DIR__ . '/../../includes/db.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Response Helper
|--------------------------------------------------------------------------
*/

function respond(
    $success,
    $message = '',
    $data = []
) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);

    exit;
}

/*
|--------------------------------------------------------------------------
| Build Employee Folder Name
|--------------------------------------------------------------------------
*/

function buildEmployeeFolderName($fullName, $id)
{
    $fullName = preg_replace(
        '/[^a-zA-Z0-9 ]/',
        '',
        (string)$fullName
    );

    $parts = preg_split(
        '/\s+/',
        trim($fullName)
    );

    if (!$parts || empty($parts[0])) {
        return 'employee_' . $id;
    }

    $folder = strtolower(
        array_shift($parts)
    );

    foreach ($parts as $part) {
        $folder .= ucfirst(
            strtolower($part)
        );
    }

    return $folder . '_' . $id;
}

/*
|--------------------------------------------------------------------------
| Clean Old Uploaded File Name
|--------------------------------------------------------------------------
*/

function cleanUploadedFileName($fileName)
{
    $fileName = trim((string)$fileName);

    if ($fileName === '') {
        return '';
    }

    $fileName = str_replace('\\', '/', $fileName);

    return basename($fileName);
}

try {

    /*
    |--------------------------------------------------------------------------
    | Validate Employee Session
    |--------------------------------------------------------------------------
    */

    $employeeId =
        intval($_SESSION['candidateId'] ?? 0);

    if ($employeeId <= 0) {
        respond(
            false,
            'Unauthorized access.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Inputs
    |--------------------------------------------------------------------------
    */

    $alternativeNumber =
        trim($_POST['alternativeNumber'] ?? '');

    $emergencyContactNumber =
        trim($_POST['emergencyContactNumber'] ?? '');

    $aboutMe =
        trim($_POST['aboutMe'] ?? '');

    $skills =
        trim($_POST['skills'] ?? '');

    /*
    |--------------------------------------------------------------------------
    | Validations
    |--------------------------------------------------------------------------
    */

    if (
        !empty($alternativeNumber) &&
        !preg_match('/^[0-9]{10}$/', $alternativeNumber)
    ) {
        respond(
            false,
            'Alternative number must be 10 digits.'
        );
    }

    if (
        !empty($emergencyContactNumber) &&
        !preg_match('/^[0-9]{10}$/', $emergencyContactNumber)
    ) {
        respond(
            false,
            'Emergency contact number must be 10 digits.'
        );
    }

    if (strlen($aboutMe) > 3000) {
        respond(
            false,
            'About me content is too long.'
        );
    }

    if (strlen($skills) > 1000) {
        respond(
            false,
            'Skills content is too long.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Check Employee Exists
    |--------------------------------------------------------------------------
    */

    $checkStmt = mysqli_prepare(
        $con,
        "SELECT
            id,
            fullName,
            profilePhoto
         FROM employeeusers
         WHERE id = ?
         LIMIT 1"
    );

    if (!$checkStmt) {
        respond(
            false,
            'Unable to prepare employee check.'
        );
    }

    mysqli_stmt_bind_param(
        $checkStmt,
        "i",
        $employeeId
    );

    mysqli_stmt_execute(
        $checkStmt
    );

    $checkResult =
        mysqli_stmt_get_result(
            $checkStmt
        );

    $employee =
        mysqli_fetch_assoc(
            $checkResult
        );

    mysqli_stmt_close(
        $checkStmt
    );

    if (!$employee) {
        respond(
            false,
            'Employee not found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Photo Upload
    |--------------------------------------------------------------------------
    */

    $profilePhotoFileName =
        cleanUploadedFileName(
            $employee['profilePhoto'] ?? ''
        );

    if (
        isset($_FILES['profilePhoto']) &&
        $_FILES['profilePhoto']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['profilePhoto']['error'] !== UPLOAD_ERR_OK) {
            respond(
                false,
                'Profile photo upload failed.'
            );
        }

        if ($_FILES['profilePhoto']['size'] > 2 * 1024 * 1024) {
            respond(
                false,
                'Profile photo size must be less than 2MB.'
            );
        }

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];

        $fileInfo =
            finfo_open(
                FILEINFO_MIME_TYPE
            );

        $mimeType =
            finfo_file(
                $fileInfo,
                $_FILES['profilePhoto']['tmp_name']
            );

        finfo_close(
            $fileInfo
        );

        if (!isset($allowedMimeTypes[$mimeType])) {
            respond(
                false,
                'Only JPG, PNG and WEBP images are allowed.'
            );
        }

        $folderName =
            buildEmployeeFolderName(
                $employee['fullName'] ?? '',
                $employeeId
            );

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT UPLOAD PATH
        |--------------------------------------------------------------------------
        | Matches your existing employee folder concept:
        | uploads/candidates/{firstNameLastName_id}/
        |--------------------------------------------------------------------------
        */

        $uploadDir =
            dirname(__DIR__, 2) .
            '/uploads/candidates/' .
            $folderName .
            '/';

        if (!is_dir($uploadDir)) {
            mkdir(
                $uploadDir,
                0775,
                true
            );
        }

        if (!is_writable($uploadDir)) {
            respond(
                false,
                'Upload folder is not writable.'
            );
        }

        $extension =
            $allowedMimeTypes[$mimeType];

        $profilePhotoFileName =
            'profilePhoto_' .
            $employeeId .
            '_' .
            time() .
            '.' .
            $extension;

        $targetPath =
            $uploadDir .
            $profilePhotoFileName;

        if (
            !move_uploaded_file(
                $_FILES['profilePhoto']['tmp_name'],
                $targetPath
            )
        ) {
            respond(
                false,
                'Unable to save profile photo.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Update Profile
    |--------------------------------------------------------------------------
    */

    $updateStmt = mysqli_prepare(
        $con,
        "UPDATE employeeusers

         SET
            alternativeNumber = ?,
            emergencyContactNumber = ?,
            aboutMe = ?,
            skills = ?,
            profilePhoto = ?,
            updatedAt = NOW()

         WHERE id = ?"
    );

    if (!$updateStmt) {
        respond(
            false,
            'Unable to prepare profile update.'
        );
    }

    mysqli_stmt_bind_param(
        $updateStmt,
        "sssssi",
        $alternativeNumber,
        $emergencyContactNumber,
        $aboutMe,
        $skills,
        $profilePhotoFileName,
        $employeeId
    );

    $updated =
        mysqli_stmt_execute(
            $updateStmt
        );

    mysqli_stmt_close(
        $updateStmt
    );

    if (!$updated) {
        respond(
            false,
            'Unable to update profile.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Profile updated successfully.',
        [
            'profilePhoto' => $profilePhotoFileName
        ]
    );

} catch (Exception $e) {

    respond(
        false,
        $e->getMessage()
    );
}