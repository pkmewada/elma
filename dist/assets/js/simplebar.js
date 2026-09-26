(function () {
    "use strict";

    function initSidebarSimplebar() {
        var myElement = document.getElementById('sidebar-scroll');
        if (myElement && typeof SimpleBar !== "undefined") {
            new SimpleBar(myElement, { autoHide: true });
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSidebarSimplebar);
    } else {
        initSidebarSimplebar();
    }
    
})();
