/**
 * Модалка «Добавить роль пользователю»: дропдаун с поиском, сохранение.
 */
import { apiError, apiFetch } from '../../../core/http.js';
import { tx } from '../../../core/i18n.js';
import { notify } from '../../../core/ui.js';
import { esc } from '../../../core/utils.js';
import { allRolesApi } from '../../all-roles/api.js';
import { urCatalog } from './api.js';
import { urRender } from './list.js';
import { isUserRoleOpen, um, userRoles } from './state.js';

/* ---------- дропдаун выбора роли ---------- */
export function urDdClose() {
  var wrap = um('[data-urole-dd]');
  if (!wrap) return;
  wrap.classList.remove('is-open');
  var list = um('[data-urole-dd-list]');
  if (list) list.hidden = true;
  var input = um('[data-urole-dd-search]');
  if (input) input.setAttribute('aria-expanded', 'false');
}

export function urDdIsOpen() {
  var wrap = um('[data-urole-dd]');
  return !!(wrap && wrap.classList.contains('is-open'));
}

/** Открыть список под полем поиска (как .roles-picker в модалке ролей книги). */
export function urDdOpen() {
  var wrap = um('[data-urole-dd]');
  var list = um('[data-urole-dd-list]');
  var input = um('[data-urole-dd-search]');
  if (!wrap || !list || !input || input.disabled) return;
  wrap.classList.add('is-open');
  list.hidden = false;
  input.setAttribute('aria-expanded', 'true');
  urDdRenderMenu();
}

/** Ввод в поле: мгновенный фильтр по названию; серверный поиск — по Enter. */
export function urDdType(text) {
  var value = String(text || '').trim();
  var selected = userRoles.selected;
  if (selected && value !== String(selected.title || '')) {
    // текст изменили после выбора — выбор сбрасывается
    userRoles.selected = null;
    selected = null;
    urDdRenderValue();
    var save = um('[data-urole-save]');
    if (save) save.disabled = true;
  }
  userRoles.typed = selected ? '' : value;
  if (!value && userRoles.query) { urSearch(''); return; }
  urDdOpen();
}

function urDdRenderValue() {
  var input = um('[data-urole-dd-search]');
  var wrap = um('[data-urole-dd]');
  var dot = um('[data-urole-dd-dot]');
  if (!input) return;
  if (input.dataset.basePlaceholder === undefined) input.dataset.basePlaceholder = input.getAttribute('placeholder') || '';
  var sel = userRoles.selected;
  if (sel) {
    input.value = sel.title || '';
    if (dot) { dot.style.background = sel.color || ''; dot.hidden = !sel.color; }
    if (wrap) wrap.classList.toggle('has-dot', !!sel.color);
  } else {
    // ничего не выбрано: набранный текст остаётся, точка скрыта
    if (dot) dot.hidden = true;
    if (wrap) wrap.classList.remove('has-dot');
  }
}

/** Опции с учётом мгновенного фильтра по набранному тексту. */
function urVisibleOptions() {
  var typed = String(userRoles.typed || '').toLowerCase();
  if (!typed) return userRoles.options;
  return userRoles.options.filter(function (r) { return String(r.title || '').toLowerCase().indexOf(typed) !== -1; });
}

function urDdRenderMenu() {
  var list = um('[data-urole-dd-list]');
  if (!list) return;
  var shown = urVisibleOptions();
  var curId = userRoles.selected ? userRoles.selected.id : null;
  if (!shown.length) {
    var msg = userRoles.searching ? tx('common.common.loading', 'Loading...')
      : (userRoles.query || userRoles.typed) ? tx('admin.role_dd_nothing', 'Nothing found')
      : tx('admin.role_none_available', 'No roles available');
    list.innerHTML = '<li class="admin-picker__note">' + esc(msg) + '</li>';
    return;
  }
  list.innerHTML = shown.map(function (r) {
    var on = r.id === curId;
    return '<li role="presentation"><button type="button" class="admin-picker__option' + (on ? ' is-selected' : '') + '"' +
      ' role="option" aria-selected="' + (on ? 'true' : 'false') + '" data-urole-dd-pick="' + r.id + '">' +
      (r.color ? '<span class="admin-role__dot" style="background:' + esc(r.color) + '"></span>' : '') +
      '<span>' + esc(r.title) + '</span></button></li>';
  }).join('');
}

