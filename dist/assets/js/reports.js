/* ==========================================================================
   Reports — one DataTable, two report types (Leads | Follow-ups), reusing
   the existing Lead APIs (no dedicated reports endpoint):
     Leads     -> api/leads/getLeads.php (projectId/sourceId/employeeId/
                  status/dateFrom/dateTo + a "reason" column for
                  Converted/Lost, already returned by the API).
     Follow-ups -> api/leads/getLeadFollowUps.php (view=all + the same
                  filters, follow-up Status is Pending/Completed/Skipped).
   Filter options -> api/leads/getLeadMasterData.php (projects/sources/
   assignees already used by the Leads page). Same DataTables/export
   pattern as dist/assets/js/lead.js (csvHtml5/excelHtml5/pdfHtml5).
   ========================================================================== */
$(function () {
    var LEAD_STATUS_OPTIONS = $('#reportStatus').html();
    var FOLLOWUP_STATUS_OPTIONS = '<option value="">All Status</option>' +
        '<option value="Pending">Pending</option>' +
        '<option value="Completed">Completed</option>' +
        '<option value="Skipped">Skipped</option>';

    var table = null;
    var reportType = 'leads';

    function esc(v) { return $('<div>').text(v == null ? '' : String(v)).html(); }
    function notify(t, m) { if (typeof showToast === 'function') showToast(t, m); }

    function formatStatusLabel(status) {
        return String(status || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function formatDateTime(value) {
        if (!value) return '-';
        var dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(String(value));
        var d = new Date(dateOnly ? value + 'T00:00:00' : String(value).replace(' ', 'T'));
        if (isNaN(d.getTime())) return value;
        var opts = { day: '2-digit', month: 'short', year: 'numeric' };
        if (!dateOnly) { opts.hour = '2-digit'; opts.minute = '2-digit'; opts.hour12 = true; }
        return d.toLocaleString('en-GB', opts);
    }

    // ---------------------------------------------------------------------
    // filter options (loaded once)
    // ---------------------------------------------------------------------
    $.getJSON(API_BASE + '/leads/getLeadMasterData.php', function (res) {
        if (!res.success) return;
        var d = res.data;
        $('#reportProject').append((d.projects || []).map(function (p) { return '<option value="' + p.id + '">' + esc(p.projectName) + '</option>'; }).join(''));
        $('#reportSource').append((d.sources || []).map(function (s) { return '<option value="' + s.id + '">' + esc(s.sourceName) + '</option>'; }).join(''));
        // Assignees list is admin/assign-permission only; if empty (e.g. a
        // manager without assign_lead), just leave "All Salespeople".
        $('#reportEmployee').append((d.assignees || []).map(function (a) { return '<option value="' + a.id + '">' + esc(a.fullName) + '</option>'; }).join(''));
    });

    var range = { from: '', to: '' };
    flatpickr('#reportDateRange', {
        mode: 'range', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y',
        onClose: function (dates, _, instance) {
            range.from = dates.length ? instance.formatDate(dates[0], 'Y-m-d') : '';
            range.to = dates.length > 1 ? instance.formatDate(dates[1], 'Y-m-d') : range.from;
            load();
        }
    });

    // ---------------------------------------------------------------------
    // table (rebuilt per report type -- columns differ)
    // ---------------------------------------------------------------------
    var COLUMN_SETS = {
        leads: {
            title: 'Lead Report',
            headers: ['Customer', 'Project', 'Source', 'Assigned To', 'Status', 'Next Follow-up', 'Reason', 'Created At'],
            exportCols: [0, 1, 2, 3, 4, 5, 6, 7],
            columns: [
                { data: 'fullName', render: function (d, t, row) {
                    if (t === 'export') return [row.fullName, row.phone].filter(Boolean).join(' | ');
                    return esc(d) + '<small class="d-block text-muted">' + esc((row.countryCode || '') + ' ' + row.phone) + '</small>';
                } },
                { data: 'projectName', render: function (d) { return d ? esc(d) : '<span class="text-muted">-</span>'; } },
                { data: 'source', render: function (d) { return esc(d || '-'); } },
                { data: 'assignedToName', render: function (d) { return d ? esc(d) : '<span class="text-muted">Unassigned</span>'; } },
                { data: 'status', render: function (d, t) { return t === 'display' ? '<span class="badge lead-status-' + d + '">' + esc(formatStatusLabel(d)) + '</span>' : formatStatusLabel(d); } },
                { data: 'nextFollowUp', render: function (d) { return d ? formatDateTime(d) : '-'; } },
                { data: 'reason', render: function (d) { return d ? esc(d) : '-'; } },
                { data: 'createdAt', render: function (d) { return formatDateTime(d); } },
            ],
        },
        followups: {
            title: 'Follow-up Report',
            headers: ['Due', 'Lead', 'Project', 'Assigned To', 'Type', 'Status', 'Remark'],
            exportCols: [0, 1, 2, 3, 4, 5, 6],
            columns: [
                { data: 'dueDate', render: function (d, t, row) { return formatDateTime(row.dueTime ? d + ' ' + row.dueTime : d); } },
                { data: 'leadName', render: function (d, t, row) {
                    if (t === 'export') return [d, row.phone].filter(Boolean).join(' | ');
                    return esc(d) + '<small class="d-block text-muted">' + esc((row.countryCode || '') + ' ' + row.phone) + '</small>';
                } },
                { data: 'projectName', render: function (d) { return d ? esc(d) : '<span class="text-muted">-</span>'; } },
                { data: 'assignedToName', render: function (d) { return esc(d); } },
                { data: 'followUpType', render: function (d) { return esc(d); } },
                { data: 'status', render: function (d) { return '<span class="badge bg-light text-dark">' + esc(d) + '</span>'; } },
                { data: 'remark', render: function (d) { return d ? esc(d) : '-'; } },
            ],
        },
    };

    function buildTable() {
        if (table) { table.destroy(); $('#reportsTable').empty(); }
        var set = COLUMN_SETS[reportType];
        $('#reportTitle').text(set.title);
        $('#reportsTable').html('<thead><tr>' + set.headers.map(function (h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead><tbody></tbody>');

        table = $('#reportsTable').DataTable(window.ModlusUI.withDataTableDefaults({
            data: [],
            columns: set.columns,
            dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            pageLength: 25,
            language: { emptyTable: 'No records match the current filters.' },
            buttons: [
                { extend: 'csvHtml5', className: 'd-none buttons-csv', title: set.title, exportOptions: { columns: set.exportCols, orthogonal: 'export' } },
                { extend: 'excelHtml5', className: 'd-none buttons-excel', title: set.title, exportOptions: { columns: set.exportCols, orthogonal: 'export' } },
                { extend: 'pdfHtml5', className: 'd-none buttons-pdf', title: set.title, orientation: 'landscape', exportOptions: { columns: set.exportCols, orthogonal: 'export' } },
            ],
        }));
    }

    function load() {
        var status = $('#reportStatus').val();
        var common = {
            dateFrom: range.from, dateTo: range.to,
            projectId: $('#reportProject').val(),
            employeeId: $('#reportEmployee').val(),
        };

        table.settings()[0].oLanguage.sEmptyTable = 'Loading...';
        table.clear().draw();

        if (reportType === 'leads') {
            $.getJSON(API_BASE + '/leads/getLeads.php', $.extend({}, common, { sourceId: $('#reportSource').val(), status: status }))
                .done(function (res) {
                    if (!res.success) { fail(res.message); return; }
                    table.settings()[0].oLanguage.sEmptyTable = 'No records match the current filters.';
                    table.clear().rows.add(res.data || []).draw();
                })
                .fail(function (xhr) { fail(xhr.responseJSON && xhr.responseJSON.message); });
        } else {
            $.getJSON(API_BASE + '/leads/getLeadFollowUps.php', $.extend({}, common, { view: 'all', status: status }))
                .done(function (res) {
                    if (!res.success) { fail(res.message); return; }
                    table.settings()[0].oLanguage.sEmptyTable = 'No records match the current filters.';
                    table.clear().rows.add(res.data || []).draw();
                })
                .fail(function (xhr) { fail(xhr.responseJSON && xhr.responseJSON.message); });
        }
    }

    function fail(message) {
        table.settings()[0].oLanguage.sEmptyTable = message || 'Unable to load report.';
        table.clear().draw();
        notify('danger', message || 'Unable to load report.');
    }

    $('#reportType').on('change', function () {
        reportType = $(this).val();
        $('#reportStatus').html(reportType === 'leads' ? LEAD_STATUS_OPTIONS : FOLLOWUP_STATUS_OPTIONS);
        // Lead Source has no matching filter on the Follow-up Report API.
        $('#reportSource').val('').prop('disabled', reportType !== 'leads').toggleClass('d-none', reportType !== 'leads');
        buildTable();
        load();
    });

    ['#reportProject', '#reportSource', '#reportEmployee', '#reportStatus'].forEach(function (sel) {
        $(sel).on('change', load);
    });

    $('#reportSearch').on('keyup', function () { table.search(this.value).draw(); });

    $('.export-btn').on('click', function () {
        table.button('.buttons-' + $(this).data('type')).trigger();
    });

    buildTable();
    load();
});
