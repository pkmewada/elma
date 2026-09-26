<footer class="footer mt-auto py-3 bg-white text-center">
    <div class="container">
        <span class="text-muted"> Copyright &copy; <span id="year"></span> <a
                href="javascript:void(0);" class="text-dark fw-medium">MQlus</a>.
            Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="https://mqlus.in" target="__blank">
                <span class="fw-medium text-primary">MQlus.in</span>
            </a> All rights reserved
        </span>
    </div>
</footer>
<!-- Footer End -->
<div class="modal fade" id="header-responsive-search" tabindex="-1" aria-labelledby="header-responsive-search" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <div class="input-group">
                    <input type="text" class="form-control border-end-0 central-route-search" placeholder="Search Anything ..."
                        aria-label="Search Anything ..." aria-describedby="button-addon2">
                    <button class="btn btn-primary central-route-search-submit" type="button"
                        id="button-addon2"><i class="bi bi-search"></i></button>
                </div>
                <div class="central-search-results" role="listbox"></div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Scroll To Top -->
<div class="scrollToTop">
    <span class="arrow lh-1"><i class="ti ti-caret-up fs-20"></i></span>
</div>
<div id="responsive-overlay"></div>
<!-- Scroll To Top -->

<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
    <div id="primaryToast" class="toast colored-toast bg-primary-transparent" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header bg-primary text-fixed-white">
            <img class="bd-placeholder-img rounded me-2" src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="Mamix">
            <strong class="me-auto">MQlus</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
    <div id="secondaryToast" class="toast colored-toast bg-secondary-transparent" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header bg-secondary text-fixed-white">
            <img class="bd-placeholder-img rounded me-2" src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="Mamix">
            <strong class="me-auto">MQlus</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
    <div id="warningToast" class="toast colored-toast bg-warning-transparent" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header bg-warning text-fixed-white">
            <img class="bd-placeholder-img rounded me-2" src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="Mamix">
            <strong class="me-auto">MQlus</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
    <div id="infoToast" class="toast colored-toast bg-info-transparent" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header bg-info text-fixed-white">
            <img class="bd-placeholder-img rounded me-2" src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="Mamix">
            <strong class="me-auto">MQlus</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
    <div id="successToast" class="toast colored-toast bg-success-transparent" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header bg-success text-fixed-white">
            <img class="bd-placeholder-img rounded me-2" src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="Mamix">
            <strong class="me-auto">MQlus</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
    <div id="dangerToast" class="toast colored-toast bg-danger-transparent" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
        <div class="toast-header bg-danger text-fixed-white">
            <img class="bd-placeholder-img rounded me-2" src="<?= ASSET_URL ?>/assets/images/brand-logos/toggle-dark.png" alt="Mamix">
            <strong class="me-auto">MQlus</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body"></div>
    </div>
</div>

<!-- Popper JS -->
<script src="<?= ASSET_URL ?>/assets/libs/@popperjs/core/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="<?= ASSET_URL ?>/assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Defaultmenu JS -->
<script src="<?= ASSET_URL ?>/assets/js/defaultmenu.min.js"></script>

<!-- Node Waves JS-->
<script src="<?= ASSET_URL ?>/assets/libs/node-waves/waves.min.js"></script>

<!-- Sticky JS -->
<script src="<?= ASSET_URL ?>/assets/js/sticky.js"></script>

<!-- Simplebar JS -->
<script src="<?= ASSET_URL ?>/assets/libs/simplebar/simplebar.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/js/simplebar.js"></script>

<!-- Auto Complete JS -->
<script src="<?= ASSET_URL ?>/assets/libs/@tarekraafat/autocomplete.js/autoComplete.min.js"></script>

<!-- Color Picker JS -->
<script src="<?= ASSET_URL ?>/assets/libs/@simonwep/pickr/pickr.es5.min.js"></script>

<!-- Date & Time Picker JS -->
<script src="<?= ASSET_URL ?>/assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/libs/filepond/filepond.min.js"></script>
<script src="<?= ASSET_URL ?>/assets/libs/quill/quill.min.js"></script>

<?php

$currentFooterPath = parse_url(
    $_SERVER['REQUEST_URI'] ?? '/',
    PHP_URL_PATH
) ?? '';

$basePath = parse_url(BASE_URL, PHP_URL_PATH) ?? '';
$basePath = rtrim($basePath, '/');

if ($basePath !== '' && strpos($currentFooterPath, $basePath) === 0) {

    $currentFooterPath = substr(
        $currentFooterPath,
        strlen($basePath)
    );
}

$currentFooterPath = '/' . ltrim($currentFooterPath, '/');
$currentFooterPath = rtrim($currentFooterPath, '/');

if (
    $currentFooterPath === '/dashboard' ||
    $currentFooterPath === ''
) :
?>
<!-- Apex Charts JS -->
<script src="<?= ASSET_URL ?>/assets/libs/apexcharts/apexcharts.min.js"></script>

