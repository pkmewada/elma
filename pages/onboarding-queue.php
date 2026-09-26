<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/basic-config.php';
require_once __DIR__ . '/../includes/config.php';

function esc(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function mapAckStatus(?string $value): string
{
    $raw = strtolower(trim((string) $value));
    if ($raw === 'completed') {
        return 'acknowledged';
    }
    return $raw === '' ? 'pending' : $raw;
}

function ackStatusBadgeClass(string $status): string
{
    return $status === 'acknowledged' ? 'success' : 'warning';
}

function reviewStatusBadgeClass(string $status): string
{
    if ($status === 'verified') {
        return 'success';
    }
    if ($status === 'rejected') {
        return 'danger';
    }
    return 'info';
}

$rows = [];
$loadError = '';
$config = getBasicConfig();
$departments = $config['departments'] ?? [];
$stmt = mysqli_prepare($con, "
    SELECT id, fullName, phoneNumber, email, appliedRole, finalSalary, joiningDate,
           acknowledgmentStatus, reviewStatus, updatedAt, acknowledgmentSubmittedAt, resubmissionCount,
           govtIdType, govtIdFile, signatureFile, currentLocation
    FROM candidateRecord
    WHERE status = 'convert'
    ORDER BY id DESC
");

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $loadError = 'Unable to load onboarding queue.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<style>
.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
}

.status-chip-warning {
    color: rgb(var(--warning-rgb));
    border-color: rgba(var(--warning-rgb), 0.3);
}

.status-chip-success {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
}

.status-chip-info {
    color: rgb(var(--info-rgb));
    border-color: rgba(var(--info-rgb), 0.3);
}

.status-chip-danger {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), 0.3);

}

.review-status-btn {
    font-size: 13px;
    font-weight: 600;
}

.review-status-lock {
    cursor: not-allowed;
    opacity: 1;
}

.queue-filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.queue-filter-bar .form-select {
    min-width: 180px;
}

.verify-select {
    font-weight: 600;
    border-width: 1px;
    min-width: 140px;
}

.verify-select.status-success {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), .35);
}

.verify-select.status-info {
    color: rgb(var(--warning-rgb));
    border-color: rgba(var(--warning-rgb), .35);
}

.table-responsive {
    overflow: visible !important;
}

.dropdown-menu {
    z-index: 99999 !important;
}

.dropdown-menu {
    z-index: 99999 !important;
    position: absolute !important;
}

.dataTables_wrapper,
.table-responsive,
.card-body {
    overflow: visible !important;
}

.dropdown-menu {
    z-index: 999999 !important;
}

