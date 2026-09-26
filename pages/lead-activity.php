<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

function formatLeadActivityValue(string $field, $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    if ($field === 'status') {
        $statusLabels = [
            'open' => 'Open',
            'interested' => 'Interested',
            'connected' => 'Connected',
            'converted' => 'Converted',
            'not_interested' => 'Not Interested',
            'not_connected' => 'Not Connected',
        ];

        $rawStatus = (string)$value;
        return $statusLabels[$rawStatus] ?? ucwords(str_replace('_', ' ', $rawStatus));
    }

    return is_array($value) ? json_encode($value) : (string)$value;
}

function formatLeadActivityField(string $field): string
{
    $fieldLabels = [
        'fullName' => 'Lead Name',
        'orgName' => 'Organization',
        'countryCode' => 'Country Code',
        'followUpDateTime' => 'Follow-up Date',
        'assignedEmployeeId' => 'Assigned Employee',
        'finalPrice' => 'Final Price',
        'statusRemark' => 'Status Remark',
        'nextPriceIncrementDate' => 'Next Price Increment Date',
        'quotationFile' => 'Quotation File',
    ];

    if (isset($fieldLabels[$field])) {
        return $fieldLabels[$field];
    }

    $spacedField = preg_replace('/(?<!^)([A-Z])/', ' $1', $field);
    return ucwords(str_replace('_', ' ', (string)$spacedField));
}

// =====================================================
// GET FILTER PARAMETERS
// =====================================================
$dateFrom = isset($_GET['dateFrom']) ? $_GET['dateFrom'] : '';
$dateTo = isset($_GET['dateTo']) ? $_GET['dateTo'] : '';
$employeeFilter = isset($_GET['employeeFilter']) ? (int)$_GET['employeeFilter'] : 0;
$statusFilter = isset($_GET['statusFilter']) ? $_GET['statusFilter'] : '';

// =====================================================
// BUILD WHERE CLAUSE
// =====================================================
$whereConditions = [];
$params = [];
$types = '';

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(al.createdAt) >= ?";
    $params[] = $dateFrom;
    $types .= 's';
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(al.createdAt) <= ?";
    $params[] = $dateTo;
    $types .= 's';
}

if ($employeeFilter > 0) {
    $whereConditions[] = "al.createdBy = ?";
    $params[] = $employeeFilter;
    $types .= 'i';
}

