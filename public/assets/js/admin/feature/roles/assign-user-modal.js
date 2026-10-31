/**
 * Модалка назначения ролей пользователю (чекбоксы ролей).
 */
import { tx } from '../../core/i18n.js';
import { notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';
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
  initialUserRoles: []
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
    .then(function (r) { return r.json(); })
    .then(function (data) {
      var items = (data && data.items) ? data.items : (Array.isArray(data) ? data : []);
      roleUserAssign.users = items.map(function (u) {
        return {
          id: u.id,
          name: u.username || u.name || u.login || u.email || ('User #' + u.id)
        };
      });
      return roleUserAssign.users;
    }).catch(function () { return []; });
}

export function openRoleUserModal(preselectedRoleId) {
  if (!roleUserAssign.modal) return;

  renderModalRolesCheckboxes(preselectedRoleId);
  if (roleUserAssign.errorEl) roleUserAssign.errorEl.hidden = true;
  if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;

  fetchUsersList().then(function (users) {
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
  roleUserAssign.modal.hidden = true;
  document.body.classList.remove('is-modal-open');
}

function loadUserCurrentRoles(userId) {
  if (roleUserAssign.loading) roleUserAssign.loading.hidden = false;
  if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = true;
  var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
  run('/api/system/users/' + userId + '/roles', { headers: { 'Accept': 'application/json' } })
    .then(function (r) { return r.json(); })
    .then(function (res) {
      var roles = (res && res.items) ? res.items : (Array.isArray(res) ? res : []);
      roleUserAssign.initialUserRoles = roles.map(function (x) {
        return Number(x.id !== undefined ? x.id : (x.roleId !== undefined ? x.roleId : x));
      });
      Array.prototype.forEach.call(roleUserAssign.rolesList.querySelectorAll('input[type="checkbox"]'), function (cb) {
        cb.checked = roleUserAssign.initialUserRoles.indexOf(Number(cb.value)) !== -1;
      });
    })
    .catch(function () {})
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

  Promise.all(requests).then(function () {
    notify(tx('admin.role_saved', 'Roles updated'), 'success');
    closeRoleUserModal();
  }).catch(function () {
    notify(tx('errors.common.request_failed', 'Request failed'), 'error');
  }).then(function () {
    if (roleUserAssign.saveBtn) roleUserAssign.saveBtn.disabled = false;
  });
}
