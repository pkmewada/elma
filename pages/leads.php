<?php
include __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . '/../includes/leadActivityLogger.php';

$allowedStatuses = ["open", "interested", "connected", "converted", "not_interested", "not_connected"];
$updateError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $leadId = (int) ($_POST["id"] ?? 0);
    $status = trim($_POST["status"] ?? "");

    if ($leadId > 0 && in_array($status, $allowedStatuses, true)) {
        $oldStatus = null;
        $oldStatusStmt = mysqli_prepare($con, 'SELECT status FROM leads WHERE id = ? LIMIT 1');
        if ($oldStatusStmt) {
            mysqli_stmt_bind_param($oldStatusStmt, 'i', $leadId);
            mysqli_stmt_execute($oldStatusStmt);
            $oldStatusRow = mysqli_fetch_assoc(mysqli_stmt_get_result($oldStatusStmt));
            $oldStatus = $oldStatusRow['status'] ?? null;
            mysqli_stmt_close($oldStatusStmt);
        }

        $updateStmt = mysqli_prepare(
            $con,
            'UPDATE leads SET status = ? WHERE id = ?'
        );
        if ($updateStmt) {
            mysqli_stmt_bind_param($updateStmt, "si", $status, $leadId);
            $statusUpdated = mysqli_stmt_execute($updateStmt);
            mysqli_stmt_close($updateStmt);

            if ($statusUpdated && $oldStatus !== null && $oldStatus !== $status) {
                saveActivityLog(
                    $con,
                    'Lead',
                    $leadId,
                    'STATUS',
                    'Lead status changed',
                    ['status' => $oldStatus],
                    ['status' => $status]
                );
            }

            redirectTo("leads");
        } else {
            $updateError = 'Unable to update lead status right now.';
        }
    } else {
        $updateError = "Invalid lead status update request.";
    }
}
$result = null;
$selectStmt = null;
$userId = (int) ($_SESSION["userId"] ?? 0);
if ($userId <= 0) {
    die("Unauthorized access.");
}

$employeeIdFilter = (int) ($_GET["employeeId"] ?? 0);
$statusFilterPrefill = trim((string) ($_GET["status"] ?? ""));
$sourceFilterPrefill = trim((string) ($_GET["source"] ?? ""));
// Carried through the Employee filter's page reload (see lead.js).
$dateFromPrefill = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET["dateFrom"] ?? "")) ? $_GET["dateFrom"] : "";
$dateToPrefill = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET["dateTo"] ?? "")) ? $_GET["dateTo"] : "";

$salesEmployees = [];
$employeeResult = mysqli_query(
    $con,
    " SELECT id, fullName FROM employeeusers
WHERE departmentName = 'Sales' AND employmentStatus = 'Active'
ORDER BY fullName ASC "
);
if (!$employeeResult) {
    die("Employee query failed: " . mysqli_error($con));
}
while ($employeeRow = mysqli_fetch_assoc($employeeResult)) {
    $salesEmployees[] = $employeeRow;
}
?>
<?php
include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css" />

<!-- Prism CSS -->
<link rel="stylesheet" href="<?= ASSET_URL ?>/assets/libs/prismjs/themes/prism-coy.min.css" />
<style>
    .lead-status-select {
        min-width: 140px;
        font-weight: 600;
        border-width: 1px;
        color: #000000 !important;
    }

    .lead-status-open {
        background: rgba(13, 202, 240, 0.15);
        color: #0dcaf0;
    }

    .lead-status-interested {
        background: rgba(255, 193, 7, 0.15);
        color: #b58900;
    }

    .lead-status-connected {
        background: rgba(32, 201, 151, 0.15);
        color: #20c997;
    }

    .lead-status-converted {
        background: rgba(25, 135, 84, 0.15);
        color: #198754;
    }

    .lead-status-not_interested {
        background: rgba(220, 53, 69, 0.15);
        color: #dc3545;
    }

    .lead-status-not_connected {
        background: rgba(108, 117, 125, 0.15);
        color: #6c757d;
    }

    .leads-table-filters {
        display: inline-flex;
        gap: 0.5rem;
        margin-left: 0.75rem;
        vertical-align: middle;
    }

    .leads-table-filters .form-select {
        min-width: 140px;
    }

    .lead-status-btn {
        font-size: 13px;
        font-weight: 600;
        font-transform: uppercase;
    }

    .lead-date-range {
        min-width: 230px;
    }

    /* All + 6 statuses on one row on wide screens */
    @media (min-width: 1200px) {
        #leadStatusCards > .col {
            flex: 1 0 0%;
        }
    }

    .lead-status-card {
        cursor: pointer;
    }

    .lead-status-card.active {
        border: 1px solid var(--primary-color);
    }
