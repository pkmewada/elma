<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <!-- PAGE HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Setup</h1>

                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>

                    <li class="breadcrumb-item active">
                        Attendance Setup
                    </li>
                </ol>
            </div>
        </div>

        <!-- PAGE CONTENT -->
        <div class="page-content pb-4">
            <div class="container-fluid px-0">
                <div class="row g-4">

                    <!-- BREAK TYPES -->
                    <div class="col-12">
                        <div class="card custom-card">

                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Break Types</h5>

                                <button type="button" class="btn btn-primary" id="addBreakTypeBtn">
                                    <i class="ri-add-line"></i>
                                    Add Break Type
                                </button>
                            </div>

                            <div class="card-body">

                                <div id="setupWarning" class="alert alert-warning d-none mb-3">
                                    Changing attendance settings may affect existing attendance calculations
                                </div>

                                <div class="table-responsive">

                                    <table class="table table-bordered text-nowrap w-100 align-middle mb-0" id="breakTypesTable">

                                        <thead>
                                            <tr>
                                                <th>Break Name</th>
                                                <th>Code</th>
                                                <th>Allowed Minutes</th>
                                                <th>Paid</th>
                                                <th>Multiple</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>

                                        <tbody></tbody>

                                    </table>

                                </div>

                            </div>

                        </div>
                    </div>

                    <!-- OFFICE TIMING -->
                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">
                                <h5 class="mb-0">Office Timing</h5>
                            </div>

                            <div class="card-body">

                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label class="form-label">Office Start Time</label>
                                        <input type="time" class="form-control" id="officeStartTime">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Office End Time</label>
                                        <input type="time" class="form-control" id="officeEndTime">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Total Working Hours</label>
                                        <input type="number" step="0.01" class="form-control" id="totalWorkingHours" value="8">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Grace Minutes</label>
                                        <input type="number" class="form-control" id="graceMinutes" value="15">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Late After Minutes</label>
                                        <input type="number" class="form-control" id="lateAfterMinutes" value="15">
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label">Half Day Hours</label>
                                        <input type="number" step="0.01" class="form-control" id="halfDayHours" value="4">
                                    </div>

                                    <div class="col-md-12">
                                        <label class="form-label">Overtime After Hours</label>
                                        <input type="number" step="0.01" class="form-control" id="overtimeAfterHours" value="9">
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- ATTENDANCE RULES -->
                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">
                                <h5 class="mb-0">Attendance Rules</h5>
                            </div>

                            <div class="card-body">

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="autoPunchOut">

                                    <label class="form-check-label" for="autoPunchOut">
                                        Auto Punch Out
                                    </label>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Auto Punch Out Time</label>

                                    <input type="time" class="form-control" id="autoPunchOutTime">
                                </div>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="allowMultipleBreaks" checked>

                                    <label class="form-check-label" for="allowMultipleBreaks">
                                        Allow Multiple Breaks
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="autoBreakReminderEnabled">
                                    <label class="form-check-label">
                                        Enable Automatic Break Reminders
                                    </label>
                                </div>
                                
                                <hr>

                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="autoMarkAbsent">
                                
                                    <label class="form-check-label">
                                        Auto Mark Absent
                                    </label>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">
                                        Auto Absent Time
                                    </label>
                                
                                    <input
                                        type="time"
                                        class="form-control"
                                        id="autoAbsentTime">
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="restrictEarlyPunchOut">
                                
                                    <label class="form-check-label">
                                        Restrict Early Punch Out
                                    </label>
                                </div>
                                
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="requireApprovedHalfDay">
                                
                                    <label class="form-check-label">
                                        Require Approved Half Day
                                    </label>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">
                                        Required Working Hours for Half Day
                                    </label>
                                
                                    <input type="number" step="0.5" min="0" class="form-control" id="minimumHalfDayHours" value="4.5">
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- WORKING DAYS -->
                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">
                                <h5 class="mb-0">Working Days</h5>
                            </div>

                            <div class="card-body">

                                <div class="d-flex flex-wrap gap-3 mb-4" id="workingDaysGroup">

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="mon" id="wdMon">
                                        <label class="form-check-label" for="wdMon">Mon</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="tue" id="wdTue">
                                        <label class="form-check-label" for="wdTue">Tue</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="wed" id="wdWed">
                                        <label class="form-check-label" for="wdWed">Wed</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="thu" id="wdThu">
                                        <label class="form-check-label" for="wdThu">Thu</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="fri" id="wdFri">
                                        <label class="form-check-label" for="wdFri">Fri</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="sat" id="wdSat">
                                        <label class="form-check-label" for="wdSat">Sat</label>
                                    </div>

                                    <div class="form-check">
                                        <input class="form-check-input working-day" type="checkbox" value="sun" id="wdSun">
                                        <label class="form-check-label" for="wdSun">Sun</label>
                                    </div>

                                </div>

                                <label class="form-label d-block">Weekend Policy</label>

                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="weekendPolicy" id="weekendExclude" value="exclude" checked>

                                    <label class="form-check-label" for="weekendExclude">
                                        Exclude weekends
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="weekendPolicy" id="weekendInclude" value="include">

                                    <label class="form-check-label" for="weekendInclude">
                                        Include weekends
                                    </label>
                                </div>

                            </div>

                        </div>

                    </div>

                    <!-- SETUP COMPLETION -->
                    <div class="col-xl-6 col-lg-6">

                        <div class="card custom-card h-100">

                            <div class="card-header">
                                <h5 class="mb-0">Setup Completion</h5>
                            </div>

                            <div class="card-body">

                                <ul class="mb-4" id="setupProgressList">

                                    <li id="progressBreakTypes">
                                        Break Types added
                                    </li>

                                    <li id="progressOfficeTiming">
                                        Office Timing configured
                                    </li>

                                    <li id="progressRules">
                                        Rules configured
                                    </li>

                                    <li id="progressWorkingDays">
                                        Working days selected
                                    </li>

                                </ul>

                                <button type="button" class="btn btn-primary" id="saveAttendanceSetupBtn">
                                    Save & Activate Attendance Module
                                </button>

                            </div>

                        </div>

                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<!-- BREAK TYPE MODAL -->
