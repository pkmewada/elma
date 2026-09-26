<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/basic-config.php';

$config = getBasicConfig();
$organizationRoles = $config['organizationRoles'] ?? [];
$allowedStatuses = ['open', 'interested', 'convert', 'in_progress', 'not_interested', 'walk_in', 'higher_budget'];
$updateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $candidateId = (int) ($_POST['id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    if ($candidateId > 0 && in_array($status, $allowedStatuses, true)) {
        $currentStatus = null;
        $statusStmt = mysqli_prepare($con, 'SELECT status FROM candidateRecord WHERE id = ? LIMIT 1');

        if ($statusStmt) {
            mysqli_stmt_bind_param($statusStmt, 'i', $candidateId);
            mysqli_stmt_execute($statusStmt);
            $statusResult = mysqli_stmt_get_result($statusStmt);
            $statusRow = $statusResult ? mysqli_fetch_assoc($statusResult) : null;
            $currentStatus = $statusRow['status'] ?? null;
            mysqli_stmt_close($statusStmt);
        }

        if (in_array((string) $currentStatus, ['convert', 'not_interested'], true)) {
            $updateError = 'Status is locked for this candidate.';
        } else {
            $updateStmt = mysqli_prepare($con, 'UPDATE candidateRecord SET status = ? WHERE id = ?');

            if ($updateStmt) {
                mysqli_stmt_bind_param($updateStmt, 'si', $status, $candidateId);
                mysqli_stmt_execute($updateStmt);
                mysqli_stmt_close($updateStmt);
                redirectTo('candidate-record');
            } else {
                $updateError = 'Unable to update candidate status right now.';
            }
        }
    } else {
        $updateError = 'Invalid candidate status update request.';
    }
}

$result = null;
$selectStmt = mysqli_prepare($con, 'SELECT id, fullName, email, phoneNumber, currentLocation, appliedRole, experienceYears, expectedSalary, resumeFile, internalNotes, status, createdAt, employeeName FROM candidateRecord ORDER BY id DESC');

if ($selectStmt) {
    mysqli_stmt_execute($selectStmt);
    $result = mysqli_stmt_get_result($selectStmt);
} else {
    $updateError = 'Unable to load candidates right now.';
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css">

<!-- Prism CSS -->
<link rel="stylesheet" href="<?= ASSET_URL ?>/assets/libs/prismjs/themes/prism-coy.min.css">
<style>
/* Base Select Style */
.candidate-status-select {
    min-width: 150px;
    font-weight: 600;
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 13px;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}

/* Hover + Focus */
.candidate-status-select:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
}

.candidate-status-select:focus {
    outline: none;
    box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.08);
}

/* STATUS VARIANTS */

/* Open */
.candidate-status-open {
    color: #1f2937;
    border-color: #000000;
}

/* Interested */
.candidate-status-interested {
    color: rgb(var(--info-rgb));
    border-color: rgba(var(--info-rgb), 0.3);
}

/* Not Interested */
.candidate-status-not_interested {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), 0.3);
}

/* Converted */
.candidate-status-convert {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
}

/* In Progress */
.candidate-status-in_progress {
    color: rgb(var(--warning-rgb));
    border-color: rgba(var(--warning-rgb), 0.3);
}

/* Walk-In */
.candidate-status-walk_in {
    color: rgb(var(--primary-rgb));
    border-color: rgba(var(--primary-rgb), 0.3);
}

/* Higher Budget */
.candidate-status-higher_budget {
    color: rgb(var(--secondary-rgb));
    border-color: rgba(var(--secondary-rgb), 0.3);
}

/* Filters Wrapper */
.candidates-table-filters {
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
    margin-left: 0.75rem;
}

/* Select inside filters */
.candidates-table-filters .form-select {
    min-width: 150px;
    border-radius: 8px;
    padding: 6px 10px;
    font-size: 13px;
}

.candidate-status-btn {
    font-size: 13px;
    font-weight: 600;
    font-transform: uppercase;
}

.candidate-status-locked {
    cursor: not-allowed;
    opacity: 0.9;
}

.candidate-status-btn:disabled {
    opacity: 1;
}

.candidate-status-btn.candidate-status-not_interested:disabled {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), 0.3);
    background-color: transparent;
}

.candidate-status-btn.candidate-status-convert:disabled {
    color: rgb(var(--success-rgb));
    border-color: rgba(var(--success-rgb), 0.3);
    background-color: transparent;
}

.candidate-status-locked i {
    font-size: 12px;
}

/* =====================================================
   POSITION URL MODAL STYLES
===================================================== */
.position-url-table {
    width: 100%;
    border-collapse: collapse;
}
.position-url-table th {
    background: #f8fafc;
    font-weight: 700;
    font-size: 14px;
    color: #0f172a;
    padding: 12px 16px;
    border-bottom: 2px solid #e2e8f0;
    text-align: left;
}
.position-url-table td {
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-size: 14px;
    color: #334155;
}
.position-url-table tr:hover td {
    background: #f8fafc;
}
.position-url-table .position-name {
    font-weight: 600;
}
.btn-copy-url {
    padding: 6px 16px;
    background: #0b8ba8;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-copy-url:hover {
    background: #0d9ec0;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(11, 139, 168, 0.25);
}
.btn-copy-url.copied {
    background: #16a34a;
}
.btn-copy-url i {
    font-size: 16px;
}
.position-url-wrapper {
    max-height: 400px;
    overflow-y: auto;
}
.position-url-wrapper::-webkit-scrollbar {
    width: 6px;
}
.position-url-wrapper::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 4px;
}
.position-url-wrapper::-webkit-scrollbar-track {
    background: transparent;
}

/* Toast notification for copy */
.copy-toast {
    position: fixed;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    background: #0f172a;
    color: #fff;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    z-index: 9999;
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.copy-toast.show {
    opacity: 1;
}

@media (max-width: 768px) {
    .candidates-table-filters {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        margin-left: 0;
        margin-top: 0.5rem;
        width: 100%;
    }

    .candidates-table-filters .form-select {
        min-width: auto;
        width: 100%;
    }

    .page-header-breadcrumb {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 1rem !important;
    }

    .modal-xl {
        margin: 0.5rem;
        max-width: calc(100vw - 1rem);
    }
}

.table-responsive::-webkit-scrollbar {
    display: none;
}

#candidates-datatable td,
#candidates-datatable th {
    white-space: nowrap;
}

