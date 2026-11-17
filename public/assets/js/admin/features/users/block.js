/**
 * Кнопки «Block» / «Unblock» в таблице пользователей.
 *
 * Бэкенд:
 *   POST   /api/users/{userId}/block — users.is_blocked = true;
 *   DELETE /api/users/{userId}/block — users.is_blocked = false.
 * После ответа обновляем строку через setUserBlocked() из list.js.
 */
import { t, tx } from '../../core/i18n.js';
import { apiError, apiFetch } from '../../core/http.js';
import { confirmModal, notify } from '../../core/ui.js';
import { setUserBlocked } from './list.js';

function applyState(btn, blocked) {
  var id = btn.getAttribute('data-user-id') || '';
  if (!id) return;
  setUserBlocked(Number(id), blocked);
}

export function blockUser(btn) {
  var id = btn.getAttribute('data-user-id') || '';

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

      applyState(btn, true);
      notify(tx('admin.block_success', 'User blocked.'), 'success');
    });
  });
}

export function unblockUser(btn) {
  var id   = btn.getAttribute('data-user-id') || '';
  var name = btn.getAttribute('data-user-name') || '';

  if (!id) return;

  confirmModal({
    title:       t('admin.unblock_title'),
    text:        t('admin.unblock_text', { user: name }),
    confirmText: t('admin.action_unblock'),
  }).then(function (confirmed) {
    if (!confirmed) return;
    if (btn.disabled || btn.dataset.sending === '1') return;

    btn.dataset.sending = '1';
    btn.disabled = true;

    apiFetch('DELETE', '/api/users/' + encodeURIComponent(id) + '/block').then(function (r) {
      delete btn.dataset.sending;

      if (!r.ok) {
        btn.disabled = false;
        notify(apiError(r), 'error');
        return;
      }

      applyState(btn, false);
      notify(tx('admin.unblock_success', 'User unblocked.'), 'success');
    });
  });
}