.verify-select.status-danger {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), .35);
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Onboarding Queue</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="candidate-record">Candidate Record</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Onboarding Queue</li>
                </ol>
            </div>
            <div>
                <button type="button" class="btn btn-success btn-wave waves-effect waves-light" id="viewJoiningListBtn">
                    <i class="ri-eye-line align-middle me-1"></i>
                    View Joining List
                </button>
            </div>
        </div>

        <?php if ($loadError !== ''): ?>
        <div class="alert alert-danger"><?= esc($loadError) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">

                        <div class="d-flex align-items-center justify-content-between">

                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-list">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary dropdown-toggle"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Export
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item export-btn" data-type="csv"
                                                    href="javascript:void(0);">CSV</a></li>
                                            <li><a class="dropdown-item export-btn" data-type="pdf"
                                                    href="javascript:void(0);">PDF</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <select id="ackFilter" class="form-select form-select-lg">
                                    <option value="">Ack. Status</option>
                                </select>

                                <select id="reviewFilter" class="form-select form-select-lg">
                                    <option value="">Review Status</option>
                                </select>

                            </div>

                            <div class="flex-fill"></div>

                            <div class="d-flex">
                                <input id="queueSearch" class="form-control form-control-sm"
                                    placeholder="Search candidates..." autocomplete="off">
                            </div>

                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header justify-content-between">
                        <div class="card-title">Onboarding Candidate Queue</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="onboarding-queue-table" data-ui-table="mamix"
                                class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SrNo</th>
                                        <th>Details</th>
                                        <th>Role</th>
                                        <th>Joining Date</th>
                                        <th>ACK Status</th>
                                        <th>Review Status</th>
                                        <th>Updated At</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php foreach ($rows as $i => $row): ?>
                                    <?php
                                        $ack = mapAckStatus($row['acknowledgmentStatus'] ?? '');
                                        $review = trim((string) ($row['reviewStatus'] ?? 'inReview'));

                                        if ($review === '') {
                                            $review = 'inReview';
                                        }

                                        $isLocked = $ack !== 'acknowledged';
                                    ?>

                                    <?php
                                        $govtIdUrl = '';
                                        if (!empty($row['govtIdFile'])) {
                                            $govtIdUrl = preg_match('/^https?:\/\//i', $row['govtIdFile']) ? $row['govtIdFile'] : BASE_URL . '/' . ltrim($row['govtIdFile'], '/');
                                        }

                                        $signatureUrl = '';
                                        if (!empty($row['signatureFile'])) {
                                            $signatureUrl = preg_match('/^https?:\/\//i', $row['signatureFile']) ? $row['signatureFile'] : BASE_URL . '/' . ltrim($row['signatureFile'], '/');
                                        }
                                    ?>

                                    <tr>
                                        <td><?= (int)$i + 1 ?></td>

                                        <!-- DETAILS COLUMN -->
                                        <td>
                                            <div class="fw-semibold text-dark">
                                                <?= esc($row['fullName']) ?>
                                            </div>

                                            <div class="small text-muted">
                                                <?= esc($row['phoneNumber']) ?>
                                            </div>

                                            <div class="small text-muted">
                                                <?= esc($row['email']) ?>
                                            </div>

                                            <div class="small text-muted">
                                                <?= esc($row['currentLocation'] ?? '-') ?>
                                            </div>
                                        </td>

                                        <td><?= esc($row['appliedRole']) ?></td>

                                        <td>
                                            <?= !empty($row['joiningDate']) 
                                                ? esc(date('d M Y', strtotime((string)$row['joiningDate']))) 
                                                : '-' ?>
                                        </td>

                                        <!-- ACK STATUS -->
                                        <td data-ack-status="<?= esc($ack) ?>">
                                            <span class="status-chip status-chip-<?= esc(ackStatusBadgeClass($ack)) ?>">
                                                <?= esc(ucfirst($ack)) ?>
                                            </span>
                                        </td>

                                        <!-- REVIEW STATUS -->
                                        <td data-review-status="<?= esc($review) ?>">
                                            <div class="btn-group" data-id="<?= (int)$row['id'] ?>"
                                                data-ack-status="<?= esc($ack) ?>">

                                                <button type="button"
                                                    class="btn btn-sm review-status-btn dropdown-toggle status-chip status-chip-<?= esc(reviewStatusBadgeClass($review)) ?>"
                                                    data-bs-toggle="dropdown"
                                                    data-bs-display="static">
                                                    <?= esc(ucfirst($review)) ?>
                                                </button>

                                                <?php if (!$isLocked): ?>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item change-review-status"
                                                            href="javascript:void(0);" data-status="inReview">
                                                            In Review
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a class="dropdown-item change-review-status"
                                                            href="javascript:void(0);" data-status="verified">
                                                            Verified
                                                        </a>
                                                    </li>

                                                    <li>
                                                        <a class="dropdown-item change-review-status"
                                                            href="javascript:void(0);" data-status="rejected">
                                                            Rejected
                                                        </a>
                                                    </li>
                                                </ul>
                                                <?php endif; ?>

                                            </div>
                                        </td>

                                        <!-- UPDATED -->
                                        <td>
                                            <?= !empty($row['updatedAt'])
                                                ? esc(date('d M Y h:i A', strtotime((string)$row['updatedAt'])))
                                                : '-' ?>
                                        </td>

                                        <!-- ACTION -->
                                        <td>
                                            <button type="button"
                                                class="btn btn-icon btn-sm btn-info-light view-submission-btn"
                                                data-id="<?= (int)$row['id'] ?>"
                                                data-fullname="<?= esc($row['fullName']) ?>"
                                                data-phonenumber="<?= esc($row['phoneNumber']) ?>"
                                                data-email="<?= esc($row['email']) ?>"
                                                data-appliedrole="<?= esc($row['appliedRole']) ?>"
                                                data-finalsalary="<?= esc($row['finalSalary']) ?>"
                                                data-joiningdate="<?= esc((string)$row['joiningDate']) ?>"
                                                data-govtidtype="<?= esc($row['govtIdType']) ?>"
                                                data-govtidfile="<?= esc($row['govtIdFile']) ?>"
                                                data-signaturefile="<?= esc($row['signatureFile']) ?>"
                                                data-acksubmittedat="<?= esc((string)$row['acknowledgmentSubmittedAt']) ?>"
                                                data-resubmissioncount="<?= (int)($row['resubmissionCount'] ?? 0) ?>"
                                                data-ackstatus="<?= esc($ack) ?>"
                                                data-reviewstatus="<?= esc($review) ?>">

                                                <i class="ri-eye-line"></i>
                                            </button>
                                        </td>

                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="submissionDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">

            <div class="modal-header">
                <h5 class="modal-title mb-0">Submitted Form Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body p-3">

                <div class="row g-3">

                    <!-- Candidate Details -->
                    <div class="col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Candidate Details</h6>
                            </div>
                            <div class="card-body">
                                <div id="submissionCandidateDetails"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Current Status -->
                    <div class="col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Current Status</h6>
                            </div>
                            <div class="card-body">
                                <div id="submissionStatus"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submitted Documents -->
                    <div class="col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Submitted Documents</h6>
                            </div>
                            <div class="card-body">
                                <div id="submissionDocuments"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Submission Info -->
                    <div class="col-lg-6">
                        <div class="card custom-card h-100">
                            <div class="card-header">
                                <h6 class="mb-0">Submission Info</h6>
                            </div>
                            <div class="card-body">
                                <div id="submissionInfo"></div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row g-3 mt-1">

                    <!-- HR Verification Workspace -->
                    <div class="col-12">
                        <div class="card custom-card">
                            <div class="card-header">
                                <div>
                                    <h6 class="mb-0">HR Verification Workspace</h6>
                                    <div id="verifiedBadgeArea"></div>
                                </div>
                            </div>

                            <div class="card-body">

                                <form id="hrVerificationForm">

                                    <input type="hidden" id="verifyEmployeeId" name="employeeUserId">

                                    <!-- Dynamic Fields Render Here -->
                                    <div id="hrVerificationFields">

                                        <div class="text-muted">
                                            Loading candidate profile data...
                                        </div>

                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="border-top pt-3 mt-3 text-end">
                                        <button type="button" class="btn btn-warning" id="submitHrReviewBtn">
                                            <i class="ri-mail-send-line me-1"></i>
                                            Submit Review
                                        </button>
                                        <button type="button" class="btn btn-success" id="finalVerifyBtn">
                                            Final Verify
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectReviewModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject & Request Resubmission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectReviewForm">
                <input type="hidden" id="rejectCandidateId" name="id">
                <div class="modal-body">
                    <label for="rejectReviewRemark" class="form-label">Review Remark</label>
                    <textarea id="rejectReviewRemark" class="form-control" name="reviewRemark" rows="4" required
                        placeholder="Enter rejection reason for candidate"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger" id="rejectReviewSubmitBtn">
                        <span class="spinner-border spinner-border-sm me-2 d-none" id="rejectReviewSpinner"></span>
                        <span id="rejectReviewText">Submit Rejection</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="joiningListModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">View Joined Candidates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="joiningListTable">
                        <thead>
                            <tr>
                                <th>SNo</th>
                                <th>Details</th>
                                <th>Role</th>
                                <th>Joining Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="joiningListTableBody">
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Verified candidates who have joined will appear here...
                                </td>
                            </tr>
                        </tbody>
                    </table>
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
<script>
var departmentList = <?= json_encode($departments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
</script>
<script>
$(function() {

    /* ─── 1. DATATABLE INIT ─────────────────────────────────────── */
    var tableEl = $('#onboarding-queue-table');

    if ($.fn.DataTable.isDataTable('#onboarding-queue-table')) {
        tableEl.DataTable().destroy();
    }

    var table = tableEl.DataTable(
        window.ModlusUI.withDataTableDefaults({
            destroy: true,
            responsive: false,
            autoWidth: false,
            scrollX: true,
            order: [],
            pageLength: 10,
            dom: "t<'row mt-3 align-items-center'<'col-md-5'i><'col-md-7'p>>",

            columnDefs: [{
                targets: [0, 7], // Sr No + Action
                orderable: false,
                searchable: false
            }],

            buttons: [{
                    extend: 'csvHtml5',
                    className: 'd-none buttons-csv',
                    exportOptions: {
                        columns: ':visible:not(:last-child)'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    className: 'd-none buttons-pdf',
                    exportOptions: {
                        columns: ':visible:not(:last-child)'
                    }
                }
            ],

            drawCallback: function() {

                var api = this.api();

                api.rows({
                    page: 'current'
                }).every(function(rowIdx) {
                    $(this.node()).find('td:first-child').text(rowIdx + 1);
                });

                $('.dataTables_scrollBody').css({
                    overflowX: 'auto',
                    overflowY: 'hidden'
                });
            }
        })
    );

    /* ─── 2. POPULATE FILTER DROPDOWNS (after DT init) ─────────── */
    var $ackFilter = $('#ackFilter');
    var $reviewFilter = $('#reviewFilter');

    function fillFilter($select, attrName) {
        var seen = {};
        table.rows().every(function() {
            var val = $(this.node()).find('td[' + attrName + ']').attr(attrName);
            if (!val || seen[val]) return;
            seen[val] = true;
            $select.append(
                $('<option>', {
                    value: val,
                    text: val.charAt(0).toUpperCase() + val.slice(1)
                })
            );
        });
    }

    fillFilter($ackFilter, 'data-ack-status');
    fillFilter($reviewFilter, 'data-review-status');

    /* ─── 3. CUSTOM FILTER FUNCTION ─────────────────────────────── */
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'onboarding-queue-table') return true;

        var $row = $(table.row(dataIndex).node());
        var ack = $row.find('td[data-ack-status]').attr('data-ack-status') || '';
        var review = $row.find('td[data-review-status]').attr('data-review-status') || '';
        var ackVal = $ackFilter.val();
        var reviewVal = $reviewFilter.val();

        if (ackVal && ack !== ackVal) return false;
        if (reviewVal && review !== reviewVal) return false;
        return true;
    });

    /* ─── 4. FILTER + SEARCH EVENT BINDINGS ─────────────────────── */
    $ackFilter.on('change', function() {
        table.draw();
    });
    $reviewFilter.on('change', function() {
        table.draw();
    });

    $('#queueSearch').on('keyup input', function() {
        table.search($.trim($(this).val())).draw();
    });

    $('.export-btn').on('click', function() {
        var type = $(this).data('type');
        if (type === 'csv') {
            table.buttons('.buttons-csv').trigger();
        } else if (type === 'pdf') {
            table.buttons('.buttons-pdf').trigger();
        }
    });

    /* ─── 5. HELPERS ─────────────────────────────────────────────── */
    // Safely escape a value for use inside innerHTML
    function safe(val) {
        return $('<div>').text(val == null || val === '' ? '-' : String(val)).html();
    }

    function formatDate(val) {
        if (!val) return '-';
        var dt = new Date(val);
        if (isNaN(dt.getTime())) return String(val);
        return dt.toLocaleString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        });
    }

    /* ─── 6. UPDATE REVIEW STATE IN ROW ─────────────────────────── */
    function updateRowReviewState($row, reviewStatus, ackStatus) {
        var isLocked = (ackStatus || '').toLowerCase() !== 'acknowledged';
        var badgeClass = reviewStatus === 'verified' ? 'success' :
            reviewStatus === 'rejected' ? 'danger' :
            'info';
        var reviewText = reviewStatus.charAt(0).toUpperCase() + reviewStatus.slice(1);

        var $btn = $row.find('.review-status-btn');
        var $group = $btn.closest('.btn-group');

        $btn.removeClass('status-chip-info status-chip-success status-chip-danger')
            .addClass('status-chip-' + badgeClass)
            .attr('data-review-status', reviewStatus)
            .text(reviewText);

        $row.find('td[data-review-status]').attr('data-review-status', reviewStatus);

        if (isLocked) {
            $btn.addClass('review-status-lock')
                .removeClass('dropdown-toggle')
                .prop('disabled', true)
                .attr('title', 'Waiting for candidate submission');
            if (!$btn.find('.ri-lock-line').length) {
                $btn.append(' <i class="ri-lock-line ms-1"></i>');
            }
            $group.find('.dropdown-menu').remove();
        } else {
            $btn.removeClass('review-status-lock')
                .addClass('dropdown-toggle')
                .prop('disabled', false)
                .attr({
                    title: '',
                    'data-bs-toggle': 'dropdown'
                });
            $btn.find('.ri-lock-line').remove();
            if (!$group.find('.dropdown-menu').length) {
                $group.append(
                    '<ul class="dropdown-menu">' +
                    '<li><a class="dropdown-item change-review-status" href="javascript:void(0);" data-status="inReview">In Review</a></li>' +
                    '<li><a class="dropdown-item change-review-status" href="javascript:void(0);" data-status="verified">Verified</a></li>' +
                    '<li><a class="dropdown-item change-review-status" href="javascript:void(0);" data-status="rejected">Rejected</a></li>' +
                    '</ul>'
                );
            }
        }
    }

    /* ─── 7. API CALL WRAPPER ────────────────────────────────────── */
    function callReviewApi(candidateId, nextStatus, reviewRemark, onDone, onAlways) {
        $.ajax({
            url: API_BASE + '/onboarding/updateOnboardingReviewStatus.php',
            type: 'POST',
            data: {
                id: candidateId,
                reviewStatus: nextStatus,
                reviewRemark: reviewRemark || ''
            },
            success: function(response) {
                if (!response || !response.success) {
                    window.showToast && window.showToast(
                        'danger',
                        (response && response.message) || 'Failed to update review status.'
                    );
                    return;
                }
                if (typeof onDone === 'function') onDone(response);
                window.showToast && window.showToast(
                    'success',
                    response.message || 'Updated successfully.'
                );
            },
            error: function(xhr) {
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ?
                    xhr.responseJSON.message :
                    'Unable to process request.';
                window.showToast && window.showToast('danger', msg);
            },
            complete: function() {
                if (typeof onAlways === 'function') onAlways();
            }
        });
    }

    /* ─── 8. CHANGE REVIEW STATUS (dropdown click) ───────────────── */
    $(document).on('click', '.change-review-status', function() {
        var status = $(this).data('status');
        var $group = $(this).closest('.btn-group');
        var id = $group.data('id');
        var ackStatus = String($group.data('ack-status') || '').toLowerCase();
        var $row = $group.closest('tr');

        if (ackStatus !== 'acknowledged') {
            window.showToast && window.showToast('warning', 'Waiting for candidate submission.');
            return;
        }

        if (status === 'rejected') {
            $('#rejectCandidateId').val(id);
            $('#rejectReviewRemark').val('').removeClass('is-invalid');
            $('#rejectReviewModal').modal('show');
            return;
        }

        callReviewApi(id, status, '', function() {
            updateRowReviewState($row, status, 'acknowledged');
            $row.find('td:nth-child(7)').text(new Date().toLocaleString('en-IN'));
            table.row($row).invalidate('dom').draw(false);
        });
    });

    /* ─── 9. REJECT FORM SUBMIT ──────────────────────────────────── */
    $('#rejectReviewForm').on('submit', function(e) {
        e.preventDefault();

        var id = $('#rejectCandidateId').val();
        var remark = $.trim($('#rejectReviewRemark').val());

        if (!remark) {
            $('#rejectReviewRemark').addClass('is-invalid');
            return;
        }
        $('#rejectReviewRemark').removeClass('is-invalid');

        var $submitBtn = $('#rejectReviewSubmitBtn');
        var $spinner = $('#rejectReviewSpinner');
        var $text = $('#rejectReviewText');

        $submitBtn.prop('disabled', true);
        $spinner.removeClass('d-none');
        $text.text('Submitting...');

        callReviewApi(id, 'rejected', remark,
            function() {
                var $row = $('.btn-group[data-id="' + id + '"]').closest('tr');

                // Reset ack status chip to Pending
                $row.find('td[data-ack-status]')
                    .attr('data-ack-status', 'pending')
                    .html('<span class="status-chip status-chip-warning">Pending</span>');

                // Sync data attribute on btn-group
                $row.find('.btn-group[data-id="' + id + '"]').attr('data-ack-status', 'pending');

                updateRowReviewState($row, 'inReview', 'pending');
                $row.find('td:nth-child(7)').text(new Date().toLocaleString('en-IN'));
                table.row($row).invalidate('dom').draw(false);
                $('#rejectReviewModal').modal('hide');
            },
            function() {
                $submitBtn.prop('disabled', false);
                $spinner.addClass('d-none');
                $text.text('Submit Rejection');
            }
        );
    });

    /* ─── 10. VIEW SUBMISSION MODAL ──────────────────────────────── */
    $(document).on('click', '.view-submission-btn', function() {

        var $btn = $(this);

        /* ==========================================================
           KEEP EXISTING OLD MODAL DATA (NO CHANGE)
        ========================================================== */

        var govtFile = $btn.data('govtidfile') || '';
        var signFile = $btn.data('signaturefile') || '';

        var baseUrl = UPLOAD_URL + '/acknowledgment/';

        var govtFile = $btn.data('govtidfile') || '';
        var signFile = $btn.data('signaturefile') || '';
        
        function buildFileUrl(file) {
            if (!file) {
                return '';
            }
        
            // Already full URL
            if (/^https?:\/\//i.test(file)) {
                return file;
            }
        
            // Remove duplicate upload path if exists
            file = file.replace(/^\/?uploads\/acknowledgment\/?/i, '');
        
            return UPLOAD_URL + '/acknowledgment/' + file;
        }
        
        var govtUrl = buildFileUrl(govtFile);
        var signUrl = buildFileUrl(signFile);
        
        var govtLink = govtUrl
            ? '<a target="_blank" class="btn btn-outline-primary" rel="noopener noreferrer" href="' +
              safe(govtUrl) + '">View</a>'
            : '-';
        
        var signLink = signUrl
            ? '<a target="_blank" class="btn btn-outline-primary" rel="noopener noreferrer" href="' +
              safe(signUrl) + '">View</a>'
            : '-';

        $('#submissionCandidateDetails').html(
            '<table class="table table-sm table-bordered align-middle mb-0">' +
            '<tr><th width="35%">Full Name</th><td>' + safe($btn.data('fullname')) + '</td></tr>' +
            '<tr><th>Phone</th><td>' + safe($btn.data('phonenumber')) + '</td></tr>' +
            '<tr><th>Email</th><td>' + safe($btn.data('email')) + '</td></tr>' +
            '<tr><th>Applied Role</th><td>' + safe($btn.data('appliedrole')) + '</td></tr>' +
            '<tr><th>Final Salary</th><td>' + safe($btn.data('finalsalary')) + '</td></tr>' +
            '<tr><th>Joining Date</th><td>' + safe($btn.data('joiningdate')) + '</td></tr>' +
            '</table>'
        );

        $('#submissionDocuments').html(
            '<table class="table table-sm table-bordered align-middle mb-0">' +
            '<tr><th width="35%">Govt ID Type</th><td>' + safe($btn.data('govtidtype')) +
            '</td></tr>' +
            '<tr><th>Govt ID File</th><td>' + govtLink + '</td></tr>' +
            '<tr><th>Signature File</th><td>' + signLink + '</td></tr>' +
            '</table>'
        );

        $('#submissionInfo').html(
            '<table class="table table-sm table-bordered align-middle mb-0">' +
            '<tr><th width="35%">Submitted At</th><td>' + formatDate($btn.data('acksubmittedat')) +
            '</td></tr>' +
            '<tr><th>Resubmission Count</th><td>' + ($btn.data('resubmissioncount') || 0) +
            '</td></tr>' +
            '</table>'
        );

        $('#submissionStatus').html(
            '<table class="table table-sm table-bordered align-middle mb-0">' +
            '<tr><th width="35%">Acknowledgment Status</th><td>' + safe($btn.data('ackstatus')) +
            '</td></tr>' +
            '<tr><th>Review Status</th><td>' + safe($btn.data('reviewstatus')) + '</td></tr>' +
            '</table>'
        );

        $('#submissionDetailsModal').modal('show');

        /* ==========================================================
           NEW HR VERIFICATION WORKSPACE
        ========================================================== */

        var candidateId = $btn.data('id');

        $('#hrVerificationFields').html(
            '<div class="text-muted">Loading candidate profile...</div>'
        );

        $.ajax({
            url: API_BASE + '/onboarding/getEmployeeVerificationData.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: candidateId
            },

            success: function(res) {

                if (!res.success) {
                    $('#hrVerificationFields').html(
                        '<div class="text-danger">Candidate profile not found.</div>'
                    );
                    return;
                }

                var d = res.data;
                var verify = res.verify || {};

                $('#verifiedBadgeArea').html('');

                /* ===============================
                   VERIFIED BADGE
                =============================== */

                if ((d.profileStatus || '').toLowerCase() === 'verified') {

                    $('#verifiedBadgeArea').html(`
                <button type="button"
                    class="btn btn-sm btn-outline-success mt-2 pe-none">
                    <i class="ri-checkbox-circle-line me-1"></i>
                    Verified • ${formatDate(d.updatedAt)}
                </button>
            `);

                } else {

                    $('#verifiedBadgeArea').html(`
                <button type="button"
                    class="btn btn-sm btn-outline-warning mt-2 pe-none">
                    <i class="ri-time-line me-1"></i>
                    Pending Final Verification
                </button>
            `);
                }

                $('#verifyEmployeeId').val(d.id);

                if ((d.profileStatus || '').toLowerCase() === 'verified') {

                    $('#finalVerifyBtn').hide();

                    $('#verifiedProfileNotice').remove();

                    $('#hrVerificationForm').append(`
                <div id="verifiedProfileNotice"
                    class="alert alert-success mt-3 mb-0 text-center fw-semibold">
                    <i class="ri-checkbox-circle-fill me-1"></i>
                    This profile is already verified.
                </div>
            `);

                } else {

                    $('#finalVerifyBtn').show();
                    $('#verifiedProfileNotice').remove();
                }

                /* =====================================================
                   FIELD CARD (2 COLUMN GRID)
                ===================================================== */

                function fieldCol(key, label, value) {

                    var status = verify[key]?.status || 'Pending';
                    var remark = verify[key]?.remark || '';
                
                    return `
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                
                            <label class="form-label fw-semibold">
                                ${label}
                            </label>
                
                            <input type="text"
                                class="form-control mb-2"
                                name="${key}"
                                value="${safe(value)}">
                
                            <select
                                class="form-select verify-select field-status
                                ${status === 'Verified'
                                    ? 'status-success'
                                    : status === 'Rejected'
                                        ? 'status-danger'
                                        : 'status-info'}"
                                data-key="${key}"
                                name="status_${key}">
                
                                <option value="Pending"
                                    ${status === 'Pending' ? 'selected' : ''}>
                                    Pending
                                </option>
                
                                <option value="Verified"
                                    ${status === 'Verified' ? 'selected' : ''}>
                                    Verified
                                </option>
                
                                <option value="Rejected"
                                    ${status === 'Rejected' ? 'selected' : ''}>
                                    Rejected
                                </option>
                
                            </select>
                
                            <div class="mt-2 rejection-remark-wrapper
                                ${status === 'Rejected' ? '' : 'd-none'}">
                
                                <textarea
                                    class="form-control field-remark"
                                    name="remark_${key}"
                                    rows="3"
                                    placeholder="Enter rejection remark">${remark}</textarea>
                
                            </div>
                
                        </div>
                    </div>
                    `;
                }

                function departmentCol(selectedVal) {

                    var status = verify['departmentName']?.status || 'Pending';
                    
                    var remark =  verify['departmentName']?.remark || '';

                    var options = '<option value="">Select Department</option>';

                    departmentList.forEach(function(dep) {

                        var selected = dep === selectedVal ? 'selected' : '';

                        options += '<option value="' + dep + '" ' + selected + '>' +
                            dep + '</option>';
                    });

                    return `
                <div class="col-md-6">
                    <div class="border rounded p-3 h-100">

                        <label class="form-label fw-semibold">
                            Department
                        </label>

                        <select class="form-select mb-2"
                            name="departmentName">

                            ${options}

                        </select>

                        <select class="form-select verify-select ${status=='Verified' ? 'status-success' : 'status-info'}"
                            name="status_departmentName">

                            <option value="Pending" ${status=='Pending' ? 'selected' : ''}>Pending</option>
                            <option value="Verified" ${status=='Verified' ? 'selected' : ''}>Verified</option>

                        </select>

                    </div>
                </div>
                `;
                }
                
                
                

                /* =====================================================
                   DOCUMENT CARD
                ===================================================== */

                var folder = d.folderPath;

                function docCard(label, file, key) {

                    var verification = verify[key] || {};
                
                    var status = verification.status || 'Pending';
                    var remark = verification.remark || '';
                
                    var statusClass =
                        status === 'Verified'
                            ? 'status-success'
                            : status === 'Rejected'
                                ? 'status-danger'
                                : 'status-info';
                
                    var viewBtn = file
                        ? `<a target="_blank"
                                class="btn btn-sm btn-outline-primary me-2"
                                href="${folder + file}">
                                View
                           </a>`
                        : '<span class="text-muted">Not Uploaded</span>';
                
                    return `
                    <div class="col-md-6">
                        <div class="border rounded p-3 h-100">
                
                            <div class="fw-semibold mb-2">
                                ${label}
                            </div>
                
                            <div class="mb-2">
                                ${viewBtn}
                            </div>
                
                            <input
                                type="file"
                                class="form-control mb-2"
                                name="replace_${key}">
                
                            <select
                                class="form-select verify-select document-status ${statusClass}"
                                data-key="${key}"
                                name="docstatus_${key}">
                
                                <option value="Pending"
                                    ${status === 'Pending' ? 'selected' : ''}>
                                    Pending
                                </option>
                
                                <option value="Verified"
                                    ${status === 'Verified' ? 'selected' : ''}>
                                    Verified
                                </option>
                
                                <option value="Rejected"
                                    ${status === 'Rejected' ? 'selected' : ''}>
                                    Rejected
                                </option>
                
                            </select>
                
                            <div class="mt-2 rejection-remark-wrapper
                                ${status === 'Rejected' ? '' : 'd-none'}">
                
                                <textarea
                                    class="form-control document-remark"
                                    name="remark_${key}"
                                    rows="3"
                                    placeholder="Enter rejection remark">${remark}</textarea>
                
                            </div>
                
                        </div>
                    </div>
                    `;
                }

                var html = '';

                /* =====================================================
                   PERSONAL DETAILS
                ===================================================== */

                html += `
                    <div class="mb-3">
                        <h6 class="fw-bold text-primary">Personal Details</h6>
                    </div>
            
                    <div class="row g-3">
                        ${fieldCol('mobileNumber','Mobile Number',d.mobileNumber)}
                        ${fieldCol('alternativeNumber','Alternative Number',d.alternativeNumber)}
                        ${fieldCol('emergencyContactNumber','Emergency Contact',d.emergencyContactNumber)}
                        ${fieldCol('dateOfBirth','Date Of Birth',d.dateOfBirth)}
                        ${fieldCol('gender','Gender',d.gender)}
                        ${fieldCol('maritalStatus','Marital Status',d.maritalStatus)}
                    </div>
                    `;

                /* =====================================================
                   ADDRESS
                ===================================================== */

                html += `
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold text-primary">Address Details</h6>
                    </div>
            
                    <div class="row g-3">
                        ${fieldCol('permanentAddress','Permanent Address',d.permanentAddress)}
                        ${fieldCol('localAddress','Local Address',d.localAddress)}
                        ${fieldCol('cityName','City',d.cityName)}
                        ${fieldCol('stateName','State',d.stateName)}
                        ${fieldCol('pinCode','PIN Code',d.pinCode)}
                    </div>
                    `;

                /* =====================================================
                   SOCIAL
                ===================================================== */

                html += `
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold text-primary">Social Profiles</h6>
                    </div>
            
                    <div class="row g-3">
                        ${fieldCol('linkedInProfile','LinkedIn',d.linkedInProfile)}
                        ${fieldCol('instagramProfile','Instagram',d.instagramProfile)}
                    </div>
                    `;


                html += `
                <div class="mt-4 mb-3">
                    <h6 class="fw-bold text-primary">Employment Details</h6>
                </div>
    
                <div class="row g-3">
                    ${departmentCol(d.departmentName)}
                </div>
                `;

                /* =====================================================
                   BANK
                ===================================================== */

                html += `
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold text-primary">Bank Details</h6>
                    </div>
            
                    <div class="row g-3">
                        ${fieldCol('accountHolderName','Account Holder',d.accountHolderName)}
                        ${fieldCol('bankName','Bank Name',d.bankName)}
                        ${fieldCol('accountNumber','Account Number',d.accountNumber)}
                        ${fieldCol('ifscCode','IFSC Code',d.ifscCode)}
                        ${fieldCol('branchName','Branch Name',d.branchName)}
                    </div>
                    `;

                /* =====================================================
                   KYC
                ===================================================== */

                html += `
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold text-primary">KYC Details</h6>
                    </div>
            
                    <div class="row g-3">
                        ${fieldCol('aadhaarNumber','Aadhaar Number',d.aadhaarNumber)}
                        ${fieldCol('panNumber','PAN Number',d.panNumber)}
                    </div>
                    `;

                /* =====================================================
                   DOCUMENTS
                ===================================================== */

                html += `
                    <div class="mt-4 mb-3">
                        <h6 class="fw-bold text-primary">Documents Verification</h6>
                    </div>
            
                    <div class="row g-3">
                        ${docCard('Profile Photo', d.profilePhoto, 'profilePhoto')}
                        ${docCard('Aadhaar File', d.aadhaarFile, 'aadhaarFile')}
                        ${docCard('PAN File', d.panFile, 'panFile')}
                        
                        ${docCard('12th Marksheet', d.marksheet12File, 'marksheet12File')}
                        ${docCard('Graduation File', d.graduationFile, 'graduationFile')}
                        ${docCard('Bank Passbook', d.bankPassbookFile, 'bankPassbookFile')}
                        ${docCard('Previews Company Document', d.marksheet10File, 'marksheet10File')}
                    </div>
                    `;

                $('#hrVerificationFields').html(html);
            },

            error: function() {
                $('#hrVerificationFields').html(
                    '<div class="text-danger">Unable to load profile data.</div>'
                );
            }
        });

    });
    
    
    $(document).on('click', '#submitHrReviewBtn', function() {

        var employeeUserId =
            $('#verifyEmployeeId').val();
    
        if (!employeeUserId) {
            return;
        }
    
        var $btn = $(this);
    
        $btn.prop('disabled', true);
    
        $.ajax({
            url: API_BASE + '/onboarding/submitHrReview.php',
            type: 'POST',
            dataType: 'json',
    
            data: {
                employeeUserId: employeeUserId
            },
    
            success: function(res) {
    
                if (res.success) {
    
                    window.showToast &&
                        window.showToast(
                            'success',
                            res.message
                        );
    
                } else {
    
                    window.showToast &&
                        window.showToast(
                            'danger',
                            res.message
                        );
                }
            },
    
            error: function() {
    
                window.showToast &&
                    window.showToast(
                        'danger',
                        'Unable to submit review.'
                    );
            },
    
            complete: function() {
    
                $btn.prop('disabled', false);
            }
        });
    
    });


    $(document).on('change', 'select[name="departmentName"]', function() {

        var $select = $(this);

        var employeeUserId = $('#verifyEmployeeId').val();

        var verifyStatus =
            $('select[name="status_departmentName"]').val() || 'Pending';
            
       var reviewRemark = '';

        $.ajax({
            url: API_BASE + '/onboarding/updateHrVerificationField.php',
            type: 'POST',
            dataType: 'json',
            
            
    
            data: {
                employeeUserId: employeeUserId,
                fieldName: 'departmentName',
                fieldValue: $select.val(),
                verifyStatus: verifyStatus,
                reviewRemark: reviewRemark
            },

            success: function(res) {

                if (res.success) {

                    glowSaved($select);

                    window.showToast &&
                        window.showToast('success', 'Department Updated');

                    checkFinalVerifyReady();
                }
            }
        });

    });




    

    $(document).on(
        'change',
        'select[name^="status_"], select[name^="docstatus_"]',
        function() {
    
            $(this).removeClass(
                'status-success status-info status-danger'
            );
    
            if ($(this).val() === 'Verified') {
    
                $(this).addClass('status-success');
    
            } else if ($(this).val() === 'Rejected') {
    
                $(this).addClass('status-danger');
    
            } else {
    
                $(this).addClass('status-info');
            }
    
        }
    );
    
    $(document).on(
        'change',
        '.field-status, .document-status',
        function() {
    
            var $card =
                $(this).closest('.border');
    
            var $remarkWrapper =
                $card.find('.rejection-remark-wrapper');
    
            if ($(this).val() === 'Rejected') {
    
                $remarkWrapper.removeClass('d-none');
    
            } else {
    
                $remarkWrapper.addClass('d-none');
    
                $remarkWrapper.find('textarea').val('');
            }
        }
    );
    
    
            $(document).on(
    'blur',
    '.field-remark, .document-remark',
    function() {

        var $textarea = $(this);

        var fieldName =
            $textarea.attr('name').replace('remark_', '');

        var employeeUserId =
            $('#verifyEmployeeId').val();

        var verifyStatus =
            $('select[name="status_' + fieldName + '"], select[name="docstatus_' + fieldName + '"]')
            .val() || 'Pending';

        var fieldValue = '';

        // For normal fields, preserve current value
        if ($textarea.hasClass('field-remark')) {

            var $field =
                $('input[name="' + fieldName + '"], select[name="' + fieldName + '"]');

            fieldValue = $field.val() || '';
        }

        $.ajax({
            url: API_BASE + '/onboarding/updateHrVerificationField.php',
            type: 'POST',
            dataType: 'json',
            data: {
                employeeUserId: employeeUserId,
                fieldName: fieldName,
                fieldValue: fieldValue,
                verifyStatus: verifyStatus,
                reviewRemark: $textarea.val()
            },

            success: function(res) {

                if (res.success) {

                    glowSaved($textarea);

                    checkFinalVerifyReady();
                }
            }
        });

    }
);

    /* ─── 11. VIEW JOINING LIST ───────────────────────────── */
   $(document).on('click', '#viewJoiningListBtn', function () {

    $('#joiningListTableBody').html(
        '<tr>' +
            '<td colspan="5" class="text-center text-muted">' +
                'Loading...' +
            '</td>' +
        '</tr>'
    );

    $('#joiningListModal').modal('show');

    $.ajax({
        url: API_BASE + '/onboarding/getJoiningList.php',
        type: 'GET',
        dataType: 'json',

        success: function (response) {

            if (!response || response.success !== true) {

                $('#joiningListTableBody').html(
                    '<tr>' +
                        '<td colspan="5" class="text-center text-muted">' +
                            safe(response?.message || 'No records found.') +
                        '</td>' +
                    '</tr>'
                );

                return;
            }

            var rows = Array.isArray(response.data) ? response.data : [];

            if (rows.length === 0) {

                $('#joiningListTableBody').html(
                    '<tr>' +
                        '<td colspan="5" class="text-center text-muted">' +
                            'No joined candidates found.' +
                        '</td>' +
                    '</tr>'
                );

                return;
            }

            var html = rows.map(function (item, index) {

                var joiningStatus = item.joiningStatus || 'Open';

                return `
                    <tr>
                        <td>${index + 1}</td>

                        <td>
                            <div class="fw-semibold">
                                ${safe(item.fullName || '')}
                            </div>

                            <div class="small text-muted">
                                ${safe(item.phoneNumber || '')}
                            </div>

                            <div class="small text-muted">
                                ${safe(item.email || '')}
                            </div>
                        </td>

                        <td>
                            ${safe(item.appliedRole || '-')}
                        </td>

                        <td>
                            ${formatDate(item.joiningDate || '')}
                        </td>

                        <td>
                            <select 
                                class="form-select form-select-sm updateJoiningStatus"
                                data-id="${safe(item.id)}"
                            >
                                <option value="Open"
                                    ${joiningStatus === 'Open' ? 'selected' : ''}>
                                    Open
                                </option>

                                <option value="Confirmed"
                                    ${joiningStatus === 'Confirmed' ? 'selected' : ''}>
                                    Confirmed
                                </option>
                            </select>
                        </td>
                    </tr>
                `;

            }).join('');

            $('#joiningListTableBody').html(html);
        },

        error: function (xhr) {

            console.error(xhr.responseText);

            $('#joiningListTableBody').html(
                '<tr>' +
                    '<td colspan="5" class="text-center text-danger">' +
                        'Unable to load joining list.' +
                    '</td>' +
                '</tr>'
            );
        }
    });
});

    $(document).on('change', '.updateJoiningStatus', function() {

        var candidateId = $(this).data('id');
        var joiningStatus = $(this).val();

        $.ajax({
            url: API_BASE + '/onboarding/updateJoiningStatus.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: candidateId,
                joiningStatus: joiningStatus
            },

            success: function(response) {

                if (response.success) {
                    window.showToast &&
                        window.showToast('success', response.message);
                } else {
                    window.showToast &&
                        window.showToast('danger', response.message);
                }
            },

            error: function() {
                window.showToast &&
                    window.showToast('danger', 'Unable to update status.');
            }
        });

    });


    /* ==========================================================
      REALTIME HR VERIFICATION JS
      Works with:
      updateHrVerificationField.php
      checkVerificationReady.php
      finalVerifyCandidate.php
      ========================================================== */

    /* ----------------------------------------------------------
       LIGHT SUCCESS GLOW
    ---------------------------------------------------------- */
    function glowSaved($el) {

        $el.addClass('border-success');

        $el.css({
            boxShadow: '0 0 0 0.2rem rgba(25,135,84,.18)',
            transition: 'all .25s ease'
        });

        setTimeout(function() {
            $el.removeClass('border-success');
            $el.css({
                boxShadow: ''
            });
        }, 1200);
    }

    /* ----------------------------------------------------------
       CHECK FINAL VERIFY READY
    ---------------------------------------------------------- */
  
    
    function checkFinalVerifyReady() {

        var employeeUserId = $('#verifyEmployeeId').val();
    
        if (!employeeUserId) {
            return;
        }
    
        var departmentName =
            $('select[name="departmentName"]').val() || '';
    
        $.ajax({
            url: API_BASE + '/onboarding/checkVerificationReady.php',
            type: 'GET',
            dataType: 'json',
    
            data: {
                employeeUserId: employeeUserId,
                departmentName: departmentName
            },
    
            success: function(res) {
    
                console.log(res);
    
                if (res.success && res.ready) {
    
                    $('#finalVerifyBtn')
                        .prop('disabled', false)
                        .removeClass('btn-secondary')
                        .addClass('btn-success');
    
                } else {
    
                    $('#finalVerifyBtn')
                        .prop('disabled', true)
                        .removeClass('btn-success')
                        .addClass('btn-secondary');
                }
            }
        });
    }
    
    
    
    /* ----------------------------------------------------------
       AUTO SAVE TEXT / INPUT FIELD
    ---------------------------------------------------------- */
    $(document).on('blur', '#hrVerificationFields input[type="text"]', function() {

        var $input = $(this);

        if (
            $input.attr('name').indexOf('replace_') === 0
        ) {
            return;
        }

        var fieldName = $input.attr('name');

        if (
            fieldName.indexOf('status_') === 0 ||
            fieldName.indexOf('docstatus_') === 0
        ) {
            return;
        }

        var employeeUserId = $('#verifyEmployeeId').val();

        var verifyStatus =
            $('select[name="status_' + fieldName + '"]').val() || 'Pending';
            
        var reviewRemark =
                $('textarea[name="remark_' + fieldName + '"]').val() || '';

        $.ajax({
            url: API_BASE + '/onboarding/updateHrVerificationField.php',
            type: 'POST',
            dataType: 'json',
            
            data: {
                employeeUserId: employeeUserId,
                fieldName: fieldName,
                fieldValue: $input.val(),
                verifyStatus: verifyStatus,
                reviewRemark: reviewRemark
            },

            success: function(res) {

                if (res.success) {

                    glowSaved($input);

                    window.showToast &&
                        window.showToast('success', 'Updated');

                    checkFinalVerifyReady();
                }
            }
        });

    });

    /* ----------------------------------------------------------
       SAVE STATUS CHANGE (FIELD VERIFIED/PENDING)
    ---------------------------------------------------------- */
    $(document).on('change', 'select[name^="status_"]', function() {

        var $select = $(this);

        var fieldName =
            $select.attr('name').replace('status_', '');

        var employeeUserId = $('#verifyEmployeeId').val();

        var fieldValue =
            $('input[name="' + fieldName + '"]').val() || '';
            
        var reviewRemark =
                $('textarea[name="remark_' + fieldName + '"]').val() || '';
                
        if ($select.val() !== 'Rejected') {
                reviewRemark = '';
            }

        $.ajax({
            url: API_BASE + '/onboarding/updateHrVerificationField.php',
            type: 'POST',
            dataType: 'json',
            data: {
                employeeUserId: employeeUserId,
                fieldName: fieldName,
                fieldValue: fieldValue,
                verifyStatus: $select.val(),
                reviewRemark: reviewRemark
            },

            success: function(res) {

                if (res.success) {

                    glowSaved($select);

                    window.showToast &&
                        window.showToast('success', 'Status Updated');

                    checkFinalVerifyReady();
                }
            }
        });

    });

    /* ----------------------------------------------------------
       SAVE DOCUMENT STATUS CHANGE
    ---------------------------------------------------------- */
    $(document).on('change', 'select[name^="docstatus_"]', function() {

        var $select = $(this);

        var fieldName =
            $select.attr('name').replace('docstatus_', '');

        var employeeUserId = $('#verifyEmployeeId').val();
        
        var reviewRemark =
                $('textarea[name="remark_' + fieldName + '"]').val() || '';
                
        if ($select.val() !== 'Rejected') {
                reviewRemark = '';
            }

        $.ajax({
            url: API_BASE + '/onboarding/updateHrVerificationField.php',
            type: 'POST',
            dataType: 'json',
            data: {
                employeeUserId: employeeUserId,
                fieldName: fieldName,
                fieldValue: '',
                verifyStatus: $select.val(),
                reviewRemark: reviewRemark
            },

            success: function(res) {

                if (res.success) {

                    glowSaved($select);

                    window.showToast &&
                        window.showToast('success', 'Document Status Updated');

                    checkFinalVerifyReady();
                }
            }
        });

    });

    /* ----------------------------------------------------------
       FINAL VERIFY BUTTON
    ---------------------------------------------------------- */
    $(document).on('click', '#finalVerifyBtn', function() {

        var $btn = $(this);

        if ($btn.prop('disabled')) {
            return;
        }

        var employeeUserId = $('#verifyEmployeeId').val();

        $btn.prop('disabled', true).text('Processing...');

        $.ajax({
            url: API_BASE + '/onboarding/finalVerifyCandidate.php',
            type: 'POST',
            dataType: 'json',
            data: {
                employeeUserId: employeeUserId
            },

            success: function(res) {

                if (res.success) {

                    window.showToast &&
                        window.showToast(
                            'success',
                            'Candidate verified successfully'
                        );

                    $('#submissionDetailsModal').modal('hide');

                    location.reload();

                } else {

                    window.showToast &&
                        window.showToast(
                            'danger',
                            res.message || 'Unable to verify.'
                        );

                    $btn
                        .prop('disabled', false)
                        .text('Final Verify');
                }
            },

            error: function() {

                window.showToast &&
                    window.showToast(
                        'danger',
                        'Server error.'
                    );

                $btn
                    .prop('disabled', false)
                    .text('Final Verify');
            }
        });

    });

    /* ----------------------------------------------------------
      MODAL OPEN DEFAULT BUTTON STATE
    ---------------------------------------------------------- */
    $(document).on('shown.bs.modal', '#submissionDetailsModal', function() {

        if ($('#finalVerifyBtn').is(':visible')) {

            $('#finalVerifyBtn')
                .prop('disabled', true)
                .removeClass('btn-success')
                .addClass('btn-secondary');

            setTimeout(function() {
                checkFinalVerifyReady();
            }, 400);
        }

    });

});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
