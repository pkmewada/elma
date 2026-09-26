$(function () {

    // Shared with employee/emp-content-production.php and
    // employee/emp-content-board.php — dist/assets/js/social-production-status.js
    // — so the same task shows the same badge everywhere.
    const { STATUS_LABEL, STATUS_COLOR, REVIEW_STATUS_COLOR } = window.SOCIAL_PRODUCTION_STATUS;
    const REVIEW_STATUSES_REQUIRING_REMARK = ['Not Approved', 'Not For Use'];

    let tasks = [];

    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }
    function notify(type, message) { if (window.showToast) window.showToast(type, message); }

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

    // No year -- the Content column's "Platform - Posting Type - Date" line
    // is the only caller, and a production-planning date within the
    // current/next few weeks never needs the year to stay unambiguous.
    function fmtDate(d) {
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

    // Production Queue status indicators — compact Bootstrap badges
    // (.badge.bg-{color}). An outline-button style was tried for these and
    // reverted: too large/prominent for a read-only indicator next to real
    // action buttons in the same row.
    function compactBadge(color, label, iconHtml, title) {
        return `<span class="badge bg-${color}"${title ? ` title="${esc(title)}"` : ''}>${iconHtml || ''}${esc(label)}</span>`;
    }

    // Status is now the single workflow indicator — there is no separate
    // Review column any more (a raw production status like "Approved" next
    // to a review-derived label like "Send to Caption" was two competing
    // states on the same row). The reviewStatus column value itself, and
    // every backend approval/review rule that reads it, are unchanged —
    // this is presentation only. Status stays clickable, reusing the exact
    // same Production Output/review modal (.scp-output) the old Review
    // column used to trigger. Once a task is sent to Caption
    // (sentToCaptionAt set), the task is locked in Production — the badge
    // shows "Sent To Caption" instead of the real status and is no longer
    // clickable, since review actions are no longer allowed here at all.
    function statusBadge(task) {
        if (task.sentToCaptionAt) {
            return compactBadge('dark', 'Sent To Caption');
        }

        const label = STATUS_LABEL[task.status] || task.status;
        const color = STATUS_COLOR[task.status] || 'secondary';
        return `<span class="scp-output" data-id="${task.id}" style="cursor:pointer;" title="Click to view production output">${compactBadge(color, label)}</span>`;
    }

    // Must match SocialContentCaptionEngine::ELIGIBLE_REVIEW_STATUSES
    // (includes/socialContentCaptionEngine.php) — the same, single
    // approved-state definition Caption Area itself gates on, not a new
    // list invented on the frontend.
    const CAPTION_ELIGIBLE_REVIEW_STATUSES = ['Approved By Team', 'Approved By Client'];

    // Must match SocialContentProductionEngine::REVIEWABLE_STATUSES
    // (includes/SocialContentProductionEngine.php) — the same statuses the
    // backend already requires before a review status update is allowed
    // ("This task has no submitted output to review yet."). Used here only
    // to decide whether the Production Output modal is worth opening at
    // all, not to duplicate that server-side rule.
    const REVIEWABLE_STATUSES = ['SUBMITTED', 'CORRECTION', 'APPROVED', 'PRODUCTION_READY'];

    function dueCell(task) {
        if (!task.dueAt) return '<span class="text-muted">—</span>';
        const overdue = new Date(task.dueAt.replace(' ', 'T')) < new Date() && !['APPROVED', 'PRODUCTION_READY'].includes(task.status);
        return `<span class="${overdue ? 'text-danger fw-semibold' : ''}">${esc(fmtDateTime(task.dueAt))}${overdue ? ' <i class="ri-error-warning-line" title="Overdue"></i>' : ''}</span>`;
    }

    // "Date Time" column — compact "Label: value" per line (was a taller
    // 4-line stack with the label on its own row). Uses the exact same
    // assignedAt/dueAt data and dueCell() (overdue styling included),
    // unchanged — only the markup/spacing changed. fmtDateTime() already
    // omits the year (see its own comment above).
    function dateTimeCell(task) {
        return `
            <div class="fs-12 production-date-line" style="white-space: nowrap;"><span class="text-muted">Assigned:</span> ${task.assignedAt ? esc(fmtDateTime(task.assignedAt)) : '<span class="text-muted">—</span>'}</div>
            <div class="fs-12 production-date-line" style="white-space: nowrap;"><span class="text-muted">Due:</span> ${dueCell(task)}</div>
        `;
    }

    // Editor column — Assign/Reassign lives here instead of the Action
    // column. No editor yet -> "Assign Editor" button; editor already
    // assigned -> a button showing just their first name. Either button
    // opens the same #scpAssignModal (unchanged assign()/manage-task.php
    // logic) — the modal's own heading (set in the .scp-assign click
    // handler below) is what reads "Assign Editor" vs "Re-Assign Editor".
    // No status gating here: a click on a task the engine's own
    // ASSIGNABLE_STATUSES no longer allows reassigning simply gets the
    // engine's existing rejection message via the normal manageTask() error
    // toast — reusing that, not adding a new check.
    function editorCell(task) {
        if (!task.editorName) {
            return `<button class="btn btn-sm btn-primary text-nowrap scp-assign" data-id="${task.id}"><i class="ri-user-add-line"></i> Assign Editor</button>`;
        }
        const firstName = String(task.editorName).trim().split(/\s+/)[0];
        return `<button class="btn btn-sm btn-outline-secondary scp-assign" data-id="${task.id}" title="${esc(task.editorName)}">${esc(firstName)}</button>`;
    }

    // Posting Type (Post/Story), derived from clientSocialContent.postType —
    // same classification pages/social-overview.php's classifyPostType()
    // already documents (duplicated per-page, this codebase's existing
    // convention, no shared JS module). Only the top-level Post/Story label
    // is needed for this column, not the full format breakdown, so this is
    // a smaller, display-only version of that mapping. Entries saved before
    // postType existed (or via social-data-entry.php, which never collects
    // it) simply have nothing to show here — omitted, never guessed.
    function classifyPostingType(raw) {
        const key = String(raw || '').trim().toLowerCase();
        if (!key) return '';
        return (key === 'story' || key === 'image story' || key === 'video story') ? 'Story' : 'Post';
    }

    // Content column — Platform - Posting Type - Date on the first line,
    // Content Title on the second, for Social Content (task.sourceType ===
    // 'social'). Same data already selected by the engine (platformName,
    // postType, contentDate, title); only the presentation/hierarchy
    // changed. Other Content tasks (no platform/posting-type concept)
    // mirror the same two-line hierarchy instead: "Other Content -
    // {editType} - Date" / Title.
    // Title line is capped to one line with an ellipsis (title="" carries
    // the full text) rather than left to wrap — a long title no longer
    // grows the row height.
    function titleLine(title) {
        const text = title ? esc(title) : '—';
        return `<div class="text-muted small text-truncate" style="max-width: 220px;" title="${esc(title || '')}">${text}</div>`;
    }

    function contentCell(task) {
        if (task.sourceType === 'other') {
            const line1 = ['Other Content', task.editType, fmtDate(task.contentDate)].filter(Boolean).map(esc).join(' - ');
            return `
                <div>${line1}</div>
                ${titleLine(task.title)}
            `;
        }
        if (task.sourceType === 'graphic') {
            const line1 = ['Other Graphic Content', task.graphicEditType, fmtDate(task.contentDate)].filter(Boolean).map(esc).join(' - ');
            return `
                <div>${line1}</div>
                ${titleLine(task.title)}
            `;
        }

        const line1Parts = [esc(task.platformName)];
        const postingType = classifyPostingType(task.postType);
        if (postingType) line1Parts.push(esc(postingType));
        line1Parts.push(fmtDate(task.contentDate));

        return `
            <div>${line1Parts.join(' - ')}</div>
            ${titleLine(task.title)}
        `;
    }

    // "View Content" column — the eye icon that used to live in Action,
    // opening the same unchanged View (Content Brief) modal via the same
    // .scp-view class/handler.
    function viewContentCell(task) {
        return `<button class="btn btn-sm btn-outline-primary scp-view" data-id="${task.id}" title="View content details"><i class="ri-eye-line"></i></button>`;
    }

    // ------------------------------------------------------------------
    // BOOTSTRAP FILTERS
    // ------------------------------------------------------------------
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

    // Date Range filter — same flatpickr range-picker pattern as
    // pages/social-data-entry.php (one text input, mode:'range'), replacing
    // the old Month dropdown. Defaults to the current month, same as that
    // page. Filters by clientSocialContent.contentDate, same column the old
    // month filter used — never createdAt/updatedAt/dueAt.
    const [defFrom, defTo] = defaultDateRange();
    let dateFrom = defFrom;
    let dateTo = defTo;

    const dateRangeFp = flatpickr('#scpDateRange', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd M',
        defaultDate: [dateFrom, dateTo],
        onClose: function (selectedDates) {
            if (!selectedDates.length) return;
            const from = toYMD(selectedDates[0]);
            const to = selectedDates.length > 1 ? toYMD(selectedDates[1]) : from;
            if (from === dateFrom && to === dateTo) return;
            dateFrom = from;
            dateTo = to;
            refreshAll();
        }
    });

    $.ajax({ url: 'api/social-content-production/get-editors.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            // one fetch, used to populate both the page filter and the
            // Assign Editor modal dropdown — no second editor query
            const options = res.data.map(e => `<option value="${e.id}">${esc(e.fullName)}</option>`).join('');
            $('#scpEditor').append(options);
            $('#scpAssignEditor').html('<option value="">Select editor…</option>' + options);
        }
    });
    $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            $('#scpClient').append(res.data.map(c => `<option value="${c.id}">${esc(c.fullName)}</option>`).join(''));
        }
    });
    $.ajax({ url: 'api/deliverables/get-platforms.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            $('#scpPlatform').append(res.data.map(p => `<option value="${p.id}">${esc(p.platformName)}</option>`).join(''));
        }
    });

    // ------------------------------------------------------------------
    // LOAD + RENDER
    // ------------------------------------------------------------------
    function loadTasks() {
        $('#scpBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/social-content-production/get-tasks.php',
            data: {
                status: $('#scpStatus').val(),
                editorId: $('#scpEditor').val(),
                clientId: $('#scpClient').val(),
                platformId: $('#scpPlatform').val(),
                source: $('#scpSource').val(),
                fromDate: dateFrom,
                toDate: dateTo,
                overdue: $('#scpOverdue').is(':checked') ? '1' : '0'
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load production tasks.');
                tasks = [];
                renderRows();
                return;
            }
            tasks = res.data || [];
            renderRows();
        }).fail(function () {
            notify('danger', 'Network error while loading production tasks.');
            tasks = [];
            renderRows();
        });
    }

    // Phase 6 — server-derived summary + editor workload. Deliberately a
    // separate request from loadTasks(): the summary only respects
    // client/platform/date-range (it exists to show the breakdown ACROSS
    // statuses/editors, so those two dimensions aren't filter inputs here).
    // Never computed from tasks[] client-side, since that array already
    // reflects whatever status/editor filter is active.
    function loadSummary() {
        $.ajax({
            url: 'api/social-content-production/get-summary.php',
            data: {
                clientId: $('#scpClient').val(),
                platformId: $('#scpPlatform').val(),
                fromDate: dateFrom,
                toDate: dateTo
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load production summary.');
                return;
            }
            renderSummary(res.data);
        }).fail(function () {
            notify('danger', 'Network error while loading production summary.');
        });
    }

    function renderSummary(summary) {
        const counts = summary.statusCounts || {};
        ['NEW', 'ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'CORRECTION', 'APPROVED', 'PRODUCTION_READY'].forEach(function (status) {
            $('#scpCount' + status).text(counts[status] != null ? counts[status] : 0);
        });
        $('#scpCountOverdue').text(summary.overdueCount != null ? summary.overdueCount : 0);

        // Other Content's own summary row was removed from this page (no
        // longer linked to Production) -- summary.otherStatusCounts/
        // otherOverdueCount are still returned by the API (historical data
        // stays intact and queryable via the Source filter/table), just no
        // longer rendered here.

        // Other Graphic Content — its own summary row, same status set,
        // never mixed into the Social Content or Other Content counts.
        const graphicCounts = summary.graphicStatusCounts || {};
        ['NEW', 'ASSIGNED', 'IN_PROGRESS', 'SUBMITTED', 'CORRECTION', 'APPROVED', 'PRODUCTION_READY'].forEach(function (status) {
            $('#scpGraphicCount' + status).text(graphicCounts[status] != null ? graphicCounts[status] : 0);
        });
        $('#scpGraphicCountOverdue').text(summary.graphicOverdueCount != null ? summary.graphicOverdueCount : 0);

        const workload = summary.editorWorkload || [];
        if (!workload.length) {
            $('#scpWorkloadBody').html('<tr><td colspan="6" class="text-center text-muted py-3">No active Video Editors found.</td></tr>');
            return;
        }

        $('#scpWorkloadBody').html(workload.map(function (w) {
            return `
                <tr>
                    <td>${esc(w.editorName)}</td>
                    <td class="text-center">${w.assignedCount}</td>
                    <td class="text-center">${w.inProgressCount}</td>
                    <td class="text-center">${w.submittedCount}</td>
                    <td class="text-center">${w.correctionCount ? '<span class="text-danger fw-semibold">' + w.correctionCount + '</span>' : '0'}</td>
                    <td class="text-center">${w.overdueCount ? '<span class="text-danger fw-semibold">' + w.overdueCount + '</span>' : '0'}</td>
                </tr>
            `;
        }).join(''));
    }

    // Assign/Reassign moved into the Editor column (editorCell()) and View
    // moved into its own "View Content" column (viewContentCell()) — Action
    // now holds Send to Caption (Social Content) / the Other Graphic
    // Content Automation-or-Client choice (sourceType='graphic'), and
    // History. Send to Automation no longer appears here at all —
    // Automation now only ever originates from Caption Area (after a
    // caption is selected) or, for Other Graphic Content, the new choice
    // modal below. The existing Automation Queue's own Send/Retry action
    // (pages/social-automation.php) is untouched.
    //
    // Mark Ready no longer shows for Social Content: an APPROVED social
    // task used to need BOTH Mark Ready (-> PRODUCTION_READY) and Send to
    // Caption clicked, which read as two parallel/competing actions on the
    // same row for no reason a manager could see. sendToCaption() now
    // performs that same PRODUCTION_READY transition itself as part of
    // sending (includes/SocialContentProductionEngine.php) —
    // SocialAutomationHandoffEngine's own PRODUCTION_READY requirement for
    // Automation eligibility is unchanged, just no longer a separate manual
    // step. Other Graphic Content has no such fold available (its own Send
    // action is a different, later button gated on PRODUCTION_READY) and
    // Other Content has no other way to reach PRODUCTION_READY at all, so
    // Mark Ready still shows for both of those, unchanged.
    function actionsFor(task) {
        const btns = [];
        if (task.status === 'APPROVED' && task.sourceType !== 'social') {
            btns.push(`<button class="btn btn-sm btn-success scp-mark-ready" data-id="${task.id}"><i class="ri-checkbox-circle-line"></i> Mark Ready</button>`);
        }
        if (task.sourceType === 'social') {
            const btn = sendToCaptionButton(task);
            if (btn) btns.push(btn);
        } else if (task.sourceType === 'graphic' && task.status === 'PRODUCTION_READY') {
            btns.push(`<button class="btn btn-sm btn-dark scp-graphic-choice" data-id="${task.id}"><i class="ri-send-plane-line"></i> Send</button>`);
        }
        btns.push(`<button class="btn btn-sm btn-outline-secondary scp-history" data-id="${task.id}" title="Production History"><i class="ri-history-line"></i></button>`);
        return btns.join(' ');
    }

    // Send to Caption — Social Content only, and only while eligible
    // (approved reviewStatus) and not already sent. Once sent
    // (task.sentToCaptionAt is set), the task is locked server-side
    // (SocialContentProductionEngine::lockTask()/lockOwnTask()) and no
    // button is shown here at all — there is no reverse/un-send action.
    // Icon-only, matching the existing .scp-history button's own
    // icon+title pattern rather than a text label.
    function sendToCaptionButton(task) {
        if (task.sentToCaptionAt) return '';
        if (!CAPTION_ELIGIBLE_REVIEW_STATUSES.includes(task.reviewStatus || 'Open')) return '';
        return `<button class="btn btn-sm btn-outline-secondary scp-send-caption" data-id="${task.id}" title="Send to Caption"><i class="ri-chat-quote-line"></i></button>`;
    }

    function renderRows() {
        if (!tasks.length) {
            $('#scpBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No production tasks match the current filters.</td></tr>');
            return;
        }

        $('#scpBody').html(tasks.map(function (task) {
            return `
                <tr>
                    <td>
                        <div class="fw-semibold text-truncate" style="max-width: 160px;" title="${esc(task.clientName)}">${esc(task.clientName)}</div>
                    </td>
                    <td>${contentCell(task)}</td>
                    <td class="text-center">${viewContentCell(task)}</td>
                    <td>${editorCell(task)}</td>
                    <td class="text-center">${statusBadge(task)}</td>
                    <td class="text-nowrap">${dateTimeCell(task)}</td>
                    <td class="text-end text-nowrap">${actionsFor(task)}</td>
                </tr>
            `;
        }).join(''));
    }

    // ------------------------------------------------------------------
    // ASSIGN / REASSIGN — trigger now lives in the Editor column
    // (editorCell()), but this is the exact same modal/save logic as
    // before, just relocated. assignModalTask remembers whether this open
    // was a first assignment or a reassignment, so Save can decide whether
    // a confirmation is needed without re-deriving it from the DOM.
    // ------------------------------------------------------------------
    let assignModalTask = null;

    $(document).on('click', '.scp-assign', function () {
        const task = tasks.find(t => t.id === Number($(this).data('id')));
        if (!task) return;
        assignModalTask = task;

        $('#scpAssignTitle').text(task.assignedEditorId ? 'Re-Assign Editor' : 'Assign Editor');
        $('#scpAssignId').val(task.id);
        $('#scpAssignEditor').val(task.assignedEditorId || '');
        $('#scpAssignDue').val(task.dueAt ? task.dueAt.replace(' ', 'T').slice(0, 16) : '');
        $('#scpAssignRemark').val('');
        $('#scpAssignModal').modal('show');
    });

    $('#scpAssignSaveBtn').on('click', function () {
        const editorId = $('#scpAssignEditor').val();
        if (!editorId) { notify('danger', 'Select an editor.'); return; }

        function submitAssignment() {
            manageTask({
                id: $('#scpAssignId').val(),
                action: 'assign',
                editorId: editorId,
                dueAt: $('#scpAssignDue').val() ? $('#scpAssignDue').val().replace('T', ' ') + ':00' : null,
                remark: $('#scpAssignRemark').val().trim()
            }, function () {
                $('#scpAssignModal').modal('hide');
            });
        }

        // A confirmation is only shown when re-assigning a task that
        // already had an editor -- a fresh first-time assignment submits
        // straight away, same as before.
        const isReassign = !!(assignModalTask && assignModalTask.assignedEditorId);
        if (!isReassign) { submitAssignment(); return; }

        const editorName = $('#scpAssignEditor option:selected').text();
        confirmDialog({
            title: 'Re-assign this task?',
            html: `Are you sure you want to re-assign this task to <b>${esc(editorName)}</b>?`,
            icon: 'question',
            confirmText: 'Re-assign',
            color: '#0d6efd'
        }).then(function (res) {
            if (res.isConfirmed) submitAssignment();
        });
    });

    // ------------------------------------------------------------------
    // PRODUCTION OUTPUT (Updated Work column -> Review column -> Status column)
    // ------------------------------------------------------------------
    // Shows/hides the remark field based on whichever status is currently
    // selected -- shared by the modal-open handler (pre-filled status) and
    // the dropdown's own change event.
    function toggleOutputRemark(reviewStatus) {
        const required = REVIEW_STATUSES_REQUIRING_REMARK.includes(reviewStatus);
        $('#scpOutputRemarkWrap').toggleClass('d-none', !required);
        $('#scpOutputRemarkLabel').text(reviewStatus === 'Not For Use' ? 'Remark' : 'Required Correction');
        if (!required) $('#scpOutputRemark').val('');
    }

    $(document).on('click', '.scp-output', function () {
        const task = tasks.find(t => t.id === Number($(this).data('id')));
        if (!task) return;

        if (task.sentToCaptionAt) {
            notify('danger', 'This task has been sent to Caption Area and can no longer be reviewed here.');
            return;
        }
        if (!REVIEWABLE_STATUSES.includes(task.status) || !task.submissionType || !task.submissionUrl) {
            notify('danger', 'Production work is not submitted yet.');
            return;
        }

        $('#scpOutputId').val(task.id);
        $('#scpOutputBody').html(renderOutput(task));
        const reviewStatus = task.reviewStatus || 'Open';
        $('#scpOutputStatus').val(reviewStatus);
        $('#scpOutputRemark').val('');
        toggleOutputRemark(reviewStatus);
        $('#scpOutputModal').modal('show');
    });

    $('#scpOutputStatus').on('change', function () {
        toggleOutputRemark($(this).val());
    });

    $('#scpOutputSubmitBtn').on('click', function () {
        const reviewStatus = $('#scpOutputStatus').val();
        const remark = $('#scpOutputRemark').val().trim();
        if (REVIEW_STATUSES_REQUIRING_REMARK.includes(reviewStatus) && !remark) {
            notify('danger', 'A remark is required for this status.');
            return;
        }
        manageTask({
            id: $('#scpOutputId').val(),
            action: 'review_status',
            reviewStatus: reviewStatus,
            remark: remark
        }, function () {
            $('#scpOutputModal').modal('hide');
        });
    });

    // ------------------------------------------------------------------
    // MARK READY
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-mark-ready', function () {
        const id = $(this).data('id');
        confirmDialog({
            title: 'Mark this production ready?',
            html: 'This confirms the content is finished and approved. It does not publish or schedule anything.',
            icon: 'question',
            confirmText: 'Mark Ready',
            color: '#198754'
        }).then(res => {
            if (!res.isConfirmed) return;
            manageTask({ id: id, action: 'mark_ready' });
        });
    });

    // ------------------------------------------------------------------
    // SEND TO CAPTION — Social Content only (Action column)
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-send-caption', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const id = $btn.data('id');

        confirmDialog({
            title: 'Send this task to Caption Area?',
            html: 'This locks the task here — it can no longer be edited in Production once sent, and there is no way to undo this.',
            icon: 'warning',
            confirmText: 'Send to Caption',
            color: '#212529'
        }).then(res => {
            if (!res.isConfirmed) return;
            $btn.prop('disabled', true);
            manageTask({ id: id, action: 'send_to_caption' }, null, function () {
                $btn.prop('disabled', false);
            });
        });
    });

    // ------------------------------------------------------------------
    // OTHER GRAPHIC CONTENT — "What do you want to do with this content?"
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-graphic-choice', function () {
        const id = $(this).data('id');
        $('#scpGraphicSendAutomation, #scpGraphicSendClient').attr('data-id', id).prop('disabled', false);
        $('#scpGraphicChoiceModal').modal('show');
    });

    // Send to Automation (Other Graphic Content) — calls the exact same,
    // unmodified endpoint Automation Queue itself uses. Graphic content has
    // no platform, so SocialAutomationHandoffEngine's existing eligibility
    // check reports "not currently supported" today — that is the real,
    // current, unmodified behavior, not something this change alters.
    $(document).on('click', '.scp-send-automation', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return; // prevent double-click while a request is already in flight
        const id = $btn.data('id');
        const originalHtml = $btn.html();
        const task = tasks.find(t => t.id === Number(id));
        const isRetry = !!(task && task.automationStatus === 'failed');

        confirmDialog({
            title: isRetry ? 'Retry sending this task to Automation?' : 'Send this task to Automation?',
            html: 'This will create a scheduled social post from the approved production output. Only Instagram/Facebook image posts are currently supported.',
            icon: 'question',
            confirmText: isRetry ? 'Retry' : 'Send to Automation',
            color: '#212529'
        }).then(res => {
            if (!res.isConfirmed) return;

            $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Sending…');

            $.ajax({
                url: 'api/social-content-production/send-to-automation.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ productionId: id }),
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success) {
                    notify('danger', (res && res.message) || 'Unable to send this task to Automation.');
                    $btn.prop('disabled', false).html(originalHtml);
                    return;
                }
                notify('success', res.message || 'Sent to Automation.');
                $('#scpGraphicChoiceModal').modal('hide');
                loadTasks(); // re-fetches automationStatus so the row updates to the "Sent" badge
            }).fail(function () {
                notify('danger', 'Network error while sending to Automation.');
                $btn.prop('disabled', false).html(originalHtml);
            });
        });
    });

    // Send to Client (Other Graphic Content) — no client-delivery/
    // notification mechanism exists in this codebase. This records the
    // decision only, as a Production History entry (existing, reused
    // recordExternalEvent()) — visible via the same History button/modal
    // every task already has.
    $(document).on('click', '.scp-send-client', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const id = $btn.data('id');

        confirmDialog({
            title: 'Mark this content as sent to the client?',
            html: 'This records the decision in this task\'s Production History. No email or client portal is sent automatically.',
            icon: 'question',
            confirmText: 'Send to Client',
            color: '#212529'
        }).then(res => {
            if (!res.isConfirmed) return;
            $btn.prop('disabled', true);
            manageTask({ id: id, action: 'send_to_client' }, function () {
                $('#scpGraphicChoiceModal').modal('hide');
            }, function () {
                $btn.prop('disabled', false);
            });
        });
    });

    // ------------------------------------------------------------------
    // DETAIL / HISTORY
    // ------------------------------------------------------------------
    $(document).on('click', '.scp-view', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load task.'); return; }
                renderDetail(res.data);
                $('#scpDetailModal').modal('show');
            });
    });

    // History is reached from its own action, in its own modal — the same
    // get-tasks.php?id= response already carries history (getTask() attaches
    // it), so no new API/query is needed for this.
    $(document).on('click', '.scp-history', function () {
        const id = $(this).data('id');
        $.ajax({ url: 'api/social-content-production/get-tasks.php', data: { id: id }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.success) { notify('danger', (res && res.message) || 'Failed to load history.'); return; }
                $('#scpHistoryTimeline').html(renderHistoryTimeline(res.data.history || []));
                $('#scpHistoryModal').modal('show');
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
            ${automationStatusLine(task)}
        `;
    }

    // Phase 6 — makes the Automation handoff state visible in the same
    // detail view a manager already reviews output in, not only as the
    // action-column badge in the queue table. Sourced from the same
    // automationStatus/automationSocialPostId/automationErrorMessage
    // fields get-tasks.php already attaches (Phase 4.5) -- no new query.
    function automationStatusLine(task) {
        if (!task.automationStatus) return '';

        if (task.automationStatus === 'sent') {
            return `<div class="fs-12 mt-2"><span class="badge bg-success-transparent"><i class="ri-send-plane-fill"></i> Sent to Automation</span>${task.automationSocialPostId ? ' <span class="text-muted">(socialPosts #' + esc(task.automationSocialPostId) + ')</span>' : ''}</div>`;
        }
        if (task.automationStatus === 'failed') {
            return `<div class="fs-12 mt-2"><span class="badge bg-danger-transparent"><i class="ri-error-warning-line"></i> Automation Failed</span> <span class="text-muted">${esc(task.automationErrorMessage || '')}</span></div>`;
        }
        return `<div class="fs-12 mt-2"><span class="badge bg-warning-transparent"><i class="ri-time-line"></i> Automation Pending</span></div>`;
    }

    // View modal — Content Brief only. Task Overview and Production Output
    // used to also render here; Production Output moved into its own
    // dedicated modal (Updated Work column), and Task Overview was dropped
    // as redundant with it.
    function renderDetail(task) {
        $('#scpDetailBrief').html(renderBrief(task));
    }

    // Full history, oldest first (the engine already returns it ORDER BY
    // h.id ASC) rendered top-to-bottom as a vertical timeline -- same event
    // data/fields as before, just its own modal instead of living inside
    // the detail modal.
    function renderHistoryTimeline(history) {
        if (!history.length) {
            return '<div class="text-muted fs-13">No history yet.</div>';
        }

        return `<div class="scp-timeline">` + history.map(h => `
            <div class="scp-timeline-item">
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
    // SHARED MUTATION CALL
    // ------------------------------------------------------------------
    // onError is optional (added for Send to Caption/Send to Client, which
    // need to re-enable their own button on failure) — existing callers
    // that don't pass it are unaffected.
    function manageTask(payload, onSuccess, onError) {
        $.ajax({
            url: 'api/social-content-production/manage-task.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Action failed.');
                onError && onError();
                return;
            }
            notify('success', res.message || 'Saved.');
            onSuccess && onSuccess();
            loadTasks();
        }).fail(function () {
            notify('danger', 'Network error.');
            onError && onError();
        });
    }

    // ------------------------------------------------------------------
    // EVENTS
    // ------------------------------------------------------------------
    function refreshAll() {
        loadTasks();
        loadSummary();
    }

    $('#scpRefreshBtn').on('click', refreshAll);
    $('#scpStatus, #scpEditor, #scpClient, #scpPlatform, #scpSource').on('change', refreshAll);
    $('#scpOverdue').on('change', loadTasks); // summary's overdue count already covers all statuses regardless of this checkbox

    refreshAll();
});
