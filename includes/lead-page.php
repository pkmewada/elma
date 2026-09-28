<?php
/*
|--------------------------------------------------------------------------
| Shared lead page body (admin /leads and employee /emp-leads)
|--------------------------------------------------------------------------
| Included after the layout header + sidebar. Expects:
|   $leadPageTitle       page heading
|   $leadPageHomeUrl     breadcrumb "Dashboard" link
|   $leadPageFollowUpUrl Follow-ups page for this layout
| Options (projects, sources, statuses, assignees, permissions) are loaded
| by dist/assets/js/lead.js from api/leads/getLeadMasterData.php; server-side
| checks in every API are the real authorisation.
*/
require_once __DIR__ . '/leadAccess.php';

$leadPageCanImport = hasActionPermission(leadCallerRoute(), 'import_leads');
$leadPageCanAdd = hasRoutePermission(leadCallerRoute(), 'canAdd');
$leadPageShowEmployeeFilter = canAccessAllLeads();
$leadPageDateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['dateFrom'] ?? '')) ? $_GET['dateFrom'] : '';
$leadPageDateTo = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['dateTo'] ?? '')) ? $_GET['dateTo'] : '';
$leadPagePrefill = [
    'status' => isset(LEAD_STATUSES[$_GET['status'] ?? '']) ? $_GET['status'] : '',
    'source' => trim((string)($_GET['source'] ?? '')),
    'employeeId' => preg_match('/^(\d+|unassigned)$/', (string)($_GET['employeeId'] ?? '')) ? $_GET['employeeId'] : '',
];
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css" />
<style>
    .lead-status-btn { font-size: 13px; font-weight: 600; }
    @media (min-width: 1200px) { #leadStatusCards > .col { flex: 1 0 0%; } }
    .lead-status-card { cursor: pointer; }
    .lead-status-card.active { border: 1px solid var(--primary-color); }
    .lead-actions { white-space: nowrap; }
</style>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2"><?= $esc($leadPageTitle) ?></h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= $esc($leadPageHomeUrl) ?>">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= $esc($leadPageTitle) ?></li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <a href="<?= $esc($leadPageFollowUpUrl) ?>" class="btn btn-warning btn-wave" id="leadFollowUpsBtn" title="Follow-ups">
                    <i class="ri-calendar-event-line me-1"></i> Follow-ups
                </a>
                <?php if ($leadPageCanImport): ?>
                <button type="button" class="btn btn-success btn-wave" data-bs-toggle="modal" data-bs-target="#importLeadModal" title="Import Leads">
                    <i class="ri-upload-cloud-2-line"></i>
                </button>
                <?php endif; ?>
                <?php if ($leadPageCanAdd): ?>
                <button type="button" class="btn btn-primary btn-wave" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                    <i class="ri-user-add-line me-1"></i> Add Lead
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">
                        <div class="crm-filter-bar">
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Export</button>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item export-btn" data-type="csv" href="javascript:void(0);">CSV</a></li>
                                    <li><a class="dropdown-item export-btn" data-type="excel" href="javascript:void(0);">Excel</a></li>
                                    <li><a class="dropdown-item export-btn" data-type="pdf" href="javascript:void(0);">PDF</a></li>
                                </ul>
                            </div>
                            <select id="statusFilter" class="form-select"><option value="">All Status</option></select>
                            <select id="sourceFilter" class="form-select"><option value="">Source</option></select>
                            <select id="projectFilter" class="form-select"><option value="">Project</option></select>
                            <?php if ($leadPageShowEmployeeFilter): ?>
                            <select id="employeeFilter" class="form-select"><option value="">Assigned To</option><option value="unassigned">Unassigned</option></select>
                            <?php endif; ?>
                            <div class="crm-filter-field">
                                <input type="text" id="leadDateRangeFilter" class="form-control" placeholder="Date range" aria-label="Date range"
                                    data-date-from="<?= $esc($leadPageDateFrom) ?>" data-date-to="<?= $esc($leadPageDateTo) ?>" />
                            </div>
                            <button type="button" id="leadsFilterResetBtn" class="btn btn-icon btn-light" title="Reset Filters"><i class="ri-refresh-line"></i></button>
                            <input id="tableSearch" class="form-control crm-filter-search" placeholder="Search leads..." autocomplete="off" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-2 row-cols-md-3 g-3 mb-3" id="leadStatusCards"></div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between"><div class="card-title">Leads</div></div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="leads-datatable" data-ui-table="mamix" class="table table-hover text-wrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Customer</th>
                                        <th>Assigned To</th>
                                        <th>Project</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Next Follow-up</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add / Edit Lead Modal -->
        <div class="modal fade" id="addLeadModal" tabindex="-1" aria-labelledby="addLeadModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addLeadModalLabel">Add Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addLeadForm" novalidate>
                        <input type="hidden" id="leadId" name="id" value="" />
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-12"><h6 class="fw-semibold text-primary mb-0">Customer Details</h6></div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-fullName" class="form-label">Customer Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="modal-fullName" name="fullName" maxlength="100" placeholder="Enter customer name" required />
                                    <div class="invalid-feedback">Customer name is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="modal-email" name="email" maxlength="150" placeholder="Enter email address" />
                                    <div class="invalid-feedback">Enter a valid email.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-country" class="form-label">Country <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="modal-country" name="country" list="lead-country-options" placeholder="Type to search country..." autocomplete="off" required />
                                    <datalist id="lead-country-options"></datalist>
                                    <div class="invalid-feedback">Please select a valid country from the list.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-phone" class="form-label">Phone <span class="text-danger">*</span></label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text" id="modal-countryCodeDisplay">+--</span>
                                        <input type="tel" class="form-control" id="modal-phone" name="phone" placeholder="Contact number or +country code number" pattern="[0-9]{6,15}" required />
                                        <div class="invalid-feedback">Select a country, then enter a valid contact number (6-15 digits).</div>
                                    </div>
                                    <input type="hidden" id="modal-countryCode" name="countryCode" />
                                </div>

                                <div class="col-12"><h6 class="fw-semibold text-primary mb-0 mt-2">Enquiry Details</h6></div>
                                <div class="col-12 col-md-4">
                                    <label for="modal-projectId" class="form-label">Project</label>
                                    <select class="form-select" id="modal-projectId" name="projectId"><option value="">Not decided yet</option></select>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="modal-sourceId" class="form-label">Source <span class="text-danger">*</span></label>
                                    <select class="form-select" id="modal-sourceId" name="sourceId" required><option value="">Select source</option></select>
                                    <div class="invalid-feedback">Source is required.</div>
                                </div>
                                <div class="col-12 col-md-4 lead-create-only">
                                    <label for="modal-status" class="form-label">Status</label>
                                    <select class="form-select" id="modal-status" name="status"></select>
                                </div>

                                <div class="col-12 col-md-6 lead-create-only lead-assign-field d-none">
                                    <label for="modal-assignedToId" class="form-label">Assigned Salesperson</label>
                                    <select class="form-select" id="modal-assignedToId" name="assignedToId"><option value="">Unassigned</option></select>
                                </div>
                                <div class="col-12 col-md-6 lead-create-only">
                                    <label for="modal-nextFollowUp" class="form-label">Next Follow-up</label>
                                    <input type="datetime-local" class="form-control" id="modal-nextFollowUp" name="nextFollowUp" />
                                </div>
                                <div class="col-12 lead-create-only">
                                    <label for="modal-remark" class="form-label">Remark</label>
                                    <textarea class="form-control" id="modal-remark" name="remark" rows="2" maxlength="2000" placeholder="Requirement, budget, preferred configuration..."></textarea>
                                    <div class="form-text">Required when the status is Converted or Lost.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="addLeadSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="addLeadSubmitSpinner" role="status" aria-hidden="true"></span>
                                <span id="addLeadSubmitText">Save Lead</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Delete Lead Confirmation Modal -->
        <div class="modal fade" id="deleteConfirmModal" data-bs-effect="effect-super-scaled">
            <div class="modal-dialog modal-dialog-centered text-center" role="document">
                <div class="modal-content modal-content-demo">
                    <div class="modal-header">
                        <h6 class="modal-title">Delete Lead</h6>
                        <button aria-label="Close" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <h6>Are you sure you want to delete this lead?</h6>
                        <p class="text-muted mb-0">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Remarks & Follow-ups Modal -->
        <div class="modal fade" id="leadRemarkModal" data-bs-effect="effect-super-scaled" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Remarks &amp; Follow-ups</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="remarkLeadId" />
                        <div class="fw-semibold mb-3" id="remarkLeadName"></div>
                        <div class="row g-3 mb-3">
                            <div class="col-12 col-md-8">
                                <label class="form-label" for="leadRemark">Remark</label>
                                <textarea class="form-control" id="leadRemark" rows="3" maxlength="2000"></textarea>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="followUpDateTime">Schedule Follow-up</label>
                                <input type="datetime-local" class="form-control" id="followUpDateTime" />
                                <button type="button" class="btn btn-primary w-100 mt-3" id="saveRemarkBtn">Save</button>
                            </div>
                        </div>
                        <h6 class="fw-semibold">Follow-ups</h6>
                        <div id="leadFollowUpList" class="mb-3"></div>
                        <h6 class="fw-semibold">History</h6>
                        <div id="remarkTimeline"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Modal -->
        <div class="modal fade" id="leadDocumentsModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Lead Documents</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="documentLeadId" />
                        <div class="fw-semibold mb-3" id="documentLeadName"></div>
                        <div class="row g-2 mb-3">
                            <div class="col-12 col-md-9"><input type="file" class="form-control" id="leadDocumentFile" accept=".pdf" /></div>
                            <div class="col-12 col-md-3"><button type="button" class="btn btn-primary w-100" id="uploadLeadDocumentBtn">Upload PDF</button></div>
                        </div>
                        <div id="leadDocumentsContainer"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status (Converted / Lost) Modal -->
        <div class="modal fade" id="leadStatusModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leadStatusModalTitle">Status Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="statusLeadId" />
                        <input type="hidden" id="selectedLeadStatus" />
                        <div class="mb-3"><span class="form-label fw-semibold">Status:</span> <span id="selectedStatusText" class="fw-bold"></span></div>
                        <label class="form-label" for="statusRemark">Remark / Reason <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="statusRemark" rows="4" maxlength="2000"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveLeadStatusBtn">Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign / Reassign Modal -->
        <div class="modal fade" id="assignLeadModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Assign Lead</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="assignLeadId" />
                        <div class="fw-semibold mb-1" id="assignLeadName"></div>
                        <div class="text-muted small mb-3">Currently: <span id="assignCurrentName"></span></div>
                        <label class="form-label" for="assignEmployeeId">Salesperson</label>
                        <select class="form-select" id="assignEmployeeId"><option value="0">Unassigned</option></select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="saveAssignLeadBtn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($leadPageCanImport): ?>
        <!-- Import Leads Modal -->
        <div class="modal fade" id="importLeadModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Import Leads</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="importLeadForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="mb-3 lead-assign-field d-none">
                                <label class="form-label" for="importEmployeeId">Assign imported leads to</label>
                                <select class="form-select" id="importEmployeeId" name="employeeId"><option value="">Unassigned (or per-row assignedTo)</option></select>
                            </div>
                            <div class="mb-2">
                                <label class="form-label" for="leadCsvFile">CSV File</label>
                                <input type="file" class="form-control" id="leadCsvFile" name="leadCsvFile" accept=".csv" required />
                            </div>
                            <div class="small text-muted">
                                Columns: <code>name</code>, <code>phone</code> (required), <code>email</code>, <code>countryCode</code>, <code>country</code>,
                                <code>project</code>, <code>source</code>, <code>status</code>, <code>assignedTo</code> (employee email), <code>remark</code>.
                                Values must match existing projects / sources / statuses exactly.
                                <a href="<?= BASE_URL ?>/api/leads/downloadLeadImportTemplate.php">Download template</a>
                            </div>
                            <div id="importLeadErrors" class="alert alert-warning small mt-3 mb-0 d-none"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" id="importLeadSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="importLeadSpinner"></span> Import Leads
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script>
    var leadsFilterPrefill = <?= json_encode($leadPagePrefill) ?>;
    var WHATSAPP_CHAT_URL = <?= json_encode($leadPageWhatsappUrl ?? 'whatsapp') ?>;
</script>
<script src="<?= ASSET_URL ?>/assets/js/lead-country-data.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/lead-country-data.js') ?>"></script>
<script src="<?= ASSET_URL ?>/assets/js/lead.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/lead.js') ?>"></script>
