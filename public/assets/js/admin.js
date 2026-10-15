/**
 * admin.js — действия админки на страницах src/views/pages/admin/*.
 *
 * Сейчас только клиентская часть: ConfirmModal.confirm() перед действием.
 * Реальных запросов к API админки НЕТ (API ещё не вынесли), поэтому каждый
 * обработчик заканчивается console.log + TODO(fetch).
 *
 * ВАЖНО про API модалки (src/partials/confirm-modal.php + js/modal.js):
 *   ConfirmModal.confirm({ title, message (алиас text), confirmText,
 *                          cancelText, danger }) -> Promise<boolean>
 * колбэка onConfirm у неё НЕТ — действие выполняем в .then(confirmed).
 *
 * Проверок прав здесь нет намеренно: бэк ещё не назвал финальные имена
 * разрешений (canPublication, canManagePublication и т.п.).
 */
(function () {
  'use strict';

  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  var confirmModal = function (options) {
    return window.ConfirmModal ? ConfirmModal.confirm(options) : Promise.resolve(false);
  };

  var notify = function (text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'success' });
    else console.warn(text);
  };

  /* ============================================================
     BLOCK USER — кнопка «Block» в таблице пользователей
     ============================================================ */
  function blockUser(btn) {
    var id   = btn.getAttribute('data-user-id') || '';
    var name = btn.getAttribute('data-user-name') || '';

    confirmModal({
      title:       t('common.admin.block_title'),
      text:        t('common.admin.block_text'),
      confirmText: t('common.admin.block_confirm'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): заменить на fetch('PATCH /api/admin/users/' + id,
      // headers: { 'X-CSRF-Token': LoreCsrf.token(), Accept: 'application/json' })
      // — роута админки пока нет (config/routes.php заморожен).
      console.log('Block user', id, name);

      var row = btn.closest('tr');
      var statusCell = row && row.children[4];
      if (statusCell) {
        statusCell.innerHTML =
          '<span class="admin-badge admin-badge--danger">' +
          t('common.admin.status_blocked') +
          '</span>';
      }
      btn.disabled = true;
      notify(t('common.admin.block_success'), 'success');
    });
  }

  /* ============================================================
     DELETE COMMENT — кнопка «Delete» в таблице комментариев
     ============================================================ */
  function deleteComment(btn) {
    var id = btn.getAttribute('data-comment-id') || '';

    confirmModal({
      title:       t('common.admin.delete_title'),
      text:        t('common.admin.delete_text'),
      confirmText: t('common.admin.action_delete'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): fetch('DELETE /api/admin/comments/' + id) — API ещё нет.
      console.log('Delete comment', id);

      var row = btn.closest('tr');
      if (row) row.remove();
    });
  }

  document.addEventListener('click', function (e) {
    var blockBtn = e.target && e.target.closest ? e.target.closest('[data-admin-block]') : null;
    if (blockBtn && !blockBtn.disabled) { blockUser(blockBtn); return; }

    var deleteBtn = e.target && e.target.closest ? e.target.closest('[data-admin-delete]') : null;
    if (deleteBtn) deleteComment(deleteBtn);
  });
})();