/** Роли, которые ещё можно выдать: из системных и которых у пользователя нет. */
function urAvailable(names) {
  var cur = userRoles.current;
  var owned = {};
  ((cur && userRoles.cache[cur.id]) || []).forEach(function (r) { owned[r.id] = true; });
  // Пустой systemIds ({}) = список системных ролей не распознан: фильтр не применяем,
  // иначе отфильтруются все роли. Недопустимую роль отклонит бэк при сохранении.
  var hasSystemIds = !!(userRoles.systemIds && Object.keys(userRoles.systemIds).length);
  if (userRoles.systemIds && !hasSystemIds) {
    console.warn('[admin] user roles: system role ids are empty, the system-role filter is skipped');
  }
  return names.filter(function (r) {
    if (owned[Number(r.id)]) return false;
    return hasSystemIds ? userRoles.systemIds[Number(r.id)] === true : true;
  });
}

/** Поиск ролей через API (GET /api/additional/roles?q=…) — только по Enter. */
export function urSearch(query) {
  var q = String(query || '').trim();
  userRoles.typed = '';   // результат сервера показываем целиком
  urDdOpen();
  if (q === userRoles.query && !userRoles.searching) return;
  userRoles.query = q;
  userRoles.searching = true;
  urDdRenderMenu();
  var seq = ++userRoles.seq;
  allRolesApi.list(q, 'all').then(function (names) {
    if (seq !== userRoles.seq) return;
    userRoles.searching = false;
    if (names === null) { notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error'); return; }
    userRoles.options = urAvailable(names).map(function (r) { return { id: Number(r.id), title: r.title, color: r.color || '' }; });
    urDdRenderMenu();
  });
}

export function urPick(id) {
  var picked = userRoles.options.filter(function (r) { return r.id === Number(id); })[0];
  if (!picked) return;
  userRoles.selected = picked;
  userRoles.typed = '';
  urDdRenderValue();
  urDdRenderMenu();
  urDdClose();
  var save = um('[data-urole-save]');
  if (save) save.disabled = false;
}

/* ---------- модалка ---------- */
export function urOpenAdd(btn) {
  if (!userRoles.modal) return;
  var id = btn.getAttribute('data-user-id');
  userRoles.current = { id: id, name: btn.getAttribute('data-user-name') || ('#' + id) };
  userRoles.selected = null;
  userRoles.options = [];
  userRoles.query = '';
  userRoles.typed = '';
  userRoles.searching = true;
  userRoles.submitting = false;
  var seq = ++userRoles.seq;

  var nameEl = um('[data-urole-user]');
  if (nameEl) nameEl.textContent = userRoles.current.name;
  var search = um('[data-urole-dd-search]');
  if (search) search.value = '';
  var save = um('[data-urole-save]');
  if (save) save.disabled = true;
  var toggle = um('[data-urole-dd-search]');   // поле поиска: заблокировано, пока грузятся роли
  if (toggle) toggle.disabled = true;
  urDdClose();
  urDdRenderValue();
  urDdRenderMenu();

  userRoles.lastFocus = document.activeElement;
  userRoles.modal.hidden = false;
  document.body.classList.add('is-modal-open');

  Promise.all([allRolesApi.list('', 'all'), urCatalog()]).then(function (res) {
    if (seq !== userRoles.seq) return;
    var names = res[0];
    userRoles.searching = false;
    if (toggle) toggle.disabled = false;
    if (names === null) {
      notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
      userRoles.options = [];
    } else {
      userRoles.options = urAvailable(names).map(function (r) { return { id: Number(r.id), title: r.title, color: r.color || '' }; });
    }
    urDdRenderMenu();
    if (toggle) toggle.focus();
  });
}

export function urClose() {
  if (!isUserRoleOpen()) return;
  urDdClose();
  userRoles.modal.hidden = true;
  userRoles.seq++;
  document.body.classList.remove('is-modal-open');
  var target = userRoles.lastFocus;
  userRoles.lastFocus = null;
  if (target && target.isConnected && target.offsetParent !== null) {
    try { target.focus(); } catch (_) {}
  }
}

export function urSave() {
  var cur = userRoles.current;
  var sel = userRoles.selected;
  if (!cur || !sel || userRoles.submitting) return;
  userRoles.submitting = true;
  var save = um('[data-urole-save]');
  if (save) save.disabled = true;

  apiFetch('POST', '/api/system/users/' + encodeURIComponent(cur.id) + '/roles', { roleId: sel.id }).then(function (r) {
    if (!r.ok) { notify(apiError(r), 'error'); return; }
    var list = userRoles.cache[cur.id] || [];
    if (!list.some(function (x) { return x.id === sel.id; })) list.push({ id: sel.id, title: sel.title, color: sel.color });
    userRoles.cache[cur.id] = list;
    urRender(cur.id);
    notify(tx('admin.role_saved', 'Roles updated'), 'success');
    urClose();
  }).then(function () {
    userRoles.submitting = false;
    if (save) save.disabled = !userRoles.selected;
  });
}