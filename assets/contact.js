(function () {
  var form = document.getElementById("quote");
  if (!form) return;
  var button = form.querySelector("button[type='submit']");
  var status = form.querySelector(".form-status");
  var fields = form.querySelector(".fields");

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
      privacy: form.privacy.checked
    };
    fetch("/api/messages", {
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
