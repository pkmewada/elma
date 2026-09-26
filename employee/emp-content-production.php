<?php
/*
|--------------------------------------------------------------------------
| My Production Tasks — Video Editor view
|--------------------------------------------------------------------------
|
| Shows only the production tasks assigned to the logged-in employee.
| assignedEditorId scoping is enforced server-side in
| api/social-content-production/emp-*.php, never trusted from the browser.
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
</script>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">My Production Tasks</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="emp-dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Production Tasks</li>
                </ol>
            </div>
            <a href="emp-content-board" class="btn btn-sm btn-light">
                <i class="ri-layout-grid-line me-1"></i> Board View
            </a>
        </div>

        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                <h5 class="mb-0">Assigned To Me</h5>

                <div class="d-flex gap-2 flex-wrap">
                    <input type="text" class="form-control form-control-sm" id="ecpDateRange"
                           placeholder="Select date or range" autocomplete="off" style="min-width:170px;">
                    <select class="form-select form-select-sm" id="ecpStatus" style="min-width:150px;">
                        <option value="">All Status</option>
                        <option value="ASSIGNED">Assigned</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="SUBMITTED">Submitted</option>
                        <option value="CORRECTION">Correction</option>
                        <option value="APPROVED">Approved</option>
                        <option value="PRODUCTION_READY">Production Ready</option>
                    </select>
                    <label class="d-flex align-items-center gap-1 fs-13 text-muted mb-0">
                        <input type="checkbox" class="form-check-input" id="ecpOverdue"> Overdue only
                    </label>
                    <button type="button" class="btn btn-sm btn-primary" id="ecpRefreshBtn">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Content</th>
                                <th class="text-center">View Content</th>
                                <th class="text-center">Status</th>
                                <th>Date Time</th>
                                <th class="text-center">Updated Work</th>
                                <th class="text-center">Review</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ecpBody">
                            <tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Submit for review -->
<div class="modal fade" id="ecpSubmitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Submit For Review</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ecpSubmitId">

                <label class="form-label d-block">Submission Method <span class="text-danger">*</span></label>
                <div class="d-flex gap-3 mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ecpSubmissionType" id="ecpTypeDrive" value="drive" checked>
                        <label class="form-check-label" for="ecpTypeDrive">Google Drive Link</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="ecpSubmissionType" id="ecpTypeMedia" value="media">
                        <label class="form-check-label" for="ecpTypeMedia">Upload Media</label>
                    </div>
                </div>

                <div id="ecpDriveField" class="mb-3">
                    <label class="form-label" for="ecpDriveUrl">Google Drive Link <span class="text-danger">*</span></label>
                    <input type="url" class="form-control" id="ecpDriveUrl" placeholder="https://drive.google.com/...">
                </div>

                <div id="ecpMediaField" class="mb-3 d-none">
                    <label class="form-label" for="ecpMediaFile">Upload Media <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="ecpMediaFile" accept="video/mp4,video/quicktime,video/webm,image/jpeg,image/png">
                    <div class="fs-11 text-muted mt-1">Video (mp4, mov, webm) or image (jpg, png), up to 100MB.</div>
                </div>

                <label class="form-label" for="ecpSubmitRemark">Note (optional)</label>
                <textarea class="form-control" id="ecpSubmitRemark" rows="2" placeholder="e.g. First cut ready for review"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="ecpSubmitSaveBtn">Submit Production</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail -->
<div class="modal fade" id="ecpDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6 class="fs-13 text-muted text-uppercase mb-2">Task Overview</h6>
                <div id="ecpDetailScope" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Content Brief</h6>
                <div id="ecpDetailBrief" class="mb-3"></div>
                <h6 class="fs-13 text-muted text-uppercase mb-2">Production Output</h6>
                <div id="ecpDetailOutput"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Production History (separate from the detail modal above — a task's
     history can grow long, and mixes with brief/output review poorly) -->
<div class="modal fade" id="ecpHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="ecpHistoryTimeline"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* simple vertical timeline for Production History — a border-left line
       with a dot per item, no charting/timeline library needed */
    .ecp-timeline { position: relative; }
    .ecp-timeline-item { position: relative; padding-left: 1.25rem; padding-bottom: 1.1rem; }
    .ecp-timeline-item:last-child { padding-bottom: 0; }
    .ecp-timeline-item::before {
        content: '';
        position: absolute;
        left: 3px;
        top: 0.95rem;
        bottom: -0.1rem;
        width: 2px;
        background: var(--default-border);
    }
    .ecp-timeline-item:last-child::before { display: none; }
    .ecp-timeline-item::after {
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
<script src="<?= ASSET_URL ?>/assets/js/social-production-status.js"></script>

<script>
$(function () {

    // Shared with pages/social-content-production.php and
    // employee/emp-content-board.php — dist/assets/js/social-production-status.js
    // — so an editor sees the same badge colors a manager does for the same
    // value. Review column stays read-only here regardless (editors cannot
    // set/approve their own review status — no approve action exists
    // anywhere on this page or its APIs).
    const { STATUS_LABEL, STATUS_COLOR, REVIEW_STATUS_COLOR } = window.SOCIAL_PRODUCTION_STATUS;

    let tasks = [];

    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }
    function notify(type, message) { if (window.showToast) window.showToast(type, message); else if (window.Swal) Swal.fire({ icon: type === 'danger' ? 'error' : type, text: message }); }

    function fmtDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function fmtDateTime(dt) {
        if (!dt) return '—';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    // Compact badges (.badge.bg-{color}) -- same styling convention
    // pages/social-content-production.php's Production Queue uses for
    // Status/Review, kept here purely for visual/list parity. No status
    // logic lives in this helper, only display.
    function compactBadge(color, label) {
        return `<span class="badge bg-${color}">${esc(label)}</span>`;
    }

    function statusBadge(status) {
        return compactBadge(STATUS_COLOR[status] || 'secondary', STATUS_LABEL[status] || status);
    }

    // Read-only -- editors never set this (no approve/review action exists
    // on this page or its APIs); it just mirrors whatever the manager has
    // set on the Production Queue, same field the API already returns.
    function reviewStatusBadge(task) {
        const reviewStatus = task.reviewStatus || 'Open';
        return compactBadge(REVIEW_STATUS_COLOR[reviewStatus] || 'secondary', reviewStatus);
    }

    function dueCell(task) {
        if (!task.dueAt) return '<span class="text-muted">—</span>';
        const overdue = new Date(task.dueAt.replace(' ', 'T')) < new Date() && !['APPROVED', 'PRODUCTION_READY'].includes(task.status);
        return `<span class="${overdue ? 'text-danger fw-semibold' : ''}">${esc(fmtDateTime(task.dueAt))}${overdue ? ' <i class="ri-error-warning-line" title="Overdue"></i>' : ''}</span>`;
    }

    // "Date Time" column -- Assigned + Due shown vertically in one cell,
    // same layout pages/social-content-production.php's Production Queue
    // uses. dueCell()'s overdue styling is reused unchanged.
    function dateTimeCell(task) {
        return `
            <div class="fs-11 text-uppercase text-muted fw-semibold">Assigned</div>
            <div class="fs-12 mb-1">${task.assignedAt ? esc(fmtDateTime(task.assignedAt)) : '<span class="text-muted">—</span>'}</div>
            <div class="fs-11 text-uppercase text-muted fw-semibold">Due</div>
            <div class="fs-12">${dueCell(task)}</div>
        `;
    }

    // Posting Type (Post/Story), derived from clientSocialContent.postType --
    // same small display-only classification
    // pages/social-content-production.php's Production Queue uses (which
    // itself mirrors pages/social-overview.php's classifyPostType(), see
    // docs/SOCIAL_CONTENT_PRODUCTION_FOUNDATION.md). Only needed for the
    // Content column's format below.
    function classifyPostingType(raw) {
        const key = String(raw || '').trim().toLowerCase();
        if (!key) return '';
        return (key === 'story' || key === 'image story' || key === 'video story') ? 'Story' : 'Post';
    }

    // No year -- matches the Production Queue's own Content column date
    // format exactly. Kept separate from fmtDate() above (which the Task
    // Overview modal still uses, unchanged) so that modal's own date
    // display is not affected by this list-only formatting choice.
    function fmtShortDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short' });
    }

    // Content column -- Platform - Posting Type - Date / Title for Social
    // Content, matching pages/social-content-production.php's Production
    // Queue exactly. Other Content (no platform/posting-type concept)
    // shows "Other Content - {editType} - Date" / Title instead, same
    // two-line hierarchy, never an empty Platform value.
    function contentCell(task) {
        if (task.sourceType === 'other') {
            const line1 = ['Other Content', task.editType, fmtShortDate(task.contentDate)].filter(Boolean).map(esc).join(' - ');
            return `
                <div>${line1}</div>
                <div class="text-muted small">${task.title ? esc(task.title) : '—'}</div>
            `;
        }
        if (task.sourceType === 'graphic') {
            const line1 = ['Other Graphic Content', task.graphicEditType, fmtShortDate(task.contentDate)].filter(Boolean).map(esc).join(' - ');
            return `
                <div>${line1}</div>
                <div class="text-muted small">${task.title ? esc(task.title) : '—'}</div>
            `;
        }

        const line1Parts = [esc(task.platformName)];
        const postingType = classifyPostingType(task.postType);
        if (postingType) line1Parts.push(esc(postingType));
        line1Parts.push(fmtShortDate(task.contentDate));

        return `
            <div>${line1Parts.join(' - ')}</div>
            <div class="text-muted small">${task.title ? esc(task.title) : '—'}</div>
        `;
    }

    // "View Content" column -- the eye icon, its own column now (was
    // previously inline in Action). Opens the exact same, unchanged Detail
    // modal (.ecp-view's existing click handler below) -- nothing about
    // what it shows changed, only where the trigger sits in the row.
    function viewContentCell(task) {
        return `<button class="btn btn-sm btn-outline-primary ecp-view" data-id="${task.id}" title="View content details"><i class="ri-eye-line"></i></button>`;
    }

    // "Updated Work" column -- shown only once something has actually been
    // submitted (submissionUrl set by submitProduction()), same gating the
    // Production Queue's own Updated Work column uses. Editors have no
    // review-setting UI (they cannot approve their own work), so this
    // reuses the exact same .ecp-view click behavior as View Content above
    // rather than introducing a second modal -- the existing Detail modal
    // already includes the Production Output section that shows this.
    function updatedWorkCell(task) {
        if (!task.submissionUrl) return '<span class="text-muted">—</span>';
        return `<button class="btn btn-sm btn-warning ecp-view" data-id="${task.id}" title="View submitted work"><i class="ri-clipboard-line"></i></button>`;
    }

    // Date Range filter — same flatpickr range-picker pattern as
    // pages/social-data-entry.php / pages/social-content-production.php (one
    // text input, mode:'range'), defaulting to the current month. Filters by
    // clientSocialContent.contentDate on the server, same column the manager
    // page's date range uses.
    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function defaultDateRange() {
        const now = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        const last = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        return [toYMD(first), toYMD(last)];
    }

    const [defFrom, defTo] = defaultDateRange();
    let dateFrom = defFrom;
    let dateTo = defTo;

    const dateRangeFp = flatpickr('#ecpDateRange', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd M Y',
        defaultDate: [dateFrom, dateTo],
        onClose: function (selectedDates) {
            if (!selectedDates.length) return;
            const from = toYMD(selectedDates[0]);
            const to = selectedDates.length > 1 ? toYMD(selectedDates[1]) : from;
            if (from === dateFrom && to === dateTo) return;
            dateFrom = from;
            dateTo = to;
            loadTasks();
        }
    });

    function loadTasks() {
        $('#ecpBody').html('<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/social-content-production/emp-get-tasks.php',
            data: {
                status: $('#ecpStatus').val(),
                fromDate: dateFrom,
                toDate: dateTo,
                overdue: $('#ecpOverdue').is(':checked') ? '1' : '0'
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load your production tasks.');
                tasks = [];
                renderRows();
                return;
            }
            tasks = res.data || [];
            renderRows();
        }).fail(function () {
            notify('danger', 'Network error while loading your production tasks.');
            tasks = [];
            renderRows();
        });
    }

    // View Content moved into its own column (viewContentCell()) --
    // Action keeps exactly the employee-specific actions it already had
    // (Start/Resume/Submit, History), nothing removed from what those
    // buttons do.
    function actionsFor(task) {
        const btns = [];
        if (task.status === 'ASSIGNED' || task.status === 'CORRECTION') {
            btns.push(`<button class="btn btn-sm btn-primary ecp-start" data-id="${task.id}"><i class="ri-play-line"></i> ${task.status === 'CORRECTION' ? 'Resume' : 'Start'}</button>`);
        }
        if (task.status === 'IN_PROGRESS') {
            btns.push(`<button class="btn btn-sm btn-success ecp-submit" data-id="${task.id}"><i class="ri-send-plane-line"></i> Submit</button>`);
        }
        btns.push(`<button class="btn btn-sm btn-outline-secondary ecp-history" data-id="${task.id}" title="Production History"><i class="ri-history-line"></i></button>`);
        return btns.join(' ');
    }

    function renderRows() {
        if (!tasks.length) {
            $('#ecpBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No production tasks assigned to you right now.</td></tr>');
            return;
        }

        $('#ecpBody').html(tasks.map(function (task) {
            return `
                <tr>
                    <td>
                        <div class="fw-semibold">${esc(task.clientName)}</div>
                    </td>
                    <td>${contentCell(task)}</td>
                    <td class="text-center">${viewContentCell(task)}</td>
                    <td class="text-center">${statusBadge(task.status)}</td>
                    <td>${dateTimeCell(task)}</td>
                    <td class="text-center">${updatedWorkCell(task)}</td>
                    <td class="text-center">${reviewStatusBadge(task)}</td>
                    <td class="text-end text-nowrap">${actionsFor(task)}</td>
                </tr>
            `;
        }).join(''));
    }

    $(document).on('click', '.ecp-start', function () {
        updateTask({ id: $(this).data('id'), action: 'start' });
    });

    $(document).on('click', '.ecp-submit', function () {
        $('#ecpSubmitId').val($(this).data('id'));
        $('#ecpSubmitRemark').val('');
        $('#ecpDriveUrl').val('');
        $('#ecpMediaFile').val('');
        $('#ecpTypeDrive').prop('checked', true);
        $('#ecpDriveField').removeClass('d-none');
        $('#ecpMediaField').addClass('d-none');
        $('#ecpSubmitModal').modal('show');
    });

    $('input[name="ecpSubmissionType"]').on('change', function () {
        const isDrive = $('#ecpTypeDrive').is(':checked');
        $('#ecpDriveField').toggleClass('d-none', !isDrive);
        $('#ecpMediaField').toggleClass('d-none', isDrive);
    });

    $('#ecpSubmitSaveBtn').on('click', function () {
        const id = $('#ecpSubmitId').val();
        const submissionType = $('#ecpTypeDrive').is(':checked') ? 'drive' : 'media';
        const remark = $('#ecpSubmitRemark').val().trim();

        if (submissionType === 'drive' && !$('#ecpDriveUrl').val().trim()) {
            notify('danger', 'Enter a Google Drive link.');
            return;
        }
        if (submissionType === 'media' && !$('#ecpMediaFile')[0].files.length) {
            notify('danger', 'Choose a file to upload.');
            return;
        }

        const formData = new FormData();
        formData.append('id', id);
        formData.append('submissionType', submissionType);
        formData.append('remark', remark);
        if (submissionType === 'drive') {
            formData.append('submissionUrl', $('#ecpDriveUrl').val().trim());
        } else {
            formData.append('media', $('#ecpMediaFile')[0].files[0]);
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
            $('#ecpSubmitModal').modal('hide');
            loadTasks();
        }).fail(function () {
            notify('danger', 'Network error while submitting.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.ecp-view', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/emp-get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load task.'); return; }
                renderDetail(res.data);
                $('#ecpDetailModal').modal('show');
            });
    });

    // History is reached from its own action, in its own modal — the same
    // emp-get-tasks.php?id= response already carries history (getTask()
    // attaches it), so no new API/query is needed for this.
    $(document).on('click', '.ecp-history', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/emp-get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load history.'); return; }
                $('#ecpHistoryTimeline').html(renderHistoryTimeline(res.data.history || []));
                $('#ecpHistoryModal').modal('show');
            });
    });

    // Read-only content brief — sourced entirely from clientSocialContent via
    // the engine's existing join, never editable here. Data Entry owns these
    // fields; Production only displays them.
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

    // Production output — the editor's actual submission (Drive link or
    // uploaded file). Read from socialContentProduction.submissionType/Url
    // (the LATEST submission only); the editor's own note for it lives in
    // the matching 'submitted' history entry, shown there rather than
    // duplicated here.
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

    // Task Overview — the same identifying fields the old inline scope strip
    // showed, just labeled and grid-arranged like Content Brief's rows
    // instead of one flex line. No new data: still exactly clientName,
    // platformName, featureName, contentDate, status, dueAt. No "Assigned
    // Editor" cell here (unlike the manager page) — it's always the logged-in
    // editor themselves, same as the original scope strip never showed it.
    function overviewCell(label, valueHtml) {
        return `<div class="col-6 col-md-4 col-lg-3 mb-2">
            <div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div>
            <div class="fs-13">${valueHtml}</div>
        </div>`;
    }

    function renderOverview(task) {
        return `<div class="row g-2">` +
            overviewCell('Client', esc(task.clientName)) +
            overviewCell('Platform', esc(task.platformName)) +
            overviewCell('Plan', esc(task.featureName)) +
            overviewCell('Content Date', fmtDate(task.contentDate)) +
            overviewCell('Status', statusBadge(task.status)) +
            overviewCell('Due (TAT)', dueCell(task)) +
            `</div>`;
    }

    function renderDetail(task) {
        $('#ecpDetailScope').html(renderOverview(task));
        $('#ecpDetailBrief').html(renderBrief(task));
        $('#ecpDetailOutput').html(renderOutput(task));
    }

    // Full history, oldest first (the engine already returns it ORDER BY
    // h.id ASC) rendered top-to-bottom as a vertical timeline -- same event
    // data/fields as before, just its own modal instead of living inside
    // the detail modal.
    function renderHistoryTimeline(history) {
        if (!history.length) {
            return '<div class="text-muted fs-13">No history yet.</div>';
        }

        return `<div class="ecp-timeline">` + history.map(h => `
            <div class="ecp-timeline-item">
                <div class="d-flex justify-content-between flex-wrap gap-2">
                    <span class="fw-semibold fs-13 text-capitalize">${esc(h.action.replace(/_/g, ' '))}</span>
                    <span class="text-muted fs-12">${esc(fmtDateTime(h.createdAt))}</span>
                </div>
                <div class="fs-12 text-muted">${esc(h.performedByName || 'Unknown')} (${esc(h.performedByType)})${h.oldStatus || h.newStatus ? ' · ' + esc(h.oldStatus || '-') + ' → ' + esc(h.newStatus || '-') : ''}</div>
                ${h.remark ? `<div class="fs-13 mt-1">${esc(h.remark)}</div>` : ''}
            </div>
        `).join('') + `</div>`;
    }

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

    $('#ecpRefreshBtn').on('click', loadTasks);
    $('#ecpStatus').on('change', loadTasks);
    $('#ecpOverdue').on('change', loadTasks);

    loadTasks();
});
</script>

<?php include __DIR__ . '/../includes/emp-footer.php'; ?>
