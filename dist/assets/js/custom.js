(function () {
  "use strict";

  function onReady(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  function syncFullscreenIcons() {
    var open = document.querySelector(".full-screen-open");
    var close = document.querySelector(".full-screen-close");
    if (!open || !close) return;

    var isFs = !!(
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.msFullscreenElement
    );

    if (isFs) {
      close.classList.add("d-block");
      close.classList.remove("d-none");
      open.classList.add("d-none");
      open.classList.remove("d-block");
    } else {
      close.classList.add("d-none");
      close.classList.remove("d-block");
      open.classList.remove("d-none");
      open.classList.add("d-block");
    }
  }

  onReady(function () {
    var html = document.documentElement;

    function setThemeMode(mode) {
      var next = mode === "dark" ? "dark" : "light";
      var lightSwitch = document.querySelector("#switcher-light-theme");
      var darkSwitch = document.querySelector("#switcher-dark-theme");

      html.setAttribute("data-theme-mode", next);
      html.setAttribute("data-menu-styles", next === "dark" ? "dark" : "light");
      html.setAttribute("data-header-styles", "transparent");

      localStorage.setItem("mamixThemeMode", next);
      localStorage.setItem("mamixHeader", "transparent");

      if (next === "dark") {
        localStorage.setItem("mamixdarktheme", "true");
        localStorage.setItem("mamixMenu", "dark");
        localStorage.removeItem("mamixlighttheme");
      } else {
        localStorage.removeItem("mamixdarktheme");
        localStorage.setItem("mamixlighttheme", "true");
        localStorage.setItem("mamixMenu", "light");
      }

      if (darkSwitch) darkSwitch.checked = next === "dark";
      if (lightSwitch) lightSwitch.checked = next === "light";
    }

    var savedTheme = localStorage.getItem("mamixdarktheme")
      ? "dark"
      : localStorage.getItem("mamixThemeMode");

    if (savedTheme === "dark" || savedTheme === "light") {
      setThemeMode(savedTheme);
    }

    function hideLoader() {
      var loader = document.getElementById("loader");
      if (loader) loader.classList.add("d-none");
    }
    window.addEventListener("load", hideLoader);

    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
      if (typeof bootstrap !== "undefined" && bootstrap.Tooltip) new bootstrap.Tooltip(el);
    });

    document.querySelectorAll('[data-bs-toggle="popover"]').forEach(function (el) {
      if (typeof bootstrap !== "undefined" && bootstrap.Popover) new bootstrap.Popover(el);
    });

    if (typeof flatpickr !== "undefined") {
      var rangeEl = document.querySelector("#daterange");
      if (rangeEl) {
        flatpickr(rangeEl, {
          mode: "range",
          dateFormat: "Y-m-d",
          defaultDate: ["2024-07-01", "2024-07-30"]
        });
      }
    }

    function toggleTheme() {
      var current = html.getAttribute("data-theme-mode") === "dark" ? "dark" : "light";
      var next = current === "dark" ? "light" : "dark";
      setThemeMode(next);
    }

    var layoutSetting = document.querySelector(".layout-setting");
    if (layoutSetting) layoutSetting.addEventListener("click", toggleTheme);

    var yearEl = document.getElementById("year");
    if (yearEl) yearEl.innerHTML = new Date().getFullYear();

    var scrollToTop = document.querySelector(".scrollToTop");
    if (scrollToTop) {
      window.addEventListener("scroll", function () {
        if (window.scrollY > 100) {
          scrollToTop.style.display = "flex";
        } else {
          scrollToTop.style.display = "none";
        }
      });
      scrollToTop.addEventListener("click", function () {
        window.scrollTo(0, 0);
      });
    }

    var headerNotification = document.getElementById("header-notification-scroll");
    if (headerNotification && typeof SimpleBar !== "undefined") {
      new SimpleBar(headerNotification, { autoHide: true });
    }

    var headerCart = document.getElementById("header-cart-items-scroll");
    if (headerCart && typeof SimpleBar !== "undefined") {
      new SimpleBar(headerCart, { autoHide: true });
    }

    syncFullscreenIcons();
    document.addEventListener("fullscreenchange", syncFullscreenIcons);
    document.addEventListener("webkitfullscreenchange", syncFullscreenIcons);
    document.addEventListener("MSFullscreenChange", syncFullscreenIcons);
  });
})();

/* full screen */
var elem = document.documentElement;
function openFullscreen() {
  var docEl = elem || document.documentElement;
  var isFs = !!(
    document.fullscreenElement ||
    document.webkitFullscreenElement ||
    document.msFullscreenElement
  );

  if (!isFs) {
    if (docEl.requestFullscreen) {
      docEl.requestFullscreen();
    } else if (docEl.webkitRequestFullscreen) {
      docEl.webkitRequestFullscreen();
    } else if (docEl.msRequestFullscreen) {
      docEl.msRequestFullscreen();
    }
  } else {
    if (document.exitFullscreen) {
      document.exitFullscreen();
    } else if (document.webkitExitFullscreen) {
      document.webkitExitFullscreen();
    } else if (document.msExitFullscreen) {
      document.msExitFullscreen();
    }
  }
}
/* full screen */
