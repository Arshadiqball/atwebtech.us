function fetchApi(path, options) {
  return fetch(path, options).then(function (response) {
    if (response.status !== 404 || path.indexOf("/api/") !== 0) return response;
    return fetch("/api.php?r=" + encodeURIComponent(path.slice(5)), options);
  });
}

(function () {
  var state = null;
  var applying = false;
  var servicesByImage = {};

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

  function ensureAnchors() {
    if (!document.getElementById("process")) {
      var processLabel = Array.prototype.find.call(document.querySelectorAll("span, h2"), function (node) {
        return node.textContent.trim() === "Work Process";
      });
      var processHeading = processLabel && (processLabel.closest("h2") || processLabel);
      if (processHeading) processHeading.id = "process";
    }
    if (!document.getElementById("products")) {
      var productHeading = Array.prototype.find.call(document.querySelectorAll("h2"), function (node) {
        return node.textContent.trim().indexOf("Deploy your AI Chatbot") === 0;
      });
      if (productHeading) productHeading.id = "products";
    }
    document.querySelectorAll("a").forEach(function (link) {
      var label = link.textContent.trim();
      var href = link.getAttribute("href") || "";
      if (label === "Privacy Policy") link.setAttribute("href", "/privacy/");
      if (label === "Terms of Service") link.setAttribute("href", "/terms/");
      if (label === "Contact" && (href === "#contact" || href === "/#contact")) {
        link.setAttribute("href", "/contact/");
      }
    });
  }

  function scrollToId(id) {
    ensureAnchors();
    var target = document.getElementById(id);
    if (!target) return;
    target.scrollIntoView({ behavior: "smooth", block: "start" });
  }

  function serviceFrom(node) {
    var card = node && node.closest && node.closest(".group");
    if (!card) return null;
    var img = card.querySelector("img");
    if (!img) return null;
    return servicesByImage[img.getAttribute("src") || ""] || null;
  }

  function openService(service) {
    location.href = "/services/" + encodeURIComponent(service.slug) + "/";
  }

  function bindServiceCards(services) {
    servicesByImage = {};
    (services || []).forEach(function (service) {
      if (service.image) servicesByImage[service.image] = service;
    });
    var grid = document.querySelector("#services .grid");
    if (grid) {
      var present = {};
      grid.querySelectorAll("img").forEach(function (img) {
        if (img.closest("[data-at-extra]")) return;
        present[img.getAttribute("src") || ""] = true;
      });
      var extras = (services || []).filter(function (service) {
        return service.image && !present[service.image];
      }).map(function (service) {
        return '<a class="at-service-extra" data-at-extra href="/services/' + encodeURIComponent(service.slug) + '/">' +
          '<img src="' + escapeHtml(service.image) + '" alt="">' +
          "<strong>" + escapeHtml(service.title) + "</strong></a>";
      }).join("");
      var holder = grid.querySelector("[data-at-extra-wrap]");
      if (!extras) {
        if (holder) holder.remove();
      } else if (!holder || holder.getAttribute("data-markup") !== extras) {
        if (!holder) {
          holder = document.createElement("div");
          holder.setAttribute("data-at-extra-wrap", "");
          grid.appendChild(holder);
        }
        holder.setAttribute("data-markup", extras);
        holder.innerHTML = extras;
      }
    }
    document.querySelectorAll("#services .group").forEach(function (card) {
      var img = card.querySelector("img");
      var service = img && servicesByImage[img.getAttribute("src") || ""];
      if (!service) return;
      if (card.getAttribute("tabindex") !== "0") card.setAttribute("tabindex", "0");
      card.setAttribute("role", "link");
      card.setAttribute("aria-label", service.title);
      var heading = card.querySelector("h3");
      if (heading && heading.textContent.trim() !== service.title) heading.textContent = service.title;
      var blurb = card.querySelector("p");
      if (blurb && blurb.textContent.trim() !== service.summary) blurb.textContent = service.summary;
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
      ensureAnchors();
      bindServiceCards(state.services || []);
    } finally {
      applying = false;
    }
  }

  document.addEventListener("click", function (event) {
    var service = serviceFrom(event.target);
    if (!service) return;
    event.preventDefault();
    openService(service);
  }, true);

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Enter" && event.key !== " ") return;
    var card = event.target.closest && event.target.closest(".group");
    if (!card || document.activeElement !== card) return;
    var service = serviceFrom(card);
    if (!service) return;
    event.preventDefault();
    openService(service);
  });

  document.addEventListener("click", function (event) {
    var link = event.target.closest && event.target.closest("a");
    if (!link) return;
    var href = link.getAttribute("href") || "";
    if (href === "#") {
      event.preventDefault();
      return;
    }
    if (href.charAt(0) !== "#" || href.length < 2) return;
    event.preventDefault();
    scrollToId(href.slice(1));
    if (history.replaceState) history.replaceState(null, "", href);
  });

  document.addEventListener("submit", function (event) {
    var form = event.target;
    if (!form || form.tagName !== "FORM") return;
    var emailInput = form.querySelector("#email, input[type='email']");
    if (!emailInput) return;
    var messageInput = form.querySelector("#message");
    var payload = messageInput
      ? {
          kind: "contact",
          firstName: (form.querySelector("#firstName") || {}).value || "",
          lastName: (form.querySelector("#lastName") || {}).value || "",
          email: emailInput.value,
          message: messageInput.value
        }
      : { kind: "newsletter", email: emailInput.value };
    event.preventDefault();
    var note = form.querySelector(".at-form-note");
    if (!note) {
      note = document.createElement("p");
      note.className = "at-form-note";
      note.setAttribute("role", "status");
      note.style.marginTop = "12px";
      note.style.fontSize = "14px";
      form.appendChild(note);
    }
    fetchApi("/api/messages", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().then(function (body) {
        if (!response.ok) throw new Error(body.error || "Try again.");
        note.textContent = payload.kind === "newsletter"
          ? "You're on the list."
          : "Received. The team can read this in the admin.";
        form.reset();
      });
    }).catch(function (error) {
      note.textContent = error.message;
    });
  }, true);

  fetchApi("/api/content")
    .then(function (response) { return response.json(); })
    .then(function (data) {
      state = data;
      paint();
      if (location.hash) scrollToId(location.hash.slice(1));
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
