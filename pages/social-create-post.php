<?php include __DIR__ . '/../includes/auth.php'; ?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2" id="pageTitle">Create Social Post</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">Automation</li>
                    <li class="breadcrumb-item">
                        <a href="social-posts">Social Posts</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page" id="breadcrumbCurrent">Create Post</li>
                </ol>
            </div>
        </div>

        <div class="alert alert-warning d-none" id="postLockedAlert">
            This post has already been published or is currently publishing and can no longer be edited.
        </div>

        <div class="alert alert-warning d-none" id="noAccountWarning">
            No Instagram account is connected yet. <a href="instagram-automation">Connect an account</a> before scheduling posts.
        </div>

        <form id="instagramPostForm" autocomplete="off">
            <?= getCsrfInput() ?>
            <input type="hidden" id="postId" name="postId" value="">

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card custom-card">
                        <div class="card-header">
                            <h5 class="mb-0">Post Content</h5>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="clientSelect">Client</label>
                                    <select class="form-select" id="clientSelect" name="clientId" required>
                                        <option value="">Select Client</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="accountSelect">Instagram Account</label>
                                    <select class="form-select" id="accountSelect" name="instagramAccountId" required disabled>
                                        <option value="">Select a client first</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label" for="mediaType">Post Type</label>
                                    <select class="form-select" id="mediaType" name="mediaType">
                                        <option value="image">Image Post</option>
                                        <option value="text">Text Post</option>
                                        <option value="reel">Reel (Video)</option>
                                        <option value="carousel">Carousel (2-10 Images)</option>
                                    </select>
                                </div>

                                <div class="col-12" id="mediaFieldGroup">
                                    <label class="form-label" id="mediaInputLabel" for="mediaFiles">Media</label>
                                    <input type="file" class="form-control" id="mediaFiles" name="media[]" accept="image/jpeg">
                                    <div class="form-text" id="mediaInputHint">JPG or PNG, up to 8 MB.</div>
                                    <div id="existingMediaNote" class="form-text text-muted d-none">Leave empty to keep the existing media.</div>
                                </div>

                                <div class="col-12" id="mediaPreviewGroup">
                                    <div id="mediaPreview" class="d-flex flex-wrap gap-2"></div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label" id="captionLabel" for="caption">Caption</label>
                                    <textarea class="form-control" id="caption" name="caption" rows="5" maxlength="2200"></textarea>
                                    <div class="form-text"><span id="captionCount">0</span>/2200 characters</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card custom-card h-100">
                        <div class="card-header">
                            <h5 class="mb-0">Publish</h5>
                        </div>

                        <div class="card-body d-flex flex-column gap-3">
                            <div>
                                <label class="form-label">Post To</label>
                                <div class="d-flex gap-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="platformInstagram" checked>
                                        <label class="form-check-label" for="platformInstagram">Instagram</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="platformFacebook" disabled>
                                        <label class="form-check-label" for="platformFacebook">Facebook</label>
                                    </div>
                                </div>
                                <div class="form-text d-none" id="noFacebookPageHint">This account has no linked Facebook Page.</div>
                            </div>

                            <div>
                                <label class="form-label" for="scheduledAt">Schedule Date &amp; Time</label>
                                <input type="datetime-local" class="form-control" id="scheduledAt" name="scheduledAt">
                                <div class="form-text">Leave empty to publish as soon as the scheduler next runs.</div>
                            </div>

                            <button type="button" class="btn btn-outline-secondary" id="saveDraftBtn">
                                Save as Draft
                            </button>

                            <button type="button" class="btn btn-primary" id="schedulePostBtn">
                                Schedule Post
                            </button>

                            <hr class="my-1">

                            <button type="button" class="btn btn-success" id="publishNowBtn">
                                Publish Now
                            </button>
                            <div class="form-text" id="publishNowHint">Publishes immediately to the platforms checked above, using the existing scheduled-post flow above only for reels/carousels.</div>

                            <div id="publishNowResults" class="d-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
const existingMediaTypeHints = {
    image: { accept: 'image/jpeg', multiple: false, hint: 'JPEG (.jpg), up to 8 MB.' },
    reel: { accept: 'video/mp4,video/quicktime', multiple: false, hint: 'MP4 or MOV, up to 100 MB.' },
    carousel: { accept: 'image/jpeg', multiple: true, hint: 'JPEG (.jpg), 2-10 images, up to 8 MB each.' },
};

