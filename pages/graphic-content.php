<?php
/*
|--------------------------------------------------------------------------
| Other Graphic Content — new, production-connected raw material entry
|--------------------------------------------------------------------------
|
| A NEW, independent module -- not a reuse of otherGraphicContentEngine.php/
| pages/other-graphic-content.php ("Other Content", disconnected from
| Production on 2026-09-17). Add Entry -> Save -> auto hand off to
| Production, the same save -> complete -> create-task pattern
| social-data-entry.php and the old (pre-disconnect) Other Content page
| both used (see includes/graphicContentEngine.php and
| api/graphic-content/save-entry.php).
|
| Feeds the SAME Production Queue (pages/social-content-production.php)
| every Social Content / Other Content entry already goes through -- see
| includes/SocialContentProductionEngine.php's createTaskForGraphic().
|
*/
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Other Graphic Content</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Other Graphic Content</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="social-content-production" class="btn btn-light btn-sm">
                    <i class="ri-clapperboard-line me-1"></i> Content Production
                </a>
                <button type="button" class="btn btn-primary btn-sm" id="gcAddBtn">
                    <i class="ri-add-line me-1"></i> Add
                </button>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-3 col-md-4">
                        <label for="gcDateRange">Date Range</label>
                        <input type="text" class="form-control form-control-sm" id="gcDateRange"
                               placeholder="Select date or range" autocomplete="off">
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="gcClient">Client</label>
                        <select class="form-select form-select-sm" id="gcClient">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-primary" id="gcRefreshBtn">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <h5 class="mb-0">Other Graphic Content</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Content Name</th>
                                <th>Edit Type</th>
                                <th>Client</th>
                                <th>Deadline</th>
                                <th>User</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="gcBody">
                            <tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Add / Edit -->
<div class="modal fade" id="gcEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="gcEntryTitle">
                    <i class="ri-add-box-line me-2 text-primary"></i> Add Other Graphic Content
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="gcFormId">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="gcFormContentName">Content Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="gcFormContentName" maxlength="150" placeholder="Enter content name">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="gcFormDate">Select Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="gcFormDate" placeholder="Select date" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="gcFormDeadline">Deadline Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="gcFormDeadline" placeholder="Select deadline date &amp; time" autocomplete="off">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label" for="gcFormClient">Client Name <span class="text-danger">*</span></label>
                        <select class="form-select" id="gcFormClient">
                            <option value="">Select client…</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gcFormEditType">Edit Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="gcFormEditType">
                            <option value="">Select</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="gcFormPriority">Priority</label>
                        <select class="form-select" id="gcFormPriority">
                            <option value="">Select</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="gcFormReference">Reference</label>
                        <input type="text" class="form-control" id="gcFormReference" placeholder="Reference">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="gcFormSongUrl">Song URL</label>
                        <input type="text" class="form-control" id="gcFormSongUrl" placeholder="https://...">
                    </div>
                    
                    <div class="col-md-12">
                        <label class="form-label" for="gcFormNotes">Notes</label>
                        <textarea class="form-control" id="gcFormNotes" rows="1" placeholder="Internal note (optional)"></textarea>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label" for="gcFormRawContent">Raw Content</label>
                        <textarea class="form-control" id="gcFormRawContent" rows="3" placeholder="Raw content"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="gcFormDescription">Content Description</label>
                        <textarea class="form-control" id="gcFormDescription" rows="4" placeholder="Enter content description..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" id="gcCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="gcSaveBtn">
                    <i class="ri-save-line me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View (read-only) -->
