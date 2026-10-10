"use strict";

(function () {
  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  var root = document.querySelector('[data-verify-result]');
  if (!root) return;
  if (root.dataset.verifySuccess === '1') return;

  var form = root.querySelector('[data-verify-resend]');
  if (!form) return;

  var retryBtn   = form.querySelector('[data-verify-retry]');
  var messagesEl = root.querySelector('[data-verify-messages]');
  if (!retryBtn) return;

  var defaultLabel = retryBtn.textContent.trim();
  var COOLDOWN_SECONDS = 30;
  var sending = false;

  function showMessage(text, type) {
    type = type || 'error';
    if (!messagesEl) {
      if (window.Messages) window.Messages.show(text, { type: type });
      else alert(text);
      return;
    }
    messagesEl.replaceChildren();

    var msg = document.createElement('div');
    msg.className = 'message message--' + type;
    msg.setAttribute('role', type === 'error' ? 'alert' : 'status');
    var body = document.createElement('div');
    body.className = 'message__body';
    body.textContent = text;
    msg.appendChild(body);
    messagesEl.appendChild(msg);
    messagesEl.hidden = false;
  }

  function clearMessage() {
    if (!messagesEl) return;
    messagesEl.hidden = true;
    messagesEl.replaceChildren();
  }

  function ensureToken() {
    var input = form.querySelector('input[name="token"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'token';
      form.appendChild(input);
    }
    if (!input.value) {
      var match = window.location.pathname.match(/\/verify\/([^/?#]+)/);
      if (match) {
        try { input.value = decodeURIComponent(match[1]); }
        catch (e) { input.value = match[1]; }
      }
    }
    return input.value;
  }

  function csrfValue() {
    var fromCookie = window.LoreCsrf ? window.LoreCsrf.token() : '';
    if (fromCookie) return fromCookie;
    var el = form.querySelector('input[name="_token"]');
    return el ? el.value : '';
  }

  function payloadText(data) {
    if (!data || typeof data !== 'object') return '';
    if (typeof data.message === 'string' && data.message) return data.message;
    if (typeof data.error === 'string' && data.error) return data.error;
    if (data.error && typeof data.error.message === 'string') return data.error.message;
    return '';
  }

  function errorText(status, data, raw) {
    var fallback = t('js.verify_failed', { status: status });
    if (window.Messages) return window.Messages.describe(status, data, raw, fallback);
    if (/^\s*</.test(raw)) return t('js.server_error');
    return payloadText(data) || fallback;
  }

  function startCooldown(seconds) {
    var left = seconds;
    retryBtn.disabled = true;
    (function tick() {
      if (left <= 0) {
        retryBtn.disabled = false;
        retryBtn.textContent = defaultLabel;
        return;
      }
      retryBtn.textContent = defaultLabel + ' (' + left + 's)';
      left--;
      window.setTimeout(tick, 1000);
    })();
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (sending || retryBtn.disabled) return;

    var token = ensureToken();
    clearMessage();

    if (!token) { showMessage(t('js.verify_token_missing'), 'error'); return; }

    sending = true;
    retryBtn.disabled = true;
    retryBtn.textContent = t('js.verify_sending');
    var sent = false;

    try {
      var body = new URLSearchParams(new FormData(form));
      var headers = {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      };
      var csrf = csrfValue();
      if (csrf) {
        headers['X-CSRF-Token'] = csrf;
        body.set('_token', csrf);
      }

      var response = await fetch(form.action, {
        method: 'POST',
        credentials: 'include',
        headers: headers,
        body: body.toString()
      });

      var raw = '';
      try { raw = await response.text(); } catch (e) { raw = ''; }
      var data = null;
      try { data = raw ? JSON.parse(raw) : null; } catch (e) { data = null; }

      var isHtml = /^\s*</.test(raw);

      if (response.ok && !isHtml) {
        sent = true;
        showMessage(t('js.verify_sent'), 'success');
      } else {
        showMessage(errorText(response.status, data, raw), response.status === 409 ? 'info' : 'error');
      }
    } catch (e) {
      showMessage(t('js.network_error'), 'error');
    } finally {
      sending = false;
      if (sent) startCooldown(COOLDOWN_SECONDS);
      else { retryBtn.disabled = false; retryBtn.textContent = defaultLabel; }
    }
  });
})();