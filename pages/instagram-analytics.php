<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Instagram Analytics</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item active" aria-current="page">Instagram Analytics</li>
                </ol>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <select class="form-select form-select-sm" id="clientSelect" style="min-width: 220px;">
                    <option value="">Select Client</option>
                </select>
                <select class="form-select form-select-sm" id="accountSelect" style="min-width: 200px;" disabled>
                    <option value="">Select an Instagram account</option>
                </select>
            </div>
        </div>

        <div class="alert alert-info d-none" id="noAnalyticsAlert">
            No analytics data yet for this account. Data is populated by the daily <code>cron/instagramAnalyticsSync.php</code> sync.
        </div>

        <div class="alert alert-warning d-none" id="syncErrorAlert"></div>
        <div class="text-muted fs-13 mb-3 d-none" id="syncStatusLine"></div>

        <div class="row g-3 mb-3" id="accountStatCards"></div>

        <div class="row g-4">
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Post Performance</h5>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Post</th>
                                        <th>Reach</th>
                                        <th>Impressions</th>
                                        <th>Plays</th>
                                        <th>Likes</th>
                                        <th>Comments</th>
                                        <th>Saved</th>
                                        <th>Captured</th>
                                    </tr>
                                </thead>
                                <tbody id="postInsightsBody">
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">Select a client and Instagram account to view analytics.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Webhook Events</h5>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered text-nowrap">
                                <thead>
                                    <tr>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Error</th>
                                        <th>Received</th>
                                    </tr>
                                </thead>
                                <tbody id="webhookEventsBody">
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Select a client and Instagram account to view webhook activity.</td>
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
let instagramAnalyticsAccounts = [];

$(function() {
    loadInstagramAnalyticsClients();

    $('#clientSelect').on('change', function() {
        loadInstagramAccountsForAnalytics($(this).val());
        renderEmptyAnalyticsState();
    });

    $('#accountSelect').on('change', function() {
        loadInstagramAnalytics();
        loadInstagramWebhookEvents();
        renderSyncStatus();
    });
});

function escapeHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function renderEmptyAnalyticsState() {
    $('#accountStatCards').empty();
    $('#postInsightsBody').html('<tr><td colspan="8" class="text-center text-muted">Select a client and Instagram account to view analytics.</td></tr>');
    $('#webhookEventsBody').html('<tr><td colspan="4" class="text-center text-muted">Select a client and Instagram account to view webhook activity.</td></tr>');
    $('#noAnalyticsAlert').addClass('d-none');
    $('#syncErrorAlert').addClass('d-none');
    $('#syncStatusLine').addClass('d-none');
}

function renderSyncStatus() {
    const accountId = $('#accountSelect').val();
    const account = instagramAnalyticsAccounts.find(a => String(a.id) === String(accountId));

    if (!account) {
        $('#syncStatusLine').addClass('d-none');
        $('#syncErrorAlert').addClass('d-none');
        return;
    }

    if (account.lastAnalyticsSyncAt) {
        $('#syncStatusLine').removeClass('d-none').text('Last analytics sync: ' + account.lastAnalyticsSyncAt);
    } else {
        $('#syncStatusLine').removeClass('d-none').text('Not synced yet — waiting for the next cron/instagramAnalyticsSync.php run.');
    }

    if (account.lastAnalyticsSyncError) {
        $('#syncErrorAlert').removeClass('d-none').text('Last sync error: ' + account.lastAnalyticsSyncError);
    } else {
        $('#syncErrorAlert').addClass('d-none');
    }
}

