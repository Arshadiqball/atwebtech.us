function fetchApi(path, options) {
  return fetch(path, options).then(function (response) {
    if (response.status !== 404 || path.indexOf("/api/") !== 0) return response;
    return fetch("/api.php?r=" + encodeURIComponent(path.slice(5)), options);
  });
}

(function () {
  var nav = document.getElementById("site-nav");
  var menu = document.getElementById("site-menu");
  var backdrop = document.querySelector(".menu-backdrop");
  var opener = document.querySelector(".burger");
  var closer = document.querySelector(".menu-close");

  function setMenu(open) {
    menu.hidden = !open;
    backdrop.hidden = !open;
    opener.setAttribute("aria-expanded", open ? "true" : "false");
    opener.setAttribute("aria-label", open ? "Close Menu" : "Open Menu");
    document.body.style.overflow = open ? "hidden" : "";
  }

  opener.addEventListener("click", function () { setMenu(menu.hidden); });
  closer.addEventListener("click", function () { setMenu(false); });
  backdrop.addEventListener("click", function () { setMenu(false); });
  menu.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () { setMenu(false); });
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape" && !menu.hidden) setMenu(false);
  });

  document.querySelector(".theme").addEventListener("click", function () {
    var dark = document.documentElement.classList.toggle("dark");
    localStorage.setItem("alphaai-theme", dark ? "dark" : "light");
  });

  function onScroll() {
    nav.classList.toggle("is-scrolled", window.scrollY > 50);
  }
  onScroll();
  window.addEventListener("scroll", onScroll, { passive: true });

  var newsletter = document.getElementById("newsletter");
  if (newsletter) {
    newsletter.addEventListener("submit", function (event) {
      event.preventDefault();
      var status = newsletter.parentElement.querySelector(".news-status");
      var button = newsletter.querySelector("button");
      button.disabled = true;
      fetchApi("/api/messages", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ kind: "newsletter", email: newsletter.email.value.trim() })
      }).then(function (response) {
        return response.json().catch(function () { return {}; }).then(function (body) {
          if (!response.ok) throw new Error(body.error || "Could not subscribe.");
          status.textContent = "You're on the list.";
          newsletter.reset();
        });
      }).catch(function (err) {
        status.textContent = err.message;
      }).finally(function () {
        button.disabled = false;
      });
    });
  }

  var form = document.getElementById("quote");
  if (!form) return;
  var button = form.querySelector("button[type='submit']");
  var status = form.querySelector(".form-status");
  var fields = form.querySelector(".fields");
  var planNote = form.querySelector(".plan-note");
  var planNames = { starter: "Starter", pro: "Pro", enterprise: "Enterprise" };
  var params = new URLSearchParams(location.search);
  var chosen = (params.get("plan") || "").toLowerCase();
  var billing = params.get("billing") === "yearly" ? "yearly" : "monthly";

  function showPlan() {
    var name = planNames[form.plan.value];
    if (!name) {
      planNote.hidden = true;
      planNote.textContent = "";
      return;
    }
    planNote.hidden = false;
    planNote.textContent = form.plan.value === chosen && billing === "yearly"
      ? "You selected the " + name + " plan, billed yearly."
      : "You selected the " + name + " plan.";
  }

  if (planNames[chosen]) {
    form.plan.value = chosen;
    showPlan();
    form.scrollIntoView({ block: "center" });
  }
  form.plan.addEventListener("change", showPlan);

  form.addEventListener("submit", function (event) {
    event.preventDefault();
    status.className = "form-status";
    status.textContent = "";
    button.disabled = true;
    var payload = {
      kind: "contact",
      firstName: form.firstName.value.trim(),
      lastName: form.lastName.value.trim(),
      email: form.email.value.trim(),
      phone: form.phone.value.trim(),
      message: form.message.value.trim(),
      plan: form.plan.value,
      billing: form.plan.value ? billing : "",
      privacy: form.privacy.checked
    };
    fetchApi("/api/messages", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) throw new Error(body.error || "Could not send that.");
        return body;
      });
    }).then(function (body) {
      fields.hidden = true;
      status.className = "form-status ok";
      status.hidden = false;
      status.textContent = body.emailed
        ? "Received. We emailed the team, and your request is in the admin panel."
        : "Received and saved in the admin panel. Email was not sent yet: " + (body.mailError || "add the mailbox under Admin → Mail.");
    }).catch(function (err) {
      status.className = "form-status bad";
      status.textContent = err.message;
      button.disabled = false;
    });
  });
})();
