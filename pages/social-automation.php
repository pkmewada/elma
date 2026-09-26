<?php
/*
|--------------------------------------------------------------------------
| Automation Queue
|--------------------------------------------------------------------------
|
| Read-only view over the existing Production -> Automation handoff
| (includes/SocialAutomationHandoffEngine.php) and socialPosts. No new
| table, no new publishing engine -- this page only renders
| SocialAutomationHandoffEngine::listQueue() and reuses the existing
| composer (social-create-post) for edits and the existing
| send-to-automation.php endpoint for retries, exactly as
| pages/social-content-production.php already does.
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
                <h1 class="page-title fw-medium fs-18 mb-2">Automation Queue</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item active" aria-current="page">Automation Queue</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Automation Queue</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <select class="form-select form-select-sm" id="aqClientFilter" style="min-width: 200px;">
                                <option value="">All Clients</option>
                            </select>
                            <select class="form-select form-select-sm" id="aqStatusFilter" style="min-width: 180px;">
                                <option value="">All Statuses</option>
                                <option value="eligible">Eligible — Not Sent</option>
                                <option value="failed">Failed</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="publishing">Publishing</option>
                                <option value="published">Published</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Platform</th>
                                        <th>Content</th>
                                        <th>Media</th>
                                        <th>Caption</th>
                                        <th>Schedule Date</th>
                                        <th>Status</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="aqBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Loading queue...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="aqContentModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Content</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="aqContentModalBody">
                <div class="text-center text-muted">Loading...</div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
let aqClientLabels = {};

$(function() {
    loadAqClients(loadAqQueue);

    $('#aqStatusFilter, #aqClientFilter').on('change', loadAqQueue);

    $('#aqBody').on('click', '.aq-view-content', function() {
        const productionId = $(this).data('production-id');
        $('#aqContentModalBody').html('<div class="text-center text-muted">Loading...</div>');
        $('#aqContentModal').modal('show');

        $.getJSON(API_BASE + '/social-content-production/get-tasks.php', { id: productionId })
            .done(function(res) {
                if (!res || !res.success) {
                    $('#aqContentModalBody').html('<div class="text-danger">' + escapeAqHtml((res && res.message) || 'Failed to load content.') + '</div>');
                    return;
                }
                const t = res.data;
                $('#aqContentModalBody').html(`
                    <p class="mb-1"><strong>Client:</strong> ${escapeAqHtml(t.clientName || '—')}</p>
                    <p class="mb-1"><strong>Platform:</strong> ${escapeAqHtml(t.platformName || '—')}</p>
                    <p class="mb-1"><strong>Content Date:</strong> ${escapeAqHtml(t.contentDate || '—')}</p>
                    <p class="mb-1"><strong>Title:</strong> ${escapeAqHtml(t.title || '—')}</p>
                    <p class="mb-0"><strong>Brief:</strong> ${escapeAqHtml(t.rawContent || t.contentDescription || '—')}</p>
                `);
            })
            .fail(function() {
                $('#aqContentModalBody').html('<div class="text-danger">Network error while loading content.</div>');
            });
    });

    $('#aqBody').on('click', '.aq-retry', function() {
        const $btn = $(this);
        const productionId = $btn.data('production-id');
        const isRetry = /retry/i.test($btn.text());

        Swal.fire({
            title: isRetry ? 'Retry sending this task to Automation?' : 'Send this task to Automation?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: isRetry ? 'Retry' : 'Send'
        }).then(function(res) {
            if (!res.isConfirmed) return;

            $btn.prop('disabled', true);

            $.ajax({
                url: API_BASE + '/social-content-production/send-to-automation.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ productionId: productionId }),
                dataType: 'json'
            }).done(function(res) {
                if (!res || !res.success) {
                    window.showToast && window.showToast('danger', (res && res.message) || 'Unable to retry Automation.');
                    $btn.prop('disabled', false);
                    return;
                }
                window.showToast && window.showToast('success', res.message || 'Sent to Automation.');
                loadAqQueue();
            }).fail(function() {
                window.showToast && window.showToast('danger', 'Network error while retrying Automation.');
                $btn.prop('disabled', false);
            });
        });
    });
});

function loadAqClients(onDone) {
    $.ajax({
        url: API_BASE + '/client/getClients.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const clients = (response && response.data) || [];
            let options = '<option value="">All Clients</option>';

            aqClientLabels = {};
            clients.forEach(function(client) {
                const label = client.fullName + ' (' + client.clientCode + ')';
                aqClientLabels[client.id] = label;
                options += `<option value="${client.id}">${escapeAqHtml(label)}</option>`;
            });

            $('#aqClientFilter').html(options);
        },
        complete: function() {
            if (typeof onDone === 'function') onDone();
        }
    });
}

function loadAqQueue() {
    const status = $('#aqStatusFilter').val();
    const clientId = $('#aqClientFilter').val();

    $.getJSON(API_BASE + '/social-content-production/get-automation-queue.php', { status: status, clientId: clientId })
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', (res && res.message) || 'Unable to load the automation queue.');
                return;
            }
            renderAqRows(res.data || []);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load the automation queue.');
        });
}

function escapeAqHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function aqStatusBadge(status) {
    const map = {
        eligible: 'primary',
        pending: 'secondary',
        scheduled: 'info',
        publishing: 'warning',
        published: 'success',
        partial: 'orange',
        failed: 'danger',
    };
    const labels = { eligible: 'Eligible — Not Sent' };
    const color = map[status] || 'secondary';
    const label = labels[status] || (status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown');
    return `<span class="badge bg-${color}-transparent">${label}</span>`;
}

function aqMediaThumb(row) {
    const urls = row.mediaUrl || [];
    if (!urls.length) {
        return '<span class="text-muted">—</span>';
    }
    return `<img src="${escapeAqHtml(urls[0])}" alt="media" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">`;
}

function renderAqRows(rows) {
    const $body = $('#aqBody');
    $body.empty();

    if (!rows.length) {
        $body.append('<tr><td colspan="8" class="text-center text-muted">No content in the automation queue.</td></tr>');
        return;
    }

    rows.forEach(function(row) {
        const contentLabel = [row.platformName, row.postType, row.contentDate].filter(Boolean).join(' - ') || '—';
        const caption = row.selectedCaption
            ? (row.selectedCaption.substring(0, 60) + (row.selectedCaption.length > 60 ? '…' : ''))
            : '<span class="text-muted">No caption</span>';
        const scheduleInfo = row.publishedAt || row.scheduledAt || '—';

        let actions = `<button type="button" class="btn btn-sm btn-outline-secondary me-1 aq-view-content" data-production-id="${row.productionId}">View Content</button>`;

        if (row.socialPostId && ['draft', 'scheduled', 'failed', 'partial'].indexOf(row.status) !== -1) {
            actions += `<a href="social-create-post?postId=${row.socialPostId}" class="btn btn-sm btn-outline-primary me-1">Edit</a>`;
        }

        if (row.status === 'eligible') {
            actions += `<button type="button" class="btn btn-sm btn-dark me-1 aq-retry" data-production-id="${row.productionId}"><i class="ri-send-plane-line"></i> Send to Automation</button>`;
        } else if (row.status === 'failed') {
            actions += `<button type="button" class="btn btn-sm btn-outline-danger me-1 aq-retry" data-production-id="${row.productionId}"><i class="ri-refresh-line"></i> Retry</button>`;
        }

        if (row.errorMessage) {
            actions += `<button type="button" class="btn btn-sm btn-outline-warning aq-view-error" title="${escapeAqHtml(row.errorMessage)}"><i class="ri-error-warning-line"></i></button>`;
        }

        $body.append(`
            <tr>
                <td>${escapeAqHtml(row.clientName || 'Unassigned')}</td>
                <td>${escapeAqHtml(row.platformName || '—')}</td>
                <td>${escapeAqHtml(contentLabel)}<div class="fs-12 text-muted">${escapeAqHtml(row.title || '')}</div></td>
                <td>${aqMediaThumb(row)}</td>
                <td>${caption}</td>
                <td>${escapeAqHtml(scheduleInfo)}</td>
                <td>${aqStatusBadge(row.status)}</td>
                <td class="text-end">${actions}</td>
            </tr>
        `);
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