function loadInstagramAnalyticsClients() {
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

function loadInstagramAccountsForAnalytics(clientId) {
    const $accountSelect = $('#accountSelect');

    if (!clientId) {
        $accountSelect.html('<option value="">Select an Instagram account</option>').prop('disabled', true);
        return;
    }

    $.getJSON(API_BASE + '/instagram/getInstagramSettings.php', { clientId: clientId })
        .done(function(res) {
            const accounts = ((res && res.data && res.data.instagramAccounts) || []).filter(a => a.status === 'connected');
            instagramAnalyticsAccounts = accounts;
            let options = '<option value="">Select an Instagram account</option>';

            accounts.forEach(function(account) {
                options += `<option value="${account.id}">@${escapeHtml(account.username || account.instagramUserId)}</option>`;
            });

            $accountSelect.html(options).prop('disabled', accounts.length === 0);
        });
}

function loadInstagramWebhookEvents() {
    const clientId = $('#clientSelect').val();
    const accountId = $('#accountSelect').val();

    if (!clientId || !accountId) {
        return;
    }

    $.getJSON(API_BASE + '/instagram/getInstagramWebhookEvents.php', { clientId: clientId, accountId: accountId })
        .done(function(res) {
            if (!res || !res.success) {
                return;
            }

            renderWebhookEvents(res.data.events || []);
        });
}

function renderWebhookEvents(events) {
    const $body = $('#webhookEventsBody');
    $body.empty();

    if (!events.length) {
        $body.append('<tr><td colspan="4" class="text-center text-muted">No webhook events received yet for this account.</td></tr>');
        return;
    }

    const statusColors = { received: 'info', processed: 'success', failed: 'danger' };

    events.forEach(function(event) {
        const color = statusColors[event.status] || 'secondary';

        $body.append(`
            <tr>
                <td>${escapeHtml(event.eventType)}</td>
                <td><span class="badge bg-${color}-transparent">${escapeHtml(event.status)}</span></td>
                <td class="text-wrap" style="max-width: 320px;">${escapeHtml(event.errorMessage || '—')}</td>
                <td>${escapeHtml(event.createdAt)}</td>
            </tr>
        `);
    });
}

function loadInstagramAnalytics() {
    const clientId = $('#clientSelect').val();
    const accountId = $('#accountSelect').val();

    if (!clientId || !accountId) {
        renderEmptyAnalyticsState();
        return;
    }

    $.getJSON(API_BASE + '/instagram/getInstagramInsights.php', { clientId: clientId, accountId: accountId, days: 30 })
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', res && res.message ? res.message : 'Unable to load analytics.');
                return;
            }

            renderInstagramAnalytics(res.data.insights || []);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load analytics.');
        });
}

function renderInstagramAnalytics(insights) {
    const accountRows = insights.filter(row => row.postId === null);
    const postRows = insights.filter(row => row.postId !== null);

    $('#noAnalyticsAlert').toggleClass('d-none', insights.length > 0);

    renderAccountStatCards(accountRows);
    renderPostInsights(postRows);
}

function renderAccountStatCards(accountRows) {
    const latestByMetric = {};

    accountRows.forEach(function(row) {
        if (!latestByMetric[row.metricName] || row.capturedAt > latestByMetric[row.metricName].capturedAt) {
            latestByMetric[row.metricName] = row;
        }
    });

    const cardDefs = [
        { key: 'followers_count', label: 'Followers', icon: 'ti-users' },
        { key: 'media_count', label: 'Total Posts', icon: 'ti-photo' },
        { key: 'reach', label: 'Reach (latest day)', icon: 'ti-eye' },
        { key: 'profile_views', label: 'Profile Views (latest day)', icon: 'ti-user-search' },
    ];

    const $cards = $('#accountStatCards');
    $cards.empty();

    cardDefs.forEach(function(def) {
        const value = latestByMetric[def.key] ? Number(latestByMetric[def.key].metricValue).toLocaleString() : '—';

        $cards.append(`
            <div class="col-xl-3 col-md-6">
                <div class="card custom-card">
                    <div class="card-body d-flex align-items-center gap-3">
                        <i class="ti ${def.icon} fs-24 text-primary"></i>
                        <div>
                            <div class="fs-20 fw-semibold">${value}</div>
                            <div class="text-muted fs-12">${def.label}</div>
                        </div>
                    </div>
                </div>
            </div>
        `);
    });
}

function renderPostInsights(postRows) {
    const $body = $('#postInsightsBody');
    $body.empty();

    if (!postRows.length) {
        $body.append('<tr><td colspan="8" class="text-center text-muted">No post-level analytics yet.</td></tr>');
        return;
    }

    const byPost = {};

    postRows.forEach(function(row) {
        if (!byPost[row.postId]) {
            byPost[row.postId] = { postId: row.postId, capturedAt: row.capturedAt, metrics: {} };
        }

        byPost[row.postId].metrics[row.metricName] = row.metricValue;

        if (row.capturedAt > byPost[row.postId].capturedAt) {
            byPost[row.postId].capturedAt = row.capturedAt;
        }
    });

    Object.values(byPost)
        .sort((a, b) => b.postId - a.postId)
        .forEach(function(post) {
            const m = post.metrics;

            $body.append(`
                <tr>
                    <td><a href="social-posts">Post #${post.postId}</a></td>
                    <td>${m.reach != null ? Number(m.reach).toLocaleString() : '—'}</td>
                    <td>${m.impressions != null ? Number(m.impressions).toLocaleString() : '—'}</td>
                    <td>${m.plays != null ? Number(m.plays).toLocaleString() : '—'}</td>
                    <td>${m.likes != null ? Number(m.likes).toLocaleString() : '—'}</td>
                    <td>${m.comments != null ? Number(m.comments).toLocaleString() : '—'}</td>
                    <td>${m.saved != null ? Number(m.saved).toLocaleString() : '—'}</td>
                    <td>${escapeHtml(post.capturedAt)}</td>
                </tr>
            `);
        });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
