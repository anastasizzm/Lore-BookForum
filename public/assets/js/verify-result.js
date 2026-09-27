"use strict";

/* ============================================
   Verify result page
   - success: ссылка на /login (рендерится в шаблоне)
   - error: кнопка "Resend verification"
       * fetch POST на API
       * успех -> редирект на messageUrl
       * ошибка -> показать плашку
   ============================================ */
(function () {
  var root = document.querySelector('[data-verify-result]');
  if (!root) return;

  var isSuccess = root.dataset.verifySuccess === '1';
  if (isSuccess) return;

  var retryBtn   = root.querySelector('[data-verify-retry]');
  var messagesEl = root.querySelector('[data-verify-messages]');
  if (!retryBtn) return;

  var apiUrl     = retryBtn.dataset.resendUrl  || '/api/verify/resend';
  var messageUrl = retryBtn.dataset.messageUrl || '/message';

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

  retryBtn.addEventListener('click', function () {
    retryBtn.disabled = true;
    retryBtn.textContent = 'Sending...';
    clearMessage();

    fetch(apiUrl, {
      method: 'POST',
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({})
    })
      .then(function (response) {
        if (!response.ok) {
          return response.json().catch(function () { return {}; }).then(function (data) {
            var err = (data && data.message) || 'Something went wrong. Please try again.';
            throw new Error(err);
          });
        }
        return response.json().catch(function () { return {}; });
      })
      .then(function () {
        // успех → редирект
        window.location.href = messageUrl;
      })
      .catch(function (err) {
        // ошибка → плашка
        showMessage(err.message || 'Request failed', 'error');
        retryBtn.disabled = false;
        retryBtn.textContent = 'Resend verification';
      });
  });
})();