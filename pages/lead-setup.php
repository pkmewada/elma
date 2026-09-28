<?php
include __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Lead Setup = Lead Sources master. The pipeline statuses are system-defined
// (includes/leadAccess.php); follow-up rules live on Follow Up Setup.
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<div class="main-content app-content">
    <div class="container-fluid">
        <div class="my-4 page-header-breadcrumb d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title fw-medium fs-18 mb-2">Lead Sources</h1>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="dashboard">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Lead Sources</li>
                </ol>
            </div>
            <button type="button" class="btn btn-primary btn-wave" id="addSourceBtn"><i class="ri-add-line me-1"></i> Add Source</button>
        </div>

        <div class="card custom-card">
            <div class="card-header"><div class="card-title">Sources</div></div>
            <div class="card-body">
                <p class="text-muted small">System sources are used by manual entry and the lead integrations and cannot be renamed; they can be deactivated. Add portals or channels your team uses (e.g. property portals).</p>
                <div class="table-responsive">
                    <table id="leadSourcesTable" data-ui-table="mamix" class="table table-hover text-wrap">
                        <thead><tr><th>Source</th><th>Key</th><th>Type</th><th>Leads</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="leadSourceModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="leadSourceModalTitle">Add Source</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="leadSourceForm" class="needs-validation" novalidate>
                        <div class="modal-body">
                            <input type="hidden" id="sourceId" name="id" />
                            <label class="form-label" for="sourceName">Source Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="sourceName" name="sourceName" maxlength="100" required />
                            <div class="invalid-feedback">Source name is required.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.12.1/css/dataTables.bootstrap5.min.css" />
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.12.1/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function () {
    var esc = function (v) { return $("<div>").text(v == null ? "" : String(v)).html(); };
    var toast = function (t, m) { if (window.showToast) window.showToast(t, m); };
    var table = $("#leadSourcesTable").DataTable(window.ModlusUI.withDataTableDefaults({
        data: [], ordering: false, paging: false, dom: "t",
        columns: [
            { data: "sourceName", render: esc },
            { data: "sourceKey", render: function (d) { return '<code>' + esc(d) + '</code>'; } },
            { data: "isSystem", render: function (d) { return d ? 'System' : 'Custom'; } },
            { data: "leadCount" },
            { data: "isActive", render: function (d) { return d ? '<span class="badge bg-success-transparent">Active</span>' : '<span class="badge bg-secondary-transparent">Inactive</span>'; } },
            { data: null, render: function (d, t, row) {
                return (row.isSystem ? '' : '<button type="button" class="btn btn-icon btn-sm btn-info-light edit-source-btn" data-id="' + row.id + '" data-name="' + esc(row.sourceName) + '" title="Rename"><i class="ri-edit-line"></i></button> ') +
                    '<button type="button" class="btn btn-sm ' + (row.isActive ? 'btn-outline-secondary' : 'btn-outline-success') + ' toggle-source-btn" data-id="' + row.id + '" data-active="' + (row.isActive ? 0 : 1) + '">' + (row.isActive ? 'Deactivate' : 'Activate') + '</button>';
            } },
        ],
    }));

    function load() {
        $.getJSON(API_BASE + "/leads/getLeadSources.php", function (r) { table.clear().rows.add(r.success ? r.data : []).draw(); });
    }

    function save(data, done) {
        $.ajax({ url: API_BASE + "/leads/saveLeadSource.php", type: "POST", dataType: "json", data: data })
            .done(function (r) { toast(r.success ? "success" : "danger", r.message); if (r.success) { load(); if (done) done(); } })
            .fail(function (x) { toast("danger", (x.responseJSON && x.responseJSON.message) || "Unable to save source."); });
    }

    $("#addSourceBtn").on("click", function () {
        $("#leadSourceForm")[0].reset(); $("#leadSourceForm").removeClass("was-validated");
        $("#sourceId").val(""); $("#leadSourceModalTitle").text("Add Source"); $("#leadSourceModal").modal("show");
    });
    $("#leadSourcesTable").on("click", ".edit-source-btn", function () {
        $("#sourceId").val($(this).data("id")); $("#sourceName").val($(this).data("name"));
        $("#leadSourceModalTitle").text("Rename Source"); $("#leadSourceModal").modal("show");
    });
    $("#leadSourcesTable").on("click", ".toggle-source-btn", function () {
        save({ action: "toggle", id: $(this).data("id"), isActive: $(this).data("active") });
    });
    $("#leadSourceForm").on("submit", function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.classList.add("was-validated"); return; }
        save({ action: "save", id: $("#sourceId").val(), sourceName: $.trim($("#sourceName").val()) }, function () { $("#leadSourceModal").modal("hide"); });
    });
    load();
});
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
