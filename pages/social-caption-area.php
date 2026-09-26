<?php
/*
|--------------------------------------------------------------------------
| Caption Area — Phase 1 foundation
|--------------------------------------------------------------------------
|
| Sits between Production approval and (future) Automation:
|   Social Content Production -> Approved Content -> Caption Area ->
|   Caption Selected -> Future Automation
|
| Social Content only (sourceType='social') -- Other Content and Other
| Graphic Content are out of scope. Shows only tasks whose reviewStatus is
| "Approved By Team" or "Approved By Client" (see
| includes/socialContentCaptionEngine.php's ELIGIBLE_REVIEW_STATUSES).
|
| No AI integration in this phase. "Generate Caption" writes static
| placeholder text into two option slots -- there is no external API call
| anywhere in this flow. This page never writes to socialContentProduction
| and never touches Automation.
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
                <h1 class="page-title fw-medium fs-18 mb-2">Caption Area</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Caption Area</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="social-content-production" class="btn btn-light btn-sm">
                    <i class="ri-clapperboard-line me-1"></i> Content Production
                </a>
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-3 col-md-4">
                        <label for="scaDateRange">Content Date Range</label>
                        <input type="text" class="form-control form-control-sm" id="scaDateRange"
                               placeholder="Select date or range" autocomplete="off">
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="scaClient">Client</label>
                        <select class="form-select form-select-sm" id="scaClient">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4">
                        <label for="scaPlatform">Platform</label>
                        <select class="form-select form-select-sm" id="scaPlatform">
                            <option value="">All Platforms</option>
                        </select>
                    </div>
                    <div class="col-xl-3 col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-sm btn-primary" id="scaRefreshBtn">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <h5 class="mb-0">Approved Content — Caption Queue</h5>
                <span class="fs-12 text-muted">Only Social Content approved by the team or the client shows here.</span>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Content</th>
                                <th>Platform</th>
                                <th>Posting Type</th>
                                <th>Content Date</th>
                                <th>Caption Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="scaBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Caption modal -->
<div class="modal fade" id="scaCaptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="ri-chat-quote-line me-2 text-primary"></i> Caption</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scaProductionId">

                <h6 class="fs-13 text-muted text-uppercase mb-2">Content Information</h6>
                <div id="scaContentInfo" class="row g-2 mb-3"></div>

                <h6 class="fs-13 text-muted text-uppercase mb-2">Content Details</h6>
                <div id="scaContentBrief" class="mb-3"></div>

                <hr>

                <label class="form-label" for="scaPrompt">Prompt <span class="text-danger">*</span></label>
                <textarea class="form-control mb-2" id="scaPrompt" rows="3" placeholder="Create an engaging Instagram caption with CTA"></textarea>
                <div class="d-flex justify-content-end align-items-center gap-2 mb-1">
                    <span class="fs-12 text-muted" id="scaRegenCount"></span>
                    <button type="button" class="btn btn-sm btn-primary" id="scaGenerateBtn">
                        <i class="ri-sparkling-2-line me-1"></i> Generate Caption
                    </button>
                </div>
                <div class="d-flex justify-content-end mb-3">
                    <span class="fs-12 text-danger d-none" id="scaRegenLimitMsg">Caption regeneration limit reached.</span>
                </div>

                <!-- Shown only while nothing is selected yet (status='pending') --
                     both options side by side, unchanged selection flow. -->
                <div id="scaOptionsWrap" class="d-none">
                    <h6 class="fs-13 text-muted text-uppercase mb-2">Caption Options</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="card custom-card border h-100" id="scaOptionOneCard">
                                <div class="card-body">
                                    <div class="fs-11 text-uppercase text-muted fw-semibold mb-2">Option 1</div>
                                    <p class="fs-13 mb-3" id="scaOptionOneText">Generated caption will appear here</p>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100 sca-select" data-option="one">
                                        <i class="ri-checkbox-circle-line me-1"></i> Select This Caption
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card custom-card border h-100" id="scaOptionTwoCard">
                                <div class="card-body">
                                    <div class="fs-11 text-uppercase text-muted fw-semibold mb-2">Option 2</div>
                                    <p class="fs-13 mb-3" id="scaOptionTwoText">Generated caption will appear here</p>
                                    <button type="button" class="btn btn-sm btn-outline-success w-100 sca-select" data-option="two">
                                        <i class="ri-checkbox-circle-line me-1"></i> Select This Caption
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shown only once a caption is selected (status='selected') --
                     the unselected option is hidden entirely (still preserved
                     in the database, just not shown), and the selected text is
                     editable + saveable here. -->
                <div id="scaSelectedWrap" class="d-none mt-3">
                    <h6 class="fs-13 text-muted text-uppercase mb-2">Selected Caption</h6>
                    <textarea class="form-control mb-2" id="scaSelectedCaptionText" rows="3"></textarea>
                    <div class="d-flex justify-content-end">
                        <button type="button" class="btn btn-sm btn-success" id="scaSaveCaptionBtn">
                            <i class="ri-save-line me-1"></i> Save Caption
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/social-caption-area.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
