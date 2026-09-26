<?php
include __DIR__ . '/../includes/auth.php';
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Setup</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Leave Setup</li>
                </ol>
            </div>
        </div>

        <div class="page-content pb-4">
            <div class="container-fluid px-0">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Leave Types</h5>
                                <button type="button" class="btn btn-primary" id="addLeaveTypeBtn">
                                    <i class="ri-add-line"></i> Add Leave Type
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="setupWarning" class="alert alert-warning d-none mb-3">Changing leave settings
                                    may affect existing leave data</div>
                                <div class="table-responsive">
                                    <table class="table table-bordered text-nowrap w-100 align-middle mb-0"
                                        id="leaveTypesTable">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Code</th>
                                                <th>Paid</th>
                                                <th>Allocation</th>
                                                <th>Total Leaves</th>
                                                <th>Half Day</th>
                                                <th>Negative</th>
                                                <th>Gender</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Leave Rules</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox"
                                        id="sandwichRule"><label class="form-check-label" for="sandwichRule">Sandwich
                                        Rule</label></div>
                                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox"
                                        id="carryForward"><label class="form-check-label" for="carryForward">Carry
                                        Forward</label></div>
                                <div class="mb-3"><label class="form-label">Carry Forward Limit</label><input
                                        type="number" class="form-control" id="carryForwardLimit" min="0" value="0">
                                </div>
                                <div class="mb-3"><label class="form-label">Max Leaves Per Request</label><input
                                        type="number" class="form-control" id="maxLeavesPerRequest" min="0" value="1">
                                </div>
                                <div><label class="form-label">Minimum Notice Days</label><input type="number"
                                        class="form-control" id="minNoticeDays" min="0" value="0"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Working Days</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3 mb-4" id="workingDaysGroup">
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="mon" id="wdMon"><label class="form-check-label"
                                            for="wdMon">Mon</label></div>
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="tue" id="wdTue"><label class="form-check-label"
                                            for="wdTue">Tue</label></div>
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="wed" id="wdWed"><label class="form-check-label"
                                            for="wdWed">Wed</label></div>
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="thu" id="wdThu"><label class="form-check-label"
                                            for="wdThu">Thu</label></div>
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="fri" id="wdFri"><label class="form-check-label"
                                            for="wdFri">Fri</label></div>
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="sat" id="wdSat"><label class="form-check-label"
                                            for="wdSat">Sat</label></div>
                                    <div class="form-check"><input class="form-check-input working-day" type="checkbox"
                                            value="sun" id="wdSun"><label class="form-check-label"
                                            for="wdSun">Sun</label></div>
                                </div>
                                <label class="form-label d-block">Weekend Policy</label>
                                <div class="form-check mb-2"><input class="form-check-input" type="radio"
                                        name="weekendPolicy" id="weekendExclude" value="exclude" checked><label
                                        class="form-check-label" for="weekendExclude">Exclude weekends</label></div>
                                <div class="form-check"><input class="form-check-input" type="radio"
                                        name="weekendPolicy" id="weekendInclude" value="include"><label
                                        class="form-check-label" for="weekendInclude">Include weekends</label></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-6 col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Approval Flow</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-0">Leave will be approved by Reporting Manager</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-6 col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h5 class="mb-0">Setup Completion</h5>
                            </div>
                            <div class="card-body">
                                <ul class="mb-4" id="setupProgressList">
                                    <li id="progressLeaveTypes">Leave Types added</li>
                                    <li id="progressRules">Rules configured</li>
                                    <li id="progressWorkingDays">Working days selected</li>
                                </ul><button type="button" class="btn btn-primary" id="saveLeaveSetupBtn">Save &
                                    Activate Leave Module</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="leaveTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="leaveTypeModalTitle">Add Leave Type</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="leaveTypeForm">

                <div class="modal-body">

                    <input type="hidden" id="leaveTypeId" value="">

                    <div class="row g-3">
                        <!-- Basic Details -->
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Leave Name</label>
                                <input type="text" class="form-control" id="leaveTypeName"
                                    placeholder="Enter leave name" required>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Code</label>
                                <input type="text" class="form-control" id="leaveTypeCode" maxlength="10"
                                    placeholder="Enter code" required>
                            </div>
                        </div>

                        <div class="col-12 col-md-6">

                            <div class="mb-3">
                                <label class="form-label">Allocation Type</label>
                                <select class="form-select" id="leaveTypeAllocation">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">

                            <div class="mb-3">
                                <label class="form-label">Total Leaves</label>
                                <input type="number" class="form-control" id="leaveTypeTotalLeaves" min="0" value="0"
                                    placeholder="Enter total leaves" required>
                            </div>
                        </div>

                        <hr>

                        <!-- ADVANCED RULES -->

                        <h6 class="fw-semibold mb-3">Advanced Rules</h6>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="allowHalfDay">
                                <label class="form-check-label">Allow Half Day</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="leaveTypePaid">
                                <label class="form-check-label">Paid Leave</label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="allowNegative">
                                <label class="form-check-label">Allow Negative Balance</label>
                            </div>

                        </div>
                        <div class="col-12 col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Max Consecutive Days</label>
                                <input type="number" class="form-control" id="maxConsecutiveDays" min="0" value="0"
                                    placeholder="Enter max consecutive days">
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Min Service Days (Probation Rule)</label>
                                <input type="number" class="form-control" id="minServiceDays" min="0" value="0"
                                    placeholder="Enter min service days">
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Applicable Gender</label>
                                <select class="form-select" id="applicableGender">
                                    <option value="all">All</option>
                                    <option value="male">Male Only</option>
                                    <option value="female">Female Only</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 col-md-12">
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
var leaveTypesState = [];
$(function() {
    var rowCounter = 0;

    function resetForm() {
        $('#leaveTypeId').val('');
        $('#leaveTypeName').val('');
        $('#leaveTypeCode').val('');
        $('#leaveTypePaid').prop('checked', false);
        $('#leaveTypeAllocation').val('yearly');
        $('#leaveTypeTotalLeaves').val(0);
        $('#leaveTypeModalTitle').text('Add Leave Type');

        // ✅ ADD THIS
        $('#allowHalfDay').prop('checked', false);
        $('#maxConsecutiveDays').val(0);
        $('#minServiceDays').val(0);
        $('#applicableGender').val('all');
        $('#allowNegative').prop('checked', false);
    }

    function getBadgeText(flag) {
        return flag ? '<span class="badge bg-success-transparent">Yes</span>' :
            '<span class="badge bg-danger-transparent">No</span>';
    }

   function renderLeaveTypeRow(item, index) {
    return '<tr data-index="' + index + '">' +
        '<td>' + $('<div>').text(item.name).html() + '</td>' +
        '<td>' + $('<div>').text(item.code).html() + '</td>' +
        '<td>' + getBadgeText(item.isPaid) + '</td>' +
        '<td>' + (item.allocationType === 'monthly' ? 'Monthly' : 'Yearly') + '</td>' +
        '<td>' + item.totalLeaves + '</td>' +
        '<td>' + getBadgeText(item.allowHalfDay) + '</td>' +
        '<td>' + getBadgeText(item.allowNegative) + '</td>' +
        '<td>' +
        (item.applicableGender === 'male' ? 'Male' :
            item.applicableGender === 'female' ? 'Female' : 'All') +
        '</td>' +
        '<td>' +

            '<div class="d-flex gap-1">' +
            
                '<a href="javascript:void(0);" \
                    class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light edit-leave-type" \
                    data-index="' + index + '" \
                    title="Edit"> \
                    <i class="ri-edit-line"></i> \
                </a>' +
            
                '<a href="javascript:void(0);" \
                    class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-leave-type" \
                    data-index="' + index + '" \
                    title="Delete"> \
                    <i class="ri-delete-bin-line"></i> \
                </a>' +
            
            '</div>' +
            
            '</td>'+
        '</tr>';
}

    function renderTable() {
        var $tbody = $('#leaveTypesTable tbody').empty();

        leaveTypesState.forEach(function(item, index) {
            if (item.isActive !== 0) {
                $tbody.append(renderLeaveTypeRow(item, index));
            }
        });
    }

    function updateCarryForwardState() {
        var on = $('#carryForward').is(':checked');
        $('#carryForwardLimit').prop('disabled', !on);
        if (!on) {
            $('#carryForwardLimit').val(0);
        }
    }

    function collectPayload() {
        var workingDays = [];
        $('.working-day:checked').each(function() {
            workingDays.push($(this).val());
        });

        return {
            leaveTypes: leaveTypesState,
            leaveSettings: {
                workingDays: workingDays,
                weekendPolicy: $('input[name="weekendPolicy"]:checked').val(),
                sandwichRule: $('#sandwichRule').is(':checked') ? 1 : 0,
                carryForward: $('#carryForward').is(':checked') ? 1 : 0,
                carryForwardLimit: Number($('#carryForwardLimit').val() || 0),
                maxLeavesPerRequest: Number($('#maxLeavesPerRequest').val() || 0),
                minNoticeDays: Number($('#minNoticeDays').val() || 0)
            }
        };
    }

    // FIX: Initialize leaveTypesState from existing 'types' variable
    if (typeof types !== 'undefined' && types && types.length) {
        leaveTypesState = [];
        $.each(types, function(_, item) {
            leaveTypesState.push({
                id: item.id || 0,
                name: item.name || '',
                code: item.code || '',
                isPaid: Number(item.isPaid),
                allocationType: item.allocationType,
                totalLeaves: Number(item.totalLeaves),
                isActive: Number(item.isActive),
                allowHalfDay: Number(item.allowHalfDay) || 0,
                maxConsecutiveDays: Number(item.maxConsecutiveDays) || 0,
                minServiceDays: Number(item.minServiceDays) || 0,
                applicableGender: item.applicableGender || 'all',
                allowNegative: Number(item.allowNegative) || 0
            });
        });
        renderTable();
    }

    function validatePayload(payload) {
        if (!payload.leaveTypes.length) return 'At least 1 leave type is required.';

        if (!payload.leaveSettings.workingDays.length)
            return 'Please select working days.';

        if (
            payload.leaveSettings.maxLeavesPerRequest < 0 ||
            payload.leaveSettings.minNoticeDays < 0 ||
            payload.leaveSettings.carryForwardLimit < 0
        ) {
            return 'Numeric fields must be 0 or greater.';
        }

        var seen = {};

        for (var i = 0; i < payload.leaveTypes.length; i++) {
            var row = payload.leaveTypes[i];

            // basic validation
            if (!row.name || !row.code || row.totalLeaves < 0)
                return 'Please complete all leave type fields with valid values.';

            // duplicate code
            if (seen[row.code])
                return 'Duplicate leave code found: ' + row.code;

            seen[row.code] = true;

            // ✅ FIX: advanced validation INSIDE loop
            if (row.maxConsecutiveDays < 0 || row.minServiceDays < 0) {
                return 'Advanced fields must be valid numbers.';
            }
        }

        return '';
    }

    function updateProgress(payload) {
        $('#progressLeaveTypes').text((payload.leaveTypes.length ? '✓ ' : '') + 'Leave Types added');
        $('#progressRules').text(((payload.leaveSettings.maxLeavesPerRequest >= 0 && payload.leaveSettings.minNoticeDays >= 0) ? '✓ ' : '') + 'Rules configured');
        $('#progressWorkingDays').text((payload.leaveSettings.workingDays.length ? '✓ ' : '') + 'Working days selected');
    }

    function bindState(res) {
        var settings = (res.data && res.data.leaveSettings) ? res.data.leaveSettings : {};
        var types = (res.data && res.data.leaveTypes) ? res.data.leaveTypes : [];

        leaveTypesState = []; // ✅ RESET STATE

        $.each(types, function(_, item) {
            leaveTypesState.push({
                id: item.id || 0,
                name: item.name || '',
                code: item.code || '',
                isPaid: Number(item.isPaid),
                allocationType: item.allocationType,
                totalLeaves: Number(item.totalLeaves),
                isActive: Number(item.isActive),
                allowHalfDay: Number(item.allowHalfDay) || 0,
                maxConsecutiveDays: Number(item.maxConsecutiveDays) || 0,
                minServiceDays: Number(item.minServiceDays) || 0,
                applicableGender: item.applicableGender || 'all',
                allowNegative: Number(item.allowNegative) || 0
            });
        });

        renderTable(); // ✅ IMPORTANT

        // settings binding
        $('#sandwichRule').prop('checked', Number(settings.sandwichRule) === 1);
        $('#carryForward').prop('checked', Number(settings.carryForward) === 1);
        $('#carryForwardLimit').val(Number(settings.carryForwardLimit || 0));
        $('#maxLeavesPerRequest').val(Number(settings.maxLeavesPerRequest || 0));
        $('#minNoticeDays').val(Number(settings.minNoticeDays || 0));

        $('input[name="weekendPolicy"][value="' +
            ((settings.weekendPolicy === 'include') ? 'include' : 'exclude') +
            '"]').prop('checked', true);

        $('.working-day').prop('checked', false);
        $.each((settings.workingDays || []), function(_, day) {
            $('.working-day[value="' + String(day).toLowerCase() + '"]').prop('checked', true);
        });

        updateCarryForwardState();
        updateProgress(collectPayload());
    }

    function loadSetup() {
        $.getJSON(API_BASE + '/leave/getLeaveSetup.php').done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast((res && res.message) ||
                    'Failed to load leave setup.');
                return;
            }
            bindState(res);
        }).fail(function() {
            window.showToast && window.showToast('Unable to load leave setup.');
        });
    }

    $('#addLeaveTypeBtn').on('click', function() {
        resetForm();
        $('#leaveTypeModal').modal('show');
    });

    $(document).on('click', '.edit-leave-type', function() {
        var index = $(this).data('index');
        var item = leaveTypesState[index];

        $('#leaveTypeId').val(index);
        $('#leaveTypeName').val(item.name);
        $('#leaveTypeCode').val(item.code);
        $('#leaveTypePaid').prop('checked', item.isPaid == 1);
        $('#leaveTypeAllocation').val(item.allocationType);
        $('#leaveTypeTotalLeaves').val(item.totalLeaves);

        $('#allowHalfDay').prop('checked', item.allowHalfDay == 1);
        $('#maxConsecutiveDays').val(item.maxConsecutiveDays);
        $('#minServiceDays').val(item.minServiceDays);
        $('#applicableGender').val(item.applicableGender);
        $('#allowNegative').prop('checked', item.allowNegative == 1);
        
        $('#leaveTypeModalTitle').text('Edit Leave Type');
        $('#leaveTypeModal').modal('show');
    });

    $('#leaveTypeCode').on('input', function() {
        $(this).val(String($(this).val() || '').toUpperCase());
    });

    function validateLeaveTypeForm() {
        var name = $.trim($('#leaveTypeName').val());
        var code = $.trim($('#leaveTypeCode').val());
        var totalLeaves = Number($('#leaveTypeTotalLeaves').val() || 0);
        var maxConsecutive = Number($('#maxConsecutiveDays').val() || 0);
        var minService = Number($('#minServiceDays').val() || 0);
        var gender = $('#applicableGender').val();

        if (!name) return 'Leave name is required.';
        if (!code) return 'Code is required.';
        if (totalLeaves < 0) return 'Total leaves must be 0 or greater.';

        // ✅ ADVANCED VALIDATION
        if (maxConsecutive < 0)
            return 'Max consecutive days cannot be negative.';

        if (minService < 0)
            return 'Min service days cannot be negative.';

        if (!['all', 'male', 'female'].includes(gender))
            return 'Invalid gender selected.';

        return '';
    }

    $('#leaveTypeForm').on('submit', function(e) {
        e.preventDefault();
        
        // Validate form before saving
        var validationError = validateLeaveTypeForm();
        if (validationError) {
            window.showToast && window.showToast('warning', validationError);
            return;
        }

        var rowIndex = $('#leaveTypeId').val();

        var item = {
            id: 0,
            name: $.trim($('#leaveTypeName').val()),
            code: $.trim($('#leaveTypeCode').val()).toUpperCase(),
            isPaid: $('#leaveTypePaid').is(':checked') ? 1 : 0,
            allocationType: $('#leaveTypeAllocation').val(),
            totalLeaves: Number($('#leaveTypeTotalLeaves').val() || 0),
            isActive: 1,
            allowHalfDay: $('#allowHalfDay').is(':checked') ? 1 : 0,
            maxConsecutiveDays: Number($('#maxConsecutiveDays').val() || 0),
            minServiceDays: Number($('#minServiceDays').val() || 0),
            applicableGender: $('#applicableGender').val(),
            allowNegative: $('#allowNegative').is(':checked') ? 1 : 0
        };

        // EDIT
        if (rowIndex !== '') {
            item.id = leaveTypesState[rowIndex].id;
            leaveTypesState[rowIndex] = item;
        } else {
            leaveTypesState.push(item);
        }

        renderTable();
        $('#leaveTypeModal').modal('hide');
        updateProgress(collectPayload());

        window.showToast && window.showToast('success', 'Leave type saved');
    });

    $(document).on('click', '.delete-leave-type', function() {
        var index = $(this).data('index');

        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then(function(result) {
            if (result.isConfirmed) {
                leaveTypesState[index].isActive = 0;
                renderTable();
                updateProgress(collectPayload());
                window.showToast('success', 'Leave type removed');
            }
        });
    });

    $('#carryForward').on('change', updateCarryForwardState);
    $(document).on('change keyup',
        '#sandwichRule,#carryForward,#carryForwardLimit,#maxLeavesPerRequest,#minNoticeDays,.working-day,input[name="weekendPolicy"]',
        function() {
            updateProgress(collectPayload());
        });

    $('#saveLeaveSetupBtn').on('click', function() {
        console.log('SAVE CLICKED');
        
        var payload = collectPayload();
        console.log(payload);
        
        var error = validatePayload(payload);
        
        if (error) {
            window.showToast && window.showToast('warning', error);
            return;
        }
        
        // Disable save button to prevent double submission
        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: API_BASE + '/leave/saveLeaveSetup.php',
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload),
            success: function(res) {
                console.log('API RESPONSE:', res);
                
                if (!res || typeof res.success === 'undefined') {
                    window.showToast && window.showToast('Invalid response.');
                    return;
                }
                
                window.showToast && window.showToast(res.success ? 'success' : 'danger', res.message);
                
                if (res.success) {
                    loadSetup();
                }
                
                $btn.prop('disabled', false).text('Save Settings');
            },
            error: function(xhr) {
                console.log('API ERROR:', xhr.responseText);
                window.showToast && window.showToast('danger', 'Failed to save leave setup.');
                $btn.prop('disabled', false).text('Save Settings');
            }
        });
    });

    // Only call loadSetup if not using the static 'types' variable
    if (typeof types === 'undefined' || !types || !types.length) {
        loadSetup();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>