let currentPost = null;

$(function() {
    applyMediaTypeUi($('#mediaType').val());
    loadInstagramClients();

    $('#clientSelect').on('change', function() {
        loadInstagramAccountsForClient($(this).val());
    });

    $('#accountSelect').on('change', function() {
        updateFacebookPlatformAvailability();
    });

    $('#mediaType').on('change', function() {
        applyMediaTypeUi($(this).val());
        $('#mediaFiles').val('');
        $('#mediaPreview').empty();

        // Existing media only carries forward when the type is unchanged —
        // switching type always requires a fresh upload (mirrors the
        // server-side rule in saveSocialPost.php).
        if (currentPost && currentPost.mediaType === $(this).val()) {
            $('#existingMediaNote').removeClass('d-none');
        } else {
            $('#existingMediaNote').addClass('d-none');
        }
    });

    $('#mediaFiles').on('change', function() {
        previewSelectedMedia(this.files);
    });

    $('#caption').on('input', function() {
        $('#captionCount').text($(this).val().length);
    });

    $('#saveDraftBtn').on('click', function() {
        submitInstagramPost('draft');
    });

    $('#schedulePostBtn').on('click', function() {
        submitInstagramPost('schedule');
    });

    $('#publishNowBtn').on('click', function() {
        submitPublishNow();
    });

    const params = new URLSearchParams(window.location.search);
    const postId = params.get('postId');

    if (postId) {
        loadInstagramPostForEdit(postId);
    }
});

function loadInstagramClients() {
    $.ajax({
        url: API_BASE + '/client/getClients.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            const clients = (response && response.data) || [];
            let options = '<option value="">Select Client</option>';

            clients.forEach(function(client) {
                options += `<option value="${client.id}">${$('<div>').text(client.fullName + ' (' + client.clientCode + ')').html()}</option>`;
            });

            $('#clientSelect').html(options);

            if (currentPost && currentPost.clientId) {
                $('#clientSelect').val(String(currentPost.clientId)).trigger('change');
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to load clients.');
        }
    });
}

function loadInstagramAccountsForClient(clientId, preselectAccountId) {
    const $accountSelect = $('#accountSelect');

    if (!clientId) {
        $accountSelect.html('<option value="">Select a client first</option>').prop('disabled', true);
        $('#noAccountWarning').addClass('d-none');
        return;
    }

    $accountSelect.html('<option value="">Loading accounts...</option>').prop('disabled', true);

    $.getJSON(API_BASE + '/instagram/getInstagramSettings.php', { clientId: clientId })
        .done(function(res) {
            const allAccounts = (res && res.data && res.data.instagramAccounts) || [];
            const connected = allAccounts.filter(a => a.status === 'connected');

            $('#noAccountWarning').toggleClass('d-none', connected.length > 0);

            if (!connected.length) {
                $accountSelect.html('<option value="">No connected account for this client</option>').prop('disabled', true);
                return;
            }

            let options = '<option value="">Select Instagram Account</option>';
            connected.forEach(function(account) {
                options += `<option value="${account.id}">@${$('<div>').text(account.username || account.instagramUserId).html()}</option>`;
            });

            $accountSelect.html(options).prop('disabled', false);
            window.instagramComposerAccounts = connected;

            const toSelect = preselectAccountId || (currentPost && currentPost.instagramAccountId);
            if (toSelect) {
                $accountSelect.val(String(toSelect));
            }

            updateFacebookPlatformAvailability();
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load Instagram accounts for this client.');
        });
}

function applyMediaTypeUi(mediaType) {
    const isText = mediaType === 'text';
    const config = existingMediaTypeHints[mediaType] || existingMediaTypeHints.image;

    // Text posts need no media at all — hide the whole media control rather
    // than just relaxing validation, since a text post has nothing to show
    // there (reuses the existing caption field as the post content instead).
    $('#mediaFieldGroup, #mediaPreviewGroup').toggleClass('d-none', isText);
    $('#captionLabel').text(isText ? 'Post Text' : 'Caption');

    if (!isText) {
        $('#mediaFiles').attr('accept', config.accept);
        $('#mediaFiles').prop('multiple', config.multiple);
        $('#mediaInputHint').text(config.hint);
        $('#mediaInputLabel').text(mediaType === 'reel' ? 'Video' : (mediaType === 'carousel' ? 'Images' : 'Image'));
    }

    // Publish Now (Phase 6) supports image and text posts. Facebook
    // scheduling is also image/text-only — no verified Facebook equivalent
    // for reels/carousels yet (docs §22.7/§22.8) — enforced here and again
    // server-side in api/saveSocialPost.php.
    const isImage = mediaType === 'image';
    $('#publishNowBtn').prop('disabled', !(isImage || isText));
    $('#publishNowHint').text((isImage || isText)
        ? 'Publishes immediately to the platforms checked above.'
        : 'Publish Now supports image and text posts only — use Schedule Post for reels/carousels.');

    updateFacebookPlatformAvailability();
}

