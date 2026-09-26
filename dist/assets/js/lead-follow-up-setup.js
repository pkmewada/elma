/* ==========================================================================
   Follow Up Setup — CRUD for leadFollowUpSettings, backed by
   api/leads/{get,save,delete}LeadFollowUpSetting.php.
   ========================================================================== */
$(function () {

    var getSettingsUrl = API_BASE + '/leads/getLeadFollowUpSettings.php';
    var saveSettingUrl = API_BASE + '/leads/saveLeadFollowUpSetting.php';
    var deleteSettingUrl = API_BASE + '/leads/deleteLeadFollowUpSetting.php';

    var rules = [];

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function notify(type, message) {
        if (typeof showToast === 'function') showToast(type, message);
    }

    function loadRules() {
        $.getJSON(getSettingsUrl, function (response) {
            if (!response.success) {
                notify('danger', response.message || 'Unable to load follow up rules.');
                return;
            }
            rules = response.data || [];
            renderRules();
        }).fail(function () {
            notify('danger', 'Unable to load follow up rules.');
        });
    }

    function renderRules() {
        if (!rules.length) {
            $('#followUpRulesTableBody').html(
                '<tr><td colspan="5" class="text-center text-muted py-3">No follow up rules configured yet.</td></tr>'
            );
            return;
        }

        var html = '';
        rules.forEach(function (rule) {
            html += '<tr>' +
                '<td>' + rule.dayNumber + ' Day' + (rule.dayNumber == 1 ? '' : 's') + '</td>' +
                '<td>Follow Up ' + rule.followUpSequence + '</td>' +
                '<td>' + esc(rule.followUpType) + '</td>' +
                '<td><span class="btn btn-sm ' + (rule.isActive ? 'btn-outline-success' : 'btn-outline-danger') + '">' +
                    (rule.isActive ? 'Active' : 'Inactive') +
                '</span></td>' +
                '<td>' +
                    '<button type="button" class="btn btn-sm btn-outline-primary edit-rule-btn" data-id="' + rule.id + '">' +
                        '<i class="ri-pencil-line"></i>' +
                    '</button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-danger delete-rule-btn" data-id="' + rule.id + '">' +
                        '<i class="ri-delete-bin-line"></i>' +
                    '</button>' +
                '</td>' +
            '</tr>';
        });

        $('#followUpRulesTableBody').html(html);
    }

    function resetForm() {
        $('#followUpRuleId').val('');
        $('#followUpDayNumber').val('');
        $('#followUpSequence').val('');
        $('#followUpType').val('Call');
        $('#followUpRuleStatus').val('1');
    }

    $('#addFollowUpRuleBtn').on('click', function () {
        $('#followUpRuleModalTitle').text('Add Follow Up Rule');
        resetForm();
        $('#followUpRuleModal').modal('show');
    });

    $('#followUpRulesTableBody').on('click', '.edit-rule-btn', function () {
        var id = $(this).data('id');
        var rule = rules.find(function (r) { return String(r.id) === String(id); });
        if (!rule) return;

        $('#followUpRuleModalTitle').text('Edit Follow Up Rule');
        $('#followUpRuleId').val(rule.id);
        $('#followUpDayNumber').val(rule.dayNumber);
        $('#followUpSequence').val(rule.followUpSequence);
        $('#followUpType').val(rule.followUpType);
        $('#followUpRuleStatus').val(String(rule.isActive));
        $('#followUpRuleModal').modal('show');
    });

    $('#followUpRulesTableBody').on('click', '.delete-rule-btn', function () {
        var id = $(this).data('id');

        if (!confirm('Delete this follow up rule? Leads already scheduled under it keep their existing follow ups.')) {
            return;
        }

        $.ajax({
            url: deleteSettingUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify({ id: id })
        }).done(function (response) {
            if (response.success) {
                notify('success', response.message || 'Rule deleted successfully.');
                loadRules();
            } else {
                notify('danger', response.message || 'Failed to delete rule.');
            }
        }).fail(function () {
            notify('danger', 'Failed to delete rule.');
        });
    });

    $('#saveFollowUpRuleBtn').on('click', function () {
        var id = $('#followUpRuleId').val();
        var dayNumber = parseInt($('#followUpDayNumber').val(), 10);
        var followUpSequence = parseInt($('#followUpSequence').val(), 10);
        var followUpType = $('#followUpType').val();
        var isActive = $('#followUpRuleStatus').val();

        if (!dayNumber || dayNumber <= 0) {
            notify('danger', 'Enter a valid day number.');
            return;
        }
        if (!followUpSequence || followUpSequence <= 0) {
            notify('danger', 'Enter a valid follow up number.');
            return;
        }

        var payload = {
            dayNumber: dayNumber,
            followUpSequence: followUpSequence,
            followUpType: followUpType,
            isActive: isActive
        };
        if (id) payload.id = id;

        var $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: saveSettingUrl,
            type: 'POST',
            contentType: 'application/json',
            dataType: 'json',
            data: JSON.stringify(payload)
        }).done(function (response) {
            if (response.success) {
                notify('success', response.message || 'Rule saved successfully.');
                $('#followUpRuleModal').modal('hide');
                loadRules();
            } else {
                notify('danger', response.message || 'Failed to save rule.');
            }
        }).fail(function () {
            notify('danger', 'Failed to save rule.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    loadRules();

});