<div class="modal fade" id="gcViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="ri-eye-line me-2 text-primary"></i> View Other Graphic Content</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="gcViewBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {

    const EDIT_TYPES = ['Post', 'PPT', 'Reels', 'Video', 'Image', 'Invitation', 'Brochure', 'Banner', 'Logo'];
    const PRIORITIES = ['Low', 'Medium', 'High'];

    let entries = [];
    let entryModal = null;
    let viewModal = null;
    let formDirty = false;

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

    function fmtDate(d) {
        if (!d) return '—';
        return new Date(d + 'T00:00:00').toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function fmtDateTime(dt) {
        if (!dt) return '—';
        const d = new Date(String(dt).replace(' ', 'T'));
        if (isNaN(d.getTime())) return dt;
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' }) + ' ' +
               d.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    }

    $('#gcFormEditType').html(
        '<option value="">Select</option>' +
        EDIT_TYPES.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('')
    );
    $('#gcFormPriority').html(
        '<option value="">Select</option>' +
        PRIORITIES.map(t => `<option value="${esc(t)}"${t === 'Medium' ? ' selected' : ''}>${esc(t)}</option>`).join('')
    );

    // ------------------------------------------------------------------
    // FILTERS
    // ------------------------------------------------------------------
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

    flatpickr('#gcDateRange', {
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
            loadEntries();
        }
    });

    const formDateFp = flatpickr('#gcFormDate', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' });
    const formDeadlineFp = flatpickr('#gcFormDeadline', {
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        altInput: true,
        altFormat: 'd M Y, h:i K',
        time_24hr: false
    });

    $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            const options = res.data.map(c => `<option value="${c.id}">${esc(c.fullName)}</option>`).join('');
            $('#gcClient').append(options);
            $('#gcFormClient').append(options);
        }
    });

    // ------------------------------------------------------------------
    // LOAD + RENDER
    // ------------------------------------------------------------------
    function loadEntries() {
        $('#gcBody').html('<tr><td colspan="8" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/graphic-content/get-entries.php',
            data: {
                clientId: $('#gcClient').val(),
                fromDate: dateFrom,
                toDate: dateTo
            },
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load entries.');
                entries = [];
                renderRows();
                return;
            }
            entries = res.data || [];
            renderRows();
        }).fail(function () {
            notify('danger', 'Network error while loading entries.');
            entries = [];
            renderRows();
        });
    }

    function renderRows() {
        if (!entries.length) {
            $('#gcBody').html('<tr><td colspan="8" class="text-center text-muted py-4">No entries match the current filters.</td></tr>');
            return;
        }

        $('#gcBody').html(entries.map(function (entry, idx) {
            const overdue = entry.deadlineAt && new Date(entry.deadlineAt.replace(' ', 'T')) < new Date();
            return `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${fmtDate(entry.contentDate)}</td>
                    <td>${esc(entry.contentName)}</td>
                    <td><span class="badge bg-info-transparent">${esc(entry.editType)}</span></td>
                    <td>
                        <div class="fw-semibold">${esc(entry.clientName)}</div>
                        <div class="text-muted small">${esc(entry.clientCode || '')}</div>
                    </td>
                    <td class="${overdue ? 'text-danger fw-semibold' : ''}">${fmtDateTime(entry.deadlineAt)}</td>
                    <td>${entry.createdByName ? esc(entry.createdByName) : '<span class="text-muted">—</span>'}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary gc-view" data-id="${entry.id}" title="View"><i class="ri-eye-line"></i></button>
                        <button class="btn btn-sm btn-outline-primary gc-edit" data-id="${entry.id}" title="Edit"><i class="ri-edit-box-line"></i></button>
                        <button class="btn btn-sm btn-outline-danger gc-delete" data-id="${entry.id}" title="Delete"><i class="ri-delete-bin-line"></i></button>
                    </td>
                </tr>
            `;
        }).join(''));
    }

    // ------------------------------------------------------------------
    // VIEW (read-only)
    // ------------------------------------------------------------------
    function viewRow(label, value) {
        if (value === null || value === undefined || String(value).trim() === '') return '';
        return `<div class="col-md-6 mb-2"><div class="fs-11 text-uppercase text-muted fw-semibold">${esc(label)}</div><div class="fs-13">${esc(value).replace(/\n/g, '<br>')}</div></div>`;
    }

    $(document).on('click', '.gc-view', function () {
        const entry = entries.find(e => e.id === Number($(this).data('id')));
        if (!entry) return;

        const rows = [
            viewRow('Content Name', entry.contentName),
            viewRow('Client', entry.clientName),
            viewRow('Select Date', fmtDate(entry.contentDate)),
            viewRow('Deadline Date', fmtDateTime(entry.deadlineAt)),
            viewRow('Edit Type', entry.editType),
            viewRow('Priority', entry.priority),
            viewRow('Raw Content', entry.rawContent),
            viewRow('Song URL', entry.songUrl),
            viewRow('Reference', entry.reference),
            viewRow('Notes', entry.notes),
            viewRow('Content Description', entry.contentDescription)
        ].filter(Boolean).join('');

        $('#gcViewBody').html(`<div class="row">${rows}</div>`);
        if (!viewModal) viewModal = new bootstrap.Modal(document.getElementById('gcViewModal'));
        viewModal.show();
    });

    // ------------------------------------------------------------------
    // ADD / EDIT
    // ------------------------------------------------------------------
    function openEntryModal(mode, entry) {
        formDirty = false;
        const isEdit = mode === 'edit';

        $('#gcEntryTitle').html(isEdit
            ? '<i class="ri-edit-box-line me-2 text-primary"></i> Edit Other Graphic Content'
            : '<i class="ri-add-box-line me-2 text-primary"></i> Add Other Graphic Content');

        $('#gcFormId').val(isEdit ? entry.id : '');
        $('#gcFormContentName').val(isEdit ? (entry.contentName || '') : '');
        formDateFp.setDate(isEdit ? entry.contentDate : null, false);
        formDeadlineFp.setDate(isEdit ? entry.deadlineAt : null, false);
        $('#gcFormClient').val(isEdit ? entry.clientId : '');
        $('#gcFormEditType').val(isEdit ? entry.editType : '');
        $('#gcFormPriority').val(isEdit ? (entry.priority || 'Medium') : 'Medium');
        $('#gcFormRawContent').val(isEdit ? (entry.rawContent || '') : '');
        $('#gcFormSongUrl').val(isEdit ? (entry.songUrl || '') : '');
        $('#gcFormReference').val(isEdit ? (entry.reference || '') : '');
        $('#gcFormNotes').val(isEdit ? (entry.notes || '') : '');
        $('#gcFormDescription').val(isEdit ? (entry.contentDescription || '') : '');
        $('#gcEntryModal .form-control, #gcEntryModal .form-select').removeClass('is-invalid');

        if (!entryModal) entryModal = new bootstrap.Modal(document.getElementById('gcEntryModal'));
        entryModal.show();
    }

    $('#gcAddBtn').on('click', function () { openEntryModal('add', null); });

    $(document).on('click', '.gc-edit', function () {
        const entry = entries.find(e => e.id === Number($(this).data('id')));
        if (!entry) return;
        openEntryModal('edit', entry);
    });

    function validateForm() {
        const errors = [];
        $('#gcEntryModal .form-control, #gcEntryModal .form-select').removeClass('is-invalid');

        if (!$('#gcFormContentName').val().trim()) { $('#gcFormContentName').addClass('is-invalid'); errors.push('Content name is required.'); }
        if (!$('#gcFormDate').val()) { $('#gcFormDate').addClass('is-invalid'); errors.push('Select date is required.'); }
        if (!$('#gcFormDeadline').val()) { $('#gcFormDeadline').addClass('is-invalid'); errors.push('Deadline date is required.'); }
        if (!$('#gcFormClient').val()) { $('#gcFormClient').addClass('is-invalid'); errors.push('Client is required.'); }
        if (!$('#gcFormEditType').val()) { $('#gcFormEditType').addClass('is-invalid'); errors.push('Edit type is required.'); }

        return errors;
    }

    $('#gcSaveBtn').on('click', function () {
        const errors = validateForm();
        if (errors.length) { notify('danger', errors[0]); return; }

        const id = $('#gcFormId').val();
        const payload = Object.assign({
            clientId: Number($('#gcFormClient').val()),
            contentDate: $('#gcFormDate').val(),
            deadlineAt: $('#gcFormDeadline').val(),
            contentName: $('#gcFormContentName').val().trim(),
            editType: $('#gcFormEditType').val(),
            priority: $('#gcFormPriority').val() || 'Medium',
            rawContent: $('#gcFormRawContent').val().trim(),
            songUrl: $('#gcFormSongUrl').val().trim(),
            reference: $('#gcFormReference').val().trim(),
            notes: $('#gcFormNotes').val().trim(),
            contentDescription: $('#gcFormDescription').val().trim()
        }, id ? { id: id } : {});

        $.ajax({
            url: 'api/graphic-content/save-entry.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to save entry.');
                return;
            }
            formDirty = false;
            notify('success', id ? 'Entry updated — sent to Production.' : 'Entry added — sent to Production.');
            entryModal.hide();
            loadEntries();
        }).fail(function () {
            notify('danger', 'Network error while saving entry.');
        });
    });

    // Delete -- server-side blocks this once real production work exists
    // beyond NEW (see api/graphic-content/delete-entry.php), same guard
    // pattern as Social Content's own Clear Data.
    $(document).on('click', '.gc-delete', function () {
        const id = $(this).data('id');
        confirmDialog({
            title: 'Delete this entry?',
            html: 'This cannot be undone.',
            icon: 'warning',
            confirmText: 'Delete',
            color: '#dc3545'
        }).then(res => {
            if (!res.isConfirmed) return;
            $.ajax({
                url: 'api/graphic-content/delete-entry.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ id: id }),
                dataType: 'json'
            }).done(function (res) {
                if (!res || !res.success) {
                    notify('danger', (res && res.message) || 'Failed to delete entry.');
                    return;
                }
                notify('success', 'Entry deleted.');
                loadEntries();
            }).fail(function () {
                notify('danger', 'Network error while deleting entry.');
            });
        });
    });

    // ------------------------------------------------------------------
    // EVENTS
    // ------------------------------------------------------------------
    $('#gcRefreshBtn').on('click', loadEntries);
    $('#gcClient').on('change', loadEntries);

    $('#gcEntryModal').on('input change', 'input, select, textarea', function () { formDirty = true; });

    $('#gcCancelBtn').on('click', function () {
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

    $('#gcEntryModal').on('hidden.bs.modal', function () { formDirty = false; });

    loadEntries();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
