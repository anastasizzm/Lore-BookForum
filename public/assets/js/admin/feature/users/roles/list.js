/**
 * Раскрывающийся список ролей в строке пользователя: рендер, снятие роли.
 */
import { apiError, apiFetch } from '../../../core/http.js';
import { tx } from '../../../core/i18n.js';
import { confirmModal, notify } from '../../../core/ui.js';
import { esc } from '../../../core/utils.js';
import { urLoad } from './api.js';
import { userRoles } from './state.js';

function urBody(userId) {
  return document.querySelector('[data-user-roles-row="' + userId + '"] [data-user-roles-body]');
}

export function urRender(userId) {
  var body = urBody(userId);
  if (!body) return;
  var roles = userRoles.cache[userId] || [];
  var mainBtn = document.querySelector('[data-user-expand][data-user-id="' + userId + '"]');
  var name = mainBtn ? (mainBtn.getAttribute('data-user-name') || '') : '';

  var removeLabel = tx('admin.user_role_remove', 'Remove role');
  var list = roles.length
    ? '<ul class="admin-user-roles__list">' + roles.map(function (r) {
        return '<li class="admin-user-roles__item">' +
          (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span>' : '') +
          '<span class="admin-user-roles__title">' + esc(r.title) + '</span>' +
          '<button type="button" class="admin-user-roles__remove" data-user-role-remove' +
            ' data-user-id="' + esc(userId) + '" data-role-id="' + r.id + '"' +
            ' title="' + esc(removeLabel) + '" aria-label="' + esc(removeLabel + ': ' + r.title) + '">' +
            '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true" focusable="false">' +
              '<path d="M3 3l10 10M13 3L3 13" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' +
          '</button></li>';
      }).join('') + '</ul>'
    : '<p class="admin-user-roles__note">' + esc(tx('admin.user_roles_empty', 'No roles')) + '</p>';

  var addLabel = tx('admin.user_role_add_title', 'Add role to user');
  body.innerHTML = list +
    '<div class="admin-user-roles__foot">' +
      '<button type="button" class="btn btn--primary admin-user-roles__add" data-user-role-add' +
        ' data-user-id="' + esc(userId) + '" data-user-name="' + esc(name) + '"' +
        ' title="' + esc(addLabel) + '" aria-label="' + esc(addLabel) + '">' +
        '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">' +
          '<path d="M8 3v10M3 8h10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' +
      '</button>' +
    '</div>';
}

export function urToggle(btn) {
  var id = btn.getAttribute('data-user-id');
  var row = document.querySelector('[data-user-roles-row="' + id + '"]');
  if (!row) return;
  var open = row.hidden;
  row.hidden = !open;
  btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  btn.classList.toggle('is-open', open);
  if (!open || userRoles.cache[id]) return;

  var body = urBody(id);
  if (body) body.innerHTML = '<p class="admin-user-roles__note">' + esc(tx('common.common.loading', 'Loading...')) + '</p>';
  urLoad(id).then(function (roles) {
    if (roles === null) {
      if (body) body.innerHTML = '<p class="admin-user-roles__note">' + esc(tx('admin.roles_load_error', 'Failed to load roles')) + '</p>';
      return;
    }
    userRoles.cache[id] = roles;
    urRender(id);
  });
}

/** Снять роль с пользователя: DELETE /api/system/users/{id}/roles/{roleId} */
export function urRemove(btn) {
  var userId = btn.getAttribute('data-user-id');
  var roleId = Number(btn.getAttribute('data-role-id'));
  var list = userRoles.cache[userId] || [];
  var role = list.filter(function (r) { return r.id === roleId; })[0];
  if (!role) return;
  var mainBtn = document.querySelector('[data-user-expand][data-user-id="' + userId + '"]');
  var userName = mainBtn ? (mainBtn.getAttribute('data-user-name') || '') : '';

  confirmModal({
    title:       tx('admin.user_role_remove_title', 'Remove role?'),
    text:        tx('admin.user_role_remove_text',
                    'The role "' + role.title + '" will be removed from ' + userName + '.',
                    { role: role.title, user: userName }),
    confirmText: tx('admin.user_role_remove', 'Remove role'),
    danger:      true
  }).then(function (confirmed) {
    if (!confirmed) return;
    btn.disabled = true;
    return apiFetch('DELETE', '/api/system/users/' + encodeURIComponent(userId) + '/roles/' + roleId).then(function (r) {
      if (!r.ok) { notify(apiError(r), 'error'); btn.disabled = false; return; }
      userRoles.cache[userId] = (userRoles.cache[userId] || []).filter(function (x) { return x.id !== roleId; });
      urRender(userId);
      notify(tx('admin.user_role_removed', 'Role removed'), 'success');
    });
  }).catch(function () {
    notify(tx('errors.common.network_error', 'Network error. Try again.'), 'error');
    btn.disabled = false;
  });
}
