<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
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

.status-chip-info {
    color: rgb(var(--info-rgb));
    border-color: rgba(var(--info-rgb), 0.3);
}

.status-chip-warning {
    color: rgb(var(--warning-rgb));
    border-color: rgba(var(--warning-rgb), 0.3);
}

.status-chip-danger {
    color: rgb(var(--danger-rgb));
    border-color: rgba(var(--danger-rgb), 0.3);
}
</style>
<div class="main-content app-content">
    <div class="container-fluid">

        <!-- HEADER -->
        <div class="my-4 d-flex justify-content-between align-items-center">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Asset Management</h1>
            </div>

            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                    <i class="ri-add-line me-1"></i> Add Asset
                </button>

                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#assignAssetModal">
                    Assign Asset
                </button>
            </div>
        </div>

        <!-- CONTROLS ROW -->
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
                                <select id="assetStatusFilter" class="form-select form-select-lg">
                                    <option value="">Status</option>
                                    <option value="available">Available</option>
                                    <option value="assigned">Assigned</option>
                                </select>
                            </div>
                            <div class="flex-fill"></div>
                            <div class="d-flex">
                                <input id="assetTableSearch" class="form-control form-control-sm"
                                    placeholder="Search assets..." autocomplete="off">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLE ROW -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="assetTable" data-ui-table="mamix" class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>SNo</th>
                                        <th>Asset Code</th>
                                        <th>Asset Name</th>
                                        <th>Category</th>
                                        <th>Status</th>
                                        <th>Assigned To</th>
                                        <th>Condition</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- DATA WILL COME FROM AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ASSET REQUESTS -->
        <div class="row">
            <div class="col-xl-12">
                <div class="card custom-card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="card-title">Asset Requests</div>
                        <select id="assetRequestStatusFilter" class="form-select form-select-sm w-auto">
                            <option value="pending" selected>Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                            <option value="">All</option>
                        </select>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Asset</th>
                                        <th>Quantity</th>
                                        <th>Purpose</th>
                                        <th>Expected Return Date</th>
                                        <th>Remarks</th>
                                        <th>Request Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="assetRequestsTableBody">
                                    <tr><td colspan="9" class="text-center text-muted py-3">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- REJECT REQUEST MODAL -->
        <div class="modal fade" id="rejectAssetRequestModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Reject Asset Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" id="rejectRequestId">
                        <label class="form-label">Rejection Remark (optional)</label>
                        <textarea id="rejectRequestRemark" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-danger" id="confirmRejectRequestBtn">Reject Request</button>
                    </div>

                </div>
            </div>
        </div>

        <!-- ADD ASSET MODAL -->
        <div class="modal fade" id="addAssetModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">

                    <form id="addAssetForm">

                        <div class="modal-header">
                            <h5 class="modal-title">Add Asset</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="row g-3">

                                <div class="col-md-6">
                                    <label class="form-label">Asset Name</label>
                                    <input type="text" name="assetName" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Category</label>
                                    <?php
                                $cat = mysqli_query($con, "
                                    SELECT id, categoryName 
                                    FROM assetCategory 
                                    ORDER BY categoryName ASC
                                ");
                            ?>

                                    <select name="categoryId" class="form-select" required>
                                        <option value="">Select Category</option>

                                        <?php if($cat && mysqli_num_rows($cat) > 0): ?>
                                        <?php while($c = mysqli_fetch_assoc($cat)): ?>
                                        <option value="<?= (int)$c['id'] ?>">
                                            <?= htmlspecialchars($c['categoryName']) ?>
                                        </option>
                                        <?php endwhile; ?>
                                        <?php else: ?>
                                        <option value="" disabled>No Categories Found</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Brand</label>
                                    <input type="text" name="brand" class="form-control">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Serial Number</label>
                                    <input type="text" name="serialNumber" class="form-control">
                                </div>

                            </div>

                        </div>

                        <div class="modal-footer">

                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                Cancel
                            </button>

                            <button type="submit" class="btn btn-primary" id="saveAssetBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="saveAssetSpinner"></span>
                                <span id="saveAssetText">Save Asset</span>
                            </button>

                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- ASSIGN ASSET MODAL -->
        <div class="modal fade" id="assignAssetModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <form id="assignAssetForm">

                        <div class="modal-header">
                            <h5 class="modal-title">Assign Asset</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">Select Asset</label>
                                <select name="assetId" id="assetDropdown" class="form-select" required>
                                    <option value="">Loading...</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Select Employee</label>
                                <select name="employeeId" id="employeeDropdown" class="form-select" required>
                                    <option value="">Loading...</option>
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="quantity" class="form-control" min="1" value="1">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Expected Return Date</label>
                                    <input type="date" name="expectedReturnDate" class="form-control">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Purpose</label>
                                    <input type="text" name="purpose" class="form-control" placeholder="e.g. Shoot, Outside work, Office purpose">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Issue Remarks</label>
                                    <textarea name="issueRemarks" class="form-control" rows="2"></textarea>
                                </div>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-success" id="assignBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="assignSpinner"></span>
                                <span id="assignText">Assign</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <div class="modal fade" id="returnAssetModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">

                    <form id="returnAssetForm">

                        <input type="hidden" name="assetId" id="returnAssetId">

                        <div class="modal-header">
                            <h5 class="modal-title">Return Asset / Close Entry</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-3">
                                <label class="form-label">Return Condition</label>
                                <select name="conditionStatus" class="form-select">
                                    <option value="good">Good</option>
                                    <option value="new">Like New</option>
                                    <option value="damaged">Damaged</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Return Remarks</label>
                                <textarea name="remarks" class="form-control"></textarea>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>

                            <button type="submit" class="btn btn-danger" id="returnBtn">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="returnSpinner"></span>
                                <span id="returnText">Submit Return / Close Entry</span>
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>

        <!-- ASSET HISTORY MODAL -->
        <div class="modal fade" id="assetHistoryModal" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">

                    <div class="modal-header">
                        <h5 class="modal-title">Asset History</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div id="assetHistoryContent">
                            <p class="text-muted">Loading...</p>
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
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/dataTables.buttons.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.bootstrap5.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.6/pdfmake.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.html5.min.js"></script>
        <script src="https://cdn.datatables.net/buttons/2.2.3/js/buttons.print.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <script>
        $(function() {

            // =========================================================
            // ✅ GLOBALS
            // =========================================================
            let table;

            // Toast helper (same as candidate module)
            function showToast(type, message) {
                if (typeof window.showToast === 'function') {
                    window.showToast(type, message);
                } else {
                    console.log(type.toUpperCase() + ': ' + message);
                }
            }

            // =========================================================
            // ✅ HELPER → LIVE ROW UPDATE
            // =========================================================
            function updateRow(assetId, newData) {

                let updated = false;

                table.rows().every(function() {
                    let d = this.data();

                    if (String(d.id) === String(assetId)) {
                        this.data($.extend({}, d, newData)).invalidate();
                        updated = true;
                    }
                });

                if (updated) {
                    table.draw(false);
                }
            }

            // =========================================================
            // ✅ DATATABLE INIT
            // =========================================================
            if ($.fn.DataTable.isDataTable('#assetTable')) {
                $('#assetTable').DataTable().destroy();
            }

            table = $('#assetTable').DataTable({
                processing: true,
                ajax: {
                    url: API_BASE + '/assets/getAsset.php',
                    dataSrc: function(json) {
                        if (!json) return [];
                        if (Array.isArray(json)) return json;
                        if (json.success === false) {
                            showToast('danger', json.message || 'Failed to load assets');
                            return [];
                        }
                        return json.data || [];
                    },
                    error: function() {
                        showToast('danger', 'Failed to load assets');
                    }
                },

                columns: [{
                        data: null
                    }, // SNo
                    {
                        data: 'assetCode'
                    },
                    {
                        data: 'assetName'
                    },
                    {
                        data: 'categoryName',
                        defaultContent: '-'
                    },

                    {
                        data: 'status',
                        render: function(data) {
                            let badge = data === 'available' ? 'status-chip-success' :
                                'status-chip-info';
                            return `<span class="status-chip ${badge}">${data}</span>`;
                        }
                    },

                    {
                        data: 'assignedTo',
                        defaultContent: '-',
                        render: function(data, type, row) {
                            if (!data || !row.assignedEmployeeId) return '-';
                            return `<a href="javascript:void(0);" class="view-employee-assets"
                                data-employee-id="${row.assignedEmployeeId}"
                                data-employee-name="${data}">${data}</a>`;
                        }
                    },
                    {
                        data: 'conditionStatus'
                    },

                    {
                        data: null,
                        orderable: false,
                        render: function(data) {
                    
                            let assignBtn = data.status === 'available' ?
                                `<a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light assign-btn"
                                    data-id="${data.id}"
                                    title="Assign">
                                    <i class="ri-user-add-line"></i>
                                </a>` :
                                '';
                    
                            let returnBtn = data.status === 'assigned' ?
                                `<a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-info-light btn-wave waves-effect waves-light return-btn"
                                    data-id="${data.id}"
                                    title="Return">
                                    <i class="ri-arrow-go-back-line"></i>
                                </a>` :
                                '';
                    
                            let historyBtn = `
                                <a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-secondary-light btn-wave waves-effect waves-light history-btn"
                                    data-id="${data.id}"
                                    title="History">
                                    <i class="ri-history-line"></i>
                                </a>
                            `;
                    
                            let deleteBtn = `
                                <a href="javascript:void(0);"
                                    class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light delete-btn"
                                    data-id="${data.id}"
                                    title="Delete">
                                    <i class="ri-delete-bin-line"></i>
                                </a>
                            `;
                    
                            return `
                                ${assignBtn}
                                ${returnBtn}
                                ${historyBtn}
                                ${deleteBtn}
                            `;
                        }
                    }
                ],

                order: [],
                pageLength: 10,
                dom: "t<'row mt-3 align-items-center'<'col-md-5'i><'col-md-7'p>>",
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
                    let api = this.api();
                    api.column(0).nodes().each(function(cell, i) {
                        cell.innerHTML = i + 1;
                    });
                }
            });

            $('#assetStatusFilter').on('change', function() {
                table.column(4).search($.trim($(this).val())).draw();
            });

            $('#assetTableSearch').on('keyup input', function() {
                table.search($.trim($(this).val())).draw();
            });

            $('.export-btn').on('click', function() {
                var type = $(this).data('type');
                if (type === 'csv') table.buttons('.buttons-csv').trigger();
                if (type === 'pdf') table.buttons('.buttons-pdf').trigger();
            });

            // =========================================================
            // ✅ ADD ASSET
            // =========================================================
            $('#addAssetForm').on('submit', function(e) {

                e.preventDefault();

                let formData = $(this).serialize();

                let btn = $('#saveAssetBtn');
                let spinner = $('#saveAssetSpinner');
                let text = $('#saveAssetText');

                btn.prop('disabled', true);
                spinner.removeClass('d-none');
                text.text('Saving...');

                $.ajax({
                    url: API_BASE + '/assets/addAsset.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }

                        $('#addAssetModal').modal('hide');
                        $('#addAssetForm')[0].reset();

                        // ✅ Add new row instantly
                        if (res.data && res.data.id) {
                            table.row.add(res.data).draw(false);
                        } else {
                            table.ajax.reload(null, false);
                        }

                        showToast('success', 'Asset added successfully');
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    },

                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                        text.text('Save Asset');
                    }
                });

            });

            // =========================================================
            // ✅ ASSIGN ASSET
            // =========================================================
            $('#assignAssetForm').on('submit', function(e) {

                e.preventDefault();

                let formData = $(this).serialize();

                let btn = $('#assignBtn');
                let spinner = $('#assignSpinner');
                let text = $('#assignText');

                btn.prop('disabled', true);
                spinner.removeClass('d-none');
                text.text('Assigning...');

                $.ajax({
                    url: API_BASE + '/assets/assignAsset.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }

                        $('#assignAssetModal').modal('hide');

                        // ✅ SAFE CHECK
                        if (res.data && res.data.id) {
                            updateRow(res.data.id, res.data);
                        } else {
                            // fallback (rare case)
                            table.ajax.reload(null, false);
                        }

                        showToast('success', 'Asset assigned successfully');
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    },

                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                        text.text('Assign');
                    }
                });

            });

            // =========================================================
            // ✅ INLINE ASSIGN BUTTON CLICK
            // =========================================================
            $(document).on('click', '.assign-btn', function() {

                let assetId = $(this).data('id');

                $('#assignAssetModal').modal('show');

                setTimeout(() => {
                    $('#assetDropdown').val(assetId);
                }, 300);
            });

            // =========================================================
            // ✅ LOAD DROPDOWNS
            // =========================================================
            $('#assignAssetModal').on('show.bs.modal', function() {

                $.ajax({
                    url: API_BASE + '/assets/getAvailableAssets.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (!res.success) return;

                        let html = '<option value="">Select Asset</option>';
                        res.data.forEach(a => {
                            html +=
                                `<option value="${a.id}">${a.assetCode} - ${a.assetName}</option>`;
                        });

                        $('#assetDropdown').html(html);
                    }
                });

                $.ajax({
                    url: API_BASE + '/employee/getEmployees.php',
                    type: 'GET',
                    dataType: 'json',
                    success: function(res) {
                        if (!res.success) return;

                        let html = '<option value="">Select Employee</option>';
                        res.data.forEach(e => {
                            html +=
                                `<option value="${e.id}">${e.fullName}</option>`;
                        });

                        $('#employeeDropdown').html(html);
                    }
                });

            });

            // =========================================================
            // ✅ RETURN FLOW
            // =========================================================
            $(document).on('click', '.return-btn', function() {
                let assetId = $(this).data('id');
                $('#returnAssetId').val(assetId);
                $('#returnAssetModal').modal('show');
            });

            $('#returnAssetForm').on('submit', function(e) {

                e.preventDefault();

                let formData = $(this).serialize();

                let btn = $('#returnBtn');
                let spinner = $('#returnSpinner');
                let text = $('#returnText');

                btn.prop('disabled', true);
                spinner.removeClass('d-none');
                text.text('Processing...');

                $.ajax({
                    url: API_BASE + '/assets/returnAsset.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }

                        $('#returnAssetModal').modal('hide');

                        if (res.data && res.data.id) {
                            updateRow(res.data.id, res.data);
                        } else {
                            table.ajax.reload(null, false);
                        }

                        showToast('success', 'Asset returned successfully');
                    },

                    error: function() {
                        showToast('danger', 'Server error');
                    },

                    complete: function() {
                        btn.prop('disabled', false);
                        spinner.addClass('d-none');
                        text.text('Return Asset');
                    }
                });

            });

            // =========================================================
            // ✅ ASSET HISTORY
            // =========================================================
            $(document).on('click', '.history-btn', function() {

                let id = $(this).data('id');
                let $deleteBtn = $(this);

                $('#assetHistoryModal').modal('show');
                $('#assetHistoryContent').html('Loading...');

                $.ajax({
                    url: API_BASE + '/assets/getAssetHistory.php',
                    type: 'GET',
                    data: {
                        assetId: id
                    },
                    dataType: 'json',

                    success: function(res) {

                        console.log('History API:', res);

                        if (!res.success) {
                            $('#assetHistoryContent').html(
                                '<p class="text-danger">Failed to load history</p>');
                            return;
                        }

                        if (!res.data || res.data.length === 0) {
                            $('#assetHistoryContent').html(
                                '<p class="text-muted">No history found</p>');
                            return;
                        }

                        let html = '';

                        res.data.forEach((h, i) => {

                            let badge = h.status === 'assigned' ?
                                'bg-warning' :
                                'bg-success';

                            html += `
            <div class="border rounded p-3 mb-2">

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge ${badge}">${h.status}</span>
                    <small class="text-muted">${h.assignedDate || h.createdAt}</small>
                </div>

                <div><strong>Employee:</strong> ${h.fullName || h.employeeName || '-'}</div>

                ${ h.quantity ? `<div><strong>Quantity:</strong> ${h.quantity}</div>` : '' }
                ${ h.purpose ? `<div><strong>Purpose:</strong> ${h.purpose}</div>` : '' }
                ${ h.expectedReturnDate ? `<div><strong>Expected Return Date:</strong> ${h.expectedReturnDate}</div>` : '' }
                ${ h.issueRemarks ? `<div><strong>Issue Remarks:</strong> ${h.issueRemarks}</div>` : '' }

                ${
                    h.actualReturnDate
                        ? `<div><strong>Returned Date:</strong> ${h.actualReturnDate}</div>`
                        : ''
                }

                ${ h.returnCondition ? `<div><strong>Return Condition:</strong> ${h.returnCondition}</div>` : '' }

                ${
                    h.remarks
                        ? `<div><strong>Return Remarks:</strong> ${h.remarks}</div>`
                        : ''
                }

            </div>
        `;
                        });

                        $('#assetHistoryContent').html(html);
                    }
                });

            });

            // =========================================================
            // ✅ EMPLOYEE ASSET RECORD ("Assigned To" name click)
            // =========================================================
            $(document).on('click', '.view-employee-assets', function() {

                let employeeId = $(this).data('employee-id');
                let employeeName = $(this).data('employee-name');

                $('#assetHistoryModal .modal-title').text('Asset Record — ' + employeeName);
                $('#assetHistoryModal').modal('show');
                $('#assetHistoryContent').html('Loading...');

                $.ajax({
                    url: API_BASE + '/assets/getEmployeeAssetHistory.php',
                    type: 'GET',
                    data: { employeeId: employeeId },
                    dataType: 'json',

                    success: function(res) {

                        if (!res.success) {
                            $('#assetHistoryContent').html('<p class="text-danger">Failed to load asset record</p>');
                            return;
                        }

                        if (!res.data || res.data.length === 0) {
                            $('#assetHistoryContent').html('<p class="text-muted">No assets found for this employee</p>');
                            return;
                        }

                        let html = '';

                        res.data.forEach((h) => {

                            let badge = h.status === 'assigned' ? 'bg-warning' : 'bg-success';

                            html += `
            <div class="border rounded p-3 mb-2">

                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="badge ${badge}">${h.status}</span>
                    <small class="text-muted">${h.assignedDate}</small>
                </div>

                <div><strong>Asset:</strong> ${h.assetCode} - ${h.assetName} ${h.categoryName ? '(' + h.categoryName + ')' : ''}</div>

                ${ h.quantity ? `<div><strong>Quantity:</strong> ${h.quantity}</div>` : '' }
                ${ h.purpose ? `<div><strong>Purpose:</strong> ${h.purpose}</div>` : '' }
                ${ h.expectedReturnDate ? `<div><strong>Expected Return Date:</strong> ${h.expectedReturnDate}</div>` : '' }
                ${ h.actualReturnDate ? `<div><strong>Returned Date:</strong> ${h.actualReturnDate}</div>` : '' }
                ${ h.returnCondition ? `<div><strong>Return Condition:</strong> ${h.returnCondition}</div>` : '' }
                ${ h.remarks ? `<div><strong>Return Remarks:</strong> ${h.remarks}</div>` : '' }

            </div>
        `;
                        });

                        $('#assetHistoryContent').html(html);
                    },

                    error: function() {
                        $('#assetHistoryContent').html('<p class="text-danger">Failed to load asset record</p>');
                    }
                });

            });

            $('#assetHistoryModal').on('hidden.bs.modal', function() {
                $('#assetHistoryModal .modal-title').text('Asset History');
            });

            // =========================================================
            // ✅ DELETE ASSET (SweetAlert only)
            // =========================================================
            $(document).on('click', '.delete-btn', function() {

                let id = $(this).data('id');
                let $deleteBtn = $(this);

                Swal.fire({
                    title: 'Are you sure?',
                    text: "This asset will be deleted!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'Yes, delete it'
                }).then((result) => {

                    if (result.isConfirmed) {

                        $.ajax({
                            url: API_BASE + '/assets/deleteAsset.php',
                            type: 'POST',
                            data: {
                                id: id
                            },
                            dataType: 'json',

                            success: function(res) {

                                if (!res.success) {
                                    showToast('danger', res.message);
                                    return;
                                }

                                // ✅ Remove only that row
                                let rowNode = $deleteBtn.closest('tr');
                                if (rowNode.length) {
                                    table.row(rowNode).remove().draw(false);
                                    table.column(0, {
                                        search: 'applied',
                                        order: 'applied'
                                    }).nodes().each(function(cell, i) {
                                        cell.innerHTML = i + 1;
                                    });
                                } else {
                                    table.ajax.reload(null, false);
                                }

                                showToast('success', 'Asset deleted successfully');
                            },

                            error: function() {
                                showToast('danger', 'Server error');
                            }
                        });
                    }
                });

            });

            // =========================================================
            // ✅ ASSET REQUESTS (employee-submitted, awaiting approval)
            // =========================================================
            function loadAssetRequests() {
                $('#assetRequestsTableBody').html('<tr><td colspan="9" class="text-center text-muted py-3">Loading...</td></tr>');

                $.getJSON(API_BASE + '/assets/getAssetRequests.php', {
                    status: $('#assetRequestStatusFilter').val()
                }, function(res) {
                    if (!res.success || !res.data.length) {
                        $('#assetRequestsTableBody').html('<tr><td colspan="9" class="text-center text-muted py-3">No asset requests found.</td></tr>');
                        return;
                    }

                    let html = '';
                    res.data.forEach(function(r) {
                        let badge = r.status === 'approved' ? 'status-chip-success' :
                            (r.status === 'rejected' ? 'status-chip-danger' : 'status-chip-warning');
                        let actions = '-';
                        if (r.status === 'pending') {
                            actions =
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-success-light btn-wave waves-effect waves-light approve-request-btn" data-id="' + r.id + '" title="Approve"><i class="ri-check-line"></i></a> ' +
                                '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-danger-light btn-wave waves-effect waves-light reject-request-btn" data-id="' + r.id + '" title="Reject"><i class="ri-close-line"></i></a>';
                        }

                        html += '<tr>' +
                            '<td>' + (r.employeeName || '-') + '</td>' +
                            '<td>' + r.assetCode + ' - ' + r.assetName + '</td>' +
                            '<td>' + r.quantity + '</td>' +
                            '<td>' + (r.purpose || '-') + '</td>' +
                            '<td>' + (r.expectedReturnDate || '-') + '</td>' +
                            '<td>' + (r.remarks || '-') + '</td>' +
                            '<td>' + r.createdAt + '</td>' +
                            '<td><span class="status-chip ' + badge + '">' + r.status + '</span></td>' +
                            '<td>' + actions + '</td>' +
                        '</tr>';
                    });
                    $('#assetRequestsTableBody').html(html);
                }).fail(function() {
                    $('#assetRequestsTableBody').html('<tr><td colspan="9" class="text-center text-danger py-3">Failed to load asset requests.</td></tr>');
                });
            }

            $('#assetRequestStatusFilter').on('change', loadAssetRequests);

            $(document).on('click', '.approve-request-btn', function() {
                let id = $(this).data('id');

                Swal.fire({
                    title: 'Approve this request?',
                    text: 'The asset will be assigned to the employee if it is still available.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, approve'
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    $.ajax({
                        url: API_BASE + '/assets/approveAssetRequest.php',
                        type: 'POST',
                        data: { id: id },
                        dataType: 'json',
                        success: function(res) {
                            if (!res.success) {
                                showToast('danger', res.message);
                                return;
                            }
                            showToast('success', res.message);
                            loadAssetRequests();
                            table.ajax.reload(null, false);
                        },
                        error: function() {
                            showToast('danger', 'Server error');
                        }
                    });
                });
            });

            $(document).on('click', '.reject-request-btn', function() {
                $('#rejectRequestId').val($(this).data('id'));
                $('#rejectRequestRemark').val('');
                $('#rejectAssetRequestModal').modal('show');
            });

            $('#confirmRejectRequestBtn').on('click', function() {
                let id = $('#rejectRequestId').val();
                let remark = $('#rejectRequestRemark').val();

                $.ajax({
                    url: API_BASE + '/assets/rejectAssetRequest.php',
                    type: 'POST',
                    data: { id: id, rejectionRemark: remark },
                    dataType: 'json',
                    success: function(res) {
                        if (!res.success) {
                            showToast('danger', res.message);
                            return;
                        }
                        $('#rejectAssetRequestModal').modal('hide');
                        showToast('success', res.message);
                        loadAssetRequests();
                    },
                    error: function() {
                        showToast('danger', 'Server error');
                    }
                });
            });

            loadAssetRequests();

        });
        </script>
        <?php include __DIR__ . '/../includes/footer.php'; ?>