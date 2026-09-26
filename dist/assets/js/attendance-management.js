
// =========================================
// API
// =========================================

const API = {

    getSummary:
        API_BASE +
        '/attendance/getAttendanceManagementSummary.php',

    getListing:
        API_BASE +
        '/attendance/getAttendanceManagementListing.php',

    getBreakHistory:
        API_BASE +
        '/attendance/getAttendanceBreakHistoryAdmin.php',

    getAttendanceDetails:
        API_BASE +
        '/attendance/getAttendanceDetails.php',

    updateAttendance:
        API_BASE +
        '/attendance/updateAttendance.php',
        
    getAttendanceAnalytics:
        API_BASE +
        '/attendance/getEmployeeAttendanceAnalytics.php',

    punchOutForgottenAttendance:
        API_BASE +
        '/attendance/punchOutForgottenAttendance.php'
};

// =========================================
// GLOBALS
// =========================================

let attendanceTable = null;

let attendanceRows = [];



// =========================================
// FILTER VALUES
// =========================================

let selectedEmployee = '';

let selectedStatus = '';

let selectedDate = '';





// =========================================
// INIT
// =========================================

$(function() {

    loadAttendanceSummary();

    loadAttendanceListing();

});

// =========================================
// LOAD SUMMARY
// =========================================