</style>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Leads</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Leads</li>
                </ol>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-info btn-wave" id="scheduledCallsBtn">
                    <i class="ri-calendar-event-line me-1"></i>

                    Scheduled Calls
                </button>

                <button type="button" class="btn btn-warning btn-wave" id="leadFollowUpsBtn" title="Follow Ups">
                    <i class="ri-list-check-2"></i>
                </button>

                <button
                    type="button"
                    class="btn btn-success btn-wave"
                    data-bs-toggle="modal"
                    data-bs-target="#importLeadModal"
                    title="Import Leads"
                >
                    <i class="ri-upload-cloud-2-line"></i>
                </button>

                <button
                    type="button"
                    class="btn btn-primary btn-wave"
                    data-bs-toggle="modal"
                    data-bs-target="#addLeadModal"
                >
                    <i class="ri-user-add-line me-1"></i>

                    Add Lead
                </button>
            </div>
        </div>

        <?php if ($updateError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo htmlspecialchars($updateError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-list">
                                    <div class="btn-group">
                                        <button
                                            type="button"
                                            class="btn btn-outline-primary dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false"
                                        >
                                            Export
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a
                                                    class="dropdown-item export-btn"
                                                    data-type="csv"
                                                    href="javascript:void(0);"
                                                    >CSV</a
                                                >
                                            </li>
                                            <li>
                                                <a
                                                    class="dropdown-item export-btn"
                                                    data-type="pdf"
                                                    href="javascript:void(0);"
                                                    >PDF</a
                                                >
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <select id="statusFilter" class="form-select form-select-lg">
                                    <option value="">All Status</option>
                                </select>
                                <select id="sourceFilter" class="form-select form-select-lg">
                                    <option value="">Source</option>
                                </select>
                                <select id="employeeFilter" class="form-select form-select-lg">
                                    <option value="">Employee</option>
                                    <?php foreach ($salesEmployees as $employee): ?>
                                    <option value="<?= (int)$employee['id'] ?>" <?= $employeeIdFilter === (int)$employee['id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($employee['fullName'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" id="leadDateRangeFilter" class="form-control form-control-lg lead-date-range" placeholder="Date range" aria-label="Date range"
                                    data-date-from="<?= htmlspecialchars($dateFromPrefill, ENT_QUOTES, 'UTF-8') ?>"
                                    data-date-to="<?= htmlspecialchars($dateToPrefill, ENT_QUOTES, 'UTF-8') ?>" />
                                <button type="button" id="leadsFilterResetBtn" class="btn btn-icon btn-sm btn-light" title="Reset Filters">
                                    <i class="ri-refresh-line"></i>
                                </button>
                            </div>
                            <div class="flex-fill"></div>
                            <div class="d-flex">
                                <input
                                    id="tableSearch"
                                    class="form-control form-control-sm"
                                    placeholder="Search leads..."
                                    autocomplete="off"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row row-cols-2 row-cols-md-3 g-3 mb-3" id="leadStatusCards">
            <!-- "All" + one card per status, rendered by lead.js from leadStatusLabels -->
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">Leads DataTable</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="leads-datatable" data-ui-table="mamix" class="table table-hover text-wrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Name</th>
                                        <th>Employee</th>
                                        <th>Category/Plan</th>
                                        <th>Source</th>
                                        <th>Status</th>
                                        <th>Organization</th>
                                        <th>Date</th>
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

        <!--Add Lead Modal -->

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
                                <div class="col-12 col-md-6">
                                    <label for="modal-fullName" class="form-label">Full Name</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="modal-fullName"
                                        name="fullName"
                                        placeholder="Enter Full Name"
                                        required
                                    />
                                    <div class="invalid-feedback">Full name is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-email" class="form-label">Email</label>
                                    <input
                                        type="email"
                                        class="form-control"
                                        id="modal-email"
                                        name="email"
                                        placeholder="Enter Email Address"
                                        required
                                    />
                                    <div class="invalid-feedback">A valid email is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-country" class="form-label">Country</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="modal-country"
                                        name="country"
                                        list="lead-country-options"
                                        placeholder="Type to search country..."
                                        autocomplete="off"
                                        required
                                    />
                                    <datalist id="lead-country-options"></datalist>
                                    <div class="invalid-feedback">Please select a valid country from the list.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-phone" class="form-label">Contact Number</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text" id="modal-countryCodeDisplay">+--</span>
                                        <input
                                            type="tel"
                                            class="form-control"
                                            id="modal-phone"
                                            name="phone"
                                            placeholder="Enter contact number or +country code number"
                                            pattern="[0-9]{6,15}"
                                            required
                                        />
                                        <div class="invalid-feedback">Select a country, then enter a valid contact number (6-15 digits).</div>
                                    </div>
                                    <input type="hidden" id="modal-countryCode" name="countryCode" />
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-source" class="form-label">Source</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="modal-source"
                                        name="source"
                                        placeholder="Enter Source"
                                        required
                                    />
                                    <div class="invalid-feedback">Source is required.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="modal-orgName" class="form-label">Organization</label>
                                    <input
                                        type="text"
                                        class="form-control"
                                        id="modal-orgName"
                                        name="orgName"
                                        placeholder="Enter Organization"
                                        required
                                    />
                                    <div class="invalid-feedback">Organization is required.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label"> Lead Category </label>

                                    <select class="form-select" id="modal-categoryId" name="categoryId" required>
                                        <option value="">Select Category</option>
                                    </select>

                                    <div class="invalid-feedback">Category is required.</div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label"> Lead Plan </label>

                                    <select class="form-select" id="modal-planId" name="planId" required>
                                        <option value="">Select Plan</option>
                                    </select>

                                    <div class="invalid-feedback">Plan is required.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="addLeadSubmitBtn">
                                <span
                                    class="spinner-border spinner-border-sm me-2 d-none"
                                    id="addLeadSubmitSpinner"
                                    role="status"
                                    aria-hidden="true"
                                ></span>
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

        <!-- Lead Remarks Modal -->

        <div class="modal fade" id="leadRemarkModal" data-bs-effect="effect-super-scaled" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Lead Remarks</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="remarkLeadId" />

                        <div class="fw-semibold mb-3" id="remarkLeadName"></div>

                        <div class="mb-3">
                            <label class="form-label"> Remark </label>

                            <textarea class="form-control" id="leadRemark" rows="4"> </textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"> Next Follow Up </label>

                            <input type="datetime-local" class="form-control" id="followUpDateTime" />
                        </div>

                        <button type="button" class="btn btn-primary" id="saveRemarkBtn">Save Remark</button>

                        <hr />

                        <div id="remarkTimeline"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Schedule Call Modal -->

        <div class="modal fade" id="scheduledCallsModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Scheduled Calls</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label"> Follow Up Date </label>

                                <input type="date" class="form-control" id="scheduledCallsDate" />
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>

                                        <th width="120">Time</th>

                                        <th>Lead Name</th>

                                        <th>Phone</th>

                                        <th>Employee</th>

                                        <th width="120">Status</th>

                                        <th>Remark</th>
                                    </tr>
                                </thead>

                                <tbody id="scheduledCallsTableBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Follow Ups Modal (leadFollowUps, generated by LeadFollowUpEngine) -->

        <div class="modal fade" id="leadFollowUpsModal" tabindex="-1">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Follow Ups</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="row g-2 mb-3 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label"> From </label>
                                <input type="date" class="form-control form-control-sm" id="lfuDateFrom" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"> To </label>
                                <input type="date" class="form-control form-control-sm" id="lfuDateTo" />
                            </div>
                            <div class="col-md-3">
                                <label class="form-label"> Lead Status </label>
                                <select class="form-select form-select-sm" id="lfuLeadStatus">
                                    <option value="">All</option>
                                    <option value="open">Open</option>
                                    <option value="interested">Interested</option>
                                    <option value="connected">Connected</option>
                                    <option value="not_connected">Not Connected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="button" class="btn btn-light btn-sm w-100" id="lfuResetBtn">
                                    <i class="ri-refresh-line me-1"></i> Reset
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th width="120">Date</th>
                                        <th>Lead</th>
                                        <th width="160">Follow Up</th>
                                        <th width="110">Status</th>
                                        <th width="160">Action</th>
                                    </tr>
                                </thead>

                                <tbody id="leadFollowUpsTableBody">
                                    <tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!--Lead Follow Change Status Modal Code-->

        <!-- Follow Up Close Remark Modal -->

        <div class="modal fade" id="followupRemarkModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Close Follow Up</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="followupLeadId" />

                        <label class="form-label"> Remark </label>

                        <textarea
                            class="form-control"
                            id="followupCloseRemark"
                            rows="4"
                            placeholder="Enter closing remark"
                        ></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="saveFollowupRemarkBtn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Documents Modal -->

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

                        <div class="row g-3">
                            <div class="col-md-9">
                                <input type="file" class="form-control" id="leadDocumentFile" accept=".pdf" />
                            </div>

                            <div class="col-md-3">
                                <button type="button" class="btn btn-primary w-100" id="uploadLeadDocumentBtn">
                                    Upload
                                </button>
                            </div>
                        </div>

                        <hr />

                        <div id="leadDocumentsContainer"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Status Modal -->

        <div class="modal fade" id="leadStatusModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leadStatusModalTitle">Status Details</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="statusLeadId" />

                        <input type="hidden" id="selectedLeadStatus" />

                        <div class="mb-4">
                            <label class="form-label fw-semibold"> Status: </label>

                            <span id="selectedStatusText" class="fw-bold"> </span>
                        </div>

                        <!-- Remark -->

                        <div class="mb-4">
                            <label class="form-label">
                                Status Remark

                                <span class="text-danger"> * </span>
                            </label>

                            <textarea
                                id="statusRemark"
                                class="form-control"
                                rows="4"
                                placeholder="Enter remark..."
                            ></textarea>
                        </div>

                        <!-- Converted Fields -->

                        <div id="convertedFields" style="display: none">
                            <div class="mb-4">
                                <label class="form-label"> Final Price </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    id="finalPrice"
                                    placeholder="Enter final agreed price"
                                />
                            </div>

                            <div class="mb-4">
                                <label class="form-label"> Next Price Increment Date </label>

                                <input type="date" class="form-control" id="nextPriceIncrementDate" />
                            </div>

                            <div class="mb-4">
                                <label class="form-label"> Quotation Document (PDF) </label>

                                <input type="file" class="form-control" id="quotationDocument" accept=".pdf" />
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="saveLeadStatusBtn">Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <!----- Import Lead Modal ---->

        <div class="modal fade" id="importLeadModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Import Leads</h5>

                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="importLeadForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                Upload CSV with columns:
                                <strong>full Name, Email, Phone, Source</strong>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Select Employee
                                    <span class="text-danger">*</span>
                                </label>

                                <select class="form-select" id="importEmployeeId" name="employeeId" required>
                                    <option value="">Select Employee</option>

                                    <?php foreach ($salesEmployees as $employee): ?>
                                    <option value="<?= (int)$employee['id'] ?>">
                                        <?= htmlspecialchars($employee['fullName'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    Upload CSV File
                                    <span class="text-danger">*</span>
                                </label>

                                <input
                                    type="file"
                                    class="form-control"
                                    id="leadCsvFile"
                                    name="leadCsvFile"
                                    accept=".csv"
                                    required
                                />
                            </div>

                            <a href="../api/leads/downloadLeadImportTemplate.php" class="btn btn-outline-primary btn-sm">
                                <i class="ri-download-line me-1"></i>
                                Download Sample CSV
                            </a>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-success" id="importLeadSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="importLeadSpinner">
                                </span>

                                Import Leads
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
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
<script src="<?= ASSET_URL ?>/assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script>
    var leadsData = [];
    // Status is API-driven. Source remains a client-side DataTables filter,
    // and these values restore selections after the Employee filter reloads.
    var leadsFilterPrefill = {
        status: <?= json_encode($statusFilterPrefill) ?>,
        source: <?= json_encode($sourceFilterPrefill) ?>
    };
</script>
<script src="<?= ASSET_URL ?>/assets/js/lead-country-data.js?v=<?php echo time(); ?>"></script>
<script src="<?= ASSET_URL ?>/assets/js/lead.js?v=<?php echo time(); ?>"></script>
<?php if ($selectStmt) { mysqli_stmt_close($selectStmt); } ?> <?php if ($result) { mysqli_free_result($result); } ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
