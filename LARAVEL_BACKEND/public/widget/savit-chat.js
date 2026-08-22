(function () {
  "use strict";

  var script = document.currentScript;
  if (!script) return;

  var companyId = script.getAttribute("data-company-id");
  var widgetToken = script.getAttribute("data-widget-token");
  var apiBase = script.getAttribute("data-api-base") || "";

  if (!companyId || !widgetToken) {
    console.warn("[SAVIT Chat] Missing data-company-id or data-widget-token");
    return;
  }

  var visitorId = localStorage.getItem("savit_visitor_id");
  if (!visitorId) {
    visitorId = "v_" + Math.random().toString(36).slice(2, 12);
    localStorage.setItem("savit_visitor_id", visitorId);
  }

  var root = document.createElement("div");
  root.id = "savit-chat-root";
  root.style.cssText =
    "position:fixed;bottom:20px;right:20px;z-index:99999;font-family:system-ui,sans-serif;font-size:14px;";
  document.body.appendChild(root);

  var panelOpen = false;
  var config = {
    companyName: "Chat",
    companyLogo: null,
    primaryColor: "#2563eb",
    accentColor: "#2563eb",
    greeting: "Hi! How can we help?",
  };

  function apiUrl(path) {
    return apiBase.replace(/\/$/, "") + path;
  }

  function fetchConfig(cb) {
    fetch(
      apiUrl(
        "/api/public/web-widget/config?companyId=" +
          encodeURIComponent(companyId) +
          "&widgetToken=" +
          encodeURIComponent(widgetToken)
      )
    )
      .then(function (r) {
        return r.json();
      })
      .then(function (data) {
        config.companyName = data.companyName || config.companyName;
        config.companyLogo = data.companyLogo || null;
        config.primaryColor = data.primaryColor || config.primaryColor;
        config.accentColor = data.accentColor || config.accentColor;
        config.greeting = data.greeting || config.greeting;
        cb();
      })
      .catch(function () {
        cb();
      });
  }

  function render() {
    root.innerHTML = "";
    var primary = config.primaryColor || "#2563eb";

    var toggle = document.createElement("button");
    toggle.textContent = panelOpen ? "✕" : "💬";
    toggle.style.cssText =
      "width:54px;height:54px;border-radius:50%;border:none;background:" +
      primary +
      ";color:#fff;font-size:22px;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.22);display:flex;align-items:center;justify-content:center;transition:transform .15s ease;";
    toggle.onmouseover = function () {
      toggle.style.transform = "scale(1.06)";
    };
    toggle.onmouseout = function () {
      toggle.style.transform = "scale(1)";
    };
    toggle.onclick = function () {
      panelOpen = !panelOpen;
      render();
    };
    root.appendChild(toggle);

    if (!panelOpen) return;

    var panel = document.createElement("div");
    panel.style.cssText =
      "position:absolute;bottom:68px;right:0;width:340px;max-height:460px;background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.18);display:flex;flex-direction:column;overflow:hidden;border:1px solid rgba(0,0,0,.08);";

    var header = document.createElement("div");
    header.style.cssText =
      "padding:14px 16px;font-weight:600;background:" +
      primary +
      ";color:#fff;display:flex;align-items:center;gap:10px;";

    if (config.companyLogo) {
      var logoImg = document.createElement("img");
      logoImg.src = config.companyLogo;
      logoImg.alt = config.companyName;
      logoImg.style.cssText =
        "width:28px;height:28px;border-radius:50%;object-fit:cover;background:#fff;";
      header.appendChild(logoImg);
    }

    var titleSpan = document.createElement("span");
    titleSpan.textContent = config.companyName;
    titleSpan.style.cssText = "flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;";
    header.appendChild(titleSpan);

    panel.appendChild(header);

    var log = document.createElement("div");
    log.id = "savit-chat-log";
    log.style.cssText =
      "flex:1;overflow-y:auto;padding:14px;display:flex;flex-direction:column;gap:10px;background:#f8fafc;";
    var botMsg = document.createElement("div");
    botMsg.textContent = config.greeting;
    botMsg.style.cssText =
      "align-self:flex-start;background:#fff;border:1px solid #e2e8f0;padding:9px 12px;border-radius:12px;border-bottom-left-radius:3px;max-width:85%;color:#1e293b;box-shadow:0 1px 3px rgba(0,0,0,.04);line-height:1.4;";
    log.appendChild(botMsg);
    panel.appendChild(log);

    var form = document.createElement("form");
    form.style.cssText =
      "display:flex;border-top:1px solid #e2e8f0;padding:10px;gap:8px;background:#fff;";
    var input = document.createElement("input");
    input.type = "text";
    input.placeholder = "Type a message…";
    input.style.cssText =
      "flex:1;border:1px solid #cbd5e1;border-radius:10px;padding:9px 12px;font-size:14px;outline:none;";
    var send = document.createElement("button");
    send.type = "submit";
    send.textContent = "Send";
    send.style.cssText =
      "background:" +
      primary +
      ";color:#fff;border:none;border-radius:10px;padding:9px 14px;font-weight:500;cursor:pointer;";
    form.appendChild(input);
    form.appendChild(send);

    form.onsubmit = function (e) {
      e.preventDefault();
      var text = input.value.trim();
      if (!text) return;
      input.value = "";
      appendMsg(log, text, "user");
      send.disabled = true;
      fetch(apiUrl("/api/public/web-widget/message"), {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json" },
        body: JSON.stringify({
          companyId: parseInt(companyId, 10),
          widgetToken: widgetToken,
          visitorId: visitorId,
          message: text,
        }),
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (data) {
          if (data.reply) appendMsg(log, data.reply, "bot");
        })
        .catch(function () {
          appendMsg(log, "Sorry, something went wrong. Please try again.", "bot");
        })
        .finally(function () {
          send.disabled = false;
        });
    };

    panel.appendChild(form);
    root.appendChild(panel);
  }

  function appendMsg(log, text, role) {
    var primary = config.primaryColor || "#2563eb";
    var el = document.createElement("div");
    el.textContent = text;
    el.style.cssText =
      role === "user"
        ? "align-self:flex-end;background:" +
          primary +
          ";color:#fff;padding:9px 12px;border-radius:12px;border-bottom-right-radius:3px;max-width:85%;line-height:1.4;box-shadow:0 1px 3px rgba(0,0,0,.08);"
        : "align-self:flex-start;background:#fff;border:1px solid #e2e8f0;color:#1e293b;padding:9px 12px;border-radius:12px;border-bottom-left-radius:3px;max-width:85%;line-height:1.4;box-shadow:0 1px 3px rgba(0,0,0,.04);";
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  fetchConfig(render);
})();
