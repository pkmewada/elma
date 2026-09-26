/* ==========================================================================
   Real CRUD, backed by api/social-content/*.php (SocialContentEngine).
   Planned slots come from clientCalendarPlans (get-plan.php); filled-in
   content lives in clientSocialContent (get-entries/save-entry/delete-entry).
   ========================================================================== */
$(function () {


    // ----------------------------------------------------------------------
    // 1. CATALOG (clients / platforms / features) — loaded once from the DB
    // ----------------------------------------------------------------------
    const CATALOG = { clients: [], platforms: [], features: {} };

    const STATUS_LABEL = {
        pending: 'Not Filled', draft: 'Draft', ready: 'Ready',
        scheduled: 'Scheduled', posted: 'Posted'
    };

    function loadCatalog() {
        return $.when(
            $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }),
            $.ajax({ url: 'api/deliverables/get-platforms.php', dataType: 'json' }),
            $.ajax({ url: 'api/deliverables/get-features.php', dataType: 'json' })
        ).then(function (clientsResp, platformsResp, featuresResp) {
            const clientsRes = clientsResp[0], platformsRes = platformsResp[0], featuresRes = featuresResp[0];

            CATALOG.clients = (clientsRes.success ? clientsRes.data : [])
                .map(c => ({ id: Number(c.id), name: c.fullName }));
            CATALOG.platforms = (platformsRes.success ? platformsRes.data : [])
                .map(p => ({ id: Number(p.id), name: p.platformName, icon: p.icon || 'ri-apps-line' }));

            CATALOG.features = {};
            (featuresRes.success ? featuresRes.data : []).forEach(f => {
                const pid = Number(f.platformId);
                (CATALOG.features[pid] = CATALOG.features[pid] || []).push({ id: Number(f.id), name: f.featureName });
            });

            if (!clientsRes.success) notify('danger', clientsRes.message || 'Failed to load clients.');
            if (!platformsRes.success) notify('danger', platformsRes.message || 'Failed to load platforms.');
            if (!featuresRes.success) notify('danger', featuresRes.message || 'Failed to load features.');
        }, function () {
            notify('danger', 'Network error while loading clients/platforms/features.');
        });
    }

    // ----------------------------------------------------------------------
    // 1b. SCOPE DATA (planned slots + filled entries) for the current client/date range
    // ----------------------------------------------------------------------
    // Both APIs are still month-keyed (api/social-content/get-plan.php,
    // get-entries.php), so a date-range filter fetches every month the range
    // spans and trims the merged result down to [dateFrom, dateTo].
    const planCache = {};      // 'clientId|month' -> { 'YYYY-MM-DD': [{clientId, platformId, featureId, editable}] }
    let currentPlan = {};      // merged/trimmed plan for state.clientId/state.dateFrom..state.dateTo
    let entries = [];          // clientSocialContent rows for the same scope

    function fetchPlan(clientId, month) {
        const key = (clientId || 0) + '|' + month;
        if (planCache[key]) return $.Deferred().resolve(planCache[key]).promise();

        return $.ajax({ url: 'api/social-content/get-plan.php', data: { clientId: clientId || 0, month: month }, dataType: 'json' })
            .then(function (res) {
                if (!res || !res.success) notify('danger', (res && res.message) || 'Failed to load calendar plan.');
                const data = (res && res.success) ? (res.data || {}) : {};
                planCache[key] = data;
                return data;
            }, function () {
                notify('danger', 'Network error while loading calendar plan.');
                return $.Deferred().reject().promise();
            });
    }

    function fetchEntriesForMonth(clientId, month) {
        return $.ajax({ url: 'api/social-content/get-entries.php', data: { clientId: clientId || 0, month: month }, dataType: 'json' })
            .then(function (res) {
                if (!res || !res.success) {
                    notify('danger', (res && res.message) || 'Failed to load entries.');
                    return [];
                }
                return res.data || [];
            }, function () {
                notify('danger', 'Network error while loading entries.');
                return $.Deferred().reject().promise();
            });
    }

    // every 'YYYY-MM' between fromDate and toDate (inclusive) — get-plan.php
    // and get-entries.php are called once per spanned month
    function monthsInRange(fromDate, toDate) {
        const months = [];
        const start = new Date(fromDate + 'T00:00:00');
        const end = new Date(toDate + 'T00:00:00');
        const cursor = new Date(start.getFullYear(), start.getMonth(), 1);
        while (cursor <= end) {
            months.push(cursor.getFullYear() + '-' + String(cursor.getMonth() + 1).padStart(2, '0'));
            cursor.setMonth(cursor.getMonth() + 1);
        }
        return months;
    }

    function loadScope() {
        const clientId = state.clientId || 0;
        const months = monthsInRange(state.dateFrom, state.dateTo);

        return Promise.all(months.map(m => fetchPlan(clientId, m))).then(function (planParts) {
            currentPlan = {};
            planParts.forEach(function (planData) {
                Object.keys(planData || {}).forEach(function (date) {
                    if (date >= state.dateFrom && date <= state.dateTo) {
                        currentPlan[date] = planData[date];
                    }
                });
            });

            return Promise.all(months.map(m => fetchEntriesForMonth(clientId, m)));
        }).then(function (entryParts) {
            entries = [].concat.apply([], entryParts).filter(function (e) {
                return e.contentDate >= state.dateFrom && e.contentDate <= state.dateTo;
            });
        }, function () {
            currentPlan = {};
            entries = [];
        });
    }

    // ----------------------------------------------------------------------
    // 2. HELPERS
    // ----------------------------------------------------------------------
    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function clientById(id) {
        return CATALOG.clients.find(c => c.id === Number(id)) || { name: 'Unknown' };
    }

    function platformById(id) {
        return CATALOG.platforms.find(p => p.id === Number(id)) || { name: 'Unknown', icon: 'ri-apps-line' };
    }

    function featureById(platformId, featureId) {
        const list = CATALOG.features[Number(platformId)] || [];
        return list.find(f => f.id === Number(featureId)) || { name: 'Unknown' };
    }

    // Posting Type / Content Format classification (Post/Story -> Content
    // Format) — mirrors pages/social-overview.php's classifyPostType()
    // exactly. The internal `postType`/`format` property names (and the
    // clientSocialContent.postType column they come from) are unchanged —
    // only the user-facing labels in the View modal read "Posting Type"/
    // "Content Format" now (see renderViewDetails() below). It stores the
    // granular Content Format value (e.g. "Reel", "Image Story"), and the
    // parent Posting Type (Post/Story) shown in the View modal is always
    // derived from it here. Legacy flat values ('Post'/'Story'/'Reel'/
    // 'Carousel'/'Video'/'Image', any case) saved before this change still
    // classify correctly.
    const LEGACY_POST_TYPE_MAP = {
        'image': { postType: 'Post', format: 'Image' },
        'reel': { postType: 'Post', format: 'Reel' },
        'carousel': { postType: 'Post', format: 'Carousel' },
        'video': { postType: 'Post', format: 'Video' },
        'image story': { postType: 'Story', format: 'Image Story' },
        'video story': { postType: 'Story', format: 'Video Story' },
        'post': { postType: 'Post', format: 'Post' },
        'story': { postType: 'Story', format: 'Story' }
    };

    function classifyPostType(raw) {
        const key = String(raw || '').trim().toLowerCase();
        if (!key) return null;
        return LEGACY_POST_TYPE_MAP[key] || { postType: 'Post', format: String(raw).trim() };
    }

    function findEntry(clientId, date, platformId, featureId) {
        return entries.find(e =>
            e.clientId === Number(clientId) &&
            e.contentDate === date &&
            e.platformId === Number(platformId) &&
            e.featureId === Number(featureId)
        );
    }

    // Board rows for one date: one row per ACTUAL clientSocialContent record
    // (never deduplicated by client/platform/feature — clientSocialContent.id
    // is the only identity a record has, so three records sharing the same
    // tuple render as three separate rows), plus one empty "not started yet"
    // placeholder row for each planned slot that has ZERO matching actual
    // entries. Calendar planning is a MINIMUM, not a cap or a uniqueness
    // rule, so an actual record's own tuple may or may not correspond to any
    // planned slot at all, and a planned slot may end up matched by several
    // records. This never affects the rail's planned/filled progress (that
    // stays tied strictly to the calendar plan via visibleSlots()).
    function boardRowsForDate(date) {
        const planned = currentPlan[date] || [];

        const actualRows = entries
            .filter(e => e.contentDate === date)
            .filter(e => !state.platform || String(e.platformId) === state.platform)
            .filter(e => !state.feature || String(e.featureId) === state.feature)
            .filter(e => !state.status || e.status === state.status)
            .filter(e => {
                if (!state.search) return true;
                const q = state.search.toLowerCase();
                const hay = [
                    featureById(e.platformId, e.featureId).name,
                    e.title || '',
                    e.caption || ''
                ].join(' ').toLowerCase();
                return hay.indexOf(q) !== -1;
            })
            .map(e => ({
                clientId: e.clientId, platformId: e.platformId, featureId: e.featureId,
                date: date, editable: null, entry: e, status: e.status
            }));

        const emptySlotRows = planned
            .filter(s => !findEntry(s.clientId, date, s.platformId, s.featureId))
            .filter(s => !state.platform || String(s.platformId) === state.platform)
            .filter(s => !state.feature || String(s.featureId) === state.feature)
            .filter(s => state.editable === '' || String(s.editable) === state.editable)
            .filter(s => !state.status || state.status === 'pending')
            .map(s => ({ ...s, date: date, entry: null, status: 'pending' }));

        return actualRows.concat(emptySlotRows);
    }

    function fmtLongDate(dateStr) {
        const d = new Date(dateStr + 'T00:00:00');
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // local YYYY-MM-DD (not toISOString(), which is UTC and can shift the day)
    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function defaultDateRange() {
        const now = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return [toYMD(first), toYMD(last)];
    }

    function fmtDateTime(dt) {
        if (!dt) return '—';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ', ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function notify(type, message) {
        if (window.showToast) window.showToast(type, message);
    }

    function confirmDialog(opts) {
        return Swal.fire({
            title: opts.title,
            html: opts.html || '',
            icon: opts.icon || 'warning',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Confirm',
            cancelButtonText: 'Cancel',
            confirmButtonColor: opts.color || '#dc3545',
            reverseButtons: true
        });
    }

    // ----------------------------------------------------------------------
    // 3. PAGE STATE
    // ----------------------------------------------------------------------
    const state = {
        clientId: null,
        dateFrom: null,
        dateTo: null,
        platform: '',
        feature: '',
        editable: '',
        status: '',
        search: '',
        activeDate: null,
        editingId: null
    };

    let entryModal = null;
    let overviewModal = null;
    let formDirty = false;
    let dateRangeFp = null;
    // the client the currently-open entry modal actually targets — the
    // clicked planned slot's own clientId when locked, otherwise the
    // global client filter (legacy manual-add path). Never read
    // state.clientId directly inside the save flow; see openEntryModal().
    let modalClientId = null;

    // ----------------------------------------------------------------------
    // 4. BOOTSTRAP THE FILTERS
    // ----------------------------------------------------------------------
    // "Plan" filter (user-facing label -- backed by featureId/CATALOG.
    // features internally, unchanged; this is the calendar-planning
    // dimension, not the Posting Type/Content Format classification below)
    // — one option per whitelisted feature (the whitelist itself is
    // enforced server-side in get-features.php, so this just walks whatever
    // CATALOG.features already came back). featureId is used as the value
    // since the same feature name (e.g. "Posting") can exist under several
    // platforms.
    function buildFeatureFilterOptions() {
        let html = '<option value="">All Plans</option>';
        CATALOG.platforms.forEach(p => {
            (CATALOG.features[p.id] || []).forEach(f => {
                html += `<option value="${f.id}">${esc(f.name)} - ${esc(p.name)}</option>`;
            });
        });
        return html;
    }

    // date-range filter — a single flatpickr range picker replaces the old
    // Month dropdown; picking one date twice (or just closing after one
    // click) is treated as a single-day range.
    function initDateRangePicker() {
        dateRangeFp = flatpickr('#sdeDateRange', {
            mode: 'range',
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'd M',
            defaultDate: [state.dateFrom, state.dateTo],
            onClose: function (selectedDates) {
                if (!selectedDates.length) return;
                const from = toYMD(selectedDates[0]);
                const to = selectedDates.length > 1 ? toYMD(selectedDates[1]) : from;
                if (from === state.dateFrom && to === state.dateTo) return;
                state.dateFrom = from;
                state.dateTo = to;
                state.activeDate = null;
                reloadAndRender();
            }
        });
    }

    function syncDateRangePicker() {
        if (dateRangeFp) dateRangeFp.setDate([state.dateFrom, state.dateTo], false);
    }

    // ----------------------------------------------------------------------
    // 5. RENDER — DATE RAIL
    // ----------------------------------------------------------------------
    function visibleSlots(date) {
        let slots = (currentPlan[date] || []).slice();

        if (state.platform) {
            slots = slots.filter(s => String(s.platformId) === state.platform);
        }

        if (state.feature) {
            slots = slots.filter(s => String(s.featureId) === state.feature);
        }

        if (state.editable !== '') {
            slots = slots.filter(s => String(s.editable) === state.editable);
        }

        return slots.map(s => {
            const entry = findEntry(s.clientId, date, s.platformId, s.featureId);
            // date isn't in the raw plan slot (it's the outer key in
            // currentPlan) — attach it here so every consumer of a slot
            // object (rail, board, the "+" button) has its own real date,
            // not the page's global activeDate/filter state
            return { ...s, date, entry: entry || null, status: entry ? entry.status : 'pending' };
        }).filter(s => {
            if (state.status && s.status !== state.status) return false;
            if (state.search) {
                const q = state.search.toLowerCase();
                const hay = [
                    featureById(s.platformId, s.featureId).name,
                    s.entry ? s.entry.title : '',
                    s.entry ? s.entry.caption : ''
                ].join(' ').toLowerCase();
                if (hay.indexOf(q) === -1) return false;
            }
            return true;
        });
    }

    // header label for the rail — a short single-line range, e.g.
    // "11 SEP 2026", "11–25 SEP 2026", "28 AUG – 5 SEP 2026"
    function railTitleLabel() {
        if (!state.dateFrom || !state.dateTo) return 'Dates';

        const from = new Date(state.dateFrom + 'T00:00:00');
        const to = new Date(state.dateTo + 'T00:00:00');
        const shortMonth = d => d.toLocaleString('en', { month: 'short' });

        let label;
        if (state.dateFrom === state.dateTo) {
            label = `${from.getDate()} ${shortMonth(from)} ${from.getFullYear()}`;
        } else if (from.getFullYear() === to.getFullYear() && from.getMonth() === to.getMonth()) {
            label = `${from.getDate()}–${to.getDate()} ${shortMonth(to)} ${to.getFullYear()}`;
        } else if (from.getFullYear() === to.getFullYear()) {
            label = `${from.getDate()} ${shortMonth(from)} – ${to.getDate()} ${shortMonth(to)} ${to.getFullYear()}`;
        } else {
            label = `${from.getDate()} ${shortMonth(from)} ${from.getFullYear()} – ${to.getDate()} ${shortMonth(to)} ${to.getFullYear()}`;
        }
        return label.toUpperCase();
    }

    function renderRail() {
        const dates = Object.keys(currentPlan).sort();
        const rail = $('#sdeRail');

        const railTitle = railTitleLabel();
        $('#sdeRailTitle').text(railTitle).attr('title', railTitle);

        let totalSlots = 0, totalFilled = 0;

        const rows = dates.map(date => {
            const slots = visibleSlots(date);
            if (!slots.length) return null;
            const filled = slots.filter(s => s.entry).length;
            totalSlots += slots.length;
            totalFilled += filled;
            const pct = Math.round((filled / slots.length) * 100);
            const d = new Date(date + 'T00:00:00');
            const barClass = pct === 100 ? 'is-done' : (pct === 0 ? 'is-empty' : '');

            return `
                <button type="button" class="sde-date-item ${state.activeDate === date ? 'active' : ''}" data-date="${date}">
                    <span class="sde-date-num">
                        <b>${d.getDate()}</b>
                        <span>${d.toLocaleString('en', { month: 'short' })}</span>
                    </span>

                    <span>
                        <span class="sde-date-toprow">
                            <span class="sde-date-dow">${d.toLocaleString('en', { weekday: 'short' })}</span>
                            <span class="sde-date-count">${filled}/${slots.length}</span>
                        </span>

                        <span class="sde-date-progress ${barClass}">
                            <span style="width:${pct}%"></span>
                        </span>
                    </span>
                </button>
            `;
        }).filter(Boolean);

        $('#sdeRailCount').text(rows.length);

       const allDatesRow = `
            <button type="button" class="sde-date-item ${!state.activeDate ? 'active' : ''}" data-date="">
                <span class="sde-date-num">
                    <b><i class="ri-list-check-2"></i></b>
                </span>

                <span>
                    <span class="sde-date-toprow">
                        <span class="sde-date-dow">All Dates</span>
                        <span class="sde-date-count">${totalFilled}/${totalSlots}</span>
                    </span>
                </span>
            </button>
        `;

        if (!rows.length) {
            rail.html(allDatesRow + `
                <div class="sde-empty py-4">
                    <i class="ri-calendar-close-line"></i>
                    <div class="fs-12">No planned dates match the current filters.</div>
                </div>
            `);
            return;
        }

        rail.html(allDatesRow + rows.join(''));
    }

    // ----------------------------------------------------------------------
    // 6. RENDER — DAY BOARD
    // ----------------------------------------------------------------------
    // groups slots by platform and returns the board HTML for them (used for
    // both the single-date view and the "All Dates" aggregated view)
    function renderSlotGroups(slots) {
        // in "All Clients" mode, split groups per client too, so the header
        // can read e.g. "Instagram - Acme Retail Pvt Ltd" instead of mixing clients
        const groups = {};
        slots.forEach(s => {
            const key = state.clientId ? s.platformId : s.platformId + '|' + s.clientId;
            (groups[key] = groups[key] || []).push(s);
        });

        let html = '';
        Object.keys(groups).forEach(key => {
            const list = groups[key];
            const platform = platformById(list[0].platformId);
            const groupLabel = state.clientId
                ? esc(platform.name)
                : `${esc(platform.name)} - ${esc(clientById(list[0].clientId).name)}`;

            html += `
                <div class="sde-group">
                    <div class="sde-group-head">
                        <i class="${platform.icon} sde-plat-icon"></i>
                        ${groupLabel}
                        <span class="badge bg-light text-default ms-auto fw-normal">${list.length} slot${list.length > 1 ? 's' : ''}</span>
                    </div>
                    <div class="sde-slot-head">
                        <span>Plan</span><span>Content</span><span class="text-center">Status</span><span>Last Updated</span><span class="text-end">Action</span>
                    </div>
            `;

            list.forEach(s => {
                const feature = featureById(s.platformId, s.featureId);
                const e = s.entry;

                const content = e
                    ? `<div class="sde-title">${esc(e.title)}</div>
                       <div class="sde-caption">${esc(e.caption || '—')}</div>`
                    : `<div class="sde-caption fst-italic">No data captured for this planned slot yet.</div>`;

                const meta = e
                    ? `<div class="sde-meta">${esc(Number(e.updatedBy) === CURRENT_USER_ID ? 'You' : 'Team member')}<br>${esc(fmtDateTime(e.updatedAt))}</div>`
                    : `<div class="sde-meta">—</div>`;

                // Saving a valid entry now auto-completes it server-side
                // (api/social-content/save-entry.php), so there is no manual
                // "Complete Entry" step left — every saved row already has
                // its production task by the time it's ever rendered here.
                // "Production Created" is a pure status indicator, shown in
                // the Status column stacked under the status badge.
                const isProductionReady = e && ['ready', 'scheduled', 'posted'].includes(e.status);
                

                // Actual-content rows are view-only from this list — Edit/Delete
                // moved off the main list entirely (not removed as a feature,
                // just no longer exposed here).
                const actions = e
                    ? `<button class="sde-icon-btn sde-view" data-id="${e.id}" title="View"><i class="ri-eye-line"></i></button>`
                    : `<button class="sde-icon-btn sde-fill" data-client-id="${s.clientId}" data-date="${s.date}" data-platform="${s.platformId}" data-feature="${s.featureId}" title="Add data"><i class="ri-add-line"></i></button>`;

                html += `
                    <div class="sde-slot ${e ? '' : 'is-empty'}">
                        <div><span class="sde-feature" title="${esc(feature.name)}">${esc(feature.name)}</span></div>
                        <div>${content}</div>
                        <div class="sde-status-stack">
                            <span class="sde-badge ${s.status}">${STATUS_LABEL[s.status]}</span>
                        </div>
                        ${meta}
                        <div class="sde-actions">${actions}</div>
                    </div>
                `;
            });

            html += `</div>`;
        });

        return html;
    }

    function renderBoard() {
        const board = $('#sdeBoard');

        if (!state.activeDate) {
            renderAllDatesBoard();
            return;
        }

        const plannedSlots = visibleSlots(state.activeDate);
        const boardRows = boardRowsForDate(state.activeDate);
        const filled = plannedSlots.filter(s => s.entry).length;
        const pending = plannedSlots.length - filled;

        $('#sdeDayTitle').html(
            `${fmtLongDate(state.activeDate)} <span class="text-muted fw-normal fs-13">· ` +
            `${new Date(state.activeDate + 'T00:00:00').toLocaleString('en', { weekday: 'long' })}</span>`
        );

        $('#sdeDayStats').html(`
            <span class="sde-stat"><i class="ri-circle-fill text-primary"></i> Planned <b>${plannedSlots.length}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-success"></i> Filled <b>${filled}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-warning"></i> Pending <b>${pending}</b></span>
        `);

        $('#sdeAddForDateBtn').prop('disabled', false);

        if (!boardRows.length) {
            board.html(`
                <div class="sde-empty">
                    <i class="ri-filter-off-line"></i>
                    <div>Nothing on this date matches the current filters.</div>
                </div>
            `);
            return;
        }

        board.html(renderSlotGroups(boardRows));
    }

    // aggregated view across every planned date in the month (and, when
    // "All Clients" is selected, across every client too)
    function renderAllDatesBoard() {
        const board = $('#sdeBoard');
        const dates = Object.keys(currentPlan).sort();

        const dateSlots = dates
            .map(date => ({ date: date, plannedSlots: visibleSlots(date), slots: boardRowsForDate(date) }))
            .filter(d => d.slots.length);

        let totalSlots = 0, totalFilled = 0;
        dateSlots.forEach(d => {
            totalSlots += d.plannedSlots.length;
            totalFilled += d.plannedSlots.filter(s => s.entry).length;
        });

        $('#sdeDayTitle').text('All Dates');
        $('#sdeDayStats').html(`
            <span class="sde-stat"><i class="ri-circle-fill text-primary"></i> Planned <b>${totalSlots}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-success"></i> Filled <b>${totalFilled}</b></span>
            <span class="sde-stat"><i class="ri-circle-fill text-warning"></i> Pending <b>${totalSlots - totalFilled}</b></span>
        `);

        // adding an entry needs one specific date, picked from the rail
        $('#sdeAddForDateBtn').prop('disabled', true);

        if (!dateSlots.length) {
            board.html(`
                <div class="sde-empty">
                    <i class="ri-filter-off-line"></i>
                    <div>Nothing matches the current filters.</div>
                </div>
            `);
            return;
        }

        let html = '';
        dateSlots.forEach(d => {
            html += `<div class="mb-3">
                <div class="fw-semibold fs-13 mb-2">${esc(fmtLongDate(d.date))}</div>
                ${renderSlotGroups(d.slots)}
            </div>`;
        });

        board.html(html);
    }

    // synchronous re-render against whatever is already cached in currentPlan/entries
    function refreshBoard() {
        const dates = Object.keys(currentPlan).sort();

        // drop the active date if it no longer exists under the current filters;
        // otherwise leave it as-is (including null, which means "All Dates")
        if (state.activeDate && dates.indexOf(state.activeDate) === -1) {
            state.activeDate = null;
        }

        renderRail();
        renderBoard();
    }

    // fetches the plan + entries for the current client/month, then renders
    function reloadAndRender() {
        $('#sdeBoard').html(`
            <div class="sde-empty">
                <div class="spinner-border text-primary spinner-border-sm mb-2" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <div class="fs-12">Loading...</div>
            </div>
        `);
        return loadScope().then(refreshBoard);
    }

    // ----------------------------------------------------------------------
    // 7. ENTRY FORM
    // ----------------------------------------------------------------------
    function fillFeatureOptions(platformId, selectedFeatureId) {
        const feats = CATALOG.features[Number(platformId)] || [];
        $('#sdeFormFeature').html(
            feats.map(f => `<option value="${f.id}" ${Number(selectedFeatureId) === f.id ? 'selected' : ''}>${esc(f.name)}</option>`).join('')
        );
    }

    // "+" on a planned slot must inherit that slot's own client/date/
    // platform/feature — those four are locked read-only in the modal so
    // the calendar plan stays authoritative (a Post can't be silently
    // saved as a Story). Editing an existing entry, and the legacy
    // free-form add path, are untouched — locking only applies here.
    //
    // 'view' reuses this same modal read-only: every field disabled, Save
    // hidden, nothing mutated. Every field's disabled state is set
    // unconditionally on every call (not just when locking) so no mode ever
    // leaves stale disabled controls behind for the next mode.
    function openEntryModal(mode, payload) {
        payload = payload || {};
        const locked = mode === 'add' && payload.locked === true;
        const viewOnly = mode === 'view';
        const freeFormAdd = mode === 'add' && !locked;

        if (locked) {
            if (!payload.clientId || !payload.date || !payload.platformId || !payload.featureId) {
                notify('danger', 'Could not determine the planned slot for this entry. Please refresh and try again.');
                return;
            }
        } else if (freeFormAdd && !state.clientId) {
            // only the legacy free-form add path (no slot, no existing
            // record) needs a client from the global filter — edit/view
            // always carry their own record's real clientId below, and must
            // work even while "All Clients" is selected.
            notify('warning', 'Select a client before adding an entry.');
            return;
        }

        // edit/view's payload is the entry itself (always has its own real
        // clientId); locked add mode was already validated to have one
        // above. Only the legacy free-form add path has neither, and falls
        // back to the global client filter as it always did.
        modalClientId = payload.clientId != null ? Number(payload.clientId) : state.clientId;

        state.editingId = (mode === 'edit' || viewOnly) ? payload.id : null;
        formDirty = false;

        $('#sdeEntryTitle').html(
            viewOnly ? '<i class="ri-eye-line me-2 text-primary"></i> View Entry'
            : mode === 'edit' ? '<i class="ri-edit-box-line me-2 text-primary"></i> Update Entry'
            : '<i class="ri-add-box-line me-2 text-primary"></i> Add Entry'
        );

        $('#sdeFormPlatform').html(
            CATALOG.platforms.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join('')
        );

        const date = payload.contentDate || payload.date || state.activeDate || '';
        const platformId = payload.platformId || (CATALOG.platforms[0] && CATALOG.platforms[0].id);

        if (locked) {
            $('#sdeScopeStrip').html(`
                <span><i class="ri-lock-2-line me-1"></i>Client: <b>${esc(clientById(modalClientId).name)}</b></span>
                <span class="text-muted">·</span>
                <span>Date: <b>${esc(fmtLongDate(date))}</b></span>
                <span class="text-muted">·</span>
                <span>Platform: <b>${esc(platformById(platformId).name)}</b></span>
                <span class="text-muted">·</span>
                <span>Plan: <b>${esc(featureById(platformId, payload.featureId).name)}</b></span>
            `);
        } else if (!viewOnly) {
            $('#sdeScopeStrip').html(`
                <span>Client: <b>${esc($('#sdeClient option:selected').text())}</b></span>
                <span class="text-muted">·</span>
                <span>Range: <b>${esc(fmtLongDate(state.dateFrom))} – ${esc(fmtLongDate(state.dateTo))}</b></span>
            `);
        }

        $('#sdeFormDate').val(date);
        $('#sdeFormPlatform').val(platformId);
        fillFeatureOptions(platformId, payload.featureId);

        // lock the planning-context fields when adding from a planned slot,
        // or when only viewing — disabled controls still report their value
        // via .val(), which is all saveEntry()/persistEntry() ever read
        // (this page never relies on native <form> submission, so
        // "disabled fields aren't submitted" doesn't apply here)
        const fieldsLocked = locked || viewOnly;
        const lockBadge = locked ? ' <i class="ri-lock-2-line text-muted" title="Locked from the calendar plan"></i>' : '';
        $('label[for="sdeFormDate"]').html('Date <span class="text-danger">*</span>' + lockBadge);
        $('label[for="sdeFormPlatform"]').html('Platform <span class="text-danger">*</span>' + lockBadge);
        $('label[for="sdeFormFeature"]').html('Plan <span class="text-danger">*</span>' + lockBadge);
        $('#sdeFormPlatform').prop('disabled', fieldsLocked);
        $('#sdeFormFeature').prop('disabled', fieldsLocked);

        $('#sdeFormTitle').val(payload.title || '').prop('disabled', viewOnly);
        $('#sdeFormStatus').val(payload.status || 'draft').prop('disabled', viewOnly);
        $('#sdeFormCaption').val(payload.caption || '').prop('disabled', viewOnly);
        $('#sdeFormLink').val(payload.referenceLink || payload.link || '').prop('disabled', viewOnly);
        $('#sdeFormRemarks').val(payload.remarks || '').prop('disabled', viewOnly);

        $('#sdeSaveBtn').toggle(!viewOnly);
        $('#sdeCancelBtn').text(viewOnly ? 'Close' : 'Cancel');

        // View mode swaps the form UI for a read-only detail presentation —
        // #sdeFormFields (scope strip + all sdeForm* inputs) is hidden rather
        // than removed, so the field-population logic above stays exactly as
        // it was for add/edit; #sdeViewDetails is built fresh from the same
        // payload every time, no separate fetch.
        $('#sdeFormFields').toggleClass('d-none', viewOnly);
        $('#sdeViewDetails').toggleClass('d-none', !viewOnly);
        if (viewOnly) renderViewDetails(payload);

        $('.sde-form .is-invalid').removeClass('is-invalid');

        if (!entryModal) {
            entryModal = new bootstrap.Modal(document.getElementById('sdeEntryModal'));
        }
        entryModal.show();

        // flatpickr is already loaded by includes/footer.php
        const dateInput = document.getElementById('sdeFormDate');
        if (dateInput._flatpickr) dateInput._flatpickr.destroy();
        dateInput.readOnly = fieldsLocked;
        flatpickr(dateInput, { dateFormat: 'Y-m-d', defaultDate: date || null, clickOpens: !fieldsLocked });
    }

    // ------------------------------------------------------------------
    // View Entry — pure detail presentation, no inputs. Built entirely from
    // the entry payload openEntryModal() already has (same object the board
    // itself rendered), so this adds no fetch and no new API surface.
    // Every optional field is omitted when empty, mirroring the "Content
    // Brief" convention already established on the Production Task detail
    // modal (pages/social-content-production.php).
    // ------------------------------------------------------------------
    function viewCell(label, valueHtml) {
        if (valueHtml === null || valueHtml === undefined || valueHtml === '') return '';
        return `<div class="col-6 col-md-4 col-lg-3">
            <div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div>
            <div class="fs-13">${valueHtml}</div>
        </div>`;
    }

    function viewBlock(label, text) {
        const value = String(text || '').trim();
        if (!value) return '';
        return `<div class="sde-view-block">
            <div class="sde-view-label">${esc(label)}</div>
            <div class="sde-view-value">${esc(value)}</div>
        </div>`;
    }

    // link-or-plain-text, extended with an inline image/video preview when
    // the URL's extension makes that possible — mirrors linkOrText()/
    // renderOutput()'s isVideo check on the Production Task detail modal.
    function viewLinkOrMedia(url) {
        const value = String(url || '').trim();
        if (!value) return '';
        if (!/^https?:\/\//i.test(value)) return esc(value);

        const ext = value.split('.').pop().toLowerCase().split(/[?#]/)[0];
        const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext);
        const isVideo = ['mp4', 'mov', 'webm'].includes(ext);
        const link = `<a href="${esc(value)}" target="_blank" rel="noopener noreferrer" class="d-inline-flex align-items-center gap-1">` +
            `<i class="ri-external-link-line"></i> Open Link</a>`;

        if (isImage) return `${link}<img src="${esc(value)}" class="sde-view-media mt-2" alt="Media preview">`;
        if (isVideo) return `${link}<video controls class="sde-view-media mt-2"><source src="${esc(value)}"></video>`;
        return link;
    }

    function renderViewDetails(payload) {
        const platform = platformById(payload.platformId);
        const feature = featureById(payload.platformId, payload.featureId);
        const client = clientById(modalClientId);
        const classified = classifyPostType(payload.postType);

        const whoLabel = (userId) => Number(userId) === CURRENT_USER_ID ? 'You' : 'Team member';

        const header = `
            <div class="sde-view-header">
                <div>
                    <h5 class="sde-view-title">${esc(payload.title || 'Untitled Entry')}</h5>
                    <div class="sde-view-badges">
                        <span class="sde-feature"><i class="${esc(platform.icon)} me-1"></i>${esc(platform.name)}</span>
                        <span class="sde-feature">${esc(feature.name)}</span>
                        <span class="sde-badge ${esc(payload.status || 'draft')}">${esc(STATUS_LABEL[payload.status] || STATUS_LABEL.draft)}</span>
                    </div>
                </div>
                <div class="sde-view-meta">
                    ${payload.createdAt ? `<span><i class="ri-add-circle-line me-1"></i>Created ${esc(fmtDateTime(payload.createdAt))}${payload.createdBy != null ? ' by ' + esc(whoLabel(payload.createdBy)) : ''}</span>` : ''}
                    ${payload.updatedAt ? `<span><i class="ri-history-line me-1"></i>Updated ${esc(fmtDateTime(payload.updatedAt))}${payload.updatedBy != null ? ' by ' + esc(whoLabel(payload.updatedBy)) : ''}</span>` : ''}
                </div>
            </div>
        `;

        const overviewCells = [
            viewCell('Client', esc(client.name)),
            viewCell('Date', esc(fmtLongDate(payload.contentDate || payload.date))),
            classified ? viewCell('Posting Type', esc(classified.postType)) : '',
            classified ? viewCell('Content Format', esc(classified.format)) : '',
            payload.socialMediaHandle ? viewCell('Social Media Handle', esc(payload.socialMediaHandle)) : ''
        ].filter(Boolean).join('');

        const textBlocks = [
            viewBlock('Caption / Description', payload.caption) || `<div class="sde-view-block"><div class="sde-view-label">Caption / Description</div><div class="sde-view-value text-muted fst-italic">No caption added.</div></div>`,
            viewBlock('Content Description', payload.contentDescription),
            viewBlock('Raw Content', payload.rawContent),
            viewBlock('Remarks', payload.remarks)
        ].filter(Boolean).join('');

        const linkCells = [
            payload.referenceLink ? viewCell('Creative / Reference Link', viewLinkOrMedia(payload.referenceLink)) : '',
            payload.songUrl ? viewCell('Song / Audio', viewLinkOrMedia(payload.songUrl)) : '',
            payload.ideaReference ? viewCell('Idea Reference', esc(payload.ideaReference)) : ''
        ].filter(Boolean).join('');

        $('#sdeViewDetails').html(`
            ${header}
            <div class="sde-view-section">
                <h6 class="sde-view-section-title">Content Details</h6>
                <div class="row g-3">${overviewCells}</div>
            </div>
            <div class="sde-view-section">${textBlocks}</div>
            ${linkCells ? `<div class="sde-view-section">
                <h6 class="sde-view-section-title">Links &amp; Media</h6>
                <div class="row g-3">${linkCells}</div>
            </div>` : ''}
        `);
    }

    function validateForm() {
        const errors = [];
        $('.sde-form .is-invalid').removeClass('is-invalid');

        const date = $('#sdeFormDate').val().trim();
        const title = $('#sdeFormTitle').val().trim();

        if (!date) { $('#sdeFormDate').addClass('is-invalid'); errors.push('Date is required.'); }
        if (!title) { $('#sdeFormTitle').addClass('is-invalid'); errors.push('Content title is required.'); }
        if (title && title.length < 3) { $('#sdeFormTitle').addClass('is-invalid'); errors.push('Content title is too short.'); }

        const link = $('#sdeFormLink').val().trim();
        if (link && !/^https?:\/\//i.test(link)) {
            $('#sdeFormLink').addClass('is-invalid');
            errors.push('Reference link must start with http:// or https://');
        }

        return errors;
    }

    // saves via api/social-content/save-entry.php; resolves only on success
    // (so callers can chain "and then show this extra toast" safely)
    function persistEntry(record) {
        const payload = Object.assign({}, record, state.editingId ? { id: state.editingId } : {});

        return $.ajax({
            url: 'api/social-content/save-entry.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to save entry.');
                return $.Deferred().reject().promise();
            }

            notify('success', state.editingId ? 'Entry updated successfully.' : 'Entry added successfully.');
            entryModal.hide();
            state.activeDate = record.contentDate;

            // saved outside the current date range filter — widen it so the
            // entry doesn't just disappear after saving
            if (record.contentDate < state.dateFrom || record.contentDate > state.dateTo) {
                if (record.contentDate < state.dateFrom) state.dateFrom = record.contentDate;
                if (record.contentDate > state.dateTo) state.dateTo = record.contentDate;
                syncDateRangePicker();
            }

            return reloadAndRender();
        }, function () {
            notify('danger', 'Network error while saving entry.');
            return $.Deferred().reject().promise();
        });
    }

    function saveEntry() {
        const errors = validateForm();
        if (errors.length) {
            notify('danger', errors[0]);
            return;
        }

        const date = $('#sdeFormDate').val().trim();
        const platformId = Number($('#sdeFormPlatform').val());
        const featureId = Number($('#sdeFormFeature').val());

        // No duplicate/clash check here on purpose — multiple independent
        // records may legitimately share the same client/date/platform/feature
        // (clientSocialContent.id is the only identity a record has; the
        // Calendar plan is a minimum requirement, not a uniqueness rule).
        // Uses modalClientId (the slot's own client when locked), never the
        // global client filter — those are not the same thing.
        const record = {
            clientId: modalClientId,
            contentDate: date,
            platformId: platformId,
            featureId: featureId,
            title: $('#sdeFormTitle').val().trim(),
            caption: $('#sdeFormCaption').val().trim(),
            referenceLink: $('#sdeFormLink').val().trim(),
            status: $('#sdeFormStatus').val(),
            remarks: $('#sdeFormRemarks').val().trim()
        };

        // off-plan guard — warn, but let the user proceed
        fetchPlan(modalClientId, date.slice(0, 7)).then(function (plan) {
            const planned = (plan[date] || []).some(s => s.platformId === platformId && s.featureId === featureId);

            if (!planned) {
                confirmDialog({
                    title: 'Slot is not in the calendar plan',
                    html: `<b>${esc(featureById(platformId, featureId).name)}</b> on <b>${esc(fmtLongDate(date))}</b> ` +
                          `is not planned for this client. Save it anyway as an extra deliverable?`,
                    icon: 'warning',
                    confirmText: 'Save anyway',
                    color: '#f7b731'
                }).then(res => {
                    if (!res.isConfirmed) {
                        notify('info', 'Save cancelled. Nothing was changed.');
                        return;
                    }
                    persistEntry(record).then(function () {
                        notify('warning', 'Saved as an off-plan entry — update the Calendar Planner to keep both in sync.');
                    });
                });
                return;
            }

            persistEntry(record);
        });
    }

    function deleteEntry(id) {
        const entry = entries.find(e => e.id === id);
        if (!entry) {
            notify('danger', 'Entry not found. Refresh and try again.');
            return;
        }

        confirmDialog({
            title: 'Delete this entry?',
            html: `<b>${esc(entry.title)}</b><br><span class="text-muted">` +
                  `${esc(platformById(entry.platformId).name)} · ${esc(featureById(entry.platformId, entry.featureId).name)} · ` +
                  `${esc(fmtLongDate(entry.contentDate))}</span><br><br>This cannot be undone.`,
            icon: 'warning',
            confirmText: 'Delete'
        }).then(res => {
            if (!res.isConfirmed) return;

            $.ajax({
                url: 'api/social-content/delete-entry.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ id: id }),
                dataType: 'json'
            }).then(function (response) {
                if (!response || !response.success) {
                    notify('danger', (response && response.message) || 'Failed to delete entry.');
                    return;
                }
                notify('success', 'Entry deleted. The planned slot is now open again.');
                reloadAndRender();
            }, function () {
                notify('danger', 'Network error while deleting entry.');
            });
        });
    }

    // ----------------------------------------------------------------------
    // 8. EVENTS
    // ----------------------------------------------------------------------
    $('#sdeClient').on('change', function () {
        const val = $(this).val();
        state.clientId = val ? Number(val) : '';
        state.activeDate = null;
        reloadAndRender();
        notify('info', 'Switched to ' + $('#sdeClient option:selected').text() + '.');
    });

    $('#sdePlatform').on('change', function () { state.platform = $(this).val(); refreshBoard(); });
    $('#sdeStatus').on('change', function () { state.status = $(this).val(); refreshBoard(); });

    // Feature/Editable narrow which dates even have visible slots, so drop
    // the active date back to "All Dates" when it no longer matches.
    $('#sdeFeature').on('change', function () {
        state.feature = $(this).val();
        if (state.activeDate && !visibleSlots(state.activeDate).length) state.activeDate = null;
        refreshBoard();
    });

    $('#sdeEditable').on('change', function () {
        state.editable = $(this).val();
        if (state.activeDate && !visibleSlots(state.activeDate).length) state.activeDate = null;
        refreshBoard();
    });

    let searchTimer = null;
    $('#sdeSearch').on('input', function () {
        const val = $(this).val().trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { state.search = val; refreshBoard(); }, 250);
    });

    $('#sdeResetBtn').on('click', function () {
        $('#sdePlatform').val('');
        $('#sdeFeature').val('');
        $('#sdeEditable').val('');
        $('#sdeStatus').val('');
        $('#sdeSearch').val('');
        state.platform = state.feature = state.editable = state.status = state.search = '';
        refreshBoard();
        notify('info', 'Filters reset.');
    });

    $('#sdeRail').on('click', '.sde-date-item', function () {
        state.activeDate = $(this).data('date') || null;
        renderRail();
        renderBoard();
    });

    $('#sdeAddForDateBtn').on('click', function () {
        openEntryModal('add', { date: state.activeDate });
    });

    // "Add Entry" now opens the full-page Social Media Overview inside a
    // full-screen modal; that page's own "Fill Now" opens its own data-entry
    // modal on top of it — nothing to duplicate here.
    $('#sdeAddBtn').on('click', function () {
        if (!overviewModal) overviewModal = new bootstrap.Modal(document.getElementById('sdeOverviewModal'));
        $('#sdeOverviewFrame').attr('src', 'social-overview?embed=1');
        overviewModal.show();
    });

    $('#sdeOverviewModal').on('hidden.bs.modal', function () {
        $('#sdeOverviewFrame').attr('src', 'about:blank');
    });

    // the embedded Overview page's own "Close" button asks us to close its modal
    window.addEventListener('message', function (e) {
        if (e.origin === window.location.origin && e.data && e.data.type === 'sde-overview-close' && overviewModal) {
            overviewModal.hide();
        }
    });

    // the "+" button belongs to one specific planned slot — that slot is
    // the source of truth for client/date/platform/feature, never the
    // page's global filters (state.clientId/state.activeDate), which may
    // be "All Clients"/"All Dates" at the moment this is clicked
    $('#sdeBoard').on('click', '.sde-fill', function () {
        const $btn = $(this);
        openEntryModal('add', {
            locked: true,
            clientId: Number($btn.data('client-id')),
            date: $btn.data('date'),
            platformId: Number($btn.data('platform')),
            featureId: Number($btn.data('feature'))
        });
    });

    $('#sdeBoard').on('click', '.sde-view', function () {
        const entry = entries.find(e => e.id === $(this).data('id'));
        if (!entry) { notify('danger', 'Entry not found.'); return; }
        openEntryModal('view', entry);
    });

    $('#sdeBoard').on('click', '.sde-edit', function () {
        const entry = entries.find(e => e.id === $(this).data('id'));
        if (!entry) { notify('danger', 'Entry not found.'); return; }
        openEntryModal('edit', entry);
    });

    $('#sdeBoard').on('click', '.sde-delete', function () {
        deleteEntry($(this).data('id'));
    });

    $('#sdeFormPlatform').on('change', function () { fillFeatureOptions($(this).val()); });
    $('#sdeSaveBtn').on('click', saveEntry);

    // dirty tracking so a half-typed entry is never lost by a stray click
    $('#sdeEntryModal').on('input change', 'input, select, textarea', function () { formDirty = true; });

    $('#sdeCancelBtn').on('click', function () {
        if (!formDirty) { entryModal.hide(); return; }
        confirmDialog({
            title: 'Discard changes?',
            html: 'The details you entered will not be saved.',
            icon: 'question',
            confirmText: 'Discard',
            color: '#dc3545'
        }).then(res => {
            if (res.isConfirmed) { formDirty = false; entryModal.hide(); }
        });
    });

    $('#sdeEntryModal').on('hidden.bs.modal', function () {
        formDirty = false;
        state.editingId = null;
    });

    // ----------------------------------------------------------------------
    // 9. INITIAL RENDER
    // ----------------------------------------------------------------------
    loadCatalog().then(function () {
        $('#sdeClient').html(
            '<option value="">All Clients</option>' +
            CATALOG.clients.map(c => `<option value="${c.id}">${esc(c.name)}</option>`).join('')
        );
        $('#sdePlatform').append(CATALOG.platforms.map(p => `<option value="${p.id}">${esc(p.name)}</option>`).join(''));
        $('#sdeFeature').html(buildFeatureFilterOptions());

        state.clientId = $('#sdeClient').val() ? Number($('#sdeClient').val()) : '';

        const [defFrom, defTo] = defaultDateRange();
        state.dateFrom = defFrom;
        state.dateTo = defTo;
        initDateRangePicker();

        reloadAndRender();
    });
    
});
