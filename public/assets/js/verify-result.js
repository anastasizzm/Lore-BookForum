"use strict";

(function () {
  var root = document.querySelector('[data-verify-result]');
  if (!root) return;
  if (root.dataset.verifySuccess === '1') return;

  var retryBtn = root.querySelector('[data-verify-retry]');
  if (!retryBtn) return;

  var apiUrl     = retryBtn.dataset.resendUrl  || '/api/verify/resend';
  var messageUrl = retryBtn.dataset.messageUrl || '/message';

  retryBtn.addEventListener('click', function () {
    retryBtn.disabled = true;
    retryBtn.textContent = 'Sending...';

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
        if (!response.ok) throw new Error('Request failed');
        return response.json().catch(function () { return {}; });
      })
      .then(function () {
        window.location.href = messageUrl;
      })
      .catch(function () {
        retryBtn.disabled = false;
        retryBtn.textContent = 'Resend verification';
      });
  });
})();