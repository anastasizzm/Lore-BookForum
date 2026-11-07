/**
 * Кнопка «Block» в таблице пользователей.
 *
 * Бэкенд: POST /api/users/{userId}/block (config/endpoints.php,
 * UsersController::block → users.is_blocked = true). Роута разблокировки
 * пока нет, поэтому для заблокированных кнопка не рисуется.
 */
import { t, tx } from '../../core/i18n.js';
import { apiError, apiFetch } from '../../core/http.js';
import { confirmModal, notify } from '../../core/ui.js';

export function blockUser(btn) {
  var id   = btn.getAttribute('data-user-id') || '';
  var name = btn.getAttribute('data-user-name') || '';

  if (!id) return;

  confirmModal({
    title:       t('admin.block_title'),
    text:        t('admin.block_text'),
    confirmText: t('admin.block_confirm'),
    danger:      true,
  }).then(function (confirmed) {
    if (!confirmed) return;
    if (btn.disabled || btn.dataset.sending === '1') return;

    btn.dataset.sending = '1';
    btn.disabled = true;

    apiFetch('POST', '/api/users/' + encodeURIComponent(id) + '/block').then(function (r) {
      delete btn.dataset.sending;

      if (!r.ok) {
        btn.disabled = false;
        notify(apiError(r), 'error');
        return;
      }

      var item = { id: Number(id), username: name, blocked: true };
      var row = btn.closest('tr');
      var statusCell = row && row.querySelector('[data-status-cell]');
      if (statusCell) {
        statusCell.innerHTML =
          '<span class="admin-badge admin-badge--danger">' +
          t('admin.status_blocked') +
          '</span>';
      }

      // Unblock-роута ещё нет — оставлять кнопку «Block» у заблокированного
      // пользователя нельзя: повторный POST бессмысленен.
      var actions = btn.parentNode;
      if (actions) btn.remove();

      // Сообщаем списку, чтобы перерисовка не вернула активную кнопку.
      if (row) {
        row.setAttribute('data-user-blocked', '1');
        row.dispatchEvent(new CustomEvent('admin:user-blocked', {
          bubbles: true,
          detail: item,
        }));
      }

      notify(tx('admin.block_success', 'User blocked.'), 'success');
    });
  });
}
