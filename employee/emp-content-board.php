<?php
/*
|--------------------------------------------------------------------------
| My Production Board — Video Editor card/Kanban view
|--------------------------------------------------------------------------
|
| A second, card-based presentation of the SAME data
| employee/emp-content-production.php already shows in a table --
| does not replace that page, and does not touch production workflow
| logic in any way. Every action here (View, Start/Resume, Submit,
| History) calls the exact same existing APIs with the exact same
| payloads as the table page:
|   - api/social-content-production/emp-get-tasks.php   (list + detail)
|   - api/social-content-production/emp-update-task.php (start)
|   - api/social-content-production/emp-submit-production.php (submit)
|
| Drag-and-drop is presentation-only: it reorders cards WITHIN their own
| status column (cards cannot be dragged between columns -- each column
| is its own isolated SortableJS instance, not a shared group), and the
| resulting order is remembered in this browser's localStorage only, per
| logged-in employee. No API call, no database write, no status change
| is ever triggered by a drag.
|
*/
include __DIR__ . '/../includes/emp-auth.php';
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/Csrf.php';
include __DIR__ . '/../includes/emp-header.php';
include __DIR__ . '/../includes/emp-sidebar.php';
?>
<script>
    const CSRF_TOKEN = "<?php echo htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>";
    const EMP_ID = Number("<?php echo (int)$_SESSION['candidateId']; ?>");
</script>

