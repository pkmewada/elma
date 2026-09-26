<?php
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
    /* one card per platform, arranged in a responsive grid (see the
       col-xl-4/col-lg-4/col-md-6/col-12 wrapper rendered per card) */
    .sms-platform-card {
        border: 1px solid var(--default-border);
        border-radius: 12px;
        padding: 0.65rem 0.75rem;
        /* intentionally no height:100% — natural height per card, not
           forced equal across a row (a 2-feature platform stays short) */
    }

    .sms-platform-head {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        font-weight: 600;
        font-size: 0.85rem;
        color: var(--default-text-color);
        margin-bottom: 0.35rem;
        padding-bottom: 0.35rem;
        border-bottom: 1px solid var(--default-border);
    }

    .sms-platform-head i {
        font-size: 1rem;
        color: var(--primary-color);
    }

    .sms-feature-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.3rem 0.1rem;
        border-bottom: 1px solid var(--default-border);
        font-size: 0.8rem;
    }

    .sms-feature-row:last-child {
        border-bottom: none;
    }

    .sms-feature-row span {
        min-width: 0;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sms-feature-row .form-check {
        flex-shrink: 0;
    }

    .sms-rule-option {
        padding: 0.6rem 0.9rem;
        border: 1px solid var(--default-border);
        border-radius: 10px;
        margin-bottom: 0.5rem;
        cursor: pointer;
    }

    .sms-rule-option label {
        cursor: pointer;
        margin-bottom: 0;
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Social Media Setup</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Setup</li>
                    <li class="breadcrumb-item active" aria-current="page">Social Media Setup</li>
                </ol>
            </div>
        </div>

        <div class="row g-4">

            <!-- ===================== SECTION 1: FEATURE CONFIGURATION ===================== -->
            <div class="col-12">
                <div class="card custom-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Social Media Feature Configuration</h6>
                            <div class="text-muted fs-12 mt-1">
                                Only enabled Platform + Feature combinations participate in Social Media planning and Data Entry. A combination with no configuration is treated as disabled.
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm" id="smsSaveFeaturesBtn">
                            <i class="ri-save-line me-1"></i> Save Feature Configuration
                        </button>
                    </div>
                    <div class="card-body" id="smsFeatureContainer">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary spinner-border-sm" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <div class="text-muted fs-12 mt-2">Loading platforms &amp; features...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ===================== SECTION 2: CALENDAR PLANNING RULES ===================== -->
            <div class="col-xl-6">
                <div class="card custom-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Calendar Planning Rules</h6>
                        <button type="button" class="btn btn-primary btn-sm" id="smsSaveRulesBtn">
                            <i class="ri-save-line me-1"></i> Save Rules
                        </button>
                    </div>
                    <div class="card-body">
                        <label class="form-label fw-semibold d-block mb-2">Exclude Weekend (Sat/Sun)</label>

                        <div class="sms-rule-option">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="smsExcludeWeekend" id="smsWeekendYes" value="1">
                                <label class="form-check-label" for="smsWeekendYes">
                                    <b>Yes</b> — Saturday and Sunday are not valid planning dates
                                </label>
                            </div>
                        </div>

                        <div class="sms-rule-option mb-0">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="smsExcludeWeekend" id="smsWeekendNo" value="0">
                                <label class="form-check-label" for="smsWeekendNo">
                                    <b>No</b> — weekends may be used for planning
                                </label>
                            </div>
                        </div>

                        <div class="text-muted fs-12 mt-3">
                            <i class="ri-information-line me-1"></i>
                            Active holidays (<code>eventholidaymaster</code>) are always excluded from generated calendar dates — this is not configurable here.
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(function () {

    function esc(str) {
        return $('<div>').text(str == null ? '' : String(str)).html();
    }

    function notify(type, message) {
        if (window.showToast) window.showToast(type, message);
    }

    // ------------------------------------------------------------------
    // SECTION 1: feature configuration
    // ------------------------------------------------------------------
    function renderFeatureConfig(platforms) {
        const container = $('#smsFeatureContainer');

        if (!platforms || !platforms.length) {
            container.html('<div class="text-muted text-center py-3">No platforms/features found.</div>');
            return;
        }

        // one card per platform, 3-up on desktop, 2-up on tablet, 1-up on
        // mobile — every platform/feature the API returns is rendered,
        // regardless of its current enabled state
        let html = '<div class="row g-3">';
        platforms.forEach(function (platform) {
            html += `
                <div class="col-xl-4 col-lg-4 col-md-6 col-12">
                    <div class="sms-platform-card">
                        <div class="sms-platform-head">
                            <i class="${esc(platform.icon || 'ri-apps-line')}"></i>
                            ${esc(platform.platformName)}
                        </div>
            `;

            (platform.features || []).forEach(function (feature) {
                html += `
                        <div class="sms-feature-row">
                            <span title="${esc(feature.featureName)}">${esc(feature.featureName)}</span>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input sms-feature-toggle" type="checkbox"
                                       data-platform-id="${platform.platformId}"
                                       data-feature-id="${feature.featureId}"
                                       ${feature.isEnabled ? 'checked' : ''}>
                            </div>
                        </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        });
        html += '</div>';

        container.html(html);
    }

    function saveFeatureConfig() {
        const items = [];
        $('.sms-feature-toggle').each(function () {
            items.push({
                platformId: Number($(this).data('platform-id')),
                featureId: Number($(this).data('feature-id')),
                isEnabled: $(this).is(':checked') ? 1 : 0
            });
        });

        if (!items.length) {
            notify('warning', 'Nothing to save.');
            return;
        }

        const $btn = $('#smsSaveFeaturesBtn');
        $btn.prop('disabled', true);

        $.ajax({
            url: 'api/social-media-setup/save-feature-config.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({ items: items }),
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to save feature configuration.');
                return;
            }
            notify('success', 'Feature configuration saved.');
        }, function () {
            notify('danger', 'Network error while saving feature configuration.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    // ------------------------------------------------------------------
    // SECTION 2: planning rules
    // ------------------------------------------------------------------
    function renderPlanningRules(rules) {
        const excludeWeekend = rules && Number(rules.excludeWeekend) === 0 ? '0' : '1';
        $('input[name="smsExcludeWeekend"][value="' + excludeWeekend + '"]').prop('checked', true);
    }

    function savePlanningRules() {
        const excludeWeekend = Number($('input[name="smsExcludeWeekend"]:checked').val());
        const $btn = $('#smsSaveRulesBtn');
        $btn.prop('disabled', true);

        $.ajax({
            url: 'api/social-media-setup/save-planning-rules.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({ excludeWeekend: excludeWeekend }),
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to save planning rules.');
                return;
            }
            notify('success', 'Planning rules saved.');
        }, function () {
            notify('danger', 'Network error while saving planning rules.');
        }).always(function () {
            $btn.prop('disabled', false);
        });
    }

    // ------------------------------------------------------------------
    // LOAD
    // ------------------------------------------------------------------
    function loadConfig() {
        $.ajax({ url: 'api/social-media-setup/get-config.php', dataType: 'json' }).then(function (res) {
            if (!res || !res.success) {
                notify('danger', (res && res.message) || 'Failed to load Social Media Setup configuration.');
                $('#smsFeatureContainer').html('<div class="text-danger text-center py-3">Failed to load configuration.</div>');
                return;
            }
            renderFeatureConfig(res.data.featureConfig);
            renderPlanningRules(res.data.planningRules);
        }, function () {
            notify('danger', 'Network error while loading Social Media Setup configuration.');
            $('#smsFeatureContainer').html('<div class="text-danger text-center py-3">Network error.</div>');
        });
    }

    $('#smsSaveFeaturesBtn').on('click', saveFeatureConfig);
    $('#smsSaveRulesBtn').on('click', savePlanningRules);

    loadConfig();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
