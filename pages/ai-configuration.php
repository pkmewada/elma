<?php
/*
|--------------------------------------------------------------------------
| AI Configuration
|--------------------------------------------------------------------------
|
| Admin UI over the same CaptionGeneratorFactory/AiCaptionConfig layer
| Caption Area already uses — no parallel config system. Provider/model/
| host are editable and persisted (encrypted at rest for the API key, via
| the existing includes/Crypto.php) so a real deployment never needs a
| code/env edit to switch providers. The API key is never sent back to the
| browser once saved — only whether one is configured.
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
                <h1 class="page-title fw-medium fs-18 mb-2">AI Configuration</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">AI Configuration</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-12 col-xl-8">
                <div class="card custom-card">
                    <div class="card-header">
                        <h5 class="mb-0">Caption Area AI Provider</h5>
                        <span class="fs-12 text-muted">Saved here, encrypted at rest — no code or server edit needed to switch providers.</span>
                    </div>
                    <div class="card-body">

                        <div class="mb-3">
                            <label class="form-label" for="aiProvider">Provider</label>
                            <select class="form-select" id="aiProvider" style="max-width: 320px;">
                                <option value="anthropic">Anthropic</option>
                                <option value="ollama">Ollama (local)</option>
                            </select>
                        </div>

                        <div id="aiAnthropicFields">
                            <div class="mb-3">
                                <label class="form-label" for="aiAnthropicApiKey">API Key</label>
                                <input type="password" class="form-control" id="aiAnthropicApiKey" maxlength="255" autocomplete="new-password" placeholder="">
                                <div class="form-text" id="aiAnthropicKeyHint">Never displayed once saved. Leave blank to keep the existing key.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="aiAnthropicModel">Model</label>
                                <input type="text" class="form-control" id="aiAnthropicModel" maxlength="100" style="max-width: 320px;">
                            </div>
                        </div>

                        <div id="aiOllamaFields" class="d-none">
                            <div class="mb-3">
                                <label class="form-label" for="aiOllamaHost">Host</label>
                                <input type="text" class="form-control" id="aiOllamaHost" maxlength="255" style="max-width: 320px;" placeholder="http://localhost:11434">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="aiOllamaModel">Model</label>
                                <input type="text" class="form-control" id="aiOllamaModel" maxlength="100" style="max-width: 320px;" placeholder="llama3.2:3b">
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" id="aiSaveBtn">
                                <i class="ri-save-line me-1"></i> Save Configuration
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="aiTestBtn">
                                <i class="ri-pulse-line me-1"></i> Test Connection
                            </button>
                        </div>

                        <div class="mt-4">
                            <h6 class="fs-13 text-muted text-uppercase mb-2">Status</h6>
                            <div id="aiStatus" class="fs-14 text-muted">Not tested yet.</div>
                        </div>

                        <hr class="my-4">
                        <p class="fs-13 text-muted mb-0">
                            Environment variables (<code>MODLUS_AI_CAPTION_PROVIDER</code>, <code>ANTHROPIC_API_KEY</code>,
                            <code>MODLUS_OLLAMA_HOST</code>, <code>MODLUS_OLLAMA_MODEL</code>) are still used automatically
                            whenever a value isn't set here — this page is additive, not a replacement requirement.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    let hasAnthropicApiKey = false;

    function showProviderFields(provider) {
        $('#aiAnthropicFields').toggleClass('d-none', provider !== 'anthropic');
        $('#aiOllamaFields').toggleClass('d-none', provider !== 'ollama');
    }

    function renderStatus(type, message) {
        const $status = $('#aiStatus');
        $status.removeClass('text-muted text-success text-danger');
        if (type === 'success') {
            $status.addClass('text-success').html('<i class="ri-checkbox-circle-fill me-1"></i>' + escapeAiHtml(message));
        } else if (type === 'danger') {
            $status.addClass('text-danger').html('<i class="ri-close-circle-fill me-1"></i>' + escapeAiHtml(message));
        } else {
            $status.addClass('text-muted').text(message);
        }
    }

    function escapeAiHtml(value) {
        return $('<div>').text(value == null ? '' : value).html();
    }

    function loadConfig() {
        $.getJSON('api/social-content-caption/get-ai-config.php')
            .done(function (res) {
                if (!res || !res.success) {
                    window.showToast && window.showToast('danger', (res && res.message) || 'Unable to load AI configuration.');
                    return;
                }
                const c = res.data;
                $('#aiProvider').val(c.provider || 'anthropic');
                showProviderFields(c.provider || 'anthropic');

                $('#aiAnthropicModel').val(c.anthropicModel || '');
                hasAnthropicApiKey = !!c.hasAnthropicApiKey;
                $('#aiAnthropicApiKey').val('').attr('placeholder', hasAnthropicApiKey ? 'Saved — leave blank to keep current key' : '');

                $('#aiOllamaHost').val(c.ollamaHost || '');
                $('#aiOllamaModel').val(c.ollamaModel || '');
            })
            .fail(function () {
                window.showToast && window.showToast('danger', 'Network error while loading AI configuration.');
            });
    }

    $('#aiProvider').on('change', function () {
        showProviderFields($(this).val());
    });

    $('#aiSaveBtn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;

        const provider = $('#aiProvider').val();
        const payload = {
            provider: provider,
            anthropicApiKey: $('#aiAnthropicApiKey').val(),
            anthropicModel: $('#aiAnthropicModel').val().trim(),
            ollamaHost: $('#aiOllamaHost').val().trim(),
            ollamaModel: $('#aiOllamaModel').val().trim()
        };

        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Saving...');

        $.ajax({
            url: 'api/social-content-caption/save-ai-config.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify(payload),
            dataType: 'json'
        }).done(function (res) {
            if (!res || !res.success) {
                window.showToast && window.showToast('danger', (res && res.message) || 'Failed to save AI configuration.');
                return;
            }
            window.showToast && window.showToast('success', res.message || 'Saved.');
            renderStatus('muted', 'Not tested yet.');
            loadConfig();
        }).fail(function () {
            window.showToast && window.showToast('danger', 'Network error while saving AI configuration.');
        }).always(function () {
            $btn.prop('disabled', false).html(originalHtml);
        });
    });

    $('#aiTestBtn').on('click', function () {
        const $btn = $(this);
        if ($btn.prop('disabled')) return;

        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Testing...');
        renderStatus('muted', 'Testing…');

        $.ajax({
            url: 'api/social-content-caption/test-connection.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({}),
            dataType: 'json'
        }).done(function (res) {
            const ok = !!(res && res.success);
            renderStatus(ok ? 'success' : 'danger', (res && res.message) || (ok ? 'Connected.' : 'Connection test failed.'));
        }).fail(function () {
            renderStatus('danger', 'Network error while testing the connection.');
        }).always(function () {
            $btn.prop('disabled', false).html(originalHtml);
        });
    });

    loadConfig();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
