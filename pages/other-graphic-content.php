<?php
/*
|--------------------------------------------------------------------------
| Other Content — standalone CRUD (UI label "Other Content"; route, file,
| table, and every internal identifier are unchanged from when this was
| labelled "Other Graphic Content")
|--------------------------------------------------------------------------
|
| Miscellaneous content requests (Blog, Bio, Caption, YouTube Description,
| etc.) that don't fit clientSocialContent's platform/feature/postType
| shape. Add Entry -> Save -> Show in List only -- this page no longer
| creates a socialContentProduction task on save (business requirement
| change; see includes/otherGraphicContentEngine.php and
| api/other-graphic-content/save-entry.php).
|
| A handful of pre-existing entries still have a production task created
| back when this module fed the Production Queue (see
| includes/SocialContentProductionEngine.php's createTaskForOther() /
| getTaskByOtherContentId()) -- those historical links are left intact and
| still display in Production; delete-entry.php still guards against
| cascade-deleting one of them. No new links are ever created now.
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
                <h1 class="page-title fw-medium fs-18 mb-2">Other Content</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Other Content</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="social-content-production" class="btn btn-light btn-sm">
                    <i class="ri-clapperboard-line me-1"></i> Content Production
                </a>
                <button type="button" class="btn btn-primary btn-sm" id="ogcAddBtn">
                    <i class="ri-add-line me-1"></i> Other Content
                </button>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-3 col-md-4">
                        <label for="ogcDateRange">Date Range</label>
                        <input type="text" class="form-control form-control-sm" id="ogcDateRange"
                               placeholder="Select date or range" autocomplete="off">
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="ogcClient">Client</label>
                        <select class="form-select form-select-sm" id="ogcClient">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-primary" id="ogcRefreshBtn">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <h5 class="mb-0">Other Content</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Edit Type</th>
                                <th>Client</th>
                                <th>Deadline</th>
                                <th>User</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="ogcBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Add / Edit -->
<div class="modal fade" id="ogcEntryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="ogcEntryTitle">
                    <i class="ri-add-box-line me-2 text-primary"></i> Add Other Content
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="ogcFormId">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label" for="ogcFormDate">Select Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ogcFormDate" placeholder="Select date" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ogcFormDeadline">Deadline Date <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="ogcFormDeadline" placeholder="Select deadline" autocomplete="off">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ogcFormClient">Client <span class="text-danger">*</span></label>
                        <select class="form-select" id="ogcFormClient">
                            <option value="">Select client…</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label" for="ogcFormEditType">Edit Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="ogcFormEditType">
                            <option value="">Select</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="ogcFormTitle">Title</label>
                        <input type="text" class="form-control" id="ogcFormTitle" maxlength="150" placeholder="Enter title">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label" for="ogcFormHook">Hook</label>
                        <textarea class="form-control" id="ogcFormHook" rows="3" placeholder="Opening hook / attention grabber"></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="ogcFormReference">Reference</label>
                        <textarea class="form-control" id="ogcFormReference" rows="3" placeholder="Where did this request come from?"></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="ogcFormDescription">Content Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="ogcFormDescription" rows="5" placeholder="Enter content description..."></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label" for="ogcFormNotes">Notes</label>
                        <textarea class="form-control" id="ogcFormNotes" rows="2" placeholder="Internal note (optional)"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" id="ogcCancelBtn">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="ogcSaveBtn">
                    <i class="ri-save-line me-1"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {

    const EDIT_TYPES = [
        'Blog', 'Short Video and Reels', 'Long Video and Reels', 'Bio',
        'Caption', 'Notes', 'Youtube Description', 'Others'
    ];

    let entries = [];
    let entryModal = null;
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

    $('#ogcFormEditType').html(
        '<option value="">Select</option>' +
        EDIT_TYPES.map(t => `<option value="${esc(t)}">${esc(t)}</option>`).join('')
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

    flatpickr('#ogcDateRange', {
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

    const formDateFp = flatpickr('#ogcFormDate', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' });
    const formDeadlineFp = flatpickr('#ogcFormDeadline', { dateFormat: 'Y-m-d', altInput: true, altFormat: 'd M Y' });

    $.ajax({ url: 'api/client/getClients.php', dataType: 'json' }).done(function (res) {
        if (res && res.success) {
            const options = res.data.map(c => `<option value="${c.id}">${esc(c.fullName)}</option>`).join('');
            $('#ogcClient').append(options);
            $('#ogcFormClient').append(options);
        }
    });

    // ------------------------------------------------------------------
    // LOAD + RENDER
    // ------------------------------------------------------------------
    function loadEntries() {
        $('#ogcBody').html('<tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>');

        $.ajax({
            url: 'api/other-graphic-content/get-entries.php',
            data: {
                clientId: $('#ogcClient').val(),
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
            $('#ogcBody').html('<tr><td colspan="7" class="text-center text-muted py-4">No entries match the current filters.</td></tr>');
            return;
        }

        $('#ogcBody').html(entries.map(function (entry, idx) {
            const overdue = entry.deadlineDate && entry.deadlineDate < toYMD(new Date());
            return `
                <tr>
                    <td>${idx + 1}</td>
                    <td>${fmtDate(entry.contentDate)}</td>
                    <td><span class="badge bg-info-transparent">${esc(entry.editType)}</span></td>
                    <td>
                        <div class="fw-semibold">${esc(entry.clientName)}</div>
                        <div class="text-muted small">${esc(entry.clientCode || '')}</div>
                    </td>
                    <td class="${overdue ? 'text-danger fw-semibold' : ''}">${fmtDate(entry.deadlineDate)}</td>
                    <td>${entry.createdByName ? esc(entry.createdByName) : '<span class="text-muted">—</span>'}</td>
                    <td class="text-end text-nowrap">
                        <button class="btn btn-sm btn-outline-primary ogc-edit" data-id="${entry.id}" title="Edit"><i class="ri-edit-box-line"></i></button>
                        <button class="btn btn-sm btn-outline-danger ogc-delete" data-id="${entry.id}" title="Delete"><i class="ri-delete-bin-line"></i></button>
                    </td>
                </tr>
            `;
        }).join(''));
    }

    // ------------------------------------------------------------------
    // ADD / EDIT
    // ------------------------------------------------------------------
    function openEntryModal(mode, entry) {
        formDirty = false;
        const isEdit = mode === 'edit';

        $('#ogcEntryTitle').html(isEdit
            ? '<i class="ri-edit-box-line me-2 text-primary"></i> Edit Other Content'
            : '<i class="ri-add-box-line me-2 text-primary"></i> Add Other Content');

        $('#ogcFormId').val(isEdit ? entry.id : '');
        formDateFp.setDate(isEdit ? entry.contentDate : null, false);
        formDeadlineFp.setDate(isEdit ? entry.deadlineDate : null, false);
        $('#ogcFormClient').val(isEdit ? entry.clientId : '');
        $('#ogcFormEditType').val(isEdit ? entry.editType : '');
        $('#ogcFormTitle').val(isEdit ? (entry.title || '') : '');
        $('#ogcFormHook').val(isEdit ? (entry.hook || '') : '');
        $('#ogcFormReference').val(isEdit ? (entry.reference || '') : '');
        $('#ogcFormDescription').val(isEdit ? (entry.contentDescription || '') : '');
        $('#ogcFormNotes').val(isEdit ? (entry.notes || '') : '');
        $('#ogcEntryModal .form-control, #ogcEntryModal .form-select').removeClass('is-invalid');

        if (!entryModal) entryModal = new bootstrap.Modal(document.getElementById('ogcEntryModal'));
        entryModal.show();
    }

    $('#ogcAddBtn').on('click', function () { openEntryModal('add', null); });

    $(document).on('click', '.ogc-edit', function () {
        const entry = entries.find(e => e.id === Number($(this).data('id')));
        if (!entry) return;
        openEntryModal('edit', entry);
    });

    function validateForm() {
        const errors = [];
        $('#ogcEntryModal .form-control, #ogcEntryModal .form-select').removeClass('is-invalid');

        if (!$('#ogcFormDate').val()) { $('#ogcFormDate').addClass('is-invalid'); errors.push('Select date is required.'); }
        if (!$('#ogcFormDeadline').val()) { $('#ogcFormDeadline').addClass('is-invalid'); errors.push('Deadline date is required.'); }
        if (!$('#ogcFormClient').val()) { $('#ogcFormClient').addClass('is-invalid'); errors.push('Client is required.'); }
        if (!$('#ogcFormEditType').val()) { $('#ogcFormEditType').addClass('is-invalid'); errors.push('Edit type is required.'); }
        if (!$('#ogcFormDescription').val().trim()) { $('#ogcFormDescription').addClass('is-invalid'); errors.push('Content description is required.'); }

        return errors;
    }

    $('#ogcSaveBtn').on('click', function () {
        const errors = validateForm();
        if (errors.length) { notify('danger', errors[0]); return; }

        const id = $('#ogcFormId').val();
        const payload = Object.assign({
            clientId: Number($('#ogcFormClient').val()),
            contentDate: $('#ogcFormDate').val(),
            deadlineDate: $('#ogcFormDeadline').val(),
            editType: $('#ogcFormEditType').val(),
            title: $('#ogcFormTitle').val().trim(),
            hook: $('#ogcFormHook').val().trim(),
            reference: $('#ogcFormReference').val().trim(),
            contentDescription: $('#ogcFormDescription').val().trim(),
            notes: $('#ogcFormNotes').val().trim()
        }, id ? { id: id } : {});

        $.ajax({
            url: 'api/other-graphic-content/save-entry.php',
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
    // beyond NEW (see api/other-graphic-content/delete-entry.php), same
    // guard pattern as Social Content's own Clear Data.
    $(document).on('click', '.ogc-delete', function () {
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
                url: 'api/other-graphic-content/delete-entry.php',
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
    $('#ogcRefreshBtn').on('click', loadEntries);
    $('#ogcClient').on('change', loadEntries);

    $('#ogcEntryModal').on('input change', 'input, select, textarea', function () { formDirty = true; });

    $('#ogcCancelBtn').on('click', function () {
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

    $('#ogcEntryModal').on('hidden.bs.modal', function () { formDirty = false; });

    loadEntries();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
