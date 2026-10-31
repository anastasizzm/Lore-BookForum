/**
 * Модалка прав роли и режим «+» (выбор роли проекта из справочника).
 */
import { t, tx } from '../../core/i18n.js';
import { notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';
import { allRolesApi } from '../all-roles/api.js';
import { allRoles } from '../all-roles/page.js';
import { rolesApi } from './api.js';
import { ROLE_GROUPS } from './constants.js';
import { finishNewRole, newRoleRequest } from './create/submit.js';
import { findRole, roleState } from './state.js';
import { updateRoleRow } from './table.js';

function roleRoot() { return roleState.modal; }

export function isRoleOpen() { return !!(roleState.modal && !roleState.modal.hidden); }

function rr(sel) { var m = roleRoot(); return m ? m.querySelector(sel) : null; }

function setRoleLoading(on) {
  var el = rr('[data-role-loading]');
  if (el) el.hidden = !on;
  Object.keys(ROLE_GROUPS).forEach(function (g) {
    var wrap = rr('[data-role-group="' + g + '"]');
    if (wrap) wrap.hidden = on;
  });
}

function setRoleSaveEnabled(enabled) {
  var btn = rr('[data-role-save]');
  if (btn) btn.disabled = !enabled;
}

export function renderRoleLists() {
  Object.keys(ROLE_GROUPS).forEach(function (group) {
    var list = rr('[data-role-list="' + group + '"]');
    if (!list) return;
    var draftGroup = (roleState.draft && roleState.draft[group]) || {};
    list.innerHTML = ROLE_GROUPS[group].map(function (key) {
      var id = 'role_' + group + '_' + key;
      return '<label class="admin-role__perm" for="' + id + '">' +
          '<input type="checkbox" id="' + id + '"' +
            ' data-role-perm' +
            ' data-role-group="' + group + '"' +
            ' data-role-key="' + key + '"' +
            (draftGroup[key] ? ' checked' : '') + '>' +
          '<span>' + esc(t('admin.perm_' + key)) + '</span>' +
        '</label>';
    }).join('');
  });
  setRoleLoading(false);
}

export function openRoleModal(id, stub) {
  if (!roleState.modal) return;
  
  var item = stub || findRole(id);
  if (!item && allRoles && allRoles.items) {
    item = allRoles.items.find(function(r) { return Number(r.id) === Number(id); });
  }
  
  if (!item) return;

  roleState.current = item;
  roleState.draft = {
    system: Object.assign({}, item.systemPermissions),
    posts:  Object.assign({}, item.postPermissions),
    pubs:   Object.assign({}, item.pubPermissions)
  };

  if (roleState.titleEl) {
    roleState.titleEl.textContent = t('admin.role_modal_title') + ' — ' + item.title;
  }

  var dropdown = roleState.modal.querySelector('[data-role-dropdown]');
  if (dropdown) dropdown.hidden = true;
  closeRoleDd();
  roleState.modal.setAttribute('data-role-new', 'false');

  setRoleLoading(true);
  renderRoleLists();
  setRoleSaveEnabled(true);

  roleState.lastFocus = document.activeElement;
  roleState.modal.hidden = false;
  roleState.modal.setAttribute('data-role-id', String(item.id));
  document.body.classList.add('is-modal-open');

  var first = rr('[data-role-perm]');
  if (first) first.focus();
}

/* ---------- дропдаун выбора роли (модалка «+» на roles) ---------- */
export function ddEl(sel) { return roleState.modal ? roleState.modal.querySelector(sel) : null; }

export function closeRoleDd() {
  var wrap = ddEl('[data-role-dd]');
  if (!wrap) return;
  wrap.classList.remove('is-open');
  var menu = ddEl('[data-role-dd-menu]');
  if (menu) menu.hidden = true;
  var toggle = ddEl('[data-role-dd-toggle]');
  if (toggle) toggle.setAttribute('aria-expanded', 'false');
}

export function isRoleDdOpen() {
  var wrap = ddEl('[data-role-dd]');
  return !!(wrap && wrap.classList.contains('is-open'));
}

export function toggleRoleDd() {
  var wrap = ddEl('[data-role-dd]');
  var toggle = ddEl('[data-role-dd-toggle]');
  var menu = ddEl('[data-role-dd-menu]');
  if (!wrap || !toggle || !menu || toggle.disabled) return;
  var open = !wrap.classList.contains('is-open');
  wrap.classList.toggle('is-open', open);
  menu.hidden = !open;
  toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  if (open) {
    var search = ddEl('[data-role-dd-search]');
    if (search) search.focus();
  }
}

function renderRoleDdValue() {
  var valueEl = ddEl('[data-role-dd-value]');
  if (!valueEl) return;
  var cur = roleState.current;
  if (cur && cur.id) {
    valueEl.classList.remove('is-placeholder');
    valueEl.innerHTML = (cur.color ? '<span class="admin-role__dot" style="background:' + esc(cur.color) + '"></span>' : '') +
      '<span class="admin-role__dd-text">' + esc(cur.title) + '</span>';
  } else {
    valueEl.classList.add('is-placeholder');
    valueEl.textContent = (roleState.ddMessage || tx('admin.role_select_placeholder', 'Choose a role'));
  }
}

/** Роли проекта, которых ещё нет в списке (системные уже добавленные скрываем). */
function freeRoles(names) {
  var taken = {};
  roleState.items.forEach(function (r) { taken[Number(r.id)] = true; });
  return names.filter(function (r) { return !taken[Number(r.id)]; });
}

function renderRoleDdMenu() {
  var list = ddEl('[data-role-dd-list]');
  if (!list) return;
  var shown = roleState.ddOptions || [];
  var curId = roleState.current ? Number(roleState.current.id) : 0;
  if (!shown.length) {
    var msg = roleState.ddSearching
      ? tx('common.common.loading', 'Loading...')
      : (roleState.ddQuery
          ? tx('admin.role_dd_nothing', 'Nothing found')
          : (roleState.ddMessage || tx('admin.role_none_available', 'No roles available')));
    list.innerHTML = '<li class="admin-role__dd-note">' + esc(msg) + '</li>';
    return;
  }
  list.innerHTML = shown.map(function (r) {
    var selected = Number(r.id) === curId;
    return '<li role="presentation"><button type="button" class="admin-role__dd-item' + (selected ? ' is-selected' : '') + '"' +
        ' role="option" aria-selected="' + (selected ? 'true' : 'false') + '" data-role-dd-pick="' + r.id + '">' +
        (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span>' : '') +
        '<span class="admin-role__dd-text">' + esc(r.title) + '</span>' +
      '</button></li>';
  }).join('');
}

var roleDdSeq = 0;
/** Серверный поиск ролей для дропдауна: GET /api/additional/roles?q=… — только по Enter. */
export function searchRoleDd(query) {
  var q = String(query || '').trim();
  if (q === (roleState.ddQuery || '') && !roleState.ddSearching) return;
  roleState.ddQuery = q;
  roleState.ddSearching = true;
  renderRoleDdMenu();
  var seq = ++roleDdSeq;
  allRolesApi.list(q, 'all').then(function (names) {
    if (seq !== roleDdSeq) return;
    roleState.ddSearching = false;
    if (names === null) {
      notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
      return;
    }
    roleState.ddOptions = freeRoles(names);
    renderRoleDdMenu();
  });
}

export function pickRoleDd(id) {
  var cur = roleState.current;
  if (!cur || !cur.attach) return;
  var picked = (roleState.ddOptions || []).filter(function (r) { return Number(r.id) === Number(id); })[0];
  if (!picked) return;
  cur.id = Number(picked.id);
  cur.title = picked.title;
  cur.color = picked.color || '';
  renderRoleDdValue();
  renderRoleDdMenu();
  closeRoleDd();
  setRoleSaveEnabled(true);
}

/** «+» на странице roles: выбрать роль проекта из справочника и задать ей права. */
export function openRoleAttach() {
  if (!roleState.modal) return;

  var stub = {
    id: 0, title: '', color: '', canManage: true, status: '', version: null,
    isNew: true, attach: true, insertAt: null,
    systemPermissions: {}, postPermissions: {}, pubPermissions: {}
  };
  roleState.current = stub;
  roleState.draft = { system: {}, posts: {}, pubs: {} };
  roleState.ddOptions = [];
  roleState.ddQuery = '';
  roleState.ddSearching = false;
  roleDdSeq++;
  roleState.ddMessage = tx('common.common.loading', 'Loading...');
  var ddSearchInput = ddEl('[data-role-dd-search]');
  if (ddSearchInput) ddSearchInput.value = '';

  if (roleState.titleEl) roleState.titleEl.textContent = tx('admin.role_attach_title', 'Add role to project');

  var dropdown = ddEl('[data-role-dropdown]');
  if (dropdown) dropdown.hidden = false;
  roleState.modal.setAttribute('data-role-new', 'true');
  var toggle = ddEl('[data-role-dd-toggle]');
  if (toggle) toggle.disabled = true;
  closeRoleDd();
  renderRoleDdValue();
  renderRoleDdMenu();

  renderRoleLists();
  setRoleSaveEnabled(false);

  roleState.lastFocus = document.activeElement;
  roleState.modal.hidden = false;
  roleState.modal.setAttribute('data-role-id', '');
  document.body.classList.add('is-modal-open');

  // Роли, которые отдаёт бэк (GET /api/additional/roles), без уже добавленных в проект
  Promise.all([allRolesApi.list('', 'all'), rolesApi.list()]).then(function (res) {
    if (roleState.current !== stub) return;
    var names = res[0], system = res[1];
    if (system) roleState.items = system;
    if (toggle) toggle.disabled = false;

    if (names === null) {
      roleState.ddMessage = tx('admin.roles_load_error', 'Failed to load roles');
    } else {
      roleState.ddOptions = freeRoles(names);
      roleState.ddMessage = roleState.ddOptions.length ? '' : tx('admin.role_none_available', 'No roles available');
    }
    renderRoleDdValue();
    renderRoleDdMenu();
    if (toggle) toggle.focus();
  });
}

export function closeRoleModal() {
  if (!roleState.modal || roleState.modal.hidden) return;
  closeRoleDd();
  roleState.modal.hidden = true;
  roleState.modal.setAttribute('data-role-id', '');
  document.body.classList.remove('is-modal-open');
  var skipped = !!(roleState.current && roleState.current.isNew && !roleState.current.attach);
  roleState.current = null;
  roleState.draft = null;
  roleState.submitting = false;
  setRoleSaveEnabled(true);
  if (skipped) notify(tx('admin.role_perms_skipped', 'The role name is saved, but its permissions are not set yet'), 'warning');

  var target = roleState.lastFocus;
  roleState.lastFocus = null;
  if (target && target.isConnected && target.offsetParent !== null) {
    try { target.focus(); } catch (_) {}
  }
}

export function saveRole() {
  var item = roleState.current;
  if (!item || roleState.submitting) return;
  if (item.attach && !item.id) {
    notify(tx('admin.role_select_placeholder', 'Choose a role'), 'error');
    return;
  }
  roleState.submitting = true;
  setRoleSaveEnabled(false);

  var full = function (group) {
    var out = {};
    ROLE_GROUPS[group].forEach(function (key) { out[key] = !!(roleState.draft[group] && roleState.draft[group][key]); });
    return out;
  };
  var body = {
    systemPermissions: full('system'),
    postPermissions:   full('posts'),
    pubPermissions:    full('pubs')
  };

  var call = item.isNew
    ? rolesApi.create(newRoleRequest(item, body))
    : rolesApi.save(item.id, item.version, body);

  call.then(function (result) {
    if (result.mode === 'forbidden') { notify(t('js.no_rights'), 'error'); return; }
    if (result.mode === 'conflict')  { notify(t('admin.role_conflict'), 'error'); return; }
    if (result.mode === 'error')     { notify(result.message, 'error'); return; }
    if (item.isNew) return finishNewRole(item, body, result);

    item.systemPermissions = body.systemPermissions;
    item.postPermissions   = body.postPermissions;
    item.pubPermissions    = body.pubPermissions;

    var parsed = result.etag ? parseInt(String(result.etag).replace(/[^0-9]/g, ''), 10) : NaN;
    item.version = isNaN(parsed) ? (Number(item.version) + 1) : parsed;

    updateRoleRow(item);
    closeRoleModal();
    notify(t('admin.role_saved'), 'success');
  })
  .catch(function (err) {
    console.warn('[admin] role save failed', err);
    notify(t('errors.common.network_error'), 'error');
  })
  .then(function () {
    roleState.submitting = false;
    setRoleSaveEnabled(!(roleState.current && roleState.current.attach && !roleState.current.id));
  });
}
