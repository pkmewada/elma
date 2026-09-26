<?php
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/leave-application-days.php';
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
    
    
    
    
    // =============================
    // GET EMPLOYEE DEDUCTIONS
    // =============================
    public function getEmployeeDeductions(
        $employeeId,
        $limit = null
    ) {
    
        $sql = "
    
            SELECT
    
                id,
                employeeId,
                employeeName,
                deductionType,
                amount,
                deductionDate,
                remark,
                createdBy,
                createdAt
    
            FROM employeeDeductions
    
            WHERE employeeId = ?
    
            ORDER BY deductionDate DESC
    
        ";
    
        /*
        |--------------------------------------------------------------------------
        | Limit
        |--------------------------------------------------------------------------
        */
    
        if ($limit !== null) {
    
            $sql .= " LIMIT " . intval($limit);
        }
    
        $stmt = mysqli_prepare(
            $this->con,
            $sql
        );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result = mysqli_stmt_get_result($stmt);
    
        $records = [];
    
        while ($row = mysqli_fetch_assoc($result)) {
    
            $records[] = $row;
        }
    
        mysqli_stmt_close($stmt);
    
        return $records;
    }
        
    // ============================================================
    // CURRENT EMPLOYEE ID
    // ============================================================
    private function getCurrentEmployeeId()
    {
        return (int)(
            $_SESSION['candidateId'] ?? 0
        );
    }
    
    // ============================================================
    // GET LEAVE SETTINGS
    // ============================================================
    public function getLeaveSettings()
    {
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT
    
                workingDays,
                weekendPolicy,
                sandwichRule,
                carryForward,
                carryForwardLimit,
                maxLeavesPerRequest,
                minNoticeDays,
                setupCompleted
    
             FROM leaveSettings
    
             LIMIT 1"
    
        );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $settings =
            mysqli_fetch_assoc($result);
    
        mysqli_stmt_close($stmt);
    
        if (!$settings) {
    
            return [];
        }
    
        $settings['workingDays'] =
            json_decode(
                $settings['workingDays'] ?? '[]',
                true
            ) ?: [];
    
        return $settings;
    }
    
    // ============================================================
    // GET LEAVE TYPES
    // ============================================================
    public function getLeaveTypes(
        $onlyActive = false
    ) {
    
        $sql = "
    
            SELECT
    
                id,
                name,
                code,
                isPaid,
                allocationType,
                totalLeaves,
                isActive,
                allowHalfDay,
                maxConsecutiveDays,
                minServiceDays,
                applicableGender,
                allowNegative
    
            FROM leaveTypes
    
        ";
    
        if ($onlyActive) {
    
            $sql .= " WHERE isActive = 1 ";
        }
    
        $sql .= " ORDER BY id ASC ";
    
        $result =
            mysqli_query(
                $this->con,
                $sql
            );
    
        $records = [];
    
        while ($row = mysqli_fetch_assoc($result)) {
    
            $records[] = [
    
                'id' =>
                    (int)$row['id'],
    
                'name' =>
                    trim($row['name'] ?? ''),
    
                'code' =>
                    strtoupper(
                        trim($row['code'] ?? '')
                    ),
    
                'isPaid' =>
                    (int)$row['isPaid'],
    
                'allocationType' =>
                    $row['allocationType'],
    
                'totalLeaves' =>
                    (int)$row['totalLeaves'],
    
                'isActive' =>
                    (int)$row['isActive'],
    
                'allowHalfDay' =>
                    (int)$row['allowHalfDay'],
    
                'maxConsecutiveDays' =>
                    (int)$row['maxConsecutiveDays'],
    
                'minServiceDays' =>
                    (int)$row['minServiceDays'],
    
                'applicableGender' =>
                    $row['applicableGender'] ?? 'all',
    
                'allowNegative' =>
                    (int)$row['allowNegative']
    
            ];
        }
    
        return $records;
    }
    
    // ============================================================
    // GET SINGLE LEAVE TYPE
    // ============================================================
    public function getLeaveType(
        $leaveTypeId
    ) {
    
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT *
    
             FROM leaveTypes
    
             WHERE id = ?
    
             LIMIT 1"
    
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $leaveTypeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $row =
            mysqli_fetch_assoc($result);
    
        mysqli_stmt_close($stmt);
    
        return $row ?: null;
    }
    
    // ============================================================
    // CALCULATE LEAVE DAYS
    // ============================================================
    public function calculateLeaveDays(
        $fromDate,
        $toDate,
        $workingDays,
        $weekendPolicy
    ) {
    
        $start =
            new DateTime($fromDate);
    
        $end =
            new DateTime($toDate);
    
        $totalDays = 0;
    
        while ($start <= $end) {
    
            $day =
                strtolower(
                    $start->format('D')
                );
    
            if (
    
                in_array(
                    $day,
                    $workingDays,
                    true
                )
    
            ) {
    
                $totalDays++;
    
            } else {
    
                if (
                    $weekendPolicy === 'include'
                ) {
    
                    $totalDays++;
                }
            }
    
            $start->modify('+1 day');
        }
    
        return $totalDays;
    }
    
    // ============================================================
    // GET MY LEAVES
    // ============================================================
    public function getEmployeeLeaves()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [];
        }
    
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT
    
                la.id,
                la.leaveTypeId,
                lt.name AS leaveTypeName,
                lt.code AS leaveTypeCode,
                la.fromDate,
                la.toDate,
                la.totalDays,
                la.dayType,
                la.reason,
                la.status,
                la.createdAt
    
             FROM leaveApplications la
    
             LEFT JOIN leaveTypes lt
                ON lt.id = la.leaveTypeId
    
             WHERE la.employeeId = ?
    
             ORDER BY la.id DESC"
    
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $records = [];
    
        while ($row = mysqli_fetch_assoc($result)) {
    
            $records[] = [
    
                'id' =>
                    (int)$row['id'],
    
                'leaveTypeId' =>
                    (int)$row['leaveTypeId'],
    
                'leaveTypeName' =>
                    $row['leaveTypeName'] ?? '',
    
                'leaveTypeCode' =>
                    strtoupper(
                        $row['leaveTypeCode'] ?? ''
                    ),
    
                'fromDate' =>
                    $row['fromDate'],
    
                'toDate' =>
                    $row['toDate'],
    
                'totalDays' =>
                    (float)$row['totalDays'],

                'dayType' =>
                    $row['dayType'] ?? 'full',
    
                'reason' =>
                    $row['reason'] ?? '',
    
                'status' =>
                    strtolower(
                        $row['status'] ?? 'pending'
                    ),
    
                'createdAt' =>
                    $row['createdAt']
    
            ];
        }
    
        mysqli_stmt_close($stmt);
    
        return $records;
    }
    
    // ============================================================
    // GET MY LEAVE BALANCE
    // ============================================================
    public function getLeaveBalance(
        $leaveTypeId
    ) {
    
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [
    
                'allocatedLeaves' => 0,
                'usedLeaves' => 0,
                'remainingLeaves' => 0
    
            ];
        }
    
        $leaveType =
            $this->getLeaveType(
                $leaveTypeId
            );
    
        if (!$leaveType) {
    
            return [
    
                'allocatedLeaves' => 0,
                'usedLeaves' => 0,
                'remainingLeaves' => 0
    
            ];
        }
    
        $allocatedLeaves =
            (float)(
                $leaveType['totalLeaves'] ?? 0
            );
    
        $usedLeaves =
            getApprovedLeaveUnits(
                $this->con,
                $employeeId,
                $leaveTypeId
            );
    
        return [
    
            'allocatedLeaves' =>
                $allocatedLeaves,
    
            'usedLeaves' =>
                $usedLeaves,
    
            'remainingLeaves' =>
                $allocatedLeaves - $usedLeaves
    
        ];
    }
    
    // ============================================================
    // APPLY LEAVE
    // ============================================================
    public function applyLeave(
        $payload
    ) {
    
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [
    
                'success' => false,
                'message' => 'Invalid employee session'
    
            ];
        }
    
        $leaveTypeId =
            (int)(
                $payload['leaveTypeId'] ?? 0
            );
    
        $fromDate =
            trim(
                $payload['fromDate'] ?? ''
            );
    
        $toDate =
            trim(
                $payload['toDate'] ?? ''
            );

        if ($toDate === '') {
            $toDate = $fromDate;
        }

        $dayType =
            strtolower(
                trim(
                    $payload['dayType'] ?? 'full'
                )
            );

        if (
            !in_array(
                $dayType,
                [
                    'full',
                    'half'
                ],
                true
            )
        ) {
            $dayType = 'full';
        }
    
        $reason =
            trim(
                $payload['reason'] ?? ''
            );
    
        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */
    
        if (
            $leaveTypeId <= 0 ||
            !$fromDate
        ) {
    
            return [
    
                'success' => false,
                'message' => 'All fields are required'
    
            ];
        }
    
        if (
            !strtotime($fromDate) ||
            !strtotime($toDate)
        ) {
    
            return [
    
                'success' => false,
                'message' => 'Invalid date format'
    
            ];
        }
    
        if ($fromDate > $toDate) {
    
            return [
    
                'success' => false,
                'message' => 'Invalid leave range'
    
            ];
        }
    
        $today =
            new DateTime(date('Y-m-d'));
    
        /*
        |--------------------------------------------------------------------------
        | Settings
        |--------------------------------------------------------------------------
        */
    
        $settings =
            $this->getLeaveSettings();
    
        if (empty($settings)) {
    
            return [
    
                'success' => false,
                'message' => 'Leave settings missing'
    
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Leave Type
        |--------------------------------------------------------------------------
        */
    
        $leaveType =
            $this->getLeaveType(
                $leaveTypeId
            );
    
        if (
            !$leaveType ||
            (int)$leaveType['isActive'] !== 1
        ) {
    
            return [
    
                'success' => false,
                'message' => 'Invalid leave type'
    
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Calculate Days
        |--------------------------------------------------------------------------
        */
    
        $dateRows =
            calculateLeaveDateRows(
                $fromDate,
                $toDate,
                $dayType,
                normalizeLeaveWorkingDays($settings['workingDays'] ?? []),
                ($settings['weekendPolicy'] ?? 'exclude') === 'include' ? 'include' : 'exclude'
            );

        $totalDays =
            getLeaveDateRowsTotal(
                $dateRows
            );
    
        if ($totalDays <= 0) {
    
            return [
    
                'success' => false,
                'message' => 'No valid leave days selected'
    
            ];
        }

        if ($dayType === 'half') {

            if ($fromDate !== $toDate) {

                return [

                    'success' => false,
                    'message' => 'Half day leave can be applied for a single date only'

                ];
            }

            if ((int)($leaveType['allowHalfDay'] ?? 0) !== 1) {

                return [

                    'success' => false,
                    'message' => 'Half day leave is not allowed for selected leave type'

                ];
            }

        }
    
        /*
        |--------------------------------------------------------------------------
        | Max Leave Request
        |--------------------------------------------------------------------------
        */
    
        if (
    
            (int)$settings['maxLeavesPerRequest'] > 0 &&
    
            $totalDays >
            (int)$settings['maxLeavesPerRequest']
    
        ) {
    
            return [
    
                'success' => false,
                'message' => 'Exceeded maximum leave request limit'
    
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Max Consecutive
        |--------------------------------------------------------------------------
        */
    
        if (
    
            (int)$leaveType['maxConsecutiveDays'] > 0 &&
    
            $totalDays >
            (int)$leaveType['maxConsecutiveDays']
    
        ) {
    
            return [
    
                'success' => false,
                'message' => 'Exceeded max consecutive leave limit'
    
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Gender Validation
        |--------------------------------------------------------------------------
        */
    
        if (
            $leaveType['applicableGender'] !== 'all'
        ) {
    
            $employee =
                $this->getCurrentEmployee();
    
            $gender =
                strtolower(
                    $employee['gender'] ?? ''
                );
    
            if (
    
                strtolower(
                    $leaveType['applicableGender']
                ) !== $gender
    
            ) {
    
                return [
    
                    'success' => false,
                    'message' => 'Leave not allowed for your gender'
    
                ];
            }
        }
    
        /*
        |--------------------------------------------------------------------------
        | Overlap Check
        |--------------------------------------------------------------------------
        */
    
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT id
    
             FROM leaveApplications
    
             WHERE
    
                employeeId = ?
    
                AND status IN (
                    'pending',
                    'approved',
                    'partial'
                )
    
                AND (
    
                    (fromDate <= ? AND toDate >= ?)
    
                    OR
    
                    (fromDate <= ? AND toDate >= ?)
    
                )
    
             LIMIT 1"
    
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "issss",
    
            $employeeId,
    
            $fromDate,
            $fromDate,
    
            $toDate,
            $toDate
    
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $overlap =
            mysqli_fetch_assoc($result);
    
        mysqli_stmt_close($stmt);
    
        if ($overlap) {
    
            return [
    
                'success' => false,
                'message' => 'Leave already exists in this date range'
    
            ];
        }
    
       /*
        |--------------------------------------------------------------------------
        | Balance Validation
        |--------------------------------------------------------------------------
        */
        
        $balance =
            $this->getLeaveBalance(
                $leaveTypeId
            );
        
        $remainingLeaves =
            (float)(
                $balance['remainingLeaves'] ?? 0
            );
        
        $totalDays =
            (float)$totalDays;
        
        if (
        
            (int)$leaveType['allowNegative'] !== 1 &&
        
            $remainingLeaves < $totalDays
        
        ) {
        
            return [
        
                'success' => false,
        
                'message' => 'Insufficient leave balance'
            ];
        }
    
        /*
        |--------------------------------------------------------------------------
        | Insert Leave
        |--------------------------------------------------------------------------
        */
    
        mysqli_begin_transaction(
            $this->con
        );
    
        try {
    
            $stmt = mysqli_prepare(
    
                $this->con,
    
                "INSERT INTO leaveApplications (
                    companyId,
                    employeeId,
                    leaveTypeId,
                    fromDate,
                    toDate,
                    totalDays,
                    dayType,
                    reason
    
                ) VALUES (
    
                    ?, ?, ?, ?, ?, ?, ?, ?
    
                )"
    
            );

            $legacyCompanyId = 0;
     
            mysqli_stmt_bind_param(
    
                $stmt,
     
                "iiissdss",
                $legacyCompanyId,
                $employeeId,
                $leaveTypeId,
                $fromDate,
                $toDate,
                $totalDays,
                $dayType,
                $reason
    
            );
    
            if (
                !mysqli_stmt_execute($stmt)
            ) {
    
                throw new Exception(
                    'Failed to apply leave'
                );
            }
    
            $leaveId =
                mysqli_insert_id(
                    $this->con
                );
    
            mysqli_stmt_close($stmt);

            insertLeaveApplicationDays(
                $this->con,
                $leaveId,
                $dateRows,
                'pending'
            );
    
            mysqli_commit($this->con);
            
            /*
            |--------------------------------------------------------------------------
            | Send Mail
            |--------------------------------------------------------------------------
            */
            
            try {
            
                $employee =
                    $this->getCurrentEmployee();
            
                if (
                    !empty($employee['emailAddress'])
                ) {
            
               $mailSent =      sendLeaveAppliedEmail(
            
                        $leaveId,
            
                        $employee['emailAddress'],
            
                        $employee['fullName'],
            
                        $leaveType['name'] ?? '',
            
                        $fromDate,
            
                        $toDate,
                        
                        $dayType
            
                    );
                }
            
            } catch (Throwable $e) {
            
                error_log(
                    'Employee Leave Apply Mail Error: ' .
                    $e->getMessage()
                );
            }
    
            return [
    
                'success' => true,
    
                'message' => 'Leave applied successfully',
    
                'data' => [
    
                    'leaveId' =>
                        $leaveId,
    
                    'totalDays' =>
                        $totalDays,

                    'dayType' =>
                        $dayType,
                        
                    'mailSent' => $mailSent
    
                ]
    
            ];
    
        } catch (Exception $e) {
    
            mysqli_rollback(
                $this->con
            );
    
            return [
    
                'success' => false,
                'message' => $e->getMessage()
    
            ];
        }
    }
    
    // ============================================================
    // CANCEL LEAVE
    // ============================================================
    public function cancelLeave(
        $leaveId
    ) {
    
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [
    
                'success' => false,
                'message' => 'Invalid employee session'
    
            ];
        }
    
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "SELECT
    
                id,
                status
    
             FROM leaveApplications
    
             WHERE
    
                id = ?
                AND employeeId = ?
    
             LIMIT 1"
    
        );
    
        mysqli_stmt_bind_param(
            $stmt,
            "ii",
            $leaveId,
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $leave =
            mysqli_fetch_assoc($result);
    
        mysqli_stmt_close($stmt);
    
        if (!$leave) {
    
            return [
    
                'success' => false,
                'message' => 'Leave not found'
    
            ];
        }
    
        if (
            strtolower(
                $leave['status']
            ) !== 'pending'
        ) {
    
            return [
    
                'success' => false,
                'message' => 'Only pending leaves can be cancelled'
    
            ];
        }
    
        $cancelStatus =
            'cancelled';
    
        $stmt = mysqli_prepare(
    
            $this->con,
    
            "UPDATE leaveApplications
    
             SET status = ?
    
             WHERE
    
                id = ?
                AND employeeId = ?"
    
        );
    
        mysqli_stmt_bind_param(
    
            $stmt,
    
            "sii",
    
            $cancelStatus,
            $leaveId,
            $employeeId
    
        );
    
        $updated =
            mysqli_stmt_execute($stmt);
    
        $affected =
            mysqli_stmt_affected_rows($stmt);
    
        mysqli_stmt_close($stmt);
    
        if (
            !$updated ||
            $affected <= 0
        ) {
    
            return [
    
                'success' => false,
                'message' => 'Failed to cancel leave'
    
            ];
        }
        
        /*
        |--------------------------------------------------------------------------
        | Send Cancellation Mail
        |--------------------------------------------------------------------------
        */
        
        try {
        
            $employee =
                $this->getCurrentEmployee();
        
            if (
                !empty($employee['emailAddress'])
            ) {
        
                if (
                    function_exists(
                        'sendLeaveCancelledEmail'
                    )
                ) {
        
                    sendLeaveCancelledEmail(
        
                        $leaveId,
        
                        $employee['emailAddress'],
        
                        $employee['fullName']
        
                    );
                }
            }
        
        } catch (Throwable $e) {
        
            error_log(
                'Employee Leave Cancel Mail Error: ' .
                $e->getMessage()
            );
        }
    
        return [
    
            'success' => true,
    
            'message' => 'Leave cancelled successfully',
    
            'data' => [
    
                'leaveId' =>
                    $leaveId,
    
                'status' =>
                    $cancelStatus
    
            ]
    
        ];
    }   
    
    // ============================================================
    // GET EMPLOYEE COMMISSION TRANSACTIONS
    // ============================================================
    
    public function getEmployeeCommissionTransactions()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [];
        }
    
        $sql = "
    
            SELECT
    
                ect.id,
                ect.transactionCode,
                ect.employeeId,
                ect.amount,
                ect.remarks,
                ect.attachment,
                ect.effectiveMonth,
                ect.approvalStatus,
                ect.payrollStatus,
                ect.createdAt,
    
                cbc.id AS categoryId,
                cbc.categoryName,
                cbc.categoryCode,
                cbc.categoryType,
    
                eu.fullName AS employeeName
    
            FROM employeeCommissionTransactions ect
    
            LEFT JOIN commissionBonusCategories cbc
                ON cbc.id = ect.categoryId
    
            LEFT JOIN employeeusers eu
                ON eu.id = ect.employeeId
    
            WHERE
    
                ect.employeeId = ?
                AND ect.isReverted = 0
    
            ORDER BY ect.id DESC
    
        ";
    
        $stmt = mysqli_prepare(
            $this->con,
            $sql
        );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            "i",
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result($stmt);
    
        $records = [];
    
        while (
            $row = mysqli_fetch_assoc($result)
        ) {
    
            $records[] = [
    
                'id' =>
                    (int)$row['id'],
    
                'transactionCode' =>
                    $row['transactionCode'] ?? '',
    
                'employeeId' =>
                    (int)$row['employeeId'],
    
                'employeeName' =>
                    $row['employeeName'] ?? '',
    
                'categoryId' =>
                    (int)$row['categoryId'],
    
                'categoryName' =>
                    $row['categoryName'] ?? '',
    
                'categoryCode' =>
                    $row['categoryCode'] ?? '',
    
                'categoryType' =>
                    $row['categoryType'] ?? '',
    
                'amount' =>
                    (float)$row['amount'],
    
                'remarks' =>
                    $row['remarks'] ?? '',
    
                'attachment' =>
                    $row['attachment'] ?? '',
    
                'effectiveMonth' =>
                    $row['effectiveMonth'] ?? '',
    
                'approvalStatus' =>
                    $row['approvalStatus'] ?? 'Pending',
    
                'payrollStatus' =>
                    $row['payrollStatus'] ?? 'Pending',
    
                'createdAt' =>
                    $row['createdAt'] ?? ''
    
            ];
        }
    
        mysqli_stmt_close($stmt);
    
        return $records;
    }
    
    
    

    // ============================================================
    // GET EMPLOYEE EXPENSES
    // ============================================================
    
    public function getEmployeeExpenses()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [];
        }
    
        $query = "
    
            SELECT
    
                id,
                employeeId,
                employeeName,
    
                expenseType,
    
                amount,
    
                invoiceNumber,
                invoiceImage,
    
                expenseDate,
    
                remark,
    
                expenseStatus,
    
                approvedBy,
                approvedAt,
    
                rejectedBy,
                rejectedAt,
    
                createdBy,
                createdAt
    
            FROM employeeExpenses
    
            WHERE employeeId = ?
    
            ORDER BY id DESC
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $expenses = [];
    
        while (
            $row = mysqli_fetch_assoc($result)
        ) {
    
            $expenses[] = [
    
                'id' =>
                    (int)$row['id'],
    
                'employeeId' =>
                    (int)$row['employeeId'],
    
                'employeeName' =>
                    $row['employeeName'] ?? '',
    
                'expenseType' =>
                    $row['expenseType'] ?? '',
    
                'amount' =>
                    (float)$row['amount'],
    
                'invoiceNumber' =>
                    $row['invoiceNumber'] ?? '',
    
                'invoiceImage' =>
                    $row['invoiceImage'] ?? '',
    
                'expenseDate' =>
                    $row['expenseDate'] ?? '',
    
                'remark' =>
                    $row['remark'] ?? '',
    
                'expenseStatus' =>
                    $row['expenseStatus'] ?? 'Pending',
    
                'approvedBy' =>
                    $row['approvedBy'] ?? '',
    
                'approvedAt' =>
                    $row['approvedAt'] ?? '',
    
                'rejectedBy' =>
                    $row['rejectedBy'] ?? '',
    
                'rejectedAt' =>
                    $row['rejectedAt'] ?? '',
    
                'createdBy' =>
                    $row['createdBy'] ?? '',
    
                'createdAt' =>
                    $row['createdAt'] ?? ''
            ];
        }
    
        mysqli_stmt_close($stmt);
    
        return $expenses;
    }
    
    // ============================================================
    // GET SINGLE EXPENSE
    // ============================================================
    
    public function getExpenseById(
        $expenseId
    ) {
    
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if (
            $employeeId <= 0 ||
            $expenseId <= 0
        ) {
    
            return null;
        }
    
        $query = "
    
            SELECT
    
                id,
                employeeId,
                employeeName,
    
                expenseType,
    
                amount,
    
                invoiceNumber,
                invoiceImage,
    
                expenseDate,
    
                remark,
    
                expenseStatus,
    
                createdAt
    
            FROM employeeExpenses
    
            WHERE
    
                id = ?
                AND employeeId = ?
    
            LIMIT 1
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return null;
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $expenseId,
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $expense =
            mysqli_fetch_assoc(
                $result
            );
    
        mysqli_stmt_close($stmt);
    
        return $expense ?: null;
    }
    
    // ============================================================
    // CHECK EXPENSE OWNERSHIP
    // ============================================================
    
    public function expenseExists(
        $expenseId
    ) {
    
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if (
            $employeeId <= 0 ||
            $expenseId <= 0
        ) {
    
            return false;
        }
    
        $query = "
    
            SELECT id
    
            FROM employeeExpenses
    
            WHERE
    
                id = ?
                AND employeeId = ?
    
            LIMIT 1
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return false;
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'ii',
            $expenseId,
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        mysqli_stmt_store_result($stmt);
    
        $exists =
            mysqli_stmt_num_rows($stmt) > 0;
    
        mysqli_stmt_close($stmt);
    
        return $exists;
    }
    
    // ============================================================
    // CHECK EDITABLE STATUS
    // ============================================================
    
    public function isExpenseEditable(
        $expenseId
    ) {
    
        $expense =
            $this->getExpenseById(
                $expenseId
            );
    
        if (!$expense) {
    
            return false;
        }
    
        return
            strtolower(
                $expense['expenseStatus']
            ) === 'pending';
    }
    
    // ============================================================
    // GET EXPENSE SUMMARY
    // ============================================================
    
    public function getExpenseSummary()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [
    
                'totalExpenses' => 0,
    
                'approvedExpenses' => 0,
    
                'pendingExpenses' => 0,
    
                'rejectedExpenses' => 0,
    
                'totalAmount' => 0,
    
                'approvedAmount' => 0,
    
                'pendingAmount' => 0,
    
                'rejectedAmount' => 0
            ];
        }
    
        $query = "
    
            SELECT
    
                COUNT(id) AS totalExpenses,
    
                SUM(
                    CASE
                        WHEN expenseStatus = 'Approved'
                        THEN 1
                        ELSE 0
                    END
                ) AS approvedExpenses,
    
                SUM(
                    CASE
                        WHEN expenseStatus = 'Pending'
                        THEN 1
                        ELSE 0
                    END
                ) AS pendingExpenses,
    
                SUM(
                    CASE
                        WHEN expenseStatus = 'Rejected'
                        THEN 1
                        ELSE 0
                    END
                ) AS rejectedExpenses,
    
                SUM(amount) AS totalAmount,
    
                SUM(
                    CASE
                        WHEN expenseStatus = 'Approved'
                        THEN amount
                        ELSE 0
                    END
                ) AS approvedAmount,
    
                SUM(
                    CASE
                        WHEN expenseStatus = 'Pending'
                        THEN amount
                        ELSE 0
                    END
                ) AS pendingAmount,
    
                SUM(
                    CASE
                        WHEN expenseStatus = 'Rejected'
                        THEN amount
                        ELSE 0
                    END
                ) AS rejectedAmount
    
            FROM employeeExpenses
    
            WHERE employeeId = ?
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $summary =
            mysqli_fetch_assoc(
                $result
            );
    
        mysqli_stmt_close($stmt);
    
        return [
    
            'totalExpenses' =>
                (int)(
                    $summary['totalExpenses'] ?? 0
                ),
    
            'approvedExpenses' =>
                (int)(
                    $summary['approvedExpenses'] ?? 0
                ),
    
            'pendingExpenses' =>
                (int)(
                    $summary['pendingExpenses'] ?? 0
                ),
    
            'rejectedExpenses' =>
                (int)(
                    $summary['rejectedExpenses'] ?? 0
                ),
    
            'totalAmount' =>
                (float)(
                    $summary['totalAmount'] ?? 0
                ),
    
            'approvedAmount' =>
                (float)(
                    $summary['approvedAmount'] ?? 0
                ),
    
            'pendingAmount' =>
                (float)(
                    $summary['pendingAmount'] ?? 0
                ),
    
            'rejectedAmount' =>
                (float)(
                    $summary['rejectedAmount'] ?? 0
                )
        ];
    }
    
    // ============================================================
    // GET PENDING EXPENSES
    // ============================================================
    
    public function getPendingExpenses()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [];
        }
    
        $query = "
    
            SELECT *
    
            FROM employeeExpenses
    
            WHERE
    
                employeeId = ?
                AND expenseStatus = 'Pending'
    
            ORDER BY id DESC
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $records = [];
    
        while (
            $row = mysqli_fetch_assoc($result)
        ) {
    
            $records[] = $row;
        }
    
        mysqli_stmt_close($stmt);
    
        return $records;
    }
    
    // ============================================================
    // GET APPROVED EXPENSES
    // ============================================================
    
    public function getApprovedExpenses()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [];
        }
    
        $query = "
    
            SELECT *
    
            FROM employeeExpenses
    
            WHERE
    
                employeeId = ?
                AND expenseStatus = 'Approved'
    
            ORDER BY id DESC
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $records = [];
    
        while (
            $row = mysqli_fetch_assoc($result)
        ) {
    
            $records[] = $row;
        }
    
        mysqli_stmt_close($stmt);
    
        return $records;
    }
    
    // ============================================================
    // GET REJECTED EXPENSES
    // ============================================================
    
    public function getRejectedExpenses()
    {
        $employeeId =
            $this->getCurrentEmployeeId();
    
        if ($employeeId <= 0) {
    
            return [];
        }
    
        $query = "
    
            SELECT *
    
            FROM employeeExpenses
    
            WHERE
    
                employeeId = ?
                AND expenseStatus = 'Rejected'
    
            ORDER BY id DESC
    
        ";
    
        $stmt =
            mysqli_prepare(
                $this->con,
                $query
            );
    
        if (!$stmt) {
    
            return [];
        }
    
        mysqli_stmt_bind_param(
            $stmt,
            'i',
            $employeeId
        );
    
        mysqli_stmt_execute($stmt);
    
        $result =
            mysqli_stmt_get_result(
                $stmt
            );
    
        $records = [];
    
        while (
            $row = mysqli_fetch_assoc($result)
        ) {
    
            $records[] = $row;
        }
    
        mysqli_stmt_close($stmt);
    
        return $records;
    }
}


