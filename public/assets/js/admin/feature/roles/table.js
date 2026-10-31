/**
 * Таблица системных ролей: загрузка, рендер строк, удаление, init страницы.
 */
import { t, tx } from '../../core/i18n.js';
import { confirmModal, notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';
import { allRoles, renderAllRoles } from '../all-roles/page.js';
import { rolesApi } from './api.js';
import { GRIP, TRASH } from './constants.js';
import { initRoleNew } from './create/modal.js';
import { dnd, initRoleDnd, moveQueue } from './dnd.js';
import { lockedCount } from './order.js';
import { findRole, permsLabel, roleState, totalPerms } from './state.js';

export function updateRoleRow(item) {
  if (allRoles.table) {
    var mirrored = allRoles.items.find(function (r) { return Number(r.id) === Number(item.id); });
    if (mirrored) {
      mirrored.systemPermissions = item.systemPermissions;
      mirrored.postPermissions   = item.postPermissions;
      mirrored.pubPermissions    = item.pubPermissions;
      mirrored.version           = item.version;
      mirrored.permsKnown        = true;
    }
    renderAllRoles();
  }
  var row = document.querySelector('[data-roles-rows] [data-role-row-id="' + item.id + '"]');
  if (!row) return;
  var badge = row.querySelector('[data-role-syscount]');
  if (badge) badge.textContent = permsLabel(totalPerms(item));
}

export function renderRoleRows(list) {
  var tbody = document.querySelector('[data-roles-rows]');
  if (!tbody) return;
  var emptyRow = tbody.querySelector('[data-admin-empty]');

  var table = document.querySelector('[data-roles-table]');
  if (table) table.setAttribute('data-roles-dnd', 'on');

  var locked = lockedCount();
  var lockEndId = (locked > 0 && locked < roleState.items.length) ? Number(roleState.items[locked - 1].id) : -1;
  var pending = moveQueue.isPending;

  tbody.innerHTML = (list || roleState.items).map(function (item) {
    var id = Number(item.id) || 0;
    var search = String(item.title || '').toLowerCase();
    var isLocked = item.canManage === false;
    var rowClass = 'admin-role-row' +
      (isLocked ? ' is-locked' : '') +
      (id === lockEndId ? ' is-lock-end' : '') +
      (dnd.active && dnd.id === id ? ' is-dragging' : '') +
      (pending(id) ? ' is-pending' : '');

    var handle = isLocked
      ? '<span class="admin-role__handle is-locked" title="' + esc(tx('admin.role_locked', 'Pinned: rank is above yours')) + '" aria-hidden="true">' + GRIP + '</span>'
      : '<button type="button" class="admin-role__handle" data-role-handle' +
          ' title="' + esc(tx('admin.role_drag', 'Drag to reorder')) + '"' +
          ' aria-label="' + esc(tx('admin.role_drag', 'Drag to reorder')) + '">' + GRIP + '</button>';

    return '<tr class="' + rowClass + '" data-role-row-id="' + id + '" data-search="' + esc(search) + '">' +
        '<td class="admin-table__drag" data-col="drag">' + handle + '</td>' +
        '<td class="admin-table__id" data-col="id">' + id + '</td>' +
        '<td data-col="title"><span class="admin-role__title">' +
          (item.color ? '<span class="admin-role__dot" style="background:' + esc(item.color) + '"></span>' : '') +
          esc(item.title) +
        '</span></td>' +
        '<td data-col="perms"><div class="admin-actions">' +
          '<span class="admin-badge admin-badge--accent" data-role-syscount>' +
            esc(permsLabel(totalPerms(item))) +
          '</span>' +
          '<button type="button" class="btn btn--secondary" data-role-edit data-role-id="' + id + '">' +
            esc(t('admin.action_permissions')) +
          '</button>' +
        '</div></td>' +
        '<td data-col="delete"><div class="admin-actions">' +
          '<button type="button" class="btn btn--danger admin-icon-btn" data-role-delete data-role-id="' + id + '"' +
            ' title="' + esc(t('admin.action_delete')) + '" aria-label="' + esc(t('admin.action_delete')) + '"' +
            (item.canManage === false ? ' disabled' : '') + '>' +
            TRASH +
          '</button>' +
        '</div></td>' +
      '</tr>';
  }).join('');

  if (emptyRow && !roleState.items.length) {
    emptyRow.hidden = false;
    tbody.appendChild(emptyRow);
  }
}

export function deleteRole(btn) {
  var id = btn.getAttribute('data-role-id');
  var item = findRole(id);
  if (!item) return;
  if (item.canManage === false) {
    notify(tx('js.no_rights', 'You do not have permission to do this'), 'error');
    return;
  }

  confirmModal({
    title:       tx('admin.role_delete_title', 'Delete role?'),
    text:        tx('admin.role_delete_text',
                    'The role "' + item.title + '" will be permanently deleted. This action cannot be undone.',
                    { name: item.title }),
    confirmText: tx('admin.action_delete', 'Delete'),
    danger:      true
  }).then(function (confirmed) {
    if (!confirmed) return;

    btn.disabled = true;
    return rolesApi.remove(item.id, item.version).then(function (result) {
      if (result.mode === 'forbidden') {
        notify(tx('js.no_rights', 'You do not have permission to do this'), 'error');
        btn.disabled = false;
        return;
      }
      if (result.mode === 'conflict') {
        notify(tx('admin.role_conflict', 'The role was changed by someone else. Refresh the list and try again'), 'error');
        btn.disabled = false;
        return;
      }
      if (result.mode === 'error') {
        notify(result.message, 'error');
        btn.disabled = false;
        return;
      }

      roleState.items = roleState.items.filter(function (r) { return Number(r.id) !== Number(item.id); });
      renderRoleRows();
      notify(tx('admin.role_deleted', 'Role deleted'), 'success');
    });
  }).catch(function (err) {
    console.warn('[admin] role delete failed', err);
    notify(tx('errors.common.network_error', 'Network error. Check your connection and try again'), 'error');
    btn.disabled = false;
  });
}

function setRolesStatus(text) {
  var el = document.querySelector('[data-roles-status]');
  if (!el) return;
  el.textContent = text || '';
  el.hidden = !text;
}

function loadRoles() {
  setRolesStatus(tx('admin.roles_loading', 'Loading roles…'));
  var emptyRow = document.querySelector('[data-roles-rows] [data-admin-empty]');
  if (emptyRow) emptyRow.hidden = true;
  return rolesApi.list().then(function (items) {
    if (items === null) {
      setRolesStatus(rolesApi.status === 'mock' ? '' :
        (tx('admin.roles_load_error', 'Failed to load roles') + (rolesApi.lastError ? ': ' + rolesApi.lastError : '')));
      renderRoleRows();
      return;
    }
    roleState.items = items;
    setRolesStatus('');
    renderRoleRows();
  });
}

export function initRoles() {
  var table = document.querySelector('[data-roles-table]');
  if (!table) return;

  roleState.items = [];
  // Модалка прав роли (admin-role-modal.php). Назначение роли пользователю —
  // отдельная модалка (admin-role-user-modal.php), с ней не смешиваем.
  roleState.modal = document.querySelector('[data-role-modal]');
  if (roleState.modal) {
    roleState.titleEl = roleState.modal.querySelector('[data-role-title]');
  }

  initRoleNew();
  initRoleDnd();
  loadRoles();
}
