<?php
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/employeeInfoEngine.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">

<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">


<style>
.table-responsive {
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.table-responsive::-webkit-scrollbar {
    display: none;
}

.dataTables_filter {
    display: none !important;
}

#statusFilter {
    min-width: 220px;
}

#customSearch {
    min-width: 260px;
}

#leaveTable td,
#leaveTable th {
    white-space: nowrap;
    vertical-align: middle;
}

.dataTables_paginate .pagination {
    margin-bottom: 0 !important;
}

.dataTables_info {
    padding-top: 8px;
}

@media (max-width: 768px) {

    #customSearch,
    #statusFilter {
        width: 100% !important;
        min-width: 100%;
    }

}
</style>
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 page-header-breadcrumb d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Leave Management</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active">Apply Leave</li>
                </ol>
            </div>

            <button class="btn btn-primary btn-wave" data-bs-toggle="modal" data-bs-target="#applyLeaveModal">
                <i class="ri-add-line me-1"></i> Apply Leave
            </button>
        </div>

        <!-- TABLE -->
        <!-- FILTER CARD -->
        <div class="card custom-card mb-3">

            <div class="card-body p-3">

                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

                    <!-- LEFT -->
                    <div class="d-flex flex-wrap align-items-center gap-2">

                        <!-- EXPORT -->
                        <div style="min-width:160px;">

                            <div class="btn-group w-100">

                                <button type="button" class="btn btn-outline-primary dropdown-toggle w-100"
                                    data-bs-toggle="dropdown">

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

                        </div>

                        <!-- FILTER -->
                        <div style="min-width:220px;">

                            <select id="statusFilter" class="form-select">

                                <option value="">
                                    All Status
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="approved">
                                    Approved
                                </option>

                                <option value="rejected">
                                    Rejected
                                </option>

                                <option value="partial">
                                    Partial
                                </option>

                                <option value="cancelled">
                                    Cancelled
                                </option>

                            </select>

                        </div>

                    </div>

                    <!-- CENTER -->
                    <div class="flex-fill"></div>

                    <!-- RIGHT -->
                    <div class="custom-search">

                        <input type="text" id="customSearch" class="form-control" placeholder="Search leaves..."
                            autocomplete="off">

                    </div>

                </div>

            </div>

        </div>

        <!-- TABLE CARD -->
        <div class="card custom-card">

            <div class="card-header">

                <div class="card-title">
                    My Leaves
                </div>

            </div>

            <div class="card-body">

                <div class="table-responsive">

                    <table id="leaveTable" class="table table-hover text-nowrap">

                        <thead>

                            <tr>
                                <th>#</th>
                                <th>Leave Type</th>
                                <th>Date Range</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>

                        </thead>

                        <tbody id="leaveTableBody">

                            <tr>

                                <td colspan="6" class="text-center text-muted">

                                    Loading...

                                </td>

                            </tr>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>
