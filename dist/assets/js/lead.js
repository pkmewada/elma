$(function () {
    
    window.addEventListener("error", function (e) {
        console.log("JS ERROR:", e.message, e.filename, e.lineno);
    });


    var leadCategories = [];
    var leadPlans = [];

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

    loadLeadMasterData();

    var statusColumnIndex = 5;
    var sourceColumnIndex = 4;
    var addLeadApiUrl = API_BASE + "/leads/addLead.php";
    var updateLeadApiUrl = API_BASE + "/leads/updateLead.php";
    var updateLeadStatusApiUrl = API_BASE + "/leads/updateLeadStatus.php";
    var deleteLeadApiUrl = API_BASE + "/leads/deleteLead.php";
    var saveLeadRemarkApiUrl = API_BASE + "/leads/saveLeadRemark.php";
    var getLeadRemarksApiUrl = API_BASE + "/leads/getLeadRemarks.php";
    var getScheduledCallsApiUrl = API_BASE + "/leads/getScheduledCalls.php";
    var getLeadFollowUpsApiUrl = API_BASE + "/leads/getLeadFollowUps.php";
    var updateLeadFollowUpStatusApiUrl = API_BASE + "/leads/updateLeadFollowUpStatus.php";
    var uploadLeadDocumentApiUrl = API_BASE + "/leads/uploadLeadDocument.php";
    var getLeadDocumentsApiUrl = API_BASE + "/leads/getLeadDocuments.php";
    var importLeadsApiUrl = API_BASE + "/leads/importLeads.php";
    var getLeadsApiUrl = API_BASE + "/leads/getLeads.php";
    var leadStatusLabels = {
        open: "Open",
        interested: "Interested",
        connected: "Connected",
        converted: "Converted",
        not_interested: "Not Interested",
        not_connected: "Not Connected",
    };

    // Some leads have very long free-text names (business listings pasted
    // as-is); the first column showed the full text unclipped and blew out
    // the table width. Truncated to 15 characters for display only --
    // sorting/searching still use the full name, and the full name stays
    // available via the native title tooltip.
    function escHtml(str) {
        return $("<div>").text(str == null ? "" : String(str)).html();
    }

    function truncateChars(text, maxChars) {
        var str = String(text || "");
        if (str.length <= maxChars) return str;
        return str.slice(0, maxChars) + "...";
    }

    function formatStatusLabel(status) {
        var rawStatus = $.trim(status || "");
        if (!rawStatus) return "";
        if (leadStatusLabels[rawStatus]) return leadStatusLabels[rawStatus];
        return rawStatus
            .replace(/_/g, " ")
            .replace(/\b\w/g, function (letter) {
                return letter.toUpperCase();
            });
    }

    var table = $("#leads-datatable").DataTable(
        window.ModlusUI.withDataTableDefaults({
            data: [],
            deferRender: true,
            order: [[7, 'desc']],
            columns: [
                { data: null, orderable: false, searchable: false, render: (data, type, row, meta) => meta.row + 1 },
                { data: 'fullName', render: (data, type, row) => {
                    // The table's global search box searches the string a
                    // render() returns for type "filter"/"sort" -- it was
                    // only ever given row.fullName here, so email/phone/
                    // country (all visibly shown in this same cell) could
                    // never actually be found by the search box.
                    if (type !== 'display') {
                        return [row.fullName, row.email, row.phone, row.country].filter(Boolean).join(' ');
                    }
                    return '<span title="' + escHtml(row.fullName) + '">' + escHtml(truncateChars(row.fullName, 15)) + '</span>' +
                        '<small class="d-block text-muted">' + row.email + '</small>' +
                        '<small class="d-block text-muted">' + row.phone + '</small>';
                } },
                { data: 'employeeName', defaultContent: 'Admin' },
                { data: null, render: (data, type, row) => {
                    if (type !== 'display') return (row.categoryName || '') + ' ' + (row.planName || '');
                    return (row.categoryName ?? '-') +
                        '<small class="d-block text-muted">' + (row.planName ?? '-') + '</small>';
                } },
                { data: 'source' },
                { data: 'status', render: (data, type, row) => {
                    // Same issue as fullName above: without this guard, the
                    // search box was matching against the FULL status
                    // dropdown menu markup (every status label, always
                    // present regardless of the row's actual status), so
                    // typing any status word matched every row.
                    if (type !== 'display') return row.status;
                    return getStatusDropdownHtml(row.id, row.status);
                } },
                { data: 'orgName', defaultContent: '-', render: (data, type) => {
                    if (type !== 'display') return data || '-';
                    return data ? data.replace(/(.{20})/g, '$1<br>') : '-';
                } },
                { data: 'createdAt', render: data => 
                    new Date(data).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:true })
                },
                { data: null, orderable: false, searchable: false, render: (data, type, row) => {
                    // Legacy leads (no country/countryCode yet) fall back to
                    // India/+91, same assumption this WhatsApp link always made.
                    var waCode = (row.countryCode || '+91').replace(/\D/g, '') || '91';
                    return '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-warning-light remark-btn" data-id="'+row.id+'" data-name="'+row.fullName+'"><i class="ri-chat-3-line"></i></a> ' +
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-info-light edit-lead-btn" data-id="'+row.id+'" data-fullname="'+row.fullName+'" data-email="'+row.email+'" data-phone="'+row.phone+'" data-country="'+(row.country||'')+'" data-countrycode="'+(row.countryCode||'')+'" data-source="'+row.source+'" data-orgname="'+row.orgName+'" data-categoryid="'+row.categoryId+'" data-planid="'+row.planId+'"><i class="ri-edit-line"></i></a> ' +
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-secondary-light document-btn" data-id="'+row.id+'" data-name="'+row.fullName+'"><i class="ri-file-pdf-line"></i></a> ' +
                    '<a href="https://wa.me/'+waCode+row.phone.replace(/\D/g,'')+'?text=Hello%20'+encodeURIComponent(row.fullName)+'" target="_blank" class="btn btn-icon btn-sm btn-success-light"><i class="ri-whatsapp-line"></i></a> ' +
                    '<a href="javascript:void(0);" class="btn btn-icon btn-sm btn-danger-light delete-lead-btn" data-id="'+row.id+'"><i class="ri-delete-bin-line"></i></a>';
                } }
            ],
            dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            buttons: [
                { extend: "csvHtml5", className: "d-none buttons-csv", exportOptions: { columns: [0,1,2,3,4,5,6,7,8] } },
                { extend: "pdfHtml5", className: "d-none buttons-pdf", exportOptions: { columns: [0,1,2,3,4,5,6,7,8] } }
            ],
            pageLength: 20
        })
    );

    var sourceFilter = $("#sourceFilter");
    var statusFilter = $("#statusFilter");
    var employeeFilter = $("#employeeFilter");
    var dateRangeInput = $("#leadDateRangeFilter");
    // Picked range as Y-m-d strings (a single picked day = from and to).
    var dateRange = {
        from: dateRangeInput.attr("data-date-from") || "",
        to: dateRangeInput.attr("data-date-to") || "",
    };
    var dateRangePicker = null;
    var statusCards = $("#leadStatusCards");
    var pendingSourceFilter = "";
    // Rows of the current Employee/Date scope, before Status/Source narrow
    // the table -- the status cards are counted from this same set.
    var scopeRows = [];
    var leadsRequest = null;

    populateStatusFilter();
    renderStatusCards();

    // Employee and Date range are server-side (getLeads.php WHERE clause).
    // Status and Source are client-side DataTables column searches over that
    // loaded scope, so the status cards can show every status's count for
    // the same scope the table is showing, even while one status is selected.
    if (window.leadsFilterPrefill) {
        if (leadsFilterPrefill.status) {
            statusFilter.val(leadsFilterPrefill.status);
        }
        if (leadsFilterPrefill.source) {
            pendingSourceFilter = leadsFilterPrefill.source;
        }
    }

    loadLeads();

    employeeFilter.on("change", function () {
        var params = new URLSearchParams();
        var employeeId = $(this).val();
        if (employeeId) params.set("employeeId", employeeId);
        if (statusFilter.val()) params.set("status", statusFilter.val());
        if (sourceFilter.val()) params.set("source", sourceFilter.val());
        if (dateRange.from) params.set("dateFrom", dateRange.from);
        if (dateRange.to) params.set("dateTo", dateRange.to);

        var query = params.toString();
        window.location.href = window.location.pathname + (query ? "?" + query : "");
    });

    // Same flatpickr range-picker pattern as the app's other filter bars
    // (apply-leave, graphic-content): one input, mode "range". Reloads only
    // when the picker closes with a different range, not on each click.
    if (dateRangeInput.length && typeof window.flatpickr === "function") {
        dateRangePicker = window.flatpickr(dateRangeInput[0], {
            mode: "range",
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "d M Y",
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

    // Reset Filters -- clears Status/Source/Date range (and Employee, admin
    // only) and reloads. Employee is server-side, so if it's active a plain
    // reload (no query string) is needed to clear the SQL WHERE clause too.
    $("#leadsFilterResetBtn").on("click", function () {
        if (employeeFilter.length && (employeeFilter.val() || window.location.search)) {
            window.location.href = window.location.pathname;
            return;
        }
        statusFilter.val("");
        sourceFilter.val("");
        dateRange = { from: "", to: "" };
        if (dateRangePicker) dateRangePicker.clear();
        pendingSourceFilter = "";
        loadLeads();
    });

    function loadLeads() {
        var selectedSource = sourceFilter.val() || pendingSourceFilter || "";
        var requestData = {};

        if (dateRange.from) requestData.dateFrom = dateRange.from;
        if (dateRange.to) requestData.dateTo = dateRange.to;
        if (employeeFilter.length && employeeFilter.val()) {
            requestData.employeeId = employeeFilter.val();
        }

        // Drop a still-running request so an older, slower response can't
        // overwrite the table/cards after the filters changed again.
        if (leadsRequest) leadsRequest.abort();

        scopeRows = [];
        statusCards.find("[data-status-count]").text("—");
        statusCards.find("[data-status-percent]").text("");
        table.settings()[0].oLanguage.sEmptyTable = "Loading...";
        table.clear().draw();

        leadsRequest = $.ajax({
            url: getLeadsApiUrl,
            method: "GET",
            dataType: "json",
            data: requestData,
        })
            .done(function (response) {
                if (!response || !response.success) {
                    table.settings()[0].oLanguage.sEmptyTable = "Unable to load leads.";
                    table.clear().draw();
                    updateStatusCounts();
                    if (typeof window.showToast === "function") {
                        window.showToast("danger", response && response.message ? response.message : "Unable to load leads.");
                    }
                    return;
                }

                var rows = Array.isArray(response.data) ? response.data : [];
                scopeRows = rows;
                table.settings()[0].oLanguage.sEmptyTable = "No leads found.";
                table.clear();
                table.rows.add(rows);

                populateFilter(sourceFilter, sourceColumnIndex);
                if (selectedSource) {
                    addOptionIfMissing(sourceFilter, selectedSource, selectedSource);
                    sourceFilter.val(selectedSource);
                    pendingSourceFilter = "";
                }
                applyColumnFilters();
            })
            .fail(function (xhr, textStatus) {
                if (textStatus === "abort") return;
                updateStatusCounts();
                var message = "Unable to load leads.";
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    message = xhr.responseJSON.message;
                }
                table.settings()[0].oLanguage.sEmptyTable = message;
                table.clear().draw();
                if (typeof window.showToast === "function") {
                    window.showToast("danger", message);
                }
            });
    }

    function populateFilter(selectEl, columnIndex) {
        selectEl.find('option:not(:first)').remove(); // Clear existing options (keep the first "all" option)
        var uniqueValues = table.column(columnIndex).data().unique().sort().toArray();

        $.each(uniqueValues, function (_, value) {
            var cleanedValue = $("<div>").html(value).text().trim();
            if (cleanedValue !== "") {
                selectEl.append(
                    $("<option>", {
                        value: cleanedValue,
                        text: cleanedValue,
                    })
                );
            }
        });
    }

    function populateStatusFilter() {
        statusFilter.find("option:not(:first)").remove();
        $.each(leadStatusLabels, function (status, label) {
            statusFilter.append(
                $("<option>", {
                    value: status,
                    text: label,
                })
            );
        });
    }

    function getStatusDropdownHtml(id, status) {
        var statusLabel = formatStatusLabel(status);
        return (
            '<div class="btn-group" data-id="' + id + '">' +
            '<button type="button" class="btn btn-sm dropdown-toggle lead-status-btn lead-status-' + status + '" data-bs-toggle="dropdown" aria-expanded="false" data-status="' + status + '">' +
            statusLabel +
            "</button>" +
            '<ul class="dropdown-menu">' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="open">Open</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="interested">Interested</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="connected">Connected</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="converted">Converted</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="not_interested">Not Interested</a></li>' +
            '<li><a class="dropdown-item change-status" href="javascript:void(0);" data-status="not_connected">Not Connected</a></li>' +
            "</ul>" +
            "</div>"
        );
    }

    function addOptionIfMissing(selectEl, value, text) {
        if (!value) return;
        if (selectEl.find('option[value="' + value.replace(/"/g, '\\"') + '"]').length === 0) {
            selectEl.append(
                $("<option>", {
                    value: value,
                    text: text || value,
                })
            );
        }
    }

    function columnExactSearch(value) {
        return value ? "^" + $.fn.dataTable.util.escapeRegex(value) + "$" : "";
    }

    function applyColumnFilters() {
        table.column(sourceColumnIndex).search(columnExactSearch(sourceFilter.val()), true, false);
        table.column(statusColumnIndex).search(columnExactSearch(statusFilter.val()), true, false);
        table.draw();
        updateStatusCounts();
    }

    // "All" card first (status "" = no Status filter), then one per status.
    function renderStatusCards() {
        var cards = [{ status: "", key: "all", label: "All" }];
        $.each(leadStatusLabels, function (status, label) {
            cards.push({ status: status, key: status, label: label });
        });

        var html = "";
        $.each(cards, function (_, card) {
            html +=
                '<div class="col">' +
                '<div class="card custom-card mb-0 lead-status-card" data-status="' + card.status + '" title="Show ' + escHtml(card.label) + ' leads">' +
                '<div class="card-body p-3">' +
                '<div class="d-flex align-items-baseline gap-2">' +
                '<span class="fs-20 fw-semibold" data-status-count="' + card.key + '">—</span>' +
                '<span class="text-muted fs-12" data-status-percent="' + card.key + '"></span>' +
                "</div>" +
                '<span class="badge ' + (card.status ? "lead-status-" + card.status : "bg-primary-transparent") + '">' + escHtml(card.label) + "</span>" +
                "</div>" +
                "</div>" +
                "</div>";
        });
        statusCards.html(html);
    }

    // Counted from the loaded scope + Source filter, but deliberately NOT the
    // Status filter -- selecting a status narrows the table while the cards
    // keep the full breakdown so the user can switch between statuses.
    // Percentages are against the "All" total of that same scope.
    function updateStatusCounts() {
        var source = sourceFilter.val();
        var counts = { all: 0 };
        $.each(scopeRows, function (_, row) {
            if (source && row.source !== source) return;
            counts[row.status] = (counts[row.status] || 0) + 1;
            counts.all++;
        });

        statusCards.find("[data-status-count]").each(function () {
            $(this).text(counts[$(this).attr("data-status-count")] || 0);
        });
        statusCards.find("[data-status-percent]").each(function () {
            var count = counts[$(this).attr("data-status-percent")] || 0;
            var percent = counts.all ? Math.round((count / counts.all) * 1000) / 10 : 0;
            $(this).text(percent + "%");
        });
        statusCards.find(".lead-status-card").each(function () {
            $(this).toggleClass("active", $(this).attr("data-status") === (statusFilter.val() || ""));
        });
    }

    statusCards.on("click", ".lead-status-card", function () {
        var status = $(this).attr("data-status");
        statusFilter.val(statusFilter.val() === status ? "" : status);
        applyColumnFilters();
    });

    sourceFilter.on("change", applyColumnFilters);

    statusFilter.on("change", applyColumnFilters);

    $(document).on("click", ".change-status", function (event) {
        event.preventDefault();
        var status = $(this).data("status");
        var parentGroup = $(this).closest(".btn-group");
        var leadId = parentGroup.data("id");

        if (!leadId || !status) return;

        if (status === "converted" || status === "not_interested") {
            openLeadStatusModal(leadId, status);
            return;
        }

        $.ajax({
            url: updateLeadStatusApiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: leadId, status: status }),
        })
            .done(function (res) {
                if (res && res.success) {
                    var button = parentGroup.find(".lead-status-btn");
                    var label = formatStatusLabel(status);
                    button
                        .text(label)
                        .removeClass("lead-status-open lead-status-interested lead-status-connected lead-status-converted lead-status-not_interested lead-status-not_connected")
                        .addClass("lead-status-btn lead-status-" + status)
                        .attr("data-status", status);
                    window.showToast("success", "Status updated");
                    loadLeads();
                }
            })
            .fail(function (xhr) {
                console.log("FAILED", xhr.status, xhr.responseText);
            });
    });

    $("#leads-datatable_wrapper .dataTables_length select").addClass("form-select form-select-sm");

    $(".export-btn").on("click", function () {
        var type = $(this).data("type");
        if (type === "csv") table.button(".buttons-csv").trigger();
        if (type === "pdf") table.button(".buttons-pdf").trigger();
    });

    $("#tableSearch").on("keyup", function () {
        table.search(this.value).draw();
    });

    table
        .on("order.dt search.dt draw.dt", function () {
            var info = table.page.info();
            table.column(0, { search: "applied", order: "applied", page: "current" })
                .nodes()
                .each(function (cell, index) {
                    cell.innerHTML = info.start + index + 1;
                });
        })
        .draw();

    // ---------- Add / Edit Lead Form ----------
    var addLeadForm = $("#addLeadForm");
    var submitButton = $("#addLeadSubmitBtn");
    var submitSpinner = $("#addLeadSubmitSpinner");
    var submitText = $("#addLeadSubmitText");

    addLeadForm.on("submit", function (event) {
        event.preventDefault();
        event.stopPropagation();
        var formEl = this;

        // Normalize a pasted "+<code><number>" that never lost focus (e.g.
        // paste then hit Enter) before running native validation on it.
        autoDetectPhoneCountry();

        formEl.classList.add("was-validated");

        if (!formEl.checkValidity()) {
            if (typeof window.showToast === "function") window.showToast("warning", "Please fill all required fields");
            return;
        }

        var isEdit = $("#leadId").val() !== "";
        var apiUrl = isEdit ? updateLeadApiUrl : addLeadApiUrl;
        var successMessage = isEdit ? "Lead updated successfully" : "Lead added successfully";
        var failMessage = isEdit ? "Failed to update lead" : "Failed to add lead";

        var payload = {
            fullName: $.trim($("#modal-fullName").val()),
            email: $.trim($("#modal-email").val()),
            phone: $.trim($("#modal-phone").val()),
            country: $.trim($("#modal-country").val()),
            countryCode: $.trim($("#modal-countryCode").val()),
            source: $.trim($("#modal-source").val()),
            orgName: $.trim($("#modal-orgName").val()),
            categoryId: parseInt($("#modal-categoryId").val() || 0),
            planId: parseInt($("#modal-planId").val() || 0),
        };
        if (isEdit) payload.id = $("#leadId").val();

        submitButton.prop("disabled", true);
        submitSpinner.removeClass("d-none");
        submitText.text(isEdit ? "Updating..." : "Saving...");

        $.ajax({
            url: apiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify(payload),
        })
            .done(function (response) {
                if (response && response.success) {
                    var lead = response.data || {};
                    var leadId = lead.id || payload.id || 0;
                    var statusValue = lead.status || "open";
                    var createdDate = lead.createdDate || "";

                    if (isEdit) {
                        var row = table.row(function (idx, data, node) {
                            return $(node).find(".edit-lead-btn").data("id") == leadId;
                        });
                        if (row.any()) {
                            var currentData = row.data();
                            var updatedRowData = {
                                ...currentData,
                                id: leadId,
                                fullName: lead.fullName || payload.fullName,
                                email: lead.email || payload.email,
                                phone: lead.phone || payload.phone,
                                country: lead.country || payload.country,
                                countryCode: lead.countryCode || payload.countryCode,
                                source: lead.source || payload.source,
                                orgName: lead.orgName || payload.orgName,
                                categoryId: lead.categoryId || payload.categoryId,
                                planId: lead.planId || payload.planId,
                                status: statusValue,
                                employeeName: lead.employeeName || currentData.employeeName || '',
                                categoryName: lead.categoryName || currentData.categoryName || '',
                                planName: lead.planName || currentData.planName || ''
                            };
                            row.data(updatedRowData).draw(false);
                        }
                    } else {
                        var newLeadObject = {
                            id: leadId,
                            fullName: lead.fullName || payload.fullName,
                            email: lead.email || payload.email,
                            phone: lead.phone || payload.phone,
                            country: lead.country || payload.country,
                            countryCode: lead.countryCode || payload.countryCode,
                            source: lead.source || payload.source,
                            orgName: lead.orgName || payload.orgName || '-',
                            categoryId: lead.categoryId || payload.categoryId || 0,
                            planId: lead.planId || payload.planId || 0,
                            status: statusValue,
                            createdAt: createdDate,
                            employeeName: lead.employeeName || '',
                            categoryName: lead.categoryName || '',
                            planName: lead.planName || ''
                        };
                        table.row.add(newLeadObject).draw(false);
                    }

                    addOptionIfMissing(sourceFilter, lead.source || payload.source, lead.orgName || payload.orgName);
                    addOptionIfMissing(statusFilter, statusValue, formatStatusLabel(statusValue));

                    formEl.reset();
                    formEl.classList.remove("was-validated");
                    $("#addLeadModal").modal("hide");
                    loadLeads();
                    if (typeof window.showToast === "function") window.showToast("success", response.message || successMessage);
                } else {
                    if (typeof window.showToast === "function") window.showToast("danger", response && response.message ? response.message : failMessage);
                }
            })
            .fail(function (xhr) {
                var message = failMessage;
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                if (typeof window.showToast === "function") window.showToast("danger", message);
            })
            .always(function () {
                submitButton.prop("disabled", false);
                submitSpinner.addClass("d-none");
                submitText.text(isEdit ? "Update Lead" : "Save Lead");
            });
    });

    // Edit Lead button
    $("#leads-datatable").on("click", ".edit-lead-btn", function () {
        var btn = $(this);
        $("#leadId").val(btn.data("id"));
        $("#modal-fullName").val(btn.data("fullname"));
        $("#modal-email").val(btn.data("email"));
        setLeadCountryField(btn.data("country") || "");
        $("#modal-phone").val(btn.data("phone"));
        $("#modal-source").val(btn.data("source"));
        $("#modal-orgName").val(btn.data("orgname"));
        $("#modal-categoryId").val(btn.data("categoryid")).trigger("change");
        setTimeout(function () {
            $("#modal-planId").val(btn.data("planid"));
        }, 100);
        $("#addLeadModalLabel").text("Edit Lead");
        $("#addLeadSubmitText").text("Update Lead");
        $("#addLeadModal").modal("show");
    });

    // Delete Lead
    $("#leads-datatable").on("click", ".delete-lead-btn", function () {
        var id = $(this).data("id");
        var modal = $("#deleteConfirmModal");
        var effect = modal.data("bs-effect");
        if (effect) modal.addClass(effect);
        modal.data("deleteId", id).modal("show");
    });

    $("#deleteConfirmModal").on("hidden.bs.modal", function () {
        var modal = $(this);
        var effect = modal.data("bs-effect");
        if (effect) modal.removeClass(effect);
    });

    $("#addLeadModal").on("show.bs.modal", function () {
        var modal = $(this);
        var effect = modal.data("bs-effect");
        if (effect) modal.addClass(effect);
        if ($("#leadId").val() === "") {
            $("#addLeadForm")[0].reset();
            $("#addLeadForm")[0].classList.remove("was-validated");
            setLeadCountryField("");
            $("#addLeadModalLabel").text("Add Lead");
            $("#addLeadSubmitText").text("Save Lead");
        }
    });

    $("#addLeadModal").on("hidden.bs.modal", function () {
        var modal = $(this);
        var effect = modal.data("bs-effect");
        if (effect) modal.removeClass(effect);
        $("#leadId").val("");
    });

    $("#confirmDeleteBtn").on("click", function () {
        var id = $("#deleteConfirmModal").data("deleteId");
        $("#deleteConfirmModal").modal("hide");
        $.ajax({
            url: deleteLeadApiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: id }),
        })
            .done(function (response) {
                if (response && response.success) {
                    var tr = $('.delete-lead-btn[data-id="' + id + '"]').closest("tr");
                    table.row(tr).remove().draw(false);
                    if (typeof window.showToast === "function") window.showToast("success", response.message || "Lead deleted successfully");
                } else {
                    if (typeof window.showToast === "function") window.showToast("danger", response && response.message ? response.message : "Failed to delete lead");
                }
            })
            .fail(function (xhr) {
                var message = "Failed to delete lead";
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                if (typeof window.showToast === "function") window.showToast("danger", message);
            });
    });

    // Remarks
    $("#leads-datatable").on("click", ".remark-btn", function () {
        var leadId = $(this).data("id");
        var leadName = $(this).data("name");
        $("#remarkLeadId").val(leadId);
        $("#remarkLeadName").text(leadName);
        $("#leadRemark").val("");
        loadLeadRemarks(leadId);
        $("#leadRemarkModal").modal("show");
    });

    function loadLeadRemarks(leadId) {
        $.getJSON(getLeadRemarksApiUrl, { leadId: leadId }, function (response) {
            let html = "";
            if (response.success && response.data.length) {
                response.data.forEach(function (item) {
                    html += `
                        <div class="border rounded p-3 mb-2">
                            <div class="small text-muted">${item.createdAt}</div>
                            <div class="mt-2">${item.remark}</div>
                            ${item.followUpDateTime ? `<div class="text-primary mt-2">Follow Up : ${item.followUpDateTime}</div>` : ""}
                            <div class="small text-muted mt-2">By ${item.employeeName}</div>
                        </div>`;
                });
            }
            $("#remarkTimeline").html(html);
        });
    }

    $(document).on("click", "#saveRemarkBtn", function () {
        $.ajax({
            url: saveLeadRemarkApiUrl,
            type: "POST",
            dataType: "json",
            data: {
                leadId: $("#remarkLeadId").val(),
                remark: $("#leadRemark").val(),
                followUpDateTime: $("#followUpDateTime").val(),
            },
            success: function (response) {
                if (response.success) {
                    $("#leadRemark").val("");
                    loadLeadRemarks($("#remarkLeadId").val());
                    window.showToast("success", "Remark saved");
                }
            },
        });
    });

    // Master data
    function loadLeadMasterData() {
        $.getJSON(API_BASE + "/leads/getLeadMasterData.php", function (response) {
            if (!response.success) return;
            leadCategories = response.data.categories || [];
            leadPlans = response.data.plans || [];
            populateCategoryDropdown();
        });
    }

    function populateCategoryDropdown() {
        let html = '<option value="">Select Category</option>';
        leadCategories.forEach(function (cat) {
            html += `<option value="${cat.id}">${cat.categoryName}</option>`;
        });
        $("#modal-categoryId").html(html);
    }

    $(document).on("change", "#modal-categoryId", function () {
        let categoryId = $(this).val();
        let html = '<option value="">Select Plan</option>';
        leadPlans.forEach(function (plan) {
            if (String(plan.categoryId) === String(categoryId)) {
                html += `<option value="${plan.id}">${plan.planName}</option>`;
            }
        });
        $("#modal-planId").html(html);
    });

    // Scheduled Calls
    $(document).on("click", "#scheduledCallsBtn", function () {
        var today = new Date().toISOString().split("T")[0];
        $("#scheduledCallsDate").val(today);
        loadScheduledCalls(today);
        $("#scheduledCallsModal").modal("show");
    });

    $(document).on("change", "#scheduledCallsDate", function () {
        loadScheduledCalls($(this).val());
    });

    function loadScheduledCalls(date) {
        $.ajax({
            url: getScheduledCallsApiUrl,
            type: "GET",
            dataType: "json",
            data: { date: date },
            success: function (response) {
                var html = "";
                if (!response.success || !response.data.length) {
                    html = `<tr><td colspan="7" class="text-center text-muted py-4">No follow-ups scheduled for this date.</td></tr>`;
                    $("#scheduledCallsTableBody").html(html);
                    return;
                }
                response.data.forEach(function (item, index) {
                    html += `
                        <tr>
                            <td>${index + 1}</td>
                            <td>${item.followUpTime}</td>
                            <td>${item.leadName}</td>
                            <td>${item.phone}</td>
                            <td>${item.employeeName}</td>
                            <td>
                                <select class="form-select form-select-sm followup-status" style="min-width:110px;" data-id="${item.leadId}">
                                    <option value="open" ${item.status.toLowerCase() === "open" ? "selected" : ""}>Open</option>
                                    <option value="close" ${item.status.toLowerCase() === "close" ? "selected" : ""}>Close</option>
                                </select>
                            </td>
                            <td>${item.remark}</td>
                        </tr>`;
                });
                $("#scheduledCallsTableBody").html(html);
            },
        });
    }

    // Follow Ups (leadFollowUps, generated by LeadFollowUpEngine from Follow
    // Up Setup rules). Date-wise by dueDate -- a lead can show multiple rows,
    // one per generated occurrence. Replaces the separate Follow Up List page.
    function fmtFollowUpDate(dateStr) {
        var d = new Date(dateStr + "T00:00:00");
        if (isNaN(d.getTime())) return dateStr;
        return d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
    }

    function resetFollowUpFilters() {
        var today = new Date().toISOString().split("T")[0];
        $("#lfuDateFrom").val(today);
        $("#lfuDateTo").val(today);
        $("#lfuLeadStatus").val("");
    }

    $(document).on("click", "#leadFollowUpsBtn", function () {
        resetFollowUpFilters();
        loadFollowUps();
        $("#leadFollowUpsModal").modal("show");
    });

    $(document).on("change", "#lfuDateFrom, #lfuDateTo, #lfuLeadStatus", loadFollowUps);

    $(document).on("click", "#lfuResetBtn", function () {
        resetFollowUpFilters();
        loadFollowUps();
    });

    function loadFollowUps() {
        $("#leadFollowUpsTableBody").html('<tr><td colspan="5" class="text-center text-muted py-3">Loading...</td></tr>');

        $.getJSON(getLeadFollowUpsApiUrl, {
            status: "Pending",
            dateFrom: $("#lfuDateFrom").val(),
            dateTo: $("#lfuDateTo").val(),
            leadStatus: $("#lfuLeadStatus").val(),
        }, function (response) {
            if (!response.success || !response.data.length) {
                $("#leadFollowUpsTableBody").html(
                    '<tr><td colspan="5" class="text-center text-muted py-3">No follow ups match the current filters.</td></tr>'
                );
                return;
            }

            var html = "";
            response.data.forEach(function (row) {
                html += "<tr>" +
                    "<td>" + fmtFollowUpDate(row.dueDate) + "</td>" +
                    "<td>" + escHtml(row.leadName) + '<small class="d-block text-muted">' + escHtml(row.phone) + "</small></td>" +
                    "<td>Day " + row.followUpSequence + " &middot; " + escHtml(row.followUpType) + "</td>" +
                    '<td><span class="btn btn-sm btn-outline-warning">' + escHtml(row.status) + "</span></td>" +
                    "<td>" +
                        '<button type="button" class="btn btn-sm btn-outline-success mark-followup-btn" data-id="' + row.id + '" data-status="Completed" title="Mark Complete"><i class="ri-check-line"></i></button> ' +
                        '<button type="button" class="btn btn-sm btn-outline-secondary mark-followup-btn" data-id="' + row.id + '" data-status="Skipped" title="Skip"><i class="ri-close-line"></i></button> ' +
                        '<button type="button" class="btn btn-sm btn-outline-primary followup-note-btn" data-id="' + row.leadId + '" data-name="' + escHtml(row.leadName) + '" title="Add Note"><i class="ri-sticky-note-line"></i></button>' +
                    "</td>" +
                "</tr>";
            });
            $("#leadFollowUpsTableBody").html(html);
        });
    }

    $(document).on("click", ".mark-followup-btn", function () {
        var id = $(this).data("id");
        var status = $(this).data("status");
        if (!confirm((status === "Completed" ? "Mark this follow up as completed?" : "Skip this follow up?"))) return;

        $.ajax({
            url: updateLeadFollowUpStatusApiUrl,
            type: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: id, status: status }),
        }).done(function (response) {
            if (response.success) {
                showToast("success", response.message || "Follow up updated.");
                loadFollowUps();
            } else {
                showToast("error", response.message || "Failed to update follow up.");
            }
        }).fail(function () {
            showToast("error", "Failed to update follow up.");
        });
    });

    $(document).on("click", ".followup-note-btn", function () {
        $("#remarkLeadId").val($(this).data("id"));
        $("#remarkLeadName").text($(this).data("name"));
        $("#leadRemark").val("");
        loadLeadRemarks($(this).data("id"));
        $("#leadFollowUpsModal").modal("hide");
        $("#leadRemarkModal").modal("show");
    });

    // Documents
    $(document).on("click", ".document-btn", function () {
        var leadId = $(this).data("id");
        var leadName = $(this).data("name");
        $("#documentLeadId").val(leadId);
        $("#documentLeadName").text(leadName);
        $("#leadDocumentFile").val("");
        loadLeadDocuments(leadId);
        $("#leadDocumentsModal").modal("show");
    });

    function loadLeadDocuments(leadId) {
        $.ajax({
            url: getLeadDocumentsApiUrl,
            type: "GET",
            dataType: "json",
            data: { leadId: leadId },
            success: function (response) {
                var html = "";
                if (!response.success || !response.data.length) {
                    html = `<div class="text-center text-muted py-4">No documents uploaded.</div>`;
                    $("#leadDocumentsContainer").html(html);
                    return;
                }
                response.data.forEach(function (doc) {
                    html += `
                        <div class="border rounded p-3 mb-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold"><i class="ri-file-pdf-line text-danger me-1"></i>${doc.fileName}</div>
                                    <div class="small text-muted mt-1">Uploaded By : ${doc.employeeName}</div>
                                    <div class="small text-muted">${doc.uploadedAt}</div>
                                </div>
                                <div>
                                    <a href="${doc.viewUrl}" target="_blank" class="btn btn-sm btn-outline-primary me-1">View</a>
                                    <a href="${doc.downloadUrl}" target="_blank" download class="btn btn-sm btn-outline-success">Download</a>
                                </div>
                            </div>
                        </div>`;
                });
                $("#leadDocumentsContainer").html(html);
            },
        });
    }

    $(document).on("click", "#uploadLeadDocumentBtn", function () {
        var leadId = $("#documentLeadId").val();
        var file = $("#leadDocumentFile")[0].files[0];
        if (!file) {
            showToast("warning", "Please select a PDF.");
            return;
        }
        var formData = new FormData();
        formData.append("leadId", leadId);
        formData.append("document", file);
        $.ajax({
            url: uploadLeadDocumentApiUrl,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                showToast("success", response.message);
                $("#leadDocumentFile").val("");
                loadLeadDocuments(leadId);
            },
            error: function () {
                showToast("error", "Upload failed.");
            },
        });
    });

    // Follow-up close
    $(document).on("change", ".followup-status", function () {
        let status = $(this).val();
        let leadId = $(this).data("id");
        if (status === "close") {
            $("#followupLeadId").val(leadId);
            $("#followupCloseRemark").val("");
            $("#followupRemarkModal").modal("show");
        }
    });

    $(document).on("click", "#saveFollowupRemarkBtn", function () {
        let leadId = $("#followupLeadId").val();
        let remark = $("#followupCloseRemark").val().trim();
        if (remark === "") {
            showToast("warning", "Please enter remark");
            return;
        }
        $.ajax({
            url: API_BASE + "/leads/closeFollowup.php",
            type: "POST",
            dataType: "json",
            data: { leadId: leadId, remark: remark },
            success: function (response) {
                if (response.success) {
                    $("#followupRemarkModal").modal("hide");
                    showToast("success", "Follow up closed");
                    loadScheduledCalls($("#scheduledCallsDate").val());
                }
            }
        });
    });

    // Status change modal
    function openLeadStatusModal(leadId, status) {
        $("#statusLeadId").val(leadId);
        $("#selectedLeadStatus").val(status);
        $("#selectedStatusText").text(formatStatusLabel(status));
        $("#statusRemark").val("");
        $("#finalPrice").val("");
        $("#nextPriceIncrementDate").val("");
        $("#quotationDocument").val("");
        if (status === "converted") {
            $("#convertedFields").show();
        } else {
            $("#convertedFields").hide();
        }
        $("#leadStatusModal").modal("show");
    }

    $(document).on("click", "#saveLeadStatusBtn", function () {
        var leadId = $("#statusLeadId").val();
        var status = $("#selectedLeadStatus").val();
        var remark = $("#statusRemark").val().trim();
        if (remark === "") {
            showToast("warning", "Please enter remark.");
            return;
        }
        if (status === "converted") {
            saveConvertedLead(leadId, status, remark);
        } else {
            saveNotInterestedLead(leadId, status, remark);
        }
    });

    function saveNotInterestedLead(leadId, status, remark) {
        $.ajax({
            url: API_BASE + "/leads/saveLeadStatusRemark.php",
            type: "POST",
            dataType: "json",
            contentType: "application/json",
            data: JSON.stringify({ leadId: leadId, status: status, remark: remark }),
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                updateLeadStatus(leadId, status);
            })
            .fail(function () {
                showToast("error", "Failed to save remark.");
            });
    }

    function saveConvertedLead(leadId, status, remark) {
        var finalPrice = $("#finalPrice").val();
        if (finalPrice === "") {
            showToast("warning", "Please enter final price.");
            return;
        }
        var formData = new FormData();
        formData.append("leadId", leadId);
        formData.append("statusRemark", remark);
        formData.append("finalPrice", finalPrice);
        formData.append("nextPriceIncrementDate", $("#nextPriceIncrementDate").val());
        if ($("#quotationDocument")[0].files.length) {
            formData.append("quotationDocument", $("#quotationDocument")[0].files[0]);
        }
        $.ajax({
            url: API_BASE + "/leads/saveLeadConversion.php",
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                updateLeadStatus(leadId, status);
            })
            .fail(function () {
                showToast("error", "Failed to save conversion.");
            });
    }

    function updateLeadStatus(leadId, status) {
        $.ajax({
            url: updateLeadStatusApiUrl,
            method: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: leadId, status: status }),
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message);
                    return;
                }
                $("#leadStatusModal").modal("hide");
                showToast("success", response.message || "Lead status updated successfully.");
                loadLeads();
            })
            .fail(function () {
                showToast("error", "Status update failed.");
            });
    }

    // Import Leads
    $(document).on("submit", "#importLeadForm", function (event) {
        event.preventDefault();
        event.stopPropagation();
        console.log("Import form submitted");
        var employeeId = $("#importEmployeeId").val();
        var fileInput = $("#leadCsvFile")[0];
        var file = fileInput && fileInput.files.length ? fileInput.files[0] : null;
        if (!employeeId) {
            showToast("warning", "Please select employee.");
            return;
        }
        if (!file) {
            showToast("warning", "Please upload CSV file.");
            return;
        }
        var formData = new FormData();
        formData.append("employeeId", employeeId);
        formData.append("leadCsvFile", file);
        $("#importLeadSubmitBtn").prop("disabled", true);
        $("#importLeadSpinner").removeClass("d-none");
        $.ajax({
            url: importLeadsApiUrl,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            timeout: 60000
        })
            .done(function (response) {
                if (!response.success) {
                    showToast("error", response.message || "Import failed.");
                    return;
                }
                showToast("success", response.message || "Leads imported successfully.");
                $("#importLeadModal").modal("hide");
                $("#importLeadForm")[0].reset();
                location.reload();
            })
            .fail(function (xhr, status, error) {
                var message = "Import failed.";
                if (status === "timeout") message = "Import request timed out.";
                if (xhr.responseJSON && xhr.responseJSON.message) message = xhr.responseJSON.message;
                showToast("error", message);
            })
            .always(function () {
                $("#importLeadSubmitBtn").prop("disabled", false);
                $("#importLeadSpinner").addClass("d-none");
            });
    });

    $("#importLeadModal").on("show.bs.modal hidden.bs.modal", function () {
        $("#importLeadSubmitBtn").prop("disabled", false);
        $("#importLeadSpinner").addClass("d-none");
    });

    // Deep link from Follow Up List ("Open Lead") -- /leads?leadId=123 opens
    // that lead's existing Remarks modal directly, reusing loadLeadRemarks()
    // above instead of a separate lead-detail view.
    (function openLeadFromQueryString() {
        var params = new URLSearchParams(window.location.search);
        var leadId = params.get("leadId");
        if (!leadId) return;

        $("#remarkLeadId").val(leadId);
        $("#remarkLeadName").text(params.get("leadName") || "");
        $("#leadRemark").val("");
        loadLeadRemarks(leadId);
        $("#leadRemarkModal").modal("show");
    })();

});
