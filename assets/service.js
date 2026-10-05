function fetchApi(path, options) {
  return fetch(path, options).then(function (response) {
    if (response.status !== 404 || path.indexOf("/api/") !== 0) return response;
    return fetch("/api.php?r=" + encodeURIComponent(path.slice(5)), options);
  });
}

(function () {
  var app = document.getElementById("app");

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
    });
  }

  function paragraphs(body) {
    return String(body || "")
      .trim()
      .split(/\n{2,}/)
      .map(function (part) {
        return "<p>" + escapeHtml(part).replace(/\n/g, "<br>") + "</p>";
      })
      .join("");
  }

  function logo() {
    return document.documentElement.classList.contains("dark") ? "/image/logo.png" : "/image/logolight.png";
  }

  function header() {
    return (
      '<header class="site-header">' +
      '<a class="brand" href="/"><img src="' + logo() + '" alt="ATwebTech"></a>' +
      '<nav class="nav" aria-label="Primary">' +
      '<a href="/#services">Services</a>' +
      '<a href="/blog/">Journal</a>' +
      '<a href="/contact/">Contact</a>' +
      '<button class="theme-btn" type="button" aria-label="Toggle theme">◐</button>' +
      "</nav></header>"
    );
  }

  function footer(contact) {
    contact = contact || {};
    return (
      '<footer class="site-footer"><div class="wrap">' +
      '<a class="foot-brand" href="/"><img src="' + logo() + '" alt="ATwebTech"></a>' +
      '<div class="foot-contact">' +
      "<div>" + escapeHtml(contact.address || "") + "</div>" +
      '<div><a href="mailto:' + escapeHtml(contact.email || "") + '">' + escapeHtml(contact.email || "") + "</a></div>" +
      '<div><a href="' + escapeHtml(contact.phoneHref || "#") + '">' + escapeHtml(contact.phone || "") + "</a></div>" +
      '<div><a href="/privacy/">Privacy</a> · <a href="/terms/">Terms</a></div>' +
      "</div></div></footer>"
    );
  }

  function bindChrome() {
    var button = document.querySelector(".theme-btn");
    if (!button) return;
    button.addEventListener("click", function () {
      var dark = document.documentElement.classList.toggle("dark");
      localStorage.setItem("alphaai-theme", dark ? "dark" : "light");
      document.querySelectorAll("img").forEach(function (img) {
        if (img.alt === "ATwebTech") img.src = logo();
      });
    });
  }

  function card(service) {
    return (
      '<a class="service-card" href="/services/' + encodeURIComponent(service.slug) + '/">' +
      '<img src="' + escapeHtml(service.image) + '" alt="">' +
      '<span class="service-card-copy"><strong>' + escapeHtml(service.title) + "</strong>" +
      "<span>" + escapeHtml(service.summary) + "</span></span></a>"
    );
  }

  fetchApi("/api/content")
    .then(function (response) { return response.json(); })
    .then(function (data) {
      var services = data.services || [];
      var parts = location.pathname.split("/").filter(Boolean);
      var slug = parts[0] === "services" && parts[1] && parts[1].indexOf(".") === -1 ? decodeURIComponent(parts[1]) : "";
      if (!slug) {
        document.title = "Services — ATwebTech";
        app.innerHTML =
          header() +
          '<main class="page"><div class="wrap">' +
          '<p class="kicker">Services</p>' +
          '<h1 class="display">Work U.S. teams <span>ask for.</span></h1>' +
          '<p class="lede">AI systems and the product work around them. Open a service for the detail, then book a conversation if it fits.</p>' +
          (services.length
            ? '<div class="service-grid">' + services.map(card).join("") + "</div>"
            : '<p class="empty">No services are published yet.</p>') +
          "</div></main>" +
          footer(data.contact);
        bindChrome();
        return;
      }
      var service = services.find(function (item) { return item.slug === slug; });
      if (!service) {
        document.title = "Service not found — ATwebTech";
        app.innerHTML =
          header() +
          '<main class="page"><div class="wrap article"><a class="back" href="/#services">Back to services</a>' +
          "<h1>That service is not on the site.</h1></div></main>" +
          footer(data.contact);
        bindChrome();
        return;
      }
      var others = services.filter(function (item) { return item.slug !== service.slug; });
      document.title = service.title + " — ATwebTech";
      app.innerHTML =
        header() +
        '<main class="page"><div class="wrap service-detail">' +
        '<a class="back" href="/#services">Back to services</a>' +
        '<p class="kicker" style="color:' + escapeHtml(service.accent) + '">Service</p>' +
        "<h1>" + escapeHtml(service.title) + "</h1>" +
        '<p class="dek">' + escapeHtml(service.summary) + "</p>" +
        '<figure class="service-hero"><img src="' + escapeHtml(service.image) + '" alt="' + escapeHtml(service.title) + '">' +
        '<span style="background:' + escapeHtml(service.accent) + '"></span></figure>' +
        (service.points && service.points.length
          ? '<ul class="service-points">' + service.points.map(function (point) {
              return "<li>" + escapeHtml(point) + "</li>";
            }).join("") + "</ul>"
          : "") +
        '<div class="prose">' + paragraphs(service.body) + "</div>" +
        '<a class="service-cta" href="/contact/">Book a consultation</a>' +
        (others.length
          ? '<section class="service-more"><h2>Other services</h2><div class="service-links">' +
            others.map(function (item) {
              return '<a href="/services/' + encodeURIComponent(item.slug) + '/">' + escapeHtml(item.title) + "</a>";
            }).join("") + "</div></section>"
          : "") +
        "</div></main>" +
        footer(data.contact);
      bindChrome();
    })
    .catch(function () {
      app.innerHTML = '<main class="page"><div class="wrap"><h1>This page could not be loaded.</h1><p class="lede">Refresh the page. If it still fails, start the site with python3 server.py.</p></div></main>';
    });
})();
