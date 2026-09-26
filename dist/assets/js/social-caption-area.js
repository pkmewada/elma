$(function () {

    let queue = [];
    let captionModal = null;

    function esc(str) { return $('<div>').text(str == null ? '' : String(str)).html(); }
    function notify(type, message) { if (window.showToast) window.showToast(type, message); }

    function fmtDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Same Post/Story classification used across social-content-production.php
    // and both employee pages -- duplicated locally per this codebase's own
    // established per-page convention (no shared JS module exists here).
    function classifyPostingType(raw) {
        const key = String(raw || '').trim().toLowerCase();
        if (!key) return '';
        return (key === 'story' || key === 'image story' || key === 'video story') ? 'Story' : 'Post';
    }

    function compactBadge(color, label) {
        return `<span class="badge bg-${color}">${esc(label)}</span>`;
    }

    // automationStatus comes from the existing socialContentAutomationHandoff
    // table (api/social-content-caption/get-queue.php now attaches it, same
    // read-only pattern get-tasks.php already uses) -- any row at all means
    // this task has already been handed off, regardless of pending/sent/
    // failed. A failed handoff still reads as "sent" here on purpose: retry
    // is the existing Automation Queue page's job, unchanged, not duplicated
    // here.
    function captionStatusBadge(task) {
        if (task.automationStatus) return compactBadge('dark', 'Sent To Automation');
        if (task.captionStatus === 'selected') return compactBadge('success', 'Caption Selected');
        if (task.captionStatus === 'pending') return compactBadge('warning', 'Caption Pending');
        return compactBadge('secondary', 'Not Started');
    }

    // View/Edit (or just View, once sent to Automation -- the modal itself
    // becomes read-only for that case, see renderCaptionState()) always
    // shows; Send To Automation only shows once a caption is selected and
    // only until it's actually sent -- no duplicate send button afterward.
    function actionButtons(task) {
        const alreadySent = !!task.automationStatus;
        const label = alreadySent ? 'View Caption' : (task.captionStatus ? 'View / Edit Caption' : 'Generate Caption');
        const icon = alreadySent ? 'ri-eye-line' : (task.captionStatus ? 'ri-edit-box-line' : 'ri-sparkling-2-line');
        const btns = [`<button class="btn btn-sm btn-primary sca-open" data-id="${task.id}"><i class="${icon} me-1"></i>${label}</button>`];

        if (task.captionStatus === 'selected' && !alreadySent) {
            btns.push(`<button class="btn btn-sm btn-dark sca-send-automation" data-id="${task.id}"><i class="ri-send-plane-2-line me-1"></i>Send To Automation</button>`);
        }

        return btns.join(' ');
    }

    // ------------------------------------------------------------------
    // FILTERS
    // ------------------------------------------------------------------
    function toYMD(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    let dateFrom = '';
    let dateTo = '';

    flatpickr('#scaDateRange', {
        mode: 'range',
        dateFormat: 'Y-m-d',
        altInput: true,
        altFormat: 'd M Y',
        onClose: function (selectedDates) {
            if (!selectedDates.length) { dateFrom = ''; dateTo = ''; loadQueue(); return; }
            dateFrom = toYMD(selectedDates[0]);
            dateTo = selectedDates.length > 1 ? toYMD(selectedDates[1]) : dateFrom;
            loadQueue();
        }
    });

    $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            const options = res.data.map(c => `<option value="${c.id}">${esc(c.fullName)}</option>`).join('');
            $('#scaClient').append(options);
        }
    });
    $.ajax({ url: 'api/deliverables/get-platforms.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            const options = (res.data || []).map(p => `<option value="${p.id}">${esc(p.platformName)}</option>`).join('');
            $('#scaPlatform').append(options);
        }
    });

    // ------------------------------------------------------------------
    // LOAD + RENDER
    // ------------------------------------------------------------------
    function loadQueue() {
        $('#scaBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/social-content-caption/get-queue.php',
            data: {
                clientId: $('#scaClient').val(),
                platformId: $('#scaPlatform').val(),
                fromDate: dateFrom,
                toDate: dateTo
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load caption queue.');
                queue = [];
                renderRows();
                return;
            }
            queue = res.data || [];
            renderRows();
        }).fail(function () {
            notify('danger', 'Network error while loading caption queue.');
            queue = [];
            renderRows();
        });
    }

    function renderRows() {
        if (!queue.length) {
            $('#scaBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No approved content is waiting on a caption right now.</td></tr>');
            return;
        }

        $('#scaBody').html(queue.map(function (task) {
            const postingType = classifyPostingType(task.postType);
            return `
                <tr>
                    <td>
                        <div class="fw-semibold">${esc(task.clientName)}</div>
                    </td>
                    <td>
                        <div>${task.title ? esc(task.title) : '<span class="text-muted">Untitled</span>'}</div>
                    </td>
                    <td>${esc(task.platformName || '—')}</td>
                    <td>${postingType ? esc(postingType) : '—'}</td>
                    <td>${fmtDate(task.contentDate)}</td>
                    <td>${captionStatusBadge(task)}</td>
                    <td class="text-end text-nowrap">${actionButtons(task)}</td>
                </tr>
            `;
        }).join(''));
    }

    // ------------------------------------------------------------------
    // CAPTION MODAL
    // ------------------------------------------------------------------
    function infoCell(label, valueHtml) {
        return `<div class="col-6 col-md-3 mb-2">
            <div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div>
            <div class="fs-13">${valueHtml}</div>
        </div>`;
    }

    function briefRow(label, value) {
        if (value === null || value === undefined) return '';
        const text = String(value).trim();
        if (text === '') return '';
        return `<div class="mb-2"><div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div><div class="fs-13">${esc(text).replace(/\n/g, '<br>')}</div></div>`;
    }

    function renderContentInfo(task) {
        const postingType = classifyPostingType(task.postType);
        $('#scaContentInfo').html(
            infoCell('Client', esc(task.clientName)) +
            infoCell('Platform', esc(task.platformName || '—')) +
            infoCell('Posting Type', postingType ? esc(postingType) : '—') +
            infoCell('Content Date', fmtDate(task.contentDate))
        );

        const rows = [
            briefRow('Content Title', task.title),
            briefRow('Raw Content', task.rawContent),
            briefRow('Caption (Data Entry)', task.caption),
            briefRow('Description', task.contentDescription)
        ].filter(Boolean).join('');
        $('#scaContentBrief').html(rows || '<div class="text-muted fs-13">No additional content details were provided.</div>');
    }

    // Regeneration limit display: shared by both the "nothing generated
    // yet" and "already generated" render paths below, and by the
    // Generate button's own click handler after a fresh response.
    // sentToAutomation permanently disables Generate too, once true --
    // regenerating would silently change the caption Automation already
    // used, with nothing here to re-sync it (that stays the Automation
    // Queue's own retry job, unchanged).
    function renderRegenLimit(caption, sentToAutomation) {
        if (sentToAutomation) {
            $('#scaRegenCount').text('');
            $('#scaRegenLimitMsg').removeClass('d-none').text('Already sent to Automation — the caption can no longer be changed.');
            $('#scaGenerateBtn').prop('disabled', true);
            return;
        }
        if (!caption) {
            $('#scaRegenCount').text('');
            $('#scaRegenLimitMsg').addClass('d-none');
            $('#scaGenerateBtn').prop('disabled', false);
            return;
        }
        const remaining = caption.regenerationsRemaining;
        const atLimit = remaining <= 0;
        $('#scaRegenCount').text(`${remaining} regeneration${remaining === 1 ? '' : 's'} left`);
        $('#scaRegenLimitMsg').toggleClass('d-none', !atLimit).text('Caption regeneration limit reached.');
        $('#scaGenerateBtn').prop('disabled', atLimit);
    }

    function renderCaptionState(caption, sentToAutomation) {
        if (!caption) {
            $('#scaPrompt').val('');
            $('#scaOptionsWrap').addClass('d-none');
            $('#scaSelectedWrap').addClass('d-none');
            $('#scaOptionOneCard, #scaOptionTwoCard').removeClass('border-success');
            renderRegenLimit(null, sentToAutomation);
            return;
        }

        $('#scaPrompt').val(caption.prompt || '');
        $('#scaOptionOneText').text(caption.captionOptionOne || 'Generated caption will appear here');
        $('#scaOptionTwoText').text(caption.captionOptionTwo || 'Generated caption will appear here');
        $('#scaOptionOneCard, #scaOptionTwoCard').removeClass('border-success');
        renderRegenLimit(caption, sentToAutomation);

        // Once selected, the two-option grid is hidden entirely (both
        // options stay in the database, unchanged) and only the selected
        // caption shows, as an editable textarea (Manual editing, Part C) --
        // unless this task has already been sent to Automation, in which
        // case it's read-only ("View Caption", not "View / Edit").
        if (caption.status === 'selected') {
            $('#scaOptionsWrap').addClass('d-none');
            $('#scaSelectedWrap').removeClass('d-none');
            $('#scaSelectedCaptionText').val(caption.selectedCaption || '').prop('readonly', !!sentToAutomation);
            $('#scaSaveCaptionBtn').toggleClass('d-none', !!sentToAutomation);
        } else {
            $('#scaOptionsWrap').removeClass('d-none');
            $('#scaSelectedWrap').addClass('d-none');
        }
    }

    function openCaptionModal(productionId) {
        productionId = Number(productionId);
        const task = queue.find(t => t.id === productionId);
        const sentToAutomation = !!(task && task.automationStatus);

        $('#scaProductionId').val(productionId);
        $('#scaContentInfo').html('<div class="col-12 text-muted fs-13">Loading…</div>');
        $('#scaContentBrief').html('');
        renderCaptionState(null, sentToAutomation);

        if (!captionModal) captionModal = new bootstrap.Modal(document.getElementById('scaCaptionModal'));
        captionModal.show();

        $.ajax({
            url: 'api/social-content-caption/get-caption.php',
            data: { productionId: productionId },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load content.');
                captionModal.hide();
                return;
            }
            renderContentInfo(res.data);
            renderCaptionState(res.data.caption, sentToAutomation);
        }).fail(function () {
            notify('danger', 'Network error while loading content.');
            captionModal.hide();
        });
    }

    $(document).on('click', '.sca-open', function () {
        openCaptionModal($(this).data('id'));
    });

    const SCA_GENERATE_BTN_DEFAULT_HTML = '<i class="ri-sparkling-2-line me-1"></i> Generate Caption';

    $('#scaGenerateBtn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return; // already processing -- ignore a duplicate/queued click

        const prompt = $('#scaPrompt').val().trim();
        if (!prompt) { notify('danger', 'Enter a prompt before generating a caption.'); return; }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Generating captions...');

        $.ajax({
            url: 'api/social-content-caption/generate-caption.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({ productionId: $('#scaProductionId').val(), prompt: prompt }),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to generate caption.');
                return;
            }
            notify('success', 'Caption options generated.');
            renderCaptionState(res.data);
            loadQueue();
        }).fail(function () {
            notify('danger', 'Network error while generating caption.');
        }).always(function () {
            $btn.prop('disabled', false).html(SCA_GENERATE_BTN_DEFAULT_HTML);
        });
    });

    $(document).on('click', '.sca-select', function () {
        const option = $(this).data('option');
        const $btn = $(this);
        $btn.prop('disabled', true);

        $.ajax({
            url: 'api/social-content-caption/select-caption.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({ productionId: $('#scaProductionId').val(), option: option }),
            dataType: 'json'
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to select caption.');
                return;
            }
            notify('success', 'Caption selected. This content is ready for future automation.');
            renderCaptionState(res.data);
            loadQueue();
        }).fail(function () {
            $btn.prop('disabled', false);
            notify('danger', 'Network error while selecting caption.');
        });
    });

    // Manual caption editing (Part C) — saves the edited text directly as
    // selectedCaption via update-caption.php; no AI call, no effect on the
    // regeneration count. This becomes the final caption Automation reads
    // (SocialAutomationHandoffEngine already reads selectedCaption
    // unchanged — no changes needed there).
    $('#scaSaveCaptionBtn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;

        const text = $('#scaSelectedCaptionText').val().trim();
        if (!text) { notify('danger', 'Caption text cannot be empty.'); return; }

        $btn.prop('disabled', true);

        $.ajax({
            url: 'api/social-content-caption/update-caption.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({ productionId: $('#scaProductionId').val(), selectedCaption: text }),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to save caption.');
                return;
            }
            notify('success', 'Caption saved.');
            renderCaptionState(res.data);
            loadQueue();
        }).fail(function () {
            notify('danger', 'Network error while saving caption.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // ------------------------------------------------------------------
    // SEND TO AUTOMATION — reuses the exact same, unmodified endpoint and
    // engine (SocialAutomationHandoffEngine::resolveAndRegisterHandoff())
    // pages/social-automation.php already calls for its own Send/Retry
    // action. This is the only place that engine is ever invoked from here;
    // eligibility (selected caption, review status, media, account) is all
    // decided server-side, never re-derived here.
    // ------------------------------------------------------------------
    $(document).on('click', '.sca-send-automation', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;
        const productionId = $btn.data('id');

        Swal.fire({
            title: 'Send this task to Automation?',
            html: 'The selected caption will be used to schedule this post. This cannot be undone from here.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Send',
            reverseButtons: true
        }).then(function (res) {
            if (!res.isConfirmed) return;

            $btn.prop('disabled', true);

            $.ajax({
                url: 'api/social-content-production/send-to-automation.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ productionId: productionId }),
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success) {
                    notify('danger', (res && res.message) || 'Unable to send this task to Automation.');
                    $btn.prop('disabled', false);
                    return;
                }
                notify('success', res.message || 'Sent to Automation.');
                loadQueue();
            }).fail(function () {
                notify('danger', 'Network error while sending to Automation.');
                $btn.prop('disabled', false);
            });
        });
    });

    // ------------------------------------------------------------------
    // EVENTS
    // ------------------------------------------------------------------
    $('#scaRefreshBtn').on('click', loadQueue);
    $('#scaClient, #scaPlatform').on('change', loadQueue);

    loadQueue();
});
