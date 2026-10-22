/* Theme switcher — shared across all pages.
   Injects a small palette button + popover wherever the page has a
   topbar avatar or a public navbar, and falls back to a floating
   button on pages with neither (e.g. login/signup). */
(function () {
  "use strict";

  var STORAGE_KEY = "portalTheme";

  var THEMES = [
    { id: "classic", label: "Classic Navy & Gold", a: "#c9a227", b: "#0b1a33" },
    { id: "ocean", label: "Ocean Teal", a: "#17a2b8", b: "#06232c" },
    { id: "forest", label: "Forest Green", a: "#b07d2a", b: "#10241a" },
    { id: "sunset", label: "Sunset Coral", a: "#e4593f", b: "#2a1a3d" },
    { id: "midnight", label: "Midnight Dark", a: "#e0b23e", b: "#05070c" }
  ];

  function getSavedTheme() {
    try {
      return localStorage.getItem(STORAGE_KEY) || "classic";
    } catch (e) {
      return "classic";
    }
  }

  function applyTheme(id) {
    if (id === "classic") {
      document.documentElement.removeAttribute("data-theme");
    } else {
      document.documentElement.setAttribute("data-theme", id);
    }
    try {
      localStorage.setItem(STORAGE_KEY, id);
    } catch (e) { /* ignore */ }
  }

  function buildSwitcher() {
    var current = getSavedTheme();

    var wrap = document.createElement("div");
    wrap.className = "theme-switcher";

    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "theme-switcher-btn";
    btn.setAttribute("aria-label", "Change color theme");
    btn.setAttribute("aria-haspopup", "true");
    btn.setAttribute("aria-expanded", "false");
    btn.innerHTML = '<i class="fa-solid fa-palette"></i>';

    var panel = document.createElement("div");
    panel.className = "theme-switcher-panel";
    panel.setAttribute("role", "menu");

    var title = document.createElement("div");
    title.className = "theme-switcher-title";
    title.textContent = "Color theme";
    panel.appendChild(title);

    THEMES.forEach(function (theme) {
      var opt = document.createElement("button");
      opt.type = "button";
      opt.className = "theme-option" + (theme.id === current ? " active" : "");
      opt.setAttribute("role", "menuitemradio");
      opt.dataset.themeId = theme.id;

      var swatch = document.createElement("span");
      swatch.className = "theme-swatch";
      swatch.style.setProperty("--swatch-a", theme.a);
      swatch.style.setProperty("--swatch-b", theme.b);

      var label = document.createElement("span");
      label.textContent = theme.label;

      opt.appendChild(swatch);
      opt.appendChild(label);

      opt.addEventListener("click", function () {
        applyTheme(theme.id);
        panel.querySelectorAll(".theme-option").forEach(function (el) {
          el.classList.toggle("active", el.dataset.themeId === theme.id);
        });
        closePanel();
      });

      panel.appendChild(opt);
    });

    wrap.appendChild(btn);
    wrap.appendChild(panel);

    function openPanel() {
      panel.classList.add("open");
      btn.setAttribute("aria-expanded", "true");
      document.addEventListener("click", onOutsideClick, true);
    }
    function closePanel() {
      panel.classList.remove("open");
      btn.setAttribute("aria-expanded", "false");
      document.removeEventListener("click", onOutsideClick, true);
    }
    function onOutsideClick(e) {
      if (!wrap.contains(e.target)) closePanel();
    }

    btn.addEventListener("click", function (e) {
      e.stopPropagation();
      panel.classList.contains("open") ? closePanel() : openPanel();
    });

    return wrap;
  }

  function mountSwitcher() {
    var wrap = buildSwitcher();
    var avatar = document.querySelector(".avatar-circle");
    var publicNavActions = document.querySelector(
      ".portal-navbar .d-flex.gap-2"
    );

    if (avatar && avatar.parentNode) {
      avatar.parentNode.insertBefore(wrap, avatar);
    } else if (publicNavActions) {
      publicNavActions.insertBefore(wrap, publicNavActions.firstChild);
    } else {
      wrap.classList.add("theme-switcher-floating");
      document.body.appendChild(wrap);
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mountSwitcher);
  } else {
    mountSwitcher();
  }
})();