<!-- Sales Dashboard -->
<script src="<?= ASSET_URL ?>/assets/js/sales-dashboard.js"></script>
<?php endif; ?>

<!-- Custom JS -->
<script src="<?= ASSET_URL ?>/assets/js/custom.js?v=theme-fix-1"></script>
<script src="<?= ASSET_URL ?>/assets/js/central-search.js?v=central-search-1"></script>


<script>
    window.showToast = function (type, message) {
        var allowedTypes = ['primary', 'secondary', 'warning', 'info', 'success', 'danger'];
        var selectedType = allowedTypes.indexOf(type) !== -1 ? type : 'info';
        var toastEl = document.getElementById(selectedType + 'Toast');

        if (!toastEl || typeof bootstrap === 'undefined') {
            return;
        }

        var toastBody = toastEl.querySelector('.toast-body');
        if (toastBody) {
            toastBody.innerText = message || '';
        }

        var toastInstance = bootstrap.Toast.getOrCreateInstance(toastEl);
        toastInstance.show();
    };

    window.ModlusUI = window.ModlusUI || {};

    window.ModlusUI.initFormValidation = function (scope) {
        var root = scope || document;
        var forms = root.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(function (form) {
            if (form.dataset.validationBound === '1') {
                return;
            }

            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);

            form.dataset.validationBound = '1';
        });
    };

    window.ModlusUI.initFileUploads = function (scope) {
        var root = scope || document;
        if (typeof FilePond === 'undefined') {
            return;
        }

        var uploadInputs = root.querySelectorAll('input[type="file"][data-ui-upload="filepond"]');
        Array.prototype.slice.call(uploadInputs).forEach(function (input) {
            if (input.dataset.filepondInitialized === '1') {
                return;
            }

            var allowMultiple = input.getAttribute('data-allow-multiple');
            FilePond.create(input, {
                allowMultiple: allowMultiple === null ? input.hasAttribute('multiple') : allowMultiple === 'true',
                credits: false
            });
            input.dataset.filepondInitialized = '1';
        });
    };

    window.ModlusUI.initEditors = function (scope) {
        var root = scope || document;
        if (typeof Quill === 'undefined') {
            return;
        }

        var editors = root.querySelectorAll('[data-ui-editor="quill"]');
        Array.prototype.slice.call(editors).forEach(function (editor) {
            if (editor.dataset.editorInitialized === '1') {
                return;
            }

            var placeholder = editor.getAttribute('data-placeholder') || 'Start writing...';
            if (!editor.id) {
                editor.id = 'quill-editor-' + Math.random().toString(36).slice(2, 10);
            }

            new Quill('#' + editor.id, {
                theme: 'snow',
                placeholder: placeholder
            });
            editor.dataset.editorInitialized = '1';
        });
    };

    window.ModlusUI.bindToastButtons = function (scope) {
        var root = scope || document;
        var toastButtons = root.querySelectorAll('[data-toast-type][data-toast-message]');
        Array.prototype.slice.call(toastButtons).forEach(function (button) {
            if (button.dataset.toastBound === '1') {
                return;
            }

            button.addEventListener('click', function () {
                window.showToast(button.getAttribute('data-toast-type'), button.getAttribute('data-toast-message'));
            });
            button.dataset.toastBound = '1';
        });

        var mappedButtons = [
            { id: 'primaryToastBtn', type: 'primary' },
            { id: 'secondaryToastBtn', type: 'secondary' },
            { id: 'warningToastBtn', type: 'warning' },
            { id: 'infoToastBtn', type: 'info' },
            { id: 'successToastBtn', type: 'success' },
            { id: 'dangerToastBtn', type: 'danger' }
        ];

        mappedButtons.forEach(function (item) {
            var button = root.querySelector('#' + item.id);
            if (!button || button.dataset.toastBound === '1') {
                return;
            }

            button.addEventListener('click', function () {
                var message = button.getAttribute('data-toast-message') || 'Your toast message here.';
                window.showToast(item.type, message);
            });
            button.dataset.toastBound = '1';
        });
    };

    window.ModlusUI.initTables = function (scope) {
        var root = scope || document;
        var tables = root.querySelectorAll('table[data-ui-table="mamix"]');
        Array.prototype.slice.call(tables).forEach(function (table) {
            if (table.dataset.tableStyled === '1') {
                return;
            }

            table.classList.add('table', 'table-hover', 'text-nowrap', 'align-middle');
            if (!table.classList.contains('mb-0')) {
                table.classList.add('mb-0');
            }

            table.dataset.tableStyled = '1';
        });
    };

    window.ModlusUI.init = function (scope) {
        window.ModlusUI.initTables(scope);
        window.ModlusUI.initFormValidation(scope);
        window.ModlusUI.initFileUploads(scope);
        window.ModlusUI.initEditors(scope);
        window.ModlusUI.bindToastButtons(scope);
    };

    document.addEventListener('DOMContentLoaded', function () {
        window.ModlusUI.init(document);
    });
</script>

<?php include __DIR__ . '/button-permission-client.php'; ?>

</body>

</html>
