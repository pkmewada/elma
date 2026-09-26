<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../includes/db.php';

class CandidateModel
{
    /*
    |--------------------------------------------------------------------------
    | Get Candidate By Email
    |--------------------------------------------------------------------------
    */
    public function getCandidateByEmail(string $email): ?array
    {
        global $con;

        $stmt = mysqli_prepare($con, "
            SELECT
                id,
                fullName,
                emailAddress,
                passwordHash,
                isTempPassword,
                accountStatus,
                profileStatus
            FROM employeeusers
            WHERE emailAddress = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    public function getCandidateById(int $id): ?array
    {
        global $con;

        $stmt = mysqli_prepare($con, "
            SELECT
                id,
                fullName,
                emailAddress,
                passwordHash,
                isTempPassword,
                accountStatus,
                profileStatus
            FROM employeeusers
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return null;
        }

        mysqli_stmt_bind_param($stmt, 'i', $id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $row ?: null;
    }

    public function resetCandidatePassword(int $id, string $newPasswordHash): bool
    {
        global $con;

        $stmt = mysqli_prepare($con, "
            UPDATE employeeusers
            SET
                passwordHash = ?,
                tempPassword = '',
                isTempPassword = 0,
                updatedAt = NOW()
            WHERE id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            return false;
        }

        mysqli_stmt_bind_param($stmt, 'si', $newPasswordHash, $id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Candidate Profile
    |--------------------------------------------------------------------------
    */
   public function updateCandidateProfile(int $id, array $data): bool
{
    global $con;

    $sql = "
        UPDATE employeeusers
        SET
            mobileNumber = ?,
            alternativeNumber = ?,
            emergencyContactNumber = ?,
            dateOfBirth = ?,
            gender = ?,
            maritalStatus = ?,
            linkedInProfile = ?,
            instagramProfile = ?,
            permanentAddress = ?,
            localAddress = ?,
            cityName = ?,
            stateName = ?,
            pinCode = ?,
            accountHolderName = ?,
            bankName = ?,
            accountNumber = ?,
            ifscCode = ?,
            branchName = ?,
            aadhaarNumber = ?,
            panNumber = ?,

            profilePhoto = ?,
            aadhaarFile = ?,
            panFile = ?,
            marksheet10File = ?,
            marksheet12File = ?,
            graduationFile = ?,
            bankPassbookFile = ?,

            profileStatus = 'Submitted',
            updatedAt = NOW()

        WHERE id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($con, $sql);

    if (!$stmt) {
        die(mysqli_error($con));
    }

    mysqli_stmt_bind_param(
        $stmt,
        'sssssssssssssssssssssssssssi',

        $data['mobileNumber'],
        $data['alternativeNumber'],
        $data['emergencyContactNumber'],
        $data['dateOfBirth'],
        $data['gender'],
        $data['maritalStatus'],
        $data['linkedInProfile'],
        $data['instagramProfile'],
        $data['permanentAddress'],
        $data['localAddress'],
        $data['cityName'],
        $data['stateName'],
        $data['pinCode'],
        $data['accountHolderName'],
        $data['bankName'],
        $data['accountNumber'],
        $data['ifscCode'],
        $data['branchName'],
        $data['aadhaarNumber'],
        $data['panNumber'],

        $data['profilePhoto'],
        $data['aadhaarFile'],
        $data['panFile'],
        $data['marksheet10File'],
        $data['marksheet12File'],
        $data['graduationFile'],
        $data['bankPassbookFile'],

        $id
    );

    $ok = mysqli_stmt_execute($stmt);

    if (!$ok) {
        die(mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
    return true;
}
}
