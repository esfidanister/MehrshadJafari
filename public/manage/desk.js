(function () {
  function copyText(value, button) {
    var previous = button.textContent;
    var done = function () {
      button.textContent = "کپی شد";
      window.setTimeout(function () { button.textContent = previous; }, 1600);
    };
    var fallback = function () {
      var input = document.createElement("textarea");
      input.value = value;
      input.setAttribute("readonly", "");
      input.style.position = "fixed";
      input.style.insetInlineStart = "0";
      input.style.top = "0";
      document.body.appendChild(input);
      input.select();
      var ok = false;
      try { ok = document.execCommand("copy"); } catch (error) { ok = false; }
      input.remove();
      if (ok) done();
    };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(value).then(done).catch(fallback);
    } else {
      fallback();
    }
  }

  document.querySelectorAll("[data-copy]").forEach(function (button) {
    button.addEventListener("click", function () {
      copyText(button.getAttribute("data-copy") || "", button);
    });
  });
  document.querySelectorAll("[data-copy-from]").forEach(function (button) {
    button.addEventListener("click", function () {
      var source = document.getElementById(button.getAttribute("data-copy-from") || "");
      copyText(source ? source.value : "", button);
    });
  });

  function paintMeter(field) {
    var note = field.parentElement ? field.parentElement.nextElementSibling : null;
    if (!note || !note.classList.contains("meter")) return;
    var kind = field.getAttribute("data-meter");
    var count = field.value.trim().length;
    if (count === 0) {
      note.textContent = field.getAttribute("data-empty") || "خالی یعنی عنوان فعلی سایت بماند.";
      note.className = "meter";
      return;
    }
    var good = kind === "title" ? count >= 45 && count <= 65 : count >= 110 && count <= 170;
    note.textContent = count + " حرف" + (good ? " — برای گوگل مناسب است." : " — بهتر است عنوان حدود ۵۰ تا ۶۰ و شرح حدود ۱۲۰ تا ۱۶۰ حرف باشد.");
    note.className = good ? "meter good" : "meter";
  }

  document.querySelectorAll("[data-meter]").forEach(function (field) {
    paintMeter(field);
    field.addEventListener("input", function () { paintMeter(field); });
  });

  var active = document.getElementById("body_fa");
  document.querySelectorAll("textarea.paper").forEach(function (area) {
    area.addEventListener("focus", function () { active = area; });
  });

  function insert(before, after) {
    if (!active) return;
    var start = active.selectionStart;
    var end = active.selectionEnd;
    var selected = active.value.slice(start, end);
    var next = before + selected + (after || "");
    active.setRangeText(next, start, end, "end");
    active.focus();
  }

  document.querySelectorAll("[data-insert]").forEach(function (button) {
    button.addEventListener("click", function () {
      var prefix = button.getAttribute("data-insert") || "";
      insert((active && active.value && !active.value.endsWith("\n") ? "\n" : "") + prefix, "");
    });
  });
  document.querySelectorAll("[data-wrap]").forEach(function (button) {
    button.addEventListener("click", function () {
      var mark = button.getAttribute("data-wrap") || "";
      insert(mark, mark);
    });
  });

  function escapeHtml(value) {
    return value.replace(/[&<>]/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;" }[char];
    });
  }

  function inline(value) {
    var safe = escapeHtml(value);
    safe = safe.replace(/\*\*([^*]+)\*\*/g, "<strong>$1</strong>");
    safe = safe.replace(/\[([^\]]+)\]\((https:\/\/[^)\s]+)\)/g, '<a href="$2">$1</a>');
    return safe;
  }

  var previewButton = document.querySelector("[data-preview]");
  var preview = document.getElementById("preview");
  if (previewButton && preview) {
    previewButton.addEventListener("click", function () {
      var source = document.getElementById(previewButton.getAttribute("data-preview") || "");
      var text = source ? source.value.trim() : "";
      if (text === "") {
        preview.hidden = false;
        preview.innerHTML = "<p>متنی برای پیش‌نمایش نیست.</p>";
        return;
      }
      var html = "";
      text.split(/\n{2,}/).forEach(function (block) {
        var lines = block.split("\n");
        if (lines[0].indexOf("### ") === 0) {
          html += "<h3>" + inline(lines[0].slice(4)) + "</h3>";
          return;
        }
        if (lines[0].indexOf("## ") === 0) {
          html += "<h2>" + inline(lines[0].slice(3)) + "</h2>";
          return;
        }
        if (lines.every(function (line) { return line.indexOf("- ") === 0; })) {
          html += "<ul>" + lines.map(function (line) { return "<li>" + inline(line.slice(2)) + "</li>"; }).join("") + "</ul>";
          return;
        }
        html += "<p>" + lines.map(inline).join("<br>") + "</p>";
      });
      preview.hidden = false;
      preview.innerHTML = html;
    });
  }

  var title = document.querySelector("[data-slug-from]");
  var slug = document.getElementById("slug");
  if (title && slug && slug.value.trim() === "") {
    title.addEventListener("input", function () {
      if (slug.dataset.touched === "1") return;
      slug.value = title.value.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
    });
    slug.addEventListener("input", function () { slug.dataset.touched = "1"; });
  }

  document.querySelectorAll("form[data-confirm]").forEach(function (form) {
    form.addEventListener("submit", function (event) {
      var message = form.getAttribute("data-confirm") || "";
      if (message && !window.confirm(message)) event.preventDefault();
    });
  });
})();