</div>
<div class="modal fade" id="applyLeaveModal">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Apply Leave</h5>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <div class="mb-3">
                    <label class="form-label">Leave Type</label>
                    <select id="leaveType" class="form-select"></select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Leave Duration</label>
                    <select id="dayType" class="form-select">
                        <option value="full">Full Day</option>
                        <option value="half">Half Day</option>
                    </select>
                </div>

                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label">From</label>
                        <input type="date" id="fromDate" class="form-control">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label">To <span class="text-muted">(optional)</span></label>
                        <input type="date" id="toDate" class="form-control">
                    </div>
                </div>

                <div class="mt-3 p-3 border rounded bg-light">

                    <div class="d-flex justify-content-between">
                        <span>Available Balance:</span>
                        <strong id="leaveBalance">--</strong>
                    </div>

                    <div class="d-flex justify-content-between">
                        <span>Requested Days:</span>
                        <strong id="leaveDays">0</strong>
                    </div>

                    <div id="leaveValidationMsg" class="mt-2 small"></div>

                </div>

                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <textarea id="reason" class="form-control"></textarea>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="submitLeaveBtn">Apply</button>
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

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(function() {

    const API = {

        getSetup: API_BASE + '/leave/emp-getLeaveSetup.php',

        applyLeave: API_BASE + '/leave/emp-applyLeave.php',

        getLeaves: API_BASE + '/leave/emp-getMyLeaves.php',

        cancelLeave: API_BASE + '/leave/emp-cancelLeave.php',

        getBalance: API_BASE + '/leave/emp-getLeaveBalance.php'
    };

    // =========================
    // GLOBAL STATE
    // =========================
    let leaveSetup = {};
    let leaveTypes = [];
    let table = null;
    let statusFilterRegistered = false;

    function escapeHtml(value) {
        return $('<div>').text(value === null || value === undefined ? '' : value).html();
    }

    function formatDate(value) {
        if (!value) {
            return '';
        }

        let date = new Date(value + 'T00:00:00');

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleDateString('en-GB', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }

    function formatDateRange(fromDate, toDate) {
        let from = formatDate(fromDate);
        let to = formatDate(toDate || fromDate);

        return from === to ? from : `${from} - ${to}`;
    }

    function getEffectiveToDate() {
        return $('#toDate').val() || $('#fromDate').val();
    }

    // =========================
    // LOAD LEAVE TYPES
    // =========================
    function loadLeaveTypes() {

        $.getJSON(API.getSetup, function(res) {

            if (!res || !res.success || !res.data) {
                window.showToast && window.showToast('danger', 'Failed to load leave setup');
                return;
            }

            leaveSetup = res.data.leaveSettings || {};
            leaveTypes = res.data.leaveTypes || [];

            let $select = $('#leaveType').empty();
            $select.append('<option value="">Select</option>');

            leaveTypes.forEach(function(t) {
                if (Number(t.isActive) === 1) {
                    $select.append(`<option value="${t.id}">${escapeHtml(t.name)}</option>`);
                }
            });

            updateDayTypeAvailability();

        }).fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load leave types');
        });
    }

    // =========================
    // LOAD LEAVES TABLE
    // =========================
    function loadLeaves() {

        $.getJSON(API.getLeaves, function(res) {

            let html = '';
            let leaves = res.data?.leaves || [];

            if (!res.success || !leaves.length) {
                html = `<tr><td colspan="6" class="text-center">No records</td></tr>`;
            } else {

                leaves.forEach((row, i) => {

                    let status = row.status || 'pending';

                    html += `
                    <tr>
                        <td>${i + 1}</td>
                        <td>${escapeHtml(row.leaveTypeName)} (${escapeHtml(row.leaveTypeCode)})</td>
                        <td>${escapeHtml(formatDateRange(row.fromDate, row.toDate))}</td>
                        <td>${escapeHtml(row.totalDays)} ${row.dayType === 'half' ? '(Half Day)' : ''}</td>
                        <td data-status="${escapeHtml(status)}">
                            <span class="btn btn-sm btn-outline-${getStatusColor(status)}">
                                ${escapeHtml(status.charAt(0).toUpperCase() + status.slice(1))}
                            </span>
                        </td>
                        <td>
                        ${status === 'pending' ? `
                            <a href="javascript:void(0);"
                                class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light cancelLeaveBtn"
                                data-id="${row.id}"
                                title="Cancel Leave">
                                <i class="ri-close-circle-line"></i>
                            </a>` : ''}
                    </td>
                    </tr>`;
                });
            }

            if ($.fn.DataTable.isDataTable('#leaveTable')) {

                $('#leaveTable').DataTable().destroy();

            }

            $('#leaveTableBody').html(html);

            table = $('#leaveTable').DataTable({

                dom: "rt" +
                    "<'d-flex justify-content-between align-items-center mt-3 flex-wrap gap-2'<'datatable-info'i><'datatable-pagination'p>>",

                pageLength: 10,

                ordering: true,

                order: [
                    [0, 'asc']
                ],

                lengthChange: false,

                autoWidth: false,

                responsive: false,

                searching: true,

                language: {

                    emptyTable: "No leave records found",

                    zeroRecords: "No matching leave records found",

                    info: "Showing _START_ to _END_ of _TOTAL_ entries",

                    infoEmpty: "No entries available",

                    paginate: {
                        previous: "Prev",
                        next: "Next"
                    }

                },

                buttons: [{
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',
                        exportOptions: {
                            columns: ':visible'
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',
                        exportOptions: {
                            columns: ':visible'
                        }
                    }
                ],

                drawCallback: function() {

                    var api = this.api();

                    api.rows({
                        page: 'current'
                    }).every(function(rowIdx) {

                        $(this.node())
                            .find('td:eq(0)')
                            .html(rowIdx + 1);

                    });

                }

            });

            $('#customSearch').off().on('keyup input', function() {

                table.search(
                    $.trim($(this).val())
                ).draw();

            });

            if (!statusFilterRegistered) {
                $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {

                    if (settings.nTable.id !== 'leaveTable') {
                        return true;
                    }

                    let status = $('#statusFilter').val();

                    let row = settings.aoData[dataIndex]?.nTr;

                    if (!row) return true;

                    let rowStatus = $(row)
                        .find('td[data-status]')
                        .attr('data-status');

                    if (status && rowStatus !== status) {
                        return false;
                    }

                    return true;

                });

                statusFilterRegistered = true;
            }

            $('#statusFilter').off().on('change', function() {

                table.draw();

            });
        });
    }

    // =========================
    // STATUS COLOR
    // =========================
    function getStatusColor(status) {
        if (status === 'approved') return 'success';
        if (status === 'rejected') return 'danger';
        if (status === 'partial') return 'info';
        if (status === 'cancelled') return 'secondary';
        return 'warning';
    }

    // =========================
    // CALCULATE DAYS
    // =========================
    function calculateDays(from, to) {

        if (!from || !to) return 0;

        let start = new Date(from);
        let end = new Date(to);

        let total = 0;

        while (start <= end) {

            let day = start.toLocaleDateString('en-US', {
                weekday: 'short'
            }).toLowerCase();

            if ((leaveSetup.workingDays || []).includes(day)) {
                total++;
            } else if (leaveSetup.weekendPolicy === 'include') {
                total++;
            }

            start.setDate(start.getDate() + 1);
        }

        return total;
    }

    function getSelectedLeaveType() {
        let leaveTypeId = $('#leaveType').val();

        return leaveTypes.find(t => t.id == leaveTypeId);
    }

    function updateDayTypeAvailability() {
        let type = getSelectedLeaveType();
        let allowHalfDay = type && Number(type.allowHalfDay) === 1;
        let $halfOption = $('#dayType option[value="half"]');

        $halfOption.prop('disabled', !allowHalfDay);

        if (!allowHalfDay && $('#dayType').val() === 'half') {
            $('#dayType').val('full');
            $('#toDate').prop('disabled', false);
        }
    }

    function syncDateInputsForDayType() {
        if ($('#dayType').val() === 'half') {
            $('#toDate')
                .val($('#fromDate').val())
                .prop('disabled', true);
        } else {
            $('#toDate').prop('disabled', false);
        }
    }

    function calculateRequestedDays(from, to, dayType) {
        let fullDays = calculateDays(from, to);

        if (dayType === 'half') {
            return fullDays > 0 ? 0.5 : 0;
        }

        return fullDays;
    }

    // =========================
    // VALIDATION PREVIEW
    // =========================
    function validatePreview() {

        let from = $('#fromDate').val();
        let to = getEffectiveToDate();
        let leaveTypeId = $('#leaveType').val();
        let dayType = $('#dayType').val();

        let msgBox = $('#leaveValidationMsg');
        msgBox.html('').removeClass('text-danger text-success');

        if (!from || !leaveTypeId) return;

        if (from > to) {
            return msgBox.addClass('text-danger').text('Invalid date range');
        }

        let days = calculateRequestedDays(from, to, dayType);
        $('#leaveDays').text(days);

        let type = getSelectedLeaveType();
        if (!type) return;

        if (dayType === 'half' && Number(type.allowHalfDay) !== 1) {
            return msgBox.addClass('text-danger').text('Half day is not allowed for this leave type');
        }

        if (dayType === 'half' && from !== to) {
            return msgBox.addClass('text-danger').text('Half day leave can be applied for one date only');
        }

        let balance = parseFloat($('#leaveBalance').text()) || 0;

        // max leaves
        if (leaveSetup.maxLeavesPerRequest > 0 && days > leaveSetup.maxLeavesPerRequest) {
            return msgBox.addClass('text-danger').text('Exceeds max leaves per request');
        }

        // max consecutive
        if (type.maxConsecutiveDays > 0 && days > type.maxConsecutiveDays) {
            return msgBox.addClass('text-danger').text('Exceeds max consecutive days');
        }

        // balance
        if (type.allowNegative != 1 && days > balance) {
            return msgBox.addClass('text-danger').text('Insufficient leave balance');
        }

        msgBox.addClass('text-success').text('Looks good');
    }

    // =========================
    // LOAD BALANCE
    // =========================
    function loadBalance(
        leaveTypeId,
        callback
    ) {

        $.getJSON(

            API.getBalance +
            '?leaveTypeId=' +
            leaveTypeId,

            function(res) {

                if (
                    res.success &&
                    res.data
                ) {

                    $('#leaveBalance').text(

                        res.data.remainingLeaves ?? 0

                    );

                } else {

                    $('#leaveBalance').text('--');

                }

                if (
                    typeof callback === 'function'
                ) {

                    callback();

                }

            }

        ).fail(function() {

            $('#leaveBalance').text('--');

        });
    }

    // =========================
    // EVENTS
    // =========================
    $('#leaveType').on('change', function() {

        let id = $(this).val();
        updateDayTypeAvailability();

        if (!id) {
            $('#leaveBalance').text('--');
            return;
        }

        loadBalance(id, function() {
            validatePreview();
        });
    });

    $('#dayType').on('change', function() {
        syncDateInputsForDayType();
        validatePreview();
    });

    $('#fromDate').on('change', function() {
        syncDateInputsForDayType();
        validatePreview();
    });

    $('#toDate').on('change', function() {
        validatePreview();
    });

    // =========================
    // APPLY LEAVE
    // =========================
    $('#submitLeaveBtn').on('click', function() {

        let payload = {
            leaveTypeId: $('#leaveType').val(),
            dayType: $('#dayType').val(),
            fromDate: $('#fromDate').val(),
            toDate: getEffectiveToDate(),
            reason: $('#reason').val().trim()
        };

        if (!payload.leaveTypeId)
            return showToast('warning', 'Select leave type');

        if (!payload.fromDate)
            return showToast('warning', 'Select from date');

        if (payload.fromDate > payload.toDate)
            return showToast('warning', 'Invalid date range');

        if (payload.dayType === 'half' && payload.fromDate !== payload.toDate)
            return showToast('warning', 'Half day leave can be applied for one date only');

        if (!payload.reason)
            return showToast('warning', 'Reason required');

        $.ajax({

            url: API.applyLeave,

            type: 'POST',

            contentType: 'application/json',

            data: JSON.stringify(payload),

            dataType: 'json',

            beforeSend: function() {

                $('#submitLeaveBtn')
                    .prop('disabled', true);

            },

            success: function(res) {

                showToast(

                    res.success ?
                    'success' :
                    'danger',

                    res.message
                );

                if (!res.success) {
                    return;
                }

                $('#applyLeaveModal').modal('hide');

                $('#reason').val('');

                $('#fromDate').val('');

                $('#toDate').val('');

                $('#leaveDays').text('0');

                $('#leaveValidationMsg').html('');

                $('#leaveBalance').text('--');

                $('#leaveType').val('');
                $('#dayType').val('full');
                $('#toDate').prop('disabled', false);

                loadLeaves();

            },

            error: function() {

                showToast(
                    'danger',
                    'Server error occurred'
                );

            },

            complete: function() {

                $('#submitLeaveBtn')
                    .prop('disabled', false);

            }

        });

    });

    // =========================
    // CANCEL LEAVE
    // =========================
    $(document).on('click', '.cancelLeaveBtn', function() {

        let leaveId = $(this).data('id');

        Swal.fire({
            title: 'Cancel Leave?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33'
        }).then(result => {

            if (!result.isConfirmed) return;

            $.ajax({

                url: API.cancelLeave,

                type: 'POST',

                contentType: 'application/json',

                data: JSON.stringify({

                    leaveId: leaveId

                }),

                dataType: 'json',

                success: function(res) {

                    showToast(

                        res.success ?
                        'success' :
                        'danger',

                        res.message

                    );

                    if (res.success) {

                        loadLeaves();

                    }

                },

                error: function() {

                    showToast(

                        'danger',

                        'Unable to cancel leave'

                    );

                }

            });

        });
    });

    $(document).on('click', '.export-btn', function() {

        let type = $(this).data('type');

        if (type === 'csv') {

            table.buttons('.buttons-csv').trigger();

        }

        if (type === 'pdf') {

            table.buttons('.buttons-pdf').trigger();

        }

    });

    // =========================
    // INIT
    // =========================
    loadLeaveTypes();
    loadLeaves();

});
</script>
<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
