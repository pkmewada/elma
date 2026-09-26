<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Social Posts</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item active" aria-current="page">Social Posts</li>
                </ol>
            </div>
            <div>
                <a href="social-create-post" class="btn btn-primary">
                    <i class="ti ti-plus me-1"></i>
                    Create Post
                </a>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="noAccountWarning">
            No Instagram account is connected. Scheduled posts will not publish until you <a href="instagram-automation">connect an account</a>.
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">All Posts</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <select class="form-select form-select-sm" id="clientFilter" style="min-width: 200px;">
                                <option value="">All Clients</option>
                            </select>
                            <select class="form-select form-select-sm" id="statusFilter" style="min-width: 180px;">
                                <option value="">All Statuses</option>
                                <option value="draft">Draft</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="publishing">Publishing</option>
                                <option value="published">Published</option>
                                <option value="partial">Partial</option>
                                <option value="failed">Failed</option>
                            </select>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Account</th>
                                        <th>Media</th>
                                        <th>Type</th>
                                        <th>Caption</th>
                                        <th>Results</th>
                                        <th>Status</th>
                                        <th>Scheduled / Published</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="instagramPostsBody">
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Loading posts...</td>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let instagramClientLabels = {};
let instagramAccountUsernames = {};

$(function() {
    loadAllInstagramAccounts(function() {
        loadInstagramClients(loadInstagramPosts);
    });
    checkInstagramAccountConnected();

    $('#statusFilter, #clientFilter').on('change', function() {
        loadInstagramPosts();
        checkInstagramAccountConnected();
    });

    $('#instagramPostsBody').on('click', '.delete-instagram-post', function() {
        const postId = $(this).data('post-id');

        if (!confirm('Delete this social post? This cannot be undone.')) {
            return;
        }

        deleteInstagramPost(postId);
    });

    $('#instagramPostsBody').on('click', '.view-instagram-post-error', function() {
        const message = $(this).data('error-message') || 'No error details available.';
        window.showToast && window.showToast('danger', message);
    });
});

function loadInstagramClients(onDone) {
    $.ajax({
        url: API_BASE + '/client/getClients.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const clients = (response && response.data) || [];
            let options = '<option value="">All Clients</option>';

            instagramClientLabels = {};
            clients.forEach(function(client) {
                const label = client.fullName + ' (' + client.clientCode + ')';
                instagramClientLabels[client.id] = label;
                options += `<option value="${client.id}">${$('<div>').text(label).html()}</option>`;
            });

            $('#clientFilter').html(options);
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to load clients.');
        },
        complete: function() {
            if (typeof onDone === 'function') {
                onDone();
            }
        }
    });
}

// Reuses the existing getInstagramSettings.php endpoint (same one the
// composer's account dropdown already calls) with no clientId filter to
// build an id->username lookup for the whole listing — one bulk fetch, no
// per-row queries. This endpoint never returns an access token (confirmed
// in includes/InstagramAutomation.php's getInstagramAccounts() column
// list), so this is safe to call without any new backend endpoint.
function loadAllInstagramAccounts(onDone) {
    $.getJSON(API_BASE + '/instagram/getInstagramSettings.php')
        .done(function(res) {
            const accounts = (res && res.data && res.data.instagramAccounts) || [];
            instagramAccountUsernames = {};

            accounts.forEach(function(account) {
                instagramAccountUsernames[account.id] = account.username || account.instagramUserId || '';
            });
        })
        .always(function() {
            if (typeof onDone === 'function') {
                onDone();
            }
        });
}

function accountLabel(post) {
    if (!post.instagramAccountId) {
        return '<span class="text-muted">—</span>';
    }

    const username = instagramAccountUsernames[post.instagramAccountId];

    if (!username) {
        return '<span class="text-muted">—</span>';
    }

    return '@' + escapeHtml(username);
}

function checkInstagramAccountConnected() {
    const clientId = $('#clientFilter').val();

    $.getJSON(API_BASE + '/instagram/getInstagramSettings.php', clientId ? { clientId: clientId } : {})
        .done(function(res) {
            const accounts = (res && res.data && res.data.instagramAccounts) || [];
            const hasConnected = accounts.some(a => a.status === 'connected');
            $('#noAccountWarning').toggleClass('d-none', hasConnected);
        });
}

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function statusBadge(status) {
    const map = {
        draft: 'secondary',
        scheduled: 'info',
        publishing: 'warning',
        published: 'success',
        partial: 'orange',
        failed: 'danger',
    };
    const color = map[status] || 'secondary';
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';

    return `<span class="badge bg-${color}-transparent">${label}</span>`;
}

function typeBadge(mediaType) {
    const labels = { image: 'Image', text: 'Text', reel: 'Reel', carousel: 'Carousel' };
    return `<span class="badge bg-primary-transparent">${labels[mediaType] || mediaType}</span>`;
}

