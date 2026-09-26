<div class="modal fade" id="serviceSelectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <!-- Header -->
            <div class="modal-header border-bottom">
                <div class="flex-grow-1">
                    <h4 class="mb-2">Service Selection</h4>
                    <div class="fw-semibold fs-16" id="serviceModalClientName">Client Name</div>
                    <div class="text-muted mb-2" id="serviceModalClientCode">Client Code: -</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <!-- Body -->
            <div class="modal-body">
                <div id="serviceSelectionContent">
                    <!-- Service selection UI will be rendered here -->
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <!-- ✅ REMOVED: Save Services button -->
                <button type="button" class="btn btn-primary" id="sendServiceFormBtn" onclick="sendServiceForm()">
                    <i class="ri-send-plane-line me-1"></i> Save & Send Form to Client
                </button>
            </div>
        </div>
    </div>
</div>