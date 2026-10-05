function fetchApi(path, options) {
  return fetch(path, options).then(function (response) {
    if (response.status !== 404 || path.indexOf("/api/") !== 0) return response;
    return fetch("/api.php?r=" + encodeURIComponent(path.slice(5)), options);
  });
}

(function () {
  var app = document.getElementById("app");

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
    });
  }

  function formatDate(iso) {
    var date = new Date(iso + "T00:00:00");
    if (Number.isNaN(date.getTime())) return iso;
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
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

  function header(current) {
    return (
      '<header class="site-header">' +
      '<a class="brand" href="/"><img src="' + logo() + '" alt="ATwebTech"></a>' +
      '<nav class="nav" aria-label="Primary">' +
      '<a href="/#contact">Contact</a>' +
      '<a href="/blog/"' + (current === "journal" ? ' aria-current="page"' : "") + ">Journal</a>" +
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

  function noteLink(post, featured) {
    return (
      '<a class="note' + (featured ? " featured" : "") + '" href="/blog/' + encodeURIComponent(post.slug) + '/">' +
      '<span class="note-meta">' + escapeHtml(formatDate(post.date)) + " · " + escapeHtml(post.category) + "</span>" +
      '<span class="note-title">' + escapeHtml(post.title) + "</span>" +
      '<span class="note-excerpt">' + escapeHtml(post.excerpt) + "</span></a>"
    );
  }

  fetchApi("/api/content")
    .then(function (response) { return response.json(); })
    .then(function (data) {
      var parts = location.pathname.split("/").filter(Boolean);
      var slug = parts[0] === "blog" && parts[1] ? decodeURIComponent(parts[1]) : "";
      var posts = data.posts || [];
      if (!slug) {
        document.title = "Journal — ATwebTech";
        app.innerHTML =
          header("journal") +
          '<main class="page"><div class="wrap">' +
          '<p class="kicker">Journal</p>' +
          '<h1 class="display">Notes from <span>the build.</span></h1>' +
          '<p class="lede">How ATwebTech thinks about agents, chatbots, and the workflows worth automating.</p>' +
          (posts.length
            ? '<div class="notes">' + posts.map(function (post, index) { return noteLink(post, index === 0); }).join("") + "</div>"
            : '<p class="empty">The journal is empty for now.</p>') +
          "</div></main>" +
          footer(data.contact);
        bindChrome();
        return;
      }
      var post = posts.find(function (item) { return item.slug === slug; });
      if (!post) {
        document.title = "Note not found — ATwebTech";
        app.innerHTML =
          header("journal") +
          '<main class="page"><div class="wrap article"><a class="back" href="/blog/">Back to the journal</a>' +
          "<h1>That note is not on the site.</h1></div></main>" +
          footer(data.contact);
        bindChrome();
        return;
      }
      document.title = post.title + " — ATwebTech";
      app.innerHTML =
        header("journal") +
        '<main class="page"><div class="wrap article">' +
        '<a class="back" href="/blog/">Back to the journal</a>' +
        '<p class="kicker">' + escapeHtml(post.category) + "</p>" +
        "<h1>" + escapeHtml(post.title) + "</h1>" +
        '<p class="dek">' + escapeHtml(post.excerpt) + "</p>" +
        '<p class="note-meta">' + escapeHtml(formatDate(post.date)) + "</p>" +
        '<div class="prose">' + paragraphs(post.body) + "</div>" +
        "</div></main>" +
        footer(data.contact);
      bindChrome();
    })
    .catch(function () {
      app.innerHTML = '<main class="page"><div class="wrap"><h1>The journal could not be loaded.</h1><p class="lede">Refresh the page. If it still fails, start the site with python3 server.py.</p></div></main>';
    });
})();