function loadAttendanceSummary()
{
    $.getJSON(

        API.getSummary,

        {
            date: selectedDate || ''
        },

        function(response)
        {
            if (!response.success) {

                return showToast(
                    'error',
                    response.message
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


$(function () {

    // Set today's date in YYYY-MM-DD format
    selectedDate = new Date().toISOString().split('T')[0];

    // Set the date picker value
    $('#attendanceDateFilter').val(selectedDate);

    loadAttendanceSummary();

    loadAttendanceListing();
});


// =========================================
// LOAD ATTENDANCE LISTING
// =========================================

function loadAttendanceListing()
{
    $.getJSON(

        API.getListing,

        {
            date: selectedDate || ''
        },

        function(response)
        {
            if (!response.success) {

                return showToast(
                    'error',
                    response.message
                );
            }

            attendanceRows =
                response.data || [];

            populateEmployeeFilter(
                attendanceRows
            );

            renderAttendanceTable(
                attendanceRows
            );
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load attendance records'
        );

    });
}
// =========================================
// SUMMARY CARDS
// =========================================

function renderSummaryCards(data)
{
    $('#presentTodayCard').text(
        data.presentToday || 0
    );

    $('#halfDayCard').text(
        data.halfDay || 0
    );

    $('#absentTodayCard').text(
        data.absent || 0
    );

    $('#activeTodayCard').text(
        data.activeToday || 0
    );
}

// =========================================
// EMPLOYEE FILTER
// =========================================

function populateEmployeeFilter(rows)
{
    const employees = {};

    rows.forEach(function(row) {

        employees[
            row.employeeId
        ] = row.fullName;
    });

    let html = `
        <option value="">
            All Employees
        </option>
    `;
    
    let analyticsOptions =
        '<option value="">Select Employee</option>';

    Object.entries(employees)
    .forEach(function(item) {

        const option = `
            <option value="${item[0]}">
                ${item[1]}
            </option>
        `;

        html += option;

        analyticsOptions += option;
    });

    $('#employeeFilter')
        .html(html);
        
    $('#analyticsEmployeeId')
    .html(
        analyticsOptions
    );
}

// =========================================
// TABLE
// =========================================

function renderAttendanceTable(rows)
{
    let html = '';

    if (!rows.length) {

        html = `
            <tr>

                <td
                    colspan="9"
                    class="text-center text-muted"
                >

                    No attendance records found

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

                        <div>

                            <div
                                class="fw-semibold"
                            >
                                ${row.fullName || '--'}
                            </div>

                            <div
                                class="small text-muted"
                            >
                                ${row.employeeCode || '--'}
                            </div>

                            <div
                                class="small text-muted"
                            >
                                ${row.designationName || '--'}
                            </div>

                        </div>

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
                        ${formatDuration(
                            row.totalWorkingSeconds
                        )}
                    </td>

                    <td>
                        ${formatDuration(
                            row.totalBreakSeconds
                        )}
                    </td>

                    <td>
                        ${getStatusBadge(
                            row.attendanceStatus
                        )}
                    </td>

                    <td>

                        <div
                            class="btn-list"
                        >

                            <button
                                class="btn btn-sm btn-outline-primary view-breaks"
                                data-id="${row.id}"
                            >
                                Breaks
                            </button>

                            <button
                                class="btn btn-sm btn-outline-warning edit-attendance"
                                data-id="${row.id}"
                            >
                                Edit
                            </button>

                        </div>

                    </td>

                </tr>
            `;
        });
    }

    if (
        $.fn.DataTable.isDataTable(
            '#attendanceTable'
        )
    ) {

        attendanceTable.destroy();
    }

    $('#attendanceTable tbody')
        .html(html);

    attendanceTable =
        $('#attendanceTable')
        .DataTable({

            order: [],

            pageLength: 10,

            drawCallback: function() {

                let api =
                    this.api();

                api.column(
                    0,
                    {
                        search: 'applied',
                        order: 'applied'
                    }
                )
                .nodes()
                .each(function(
                    cell,
                    i
                ) {

                    cell.innerHTML =
                        i + 1;

                });
            },

            dom:
                "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",

            columnDefs: [

                {
                    targets: [0],

                    searchable: false,

                    orderable: false
                }
            ]
        });
        
        
        
        // =========================================
    // CUSTOM FILTERS
    // =========================================
    
    $.fn.dataTable.ext.search = [];
    
    
    
    $.fn.dataTable.ext.search.push(
    
        function(settings, data, dataIndex)
        {
            const row =
                attendanceRows[dataIndex];
    
            if (!row) {
                return true;
            }
    
            // Employee Filter
            if (
                selectedEmployee &&
                row.employeeId != selectedEmployee
            ) {
                return false;
            }
    
            // Status Filter
            if (
                selectedStatus &&
                row.attendanceStatus !== selectedStatus
            ) {
                return false;
            }
    
            // Date Filter
            if (
                selectedDate &&
                row.attendanceDate !== selectedDate
            ) {
                return false;
            }
    
            return true;
        }
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

    switch (status) {

        case 'present':

            return `
                <span
                    class="badge bg-success"
                >
                    Present
                </span>
            `;

        case 'half_day':

            return `
                <span
                    class="badge bg-warning"
                >
                    Half Day
                </span>
            `;

        case 'absent':

            return `
                <span
                    class="badge bg-danger"
                >
                    Absent
                </span>
            `;

        case 'in_progress':

            return `
                <span
                    class="badge bg-primary"
                >
                    In Progress
                </span>
            `;

        default:

            return `
                <span
                    class="badge bg-secondary"
                >
                    ${status}
                </span>
            `;
    }
}

// =========================================
// DATE
// =========================================

function formatDate(date)
{
    if (!date) {

        return '--';
    }

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

// =========================================
// TIME
// =========================================

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

// =========================================
// DURATION
// =========================================

function formatDuration(seconds)
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

// =========================================
// SEARCH
// =========================================

$('#attendanceTableSearch').on(
    'keyup',
    function()
    {
        if (
            attendanceTable
        ) {

            attendanceTable
                .search(
                    this.value
                )
                .draw();
        }
    }
);

// =========================================
// STATUS FILTER
// =========================================

$('#statusFilter').on(
    'change',
    function()
    {
        selectedStatus =
            $(this).val();

        if (attendanceTable) {

            attendanceTable.draw();
        }
    }
);

// =========================================
// EMPLOYEE FILTER
// =========================================

$('#employeeFilter').on(
    'change',
    function()
    {
        selectedEmployee =
            $(this).val();

        if (attendanceTable) {

            attendanceTable.draw();
        }
    }
);

// =========================================
// DATE FILTER
// =========================================

$('#attendanceDateFilter').on(
    'change',
    function()
    {
        selectedDate = this.value;

        loadAttendanceSummary();

        loadAttendanceListing();
    }
);

// =========================================
// REFRESH
// =========================================

$('#refreshAttendanceBtn').on(
    'click',
    function()
    {
        loadAttendanceSummary();

        loadAttendanceListing();

        showToast(
            'success',
            'Attendance refreshed'
        );
    }
);


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

// =========================================
// LOAD BREAK HISTORY
// =========================================

function loadBreakHistory(
    attendanceId
)
{
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

            renderBreakHistory(
                response.data || []
            );

            renderBreakSummary(
                response.summary || {}
            );

            $('#attendanceBreakModal')
                .modal('show');
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load break history'
        );

    });
}

// =========================================
// BREAK HISTORY
// =========================================

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
                        ${formatDuration(
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
// BREAK SUMMARY
// =========================================

function renderBreakSummary(summary)
{
    let html = '';

    Object.entries(summary)
        .forEach(function(
            [name, seconds]
        ) {

            html += `
                <div
                    class="d-flex justify-content-between
                           align-items-center
                           border rounded
                           px-3 py-2 mb-2"
                >

                    <span
                        class="fw-semibold"
                    >
                        ${name}
                    </span>

                    <span
                        class="badge bg-success"
                    >
                        ${formatDuration(
                            seconds
                        )}
                    </span>

                </div>
            `;
        });

    $('#breakSummaryContainer')
        .html(html);
}

$(document).on(
    'click',
    '.edit-attendance',
    function() {

        loadAttendanceDetails(
            $(this).data('id')
        );
    }
);

// =========================================
// LOAD ATTENDANCE DETAILS
// =========================================

function loadAttendanceDetails(
    attendanceId
)
{
    $.getJSON(

        API.getAttendanceDetails,

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

            populateAttendanceModal(
                response.data
            );

            $('#editAttendanceModal')
                .modal('show');
        }

    ).fail(function() {

        showToast(
            'error',
            'Failed to load attendance details'
        );

    });
}

// =========================================
// POPULATE ATTENDANCE MODAL
// =========================================

function populateAttendanceModal(data)
{
    $('#attendanceId').val(
        data.id || ''
    );

    $('#employeeName').val(
        data.fullName || ''
    );

    $('#attendanceDate').val(
        data.attendanceDate || ''
    );

    $('#punchInTime').val(
        data.punchInTime || ''
    );

    $('#punchOutTime').val(
        data.punchOutTime || ''
    );

    $('#attendanceStatus').val(
        data.attendanceStatus || ''
    );

    $('#remarks').val(
        data.remarks || ''
    );
}

// =========================================
// UPDATE ATTENDANCE
// =========================================

$('#editAttendanceForm').on(
    'submit',
    function(e)
    {
        e.preventDefault();

        submitAttendanceUpdate();
    }
);

function submitAttendanceUpdate()
{
    $.ajax({

        url:
            API.updateAttendance,

        type: 'POST',

        dataType: 'json',

        data:
            $('#editAttendanceForm')
            .serialize(),

        success: function(response)
        {
            if (!response.success) {

                return showToast(
                    'error',
                    response.message
                );
            }

            showToast(
                'success',
                response.message
            );

            bootstrap.Modal
                .getInstance(
                    document.getElementById(
                        'editAttendanceModal'
                    )
                )
                ?.hide();

            loadAttendanceListing();

            loadAttendanceSummary();
        },

        error: function()
        {
            showToast(
                'error',
                'Failed to update attendance'
            );
        }
    });
}


let attendanceTimelineChart = null;

const ATTENDANCE_TIMELINE_SERIES = [
    { key: 'punchInTime', name: 'Punch In', color: '#198754' },
    { key: 'lunchStartTime', name: 'Lunch Break Start', color: '#f59e0b' },
    { key: 'lunchEndTime', name: 'Lunch Break End', color: '#dc3545' },
    { key: 'teaStartTime', name: 'Tea Break Start', color: '#7c3aed' },
    { key: 'teaEndTime', name: 'Tea Break End', color: '#0891b2' },
    { key: 'punchOutTime', name: 'Punch Out', color: '#0d6efd' }
];

const ATTENDANCE_REFERENCE_TIMES = [
    { value: 630, label: 'Office In' },
    { value: 840, label: 'Lunch Start' },
    { value: 885, label: 'Lunch End' },
    { value: 1020, label: 'Tea Start' },
    { value: 1035, label: 'Tea End' },
    { value: 1140, label: 'Office Out' }
];

// "7h 30m" / "8h 00m" / "30m" -- distinct from formatDuration() above
// (which is used by the main attendance table/history and intentionally
// renders short durations as "N Min"/"N Sec"); the Analytics modal's own
// spec calls for this exact "Xh Ym" style everywhere, including for
// sub-hour break durations.
function formatHoursMinutes(seconds)
{
    const totalMinutes = Math.round((seconds || 0) / 60);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;

    if (h > 0) {
        return h + 'h ' + String(m).padStart(2, '0') + 'm';
    }

    return m + 'm';
}

$('#generateAnalyticsBtn').on(

    'click',

    function()
    {
        loadAttendanceAnalytics();
    }
);

function loadAttendanceAnalytics()
{
    const employeeId = $('#analyticsEmployeeId').val();
    const fromDate = $('#analyticsFromDate').val();
    const toDate = $('#analyticsToDate').val();

    if (!employeeId || !fromDate || !toDate) {
        showToast('warning', 'Please select an employee and date range');
        return;
    }

    destroyAttendanceTimelineChart();
    $('#attendanceTimelineChart').html(
        '<div class="text-center text-muted py-5">Loading attendance analytics...</div>'
    );
    $('#analyticsSummary').empty();
    $('#analyticsDetailsBody').html(
        '<tr><td colspan="8" class="text-center text-muted">Loading attendance analytics...</td></tr>'
    );

    $.getJSON(
        API.getAttendanceAnalytics,
        {
            employeeId: employeeId,
            fromDate: fromDate,
            toDate: toDate
        },
        function(response)
        {
            if (!response.success) {
                $('#attendanceTimelineChart').html(
                    '<div class="text-center text-danger py-5">Unable to load attendance analytics.</div>'
                );
                return showToast('error', response.message);
            }

            renderAttendanceAnalytics(response);
        }
    ).fail(function() {
        $('#attendanceTimelineChart').html(
            '<div class="text-center text-danger py-5">Unable to load attendance analytics.</div>'
        );
        showToast('error', 'Failed to load attendance analytics');
    });
}

function destroyAttendanceTimelineChart()
{
    if (attendanceTimelineChart) {
        attendanceTimelineChart.destroy();
        attendanceTimelineChart = null;
    }
}

function timeToMinutes(time)
{
    if (!time || typeof time !== 'string') {
        return null;
    }

    const parts = time.split(':');
    const hours = Number(parts[0]);
    const minutes = Number(parts[1]);

    if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
        return null;
    }

    return (hours * 60) + minutes;
}

function formatMinutesToTime(value)
{
    if (value === null || value === undefined || !Number.isFinite(Number(value))) {
        return '--';
    }

    const totalMinutes = Math.round(Number(value));
    const hours24 = Math.floor(totalMinutes / 60) % 24;
    const minutes = totalMinutes % 60;
    const period = hours24 >= 12 ? 'PM' : 'AM';
    const hours12 = hours24 % 12 || 12;

    return hours12 + ':' + String(minutes).padStart(2, '0') + ' ' + period;
}

function getAttendanceTimelineRow(row)
{
    const timelineRow = {
        attendanceDate: row.attendanceDate,
        punchInTime: row.punchInTime || null,
        lunchStartTime: null,
        lunchEndTime: null,
        teaStartTime: null,
        teaEndTime: null,
        punchOutTime: row.punchOutTime || null
    };

    (row.breaks || []).forEach(function(breakRow) {
        const breakName = String(breakRow.breakName || '').toLowerCase();

        if (breakName.includes('lunch') && !timelineRow.lunchStartTime) {
            timelineRow.lunchStartTime = breakRow.startTime || null;
            timelineRow.lunchEndTime = breakRow.endTime || null;
        } else if (breakName.includes('tea') && !timelineRow.teaStartTime) {
            timelineRow.teaStartTime = breakRow.startTime || null;
            timelineRow.teaEndTime = breakRow.endTime || null;
        }
    });

    return timelineRow;
}

function buildAttendanceTimelineChart(rows)
{
    // Every date of the selected range (up to today) gets an x-axis slot,
    // so days with no attendance row still show up as Absent below.
    const rowsByDate = {};
    rows.forEach(function(row) {
        rowsByDate[row.attendanceDate] = row;
    });

    const today = new Date().toLocaleDateString('en-CA');
    const rangeEnd = $('#analyticsToDate').val() < today ? $('#analyticsToDate').val() : today;
    const rangeCursor = new Date($('#analyticsFromDate').val() + 'T00:00:00');

    while (rangeCursor.toLocaleDateString('en-CA') <= rangeEnd) {
        const date = rangeCursor.toLocaleDateString('en-CA');
        if (!rowsByDate[date]) {
            rowsByDate[date] = { attendanceDate: date };
        }
        rangeCursor.setDate(rangeCursor.getDate() + 1);
    }

    const timelineRows = Object.keys(rowsByDate)
        .sort()
        .map(function(date) {
            return getAttendanceTimelineRow(rowsByDate[date]);
        });

    const dates = timelineRows.map(function(row) {
        return row.attendanceDate;
    });

    const series = ATTENDANCE_TIMELINE_SERIES.map(function(seriesDefinition) {
        return {
            name: seriesDefinition.name,
            data: timelineRows.map(function(row) {
                return timeToMinutes(row[seriesDefinition.key]);
            })
        };
    });

    const plottedValues = series.flatMap(function(item) {
        return item.data.filter(function(value) {
            return value !== null;
        });
    });
    const referenceValues = ATTENDANCE_REFERENCE_TIMES.map(function(item) {
        return item.value;
    });
    const allValues = plottedValues.concat(referenceValues);
    const minimum = Math.floor((Math.min.apply(null, allValues) - 30) / 30) * 30;
    const maximum = Math.ceil((Math.max.apply(null, allValues) + 30) / 30) * 30;

    // Absent (no punch in): red point on the chart's bottom edge. The red
    // dashed "Absent" line also runs from the neighbouring days' Punch In
    // down to those points, so the timeline stays unbroken across absences.
    const isAbsent = timelineRows.map(function(row) {
        return !row.punchInTime;
    });
    const absentData = timelineRows.map(function(row, index) {
        if (isAbsent[index]) {
            return minimum;
        }

        return isAbsent[index - 1] || isAbsent[index + 1]
            ? timeToMinutes(row.punchInTime)
            : null;
    });
    series.push({ name: 'Absent', data: absentData });

    const absentMarkers = [];
    isAbsent.forEach(function(absent, index) {
        if (absent) {
            absentMarkers.push({
                seriesIndex: series.length - 1,
                dataPointIndex: index,
                fillColor: '#ff0000',
                strokeColor: '#ffffff',
                size: 7
            });
        }
    });

    return new ApexCharts(document.querySelector('#attendanceTimelineChart'), {
        chart: {
            type: 'line',
            height: 440,
            toolbar: { show: false },
            zoom: { enabled: false }
        },
        series: series,
        colors: ATTENDANCE_TIMELINE_SERIES.map(function(item) {
            return item.color;
        }).concat(['#ff0000']),
        stroke: {
            curve: ATTENDANCE_TIMELINE_SERIES.map(function() {
                return 'smooth';
            }).concat(['straight']),
            width: ATTENDANCE_TIMELINE_SERIES.map(function() {
                return 3;
            }).concat([2]),
            dashArray: ATTENDANCE_TIMELINE_SERIES.map(function() {
                return 0;
            }).concat([5]),
            connectNulls: false
        },
        markers: {
            size: ATTENDANCE_TIMELINE_SERIES.map(function() {
                return 4;
            }).concat([0]),
            discrete: absentMarkers,
            strokeWidth: 1,
            hover: { size: 6 }
        },
        dataLabels: { enabled: false },
        legend: {
            show: true,
            position: 'top',
            horizontalAlign: 'center',
            markers: { width: 10, height: 10, radius: 10 }
        },
        grid: {
            borderColor: '#e9ecef',
            strokeDashArray: 4,
            xaxis: { lines: { show: false } }
        },
        xaxis: {
            categories: dates,
            labels: {
                rotate: -45,
                hideOverlappingLabels: true,
                formatter: function(value) {
                    if (!value) {
                        return '';
                    }

                    return new Date(value + 'T00:00:00').toLocaleDateString('en-IN', {
                        day: '2-digit',
                        month: 'short'
                    });
                }
            }
        },
        yaxis: {
            min: minimum,
            max: maximum,
            reversed: false,
            tickAmount: Math.min(10, Math.max(4, Math.round((maximum - minimum) / 60))),
            labels: {
                formatter: formatMinutesToTime
            }
        },
        tooltip: {
            shared: true,
            intersect: false,
            custom: function({ series: tooltipSeries, dataPointIndex }) {
                const date = dates[dataPointIndex];
                const dateLabel = date
                    ? new Date(date + 'T00:00:00').toLocaleDateString('en-IN', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    })
                    : '';
                let content = '<div class="apexcharts-tooltip-title">' + dateLabel + '</div>';

                ATTENDANCE_TIMELINE_SERIES.forEach(function(item, index) {
                    const value = tooltipSeries[index]
                        ? tooltipSeries[index][dataPointIndex]
                        : null;
                    content +=
                        '<div class="apexcharts-tooltip-series-group apexcharts-active" style="display:flex;">' +
                        '<span class="apexcharts-tooltip-marker" style="background-color:' + item.color + '"></span>' +
                        '<div class="apexcharts-tooltip-text"><div class="apexcharts-tooltip-y-group">' +
                        '<span class="apexcharts-tooltip-text-y-label">' + item.name + ': </span>' +
                        '<span class="apexcharts-tooltip-text-y-value">' + formatMinutesToTime(value) + '</span>' +
                        '</div></div></div>';
                });

                if (isAbsent[dataPointIndex]) {
                    content += '<div class="text-danger fw-semibold px-2 pb-1">Absent</div>';
                }

                return content;
            }
        },
        annotations: {
            yaxis: ATTENDANCE_REFERENCE_TIMES.map(function(reference) {
                return {
                    y: reference.value,
                    borderColor: '#adb5bd',
                    strokeDashArray: 5,
                    label: {
                        text: reference.label + ' ' + formatMinutesToTime(reference.value),
                        position: 'left',
                        offsetX: 4,
                        style: {
                            color: '#495057',
                            background: '#f8f9fa',
                            fontSize: '10px',
                            fontWeight: 500
                        }
                    }
                };
            })
        },
        noData: {
            text: 'No attendance data available for this period.'
        },
        responsive: [
            {
                breakpoint: 768,
                options: {
                    chart: { height: 400 },
                    legend: { position: 'bottom' },
                    xaxis: { labels: { rotate: -45 } }
                }
            }
        ]
    });
}

