$(function () {

    // Single source of truth for Country -> ISO2 -> Dial Code, loaded from
    // lead-country-data.js (window.LEAD_COUNTRIES) before this file runs, and
    // shared by both the admin Add/Edit Lead modal and the identical modal in
    // employee/emp-leads.php.
    var LEAD_COUNTRIES = window.LEAD_COUNTRIES || [];

    function isoToFlagEmoji(iso2) {
        if (!iso2 || iso2.length !== 2) return "";
        var codePoints = iso2.toUpperCase().split("").map(function (ch) {
            return 127397 + ch.charCodeAt(0);
        });
        return String.fromCodePoint.apply(null, codePoints);
    }

    // #modal-country is a plain text <input list="..."> backed by this
    // <datalist>, not a <select> and not the theme's Choices.js widget --
    // that widget's dropdown panel was getting clipped/not rendering inside
    // the Bootstrap modal ("no dropdown values"). A native input+datalist
    // has no such rendering dependency: the browser draws the suggestion
    // list itself, and it filters to matching countries as the user types.
    function populateLeadCountryDropdown(datalistEl) {
        var html = "";
        LEAD_COUNTRIES.forEach(function (c) {
            html += '<option value="' + c.name + '">' + isoToFlagEmoji(c.iso2) + ' ' + c.name + ' (' + c.dialCode + ')</option>';
        });
        datalistEl.html(html);
    }

    function findLeadCountryByName(countryName) {
        var typed = $.trim(countryName || "");
        if (!typed) return null;
        return LEAD_COUNTRIES.find(function (c) { return c.name === typed; })
            || LEAD_COUNTRIES.find(function (c) { return c.name.toLowerCase() === typed.toLowerCase(); })
            || null;
    }

    // Derives the country code from a country name (never typed by the user
    // directly) and keeps the read-only code badge, the hidden countryCode
    // field, and the #modal-country field's own validity all in sync. Does
    // NOT touch #modal-country's value -- it runs on every keystroke via the
    // "input" handler below, and overwriting the field while the user is
    // still typing would erase what they just typed.
    function applyLeadCountry(countryName) {
        var country = findLeadCountryByName(countryName);

        $("#modal-countryCode").val(country ? country.dialCode : "");
        $("#modal-countryCodeDisplay").text(country ? (isoToFlagEmoji(country.iso2) + " " + country.dialCode) : "+--");

        var countryInputEl = document.getElementById("modal-country");
        if (countryInputEl) {
            countryInputEl.setCustomValidity(country ? "" : "Please select a valid country from the list.");
        }
    }

    // Programmatic set (edit-lead, reset, phone auto-detect) -- unlike
    // applyLeadCountry() above, this also overwrites the visible field text,
    // which is safe here because these call sites aren't reacting to the
    // user's own keystrokes.
    function setLeadCountryField(countryName) {
        var country = findLeadCountryByName(countryName);
        $("#modal-country").val(country ? country.name : "");
        applyLeadCountry(country ? country.name : "");
    }

    // Matches a pasted/typed international number (e.g. "+919876543210")
    // against the local country dataset by longest dial-code prefix, so the
    // country doesn't have to be picked manually first for a full number.
    function detectCountryFromDigits(rawValue) {
        var digits = $.trim(rawValue || "").replace(/[^\d+]/g, "");
        if (digits.charAt(0) !== "+") return null;

        var best = null;
        LEAD_COUNTRIES.forEach(function (c) {
            if (digits.indexOf(c.dialCode) === 0 && (!best || c.dialCode.length > best.dialCode.length)) {
                best = c;
            }
        });
        if (!best) return null;

        return { country: best, localNumber: digits.slice(best.dialCode.length) };
    }

    // Runs on paste/blur/submit so a "+<code><number>" entry resolves the
    // country automatically -- the dial code is stripped so it is never
    // stored twice (once in countryCode, again inside the contact number).
    function autoDetectPhoneCountry() {
        var raw = $.trim($("#modal-phone").val());
        if (raw.charAt(0) !== "+") return;

        var detected = detectCountryFromDigits(raw);
        if (detected && detected.localNumber.length >= 6 && detected.localNumber.length <= 15) {
            setLeadCountryField(detected.country.name);
            $("#modal-phone").val(detected.localNumber);
        }
    }

    populateLeadCountryDropdown($("#lead-country-options"));

    // "input" (not just "change") so the code/badge update live as the user
    // types or picks a suggestion from the datalist -- but see the comment
    // on applyLeadCountry() for why this never writes back into the field.
    $(document).on("input change", "#modal-country", function () {
        applyLeadCountry($(this).val());
    });

    $(document).on("blur", "#modal-phone", autoDetectPhoneCountry);
    $(document).on("paste", "#modal-phone", function () {
        setTimeout(autoDetectPhoneCountry, 0);
    });

    /* ------------------------------------------------------------------
       Real Estate CRM lead page (admin /leads + employee /emp-leads).
       Options + permissions come from getLeadMasterData.php; every API
       re-checks permission, lead scope and CSRF server-side.
       ------------------------------------------------------------------ */
    var api = {
        master: API_BASE + "/leads/getLeadMasterData.php",
        list: API_BASE + "/leads/getLeads.php",
        add: API_BASE + "/leads/addLead.php",
        update: API_BASE + "/leads/updateLead.php",
        status: API_BASE + "/leads/updateLeadStatus.php",
        assign: API_BASE + "/leads/assignLead.php",
        contact: API_BASE + "/leads/logLeadContact.php",
        remove: API_BASE + "/leads/deleteLead.php",
        saveRemark: API_BASE + "/leads/saveLeadRemark.php",
        remarks: API_BASE + "/leads/getLeadRemarks.php",
        followUps: API_BASE + "/leads/getLeadFollowUps.php",
        followUpStatus: API_BASE + "/leads/updateLeadFollowUpStatus.php",
        uploadDoc: API_BASE + "/leads/uploadLeadDocument.php",
        docs: API_BASE + "/leads/getLeadDocuments.php",
        importCsv: API_BASE + "/leads/importLeads.php",
    };

    var master = { statuses: {}, closingStatuses: ["converted", "lost"], projects: [], sources: [], assignees: [], permissions: {} };
    var statusColumnIndex = 5;
    var sourceColumnIndex = 4;
    var projectColumnIndex = 3;

    function toast(type, message) {
        if (typeof window.showToast === "function") window.showToast(type, message);
    }

    function escHtml(str) {
        return $("<div>").text(str == null ? "" : String(str)).html();
    }

    function truncateChars(text, maxChars) {
        var str = String(text || "");
        return str.length <= maxChars ? str : str.slice(0, maxChars) + "...";
    }

    function formatStatusLabel(status) {
        return master.statuses[status] || String(status || "").replace(/_/g, " ");
    }

    function formatDateTime(value) {
        if (!value) return "";
        var dateOnly = /^\d{4}-\d{2}-\d{2}$/.test(String(value));
        var d = new Date(dateOnly ? value + "T00:00:00" : String(value).replace(" ", "T"));
        if (isNaN(d.getTime())) return value;
        if (dateOnly) return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
        return d.toLocaleString("en-GB", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: true });
    }

    function apiError(xhr, fallback) {
        return xhr && xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : fallback;
    }

    function waNumber(row) {
        var code = String(row.countryCode || "+91").replace(/\D/g, "") || "91";
        return code + String(row.phone || "").replace(/\D/g, "");
    }

    // ---------- Table ----------
    var table = $("#leads-datatable").DataTable(
        window.ModlusUI.withDataTableDefaults({
            data: [],
            deferRender: true,
            order: [[7, "desc"]],
            columns: [
                { data: null, orderable: false, searchable: false, render: function (d, t, r, meta) { return meta.row + 1; } },
                { data: "fullName", render: function (data, type, row) {
                    if (type === "export") return [row.fullName, row.phone ? (row.countryCode || "") + " " + row.phone : "", row.email].filter(Boolean).join(" | ");
                    if (type !== "display") return [row.fullName, row.email, row.phone].filter(Boolean).join(" ");
                    return '<span title="' + escHtml(row.fullName) + '">' + escHtml(truncateChars(row.fullName, 22)) + "</span>" +
                        '<small class="d-block text-muted">' + escHtml((row.countryCode || "") + " " + row.phone) + "</small>" +
                        (row.email ? '<small class="d-block text-muted">' + escHtml(row.email) + "</small>" : "");
                } },
                { data: "assignedToName", render: function (data, type) {
                    if (type !== "display") return data || "Unassigned";
                    return data ? escHtml(data) : '<span class="text-muted">Unassigned</span>';
                } },
                { data: "projectName", render: function (data, type) {
                    if (type !== "display") return data || "";
                    return data ? escHtml(data) : '<span class="text-muted">-</span>';
                } },
                { data: "source", render: function (data, type) { return type === "display" ? escHtml(data || "-") : (data || ""); } },
                { data: "status", render: function (data, type, row) {
                    if (type === "export") return formatStatusLabel(row.status);
                    if (type !== "display") return row.status;
                    return getStatusDropdownHtml(row.id, row.status);
                } },
                { data: "nextFollowUp", render: function (data, type) {
                    if (type === "sort") return data || "9999";
                    if (type !== "display") return data ? formatDateTime(data) : "";
                    return data ? escHtml(formatDateTime(data)) : '<span class="text-muted">-</span>';
                } },
                { data: "createdAt", render: function (data, type) { return type === "sort" ? data : formatDateTime(data); } },
                { data: null, orderable: false, searchable: false, className: "lead-actions", render: function (data, type, row) {
                    var p = master.permissions;
                    var id = row.id;
                    return '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-warning-light remark-btn" data-id="' + id + '" title="Remarks & follow-ups"><i class="ri-chat-3-line"></i></a> ' +
                        (p.canEdit ? '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light edit-lead-btn" data-id="' + id + '" title="Edit"><i class="ri-edit-line"></i></a> ' : "") +
                        '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-secondary-light document-btn" data-id="' + id + '" title="Documents"><i class="ri-file-pdf-line"></i></a> ' +
                        '<a href="tel:+' + escHtml(waNumber(row)) + '" class="btn btn-icon btn-sm btn-primary-light call-btn" data-id="' + id + '" title="Call"><i class="ri-phone-line"></i></a> ' +
                        '<a href="' + escHtml(WHATSAPP_CHAT_URL) + "?leadId=" + id + '" class="btn btn-icon btn-sm btn-success-light whatsapp-btn" data-id="' + id + '" title="WhatsApp (opens CRM chat)"><i class="ri-whatsapp-line"></i></a> ' +
                        (p.canAssign ? '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-purple-light assign-lead-btn" data-id="' + id + '" title="' + (row.assignedToId ? "Reassign" : "Assign") + '"><i class="ri-user-shared-line"></i></a> ' : "") +
                        (p.canDelete ? '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-danger-light delete-lead-btn" data-id="' + id + '" title="Delete"><i class="ri-delete-bin-line"></i></a>' : "");
                } },
            ],
            dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            buttons: [
                { extend: "csvHtml5", className: "d-none buttons-csv", title: "leads", exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7], orthogonal: "export" } },
                { extend: "excelHtml5", className: "d-none buttons-excel", title: "leads", exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7], orthogonal: "export" } },
                { extend: "pdfHtml5", className: "d-none buttons-pdf", title: "Leads", orientation: "landscape", exportOptions: { columns: [1, 2, 3, 4, 5, 6, 7], orthogonal: "export" } },
            ],
            pageLength: 20,
        })
    );

    function rowById(id) {
        return table.rows().data().toArray().find(function (r) { return String(r.id) === String(id); }) || null;
    }

    function getStatusDropdownHtml(id, status) {
        var items = "";
        $.each(master.statuses, function (key, label) {
            items += '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="' + key + '">' + escHtml(label) + "</a></li>";
        });
        var disabled = master.permissions.canEdit ? "" : " disabled";
        return '<div class="btn-group" data-id="' + id + '">' +
            '<button type="button" class="btn btn-sm dropdown-toggle lead-status-btn lead-status-' + status + '" data-bs-toggle="dropdown" aria-expanded="false"' + disabled + ">" +
            escHtml(formatStatusLabel(status)) + "</button>" +
            '<ul class="dropdown-menu">' + items + "</ul></div>";
    }

    // ---------- Filters + status cards ----------
    var sourceFilter = $("#sourceFilter");
    var statusFilter = $("#statusFilter");
    var projectFilter = $("#projectFilter");
    var employeeFilter = $("#employeeFilter");
    var dateRangeInput = $("#leadDateRangeFilter");
    var statusCards = $("#leadStatusCards");
    var dateRange = { from: dateRangeInput.attr("data-date-from") || "", to: dateRangeInput.attr("data-date-to") || "" };
    var dateRangePicker = null;
    var scopeRows = [];
    var leadsRequest = null;
    var prefill = window.leadsFilterPrefill || {};

    function fillSelect(selectEl, items, valueKey, labelKey, keepFirst) {
        selectEl.find(keepFirst ? "option:not(:first)" : "option").remove();
        items.forEach(function (item) {
            selectEl.append($("<option>", { value: item[valueKey], text: item[labelKey] }));
        });
    }

    function applyMasterData() {
        var statusItems = $.map(master.statuses, function (label, key) { return { key: key, label: label }; });
        fillSelect(statusFilter, statusItems, "key", "label", true);
        fillSelect($("#modal-status"), statusItems, "key", "label", false);
        fillSelect(sourceFilter, master.sources, "sourceName", "sourceName", true);
        fillSelect($("#modal-sourceId"), master.sources, "id", "sourceName", true);
        fillSelect(projectFilter, master.projects, "projectName", "projectName", true);
        fillSelect($("#modal-projectId"), master.projects, "id", "projectName", true);
        var assigneeItems = master.assignees.map(function (e) { return { id: e.id, label: e.fullName + " (" + e.role + ")" }; });
        fillSelect($("#modal-assignedToId"), assigneeItems, "id", "label", true);
        fillSelect($("#assignEmployeeId"), assigneeItems, "id", "label", true);
        fillSelect($("#importEmployeeId"), assigneeItems, "id", "label", true);
        if (employeeFilter.length) {
            employeeFilter.find("option").slice(2).remove();
            master.assignees.forEach(function (e) { employeeFilter.append($("<option>", { value: e.id, text: e.fullName })); });
        }
        $(".lead-assign-field").toggleClass("d-none", !master.permissions.canAssign);

        if (prefill.status) statusFilter.val(prefill.status);
        if (prefill.source) sourceFilter.val(prefill.source);
        if (prefill.employeeId && employeeFilter.length) employeeFilter.val(prefill.employeeId);
        renderStatusCards();
    }

    function renderStatusCards() {
        var cards = [{ status: "", key: "all", label: "All" }];
        $.each(master.statuses, function (key, label) { cards.push({ status: key, key: key, label: label }); });
        var html = "";
        cards.forEach(function (card) {
            html += '<div class="col"><div class="card custom-card mb-0 lead-status-card" data-status="' + card.status + '" title="Show ' + escHtml(card.label) + ' leads">' +
                '<div class="card-body p-3"><div class="d-flex align-items-baseline gap-2">' +
                '<span class="fs-20 fw-semibold" data-status-count="' + card.key + '">—</span>' +
                '<span class="text-muted fs-12" data-status-percent="' + card.key + '"></span></div>' +
                '<span class="badge ' + (card.status ? "lead-status-" + card.status : "bg-primary-transparent") + '">' + escHtml(card.label) + "</span>" +
                "</div></div></div>";
        });
        statusCards.html(html);
    }

    function columnExactSearch(value) {
        return value ? "^" + $.fn.dataTable.util.escapeRegex(value) + "$" : "";
    }

    function applyColumnFilters() {
        table.column(sourceColumnIndex).search(columnExactSearch(sourceFilter.val()), true, false);
        table.column(projectColumnIndex).search(columnExactSearch(projectFilter.val()), true, false);
        table.column(statusColumnIndex).search(columnExactSearch(statusFilter.val()), true, false);
        table.draw();
        updateStatusCounts();
    }

    // Cards count the loaded scope + Source/Project filters, not the Status
    // filter, so every status stays visible while one is selected.
    function updateStatusCounts() {
        var source = sourceFilter.val();
        var project = projectFilter.val();
        var counts = { all: 0 };
        scopeRows.forEach(function (row) {
            if (source && row.source !== source) return;
            if (project && row.projectName !== project) return;
            counts[row.status] = (counts[row.status] || 0) + 1;
            counts.all++;
        });
        statusCards.find("[data-status-count]").each(function () { $(this).text(counts[$(this).attr("data-status-count")] || 0); });
        statusCards.find("[data-status-percent]").each(function () {
            var count = counts[$(this).attr("data-status-percent")] || 0;
            $(this).text((counts.all ? Math.round((count / counts.all) * 1000) / 10 : 0) + "%");
        });
        statusCards.find(".lead-status-card").each(function () {
            $(this).toggleClass("active", $(this).attr("data-status") === (statusFilter.val() || ""));
        });
    }

    function loadLeads() {
        var requestData = {};
        if (dateRange.from) requestData.dateFrom = dateRange.from;
        if (dateRange.to) requestData.dateTo = dateRange.to;
        if (employeeFilter.length && employeeFilter.val()) requestData.employeeId = employeeFilter.val();
        if (leadsRequest) leadsRequest.abort();

        scopeRows = [];
        table.settings()[0].oLanguage.sEmptyTable = "Loading...";
        table.clear().draw();

        leadsRequest = $.ajax({ url: api.list, method: "GET", dataType: "json", data: requestData })
            .done(function (response) {
                if (!response || !response.success) {
                    table.settings()[0].oLanguage.sEmptyTable = "Unable to load leads.";
                    table.clear().draw();
                    toast("danger", (response && response.message) || "Unable to load leads.");
                    return;
                }
                scopeRows = response.data || [];
                table.settings()[0].oLanguage.sEmptyTable = "No leads found.";
                table.clear().rows.add(scopeRows);
                applyColumnFilters();
            })
            .fail(function (xhr, textStatus) {
                if (textStatus === "abort") return;
                var message = apiError(xhr, "Unable to load leads.");
                table.settings()[0].oLanguage.sEmptyTable = message;
                table.clear().draw();
                toast("danger", message);
            });
    }

    statusCards.on("click", ".lead-status-card", function () {
        var status = $(this).attr("data-status");
        statusFilter.val(statusFilter.val() === status ? "" : status);
        applyColumnFilters();
    });
    sourceFilter.on("change", applyColumnFilters);
    projectFilter.on("change", applyColumnFilters);
    statusFilter.on("change", applyColumnFilters);
    employeeFilter.on("change", loadLeads);

    if (dateRangeInput.length && typeof window.flatpickr === "function") {
        dateRangePicker = window.flatpickr(dateRangeInput[0], {
            mode: "range", dateFormat: "Y-m-d", altInput: true, altFormat: "d M Y",
            defaultDate: dateRange.from ? [dateRange.from, dateRange.to || dateRange.from] : [],
            onClose: function (selectedDates, _, instance) {
                var from = selectedDates.length ? instance.formatDate(selectedDates[0], "Y-m-d") : "";
                var to = selectedDates.length > 1 ? instance.formatDate(selectedDates[1], "Y-m-d") : from;
                if (from === dateRange.from && to === dateRange.to) return;
                dateRange = { from: from, to: to };
                loadLeads();
            },
        });
    }

    $("#leadsFilterResetBtn").on("click", function () {
        statusFilter.val(""); sourceFilter.val(""); projectFilter.val("");
        if (employeeFilter.length) employeeFilter.val("");
        dateRange = { from: "", to: "" };
        if (dateRangePicker) dateRangePicker.clear();
        loadLeads();
    });

    $(".export-btn").on("click", function () {
        var type = $(this).data("type");
        table.button(".buttons-" + type).trigger();
    });

    $("#tableSearch").on("keyup", function () { table.search(this.value).draw(); });

    table.on("order.dt search.dt draw.dt", function () {
        var info = table.page.info();
        table.column(0, { search: "applied", order: "applied", page: "current" }).nodes().each(function (cell, index) {
            cell.innerHTML = info.start + index + 1;
        });
    });

    // ---------- Status ----------
    $(document).on("click", ".change-status", function (event) {
        event.preventDefault();
        var status = $(this).data("status");
        var leadId = $(this).closest(".btn-group").data("id");
        if (!leadId || !status) return;
        if (master.closingStatuses.indexOf(status) !== -1) {
            $("#statusLeadId").val(leadId);
            $("#selectedLeadStatus").val(status);
            $("#selectedStatusText").text(formatStatusLabel(status));
            $("#statusRemark").val("");
            $("#leadStatusModalTitle").text(status === "lost" ? "Mark Lead as Lost" : "Mark Lead as Converted");
            $("#leadStatusModal").modal("show");
            return;
        }
        saveStatus(leadId, status, "");
    });

    $("#saveLeadStatusBtn").on("click", function () {
        var remark = $.trim($("#statusRemark").val());
        if (!remark) { toast("warning", "Please enter a remark / reason."); return; }
        saveStatus($("#statusLeadId").val(), $("#selectedLeadStatus").val(), remark);
    });

    function saveStatus(leadId, status, remark) {
        $.ajax({ url: api.status, method: "POST", contentType: "application/json", dataType: "json", data: JSON.stringify({ id: leadId, status: status, remark: remark }) })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Status update failed."); return; }
                $("#leadStatusModal").modal("hide");
                toast("success", res.message || "Status updated");
                loadLeads();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Status update failed.")); });
    }

    // ---------- Add / Edit ----------
    var addLeadForm = $("#addLeadForm");
    var submitButton = $("#addLeadSubmitBtn");

    addLeadForm.on("submit", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var formEl = this;
        autoDetectPhoneCountry();
        formEl.classList.add("was-validated");
        if (!formEl.checkValidity()) { toast("warning", "Please fill all required fields"); return; }

        var isEdit = $("#leadId").val() !== "";
        var status = $("#modal-status").val();
        var payload = {
            fullName: $.trim($("#modal-fullName").val()),
            email: $.trim($("#modal-email").val()),
            phone: $.trim($("#modal-phone").val()),
            country: $.trim($("#modal-country").val()),
            countryCode: $.trim($("#modal-countryCode").val()),
            projectId: parseInt($("#modal-projectId").val() || 0, 10),
            sourceId: parseInt($("#modal-sourceId").val() || 0, 10),
        };
        if (isEdit) {
            payload.id = $("#leadId").val();
        } else {
            payload.status = status;
            payload.assignedToId = parseInt($("#modal-assignedToId").val() || 0, 10);
            payload.nextFollowUp = $("#modal-nextFollowUp").val();
            payload.remark = $.trim($("#modal-remark").val());
            if (master.closingStatuses.indexOf(status) !== -1 && !payload.remark) {
                toast("warning", "A remark / reason is required for " + formatStatusLabel(status) + " leads.");
                return;
            }
        }

        submitButton.prop("disabled", true);
        $("#addLeadSubmitSpinner").removeClass("d-none");
        $.ajax({ url: isEdit ? api.update : api.add, method: "POST", contentType: "application/json", dataType: "json", data: JSON.stringify(payload) })
            .done(function (response) {
                if (!response || !response.success) { toast("danger", (response && response.message) || "Failed to save lead"); return; }
                formEl.reset();
                formEl.classList.remove("was-validated");
                $("#addLeadModal").modal("hide");
                toast("success", response.message || "Lead saved");
                loadLeads();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Failed to save lead")); })
            .always(function () {
                submitButton.prop("disabled", false);
                $("#addLeadSubmitSpinner").addClass("d-none");
            });
    });

    $("#leads-datatable").on("click", ".edit-lead-btn", function () {
        var row = rowById($(this).data("id"));
        if (!row) return;
        $("#leadId").val(row.id);
        $("#modal-fullName").val(row.fullName);
        $("#modal-email").val(row.email || "");
        setLeadCountryField(row.country || "");
        $("#modal-phone").val(row.phone);
        if (row.projectId && !$("#modal-projectId option[value='" + row.projectId + "']").length) {
            $("#modal-projectId").append($("<option>", { value: row.projectId, text: row.projectName + " (inactive)" }));
        }
        $("#modal-projectId").val(row.projectId || "");
        $("#modal-sourceId").val(row.sourceId || "");
        $(".lead-create-only").addClass("d-none");
        $("#addLeadModalLabel").text("Edit Lead");
        $("#addLeadSubmitText").text("Update Lead");
        $("#addLeadModal").modal("show");
    });

    $("#addLeadModal").on("show.bs.modal", function () {
        if ($("#leadId").val() === "") {
            addLeadForm[0].reset();
            addLeadForm[0].classList.remove("was-validated");
            setLeadCountryField("");
            $("#modal-status").val("new");
            $(".lead-create-only").removeClass("d-none");
            $(".lead-assign-field").toggleClass("d-none", !master.permissions.canAssign);
            $("#addLeadModalLabel").text("Add Lead");
            $("#addLeadSubmitText").text("Save Lead");
        }
    });

    $("#addLeadModal").on("hidden.bs.modal", function () { $("#leadId").val(""); });

    // ---------- Assign / Reassign ----------
    $("#leads-datatable").on("click", ".assign-lead-btn", function () {
        var row = rowById($(this).data("id"));
        if (!row) return;
        $("#assignLeadId").val(row.id);
        $("#assignLeadName").text(row.fullName);
        $("#assignCurrentName").text(row.assignedToName || "Unassigned");
        $("#assignEmployeeId").val(row.assignedToId || 0);
        $("#assignLeadModal").modal("show");
    });

    $("#saveAssignLeadBtn").on("click", function () {
        $.ajax({ url: api.assign, method: "POST", dataType: "json", data: { leadId: $("#assignLeadId").val(), employeeId: $("#assignEmployeeId").val() || 0 } })
            .done(function (res) {
                if (!res || !res.success) { toast("danger", (res && res.message) || "Assignment failed."); return; }
                $("#assignLeadModal").modal("hide");
                toast("success", res.message || "Lead assigned");
                loadLeads();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Assignment failed.")); });
    });

    // ---------- Delete ----------
    $("#leads-datatable").on("click", ".delete-lead-btn", function () {
        $("#deleteConfirmModal").data("deleteId", $(this).data("id")).modal("show");
    });

    $("#confirmDeleteBtn").on("click", function () {
        var id = $("#deleteConfirmModal").data("deleteId");
        $("#deleteConfirmModal").modal("hide");
        $.ajax({ url: api.remove, method: "POST", contentType: "application/json", dataType: "json", data: JSON.stringify({ id: id }) })
            .done(function (response) {
                if (!response || !response.success) { toast("danger", (response && response.message) || "Failed to delete lead"); return; }
                toast("success", response.message || "Lead deleted successfully");
                loadLeads();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Failed to delete lead")); });
    });

    // ---------- Call (log that the action was opened) ----------
    // WhatsApp now navigates into the CRM's own chat page (WHATSAPP_CHAT_URL)
    // instead of wa.me; that page's own activity logging covers sent/received
    // messages, so there is nothing to log on the click itself here anymore.
    $("#leads-datatable").on("click", ".call-btn", function () {
        $.ajax({ url: api.contact, method: "POST", dataType: "json", data: { leadId: $(this).data("id"), channel: "call" } });
    });

    // ---------- Remarks + follow-ups ----------
    function openRemarks(leadId, leadName) {
        $("#remarkLeadId").val(leadId);
        $("#remarkLeadName").text(leadName || "");
        $("#leadRemark").val("");
        $("#followUpDateTime").val("");
        loadLeadRemarks(leadId);
        loadLeadFollowUps(leadId);
        $("#leadRemarkModal").modal("show");
    }

    $("#leads-datatable").on("click", ".remark-btn", function () {
        var row = rowById($(this).data("id"));
        openRemarks($(this).data("id"), row ? row.fullName : "");
    });

    function loadLeadRemarks(leadId) {
        $.getJSON(api.remarks, { leadId: leadId }, function (response) {
            var html = "";
            (response.success ? response.data : []).forEach(function (item) {
                html += '<div class="border rounded p-3 mb-2">' +
                    '<div class="small text-muted">' + escHtml(item.createdAt) + " &middot; " + escHtml(item.employeeName) + "</div>" +
                    (item.status ? '<span class="badge bg-light text-dark mt-1">' + escHtml(item.status) + "</span>" : "") +
                    '<div class="mt-2">' + escHtml(item.remark) + "</div></div>";
            });
            $("#remarkTimeline").html(html || '<div class="text-muted small">No remarks yet.</div>');
        });
    }

    function loadLeadFollowUps(leadId) {
        $.getJSON(api.followUps, { leadId: leadId }, function (response) {
            var html = "";
            (response.success ? response.data : []).forEach(function (f) {
                var when = formatDateTime(f.dueTime ? f.dueDate + " " + f.dueTime : f.dueDate);
                var pending = f.status === "Pending";
                html += '<div class="d-flex justify-content-between align-items-center border rounded p-2 mb-2">' +
                    "<div><span class=\"fw-semibold\">" + escHtml(when) + "</span> " +
                    '<span class="badge ' + (pending ? "bg-warning-transparent" : "bg-success-transparent") + '">' + escHtml(f.status) + "</span>" +
                    (f.remark ? '<div class="small text-muted">' + escHtml(f.remark) + "</div>" : "") + "</div>" +
                    (pending && master.permissions.canEdit ? '<button type="button" class="btn btn-sm btn-outline-success mark-followup-btn" data-id="' + f.id + '" data-lead="' + f.leadId + '">Complete</button>' : "") +
                    "</div>";
            });
            $("#leadFollowUpList").html(html || '<div class="text-muted small">No follow-ups scheduled.</div>');
        });
    }

    $("#saveRemarkBtn").on("click", function () {
        var leadId = $("#remarkLeadId").val();
        $.ajax({ url: api.saveRemark, type: "POST", dataType: "json", data: { leadId: leadId, remark: $.trim($("#leadRemark").val()), followUpDateTime: $("#followUpDateTime").val() } })
            .done(function (response) {
                if (!response || !response.success) { toast("danger", (response && response.message) || "Unable to save."); return; }
                $("#leadRemark").val("");
                $("#followUpDateTime").val("");
                loadLeadRemarks(leadId);
                loadLeadFollowUps(leadId);
                toast("success", response.message || "Remark saved");
                loadLeads();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to save.")); });
    });

    $(document).on("click", "#leadFollowUpList .mark-followup-btn", function () {
        var leadId = $(this).data("lead");
        $.ajax({ url: api.followUpStatus, type: "POST", contentType: "application/json", dataType: "json", data: JSON.stringify({ id: $(this).data("id"), status: "Completed" }) })
            .done(function (response) {
                if (!response || !response.success) { toast("danger", (response && response.message) || "Failed to update follow up."); return; }
                toast("success", response.message || "Follow up completed.");
                loadLeadFollowUps(leadId);
                loadLeads();
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Failed to update follow up.")); });
    });

    // ---------- Documents ----------
    $("#leads-datatable").on("click", ".document-btn", function () {
        var row = rowById($(this).data("id"));
        $("#documentLeadId").val($(this).data("id"));
        $("#documentLeadName").text(row ? row.fullName : "");
        $("#leadDocumentFile").val("");
        loadLeadDocuments($(this).data("id"));
        $("#leadDocumentsModal").modal("show");
    });

    function loadLeadDocuments(leadId) {
        $.getJSON(api.docs, { leadId: leadId }, function (response) {
            var html = "";
            (response.success ? response.data : []).forEach(function (doc) {
                html += '<div class="border rounded p-3 mb-3"><div class="d-flex justify-content-between align-items-start flex-wrap gap-2"><div>' +
                    '<div class="fw-semibold"><i class="ri-file-pdf-line text-danger me-1"></i>' + escHtml(doc.fileName) + "</div>" +
                    '<div class="small text-muted mt-1">Uploaded by ' + escHtml(doc.employeeName) + " &middot; " + escHtml(doc.uploadedAt) + "</div></div><div>" +
                    '<a href="' + escHtml(doc.viewUrl) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary me-1">View</a>' +
                    '<a href="' + escHtml(doc.downloadUrl) + '" class="btn btn-sm btn-outline-success">Download</a></div></div></div>';
            });
            $("#leadDocumentsContainer").html(html || '<div class="text-center text-muted py-4">No documents uploaded.</div>');
        });
    }

    $("#uploadLeadDocumentBtn").on("click", function () {
        var leadId = $("#documentLeadId").val();
        var file = $("#leadDocumentFile")[0].files[0];
        if (!file) { toast("warning", "Please select a PDF."); return; }
        var formData = new FormData();
        formData.append("leadId", leadId);
        formData.append("document", file);
        $.ajax({ url: api.uploadDoc, type: "POST", data: formData, processData: false, contentType: false, dataType: "json" })
            .done(function (response) {
                if (!response || !response.success) { toast("danger", (response && response.message) || "Upload failed."); return; }
                toast("success", response.message);
                $("#leadDocumentFile").val("");
                loadLeadDocuments(leadId);
            })
            .fail(function (xhr) { toast("danger", apiError(xhr, "Upload failed.")); });
    });

    // ---------- Import ----------
    $(document).on("submit", "#importLeadForm", function (event) {
        event.preventDefault();
        var file = $("#leadCsvFile")[0].files[0];
        if (!file) { toast("warning", "Please upload a CSV file."); return; }
        var formData = new FormData();
        formData.append("leadCsvFile", file);
        formData.append("employeeId", $("#importEmployeeId").val() || "");
        $("#importLeadSubmitBtn").prop("disabled", true);
        $("#importLeadSpinner").removeClass("d-none");
        $("#importLeadErrors").addClass("d-none").empty();
        $.ajax({ url: api.importCsv, type: "POST", data: formData, processData: false, contentType: false, dataType: "json", timeout: 60000 })
            .done(function (response) {
                var errors = (response && response.data && response.data.errors) || [];
                if (errors.length) {
                    $("#importLeadErrors").removeClass("d-none").html(errors.map(escHtml).join("<br>"));
                }
                toast(response && response.success ? "success" : "danger", (response && response.message) || "Import failed.");
                if (response && response.success) {
                    loadLeads();
                    if (!errors.length) $("#importLeadModal").modal("hide");
                }
            })
            .fail(function (xhr, status) { toast("danger", status === "timeout" ? "Import request timed out." : apiError(xhr, "Import failed.")); })
            .always(function () {
                $("#importLeadSubmitBtn").prop("disabled", false);
                $("#importLeadSpinner").addClass("d-none");
            });
    });

    // Deep link (Follow-ups page "Open"): ?leadId=123 opens that lead's remarks.
    function openLeadFromQueryString() {
        var leadId = new URLSearchParams(window.location.search).get("leadId");
        if (!leadId) return;
        var row = rowById(leadId);
        openRemarks(leadId, row ? row.fullName : "");
    }

    // ---------- Boot ----------
    $.getJSON(api.master)
        .done(function (response) {
            if (response && response.success) {
                master = response.data;
                applyMasterData();
            }
            loadLeads();
            if (leadsRequest) leadsRequest.done(openLeadFromQueryString);
        })
        .fail(function (xhr) { toast("danger", apiError(xhr, "Unable to load lead options.")); });
});
