<?php

include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';

?>

<div class="main-content app-content">

    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex justify-content-between align-items-center">

            <div>

                <h1 class="page-title fw-medium fs-18 mb-2">
                    Employee Attendance
                </h1>

                <ol class="breadcrumb mb-0">

                    <li class="breadcrumb-item">
                        <a href="dashboard">
                            Dashboard
                        </a>
                    </li>
                
                    <li class="breadcrumb-item">
                        Attendance
                    </li>
                
                    <li class="breadcrumb-item active">
                        Employee Attendance
                    </li>
                
                </ol>

            </div>

        </div>

        <!-- SUMMARY CARDS -->
        <div class="row">

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Present Days
                        </div>

                        <h4
                            class="fw-semibold text-success mb-0"
                            id="presentDaysCard"
                        >
                            0
                        </h4>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Half Days
                        </div>

                        <h4
                            class="fw-semibold text-warning mb-0"
                            id="halfDaysCard"
                        >
                            0
                        </h4>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Absent Days
                        </div>

                        <h4
                            class="fw-semibold text-danger mb-0"
                            id="absentDaysCard"
                        >
                            0
                        </h4>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-lg-6 col-md-6">

                <div class="card custom-card">

                    <div class="card-body">

                        <div class="fs-12 text-muted mb-1">
                            Total Working Hours
                        </div>

                        <h4
                            class="fw-semibold text-primary mb-0"
                            id="workingHoursCard"
                        >
                            00:00:00
                        </h4>

                    </div>

                </div>

            </div>

        </div>

        <!-- FILTER CARD -->
        <div class="row">

            <div class="col-xl-12">

                <div class="card custom-card">

                    <div class="card-body p-3">

                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

                            <!-- LEFT SIDE -->
                            <div class="d-flex align-items-center flex-wrap gap-3">
                        
                                <!-- Export -->
                                <div class="btn-group">
                        
                                    <button
                                        class="btn btn-outline-primary dropdown-toggle"
                                        data-bs-toggle="dropdown"
                                    >
                                        Export
                                    </button>
                        
                                    <ul class="dropdown-menu">
                        
                                        <li>
                                            <a
                                                class="dropdown-item export-btn"
                                                data-type="csv"
                                                href="javascript:void(0)"
                                            >
                                                CSV
                                            </a>
                                        </li>
                        
                                        <li>
                                            <a
                                                class="dropdown-item export-btn"
                                                data-type="pdf"
                                                href="javascript:void(0)"
                                            >
                                                PDF
                                            </a>
                                        </li>
                        
                                    </ul>
                        
                                </div>
                        
                                <!-- From Date -->
                                <input
                                    type="date"
                                    id="fromDate"
                                    class="form-control"
                                    style="width: 170px;"
                                >
                        
                                <!-- To Date -->
                                <input
                                    type="date"
                                    id="toDate"
                                    class="form-control"
                                    style="width: 170px;"
                                >
                        
                                <!-- Status -->
                                <select
                                    id="statusFilter"
                                    class="form-select"
                                    style="width: 180px;"
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
                        
                                </select>
                        
                            </div>
                        
                            <!-- RIGHT SIDE -->
                            <div>
                        
                                <input
                                    id="tableSearch"
                                    class="form-control"
                                    placeholder="Search..."
                                    style="width: 250px;"
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

                    <div class="card-header">

                        <div class="card-title">
                            Attendance History
                        </div>

                    </div>

                    <div class="card-body">

                        <div class="table-responsive">

                            <table
                                id="attendanceTable"
                                class="table table-hover text-nowrap"
                            >

                                <thead>

                                    <tr>

                                        <th>SNo</th>
                                        <th>Date</th>
                                        <th>Punch In</th>
                                        <th>Punch Out</th>
                                        <th>Working Hours</th>
                                        <th>Break Hours</th>
                                        <th>Status</th>
                                        <th>Action</th>

                                    </tr>

                                </thead>

                                <tbody id="attendanceTableBody">

                                    <tr>

                                        <td
                                            colspan="8"
                                            class="text-center text-muted"
                                        >
                                            No records found
                                        </td>

                                    </tr>

                                </tbody>

                            </table>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<div
    class="modal fade"
    id="attendanceBreakModal"
    tabindex="-1"
