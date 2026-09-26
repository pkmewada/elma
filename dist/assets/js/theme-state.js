(function () {
  "use strict";

  var html = document.documentElement;
  var script = document.currentScript || document.querySelector('script[src*="theme-state.js"]');
  var assetRoot = "";

  if (script && script.src) {
    assetRoot = script.src.split("assets/js/theme-state.js")[0];
  }

  var bootstrapCssHref = assetRoot + "assets/libs/bootstrap/css/bootstrap.min.css";
  var bootstrapRtlCssHref = assetRoot + "assets/libs/bootstrap/css/bootstrap.rtl.min.css";

  function storageValue(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function updateBootstrapStyle(isRtl) {
    var styleLink = document.getElementById("style");

    if (styleLink) {
      styleLink.setAttribute("href", isRtl ? bootstrapRtlCssHref : bootstrapCssHref);
    }
  }

  function applySavedTheme() {
    var legacyTheme = storageValue("mamixThemeMode");
    var isDark = storageValue("mamixdarktheme") || legacyTheme === "dark";
    var isRtl = storageValue("mamixrtl");
    var menuStyle = storageValue("mamixMenu");
    var headerStyle = storageValue("mamixHeader");
    var verticalStyle = storageValue("mamixverticalstyles");
    var navLayout = storageValue("mamixlayout");
    var navStyle = storageValue("mamixnavstyles");
    var width = storageValue("mamixboxed") ? "boxed" : (storageValue("mamixfullwidth") ? "fullwidth" : "default");

    html.setAttribute("dir", isRtl ? "rtl" : "ltr");
    html.setAttribute("data-theme-mode", isDark ? "dark" : "light");
    html.setAttribute("data-menu-styles", menuStyle || (isDark ? "dark" : "light"));
    html.setAttribute("data-header-styles", headerStyle || "transparent");
    html.setAttribute("data-width", width);
    html.setAttribute("data-menu-position", storageValue("mamixmenuscrollable") ? "scrollable" : "fixed");
    html.setAttribute("loader", storageValue("loaderEnable") === "false" ? "disable" : "enable");

    if (navLayout === "horizontal") {
      html.setAttribute("data-nav-layout", "horizontal");
      html.removeAttribute("data-vertical-style");
      html.setAttribute("data-nav-style", navStyle || "menu-click");
    } else {
      html.setAttribute("data-nav-layout", "vertical");
      html.setAttribute("data-vertical-style", verticalStyle || "doublemenu");

      if (navStyle) {
        html.setAttribute("data-nav-style", navStyle);
      } else {
        html.removeAttribute("data-nav-style");
      }
    }

    updateBootstrapStyle(isRtl);
  }

  applySavedTheme();

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", applySavedTheme, { once: true });
  }

  window.ModlusThemeState = {
    applySavedTheme: applySavedTheme
  };
})();
