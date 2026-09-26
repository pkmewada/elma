<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/config.php';

require_once __DIR__ . '/../includes/header.php'; ?>
<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.2.3/css/buttons.bootstrap5.min.css" />
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<link
rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
<style>
    .swal2-over-modal {
    z-index: 20000 !important;
}
</style>
<!-- Prism CSS -->
<link rel="stylesheet" href="<?= ASSET_URL ?>/assets/libs/prismjs/themes/prism-coy.min.css" />

<?php include __DIR__ . '/../includes/sidebar.php'; ?>

<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Onboarding Leads</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Onboarding Leads</li>
                </ol>
            </div>
        </div>

        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center gap-2">
                                <div class="btn-list">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" >
                                            Export
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item export-btn" data-type="csv" href="javascript:void(0);">CSV</a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item export-btn"  data-type="pdf" href="javascript:void(0);">PDF</a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                                <select id="statusFilter" class="form-select form-select-lg">
                                    <option value="">Status</option>
                                </select>
                                <select id="sourceFilter" class="form-select form-select-lg">
                                    <option value="">Source</option>
                                </select>
                            </div>
                            <div class="flex-fill"></div>
                            <div class="d-flex">
                                <input id="tableSearch" class="form-control form-control-sm" placeholder="Search leads..." autocomplete="off"/>
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
                        <div class="card-title">Converted Leads</div>
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="onboarding-leads-datatable" class="table text-nowrap w-100">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Employee</th>
                                        <th>Final Price</th>
                                        <th>Documents</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                            
                                <tbody>
                            
                                    <?php
                            
                                    $query = mysqli_query(
                                        $con,
                                        "
                                        SELECT
                            
                                            l.id,
                                            l.fullName,
                                            l.email,
                                            l.phone,
                                            l.status,
                            
                                            lc.finalPrice,
                                            lc.quotationFile,
                            
                                            e.fullName AS employeeName,
                            
                                            oa.id AS agreementId,
                                            oa.agreementStatus,
                                            oa.createdAt,
                                            oa.sentAt,
                                            oa.agreementViewedAt,
                                            oa.agreementAcceptedAt,
                                            oa.signedAgreementFile,
                                            oas.businessDocument,
                                            oas.reviewStatus,
                                            oas.reviewRemark,
                                            oas.submittedAt
                            
                                        FROM leads l
                            
                                        INNER JOIN leadConversions lc
                                            ON lc.leadId = l.id
                            
                                        LEFT JOIN employeeusers e
                                            ON e.id = l.createdByCandidateId
                            
                                        LEFT JOIN onboardingAgreements oa
                                            ON oa.leadId = l.id
                            
                                        LEFT JOIN onboardingAgreementSubmissions oas
                                            ON oas.id = (
                                                SELECT s2.id
                                                FROM onboardingAgreementSubmissions s2
                                                WHERE s2.agreementId = oa.id
                                                ORDER BY s2.id DESC
                                                LIMIT 1
                                            )
                            
                                        WHERE l.status = 'converted'
                            
                                        ORDER BY lc.id DESC
                                        "
                                    );
                            
                                    $sr = 1;
                            
                                    while ($row = mysqli_fetch_assoc($query)) :
                            
                                        $status =
                                            !empty($row['agreementStatus'])
                                            ? strtolower($row['agreementStatus'])
                                            : 'draft';
                            
                                        $statusClasses = [
                                            'draft'     => 'btn-outline-secondary',
                                            'sent'      => 'btn-outline-primary',
                                            'viewed'    => 'btn-outline-warning',
                                            'submitted' => 'btn-outline-info',
                                            'approved'  => 'btn-outline-success',
                                            'rejected'  => 'btn-outline-danger'
                                        ];
                            
                                        $statusClass =
                                            $statusClasses[$status]
                                            ?? 'btn-outline-secondary';
                            
                                    ?>
                            
                                        <tr>
                            
                                            <td><?= $sr++ ?></td>
                            
                                            <td>
                                                <?= htmlspecialchars($row['fullName']) ?>
                            
                                                <small class="d-block text-muted">
                                                    <?= htmlspecialchars($row['email']) ?>
                                                </small>
                            
                                                <small class="d-block text-muted">
                                                    <?= htmlspecialchars($row['phone']) ?>
                                                </small>
                                            </td>
                            
                                            <td>
                                                <?= htmlspecialchars($row['employeeName'] ?? '') ?>
                                            </td>
                            
                                            <td>
                                                ₹ <?= number_format((float)($row['finalPrice'] ?? 0), 2) ?>
                                            </td>
                            
                                            <!-- DOCUMENTS -->
                                            <td>

                                                <?php if (
                                                    $status === 'approved'
                                                    && !empty($row['signedAgreementFile'])
                                                ) : ?>
                                            
                                                    <a
                                                        href="<?= SITE_URL ?>/uploads/onboarding/agreements/<?= urlencode($row['signedAgreementFile']) ?>"
                                                        target="_blank"
                                                        class="btn btn-outline-success btn-sm"
                                                        title="View Signed Agreement">
                                            
                                                        <i class="ri-file-shield-2-line me-1"></i>
                                                        Signed Agreement
                                            
                                                    </a>
                                            
                                                <?php elseif (!empty($row['quotationFile'])) : ?>
                                            
                                                    <a
                                                        href="<?= SITE_URL ?>/uploads/lead-conversions/<?= urlencode($row['quotationFile']) ?>"
                                                        target="_blank"
                                                        class="btn btn-outline-secondary btn-sm"
                                                        title="View Quotation">
                                            
                                                        <i class="ri-file-pdf-line me-1"></i>
                                                        Quotation
                                            
                                                    </a>
                                            
                                                <?php else : ?>
                                            
                                                    <span class="text-muted">--</span>
                                            
                                                <?php endif; ?>
                                            
                                            </td>
                            
                                            <!-- STATUS -->
                                            <td>
                            
                                                <button
                                                    type="button"
                                                    class="btn <?= $statusClass ?> btn-sm agreement-btn"
                            
                                                    data-id="<?= (int)$row['id'] ?>"
                            
                                                    data-name="<?= htmlspecialchars(
                                                        $row['fullName'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                            
                                                    data-email="<?= htmlspecialchars(
                                                        $row['email'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                            
                                                    data-price="<?= (float)($row['finalPrice'] ?? 0) ?>">
                            
                                                    <?= ucfirst($status) ?>
                            
                                                </button>
                            
                                            </td>
                            
                                            <!-- ACTIONS -->
                                            <td>
                            
                                                <?php
                            
                                                $canReview =
                                                    in_array(
                                                        $status,
                                                        [
                                                            'submitted',
                                                            'approved',
                                                            'rejected'
                                                        ]
                                                    );
                            
                                                ?>
                            
                                                <?php if ($canReview) : ?>
                            
                                                    <button
                                                        class="btn btn-outline-info btn-sm review-submission-btn"
                                                        data-id="<?= (int)$row['agreementId'] ?>">
                            
                                                        <?= $status === 'submitted'
                                                            ? 'Review'
                                                            : 'View Review' ?>
                            
                                                    </button>
                            
                                                <?php else : ?>
                            
                                                    <span class="text-muted">--</span>
                            
                                                <?php endif; ?>
                            
                                            </td>
                            
                                        </tr>
                            
                                    <?php endwhile; ?>
                            
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Agreement Builder Modal -->
<div class="modal fade" id="agreementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Client Agreement Builder</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="agreementLeadId" />

                <!-- Client Information -->

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label"> Client Name </label>

                        <input type="text" class="form-control" id="agreementClientName" readonly />
                    </div>

                    <div class="col-md-4">
                        <label class="form-label"> Email Address </label>

                        <input type="text" class="form-control" id="agreementClientEmail" readonly />
                    </div>

                    <div class="col-md-4">
                        <label class="form-label"> Final Price </label>

                        <input type="text" class="form-control" id="agreementFinalPrice" readonly />
                    </div>
                </div>

                <!-- Welcome Note -->

                <div class="alert alert-primary">
                    <strong> Welcome Note </strong>

                    <hr />

                    Please prepare the final onboarding agreement, pricing details, workflow, deliverables, terms &
                    conditions and onboarding instructions for the client.
                </div>
                
                
                
                <!-- Agreement Timeline -->

                <div class="card border mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">Agreement Status</h6>
                    </div>
                
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label text-muted"> Current Status </label>
                                <div>
                                    <span id="agreementStatusBadge" class="badge bg-secondary">
                                        Draft
                                    </span>
                                </div>
                            </div>
                
                            <div class="col-md-3">
                                <label class="form-label text-muted"> Created </label>
                
                                <div id="agreementCreatedAt">--</div>
                            </div>
                
                            <div class="col-md-3">
                                <label class="form-label text-muted"> Sent </label>
                
                                <div id="agreementSentAt">--</div>
                            </div>
                
                            <div class="col-md-3">
                                <label class="form-label text-muted"> Viewed </label>
                
                                <div id="agreementViewedAt">--</div>
                            </div>
                
                            <div class="col-md-3">
                                <label class="form-label text-muted"> Accepted </label>
                
                                <div id="agreementAcceptedAt">--</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quill Editor -->

                <div class="mb-3">
                    <label class="form-label"> Agreement Content </label>

                    <div id="agreementEditor" style="min-height: 400px; background: #fff"></div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
            
                <button type="button" class="btn btn-primary" id="saveAgreementBtn">Save Draft</button>
            
                <button type="button" class="btn btn-success" id="sendAgreementBtn">Send Agreement</button>
            </div>
        </div>
    </div>
</div>



<!-- HR Review Modal -->
<div class="modal fade" id="reviewAgreementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Client Submission</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <input type="hidden" id="reviewAgreementId">

                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Client Name</label>
                        <input type="text" class="form-control" id="reviewClientName" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="text" class="form-control" id="reviewClientEmail" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Final Price</label>
                        <input type="text" class="form-control" id="reviewFinalPrice" readonly>
                    </div>
                </div>
                
                <div class="card mb-4">

                    <div class="card-header">
                        <h6 class="mb-0">
                            Agreement Content
                        </h6>
                    </div>
                
                    <div class="card-body">
                        <div id="reviewAgreementContent"
                            class="ql-editor ">
                        </div>
                    </div>
                
                </div>

                <!-- Uploaded Document -->
                <div class="mb-3">
                    <label class="form-label">
                        Business Document
                    </label>
                
                    <div>
                        <a href="#" target="_blank" id="reviewBusinessDoc" class="btn btn-outline-warning">
                            <i class="ri-file-pdf-line me-1"></i>
                            View Business Document
                        </a>
                    </div>
                </div>

                <!-- Signature -->
                <div class="mb-3">
                    <label class="form-label">Authorized Signature</label>
                    <div>
                        <img id="reviewSignatureImg" src="" alt="Signature" style="border:1px solid #ccc; max-width:250px;">
                    </div>
                </div>

                <!-- Signatory Name -->
                <div class="mb-3">
                    <label class="form-label">Signatory Name</label>
                    <input type="text" class="form-control" id="reviewSignatoryName" readonly>
                </div>

                <!-- Remark (Optional) -->
                <div class="mb-3">
                    <label class="form-label">Remark (if rejecting)</label>
                    <textarea class="form-control" id="reviewRemark" rows="3" placeholder="Enter remark if rejecting"></textarea>
                </div>

                <!-- Action -->
                <div class="mb-3">
                    <label class="form-label">Action</label>
                    <select class="form-select" id="reviewAction">
                        <option value="approved">Approve</option>
                        <option value="rejected">Reject</option>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label"> Authorized Signatory Name (Company) </label>
                    <input type="text" class="form-control" id="companySignatoryName" placeholder="Enter authorized signatory name">
                </div>
                
                <div class="mb-3">
                    <label class="form-label"> Company Digital Signature </label>
                
                    <canvas id="companySignaturePad" 
                        style=" width:100%; height:180px; border:1px solid #dee2e6; border-radius:8px; background:#fff; cursor:crosshair;">
                    </canvas>
                
                    <small class="text-muted"> Sign using mouse or touch.</small>
                
                    <br>
                
                    <button type="button" class="btn btn-sm btn-secondary mt-2"
                        id="clearCompanySignature">
                        Clear Signature
                    </button>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="saveReviewBtn">Save Review</button>
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
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.0/dist/signature_pad.umd.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/oboarding-leads.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