>

    <div class="modal-dialog modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">
                    Break History
                </h5>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                ></button>

            </div>
            
            <div class="modal-header">
                
                <div id="breakSummaryContainer" class="w-100"></div>
                
            </div>

            <div class="modal-body">

                <div class="table-responsive">

                    <table
                        class="table table-bordered"
                    >

                        <thead>

                            <tr>

                                <th>
                                    Break Type
                                </th>

                                <th>
                                    Start Time
                                </th>

                                <th>
                                    End Time
                                </th>

                                <th>
                                    Duration
                                </th>

                            </tr>

                        </thead>

                        <tbody
                            id="breakHistoryTableBody"
                        >

                        </tbody>

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
<script>
$(function() {

    // =========================================
    // API
    // =========================================

    const API = {

        getSummary:
            API_BASE +
            '/attendance/getEmployeeAttendanceSummary.php',

        getHistory:
            API_BASE +
            '/attendance/getEmployeeAttendanceHistory.php',
            
        getBreakHistory:
            API_BASE +
            '/attendance/getAttendanceBreakHistory.php'
    };

    // =========================================
    // DATATABLE
    // =========================================

    let table = null;
    
    let attendanceRows = [];

    // =========================================
    // INIT
    // =========================================

    loadSummary();

    loadAttendanceHistory();

    // =========================================
    // LOAD SUMMARY
    // =========================================

    function loadSummary()
    {
        $.getJSON(

            API.getSummary,

            function(response) {

                if (!response.success) {

                    return showToast(
                        'error',
                        response.message ||
                        'Failed to load summary'
                    );
                }

                renderSummaryCards(
                    response.data
                );
            }

        ).fail(function() {

            showToast(
                'error',
                'Failed to load attendance summary'
            );

        });
    }
    
    
    function formatDate(date)
    {
        return new Date(date)
            .toLocaleDateString(
                'en-IN',
                {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric'
                }
            );
    }
    
    
    function formatTime(time)
    {
        if (!time) {
            return '--';
        }
    
        const date =
            new Date(
                '1970-01-01T' + time
            );
    
        return date.toLocaleTimeString(
            'en-IN',
            {
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            }
        );
    }
    
    function formatHours(seconds)
    {
        seconds =
            parseInt(seconds || 0);
    
        if (seconds < 60) {
    
            return seconds + ' Sec';
        }
    
        if (seconds < 3600) {
    
            return Math.floor(
                seconds / 60
            ) + ' Min';
        }
    
        const hours =
            Math.floor(
                seconds / 3600
            );
    
        const minutes =
            Math.floor(
                (seconds % 3600) / 60
            );
    
        return (
            hours +
            'h ' +
            minutes +
            'm'
        );
    }
    
    
  function renderBreakSummary(summary)
{
    let html = `
        <div class="row g-2">
    `;

    Object.entries(summary).forEach(
        function([name, seconds])
        {
            html += `
                <div class="col-md-6">

                    <div
                        class="border rounded p-3 bg-light"
                    >

                        <div class="text-muted small">
                            ${name}
                        </div>

                        <div class="fw-bold fs-5">
                            ${formatHours(seconds)}
                        </div>

                    </div>

                </div>
            `;
        }
    );

    html += `
        </div>
    `;

    $('#breakSummaryContainer')
        .html(html);
}
    
    // =========================================
    // LOAD HISTORY
    // =========================================

    function loadAttendanceHistory()
    {
        $.getJSON(

            API.getHistory,

            function(response) {

                if (!response.success) {

                    return showToast(
                        'error',
                        response.message ||
                        'Failed to load attendance'
                    );
                }

                renderAttendanceTable(
                    response.data || []
                );
            }

        ).fail(function() {

            showToast(
                'error',
                'Failed to load attendance history'
            );

        });
    }

    // =========================================
    // SUMMARY CARDS
    // =========================================

    function renderSummaryCards(data)
    {
        $('#presentDaysCard').text(
            data.presentDays || 0
        );

        $('#halfDaysCard').text(
            data.halfDays || 0
        );

        $('#workingHoursCard').text(
            formatDuration(
                data.workingSeconds || 0
            )
        );

        $('#breakHoursCard').text(
            formatDuration(
                data.breakSeconds || 0
            )
        );
    }

    // =========================================
    // FORMAT DURATION
    // =========================================

    function formatDuration(seconds)
    {
        seconds =
            parseInt(
                seconds || 0
            );

        const hours =
            String(
                Math.floor(
                    seconds / 3600
                )
            ).padStart(2, '0');

        const minutes =
            String(
                Math.floor(
                    (seconds % 3600) / 60
                )
            ).padStart(2, '0');

        const secs =
            String(
                seconds % 60
            ).padStart(2, '0');

        return (
            hours +
            ':' +
            minutes +
            ':' +
            secs
        );
    }

    // =========================================
    // STATUS BADGE
    // =========================================

    function getStatusBadge(status)
    {
        status =
            (status || '')
            .toLowerCase();

        if (
            status === 'present'
        ) {

            return `
                <span class="btn btn-outline-success btn-sm">
                    Present
                </span>
            `;
        }

        if (
            status === 'half_day'
        ) {

            return `
                <span class="btn btn-outline-warning btn-sm">
                    Half Day
                </span>
            `;
        }

        if (
            status === 'absent'
        ) {

            return `
                <span class="btn btn-outline-danger btn-sm">
                    Absent
                </span>
            `;
        }

        if (
            status === 'in_progress'
        ) {

            return `
                <span class="btn btn-outline-primary btn-sm">
                    In Progress
                </span>
            `;
        }

        return `
            <span class="btn btn-outline-secondary btn-sm">
                ${status || '--'}
            </span>
        `;
    }

    // =========================================
    // TABLE
    // =========================================

    
    function renderAttendanceTable(rows)
    {
        attendanceRows = rows;
        
        let html = '';

        if (!rows.length) {

            html = `
                <tr>

                    <td
                        colspan="8"
                        class="text-center text-muted"
                    >

                        No attendance found

                    </td>

                </tr>
            `;
        }
        else {

            rows.forEach(function(row, index) {

                html += `
                    <tr>

                        <td>

                            ${index + 1}

                        </td>

                        <td>
                            ${formatDate(
                                row.attendanceDate
                            )}
                        </td>
                        
                        <td>
                            ${formatTime(
                                row.punchInTime
                            )}
                        </td>
                        
                        <td>
                            ${
                                row.punchOutTime
                                    ? formatTime(
                                        row.punchOutTime
                                    )
                                    : '--'
                            }
                        </td>
                        
                        <td>
                            ${formatHours(
                                row.totalWorkingSeconds
                            )}
                        </td>
                        
                        <td>
                            ${formatHours(
                                row.totalBreakSeconds
                            )}
                        </td>

                        <td>

                            ${getStatusBadge(
                                row.attendanceStatus
                            )}

                        </td>

                        <td>

                            <button
                                class="btn btn-outline-primary btn-sm view-breaks"
                                data-id="${row.id}"
                            >

                                View

                            </button>

                        </td>

                    </tr>
                `;
            });
        }

        // =====================================
        // DESTROY EXISTING
        // =====================================

        if (
            $.fn.DataTable.isDataTable(
                '#attendanceTable'
            )
        ) {

            table.destroy();
        }

        // =====================================
        // UPDATE BODY
        // =====================================

        $('#attendanceTableBody')
            .html(html);

        // =====================================
        // REINIT TABLE
        // =====================================

        table =
            $('#attendanceTable')
            .DataTable({

                drawCallback: function() {

                    let api =
                        this.api();

                    api.column(
                        0,
                        {
                            search: 'applied',
                            order: 'applied'
                        }
                    ).nodes().each(function(
                        cell,
                        i
                    ) {

                        cell.innerHTML =
                            i + 1;

                    });
                },

                order: [],

                pageLength: 10,

                dom:
                    "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",

                columnDefs: [
                    {
                        targets: [0],
                        orderable: false,
                        searchable: false
                    }
                ]
            });
    }
    
    
    // =========================================
    // VIEW BREAKS
    // =========================================
    
    $(document).on(
    
        'click',
    
        '.view-breaks',
    
        function()
        {
            const attendanceId =
                $(this).data('id');
    
            loadBreakHistory(
                attendanceId
            );
        }
    );
    
    function loadBreakHistory(
        attendanceId
    )
    {
        
        console.log(
            'Attendance ID:',
            attendanceId
        );
        
        $.getJSON(
    
            API.getBreakHistory,
    
            {
                attendanceId:
                    attendanceId
            },
    
            function(response)
            {
                if (
                    !response.success
                ) {
    
                    return showToast(
                        'error',
                        response.message
                    );
                }
    
                
                
                renderBreakSummary(
                    response.summary || {}
                );
                
                renderBreakHistory(
                    response.data || []
                );
    
                $('#attendanceBreakModal')
                    .modal('show');
            }
        );
    }
    
    function renderBreakHistory(rows)
    {
        let html = '';
    
        if (!rows.length) {
    
            html = `
                <tr>
    
                    <td
                        colspan="4"
                        class="text-center text-muted"
                    >
                        No break records found
                    </td>
    
                </tr>
            `;
        }
        else {
    
            rows.forEach(function(row) {
    
                html += `
                    <tr>
    
                        <td>
    
                            ${row.breakName}
    
                        </td>
    
                        <td>
    
                            ${formatTime(
                                row.breakStartTime
                            )}
    
                        </td>
    
                        <td>
    
                            ${
                                row.breakEndTime
                                    ? formatTime(
                                        row.breakEndTime
                                    )
                                    : '--'
                            }
    
                        </td>
    
                        <td>
    
                            ${formatHours(
                                row.breakDurationSeconds
                            )}
    
                        </td>
    
                    </tr>
                `;
            });
        }
    
        $('#breakHistoryTableBody')
            .html(html);
    }
    

    // =========================================
    // SEARCH
    // =========================================

    $('#tableSearch').on(
        'keyup',
        function() {

            if (table) {

                table.search(
                    this.value
                ).draw();
            }
        }
    );

    // =========================================
    // STATUS FILTER
    // =========================================

    $('#statusFilter').on(
        'change',
        function() {
    
            if (!table) {
                return;
            }
    
            let value =
                $(this).val();
    
            if (
                value === 'half_day'
            ) {
    
                value = 'Half Day';
            }
            else if (
                value === 'present'
            ) {
    
                value = 'Present';
            }
            else if (
                value === 'absent'
            ) {
    
                value = 'Absent';
            }
            else if (
                value === 'in_progress'
            ) {
    
                value = 'In Progress';
            }
    
            table
                .column(6)
                .search(value)
                .draw();
        }
    );
    
    
    // =========================================
// DATE FILTER
// =========================================

$.fn.dataTable.ext.search.push(

    function(settings, data, dataIndex)
    {
        const fromDate =
            $('#fromDate').val();

        const toDate =
            $('#toDate').val();

        if (
            !fromDate &&
            !toDate
        ) {
            return true;
        }

        const row =
            attendanceRows[dataIndex];

        if (!row) {
            return true;
        }

        const attendanceDate =
            row.attendanceDate;

        if (
            fromDate &&
            attendanceDate < fromDate
        ) {
            return false;
        }

        if (
            toDate &&
            attendanceDate > toDate
        ) {
            return false;
        }

        return true;
    }
);


$('#fromDate, #toDate').on(
    'change',
    function()
    {
        if (table) {

            table.draw();
        }
    }
);

});
</script>
<?php
include __DIR__ . '/../includes/emp-footer.php';
?>