function updateFacebookPlatformAvailability() {
    const accountId = $('#accountSelect').val();
    const accounts = window.instagramComposerAccounts || [];
    const account = accounts.find(a => String(a.id) === String(accountId));
    const hasPage = !!(account && account.facebookPageId);
    const mediaType = $('#mediaType').val();
    const isText = mediaType === 'text';

    if (isText) {
        // Text posts are Facebook-only by architecture — Instagram has no
        // text-only feed post (see includes/SocialPostEngine.php). Locked
        // here client-side; api/saveSocialPost.php and
        // api/publishSocialPostNow.php enforce the same rule server-side.
        $('#platformInstagram').prop('checked', false).prop('disabled', true);
        $('#platformFacebook').prop('disabled', !hasPage).prop('checked', hasPage);
        $('#noFacebookPageHint').text('This account has no linked Facebook Page — a text post needs one.').toggleClass('d-none', hasPage);
        window.instagramComposerEditPlatforms = null;
        return;
    }

    $('#platformInstagram').prop('disabled', false);

    const canUseFacebook = hasPage && mediaType === 'image';
    $('#platformFacebook').prop('disabled', !canUseFacebook);

    if (!hasPage) {
        $('#noFacebookPageHint').text('This account has no linked Facebook Page.').removeClass('d-none');
    } else if (mediaType !== 'image') {
        $('#noFacebookPageHint').text('Facebook scheduling is only supported for image and text posts.').removeClass('d-none');
    } else {
        $('#noFacebookPageHint').addClass('d-none');
    }

    const pendingEditPlatforms = window.instagramComposerEditPlatforms;

    if (pendingEditPlatforms) {
        $('#platformInstagram').prop('checked', pendingEditPlatforms.indexOf('instagram') !== -1);
        $('#platformFacebook').prop('checked', canUseFacebook && pendingEditPlatforms.indexOf('facebook') !== -1);
        window.instagramComposerEditPlatforms = null;
    } else {
        $('#platformFacebook').prop('checked', canUseFacebook);
    }
}

function escapeComposerHtml(value) {
    return $('<div>').text(value == null ? '' : value).html();
}

function submitPublishNow() {
    const mediaType = $('#mediaType').val();
    const isText = mediaType === 'text';

    if (mediaType !== 'image' && !isText) {
        window.showToast && window.showToast('warning', 'Publish Now currently supports image and text posts only. Use Schedule Post for reels/carousels.');
        return;
    }

    const platforms = [];
    if (!isText && $('#platformInstagram').is(':checked')) platforms.push('instagram');
    if ($('#platformFacebook').is(':checked')) platforms.push('facebook');

    if (!platforms.length) {
        window.showToast && window.showToast('warning', 'Please select at least one platform.');
        return;
    }

    if (isText) {
        if (platforms.indexOf('instagram') !== -1) {
            window.showToast && window.showToast('warning', 'Text posts can only be published to Facebook.');
            return;
        }

        if (!$('#caption').val().trim()) {
            window.showToast && window.showToast('warning', 'Please enter the text for this post.');
            return;
        }
    } else {
        const hasExistingMedia = !!(
            currentPost &&
            currentPost.mediaUrls &&
            currentPost.mediaUrls.length &&
            currentPost.mediaType === mediaType
        );
        const error = validateInstagramPost(mediaType, hasExistingMedia);

        if (error) {
            window.showToast && window.showToast('warning', error);
            return;
        }

        if (!$('#mediaFiles')[0].files.length) {
            window.showToast && window.showToast('warning', 'Please upload an image to publish now.');
            return;
        }
    }

    const formData = new FormData(document.getElementById('instagramPostForm'));
    formData.set('csrfToken', CSRF_TOKEN);
    platforms.forEach(function(p) { formData.append('platforms[]', p); });

    const $btn = $('#publishNowBtn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Publishing...');
    $('#saveDraftBtn, #schedulePostBtn, #publishNowBtn').prop('disabled', true);
    $('#publishNowResults').addClass('d-none').empty();

    $.ajax({
        url: API_BASE + '/social-media/publishSocialPostNow.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function(res) {
            renderPublishNowResults(res);
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to publish now.');
        },
        complete: function() {
            $btn.text(originalText);
            $('#saveDraftBtn, #schedulePostBtn').prop('disabled', false);
            applyMediaTypeUi($('#mediaType').val());
        }
    });
}