if (!empty($statusFilter)) {
    $whereConditions[] = "al.actionType = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$whereClause = !empty($whereConditions) ? 'AND ' . implode(' AND ', $whereConditions) : '';

// =====================================================
// MAIN QUERY
// =====================================================
$sql = "
SELECT
    al.*,
    l.fullName AS leadName,
    COALESCE(
        eu.fullName,
        u.fullName,
        'System'
    ) AS employeeName
FROM leadsActivityLogs al
LEFT JOIN leads l ON l.id = al.recordId
LEFT JOIN employeeusers eu ON eu.id = al.createdBy
LEFT JOIN users u ON u.id = al.createdBy
WHERE al.moduleName = 'Lead'
{$whereClause}
ORDER BY al.id DESC
";

// Prepare and execute with filters
if (!empty($params)) {
    $stmt = mysqli_prepare($con, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
    } else {
        $result = mysqli_query($con, $sql);
    }
} else {
    $result = mysqli_query($con, $sql);
}

// =====================================================
// SUMMARY COUNTERS
// =====================================================
// Total Activities
$totalSql = "
    SELECT COUNT(*) AS total
    FROM leadsActivityLogs al
    WHERE al.moduleName = 'Lead'
    {$whereClause}
";
$totalStmt = mysqli_prepare($con, $totalSql);
$totalResult = false;

if ($totalStmt) {
    if ($types !== '') {
        mysqli_stmt_bind_param($totalStmt, $types, ...$params);
    }

    mysqli_stmt_execute($totalStmt);
    $totalResult = mysqli_stmt_get_result($totalStmt);
}
$totalActivities = ($totalResult && mysqli_num_rows($totalResult) > 0) ? mysqli_fetch_assoc($totalResult)['total'] : 0;

if ($totalStmt) {
    mysqli_stmt_close($totalStmt);
}

// Action Types Breakdown
$actionSql = "
SELECT actionType, COUNT(*) as count 
FROM leadsActivityLogs 
WHERE moduleName = 'Lead' 
GROUP BY actionType
";
$actionResult = mysqli_query($con, $actionSql);
$actionStats = [];
if ($actionResult && mysqli_num_rows($actionResult) > 0) {
    while ($row = mysqli_fetch_assoc($actionResult)) {
        $actionStats[$row['actionType']] = $row['count'];
    }
}

// Get employees for filter
$employeeSql = "
    SELECT id, fullName
    FROM employeeusers
    WHERE LOWER(TRIM(departmentName)) = 'sales' AND employmentStatus = 'Active'
    ORDER BY fullName ASC
";
$employeeResult = mysqli_query($con, $employeeSql);
$employees = [];
while ($row = mysqli_fetch_assoc($employeeResult)) {
    $employees[] = $row;
}

// Get unique action types for filter
$actionTypesSql = "SELECT DISTINCT actionType FROM leadsActivityLogs WHERE moduleName = 'Lead' ORDER BY actionType";
$actionTypesResult = mysqli_query($con, $actionTypesSql);
$actionTypes = [];
while ($row = mysqli_fetch_assoc($actionTypesResult)) {
    $actionTypes[] = $row['actionType'];
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';

?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />

<style>
    .summary-card {
        flex: 1 1 0;
        min-width: 110px;
    }
    .summary-card .number {
        font-size: 22px;
        font-weight: 700;
        line-height: 1.2;
    }
    .summary-card .label {
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
        margin-top: 2px;
    }
    .summary-card.primary .number { color: rgb(var(--primary-rgb)); }
    .summary-card.success .number { color: rgb(var(--success-rgb)); }
    .summary-card.warning .number { color: rgb(var(--warning-rgb)); }
    .summary-card.danger .number { color: rgb(var(--danger-rgb)); }
    .summary-card.info .number { color: rgb(var(--info-rgb)); }
    .summary-card.purple .number { color: rgb(var(--secondary-rgb)); }

    .activity-badge {
        padding: 3px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
        display: inline-block;
    }
    .activity-badge.created { background: #dbeafe; color: #1d4ed8; }
    .activity-badge.updated { background: #fef3c7; color: #b45309; }
    .activity-badge.deleted { background: #fee2e2; color: #dc2626; }
    .activity-badge.status_change { background: #d1fae5; color: #065f46; }
    .activity-badge.remark_added { background: #ede9fe; color: #6d28d9; }
    .activity-badge.converted { background: #d1fae5; color: #065f46; }
    .activity-badge.imported { background: #e0f2fe; color: #0369a1; }

    .change-item {
        padding: 2px 6px;
        background: #f8fafc;
        border-radius: 4px;
        margin-bottom: 2px;
        font-size: 12px;
        line-height: 1.6;
        word-break: break-all;
        max-width: 280px;
    }
    .change-item .old-value {
        color: #dc2626;
        text-decoration: line-through;
        margin-right: 4px;
    }
    .change-item .new-value {
        color: #16a34a;
        font-weight: 600;
    }
    .change-item .arrow {
        color: #94a3b8;
        margin: 0 4px;
    }
    .change-item .field-name {
        font-weight: 600;
        color: #475569;
        margin-right: 4px;
    }

    .lead-name-cell {
        max-width: 150px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .lead-name-cell .lead-id {
        font-size: 11px;
        color: #94a3b8;
    }

    .desc-cell {
        max-width: 200px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .lead-activity-filter-controls .form-control,
    .lead-activity-filter-controls .form-select {
        min-width: 150px;
    }

    #activityTableSearch {
        min-width: 220px;
    }

    @media (max-width: 768px) {
        .change-item {
            max-width: 180px;
        }

        #activityTableSearch {
            min-width: 100%;
        }
    }
</style>

<div class="main-content app-content">
    <div class="container-fluid">

        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Lead Activity Logs</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="leads">Leads</a></li>
                    <li class="breadcrumb-item active">Activity Logs</li>
                </ol>
            </div>
            <div>
                <a href="leads" class="btn btn-outline-primary btn-wave btn-sm">
                    <i class="ri-arrow-left-line me-1"></i> Back to Leads
                </a>
            </div>
        </div>

        <!-- =====================================================
        SUMMARY CARDS
        ===================================================== -->
        <div class="d-flex flex-wrap gap-2 mb-3">
            <div class="card custom-card summary-card primary mb-0">
                <div class="card-body py-2 px-3">
                    <div class="number"><?= number_format($totalActivities) ?></div>
                    <div class="label">Total Activities</div>
                </div>
            </div>

            <?php foreach ($actionStats as $action => $count): ?>
                <?php
                $normalizedAction = strtolower($action);
                $colorClass = match($normalizedAction) {
                    'created' => 'success',
                    'updated' => 'warning',
                    'deleted' => 'danger',
                    'status_change' => 'info',
                    'remark_added' => 'purple',
                    'converted' => 'success',
                    'imported' => 'info',
                    default => 'primary'
                };
                $label = ucwords(str_replace('_', ' ', $normalizedAction));
                ?>
                <div class="card custom-card summary-card <?= $colorClass ?> mb-0">
                    <div class="card-body py-2 px-3">
                        <div class="number"><?= number_format($count) ?></div>
                        <div class="label"><?= $label ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- =====================================================
        FILTER SECTION
        ===================================================== -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">
                        <form method="GET" action="" class="d-flex align-items-end justify-content-between flex-wrap gap-2">
                            <div class="lead-activity-filter-controls d-flex align-items-end flex-wrap gap-2">
                                <div>
                                    <label class="form-label fs-12 mb-1">Date From</label>
                                    <input type="date" name="dateFrom" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div>
                                    <label class="form-label fs-12 mb-1">Date To</label>
                                    <input type="date" name="dateTo" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div>
                                    <label class="form-label fs-12 mb-1">Employee</label>
                                    <select name="employeeFilter" class="form-select form-select-sm">
                                        <option value="0">All Employees</option>
                                        <?php foreach ($employees as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= $employeeFilter == $emp['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($emp['fullName'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label fs-12 mb-1">Action Type</label>
                                    <select name="statusFilter" class="form-select form-select-sm">
                                        <option value="">All Actions</option>
                                        <?php foreach ($actionTypes as $type): ?>
                                            <option value="<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter == $type ? 'selected' : '' ?>>
                                                <?= ucfirst(str_replace('_', ' ', $type)) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Filter</button>
                                <a href="lead-activity" class="btn btn-icon btn-sm btn-light" title="Reset Filters" aria-label="Reset filters">
                                    <i class="ri-refresh-line"></i>
                                </a>
                            </div>
                            <div class="d-flex">
                                <input
                                    id="activityTableSearch"
                                    class="form-control form-control-sm"
                                    placeholder="Search activity..."
                                    autocomplete="off"
                                >
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- =====================================================
        ACTIVITY TABLE
        ===================================================== -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">Lead Activity DataTable</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="activityTable" data-ui-table="mamix" class="table table-hover text-wrap">
                        <thead>
                            <tr>
                                <th style="min-width: 130px;">Date</th>
                                <th style="min-width: 120px;">Lead</th>
                                <th style="min-width: 100px;">Action</th>
                                <th style="min-width: 160px;">Description</th>
                                <th style="min-width: 200px;">Changes</th>
                                <th style="min-width: 100px;">User</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>

                                    <?php
                                    $oldData = !empty($row['oldData']) ? json_decode($row['oldData'], true) : [];
                                    $newData = !empty($row['newData']) ? json_decode($row['newData'], true) : [];

                                    $leadName = $row['leadName'] ?? ($oldData['fullName'] ?? '-');

                                    // Action badge class
                                    $normalizedAction = strtolower($row['actionType']);
                                    $badgeClass = match($normalizedAction) {
                                        'created' => 'created',
                                        'updated' => 'updated',
                                        'deleted' => 'deleted',
                                        'status_change' => 'status_change',
                                        'remark_added' => 'remark_added',
                                        'converted' => 'converted',
                                        'imported' => 'imported',
                                        default => 'created'
                                    };

                                    $actionLabel = ucwords(str_replace('_', ' ', $normalizedAction));
                                    
                                    // Format changes for display
                                    $changesHtml = '';
                                    if (!empty($newData)) {
                                        foreach ($newData as $key => $value) {
                                            $oldValue = formatLeadActivityValue($key, $oldData[$key] ?? null);
                                            $newValue = formatLeadActivityValue($key, $value);
                                            $fieldLabel = formatLeadActivityField($key);
                                            
                                            $changesHtml .= '<div class="change-item">';
                                            $changesHtml .= '<span class="field-name">' . htmlspecialchars($fieldLabel, ENT_QUOTES, 'UTF-8') . ':</span>';
                                            $changesHtml .= '<span class="old-value">' . htmlspecialchars((string)$oldValue, ENT_QUOTES, 'UTF-8') . '</span>';
                                            $changesHtml .= '<span class="arrow">→</span>';
                                            $changesHtml .= '<span class="new-value">' . htmlspecialchars($newValue, ENT_QUOTES, 'UTF-8') . '</span>';
                                            $changesHtml .= '</div>';
                                        }
                                    } else {
                                        $changesHtml = '<span class="text-muted" style="font-size:12px;">No Changes</span>';
                                    }
                                    ?>

                                    <tr>
                                        <td style="white-space: nowrap; font-size: 13px;" data-order="<?= (int) strtotime($row['createdAt']) ?>">
                                            <?= date('d M Y', strtotime($row['createdAt'])) ?>
                                            <br>
                                            <small class="text-muted"><?= date('h:i A', strtotime($row['createdAt'])) ?></small>
                                        </td>

                                        <td class="lead-name-cell">
                                            <span title="<?= htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($leadName, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                            <?php if (!empty($row['recordId'])): ?>
                                                <div class="lead-id">#<?= $row['recordId'] ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td>
                                            <span class="activity-badge <?= $badgeClass ?>">
                                                <?= htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </td>

                                        <td class="desc-cell" title="<?= htmlspecialchars($row['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?>">
                                            <?= htmlspecialchars($row['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td>
                                            <?= $changesHtml ?>
                                        </td>

                                        <td style="white-space: nowrap; font-size: 13px;">
                                            <?= htmlspecialchars($row['employeeName'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    </tr>

                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">
                                        <i class="ri-inbox-line fs-4 d-block mb-2"></i>
                                        No activity logs found
                                        <?php if (!empty($dateFrom) || !empty($dateTo) || $employeeFilter > 0 || !empty($statusFilter)): ?>
                                            <br><small>Try adjusting your filters</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/lead-activity.js?v=<?php echo time(); ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
