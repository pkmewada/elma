(function () {
    "use strict";

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
        toggled: "open"
    };

    const bootstrapCssHref =
        "https://modlus.in/dist/assets/libs/bootstrap/css/bootstrap.min.css";

    const storageDefaults = {
        mamixltr: "true",
        mamixverticalstyles: "doublemenu",
        mamixregular: "true",
        mamixdefaultwidth: "true",
        mamixmenufixed: "true",
        mamixMenu: "light",
        mamixHeader: "transparent",
        loaderEnable: "true"
    };

    const storageKeysToRemove = [
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

    const controlIdsToCheck = [
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

    const controlIdsToUncheck = [
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

    const themeStyleProperties = [
        "--body-bg-rgb",
        "--body-bg-rgb2",
        "--light-rgb",
        "--form-control-bg",
        "--gray-3",
        "--input-border",
        "--primary-rgb"
    ];

    function useStorage(callback) {
        ["localStorage", "sessionStorage"].forEach((storageName) => {
            try {
                const storage = window[storageName];
                if (storage) callback(storage);
            } catch (e) {
                console.warn(`${storageName} unavailable`, e);
            }
        });
    }

    function syncStorage() {
        useStorage((storage) => {
            storageKeysToRemove.forEach((key) => {
                storage.removeItem(key);
            });

            Object.entries(storageDefaults).forEach(([key, value]) => {
                storage.setItem(key, value);
            });
        });
    }

    function applyDefaults() {
        const html = document.documentElement;
        if (!html) return;

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

        themeStyleProperties.forEach((property) => {
            html.style.removeProperty(property);
        });

        const styleLink = document.getElementById("style");
        if (styleLink && styleLink.href !== bootstrapCssHref) {
            styleLink.href = bootstrapCssHref;
        }
    }

    function syncControls() {
        controlIdsToCheck.forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.checked = true;
                element.disabled = true;
            }
        });

        controlIdsToUncheck.forEach((id) => {
            const element = document.getElementById(id);
            if (element) {
                element.checked = false;
                element.disabled = true;
            }
        });

        const switcherCanvas = document.getElementById("switcher-canvas");

        if (switcherCanvas) {
            switcherCanvas.style.display = "none";
            switcherCanvas.classList.remove("show");
            switcherCanvas.setAttribute("aria-hidden", "true");

            switcherCanvas
                .querySelectorAll("input, button, a, select, textarea")
                .forEach((element) => {
                    element.disabled = true;
                    element.tabIndex = -1;
                });
        }

        document.querySelectorAll(".switcher-icon").forEach((element) => {
            element.style.display = "none";
            element.setAttribute("aria-hidden", "true");
        });
    }

    function blockThemeControlEvents(event) {
        const target = event.target;

        if (!target || typeof target.closest !== "function") return;

        if (
            target.closest(".switcher-icon") ||
            target.closest("#switcher-canvas")
        ) {
            event.preventDefault();
            event.stopPropagation();

            if (typeof event.stopImmediatePropagation === "function") {
                event.stopImmediatePropagation();
            }

            return false;
        }
    }

    function lockTheme() {
        syncStorage();
        applyDefaults();
        syncControls();
    }

    function observeDomChanges() {
        const observer = new MutationObserver(() => {
            lockTheme();
        });

        observer.observe(document.documentElement, {
            attributes: true,
            childList: true,
            subtree: true
        });
    }

    document.addEventListener("click", blockThemeControlEvents, true);
    document.addEventListener("change", blockThemeControlEvents, true);

    document.addEventListener("DOMContentLoaded", () => {
        lockTheme();
        observeDomChanges();
    });

    window.addEventListener("load", lockTheme);

    window.MamixThemeLock = {
        apply: lockTheme,
        defaults
    };

    lockTheme();
})();