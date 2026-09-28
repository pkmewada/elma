<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/leadAccess.php';

/*
|--------------------------------------------------------------------------
| Reports
|--------------------------------------------------------------------------
| ONE report table with a Report Type toggle (Leads | Follow-ups), covering
| Lead/Source/Project/Sales-Executive/Pipeline/Converted-Lost reporting via
| filters+columns rather than separate pages. Zero new API endpoints:
| - Leads    -> api/leads/getLeads.php (extended with projectId/sourceId +
|               a reason column for Converted/Lost).
| - Follow-ups -> api/leads/getLeadFollowUps.php (view=all + dateFrom/
|               dateTo/projectId/status).
| Filter options -> api/leads/getLeadMasterData.php (already returns
| projects/sources/assignees/statuses). Data scope: the same
| getLeadScopeEmployeeId() rule as every lead API (view-all-leads).
| Admin-only route by default (Roles & Permissions can extend it).
*/
$statuses = LEAD_STATUSES;
$esc = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Reports</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Reports</li>
                </ol>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-body py-3">
                <div class="crm-filter-bar">
                    <select id="reportType" class="form-select">
                        <option value="leads">Lead Report</option>
                        <option value="followups">Follow-up Report</option>
                    </select>
                    <input type="text" id="reportDateRange" class="form-control" placeholder="Date range" autocomplete="off">
                    <select id="reportProject" class="form-select"><option value="">All Projects</option></select>
                    <select id="reportSource" class="form-select"><option value="">All Sources</option></select>
                    <select id="reportEmployee" class="form-select"><option value="">All Salespeople</option></select>
                    <select id="reportStatus" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach ($statuses as $key => $label): ?>
                        <option value="<?= $esc($key) ?>"><?= $esc($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">Export</button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item export-btn" data-type="csv" href="javascript:void(0);">CSV</a></li>
                            <li><a class="dropdown-item export-btn" data-type="excel" href="javascript:void(0);">Excel</a></li>
                            <li><a class="dropdown-item export-btn" data-type="pdf" href="javascript:void(0);">PDF</a></li>
                        </ul>
                    </div>
                    <input id="reportSearch" class="form-control crm-filter-search" placeholder="Search..." autocomplete="off">
                </div>
            </div>
        </div>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title" id="reportTitle">Lead Report</div></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="reportsTable" data-ui-table="mamix" class="table table-hover text-wrap"></table>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/reports.js?v=<?= filemtime(dirname(__DIR__) . '/dist/assets/js/reports.js') ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
