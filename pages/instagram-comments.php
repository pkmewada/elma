<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Instagram Comments</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item active" aria-current="page">Instagram Comments</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h5 class="mb-0">Comments</h5>
                        <div class="d-flex gap-2 flex-wrap">
                            <select class="form-select form-select-sm" id="clientSelect" style="min-width: 220px;">
                                <option value="">Select Client</option>
                            </select>
                            <select class="form-select form-select-sm" id="accountSelect" style="min-width: 200px;" disabled>
                                <option value="">All Accounts</option>
                            </select>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Comment</th>
                                        <th>Post</th>
                                        <th>Status</th>
                                        <th>Commented</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="instagramCommentsBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Select a client to view their comments.</td>
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

<div class="modal fade" id="replyCommentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="replyCommentForm">
                <?= getCsrfInput() ?>
                <input type="hidden" id="replyCommentId" name="commentId" value="">

                <div class="modal-header">
                    <h6 class="modal-title">Reply to Comment</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="text-muted mb-2" id="replyCommentOriginal"></p>
                    <label class="form-label" for="replyCommentMessage">Your Reply</label>
                    <textarea class="form-control" id="replyCommentMessage" name="message" rows="3" maxlength="2200" required></textarea>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="replyCommentSubmitBtn">Send Reply</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
let instagramCommentsCurrent = [];

$(function() {
    loadInstagramCommentsClients();

    $('#clientSelect').on('change', function() {
        loadInstagramAccountsForClientFilter($(this).val());
        loadInstagramComments();
    });

    $('#accountSelect').on('change', function() {
        loadInstagramComments();
    });

    $('#instagramCommentsBody').on('click', '.reply-instagram-comment', function() {
        const commentId = $(this).data('comment-id');
        const comment = instagramCommentsCurrent.find(c => String(c.id) === String(commentId));

        $('#replyCommentId').val(commentId);
        $('#replyCommentOriginal').text(comment ? ('@' + (comment.username || 'user') + ': ' + (comment.commentText || '')) : '');
        $('#replyCommentMessage').val('');

        const modal = new bootstrap.Modal(document.getElementById('replyCommentModal'));
        modal.show();
    });

    $('#instagramCommentsBody').on('click', '.toggle-hide-instagram-comment', function() {
        const commentId = $(this).data('comment-id');
        const hide = $(this).data('hide') == 1;
        toggleHideInstagramComment(commentId, hide);
    });

    $('#replyCommentForm').on('submit', function(e) {
        e.preventDefault();
        submitInstagramCommentReply();
    });
});

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function loadInstagramCommentsClients() {
    $.ajax({
        url: API_BASE + '/client/getClients.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const clients = (response && response.data) || [];
            let options = '<option value="">Select Client</option>';

            clients.forEach(function(client) {
                options += `<option value="${client.id}">${escapeHtml(client.fullName + ' (' + client.clientCode + ')')}</option>`;
            });

            $('#clientSelect').html(options);
        }
    });
}

function loadInstagramAccountsForClientFilter(clientId) {
    const $accountSelect = $('#accountSelect');

    if (!clientId) {
        $accountSelect.html('<option value="">All Accounts</option>').prop('disabled', true);
        return;
    }

    $.getJSON(API_BASE + '/instagram/getInstagramSettings.php', { clientId: clientId })
        .done(function(res) {
            const accounts = (res && res.data && res.data.instagramAccounts) || [];
            let options = '<option value="">All Accounts</option>';

            accounts.forEach(function(account) {
                options += `<option value="${account.id}">@${escapeHtml(account.username || account.instagramUserId)}</option>`;
            });

            $accountSelect.html(options).prop('disabled', accounts.length === 0);
        });
}

function statusBadgeForComment(status) {
    const map = { visible: 'success', replied: 'info', hidden: 'secondary' };
    const color = map[status] || 'secondary';
    const label = status ? status.charAt(0).toUpperCase() + status.slice(1) : 'Unknown';

    return `<span class="badge bg-${color}-transparent">${label}</span>`;
}

function loadInstagramComments() {
    const clientId = $('#clientSelect').val();
    const accountId = $('#accountSelect').val();

    if (!clientId) {
        $('#instagramCommentsBody').html('<tr><td colspan="6" class="text-center text-muted">Select a client to view their comments.</td></tr>');
        return;
    }

    $.getJSON(API_BASE + '/instagram/getInstagramComments.php', { clientId: clientId, accountId: accountId })
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load comments.');
                return;
            }

            instagramCommentsCurrent = res.data.comments || [];
            renderInstagramComments(instagramCommentsCurrent);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load comments.');
        });
}

function renderInstagramComments(comments) {
    const $body = $('#instagramCommentsBody');
    $body.empty();

    if (!comments.length) {
        $body.append('<tr><td colspan="6" class="text-center text-muted">No comments found.</td></tr>');
        return;
    }

    comments.forEach(function(comment) {
        const isHidden = comment.status === 'hidden';
        const postLabel = comment.postId ? ('Post #' + comment.postId) : `<span class="text-muted" title="Not published through Modlus">External post</span>`;

        const actions = `
            <button type="button" class="btn btn-sm btn-outline-primary me-1 reply-instagram-comment" data-comment-id="${comment.id}">Reply</button>
            <button type="button" class="btn btn-sm btn-outline-${isHidden ? 'success' : 'secondary'} toggle-hide-instagram-comment" data-comment-id="${comment.id}" data-hide="${isHidden ? 0 : 1}">
                ${isHidden ? 'Unhide' : 'Hide'}
            </button>
        `;

        $body.append(`
            <tr>
                <td>@${escapeHtml(comment.username || 'unknown')}</td>
                <td class="text-wrap" style="max-width: 320px;">${escapeHtml(comment.commentText || '')}</td>
                <td>${postLabel}</td>
                <td>${statusBadgeForComment(comment.status)}</td>
                <td>${escapeHtml(comment.commentedAt || comment.createdAt || '—')}</td>
                <td class="text-end">${actions}</td>
            </tr>
        `);
    });
}

function submitInstagramCommentReply() {
    const commentId = $('#replyCommentId').val();
    const message = $('#replyCommentMessage').val().trim();

    if (!message) {
        window.showToast && window.showToast('warning', 'Please enter a reply.');
        return;
    }

    const $btn = $('#replyCommentSubmitBtn');
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Sending...');

    $.ajax({
        url: API_BASE + '/instagram/replyInstagramComment.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: { commentId: commentId, message: message, csrfToken: CSRF_TOKEN },
        dataType: 'json',
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Unable to send reply.'
            );

            if (res && res.success) {
                bootstrap.Modal.getInstance(document.getElementById('replyCommentModal')).hide();
                loadInstagramComments();
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to send reply.');
        },
        complete: function() {
            $btn.prop('disabled', false).text('Send Reply');
        }
    });
}

function toggleHideInstagramComment(commentId, hide) {
    $.ajax({
        url: API_BASE + '/instagram/hideInstagramComment.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: { commentId: commentId, hide: hide ? 1 : 0, csrfToken: CSRF_TOKEN },
        dataType: 'json',
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Unable to update comment.'
            );

            if (res && res.success) {
                loadInstagramComments();
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to update comment.');
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
