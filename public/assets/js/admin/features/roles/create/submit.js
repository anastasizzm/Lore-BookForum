/**
 * Создание роли: POST имени в справочник. Роль без прав системной не считается,
 * поэтому здесь сохраняется только название (видно в «Все роли»);
 * права и привязка к проекту — через «+» (permissions-modal.js → finishNewRole).
 */
import { tx } from '../../../core/i18n.js';
import { searchState } from '../../../core/search.js';
import { notify } from '../../../core/ui.js';
import { colorForApi, currentLang } from '../../../core/utils.js';
import { refreshAllRoles } from '../../all-roles/page.js';
import { rolesApi } from '../api.js';
import { ROLE_COLORS } from '../constants.js';
import { closeRoleNew, resetRoleNewErrors, roleNew, roleNewChoice, setRoleNewBusy, setRoleNewError, showRoleNewErrors } from './modal.js';
import { placeCreatedRole } from '../dnd.js';
import { nextVersion, tailCount } from '../order.js';
import { closeRoleModal } from '../permissions-modal.js';
import { hasAnyPerms, roleState } from '../state.js';
import { renderRoleRows } from '../table.js';

function pickRoleTitle(rows) {
  var lang = currentLang().slice(0, 2).toLowerCase();
  for (var i = 0; i < rows.length; i++) {
    if (String(rows[i].langCode).slice(0, 2).toLowerCase() === lang) return rows[i].title;
  }
  return rows.length ? rows[0].title : '';
}

function buildRoleCreateBody(rows) {
  var translations = {};
  rows.forEach(function (row) { translations[row.langCode] = row.title; });
  return {
    translations: translations,
    // бэк принимает булев флаг isPublic (раньше была строка visibility)
    isPublic: (roleNewChoice('visibility') || 'public') === 'public',
    color: colorForApi(roleNewChoice('color') || ROLE_COLORS[0])
  };
}

function createdRoleId(result) {
  var candidates = [];
  var d = result.data;
  if (d && typeof d === 'object') {
    var inner = (d.data && typeof d.data === 'object') ? d.data : d;
    var node = inner.item || inner.role || inner;
    candidates.push(inner.createdId, node.createdId, node.id, node.roleId, inner.id, inner.roleId);
  }
  if (result.location) {
    var m = String(result.location).match(/(\d+)\/?$/);
    if (m) candidates.push(m[1]);
  }
  for (var i = 0; i < candidates.length; i++) {
    var c = candidates[i];
    if (c == null || c === '') continue;
    var n = Number(c);
    if (isFinite(n) && n > 0) return n;
  }
  return null;
}

function newRolePosition(insertAt) {
  if (insertAt) {
    return {
      aboveId: insertAt.aboveId != null ? insertAt.aboveId : null,
      belowId: insertAt.belowId != null ? insertAt.belowId : null
    };
  }
  var items = roleState.items;
  var tail = tailCount();
  var last = items.length - tail - 1;
  return {
    aboveId: last >= 0 ? items[last].id : null,
    belowId: tail > 0 ? items[items.length - tail].id : null
  };
}

export function newRoleRequest(item, perms) {
  return Object.assign({ roleId: item.id }, newRolePosition(item.insertAt), perms);
}

/** Вызывается после POST /api/system/roles (привязка роли к проекту с правами). */
export function finishNewRole(item, body, result) {
  item.systemPermissions = body.systemPermissions;
  item.postPermissions   = body.postPermissions;
  item.pubPermissions    = body.pubPermissions;
  item.isNew = false;
  var insertAt = item.insertAt;
  item.insertAt = null;

  var hasPerms = hasAnyPerms(body);

  var done = function () {
    var searchInput = document.querySelector('[data-admin-search]');
    if (searchInput) searchInput.value = '';
    if (searchState) searchState.serverQuery = '';
    renderRoleRows();
    refreshAllRoles();
    closeRoleModal();
    if (hasPerms) {
      notify(tx('admin.role_created', 'Role created'), 'success');
    } else {
      notify(tx('admin.role_perms_skipped', 'The role name is saved, but its permissions are not set yet'), 'warning');
    }
  };
  var addLocal = function () {
    item.version = nextVersion(result.etag, 0);
    roleState.items.splice(roleState.items.length - tailCount(), 0, item);
    return item;
  };

  if (result.mode === 'mock') {
    // роль без прав системной не считается — в локальный список её не добавляем
    if (hasPerms) {
      addLocal();
      if (insertAt) placeCreatedRole(item, insertAt);
    }
    done();
    return Promise.resolve();
  }

  return rolesApi.list().then(function (items) {
    if (items) {
      roleState.items = items; // источник правды — ответ сервера
    } else if (hasPerms) {
      addLocal();              // список не загрузился, но права есть
    }
    done();
  });
}

export function submitRoleNew() {
  if (roleNew.submitting) return;

  var main = roleNew.draft[0];
  if (!main || !String(main.title || '').trim()) {
    setRoleNewError(tx('errors.roles.name_required', 'Enter the role name'));
    if (roleNew.nameInput) roleNew.nameInput.focus();
    return;
  }
  var rows = roleNew.draft
    .map(function (row, i) { return { draftIndex: i, langCode: String(row.langCode || '').trim(), title: String(row.title || '').trim() }; })
    .filter(function (row) { return row.langCode !== '' && row.title !== ''; });

  setRoleNewError('');
  resetRoleNewErrors();
  setRoleNewBusy(true);
  var body = buildRoleCreateBody(rows);

  rolesApi.createName(body).then(function (result) {
    if (result.mode === 'forbidden') { notify(tx('js.no_rights', 'You do not have permission to do this'), 'error'); return null; }
    if (result.mode === 'error')     { showRoleNewErrors(result, rows); return null; }

    if (result.mode !== 'mock' && createdRoleId(result) === null) {
      console.warn('[admin] POST /api/additional/roles: could not read the new role id', result);
      setRoleNewError(tx('errors.common.server_error', 'Server error. Please try again later.'));
      return null;
    }
    return { title: pickRoleTitle(rows) };
  }).then(function (created) {
    if (!created) return;

    roleNew.insertAt = null;
    closeRoleNew();
    // Только название: без прав роль не системная, в список системных ролей не попадает.
    // Она появится в «Все роли»; права выдаются через «+».
    notify(tx('admin.role_name_saved', 'Role name saved'), 'success');
    refreshAllRoles();
  }).catch(function (err) {
    console.warn('[admin] role create failed', err);
    setRoleNewError(tx('errors.common.network_error', 'Network error. Check your connection and try again'));
  }).then(function () {
    if (roleNew.modal) setRoleNewBusy(false);
  });
}