/**
 * Модалка назначения ролей пользователю (чекбоксы ролей).
 */
import { tx } from '../../core/i18n.js';
import { notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';
import { rolesApi } from './api.js';
import { roleState } from './state.js';

export var roleUserAssign = {
  modal: null,
  form: null,
  select: null,
  rolesList: null,
  loading: null,
  saveBtn: null,
  errorEl: null,
  users: [],
  initialUserRoles: [],
  picker: null,
  userSearch: null,
  userResults: null,
  picked: null,        // выбранный пользователь { id, name }
  userQuery: '',       // набранный текст (мгновенный фильтр по загруженным пользователям)
  pickerOpen: false,
  usersLoading: false
};

export function initRoleUserModal() {
  var m = document.querySelector('[data-role-user-modal]');
  if (!m) return;

  roleUserAssign.modal = m;
  roleUserAssign.form = m.querySelector('[data-role-user-form]');
  roleUserAssign.select = m.querySelector('[data-role-user-select]');
  roleUserAssign.rolesList = m.querySelector('[data-role-user-roles-list]');
  roleUserAssign.loading = m.querySelector('[data-role-user-loading]');
  roleUserAssign.saveBtn = m.querySelector('[data-role-user-save]');
  roleUserAssign.errorEl = m.querySelector('[data-role-user-error]');
  roleUserAssign.picker = m.querySelector('[data-role-user-picker]');
  roleUserAssign.userSearch = m.querySelector('[data-role-user-search]');
  roleUserAssign.userResults = m.querySelector('[data-role-user-results]');

  // Поле выбора пользователя (как .roles-picker): список под полем, ввод фильтрует мгновенно
  var userSearch = roleUserAssign.userSearch;
  if (userSearch) {
    userSearch.addEventListener('focus', openUserPicker);
    userSearch.addEventListener('click', openUserPicker);
    userSearch.addEventListener('input', function () {
      onUserTyped(userSearch.value);
      openUserPicker();
    });
    userSearch.addEventListener('keydown', function (e) {
      var firstOption = roleUserAssign.userResults && roleUserAssign.userResults.querySelector('[data-role-user-pick]');
      if (e.key === 'Enter') {
        e.preventDefault();               // не отправлять форму; Enter выбирает первого в списке
        if (firstOption && !roleUserAssign.picked) pickUser(firstOption.getAttribute('data-role-user-pick'));
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        openUserPicker();
        firstOption = roleUserAssign.userResults && roleUserAssign.userResults.querySelector('[data-role-user-pick]');
        if (firstOption) firstOption.focus();
      }
    });
  }
  m.addEventListener('click', function (e) {
    var pick = e.target.closest && e.target.closest('[data-role-user-pick]');
    if (pick) { pickUser(pick.getAttribute('data-role-user-pick')); return; }
    if (!e.target.closest || !e.target.closest('[data-role-user-picker]')) closeRoleUserPicker();
  });

  if (roleUserAssign.select) {
    roleUserAssign.select.addEventListener('change', function () {
      var userId = roleUserAssign.select.value;
      if (!userId) {
        if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;
        uncheckAllUserRoles();
        return;
      }
      loadUserCurrentRoles(userId);
    });
  }

  if (roleUserAssign.form) {
    roleUserAssign.form.addEventListener('submit', function (e) {
      e.preventDefault();
      saveUserRoles();
    });
  }
}

function uncheckAllUserRoles() {
  if (!roleUserAssign.rolesList) return;
  Array.prototype.forEach.call(roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
    cb.checked = false;
  });
}

function renderRolesMessage(text) {
  if (!roleUserAssign.rolesList) return;
  roleUserAssign.rolesList.innerHTML = '<p class="admin-role__loading">' + esc(text) + '</p>';
}

/** Роли могут быть не загружены (мы не на странице roles): подгружаем сами. */
function ensureRoles() {
  if (roleState.items.length) return Promise.resolve(roleState.items);
  return rolesApi.list().then(function (items) {
    if (items && items.length) roleState.items = items;
    return roleState.items;
  });
}

