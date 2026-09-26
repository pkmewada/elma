<?php
include __DIR__ . "/../includes/auth.php";
include __DIR__ . "/../includes/db.php";
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<style>

.activity-table td{
    vertical-align:top;
}

.activity-table .user-col{
    white-space:nowrap;
}

.activity-table .month-col{
    white-space:nowrap;
}

#activityLogModal .modal-body{
    max-height:70vh;
    overflow-y:auto;
}

.activity-badge{
    padding:3px 10px;
    border-radius:20px;
    font-size:11px;
    font-weight:600;
    white-space:nowrap;
    display:inline-block;
}

.activity-badge.created{
    background:#dbeafe;
    color:#1d4ed8;
}

.activity-badge.updated{
    background:#fef3c7;
    color:#b45309;
}

.activity-badge.deleted{
    background:#fee2e2;
    color:#dc2626;
}

.change-item{
    padding:4px 8px;
    background:#f8fafc;
    border-radius:6px;
    margin-bottom:4px;
    font-size:12px;
    line-height:1.6;
}

.change-item:last-child{
    margin-bottom:0;
}

.change-item .field-name{
    font-weight:600;
    color:#475569;
    margin-right:6px;
}

.change-item .old-value{
    color:#dc2626;
    text-decoration:line-through;
}

.change-item .new-value{
    color:#16a34a;
    font-weight:600;
}

.change-item .arrow{
    color:#94a3b8;
    margin:0 6px;
}

.date-chip{
    display:inline-block;
    background:#ffffff;
    border:1px solid currentColor;
    border-radius:20px;
    padding:1px 8px;
    margin:2px 4px 2px 0;
    font-weight:500;
    font-size:11px;
}

.activity-table{
    margin-bottom:0;
}

.activity-table td{
    vertical-align:top;
    padding:10px;
}

.activity-table th{
    white-space:nowrap;
    font-size:12px;
    font-weight:700;
}

.activity-table .user-col{
    white-space:nowrap;
}

.activity-table .month-col{
    white-space:nowrap;
}

#activityLogModal .modal-body{
    max-height:70vh;
    overflow-y:auto;
}
</style>
<div class="main-content app-content">
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Client Calendar Planner</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Client Calendar Planner</li>
                </ol>
            </div>
            <!-- <div>
                <button class="btn btn-primary btn-sm" id="exportCalendarBtn">
                    <i class="ri-download-line"></i> Export Calendar
                </button>
            </div> -->
        </div>

        <!-- Filter Section -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body py-2 px-3">
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <div class="d-flex align-items-center gap-2">
                                <label class="fw-medium text-muted small mb-0">Client:</label>
                                <select id="clientFilter" class="form-select form-select-sm" style="min-width: 160px; width: auto;">
                                    <option value="">All Clients</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="fw-medium text-muted small mb-0">Platform:</label>
                                <select id="platformFilter" class="form-select form-select-sm" style="min-width: 160px; width: auto;">
                                    <option value="">All Platforms</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <label class="fw-medium text-muted small mb-0">Month:</label>
                                <select id="monthFilter" class="form-select form-select-sm" style="min-width: 140px; width: auto;">
                                    <!-- Populated via JS -->
                                </select>
                            </div>
                            <div class="d-flex align-items-center gap-2 ms-auto">
                                <span class="text-muted small" id="recordCount">0 clients</span>
                                <button class="btn btn-outline-secondary btn-sm" id="refreshBtn" title="Refresh">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Client Cards Grid -->
        <div class="row g-4" id="clientCardContainer">
            <!-- Cards rendered by JavaScript -->
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading clients...</p>
            </div>
        </div>
    </div>
</div>

<!-- Calendar Planner Modal -->
<div class="modal fade" id="calendarPlannerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">
                    <i class="ri-calendar-event-line me-2 text-primary"></i> 
                    Plan Calendar · <span id="clientNameModal">Client</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Month selector -->
                <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
                    <label class="fw-semibold" style="color: #1e293b;">
                        <i class="ri-calendar-2-line me-1"></i> Month
                    </label>
                    <select class="form-select month-selector" id="monthSelector" style="max-width: 220px; border-radius: 40px; border: 1px solid #e2eaf5; padding: 0.4rem 1.2rem;"></select>
                    <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                        <i class="ri-checkbox-circle-fill text-primary me-1"></i> past, current & upcoming
                    </span>
                </div>

                <!-- dynamic platform features -->
                <div id="socialPlatformsContainer"></div>

                <!-- global hint -->
                <div class="mt-3 text-muted small d-flex gap-3 flex-wrap">
                    <span><i class="ri-circle-fill" style="font-size: 0.4rem;"></i> Click calendar to pick multiple dates</span>
                    <span><i class="ri-circle-fill" style="font-size: 0.4rem;"></i> Dates update per feature</span>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-outline-primary generate-dates-btn">
                    <i class="ri-magic-line me-1"></i> Generate Dates
                </button>
                <button type="button" class="btn btn-primary save-calendar-btn">
                    <i class="ri-save-line me-1"></i> Save Plan
                </button>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log Modal -->
