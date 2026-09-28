<?php
/*
|--------------------------------------------------------------------------
| Shared Follow-ups page body (admin /lead-follow-up-list, employee
| /emp-follow-ups). Expects $followUpHomeUrl and $followUpLeadsUrl.
| Data: api/leads/getLeadFollowUps.php (same lead scope as the lead APIs).
|--------------------------------------------------------------------------
*/
$fuEsc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Follow-ups</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= $fuEsc($followUpHomeUrl) ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Follow-ups</li>
                </ol>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header justify-content-between flex-wrap gap-2">
                <ul class="nav nav-tabs tab-style-1 mb-0 border-0" role="tablist" id="followUpViewTabs">
                    <li class="nav-item"><a class="nav-link active" href="javascript:void(0);" data-view="today">Today <span class="badge bg-primary-transparent ms-1" data-count="today">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="javascript:void(0);" data-view="upcoming">Upcoming <span class="badge bg-info-transparent ms-1" data-count="upcoming">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="javascript:void(0);" data-view="overdue">Overdue <span class="badge bg-danger-transparent ms-1" data-count="overdue">0</span></a></li>
                    <li class="nav-item"><a class="nav-link" href="javascript:void(0);" data-view="completed">Completed <span class="badge bg-success-transparent ms-1" data-count="completed">0</span></a></li>
                </ul>
                <input type="text" id="followUpSearch" class="form-control form-control-sm" style="max-width: 240px" placeholder="Search name or phone..." autocomplete="off" />
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="followUpTable" data-ui-table="mamix" class="table table-hover text-wrap">
                        <thead>
                            <tr>
                                <th>Due</th>
                                <th>Lead</th>
                                <th>Project</th>
                                <th>Assigned To</th>
                                <th>Type</th>
                                <th>Remark</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Complete follow-up -->
        <div class="modal fade" id="completeFollowUpModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Complete Follow-up</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="completeFollowUpId" />
                        <input type="hidden" id="completeFollowUpStatus" />
                        <div class="fw-semibold mb-3" id="completeFollowUpLead"></div>
                        <label class="form-label" for="completeFollowUpRemark">Outcome / Remark</label>
                        <textarea class="form-control" id="completeFollowUpRemark" rows="3" maxlength="2000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="saveCompleteFollowUpBtn">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script>var FOLLOW_UP_LEADS_URL = <?= json_encode($followUpLeadsUrl) ?>;</script>
<script src="<?= ASSET_URL ?>/assets/js/lead-follow-up-list.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/lead-follow-up-list.js') ?>"></script>