function renderModalRolesCheckboxes(preselectedRoleId) {
  if (!roleUserAssign.rolesList) return;
  roleUserAssign.rolesList.innerHTML = roleState.items.map(function (r) {
    var isChecked = preselectedRoleId && Number(preselectedRoleId) === Number(r.id);
    return '<label class="admin-role__perm" for="user_role_' + r.id + '">' +
        '<input type="checkbox" id="user_role_' + r.id + '" value="' + r.id + '"' + (isChecked ? ' checked' : '') + '>' +
        (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span> ' : '') +
        '<span>' + esc(r.title) + '</span>' +
      '</label>';
  }).join('');
}

function fetchUsersList() {
  if (roleUserAssign.users.length) return Promise.resolve(roleUserAssign.users);
  var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
  return run('/api/users', { headers: { 'Accept': 'application/json' } })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (data) {
      var items = (data && data.items) ? data.items : (Array.isArray(data) ? data : []);
      roleUserAssign.users = items.map(function (u) {
        return {
          id: u.id,
          name: u.username || u.name || u.login || u.email || ('User #' + u.id)
        };
      });
      return roleUserAssign.users;
    }).catch(function (err) {
      console.warn('[admin] users list load failed', err);
      return [];
    });
}

export function openRoleUserModal(preselectedRoleId) {
  if (!roleUserAssign.modal) return;

  if (roleUserAssign.errorEl) roleUserAssign.errorEl.hidden = true;
  if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;

  roleUserAssign.picked = null;
  roleUserAssign.userQuery = '';
  roleUserAssign.pickerOpen = false;
  roleUserAssign.usersLoading = true;
  if (roleUserAssign.userSearch) roleUserAssign.userSearch.value = '';
  if (roleUserAssign.select) roleUserAssign.select.value = '';
  renderUserResults();

  renderRolesMessage(tx('common.common.loading', 'Loading...'));
  ensureRoles().then(function (items) {
    if (!items.length) {
      var reason = rolesApi.lastError || tx('admin.roles_load_error', 'Failed to load roles');
      renderRolesMessage(reason);
      notify(reason, 'error');
      return;
    }
    renderModalRolesCheckboxes(preselectedRoleId);
  });

  fetchUsersList().then(function (users) {
    roleUserAssign.usersLoading = false;
    if (roleUserAssign.pickerOpen) renderUserResults();
    if (!roleUserAssign.select) return;
    roleUserAssign.select.innerHTML = '<option value="">— ' + esc(tx('admin.role_choose_user', 'Choose a user')) + ' —</option>' +
      users.map(function (u) {
        return '<option value="' + u.id + '">' + esc(u.name) + ' (ID: ' + u.id + ')</option>';
      }).join('');
  });

  roleUserAssign.modal.hidden = false;
  document.body.classList.add('is-modal-open');
}

export function closeRoleUserModal() {
  if (!roleUserAssign.modal) return;
  closeRoleUserPicker();
  roleUserAssign.modal.hidden = true;
  document.body.classList.remove('is-modal-open');
}

function loadUserCurrentRoles(userId) {
  if (roleUserAssign.loading) roleUserAssign.loading.hidden = false;
  if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;
  var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
  run('/api/system/users/' + userId + '/roles', { headers: { 'Accept': 'application/json' } })
    .then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    })
    .then(function (res) {
      var roles = (res && res.items) ? res.items : (Array.isArray(res) ? res : []);
      roleUserAssign.initialUserRoles = roles.map(function (x) {
        var src = (x && typeof x === 'object' && x.role && typeof x.role === 'object') ? x.role : x;
        if (src && typeof src === 'object') {
          return Number(src.id !== undefined ? src.id : src.roleId);
        }
        return Number(src);
      });
      Array.prototype.forEach.call(roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
        cb.checked = roleUserAssign.initialUserRoles.indexOf(Number(cb.value)) !== -1;
      });
    })
    .catch(function (err) {
      console.warn('[admin] user roles load failed', err);
      notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
    })
    .then(function () {
      if (roleUserAssign.loading) roleUserAssign.loading.hidden = true;
      if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = false;
    });
}

