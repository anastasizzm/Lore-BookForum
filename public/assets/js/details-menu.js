/**
 * details-menu.js — меню «три точки» в шапке book/article details
 * (src/partials/details-menu.php).
 *
 * Две задачи:
 *   1. открытие/закрытие выпадашки (клик по иконке, клик мимо, Escape)
 *      — тот же приём, что у меню комментариев в comments.js, но со
 *      своими data-атрибутами, чтобы не смешивать с комментариями;
 *   2. удаление публикации: ConfirmModal → DELETE → редирект на список.
 *
 * КОНТРАКТ:
 *   DELETE /api/books/{id}     → 204; 401 — нет сессии, 419 — CSRF,
 *   DELETE /api/articles/{id}     403/404 — нет прав или уже удалена.
 *
 * Права на показ пунктов меню — permissions.js (data-requires, пункты
 * рендерятся скрытыми); 404 от DELETE фронт показывает как обычную
 * ошибку — эффект один: книга не удаляется.
 *
 * Меню ПЛОСКОЕ (без подменю): 4 кнопки правки + Delete + «Пожаловаться».
 * Пункты 1–2 — ссылки на страницы правки (?part=meta|content), пункт 3–4
 * (data-roles-open / data-roles-open-users) открывают модуль публикации
 * (window.RightsModal / window.LoreArticlePublish, mode:'roles',
 * screen 1|2, context 'edit').
 */
(function () {
  'use strict';

  function t(key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  }

  function closeAll(except) {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-details-menu]:not([hidden])'),
      function (menu) {
        if (menu === except) return;
        menu.hidden = true;
        var btn = menu.parentNode && menu.parentNode.querySelector('[data-details-menu-toggle]');
        if (btn) btn.setAttribute('aria-expanded', 'false');
      }
    );
  }

  document.addEventListener('click', function (e) {
    var toggle = e.target.closest && e.target.closest('[data-details-menu-toggle]');
    if (toggle) {
      var wrap = toggle.closest('[data-details-menu-wrap]');
      var menu = wrap && wrap.querySelector('[data-details-menu]');
      if (!menu) return;
      var open = menu.hidden;
      closeAll(open ? menu : null);
      menu.hidden = !open;
      toggle.setAttribute('aria-expanded', String(open));
      return;
    }
    if (e.target.closest && e.target.closest('[data-details-menu-wrap]')) return;
    closeAll();
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeAll();
  });

  // ---- Пункты «Изменить роли» / «Изменить пользователей»: открыть модуль
  //      публикации (экран 1 — роли, экран 2 — пользователи; context 'edit') ----
  function openRolesModule(btn) {
    var kind = btn.getAttribute('data-pub-type') || 'book';
    var id   = parseInt(btn.getAttribute('data-pub-id'), 10) || 0;
    if (!id) return;

    closeAll();

    var screen = btn.getAttribute('data-roles-open-users') ? 2 : 1;

    if (kind === 'article') {
      if (window.LoreArticlePublish && typeof window.LoreArticlePublish.open === 'function') {
        window.LoreArticlePublish.open(id, { mode: 'roles', screen: screen, context: 'edit' });
      }
      return;
    }
    if (window.RightsModal && typeof window.RightsModal.open === 'function') {
      window.RightsModal.open({ mode: 'roles', bookId: id, screen: screen, context: 'edit' });
    }
  }

  document.addEventListener('click', function (e) {
    var rolesBtn = e.target.closest && e.target.closest('[data-roles-open]');
    if (!rolesBtn) return;
    openRolesModule(rolesBtn);
  });

  document.addEventListener('click', function (e) {
    var usersBtn = e.target.closest && e.target.closest('[data-roles-open-users]');
    if (!usersBtn) return;
    openRolesModule(usersBtn);
  });

  // ---- Удаление публикации ----
  document.addEventListener('click', function (e) {
    var delBtn = e.target.closest && e.target.closest('[data-details-delete]');
    if (!delBtn || delBtn.dataset.deleting === '1') return;

    var url    = delBtn.getAttribute('data-delete-url');
    var kind   = delBtn.getAttribute('data-delete-kind') || 'book';
    var toList = delBtn.getAttribute('data-delete-redirect') || (kind === 'article' ? '/articles' : '/books');
    if (!url) return;

    closeAll();

    var ask = (window.ConfirmModal && typeof window.ConfirmModal.confirm === 'function')
      ? window.ConfirmModal.confirm({
          title: t(kind === 'article' ? 'js.delete_article_title' : 'js.delete_book_title'),
          message: t(kind === 'article' ? 'js.delete_article_text' : 'js.delete_book_text'),
          confirmText: t('js.delete'),
          danger: true
        })
      : Promise.resolve(window.confirm(
          t(kind === 'article' ? 'js.delete_article_text' : 'js.delete_book_text')));

    Promise.resolve(ask).then(function (confirmed) {
      if (!confirmed) return;

      delBtn.dataset.deleting = '1';
      var doFetch = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;

      doFetch(url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then(function (res) {
          if (res.ok) {
            // 204/200 — публикация удалена, уходим со страницы details
            // на список (book/article library).
            window.location.assign(toList);
            return;
          }
          return (window.Messages
            ? window.Messages.readError(res, t('errors.common.delete_failed', { status: res.status }))
            : Promise.resolve(t('errors.common.delete_failed', { status: res.status }))
          ).then(function (msg) {
            if (window.Messages) window.Messages.show(msg, { type: 'error' });
            else console.warn('[details-menu] DELETE ' + url + ' failed: HTTP ' + res.status);
          });
        })
        .catch(function (err) {
          console.error('[details-menu] DELETE ' + url + ' failed', err);
          if (window.Messages) window.Messages.show(t('errors.common.network_error'), { type: 'error' });
        })
        .then(function () {
          delete delBtn.dataset.deleting;
        });
    });
  });
})();