function renderPublishNowResults(res) {
    const $panel = $('#publishNowResults');
    $panel.removeClass('d-none').empty();

    const result = res && res.data && res.data.result;

    if (!result) {
        $panel.append(`<div class="alert alert-danger mb-2">${escapeComposerHtml(res && res.message ? res.message : 'Publishing failed.')}</div>`);
        window.showToast && window.showToast('danger', res && res.message ? res.message : 'Publishing failed.');
        return;
    }

    const labels = { instagram: 'Instagram', facebook: 'Facebook' };

    Object.keys(result.platforms || {}).forEach(function(platform) {
        const platformResult = result.platforms[platform];
        const ok = !!platformResult.success;
        const icon = ok ? '✓' : '✕';
        const cls = ok ? 'alert-success' : 'alert-danger';
        const postIdLine = platformResult.postId ? ('<br>Post ID: ' + escapeComposerHtml(String(platformResult.postId))) : '';

        $panel.append(`
            <div class="alert ${cls} mb-2">
                <strong>${icon} ${labels[platform] || platform}</strong><br>
                ${escapeComposerHtml(platformResult.message || '')}${postIdLine}
            </div>
        `);
    });

    window.showToast && window.showToast(
        result.status === 'success' ? 'success' : (result.status === 'partial' ? 'warning' : 'danger'),
        res && res.message ? res.message : 'Publishing complete.'
    );
}

function previewSelectedMedia(files) {
    const $preview = $('#mediaPreview');
    $preview.empty();

    Array.from(files || []).forEach(function(file) {
        if (file.type.indexOf('video') === 0) {
            const url = URL.createObjectURL(file);
            $preview.append(`<video src="${url}" controls style="width:140px;height:140px;object-fit:cover;border-radius:8px;"></video>`);
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            $preview.append(`<img src="${e.target.result}" style="width:100px;height:100px;object-fit:cover;border-radius:8px;">`);
        };
        reader.readAsDataURL(file);
    });
}

function loadInstagramPostForEdit(postId) {
    $.getJSON(API_BASE + '/social-media/getSocialPosts.php')
        .done(function(res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', 'Unable to load the post.');
                return;
            }

            const post = (res.data.posts || []).find(p => String(p.id) === String(postId));

            if (!post) {
                window.showToast && window.showToast('danger', 'Instagram post not found.');
                return;
            }

            bindInstagramPostForEdit(post);
        })
        .fail(function() {
            window.showToast && window.showToast('danger', 'Unable to load the post.');
        });
}

function bindInstagramPostForEdit(post) {
    currentPost = post;

    $('#pageTitle').text('Edit Social Post');
    $('#breadcrumbCurrent').text('Edit Post');
    $('#postId').val(post.id);
    $('#mediaType').val(post.mediaType);
    applyMediaTypeUi(post.mediaType);
    $('#caption').val(post.caption || '').trigger('input');
    $('#existingMediaNote').removeClass('d-none');

    // Set after applyMediaTypeUi() above (whose own call to
    // updateFacebookPlatformAvailability() must NOT see this yet) so it's
    // only consumed once loadInstagramAccountsForClient() below finishes
    // loading accounts and calls updateFacebookPlatformAvailability() again
    // with the account's facebookPageId known.
    window.instagramComposerEditPlatforms = (post.platforms || 'instagram').split(',').map(function(p) { return p.trim(); });

    if (post.clientId) {
        $('#clientSelect').val(String(post.clientId));
        loadInstagramAccountsForClient(post.clientId, post.instagramAccountId);
    }

    if (post.scheduledAt) {
        $('#scheduledAt').val(post.scheduledAt.replace(' ', 'T').substring(0, 16));
    }

    const $preview = $('#mediaPreview');
    $preview.empty();
    (post.mediaUrls || []).forEach(function(url) {
        if (post.mediaType === 'reel') {
            $preview.append(`<video src="${url}" controls style="width:140px;height:140px;object-fit:cover;border-radius:8px;"></video>`);
        } else {
            $preview.append(`<img src="${url}" style="width:100px;height:100px;object-fit:cover;border-radius:8px;">`);
        }
    });

    const canEdit = ['draft', 'scheduled', 'failed'].indexOf(post.status) !== -1;

    if (!canEdit) {
        $('#postLockedAlert').removeClass('d-none');
        $('#saveDraftBtn, #schedulePostBtn').prop('disabled', true);
        $('#instagramPostForm :input').prop('disabled', true);
    }
}

