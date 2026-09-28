/* ==========================================================================
   Projects (admin /projects + employee /emp-projects). Data and permissions
   from api/projects/getProjects.php; every management API re-checks
   permission and CSRF server-side. Employees get active projects read-only.
   ========================================================================== */
$(function () {
    var api = {
        list: API_BASE + "/projects/getProjects.php",
        save: API_BASE + "/projects/saveProject.php",
        status: API_BASE + "/projects/setProjectStatus.php",
        docs: API_BASE + "/projects/getProjectDocuments.php",
        upload: API_BASE + "/projects/uploadProjectDocument.php",
        deleteDoc: API_BASE + "/projects/deleteProjectDocument.php",
    };
    var permissions = {};
    var rows = [];
    var viewingId = null;
    var confirmAction = null;

    function esc(v) { return $("<div>").text(v == null ? "" : String(v)).html(); }
    function toast(t, m) { if (window.showToast) window.showToast(t, m); }
    function apiError(xhr, fallback) { return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback; }
    function byId(id) { return rows.find(function (r) { return String(r.id) === String(id); }) || null; }

    var table = $("#projectsTable").DataTable(window.ModlusUI.withDataTableDefaults({
        data: [],
        order: [[0, "asc"]],
        pageLength: 20,
        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
        language: { emptyTable: "No projects yet." },
        columns: [
            { data: "projectName", render: function (d, t, r) {
                if (t !== "display") return d;
                return '<a href="javascript:void(0);" class="fw-semibold view-project-btn" data-id="' + r.id + '">' + esc(d) + "</a>" +
                    '<small class="d-block text-muted">' + r.leadCount + " lead(s)</small>";
            } },
            { data: "developerName", render: function (d) { return esc(d || "-"); } },
            { data: "location", render: function (d) { return esc(d || "-"); } },
            { data: "propertyType", render: function (d) { return esc(d || "-"); } },
            { data: "configuration", render: function (d) { return esc(d || "-"); } },
            { data: "pricing", render: function (d) { return esc(d || "-"); } },
            { data: "documentCount" },
            { data: "isActive", render: function (d, t) {
                if (t !== "display") return d ? "Active" : "Inactive";
                return d ? '<span class="badge bg-success-transparent">Active</span>' : '<span class="badge bg-secondary-transparent">Inactive</span>';
            } },
            { data: null, orderable: false, render: function (d, t, r) {
                var html = '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-primary-light view-project-btn" data-id="' + r.id + '" title="View & documents"><i class="ri-eye-line"></i></a> ';
                if (permissions.canEdit) {
                    html += '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light edit-project-btn" data-id="' + r.id + '" title="Edit"><i class="ri-edit-line"></i></a> ' +
                        '<a href="javascript:void(0);" class="btn btn-sm ' + (r.isActive ? "btn-outline-secondary" : "btn-outline-success") + ' toggle-project-btn" data-id="' + r.id + '">' + (r.isActive ? "Deactivate" : "Activate") + "</a>";
                }
                return html;
            } },
        ],
    }));

    function load() {
        $.getJSON(api.list)
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to load projects."); return; }
                permissions = res.permissions || {};
                rows = res.data || [];
                if (!$("#propertyType option").length || $("#propertyType option").length === 1) {
                    (res.propertyTypes || []).forEach(function (type) {
                        $("#propertyType, #projectTypeFilter").append($("<option>", { value: type, text: type }));
                    });
                    $.each(res.documentTypes || {}, function (key, label) {
                        $("#projectDocumentType").append($("<option>", { value: key, text: label }));
                    });
                }
                $("#addProjectBtn").toggleClass("d-none", !permissions.canAdd);
                $("#projectStatusFilter").toggleClass("d-none", !permissions.canEdit);
                table.clear().rows.add(rows).draw();
                if (viewingId) renderDetails(byId(viewingId));
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to load projects.")); });
    }

    $("#projectSearch").on("keyup", function () { table.search(this.value).draw(); });
    $("#projectTypeFilter").on("change", function () {
        var v = this.value;
        table.column(3).search(v ? "^" + $.fn.dataTable.util.escapeRegex(v) + "$" : "", true, false).draw();
    });
    $("#projectStatusFilter").on("change", function () {
        var v = this.value;
        table.column(7).search(v ? "^" + v + "$" : "", true, false).draw();
    });

    // ---------- Add / Edit ----------
    $("#addProjectBtn").on("click", function () {
        $("#projectForm")[0].reset();
        $("#projectForm").removeClass("was-validated");
        $("#projectId").val("");
        $("#projectModalTitle").text("Add Project");
        $("#projectModal").modal("show");
    });

    $("#projectsTable").on("click", ".edit-project-btn", function () {
        var r = byId($(this).data("id"));
        if (!r) return;
        $("#projectForm").removeClass("was-validated");
        $("#projectId").val(r.id);
        ["projectName", "developerName", "location", "propertyType", "configuration", "pricing", "amenities", "description"].forEach(function (f) {
            $("#" + f).val(r[f] || "");
        });
        $("#projectModalTitle").text("Edit Project");
        $("#projectModal").modal("show");
    });

    $("#projectForm").on("submit", function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.classList.add("was-validated"); toast("warning", "Please fill all required fields"); return; }
        $("#saveProjectBtn").prop("disabled", true);
        $.ajax({ url: api.save, type: "POST", dataType: "json", data: $(this).serialize() })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to save project."); return; }
                $("#projectModal").modal("hide");
                toast("success", res.message);
                load();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to save project.")); })
            .always(function () { $("#saveProjectBtn").prop("disabled", false); });
    });

    // ---------- Activate / Deactivate (confirm modal) ----------
    function confirm(title, text, action) {
        $("#projectConfirmTitle").text(title);
        $("#projectConfirmText").text(text);
        confirmAction = action;
        $("#projectConfirmModal").modal("show");
    }

    $("#projectConfirmBtn").on("click", function () {
        $("#projectConfirmModal").modal("hide");
        if (confirmAction) confirmAction();
    });

    $("#projectsTable").on("click", ".toggle-project-btn", function () {
        var r = byId($(this).data("id"));
        if (!r) return;
        var activate = !r.isActive;
        confirm(activate ? "Activate Project" : "Deactivate Project",
            activate ? "Activate " + r.projectName + "? It becomes selectable on leads and visible to sales staff."
                : "Deactivate " + r.projectName + "? Existing leads keep it, but it can no longer be selected and sales staff will not see it.",
            function () {
                $.ajax({ url: api.status, type: "POST", dataType: "json", data: { id: r.id, isActive: activate ? 1 : 0 } })
                    .done(function (res) { toast(res.success ? "success" : "danger", res.message); if (res.success) load(); })
                    .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to update project.")); });
            });
    });

    // ---------- View + documents ----------
    function detail(label, value, cls) {
        return '<div class="' + (cls || "col-md-4") + '"><div class="project-view-label">' + esc(label) + "</div><div>" + (value ? esc(value).replace(/\n/g, "<br>") : "-") + "</div></div>";
    }

    function renderDetails(r) {
        if (!r) return;
        $("#projectViewTitle").text(r.projectName);
        $("#projectViewDetails").html(
            detail("Developer / Builder", r.developerName) + detail("Location", r.location) + detail("Property Type", r.propertyType) +
            detail("Configuration", r.configuration) + detail("Pricing", r.pricing) + detail("Status", r.isActive ? "Active" : "Inactive") +
            detail("Amenities", r.amenities, "col-12") + detail("Description", r.description, "col-12")
        );
    }

    function loadDocuments(projectId) {
        $("#projectDocuments").html('<div class="text-muted small">Loading...</div>');
        $.getJSON(api.docs, { projectId: projectId })
            .done(function (res) {
                var html = "";
                (res.success ? res.data : []).forEach(function (doc) {
                    html += '<div class="border rounded p-2 mb-2 d-flex align-items-center gap-3 flex-wrap">' +
                        (doc.isImage ? '<img class="project-thumb" src="' + esc(doc.viewUrl) + '" alt="' + esc(doc.fileName) + '" loading="lazy">' : '<i class="ri-file-pdf-line fs-2 text-danger"></i>') +
                        '<div class="flex-fill"><div class="fw-semibold">' + esc(doc.fileName) + "</div>" +
                        '<div class="small text-muted"><span class="badge bg-light text-dark">' + esc(doc.documentTypeLabel) + "</span> " + doc.fileSizeKb + " KB &middot; " + esc(doc.uploadedBy) + " &middot; " + esc(doc.uploadedAt) + "</div></div>" +
                        '<div><a href="' + esc(doc.viewUrl) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1">View</a>' +
                        '<a href="' + esc(doc.downloadUrl) + '" class="btn btn-sm btn-outline-success me-1">Download</a>' +
                        (permissions.canDelete ? '<button type="button" class="btn btn-sm btn-outline-danger delete-project-doc-btn" data-id="' + doc.id + '" data-name="' + esc(doc.fileName) + '">Delete</button>' : "") +
                        "</div></div>";
                });
                $("#projectDocuments").html(html || '<div class="text-muted small">No documents uploaded.</div>');
            })
            .fail(function (xhr) { $("#projectDocuments").html('<div class="text-danger small">' + esc(apiError(xhr, "Unable to load documents.")) + "</div>"); });
    }

    $("#projectsTable").on("click", ".view-project-btn", function () {
        var r = byId($(this).data("id"));
        if (!r) return;
        viewingId = r.id;
        renderDetails(r);
        $("#projectUploadRow").toggleClass("d-none", !permissions.canEdit);
        $("#projectDocumentFile").val("");
        loadDocuments(r.id);
        $("#projectViewModal").modal("show");
    });

    $("#projectViewModal").on("hidden.bs.modal", function () { viewingId = null; });

    $("#uploadProjectDocumentBtn").on("click", function () {
        var file = $("#projectDocumentFile")[0].files[0];
        if (!file) { toast("warning", "Please select a file."); return; }
        var formData = new FormData();
        formData.append("projectId", viewingId);
        formData.append("documentType", $("#projectDocumentType").val());
        formData.append("document", file);
        $("#uploadProjectDocumentBtn").prop("disabled", true);
        $.ajax({ url: api.upload, type: "POST", data: formData, processData: false, contentType: false, dataType: "json" })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Upload failed."); return; }
                toast("success", res.message);
                $("#projectDocumentFile").val("");
                loadDocuments(viewingId);
                load();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Upload failed.")); })
            .always(function () { $("#uploadProjectDocumentBtn").prop("disabled", false); });
    });

    $("#projectDocuments").on("click", ".delete-project-doc-btn", function () {
        var id = $(this).data("id");
        confirm("Delete Document", "Delete " + $(this).data("name") + "? This cannot be undone.", function () {
            $.ajax({ url: api.deleteDoc, type: "POST", dataType: "json", data: { id: id } })
                .done(function (res) { toast(res.success ? "success" : "danger", res.message); if (res.success) { loadDocuments(viewingId); load(); } })
                .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to delete document.")); });
        });
    });

    load();
});
