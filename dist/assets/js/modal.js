(function () {
    "use strict";

    function initModalHandlers() {
        var exampleModal = document.getElementById('formmodal');
        if (exampleModal) {
            exampleModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget;
                if (!button) return;
                var recipient = button.getAttribute('data-bs-whatever') || '';
                var modalTitle = exampleModal.querySelector('.modal-title');
                var modalBodyInput = exampleModal.querySelector('.modal-body input');
                if (modalTitle) modalTitle.textContent = 'New message to ' + recipient;
                if (modalBodyInput) modalBodyInput.value = recipient;
            });
        }

        // Animated modals 
        /* showing modal effects */
        document.querySelectorAll(".modal-effect").forEach(e => {
            e.addEventListener('click', function (e) {
                e.preventDefault();
                let effect = this.getAttribute('data-bs-effect');
                const modalDemo = document.querySelector("#modaldemo8");
                if (modalDemo && effect) modalDemo.classList.add(effect);
            });
        });
        /* hide modal effects */
        const modalDemo = document.getElementById("modaldemo8");
        if (modalDemo) {
            modalDemo.addEventListener('hidden.bs.modal', function () {
                let removeClass = this.classList.value.match(/(^|\s)effect-\S+/g);
                if (removeClass && removeClass[0]) {
                    this.classList.remove(removeClass[0].trim());
                }
            });
        }
    // Animated modals 
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initModalHandlers);
    } else {
        initModalHandlers();
    }
})();
