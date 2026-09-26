<?php
    include __DIR__ . "/../includes/auth.php";
    include __DIR__ . "/../includes/db.php";
    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/sidebar.php';
?>


<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Client Onboarding</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">
                        <a href="dashboard">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">Client Onboarding</li>
                </ol>
            </div>
            <!--<div class="d-flex gap-2">-->
            <!--    <button type="button" class="btn btn-info btn-wave" id="scheduledCallsBtn">-->
            <!--        <i class="ri-calendar-event-line me-1"></i>-->
            <!--        Scheduled Calls-->
            <!--    </button>-->

            <!--    <button type="button" class="btn btn-success btn-wave" data-bs-toggle="modal" data-bs-target="#importLeadModal">-->
            <!--        <i class="ri-upload-cloud-2-line me-1"></i>-->
            <!--        Import Leads-->
            <!--    </button>-->

            <!--    <button type="button" class="btn btn-primary btn-wave" data-bs-toggle="modal" data-bs-target="#addLeadModal">-->
            <!--        <i class="ri-user-add-line me-1"></i>-->
            <!--        Add Lead-->
            <!--    </button>-->
            <!--</div>-->
        </div>
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
                                                <a class="dropdown-item export-btn" data-type="csv" href="javascript:void(0);">CSV</a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item export-btn" data-type="pdf" href="javascript:void(0);">PDF</a>
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
                                <input id="tableSearch" class="form-control form-control-sm" placeholder="Search Client..." autocomplete="off"/>
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
                        <div class="card-title">Client Onboaridng DataTable</div>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="clientMasterTable" class="table table-hover w-100">
                                <thead>
                                <tr>
                                    <th>Sno</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Onboarded</th>
                                    <th>Action</th>
                                </tr>
                                </thead>
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
<script src="<?= ASSET_URL ?>/assets/libs/sweetalert2/sweetalert2.min.js"></script>
<script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/client-master.js?v=<?php echo time(); ?>"></script>
<?php include __DIR__ .'/../includes/modals/client-onboarding-modal.php'; ?>
<?php include __DIR__ .'/../includes/modals/service-selection-modal.php'; ?>
<?php include __DIR__ .'/../includes/footer.php'; ?>