<?php
require_once __DIR__ . '/mailer.php';
class EmployeeInfoEngine
{
    private $con;

    public function __construct($con)
    {
        $this->con = $con;
    }

    // =============================
    // GET EMPLOYEE BY ID
    // =============================
    public function getById($employeeId)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM employeeusers
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "i", $employeeId);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $employee = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $employee ?: null;
    }

    // =============================
    // GET EMPLOYEE BY EMPLOYEE CODE
    // =============================
    public function getByEmployeeCode($employeeCode)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM employeeusers
             WHERE employeeCode = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $employeeCode);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $employee = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $employee ?: null;
    }

    // =============================
    // GET EMPLOYEE BY EMAIL
    // =============================
    public function getByEmail($email)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT *
             FROM employeeusers
             WHERE emailAddress = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "s", $email);

        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        $employee = mysqli_fetch_assoc($result);

        mysqli_stmt_close($stmt);

        return $employee ?: null;
    }

    // =============================
    // GET CURRENT LOGGED EMPLOYEE
    // =============================
    public function getCurrentEmployee()
    {
        if (empty($_SESSION['candidateId'])) {
            return null;
        }

        return $this->getById(
            $_SESSION['candidateId']
        );
    }

    // =============================
    // GET BASIC PROFILE
    // =============================
    public function getBasicProfile($employeeId)
    {
        $employee = $this->getById($employeeId);

        if (!$employee) {
            return null;
        }

        return [

            'id' => $employee['id'],

            'employeeCode' => $employee['employeeCode'],

            'fullName' => $employee['fullName'],

            'emailAddress' => $employee['emailAddress'],

            'mobileNumber' => $employee['mobileNumber'],

            'departmentName' => $employee['departmentName'],

            'designationName' => $employee['designationName'],

            'joiningDate' => $employee['joiningDate'],

            'employmentStatus' => $employee['employmentStatus'],

            'cityName' => $employee['cityName'],

            'stateName' => $employee['stateName'],

            'profilePhoto' => $employee['profilePhoto']

        ];
    }

    // =============================
    // BUILD EMPLOYEE FOLDER NAME
    // =============================
    public function buildEmployeeFolderName($fullName, $id)
    {
        $fullName = preg_replace(
            '/[^a-zA-Z0-9 ]/',
            '',
            $fullName
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

    // =============================
    // GET EMPLOYEE FOLDER PATH
    // =============================
    public function getEmployeeFolderPath($employee)
    {
        if (
            empty($employee['fullName']) ||
            empty($employee['id'])
        ) {
            return null;
        }

        $folderName = $this->buildEmployeeFolderName(
            $employee['fullName'],
            $employee['id']
        );

        return UPLOAD_URL .
            '/candidates/' .
            $folderName .
            '/';
    }

    // =============================
    // GET PROFILE PHOTO URL
    // =============================
    public function getProfilePhotoUrl($employee)
    {
        if (empty($employee['profilePhoto'])) {
            return null;
        }

        return $this->getEmployeeFolderPath($employee) .
            $employee['profilePhoto'];
    }

    // =============================
    // GET ALL EMPLOYEE DOCUMENTS
    // =============================
    public function getDocuments($employee)
    {
        if (!$employee) {
            return [];
        }

        $folderPath = $this->getEmployeeFolderPath(
            $employee
        );

        return [

            'profilePhoto' => [
                'fileName' => $employee['profilePhoto'] ?? '',
                'fileUrl' => !empty($employee['profilePhoto'])
                    ? $folderPath . $employee['profilePhoto']
                    : null
            ],

            'aadhaarFile' => [
                'fileName' => $employee['aadhaarFile'] ?? '',
                'fileUrl' => !empty($employee['aadhaarFile'])
                    ? $folderPath . $employee['aadhaarFile']
                    : null
            ],

            'panFile' => [
                'fileName' => $employee['panFile'] ?? '',
                'fileUrl' => !empty($employee['panFile'])
                    ? $folderPath . $employee['panFile']
                    : null
            ],

            'marksheet10File' => [
                'fileName' => $employee['marksheet10File'] ?? '',
                'fileUrl' => !empty($employee['marksheet10File'])
                    ? $folderPath . $employee['marksheet10File']
                    : null
            ],

            'marksheet12File' => [
                'fileName' => $employee['marksheet12File'] ?? '',
                'fileUrl' => !empty($employee['marksheet12File'])
                    ? $folderPath . $employee['marksheet12File']
                    : null
            ],

            'graduationFile' => [
                'fileName' => $employee['graduationFile'] ?? '',
                'fileUrl' => !empty($employee['graduationFile'])
                    ? $folderPath . $employee['graduationFile']
                    : null
            ],

            'bankPassbookFile' => [
                'fileName' => $employee['bankPassbookFile'] ?? '',
                'fileUrl' => !empty($employee['bankPassbookFile'])
                    ? $folderPath . $employee['bankPassbookFile']
                    : null
            ]

        ];
    }

    // =============================
    // CHECK EMPLOYEE EXISTS
    // =============================
    public function exists($employeeId)
    {
        $stmt = mysqli_prepare(
            $this->con,
            "SELECT id
             FROM employeeusers
             WHERE id = ?
             LIMIT 1"
        );

        mysqli_stmt_bind_param($stmt, "i", $employeeId);

        mysqli_stmt_execute($stmt);

        mysqli_stmt_store_result($stmt);

        $exists = mysqli_stmt_num_rows($stmt) > 0;

        mysqli_stmt_close($stmt);

        return $exists;
    }
}
