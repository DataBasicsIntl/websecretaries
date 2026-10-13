// WebSecretaries.com — small site behaviours
(function () {
  // Mobile menu
  var toggle = document.querySelector(".nav-toggle");
  var nav = document.getElementById("site-nav");
  if (toggle && nav) {
    toggle.addEventListener("click", function () {
      var open = nav.classList.toggle("open");
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    });
  }

  // Copyright year
  var year = document.getElementById("year");
  if (year) year.textContent = new Date().getFullYear();

  // Contact form: file picker + submission
  var form = document.getElementById("contact-form");
  if (!form) return;

  var MAX_TOTAL_MB = 20;
  var MAX_FILES = 10;
  var input = document.getElementById("attachments");
  var drop = document.getElementById("file-drop");
  var list = document.getElementById("file-list");
  var status = document.getElementById("form-status");

  function totalBytes() {
    var t = 0;
    for (var i = 0; i < input.files.length; i++) t += input.files[i].size;
    return t;
  }

  function showFiles() {
    list.innerHTML = "";
    for (var i = 0; i < input.files.length; i++) {
      var f = input.files[i];
      var li = document.createElement("li");
      li.textContent = f.name + " (" + (f.size / 1048576).toFixed(1) + " MB)";
      list.appendChild(li);
    }
    if (input.files.length > MAX_FILES) {
      var many = document.createElement("li");
      many.className = "err";
      many.textContent = "Please choose no more than " + MAX_FILES + " files.";
      list.appendChild(many);
    }
    if (totalBytes() > MAX_TOTAL_MB * 1048576) {
      var warn = document.createElement("li");
      warn.className = "err";
      warn.textContent = "Files total more than " + MAX_TOTAL_MB + " MB. Please remove some, or email large files to info@WebSecretaries.com.";
      list.appendChild(warn);
    }
  }

  input.addEventListener("change", showFiles);
  ["dragenter", "dragover"].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add("drag"); });
  });
  ["dragleave", "drop"].forEach(function (ev) {
    drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove("drag"); });
  });
  drop.addEventListener("drop", function (e) {
    if (e.dataTransfer && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      showFiles();
    }
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    status.style.color = "";

    if (input.files.length > MAX_FILES || totalBytes() > MAX_TOTAL_MB * 1048576) {
      status.style.color = "var(--red)";
      status.textContent = "Your files are too large to send here. Please email them to info@WebSecretaries.com.";
      return;
    }

    var button = form.querySelector("button[type=submit]");
    button.disabled = true;
    status.textContent = "Sending…";

    fetch(form.action, { method: "POST", body: new FormData(form), headers: { Accept: "application/json" } })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok || !data.ok) throw { fromServer: true, message: data.message };
          form.reset();
          list.innerHTML = "";
          status.textContent = data.message || "Thank you! Your request has been sent. We'll be in touch shortly.";
        });
      })
      .catch(function (err) {
        status.style.color = "var(--red)";
        status.textContent = (err && err.fromServer && err.message)
          ? err.message
          : "Sorry, something went wrong. Please email info@WebSecretaries.com or call +1 (404) 721-2620.";
      })
      .finally(function () { button.disabled = false; });
  });
})();