#candidates-datatable small {
    display: inline;
    margin-left: 6px;
}
</style>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Candidate Record</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Candidate Record</li>
                </ol>
            </div>
            <div>
                <!-- NEW: Import Candidates Button -->
                <button type="button" class="btn btn-success btn-wave waves-effect waves-light me-2" data-bs-toggle="modal"
                    data-bs-target="#importCandidateModal" title="Import Candidates">
                    <i class="ri-upload-cloud-2-line"></i>
                </button>
                <!-- Position URL Button -->
                <button type="button" class="btn btn-secondary btn-wave waves-effect waves-light me-2" data-bs-toggle="modal"
                    data-bs-target="#positionUrlModal" title="Position URLs">
                    <i class="ri-links-line"></i>
                </button>
                <button type="button" class="btn btn-success btn-wave waves-effect waves-light me-2" data-bs-toggle="modal"
                    data-bs-target="#viewFollowUpModal" title="View Follow-ups">
                    <i class="ri-eye-line"></i>
                </button>
                <button type="button" class="btn btn-info btn-wave waves-effect waves-light me-2" data-bs-toggle="modal"
                    data-bs-target="#walkInModal" title="Walk-In">
                    <i class="ri-walk-line"></i>
                </button>
                <button type="button" class="btn btn-primary btn-wave waves-effect waves-light" data-bs-toggle="modal"
                    data-bs-target="#addCandidateModal">
                    <i class="ri-user-add-line align-middle me-1"></i>Add Record
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
                                <select id="statusFilter" class="form-select form-select-lg">
                                    <option value="">Status</option>
                                </select>
                                <select id="roleFilter" class="form-select form-select-lg">
                                    <option value="">Role Applied</option>
                                </select>
                            </div>
                            <div class="flex-fill"></div>
                            <div class="d-flex">
                                <input id="tableSearch" class="form-control form-control-sm"
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
                        <div class="card-title">
                            Candidate Records DataTable
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="candidates-datatable" data-ui-table="mamix"
                                class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Details</th>
                                        <th>Role</th>
                                        <th>Exp. & Salary</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>User</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                    <?php $sno = 1; ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php
                                        $resumeUrl = '';
                                        if (!empty($row['resumeFile'])) {
                                                        $resumeUrl = preg_match('/^https?:\/\//i', $row['resumeFile']) ? $row['resumeFile'] : BASE_URL . '/' . ltrim($row['resumeFile'], '/');
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo $sno++; ?></td>
                                        <td><?php echo htmlspecialchars($row['fullName'] . (!empty($row['currentLocation']) ? ' - ' . $row['currentLocation'] : ''), ENT_QUOTES, 'UTF-8'); ?>
                                            <small
                                                class="d-block text-muted"><?php echo htmlspecialchars($row['phoneNumber'], ENT_QUOTES, 'UTF-8'); ?></small>
                                            <small
                                                class="d-block text-muted"><?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td
                                            data-role="<?php echo htmlspecialchars($row['appliedRole'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($row['appliedRole'], ENT_QUOTES, 'UTF-8'); ?>
                                            <small class="d-block text-muted"><?php if ($row['resumeFile']): ?><a
                                                    href="<?php echo htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                    target="_blank">View Resume</a><?php endif; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['experienceYears'], ENT_QUOTES, 'UTF-8'); ?>
                                            Years
                                            <small
                                                class="d-block text-muted"><?php echo htmlspecialchars($row['expectedSalary'], ENT_QUOTES, 'UTF-8'); ?></small>
                                        </td>
                                        <td>
                                            <div class="btn-group" data-id="<?php echo (int) $row['id']; ?>">
                                                <?php 
                                            $currentStatus = $row['status'];
                                            $isLocked = in_array($currentStatus, ['convert', 'not_interested'], true);
                                            ?>

                                                <button type="button"
                                                    class="btn btn-sm <?php echo $isLocked ? 'candidate-status-locked' : 'dropdown-toggle'; ?> candidate-status-btn candidate-status-<?php echo htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php if (!$isLocked): ?> data-bs-toggle="dropdown" <?php endif; ?>
                                                    aria-expanded="false"
                                                    data-status="<?php echo htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8'); ?>"
                                                    <?php if ($isLocked): ?> disabled title="Status locked"
                                                    <?php endif; ?>>

                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $currentStatus)), ENT_QUOTES, 'UTF-8'); ?>
                                                    <?php if ($isLocked): ?><i
                                                        class="ri-lock-line ms-1"></i><?php endif; ?>
                                                </button>
                                                <?php if (!$isLocked): ?>
                                                <ul class="dropdown-menu">
                                                    <?php foreach ($allowedStatuses as $statusOption): ?>
                                                    <li>
                                                        <a class="dropdown-item change-status"
                                                            href="javascript:void(0);"
                                                            data-status="<?php echo htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $statusOption)), ENT_QUOTES, 'UTF-8'); ?>
                                                        </a>
                                                    </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars(date('d M Y h:i A', strtotime($row['createdAt'])), ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($row['employeeName'], ENT_QUOTES, 'UTF-8'); ?>
                                        </td>
                                        <td>
                                            <a href="javascript:void(0);"
                                                class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light remark-btn"
                                                data-id="<?php echo (int) $row['id']; ?>"
                                                data-name="<?php echo htmlspecialchars($row['fullName']); ?>">
                                                <i class="ri-chat-1-line"></i>
                                            </a>
                                            <a href="javascript:void(0);"
                                                class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-candidate-btn"
                                                data-id="<?php echo (int) $row['id']; ?>" title="Delete">
                                                <i class="ri-delete-bin-line"></i>
                                            </a>
                                            <a href="javascript:void(0);"
                                                class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light edit-candidate-btn"
                                                data-id="<?php echo (int) $row['id']; ?>"
                                                data-fullname="<?php echo htmlspecialchars($row['fullName'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-email="<?php echo htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-phone-number="<?php echo htmlspecialchars($row['phoneNumber'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-current-location="<?php echo htmlspecialchars($row['currentLocation'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-applied-role="<?php echo htmlspecialchars($row['appliedRole'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-experience-years="<?php echo htmlspecialchars($row['experienceYears'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-expected-salary="<?php echo htmlspecialchars($row['expectedSalary'], ENT_QUOTES, 'UTF-8'); ?>"
                                                data-resume-file="<?php echo htmlspecialchars($resumeUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                                data-internal-notes="<?php echo htmlspecialchars($row['internalNotes'], ENT_QUOTES, 'UTF-8'); ?>"
                                                title="Edit Record">
                                                <i class="ri-edit-line"></i>
                                            </a>
                                            <a href="javascript:void(0);"
                                                class="btn btn-icon btn-sm btn-warning-light btn-wave waves-effect waves-light"
                                                title="Whatsapp">
                                                <i class="ri-whatsapp-line"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <div class="modal fade" id="addCandidateModal" tabindex="-1" aria-labelledby="addCandidateModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addCandidateModalLabel">Add Candidate</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="addCandidateForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" id="candidateId" name="id" value="">
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            <div class="row g-3">
                                <!-- Basic Details -->
                                <div class="col-12 col-md-6">
                                    <label for="modal-fullName" class="form-label text-default">Full Name</label>
                                    <input type="text" class="form-control" id="modal-fullName" name="fullName"
                                        placeholder="Enter Full Name" required>
                                    <div class="invalid-feedback">Full name is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-email" class="form-label text-default">Email Address</label>
                                    <input type="email" class="form-control" id="modal-email" name="email"
                                        placeholder="Enter Email Address" required>
                                    <div class="invalid-feedback">A valid email is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-phoneNumber" class="form-label text-default">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+91</span>
                                        <input type="text" class="form-control" id="modal-phoneNumber"
                                            name="phoneNumber" placeholder="Enter Phone Number" required
                                            pattern="[0-9]{10}">
                                    </div>
                                    <div class="invalid-feedback">A valid 10-digit phone number is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-currentLocation" class="form-label text-default">Current
                                        Location</label>
                                    <input type="text" class="form-control" id="modal-currentLocation"
                                        name="currentLocation" placeholder="Enter Current Location" required>
                                    <div class="invalid-feedback">Current location is required.</div>
                                </div>

                                <!-- Application Details -->
                                <div class="col-12 col-md-6">
                                    <label for="modal-appliedRole" class="form-label text-default">
                                        Role Applied For
                                    </label>

                                    <select class="form-select" id="modal-appliedRole" name="appliedRole" required>

                                        <option value="">Select Role</option>

                                        <?php foreach ($organizationRoles as $role): ?>
                                        <option value="<?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                        <?php endforeach; ?>

                                    </select>

                                    <div class="invalid-feedback">
                                        Role applied for is required.
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="modal-experienceYears" class="form-label text-default">Total Experience
                                        (Years)</label>
                                    <select class="form-select" id="modal-experienceYears" name="experienceYears"
                                        required>
                                        <option value="">Select Experience</option>
                                        <option value="0">Fresher</option>
                                        <option value="1">1 Year</option>
                                        <option value="2">2 Years</option>
                                        <option value="3">3 Years</option>
                                        <option value="4">4 Years</option>
                                        <option value="5">5 Years</option>
                                        <option value="6">6 Years</option>
                                        <option value="7">7 Years</option>
                                        <option value="8">8 Years</option>
                                        <option value="9">9 Years</option>
                                        <option value="10">10+ Years</option>
                                    </select>
                                    <div class="invalid-feedback">Experience is required.</div>
                                </div>

                                <!-- Compensation -->
                                <div class="col-12 col-md-6">
                                    <label for="modal-expectedSalary" class="form-label text-default">Expected Salary
                                        (CTC)</label>
                                    <input type="text" class="form-control" id="modal-expectedSalary"
                                        name="expectedSalary" placeholder="e.g. 4,50,000 - 6,00,000" required>
                                    <div class="invalid-feedback">Expected salary is required.</div>
                                </div>

                                <!-- Documents -->
                                <div class="col-12 col-md-6">
                                    <label for="modal-resumeFile" class="form-label text-default">Resume Upload</label>
                                    <input type="file" class="form-control" id="modal-resumeFile" name="resumeFile"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" required>
                                    <div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG, WEBP. Max size: 5MB</div>
                                    <div class="invalid-feedback">Resume upload is required.</div>
                                </div>
                                <!-- Additional Info -->
                                <div class="col-12">
                                    <label for="modal-internalNotes" class="form-label text-default">Notes /
                                        Remarks</label>
                                    <textarea class="form-control" id="modal-internalNotes" name="internalNotes"
                                        rows="3" placeholder="Enter any additional notes or remarks"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="addCandidateSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none"
                                    id="addCandidateSubmitSpinner" role="status" aria-hidden="true"></span>
                                <span id="addCandidateSubmitText">Save Candidate</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        
        <!-- =====================================================
        IMPORT CANDIDATES MODAL
        ===================================================== -->
        <div class="modal fade" id="importCandidateModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="ri-upload-cloud-2-line me-2"></i>Import Candidates
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
        
                    <form id="importCandidateForm" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="ri-information-line me-1"></i>
                                Upload CSV with columns:
                                <strong>fullName, email, phoneNumber, currentLocation, appliedRole, experienceYears, expectedSalary</strong>
                            </div>
        
                            <div class="mb-3">
                                <label class="form-label">
                                    Upload CSV File
                                    <span class="text-danger">*</span>
                                </label>
                                <input type="file" class="form-control" id="candidateCsvFile" name="candidateCsvFile"
                                    accept=".csv" required>
                                <div class="form-text">Accepted format: CSV only</div>
                            </div>
        
                            <a href="../api/recruitment/downloadCandidateImportTemplate.php" class="btn btn-outline-primary btn-sm">
                                <i class="ri-download-line me-1"></i>
                                Download Sample CSV
                            </a>
                        </div>
        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success" id="importCandidateSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="importCandidateSpinner"></span>
                                Import Candidates
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="viewFollowUpModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">View Follow Ups</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <div class="row g-2 align-items-end mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Select Date</label>
                                <input type="date" id="followUpDate" class="form-control">
                            </div>

                            <div class="col-md-2">
                                <button type="button" id="loadFollowUpsBtn" class="btn btn-primary w-100">
                                    Submit
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="followUpTable">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Details</th>
                                        <th>Role</th>
                                        <th>Exp. & Salary</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody id="followUpTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            Select date and click submit
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                </div>
            </div>
        </div>

        <!-- Walk-In Modal: candidateRecord rows filtered by status = 'walk_in' -->
        <div class="modal fade" id="walkInModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-xl">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Walk-In Candidates</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle" id="walkInTable">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Details</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="walkInTableBody">
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Loading...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="modal fade" id="editCandidateModal" tabindex="-1" aria-labelledby="editCandidateModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCandidateModalLabel">Edit Candidate</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form id="editCandidateForm" enctype="multipart/form-data" novalidate>
                        <input type="hidden" id="editCandidateId" name="id" value="">
                        <input type="hidden" id="editCurrentResumeFile" name="currentResumeFile" value="">
                        <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                            <div class="row g-3">
                                <!-- Basic Details -->
                                <div class="col-12 col-md-6">
                                    <label for="edit-fullName" class="form-label text-default">Full Name</label>
                                    <input type="text" class="form-control" id="edit-fullName" name="fullName"
                                        placeholder="Enter Full Name" required>
                                    <div class="invalid-feedback">Full name is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="edit-email" class="form-label text-default">Email Address</label>
                                    <input type="email" class="form-control" id="edit-email" name="email"
                                        placeholder="Enter Email Address" required>
                                    <div class="invalid-feedback">A valid email is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="edit-phoneNumber" class="form-label text-default">Phone Number</label>
                                    <div class="input-group">
                                        <span class="input-group-text">+91</span>
                                        <input type="text" class="form-control" id="edit-phoneNumber" name="phoneNumber"
                                            placeholder="Enter Phone Number" required pattern="[0-9]{10}">
                                    </div>
                                    <div class="invalid-feedback">A valid 10-digit phone number is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="edit-currentLocation" class="form-label text-default">Current
                                        Location</label>
                                    <input type="text" class="form-control" id="edit-currentLocation"
                                        name="currentLocation" placeholder="Enter Current Location" required>
                                    <div class="invalid-feedback">Current location is required.</div>
                                </div>

                                <!-- Application Details -->
                                <div class="col-12 col-md-6">
                                    <label for="edit-appliedRole" class="form-label text-default">Role Applied
                                        For</label>
                                    <input type="text" class="form-control" id="edit-appliedRole" name="appliedRole"
                                        placeholder="Enter Role Applied For" required>
                                    <div class="invalid-feedback">Role applied for is required.</div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="edit-experienceYears" class="form-label text-default">Total Experience
                                        (Years)</label>
                                    <select class="form-select" id="edit-experienceYears" name="experienceYears"
                                        required>
                                        <option value="">Select Experience</option>
                                        <option value="0">Fresher</option>
                                        <option value="1">1 Year</option>
                                        <option value="2">2 Years</option>
                                        <option value="3">3 Years</option>
                                        <option value="4">4 Years</option>
                                        <option value="5">5 Years</option>
                                        <option value="6">6 Years</option>
                                        <option value="7">7 Years</option>
                                        <option value="8">8 Years</option>
                                        <option value="9">9 Years</option>
                                        <option value="10">10+ Years</option>
                                    </select>
                                    <div class="invalid-feedback">Experience is required.</div>
                                </div>

                                <!-- Compensation -->
                                <div class="col-12 col-md-6">
                                    <label for="edit-expectedSalary" class="form-label text-default">Expected Salary
                                        (CTC)</label>
                                    <input type="text" class="form-control" id="edit-expectedSalary"
                                        name="expectedSalary" placeholder="e.g. 4,50,000 - 6,00,000" required>
                                    <div class="invalid-feedback">Expected salary is required.</div>
                                </div>

                                <!-- Documents -->
                                <div class="col-12 col-md-6">
                                    <label for="edit-resumeFile" class="form-label text-default">Resume Upload</label>
                                    <input type="file" class="form-control" id="edit-resumeFile" name="resumeFile"
                                        accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp">
                                    <div class="form-text">Accepted formats: PDF, DOC, DOCX, JPG, PNG, WEBP. Max size: 5MB</div>
                                    <div class="invalid-feedback">Resume upload is required.</div>
                                    <div class="form-text" id="editResumeCurrentText"></div>
                                </div>

                                <!-- Additional Info -->
                                <div class="col-12">
                                    <label for="edit-internalNotes" class="form-label text-default">Notes /
                                        Remarks</label>
                                    <textarea class="form-control" id="edit-internalNotes" name="internalNotes" rows="3"
                                        placeholder="Enter any additional notes or remarks"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="editCandidateSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none"
                                    id="editCandidateSubmitSpinner" role="status" aria-hidden="true"></span>
                                <span id="editCandidateSubmitText">Update Candidate</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="convertCandidateModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Status Details - Converted</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="convertCandidateForm" enctype="multipart/form-data">
                        <input type="hidden" name="id" id="convertCandidateId">

                        <div class="modal-body">

                            <!-- Status -->
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="Converted" readonly>
                            </div>

                            <!-- Remark -->
                            <div class="mb-3">
                                <label class="form-label">Status Remark</label>
                                <textarea name="remark" class="form-control" placeholder="Enter remark..."
                                    required></textarea>
                            </div>

                            <!-- Final Salary -->
                            <div class="mb-3">
                                <label class="form-label">Final Salary</label>
                                <input type="text" name="finalSalary" class="form-control"
                                    placeholder="Enter final agreed salary" required>
                            </div>

                            <!-- Joining Date -->
                            <div class="mb-3">
                                <label class="form-label">Joining Date</label>
                                <input type="date" name="joiningDate" class="form-control" required>
                            </div>

                            <!-- CV Upload -->
                            <div class="mb-3">
                                <label class="form-label">CV Document (PDF)</label>
                                <input type="file" name="cvFile" class="form-control" accept=".pdf" required>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary" id="convertCandidateSubmitBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none"
                                    id="convertCandidateSubmitSpinner" role="status" aria-hidden="true"></span>
                                <span id="convertCandidateSubmitText">Submit</span>
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <div class="modal fade" id="notInterestedModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Not Interested - Reason</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="notInterestedForm">
                        <input type="hidden" name="id" id="notInterestedCandidateId">

                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <input type="text" class="form-control" value="Not Interested" readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Reason / Description</label>
                                <textarea name="remark" class="form-control"
                                    placeholder="Enter reason why candidate is not interested..." required></textarea>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-danger">Submit</button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <div class="modal fade" id="deleteConfirmModal" data-bs-effect="effect-super-scaled">
            <div class="modal-dialog modal-dialog-centered text-center" role="document">
                <div class="modal-content modal-content-demo">
                    <div class="modal-header">
                        <h6 class="modal-title">Delete Candidate</h6><button aria-label="Close" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body text-start">
                        <h6>Are you sure you want to delete this candidate?</h6>
                        <p class="text-muted mb-0">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-danger" id="confirmDeleteBtn">Delete</button> <button
                            class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="remarkModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title" id="remarkModalTitle">Remarks</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <form id="remarkForm">
                        <input type="hidden" id="remarkCandidateId" name="candidateId">

                        <div class="modal-body">

                            <!-- Remark -->
                            <div class="mb-3">
                                <label class="form-label">Add New Remark</label>
                                <textarea id="remarkText" name="remark" class="form-control"
                                    placeholder="Type your remark here..." required></textarea>
                            </div>

                            <!-- Follow-up Type -->
                            <div class="mb-3">
                                <label class="form-label">Follow-up Type</label>
                                <select name="followUpType" class="form-select">
                                    <option value="">Select Type</option>
                                    <option value="Call">Call</option>
                                    <option value="Follow-up">Follow-up</option>
                                    <option value="Interview">Interview</option>
                                </select>
                            </div>

                            <!-- Schedule -->
                            <div class="mb-3">
                                <label class="form-label">Schedule Time</label>
                                <input type="datetime-local" name="followUpDateTime" class="form-control">
                            </div>

                            <hr>

                            <!-- History -->
                            <div id="remarksHistory"></div>

                        </div>

                        <div class="modal-footer">
                            <button type="submit" class="btn btn-primary">Submit Remark</button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- =====================================================
        NEW: POSITION URL MODAL
        ===================================================== -->
        <div class="modal fade" id="positionUrlModal" tabindex="-1" aria-labelledby="positionUrlModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="positionUrlModalLabel">
                            <i class="ri-links-line me-2"></i>Position URLs
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3">Click the copy button to copy the URL for each position.</p>
                        <div class="position-url-wrapper">
                            <table class="position-url-table">
                                <thead>
                                    <tr>
                                        <th style="width:50%;">Position</th>
                                        <th style="width:50%;">URL</th>
                                    </tr>
                                </thead>
                                <tbody id="positionUrlTableBody">
                                    <!-- Rows will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Copy Toast Notification -->
        <div class="copy-toast" id="copyToast">URL copied to clipboard!</div>
        
    </div>
    
</div>

        <?php if ($selectStmt) {
    mysqli_stmt_close($selectStmt);
} ?>
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
        $(function() {
            var statusColumnIndex = 4;
            var roleColumnIndex = 2;
            var addCandidateApiUrl = API_BASE + '/recruitment/addCandidate.php';
            var updateCandidateApiUrl = API_BASE + '/recruitment/updateCandidate.php';
            var updateCandidateStatusApiUrl = API_BASE + '/recruitment/updateCandidateStatus.php';
            var deleteCandidateApiUrl = API_BASE + '/recruitment/deleteCandidate.php';
            var getWalkInStatusCandidatesApiUrl = API_BASE + '/recruitment/getWalkInStatusCandidates.php';
            var getCandidateRemarks = API_BASE + '/recruitment/getCandidateRemarks.php';
            var addCandidateRemark = API_BASE + '/recruitment/addCandidateRemark.php';

            var table = $('#candidates-datatable').DataTable(window.ModlusUI.withDataTableDefaults({

                drawCallback: function(settings) {
                    var api = this.api();
                    api.column(0, {
                        search: 'applied',
                        order: 'applied'
                    }).nodes().each(function(cell, i) {
                        cell.innerHTML = i + 1;
                    });
                },

                order: [],
                pageLength: 10,
                dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
                columnDefs: [{
                        targets: 0,
                        orderable: false,
                        searchable: false
                    },
                    {
                        targets: 7,
                        orderable: false,
                        searchable: false
                    }
                ],
                buttons: [{
                        extend: 'csvHtml5',
                        className: 'd-none buttons-csv',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        }
                    },
                    {
                        extend: 'pdfHtml5',
                        className: 'd-none buttons-pdf',
                        exportOptions: {
                            columns: [0, 1, 2, 3, 4, 5, 6, 7]
                        }
                    }
                ]
            }));

            function escapeHtml(value) {
                return $('<div>').text(value || '').html();
            }

            function formatStatus(status) {
                return (status || '').replace('_', ' ').replace(/\b\w/g, function(char) {
                    return char.toUpperCase();
                });
            }

            function addStatusFilterOption(status) {
                if (!status || statusFilter.find('option[value="' + status + '"]').length) {
                    return;
                }

                statusFilter.append($('<option>', {
                    value: status,
                    text: formatStatus(status)
                }));
            }

            function getStatusDropdownHtml(id, status) {
                var safeId = escapeHtml(id);
                var safeStatus = escapeHtml(status);
                var statusLabel = formatStatus(status);
                var isLocked = status === 'convert' || status === 'not_interested';
                var buttonClass = isLocked ? 'candidate-status-locked' : 'dropdown-toggle';
                var toggleAttr = isLocked ? 'disabled title="Status locked"' :
                    'data-bs-toggle="dropdown" aria-expanded="false"';
                var lockIcon = isLocked ? '<i class="ri-lock-line ms-1"></i>' : '';

                var html = '<div class="btn-group" data-id="' + safeId + '">' +
                    '<button type="button" class="btn btn-sm ' + buttonClass +
                    ' candidate-status-btn candidate-status-' + safeStatus + '" ' + toggleAttr +
                    ' data-status="' + safeStatus + '">' +
                    escapeHtml(statusLabel) + lockIcon +
                    '</button>';

                if (!isLocked) {
                    html += '<ul class="dropdown-menu">' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="open">Open</a></li>' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="interested">Interested</a></li>' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="convert">Convert</a></li>' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="in_progress">In Progress</a></li>' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="not_interested">Not Interested</a></li>' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="walk_in">Walk-In</a></li>' +
                        '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="higher_budget">Higher Budget</a></li>' +
                        '</ul>';
                }

                return html + '</div>';
            }

            var roleFilter = $('#roleFilter');
            var statusFilter = $('#statusFilter');

            // Populate role filter from data-role attribute
            var seenRoles = {};
            $('#candidates-datatable tbody td[data-role]').each(function() {
                var role = $(this).data('role');
                if (role && !seenRoles[role]) {
                    seenRoles[role] = true;
                    roleFilter.append($('<option>', {
                        value: role,
                        text: role
                    }));
                }
            });

            var seenStatuses = {};
            $('#candidates-datatable .candidate-status-btn').each(function() {
                var value = $(this).data('status');
                if (value && !seenStatuses[value]) {
                    seenStatuses[value] = true;
                    statusFilter.append($('<option>', {
                        value: value,
                        text: value.charAt(0).toUpperCase() + value.slice(1).replace('_',
                            ' ')
                    }));
                }
            });

            // Custom filter for both status and role columns
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                var statusValue = $('#statusFilter').val();
                var roleValue = $('#roleFilter').val();

                var rowNode = $(table.row(dataIndex).node());

                // Check status filter
                if (statusValue) {
                    var statusBtn = rowNode.find('.candidate-status-btn');
                    var rowStatus = statusBtn.data('status');
                    if (rowStatus !== statusValue) return false;
                }

                // Check role filter
                if (roleValue) {
                    var cellRole = rowNode.find('td[data-role]').data('role');
                    if (cellRole !== roleValue) return false;
                }

                return true;
            });

            $('#statusFilter, #roleFilter').on('change', function() {
                table.draw();
            });

            $('#tableSearch').on('keyup', function() {
                table.search(this.value).draw();
            });

            $('.export-btn').on('click', function() {
                var type = $(this).data('type');
                if (type === 'csv') {
                    table.buttons('.buttons-csv').trigger();
                } else if (type === 'pdf') {
                    table.buttons('.buttons-pdf').trigger();
                }
            });

            function updateCandidateStatus(id, status, extraData = {}) {
                $.ajax({
                    url: updateCandidateStatusApiUrl,
                    type: 'POST',
                    data: {
                        id: id,
                        status: status,
                        ...extraData
                    },
                    success: function(response) {
                        if (response.success) {
                            var row = $('.btn-group[data-id="' + id + '"]').closest('tr');
                            row.find('.btn-group[data-id="' + id + '"]').replaceWith(
                                getStatusDropdownHtml(id, status));
                            addStatusFilterOption(status);
                            table.row(row).invalidate('dom').draw(false);

                            window.showToast && window.showToast('success', 'Status updated');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON
                            .message : 'Unable to update status.';
                        window.showToast && window.showToast('danger', msg);
                    }
                });
            }

            $(document).on('click', '.change-status', function() {
                var status = $(this).data('status');
                var id = $(this).closest('.btn-group').data('id');

                if (status === 'convert') {
                    $('#convertCandidateId').val(id);
                    $('#convertCandidateModal').modal('show');
                    return;
                }

                if (status === 'not_interested') {
                    $('#notInterestedCandidateId').val(id);
                    $('#notInterestedModal').modal('show');
                    return;
                }

                // normal flow
                updateCandidateStatus(id, status);

                // Row may also be showing inside the Walk-In modal -- keep
                // it in sync so leaving 'walk_in' removes it from there too.
                if ($('#walkInModal').hasClass('show')) {
                    loadWalkInCandidates();
                }
            });

            // Walk-In modal: candidateRecord rows filtered by status = 'walk_in'.
            // Reuses getStatusDropdownHtml()/updateCandidateStatus() above and
            // the existing remark/delete row-action handlers (delegated on
            // document, so identical markup here just works).
            function loadWalkInCandidates() {
                $('#walkInTableBody').html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

                $.getJSON(getWalkInStatusCandidatesApiUrl, function(response) {
                    if (!response.success || !response.data.length) {
                        $('#walkInTableBody').html('<tr><td colspan="6" class="text-center text-muted">No walk-in candidates.</td></tr>');
                        return;
                    }

                    var html = '';
                    response.data.forEach(function(row, index) {
                        var details = escapeHtml(row.fullName) + (row.currentLocation ? ' - ' + escapeHtml(row.currentLocation) : '');
                        html += '<tr>' +
                            '<td>' + (index + 1) + '</td>' +
                            '<td>' + details +
                                '<small class="d-block text-muted">' + escapeHtml(row.phoneNumber) + '</small>' +
                                '<small class="d-block text-muted">' + escapeHtml(row.email) + '</small>' +
                            '</td>' +
                            '<td>' + escapeHtml(row.appliedRole) + '</td>' +
                            '<td>' + getStatusDropdownHtml(row.id, row.status) + '</td>' +
                            '<td>' + formatDateTime(row.createdAt) + '</td>' +
                            '<td>' +
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light remark-btn" data-id="' + row.id + '" data-name="' + escapeHtml(row.fullName) + '"><i class="ri-chat-1-line"></i></a> ' +
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-candidate-btn" data-id="' + row.id + '" title="Delete"><i class="ri-delete-bin-line"></i></a>' +
                            '</td>' +
                        '</tr>';
                    });
                    $('#walkInTableBody').html(html);
                });
            }

            $('#walkInModal').on('show.bs.modal', loadWalkInCandidates);

            $('#loadFollowUpsBtn').on('click', function() {

                let date = $('#followUpDate').val();

                if (!date) {
                    showToast('danger', 'Please select date');
                    return;
                }

                $.ajax({
                    url: API_BASE + '/leads/getFollowUpByDate.php',
                    type: 'POST',
                    data: {
                        followUpDate: date
                    },
                    dataType: 'json',

                    success: function(response) {

                        let html = '';

                        if (response.success && response.data.length > 0) {

                            $.each(response.data, function(i, row) {

                                html += `
                        <tr>
                            <td>${i + 1}</td>

                            <td>
                                ${row.fullName}
                                <small class="d-block text-muted">${row.phoneNumber}</small>
                                <small class="d-block text-muted">${row.email}</small>
                                <small class="d-block text-muted">${row.currentLocation}</small>
                            </td>

                            <td>${row.appliedRole}</td>

                            <td>
                                ${row.experienceYears} Years
                                <small class="d-block text-muted">${row.expectedSalary}</small>
                            </td>

                            <td>${row.followUpType}</td>

                            <td>
                                <select class="form-select form-select-sm followup-status-change"
                                    data-id="${row.remarkId}">
                                    <option value="Pending" ${row.followUpStatus === 'Pending' ? 'selected' : ''}>Pending</option>
                                    <option value="Complete" ${row.followUpStatus === 'Complete' ? 'selected' : ''}>Complete</option>
                                    <option value="Cancelled" ${row.followUpStatus === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                                    <option value="Rescheduled" ${row.followUpStatus === 'Rescheduled' ? 'selected' : ''}>Rescheduled</option>
                                </select>
                            </td>
                        </tr>
                    `;
                            });

                        } else {

                            html = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">
                            No records found
                        </td>
                    </tr>
                `;
                        }

                        $('#followUpTableBody').html(html);
                    }
                });
            });

            $(document).on('change', '.followup-status-change', function() {

                let id = $(this).data('id');
                let status = $(this).val();
                let select = $(this);

                $.ajax({
                    url: API_BASE + '/leads/updateFollowUpStatus.php',
                    type: 'POST',
                    data: {
                        id: id,
                        status: status
                    },
                    dataType: 'json',

                    success: function(res) {

                        if (res.success) {
                            showToast('success', 'Status updated');
                        } else {
                            showToast('danger', 'Failed');
                        }
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    }
                });

            });


            $('#convertCandidateForm').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('status', 'convert');
                var submitBtn = $('#convertCandidateSubmitBtn');
                var submitSpinner = $('#convertCandidateSubmitSpinner');
                var submitText = $('#convertCandidateSubmitText');

                submitBtn.prop('disabled', true);
                submitSpinner.removeClass('d-none');
                submitText.text('Submitting...');

                $.ajax({
                    url: updateCandidateStatusApiUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {

                            var id = $('#convertCandidateId').val();

                            var row = $('.btn-group[data-id="' + id + '"]').closest('tr');
                            row.find('.btn-group[data-id="' + id + '"]').replaceWith(
                                getStatusDropdownHtml(id, 'convert'));
                            addStatusFilterOption('convert');
                            table.row(row).invalidate('dom').draw(false);

                            $('#convertCandidateModal').modal('hide');
                            $('#convertCandidateForm')[0].reset();

                            window.showToast && window.showToast('success',
                                'Candidate converted successfully');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr
                            .responseJSON
                            .message : 'Unable to convert candidate.';
                        window.showToast && window.showToast('danger', msg);
                    },
                    complete: function() {
                        submitBtn.prop('disabled', false);
                        submitSpinner.addClass('d-none');
                        submitText.text('Submit');
                    }
                });
            });

            $('#notInterestedForm').on('submit', function(e) {
                e.preventDefault();

                var formData = new FormData(this);
                formData.append('status', 'not_interested');

                $.ajax({
                    url: updateCandidateStatusApiUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,

                    success: function(response) {
                        if (response.success) {

                            var id = $('#notInterestedCandidateId').val();

                            var row = $('.btn-group[data-id="' + id + '"]').closest('tr');
                            row.find('.btn-group[data-id="' + id + '"]').replaceWith(
                                getStatusDropdownHtml(id, 'not_interested'));
                            addStatusFilterOption('not_interested');
                            table.row(row).invalidate('dom').draw(false);

                            $('#notInterestedModal').modal('hide');
                            $('#notInterestedForm')[0].reset();

                            window.showToast && window.showToast('success',
                                'Marked as Not Interested');
                        }
                    },
                    error: function(xhr) {
                        var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr
                            .responseJSON
                            .message : 'Unable to mark as not interested.';
                        window.showToast && window.showToast('danger', msg);
                    }
                });
            });


            $('#addCandidateForm').on('submit', function(e) {
                e.preventDefault();

                var form = this;
                var isValid = true;

                // Reset previous errors
                $(form).find('.is-invalid').removeClass('is-invalid');

                // Get values
                var fullName = $('#modal-fullName').val().trim();
                var email = $('#modal-email').val().trim();
                var phone = $('#modal-phoneNumber').val().trim();
                var location = $('#modal-currentLocation').val().trim();
                var role = $('#modal-appliedRole').val().trim();
                var experience = $('#modal-experienceYears').val();
                var salary = $('#modal-expectedSalary').val().trim();
                var resume = $('#modal-resumeFile')[0].files[0];

                // ==============================
                // ✅ VALIDATION
                // ==============================

                if (!fullName) {
                    $('#modal-fullName').addClass('is-invalid');
                    isValid = false;
                }

                if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                    $('#modal-email').addClass('is-invalid');
                    isValid = false;
                }

                if (!phone || !/^[0-9]{10}$/.test(phone)) {
                    $('#modal-phoneNumber').addClass('is-invalid');
                    isValid = false;
                }

                if (!location) {
                    $('#modal-currentLocation').addClass('is-invalid');
                    isValid = false;
                }

                if (!role) {
                    $('#modal-appliedRole').addClass('is-invalid');
                    isValid = false;
                }

                if (!experience) {
                    $('#modal-experienceYears').addClass('is-invalid');
                    isValid = false;
                }

                if (!salary) {
                    $('#modal-expectedSalary').addClass('is-invalid');
                    isValid = false;
                }

                if (!resume) {
                    $('#modal-resumeFile').addClass('is-invalid');
                    isValid = false;
                }

                // 🚫 STOP if invalid
                if (!isValid) {
                    window.showToast && window.showToast('danger',
                        'Please fill all required fields correctly.');
                    return;
                }

                // ==============================
                // ✅ AJAX START
                // ==============================

                var formData = new FormData(form);

                var submitBtn = $('#addCandidateSubmitBtn');
                var submitText = $('#addCandidateSubmitText');
                var submitSpinner = $('#addCandidateSubmitSpinner');

                submitBtn.prop('disabled', true);
                submitText.text('Saving...');
                submitSpinner.removeClass('d-none');

                $.ajax({
                    url: addCandidateApiUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,

                    success: function(response) {

                        if (response.success) {

                            $('#addCandidateModal').modal('hide');
                            form.reset();

                            // ==============================
                            // ✅ ADD ROW TO DATATABLE
                            // ==============================

                            var newRow = [
                                '',
                                escapeHtml(response.data.fullName) +
                                '<small class="d-block text-muted">' + escapeHtml(
                                    response.data
                                    .phoneNumber) + '</small>' +
                                '<small class="d-block text-muted">' + escapeHtml(
                                    response.data.email) +
                                '</small>' +
                                '<small class="d-block text-muted">' + escapeHtml(
                                    response.data
                                    .currentLocation) + '</small>',
                                '<span data-role="' + escapeHtml(response.data
                                    .appliedRole) + '">' +
                                escapeHtml(response.data.appliedRole) + '</span>' +
                                '<small class="d-block text-muted">' +
                                (response.data.resumeFile ? '<a href="' + escapeHtml(
                                        response.data
                                        .resumeFile) +
                                    '" target="_blank">View Resume</a>' : '') +
                                '</small>',
                                escapeHtml(response.data.experienceYears) +
                                ' Years<small class="d-block text-muted">' + escapeHtml(
                                    response.data
                                    .expectedSalary) + '</small>',
                                getStatusDropdownHtml(response.data.id, response.data
                                    .status || 'open'),
                                escapeHtml(response.data.createdDate),
                                escapeHtml(response.data.employeeName),
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light remark-btn" data-id="' +
                                escapeHtml(response.data.id) + '" data-name="' +
                                escapeHtml(response.data
                                    .fullName) +
                                '"><i class="ri-chat-1-line"></i></a>' +
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-danger-light delete-candidate-btn" data-id="' +
                                escapeHtml(response.data.id) +
                                '"><i class="ri-delete-bin-line"></i></a>' +
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-success-light edit-candidate-btn" data-id="' +
                                escapeHtml(response.data.id) +
                                '"><i class="ri-edit-line"></i></a>' +
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-warning-light"><i class="ri-whatsapp-line"></i></a>'
                            ];

                            var existingRows = table.rows().data().toArray();
                            table.clear();
                            table.rows.add([newRow].concat(existingRows)).draw(false);

                            window.showToast && window.showToast('success',
                                'Candidate added successfully.');

                        } else {
                            window.showToast && window.showToast('danger', response
                                .message || 'Failed to add candidate.');
                        }
                    },

                    error: function(xhr) {
                        let msg = 'Server error. Please try again.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }

                        window.showToast && window.showToast('danger', msg);
                    },

                    complete: function() {
                        submitBtn.prop('disabled', false);
                        submitText.text('Save Candidate');
                        submitSpinner.addClass('d-none');
                    }
                });
            });

            function resetEditCandidateForm() {
                $('#editCandidateForm')[0].reset();
                $('#editResumeCurrentText').text('');
                $('#editCurrentResumeFile').val('');
            }

            $(document).on('click', '.edit-candidate-btn', function() {
                resetEditCandidateForm();

                var button = $(this);
                var id = button.attr('data-id');
                var resumeFile = button.attr('data-resume-file') || '';
                var resumeText = resumeFile ?
                    '<span class="text-muted">Current resume: </span><a href="' + resumeFile +
                    '" target="_blank">View</a>' : 'No resume uploaded yet.';

                $('#editCandidateId').val(id);
                $('#edit-fullName').val(button.attr('data-fullname') || '');
                $('#edit-email').val(button.attr('data-email') || '');
                $('#edit-phoneNumber').val(button.attr('data-phone-number') || '');
                $('#edit-currentLocation').val(button.attr('data-current-location') || '');
                $('#edit-appliedRole').val(button.attr('data-applied-role') || '');
                $('#edit-experienceYears').val(button.attr('data-experience-years') || '');
                $('#edit-expectedSalary').val(button.attr('data-expected-salary') || '');
                $('#edit-internalNotes').val(button.attr('data-internal-notes') || '');
                $('#editCurrentResumeFile').val(resumeFile);
                $('#editResumeCurrentText').html(resumeText);

                $('#editCandidateModal').modal('show');
            });

            $('#editCandidateForm').on('submit', function(e) {
                e.preventDefault();

                var form = this;
                var isValid = true;

                // Reset errors
                $(form).find('.is-invalid').removeClass('is-invalid');

                // Get values
                var fullName = $('#edit-fullName').val().trim();
                var email = $('#edit-email').val().trim();
                var phone = $('#edit-phoneNumber').val().trim();
                var location = $('#edit-currentLocation').val().trim();
                var role = $('#edit-appliedRole').val().trim();
                var experience = $('#edit-experienceYears').val();
                var salary = $('#edit-expectedSalary').val().trim();

                // ==============================
                // ✅ VALIDATION
                // ==============================

                if (!fullName) {
                    $('#edit-fullName').addClass('is-invalid');
                    isValid = false;
                }

                if (!email || !/^\S+@\S+\.\S+$/.test(email)) {
                    $('#edit-email').addClass('is-invalid');
                    isValid = false;
                }

                if (!phone || !/^[0-9]{10}$/.test(phone)) {
                    $('#edit-phoneNumber').addClass('is-invalid');
                    isValid = false;
                }

                if (!location) {
                    $('#edit-currentLocation').addClass('is-invalid');
                    isValid = false;
                }

                if (!role) {
                    $('#edit-appliedRole').addClass('is-invalid');
                    isValid = false;
                }

                if (!experience) {
                    $('#edit-experienceYears').addClass('is-invalid');
                    isValid = false;
                }

                if (!salary) {
                    $('#edit-expectedSalary').addClass('is-invalid');
                    isValid = false;
                }

                // 🚫 STOP if invalid
                if (!isValid) {
                    window.showToast && window.showToast('danger',
                        'Please fix the highlighted fields.');
                    return;
                }

                // ==============================
                // ✅ AJAX START (UNCHANGED LOGIC)
                // ==============================

                var formData = new FormData(form);

                var submitBtn = $('#editCandidateSubmitBtn');
                var submitText = $('#editCandidateSubmitText');
                var submitSpinner = $('#editCandidateSubmitSpinner');

                submitBtn.prop('disabled', true);
                submitText.text('Updating...');
                submitSpinner.removeClass('d-none');

                $.ajax({
                    url: updateCandidateApiUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,

                    success: function(response) {

                        if (response.success) {

                            $('#editCandidateModal').modal('hide');

                            window.showToast && window.showToast('success',
                                response.message || 'Candidate updated successfully.');

                            var row = $('.edit-candidate-btn[data-id="' + response.data.id +
                                '"]').closest('tr');

                            if (row.length) {

                                row.find('td:nth-child(2)').html(
                                    $('<div>').append(
                                        $('<div>').text(response.data.fullName),
                                        $('<small>').addClass('d-block text-muted')
                                        .text(response.data.phoneNumber),
                                        $('<small>').addClass('d-block text-muted')
                                        .text(response.data.email),
                                        $('<small>').addClass('d-block text-muted')
                                        .text(response.data.currentLocation)
                                    ).html()
                                );

                                var resumeUrl = response.data.resumeFile || '';
                                var roleHtml = response.data.appliedRole;

                                if (resumeUrl) {
                                    roleHtml +=
                                        '<small class="d-block text-muted"><a href="' +
                                        resumeUrl +
                                        '" target="_blank">View Resume</a></small>';
                                }

                                row.find('td:nth-child(3)').html(roleHtml);

                                row.find('td:nth-child(4)').html(
                                    response.data.experienceYears +
                                    ' Years<small class="d-block text-muted">' +
                                    response.data.expectedSalary + '</small>'
                                );

                                var editButton = row.find('.edit-candidate-btn');

                                editButton.attr('data-fullname', response.data.fullName);
                                editButton.attr('data-email', response.data.email);
                                editButton.attr('data-phone-number', response.data
                                    .phoneNumber);
                                editButton.attr('data-current-location', response.data
                                    .currentLocation);
                                editButton.attr('data-applied-role', response.data
                                    .appliedRole);
                                editButton.attr('data-experience-years', response.data
                                    .experienceYears);
                                editButton.attr('data-expected-salary', response.data
                                    .expectedSalary);
                                editButton.attr('data-resume-file', response.data
                                    .resumeFile);
                                editButton.attr('data-internal-notes', response.data
                                    .internalNotes);

                                if (table) {
                                    table.row(row).invalidate('dom').draw(false);
                                }
                            }

                        } else {
                            window.showToast && window.showToast('danger',
                                response.message || 'Failed to update candidate.');
                        }
                    },

                    error: function(xhr) {

                        let msg = 'Server error. Please try again.';

                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }

                        window.showToast && window.showToast('danger', msg);
                    },

                    complete: function() {
                        submitBtn.prop('disabled', false);
                        submitText.text('Update Candidate');
                        submitSpinner.addClass('d-none');
                    }
                });
            });

            $(document).on('click', '.delete-candidate-btn', function() {
                var id = $(this).data('id');
                $('#deleteConfirmModal').modal('show');
                $('#confirmDeleteBtn').data('id', id);
            });

            $('#confirmDeleteBtn').on('click', function() {
                var id = $(this).data('id');

                $.ajax({
                    url: deleteCandidateApiUrl,
                    type: 'POST',
                    data: {
                        id: id
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#deleteConfirmModal').modal('hide');

                            // ✅ IMPORTANT FIX
                            var row = $('[data-id="' + id + '"]').closest('tr');
                            table.row(row).remove().draw(false);

                            if (typeof window.showToast === 'function') {
                                window.showToast('success',
                                    'Candidate deleted successfully.');
                            }
                        } else {
                            if (typeof window.showToast === 'function') {
                                window.showToast('danger', response.message ||
                                    'Failed to delete candidate.');
                            }
                        }
                    },
                    error: function() {
                        if (typeof window.showToast === 'function') {
                            window.showToast('danger',
                                'An error occurred while deleting the candidate.');
                        }
                    }
                });
            });

            $(document).on('click', '.remark-btn', function() {
                var id = $(this).data('id');
                var name = $(this).data('name');

                $('#remarkCandidateId').val(id);
                $('#remarkModalTitle').text('Remarks for ' + name);

                loadRemarks(id);

                $('#remarkModal').modal('show');
            });


            function loadRemarks(candidateId) {
                $.ajax({
                    url: getCandidateRemarks,
                    type: 'GET',
                    data: {
                        candidateId: candidateId
                    },
                    dataType: 'json',

                    success: function(response) {

                        let html = '';

                        if (response.success && Array.isArray(response.data) && response.data
                            .length > 0) {

                            response.data.forEach(function(item) {

                                // ✅ FIX: move inside loop
                                let typeColor = 'text-primary';

                                if (item.followUpType === 'Call') typeColor =
                                    'text-success';
                                else if (item.followUpType === 'Follow-up') typeColor =
                                    'text-warning';
                                else if (item.followUpType === 'Interview') typeColor =
                                    'text-info';

                                html += `
                                    <div class="border rounded p-3 mb-2 bg-light">

                                        <div class="fw-semibold ${typeColor} mb-1">
                                            Remark: ${item.followUpType || 'N/A'}
                                        </div>

                                        <div class="mb-1">
                                            <strong>Description:</strong> ${item.remark}
                                        </div>

                                        ${item.followUpDateTime ? `
                                        <div class="mb-1">
                                            <strong>Re-Schedule:</strong> ${formatDateTime(item.followUpDateTime)}
                                        </div>` : ''}

                                        <div class="text-muted small">
                                            ${formatDateTime(item.createdAt)}
                                        </div>

                                    </div>
                                `;
                            });

                        } else {
                            html = `<p class="text-muted">No remarks available.</p>`;
                        }

                        $('#remarksHistory').html(html);
                    }
                });
            }

            function formatDateTime(dateString) {
                if (!dateString) return '';

                const date = new Date(dateString);

                return date.toLocaleString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true
                });
            }

            $('#remarkForm').on('submit', function(e) {
                e.preventDefault();

                let remark = $('#remarkText').val().trim();

                if (!remark) {
                    $('#remarkText').addClass('is-invalid');
                    return;
                }

                let formData = new FormData(this);

                $.ajax({
                    url: addCandidateRemark,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json', // ✅ ADD THIS
                    success: function(response) {
                        if (response.success) {

                            $('#remarkForm')[0].reset();

                            loadRemarks($('#remarkCandidateId').val());

                            window.showToast('success', 'Remark added');

                        } else {
                            window.showToast('danger', response.message);
                        }
                    }
                });
            });

            // =====================================================
            // POSITION URL MODAL - POPULATE TABLE
            // =====================================================
            
            // Pass PHP organization roles to JavaScript
            var organizationRoles = <?php echo json_encode($organizationRoles); ?>;


           function populatePositionUrls() {
                // =====================================================
                // OPTION 2: ONLY use organizationRoles (ignore DataTable)
                // =====================================================
                var positions = organizationRoles;
            
                var tbody = $('#positionUrlTableBody');
                tbody.empty();
            
                positions.forEach(function(position) {
                    // Generate URL based on position
                    var url = window.location.origin + '/public-record?position=' + encodeURIComponent(position);
                    
                    var row = '<tr>' +
                        '<td class="position-name">' + escapeHtml(position) + '</td>' +
                        '<td>' +
                        '<div class="d-flex align-items-center gap-2">' +
                        '<input type="text" class="form-control form-control-sm position-url-input" value="' + escapeHtml(url) + '" readonly style="font-size:13px;background:#f8fafc;flex:1;">' +
                        '<button class="btn-copy-url" data-url="' + escapeHtml(url) + '">' +
                        '<i class="ri-file-copy-line"></i> Copy' +
                        '</button>' +
                        '</div>' +
                        '</td>' +
                        '</tr>';
                    tbody.append(row);
                });
            }

            // Show toast notification
            function showCopyToast(message) {
                var toast = $('#copyToast');
                toast.text(message);
                toast.addClass('show');
                clearTimeout(toast.data('timeout'));
                var timeout = setTimeout(function() {
                    toast.removeClass('show');
                }, 2500);
                toast.data('timeout', timeout);
            }

            // Copy URL functionality
            $(document).on('click', '.btn-copy-url', function() {
                var url = $(this).data('url');
                var button = $(this);
                
                // Copy to clipboard
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(function() {
                        button.addClass('copied');
                        button.html('<i class="ri-check-line"></i> Copied!');
                        showCopyToast('URL copied to clipboard!');
                        
                        setTimeout(function() {
                            button.removeClass('copied');
                            button.html('<i class="ri-file-copy-line"></i> Copy');
                        }, 2000);
                    }).catch(function() {
                        // Fallback
                        fallbackCopy(url, button);
                    });
                } else {
                    // Fallback for older browsers
                    fallbackCopy(url, button);
                }
            });

            // Fallback copy method
            function fallbackCopy(text, button) {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    button.addClass('copied');
                    button.html('<i class="ri-check-line"></i> Copied!');
                    showCopyToast('URL copied to clipboard!');
                    
                    setTimeout(function() {
                        button.removeClass('copied');
                        button.html('<i class="ri-file-copy-line"></i> Copy');
                    }, 2000);
                } catch (err) {
                    showCopyToast('Failed to copy URL. Please select and copy manually.');
                }
                document.body.removeChild(textarea);
            }

            // Populate position URLs when modal is shown
            $('#positionUrlModal').on('show.bs.modal', function() {
                populatePositionUrls();
            });

            // Also populate on page load if modal content is needed
            setTimeout(function() {
                populatePositionUrls();
            }, 500);
            
            // =====================================================
            // IMPORT CANDIDATES
            // =====================================================
            var importCandidatesApiUrl = API_BASE + '/recruitment/importCandidates.php';
            
            $(document).on("submit", "#importCandidateForm", function(event) {
                event.preventDefault();
                event.stopPropagation();
            
                var fileInput = $("#candidateCsvFile")[0];
                var file = fileInput && fileInput.files.length ? fileInput.files[0] : null;
            
                if (!file) {
                    showToast("warning", "Please upload CSV file.");
                    return;
                }
            
                var formData = new FormData();
                formData.append("candidateCsvFile", file);
            
                $("#importCandidateSubmitBtn").prop("disabled", true);
                $("#importCandidateSpinner").removeClass("d-none");
            
                $.ajax({
                    url: importCandidatesApiUrl,
                    type: "POST",
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: "json",
                    timeout: 60000
                })
            
                .done(function(response) {
                    if (!response.success) {
                        showToast("error", response.message || "Import failed.");
                        return;
                    }
            
                    showToast("success", response.message || "Candidates imported successfully.");
            
                    $("#importCandidateModal").modal("hide");
                    $("#importCandidateForm")[0].reset();
            
                    // Reload page to show new candidates
                    location.reload();
                })
            
                .fail(function(xhr, status, error) {
                    var message = "Import failed.";
            
                    if (status === "timeout") {
                        message = "Import request timed out. Please try again.";
                    }
            
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        message = xhr.responseJSON.message;
                    }
            
                    showToast("error", message);
                })
            
                .always(function() {
                    $("#importCandidateSubmitBtn").prop("disabled", false);
                    $("#importCandidateSpinner").addClass("d-none");
                });
            });
            
            // Reset form when modal is closed
            $("#importCandidateModal").on("hidden.bs.modal", function() {
                $("#importCandidateSubmitBtn").prop("disabled", false);
                $("#importCandidateSpinner").addClass("d-none");
                $("#importCandidateForm")[0].reset();
            });
        });
        </script>



        <?php include __DIR__ . '/../includes/footer.php'; ?>