<div class="modal fade" id="breakTypeModal" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-dialog-centered modal-lg">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="breakTypeModalTitle">
                    Add Break Type
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>

            </div>

            <form id="breakTypeForm">

                <div class="modal-body">

                    <input type="hidden" id="breakTypeId" value="">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">Break Name</label>
                            <input type="text" class="form-control" id="breakName" placeholder="Enter break name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Break Code</label>
                            <input type="text" class="form-control" id="breakCode" maxlength="10" placeholder="Enter break code" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Allowed Minutes</label>
                            <input type="number" class="form-control" id="allowedMinutes" min="0" value="0">
                        </div>

                        <div class="col-md-6 d-flex align-items-center">

                            <div class="form-check form-switch mt-4">
                                <input class="form-check-input" type="checkbox" id="isPaidBreak">

                                <label class="form-check-label">
                                    Paid Break
                                </label>
                            </div>

                        </div>

                        <div class="col-md-12">

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="allowMultipleTimes">

                                <label class="form-check-label">
                                    Allow Multiple Times
                                </label>
                            </div>
                            
                            <div class="col-md-6">

                                <div class="form-check form-switch">
                            
                                    <input class="form-check-input" type="checkbox" id="isScheduledBreak">
                            
                                    <label class="form-check-label">
                                        Scheduled Break
                                    </label>
                            
                                </div>
                            
                            </div>
                            
                            <div class="col-md-6">
                            
                                <label class="form-label">
                                    Preferred Time
                                </label>
                            
                                <input type="time" class="form-control" id="preferredStartTime">
                                
                            </div>

                        </div>

                    </div>

                </div>

                <div class="modal-footer">

                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                        Cancel
                    </button>

                    <button type="submit" class="btn btn-primary">
                        Save
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>
<!-- JQUERY -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- SWEET ALERT -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>

var breakTypesState = [];
var hasUnsavedChanges = false;

// =========================
// INIT
// =========================
$(function() {

    bindEvents();

    updateAutoPunchOutState();

    updateProgress();

    loadSetup();
});

