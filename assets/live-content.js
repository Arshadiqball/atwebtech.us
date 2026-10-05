(function () {
  var state = null;
  var applying = false;

  function ensureStyle() {
    if (document.getElementById("at-editorial")) return;
    var link = document.createElement("link");
    link.id = "at-editorial";
    link.rel = "stylesheet";
    link.href = "/assets/editorial.css";
    document.head.appendChild(link);
  }

  function formatDate(iso) {
    var date = new Date(iso + "T00:00:00");
    if (Number.isNaN(date.getTime())) return iso;
    return date.toLocaleDateString("en-US", { month: "short", day: "numeric", year: "numeric" });
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
    });
  }

  function applyContact(contact) {
    document.querySelectorAll('a[href^="mailto:"]').forEach(function (link) {
      var href = "mailto:" + contact.email;
      if (link.getAttribute("href") !== href) link.setAttribute("href", href);
      if (link.textContent.trim() !== contact.email && link.textContent.indexOf("@") !== -1) {
        link.textContent = contact.email;
      }
    });
    document.querySelectorAll('a[href^="tel:"]').forEach(function (link) {
      if (link.getAttribute("href") !== contact.phoneHref) link.setAttribute("href", contact.phoneHref);
      if (link.textContent.trim() !== contact.phone) link.textContent = contact.phone;
    });
    var address = document.querySelector("[data-at-address]");
    if (!address) {
      document.querySelectorAll("p").forEach(function (node) {
        if (address) return;
        if (node.textContent.trim() === "Headquarters" && node.nextElementSibling) {
          address = node.nextElementSibling;
          address.setAttribute("data-at-address", "");
        }
      });
    }
    if (address && address.textContent.trim() !== contact.address) address.textContent = contact.address;
  }

  function journalMarkup(posts) {
    var rows = posts.slice(0, 3).map(function (post) {
      return (
        '<a class="note" href="/blog/' + encodeURIComponent(post.slug) + '/">' +
        '<span class="note-meta">' + escapeHtml(formatDate(post.date)) + " · " + escapeHtml(post.category) + "</span>" +
        '<span class="note-title">' + escapeHtml(post.title) + "</span>" +
        '<span class="note-excerpt">' + escapeHtml(post.excerpt) + "</span></a>"
      );
    }).join("");
    return (
      '<div class="wrap">' +
      '<div class="journal-head"><div><p class="kicker">Journal</p>' +
      '<h2 class="display">Notes from <span>the build.</span></h2></div>' +
      '<a class="all" href="/blog/">All notes</a></div>' +
      '<div class="notes">' + rows + "</div></div>"
    );
  }

  function ensureJournal(posts) {
    var contact = document.getElementById("contact");
    if (!contact || !contact.parentNode || !posts.length) return;
    var section = document.getElementById("at-journal");
    var markup = journalMarkup(posts);
    if (!section) {
      section = document.createElement("section");
      section.id = "at-journal";
      section.setAttribute("aria-label", "Journal");
      section.innerHTML = markup;
      contact.parentNode.insertBefore(section, contact);
      return;
    }
    if (section.nextElementSibling !== contact) contact.parentNode.insertBefore(section, contact);
    if (section.getAttribute("data-markup") !== markup) {
      section.innerHTML = markup;
      section.setAttribute("data-markup", markup);
    }
  }

  function ensureNav() {
    document.querySelectorAll("a").forEach(function (link) {
      if (link.textContent.trim() !== "Contact") return;
      var parent = link.parentElement;
      var list = parent.tagName === "LI" ? parent.parentElement : parent;
      if (!list || list.querySelector("[data-at-blog]")) return;
      var blog = link.cloneNode(false);
      blog.textContent = "Journal";
      blog.setAttribute("href", "/blog/");
      blog.setAttribute("data-at-blog", "");
      if (parent.tagName === "LI" && parent.parentElement) {
        var item = document.createElement("li");
        item.appendChild(blog);
        parent.parentElement.insertBefore(item, parent.nextSibling);
      } else {
        parent.appendChild(blog);
      }
    });
  }

  function paint() {
    if (!state || applying) return;
    applying = true;
    try {
      ensureStyle();
      applyContact(state.contact);
      ensureJournal(state.posts || []);
      ensureNav();
    } finally {
      applying = false;
    }
  }

  fetch("/api/content")
    .then(function (response) { return response.json(); })
    .then(function (data) {
      state = data;
      paint();
      var queued = false;
      new MutationObserver(function () {
        if (applying || queued) return;
        queued = true;
        requestAnimationFrame(function () {
          queued = false;
          paint();
        });
      }).observe(document.body, { childList: true, subtree: true });
    })
    .catch(function () {});
})();
