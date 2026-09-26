/* ==========================================================================
   My Assets (employee-facing) — reads/writes api/assets/emp-*.php.
   Employee identity always comes from the server-side session
   ($_SESSION['candidateId']); nothing here ever sends an employeeId.
   Reuses the existing asset assignment data/model via
   AssetAssignmentModel::getHistoryByEmployee() and the existing
   getAvailableAssets.php list (same one the admin "Assign Asset" modal uses).
   ========================================================================== */
$(function () {

    var getMyAssetsUrl = API_BASE + '/assets/emp-getMyAssets.php';
    var getMyRequestsUrl = API_BASE + '/assets/emp-getMyAssetRequests.php';
    var getAvailableAssetsUrl = API_BASE + '/assets/getAvailableAssets.php';
    var requestAssetUrl = API_BASE + '/assets/emp-requestAsset.php';

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function assetStatusBadge(status) {
        var cls = 'status-chip-info';
        if (status === 'assigned') cls = 'status-chip-warning';
        if (status === 'returned') cls = 'status-chip-success';
        if (status === 'overdue') cls = 'status-chip-danger';
        return '<span class="status-chip ' + cls + '">' + esc(status) + '</span>';
    }

    function requestStatusBadge(status) {
        var cls = 'status-chip-info';
        if (status === 'pending') cls = 'status-chip-warning';
        if (status === 'approved') cls = 'status-chip-success';
        if (status === 'rejected') cls = 'status-chip-danger';
        return '<span class="status-chip ' + cls + '">' + esc(status) + '</span>';
    }

    function loadMyAssets() {
        $.getJSON(getMyAssetsUrl, function (response) {
            if (!response.success || !response.data.length) {
                $('#myAssetsTableBody').html('<tr><td colspan="8" class="text-center text-muted py-3">No assets assigned to you yet.</td></tr>');
                return;
            }

            var html = '';
            response.data.forEach(function (row) {
                var returnInfo = '—';
                if (row.status === 'returned') {
                    var parts = [];
                    if (row.actualReturnDate) parts.push('Returned ' + fmtDate(row.actualReturnDate));
                    if (row.returnCondition) parts.push('Condition: ' + esc(row.returnCondition));
                    if (row.remarks) parts.push(esc(row.remarks));
                    returnInfo = parts.length ? parts.join('<br>') : '—';
                }

                html += '<tr>' +
                    '<td>' + esc(row.assetCode) + ' - ' + esc(row.assetName) + '</td>' +
                    '<td>' + esc(row.categoryName || '-') + '</td>' +
                    '<td>' + esc(row.quantity) + '</td>' +
                    '<td>' + fmtDate(row.assignedDate) + '</td>' +
                    '<td>' + esc(row.purpose || '-') + '</td>' +
                    '<td>' + fmtDate(row.expectedReturnDate) + '</td>' +
                    '<td>' + assetStatusBadge(row.status) + '</td>' +
                    '<td>' + returnInfo + '</td>' +
                '</tr>';
            });
            $('#myAssetsTableBody').html(html);
        }).fail(function () {
            $('#myAssetsTableBody').html('<tr><td colspan="8" class="text-center text-danger py-3">Unable to load your assets.</td></tr>');
        });
    }

    function loadMyRequests() {
        $.getJSON(getMyRequestsUrl, function (response) {
            if (!response.success || !response.data.length) {
                $('#myRequestsTableBody').html('<tr><td colspan="7" class="text-center text-muted py-3">You have not requested any assets yet.</td></tr>');
                return;
            }

            var html = '';
            response.data.forEach(function (row) {
                html += '<tr>' +
                    '<td>' + esc(row.assetCode) + ' - ' + esc(row.assetName) + '</td>' +
                    '<td>' + esc(row.quantity) + '</td>' +
                    '<td>' + esc(row.purpose || '-') + '</td>' +
                    '<td>' + fmtDate(row.expectedReturnDate) + '</td>' +
                    '<td>' + fmtDate((row.createdAt || '').split(' ')[0]) + '</td>' +
                    '<td>' + requestStatusBadge(row.status) + '</td>' +
                    '<td>' + esc(row.status === 'rejected' ? (row.rejectionRemark || '-') : '-') + '</td>' +
                '</tr>';
            });
            $('#myRequestsTableBody').html(html);
        }).fail(function () {
            $('#myRequestsTableBody').html('<tr><td colspan="7" class="text-center text-danger py-3">Unable to load your requests.</td></tr>');
        });
    }

    $('#requestAssetModal').on('show.bs.modal', function () {
        $.getJSON(getAvailableAssetsUrl, function (res) {
            if (!res.success) return;
            var html = '<option value="">Select Asset</option>';
            res.data.forEach(function (a) {
                html += '<option value="' + a.id + '">' + esc(a.assetCode) + ' - ' + esc(a.assetName) + '</option>';
            });
            $('#requestAssetDropdown').html(html);
        });
    });

    $('#requestAssetForm').on('submit', function (e) {
        e.preventDefault();

        var $btn = $('#submitRequestBtn');
        var $spinner = $('#submitRequestSpinner');
        var $text = $('#submitRequestText');

        $btn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $text.text('Submitting...');

        $.ajax({
            url: requestAssetUrl,
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (res) {
            if (!res.success) {
                showToast('danger', res.message || 'Failed to submit request.');
                return;
            }
            $('#requestAssetModal').modal('hide');
            $('#requestAssetForm')[0].reset();
            showToast('success', res.message || 'Asset request submitted.');
            loadMyRequests();
        }).fail(function () {
            showToast('danger', 'Server error.');
        }).always(function () {
            $btn.prop('disabled', false);
            $spinner.addClass('d-none');
            $text.text('Submit Request');
        });
    });

    loadMyAssets();
    loadMyRequests();

});
