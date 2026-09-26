<?php
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/header.php'; 
    require_once __DIR__ . '/../includes/sidebar.php'; 
    require_once __DIR__ . '/../includes/AttendanceEngine.php'; 
    
    
    $attendanceEngine = new AttendanceEngine($con);
    $attendanceEngine->markAutoAbsent();
?>

<div class="main-content app-content">

    <div class="container-fluid">

        <!-- PAGE HEADER -->
        <div class="my-4 d-flex justify-content-between align-items-center">

            <div>

                <h1 class="page-title fw-medium fs-18 mb-2">
                    Attendance Management
                </h1>

            </div>

            <div class="d-flex gap-2">

                <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#attendanceAnalyticsModal" >
                    <i class="ri-line-chart-line me-1"></i>
                    Attendance Analytics
                </button>
                
                <button type="button" id="refreshAttendanceBtn" class="btn btn-primary">
                    <i class="ri-refresh-line me-1"></i>
                    Refresh
                </button>

            </div>

        </div>

        <!-- SUMMARY CARDS -->
        <div class="row">

            <div class="col-xl-3 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="mb-1 text-muted">
                                    Present Today
                                </p>

                                <h4
                                    class="fw-semibold mb-0"
                                    id="presentTodayCard"
                                >
                                    0
                                </h4>

                            </div>

                            <div class="avatar avatar-md bg-success-transparent">

                                <i class="ri-user-follow-line fs-20"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="mb-1 text-muted">
                                    Half Day
                                </p>

                                <h4
                                    class="fw-semibold mb-0"
                                    id="halfDayCard"
                                >
                                    0
                                </h4>

                            </div>

                            <div class="avatar avatar-md bg-warning-transparent">

                                <i class="ri-time-line fs-20"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="mb-1 text-muted">
                                    Absent
                                </p>

                                <h4
                                    class="fw-semibold mb-0"
                                    id="absentTodayCard"
                                >
                                    0
                                </h4>

                            </div>

                            <div class="avatar avatar-md bg-danger-transparent">

                                <i class="ri-user-unfollow-line fs-20"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="mb-1 text-muted">
                                    Currently Working
                                </p>

                                <h4
                                    class="fw-semibold mb-0"
                                    id="activeTodayCard"
                                >
                                    0
                                </h4>

                            </div>

                            <div class="avatar avatar-md bg-primary-transparent">

                                <i class="ri-timer-line fs-20"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- FILTERS -->
        <div class="row">

            <div class="col-xl-12">

                <div class="card custom-card">

                    <div class="card-body p-3">

                        <div class="d-flex align-items-center justify-content-between">

                            <div class="d-flex align-items-center gap-2">
                                
                                <div class="btn-group">

                                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
    
                                        Export
    
                                    </button>
    
                                    <ul class="dropdown-menu">
    
                                        <li>
    
                                            <a class="dropdown-item export-btn" data-type="csv" href="javascript:void(0);">
    
                                                CSV
    
                                            </a>
    
                                        </li>
    
                                        <li>
    
                                            <a class="dropdown-item export-btn" data-type="pdf" href="javascript:void(0);">
    
                                                PDF
    
                                            </a>
    
                                        </li>
    
                                    </ul>
    
                                </div>

                                <select
                                    id="employeeFilter"
                                    class="form-select form-select-lg"
                                >
                                    <option value="">
                                        All Employees
                                    </option>
                                </select>

                                <select
                                    id="statusFilter"
                                    class="form-select form-select-lg"
                                >
                                    <option value="">
                                        All Status
                                    </option>

                                    <option value="present">
                                        Present
                                    </option>

                                    <option value="half_day">
                                        Half Day
                                    </option>

                                    <option value="absent">
                                        Absent
                                    </option>

                                    <option value="in_progress">
                                        In Progress
                                    </option>

                                </select>

                                <input
                                    type="date"
                                    id="attendanceDateFilter"
                                    class="form-control"
                                >

                            </div>

                            <div class="flex-fill"></div>

                            <div>

                                <input
                                    type="text"
                                    id="attendanceTableSearch"
                                    class="form-control form-control-sm"
                                    placeholder="Search attendance..."
                                >

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- TABLE -->
        <div class="row">

            <div class="col-xl-12">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="table-responsive">

                            <table
                                id="attendanceTable"
                                data-ui-table="mamix"
                                class="table table-hover text-nowrap"
                            >

                                <thead>

                                    <tr>

                                        <th>SNo</th>

                                        <th>Employee</th>

                                        <th>Date</th>

                                        <th>Punch In</th>

                                        <th>Punch Out</th>

                                        <th>Working Hours</th>

                                        <th>Break Hours</th>

                                        <th>Status</th>

                                        <th>Actions</th>

                                    </tr>

                                </thead>

                                <tbody>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<!-- BREAK HISTORY MODAL -->
<div
    class="modal fade"
    id="attendanceBreakModal"
    tabindex="-1"