function renderAnalyticsDetailsTable(rows)
{
    if (!rows.length) {

        $('#analyticsDetailsBody').html(
            '<tr><td colspan="8" class="text-center text-muted">No attendance records in this date range</td></tr>'
        );

        return;
    }

    let html = '';

    rows.forEach(function(row) {

        html += `
            <tr>
                <td>${formatDate(row.attendanceDate)}</td>
                <td>${formatTime(row.punchInTime)}</td>
                <td>${row.punchOutTime ? formatTime(row.punchOutTime) : '--'}</td>
                <td>${row.breakStartTime ? formatTime(row.breakStartTime) : '--'}</td>
                <td>${row.breakEndTime ? formatTime(row.breakEndTime) : '--'}</td>
                <td>${formatHoursMinutes(row.totalWorkingSeconds)}</td>
                <td>${formatHoursMinutes(row.totalBreakSeconds)}</td>
                <td>${getAnalyticsStatusCell(row)}</td>
            </tr>
        `;
    });

    $('#analyticsDetailsBody').html(html);
}

// 'In Progress' with no punch out = employee forgot to punch out; the admin
// can click it to punch out at 07:00 PM (AttendanceEngine calculates the rest).
function getAnalyticsStatusCell(row)
{
    if (row.attendanceStatus !== 'in_progress' || row.punchOutTime) {
        return getStatusBadge(row.attendanceStatus);
    }

    return `
        <a href="javascript:void(0);"
            class="forgotten-punch-out-btn"
            data-id="${row.id}"
            data-date="${row.attendanceDate}"
            title="Click to punch out at 07:00 PM">
            ${getStatusBadge(row.attendanceStatus)}
        </a>
    `;
}

