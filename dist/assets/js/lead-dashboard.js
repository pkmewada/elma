/* ==========================================================================
   Lead Dashboard — reads api/leads/get-dashboard-filters.php (dropdowns,
   loaded once) and api/leads/get-dashboard-summary.php (everything else,
   re-fetched on every filter change). All data comes from existing tables
   (leads, leadFollowUps, leadsActivityLogs) via includes/leadDashboardEngine.php
   — nothing here computes analytics client-side.
   ========================================================================== */
$(function () {

    var getFiltersUrl = API_BASE + '/leads/get-dashboard-filters.php';
    var getDashboardUrl = API_BASE + '/leads/get-dashboard-summary.php';

    var rangeFp = null;
    var charts = { trend: null, status: null, followUp: null, employee: null };

    var state = {
        rangeType: 'this_month',
        customFrom: null,
        customTo: null,
        employeeId: '',
        source: ''
    };

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function notify(type, message) {
        if (typeof showToast === 'function') showToast(type, message);
    }

    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function prettifyStatus(status) {
        return String(status || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    // ----------------------------------------------------------------------
    // date range
    // ----------------------------------------------------------------------
    function currentRange() {
        var now = new Date();

        if (state.rangeType === 'last_month') {
            var first = new Date(now.getFullYear(), now.getMonth() - 1, 1);
            var last = new Date(now.getFullYear(), now.getMonth(), 0);
            return [toYMD(first), toYMD(last)];
        }

        if (state.rangeType === 'this_week') {
            var day = now.getDay();
            var diffToMonday = (day === 0 ? -6 : 1) - day;
            var monday = new Date(now);
            monday.setDate(now.getDate() + diffToMonday);
            var sunday = new Date(monday);
            sunday.setDate(monday.getDate() + 6);
            return [toYMD(monday), toYMD(sunday)];
        }

        if (state.rangeType === 'custom' && state.customFrom && state.customTo) {
            return [state.customFrom, state.customTo];
        }

        // default / this_month
        var first = new Date(now.getFullYear(), now.getMonth(), 1);
        var last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return [toYMD(first), toYMD(last)];
    }

    function toggleCustomRangeInput() {
        $('#ldCustomRangeWrap').toggleClass('d-none', state.rangeType !== 'custom');
    }

    // ----------------------------------------------------------------------
    // filter dropdowns (loaded once)
    // ----------------------------------------------------------------------
    function loadFilters() {
        $.getJSON(getFiltersUrl, function (response) {
            if (!response.success) {
                notify('danger', response.message || 'Unable to load dashboard filters.');
                return;
            }

            var employees = response.data.employees || [];
            var sources = response.data.sources || [];

            $('#ldEmployee').append(employees.map(function (e) {
                return '<option value="' + e.id + '">' + esc(e.fullName) + '</option>';
            }).join(''));

            $('#ldSource').append(sources.map(function (s) {
                return '<option value="' + esc(s) + '">' + esc(s) + '</option>';
            }).join(''));
        }).fail(function () {
            notify('danger', 'Unable to load dashboard filters.');
        });
    }

    // ----------------------------------------------------------------------
    // main data load + render
    // ----------------------------------------------------------------------
    function loadDashboard() {
        var range = currentRange();

        $.getJSON(getDashboardUrl, {
            employeeId: state.employeeId,
            source: state.source,
            dateFrom: range[0],
            dateTo: range[1]
        }, function (response) {
            if (!response.success) {
                notify('danger', response.message || 'Unable to load dashboard data.');
                return;
            }

            var data = response.data;
            renderSummary(data.summary);
            renderLeadTrend(data.leadTrend);
            renderStatusChart(data.statusDistribution);
            renderFollowUpChart(data.followUpPerformance);
            renderEmployeeChart(data.employeePerformance);
            renderRecentTable(data.recentFollowUps);
        }).fail(function () {
            notify('danger', 'Unable to load dashboard data.');
        });
    }

    function renderSummary(summary) {
        $('#ldTotalLeads').text(summary.totalLeads ?? 0);
        $('#ldNewLeads').text(summary.newLeads ?? 0);
        $('#ldPendingFollowUps').text(summary.pendingFollowUps ?? 0);
        $('#ldCompletedFollowUps').text(summary.completedFollowUps ?? 0);
        $('#ldConvertedLeads').text(summary.convertedLeads ?? 0);
    }

    function renderLeadTrend(trend) {
        var options = {
            series: [{ name: 'Leads', data: (trend || []).map(function (d) { return d.count; }) }],
            chart: { height: 320, type: 'area', toolbar: { show: false }, zoom: { enabled: false } },
            colors: ['var(--primary-color)'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.05 } },
            grid: { borderColor: 'var(--default-border)' },
            xaxis: {
                categories: (trend || []).map(function (d) { return d.date; }),
                labels: { style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600 } }
            },
            yaxis: {
                labels: { formatter: function (v) { return Math.round(v); }, style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600 } }
            },
            tooltip: { shared: true, intersect: false }
        };

        if (!charts.trend) {
            charts.trend = new ApexCharts(document.querySelector('#ldLeadTrendChart'), options);
            charts.trend.render();
        } else {
            charts.trend.updateOptions(options);
        }
    }

    function renderStatusChart(distribution) {
        var palette = ['#0dcaf0', 'var(--primary-color)', 'rgb(var(--success-rgb))', 'rgb(var(--danger-rgb))', 'rgb(var(--secondary-rgb))', '#f4a742'];
        var rows = distribution || [];

        var options = {
            series: rows.map(function (d) { return d.count; }),
            labels: rows.map(function (d) { return prettifyStatus(d.status); }),
            chart: { type: 'donut', height: 320 },
            colors: palette,
            legend: { position: 'bottom' },
            dataLabels: { dropShadow: { enabled: false } }
        };

        if (!charts.status) {
            charts.status = new ApexCharts(document.querySelector('#ldStatusChart'), options);
            charts.status.render();
        } else {
            charts.status.updateOptions(options);
        }
    }

    function renderFollowUpChart(performance) {
        performance = performance || { Pending: 0, Completed: 0, Skipped: 0 };
        var categories = ['Pending', 'Completed', 'Skipped'];

        var options = {
            series: [{ name: 'Follow Ups', data: categories.map(function (c) { return performance[c] || 0; }) }],
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 4, distributed: true, columnWidth: '45%' } },
            colors: ['rgb(var(--warning-rgb))', 'rgb(var(--success-rgb))', 'rgb(var(--secondary-rgb))'],
            legend: { show: false },
            dataLabels: { enabled: true },
            grid: { borderColor: 'var(--default-border)' },
            xaxis: {
                categories: categories,
                labels: { style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600 } }
            },
            yaxis: {
                labels: { formatter: function (v) { return Math.round(v); }, style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600 } }
            }
        };

        if (!charts.followUp) {
            charts.followUp = new ApexCharts(document.querySelector('#ldFollowUpChart'), options);
            charts.followUp.render();
        } else {
            charts.followUp.updateOptions(options);
        }
    }

    function renderEmployeeChart(rows) {
        rows = rows || [];
        var hasData = rows.length > 0;

        $('#ldEmployeeChartEmpty').toggleClass('d-none', hasData);
        $('#ldEmployeeChart').toggleClass('d-none', !hasData);

        if (!hasData) {
            if (charts.employee) { charts.employee.destroy(); charts.employee = null; }
            return;
        }

        var options = {
            series: [
                { name: 'Assigned Leads', data: rows.map(function (r) { return r.assignedLeads; }) },
                { name: 'Pending Follow Ups', data: rows.map(function (r) { return r.pendingFollowUps; }) },
                { name: 'Completed Follow Ups', data: rows.map(function (r) { return r.completedFollowUps; }) }
            ],
            chart: { type: 'bar', height: Math.max(320, rows.length * 60), toolbar: { show: false } },
            plotOptions: { bar: { horizontal: true, borderRadius: 3, columnWidth: '55%' } },
            colors: ['var(--primary-color)', 'rgb(var(--warning-rgb))', 'rgb(var(--success-rgb))'],
            dataLabels: { enabled: false },
            grid: { borderColor: 'var(--default-border)' },
            legend: { position: 'top', horizontalAlign: 'left' },
            xaxis: {
                categories: rows.map(function (r) { return r.employeeName; }),
                labels: { style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600 } }
            }
        };

        if (!charts.employee) {
            charts.employee = new ApexCharts(document.querySelector('#ldEmployeeChart'), options);
            charts.employee.render();
        } else {
            charts.employee.updateOptions(options);
        }
    }

    function fmtDate(dateStr) {
        if (!dateStr) return '—';
        var d = new Date(dateStr + 'T00:00:00');
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function renderRecentTable(rows) {
        rows = rows || [];

        if (!rows.length) {
            $('#ldRecentTableBody').html(
                '<tr><td colspan="6" class="text-center text-muted py-3">No follow up activity matches the current filters.</td></tr>'
            );
            return;
        }

        var html = '';
        rows.forEach(function (row) {
            html += '<tr>' +
                '<td>' + esc(row.leadName) + '</td>' +
                '<td>' + esc(row.employeeName) + '</td>' +
                '<td>' + fmtDate(row.dueDate) + '</td>' +
                '<td>' + esc(row.followUpType) + '</td>' +
                '<td><span class="ld-badge ' + esc(row.status) + '">' + esc(row.status) + '</span></td>' +
                '<td class="text-muted fs-12">' + esc(row.lastAction) + '</td>' +
            '</tr>';
        });

        $('#ldRecentTableBody').html(html);
    }

    // ----------------------------------------------------------------------
    // events
    // ----------------------------------------------------------------------
    $('#ldRangeType').on('change', function () {
        state.rangeType = $(this).val();
        toggleCustomRangeInput();
        if (state.rangeType !== 'custom' || (state.customFrom && state.customTo)) {
            loadDashboard();
        }
    });

    $('#ldEmployee').on('change', function () {
        state.employeeId = $(this).val();
        loadDashboard();
    });

    $('#ldSource').on('change', function () {
        state.source = $(this).val();
        loadDashboard();
    });

    rangeFp = flatpickr('#ldCustomRange', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd M Y',
        onClose: function (selectedDates) {
            if (!selectedDates.length) return;
            state.customFrom = toYMD(selectedDates[0]);
            state.customTo = selectedDates.length > 1 ? toYMD(selectedDates[1]) : state.customFrom;
            loadDashboard();
        }
    });

    // ----------------------------------------------------------------------
    // init
    // ----------------------------------------------------------------------
    loadFilters();
    loadDashboard();

});
