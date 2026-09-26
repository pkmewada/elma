
    (function () {
        const defaults = {
            dir: "ltr",
            themeMode: "light",
            navLayout: "vertical",
            verticalStyle: "doublemenu",
            pageStyle: "regular",
            width: "default",
            menuPosition: "fixed",
            menuStyles: "light",
            headerStyles: "transparent",
            loader: "enable",
            toggled: "close"
        };
       const bootstrapCssHref =
            window.location.origin +
            "/assets/libs/bootstrap/css/bootstrap.min.css";
        var storageDefaults = {
            mamixltr: "true",
            mamixverticalstyles: "doublemenu",
            mamixregular: "true",
            mamixdefaultwidth: "true",
            mamixmenufixed: "true",
            mamixMenu: "light",
            mamixHeader: "transparent",
            loaderEnable: "true"
        };
        var storageKeysToRemove = [
            "mamixdarktheme",
            "mamixrtl",
            "mamixlayout",
            "mamixnavstyles",
            "mamixboxed",
            "mamixfullwidth",
            "mamixheaderscrollable",
            "mamixheaderfixed",
            "mamixmenuscrollable",
            "mamixclassic",
            "mamixmodern",
            "bodyBgRGB",
            "bodylightRGB",
            "primaryRGB",
            "bgimg",
            "mamixlighttheme",
            "mamixbgColor",
            "mamixheaderbg",
            "mamixbgwhite",
            "mamixmenubg"
        ];
        var controlIdsToCheck = [
            "switcher-light-theme",
            "switcher-ltr",
            "switcher-vertical",
            "switcher-double-menu",
            "switcher-regular",
            "switcher-default-width",
            "switcher-menu-fixed",
            "switcher-loader-enable",
            "switcher-menu-light",
            "switcher-header-transparent"
        ];
        var controlIdsToUncheck = [
            "switcher-dark-theme",
            "switcher-rtl",
            "switcher-horizontal",
            "switcher-default-menu",
            "switcher-closed-menu",
            "switcher-icontext-menu",
            "switcher-icon-overlay",
            "switcher-detached",
            "switcher-classic",
            "switcher-modern",
            "switcher-full-width",
            "switcher-boxed",
            "switcher-menu-scroll",
            "switcher-loader-disable",
            "switcher-menu-dark",
            "switcher-menu-primary",
            "switcher-menu-gradient",
            "switcher-menu-transparent",
            "switcher-header-light",
            "switcher-header-dark",
            "switcher-header-primary",
            "switcher-header-gradient"
        ];
        var themeStyleProperties = [
            "--body-bg-rgb",
            "--body-bg-rgb2",
            "--light-rgb",
            "--form-control-bg",
            "--gray-3",
            "--input-border",
            "--primary-rgb"
        ];

        function useStorage(callback) {
            ["localStorage", "sessionStorage"].forEach(function (storageName) {
                try {
                    var storage = window[storageName];
                    if (storage) {
                        callback(storage);
                    }
                } catch (error) {
                    // Ignore storage access errors and still apply DOM defaults.
                }
            });
        }

        function syncStorage() {
            useStorage(function (storage) {
                storageKeysToRemove.forEach(function (key) {
                    storage.removeItem(key);
                });

                Object.keys(storageDefaults).forEach(function (key) {
                    storage.setItem(key, storageDefaults[key]);
                });
            });
        }

        function applyDefaults() {
            var html = document.documentElement;
            if (!html) {
                return;
            }

            html.setAttribute("dir", defaults.dir);
            html.setAttribute("data-theme-mode", defaults.themeMode);
            html.setAttribute("data-nav-layout", defaults.navLayout);
            html.setAttribute("data-vertical-style", defaults.verticalStyle);
            html.setAttribute("data-page-style", defaults.pageStyle);
            html.setAttribute("data-width", defaults.width);
            html.setAttribute("data-menu-position", defaults.menuPosition);
            html.setAttribute("data-menu-styles", defaults.menuStyles);
            html.setAttribute("data-header-styles", defaults.headerStyles);
            html.setAttribute("loader", defaults.loader);
            html.setAttribute("data-toggled", defaults.toggled);

            html.removeAttribute("data-nav-style");
            html.removeAttribute("data-bg-img");

            themeStyleProperties.forEach(function (propertyName) {
                html.style.removeProperty(propertyName);
            });

            var styleLink = document.getElementById("style");
            if (styleLink) {
                styleLink.setAttribute("href", bootstrapCssHref);
            }
        }

        function syncControls() {
            controlIdsToCheck.forEach(function (id) {
                var element = document.getElementById(id);
                if (element) {
                    element.checked = true;
                }
            });

            controlIdsToUncheck.forEach(function (id) {
                var element = document.getElementById(id);
                if (element) {
                    element.checked = false;
                }
            });

            var switcherCanvas = document.getElementById("switcher-canvas");
            if (switcherCanvas) {
                switcherCanvas.style.display = "none";
                switcherCanvas.classList.remove("show");
                switcherCanvas.setAttribute("aria-hidden", "true");
                switcherCanvas.querySelectorAll("input, button, a, select, textarea").forEach(function (element) {
                    element.disabled = true;
                    element.tabIndex = -1;
                });
            }

            document.querySelectorAll(".switcher-icon").forEach(function (element) {
                element.style.display = "none";
                element.setAttribute("aria-hidden", "true");
                element.tabIndex = -1;
            });
        }

        function blockThemeControlEvents(event) {
            if (!event.target || typeof event.target.closest !== "function") {
                return;
            }

            var blockedTarget = event.target.closest(".switcher-icon, #switcher-canvas, #switcher-canvas *");
            if (!blockedTarget) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            if (typeof event.stopImmediatePropagation === "function") {
                event.stopImmediatePropagation();
            }
        }

        function lockTheme() {
            syncStorage();
            applyDefaults();
            syncControls();
        }

        syncStorage();
        applyDefaults();

        document.addEventListener("click", blockThemeControlEvents, true);
        document.addEventListener("change", blockThemeControlEvents, true);
        document.addEventListener("DOMContentLoaded", lockTheme);
        window.addEventListener("load", lockTheme);

        window.MamixThemeLock = {
            apply: lockTheme,
            defaults: defaults
        };
    })();