>
    <div class="modal-dialog modal-lg modal-dialog-centered">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Attendance Break History
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div class="modal-body">

                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead>

                            <tr>

                                <th>Break Type</th>

                                <th>Start Time</th>

                                <th>End Time</th>

                                <th>Duration</th>

                            </tr>

                        </thead>

                        <tbody
                            id="breakHistoryTableBody"
                        >

                        </tbody>

                    </table>

                </div>

            </div>

            <div class="modal-footer">

                <div
                    id="breakSummaryContainer"
                    class="w-100"
                ></div>

            </div>

        </div>

    </div>
</div>

<!-- EDIT ATTENDANCE MODAL -->
<div class="modal fade" id="editAttendanceModal" tabindex="-1">
    
    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <form id="editAttendanceForm">

                <input
                    type="hidden"
                    id="attendanceId"
                    name="attendanceId"
                >

                <div class="modal-header">

                    <h5 class="modal-title">
                        Edit Attendance
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>

                </div>

                <div class="modal-body">

                    <div class="row">
                
                        <div class="col-md-6 mb-3">
                
                            <label class="form-label">
                                Employee Name
                            </label>
                
                            <input
                                type="text"
                                id="employeeName"
                                class="form-control"
                                readonly
                            >
                
                        </div>
                
                        <div class="col-md-6 mb-3">
                
                            <label class="form-label">
                                Attendance Date
                            </label>
                
                            <input
                                type="date"
                                id="attendanceDate"
                                name="attendanceDate"
                                class="form-control"
                            >
                
                        </div>
                
                        <div class="col-md-6 mb-3">
                
                            <label class="form-label">
                                Punch In Time
                            </label>
                
                            <input
                                type="time"
                                id="punchInTime"
                                name="punchInTime"
                                class="form-control"
                                step="1"
                            >
                
                        </div>
                
                        <div class="col-md-6 mb-3">
                
                            <label class="form-label">
                                Punch Out Time
                            </label>
                
                            <input
                                type="time"
                                id="punchOutTime"
                                name="punchOutTime"
                                class="form-control"
                                step="1"
                            >
                
                        </div>
                
                        <div class="col-12 mb-3">
                
                            <label class="form-label">
                                Attendance Status
                            </label>
                
                            <select
                                id="attendanceStatus"
                                name="attendanceStatus"
                                class="form-select"
                            >
                                <option value="present">
                                    Present
                                </option>
                
                                <option value="half_day">
                                    Half Day
                                </option>
                
                                <option value="absent">
                                    Absent
                                </option>
                
                                <option value="in_progress">
                                    In Progress
                                </option>
                            </select>
                
                        </div>
                
                        <div class="col-12 mb-3">
                
                            <div class="alert alert-warning mb-0">
                
                                Changing Punch In or Punch Out time
                                will automatically recalculate
                                attendance hours.
                
                            </div>
                
                        </div>
                
                        <div class="col-12">
                
                            <label class="form-label">
                                Remarks
                            </label>
                
                            <textarea
                                id="remarks"
                                name="remarks"
                                class="form-control"
                                rows="3"
                            ></textarea>
                
                        </div>
                
                    </div>
                
                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-light"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="saveAttendanceBtn"
                    >
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>



<div class="modal fade" id="attendanceAnalyticsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Employee Attendance Analytics
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>

            <div
                class="modal-body"
            >

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="form-label">
                            Employee
                        </label>

                        <select
                            id="analyticsEmployeeId"
                            class="form-select"
                        >

                            <option value="">
                                Select Employee
                            </option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            From Date
                        </label>

                        <input
                            type="date"
                            id="analyticsFromDate"
                            class="form-control"
                        >

                    </div>

                    <div class="col-md-3 mb-3">

                        <label class="form-label">
                            To Date
                        </label>

                        <input
                            type="date"
                            id="analyticsToDate"
                            class="form-control"
                        >

                    </div>

                    <div
                        class="col-md-2 d-flex align-items-end mb-3"
                    >

                        <button
                            class="btn btn-primary w-100"
                            id="generateAnalyticsBtn"
                        >
                            Generate
                        </button>

                    </div>

                </div>

                <hr>

                <div class="mb-4">
                    <h6 class="mb-2">Daily Attendance Timeline</h6>
                    <div
                        id="attendanceTimelineChart"
                        style="height:440px; min-height:440px;"
                    >
                        <div class="text-center text-muted py-5">
                            Select an employee and date range to view attendance analytics.
                        </div>
                    </div>
                </div>

                <div
                    id="analyticsSummary"
                    class="row mb-4"
                ></div>

                <hr>

                <h6 class="mb-3">Daily Attendance Details</h6>

                <div class="table-responsive">
                    <table class="table table-hover text-nowrap">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Punch In</th>
                                <th>Punch Out</th>
                                <th>Break Start</th>
                                <th>Break End</th>
                                <th>Working Time</th>
                                <th>Break Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="analyticsDetailsBody"></tbody>
                    </table>
                </div>

            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>


<script src="<?= ASSET_URL ?>/assets/js/attendance-management.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
