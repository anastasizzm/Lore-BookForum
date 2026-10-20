/**
 * book-rights.js — асинхронная подгрузка прав текущего пользователя
 * на публикацию (книжная страница book-details.php и article-details.php).
 *
 * Пункт «Редактировать» (data-edit-btn, лежит в меню «три точки» в шапке
 * страницы — details-menu.php) рендерится вьюхой ВСЕГДА
 * в нейтральном (сером) состоянии; после загрузки прав получает цвет:
 *   canPublish → book-actions__edit--publish (красный — есть права
 *                на публикацию книги),
 *   canEdit    → book-actions__edit--edit    (синий — есть права
 *                на редактирование),
 *   иначе      → book-actions__edit--none    (серый — прав нет;
 *                состояние уже стоит в разметке, но на ошибке
 *                сети возвращаем его явно).
 *
 * КОНТРАКТ (бэк добавит роут; config/ и app/ заморожены):
 *   GET /api/publications/{id}/rights
 *   200 { data: { canEdit: bool, canPublish: bool } }
 *   401/403/404/5xx → кнопка остаётся серой, JS не падает.
 *
 * Публикация черновика — отдельная механика (rights-modal.js):
 * статус draft вьюха рисует по $status, кнопка внизу страницы есть
 * всегда, пока книга не опубликована.
 */
(function () {
  'use strict';

  function applyState(btn, state) {
    btn.classList.remove(
      'book-actions__edit--publish',
      'book-actions__edit--edit',
      'book-actions__edit--none'
    );
    if (state === 'publish') btn.classList.add('book-actions__edit--publish');
    else if (state === 'edit') btn.classList.add('book-actions__edit--edit');
    else btn.classList.add('book-actions__edit--none');
    btn.setAttribute('data-rights-state', state);
  }

  function loadRights(btn) {
    // data-book-id — книга (book-details), data-pub-id — статья
    // (article-details): роут /api/publications/{id}/rights один на оба вида.
    var id = parseInt(btn.getAttribute('data-book-id') || btn.getAttribute('data-pub-id'), 10) || 0;
    if (!id) return;

    fetch('/api/publications/' + id + '/rights', {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (data) {
        var payload = (data && data.data) || data || {};
        if (payload.canPublish) applyState(btn, 'publish');
        else if (payload.canEdit) applyState(btn, 'edit');
        else applyState(btn, 'none');
      })
      .catch(function (err) {
        // Права не пришли (нет авторизации, роута ещё нет) — серая кнопка
        console.warn('[book-rights] load failed', err);
        applyState(btn, 'none');
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var btn = document.querySelector('[data-edit-btn]');
    if (btn) loadRights(btn);
  });
})();
