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

  // Tell the form handler which site to send the visitor back to (live site, or local preview)
  var ret = document.getElementById("return-to");
  if (ret) ret.value = window.location.origin + window.location.pathname.replace(/[^\/]*$/, "");

  // Show an error passed back from the form handler (contact.html?error=...)
  var err = new URLSearchParams(window.location.search).get("error");
  if (err) {
    status.style.color = "var(--red)";
    status.textContent = err;
  }

  // The form submits as a normal page POST; the handler redirects to thanks.html
  form.addEventListener("submit", function (e) {
    status.style.color = "";
    if (input.files.length > MAX_FILES || totalBytes() > MAX_TOTAL_MB * 1048576) {
      e.preventDefault();
      status.style.color = "var(--red)";
      status.textContent = "Your files are too large to send here (" + MAX_FILES + " files / " + MAX_TOTAL_MB + " MB max). Please email them to info@WebSecretaries.com.";
      return;
    }
    form.querySelector("button[type=submit]").disabled = true;
    status.textContent = input.files.length ? "Sending your request and files… please wait." : "Sending…";
  });

  // If the visitor comes back with the browser's Back button, re-enable the button
  window.addEventListener("pageshow", function () {
    form.querySelector("button[type=submit]").disabled = false;
  });
})();