// =========================
// BIND EVENTS
// =========================
function bindEvents() {

    // ADD BREAK TYPE
    $('#addBreakTypeBtn').on('click', function() {

        resetForm();

        $('#breakTypeModal').modal('show');
    });

    // BREAK CODE UPPERCASE
    $('#breakCode').on('input', function() {

        $(this).val(
            String($(this).val() || '').toUpperCase()
        );
    });

    // SAVE BREAK TYPE
    $('#breakTypeForm').on('submit', function(e) {

        e.preventDefault();

        saveBreakType();
    });

    // EDIT BREAK TYPE
    $(document).on(
        'click',
        '.edit-break-type',
        handleEditBreakType
    );

    // DELETE BREAK TYPE
    $(document).on(
        'click',
        '.delete-break-type',
        handleDeleteBreakType
    );

    // SAVE SETUP
    $('#saveAttendanceSetupBtn').on(
        'click',
        saveAttendanceSetup
    );

    // AUTO PUNCH TOGGLE
    $('#autoPunchOut').on(
        'change',
        updateAutoPunchOutState
    );

    // UPDATE PROGRESS
    $(document).on(
        'change keyup',
        `
            input,
            select,
            textarea
        `,
        function() {

            hasUnsavedChanges = true;

            $('#setupWarning')
                .removeClass('d-none');

            updateProgress();
        }
    );

    // PREVENT NEGATIVE
    $(document).on(
        'input',
        `
            #totalWorkingHours,
            #graceMinutes,
            #lateAfterMinutes,
            #halfDayHours,
            #overtimeAfterHours,
            #allowedMinutes,
            #minimumHalfDayHours
        `,
        function() {

            if (
                Number($(this).val()) < 0
            ) {

                $(this).val(0);
            }
        }
    );

    // MODAL RESET
    $('#breakTypeModal').on(
        'hidden.bs.modal',
        function() {

            $('#breakCode')
                .removeClass('is-invalid');

            $('#duplicateCodeError').remove();

            resetForm();
        }
    );

    // LEAVE WARNING
    window.addEventListener(
        'beforeunload',
        function(e) {

            if (!hasUnsavedChanges)
                return;

            e.preventDefault();

            e.returnValue = '';
        }
    );
}

// =========================
// RESET FORM
// =========================
function resetForm() {

    $('#breakTypeId').val('');

    $('#breakName').val('');

    $('#breakCode').val('');

    $('#allowedMinutes').val(0);

    $('#isPaidBreak').prop(
        'checked',
        false
    );

    $('#allowMultipleTimes').prop(
        'checked',
        false
    );

    $('#breakTypeModalTitle').text(
        'Add Break Type'
    );
    
    $('#isScheduledBreak').prop(
        'checked',
        false
    );
    
    $('#preferredStartTime').val('');
}

// =========================
// BADGE
// =========================
function getBadge(flag) {

    return `
        <span class="badge ${
            flag

                ? 'bg-success-transparent'

                : 'bg-danger-transparent'
        }">

            ${flag ? 'Yes' : 'No'}

        </span>
    `;
}

// =========================
// SORT BREAK TYPES
// =========================
function sortBreakTypes() {

    breakTypesState.sort(function(a, b) {

        return a.breakName.localeCompare(
            b.breakName
        );
    });
}

// =========================
// RENDER ROW
// =========================
function renderBreakTypeRow(item, index) {

    return `
        <tr data-index="${index}">

            <td>${$('<div>').text(item.breakName).html()}</td>

            <td>${$('<div>').text(item.breakCode).html()}</td>

            <td>${item.allowedMinutes}</td>

            <td>${getBadge(item.isPaid)}</td>

            <td>${getBadge(item.allowMultipleTimes)}</td>

            <td>

                <div class="d-flex gap-1">

                    <button
                        type="button"
                        class="btn btn-sm btn-info-light edit-break-type"
                        data-index="${index}"
                    >
                        <i class="ri-edit-line"></i>
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-danger-light delete-break-type"
                        data-index="${index}"
                    >
                        <i class="ri-delete-bin-line"></i>
                    </button>

                </div>

            </td>

        </tr>
    `;
}

// =========================
// RENDER TABLE
// =========================
function renderTable() {

    sortBreakTypes();

    var $tbody =
        $('#breakTypesTable tbody');

    $tbody.empty();

    breakTypesState.forEach(function(item, index) {

        if (
            Number(item.isActive) === 1
        ) {

            $tbody.append(
                renderBreakTypeRow(item, index)
            );
        }
    });
}

// =========================
// UPDATE PROGRESS
// =========================
function updateProgress() {

    var activeBreaks =
        breakTypesState.filter(
            x => Number(x.isActive) === 1
        );

    $('#progressBreakTypes').text(

        (activeBreaks.length ? '✓ ' : '') +

        'Break Types added'
    );

    $('#progressOfficeTiming').text(

        (
            $('#officeStartTime').val() &&
            $('#officeEndTime').val()
        )

        ? '✓ Office Timing configured'

        : 'Office Timing configured'
    );

    $('#progressRules').text(
        '✓ Rules configured'
    );

    var workingDays = [];

    $('.working-day:checked').each(function() {

        workingDays.push(
            $(this).val()
        );
    });

    $('#progressWorkingDays').text(

        (workingDays.length ? '✓ ' : '') +

        'Working days selected'
    );
}

