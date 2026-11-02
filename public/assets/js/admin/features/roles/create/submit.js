/**
 * Создание роли: POST имён, привязка к проекту, размещение в списке.
 */
import { tx } from '../../../core/i18n.js';
import { searchState } from '../../../core/search.js';
import { notify } from '../../../core/ui.js';
import { colorForApi, currentLang, normalizeColor } from '../../../core/utils.js';
import { ensureSystemRoles, refreshAllRoles } from '../../all-roles/page.js';
import { rolesApi } from '../api.js';
import { ROLE_COLORS, ROLE_GROUPS } from '../constants.js';
import { closeRoleNew, resetRoleNewErrors, roleNew, roleNewChoice, setRoleNewBusy, setRoleNewError, showRoleNewErrors } from './modal.js';
import { placeCreatedRole, resyncRoles } from '../dnd.js';
import { nextVersion, tailCount } from '../order.js';
import { closeRoleModal } from '../permissions-modal.js';
import { findRole, roleState } from '../state.js';
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
    visibility: roleNewChoice('visibility') || 'public',
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

var SAVE_ONLY_ATTACHES_EMPTY_PERMS = true;
function emptyPermissionsBody() {
  var names = { system: 'systemPermissions', posts: 'postPermissions', pubs: 'pubPermissions' };
  var out = {};
  Object.keys(ROLE_GROUPS).forEach(function (group) {
    out[names[group]] = {};
    ROLE_GROUPS[group].forEach(function (key) { out[names[group]][key] = false; });
  });
  return out;
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

function attachEmptyRole(stub) {
  var perms = emptyPermissionsBody();
  return rolesApi.create(newRoleRequest(stub, perms)).then(function (result) {
    if (result.mode === 'forbidden') {
      notify(tx('js.no_rights', 'You do not have permission to do this'), 'error');
    } else if (result.mode === 'error') {
      notify(result.message, 'error');
    } else {
      return finishNewRole(stub, perms, result);
    }
    notify(tx('admin.role_perms_skipped', 'The role name is saved, but its permissions are not set yet'), 'warning');
  }).catch(function (err) {
    console.warn('[admin] attach role failed', err);
    notify(tx('errors.common.network_error', 'Network error. Check your connection and try again'), 'error');
  });
}

function newRoleStub(created, body, insertAt) {
  return {
    id: created.id, title: created.title, color: normalizeColor(body.color), createdAt: '',
    canManage: true, status: body.visibility, version: null,
    isNew: true, insertAt: insertAt || null,
    systemPermissions: {}, postPermissions: {}, pubPermissions: {}
  };
}

export function finishNewRole(item, body, result) {
  item.systemPermissions = body.systemPermissions;
  item.postPermissions   = body.postPermissions;
  item.pubPermissions    = body.pubPermissions;
  item.isNew = false;
  var insertAt = item.insertAt;
  item.insertAt = null;

  var done = function () {
    var searchInput = document.querySelector('[data-admin-search]');
    if (searchInput) searchInput.value = '';
    if (searchState) searchState.serverQuery = '';
    renderRoleRows();
    refreshAllRoles();
    closeRoleModal();
    notify(tx('admin.role_created', 'Role created'), 'success');
  };
  var addLocal = function () {
    item.version = nextVersion(result.etag, 0);
    roleState.items.splice(roleState.items.length - tailCount(), 0, item);
    return item;
  };

  if (result.mode === 'mock') {
    addLocal();
    if (insertAt) placeCreatedRole(item, insertAt);
    done();
    return Promise.resolve();
  }

  return rolesApi.list().then(function (items) {
    var placed = null;
    if (items) {
      roleState.items = items;
      placed = findRole(item.id);
    }
    if (!placed) placed = addLocal();
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

    var id;
    if (result.mode === 'mock') {
      id = roleState.items.reduce(function (m, r) { return Math.max(m, Number(r.id) || 0); }, 0) + 1;
    } else {
      id = createdRoleId(result);
      if (id === null) {
        console.warn('[admin] POST /api/additional/roles: could not read the new role id', result);
        setRoleNewError(tx('errors.common.server_error', 'Server error. Please try again later.'));
        return null;
      }
    }
    return { id: id, title: pickRoleTitle(rows) };
  }).then(function (created) {
    if (!created) return;

    var insertAt = roleNew.insertAt;
    roleNew.insertAt = null;
    closeRoleNew();
    var stub = newRoleStub(created, body, insertAt);

    return ensureSystemRoles().then(function (ok) {
      if (!ok) {
        notify(tx('admin.role_name_saved', 'Role name saved'), 'success');
        notify(tx('admin.roles_load_error', 'Failed to load roles') + (rolesApi.lastError ? ': ' + rolesApi.lastError : ''), 'error');
        refreshAllRoles();
        return;
      }
      if (SAVE_ONLY_ATTACHES_EMPTY_PERMS) {
        return attachEmptyRole(stub);
      } else {
        notify(tx('admin.role_name_saved', 'Role name saved'), 'success');
        resyncRoles();
      }
    });
  }).catch(function (err) {
    console.warn('[admin] role create failed', err);
    setRoleNewError(tx('errors.common.network_error', 'Network error. Check your connection and try again'));
  }).then(function () {
    if (roleNew.modal) setRoleNewBusy(false);
  });
}
