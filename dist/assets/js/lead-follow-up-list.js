/* ==========================================================================
   Follow-ups (Today / Upcoming / Overdue / Completed) — admin
   /lead-follow-up-list and employee /emp-follow-ups. Data from
   api/leads/getLeadFollowUps.php, which applies the same lead scope as the
   lead APIs; completing goes through updateLeadFollowUpStatus.php.
   ========================================================================== */
$(function () {
    var listUrl = API_BASE + "/leads/getLeadFollowUps.php";
    var statusUrl = API_BASE + "/leads/updateLeadFollowUpStatus.php";
    var currentView = "today";
    var searchTimer = null;

    function esc(value) {
        return $("<div>").text(value == null ? "" : String(value)).html();
    }

    function toast(type, message) {
        if (typeof window.showToast === "function") window.showToast(type, message);
    }

    function formatDue(date, time) {
        var d = new Date(date + "T" + (time || "00:00:00"));
        if (isNaN(d.getTime())) return date;
        var day = d.toLocaleDateString("en-GB", { day: "2-digit", month: "short", year: "numeric" });
        return time ? day + " " + d.toLocaleTimeString("en-GB", { hour: "2-digit", minute: "2-digit", hour12: true }) : day;
    }

    var table = $("#followUpTable").DataTable(window.ModlusUI.withDataTableDefaults({
        data: [],
        ordering: false,
        pageLength: 25,
        dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
        language: { emptyTable: "No follow-ups in this view." },
        columns: [
            { data: null, render: function (d, t, row) { return esc(formatDue(row.dueDate, row.dueTime)); } },
            { data: null, render: function (d, t, row) {
                return '<a href="' + esc(FOLLOW_UP_LEADS_URL) + "?leadId=" + row.leadId + '">' + esc(row.leadName) + "</a>" +
                    '<small class="d-block text-muted">' + esc((row.countryCode || "") + " " + row.phone) + "</small>";
            } },
            { data: "projectName", render: function (d) { return d ? esc(d) : '<span class="text-muted">-</span>'; } },
            { data: "assignedToName", render: function (d) { return esc(d); } },
            { data: null, render: function (d, t, row) {
                return row.isManual ? esc(row.followUpType) + ' <small class="text-muted">by ' + esc(row.createdByName) + "</small>"
                    : "Rule #" + esc(row.followUpSequence) + " &middot; " + esc(row.followUpType);
            } },
            { data: "remark", render: function (d) { return d ? esc(d).replace(/\n/g, "<br>") : '<span class="text-muted">-</span>'; } },
            { data: null, render: function (d, t, row) {
                var cls = row.status === "Completed" ? "bg-success-transparent" : (row.status === "Skipped" ? "bg-secondary-transparent" : "bg-warning-transparent");
                return '<span class="badge ' + cls + '">' + esc(row.status) + "</span>" +
                    (row.completedByName ? '<small class="d-block text-muted">' + esc(row.completedByName) + "</small>" : "");
            } },
            { data: null, render: function (d, t, row) {
                var open = '<a href="' + esc(FOLLOW_UP_LEADS_URL) + "?leadId=" + row.leadId + '" class="btn btn-sm btn-outline-primary" title="Open lead"><i class="ri-external-link-line"></i></a>';
                if (row.status !== "Pending") return open;
                return '<button type="button" class="btn btn-sm btn-outline-success complete-followup-btn" data-id="' + row.id + '" data-status="Completed" data-lead="' + esc(row.leadName) + '" title="Complete"><i class="ri-check-line"></i></button> ' +
                    '<button type="button" class="btn btn-sm btn-outline-secondary complete-followup-btn" data-id="' + row.id + '" data-status="Skipped" data-lead="' + esc(row.leadName) + '" title="Skip"><i class="ri-close-line"></i></button> ' + open;
            } },
        ],
    }));

    function load() {
        $.getJSON(listUrl, { view: currentView, search: $.trim($("#followUpSearch").val()) })
            .done(function (response) {
                if (!response || !response.success) {
                    toast("danger", (response && response.message) || "Unable to load follow-ups.");
                    return;
                }
                table.clear().rows.add(response.data || []).draw();
                $.each(response.counts || {}, function (view, count) {
                    $('[data-count="' + view + '"]').text(count);
                });
            })
            .fail(function (xhr) {
                toast("danger", (xhr.responseJSON && xhr.responseJSON.message) || "Unable to load follow-ups.");
            });
    }

    $("#followUpViewTabs").on("click", "[data-view]", function () {
        $("#followUpViewTabs .nav-link").removeClass("active");
        $(this).addClass("active");
        currentView = $(this).data("view");
        load();
    });

    $("#followUpSearch").on("input", function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(load, 300);
    });

    $("#followUpTable").on("click", ".complete-followup-btn", function () {
        $("#completeFollowUpId").val($(this).data("id"));
        $("#completeFollowUpStatus").val($(this).data("status"));
        $("#completeFollowUpLead").text($(this).data("lead"));
        $("#completeFollowUpRemark").val("");
        $("#completeFollowUpModal .modal-title").text($(this).data("status") === "Completed" ? "Complete Follow-up" : "Skip Follow-up");
        $("#completeFollowUpModal").modal("show");
    });

    $("#saveCompleteFollowUpBtn").on("click", function () {
        $.ajax({
            url: statusUrl,
            type: "POST",
            contentType: "application/json",
            dataType: "json",
            data: JSON.stringify({ id: $("#completeFollowUpId").val(), status: $("#completeFollowUpStatus").val(), remark: $.trim($("#completeFollowUpRemark").val()) }),
        })
            .done(function (response) {
                if (!response || !response.success) {
                    toast("danger", (response && response.message) || "Failed to update follow-up.");
                    return;
                }
                $("#completeFollowUpModal").modal("hide");
                toast("success", response.message || "Follow-up updated.");
                load();
            })
            .fail(function (xhr) {
                toast("danger", (xhr.responseJSON && xhr.responseJSON.message) || "Failed to update follow-up.");
            });
    });

    load();
});
