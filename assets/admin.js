(function () {
  var app = document.getElementById("app");
  var content = null;
  var view = "contact";
  var editing = null;
  var serviceEditing = null;
  var message = "";
  var error = "";

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
    });
  }

  function api(path, options) {
    options = options || {};
    options.credentials = "same-origin";
    options.headers = Object.assign({ "Content-Type": "application/json" }, options.headers || {});
    return fetch(path, options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) throw new Error(body.error || "Something went wrong.");
        return body;
      });
    });
  }

  function shell(inner) {
    app.innerHTML =
      '<header class="top"><a href="/"><img src="/image/logolight.png" alt="ATwebTech"></a>' +
      '<nav><a href="#contact" data-view="contact"' + (view === "contact" ? ' aria-current="page"' : "") + ">Contact</a>" +
      '<a href="#services" data-view="services"' + (view === "services" || view === "service-edit" ? ' aria-current="page"' : "") + ">Services</a>" +
      '<a href="#journal" data-view="journal"' + (view === "journal" || view === "edit" ? ' aria-current="page"' : "") + ">Journal</a>" +
      '<a href="#messages" data-view="messages"' + (view === "messages" ? ' aria-current="page"' : "") + ">Messages</a>" +
      '<a href="#mail" data-view="mail"' + (view === "mail" ? ' aria-current="page"' : "") + ">Mail</a>" +
      '<a href="#password" data-view="password"' + (view === "password" ? ' aria-current="page"' : "") + ">Password</a></nav>" +
      '<div class="top-actions"><a href="/blog/" target="_blank" rel="noopener">View journal</a>' +
      '<button class="ghost" type="button" id="signout">Sign out</button></div></header>' +
      '<main class="shell">' + inner + "</main>";
    app.querySelectorAll("[data-view]").forEach(function (link) {
      link.addEventListener("click", function (event) {
        event.preventDefault();
        view = link.getAttribute("data-view");
        editing = null;
        serviceEditing = null;
        message = "";
        error = "";
        render();
      });
    });
    document.getElementById("signout").addEventListener("click", function () {
      api("/api/logout", { method: "POST", body: "{}" }).finally(function () { location.reload(); });
    });
  }

  function showError(text) {
    return text ? '<p class="banner" role="alert">' + escapeHtml(text) + "</p>" : "";
  }

  function renderLogin() {
    app.innerHTML =
      '<form class="login" id="login">' +
      '<img src="/image/logolight.png" alt="ATwebTech" style="height:36px">' +
      "<h1>Admin</h1><p class=\"sub\">Update the contact details and the journal.</p>" +
      showError(error) +
      '<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>' +
      '<div class="actions"><button class="primary" type="submit">Sign in</button></div></form>';
    document.getElementById("login").addEventListener("submit", function (event) {
      event.preventDefault();
      error = "";
      api("/api/login", { method: "POST", body: JSON.stringify({ password: event.target.password.value }) })
        .then(load)
        .catch(function (err) {
          error = err.message;
          renderLogin();
        });
    });
  }

  function render() {
    if (view === "contact") {
      var contact = content.contact;
      shell(
        "<h1>Contact</h1><p class=\"sub\">These details appear in the menu, the contact section, and the journal.</p>" +
        showError(error) + (message ? '<p class="ok">' + escapeHtml(message) + "</p>" : "") +
        '<form id="contact-form" class="card">' +
        '<label for="email">Email</label><input id="email" name="email" type="email" required value="' + escapeHtml(contact.email) + '">' +
        '<label for="phone">Phone</label><input id="phone" name="phone" type="tel" required value="' + escapeHtml(contact.phone) + '">' +
        '<label for="address">Address</label><input id="address" name="address" required value="' + escapeHtml(contact.address) + '">' +
        '<div class="actions"><button class="primary" type="submit">Save contact details</button></div></form>'
      );
      document.getElementById("contact-form").addEventListener("submit", function (event) {
        event.preventDefault();
        message = "";
        error = "";
        var form = event.target;
        api("/api/admin/contact", {
          method: "PUT",
          body: JSON.stringify({ email: form.email.value, phone: form.phone.value, address: form.address.value })
        }).then(function (body) {
          content.contact = body.contact;
          message = "Saved. The site is using these details.";
          render();
        }).catch(function (err) {
          error = err.message;
          render();
        });
      });
      return;
    }
    if (view === "service-edit") {
      var service = serviceEditing || { title: "", slug: "", summary: "", image: "/services/", accent: "#67e8f9", order: (content.services || []).length + 1, points: [], body: "", published: true };
      var pointText = Array.isArray(service.points) ? service.points.join("\n") : "";
      shell(
        "<h1>" + (service.id ? "Edit service" : "New service") + "</h1>" +
        '<p class="sub">This is the page that opens when someone clicks the service. One point per line.</p>' +
        showError(error) +
        '<form id="service-form" class="card">' +
        '<label for="title">Name</label><input id="title" name="title" required value="' + escapeHtml(service.title) + '">' +
        '<div class="row"><div><label for="slug">Link slug</label><input id="slug" name="slug" value="' + escapeHtml(service.slug || "") + '"></div>' +
        '<div><label for="order">Order</label><input id="order" name="order" type="number" value="' + escapeHtml(service.order) + '"></div></div>' +
        '<label for="summary">Summary</label><input id="summary" name="summary" required value="' + escapeHtml(service.summary) + '">' +
        '<div class="row"><div><label for="image">Image path</label><input id="image" name="image" required value="' + escapeHtml(service.image) + '"></div>' +
        '<div><label for="accent">Accent</label><input id="accent" name="accent" value="' + escapeHtml(service.accent || "#67e8f9") + '"></div></div>' +
        '<label for="points">What it includes</label><textarea id="points" name="points" class="short">' + escapeHtml(pointText) + "</textarea>" +
        '<label for="body">Detail</label><textarea id="body" name="body" required>' + escapeHtml(service.body) + "</textarea>" +
        '<label class="check"><input type="checkbox" name="published"' + (service.published !== false ? " checked" : "") + "> Show on the site</label>" +
        '<div class="actions"><button class="primary" type="submit">Save service</button>' +
        '<button class="quiet" type="button" id="cancel">Cancel</button>' +
        (service.id ? '<a class="quiet" href="/services/' + encodeURIComponent(service.slug) + '/" target="_blank" rel="noopener">View page</a>' : "") +
        (service.id ? '<button class="danger" type="button" id="remove">Delete</button>' : "") +
        "</div></form>"
      );
      document.getElementById("cancel").addEventListener("click", function () {
        view = "services";
        serviceEditing = null;
        error = "";
        render();
      });
      var removeService = document.getElementById("remove");
      if (removeService) {
        removeService.addEventListener("click", function () {
          if (!window.confirm("Delete this service?")) return;
          api("/api/admin/services/" + service.id, { method: "DELETE" }).then(function () {
            message = "Service deleted.";
            return api("/api/admin/content");
          }).then(function (body) {
            content = body;
            view = "services";
            serviceEditing = null;
            render();
          }).catch(function (err) {
            error = err.message;
            render();
          });
        });
      }
      document.getElementById("service-form").addEventListener("submit", function (event) {
        event.preventDefault();
        var form = event.target;
        var payload = {
          title: form.title.value,
          slug: form.slug.value,
          summary: form.summary.value,
          image: form.image.value,
          accent: form.accent.value,
          order: Number(form.order.value),
          points: form.points.value,
          body: form.body.value,
          published: form.published.checked
        };
        var request = service.id
          ? api("/api/admin/services/" + service.id, { method: "PUT", body: JSON.stringify(payload) })
          : api("/api/admin/services", { method: "POST", body: JSON.stringify(payload) });
        request.then(function () {
          message = "Service saved.";
          return api("/api/admin/content");
        }).then(function (body) {
          content = body;
          view = "services";
          serviceEditing = null;
          error = "";
          render();
        }).catch(function (err) {
          error = err.message;
          serviceEditing = Object.assign({}, service, payload, { points: form.points.value.split("\n").filter(Boolean) });
          render();
        });
      });
      return;
    }
    if (view === "services") {
      var services = content.services || [];
      shell(
        '<div class="actions" style="justify-content:space-between"><div><h1>Services</h1><p class="sub">Each one opens as its own page from the homepage cards.</p></div>' +
        '<button class="primary" type="button" id="new-service">New service</button></div>' +
        (message ? '<p class="ok">' + escapeHtml(message) + "</p>" : "") +
        (services.length ? '<div class="list">' + services.map(function (item) {
          return '<a class="item" href="#service" data-id="' + escapeHtml(item.id) + '"><span><strong>' + escapeHtml(item.title) +
            "</strong><span>/" + escapeHtml(item.slug) + "</span></span>" +
            '<span class="status">' + (item.published === false ? "Hidden" : "Live") + "</span></a>";
        }).join("") + "</div>" : "<p class=\"sub\">No services yet.</p>")
      );
      var addService = document.getElementById("new-service");
      if (addService) {
        addService.addEventListener("click", function () {
          serviceEditing = null;
          view = "service-edit";
          error = "";
          message = "";
          render();
        });
      }
      app.querySelectorAll("[data-id]").forEach(function (row) {
        row.addEventListener("click", function (event) {
          event.preventDefault();
          serviceEditing = services.find(function (item) { return item.id === row.getAttribute("data-id"); });
          view = "service-edit";
          error = "";
          message = "";
          render();
        });
      });
      return;
    }
    if (view === "edit") {
      var post = editing || { title: "", slug: "", excerpt: "", category: "Notes", date: new Date().toISOString().slice(0, 10), body: "", published: true };
      shell(
        "<h1>" + (post.id ? "Edit note" : "New note") + "</h1>" +
        '<p class="sub">Blank lines start a new paragraph on the site.</p>' +
        showError(error) +
        '<form id="post-form" class="card">' +
        '<label for="title">Title</label><input id="title" name="title" required value="' + escapeHtml(post.title) + '">' +
        '<div class="row"><div><label for="category">Category</label><input id="category" name="category" value="' + escapeHtml(post.category) + '"></div>' +
        '<div><label for="date">Date</label><input id="date" name="date" type="date" required value="' + escapeHtml(post.date) + '"></div></div>' +
        '<label for="slug">Link slug</label><input id="slug" name="slug" value="' + escapeHtml(post.slug || "") + '" placeholder="Filled from the title if you leave it blank">' +
        '<label for="excerpt">Excerpt</label><input id="excerpt" name="excerpt" value="' + escapeHtml(post.excerpt) + '">' +
        '<label for="body">Body</label><textarea id="body" name="body" required>' + escapeHtml(post.body) + "</textarea>" +
        '<label class="check"><input type="checkbox" name="published"' + (post.published ? " checked" : "") + "> Show on the site</label>" +
        '<div class="actions"><button class="primary" type="submit">Save note</button>' +
        '<button class="quiet" type="button" id="cancel">Cancel</button>' +
        (post.id ? '<button class="danger" type="button" id="remove">Delete</button>' : "") +
        "</div></form>"
      );
      document.getElementById("cancel").addEventListener("click", function () {
        view = "journal";
        editing = null;
        error = "";
        render();
      });
      var remove = document.getElementById("remove");
      if (remove) {
        remove.addEventListener("click", function () {
          if (!window.confirm("Delete this note?")) return;
          api("/api/admin/posts/" + post.id, { method: "DELETE" }).then(loadJournal).catch(function (err) {
            error = err.message;
            render();
          });
        });
      }
      document.getElementById("post-form").addEventListener("submit", function (event) {
        event.preventDefault();
        var form = event.target;
        var payload = {
          title: form.title.value,
          slug: form.slug.value,
          category: form.category.value,
          date: form.date.value,
          excerpt: form.excerpt.value,
          body: form.body.value,
          published: form.published.checked
        };
        var request = post.id
          ? api("/api/admin/posts/" + post.id, { method: "PUT", body: JSON.stringify(payload) })
          : api("/api/admin/posts", { method: "POST", body: JSON.stringify(payload) });
        request.then(function () {
          message = "Note saved.";
          loadJournal();
        }).catch(function (err) {
          error = err.message;
          editing = Object.assign({}, post, payload);
          render();
        });
      });
      return;
    }
    if (view === "password") {
      shell(
        "<h1>Password</h1><p class=\"sub\">This only protects the admin on this computer.</p>" +
        showError(error) + (message ? '<p class="ok">' + escapeHtml(message) + "</p>" : "") +
        '<form id="password-form" class="card">' +
        '<label for="current">Current password</label><input id="current" name="current" type="password" autocomplete="current-password" required>' +
        '<label for="next">New password</label><input id="next" name="next" type="password" autocomplete="new-password" minlength="8" required>' +
        '<div class="actions"><button class="primary" type="submit">Change password</button></div></form>'
      );
      document.getElementById("password-form").addEventListener("submit", function (event) {
        event.preventDefault();
        message = "";
        error = "";
        api("/api/admin/password", {
          method: "POST",
          body: JSON.stringify({ current: event.target.current.value, next: event.target.next.value })
        }).then(function () {
          message = "Password changed.";
          render();
        }).catch(function (err) {
          error = err.message;
          render();
        });
      });
      return;
    }
    if (view === "mail") {
      shell("<h1>Mail</h1><p class=\"sub\">Loading mailbox…</p>");
      api("/api/admin/mail").then(function (body) {
        var mail = body.mail || {};
        if (view !== "mail") return;
        shell(
          "<h1>Mail</h1><p class=\"sub\">Consultation requests are always saved under Messages. Fill this in so each one is also emailed to the team.</p>" +
          showError(error) + (message ? '<p class="ok">' + escapeHtml(message) + "</p>" : "") +
          '<form id="mail-form" class="card">' +
          '<label for="host">SMTP host</label><input id="host" name="host" placeholder="smtp.gmail.com" value="' + escapeHtml(mail.host || "") + '">' +
          '<div class="row"><div><label for="port">Port</label><input id="port" name="port" type="number" min="1" max="65535" required value="' + escapeHtml(mail.port || 587) + '"></div>' +
          '<div><label for="tls">Security</label><select id="tls" name="tls">' +
          '<option value="starttls"' + (mail.tls !== "ssl" ? " selected" : "") + ">STARTTLS</option>" +
          '<option value="ssl"' + (mail.tls === "ssl" ? " selected" : "") + ">SSL</option></select></div></div>" +
          '<label for="user">Username</label><input id="user" name="user" autocomplete="off" value="' + escapeHtml(mail.user || "") + '">' +
          '<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" placeholder="' + (mail.passwordSet ? "Saved. Leave blank to keep it." : "Mailbox password or app password") + '">' +
          '<label for="fromName">From name</label><input id="fromName" name="fromName" required value="' + escapeHtml(mail.fromName || "At Web Technologies") + '">' +
          '<label for="from">From</label><input id="from" name="from" type="email" required value="' + escapeHtml(mail.from || "arshad@atwebtechnologies.com") + '">' +
          '<label for="to">Send requests to</label><input id="to" name="to" type="text" required value="' + escapeHtml(mail.to || "") + '">' +
          '<div class="actions"><button class="primary" type="submit">Save mailbox</button></div></form>'
        );
        document.getElementById("mail-form").addEventListener("submit", function (event) {
          event.preventDefault();
          message = "";
          error = "";
          var form = event.target;
          api("/api/admin/mail", {
            method: "PUT",
            body: JSON.stringify({
              host: form.host.value,
              port: Number(form.port.value),
              tls: form.tls.value,
              user: form.user.value,
              password: form.password.value,
              from: form.from.value,
              fromName: form.fromName.value,
              to: form.to.value
            })
          }).then(function () {
            message = "Mailbox saved. New consultation requests will be emailed.";
            render();
          }).catch(function (err) {
            error = err.message;
            render();
          });
        });
      }).catch(function (err) {
        error = err.message;
        shell("<h1>Mail</h1>" + showError(error));
      });
      return;
    }
    if (view === "messages") {
      shell("<h1>Messages</h1><p class=\"sub\">Notes from the contact form and the newsletter.</p><div id=\"message-list\"><p class=\"sub\">Loading…</p></div>");
      api("/api/admin/messages").then(function (body) {
        var list = document.getElementById("message-list");
        if (!list) return;
        var items = body.messages || [];
        list.innerHTML = items.length
          ? '<div class="list">' + items.map(function (item) {
              var who = item.kind === "contact"
                ? escapeHtml((item.firstName || "") + " " + (item.lastName || "")).trim()
                : "Newsletter";
              var extra = item.phone ? " · " + escapeHtml(item.phone) : "";
              var delivery = item.kind === "contact" ? (item.emailed ? "emailed" : "saved") : item.kind;
              return '<article class="item"><span><strong>' + (who || "Contact") + "</strong><span>" +
                escapeHtml(item.email) + extra + " · " + escapeHtml(item.at) +
                (item.message ? "<br>" + escapeHtml(item.message) : "") +
                "</span></span><span class=\"status\">" + escapeHtml(delivery) + "</span></article>";
            }).join("") + "</div>"
          : "<p class=\"sub\">No messages yet.</p>";
      }).catch(function (err) {
        var list = document.getElementById("message-list");
        if (list) list.innerHTML = '<p class="banner" role="alert">' + escapeHtml(err.message) + "</p>";
      });
      return;
    }
    var posts = content.posts || [];
    shell(
      '<div class="actions" style="justify-content:space-between"><div><h1>Journal</h1><p class="sub">Published notes show on the homepage and at /blog.</p></div>' +
      '<button class="primary" type="button" id="new-note">New note</button></div>' +
      (message ? '<p class="ok">' + escapeHtml(message) + "</p>" : "") +
      (posts.length ? '<div class="list">' + posts.map(function (post) {
        return '<a class="item" href="#edit" data-id="' + escapeHtml(post.id) + '"><span><strong>' + escapeHtml(post.title) +
          "</strong><span>" + escapeHtml(post.date) + " · " + escapeHtml(post.category) + "</span></span>" +
          '<span class="status' + (post.published ? " live" : "") + '">' + (post.published ? "On the site" : "Hidden") + "</span></a>";
      }).join("") + "</div>" : '<p class="sub">No notes yet.</p>')
    );
    document.getElementById("new-note").addEventListener("click", function () {
      editing = null;
      view = "edit";
      message = "";
      render();
    });
    app.querySelectorAll("[data-id]").forEach(function (link) {
      link.addEventListener("click", function (event) {
        event.preventDefault();
        editing = content.posts.find(function (post) { return post.id === link.getAttribute("data-id"); });
        view = "edit";
        message = "";
        render();
      });
    });
  }

  function loadJournal() {
    return api("/api/admin/content").then(function (body) {
      content = body;
      view = "journal";
      editing = null;
      error = "";
      render();
    });
  }

  function load() {
    return api("/api/admin/content").then(function (body) {
      content = body;
      view = "contact";
      render();
    });
  }

  api("/api/session").then(function (body) {
    if (body.ok) return load();
    renderLogin();
  }).catch(function () { renderLogin(); });
})();
