/**
 * Кнопка «Delete» в таблице комментариев: DELETE /api/posts/{id}.
 */
import { httpRequest } from '../../core/http.js';
import { t, tx } from '../../core/i18n.js';
import { confirmModal, notify } from '../../core/ui.js';
import { removeCommentFromList } from './list.js';

export function deleteComment(btn) {
  var id = btn.getAttribute('data-comment-id') || '';
  if (!id || btn.disabled) return;

  confirmModal({
    title:       t('admin.delete_title'),
    text:        t('admin.delete_text'),
    confirmText: t('admin.action_delete'),
    danger:      true,
  }).then(function (confirmed) {
    if (!confirmed) return;
    btn.disabled = true;

    return httpRequest('DELETE', '/api/posts/' + encodeURIComponent(id)).then(function (r) {
      var looksHtml = /^\s*</.test(r.raw || '');
      if (r.ok && !looksHtml) {
        removeCommentFromList(id);
        notify(tx('admin.delete_comment_success', 'Comment deleted'), 'success');
        return;
      }
      btn.disabled = false;
      if (r.status === 403) {
        var msg = r.data && r.data.error && r.data.error.message;
        notify(msg || tx('js.no_rights', 'You do not have permission to do this'), 'error');
        return;
      }
      var serverMsg = r.data && r.data.error && r.data.error.message;
      console.warn('[admin] DELETE /api/posts/' + id + ' failed', r.status, r.raw);
      notify(serverMsg || tx('errors.common.request_failed', 'Request failed'), 'error');
    });
  });
}