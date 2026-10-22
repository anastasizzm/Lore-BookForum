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
      title:       t('admin.block_title'),
      text:        t('admin.block_text'),
      confirmText: t('admin.block_confirm'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): заменить на fetch('PATCH /api/admin/users/' + id,
      // headers: { 'X-CSRF-Token': LoreCsrf.token(), Accept: 'application/json' })
      // — роута админки пока нет (config/routes.php заморожен).
      console.log('Block user', id, name);

      var row = btn.closest('tr');
      // Ячейка статуса помечена в разметке (users.php), а не по индексу:
      // лишняя колонка в таблице не должна ломать обновление.
      var statusCell = row && row.querySelector('[data-status-cell]');
      if (statusCell) {
        statusCell.innerHTML =
          '<span class="admin-badge admin-badge--danger">' +
          t('admin.status_blocked') +
          '</span>';
      }
      btn.disabled = true;
      notify(t('admin.block_success'), 'success');
    });
  }

  /* ============================================================
     DELETE COMMENT — кнопка «Delete» в таблице комментариев
     ============================================================ */
  function deleteComment(btn) {
    var id = btn.getAttribute('data-comment-id') || '';

    confirmModal({
      title:       t('admin.delete_title'),
      text:        t('admin.delete_text'),
      confirmText: t('admin.action_delete'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): fetch('DELETE /api/admin/comments/' + id) — API ещё нет.
      console.log('Delete comment', id);

      var row = btn.closest('tr');
      if (row) row.remove();
    });
  }

  /* ============================================================
     SEARCH — клиентский поиск в таблицах админки

     Поле [data-admin-search] фильтрует строки таблицы по data-search
     (у строки справочника это ВСЕ её переводы разом) и поддерживает
     в актуальном состоянии счётчик [data-admin-count] и заглушку
     [data-admin-empty]: данных нет — common.admin.empty, поиск пуст —
     admin.no_results с запросом.
     ============================================================ */
  var searchState = null;

  function initSearch() {
    var input = document.querySelector('[data-admin-search]');
    var table = document.querySelector('[data-admin-table]');
    if (!input || !table) return;

    var emptyRow = table.querySelector('[data-admin-empty]');
    var emptyText = emptyRow ? (emptyRow.querySelector('td') || emptyRow).textContent.trim() : '';

    searchState = {
      input: input,
      table: table,
      count: document.querySelector('[data-admin-count]'),
      emptyRow: emptyRow,
      emptyText: emptyText,
      apply: function () {
        var q = (searchState.input.value || '').trim().toLowerCase();
        var rows = searchState.table.querySelectorAll('tbody tr[data-search]');
        var shown = 0;

        Array.prototype.forEach.call(rows, function (row) {
          var haystack = (row.getAttribute('data-search') || '').toLowerCase();
          var hit = q === '' || haystack.indexOf(q) !== -1;
          row.hidden = !hit;
          if (hit) shown++;
        });

        if (searchState.count) searchState.count.textContent = String(shown);

        if (searchState.emptyRow) {
          if (shown > 0) {
            searchState.emptyRow.hidden = true;
          } else {
            searchState.emptyRow.hidden = false;
            var cell = searchState.emptyRow.querySelector('td') || searchState.emptyRow;
            cell.textContent = q
              ? t('admin.no_results', { query: (searchState.input.value || '').trim() })
              : searchState.emptyText;
          }
        }
      }
    };

    input.addEventListener('input', searchState.apply);
    searchState.apply();
  }

  /** Повторный прогон поиска — вызывается после перерисовки строк. */
  function applySearch() {
    if (searchState) searchState.apply();
  }

  document.addEventListener('click', function (e) {
    var blockBtn = e.target && e.target.closest ? e.target.closest('[data-admin-block]') : null;
    if (blockBtn && !blockBtn.disabled) { blockUser(blockBtn); return; }

    var deleteBtn = e.target && e.target.closest ? e.target.closest('[data-admin-delete]') : null;
    if (deleteBtn) deleteComment(deleteBtn);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSearch);
  } else {
    initSearch();
  }
})();