function saveUserRoles() {
  var userId = roleUserAssign.select.value;
  if (!userId) return;

  roleUserAssign.saveBtn.disabled = true;
  var checkedBoxes = roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]:checked');
  var currentSelected = Array.prototype.map.call(checkedBoxes, function (cb) { return Number(cb.value); });

  var toAdd = currentSelected.filter(function (id) { return roleUserAssign.initialUserRoles.indexOf(id) === -1; });
  var toRemove = roleUserAssign.initialUserRoles.filter(function (id) { return currentSelected.indexOf(id) === -1; });

  var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
  var requests = [];

  toAdd.forEach(function (roleId) {
    requests.push(run('/api/system/users/' + userId + '/roles', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ roleId: roleId })
    }));
  });

  toRemove.forEach(function (roleId) {
    requests.push(run('/api/system/users/' + userId + '/roles/' + roleId, {
      method: 'DELETE',
      headers: { 'Accept': 'application/json' }
    }));
  });

  Promise.all(requests).then(function (responses) {
    var failed = responses.filter(function (r) { return !r.ok; })[0];
    if (failed) {
      notify(failed.status === 403
        ? tx('js.no_rights', 'You do not have permission to do this')
        : tx('errors.common.request_failed', 'Request failed') + ' (HTTP ' + failed.status + ')', 'error');
      return;
    }
    notify(tx('admin.role_saved', 'Roles updated'), 'success');
    closeRoleUserModal();
  }).catch(function () {
    notify(tx('errors.common.request_failed', 'Request failed'), 'error');
  }).then(function () {
    if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = false;
  });
}

/* ---------- поле выбора пользователя (поиск + список под ним) ---------- */

export function isRoleUserPickerOpen() {
  return !!roleUserAssign.pickerOpen;
}

export function closeRoleUserPicker() {
  roleUserAssign.pickerOpen = false;
  renderUserResults();
}

function openUserPicker() {
  if (roleUserAssign.pickerOpen) return;
  roleUserAssign.pickerOpen = true;
  renderUserResults();
}

function renderUserResults() {
  var box = roleUserAssign.userResults;
  if (!box) return;
  var open = roleUserAssign.pickerOpen;
  if (roleUserAssign.picker) roleUserAssign.picker.classList.toggle('is-open', open);
  if (roleUserAssign.userSearch) roleUserAssign.userSearch.setAttribute('aria-expanded', open ? 'true' : 'false');
  if (!open) { box.hidden = true; box.innerHTML = ''; return; }

  var query = String(roleUserAssign.userQuery || '').toLowerCase();
  var users = roleUserAssign.users.filter(function (u) {
    return !query || String(u.name).toLowerCase().indexOf(query) !== -1 || String(u.id).indexOf(query) !== -1;
  }).slice(0, 50);

  if (!users.length) {
    var msg = roleUserAssign.usersLoading
      ? tx('roles.searching', 'Searching…')
      : tx('admin.role_dd_nothing', 'Nothing found');
    box.innerHTML = '<li class="admin-picker__note">' + esc(msg) + '</li>';
  } else {
    var pickedId = roleUserAssign.picked ? String(roleUserAssign.picked.id) : '';
    box.innerHTML = users.map(function (u) {
      var selected = pickedId === String(u.id);
      return '<li role="presentation"><button type="button" class="admin-picker__option' + (selected ? ' is-selected' : '') + '"' +
          ' role="option" aria-selected="' + (selected ? 'true' : 'false') + '" data-role-user-pick="' + esc(String(u.id)) + '">' +
          '<span>' + esc(u.name) + '</span>' +
          '<span class="admin-picker__option-meta">ID: ' + esc(String(u.id)) + '</span>' +
        '</button></li>';
    }).join('');
  }
  box.hidden = false;
}

/** Текст в поле изменили: после выбора это сбрасывает выбранного пользователя. */
function onUserTyped(text) {
  var value = String(text || '').trim();
  var picked = roleUserAssign.picked;
  if (picked && value !== picked.name) {
    roleUserAssign.picked = null;
    if (roleUserAssign.select) {
      roleUserAssign.select.value = '';
      roleUserAssign.select.dispatchEvent(new Event('change'));
    }
  }
  roleUserAssign.userQuery = roleUserAssign.picked ? '' : value;
}

function pickUser(id) {
  var user = roleUserAssign.users.filter(function (u) { return String(u.id) === String(id); })[0];
  if (!user) return;
  roleUserAssign.picked = user;
  roleUserAssign.userQuery = '';
  if (roleUserAssign.userSearch) roleUserAssign.userSearch.value = user.name;
  closeRoleUserPicker();
  var select = roleUserAssign.select;
  if (select) {
    select.value = String(user.id);
    select.dispatchEvent(new Event('change'));   // дальше — прежняя логика: загрузка ролей пользователя
  }
}