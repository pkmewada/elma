<?php
error_reporting(0);
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
    
    /*
    |--------------------------------------------------------------------------
    | Update Reset OTP
    |--------------------------------------------------------------------------
    */
    
    public function updateResetOtp(
        string $email,
        string $otp,
        string $otpExpiresAt
    ): bool {
    
        global $con;
    
        $stmt = mysqli_prepare($con, "
    
            UPDATE employeeusers
    
            SET
    
                otp = ?,
                otpExpiresAt = ?
    
            WHERE emailAddress = ?
    
            LIMIT 1
    
        ");
    
        if (!$stmt) {
    
            return false;
    
        }
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            'sss',
    
            $otp,
    
            $otpExpiresAt,
    
            $email
    
        );
    
        $ok =
            mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        return $ok;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Verify Reset OTP
    |--------------------------------------------------------------------------
    */
    
    public function verifyResetOtp(
        string $email,
        string $otp
    ): bool {
    
        global $con;
    
        $stmt = mysqli_prepare($con, "
    
            SELECT
    
                id,
                otp,
                otpExpiresAt
    
            FROM employeeusers
    
            WHERE emailAddress = ?
    
            LIMIT 1
    
        ");
    
        if (!$stmt) {
    
            return false;
    
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            's',
            $email
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $candidate =
            mysqli_fetch_assoc($result);
    
        mysqli_stmt_close($stmt);
    
        if (!$candidate) {
    
            return false;
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Invalid OTP
        |--------------------------------------------------------------------------
        */
    
        if (
    
            trim((string)$candidate['otp']) !==
            trim((string)$otp)
    
        ) {
    
            return false;
    
        }
    
        /*
        |--------------------------------------------------------------------------
        | Expired OTP
        |--------------------------------------------------------------------------
        */
    
        if (
    
            empty($candidate['otpExpiresAt']) ||
    
            strtotime($candidate['otpExpiresAt']) < time()
    
        ) {
    
            return false;
    
        }
    
        return true;
    }
    
    /*
    |--------------------------------------------------------------------------
    | Update Forgot Password
    |--------------------------------------------------------------------------
    */
    
    public function updateForgotPassword(
        string $email,
        string $passwordHash
    ): bool {
    
        global $con;
    
        $stmt = mysqli_prepare($con, "
    
            UPDATE employeeusers
    
            SET
    
                passwordHash = ?,
                otp = NULL,
                otpExpiresAt = NULL,
                updatedAt = NOW()
    
            WHERE emailAddress = ?
    
            LIMIT 1
    
        ");
    
        if (!$stmt) {
    
            return false;
    
        }
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            'ss',
    
            $passwordHash,
    
            $email
    
        );
    
        $ok =
            mysqli_stmt_execute($stmt);
    
        mysqli_stmt_close($stmt);
    
        return $ok;
    }
    
    

    public function getCandidateById(int $id): ?array
    {
        global $con;

        $stmt = mysqli_prepare($con, "
            SELECT *
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
    
     public function resetVerificationForFields(
        int $employeeUserId,
        array $fields
    ): void {
    
        global $con;
    
        if (empty($fields)) {
            return;
        }
    
        $escapedFields = [];
    
        foreach ($fields as $field) {
    
            $escapedFields[] =
                "'" .
                mysqli_real_escape_string(
                    $con,
                    $field
                ) .
                "'";
        }
    
        mysqli_query(
            $con,
            "
            UPDATE employeeProfileVerification
            SET
                verifyStatus = 'Pending',
                reviewRemark = '',
                updatedAt = NOW()
            WHERE employeeUserId = {$employeeUserId}
              AND fieldName IN (
                    " . implode(',', $escapedFields) . "
              )
        "
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Update Candidate Profile
    |--------------------------------------------------------------------------
    */
      public function updateCandidateProfile(
        int $id,
        array $data
    ): bool {
    
        global $con;
    
        if (empty($data)) {
            return false;
        }
    
        $allowedFields = [
    
            'mobileNumber',
            'alternativeNumber',
            'emergencyContactNumber',
    
            'dateOfBirth',
    
            'gender',
            'maritalStatus',
    
            'linkedInProfile',
            'instagramProfile',
    
            'permanentAddress',
            'localAddress',
    
            'cityName',
            'stateName',
    
            'pinCode',
    
            'accountHolderName',
            'bankName',
            'accountNumber',
            'ifscCode',
            'branchName',
    
            'aadhaarNumber',
            'panNumber',
    
            'profilePhoto',
            'aadhaarFile',
            'panFile',
    
            'marksheet10File',
            'marksheet12File',
    
            'graduationFile',
    
            'bankPassbookFile'
        ];
    
        $setParts = [];
    
        $values = [];
    
        $types = '';
    
        foreach ($allowedFields as $field) {
    
            if (!array_key_exists($field, $data)) {
                continue;
            }
    
            $setParts[] = "{$field} = ?";
    
            $values[] = $data[$field];
    
            $types .= 's';
        }
    
        if (empty($setParts)) {
            return false;
        }
    
        $setParts[] = "profileStatus = 'Submitted'";
    
        $setParts[] = "updatedAt = NOW()";
    
        $sql = "
            UPDATE employeeusers
            SET
                " . implode(",\n", $setParts) . "
            WHERE id = ?
            LIMIT 1
        ";
    
        $stmt = mysqli_prepare($con, $sql);
    
        if (!$stmt) {
    
            die(mysqli_error($con));
        }
    
        $types .= 'i';
    
        $values[] = $id;
    
        mysqli_stmt_bind_param(
            $stmt,
            $types,
            ...$values
        );
    
        $ok = mysqli_stmt_execute($stmt);
    
        if (!$ok) {
    
            die(mysqli_stmt_error($stmt));
        }
    
        mysqli_stmt_close($stmt);
    
        return true;
    }
    
    
    
    public function getRejectedVerificationRemarks(
        int $employeeUserId
    ): array {
    
        global $con;
    
        $remarks = [];
    
        $stmt = mysqli_prepare(
            $con,
            "
            SELECT
                fieldName,
                reviewRemark
            FROM employeeProfileVerification
            WHERE employeeUserId = ?
              AND verifyStatus = 'Rejected'
        "
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeUserId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        while ($row = mysqli_fetch_assoc($result)) {
    
            $remarks[
                $row['fieldName']
            ] = $row['reviewRemark'];
        }
    
        mysqli_stmt_close($stmt);
    
        return $remarks;
    }

}
