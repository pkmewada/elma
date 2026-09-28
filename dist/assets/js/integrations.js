/* ==========================================================================
   Integrations settings (admin /integrations). Settings/mapping saves are
   gated server-side by the 'manage_integrations' special action; this file
   never assumes permission beyond hiding controls the API says are missing.
   ========================================================================== */
$(function () {
    var api = {
        settings: API_BASE + "/integrations/getIntegrationSettings.php",
        saveSettings: API_BASE + "/integrations/saveIntegrationSettings.php",
        logs: API_BASE + "/integrations/getIntegrationLogs.php",
        mappings: API_BASE + "/integrations/getFormMappings.php",
        saveMapping: API_BASE + "/integrations/saveFormMapping.php",
        leadMasterData: API_BASE + "/leads/getLeadMasterData.php",
    };
    var PROVIDER_LABELS = { meta: "Meta Lead Ads", google: "Google Lead Forms", website: "Website Lead Capture", whatsapp: "WhatsApp Cloud API" };
    var settingsByProvider = {};
    var urlsByProvider = {};
    var projects = [];
    var assignees = [];

    function esc(v) { return $("<div>").text(v == null ? "" : String(v)).html(); }
    function toast(t, m) { if (window.showToast) window.showToast(t, m); }
    function apiError(xhr, fallback) { return (xhr && xhr.responseJSON && xhr.responseJSON.message) || fallback; }
    function fmtDate(v) { return v ? new Date(v.replace(" ", "T")).toLocaleString() : "-"; }

    function badge(text, cls) { return '<span class="badge ' + cls + '">' + esc(text) + "</span>"; }

    function renderCards() {
        var html = "";
        $.each(PROVIDER_LABELS, function (provider, label) {
            var s = settingsByProvider[provider] || {};
            var connected = !!s.hasSecret;
            html += '<div class="col-xl-4 col-lg-6">' +
                '<div class="card custom-card h-100">' +
                '<div class="card-header d-flex align-items-center justify-content-between">' +
                '<h5 class="mb-0">' + esc(label) + "</h5>" +
                (connected ? badge("Connected", "bg-success-transparent") : badge("Not Configured", "bg-secondary-transparent")) +
                "</div>" +
                '<div class="card-body">' +
                '<p class="mb-2">Status: ' + (s.isEnabled ? badge("Enabled", "bg-success-transparent") : badge("Disabled", "bg-secondary-transparent")) + "</p>" +
                '<p class="mb-1 text-muted">Last Successful Lead: ' + esc(fmtDate(s.lastSuccessAt)) + "</p>" +
                '<p class="mb-3 text-muted">Last Error: ' + esc(s.lastErrorAt ? fmtDate(s.lastErrorAt) + " - " + (s.lastErrorMessage || "") : "-") + "</p>" +
                '<button type="button" class="btn btn-sm btn-primary configure-integration-btn" data-provider="' + provider + '">Configure</button>' +
                "</div></div></div>";
        });
        $("#integrationCards").html(html);
    }

    function loadSettings() {
        $.getJSON(api.settings)
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to load integration settings."); return; }
                settingsByProvider = {};
                (res.data || []).forEach(function (row) { settingsByProvider[row.provider] = row; });
                urlsByProvider = res.urls || {};
                renderCards();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to load integration settings.")); });
    }

    function loadMasterData() {
        $.getJSON(api.leadMasterData).done(function (res) {
            if (!res || !res.success) return;
            projects = res.data.projects || [];
            assignees = res.data.assignees || [];
            var sourceSelect = $("#configDefaultSourceId");
            (res.data.sources || []).forEach(function (s) {
                sourceSelect.append($("<option>", { value: s.id, text: s.sourceName }));
            });
            [$("#configDefaultAssigneeId"), $("#mappingDefaultAssigneeId")].forEach(function ($sel) {
                assignees.forEach(function (a) { $sel.append($("<option>", { value: a.id, text: a.fullName + " (" + a.role + ")" })); });
            });
            projects.forEach(function (p) { $("#mappingProjectId").append($("<option>", { value: p.id, text: p.projectName })); });
        });
    }

    // ---------- Configure modal ----------
    $("#integrationCards").on("click", ".configure-integration-btn", function () {
        var provider = $(this).data("provider");
        var s = settingsByProvider[provider] || {};
        var form = $("#integrationConfigForm")[0];
        form.reset();
        $(form).removeClass("was-validated");
        $("#configProvider").val(provider);
        $("#integrationConfigTitle").text("Configure " + PROVIDER_LABELS[provider]);
        $("#configEndpointUrl").val(urlsByProvider[provider] || "");
        $("#configIsEnabled").prop("checked", !!s.isEnabled);
        $("#configDefaultSourceId").val((s.config && s.config.defaultSourceId) || "");
        $("#configDefaultAssigneeId").val((s.config && s.config.defaultAssigneeId) || "");
        $("#configSourceAssigneeRow").toggleClass("d-none", provider === "whatsapp");
        $(".provider-fields").addClass("d-none");
        $("#" + provider + "Fields").removeClass("d-none");

        if (provider === "whatsapp") {
            var wc = s.config || {};
            $("#whatsappPhoneNumberId").val(wc.phoneNumberId || "");
            $("#whatsappWabaId").val(wc.wabaId || "");
            $("#whatsappApiVersion").val(wc.apiVersion || "");
            $("#whatsappTemplatesRaw").val((wc.templates || []).map(function (t) {
                return [t.name, t.language, t.variableCount, t.label].join("|");
            }).join("\n"));
        }

        $("#integrationConfigModal").modal("show");
    });

    $("#integrationConfigForm").on("submit", function (e) {
        e.preventDefault();
        var $btn = $("#saveIntegrationBtn").prop("disabled", true);
        $.ajax({ url: api.saveSettings, type: "POST", dataType: "json", data: $(this).serialize() })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to save settings."); return; }
                $("#integrationConfigModal").modal("hide");
                toast("success", res.message);
                loadSettings();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to save settings.")); })
            .always(function () { $btn.prop("disabled", false); });
    });

    $("#generateWebsiteKeyBtn").on("click", function () {
        var bytes = new Uint8Array(24);
        window.crypto.getRandomValues(bytes);
        var key = Array.from(bytes, function (b) { return b.toString(16).padStart(2, "0"); }).join("");
        $("#websiteApiKey").attr("type", "text").val(key);
    });

    // ---------- Mapping ----------
    var mappingTable = $("#mappingTable").DataTable(window.ModlusUI.withDataTableDefaults({
        data: [],
        order: [],
        pageLength: 20,
        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
        language: { emptyTable: "No mappings yet." },
        columns: [
            { data: "externalFormId" },
            { data: "externalPageId", render: function (d) { return esc(d || "-"); } },
            { data: "formLabel", render: function (d) { return esc(d || "-"); } },
            { data: "projectName", render: function (d) { return esc(d || "Unmapped"); } },
            { data: "assigneeName", render: function (d) { return esc(d || "Unassigned"); } },
            { data: "isActive", render: function (d, t) {
                if (t !== "display") return d ? "Active" : "Inactive";
                return d ? '<span class="badge bg-success-transparent">Active</span>' : '<span class="badge bg-secondary-transparent">Inactive</span>';
            } },
            { data: null, orderable: false, render: function (d, t, r) {
                return '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light edit-mapping-btn" data-id="' + r.id + '" title="Edit"><i class="ri-edit-line"></i></a>';
            } },
        ],
    }));
    var mappingRows = [];

    function loadMappings() {
        $.getJSON(api.mappings, { provider: $("#mappingProviderFilter").val() })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to load mappings."); return; }
                mappingRows = res.data || [];
                mappingTable.clear().rows.add(mappingRows).draw();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to load mappings.")); });
    }

    $("#mappingProviderFilter").on("change", loadMappings);

    $("#addMappingBtn").on("click", function () {
        var form = $("#mappingForm")[0];
        form.reset();
        $(form).removeClass("was-validated");
        $("#mappingProvider").val($("#mappingProviderFilter").val());
        $("#mappingIsActive").prop("checked", true);
        $("#mappingModal").modal("show");
    });

    $("#mappingTable").on("click", ".edit-mapping-btn", function () {
        var id = $(this).data("id");
        var row = mappingRows.find(function (r) { return String(r.id) === String(id); });
        if (!row) return;
        $("#mappingForm")[0].reset();
        $("#mappingForm").removeClass("was-validated");
        $("#mappingProvider").val(row.provider);
        $("#mappingExternalFormId").val(row.externalFormId);
        $("#mappingExternalPageId").val(row.externalPageId || "");
        $("#mappingFormLabel").val(row.formLabel || "");
        $("#mappingProjectId").val(row.projectId || "");
        $("#mappingDefaultAssigneeId").val(row.defaultAssigneeId || "");
        $("#mappingIsActive").prop("checked", !!row.isActive);
        $("#mappingModal").modal("show");
    });

    $("#mappingForm").on("submit", function (e) {
        e.preventDefault();
        if (!this.checkValidity()) { this.classList.add("was-validated"); toast("warning", "Please fill all required fields"); return; }
        var $btn = $(this).find("button[type=submit]").prop("disabled", true);
        $.ajax({ url: api.saveMapping, type: "POST", dataType: "json", data: $(this).serialize() })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to save mapping."); return; }
                $("#mappingModal").modal("hide");
                toast("success", res.message);
                loadMappings();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to save mapping.")); })
            .always(function () { $btn.prop("disabled", false); });
    });

    // ---------- Logs ----------
    var logsTable = $("#logsTable").DataTable(window.ModlusUI.withDataTableDefaults({
        data: [],
        order: [],
        pageLength: 20,
        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
        language: { emptyTable: "No integration activity yet." },
        columns: [
            { data: "receivedAt", render: function (d) { return esc(fmtDate(d)); } },
            { data: "provider", render: function (d) { return esc(PROVIDER_LABELS[d] || d); } },
            { data: "eventType" },
            { data: "externalId", render: function (d) { return esc(d || "-"); } },
            { data: "status", render: function (d, t) {
                if (t !== "display") return d;
                var cls = { created: "bg-success-transparent", duplicate: "bg-info-transparent", rejected: "bg-warning-transparent", error: "bg-danger-transparent", received: "bg-secondary-transparent" }[d] || "bg-secondary-transparent";
                return '<span class="badge ' + cls + '">' + esc(d) + "</span>";
            } },
            { data: "message", render: function (d) { return esc(d || "-"); } },
            { data: "leadName", render: function (d) { return esc(d || "-"); } },
        ],
    }));

    function loadLogs() {
        $.getJSON(api.logs, { provider: $("#logProviderFilter").val(), status: $("#logStatusFilter").val() })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Unable to load logs."); return; }
                logsTable.clear().rows.add(res.data || []).draw();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to load logs.")); });
    }

    $("#logProviderFilter, #logStatusFilter").on("change", loadLogs);
    $("#logSearch").on("keyup", function () { logsTable.search(this.value).draw(); });

    loadSettings();
    loadMasterData();
    loadMappings();
    loadLogs();
});
