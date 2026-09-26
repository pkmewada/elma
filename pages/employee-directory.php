<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/basic-config.php';

// Designation doubles as the permission role, so suggest the Basic Setup roles.
$designationRoleOptions = getBasicConfig()['organizationRoles'] ?? [];

function esc_ed(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function resolveProfilePhotoUrl(array $row): string
{
    

    $profilePhoto = trim((string) ($row['profilePhoto'] ?? ''));
    if ($profilePhoto === '') {
        return '';
    }

    // ✅ If already full URL
    if (preg_match('#^https?://#i', $profilePhoto)) {
        return $profilePhoto;
    }

    // Normalize slashes
    $profilePhoto = str_replace('\\', '/', $profilePhoto);

    // ✅ If already contains uploads path
    if (strpos($profilePhoto, 'uploads/') === 0) {
        return rtrim(BASE_URL, '/') . '/' . ltrim($profilePhoto, '/');
    }

    // ✅ If starts with /uploads
    if (strpos($profilePhoto, '/uploads/') === 0) {
        return rtrim(BASE_URL, '/') . $profilePhoto;
    }

    // ✅ Try to locate file dynamically
    $basePath = dirname(__DIR__) . '/uploads/candidates/';
    $matches = glob($basePath . '*/' . $profilePhoto);

    if (!empty($matches)) {
        $relative = str_replace(dirname(__DIR__) . '/', '', $matches[0]);
        $relative = str_replace('\\', '/', $relative);

        return rtrim(BASE_URL, '/') . '/' . ltrim($relative, '/');
    }

    return '';
}

$rows = [];
$loadError = '';

$stmt = mysqli_prepare($con, "
    SELECT
        id,
        employeeCode,
        fullName,
        mobileNumber,
        emailAddress,
        departmentName,
        designationName,
        joiningDate,
        employmentStatus,
        accountStatus,
        profilePhoto,
        updatedAt
    FROM employeeusers
    ORDER BY id DESC
");

if ($stmt) {
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($result && ($row = mysqli_fetch_assoc($result))) {
        if (empty($row['employmentStatus'])) {
            $row['employmentStatus'] = (strtolower((string) ($row['accountStatus'] ?? 'active')) === 'inactive')
                ? 'Deactive'
                : 'Active';
        }
        $row['photoUrl'] = resolveProfilePhotoUrl($row);
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
} else {
    $loadError = 'Unable to load employee directory.';
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

.status-chip-success {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
}

.status-chip-danger {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), 0.3);
}

.directory-filter-bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.directory-filter-bar .form-select {
    min-width: 180px;
}

.employment-select {
    font-weight: 600;
    border-width: 1px;
    min-width: 130px;
}

.employment-select.status-active {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), .35);
}

.employment-select.status-deactive {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), .35);
}

.employee-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    object-fit: cover;
    border: 1px solid rgba(0, 0, 0, .06);
}

.employee-view-cover {
    height: 120px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(var(--primary-rgb), .18), rgba(var(--info-rgb), .12));
}

.employee-view-avatar {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    object-fit: cover;
    border: 4px solid #fff;
    margin-top: -48px;
    box-shadow: 0 8px 20px rgba(0,0,0,.12);
}

.info-box {
    border: 1px solid rgba(0,0,0,.06);
    border-radius: 12px;
    padding: 16px;
    background: #fff;
    height: 100%;
}

.info-label {
    font-size: 12px;
    color: #8c9097;
    margin-bottom: 3px;
}

.info-value {
    font-weight: 600;
    color: #212529;
    word-break: break-word;
}

.document-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 8px;
    background: rgba(var(--primary-rgb), .08);
    margin: 4px;
    font-size: 13px;
}

#employeeEditModal .modal-dialog {
    max-height: calc(100vh - 40px);
}

#employeeEditModal .modal-content {
    max-height: calc(100vh - 40px);
    overflow: hidden;
}

#employeeEditModal .modal-body {
    max-height: calc(100vh - 160px);
    overflow-y: auto;
}