<div class="modal fade" id="activityLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header border-bottom">
                <h5 class="modal-title">
                    <i class="ri-history-line me-2 text-primary"></i> 
                    Activity Log · <span id="logClientName">Client</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <label class="fw-semibold me-2">Month:</label>
                        <select id="logMonthFilter" class="form-select form-select-sm d-inline-block" style="width: auto;">
                            <option value="">All Months</option>
                        </select>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" id="refreshLogsBtn">
                        <i class="ri-refresh-line"></i> Refresh
                    </button>
                </div>
                <div id="logContainer">
                    <div class="text-center py-4 text-muted">
                        <i class="ri-inbox-line fs-2 d-block mb-2"></i>
                        No logs found
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/light.css">

<style>
    .flatpickr-input {
        background: white;
        border: 1px solid #dce5f0;
        border-radius: 40px;
        padding: 0.2rem 1rem;
        font-size: 0.8rem;
        width: 150px;
        color: #1f2a44;
        cursor: pointer;
    }
    .flatpickr-input:focus {
        border-color: #7da0f0;
        box-shadow: 0 0 0 2px rgba(37,99,235,0.15);
        outline: none;
    }
    .platform-header {
        font-weight: 600;
        color: #1e293b;
        font-size: 0.95rem;
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 0.5rem;
    }
    .platform-header i { font-size: 1.3rem; color: #2563eb; }
    .social-platform {
        background: #fafcff;
        border-radius: 20px;
        padding: 0.75rem 1rem;
        margin-top: 1rem;
        border: 1px solid #edf2f9;
    }
    .feature-row {
        display: grid;
        grid-template-columns: 180px 50px 70px 220px 1fr 50px;
        align-items: start;          /* Align all cells at the top */
        gap: 0.5rem 0.75rem;
        padding: 0.4rem 0.2rem;
        border-bottom: 1px solid #f0f5fe;
    }
    .feature-row:last-child {
        border-bottom: none;
    }
    .feature-name {
        background: #f1f6fd;
        padding: 0.15rem 0.9rem;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 500;
        color: #1e3a6f;
        min-width: 100px;
        text-align: center;
    }
    .count-badge {
        background: white;
        border: 1px solid #e2eaf5;
        padding: 0.1rem 0.9rem;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #0a1e3c;
        min-width: 40px;
        text-align: center;
    }
    .month-selector {
        background: #f8fafc;
        padding: 0.5rem 1.2rem;
        border-radius: 60px;
        border: 1px solid #eef2f6;
        font-weight: 500;
        font-size: 0.95rem;
        color: #0b1e33;
    }
    .month-selector:focus {
        border-color: #a5c1f0;
        box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
    }
    /*.modal-dialog { max-width: 1100px; }*/
    /*.modal-content {*/
    /*    border-radius: 32px;*/
    /*    border: none;*/
    /*    box-shadow: 0 40px 80px rgba(0,0,0,0.08);*/
    /*}*/
    /*.modal-header {*/
    /*    padding: 1.5rem 2rem 0.75rem;*/
    /*    border-radius: 32px 32px 0 0;*/
    /*}*/
    /*.modal-body { */
    /*    padding: 1.5rem 2rem 1.75rem; */
    /*    max-height: 70vh;*/
    /*    overflow-y: auto;*/
    /*}*/
    /*.modal-footer {*/
    /*    padding: 0.75rem 2rem 1.5rem;*/
    /*    border-radius: 0 0 32px 32px;*/
    /*}*/
    /*.modal-body::-webkit-scrollbar {*/
    /*    width: 6px;*/
    /*}*/
    /*.modal-body::-webkit-scrollbar-track {*/
    /*    background: #f1f1f1;*/
    /*    border-radius: 10px;*/
    /*}*/
    /*.modal-body::-webkit-scrollbar-thumb {*/
    /*    background: #d1d9e6;*/
    /*    border-radius: 10px;*/
    /*}*/
    /*.modal-body::-webkit-scrollbar-thumb:hover {*/
    /*    background: #b8c4d4;*/
    /*}*/
    /*@media (max-width: 768px) {*/
    /*    .modal-dialog { max-width: 98%; margin: 0.5rem; }*/
    /*    .modal-body { padding: 1rem; }*/
    /*    .feature-row { gap: 0.3rem; }*/
    /*    .flatpickr-input { width: 120px; }*/
    /*    .module-selected-dates { font-size: 0.7rem; padding: 0.1rem 0.6rem; }*/
    /*}*/

    .client-card {
        border: none;
        border-radius: 12px;
        background: #ffffff;
        transition: transform 0.15s ease, box-shadow 0.2s;
        height: 100%;
        padding: 1.5rem 1rem;
    }
    .client-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 36px rgba(0,0,0,0.06), 0 4px 12px rgba(0,0,0,0.03);
    }
    .client-avatar {
        width: 72px;
        height: 72px;
        object-fit: contain;
        margin: 0 auto 0.5rem;
        border-radius: 50%;
        background: #f1f6fd;
        padding: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        font-weight: 600;
        color: #2563eb;
    }
    .client-card-title {
        font-weight: 600;
        font-size: 1.1rem;
        letter-spacing: -0.01em;
        color: #1e293b;
        margin-bottom: 0.25rem;
    }
    .client-platform-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.7rem;
        border-radius: 20px;
        background: #f8fafc;
        border: 1px solid #e9edf4;
        color: #475569;
        display: inline-block;
        margin: 0.1rem;
    }
    .total-count {
        font-size: 0.85rem;
        font-weight: 500;
        color: #2563eb;
    }
    .btn-plan {
        border-radius: 40px;
        padding: 0.35rem 1.2rem;
        font-weight: 500;
        font-size: 0.85rem;
        border: 1px solid #e9edf4;
        background: white;
        color: #1e293b;
        transition: 0.15s;
    }
    .btn-plan:hover {
        background: #f1f5f9;
        border-color: #d0d8e3;
    }
    .btn-plan-primary {
        border-color: #dbe7f4;
        color: #2563eb;
    }
    .btn-plan-primary:hover {
        background: #eef4ff;
        border-color: #94b9ff;
    }
    
    /* Enhanced feature row - Table-like layout */
    .feature-row {
        display: grid;
        grid-template-columns: 180px 50px 70px 220px 1fr 50px;
        align-items: center;
        gap: 0.5rem 0.75rem;
        padding: 0.4rem 0.2rem;
        border-bottom: 1px solid #f0f5fe;
    }
    .feature-row:last-child { border-bottom: none; }
    
    .feature-name {
        background: #f1f6fd;
        padding: 0.15rem 0.6rem;
        border-radius: 40px;
        font-size: 0.78rem;
        font-weight: 500;
        color: #1e3a6f;
        text-align: center;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .count-badge {
        background: white;
        border: 1px solid #e2eaf5;
        padding: 0.1rem 0.6rem;
        border-radius: 40px;
        font-size: 0.8rem;
        font-weight: 600;
        color: #0a1e3c;
        text-align: center;
    }
    
    .edited-toggle {
        display: flex;
        align-items: center;
        gap: 0.2rem;
        font-size: 0.7rem;
        color: #475569;
        white-space: nowrap;
        justify-content: center;
    }
    .edited-toggle .form-check-input {
        margin-top: 0;
        width: 28px;
        height: 16px;
    }
    .edited-toggle .form-check-label {
        font-size: 0.7rem;
    }
    
    .flatpickr-input {
        background: white;
        border: 1px solid #dce5f0;
        border-radius: 40px;
        padding: 0.15rem 0.8rem;
        font-size: 0.75rem;
        width: 100%;
        min-width: 140px;
        color: #1f2a44;
        cursor: pointer;
    }
    
    .module-selected-dates {
        display: flex;
        flex-wrap: wrap;
        gap: 0.25rem;
        max-width: 100%;
    }

    .date-capsule {
        display: inline-block;
        font-size: 0.72rem;
        color: #1e293b;
        background: #ffffff;
        padding: 0.1rem 0.6rem;
        border-radius: 30px;
        border: 1px solid #000000;
        font-weight: 500;
        white-space: nowrap;
    }

    .date-capsule-removed {
        color: #dc2626;
        border-color: #dc2626;
        text-decoration: line-through;
    }

    .min-label {
        font-size: 0.65rem;
        color: #94a3b8;
        white-space: nowrap;
        text-align: center;
    }
    
    /* Responsive: stack on small screens */
    @media (max-width: 768px) {
        .feature-row {
            grid-template-columns: 1fr;
            gap: 0.3rem;
            padding: 0.6rem 0.2rem;
        }
        .feature-left {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.3rem;
            justify-content: flex-start;
        }
        .feature-right {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.3rem;
            justify-content: flex-start;
        }
        .flatpickr-input {
            width: 100%;
            min-width: auto;
        }
    }
</style>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="<?= ASSET_URL ?>/assets/libs/sweetalert2/sweetalert2.min.js"></script>

<script>
$(document).ready(function() {
    // ============================================
    // 1. GLOBAL VARIABLES
    // ============================================
    let currentClientId = null;
    let currentClientName = null;
    let pendingPlans = null;
    let pendingClientId = null;
    let currentLogClientId = null;

    // ============================================
    // 2. INITIALIZE MONTH SELECTORS
    // ============================================
    function initializeMonthSelectors() {
        const now = new Date();
        const months = [];
        for (let i = -2; i <= 4; i++) {
            const d = new Date(now.getFullYear(), now.getMonth() + i, 1);
            const yyyymm = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            const label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
            months.push({ value: yyyymm, label: label });
        }
        const monthOptions = months.map((m, idx) =>
            `<option value="${m.value}" ${m.value === currentYearMonth() ? 'selected' : ''}>${m.label}</option>`
        ).join('');
        $('#monthFilter, #monthSelector').html(monthOptions);
    }

    function currentYearMonth() {
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
    }
    initializeMonthSelectors();

    // ============================================
    // 3. HELPER FUNCTIONS
    // ============================================
    function formatDateShort(dateStr) {
        try {
            const d = new Date(dateStr + 'T00:00:00');
            if (isNaN(d)) return dateStr;
            return String(d.getDate()).padStart(2, '0') + ' ' + d.toLocaleString('en', { month: 'short' });
        } catch (e) {
            return dateStr;
        }
    }

    // ============================================
    // 4. BUILD PLATFORM HTML FOR MODAL
    // ============================================
    function buildPlatformsHTML(platforms, savedPlans) {
        if (!platforms || platforms.length === 0) {
            return '<div class="text-center py-3 text-muted">No deliverables found for this client</div>';
        }
        let html = '';
        platforms.forEach(p => {
            const iconClass = p.icon || 'ri-apps-line';
            const platformName = p.platform_name || 'Unknown';
            const platformId = p.platform_id;
            html += `<div class="social-platform" data-platform="${platformId}">`;
            html += `<div class="platform-header"><i class="${iconClass}"></i> ${platformName}</div>`;
            const features = p.features || [];
            if (features.length === 0) {
                html += `<div class="text-muted small p-2">No features for this platform</div>`;
            } else {
                // Add header row with labels (optional but helpful)
                html += `
                    <div class="feature-row" style="font-weight:600; color:#64748b; font-size:0.7rem; border-bottom:2px solid #e2e8f0; padding-bottom:0.3rem; margin-bottom:0.2rem;">
                        <span>Feature</span>
                        <span>Count</span>
                        <span>Edited</span>
                        <span>Date Picker</span>
                        <span>Selected Dates</span>
                        <span>Min</span>
                    </div>
                `;
                features.forEach((feature) => {
                    const plannedCount = feature.plannedCount || 0;
                    const planKey = platformId + '_' + feature.feature_id;
                    const plan = (savedPlans && savedPlans[planKey]) ? savedPlans[planKey] : null;
                    let selectedDates = plan ? plan.dates.slice().sort() : [];
                    let removedDates = plan ? (plan.removedDates || []).slice().sort() : [];
                    const isEdited = plan ? plan.isEdited : 'yes';
                    const dateStr = selectedDates.join(', ');
                    const displayId = `display_${platformId}_${feature.feature_id}`;
    
                    html += `
                        <div class="feature-row" data-feature-id="${feature.feature_id}"
                             data-platform-id="${platformId}" data-min-dates="${plannedCount}">
                            <span class="feature-name" title="${feature.feature_name}">${feature.feature_name}</span>
                            <span class="count-badge">${plannedCount}</span>
                            <div class="form-check form-switch edited-toggle">
                                <input class="form-check-input is-edited-toggle" type="checkbox"
                                       data-feature-id="${feature.feature_id}"
                                       data-platform="${platformId}"
                                       ${isEdited === 'yes' ? 'checked' : ''}>
                                <label class="form-check-label">Edited</label>
                            </div>
                            <input type="text" class="flatpickr-input"
                                   data-feature-id="${feature.feature_id}"
                                   data-platform="${platformId}"
                                   data-min-dates="${plannedCount}"
                                   value="${dateStr}"
                                   placeholder="Select dates">
                            <span class="module-selected-dates" id="${displayId}">
                                ${selectedDates.map(d => `<span class="date-capsule">${formatDateShort(d)}</span>`).join('')}
                                ${removedDates.map(d => `<span class="date-capsule date-capsule-removed">${formatDateShort(d)}</span>`).join('')}
                            </span>
                            <span class="min-label">${plannedCount}</span>
                        </div>
                    `;
                });
            }
            html += `</div>`;
        });
        return html;
    }

    // ============================================
    // 5. INITIALIZE FLATPICKR (with Toast)
    // ============================================
    function initFlatpickr() {
        document.querySelectorAll('.flatpickr-input').forEach(input => {
            if (input._flatpickr) {
                input._flatpickr.destroy();
            }
            const minDates = parseInt(input.dataset.minDates) || 0;
            const existingDates = input.value.split(',').map(d => d.trim()).filter(Boolean);
            flatpickr(input, {
                mode: 'multiple',
                dateFormat: 'Y-m-d',
                enableTime: false,
                minDate: new Date(),
                defaultDate: existingDates,
                onClose: function(selectedDates) {
                    const featureId = input.dataset.featureId;
                    const platformId = input.dataset.platform;
                    const displaySpan = document.getElementById(`display_${platformId}_${featureId}`);
                    if (selectedDates.length < minDates && selectedDates.length > 0) {
                        showToast(
                            `You need at least ${minDates} dates for this feature. Selected: ${selectedDates.length}`,
                            'warning'
                        );
                    }
                    if (displaySpan) {
                        const dates = selectedDates.slice().sort((a, b) => a - b).map(d => {
                            const day = String(d.getDate()).padStart(2, '0');
                            const month = d.toLocaleString('en', { month: 'short' });
                            return day + ' ' + month;
                        });
                        displaySpan.innerHTML = dates.length
                            ? dates.map(d => `<span class="date-capsule">${d}</span>`).join('')
                            : '—';
                    }
                },
                onChange: function(selectedDates) {
                    const featureId = input.dataset.featureId;
                    const platformId = input.dataset.platform;
                    const displaySpan = document.getElementById(`display_${platformId}_${featureId}`);
                    if (displaySpan) {
                        const dates = selectedDates.slice().sort((a, b) => a - b).map(d => {
                            const day = String(d.getDate()).padStart(2, '0');
                            const month = d.toLocaleString('en', { month: 'short' });
                            return day + ' ' + month;
                        });
                        displaySpan.innerHTML = dates.length
                            ? dates.map(d => `<span class="date-capsule">${d}</span>`).join('')
                            : '—';
                    }
                }
            });
        });
    }

    // ============================================
    // 6. TOAST NOTIFICATION
    // ============================================
    function showToast(message, type = 'warning') {
        const toastColors = {
            warning: '#ffc107',
            error: '#dc3545',
            success: '#198754',
            info: '#0dcaf0'
        };
        const toastId = 'toast-' + Date.now();
        const toastHtml = `
            <div id="${toastId}" style="
                position: fixed;
                top: 80px;
                right: 20px;
                z-index: 99999;
                background: white;
                border-left: 4px solid ${toastColors[type] || toastColors.warning};
                border-radius: 8px;
                padding: 16px 20px;
                box-shadow: 0 12px 40px rgba(0,0,0,0.15), 0 4px 12px rgba(0,0,0,0.05);
                max-width: 420px;
                font-size: 14px;
                color: #1e293b;
                transform: translateX(120%);
                transition: transform 0.3s ease;
                font-family: 'Inter', -apple-system, system-ui, sans-serif;
                display: flex;
                align-items: center;
                gap: 12px;
            ">
                <span style="
                    background: ${toastColors[type] || toastColors.warning};
                    border-radius: 50%;
                    width: 28px;
                    height: 28px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    color: white;
                    font-weight: 700;
                    flex-shrink: 0;
                    font-size: 16px;
                ">${type === 'warning' ? '⚠️' : type === 'error' ? '❌' : type === 'success' ? '✅' : 'ℹ️'}</span>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="
                    background: none;
                    border: none;
                    font-size: 18px;
                    color: #94a3b8;
                    cursor: pointer;
                    padding: 0 4px;
                    margin-left: auto;
                ">×</button>
            </div>
        `;
        document.querySelectorAll('[id^="toast-"]').forEach(el => el.remove());
        document.body.insertAdjacentHTML('beforeend', toastHtml);
        const toastEl = document.getElementById(toastId);
        requestAnimationFrame(() => {
            toastEl.style.transform = 'translateX(0)';
        });
        setTimeout(() => {
            if (toastEl) {
                toastEl.style.transform = 'translateX(120%)';
                setTimeout(() => toastEl.remove(), 400);
            }
        }, 4000);
    }

    // ============================================
    // 7. LOAD & RENDER CLIENTS
    // ============================================
    function loadClients() {
        const clientFilter = $('#clientFilter').val();
        const platformFilter = $('#platformFilter').val();
        // monthFilter is now YYYY-MM, but API expects index (0-based)
        // So we compute index: if value is e.g. "2026-08", we need to know how many months from current?
        // Actually the API uses month=0,1,2... which is the offset from current month.
        // We'll compute the offset from the selected YYYY-MM to current month.
        const selectedMonth = $('#monthFilter').val(); // YYYY-MM
        let monthIndex = 0;
        if (selectedMonth) {
            const now = new Date();
            const parts = selectedMonth.split('-');
            const selectedDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, 1);
            monthIndex = (selectedDate.getFullYear() - now.getFullYear()) * 12 + (selectedDate.getMonth() - now.getMonth());
        }
        // But we already set monthFilter options to YYYY-MM, the API expects month as integer index.
        // So we pass the index.
        $('#clientCardContainer').html(`
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading clients...</p>
            </div>
        `);
        $.ajax({
            url: 'api/deliverables/get-clients-calendar.php',
            type: 'GET',
            data: { client: clientFilter, platform: platformFilter, month: monthIndex },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderClients(response.data);
                    updateFilters(response.filters);
                } else {
                    showError(response.message || 'Failed to load clients');
                }
            },
            error: function(xhr) {
                showError('Network error. Please try again.');
                console.error('Error:', xhr.responseText);
            }
        });
    }

    function renderClients(clients) {
        const container = $('#clientCardContainer');
        if (!clients || clients.length === 0) {
            container.html(`
                <div class="col-12 text-center py-5">
                    <i class="ri-inbox-line" style="font-size: 48px; color: #ccc;"></i>
                    <p class="text-muted mt-3">No clients found matching your filters.</p>
                </div>
            `);
            $('#recordCount').text('0 clients');
            return;
        }
        let html = '';
        clients.forEach(client => {
            const clientName = client.client_name || 'Unknown';
            const initials = clientName.split(' ').map(n => n[0]).join('').toUpperCase();
            const totalDeliverables = client.total_deliverables || 0;
            const platformBadges = (client.platforms && client.platforms.length > 0)
                ? client.platforms.map(p =>
                    `<span class="client-platform-badge">
                        <i class="${p.icon || 'ri-apps-line'}"></i> ${p.platform_name}
                    </span>`
                ).join('')
                : '<span class="text-muted small">No platforms</span>';
            html += `
                <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
                    <div class="client-card d-flex flex-column align-items-center text-center">
                        <div class="client-avatar">${initials}</div>
                        <h6 class="client-card-title">${clientName}</h6>
                        <div class="mb-2">${platformBadges}</div>
                        <div class="total-count mb-2">Total: ${totalDeliverables} deliverables</div>
                        <div class="btn-group" role="group">
                            <button class="btn btn-plan btn-plan-primary open-calendar-btn"
                                    data-client="${clientName}"
                                    data-client-id="${client.id}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#calendarPlannerModal">
                                <i class="ri-calendar-event-line"></i> Create Plan
                            </button>
                            <button class="btn btn-plan view-details-btn" data-client-id="${client.id}">
                                <i class="ri-eye-line"></i> View Plan
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        container.html(html);
        $('#recordCount').text(`${clients.length} clients`);
    }

    // ============================================
    // 8. UPDATE FILTERS
    // ============================================
    function updateFilters(filters) {
        if (!filters) return;
        if (filters.clients) {
            const clientSelect = $('#clientFilter');
            const currentVal = clientSelect.val();
            clientSelect.html('<option value="">All Clients</option>' +
                filters.clients.map(c => `<option value="${c.id}">${c.client_name}</option>`).join(''));
            if (currentVal) clientSelect.val(currentVal);
        }
        if (filters.platforms) {
            const platformSelect = $('#platformFilter');
            const currentVal = platformSelect.val();
            platformSelect.html('<option value="">All Platforms</option>' +
                filters.platforms.map(p => `<option value="${p.id}">${p.platformName}</option>`).join(''));
            if (currentVal) platformSelect.val(currentVal);
        }
    }

    // ============================================
    // 9. SHOW ERROR
    // ============================================
    function showError(message) {
        $('#clientCardContainer').html(`
            <div class="col-12 text-center py-5">
                <i class="ri-error-warning-line" style="font-size: 48px; color: #dc3545;"></i>
                <p class="text-danger mt-3">${message}</p>
                <button class="btn btn-outline-primary btn-sm" onclick="loadClients()">
                    <i class="ri-refresh-line"></i> Retry
                </button>
            </div>
        `);
        $('#recordCount').text('0 clients');
    }

    // ============================================
    // 10. MODAL OPEN HANDLER
    // ============================================
    function loadModalPlan(clientId, month) {
        $('#socialPlatformsContainer').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);
        $.ajax({
            url: 'api/deliverables/get-client-calendar-plan.php',
            type: 'GET',
            data: { clientId: clientId, month: month },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#socialPlatformsContainer').html(buildPlatformsHTML(response.platforms, response.saved_plans));
                    initFlatpickr();
                } else {
                    showToast(response.message || 'Failed to load plan', 'error');
                }
            },
            error: function() {
                showToast('Network error loading plan', 'error');
            }
        });
    }

    $('#calendarPlannerModal').on('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        if (!button) return;
        currentClientId = button.getAttribute('data-client-id');
        currentClientName = button.getAttribute('data-client') || 'Client';
        $('#clientNameModal').text(currentClientName);
        $('#monthSelector').val($('#monthFilter').val());
        loadModalPlan(currentClientId, $('#monthSelector').val());
    });

    // Refresh the modal's data whenever the month inside it is changed
    $('#monthSelector').on('change', function() {
        if (currentClientId) {
            loadModalPlan(currentClientId, $(this).val());
        }
    });

    // ============================================
    // 11. SAVE CALENDAR PLAN (with validation)
    // ============================================
    $('.save-calendar-btn').on('click', function() {
        if (!currentClientId) {
            showToast('No client selected', 'error');
            return;
        }

        // Get the selected month in YYYY-MM format
        const month = $('#monthSelector').val(); // now YYYY-MM

        let plans = [];
        let allValid = true;
        let firstInvalidFeature = null;

        // Collect and validate each feature row
        document.querySelectorAll('.feature-row').forEach(row => {
            const input = row.querySelector('.flatpickr-input');
            if (input) {
                const minDates = parseInt(row.dataset.minDates) || 0;
                const dates = input.value.split(',').map(d => d.trim()).filter(Boolean);
                const count = dates.length;

                // Validate: must have at least minDates
                if (count < minDates) {
                    allValid = false;
                    firstInvalidFeature = row.querySelector('.feature-name')?.textContent || 'Unknown';
                }

                // Only add if dates exist
                if (count > 0) {
                    const isEditedToggle = row.querySelector('.is-edited-toggle');
                    const isEdited = (isEditedToggle && isEditedToggle.checked) ? 'yes' : 'no';
                    plans.push({
                        clientId: currentClientId,
                        platformId: parseInt(row.dataset.platformId),
                        featureId: parseInt(row.dataset.featureId),
                        dates: dates,
                        month: month,
                        isEdited: isEdited
                    });
                }
            }
        });

        if (!allValid) {
            showToast(`Minimum date requirement not met for "${firstInvalidFeature}". Please select enough dates.`, 'error');
            return; // Do NOT close modal, do NOT save
        }

        if (plans.length === 0) {
            showToast('No dates selected to save', 'warning');
            return;
        }

        // Store for deferred save after modal closes
        pendingPlans = plans;
        pendingClientId = currentClientId;

        // Close the modal (this triggers hidden.bs.modal)
        const modalElement = document.getElementById('calendarPlannerModal');
        const modalInstance = bootstrap.Modal.getInstance(modalElement);
        if (modalInstance) {
            modalInstance.hide();
        } else {
            $('#calendarPlannerModal').modal('hide');
        }
    });

    // ============================================
    // 11b. GENERATE DATES (preview only — never saves)
    // ============================================
    // Calls the existing api/deliverables/generate-calendar-plan.php
    // (SocialCalendarPlanningEngine::generatePlans()), then merges each
    // returned platformId+featureId group's finalDates into the matching
    // existing Flatpickr instance via setDate(..., true) — reusing the
    // instance's own onChange handler to redraw the date-capsule display,
    // instead of duplicating that rendering logic here. Nothing is
    // persisted; the existing "Save Plan" button remains the only write path.
    $('.generate-dates-btn').on('click', function () {
        if (!currentClientId) {
            showToast('No client selected', 'error');
            return;
        }

        const month = $('#monthSelector').val();
        if (!month) {
            showToast('Select a month first', 'error');
            return;
        }

        const $btn = $(this);
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Generating...');

        $.ajax({
            url: 'api/deliverables/generate-calendar-plan.php',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-Token': CSRF_TOKEN },
            data: JSON.stringify({ clientId: currentClientId, month: month }),
            dataType: 'json'
        }).then(function (res) {
            if (!res || !res.success) {
                showToast(res && res.message ? res.message : 'Failed to generate dates', 'error');
                return;
            }

            const plans = (res.data && res.data.plans) || [];
            let totalGenerated = 0;
            let anyCapped = false;
            let cappedPoolSize = 0;

            plans.forEach(function (plan) {
                const row = document.querySelector(
                    `.feature-row[data-platform-id="${plan.platformId}"][data-feature-id="${plan.featureId}"]`
                );
                if (!row) return; // this client's plan may include a feature not rendered in this modal

                totalGenerated += plan.generatedDates.length;

                // The backend caps its target at however many valid planning
                // dates the month actually has (targetCount = min(requiredCount,
                // validDatePool count)), so finalDates falling short of
                // requiredCount here means every valid date was used — expected,
                // successful behavior, not a shortfall to warn about.
                if (plan.finalDates.length < plan.requiredCount) {
                    anyCapped = true;
                    cappedPoolSize = plan.finalDates.length;
                }

                if (!plan.generatedDates.length) return; // nothing new for this slot — leave it untouched

                const input = row.querySelector('.flatpickr-input');
                if (input && input._flatpickr) {
                    // triggers the existing onChange handler, which redraws
                    // the module-selected-dates capsules — no duplicate
                    // rendering logic needed here
                    input._flatpickr.setDate(plan.finalDates, true);
                }
            });

            if (!plans.length) {
                showToast('No deliverables configured for this client/month.', 'info');
            } else if (totalGenerated === 0) {
                showToast('All required dates are already planned.', 'info');
            } else if (anyCapped) {
                showToast(`All ${cappedPoolSize} possible date${cappedPoolSize === 1 ? ' has' : 's have'} been selected for this month. Review below, then click Save Plan.`, 'success');
            } else {
                showToast(`Generated ${totalGenerated} new date${totalGenerated === 1 ? '' : 's'}. Review below, then click Save Plan.`, 'success');
            }
        }, function (xhr) {
            let message = 'Network error while generating dates.';
            try {
                const parsed = JSON.parse(xhr.responseText);
                if (parsed && parsed.message) message = parsed.message;
            } catch (e) { /* keep the generic message */ }
            showToast(message, 'error');
        }).always(function () {
            $btn.prop('disabled', false).html(originalHtml);
        });
    });

    // ============================================
    // 12. MODAL HIDDEN HANDLER (execute save)
    // ============================================
    $('#calendarPlannerModal').on('hidden.bs.modal', function() {
        // Force cleanup
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('body').css('overflow', '');
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());

        // Destroy flatpickr
        document.querySelectorAll('.flatpickr-input').forEach(input => {
            if (input._flatpickr) {
                input._flatpickr.destroy();
            }
        });

        // If pending plans exist, save
        if (pendingPlans && pendingClientId) {
            showToast('Saving calendar plan...', 'info');

            $.ajax({
                url: 'api/deliverables/save-calendar-plan.php',
                type: 'POST',
                contentType: 'application/json',
                headers: { 'X-CSRF-Token': CSRF_TOKEN },
                data: JSON.stringify({ client_id: pendingClientId, plans: pendingPlans }),
                success: function(response) {
                    if (response.success) {
                        showToast('Calendar plan saved successfully!', 'success');
                        loadClients(); // refresh
                    } else {
                        showToast(response.message || 'Failed to save plan', 'error');
                    }
                    pendingPlans = null;
                    pendingClientId = null;
                },
                error: function(xhr) {
                    console.error('Error:', xhr.responseText);
                    showToast('Network error. Please try again.', 'error');
                    pendingPlans = null;
                    pendingClientId = null;
                }
            });
        }
    });

    // ============================================
    // 13. EVENT LISTENERS
    // ============================================
    $('#clientFilter, #platformFilter, #monthFilter').on('change', loadClients);
    $('#refreshBtn').on('click', loadClients);
    $('#exportCalendarBtn').on('click', function() {
        showToast('Export functionality coming soon!', 'info');
    });
    
    
    // ============================================
    // VIEW CLIENT ACTIVITY LOGS
    // ============================================
    $(document).on('click', '.view-details-btn', function(e) {
        e.preventDefault();
        const clientId = $(this).data('client-id');
        const clientName = $(this).closest('.client-card').find('.client-card-title').text() || 'Client';
        openActivityLog(clientId, clientName);
    });
    
    function openActivityLog(clientId, clientName) {
        // Set client name in modal
        $('#logClientName').text(clientName);
        
        // Show modal
        $('#activityLogModal').modal('show');
        
        // Load logs
        loadActivityLogs(clientId);
    }
    
    function loadActivityLogs(clientId) {
        const month = $('#logMonthFilter').val();
        const container = $('#logContainer');
        container.html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading logs...</p>
            </div>
        `);
    
        $.ajax({
            url: 'api/deliverables/getCalendarLogs.php',
            type: 'GET',
            data: { clientId: clientId, month: month },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    renderLogs(response.data);
                } else {
                    container.html(`
                        <div class="alert alert-danger">${response.message || 'Failed to load logs'}</div>
                    `);
                }
            },
            error: function() {
                container.html(`
                    <div class="alert alert-danger">Network error. Please try again.</div>
                `);
            }
        });
    }

    function formatShortDate(dateStr) {
    const d = new Date(dateStr);

    return d.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: 'short'
    });
}

    function renderLogs(logs) {
        const container = $('#logContainer');
    
        if (!logs || logs.length === 0) {
            container.html(`
                <div class="text-center py-4 text-muted">
                    <i class="ri-inbox-line fs-2 d-block mb-2"></i>
                    No activity logs found for this client.
                </div>
            `);
            return;
        }
    
        let html = `
            <div class="table-responsive">
                <table class="table table-hover mb-0 activity-table">
                    <thead class="table-light">
                        <tr>
                            <th style="min-width:140px;">Date</th>
                            <th style="min-width:100px;">Action</th>
                            <th style="min-width:120px;">Platform</th>
                            <th style="min-width:120px;">Feature</th>
                            <th style="min-width:100px;">Month</th>
                            <th style="min-width:320px;">Changes</th>
                            <th style="min-width:120px;">User</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
    
        logs.forEach(log => {
    
            // ==================================================
            // ACTION BADGE
            // ==================================================
            let actionBadge = '';
    
            switch (log.action) {
                case 'INSERT':
                    actionBadge = '<span class="activity-badge created">Created</span>';
                    break;
    
                case 'DELETE':
                    actionBadge = '<span class="activity-badge deleted">Deleted</span>';
                    break;
    
                default:
                    actionBadge = '<span class="activity-badge updated">Updated</span>';
            }
    
            // ==================================================
            // CHANGE DETECTION
            // ==================================================
            const oldDates = Array.isArray(log.oldDates) ? log.oldDates : [];
            const newDates = Array.isArray(log.newDates) ? log.newDates : [];
    
            const addedDates = newDates.filter(d => !oldDates.includes(d));
            const removedDates = oldDates.filter(d => !newDates.includes(d));
    
            let changes = '';

            if (addedDates.length) {
                changes += `
                    <div class="change-item">
                        <span class="field-name text-success">Added:</span>
                        ${addedDates.map(date => `<span class="new-value date-chip">${formatShortDate(date)}</span>`).join('')}
                    </div>
                `;
            }

            if (removedDates.length) {
                changes += `
                    <div class="change-item">
                        <span class="field-name text-danger">Removed:</span>
                        ${removedDates.map(date => `<span class="old-value date-chip">${formatShortDate(date)}</span>`).join('')}
                    </div>
                `;
            }
    
            if (log.oldIsEdited !== log.newIsEdited) {
                changes += `
                    <div class="change-item">
                        <span class="field-name text-warning">Edited:</span>
                        <span class="old-value">${log.oldIsEdited || 'no'}</span>
                        <span class="arrow">→</span>
                        <span class="new-value">${log.newIsEdited}</span>
                    </div>
                `;
            }
    
            if (!changes) {
                changes = `
                    <span class="text-muted small">
                        No Changes
                    </span>
                `;
            }
    
            // ==================================================
            // MONTH FORMAT
            // ==================================================
            const formattedMonth = log.month
                ? new Date(log.month + '-01').toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                })
                : '-';
    
            html += `
                <tr>
                    <td style="white-space:nowrap;">
                        ${formatDate(log.createdAt)}
                    </td>
    
                    <td>
                        ${actionBadge}
                    </td>
    
                    <td>
                        ${log.platformName}
                    </td>
    
                    <td>
                        ${log.featureName}
                    </td>
    
                    <td class="text-nowrap">
                        ${formattedMonth}
                    </td>
    
                    <td>
                        ${changes}
                    </td>
    
                    <td style="white-space:nowrap;">
                        <i class="ri-user-line me-1 text-muted"></i>
                        ${log.userName}
                    </td>
                </tr>
            `;
        });
    
        html += `
                    </tbody>
                </table>
            </div>
        `;
    
        container.html(html);
    }
    
    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    // ============================================
    // 14. INITIAL LOAD
    // ============================================
    loadClients();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>