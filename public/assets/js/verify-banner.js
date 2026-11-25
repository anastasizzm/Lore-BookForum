"use strict";

/* Плашка «подтвердите почту»: повторная отправка письма. Разметка — partials/verify-banner.php. */
(function () {
  var banner = document.querySelector('[data-verify-banner]');
  if (!banner) return;

  var btn = banner.querySelector('[data-verify-banner-send]');
  if (!btn) return;

  var url = btn.getAttribute('data-url');
  var defaultLabel = btn.textContent;
  var sending = false;

  btn.addEventListener('click', async function () {
    if (sending) return;
    sending = true;
    btn.disabled = true;
    btn.textContent = LoreI18n.t('js.verify_sending');

    try {
      var res = await LoreCsrf.fetch(url, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (res.ok) {
        Messages.show(LoreI18n.t('js.verify_sent'), { type: 'success' });
      } else {
        await Messages.fail(res);
      }
    } catch (e) {
      Messages.show(LoreI18n.t('errors.common.network_error'), { type: 'error' });
    } finally {
      sending = false;
      btn.disabled = false;
      btn.textContent = defaultLabel;
    }
  });
})();