$(document).on('click', '.forgotten-punch-out-btn', function()
{
    const attendanceId = $(this).data('id');
    const attendanceDate = $(this).data('date');

    Swal.fire({
        title: 'Mark punch out?',
        text: 'Punch out will be marked at 07:00 PM for ' + formatDate(attendanceDate) + ' and working time will be calculated.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, punch out'
    }).then(function(result) {

        if (!result.isConfirmed) {
            return;
        }

        $.post(
            API.punchOutForgottenAttendance,
            { attendanceId: attendanceId },
            function(response)
            {
                if (!response.success) {
                    return showToast('error', response.message);
                }

                showToast('success', response.message);

                loadAttendanceAnalytics();
                loadAttendanceListing();
                loadAttendanceSummary();
            },
            'json'
        ).fail(function() {
            showToast('error', 'Failed to mark punch out');
        });
    });
});

function renderAttendanceAnalytics(
    response
)
{
    const rows =
        response.data || [];

    const summary =
        response.summary || {};

    destroyAttendanceTimelineChart();
    $('#attendanceTimelineChart').empty();
    attendanceTimelineChart = buildAttendanceTimelineChart(rows);
    attendanceTimelineChart.render();

    renderAnalyticsDetailsTable(rows);

    $('#analyticsSummary').html(`

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Present Days
                    </h6>

                    <h3>
                        ${summary.presentDays}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Half Days
                    </h6>

                    <h3>
                        ${summary.halfDays}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Absent Days
                    </h6>

                    <h3>
                        ${summary.absentDays || 0}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Late Days
                    </h6>

                    <h3>
                        ${summary.lateDays || 0}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Leave Days
                    </h6>

                    <h3>
                        ${summary.leaveDays || 0}
                    </h3>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Working Hours
                    </h6>

                    <h3>
                        ${formatHoursMinutes(summary.workingSeconds)}
                    </h3>

                    <small class="text-muted">
                        ${(summary.workingSeconds / 3600).toFixed(1)}h
                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card">

                <div class="card-body text-center">

                    <h6>
                        Break Hours
                    </h6>

                    <h3>
                        ${formatHoursMinutes(summary.breakSeconds)}
                    </h3>

                    <small class="text-muted">
                        ${(summary.breakSeconds / 3600).toFixed(1)}h
                    </small>

                </div>

            </div>

        </div>

    `);
}

$('#attendanceAnalyticsModal').on('hidden.bs.modal', function() {
    destroyAttendanceTimelineChart();
});