// =========================
// VALIDATE BREAK TYPE
// =========================
function validateBreakTypeForm() {

    var breakName =
        $.trim($('#breakName').val());

    var breakCode =
        $.trim($('#breakCode').val());

    var allowedMinutes =
        Number(
            $('#allowedMinutes').val() || 0
        );

    if (!breakName)
        return 'Break name is required.';

    if (!breakCode)
        return 'Break code is required.';

    if (allowedMinutes < 0)
        return 'Allowed minutes must be valid.';

    var currentIndex =
        $('#breakTypeId').val();

    var duplicateCode = false;

    breakTypesState.forEach(function(item, index) {

        if (
            item.isActive == 1 &&
            item.breakCode == breakCode &&
            index != currentIndex
        ) {

            duplicateCode = true;
        }
    });

    if (duplicateCode)
        return 'Break code already exists.';
        
        // =========================
        // ONLY ONE SCHEDULED BREAK
        // =========================
        var isScheduledBreak =
            $('#isScheduledBreak').is(':checked')
                ? 1
                : 0;
        
        if (isScheduledBreak) {
        
            var currentIndex =
                $('#breakTypeId').val();
        
            var existingScheduledBreak =
                breakTypesState.some(function(item, index) {
        
                    return (
                        Number(item.isActive) === 1 &&
                        Number(item.isScheduledBreak) === 1 &&
                        String(index) !== String(currentIndex)
                    );
                });
        
            if (existingScheduledBreak) {
        
                return 'Only one scheduled break allowed.';
            }
        }

    return '';
}

// =========================
// SAVE BREAK TYPE
// =========================
function saveBreakType() {

    var error =
        validateBreakTypeForm();

    if (error) {

        window.showToast &&
        window.showToast(
            'warning',
            error
        );

        return;
    }

    var rowIndex =
        $('#breakTypeId').val();

    var item = {

        id: 0,

        breakName:
            $.trim($('#breakName').val()),

        breakCode:
            $.trim(
                $('#breakCode').val()
            ).toUpperCase(),

        allowedMinutes:
            Number(
                $('#allowedMinutes').val() || 0
            ),

        isPaid:
            $('#isPaidBreak').is(':checked')
                ? 1
                : 0,

        allowMultipleTimes:
            $('#allowMultipleTimes').is(':checked')
                ? 1
                : 0,
                
                
        isScheduledBreak:
            $('#isScheduledBreak').is(':checked')
                ? 1
                : 0,
        
        preferredStartTime:
            $('#preferredStartTime').val(),

        isActive: 1
    };

    // EDIT
    if (rowIndex !== '') {

        item.id =
            breakTypesState[rowIndex].id;

        breakTypesState[rowIndex] = item;
    }

    // ADD
    else {

        breakTypesState.push(item);
    }

    renderTable();

    updateProgress();

    $('#breakTypeModal').modal('hide');

    window.showToast &&
    window.showToast(
        'success',
        'Break type saved'
    );
}

// =========================
// EDIT BREAK TYPE
// =========================
function handleEditBreakType() {

    var index =
        $(this).data('index');

    var item =
        breakTypesState[index];

    $('#breakTypeId').val(index);

    $('#breakName').val(item.breakName);

    $('#breakCode').val(item.breakCode);

    $('#allowedMinutes').val(
        item.allowedMinutes
    );

    $('#isPaidBreak').prop(
        'checked',
        item.isPaid == 1
    );

    $('#allowMultipleTimes').prop(
        'checked',
        item.allowMultipleTimes == 1
    );

    $('#breakTypeModalTitle').text(
        'Edit Break Type'
    );

    $('#breakTypeModal').modal('show');
    
    
    $('#isScheduledBreak').prop(
        'checked',
        item.isScheduledBreak == 1
    );
    
    $('#preferredStartTime').val(
        item.preferredStartTime || ''
    );
}