function validateInstagramPost(mediaType, hasExistingMedia) {
    if (!$('#clientSelect').val()) {
        return 'Please select a client.';
    }

    if (!$('#accountSelect').val()) {
        return 'Please select an Instagram account for this client.';
    }

    if (mediaType === 'text') {
        if (!$('#caption').val().trim()) {
            return 'Please enter the text for this post.';
        }

        return '';
    }

    const files = $('#mediaFiles')[0].files;

    if (!files.length && !hasExistingMedia) {
        return 'Please upload media for this post.';
    }

    if (mediaType === 'carousel' && files.length && (files.length < 2 || files.length > 10)) {
        return 'A carousel post needs between 2 and 10 images.';
    }

    return '';
}

function submitInstagramPost(action) {
    const mediaType = $('#mediaType').val();
    const hasExistingMedia = !!(
        currentPost &&
        currentPost.mediaUrls &&
        currentPost.mediaUrls.length &&
        currentPost.mediaType === mediaType
    );
    const error = validateInstagramPost(mediaType, hasExistingMedia);

    if (error) {
        window.showToast && window.showToast('warning', error);
        return;
    }

    const platforms = [];
    if (mediaType !== 'text' && $('#platformInstagram').is(':checked')) platforms.push('instagram');
    if ($('#platformFacebook').is(':checked')) platforms.push('facebook');

    if (!platforms.length) {
        window.showToast && window.showToast('warning', 'Please select at least one platform.');
        return;
    }

    if (mediaType === 'text' && platforms.indexOf('instagram') !== -1) {
        window.showToast && window.showToast('warning', 'Text posts can only be published to Facebook.');
        return;
    }

    if (platforms.indexOf('facebook') !== -1 && mediaType !== 'image' && mediaType !== 'text') {
        window.showToast && window.showToast('warning', 'Facebook scheduling is only supported for image and text posts.');
        return;
    }

    if (action === 'schedule') {
        const scheduledAt = $('#scheduledAt').val();

        if (scheduledAt) {
            const chosen = new Date(scheduledAt);
            if (chosen.getTime() < Date.now() - 60000) {
                window.showToast && window.showToast('warning', 'Please choose a schedule time in the future.');
                return;
            }
        }
    }

    const formData = new FormData(document.getElementById('instagramPostForm'));
    formData.set('action', action);
    formData.set('csrfToken', CSRF_TOKEN);
    platforms.forEach(function(p) { formData.append('platforms[]', p); });

    const $btn = action === 'schedule' ? $('#schedulePostBtn') : $('#saveDraftBtn');
    const originalText = $btn.text();
    $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Saving...');
    $('#saveDraftBtn, #schedulePostBtn').prop('disabled', true);

    $.ajax({
        url: API_BASE + '/social-media/saveSocialPost.php',
        type: 'POST',
        headers: { 'X-CSRF-Token': CSRF_TOKEN },
        data: formData,
        dataType: 'json',
        processData: false,
        contentType: false,
        success: function(res) {
            window.showToast && window.showToast(
                res && res.success ? 'success' : 'danger',
                res && res.message ? res.message : 'Invalid server response.'
            );

            if (res && res.success) {
                window.location.href = 'social-posts';
            }
        },
        error: function() {
            window.showToast && window.showToast('danger', 'Unable to save Instagram post.');
        },
        complete: function() {
            $btn.text(originalText);
            $('#saveDraftBtn, #schedulePostBtn').prop('disabled', false);
        }
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
