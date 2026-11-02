/**
 * Кнопка «Delete» в таблице комментариев: DELETE /api/posts/{id}.
 */
import { t, tx } from '../../core/i18n.js';
import { confirmModal, notify } from '../../core/ui.js';

export function deleteComment(btn) {
  var id = btn.getAttribute('data-comment-id') || '';

  confirmModal({
    title:       t('admin.delete_title'),
    text:        t('admin.delete_text'),
    confirmText: t('admin.action_delete'),
    danger:      true,
  }).then(function (confirmed) {
    if (!confirmed) return;

    var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
    run('/api/posts/' + id, {
      method: 'DELETE',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var row = btn.closest('tr');
      if (row) row.remove();
      notify(tx('admin.delete_comment_success', 'Comment deleted'), 'success');
    }).catch(function (err) {
      console.warn('[admin] DELETE /api/posts/' + id + ' failed', err);
      notify(tx('errors.common.request_failed', 'Request failed'), 'error');
    });
  });
}