// =========================
// DELETE BREAK TYPE
// =========================
function handleDeleteBreakType() {

    var index =
        $(this).data('index');

    Swal.fire({

        title: 'Are you sure?',

        text: "You won't be able to revert this!",

        icon: 'warning',

        showCancelButton: true,

        confirmButtonText: 'Yes, delete it!'

    })

    .then(function(result) {

        if (!result.isConfirmed)
            return;

        breakTypesState[index].isActive = 0;

        renderTable();

        updateProgress();

        window.showToast &&
        window.showToast(
            'success',
            'Break type removed'
        );
    });
}

// =========================
// AUTO PUNCH OUT
// =========================
function updateAutoPunchOutState() {

    var enabled =
        $('#autoPunchOut').is(':checked');

    $('#autoPunchOutTime').prop(
        'disabled',
        !enabled
    );

    if (!enabled) {

        $('#autoPunchOutTime').val('');
    }
}

// =========================
// COLLECT PAYLOAD
// =========================
function collectPayload() {

    var workingDays = [];

    $('.working-day:checked').each(function() {

        workingDays.push(
            $(this).val()
        );
    });

    return {

        breakTypes: breakTypesState,

        attendanceSettings: {

            officeStartTime:
                $('#officeStartTime').val(),

            officeEndTime:
                $('#officeEndTime').val(),

            totalWorkingHours:
                Number(
                    $('#totalWorkingHours').val() || 0
                ),

            graceMinutes:
                Number(
                    $('#graceMinutes').val() || 0
                ),

            lateAfterMinutes:
                Number(
                    $('#lateAfterMinutes').val() || 0
                ),

            halfDayHours:
                Number(
                    $('#halfDayHours').val() || 0
                ),

            overtimeAfterHours:
                Number(
                    $('#overtimeAfterHours').val() || 0
                ),

            autoPunchOut:
                $('#autoPunchOut').is(':checked')
                    ? 1
                    : 0,

            autoPunchOutTime:
                $('#autoPunchOutTime').val(),

            allowMultipleBreaks:
                $('#allowMultipleBreaks').is(':checked')
                    ? 1
                    : 0,
                    
            autoBreakReminderEnabled:
                $('#autoBreakReminderEnabled').is(':checked')
                    ? 1
                    : 0,
                    
            autoMarkAbsent:
                $('#autoMarkAbsent').is(':checked')
                ?1:0,
                
                autoAbsentTime:
                $('#autoAbsentTime').val(),
                
                restrictEarlyPunchOut:
                $('#restrictEarlyPunchOut').is(':checked')
                ?1:0,
                
                requireApprovedHalfDay:
                $('#requireApprovedHalfDay').is(':checked')
                ?1:0,
                
                minimumHalfDayHours:
                Number(
                $('#minimumHalfDayHours').val() || 4.5
                ),

            weekendPolicy:
                $('input[name="weekendPolicy"]:checked').val(),

            workingDays: workingDays
        }
    };
}

// =========================
// VALIDATE PAYLOAD
// =========================
function validatePayload(payload) {

    if (!payload.breakTypes.length)
        return 'At least 1 break type is required.';

    if (!payload.attendanceSettings.officeStartTime)
        return 'Office start time is required.';

    if (!payload.attendanceSettings.officeEndTime)
        return 'Office end time is required.';

    if (
        payload.attendanceSettings.totalWorkingHours <= 0
    ) {

        return 'Total working hours must be greater than 0.';
    }

    if (
        payload.attendanceSettings.halfDayHours <= 0
    ) {

        return 'Half day hours must be greater than 0.';
    }

    if (
        payload.attendanceSettings.overtimeAfterHours <=
        payload.attendanceSettings.totalWorkingHours
    ) {

        return 'Overtime hours should be greater than total working hours.';
    }

    if (
        !payload.attendanceSettings.workingDays.length
    ) {

        return 'Please select working days.';
    }

    return '';
}

// =========================
// SAVE SETUP
// =========================
function saveAttendanceSetup() {

    var payload =
        collectPayload();

    var error =
        validatePayload(payload);

    if (error) {

        window.showToast &&
        window.showToast(
            'warning',
            error
        );

        return;
    }

    var $btn =
        $('#saveAttendanceSetupBtn');

    $btn
        .prop('disabled', true)
        .html(`
            <span class="spinner-border spinner-border-sm me-2"></span>
            Saving...
        `);

    $.ajax({

        url:
            API_BASE +
            '/attendance/saveAttendanceSetup.php',

        type: 'POST',

        contentType: 'application/json',

        dataType: 'json',

        data: JSON.stringify(payload),

        success: function(res) {

            $btn
                .prop('disabled', false)
                .text(
                    'Save & Activate Attendance Module'
                );

            if (
                !res ||
                typeof res.success === 'undefined'
            ) {

                window.showToast &&
                window.showToast(
                    'danger',
                    'Invalid server response.'
                );

                return;
            }

            window.showToast &&
            window.showToast(
                res.success
                    ? 'success'
                    : 'danger',
                res.message
            );

            if (res.success) {

                hasUnsavedChanges = false;

                $('#setupWarning')
                    .addClass('d-none');

                loadSetup();
            }
        },

        error: function() {

            $btn
                .prop('disabled', false)
                .text(
                    'Save & Activate Attendance Module'
                );

            window.showToast &&
            window.showToast(
                'danger',
                'Failed to save attendance setup.'
            );
        }
    });
}

