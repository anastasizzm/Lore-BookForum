/**
 * Кнопка «Block» в таблице пользователей (пока локально — роута блокировки нет).
 */
import { t } from '../../core/i18n.js';
import { confirmModal, notify } from '../../core/ui.js';

export function blockUser(btn) {
  var id   = btn.getAttribute('data-user-id') || '';
  var name = btn.getAttribute('data-user-name') || '';

  confirmModal({
    title:       t('admin.block_title'),
    text:        t('admin.block_text'),
    confirmText: t('admin.block_confirm'),
    danger:      true,
  }).then(function (confirmed) {
    if (!confirmed) return;

    console.warn('[admin] User block route is not yet defined in routes.php. Applied locally for user:', id, name);

    var row = btn.closest('tr');
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
