(function () {
  "use strict";

  var script = document.currentScript || document.querySelector('script[src*="central-search.js"]');
  var baseUrl = "";

  if (script && script.src) {
    baseUrl = script.src.split("dist/assets/js/central-search.js")[0];
  }

  var searchUrl = baseUrl + "api/centralSearch.php";
  var debounceTimer = null;
  var activeController = null;

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function getResultBox(input) {
    var root = input.closest(".modal-body") || input.closest(".header-search") || input.parentElement;
    var resultBox = root ? root.querySelector(".central-search-results") : null;

    if (!resultBox && root) {
      resultBox = document.createElement("div");
      resultBox.className = "central-search-results";
      resultBox.setAttribute("role", "listbox");
      root.appendChild(resultBox);
    }

    return resultBox;
  }

  function closeOtherResults(currentBox) {
    document.querySelectorAll(".central-search-results.is-open").forEach(function (box) {
      if (box !== currentBox) {
        box.classList.remove("is-open");
      }
    });
  }

  function showMessage(input, message) {
    var resultBox = getResultBox(input);

    if (!resultBox) {
      return;
    }

    resultBox.innerHTML = '<div class="central-search-empty">' + escapeHtml(message) + "</div>";
    resultBox.classList.add("is-open");
    closeOtherResults(resultBox);
  }

  function renderResults(input, data) {
    var resultBox = getResultBox(input);

    if (!resultBox) {
      return;
    }

    var results = Array.isArray(data.results) ? data.results : [];

    if (!results.length) {
      showMessage(input, data.message || "No Result Found. Please try with another keyword.");
      return;
    }

    resultBox.innerHTML = results.map(function (item) {
      return [
        '<a class="central-search-item" href="', escapeHtml(item.url), '" role="option">',
        '<span class="central-search-title">', escapeHtml(item.title), "</span>",
        '<span class="central-search-meta">', escapeHtml(item.moduleName), " &middot; ", escapeHtml(item.routePath), "</span>",
        "</a>"
      ].join("");
    }).join("");

    resultBox.classList.add("is-open");
    closeOtherResults(resultBox);
  }

  function runSearch(input) {
    var keyword = input.value.trim();

    if (keyword.length === 0) {
      var resultBox = getResultBox(input);
      if (resultBox) {
        resultBox.classList.remove("is-open");
      }
      return;
    }

    if (keyword.length < 2) {
      showMessage(input, "Type at least 2 characters to search.");
      return;
    }

    if (activeController) {
      activeController.abort();
    }

    activeController = new AbortController();

    fetch(searchUrl + "?q=" + encodeURIComponent(keyword), {
      method: "GET",
      headers: {
        "Accept": "application/json"
      },
      signal: activeController.signal
    })
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        renderResults(input, data || {});
      })
      .catch(function (error) {
        if (error.name === "AbortError") {
          return;
        }

        showMessage(input, "Search is not available right now.");
      });
  }

  function bindInput(input) {
    if (input.dataset.centralSearchBound === "1") {
      return;
    }

    input.dataset.centralSearchBound = "1";
    input.setAttribute("aria-autocomplete", "list");

    input.addEventListener("input", function () {
      window.clearTimeout(debounceTimer);
      debounceTimer = window.setTimeout(function () {
        runSearch(input);
      }, 250);
    });

    input.addEventListener("focus", function () {
      if (input.value.trim().length >= 2) {
        runSearch(input);
      }
    });

    input.addEventListener("keydown", function (event) {
      if (event.key !== "Enter") {
        return;
      }

      var firstResult = getResultBox(input).querySelector(".central-search-item");

      if (firstResult) {
        event.preventDefault();
        window.location.href = firstResult.href;
      }
    });
  }

  function bindSearchButtons() {
    document.querySelectorAll(".central-route-search-submit").forEach(function (button) {
      if (button.dataset.centralSearchBound === "1") {
        return;
      }

      button.dataset.centralSearchBound = "1";
      button.addEventListener("click", function () {
        var root = button.closest(".modal-body") || button.closest(".header-search") || document;
        var input = root.querySelector(".central-route-search");

        if (input) {
          runSearch(input);
          input.focus();
        }
      });
    });
  }

  document.addEventListener("click", function (event) {
    if (event.target.closest(".header-search") || event.target.closest("#header-responsive-search")) {
      return;
    }

    document.querySelectorAll(".central-search-results.is-open").forEach(function (box) {
      box.classList.remove("is-open");
    });
  });

  document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll(".central-route-search").forEach(bindInput);
    bindSearchButtons();
  });
})();