// Facebook's own per-platform state already exists on the row
// (facebookStatus, written by finalizeSocialScheduledPost() /
// publishSocialPostNow.php) — displayed as-is, never recalculated.
// Instagram has no equivalent dedicated status column; its result is
// shown from the same two fields the backend itself already uses as the
// source of truth for "did Instagram succeed" (instagramMediaId present)
// and "did Instagram fail" (the overall post status reached a terminal
// failed/partial state without an id) — this reads existing data, it does
// not compute a new status.
function platformResults(post) {
    const platforms = (post.platforms || 'instagram').split(',').map(p => p.trim()).filter(Boolean);
    const terminal = ['published', 'partial', 'failed'].indexOf(post.status) !== -1;
    let html = '';

    if (platforms.indexOf('instagram') !== -1) {
        const published = !!post.instagramMediaId;
        const failed = !published && terminal;
        const color = published ? 'success' : (failed ? 'danger' : 'secondary');
        const label = published ? 'Instagram · Published' : (failed ? 'Instagram · Failed' : 'Instagram · Pending');

        html += `<div class="mb-2"><span class="badge bg-${color}-transparent">${label}</span>`;

        if (published) {
            html += `<div class="fs-12 text-muted mt-1">ID: ${escapeHtml(post.instagramMediaId)}</div>`;
        } else if (failed && post.errorMessage) {
            html += `<div class="fs-12 text-danger text-wrap mt-1" style="max-width: 220px;">${escapeHtml(post.errorMessage)}</div>`;
        } else {
            html += '<div class="fs-12 text-muted mt-1">—</div>';
        }

        html += '</div>';
    }

    if (platforms.indexOf('facebook') !== -1) {
        const fbColors = { published: 'success', failed: 'danger', pending: 'secondary', not_applicable: 'secondary' };
        const color = fbColors[post.facebookStatus] || 'secondary';
        const label = 'Facebook · ' + (post.facebookStatus ? (post.facebookStatus.charAt(0).toUpperCase() + post.facebookStatus.slice(1)) : 'Pending');

        html += `<div><span class="badge bg-${color}-transparent">${label}</span>`;

        if (post.facebookStatus === 'published' && post.facebookPostId) {
            const fbUrl = 'https://www.facebook.com/' + encodeURIComponent(post.facebookPostId);
            html += `<div class="fs-12 text-muted mt-1">ID: <a href="${fbUrl}" target="_blank" rel="noopener noreferrer">${escapeHtml(post.facebookPostId)}</a></div>`;
        } else if (post.facebookStatus === 'failed' && post.facebookErrorMessage) {
            html += `<div class="fs-12 text-danger text-wrap mt-1" style="max-width: 220px;">${escapeHtml(post.facebookErrorMessage)}</div>`;
        } else {
            html += '<div class="fs-12 text-muted mt-1">—</div>';
        }

        html += '</div>';
    }

    return html || '<span class="text-muted">—</span>';
}

function mediaThumb(post) {
    const urls = post.mediaUrls || [];

    if (!urls.length) {
        return '<span class="text-muted">—</span>';
    }

    if (post.mediaType === 'reel') {
        return '<i class="ti ti-video fs-20 text-muted" title="Video"></i>';
    }

    return `<img src="${escapeHtml(urls[0])}" alt="media" style="width:48px;height:48px;object-fit:cover;border-radius:6px;">`;
}

function loadInstagramPosts() {
    const status = $('#statusFilter').val();
    const clientId = $('#clientFilter').val();

    $.getJSON(API_BASE + '/social-media/getSocialPosts.php', { status: status, clientId: clientId })
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load social posts.');
                return;
            }

            renderInstagramPosts(res.data.posts || []);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load social posts.');
        });
}

function renderInstagramPosts(posts) {
    const $body = $('#instagramPostsBody');
    $body.empty();

    if (!posts.length) {
        $body.append('<tr><td colspan="9" class="text-center text-muted">No social posts found.</td></tr>');
        return;
    }

    posts.forEach(function(post) {
        const canEdit = ['draft', 'scheduled', 'failed', 'partial'].indexOf(post.status) !== -1;
        const canDelete = ['draft', 'scheduled', 'failed', 'partial'].indexOf(post.status) !== -1;
        const caption = post.caption ? post.caption.substring(0, 60) + (post.caption.length > 60 ? '…' : '') : '<span class="text-muted">No caption</span>';
        const scheduleInfo = post.status === 'published'
            ? escapeHtml(post.publishedAt || '—')
            : escapeHtml(post.scheduledAt || '—');
        const clientLabel = post.clientId ? (instagramClientLabels[post.clientId] || ('Client #' + post.clientId)) : 'Unassigned';

        let actions = '';

        if (canEdit) {
            actions += `<a href="social-create-post?postId=${post.id}" class="btn btn-sm btn-outline-primary me-1">Edit</a>`;
        }

        if (post.status === 'failed' || post.status === 'partial' || post.facebookStatus === 'failed') {
            const errorParts = [];
            if (post.errorMessage) { errorParts.push('Instagram: ' + post.errorMessage); }
            if (post.facebookErrorMessage) { errorParts.push('Facebook: ' + post.facebookErrorMessage); }
            actions += `<button type="button" class="btn btn-sm btn-outline-warning me-1 view-instagram-post-error" data-error-message="${escapeHtml(errorParts.join('\n') || 'No error details recorded.')}">View Error</button>`;
        }

        if (canDelete) {
            actions += `<button type="button" class="btn btn-sm btn-outline-danger delete-instagram-post" data-post-id="${post.id}">Delete</button>`;
        }

        $body.append(`
            <tr>
                <td>${escapeHtml(clientLabel)}</td>
                <td>${accountLabel(post)}</td>
                <td>${mediaThumb(post)}</td>
                <td>${typeBadge(post.mediaType)}</td>
                <td class="text-wrap" style="max-width: 320px;">${caption}</td>
                <td>${platformResults(post)}</td>
                <td>${statusBadge(post.status)}</td>
                <td>${scheduleInfo}</td>
                <td class="text-end">${actions}</td>
            </tr>
        `);
    });
}

function deleteInstagramPost(postId) {
    if (!postId) {
        return;
    }

    $.ajax({
        url: API_BASE + '/social-media/deleteSocialPost.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: { postId: postId, csrfToken: CSRF_TOKEN },
        dataType: 'json',
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Unable to delete post.'
            );

            if (res && res.success) {
                loadInstagramPosts();
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to delete post.');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
