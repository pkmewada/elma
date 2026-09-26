/* ==========================================================================
   Social Data Entry — Analytics modal.
   Reuses api/social-content/get-analytics.php (SocialContentEngine::getAnalytics()),
   which itself reuses getPlan()/getEntries() — same data as the main board,
   no separate module/page. Loaded only on pages/social-data-entry.php.
   ========================================================================== */
$(function () {

    if (!$('#sdeAnalyticsModal').length) return;

    let modal = null;
    let chart = null;
    let weekFp = null;

    const state = {
        rangeType: 'month' // 'month' | 'week' | 'custom'
    };

    // ----------------------------------------------------------------------
    // date helpers (local, no UTC shift — mirrors social-data-entry.js)
    // ----------------------------------------------------------------------
    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function currentMonthValue() {
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    }

    function daysInMonth(monthValue) {
        const [y, m] = monthValue.split('-').map(Number);
        return new Date(y, m, 0).getDate();
    }

    function monthRange(monthValue) {
        const [y, m] = monthValue.split('-').map(Number);
        const first = new Date(y, m - 1, 1);
        const last = new Date(y, m, 0);
        return [toYMD(first), toYMD(last)];
    }

    // weeks partitioned as flat 7-day blocks from the 1st (Week 1: 1-7,
    // Week 2: 8-14, ...) — simple and predictable, not calendar (Mon-Sun) weeks
    function weeksInMonth(monthValue) {
        const total = daysInMonth(monthValue);
        const weeks = [];
        let start = 1;
        let n = 1;
        while (start <= total) {
            const end = Math.min(start + 6, total);
            weeks.push({ n: n, start: start, end: end });
            start = end + 1;
            n++;
        }
        return weeks;
    }

    function weekRange(monthValue, weekNum) {
        const [y, m] = monthValue.split('-').map(Number);
        const week = weeksInMonth(monthValue).find(w => w.n === Number(weekNum)) || weeksInMonth(monthValue)[0];
        const from = new Date(y, m - 1, week.start);
        const to = new Date(y, m - 1, week.end);
        return [toYMD(from), toYMD(to)];
    }

    // ----------------------------------------------------------------------
    // filter UI
    // ----------------------------------------------------------------------
    function populateWeekOptions(monthValue) {
        const weeks = weeksInMonth(monthValue);
        $('#sdaWeek').html(weeks.map(w =>
            `<option value="${w.n}">Week ${w.n} (${w.start}–${w.end})</option>`
        ).join(''));
    }

    function toggleRangeInputs() {
        $('#sdaMonthWrap').toggleClass('d-none', state.rangeType !== 'month');
        $('#sdaWeekMonthWrap').toggleClass('d-none', state.rangeType !== 'week');
        $('#sdaWeekWrap').toggleClass('d-none', state.rangeType !== 'week');
        $('#sdaCustomWrap').toggleClass('d-none', state.rangeType !== 'custom');
    }

    function resetToDefaults() {
        state.rangeType = 'month';
        $('#sdaRangeType').val('month');

        const month = currentMonthValue();
        $('#sdaMonth').val(month);
        $('#sdaWeekMonth').val(month);
        populateWeekOptions(month);
        $('#sdaWeek').val('1');

        toggleRangeInputs();
    }

    function currentRange() {
        if (state.rangeType === 'week') {
            const month = $('#sdaWeekMonth').val() || currentMonthValue();
            return weekRange(month, $('#sdaWeek').val() || 1);
        }
        if (state.rangeType === 'custom') {
            const from = $('#sdaCustomRange').attr('data-from');
            const to = $('#sdaCustomRange').attr('data-to');
            if (from && to) return [from, to];
            return monthRange(currentMonthValue());
        }
        return monthRange($('#sdaMonth').val() || currentMonthValue());
    }

    // ----------------------------------------------------------------------
    // data + render
    // ----------------------------------------------------------------------
    function notify(type, message) {
        if (window.showToast) window.showToast(type, message);
    }

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function renderSummary(data) {
        $('#sdaTotalContent').text(data.totalContent ?? 0);
        $('#sdaCompletedContent').text(data.completedContent ?? 0);
        $('#sdaPendingContent').text(data.pendingContent ?? 0);
        $('#sdaTotalClients').text(data.totalClients ?? 0);
    }

    function chartOptions(chartData) {
        return {
            series: [
                { name: 'Total Content', data: chartData.map(d => d.total) },
                { name: 'Completed', data: chartData.map(d => d.completed) },
                { name: 'Pending', data: chartData.map(d => d.pending) }
            ],
            chart: {
                height: 340,
                type: 'line',
                toolbar: { show: false },
                zoom: { enabled: false }
            },
            colors: ['var(--primary-color)', 'rgb(var(--success-rgb))', 'rgb(var(--warning-rgb))'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 3 },
            markers: { size: 3 },
            grid: { borderColor: 'var(--default-border)' },
            legend: { position: 'top', horizontalAlign: 'left' },
            xaxis: {
                categories: chartData.map(d => d.date),
                labels: {
                    style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600, cssClass: 'apexcharts-xaxis-label' }
                }
            },
            yaxis: {
                title: { text: 'Content Count' },
                labels: {
                    formatter: v => Math.round(v),
                    style: { colors: '#8c9097', fontSize: '11px', fontWeight: 600, cssClass: 'apexcharts-yaxis-label' }
                }
            },
            tooltip: { shared: true, intersect: false }
        };
    }

    function renderChart(chartData) {
        const hasData = chartData && chartData.length > 0;
        $('#sdaChartEmpty').toggleClass('d-none', hasData);
        $('#sdaChart').toggleClass('d-none', !hasData);

        if (!hasData) {
            if (chart) { chart.destroy(); chart = null; }
            return;
        }

        const options = chartOptions(chartData);
        if (!chart) {
            chart = new ApexCharts(document.querySelector('#sdaChart'), options);
            chart.render();
        } else {
            chart.updateOptions(options);
        }
    }

    function loadAnalytics() {
        const [startDate, endDate] = currentRange();

        $('#sdaSummaryRow .fs-18').text('…');

        return $.ajax({
            url: 'api/social-content/get-analytics.php',
            data: { startDate: startDate, endDate: endDate, filterType: state.rangeType },
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load analytics.');
                return;
            }
            renderSummary(res.data);
            renderChart(res.data.chartData || []);
        }, function () {
            notify('danger', 'Network error while loading analytics.');
        });
    }

    // ----------------------------------------------------------------------
    // events
    // ----------------------------------------------------------------------
    $('#sdeAnalyticsBtn').on('click', function () {
        if (!modal) modal = new bootstrap.Modal(document.getElementById('sdeAnalyticsModal'));
        resetToDefaults();
        modal.show();
    });

    $('#sdeAnalyticsModal').on('shown.bs.modal', function () {
        if (!weekFp) {
            weekFp = flatpickr('#sdaCustomRange', {
                mode: 'range',
                dateFormat: 'Y-m-d',
                altInput: true,
                altFormat: 'd M Y',
                onClose: function (selectedDates) {
                    if (!selectedDates.length) return;
                    const from = toYMD(selectedDates[0]);
                    const to = selectedDates.length > 1 ? toYMD(selectedDates[1]) : from;
                    $('#sdaCustomRange').attr('data-from', from).attr('data-to', to);
                    loadAnalytics();
                }
            });
        }
        loadAnalytics();
    });

    $('#sdaRangeType').on('change', function () {
        state.rangeType = $(this).val();
        toggleRangeInputs();
        if (state.rangeType !== 'custom' || $('#sdaCustomRange').attr('data-from')) {
            loadAnalytics();
        }
    });

    $('#sdaMonth').on('change', loadAnalytics);

    $('#sdaWeekMonth').on('change', function () {
        populateWeekOptions($(this).val());
        loadAnalytics();
    });

    $('#sdaWeek').on('change', loadAnalytics);

});
