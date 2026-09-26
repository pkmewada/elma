/* ==========================================================================
   Walk-in Candidates — reads/writes api/walkin/*.php (WalkInCandidateEngine).
   "Handled By" reuses the existing api/employee/getEmployees.php dropdown
   source, same as the Asset Management "Assign" modal.
   ========================================================================== */
$(function () {

    var getListUrl = API_BASE + '/walkin/getWalkInCandidates.php';
    var saveUrl = API_BASE + '/walkin/saveWalkInCandidate.php';
    var updateStatusUrl = API_BASE + '/walkin/updateWalkInCandidateStatus.php';
    var getEmployeesUrl = API_BASE + '/employee/getEmployees.php';

    var rows = [];

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function notify(type, message) {
        if (typeof showToast === 'function') showToast(type, message);
        else if (window.Swal) Swal.fire({ icon: type === 'danger' ? 'error' : type, text: message });
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function statusBadgeClass(status) {
        if (status === 'Selected') return 'btn-outline-success';
        if (status === 'Completed') return 'btn-outline-info';
        if (status === 'Rejected') return 'btn-outline-danger';
        return 'btn-outline-warning';
    }

    function loadEmployees() {
        $.getJSON(getEmployeesUrl, function (res) {
            if (!res.success) return;
            var html = '<option value="">Select Employee</option>';
            (res.data || []).forEach(function (e) {
                html += '<option value="' + e.id + '">' + esc(e.fullName) + '</option>';
            });
            $('#wicHandledBy').html(html);
        });
    }

    function currentFilters() {
        return {
            status: $('#wicStatus').val(),
            dateFrom: $('#wicDateFrom').val(),
            dateTo: $('#wicDateTo').val(),
            search: $('#wicSearch').val().trim()
        };
    }

    function loadList() {
        $('#wicTableBody').html('<tr><td colspan="8" class="text-center text-muted py-3">Loading...</td></tr>');

        $.getJSON(getListUrl, currentFilters(), function (response) {
            if (!response.success) {
                notify('danger', response.message || 'Unable to load walk-in candidates.');
                rows = [];
                renderList();
                return;
            }
            rows = response.data || [];
            renderList();
        }).fail(function () {
            notify('danger', 'Unable to load walk-in candidates.');
            rows = [];
            renderList();
        });
    }

    function renderList() {
        if (!rows.length) {
            $('#wicTableBody').html(
                '<tr><td colspan="8" class="text-center text-muted py-3">No walk-in candidates match the current filters.</td></tr>'
            );
            return;
        }

        var html = '';
        rows.forEach(function (row) {
            html += '<tr>' +
                '<td>' +
                    '<div class="fw-semibold">' + esc(row.candidateName) + '</div>' +
                    (row.email ? '<div class="text-muted small">' + esc(row.email) + '</div>' : '') +
                '</td>' +
                '<td>' + esc(row.phone) + '</td>' +
                '<td>' + esc(row.positionApplied) + '</td>' +
                '<td>' + fmtDate(row.interviewDate) + (row.interviewTime ? ' <span class="text-muted small">' + esc(row.interviewTime.substring(0, 5)) + '</span>' : '') + '</td>' +
                '<td>' + esc(row.source || '—') + '</td>' +
                '<td>' +
                    '<select class="form-select form-select-sm wic-status-select ' + statusBadgeClass(row.status) + '" data-id="' + row.id + '">' +
                        ['Scheduled', 'Completed', 'Rejected', 'Selected'].map(function (s) {
                            return '<option value="' + s + '"' + (s === row.status ? ' selected' : '') + '>' + s + '</option>';
                        }).join('') +
                    '</select>' +
                '</td>' +
                '<td>' + esc(row.handledByName) + '</td>' +
                '<td>' +
                    '<button type="button" class="btn btn-sm btn-outline-primary edit-wic-btn" data-id="' + row.id + '" title="Edit">' +
                        '<i class="ri-pencil-line"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        });

        $('#wicTableBody').html(html);
    }

    function resetForm() {
        $('#wicId').val('');
        $('#wicCandidateName').val('');
        $('#wicPhone').val('');
        $('#wicEmail').val('');
        $('#wicPositionApplied').val('');
        $('#wicInterviewDate').val('');
        $('#wicInterviewTime').val('');
        $('#wicSource').val('');
        $('#wicStatusInput').val('Scheduled');
        $('#wicHandledBy').val('');
        $('#wicRemarks').val('');
    }

    $('#addWalkInBtn').on('click', function () {
        $('#wicModalTitle').text('Add Walk-in Candidate');
        resetForm();
        $('#wicModal').modal('show');
    });

    $('#wicTableBody').on('click', '.edit-wic-btn', function () {
        var id = $(this).data('id');
        var row = rows.find(function (r) { return String(r.id) === String(id); });
        if (!row) return;

        $('#wicModalTitle').text('Edit Walk-in Candidate');
        $('#wicId').val(row.id);
        $('#wicCandidateName').val(row.candidateName);
        $('#wicPhone').val(row.phone);
        $('#wicEmail').val(row.email || '');
        $('#wicPositionApplied').val(row.positionApplied);
        $('#wicInterviewDate').val(row.interviewDate);
        $('#wicInterviewTime').val(row.interviewTime ? row.interviewTime.substring(0, 5) : '');
        $('#wicSource').val(row.source || '');
        $('#wicStatusInput').val(row.status);
        $('#wicHandledBy').val(row.handledByCandidateId || '');
        $('#wicRemarks').val(row.remarks || '');
        $('#wicModal').modal('show');
    });

    $('#saveWicBtn').on('click', function () {
        var candidateName = $('#wicCandidateName').val().trim();
        var phone = $('#wicPhone').val().trim();
        var positionApplied = $('#wicPositionApplied').val().trim();
        var interviewDate = $('#wicInterviewDate').val();

        if (!candidateName) { notify('danger', 'Candidate name is required.'); return; }
        if (!phone) { notify('danger', 'Phone number is required.'); return; }
        if (!positionApplied) { notify('danger', 'Position applied for is required.'); return; }
        if (!interviewDate) { notify('danger', 'Interview date is required.'); return; }

        var payload = {
            candidateName: candidateName,
            phone: phone,
            email: $('#wicEmail').val().trim(),
            positionApplied: positionApplied,
            interviewDate: interviewDate,
            interviewTime: $('#wicInterviewTime').val(),
            source: $('#wicSource').val().trim(),
            status: $('#wicStatusInput').val(),
            handledByCandidateId: $('#wicHandledBy').val(),
            remarks: $('#wicRemarks').val().trim()
        };

        var id = $('#wicId').val();
        if (id) payload.id = id;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: saveUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload)
        }).done(function (response) {
            if (response.success) {
                notify('success', response.message || 'Candidate saved successfully.');
                $('#wicModal').modal('hide');
                loadList();
            } else {
                notify('danger', response.message || 'Failed to save candidate.');
            }
        }).fail(function () {
            notify('danger', 'Failed to save candidate.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $('#wicTableBody').on('change', '.wic-status-select', function () {
        var id = $(this).data('id');
        var status = $(this).val();
        var $select = $(this);

        $.ajax({
            url: updateStatusUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ id: id, status: status })
        }).done(function (response) {
            if (response.success) {
                notify('success', 'Status updated.');
                $select.removeClass('btn-outline-success btn-outline-info btn-outline-danger btn-outline-warning')
                    .addClass(statusBadgeClass(status));
                var row = rows.find(function (r) { return String(r.id) === String(id); });
                if (row) row.status = status;
            } else {
                notify('danger', response.message || 'Failed to update status.');
                loadList();
            }
        }).fail(function () {
            notify('danger', 'Failed to update status.');
            loadList();
        });
    });

    $('#wicStatus, #wicDateFrom, #wicDateTo').on('change', loadList);

    var searchTimer = null;
    $('#wicSearch').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadList, 300);
    });

    $('#wicResetBtn').on('click', function () {
        $('#wicStatus').val('');
        $('#wicDateFrom').val('');
        $('#wicDateTo').val('');
        $('#wicSearch').val('');
        loadList();
    });

    loadEmployees();
    loadList();

});