.form-check-input {
    width: 1.4em;
    height: 1.4em;
    background-color: var(--custom-white);
    border: 1px solid grey!important;
}
</style>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Employee Directory</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Employee Directory</li>
                </ol>
            </div>
        </div>

        <?php if ($loadError !== ''): ?>
        <div class="alert alert-danger"><?= esc_ed($loadError) ?></div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">

                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">

                            <!-- Left Side -->
                            <div class="d-flex flex-wrap align-items-center gap-2">

                                <!-- Export -->
                                <div style="min-width:160px;">
                                    <div class="btn-group w-100">
                                        <button type="button" class="btn btn-outline-primary dropdown-toggle w-100"
                                            data-bs-toggle="dropdown" aria-expanded="false">
                                            Export
                                        </button>

                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item export-btn" data-type="csv"
                                                    href="javascript:void(0);">
                                                    CSV
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item export-btn" data-type="pdf"
                                                    href="javascript:void(0);">
                                                    PDF
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <!-- Employment Filter -->
                                <div style="min-width:220px;">
                                    <select id="employmentFilter" class="form-select">
                                        <option value="">Employment Status</option>
                                    </select>
                                </div>

                            </div>

                            <!-- Right Side Search -->
                            <div style="min-width:260px;">
                                <input id="directorySearch" class="form-control" placeholder="Search employees..."
                                    autocomplete="off">
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
                        <div class="card-title">Employee Directory</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="employee-directory-table" data-ui-table="mamix"
                                class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Details</th>
                                        <th>Employee Code</th>
                                        <th>Role/Dept</th>
                                        <th>Joining Date</th>
                                        <th>Account Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $i => $row): ?>
                                    <?php
                                    $employmentStatus = (string) ($row['employmentStatus'] ?? 'Active');
                                    $accountStatus = (string) ($row['accountStatus'] ?? 'Active');
                                    $isActive = strtolower($employmentStatus) === 'active';
                                    ?>
                                    <tr data-id="<?= (int) $row['id'] ?>">
                                        <td><?= $i + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-start gap-2">
                                                <?php if (!empty($row['photoUrl'])): ?>
                                                <img src="<?= esc_ed($row['photoUrl']) ?>" class="employee-avatar"
                                                    alt="profile">
                                                <?php else: ?>
                                               <img src="<?= ASSET_URL ?>/assets/images/faces/14.jpg"
                                                     class="employee-avatar"
                                                     alt="profile">
                                                <?php endif; ?>
                                                <div>
                                                    <div class="fw-semibold text-dark"><?= esc_ed($row['fullName']) ?>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?= esc_ed($row['mobileNumber'] ?: '-') ?></div>
                                                    <div class="small text-muted"><?= esc_ed($row['emailAddress']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= esc_ed($row['employeeCode'] ?: '-') ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= esc_ed($row['designationName'] ?: '-') ?></div>
                                            <div class="small text-muted"><?= esc_ed($row['departmentName'] ?: '-') ?>
                                            </div>
                                        </td>
                                        <td><?= !empty($row['joiningDate']) ? esc_ed(date('d M Y', strtotime((string) $row['joiningDate']))) : '-' ?>
                                        </td>
                                        <td data-employment-status="<?= esc_ed($employmentStatus) ?>">
                                            <select
                                                class="form-select employment-select <?= $isActive ? 'status-active' : 'status-deactive' ?>">
                                                <option value="Active"
                                                    <?= $employmentStatus === 'Active' ? 'selected' : '' ?>>Active
                                                </option>
                                                <option value="Deactive"
                                                    <?= $employmentStatus === 'Deactive' ? 'selected' : '' ?>>Deactive
                                                </option>
                                            </select>
                                        </td>
                                        <td>
                                            <div class="btn-list">
                                                <button type="button"
                                                    class="btn btn-icon btn-sm btn-info-light view-profile-btn"
                                                    title="View Profile">
                                                    <i class="ri-eye-line"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-icon btn-sm btn-warning-light edit-profile-btn"
                                                    title="Edit">
                                                    <i class="ri-edit-line"></i>
                                                </button>
                                            </div>
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

<div class="modal fade" id="employeeViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Employee Information</h5>
                    <small class="text-muted">Administrative / HR View</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body" id="employeeViewModalBody">
                <div class="text-center py-5 text-muted">
                    Loading employee information...
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="employeeEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header">
                <div>
                    <h5 class="modal-title mb-0">Edit Employee Details</h5>
                    <small class="text-muted">HR / Administrative Edit</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form id="employeeEditForm">
                <input type="hidden" name="id" id="editEmployeeId">

                <div class="modal-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <h6 class="fw-semibold text-primary">Basic Information</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="fullName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="userName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="emailAddress">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" name="mobileNumber">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Alternative Number</label>
                            <input type="text" class="form-control" name="alternativeNumber">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Emergency Contact</label>
                            <input type="text" class="form-control" name="emergencyContactNumber">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" name="dateOfBirth">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Gender</label>
                            <input type="text" class="form-control" name="gender">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Marital Status</label>
                            <input type="text" class="form-control" name="maritalStatus">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-semibold text-primary">Job Information</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="departmentName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Designation</label>
                            <input type="text" class="form-control" name="designationName" list="designationRoleOptions">
                            <datalist id="designationRoleOptions">
                                <?php foreach ($designationRoleOptions as $roleOption): ?>
                                <option value="<?= esc_ed((string) $roleOption) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Joining Date</label>
                            <input type="date" class="form-control" name="joiningDate">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Employee Type</label>
                            <input type="text" class="form-control" name="employeeType">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Reporting Manager</label>
                            <input type="text" class="form-control" name="reportingManager">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Joining Status</label>
                            <input type="text" class="form-control" name="joiningStatus">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-semibold text-primary">Address Information</h6>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Permanent Address</label>
                            <textarea class="form-control" name="permanentAddress" rows="2"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Local Address</label>
                            <textarea class="form-control" name="localAddress" rows="2"></textarea>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" class="form-control" name="cityName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">State</label>
                            <input type="text" class="form-control" name="stateName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Pin Code</label>
                            <input type="text" class="form-control" name="pinCode">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-semibold text-primary">Salary Information</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Basic Salary</label>
                            <input type="number" step="0.01" class="form-control" name="basicSalary">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">HRA Amount</label>
                            <input type="number" step="0.01" class="form-control" name="hraAmount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Allowance Amount</label>
                            <input type="number" step="0.01" class="form-control" name="allowanceAmount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Deduction Amount</label>
                            <input type="number" step="0.01" class="form-control" name="deductionAmount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Net Salary</label>
                            <input type="number" step="0.01" class="form-control" name="netSalary">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Other Earning 1</label>
                            <input type="number" step="0.01" class="form-control" name="otherEarning1Amount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Other Earning 2</label>
                            <input type="number" step="0.01" class="form-control" name="otherEarning2Amount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Other Earning 3</label>
                            <input type="number" step="0.01" class="form-control" name="otherEarning3Amount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Other Allowances</label>
                            <input type="number" step="0.01" class="form-control" name="otherAllowanceAmount">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Payment Frequency</label>
                            <input type="text" class="form-control" name="paymentFrequency">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Next Increment Date</label>
                            <input type="date" class="form-control" name="nextIncrementDate">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-semibold text-primary">Bank Information</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Account Holder Name</label>
                            <input type="text" class="form-control" name="accountHolderName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Bank Name</label>
                            <input type="text" class="form-control" name="bankName">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Account Number</label>
                            <input type="text" class="form-control" name="accountNumber">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">IFSC Code</label>
                            <input type="text" class="form-control" name="ifscCode">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Branch Name</label>
                            <input type="text" class="form-control" name="branchName">
                        </div>

                        <div class="col-12 mt-3">
                            <h6 class="fw-semibold text-primary">KYC / Other</h6>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Aadhaar Number</label>
                            <input type="text" class="form-control" name="aadhaarNumber">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">PAN Number</label>
                            <input type="text" class="form-control" name="panNumber">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">LinkedIn</label>
                            <input type="text" class="form-control" name="linkedInProfile">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Instagram</label>
                            <input type="text" class="form-control" name="instagramProfile">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Skills</label>
                            <input type="text" class="form-control" name="skills">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">About Me</label>
                            <textarea class="form-control" name="aboutMe" rows="3"></textarea>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">HR Remark</label>
                            <textarea class="form-control" name="hrRemark" rows="3"></textarea>
                        </div>

                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveEmployeeEditBtn">
                        Save Employee Details
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
<script>
$(function() {
    var table = $('#employee-directory-table').DataTable({
        dom: "<'d-flex align-items-center justify-content-between mb-2'<'dt-buttons'B>>" +
            "rt" +
            "<'d-flex align-items-center justify-content-between mt-2'<'small'i><'small'p>>",

        pageLength: 10,
        ordering: true,
        order: [[0, 'asc']],
        lengthChange: false,
        autoWidth: false,
        searching: true,

        language: {
            search: '',
            searchPlaceholder: 'Search...'
        },

        buttons: [
            {
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

            api.rows({ page: 'current' }).every(function(rowIdx) {
                $(this.node()).find('td:eq(0)').html(rowIdx + 1);
            });
        }
    });

    var $employmentFilter = $('#employmentFilter');

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function displayValue(value) {
        value = $.trim(String(value || ''));
        return value ? escapeHtml(value) : '-';
    }

    function formatDate(value) {
        if (!value) return '-';

        var date = new Date(value);

        if (isNaN(date.getTime())) {
            return displayValue(value);
        }

        return date.toLocaleDateString('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric'
        });
    }

    function fillFilter($select, attrName) {
        var values = [];

        $('#employee-directory-table tbody tr').each(function() {
            var val = $(this).find('td[' + attrName + ']').attr(attrName);

            if (val && $.inArray(val, values) === -1) {
                values.push(val);
            }
        });

        values.sort();

        $.each(values, function(i, item) {
            $select.append(
                $('<option>', {
                    value: item,
                    text: item
                })
            );
        });
    }

    fillFilter($employmentFilter, 'data-employment-status');

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'employee-directory-table') {
            return true;
        }

        var row = table.row(dataIndex).node();
        if (!row) return true;

        var employment = $(row)
            .find('td[data-employment-status]')
            .attr('data-employment-status') || '';

        var employmentFilter = $employmentFilter.val();

        if (employmentFilter && employment !== employmentFilter) {
            return false;
        }

        return true;
    });

    $employmentFilter.on('change', function() {
        table.draw();
    });

    $('#directorySearch').on('keyup input', function() {
        table.search($.trim($(this).val())).draw();
    });

    $('.export-btn').on('click', function() {
        var type = $(this).data('type');

        if (type === 'csv') {
            table.buttons('.buttons-csv').trigger();
        }

        if (type === 'pdf') {
            table.buttons('.buttons-pdf').trigger();
        }
    });

    function updateStatusVisual($row, employmentStatus) {
        var $select = $row.find('.employment-select');

        $select.removeClass('status-active status-deactive')
            .addClass(employmentStatus === 'Active' ? 'status-active' : 'status-deactive')
            .val(employmentStatus);

        $row.find('td[data-employment-status]').attr('data-employment-status', employmentStatus);
        table.row($row).invalidate('dom').draw(false);
    }

    $(document).on('change', '.employment-select', function() {
        var $select = $(this);
        var $row = $select.closest('tr');
        var id = parseInt($row.data('id'), 10);
        var nextStatus = $select.val();

        $.ajax({
            url: API_BASE + '/employee/updateEmployeeEmploymentStatus.php',
            type: 'POST',
            dataType: 'json',
            data: {
                id: id,
                employmentStatus: nextStatus
            },
            success: function(res) {
                if (!res || !res.success) {
                    window.showToast && window.showToast(
                        'danger',
                        (res && res.message) || 'Unable to update status.'
                    );
                    table.draw(false);
                    return;
                }

                updateStatusVisual($row, res.data.employmentStatus);
                window.showToast && window.showToast(
                    'success',
                    res.message || 'Status updated.'
                );
            },
            error: function() {
                table.draw(false);
                window.showToast && window.showToast(
                    'danger',
                    'Unable to update status right now.'
                );
            }
        });
    });

    $(document).on('click', '.view-profile-btn', function() {
        var id = parseInt($(this).closest('tr').data('id'), 10);

        $('#employeeViewModalBody').html(`
            <div class="text-center py-5 text-muted">
                Loading employee information...
            </div>
        `);

        var modal = new bootstrap.Modal(document.getElementById('employeeViewModal'));
        modal.show();

        $.ajax({
            url: API_BASE + '/onboarding/getEmployeeDetails.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: id
            },
            success: function(res) {
                if (!res || !res.success) {
                    $('#employeeViewModalBody').html(`
                        <div class="alert alert-danger mb-0">
                            ${escapeHtml((res && res.message) || 'Unable to load employee details.')}
                        </div>
                    `);
                    return;
                }

                renderEmployeeView(res.data);
            },
            error: function() {
                $('#employeeViewModalBody').html(`
                    <div class="alert alert-danger mb-0">
                        Unable to load employee information right now.
                    </div>
                `);
            }
        });
    });

    function infoItem(label, value) {
        return `
            <div class="col-xl-4 col-lg-6 col-md-6">
                <div class="info-box">
                    <div class="info-label">${escapeHtml(label)}</div>
                    <div class="info-value">${displayValue(value)}</div>
                </div>
            </div>
        `;
    }

    function sectionTitle(title, icon) {
        return `
            <div class="col-12 mt-3">
                <h6 class="fw-semibold mb-2">
                    <i class="${icon} me-1"></i>${escapeHtml(title)}
                </h6>
            </div>
        `;
    }

    function documentLink(label, url) {
        if (!url) {
            return `
                <span class="document-link text-muted">
                    <i class="ri-file-warning-line"></i>
                    ${escapeHtml(label)} : Not Uploaded
                </span>
            `;
        }

        return `
            <a href="${escapeHtml(url)}" target="_blank" class="document-link">
                <i class="ri-file-text-line"></i>
                ${escapeHtml(label)}
            </a>
        `;
    }

    function buildEmployeeFileUrl(emp, file) {
        if (!file) {
            return '';
        }

        if (/^https?:\/\//i.test(file)) {
            return file;
        }

        file = String(file).replace(/^\/?uploads\/candidates\/[^\/]+\/?/i, '');

        return (emp.folderPath || '') + file;
    }

    function renderEmployeeView(emp) {
        var photo = buildEmployeeFileUrl(emp, emp.profilePhoto) || '<?= ASSET_URL ?>/assets/images/faces/14.jpg';

        var skillsHtml = '-';

        if (emp.skills) {
            var skills = emp.skills.split(',').map(function(item) {
                return $.trim(item);
            }).filter(Boolean);

            if (skills.length) {
                skillsHtml = skills.map(function(skill) {
                    return `<span class="badge bg-light text-muted border me-1 mb-1">${escapeHtml(skill)}</span>`;
                }).join('');
            }
        }

        var documentsHtml = `
            ${documentLink('Profile Photo', buildEmployeeFileUrl(emp, emp.profilePhoto))}
            ${documentLink('Aadhaar', buildEmployeeFileUrl(emp, emp.aadhaarFile))}
            ${documentLink('PAN', buildEmployeeFileUrl(emp, emp.panFile))}
            ${documentLink('10th / Previous Company Document', buildEmployeeFileUrl(emp, emp.marksheet10File))}
            ${documentLink('12th Marksheet', buildEmployeeFileUrl(emp, emp.marksheet12File))}
            ${documentLink('Graduation', buildEmployeeFileUrl(emp, emp.graduationFile))}
            ${documentLink('Bank Passbook', buildEmployeeFileUrl(emp, emp.bankPassbookFile))}
        `;

        var html = `
            <div class="employee-view-cover"></div>

            <div class="text-center mb-4">
                <img src="${escapeHtml(photo)}" class="employee-view-avatar" alt="Employee">
                <h5 class="fw-semibold mb-1 mt-2">${displayValue(emp.fullName)}</h5>
                <div class="text-muted">
                    ${displayValue(emp.designationName)} · ${displayValue(emp.departmentName)}
                </div>
                <div class="mt-2">
                    <span class="badge bg-primary-transparent">${displayValue(emp.employeeCode)}</span>
                    <span class="badge bg-success-transparent">${displayValue(emp.employmentStatus)}</span>
                    <span class="badge bg-info-transparent">${displayValue(emp.accountStatus)}</span>
                </div>
            </div>

            <div class="row g-3">
                ${sectionTitle('Basic Information', 'ri-user-line')}
                ${infoItem('Full Name', emp.fullName)}
                ${infoItem('Username', emp.userName)}
                ${infoItem('Employee Code', emp.employeeCode)}
                ${infoItem('Email', emp.emailAddress)}
                ${infoItem('Mobile Number', emp.mobileNumber)}
                ${infoItem('Alternative Number', emp.alternativeNumber)}
                ${infoItem('Emergency Contact', emp.emergencyContactNumber)}
                ${infoItem('Date of Birth', formatDate(emp.dateOfBirth))}
                ${infoItem('Gender', emp.gender)}
                ${infoItem('Marital Status', emp.maritalStatus)}

                ${sectionTitle('Job Information', 'ri-briefcase-line')}
                ${infoItem('Department', emp.departmentName)}
                ${infoItem('Designation', emp.designationName)}
                ${infoItem('Joining Date', formatDate(emp.joiningDate))}
                ${infoItem('Employment Status', emp.employmentStatus)}
                ${infoItem('Employee Type', emp.employeeType)}
                ${infoItem('Reporting Manager', emp.reportingManager)}
                ${infoItem('Joining Status', emp.joiningStatus)}
                ${infoItem('Profile Status', emp.profileStatus)}
                ${infoItem('Verified By', emp.verifiedBy)}
                ${infoItem('Verified At', formatDate(emp.verifiedAt))}

                ${sectionTitle('Address Information', 'ri-map-pin-line')}
                ${infoItem('Permanent Address', emp.permanentAddress)}
                ${infoItem('Local Address', emp.localAddress)}
                ${infoItem('City', emp.cityName)}
                ${infoItem('State', emp.stateName)}
                ${infoItem('Pin Code', emp.pinCode)}

                ${sectionTitle('Salary Information', 'ri-money-rupee-circle-line')}
                ${infoItem('Basic Salary', emp.basicSalary)}
                ${infoItem('HRA Amount', emp.hraAmount)}
                ${infoItem('Allowance Amount', emp.allowanceAmount)}
                ${infoItem('Deduction Amount', emp.deductionAmount)}
                ${infoItem('Net Salary', emp.netSalary)}
                ${infoItem('Other Earning 1', emp.otherEarning1Amount)}
                ${infoItem('Other Earning 2', emp.otherEarning2Amount)}
                ${infoItem('Other Earning 3', emp.otherEarning3Amount)}
                ${infoItem('Other Allowances', emp.otherAllowanceAmount)}
                ${infoItem('Payment Frequency', emp.paymentFrequency)}
                ${infoItem('Next Increment Date', formatDate(emp.nextIncrementDate))}

                ${sectionTitle('Bank Information', 'ri-bank-line')}
                ${infoItem('Account Holder Name', emp.accountHolderName)}
                ${infoItem('Bank Name', emp.bankName)}
                ${infoItem('Account Number', emp.accountNumber)}
                ${infoItem('IFSC Code', emp.ifscCode)}
                ${infoItem('Branch Name', emp.branchName)}

                ${sectionTitle('KYC Information', 'ri-shield-user-line')}
                ${infoItem('Aadhaar Number', emp.aadhaarNumber)}
                ${infoItem('PAN Number', emp.panNumber)}

                ${sectionTitle('About / Skills', 'ri-information-line')}
                <div class="col-xl-6">
                    <div class="info-box">
                        <div class="info-label">About Me</div>
                        <div class="info-value">${displayValue(emp.aboutMe)}</div>
                    </div>
                </div>
                <div class="col-xl-6">
                    <div class="info-box">
                        <div class="info-label">Skills</div>
                        <div class="info-value">${skillsHtml}</div>
                    </div>
                </div>

                ${sectionTitle('Social Media', 'ri-links-line')}
                <div class="col-xl-6 col-lg-6 col-md-6">
                    <div class="info-box">
                        <div class="info-label">LinkedIn Profile</div>
                        <div class="info-value">
                            ${
                                emp.linkedInProfile
                                    ? `<a href="${escapeHtml(emp.linkedInProfile)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                                            <i class="ri-linkedin-box-fill me-1"></i>
                                            View LinkedIn
                                       </a>`
                                    : `<span class="text-muted">Not Added</span>`
                            }
                        </div>
                    </div>
                </div>

                <div class="col-xl-6 col-lg-6 col-md-6">
                    <div class="info-box">
                        <div class="info-label">Instagram Profile</div>
                        <div class="info-value">
                            ${
                                emp.instagramProfile
                                    ? `<a href="${escapeHtml(emp.instagramProfile)}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-danger btn-sm">
                                            <i class="ri-instagram-line me-1"></i>
                                            View Instagram
                                       </a>`
                                    : `<span class="text-muted">Not Added</span>`
                            }
                        </div>
                    </div>
                </div>

                ${sectionTitle('Documents', 'ri-folder-line')}
                <div class="col-12">
                    <div class="info-box">
                        ${documentsHtml}
                    </div>
                </div>

                ${sectionTitle('HR / System Information', 'ri-admin-line')}
                ${infoItem('HR Remark', emp.hrRemark)}
                ${infoItem('Candidate Record ID', emp.candidateRecordId)}
                ${infoItem('Created At', formatDate(emp.createdAt))}
                ${infoItem('Updated At', formatDate(emp.updatedAt))}
            </div>
        `;

        $('#employeeViewModalBody').html(html);
    }

    $(document).on('click', '.edit-profile-btn', function() {
        var id = parseInt($(this).closest('tr').data('id'), 10);

        $('#employeeEditForm')[0].reset();
        $('#editEmployeeId').val(id);

        var modal = new bootstrap.Modal(document.getElementById('employeeEditModal'));
        modal.show();

        $.ajax({
            url: API_BASE + '/onboarding/getEmployeeDetails.php',
            type: 'GET',
            dataType: 'json',
            data: {
                id: id
            },
            success: function(res) {
                if (!res || !res.success) {
                    window.showToast && window.showToast(
                        'danger',
                        (res && res.message) || 'Unable to load employee details.'
                    );
                    return;
                }

                fillEmployeeEditForm(res.data);
            },
            error: function() {
                window.showToast && window.showToast(
                    'danger',
                    'Unable to load employee details right now.'
                );
            }
        });
    });

    function cleanDateForInput(value) {
        if (!value) {
            return '';
        }

        return String(value).substring(0, 10);
    }

    function fillEmployeeEditForm(emp) {
        var $form = $('#employeeEditForm');

        $.each(emp, function(key, value) {
            var $field = $form.find('[name="' + key + '"]');

            if (!$field.length) {
                return;
            }

            if ($field.attr('type') === 'date') {
                $field.val(cleanDateForInput(value));
            } else {
                $field.val(value || '');
            }
        });

        $('#editEmployeeId').val(emp.id || '');
    }

    $(document).on('submit', '#employeeEditForm', function(e) {
        e.preventDefault();

        var $btn = $('#saveEmployeeEditBtn');
        var formData = $(this).serialize();

        $btn.prop('disabled', true).text('Saving...');

        $.ajax({
            url: API_BASE + '/onboarding/updateEmployeeDetailsByHr.php',
            type: 'POST',
            dataType: 'json',
            data: formData,
            success: function(res) {
                window.showToast && window.showToast(
                    res && res.success ? 'success' : 'danger',
                    (res && res.message) || 'Unable to update employee.'
                );

                if (res && res.success) {
                    $('#employeeEditModal').modal('hide');

                    setTimeout(function() {
                        location.reload();
                    }, 700);
                }
            },
            error: function(xhr) {
                var message = 'Server error occurred while updating employee.';

                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                } else if (xhr.responseText) {
                    message = xhr.responseText.replace(/<[^>]*>/g, '').trim() || message;
                }

                window.showToast && window.showToast(
                    'danger',
                    message
                );
            },
            complete: function() {
                $btn.prop('disabled', false).text('Save Employee Details');
            }
        });
    });
});
</script>


<?php include __DIR__ . '/../includes/footer.php'; ?>
