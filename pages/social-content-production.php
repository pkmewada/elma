<?php
/*
|--------------------------------------------------------------------------
| Social Content Production — Manager view
|--------------------------------------------------------------------------
|
| The "manufacturing" stage between raw content entry (clientSocialContent,
| filled in on social-data-entry.php / social-overview.php) and publishing
| (socialPosts, handled entirely by SocialPostEngine.php — untouched here).
|
| This page never writes to socialPosts. PRODUCTION_READY is a business
| state only; the handoff to Social Media Automation is a future phase.
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
                <h1 class="page-title fw-medium fs-18 mb-2">Content Production</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item">Social Media</li>
                    <li class="breadcrumb-item active" aria-current="page">Content Production</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="social-data-entry" class="btn btn-light btn-sm">
                    <i class="ri-edit-box-line me-1"></i> Data Entry
                </a>
                <!-- <a href="social-overview" class="btn btn-light btn-sm">
                    <i class="ri-grid-line me-1"></i> Overview
                </a> -->
            </div>
        </div>

        <!-- ============================ FILTER BAR ============================ -->
        <div class="card custom-card scp-filterbar">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-xl-2 col-md-4">
                        <label for="scpDateRange">Date Range</label>
                        <input type="text" class="form-control form-control-sm" id="scpDateRange"
                               placeholder="Select date or range" autocomplete="off">
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpStatus">Status</label>
                        <select class="form-select form-select-sm" id="scpStatus">
                            <option value="">All Status</option>
                            <option value="NEW">New</option>
                            <option value="ASSIGNED">Assigned</option>
                            <option value="IN_PROGRESS">In Progress</option>
                            <option value="SUBMITTED">Submitted</option>
                            <option value="CORRECTION">Correction</option>
                            <option value="APPROVED">Approved</option>
                            <option value="PRODUCTION_READY">Production Ready</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpEditor">Editor</label>
                        <select class="form-select form-select-sm" id="scpEditor">
                            <option value="">All Editors</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpClient">Client</label>
                        <select class="form-select form-select-sm" id="scpClient">
                            <option value="">All Clients</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpPlatform">Platform</label>
                        <select class="form-select form-select-sm" id="scpPlatform">
                            <option value="">All Platforms</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4">
                        <label for="scpSource">Source</label>
                        <select class="form-select form-select-sm" id="scpSource">
                            <option value="">All Sources</option>
                            <option value="social">Social Content</option>
                            <option value="other">Other Content</option>
                            <option value="graphic">Other Graphic Content</option>
                        </select>
                    </div>
                    <div class="col-xl-2 col-md-4 d-flex align-items-center justify-content-between gap-2 pt-3 pt-xl-0">
                        <label class="d-flex align-items-center gap-1 fs-13 text-muted mb-0">
                            <input type="checkbox" class="form-check-input" id="scpOverdue"> Overdue only
                        </label>
                        <button type="button" class="btn btn-sm btn-primary" id="scpRefreshBtn">
                            <i class="ri-refresh-line"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================== PRODUCTION SUMMARY (Phase 6) ====================== -->
        <!-- Server-derived counts only -- never computed from the client-side
             tasks[] array, since that array reflects whatever status/editor
             filter is currently active. See api/social-content-production/get-summary.php.
             Calendar-planned Social Content tasks (sourceType='social'). -->
        <div class="fs-13 fw-semibold text-muted mb-2">Regular Graphic</div>
        <div class="row g-2 mb-3" id="scpSummaryRow">
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpCountNEW">—</div>
                        <div class="fs-11 text-muted text-uppercase">New</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpCountASSIGNED">—</div>
                        <div class="fs-11 text-muted text-uppercase">Assigned</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpCountIN_PROGRESS">—</div>
                        <div class="fs-11 text-muted text-uppercase">In Progress</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpCountSUBMITTED">—</div>
                        <div class="fs-11 text-muted text-uppercase">Submitted</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold text-danger" id="scpCountCORRECTION">—</div>
                        <div class="fs-11 text-muted text-uppercase">Correction</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold text-success" id="scpCountAPPROVED">—</div>
                        <div class="fs-11 text-muted text-uppercase">Approved</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpCountPRODUCTION_READY">—</div>
                        <div class="fs-11 text-muted text-uppercase">Ready</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0 border-danger-transparent">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold text-danger" id="scpCountOverdue">—</div>
                        <div class="fs-11 text-muted text-uppercase">Overdue</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================== OTHER GRAPHIC CONTENT SUMMARY ======================
             Same status breakdown as the rows above, scoped to sourceType='graphic'
             (SocialContentProductionEngine::getProductionSummary()'s
             graphicStatusCounts/graphicOverdueCount) -- a separate row, never mixed
             into the Social Content or Other Content counts. This source DOES feed
             new production tasks (see api/graphic-content/save-entry.php). -->
        <div class="fs-13 fw-semibold text-muted mb-2">Other Graphic Content</div>
        <div class="row g-2 mb-3" id="scpGraphicSummaryRow">
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpGraphicCountNEW">—</div>
                        <div class="fs-11 text-muted text-uppercase">New</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpGraphicCountASSIGNED">—</div>
                        <div class="fs-11 text-muted text-uppercase">Assigned</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpGraphicCountIN_PROGRESS">—</div>
                        <div class="fs-11 text-muted text-uppercase">In Progress</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpGraphicCountSUBMITTED">—</div>
                        <div class="fs-11 text-muted text-uppercase">Submitted</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold text-danger" id="scpGraphicCountCORRECTION">—</div>
                        <div class="fs-11 text-muted text-uppercase">Correction</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold text-success" id="scpGraphicCountAPPROVED">—</div>
                        <div class="fs-11 text-muted text-uppercase">Approved</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold" id="scpGraphicCountPRODUCTION_READY">—</div>
                        <div class="fs-11 text-muted text-uppercase">Ready</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl">
                <div class="card custom-card mb-0 border-danger-transparent">
                    <div class="card-body py-2 px-3 text-center">
                        <div class="fs-18 fw-semibold text-danger" id="scpGraphicCountOverdue">—</div>
                        <div class="fs-11 text-muted text-uppercase">Overdue</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================== EDITOR WORKLOAD (Phase 6) ====================== -->
        <div class="card custom-card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">Editor Workload</h5>
                <span class="fs-12 text-muted">Active Video Editors — pending work only (Production Ready excluded)</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Editor</th>
                                <th class="text-center">Assigned</th>
                                <th class="text-center">In Progress</th>
                                <th class="text-center">Submitted</th>
                                <th class="text-center">Correction</th>
                                <th class="text-center">Overdue</th>
                            </tr>
                        </thead>
                        <tbody id="scpWorkloadBody">
                            <tr><td colspan="6" class="text-center text-muted py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header">
                <h5 class="mb-0">Production Queue</h5>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Content</th>
                                <th class="text-center">View Content</th>
                                <th>Editor</th>
                                <th class="text-center">Status</th>
                                <th>Date Time</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody id="scpBody">
                            <tr><td colspan="7" class="text-center text-muted py-4">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Assign / Reassign -->
<div class="modal fade" id="scpAssignModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title" id="scpAssignTitle">Assign Editor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scpAssignId">
                <div class="mb-3">
                    <label class="form-label" for="scpAssignEditor">Video Editor <span class="text-danger">*</span></label>
                    <select class="form-select" id="scpAssignEditor"></select>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="scpAssignDue">Due (TAT)</label>
                    <input type="datetime-local" class="form-control" id="scpAssignDue">
                </div>
                <div class="mb-0">
                    <label class="form-label" for="scpAssignRemark">Note (optional)</label>
                    <textarea class="form-control" id="scpAssignRemark" rows="2" placeholder="e.g. Assigned for product reel"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="scpAssignSaveBtn">Save Assignment</button>
            </div>
        </div>
    </div>
</div>

<!-- Production Output -- replaces the old Review (approve/correction) modal.
     Shows the editor's submitted output, plus the Review status dropdown
     that sets socialContentProduction.reviewStatus (see
     SocialContentProductionEngine::updateReviewStatus()). -->
<div class="modal fade" id="scpOutputModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production Output</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="scpOutputId">
                <div id="scpOutputBody" class="mb-3"></div>

                <div class="mb-3">
                    <label class="form-label" for="scpOutputStatus">Status</label>
                    <select class="form-select" id="scpOutputStatus">
                        <option value="Open">Open</option>
                        <option value="Approved By Team">Approved By Team</option>
                        <option value="Approved By Client">Approved By Client</option>
                        <option value="Approval Pending From Client">Approval Pending From Client</option>
                        <option value="Not Approved">Not Approved</option>
                        <option value="Not For Use">Not For Use</option>
                    </select>
                </div>

                <div class="mb-0 d-none" id="scpOutputRemarkWrap">
                    <label class="form-label" for="scpOutputRemark" id="scpOutputRemarkLabel">Remark <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="scpOutputRemark" rows="3" placeholder="Explain what needs to change..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" id="scpOutputSubmitBtn">Submit</button>
            </div>
        </div>
    </div>
</div>

<!-- Detail / View — Content Brief only. Task Overview and Production Output
     used to also live here; Production Output now has its own dedicated
     modal above (opened from the Updated Work column), and Task Overview
     was dropped as redundant with it. -->
<div class="modal fade" id="scpDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Content Brief</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="scpDetailBrief"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Production History (separate from the detail modal above — a task's
     history can grow long, and mixes with brief/output review poorly) -->
<div class="modal fade" id="scpHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Production History</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="scpHistoryTimeline"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Other Graphic Content only — Action column choice. Send to Automation
     reuses the existing, unmodified send-to-automation.php; Send to Client
     records a Production History entry only (no client-delivery mechanism
     exists in this codebase — see includes/SocialContentProductionEngine.php's
     recordExternalEvent()). -->
<div class="modal fade" id="scpGraphicChoiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">What do you want to do with this content?</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body d-flex flex-column gap-2">
                <button type="button" class="btn btn-dark scp-send-automation" id="scpGraphicSendAutomation">
                    <i class="ri-send-plane-line me-1"></i> Send to Automation
                </button>
                <button type="button" class="btn btn-outline-secondary scp-send-client" id="scpGraphicSendClient">
                    <i class="ri-share-forward-line me-1"></i> Send to Client
                </button>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
    /* simple vertical timeline for Production History — a border-left line
       with a dot per item, no charting/timeline library needed */
    .scp-timeline { position: relative; }
    .scp-timeline-item { position: relative; padding-left: 1.25rem; padding-bottom: 1.1rem; }
    .scp-timeline-item:last-child { padding-bottom: 0; }
    .scp-timeline-item::before {
        content: '';
        position: absolute;
        left: 3px;
        top: 0.95rem;
        bottom: -0.1rem;
        width: 2px;
        background: var(--default-border);
    }
    .scp-timeline-item:last-child::before { display: none; }
    .scp-timeline-item::after {
        content: '';
        position: absolute;
        left: 0;
        top: 0.3rem;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--primary-color, #6c5ffc);
    }
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= ASSET_URL ?>/assets/js/social-production-status.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/social-content-production.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
