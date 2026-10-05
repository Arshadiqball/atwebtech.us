(function () {
  var app = document.getElementById("app");
  var source = document.getElementById("page-body");

  function escapeHtml(value) {
    return String(value || "").replace(/[&<>"']/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
    });
  }

  function logo() {
    return document.documentElement.classList.contains("dark") ? "/image/logo.png" : "/image/logolight.png";
  }

  function render(contact) {
    contact = contact || {};
    app.innerHTML =
      '<header class="site-header"><a class="brand" href="/"><img src="' + logo() + '" alt="ATwebTech"></a>' +
      '<nav class="nav" aria-label="Primary"><a href="/#contact">Contact</a><a href="/blog/">Journal</a>' +
      '<button class="theme-btn" type="button" aria-label="Toggle theme">◐</button></nav></header>' +
      '<main class="page"><div class="wrap article">' + (source ? source.innerHTML : "") + "</div></main>" +
      '<footer class="site-footer"><div class="wrap"><a class="foot-brand" href="/"><img src="' + logo() + '" alt="ATwebTech"></a>' +
      '<div class="foot-contact"><div>' + escapeHtml(contact.address || "") + "</div>" +
      '<div><a href="mailto:' + escapeHtml(contact.email || "") + '">' + escapeHtml(contact.email || "") + "</a></div>" +
      '<div><a href="' + escapeHtml(contact.phoneHref || "#") + '">' + escapeHtml(contact.phone || "") + "</a></div>" +
      '<div><a href="/privacy/">Privacy</a> · <a href="/terms/">Terms</a></div></div></div></footer>';
    var button = document.querySelector(".theme-btn");
    if (button) {
      button.addEventListener("click", function () {
        var dark = document.documentElement.classList.toggle("dark");
        localStorage.setItem("alphaai-theme", dark ? "dark" : "light");
        document.querySelectorAll("img").forEach(function (img) {
          if (img.alt === "ATwebTech") img.src = logo();
        });
      });
    }
  }

  fetch("/api/content")
    .then(function (response) { return response.json(); })
    .then(function (data) { render(data.contact); })
    .catch(function () { render(); });
})();
