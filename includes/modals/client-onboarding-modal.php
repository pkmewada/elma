<div class="modal fade" id="clientOnboardingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <!-- Header - Same as before -->
            <div class="modal-header border-bottom">
                <div class="flex-grow-1">
                    <h4 class="mb-2">Client Onboarding</h4>
                    <div class="fw-semibold fs-16" id="modalClientName">Client Name</div>
                    <div class="text-muted mb-2" id="modalOrgName">Organization Name</div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-primary">Client Code: <span id="modalClientCode">-</span></span>
                        <span class="badge bg-success" id="modalStatus">Active</span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="javascript:void(0);" id="downloadAgreementBtn" class="btn btn-success" target="_blank">
                        <i class="ri-file-pdf-line me-1"></i> Signed Agreement
                    </a>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>

            <!-- Quick Information -->
            <div class="border-bottom px-4 py-3">
                <div class="row g-3">
                    <div class="col-lg-3">
                        <small class="text-muted d-block">Email</small>
                        <div class="fw-medium" id="modalEmail">-</div>
                    </div>
                    <div class="col-lg-3">
                        <small class="text-muted d-block">Phone</small>
                        <div class="fw-medium" id="modalPhone">-</div>
                    </div>
                    <div class="col-lg-3">
                        <small class="text-muted d-block">Plan Value</small>
                        <div class="fw-medium" id="modalFinalPrice">-</div>
                    </div>
                    <div class="col-lg-3">
                        <small class="text-muted d-block">Onboarded On</small>
                        <div class="fw-medium" id="modalOnboardedAt">-</div>
                    </div>
                </div>
            </div>

            <!-- Body - No sidebar, just content -->
            <div class="modal-body p-4">
                <div id="viewContent">
                    <!-- All client data will be rendered here -->
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>