<style>
    /* ==========================================================================
       My Production Board -- scoped styles (ecb-*). Layered on top of this
       theme's own CSS custom properties (--primary-rgb, --custom-white,
       --default-border, --text-muted, --body-bg-rgb) so light/dark mode and
       the app's own theme switcher (html[data-theme-mode]) are respected
       automatically -- no parallel color system introduced.
       ========================================================================== */

    .ecb-page {
        position: relative;
    }

    /* Very subtle atmosphere wash -- felt, not seen. A dense data screen
       (many cards, lots of text) stays fully readable; this only softens
       the empty space between sections. */
    .ecb-page::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background:
            radial-gradient(at 15% 10%, rgba(var(--primary-rgb), 0.08) 0%, transparent 45%),
            radial-gradient(at 85% 30%, rgba(var(--primary-rgb), 0.05) 0%, transparent 50%);
    }

    .ecb-toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .ecb-search {
        position: relative;
        min-width: 240px;
    }
    .ecb-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
        font-size: 14px;
    }
    .ecb-search input {
        padding-left: 34px;
        border-radius: 999px;
    }

    /* -------------------------- Unified grid -------------------------- */
    /* No more status sections -- one grid, every assigned task together.
       Status is card-level only (accent bar + badge + icon below).
       2 cards per row on desktop/tablet, 1 on mobile -- matches the
       card-style-7 reference layout exactly. */
    .ecb-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        min-height: 12px; /* keeps an empty grid a valid drop target */
    }
    @media (max-width: 767.98px) {
        .ecb-grid { grid-template-columns: 1fr; }
    }

    .ecb-empty-board {
        border: 1.5px dashed var(--default-border);
        border-radius: 20px;
        padding: 64px 20px;
        text-align: center;
        color: var(--text-muted);
        font-size: 14px;
    }
    .ecb-empty-board i {
        display: block;
        font-size: 34px;
        margin-bottom: 10px;
        opacity: 0.5;
    }

    /* Bootstrap doesn't ship an "orange" utility in this theme's bundle --
       Bootstrap's own canonical orange (#fd7e14), scoped to this page only. */
    .badge.bg-orange {
        background-color: #fd7e14 !important;
        color: #fff;
    }

    /* -------------------------- Card (theme card-style-7) --------------------------
       Task card, built on the theme's own ecommerce "ticket" card
       (card-style-7/card-content-1/card-content-2) instead of a bespoke
       glass card -- dark mode, hover, and border treatment all come from
       the bundled theme CSS for those classes. .ecb-card is kept purely as
       a selector hook (SortableJS drag target, data-id lookups, drag-state
       opacity) -- it no longer carries its own background/border. */
    .ecb-card.ecb-dragging { opacity: 0.55; }
    .ecb-card.ecb-drag-ghost { opacity: 0.35; }

    .ecb-card-source {
        font-size: 10.5px;
        font-weight: normal;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .ecb-card-title {
        font-size: 14.5px;
        font-weight: 600;
        line-height: 1.35;
        margin: 6px 0 14px;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .ecb-card-actions .btn {
        border-radius: 8px;
        font-size: 12.5px;
        font-weight: 600;
    }
    .ecb-icon-btn {
        width: 34px;
        height: 34px;
        flex-shrink: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 11px;
        border: 1px solid var(--default-border);
        background: var(--custom-white);
        color: var(--text-muted);
        font-size: 14px;
        transition: 0.15s;
    }
    .ecb-icon-btn:hover {
        background: rgba(var(--primary-rgb), 0.1);
        color: rgb(var(--primary-rgb));
        border-color: rgba(var(--primary-rgb), 0.3);
    }

    .ecb-drag-handle {
        cursor: grab;
        color: var(--text-muted);
        font-size: 15px;
        padding: 2px 4px;
        flex-shrink: 0;
    }
    .ecb-drag-handle:active { cursor: grabbing; }

    .ecb-date-label {
        display: block;
        font-size: 9.5px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--text-muted);
        margin-bottom: 2px;
    }
    .ecb-date-value {
        display: block;
        font-size: 12.5px;
        font-weight: 600;
    }
    /* Due date in the footer -- always a light danger red, not only when
       overdue, per this redesign's own spec. */
    .ecb-due-value {
        color: rgba(var(--bs-danger-rgb, 220, 53, 69), 0.8);
    }

    /* -------------------------- Dark mode -------------------------- */
    html[data-theme-mode="dark"] .ecb-page::before {
        background:
            radial-gradient(at 15% 10%, rgba(var(--primary-rgb), 0.14) 0%, transparent 45%),
            radial-gradient(at 85% 30%, rgba(var(--primary-rgb), 0.08) 0%, transparent 50%);
    }
</style>

<div class="main-content app-content ecb-page">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">My Production Board</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="emp-dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="emp-content-production">Production Tasks</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Board</li>
                </ol>
            </div>
            <div class="ecb-toolbar">
                <div class="ecb-search">
                    <i class="ri-search-line"></i>
                    <input type="text" class="form-control form-control-sm" id="ecbSearch" placeholder="Search title or client…">
                </div>
                <button type="button" class="btn btn-sm btn-primary" id="ecbRefreshBtn">
                    <i class="ri-refresh-line"></i>
                </button>
                <a href="emp-content-production" class="btn btn-sm btn-light">
                    <i class="ri-table-line me-1"></i> Table View
                </a>
            </div>
        </div>

        <div id="ecbBoard">
            <div class="text-center text-muted py-5">Loading your board…</div>
        </div>

    </div>
</div>

<!-- Submit for review -- identical to employee/emp-content-production.php's own
     modal/handler, reused unchanged (same ids kept unique with the ecb- prefix
     since this is a separate page/document). -->
<div class="modal fade" id="ecbSubmitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Submit For Review</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ecbSubmitId">

                <label class="form-label d-block">Submission Method <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ecbSubmissionType" id="ecbTypeDrive" value="drive" checked>
                        <label class="form-check-label" for="ecbTypeDrive">Google Drive Link</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ecbSubmissionType" id="ecbTypeMedia" value="media">
                        <label class="form-check-label" for="ecbTypeMedia">Upload Media</label>
                    </div>
                </div>

                <div id="ecbDriveField" class="mb-3">
                    <label class="form-label" for="ecbDriveUrl">Google Drive Link <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="ecbDriveUrl" placeholder="https://drive.google.com/...">
                </div>

                <div id="ecbMediaField" class="mb-3 d-none">
                    <label class="form-label" for="ecbMediaFile">Upload Media <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="ecbMediaFile" accept="video/mp4,video/quicktime,video/webm,image/jpeg,image/png">
                    <div class="fs-11 text-muted mt-1">Video (mp4, mov, webm) or image (jpg, png), up to 100MB.</div>
                </div>

                <label class="form-label" for="ecbSubmitRemark">Note (optional)</label>
                <textarea class="form-control" id="ecbSubmitRemark" rows="2" placeholder="e.g. First cut ready for review"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="ecbSubmitSaveBtn">Submit Production</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail -- identical section structure (Task Overview / Content Brief /
     Production Output) to employee/emp-content-production.php's own modal. -->
<div class="modal fade" id="ecbDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="fs-13 text-muted text-uppercase mb-2">Task Overview</h6>
                <div id="ecbDetailScope" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Content Brief</h6>
                <div id="ecbDetailBrief" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Production Output</h6>
                <div id="ecbDetailOutput"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Production History -->
<div class="modal fade" id="ecbHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="ecbHistoryTimeline"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* simple vertical timeline for Production History -- identical pattern to
       employee/emp-content-production.php's own (.ecp-timeline), just scoped
       under the ecb- prefix for this page. */
    .ecb-timeline { position: relative; }
    .ecb-timeline-item { position: relative; padding-left: 1.25rem; padding-bottom: 1.1rem; }
    .ecb-timeline-item:last-child { padding-bottom: 0; }
    .ecb-timeline-item::before {
        content: '';
        position: absolute;
        left: 3px;
        top: 0.95rem;
        bottom: -0.1rem;
        width: 2px;
        background: var(--default-border);
    }
    .ecb-timeline-item:last-child::before { display: none; }
    .ecb-timeline-item::after {
        content: '';
        position: absolute;
        left: 0;
        top: 0.3rem;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary-color, #6c5ffc);
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/libs/sortablejs/Sortable.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/social-production-status.js"></script>

<script>
$(function () {

    // Shared with pages/social-content-production.php and
    // employee/emp-content-production.php — dist/assets/js/social-production-status.js
    // — so the same task's status badge/label reads the same on every
    // surface, not just this board.
    const { STATUS_LABEL, STATUS_COLOR, REVIEW_STATUS_COLOR } = window.SOCIAL_PRODUCTION_STATUS;
    // Card accent bar + status icon stay board-only (no equivalent on the
    // other two pages to drift out of sync with). 'orange' isn't a stock
    // Bootstrap utility in this theme's bundle, so a small
    // .bg-orange/.text-bg-orange rule is defined in this page's own
    // <style> block (Bootstrap's own canonical orange hex, #fd7e14) rather
    // than touching the shared stylesheet.
    const STATUS_HEX = {
        NEW: '#0dcaf0', ASSIGNED: 'rgb(var(--primary-rgb))', IN_PROGRESS: '#ffc107', SUBMITTED: '#fd7e14',
        CORRECTION: '#dc3545', APPROVED: '#198754', PRODUCTION_READY: '#198754'
    };
    const STATUS_ICON = {
        NEW: 'ri-inbox-line', ASSIGNED: 'ri-user-received-2-line', IN_PROGRESS: 'ri-loader-4-line',
        SUBMITTED: 'ri-send-plane-line', CORRECTION: 'ri-error-warning-line',
        APPROVED: 'ri-checkbox-circle-line', PRODUCTION_READY: 'ri-rocket-2-line'
    };

    let tasks = [];
    let sortableInstance = null;

    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }
    function notify(type, message) { if (window.showToast) window.showToast(type, message); else if (window.Swal) Swal.fire({ icon: type === 'danger' ? 'error' : type, text: message }); }

    function fmtDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function fmtShortDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    }
    function fmtDateTime(dt) {
        if (!dt) return '—';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    function compactBadge(color, label, iconClass) {
        const icon = iconClass ? `<i class="${iconClass} me-1"></i>` : '';
        return `<span class="badge bg-${color}">${icon}${esc(label)}</span>`;
    }
    // Status icon lives inside the status badge itself -- the card's only
    // other status-driven visual is the top accent bar (--ecb-accent).
    function statusBadge(status) {
        return compactBadge(STATUS_COLOR[status] || 'secondary', STATUS_LABEL[status] || status, STATUS_ICON[status]);
    }
    function reviewStatusBadge(task) {
        const reviewStatus = task.reviewStatus || 'Open';
        return compactBadge(REVIEW_STATUS_COLOR[reviewStatus] || 'secondary', reviewStatus);
    }
    function isOverdue(task) {
        return !!(task.dueAt && new Date(task.dueAt.replace(' ', 'T')) < new Date() && !['APPROVED', 'PRODUCTION_READY'].includes(task.status));
    }
    function classifyPostingType(raw) {
        const key = String(raw || '').trim().toLowerCase();
        if (!key) return '';
        return (key === 'story' || key === 'image story' || key === 'video story') ? 'Story' : 'Post';
    }
    function sourceLine(task) {
        if (task.sourceType === 'other') {
            return ['Other Content', task.editType].filter(Boolean).join(' · ');
        }
        if (task.sourceType === 'graphic') {
            return ['Other Graphic Content', task.graphicEditType].filter(Boolean).join(' · ');
        }
        const parts = [task.platformName];
        const postingType = classifyPostingType(task.postType);
        if (postingType) parts.push(postingType);
        return parts.filter(Boolean).join(' · ');
    }

    // ------------------------------------------------------------------
    // LOAD -- same emp-get-tasks.php endpoint the table page uses, no
    // filters: the board's whole purpose is to show everything currently
    // assigned, grouped by status, rather than one status-filtered slice.
    // ------------------------------------------------------------------
    function loadTasks() {
        $.ajax({
            url: 'api/social-content-production/emp-get-tasks.php',
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load your production board.');
                tasks = [];
                renderBoard();
                return;
            }
            tasks = res.data || [];
            renderBoard();
        }).fail(function () {
            notify('danger', 'Network error while loading your production board.');
            tasks = [];
            renderBoard();
        });
    }

    // ------------------------------------------------------------------
    // CARD ORDER PREFERENCE -- localStorage only, per employee, per
    // browser. Never sent to the server, never affects status/sorting on
    // the table page, purely a personal display convenience here. One
    // flat id order for the whole (now unified) board -- no more
    // per-status grouping to key it by.
    // ------------------------------------------------------------------
    const ORDER_KEY = 'ecbCardOrder_' + EMP_ID;
    function loadOrder() {
        try {
            const parsed = JSON.parse(localStorage.getItem(ORDER_KEY) || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }
    function saveOrder(ids) {
        try { localStorage.setItem(ORDER_KEY, JSON.stringify(ids)); } catch (e) { /* private mode / quota -- ignore, non-critical */ }
    }
    function orderedTasks(list) {
        const order = loadOrder();
        if (!order.length) return list;
        const byId = {};
        list.forEach(t => { byId[t.id] = t; });
        const ordered = [];
        order.forEach(id => { if (byId[id]) { ordered.push(byId[id]); delete byId[id]; } });
        // anything not in the saved order (new tasks) goes to the end, unchanged relative order
        list.forEach(t => { if (byId[t.id]) ordered.push(t); });
        return ordered;
    }

    // ------------------------------------------------------------------
    // ACTIONS -- exact same gating/logic as
    // employee/emp-content-production.php's actionsFor(): ASSIGNED/
    // CORRECTION -> Start/Resume, IN_PROGRESS -> Submit, everything else
    // has no editor action left to take.
    // ------------------------------------------------------------------
    function primaryActionButton(task) {
        if (task.status === 'ASSIGNED' || task.status === 'CORRECTION') {
            return `<button class="btn btn-sm btn-primary ecb-primary-action ecb-start" data-id="${task.id}"><i class="ri-play-line me-1"></i>${task.status === 'CORRECTION' ? 'Resume' : 'Start'}</button>`;
        }
        if (task.status === 'IN_PROGRESS') {
            return `<button class="btn btn-sm btn-success ecb-primary-action ecb-submit" data-id="${task.id}"><i class="ri-send-plane-line me-1"></i>Submit Work</button>`;
        }
        return '';
    }

    // Card top line -- Platform - Posting Type - Client Name (or the
    // equivalent Other Content/Other Graphic Content source parts) --
    // built fresh here rather than reusing sourceLine() (still used
    // unchanged by the detail modal's Source field below) since the card
    // needs the client name folded into the same hyphen-separated line,
    // which sourceLine() doesn't do.
    function cardTopLine(task) {
        let parts;
        if (task.sourceType === 'other') {
            parts = ['Other Content', task.editType];
        } else if (task.sourceType === 'graphic') {
            parts = ['Other Graphic Content', task.graphicEditType];
        } else {
            parts = [task.platformName, classifyPostingType(task.postType)];
        }
        parts.push(task.clientName);
        return parts.filter(Boolean).join(' - ');
    }

    function renderCard(task) {
        return `
            <div class="card custom-card border-0 card-style-7 ecb-card" data-id="${task.id}">
                <div class="card-body p-0">
                    <div class="p-3 card-content-1">
                        <div class="d-flex align-items-start justify-content-between">
                            <div class="ecb-card-source">${esc(cardTopLine(task))}</div>
                            <span class="ecb-drag-handle" title="Drag to reorder"><i class="ri-draggable"></i></span>
                        </div>
                        <div class="ecb-card-title">${task.title ? esc(task.title) : '<span class="text-muted fw-normal">Untitled</span>'}</div>
                        <div class="d-flex align-items-center justify-content-between ecb-card-actions">
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-light ecb-view" data-id="${task.id}"><i class="ri-eye-line me-1"></i>View Content</button>
                                ${primaryActionButton(task)}
                            </div>
                            <button class="ecb-icon-btn ecb-history" data-id="${task.id}" title="Production History"><i class="ri-history-line"></i></button>
                        </div>
                    </div>
                    <div class="p-3 card-content-2 d-flex align-items-center justify-content-between">
                        <div>
                            <span class="ecb-date-label">Due Date</span>
                            <span class="ecb-date-value ecb-due-value">${task.dueAt ? esc(fmtDateTime(task.dueAt)) : '—'}</span>
                        </div>
                        <div class="text-end">
                            <span class="ecb-date-label">Content Date</span>
                            <span class="ecb-date-value">${task.contentDate ? esc(fmtDate(task.contentDate)) : '—'}</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Status no longer groups cards into sections -- it is a purely visual
    // signal on each card (accent bar + badge + icon). All of the
    // employee's tasks render together in one unified grid, in the
    // employee's own saved drag order (or fetch order, for tasks that
    // don't have one yet).
    function renderBoard() {
        if (sortableInstance) { sortableInstance.destroy(); sortableInstance = null; }

        if (!tasks.length) {
            $('#ecbBoard').html('<div class="ecb-empty-board"><i class="ri-inbox-line"></i><div>No tasks assigned yet</div></div>');
            return;
        }

        const q = $('#ecbSearch').val().trim().toLowerCase();
        const filtered = q
            ? tasks.filter(t => (t.title || '').toLowerCase().includes(q) || (t.clientName || '').toLowerCase().includes(q))
            : tasks;

        if (!filtered.length) {
            $('#ecbBoard').html('<div class="ecb-empty-board"><i class="ri-search-line"></i><div>No tasks match your search</div></div>');
            return;
        }

        const list = orderedTasks(filtered);
        $('#ecbBoard').html(`<div class="ecb-grid" id="ecbGrid">${list.map(renderCard).join('')}</div>`);

        initSortable();
    }

    // ------------------------------------------------------------------
    // DRAG AND DROP -- a single Sortable instance over the whole unified
    // grid. There is only one list now, so there is nowhere for a card to
    // be dragged INTO other than a new position in that same list --
    // reordering is structurally the only thing a drag can do here.
    // onEnd only ever persists the resulting order to localStorage; it
    // never calls any API or touches task.status.
    // ------------------------------------------------------------------
    function initSortable() {
        const el = document.getElementById('ecbGrid');
        if (!el || typeof Sortable === 'undefined') return;

        sortableInstance = Sortable.create(el, {
            animation: 150,
            handle: '.ecb-drag-handle',
            draggable: '.ecb-card',
            ghostClass: 'ecb-drag-ghost',
            chosenClass: 'ecb-dragging',
            onEnd: function () {
                const visibleIds = Array.from(el.querySelectorAll('.ecb-card')).map(function (card) {
                    return Number(card.getAttribute('data-id'));
                });
                // While a search filter is active, only the matching cards
                // are draggable/visible -- merge their new order back into
                // the full saved order (rather than overwriting it) so
                // tasks currently hidden by the search never lose their
                // own remembered position.
                const visibleSet = new Set(visibleIds);
                let cursor = 0;
                const merged = loadOrder()
                    .map(id => visibleSet.has(id) ? visibleIds[cursor++] : id);
                while (cursor < visibleIds.length) merged.push(visibleIds[cursor++]);
                saveOrder(merged);
            }
        });
    }

    // ------------------------------------------------------------------
    // START -- identical call shape to
    // employee/emp-content-production.php's updateTask({action:'start'}).
    // ------------------------------------------------------------------
    function updateTask(payload, onSuccess) {
        $.ajax({
            url: 'api/social-content-production/emp-update-task.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Action failed.');
                return;
            }
            notify('success', 'Saved.');
            onSuccess && onSuccess();
            loadTasks();
        }).fail(function () {
            notify('danger', 'Network error.');
        });
    }

    $(document).on('click', '.ecb-start', function () {
        updateTask({ id: $(this).data('id'), action: 'start' });
    });

    // ------------------------------------------------------------------
    // SUBMIT -- identical multipart request to
    // employee/emp-content-production.php's own #ecpSubmitModal handler.
    // ------------------------------------------------------------------
    $(document).on('click', '.ecb-submit', function () {
        $('#ecbSubmitId').val($(this).data('id'));
        $('#ecbSubmitRemark').val('');
        $('#ecbDriveUrl').val('');
        $('#ecbMediaFile').val('');
        $('#ecbTypeDrive').prop('checked', true);
        $('#ecbDriveField').removeClass('d-none');
        $('#ecbMediaField').addClass('d-none');
        $('#ecbSubmitModal').modal('show');
    });

    $('input[name="ecbSubmissionType"]').on('change', function () {
        const isDrive = $('#ecbTypeDrive').is(':checked');
        $('#ecbDriveField').toggleClass('d-none', !isDrive);
        $('#ecbMediaField').toggleClass('d-none', isDrive);
    });

    $('#ecbSubmitSaveBtn').on('click', function () {
        const id = $('#ecbSubmitId').val();
        const submissionType = $('#ecbTypeDrive').is(':checked') ? 'drive' : 'media';
        const remark = $('#ecbSubmitRemark').val().trim();

        if (submissionType === 'drive' && !$('#ecbDriveUrl').val().trim()) {
            notify('danger', 'Enter a Google Drive link.');
            return;
        }
        if (submissionType === 'media' && !$('#ecbMediaFile')[0].files.length) {
            notify('danger', 'Choose a file to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('id', id);
        formData.append('submissionType', submissionType);
        formData.append('remark', remark);
        if (submissionType === 'drive') {
            formData.append('submissionUrl', $('#ecbDriveUrl').val().trim());
        } else {
            formData.append('media', $('#ecbMediaFile')[0].files[0]);
        }

        const $btn = $(this).prop('disabled', true);
        $.ajax({
            url: 'api/social-content-production/emp-submit-production.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to submit production.');
                return;
            }
            notify('success', 'Submitted for review.');
            $('#ecbSubmitModal').modal('hide');
            loadTasks();
        }).fail(function () {
            notify('danger', 'Network error while submitting.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ------------------------------------------------------------------
    // VIEW -- identical read-only Content Brief flow to
    // employee/emp-content-production.php's own .ecp-view handler.
    // ------------------------------------------------------------------
    $(document).on('click', '.ecb-view', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/emp-get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load task.'); return; }
                renderDetail(res.data);
                $('#ecbDetailModal').modal('show');
            });
    });

    $(document).on('click', '.ecb-history', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/emp-get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load history.'); return; }
                $('#ecbHistoryTimeline').html(renderHistoryTimeline(res.data.history || []));
                $('#ecbHistoryModal').modal('show');
            });
    });

    function linkOrText(value) {
        return /^https?:\/\//i.test(value)
            ? `<a href="${esc(value)}" target="_blank" rel="noopener noreferrer">${esc(value)}</a>`
            : esc(value);
    }

    function briefRow(label, value, isLink) {
        if (value === null || value === undefined) return '';
        const text = String(value).trim();
        if (text === '') return '';
        const rendered = isLink ? linkOrText(text) : esc(text).replace(/\n/g, '<br>');
        return `<div class="mb-2"><div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div><div class="fs-13">${rendered}</div></div>`;
    }

    function renderBrief(task) {
        if (task.sourceType === 'other') {
            const otherRows = [
                briefRow('Title', task.title),
                briefRow('Edit Type', task.editType),
                briefRow('Hook', task.hook),
                briefRow('Content Description', task.otherContentDescription),
                briefRow('Reference', task.otherReference),
                briefRow('Notes', task.otherNotes),
                briefRow('Deadline', task.deadlineDate)
            ].filter(Boolean).join('');

            return otherRows || '<div class="text-muted fs-13">No additional content details were provided.</div>';
        }
        if (task.sourceType === 'graphic') {
            const graphicRows = [
                briefRow('Content Name', task.title),
                briefRow('Edit Type', task.graphicEditType),
                briefRow('Priority', task.graphicPriority),
                briefRow('Raw Content', task.graphicRawContent),
                briefRow('Song URL', task.graphicSongUrl, true),
                briefRow('Reference', task.graphicReference),
                briefRow('Content Description', task.graphicContentDescription),
                briefRow('Notes', task.graphicNotes),
                briefRow('Deadline', task.graphicDeadlineAt ? fmtDateTime(task.graphicDeadlineAt) : null)
            ].filter(Boolean).join('');

            return graphicRows || '<div class="text-muted fs-13">No additional content details were provided.</div>';
        }

        const rows = [
            briefRow('Title', task.title),
            briefRow('Raw Content', task.rawContent),
            briefRow('Caption', task.caption),
            briefRow('Description', task.contentDescription),
            briefRow('Song / Audio', task.songUrl, true),
            briefRow('Reference (Idea)', task.ideaReference),
            briefRow('Reference Link', task.referenceLink, true),
            briefRow('Social Media Handle', task.socialMediaHandle),
            briefRow('Post Type', task.postType),
            briefRow('Data Entry Remarks', task.contentRemarks)
        ].filter(Boolean).join('');

        return rows || '<div class="text-muted fs-13">No additional content details were provided in Data Entry.</div>';
    }

    function renderOutput(task) {
        if (!task.submissionType || !task.submissionUrl) {
            return '<div class="text-muted fs-13">No production output submitted yet.</div>';
        }

        const isDrive = task.submissionType === 'drive';
        const url = task.submissionUrl;
        const ext = (String(url).split('.').pop() || '').toLowerCase().split(/[?#]/)[0];
        const isVideo = ['mp4', 'mov', 'webm'].includes(ext);

        const openLink = `<a href="${esc(url)}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">` +
            `<i class="ri-external-link-line"></i> ${isDrive ? 'Open Google Drive' : 'View / Open Media'}</a>`;

        const preview = (!isDrive && isVideo)
            ? `<video controls class="mt-2 d-block" style="max-width:100%; max-height:260px;"><source src="${esc(url)}"></video>`
            : '';

        const meta = [
            task.editorName ? 'Submitted by <b>' + esc(task.editorName) + '</b>' : '',
            task.submittedAt ? esc(fmtDateTime(task.submittedAt)) : ''
        ].filter(Boolean).join(' · ');

        return `
            <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="badge bg-info-transparent">${isDrive ? 'Google Drive' : 'Uploaded Media'}</span>
                ${openLink}
            </div>
            ${preview}
            ${meta ? `<div class="fs-12 text-muted mt-2">${meta}</div>` : ''}
        `;
    }

    function overviewCell(label, valueHtml) {
        return `<div class="col-6 col-md-4 col-lg-3 mb-2">
            <div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div>
            <div class="fs-13">${valueHtml}</div>
        </div>`;
    }

    function renderOverview(task) {
        return `<div class="row g-2">` +
            overviewCell('Client', esc(task.clientName)) +
            overviewCell('Source', esc(sourceLine(task) || (task.sourceType === 'other' ? 'Other Content' : 'Social Content'))) +
            overviewCell('Content Date', fmtDate(task.contentDate)) +
            overviewCell('Status', statusBadge(task.status)) +
            overviewCell('Due (TAT)', task.dueAt ? esc(fmtDateTime(task.dueAt)) : '—') +
            `</div>`;
    }

    function renderDetail(task) {
        $('#ecbDetailScope').html(renderOverview(task));
        $('#ecbDetailBrief').html(renderBrief(task));
        $('#ecbDetailOutput').html(renderOutput(task));
    }

    function renderHistoryTimeline(history) {
        if (!history.length) {
            return '<div class="text-muted fs-13">No history yet.</div>';
        }

        return `<div class="ecb-timeline">` + history.map(h => `
            <div class="ecb-timeline-item">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <span class="fw-semibold fs-13 text-capitalize">${esc(h.action.replace(/_/g, ' '))}</span>
                    <span class="text-muted fs-12">${esc(fmtDateTime(h.createdAt))}</span>
                </div>
                <div class="fs-12 text-muted">${esc(h.performedByName || 'Unknown')} (${esc(h.performedByType)})${h.oldStatus || h.newStatus ? ' · ' + esc(h.oldStatus || '-') + ' → ' + esc(h.newStatus || '-') : ''}</div>
                ${h.remark ? `<div class="fs-13 mt-1">${esc(h.remark)}</div>` : ''}
            </div>
        `).join('') + `</div>`;
    }

    // ------------------------------------------------------------------
    // EVENTS
    // ------------------------------------------------------------------
    $('#ecbRefreshBtn').on('click', loadTasks);

    let searchDebounce = null;
    $('#ecbSearch').on('input', function () {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(renderBoard, 150);
    });

    loadTasks();
});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
