"use strict";

/* ============================================
   Verify result page
   - success: ссылка на /login (рендерится в шаблоне)
   - error: форма "Resend verification"
       * POST на URL из action формы (выводится через $view->url() в шаблоне)
       * тело — поля формы: token (из адреса страницы) + _token (CSRF)
       * успех (любой 2xx, не HTML) -> зелёная плашка + пауза перед повтором
       * ошибка -> красная плашка с текстом из ответа API
         (409 «уже подтверждён» -> информационная)
   Тексты ошибок разбирает Messages.describe (messages.js), если он подключён.
   ============================================ */
(function () {
  var root = document.querySelector('[data-verify-result]');
  if (!root) return;

  var isSuccess = root.dataset.verifySuccess === '1';
  if (isSuccess) return;

  var form = root.querySelector('[data-verify-resend]');
  if (!form) return;

  var retryBtn   = form.querySelector('[data-verify-retry]');
  var messagesEl = root.querySelector('[data-verify-messages]');
  if (!retryBtn) return;

  var defaultLabel = retryBtn.textContent.trim();
  var COOLDOWN_SECONDS = 30;   // пауза между отправками, чтобы не спамить почту
  var sending = false;

  /* ---------- Плашка ---------- */
  function showMessage(text, type) {
    type = type || 'error';

    if (!messagesEl) {
      // fallback, если плашки нет в разметке
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
    body.textContent = text;            // textContent: текст сервера не выполняется как HTML

    msg.appendChild(body);
    messagesEl.appendChild(msg);
    messagesEl.hidden = false;
  }

  function clearMessage() {
    if (!messagesEl) return;
    messagesEl.hidden = true;
    messagesEl.replaceChildren();
  }

  /* ---------- Токен ---------- */
  // token в скрытое поле кладётся в шаблоне из URL; на случай, если поле
  // пустое, достраиваем его из адреса страницы (/auth/verify/{token}).
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

  /* ---------- CSRF ---------- */
  // cookie — источник правды (её сравнивает бэк, см. app.js); поле формы — запасной вариант
  function csrfValue() {
    var fromCookie = window.LoreCsrf ? window.LoreCsrf.token() : '';
    if (fromCookie) return fromCookie;
    var el = form.querySelector('input[name="_token"]');
    return el ? el.value : '';
  }

  /* ---------- Текст ошибки из ответа ---------- */
  function payloadText(data) {
    if (!data || typeof data !== 'object') return '';
    if (typeof data.message === 'string' && data.message) return data.message;
    if (typeof data.error === 'string' && data.error) return data.error;
    if (data.error && typeof data.error.message === 'string') return data.error.message;
    return '';
  }

  function errorText(status, data, raw) {
    var fallback = 'Could not send the email (HTTP ' + status + ').';
    if (window.Messages) return window.Messages.describe(status, data, raw, fallback);
    if (/^\s*</.test(raw)) return 'Server error. Please try again later.';
    return payloadText(data) || fallback;
  }

  /* ---------- Пауза после успешной отправки ---------- */
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

  /* ---------- Отправка ---------- */
  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (sending || retryBtn.disabled) return;

    var token = ensureToken();
    clearMessage();

    if (!token) {
      showMessage('The verification link is incomplete: the token is missing.', 'error');
      return;
    }

    sending = true;
    retryBtn.disabled = true;
    retryBtn.textContent = 'Sending...';
    var sent = false;

    try {
      // поля формы: _token (CSRF) + token (из url)
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

      // Успех: любой 2xx. Пустое тело (204 / jsonEmpty()) — это успех;
      // HTML вместо JSON (PHP-ошибка, страница-заглушка) — нет.
      var isHtml = /^\s*</.test(raw);

      if (response.ok && !isHtml) {
        sent = true;
        showMessage('A new verification link has been sent to your email. Check your inbox.', 'success');
      } else {
        // 409 «уже подтверждён» — не ошибка пользователя, показываем как информацию
        showMessage(errorText(response.status, data, raw), response.status === 409 ? 'info' : 'error');
      }
    } catch (e) {
      showMessage('Network error. Try again.', 'error');
    } finally {
      sending = false;
      if (sent) {
        startCooldown(COOLDOWN_SECONDS);
      } else {
        retryBtn.disabled = false;
        retryBtn.textContent = defaultLabel;
      }
    }
  });
})();