// =========================
// BIND STATE
// =========================
function bindState(res) {

    var settings =
        res.data?.attendanceSettings || {};

    var breakTypes =
        res.data?.breakTypes || [];

    breakTypesState = [];

    breakTypes.forEach(function(item) {

        breakTypesState.push({

            id:
                Number(item.id || 0),
        
            breakName:
                item.breakName || '',
        
            breakCode:
                item.breakCode || '',
        
            allowedMinutes:
                Number(item.allowedMinutes || 0),
        
            isPaid:
                Number(item.isPaid || 0),
        
            allowMultipleTimes:
                Number(item.allowMultipleTimes || 0),
        
            isScheduledBreak:
                Number(
                    item.isScheduledBreak || 0
                ),
        
            preferredStartTime:
                item.preferredStartTime || '',
        
            isActive:
                Number(item.isActive || 0)
        });
        
    });

    renderTable();

    $('#officeStartTime').val(
        settings.officeStartTime || ''
    );

    $('#officeEndTime').val(
        settings.officeEndTime || ''
    );

    $('#totalWorkingHours').val(
        Number(settings.totalWorkingHours || 0)
    );

    $('#graceMinutes').val(
        Number(settings.graceMinutes || 0)
    );

    $('#lateAfterMinutes').val(
        Number(settings.lateAfterMinutes || 0)
    );

    $('#halfDayHours').val(
        Number(settings.halfDayHours || 0)
    );

    $('#overtimeAfterHours').val(
        Number(settings.overtimeAfterHours || 0)
    );

    $('#autoPunchOut').prop(
        'checked',
        Number(settings.autoPunchOut) === 1
    );

    $('#autoPunchOutTime').val(
        settings.autoPunchOutTime || ''
    );

    $('#allowMultipleBreaks').prop(
        'checked',
        Number(settings.allowMultipleBreaks) === 1
    );
    
    $('#autoBreakReminderEnabled').prop(
        'checked',
        Number(
            settings.autoBreakReminderEnabled
        ) === 1
    );
    
    
    $('#autoMarkAbsent').prop(
        'checked',
        Number(settings.autoMarkAbsent)===1
        );
        
        $('#autoAbsentTime').val(
        settings.autoAbsentTime || '19:30'
        );
        
        $('#restrictEarlyPunchOut').prop(
        'checked',
        Number(settings.restrictEarlyPunchOut)===1
        );
        
        $('#requireApprovedHalfDay').prop(
        'checked',
        Number(settings.requireApprovedHalfDay)===1
        );
        
        $('#minimumHalfDayHours').val(
        Number(settings.minimumHalfDayHours || 4.5)
        );
    
    $('input[name="weekendPolicy"][value="' +

        (
            settings.weekendPolicy === 'include'

                ? 'include'

                : 'exclude'
        )

    + '"]').prop('checked', true);

    $('.working-day').prop(
        'checked',
        false
    );

    (settings.workingDays || []).forEach(function(day) {

        $('.working-day[value="' +

            String(day).toLowerCase()

        + '"]').prop(
            'checked',
            true
        );
    });

    updateAutoPunchOutState();

    updateProgress();
}

// =========================
// LOAD SETUP
// =========================
function loadSetup() {

    $.getJSON(
        API_BASE + '/attendance/getAttendanceSetup.php'
    )

    .done(function(res) {

        if (
            !res ||
            !res.success
        ) {

            window.showToast &&
            window.showToast(
                'danger',
                (
                    res &&
                    res.message
                )

                || 'Failed to load attendance setup.'
            );

            return;
        }

        bindState(res);
    })

    .fail(function() {

        window.showToast &&
        window.showToast(
            'danger',
            'Unable to load attendance setup.'
        );
    });
}

</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>