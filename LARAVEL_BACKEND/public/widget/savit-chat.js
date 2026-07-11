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
  var config = { companyName: "Chat", greeting: "Hi! How can we help?" };

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
        config.greeting = data.greeting || config.greeting;
        cb();
      })
      .catch(function () {
        cb();
      });
  }

  function render() {
    root.innerHTML = "";
    var toggle = document.createElement("button");
    toggle.textContent = panelOpen ? "✕" : "💬";
    toggle.style.cssText =
      "width:52px;height:52px;border-radius:50%;border:none;background:#2563eb;color:#fff;font-size:20px;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2);";
    toggle.onclick = function () {
      panelOpen = !panelOpen;
      render();
    };
    root.appendChild(toggle);

    if (!panelOpen) return;

    var panel = document.createElement("div");
    panel.style.cssText =
      "position:absolute;bottom:64px;right:0;width:320px;max-height:420px;background:#fff;border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,.15);display:flex;flex-direction:column;overflow:hidden;border:1px solid #e5e7eb;";

    var header = document.createElement("div");
    header.textContent = config.companyName;
    header.style.cssText = "padding:12px 14px;font-weight:600;background:#f8fafc;border-bottom:1px solid #e5e7eb;";
    panel.appendChild(header);

    var log = document.createElement("div");
    log.id = "savit-chat-log";
    log.style.cssText = "flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px;";
    var botMsg = document.createElement("div");
    botMsg.textContent = config.greeting;
    botMsg.style.cssText = "align-self:flex-start;background:#f1f5f9;padding:8px 10px;border-radius:10px;max-width:85%;";
    log.appendChild(botMsg);
    panel.appendChild(log);

    var form = document.createElement("form");
    form.style.cssText = "display:flex;border-top:1px solid #e5e7eb;padding:8px;gap:6px;";
    var input = document.createElement("input");
    input.type = "text";
    input.placeholder = "Type a message…";
    input.style.cssText = "flex:1;border:1px solid #d1d5db;border-radius:8px;padding:8px;font-size:14px;";
    var send = document.createElement("button");
    send.type = "submit";
    send.textContent = "Send";
    send.style.cssText = "background:#2563eb;color:#fff;border:none;border-radius:8px;padding:8px 12px;cursor:pointer;";
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
    var el = document.createElement("div");
    el.textContent = text;
    el.style.cssText =
      role === "user"
        ? "align-self:flex-end;background:#2563eb;color:#fff;padding:8px 10px;border-radius:10px;max-width:85%;"
        : "align-self:flex-start;background:#f1f5f9;padding:8px 10px;border-radius:10px;max-width:85%;";
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
  }

  fetchConfig(render);
})();
