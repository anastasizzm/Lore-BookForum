"use strict";

/* ============================================
   Verify result page
   - success: ссылка на /login (рендерится в шаблоне)
   - error: форма "Resend verification"
       * POST на URL из action формы (выводится через $view->url() в шаблоне)
       * тело — поля формы: token (из адреса страницы) + _token (CSRF)
       * успех -> зелёная плашка на странице
       * ошибка -> красная плашка
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

  function showMessage(text, type) {
    if (!messagesEl) {
      // fallback, если плашки нет в разметке
      alert(text);
      return;
    }
    messagesEl.hidden = false;
    messagesEl.innerHTML =
      '<div class="message message--' + (type || 'error') + '">' +
        '<div class="message__body">' + text + '</div>' +
      '</div>';
  }

  function clearMessage() {
    if (!messagesEl) return;
    messagesEl.hidden = true;
    messagesEl.innerHTML = '';
  }

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
      if (match) input.value = decodeURIComponent(match[1]);
    }
    return input.value;
  }

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    if (retryBtn.disabled) return;

    ensureToken();
    clearMessage();
    retryBtn.disabled = true;
    retryBtn.textContent = 'Sending...';

    var headers = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
    };
    var csrf = form.querySelector('input[name="_token"]');
    if (csrf && csrf.value) headers['X-CSRF-Token'] = csrf.value;

    fetch(form.action, {
      method: 'POST',
      credentials: 'include',
      headers: headers,
      // поля формы: _token (CSRF) + token (из url)
      body: new URLSearchParams(new FormData(form)).toString()
    })
      .then(function (response) {
        var isJson = (response.headers.get('content-type') || '')
          .toLowerCase()
          .indexOf('application/json') !== -1;

        if (!response.ok || !isJson) {
          // ответ не JSON (например, HTML с ошибкой) тоже считаем ошибкой
          return response.text().catch(function () { return ''; }).then(function (raw) {
            var data = {};
            try { data = JSON.parse(raw) || {}; } catch (e) { /* not JSON */ }
            var err = data.error || data;
            throw new Error(err.message || 'Something went wrong. Please try again.');
          });
        }
        return response.json().catch(function () { return {}; });
      })
      .then(function () {
        retryBtn.disabled = false;
        retryBtn.textContent = defaultLabel;
        showMessage('A new verification link has been sent to your email. Check your inbox.', 'success');
      })
      .catch(function (err) {
        // ошибка -> плашка
        showMessage(err.message || 'Request failed', 'error');
        retryBtn.disabled = false;
        retryBtn.textContent = defaultLabel;
      });
  });
})();
