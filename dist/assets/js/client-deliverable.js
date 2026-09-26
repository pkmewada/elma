$(function () {
    // ============================================================
    // STATE
    // ============================================================
    let currentData = [];
    let allPlatforms = [];
    let platformFeatureMap = {};
    let allSubFeatures = {};
    let filtersLoaded = false;
    let platformIdMap = {};        // platformName -> platformId
    let featureIdMap = {};         // platformId -> { featureName -> featureId }

    // ============================================================
    // HELPER FUNCTIONS (Single definition, extended for 11 platforms)
    // ============================================================
    function getPlatformIcon(platform) {
        const icons = {
            'Instagram': 'instagram-line',
            'LinkedIn': 'linkedin-box-line',
            'YouTube': 'youtube-line',
            'Telegram': 'telegram-line',
            'Facebook': 'facebook-circle-line',
            'Pinterest': 'pinterest-line',
            'GMB': 'google-line',
            'SEO': 'search-line',
            'Twitter': 'twitter-x-line',
            'Google Ads': 'google-ads-fill',
            'Videos': 'video-line'
        };
        return icons[platform] || 'service-line';
    }

    function getPlatformColor(index) {
        const colors = [
            '#F8E8F0', // Instagram
            '#E8F0FA', // LinkedIn
            '#FDE8E8', // YouTube
            '#E8F4FA', // Telegram
            '#E8F0FF', // Facebook
            '#FDE8EE', // Pinterest
            '#FDE8E5', // GMB
            '#E8EEF8', // SEO
            '#E8E6FF', // Twitter
            '#E0F7FA', // Google Ads
            '#FFF3E0'  // Videos
        ];
        return colors[index % colors.length];
    }

    // ============================================================
    // LOAD FILTERS
    // ============================================================
    function loadFilters(callback) {
        if (filtersLoaded) {
            if (callback) callback();
            return;
        }

        let completed = 0;
        let total = 3;

        function checkComplete() {
            completed++;
            if (completed >= total) {
                filtersLoaded = true;
                if (callback) callback();
            }
        }

        // Load Clients
        $.ajax({
            url: API_BASE + '/client/getClients.php',
            method: 'GET',
            success: function(response) {
                let options = '<option value="">All Clients</option>';
                if (response.data && response.data.length) {
                    response.data.forEach(function(client) {
                        options += `<option value="${client.id}">${client.fullName} (${client.clientCode})</option>`;
                    });
                }
                $('#clientFilter').html(options);
                checkComplete();
            },
            error: function() {
                showToast('Error loading clients', 'error');
                checkComplete();
            }
        });

        // Load Services (Platforms)
        $.ajax({
            url: API_BASE + '/deliverables/getDeliverableStructure.php',
            method: 'GET',
            success: function(response) {
                if (response.success) {
                    let platforms = response.data.platforms || [];
                    let options = '<option value="">All Services</option>';
                    platforms.forEach(function(platform) {
                        options += `<option value="${platform}">${platform}</option>`;
                    });
                    $('#serviceFilter').html(options);

                    // Store ID maps for later use
                    platformIdMap = response.data.platformIdMap || {};
                    featureIdMap = response.data.featureIdMap || {};
                }
                checkComplete();
            },
            error: function() {
                showToast('Error loading services', 'error');
                checkComplete();
            }
        });

        // Load Months: 2 past, current, and 4 future months
        let monthOptions = '';
        let currentDate = new Date();
        let currentYear = currentDate.getFullYear();
        let currentMonth = currentDate.getMonth();

        for (let i = -2; i <= 4; i++) {
            let d = new Date(currentYear, currentMonth + i, 1);
            let month = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
            let label = d.toLocaleString('default', { month: 'long', year: 'numeric' });
            monthOptions += `<option value="${month}">${label}</option>`;
        }

        $('#monthFilter').html(monthOptions);

        let defaultMonth = currentYear + '-' + String(currentMonth + 1).padStart(2, '0');
        $('#monthFilter').val(defaultMonth);
        checkComplete();
    }

    // ============================================================
    // LOAD DELIVERABLES
    // ============================================================
    function loadDeliverables() {
        let clientId = $('#clientFilter').val();
        let service = $('#serviceFilter').val();
        let month = $('#monthFilter').val();

        $('#deliverableTableContainer').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted">Loading deliverables...</p>
            </div>
        `);

        $.ajax({
            url: API_BASE + '/deliverables/getDeliverableStructure.php',
            method: 'GET',
            success: function(structResponse) {
                if (structResponse.success) {
                    allPlatforms = structResponse.data.platforms || [];
                    platformFeatureMap = structResponse.data.platformFeatureMap || {};
                    allSubFeatures = structResponse.data.subFeatures || {};

                    // Update ID maps (in case they changed)
                    platformIdMap = structResponse.data.platformIdMap || {};
                    featureIdMap = structResponse.data.featureIdMap || {};

                    // If service filter is set, filter platforms
                    if (service) {
                        allPlatforms = allPlatforms.filter(function(p) {
                            return p === service;
                        });
                    }

                    loadDeliverableData(clientId, month, service);
                } else {
                    showToast('Error loading structure', 'error');
                    $('#deliverableTableContainer').html(`<div class="text-center py-4 text-danger">Failed to load structure</div>`);
                }
            },
            error: function() {
                showToast('Error loading structure', 'error');
                $('#deliverableTableContainer').html(`<div class="text-center py-4 text-danger">Failed to load structure</div>`);
            }
        });
    }

    function loadDeliverableData(clientId, month, service) {
        $.ajax({
            url: API_BASE + '/deliverables/getDeliverablesPivot.php',
            method: 'GET',
            data: { clientId: clientId, month: month },
            success: function(response) {
                if (response.success) {
                    let data = response.data || [];

                    // If service filter is set, filter clients who have this service
                    if (service) {
                        data = data.filter(function(row) {
                            let hasService = false;
                            for (var key in row) {
                                if (key === service || key.startsWith(service + '_')) {
                                    var val = row[key];
                                    if (val !== '' && val !== null && val !== undefined && parseInt(val) > 0) {
                                        hasService = true;
                                        break;
                                    }
                                }
                            }
                            return hasService;
                        });
                    }

                    currentData = data;
                    renderPivotTable(currentData);
                    updateRecordCount();
                } else {
                    showToast(response.message || 'Error loading data', 'error');
                    $('#deliverableTableContainer').html(`<div class="text-center py-4 text-danger">${response.message || 'Failed to load data'}</div>`);
                }
            },
            error: function() {
                showToast('Error loading deliverables', 'error');
                $('#deliverableTableContainer').html(`<div class="text-center py-4 text-danger">Failed to load data</div>`);
            }
        });
    }

    // ============================================================
    // RENDER PIVOT TABLE (with platform permission check)
    // ============================================================
    function renderPivotTable(data) {
        if (!allPlatforms || allPlatforms.length === 0) {
            let service = $('#serviceFilter').val();
            let message = service ? `No data found for service "${service}"` : 'No platforms configured';
            $('#deliverableTableContainer').html(`
                <div class="text-center py-5">
                    <i class="ri-inbox-line fs-1 text-muted"></i>
                    <p class="mt-2 text-muted">${message}</p>
                </div>
            `);
            return;
        }

        if (!data || data.length === 0) {
            let service = $('#serviceFilter').val();
            let message = service ? `No clients found with the service "${service}"` : 'No data found';
            $('#deliverableTableContainer').html(`
                <div class="text-center py-5">
                    <i class="ri-inbox-line fs-1 text-muted"></i>
                    <p class="mt-2 text-muted">${message}</p>
                </div>
            `);
            return;
        }

        let html = '';
        let headerColumns = [];

        // Add Client columns
        headerColumns.push({ key: 'sno', label: '#', width: '50px', sticky: true, isEditable: false, platform: null });
        headerColumns.push({ key: 'clientName', label: 'Client Name', width: '200px', sticky: true, isEditable: false, platform: null });

        // Track platform boundaries for column spans
        let platformColumnRanges = {};
        let currentColIndex = 2;

        allPlatforms.forEach(function(platform) {
            let features = platformFeatureMap[platform] || [];
            let startIndex = currentColIndex;
            let colCount = 0;

            if (features.length === 0) {
                headerColumns.push({
                    key: platform,
                    label: 'Total',
                    width: '100px',
                    platform: platform,
                    isEditable: true,
                    isTotalColumn: true
                });
                colCount = 1;
            } else {
                features.forEach(function(feature) {
                    let subFeatures = allSubFeatures[feature] || [];

                    if (subFeatures.length === 0) {
                        headerColumns.push({
                            key: platform + '_' + feature,
                            label: feature,
                            width: '120px',
                            platform: platform,
                            feature: feature,
                            isEditable: true
                        });
                        colCount++;
                    } else {
                        subFeatures.forEach(function(subFeature) {
                            headerColumns.push({
                                key: platform + '_' + feature + '_' + subFeature,
                                label: subFeature,
                                width: '100px',
                                platform: platform,
                                feature: feature,
                                subFeature: subFeature,
                                isEditable: true
                            });
                            colCount++;
                        });
                    }
                });
            }

            platformColumnRanges[platform] = {
                start: startIndex,
                end: startIndex + colCount - 1,
                count: colCount
            };
            currentColIndex += colCount;
        });

        // --- Build Table Header ---
        html += `<thead class="table-light sticky-top">`;

        // Row 1: Platform headers with colored backgrounds
        html += `<tr>`;
        html += `<th style="min-width: 50px; position: sticky; left: 0; background: #f8f9fa; z-index: 20; border-bottom: 2px solid #dee2e6;"></th>`;
        html += `<th style="min-width: 200px; position: sticky; left: 50px; background: #f8f9fa; z-index: 20; border-bottom: 2px solid #dee2e6;"></th>`;

        allPlatforms.forEach(function(platform, index) {
            let range = platformColumnRanges[platform];
            let colspan = range ? range.count : 1;
            let platformColor = getPlatformColor(index);

            html += `<th colspan="${colspan}" 
                style="text-align: center; background: ${platformColor}; 
                        font-weight: 700; color: #000000; 
                        font-size: 13px; padding: 8px 4px;
                        border-left: 2px solid #000000 !important;
                        border-right: 2px solid #000000 !important;
                <i class="ri-${getPlatformIcon(platform)} me-1"></i> ${platform}
            </th>`;
        });
        html += `</tr>`;

        // Row 2: Feature/Sub-feature headers
        html += `<tr>`;
        html += `<th style="min-width: 50px; position: sticky; left: 0; background: #f8f9fa; z-index: 20; font-weight: 600; font-size: 12px;">#</th>`;
        html += `<th style="min-width: 200px; position: sticky; left: 50px; background: #f8f9fa; z-index: 20; font-weight: 600; font-size: 12px;">Client Name</th>`;

        allPlatforms.forEach(function(platform) {
            let features = platformFeatureMap[platform] || [];
            let platformColor = getPlatformColor(allPlatforms.indexOf(platform));

            if (features.length === 0) {
                html += `<th style="min-width: 100px; text-align: center; background: ${platformColor}20; 
                                  font-weight: 600; font-size: 12px; color: #495057;
                                  border-left: 2px solid ${platformColor};
                                  border-right: 2px solid ${platformColor};
                                  border-bottom: 2px solid ${platformColor};">
                    Total
                </th>`;
            } else {
                features.forEach(function(feature, fIndex) {
                    let subFeatures = allSubFeatures[feature] || [];
                    let isFirst = fIndex === 0;
                    let isLast = fIndex === features.length - 1;

                    if (subFeatures.length === 0) {
                        html += `<th style="min-width: 120px; text-align: center; background: ${platformColor}15; 
                                          font-weight: 600; font-size: 12px; color: #495057;
                                          border-left: ${isFirst ? '2px solid ' + platformColor : '1px solid #dee2e6'};
                                          border-right: ${isLast ? '2px solid ' + platformColor : '1px solid #dee2e6'};
                                          border-bottom: 2px solid ${platformColor};">
                            ${feature}
                        </th>`;
                    } else {
                        subFeatures.forEach(function(subFeature, sIndex) {
                            let isFirstSub = isFirst && sIndex === 0;
                            let isLastSub = isLast && sIndex === subFeatures.length - 1;

                            html += `<th style="min-width: 100px; text-align: center; background: ${platformColor}10; 
                                              font-weight: 500; font-size: 12px; color: #495057;
                                              border-left: ${isFirstSub ? '2px solid ' + platformColor : '1px solid #dee2e6'};
                                              border-right: ${isLastSub ? '2px solid ' + platformColor : '1px solid #dee2e6'};
                                              border-bottom: 2px solid ${platformColor};">
                                ${subFeature}
                            </th>`;
                        });
                    }
                });
            }
        });
        html += `</tr>`;
        html += `</thead>`;

        // --- Build Table Body (with permission check) ---
        html += `<tbody>`;

        let sno = 1;
        data.forEach(function(row, rowIndex) {
            const allowedPlatforms = row.allowedPlatformIds || [];

            html += `<tr data-row="${rowIndex}" style="border-bottom: 1px solid #e9ecef;">`;
            html += `<td style="position: sticky; left: 0; background: white; z-index: 5; text-align: center; font-weight: 600;">${sno}</td>`;
            html += `<td style="position: sticky; left: 50px; background: white; z-index: 5; font-weight: 500;">${row.clientName || '-'}</td>`;

            for (let i = 2; i < headerColumns.length; i++) {
                let col = headerColumns[i];
                let value = row[col.key] !== undefined && row[col.key] !== null ? row[col.key] : '';
                let platformColor = col.platform ? getPlatformColor(allPlatforms.indexOf(col.platform)) : '#f8f9fa';

                let range = col.platform ? platformColumnRanges[col.platform] : null;
                let isFirst = range && i === range.start;
                let isLast = range && i === range.end;

                if (col.isEditable) {
                    // ---- Permission Check ----
                    let isAllowed = true;
                    if (col.platform) {
                        let platformId = platformIdMap[col.platform] || 0;
                        isAllowed = allowedPlatforms.includes(platformId);
                    }

                    let disabledAttr = isAllowed ? '' : 'disabled';
                    let inputStyle = isAllowed
                        ? 'background: transparent; border: none;'
                        : 'background: #e9ecef; border: 1px solid #dee2e6; cursor: not-allowed;';

                    html += `<td style="text-align: center; padding: 2px;
                                        border-left: ${isFirst ? '2px solid ' + platformColor : '1px solid #dee2e6'};
                                        border-right: ${isLast ? '2px solid ' + platformColor : '1px solid #dee2e6'};
                                        background: ${rowIndex % 2 === 0 ? '#ffffff' : '#f8f9fa'};">
                        <input type="number" class="form-control form-control-sm editable-cell" 
                               value="${value}" min="0" 
                               style="width: 100%; text-align: center; ${inputStyle} outline: none;"
                               data-row="${rowIndex}" data-key="${col.key}"
                               ${disabledAttr}>
                    </td>`;
                } else {
                    html += `<td style="text-align: center; font-weight: 500;">${value}</td>`;
                }
            }

            html += `</tr>`;
            sno++;
        });

        html += `</tbody>`;

        // Table wrapper (unchanged)
        let tableHtml = `
            <style>
                .deliverable-table-wrapper {
                    max-height: 600px;
                    overflow: auto;
                    position: relative;
                }
                .deliverable-table-wrapper table {
                    min-width: 100%;
                    font-size: 13px;
                    border-collapse: collapse;
                }
                .deliverable-table-wrapper table th,
                .deliverable-table-wrapper table td {
                    white-space: nowrap;
                    padding: 4px 6px;
                }
                .deliverable-table-wrapper table tbody tr:hover {
                    background: #e8f5e9 !important;
                }
                .deliverable-table-wrapper table tbody tr:hover td {
                    background: #e8f5e9 !important;
                }
                .deliverable-table-wrapper table tbody tr:nth-child(even) td {
                    background: #f8f9fa;
                }
                .deliverable-table-wrapper table tbody tr:nth-child(even) td:first-child,
                .deliverable-table-wrapper table tbody tr:nth-child(even) td:nth-child(2) {
                    background: #f8f9fa;
                }
                .deliverable-table-wrapper table tbody tr:hover td:first-child,
                .deliverable-table-wrapper table tbody tr:hover td:nth-child(2) {
                    background: #e8f5e9 !important;
                }
                .platform-label {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 4px;
                    color: white;
                    font-weight: 600;
                    font-size: 11px;
                }
                .deliverable-table-wrapper::-webkit-scrollbar {
                    height: 10px;
                    width: 10px;
                }
                .deliverable-table-wrapper::-webkit-scrollbar-track {
                    background: #f1f1f1;
                    border-radius: 10px;
                }
                .deliverable-table-wrapper::-webkit-scrollbar-thumb {
                    background: #c1c7cd;
                    border-radius: 10px;
                }
                .deliverable-table-wrapper::-webkit-scrollbar-thumb:hover {
                    background: #a0a7ad;
                }
                .scroll-indicator {
                    display: ${headerColumns.length > 10 ? 'block' : 'none'};
                    padding: 4px 8px;
                    background: #fff3cd;
                    border-bottom: 1px solid #ffc107;
                    font-size: 12px;
                    color: #856404;
                    text-align: center;
                }
                .scroll-indicator i {
                    margin-right: 4px;
                }
                .editable-cell:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }
            </style>
            ${headerColumns.length > 10 ? `
            <div class="scroll-indicator">
                <i class="ri-arrow-left-right-line"></i> 
              Press Shift + Scroll horizontally to see all columns
                <i class="ri-arrow-left-right-line ms-1"></i>
            </div>
            ` : ''}
            <div class="deliverable-table-wrapper">
                <table class="table table-bordered mb-0" id="deliverableTable">
                    ${html}
                </table>
            </div>
            <div class="p-2 d-flex justify-content-between align-items-center bg-light border-top">
                <span class="text-muted small">Total: ${data ? data.length : 0} clients | ${allPlatforms.length} platforms</span>
                <span class="text-muted small"><i class="ri-information-line me-1"></i> Click any number to edit, auto-saves on Enter</span>
            </div>
        `;

        $('#deliverableTableContainer').html(tableHtml);
        bindCellEvents();
    }

    // ============================================================
    // BIND CELL EVENTS
    // ============================================================
    function bindCellEvents() {
        $(document).off('blur.deliverable', '.editable-cell');
        $(document).off('keydown.deliverable', '.editable-cell');
        $(document).off('focus.deliverable', '.editable-cell');

        // Only bind on non-disabled cells
        $(document).on('blur.deliverable', '.editable-cell:not(:disabled)', function() {
            saveSingleCell($(this));
        });

        $(document).on('keydown.deliverable', '.editable-cell:not(:disabled)', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                $(this).blur();
            }
            if (e.key === 'Escape') {
                e.preventDefault();
                let row = $(this).data('row');
                let key = $(this).data('key');
                let originalValue = currentData[row] ? currentData[row][key] : '';
                $(this).val(originalValue || '');
                $(this).blur();
            }
        });

        $(document).on('focus.deliverable', '.editable-cell:not(:disabled)', function() {
            $(this).closest('td').css('background', '#fff3cd');
            $(this).select();
        });

        $(document).on('blur.deliverable', '.editable-cell:not(:disabled)', function() {
            $(this).closest('td').css('background', '');
        });
    }

    // ============================================================
    // SAVE SINGLE CELL (with disabled check)
    // ============================================================
    function saveSingleCell($input) {
        // Skip if disabled
        if ($input.prop('disabled')) {
            return;
        }

        let row = $input.data('row');
        let key = $input.data('key');
        let value = $input.val().trim();
        let $td = $input.closest('td');

        if (value === '' || value === null || value === undefined) {
            $td.css('background', '');
            return;
        }

        if (isNaN(value)) {
            showToast('Please enter a valid number', 'warning');
            let originalValue = currentData[row] ? currentData[row][key] : '';
            $input.val(originalValue || '');
            $td.css('background', '');
            return;
        }

        let parsedValue = parseInt(value);

        if (parsedValue < 0) {
            showToast('Please enter a positive number', 'warning');
            let originalValue = currentData[row] ? currentData[row][key] : '';
            $input.val(originalValue || '');
            $td.css('background', '');
            return;
        }

        let originalValue = currentData[row] ? currentData[row][key] : '';

        if (originalValue !== '' && parseInt(originalValue) === parsedValue) {
            $td.css('background', '');
            return;
        }

        if (parsedValue === 0 && (originalValue === '' || originalValue === null || originalValue === undefined)) {
            $td.css('background', '');
            $input.val('');
            return;
        }

        let clientMasterId = currentData[row] ? currentData[row].clientMasterId : 0;

        if (!clientMasterId) {
            return;
        }

        if (currentData[row]) {
            currentData[row][key] = parsedValue;
        }

        $td.css('background', '#d4edda');

        // Extract platform and feature names from key
        let parts = key.split('_');
        let platformName = parts[0] || '';
        let featureName = parts[1] || '';

        // Look up IDs from the maps
        let platformId = platformIdMap[platformName] || 0;
        let featureId = 0;
        if (platformId && featureName) {
            if (featureIdMap[platformId] && featureIdMap[platformId][featureName]) {
                featureId = featureIdMap[platformId][featureName];
            }
        }

        let saveData = {
            clientMasterId: clientMasterId,
            plannedCount: parsedValue,
            month: $('#monthFilter').val()
        };

        if (platformId && featureId) {
            saveData.platformId = platformId;
            saveData.featureId = featureId;
        } else {
            saveData.platform = platformName;
            saveData.feature = featureName;
        }

        $.ajax({
            url: API_BASE + '/deliverables/saveDeliverableCell.php',
            method: 'POST',
            data: JSON.stringify(saveData),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    $td.css('background', '#d4edda');
                    setTimeout(function() { $td.css('background', ''); }, 800);
                } else {
                    showToast(response.message || 'Failed to save', 'error');
                    $td.css('background', '#f8d7da');
                    setTimeout(function() { $td.css('background', ''); }, 2000);
                    if (currentData[row]) {
                        $input.val(currentData[row][key] || '');
                    }
                }
            },
            error: function() {
                showToast('Error saving data', 'error');
                $td.css('background', '#f8d7da');
                setTimeout(function() { $td.css('background', ''); }, 2000);
                if (currentData[row]) {
                    $input.val(currentData[row][key] || '');
                }
            }
        });
    }

    // ============================================================
    // UPDATE RECORD COUNT
    // ============================================================
    function updateRecordCount() {
        let count = currentData ? currentData.length : 0;
        $('#recordCount').text(count + ' clients');
    }

    // ============================================================
    // FILTER CHANGES
    // ============================================================
    $('#clientFilter, #serviceFilter, #monthFilter').on('change', function() {
        loadDeliverables();
    });

    // ============================================================
    // REFRESH
    // ============================================================
    $('#refreshBtn').on('click', function() {
        loadDeliverables();
        showToast('Refreshed!', 'info');
    });

    // ============================================================
    // TOAST NOTIFICATION
    // ============================================================
    function showToast(message, type = 'success') {
        Swal.fire({
            icon: type,
            title: message,
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    // ============================================================
    // INITIAL LOAD
    // ============================================================
    loadFilters(function() {
        loadDeliverables();
    });
});