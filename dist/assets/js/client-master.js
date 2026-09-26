$(function () {
    // ============================================================
    // SERVICE CONFIGURATION
    // ============================================================
    const serviceConfig = {
        socialMedia: {
            label: 'Social Media (IG | FB)',
            fields: [
                { id: 'socialMediaUsername', label: 'Username', type: 'text' },
                { id: 'socialMediaPassword', label: 'Password', type: 'password' }
            ]
        },
        youtube: {
            label: 'YouTube',
            fields: [
                { 
                    id: 'youtubeGrantAccess', 
                    label: 'Grant Access to MQlus Team',
                    type: 'checkbox',
                    description: 'uday.work@gmail.com, social@mqlus.in'
                },
                { id: 'youtubeScreenshot', label: 'Upload Screenshot', type: 'file' }
            ]
        },
        pinterest: {
            label: 'Pinterest',
            fields: [
                { id: 'pinterestUsername', label: 'Username', type: 'text' },
                { id: 'pinterestPassword', label: 'Password', type: 'password' }
            ]
        },
        twitter: {
            label: 'Twitter (X)',
            fields: [
                { id: 'twitterUsername', label: 'Username', type: 'text' },
                { id: 'twitterPassword', label: 'Password', type: 'password' }
            ]
        },
        gmb: {
            label: 'Google My Business',
            fields: [
                { id: 'gmbUsername', label: 'Username', type: 'text' },
                { id: 'gmbPassword', label: 'Password', type: 'password' }
            ]
        },
        googleAds: {
            label: 'Google Ads',
            fields: [
                { id: 'googleAdsUsername', label: 'Google Account Email', type: 'email' },
                { id: 'googleAdsPassword', label: 'Google Account Password', type: 'password' }
            ]
        },
        metaAds: {
            label: 'Meta Ads (FB + IG)',
            fields: [
                { id: 'metaFbUsername', label: 'Facebook Username', type: 'text' },
                { id: 'metaFbPassword', label: 'Facebook Password', type: 'password' },
                { id: 'metaIgUsername', label: 'Instagram Username', type: 'text' },
                { id: 'metaIgPassword', label: 'Instagram Password', type: 'password' }
            ]
        },
        videos: {
            label: 'Videos',
            fields: [
                { id: 'videosNote', label: 'No credentials required', type: 'info' }
            ]
        }
    };

    // ============================================================
    // DATA TABLE INITIALIZATION
    // ============================================================
    const table = $('#clientMasterTable').DataTable({
        ajax: {
            url: API_BASE + '/client/getClientMaster.php',
            dataSrc: 'data'
        },
        columns: [
            {
                data: null,
                render: function (data, type, row, meta) {
                    return meta.row + 1;
                }
            },
            {
                data: null,
                render: function (data) {
                    return `
                        <div>
                            <strong>${data.fullName || '-'}</strong><br>
                            <small>${data.email || '-'}</small><br>
                            <small>${data.phone || '-'}</small>
                        </div>
                    `;
                }
            },
            {
                data: 'onboardingStatus',
                render: function (data) {
                    const statusMap = {
                        'draft': 'Draft',
                        'sent': 'Sent to Client',
                        'clientViewed': 'Client Viewed',
                        'clientSubmitted': 'Client Submitted',
                        'verified': 'Verified',
                        'completed': 'Completed'
                    };
                    return statusMap[data] || data || 'Pending';
                }
            },
            {
                data: 'onboardedAt',
                render: function (data) {
                    if (!data) return '-';
                    const date = new Date(data);
                    return date.toLocaleDateString('en-IN', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    });
                }
            },
            {
                data: null,
                orderable: false,
                render: function (data) {
                    return `
                        <div class="d-flex gap-1">
                            <button class="btn btn-info btn-sm service-btn" data-id="${data.id}" title="Select Services">
                                <i class="ri-briefcase-line"></i>
                            </button>
                            <button class="btn btn-primary btn-sm view-client" data-id="${data.id}" title="View Details">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // ============================================================
    // SEARCH FUNCTIONALITY
    // ============================================================
    $('#tableSearch').on('keyup', function () {
        table.search(this.value).draw();
    });

    // ============================================================
    // STATUS FILTER
    // ============================================================
    const statuses = ['draft', 'sent', 'clientViewed', 'clientSubmitted', 'verified', 'completed'];
    const statusLabels = {
        'draft': 'Draft',
        'sent': 'Sent to Client',
        'clientViewed': 'Client Viewed',
        'clientSubmitted': 'Client Submitted',
        'verified': 'Verified',
        'completed': 'Completed'
    };
    
    let statusOptions = '<option value="">All Status</option>';
    statuses.forEach(s => {
        statusOptions += `<option value="${s}">${statusLabels[s]}</option>`;
    });
    $('#statusFilter').html(statusOptions);

    $('#statusFilter').on('change', function () {
        table.ajax.url(API_BASE + '/client/getClientMaster.php?status=' + this.value).load();
    });

    // ============================================================
    // EXPORT FUNCTIONALITY
    // ============================================================
    $('.export-btn').on('click', function () {
        const type = $(this).data('type');
        if (type === 'csv') {
            alert('CSV Export - Will be implemented');
        } else if (type === 'pdf') {
            alert('PDF Export - Will be implemented');
        }
    });

    // ============================================================
    // CLIENT ONBOARDING MODAL
    // ============================================================
    const onboardingSteps = [
        { id: 'clientInformation', icon: 'ri-user-line', title: 'Client Information' },
        { id: 'serviceSelection', icon: 'ri-briefcase-line', title: 'Select Services' },
        { id: 'clientForm', icon: 'ri-file-text-line', title: 'Client Form' },
        { id: 'credentials', icon: 'ri-key-2-line', title: 'Credentials' }
    ];

    let currentState = {
        clientId: null,
        clientData: null,
        currentStep: 0,
        selectedServices: [],
        formId: null
    };

    // ============================================================
    // RENDER SIDEBAR
    // ============================================================
    function renderSidebar() {
        let html = '';
        onboardingSteps.forEach(function (step, index) {
            html += `
                <a href="javascript:void(0)" 
                   class="list-group-item list-group-item-action onboarding-step ${index === currentState.currentStep ? 'active' : ''}"
                   data-step="${step.id}" 
                   data-index="${index}">
                    <i class="${step.icon} me-2"></i>
                    ${step.title}
                </a>
            `;
        });
        $('#onboardingSidebar').html(html);
    }

    // ============================================================
    // LOAD CLIENT DETAILS
    // ============================================================
    function loadClientDetails(clientId) {
        currentState.clientId = clientId;
        
        $.ajax({
            url: API_BASE + '/client-onboarding/getClientOnboardingDetails.php',
            method: 'POST',
            data: { clientId: clientId },
            success: function (response) {
                if (response.success) {
                    currentState.clientData = response.data;
                    populateModalHeader(response.data);
                    
                    if (response.data.onboardingForm) {
                        currentState.formId = response.data.onboardingForm.id;
                        currentState.selectedServices = response.data.onboardingForm.selectedServices || [];
                    }
                    
                    loadStep(currentState.currentStep);
                } else {
                    showToast('Failed to load client details', 'error');
                }
            },
            error: function () {
                showToast('Error loading client details', 'error');
            }
        });
    }

    // ============================================================
    // POPULATE MODAL HEADER
    // ============================================================
    function populateModalHeader(data) {
        $('#modalClientName').text(data.fullName || 'Client Name');
        $('#modalClientCode').text(data.clientCode || '-');
        $('#modalEmail').text(data.email || '-');
        $('#modalPhone').text(data.phone || '-');
        $('#modalFinalPrice').text(data.finalPrice ? '₹ ' + data.finalPrice : '-');
        $('#modalOnboardedAt').text(data.onboardedAt ? formatDate(data.onboardedAt) : '-');
        
        const statusMap = {
            'draft': 'badge-warning',
            'sent': 'badge-info',
            'clientViewed': 'badge-primary',
            'clientSubmitted': 'badge-success',
            'verified': 'badge-success',
            'completed': 'badge-success'
        };
        const statusLabels = {
            'draft': 'Draft',
            'sent': 'Sent to Client',
            'clientViewed': 'Client Viewed',
            'clientSubmitted': 'Client Submitted',
            'verified': 'Verified',
            'completed': 'Completed'
        };
        const status = data.onboardingStatus || 'draft';
        $('#modalStatus')
            .text(statusLabels[status] || status)
            .removeClass('badge-warning badge-info badge-primary badge-success')
            .addClass(statusMap[status] || 'badge-warning');
        
        if (data.signedAgreementFile) {
            $('#downloadAgreementBtn')
                .show()
                .attr('href', '/uploads/onboarding/agreements/' + data.signedAgreementFile);
        } else {
            $('#downloadAgreementBtn').hide();
        }
    }

    // ============================================================
    // LOAD STEP
    // ============================================================
    function loadStep(index) {
        currentState.currentStep = index;
        const step = onboardingSteps[index];
        
        if (!step) return;
        
        $('#sectionTitle').text(step.title);
        
        switch (step.id) {
            case 'clientInformation':
                renderClientInformation();
                break;
            case 'serviceSelection':
                renderServiceSelection();
                break;
            case 'clientForm':
                renderClientForm();
                break;
            case 'credentials':
                renderCredentials();
                break;
        }
        
        renderSidebar();
        updateNavigationButtons();
    }

    // ============================================================
    // RENDER CLIENT INFORMATION
    // ============================================================
    function renderClientInformation() {
        const data = currentState.clientData || {};
        const form = data.onboardingForm || {};
        
        let html = `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <h6>Client Details</h6>
                    <hr>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <div class="border rounded p-2">${data.fullName || '-'}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Client Code</label>
                    <div class="border rounded p-2">${data.clientCode || '-'}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <div class="border rounded p-2">${data.email || '-'}</div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Phone</label>
                    <div class="border rounded p-2">${data.phone || '-'}</div>
                </div>
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Organization</label>
                    <div class="border rounded p-2">${data.orgName || '-'}</div>
                </div>
                
                <div class="col-md-12 mt-3">
                    <h6>Client Submitted Information</h6>
                    <hr>
                </div>
                
                <!-- Brand Logos -->
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Brand Logos</label>
                    <div class="border rounded p-3">
                        ${renderBrandLogos(form.brandLogos || [])}
                    </div>
                </div>
                
                <!-- Official Email -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Official Email</label>
                    <div class="border rounded p-2">${form.officialEmail || 'Not provided'}</div>
                </div>
                
                <!-- Contact Phone -->
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">Contact Phone</label>
                    <div class="border rounded p-2">${form.contactPhone || 'Not provided'}</div>
                </div>
                
                <!-- Media Links -->
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Media Links</label>
                    <div class="border rounded p-3">
                        ${renderMediaLinksList(form.mediaLinks || [])}
                    </div>
                </div>
                
                <!-- Competitors -->
                <div class="col-md-12 mb-3">
                    <label class="form-label fw-bold">Competitors</label>
                    <div class="border rounded p-3">
                        ${renderCompetitorsList(form.competitors || [])}
                    </div>
                </div>
                
                <!-- Form Status -->
                <div class="col-md-12 mt-3">
                    <div class="alert alert-${getStatusColor(form.formStatus || 'draft')}">
                        <strong>Form Status:</strong> ${getStatusLabel(form.formStatus || 'draft')}
                        ${form.sentAt ? `<br><small>Sent on: ${formatDate(form.sentAt)}</small>` : ''}
                        ${form.clientSubmittedAt ? `<br><small>Submitted on: ${formatDate(form.clientSubmittedAt)}</small>` : ''}
                    </div>
                </div>
            </div>
        `;
        
        $('#onboardingContent').html(html);
    }

    // ============================================================
    // RENDER SERVICE SELECTION
    // ============================================================
    function renderServiceSelection() {
        const selected = currentState.selectedServices || [];
        const form = currentState.clientData?.onboardingForm || {};
        const formStatus = form.formStatus || 'draft';
        
        const isSent = formStatus !== 'draft';
        const isSubmitted = formStatus === 'clientSubmitted' || formStatus === 'verified';
        
        let html = `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <p class="text-muted">
                        <i class="ri-information-line me-1"></i>
                        Select the services that the client has purchased.
                        ${isSent ? '<span class="text-warning">⚠️ Form already sent. Re-sending will send a new email.</span>' : ''}
                    </p>
                    <hr>
                </div>
                
                <div class="col-md-12">
                    <div class="row">
        `;
        
        const services = [
            { id: 'socialMedia', label: 'Social Media (IG | FB)', icon: 'ri-instagram-line' },
            { id: 'youtube', label: 'YouTube', icon: 'ri-youtube-line' },
            { id: 'pinterest', label: 'Pinterest', icon: 'ri-pinterest-line' },
            
            { id: 'twitter', label: 'Twitter (X)', icon: 'ri-twitter-x-line' },
            { id: 'gmb', label: 'Google My Business', icon: 'ri-google-line' },
            { id: 'googleAds', label: 'Google Ads', icon: 'ri-google-line' },
            { id: 'metaAds', label: 'Meta Ads (FB + IG)', icon: 'ri-facebook-circle-line' },
            { id: 'videos', label: 'Videos', icon: 'ri-video-line' }
        ];
        
        services.forEach(service => {
            const isSelected = selected.includes(service.id);
            const isCompleted = isSelected && isSubmitted;
            html += `
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card service-card ${isSelected ? 'border-primary' : ''} ${isCompleted ? 'border-primary' : ''}" 
                         onclick="toggleService('${service.id}')"
                         style="cursor: pointer; transition: all 0.3s;">
                        <div class="card-body text-center">
                            <i class="${service.icon} fs-2 ${isSelected ? 'text-primary' : ''}"></i>
                            <h6 class="mt-2">${service.label}</h6>
                            ${isSelected ? '<span class="badge bg-primary">Selected</span>' : ''}
                            ${isCompleted ? '<span class="badge bg-success ms-1">✓ Done</span>' : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                    </div>
                </div>
                
                <div class="col-md-12 mt-3">
                    <div class="alert alert-info">
                        <i class="ri-information-line me-1"></i>
                        Selected services: <strong id="selectedServicesCount">${selected.length}</strong>
                    </div>
                </div>
            </div>
            
            <div class="mt-3">
                <button type="button" class="btn btn-success" onclick="saveServiceSelection()">
                    <i class="ri-save-line me-1"></i> Save Services
                </button>
        `;
        
        if (!isSubmitted) {
            html += `
                <button type="button" class="btn btn-primary ms-2" onclick="debugSendForm()">
                    <i class="ri-send-plane-line me-1"></i> 
                    ${isSent ? 'Re-send Form to Client' : 'Send Form to Client'}
                </button>
            `;
        } else {
            html += `
                <span class="ms-2 text-success">
                    <i class="ri-check-circle-line me-1"></i>
                    Form already submitted by client
                </span>
            `;
        }
        
        html += `</div>`;
        
        $('#onboardingContent').html(html);
    }

    // ============================================================
    // RENDER CLIENT FORM
    // ============================================================
    function renderClientForm() {
        const form = currentState.clientData?.onboardingForm || {};
        const status = form.formStatus || 'draft';
        
        let html = `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="alert alert-${getStatusColor(status)}">
                        <strong>Form Status:</strong> ${getStatusLabel(status)}
                        ${form.sentAt ? `<br><small>Sent on: ${formatDate(form.sentAt)}</small>` : ''}
                        ${form.clientViewedAt ? `<br><small>Client viewed on: ${formatDate(form.clientViewedAt)}</small>` : ''}
                        ${form.clientSubmittedAt ? `<br><small>Client submitted on: ${formatDate(form.clientSubmittedAt)}</small>` : ''}
                    </div>
                </div>
                
                <div class="col-md-12 mb-3">
                    <h6>Client Submitted Data</h6>
                    <hr>
                </div>
        `;
        
        // Brand Logos
        const brandLogos = form.brandLogos || [];
        html += `
            <div class="col-md-12 mb-3">
                <label class="form-label fw-bold">Brand Logos</label>
                <div class="border rounded p-3">
                    ${brandLogos.length > 0 ? renderBrandLogos(brandLogos) : '<span class="text-muted">No logos uploaded</span>'}
                </div>
            </div>
        `;
        
        // Official Email
        html += `
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Official Email</label>
                <div class="border rounded p-2">${form.officialEmail || 'Not provided'}</div>
            </div>
        `;
        
        // Contact Phone
        html += `
            <div class="col-md-6 mb-3">
                <label class="form-label fw-bold">Contact Phone</label>
                <div class="border rounded p-2">${form.contactPhone || 'Not provided'}</div>
            </div>
        `;
        
        // Media Links
        const mediaLinks = form.mediaLinks || [];
        html += `
            <div class="col-md-12 mb-3">
                <label class="form-label fw-bold">Media Links</label>
                <div class="border rounded p-3">
                    ${mediaLinks.length > 0 ? renderMediaLinksList(mediaLinks) : '<span class="text-muted">No media links provided</span>'}
                </div>
            </div>
        `;
        
        // Competitors
        const competitors = form.competitors || [];
        html += `
            <div class="col-md-12 mb-3">
                <label class="form-label fw-bold">Competitors</label>
                <div class="border rounded p-3">
                    ${competitors.length > 0 ? renderCompetitorsList(competitors) : '<span class="text-muted">No competitors listed</span>'}
                </div>
            </div>
        `;
        
        // Selected Services
        const selectedServices = form.selectedServices || [];
        html += `
            <div class="col-md-12 mb-3">
                <label class="form-label fw-bold">Selected Services</label>
                <div class="border rounded p-3">
                    ${renderSelectedServices(selectedServices)}
                </div>
            </div>
        `;
        
        // Verify button (if submitted)
        if (status === 'clientSubmitted') {
            html += `
                <div class="col-md-12 mt-3">
                    <div class="alert alert-success">
                        <i class="ri-check-circle-line me-1"></i>
                        Client has submitted the form. Please verify the credentials in the Credentials tab.
                    </div>
                    <button class="btn btn-success" onclick="verifyClientForm()">
                        <i class="ri-check-double-line me-1"></i> Mark as Verified
                    </button>
                </div>
            `;
        } else if (status === 'draft') {
            html += `
                <div class="col-md-12 mt-3">
                    <button class="btn btn-primary" onclick="sendFormToClient()">
                        <i class="ri-send-plane-line me-1"></i> Send Form to Client
                    </button>
                </div>
            `;
        }
        
        html += `</div>`;
        
        $('#onboardingContent').html(html);
    }

    // ============================================================
    // RENDER CREDENTIALS
    // ============================================================
    function renderCredentials() {
        const services = currentState.selectedServices || [];
        const credentials = currentState.clientData?.serviceCredentials || [];
        
        if (services.length === 0) {
            $('#onboardingContent').html(`
                <div class="alert alert-warning">
                    <i class="ri-alert-line me-1"></i>
                    No services selected. Please select services in the previous step.
                </div>
            `);
            return;
        }
        
        let html = `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <p class="text-muted">Client has submitted the following credentials. Verify and mark as verified.</p>
                    <hr>
                </div>
        `;
        
        services.forEach(serviceId => {
            const serviceConfigs = {
                socialMedia: { label: 'Social Media', icon: 'ri-instagram-line' },
                youtube: { label: 'YouTube', icon: 'ri-youtube-line' },
                pinterest: { label: 'Pinterest', icon: 'ri-pinterest-line' },
                twitter: { label: 'Twitter (X)', icon: 'ri-twitter-x-line' },
                gmb: { label: 'Google My Business', icon: 'ri-google-line' },
                googleAds: { label: 'Google Ads', icon: 'ri-google-line' },
                metaAds: { label: 'Meta Ads', icon: 'ri-facebook-circle-line' },
                videos: { label: 'Videos', icon: 'ri-video-line' }
            };
            
            const config = serviceConfigs[serviceId] || { label: serviceId, icon: 'ri-service-line' };
            const creds = credentials.filter(c => c.serviceType === serviceId);
            
            html += `
                <div class="col-md-12 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <i class="${config.icon} me-1"></i>
                            <strong>${config.label}</strong>
                            <span class="badge ${creds.length > 0 ? 'bg-success' : 'bg-secondary'} ms-2">
                                ${creds.length > 0 ? 'Submitted' : 'Not Submitted'}
                            </span>
                        </div>
                        <div class="card-body">
                            ${renderCredentialsForService(serviceId, creds)}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `</div>`;
        
        $('#onboardingContent').html(html);
    }

    // ============================================================
    // RENDER CREDENTIALS FOR SERVICE
    // ============================================================
    function renderCredentialsForService(serviceId, credentials) {
        if (credentials.length === 0) {
            return `
                <div class="alert alert-info">
                    <i class="ri-information-line me-1"></i>
                    Client has not submitted credentials for this service yet.
                </div>
            `;
        }
        
        let html = '';
        credentials.forEach(cred => {
            html += `
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Platform</label>
                        <input type="text" class="form-control" value="${cred.platform || ''}" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="${cred.username || ''}" disabled>
                    </div>
                    ${cred.password ? `
                    <div class="col-md-12 mt-2">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" value="${cred.password}" id="password_${cred.id}" disabled>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password_${cred.id}')">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${cred.youtubeGrantAccess ? `
                    <div class="col-md-12 mt-2">
                        <span class="badge bg-success">YouTube Access Granted</span>
                        <small class="d-block text-muted">Emails: uday.work@gmail.com, social@mqlus.in</small>
                        ${cred.youtubeScreenshot ? `<a href="/uploads/${cred.youtubeScreenshot}" target="_blank" class="btn btn-sm btn-outline-primary mt-1">View Screenshot</a>` : ''}
                    </div>
                    ` : ''}
                    
                    ${cred.fbUsername ? `
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Facebook Username</label>
                        <input type="text" class="form-control" value="${cred.fbUsername}" disabled>
                    </div>
                    ` : ''}
                    
                    ${cred.fbPassword ? `
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Facebook Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" value="${cred.fbPassword}" id="fbPassword_${cred.id}" disabled>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('fbPassword_${cred.id}')">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                    ` : ''}
                    
                    ${cred.igUsername ? `
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Instagram Username</label>
                        <input type="text" class="form-control" value="${cred.igUsername}" disabled>
                    </div>
                    ` : ''}
                    
                    ${cred.igPassword ? `
                    <div class="col-md-6 mt-2">
                        <label class="form-label">Instagram Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" value="${cred.igPassword}" id="igPassword_${cred.id}" disabled>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('igPassword_${cred.id}')">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                    </div>
                    ` : ''}
                    
                    <div class="col-md-12 mt-3">
                        <button class="btn btn-sm ${cred.isVerified ? 'btn-success' : 'btn-outline-success'}" 
                                onclick="verifyCredentials(${cred.id})">
                            <i class="ri-check-line me-1"></i>
                            ${cred.isVerified ? 'Verified' : 'Mark as Verified'}
                        </button>
                    </div>
                    
                    <hr class="my-3">
                </div>
            `;
        });
        
        return html;
    }

    // ============================================================
    // HELPER FUNCTIONS
    // ============================================================
    
    function renderBrandLogos(logos) {
        if (!logos || logos.length === 0) return '<span class="text-muted">No logos uploaded</span>';
        let html = '<div class="d-flex flex-wrap gap-2">';
        logos.forEach(logo => {
            html += `
                <div class="border rounded p-1 bg-white">
                    <img src="/uploads/onboarding/brand-logos/${logo}" alt="Brand Logo" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">
                </div>
            `;
        });
        html += '</div>';
        return html;
    }

    function renderMediaLinksList(links) {
        if (!links || links.length === 0) return '<span class="text-muted">No media links provided</span>';
        let html = '<ul class="list-unstyled mb-0">';
        links.forEach(link => {
            html += `
                <li class="mb-1">
                    <a href="${link}" target="_blank" class="text-primary">
                        <i class="ri-link me-1"></i> ${link}
                    </a>
                </li>
            `;
        });
        html += '</ul>';
        return html;
    }

    function renderCompetitorsList(competitors) {
        if (!competitors || competitors.length === 0) return '<span class="text-muted">No competitors listed</span>';
        let html = '<div class="d-flex flex-wrap gap-1">';
        competitors.forEach(comp => {
            html += `<span class="badge bg-secondary">${comp}</span> `;
        });
        html += '</div>';
        return html;
    }

    function renderSelectedServices(services) {
        if (!services || services.length === 0) return '<span class="text-muted">No services selected</span>';
        const labels = {
            socialMedia: 'Social Media (IG | FB)',
            youtube: 'YouTube',
            pinterest: 'Pinterest',
            twitter: 'Twitter (X)',
            gmb: 'Google My Business',
            googleAds: 'Google Ads',
            metaAds: 'Meta Ads (FB + IG)',
            videos: 'Videos'
        };
        let html = '<ul class="list-unstyled mb-0">';
        services.forEach(s => {
            html += `<li><span class="badge bg-primary me-1">•</span> ${labels[s] || s}</li>`;
        });
        html += '</ul>';
        return html;
    }

    function getStatusColor(status) {
        const colors = {
            'draft': 'secondary',
            'sent': 'info',
            'clientViewed': 'primary',
            'clientSubmitted': 'success',
            'verified': 'success'
        };
        return colors[status] || 'secondary';
    }

    function getStatusLabel(status) {
        const labels = {
            'draft': 'Draft (Not Sent)',
            'sent': 'Sent to Client',
            'clientViewed': 'Viewed by Client',
            'clientSubmitted': 'Submitted by Client',
            'verified': 'Verified'
        };
        return labels[status] || status;
    }

    function formatDate(date) {
        if (!date) return '-';
        const d = new Date(date);
        return d.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function showToast(message, type = 'success') {
        Swal.fire({
            icon: type,
            title: message,
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
    }

    function updateNavigationButtons() {
        const total = onboardingSteps.length;
        const current = currentState.currentStep;
        
        $('#previousSectionBtn').toggle(current > 0);
        $('#nextSectionBtn').toggle(current < total - 1);
        
        $('#previousSectionBtn').off('click').on('click', function () {
            if (current > 0) loadStep(current - 1);
        });
        
        $('#nextSectionBtn').off('click').on('click', function () {
            if (current < total - 1) loadStep(current + 1);
        });
    }

    // ============================================================
    // GLOBAL FUNCTIONS (Called from inline onclick)
    // ============================================================
    
    window.toggleService = function(serviceId) {
        const index = currentState.selectedServices.indexOf(serviceId);
        if (index > -1) {
            currentState.selectedServices.splice(index, 1);
        } else {
            currentState.selectedServices.push(serviceId);
        }
        renderServiceSelection();
    };

    window.saveServiceSelection = function() {
        if (currentState.selectedServices.length === 0) {
            showToast('Please select at least one service', 'warning');
            return;
        }
        
        const data = {
            clientId: currentState.clientId,
            selectedServices: currentState.selectedServices
        };
        
        $.ajax({
            url: API_BASE + '/onboarding/saveOnboardingForm.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    showToast('Services saved successfully');
                    if (response.formId) {
                        currentState.formId = response.formId;
                    }
                } else {
                    showToast(response.message || 'Failed to save services', 'error');
                }
            },
            error: function() {
                showToast('Error saving services', 'error');
            }
        });
    };

    window.debugSendForm = function() {
        console.log('=== DEBUG SEND FORM ===');
        console.log('currentState.formId:', currentState.formId);
        console.log('currentState.clientId:', currentState.clientId);
        console.log('currentState.selectedServices:', currentState.selectedServices);
        
        if (!currentState.formId) {
            console.log('No form ID - will save first');
            if (currentState.selectedServices.length === 0) {
                showToast('Please select services first', 'warning');
                return;
            }
            saveServiceSelection();
            setTimeout(() => {
                if (currentState.formId) {
                    actuallySendForm();
                } else {
                    showToast('Form ID still missing after save', 'error');
                }
            }, 1000);
        } else {
            console.log('Form ID exists, sending directly');
            actuallySendForm();
        }
    };

    window.sendFormToClient = function() {
        if (!currentState.formId) {
            showToast('Saving services first...', 'info');
            
            const data = {
                clientId: currentState.clientId,
                selectedServices: currentState.selectedServices
            };
            
            $.ajax({
                url: API_BASE + '/onboarding/saveOnboardingForm.php',
                method: 'POST',
                data: JSON.stringify(data),
                contentType: 'application/json',
                async: false,
                success: function(response) {
                    if (response.success) {
                        if (response.formId) {
                            currentState.formId = response.formId;
                            showToast('Form saved, now sending...', 'info');
                            actuallySendForm();
                        } else {
                            showToast('Failed to get form ID after saving', 'error');
                        }
                    } else {
                        showToast(response.message || 'Failed to save form', 'error');
                    }
                },
                error: function() {
                    showToast('Error saving form', 'error');
                }
            });
        } else {
            actuallySendForm();
        }
    };

    function actuallySendForm() {
        console.log('Sending form with ID:', currentState.formId);
        
        if (!currentState.formId) {
            showToast('Form ID not found. Please save the form first.', 'error');
            return;
        }
        
        var modalElement = document.getElementById('clientOnboardingModal');
        var modal = bootstrap.Modal.getInstance(modalElement);
        
        if (modal) {
            modal.hide();
        }
        
        setTimeout(function() {
            var overlay = document.createElement('div');
            overlay.id = 'customConfirmOverlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeInOverlay 0.3s ease;
            `;
            
            var box = document.createElement('div');
            box.style.cssText = `
                background: white;
                border-radius: 16px;
                padding: 40px;
                max-width: 450px;
                width: 90%;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.3s ease;
                position: relative;
                z-index: 1000000;
            `;
            
            box.innerHTML = `
                <div style="text-align: center;">
                    <div style="font-size: 50px; margin-bottom: 15px;">📧</div>
                    <h3 style="margin-bottom: 10px; color: #1a1a2e; font-weight: 600;">Send Form to Client?</h3>
                    <p style="color: #666; margin-bottom: 25px; font-size: 15px; line-height: 1.6;">
                        The client will receive an email with a link to fill the form.
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center;">
                        <button id="cancelSendBtn" 
                                style="padding: 12px 35px; border: 2px solid #e0e0e0; background: white; 
                                       border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 500;
                                       transition: all 0.3s;">
                            Cancel
                        </button>
                        <button id="confirmSendBtn" 
                                style="padding: 12px 35px; border: none; background: #4CAF50; color: white; 
                                       border-radius: 8px; cursor: pointer; font-size: 15px; font-weight: 600;
                                       transition: all 0.3s; box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);">
                            Yes, Send
                        </button>
                    </div>
                </div>
            `;
            
            overlay.appendChild(box);
            document.body.appendChild(overlay);
            
            var style = document.createElement('style');
            style.textContent = `
                @keyframes fadeInOverlay {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(30px) scale(0.95); }
                    to { opacity: 1; transform: translateY(0) scale(1); }
                }
                @keyframes fadeOutOverlay {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
                #cancelSendBtn:hover {
                    background: #f5f5f5;
                    border-color: #ccc;
                }
                #confirmSendBtn:hover {
                    background: #45a049;
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
                }
            `;
            document.head.appendChild(style);
            
            document.getElementById('cancelSendBtn').addEventListener('click', function() {
                overlay.style.animation = 'fadeOutOverlay 0.2s ease';
                setTimeout(function() {
                    overlay.remove();
                    if (modal) {
                        modal.show();
                    }
                }, 250);
            });
            
            document.getElementById('confirmSendBtn').addEventListener('click', function() {
                overlay.remove();
                showCustomLoading();
                
                $.ajax({
                    url: API_BASE + '/onboarding/sendOnboardingForm.php',
                    method: 'POST',
                    data: JSON.stringify({ formId: currentState.formId }),
                    contentType: 'application/json',
                    success: function(response) {
                        closeCustomLoading();
                        console.log('Send response:', response);
                        
                        if (response.success) {
                            showCustomSuccess('✅ Form Sent!', 'The client has been notified via email.');
                            setTimeout(function() {
                                loadClientDetails(currentState.clientId);
                            }, 1500);
                        } else {
                            showCustomError('❌ Failed to Send', response.message || 'Failed to send form.');
                        }
                    },
                    error: function(xhr, status, error) {
                        closeCustomLoading();
                        console.error('Error:', error, xhr.responseText);
                        showCustomError('❌ Error', 'An error occurred while sending the form.');
                    }
                });
            });
            
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    document.getElementById('cancelSendBtn').click();
                }
            });
            
            document.addEventListener('keydown', function escHandler(e) {
                if (e.key === 'Escape') {
                    if (document.getElementById('customConfirmOverlay')) {
                        document.getElementById('cancelSendBtn').click();
                        document.removeEventListener('keydown', escHandler);
                    }
                }
            });
            
        }, 400);
    }
    
    function showCustomLoading() {
        var loadingDiv = document.createElement('div');
        loadingDiv.id = 'customLoading';
        loadingDiv.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        loadingDiv.innerHTML = `
            <div style="background: white; padding: 40px; border-radius: 16px; text-align: center; min-width: 200px;">
                <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; 
                            border-top: 5px solid #4CAF50; border-radius: 50%; 
                            animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
                <p style="font-size: 16px; color: #333; margin: 0;">Sending form...</p>
                <style>
                    @keyframes spin {
                        0% { transform: rotate(0deg); }
                        100% { transform: rotate(360deg); }
                    }
                </style>
            </div>
        `;
        
        document.body.appendChild(loadingDiv);
    }
    
    function closeCustomLoading() {
        var loading = document.getElementById('customLoading');
        if (loading) {
            loading.remove();
        }
    }
    
    function showCustomSuccess(title, message) {
        var div = document.createElement('div');
        div.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 999999;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
            animation: slideUp 0.3s ease;
        `;
        
        div.innerHTML = `
            <div style="font-size: 50px; margin-bottom: 15px;">✅</div>
            <h3 style="color: #4CAF50; margin-bottom: 10px; font-weight: 600;">${title}</h3>
            <p style="color: #666; margin: 0;">${message}</p>
        `;
        
        document.body.appendChild(div);
        
        setTimeout(function() {
            div.style.animation = 'fadeOutOverlay 0.3s ease';
            setTimeout(function() {
                div.remove();
                var modalElement = document.getElementById('clientOnboardingModal');
                var modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.show();
                    loadClientDetails(currentState.clientId);
                }
            }, 300);
        }, 2500);
    }
    
    function showCustomError(title, message) {
        var div = document.createElement('div');
        div.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 999999;
            background: white;
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
            max-width: 400px;
            width: 90%;
            animation: slideUp 0.3s ease;
        `;
        
        div.innerHTML = `
            <div style="font-size: 50px; margin-bottom: 15px;">❌</div>
            <h3 style="color: #d32f2f; margin-bottom: 10px; font-weight: 600;">${title}</h3>
            <p style="color: #666; margin-bottom: 20px;">${message}</p>
            <button onclick="this.closest('div').remove(); 
                    var modal = bootstrap.Modal.getInstance(document.getElementById('clientOnboardingModal'));
                    if(modal) modal.show();" 
                    style="padding: 10px 35px; border: none; background: #d32f2f; color: white; 
                           border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500;">
                Close
            </button>
        `;
        
        document.body.appendChild(div);
    }

    window.verifyCredentials = function(credId) {
        showToast('Credential verification will be implemented', 'info');
    };

    window.verifyClientForm = function() {
        showToast('Form verification will be implemented', 'info');
    };

    window.togglePassword = function(id) {
        const input = document.getElementById(id);
        if (input) {
            const type = input.type === 'password' ? 'text' : 'password';
            input.type = type;
        }
    };

    // ============================================================
    // VIEW CLIENT EVENT
    // ============================================================
   
    $(document).on('click', '.view-client', function () {
        const clientId = $(this).data('id');
        currentState.clientId = clientId;
        
        $.ajax({
            url: API_BASE + '/client-onboarding/getClientOnboardingDetails.php',
            method: 'POST',
            data: { clientId: clientId },
            success: function (response) {
                if (response.success) {
                    currentState.clientData = response.data;
                    populateModalHeader(response.data);
                    
                    if (response.data.onboardingForm) {
                        currentState.formId = response.data.onboardingForm.id;
                        currentState.selectedServices = response.data.onboardingForm.selectedServices || [];
                    }
                    
                    // ✅ Render view modal content (no tabs)
                    renderViewModal();
                    $('#clientOnboardingModal').modal('show');
                } else {
                    showToast('Failed to load client details', 'error');
                }
            },
            error: function () {
                showToast('Error loading client details', 'error');
            }
        });
    });

    // ============================================================
    // SCHEDULED CALLS BUTTON
    // ============================================================
    $('#scheduledCallsBtn').on('click', function () {
        alert('Scheduled Calls - Will be implemented');
    });
    
    
    // ============================================================
    // SERVICE SELECTION MODAL
    // ============================================================
    let serviceModalState = {
        clientId: null,
        clientData: null,
        selectedServices: [],
        formId: null
    };
    
    $(document).on('click', '.service-btn', function () {
        const clientId = $(this).data('id');
        openServiceModal(clientId);
    });
    
    function openServiceModal(clientId) {
        serviceModalState.clientId = clientId;
        
        $.ajax({
            url: API_BASE + '/client-onboarding/getClientOnboardingDetails.php',
            method: 'POST',
            data: { clientId: clientId },
            success: function (response) {
                if (response.success) {
                    serviceModalState.clientData = response.data;
                    
                    // Populate header
                    $('#serviceModalClientName').text(response.data.fullName || 'Client Name');
                    $('#serviceModalClientCode').text('Client Code: ' + (response.data.clientCode || '-'));
                    
                    // Set selected services
                    if (response.data.onboardingForm) {
                        serviceModalState.formId = response.data.onboardingForm.id;
                        serviceModalState.selectedServices = response.data.onboardingForm.selectedServices || [];
                    }
                    
                    renderServiceModalContent();
                    $('#serviceSelectionModal').modal('show');
                } else {
                    showToast('Failed to load client details', 'error');
                }
            },
            error: function () {
                showToast('Error loading client details', 'error');
            }
        });
    }
    
    function renderServiceModalContent() {
        const selected = serviceModalState.selectedServices || [];
        
        let html = `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <p class="text-muted">
                        <i class="ri-information-line me-1"></i>
                        Select the services that the client has purchased.
                    </p>
                    <hr>
                </div>
                <div class="col-md-12">
                    <div class="row">
        `;
        
        const services = [
            { id: 'socialMedia', label: 'Social Media (IG | FB)', icon: 'ri-instagram-line' },
            { id: 'youtube', label: 'YouTube', icon: 'ri-youtube-line' },
            { id: 'pinterest', label: 'Pinterest', icon: 'ri-pinterest-line' },
            { id: 'twitter', label: 'Twitter (X)', icon: 'ri-twitter-x-line' },
            { id: 'gmb', label: 'Google My Business', icon: 'ri-google-line' },
            { id: 'googleAds', label: 'Google Ads', icon: 'ri-google-line' },
            { id: 'metaAds', label: 'Meta Ads (FB + IG)', icon: 'ri-facebook-circle-line' },
            { id: 'videos', label: 'Videos', icon: 'ri-video-line' }
        ];
        
        services.forEach(service => {
            const isSelected = selected.includes(service.id);
            html += `
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="card service-card ${isSelected ? 'border-primary' : ''}" 
                         onclick="toggleServiceModal('${service.id}')"
                         style="cursor: pointer; transition: all 0.3s;">
                        <div class="card-body text-center">
                            <i class="${service.icon} fs-2 ${isSelected ? 'text-primary' : ''}"></i>
                            <h6 class="mt-2">${service.label}</h6>
                            ${isSelected ? '<span class="badge bg-primary">Selected</span>' : ''}
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += `
                    </div>
                </div>
                <div class="col-md-12 mt-3">
                    <div class="alert alert-info">
                        <i class="ri-information-line me-1"></i>
                        Selected services: <strong id="serviceModalCount">${selected.length}</strong>
                    </div>
                </div>
            </div>
        `;
        
        $('#serviceSelectionContent').html(html);
    }
    
    window.toggleServiceModal = function(serviceId) {
        const index = serviceModalState.selectedServices.indexOf(serviceId);
        if (index > -1) {
            serviceModalState.selectedServices.splice(index, 1);
        } else {
            serviceModalState.selectedServices.push(serviceId);
        }
        renderServiceModalContent();
    };
    
    window.saveServiceFromModal = function() {
        if (serviceModalState.selectedServices.length === 0) {
            showToast('Please select at least one service', 'warning');
            return;
        }
        
        const data = {
            clientId: serviceModalState.clientId,
            selectedServices: serviceModalState.selectedServices
        };
        
        $.ajax({
            url: API_BASE + '/onboarding/saveOnboardingForm.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    showToast('Services saved successfully');
                    if (response.formId) {
                        serviceModalState.formId = response.formId;
                    }
                    // Update the main table
                    table.ajax.reload();
                } else {
                    showToast(response.message || 'Failed to save services', 'error');
                }
            },
            error: function() {
                showToast('Error saving services', 'error');
            }
        });
    };
    
    window.sendServiceForm = function() {
        if (serviceModalState.selectedServices.length === 0) {
            showToast('Please select at least one service', 'warning');
            return;
        }
        
        // ✅ Show loading on the button
        const sendBtn = document.getElementById('sendServiceFormBtn');
        const originalHtml = sendBtn.innerHTML;
        sendBtn.innerHTML = '<i class="ri-loader-4-line ri-spin me-1"></i> Saving & Sending...';
        sendBtn.disabled = true;
        
        // ✅ Step 1: Save services first
        const data = {
            clientId: serviceModalState.clientId,
            selectedServices: serviceModalState.selectedServices
        };
        
        $.ajax({
            url: API_BASE + '/onboarding/saveOnboardingForm.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json',
            success: function(response) {
                if (response.success) {
                    if (response.formId) {
                        serviceModalState.formId = response.formId;
                        currentState.formId = response.formId;
                        currentState.selectedServices = serviceModalState.selectedServices;
                        currentState.clientId = serviceModalState.clientId;
                        
                        showToast('Services saved, now sending...', 'info');
                        
                        // ✅ Step 2: Close service modal
                        $('#serviceSelectionModal').modal('hide');
                        
                        // ✅ Step 3: Send the form using the updated formId
                        // Set currentState for sending
                        currentState.formId = serviceModalState.formId;
                        currentState.clientId = serviceModalState.clientId;
                        
                        // Reset button
                        sendBtn.innerHTML = originalHtml;
                        sendBtn.disabled = false;
                        
                        // Call the existing send function
                        actuallySendForm();
                    } else {
                        showToast('Failed to get form ID after saving', 'error');
                        sendBtn.innerHTML = originalHtml;
                        sendBtn.disabled = false;
                    }
                } else {
                    showToast(response.message || 'Failed to save services', 'error');
                    sendBtn.innerHTML = originalHtml;
                    sendBtn.disabled = false;
                }
            },
            error: function() {
                showToast('Error saving services', 'error');
                sendBtn.innerHTML = originalHtml;
                sendBtn.disabled = false;
            }
        });
    };
    
    // ============================================================
    // RENDER VIEW MODAL - NO TABS, ALL DATA
    // ============================================================
    function renderViewModal() {
        const data = currentState.clientData || {};
        const form = data.onboardingForm || {};
        const status = form.formStatus || 'draft';
        
        // Check if mail is sent
        const isMailSent = status !== 'draft';
        
        if (!isMailSent) {
            $('#viewContent').html(`
                <div class="alert alert-warning text-center py-5">
                    <i class="ri-mail-line fs-1 d-block mb-3"></i>
                    <h5>Service Mail is Not Initiated</h5>
                    <p class="text-muted">Please initiate Service Selection based onboarding first.</p>
                    <button class="btn btn-primary mt-2" onclick="openServiceModal(${data.id})">
                        <i class="ri-briefcase-line me-1"></i> Open Service Selection
                    </button>
                </div>
            `);
            return;
        }
        
        let html = `
            <div class="row">
                <div class="col-md-12 mb-3">
                    <div class="alert alert-${getStatusColor(status)}">
                        <strong>Form Status:</strong> ${getStatusLabel(status)}
                        ${form.sentAt ? `<br><small>Sent on: ${formatDate(form.sentAt)}</small>` : ''}
                        ${form.clientSubmittedAt ? `<br><small>Client submitted on: ${formatDate(form.clientSubmittedAt)}</small>` : ''}
                    </div>
                </div>
                
                <div class="col-md-12 mb-3">
                    <h6>Client Details</h6>
                    <hr>
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="fw-bold">Full Name</label>
                    <div class="border rounded p-2">${data.fullName || '-'}</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold">Email</label>
                    <div class="border rounded p-2">${data.email || '-'}</div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="fw-bold">Phone</label>
                    <div class="border rounded p-2">${data.phone || '-'}</div>
                </div>
                
                <div class="col-md-12 mt-3">
                    <h6>Client Submitted Information</h6>
                    <hr>
                </div>
                
                <!-- Brand Logos with Download -->
                <div class="col-md-12 mb-3">
                    <label class="fw-bold">Brand Logos</label>
                    <div class="border rounded p-3">
                        ${renderBrandLogosWithDownload(form.brandLogos || [])}
                    </div>
                </div>
                
                <!-- Official Email -->
                <div class="col-md-6 mb-3">
                    <label class="fw-bold">Official Email</label>
                    <div class="border rounded p-2">${form.officialEmail || 'Not provided'}</div>
                </div>
                
                <!-- Contact Phone -->
                <div class="col-md-6 mb-3">
                    <label class="fw-bold">Contact Phone</label>
                    <div class="border rounded p-2">${form.contactPhone || 'Not provided'}</div>
                </div>
                
                <!-- Media Links -->
                <div class="col-md-12 mb-3">
                    <label class="fw-bold">Media Links</label>
                    <div class="border rounded p-3">
                        ${renderMediaLinksList(form.mediaLinks || [])}
                    </div>
                </div>
                
                <!-- Competitors -->
                <div class="col-md-12 mb-3">
                    <label class="fw-bold">Competitors</label>
                    <div class="border rounded p-3">
                        ${renderCompetitorsList(form.competitors || [])}
                    </div>
                </div>
                
                <!-- Selected Services -->
                <div class="col-md-12 mb-3">
                    <label class="fw-bold">Selected Services</label>
                    <div class="border rounded p-3">
                        ${renderSelectedServices(form.selectedServices || [])}
                    </div>
                </div>
                
                <!-- Service Credentials -->
                ${renderCredentialsView(data.serviceCredentials || [], form.selectedServices || [])}
            </div>
        `;
        
        $('#viewContent').html(html);
    }
    
    // ============================================================
    // RENDER BRAND LOGOS WITH DOWNLOAD
    // ============================================================
    function renderBrandLogosWithDownload(logos) {
        if (!logos || logos.length === 0) return '<span class="text-muted">No logos uploaded</span>';
        let html = '<div class="d-flex flex-wrap gap-3">';
        logos.forEach(logo => {
            html += `
                <div class="border rounded p-2 bg-white text-center">
                    <img src="/uploads/onboarding/brand-logos/${logo}" alt="Brand Logo" 
                         style="width: 100px; height: 100px; object-fit: cover; border-radius: 4px; display: block; margin-bottom: 5px;">
                    <a href="/uploads/onboarding/brand-logos/${logo}" download 
                       class="btn btn-sm btn-outline-primary">
                        <i class="ri-download-line"></i> Download
                    </a>
                </div>
            `;
        });
        html += '</div>';
        return html;
    }
    
    // ============================================================
    // RENDER CREDENTIALS VIEW
    // ============================================================
   
    function renderCredentialsView(credentials, selectedServices) {
        if (!selectedServices || selectedServices.length === 0) {
            return `<div class="col-md-12 mt-3"><p class="text-muted">No services selected</p></div>`;
        }
        
        let html = `<div class="col-md-12 mt-3"><h6>Service Credentials</h6><hr>`;
        
        const serviceLabels = {
            socialMedia: 'Social Media',
            youtube: 'YouTube',
            pinterest: 'Pinterest',
            twitter: 'Twitter (X)',
            gmb: 'Google My Business',
            googleAds: 'Google Ads',
            metaAds: 'Meta Ads',
            videos: 'Videos'
        };
        
        const serviceIcons = {
            socialMedia: 'ri-instagram-line',
            youtube: 'ri-youtube-line',
            pinterest: 'ri-pinterest-line',
            twitter: 'ri-twitter-x-line',
            gmb: 'ri-google-line',
            googleAds: 'ri-google-line',
            metaAds: 'ri-facebook-circle-line',
            videos: 'ri-video-line'
        };
        
        selectedServices.forEach(serviceId => {
            const creds = credentials.filter(c => c.serviceType === serviceId);
            const label = serviceLabels[serviceId] || serviceId;
            const icon = serviceIcons[serviceId] || 'ri-service-line';
            
            html += `
                <div class="card mb-3 border-${creds.length > 0 ? 'success' : 'secondary'}">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <i class="${icon} me-1"></i>
                            <strong>${label}</strong>
                            <span class="badge ${creds.length > 0 ? 'bg-success' : 'bg-secondary'} ms-2">
                                ${creds.length > 0 ? 'Submitted' : 'Not Submitted'}
                            </span>
                        </div>
                    </div>
                    <div class="card-body py-2">
            `;
            
            if (creds.length > 0) {
                creds.forEach((cred, index) => {
                    const credId = 'cred_' + serviceId + '_' + index;
                    
                    html += `
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="fw-bold small text-muted">Platform</label>
                                <div class="border rounded p-1">${cred.platform || '-'}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold small text-muted">Username</label>
                                <div class="border rounded p-1">${cred.username || '-'}</div>
                            </div>
                            <div class="col-md-4">
                                <label class="fw-bold small text-muted">Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control form-control-sm" 
                                           value="${cred.password || ''}" 
                                           id="${credId}_password"
                                           readonly>
                                    <button class="btn btn-outline-secondary btn-sm" 
                                            type="button" 
                                            onclick="toggleViewPassword('${credId}_password')">
                                        <i class="ri-eye-line" id="${credId}_eye"></i>
                                    </button>
                                    <button class="btn btn-outline-primary btn-sm" 
                                            type="button" 
                                            onclick="copyToClipboard('${credId}_password')">
                                        <i class="ri-file-copy-line"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // YouTube specific fields
                    if (cred.youtubeGrantAccess) {
                        html += `
                            <div class="row mt-2">
                                <div class="col-12">
                                    <span class="badge bg-success">
                                        <i class="ri-check-line"></i> YouTube Access Granted
                                    </span>
                                    <small class="text-muted d-block">Emails: uday.work@gmail.com, social@mqlus.in</small>
                                    ${cred.youtubeScreenshot ? `
                                        <a href="/uploads/onboarding/youtube-screenshots/${cred.youtubeScreenshot}" 
                                           target="_blank" class="btn btn-sm btn-outline-primary mt-1">
                                            <i class="ri-image-line"></i> View Screenshot
                                        </a>
                                        <a href="/uploads/onboarding/youtube-screenshots/${cred.youtubeScreenshot}" 
                                           download class="btn btn-sm btn-outline-success mt-1">
                                            <i class="ri-download-line"></i> Download
                                        </a>
                                    ` : ''}
                                </div>
                            </div>
                        `;
                    }
                    
                    // Facebook + Instagram fields for Meta Ads
                    if (cred.fbUsername || cred.igUsername) {
                        html += `
                            <div class="row mt-2 g-2">
                                ${cred.fbUsername ? `
                                <div class="col-md-6">
                                    <label class="fw-bold small text-muted">Facebook Username</label>
                                    <div class="border rounded p-1">${cred.fbUsername}</div>
                                </div>
                                ` : ''}
                                ${cred.fbPassword ? `
                                <div class="col-md-6">
                                    <label class="fw-bold small text-muted">Facebook Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-sm" 
                                               value="${cred.fbPassword}" 
                                               id="${credId}_fbPassword"
                                               readonly>
                                        <button class="btn btn-outline-secondary btn-sm" 
                                                type="button" 
                                                onclick="toggleViewPassword('${credId}_fbPassword')">
                                            <i class="ri-eye-line" id="${credId}_fbEye"></i>
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm" 
                                                type="button" 
                                                onclick="copyToClipboard('${credId}_fbPassword')">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                ` : ''}
                                ${cred.igUsername ? `
                                <div class="col-md-6">
                                    <label class="fw-bold small text-muted">Instagram Username</label>
                                    <div class="border rounded p-1">${cred.igUsername}</div>
                                </div>
                                ` : ''}
                                ${cred.igPassword ? `
                                <div class="col-md-6">
                                    <label class="fw-bold small text-muted">Instagram Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control form-control-sm" 
                                               value="${cred.igPassword}" 
                                               id="${credId}_igPassword"
                                               readonly>
                                        <button class="btn btn-outline-secondary btn-sm" 
                                                type="button" 
                                                onclick="toggleViewPassword('${credId}_igPassword')">
                                            <i class="ri-eye-line" id="${credId}_igEye"></i>
                                        </button>
                                        <button class="btn btn-outline-primary btn-sm" 
                                                type="button" 
                                                onclick="copyToClipboard('${credId}_igPassword')">
                                            <i class="ri-file-copy-line"></i>
                                        </button>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                        `;
                    }
                    
                    html += `<hr class="my-2">`;
                });
            } else {
                html += `<p class="text-muted mb-0">No credentials submitted</p>`;
            }
            
            html += `</div></div>`;
        });
        
        html += `</div>`;
        return html;
    }
    
    // ============================================================
    // TOGGLE PASSWORD VISIBILITY IN VIEW MODAL
    // ============================================================
    window.toggleViewPassword = function(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        const eyeId = inputId.replace('_password', '_eye').replace('_fbPassword', '_fbEye').replace('_igPassword', '_igEye');
        const eye = document.getElementById(eyeId);
        
        if (input.type === 'password') {
            input.type = 'text';
            if (eye) {
                eye.className = 'ri-eye-off-line';
            }
        } else {
            input.type = 'password';
            if (eye) {
                eye.className = 'ri-eye-line';
            }
        }
    };
    
    // ============================================================
    // COPY TO CLIPBOARD
    // ============================================================
    window.copyToClipboard = function(inputId) {
        const input = document.getElementById(inputId);
        if (!input) return;
        
        // Temporarily change type to text to copy
        const originalType = input.type;
        if (originalType === 'password') {
            input.type = 'text';
        }
        
        input.select();
        document.execCommand('copy');
        
        // Reset type
        input.type = originalType;
        
        // Show feedback
        showToast('Password copied to clipboard!', 'success');
    };
    
    
    
    
});