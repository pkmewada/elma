/* ==========================================================================
   Lead Activity — DataTable init for #activityTable (pages/lead-activity.php).
   Rows are server-rendered by that page; this only wires up paging/sorting.
   ========================================================================== */
$(function () {
    var table = $("#activityTable").DataTable(
        window.ModlusUI.withDataTableDefaults({
            pageLength: 20,
            order: [[0, "desc"]],
            dom: "t<'row mt-3'<'col-md-5'i><'col-md-7'p>>",
            columnDefs: [
                { orderable: false, targets: [2, 4, 5] },
                { width: "130px", targets: 0 },
                { width: "120px", targets: 1 },
                { width: "100px", targets: 2 },
                { width: "160px", targets: 3 },
                { width: "200px", targets: 4 },
                { width: "100px", targets: 5 }
            ],
            language: {
                emptyTable: "No activity logs found"
            }
        })
    );

    $("#activityTableSearch").on("keyup", function () {
        table.search(this.value).draw();
    });
});
