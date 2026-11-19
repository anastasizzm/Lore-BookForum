/**
 * Страница «Все роли»: таблица, поиск, фильтр видимости, удаление.
 */
import { t, tx } from '../../core/i18n.js';
import { confirmModal, notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';
import { allRolesApi } from './api.js';
import { rolesApi } from '../roles/api.js';
import { TRASH } from '../roles/constants.js';
import { roleState } from '../roles/state.js';

export var allRoles = {
  table: null, rows: null, empty: null, status: null, count: null, search: null,
  filterStatus: null,
  query: '',
  sort: '',            // '' | 'asc' | 'desc' — по Visibility, сортируется на клиенте
  seq: 0,
  items: []
};

function setAllRolesStatus(text) {
  if (!allRoles.status) return;
  allRoles.status.textContent = text || '';
  allRoles.status.hidden = !text;
}

function applyAllRolesFilters() {
  if (!allRoles.table) return;
  // Текст ищет бэк (по Enter, allRoles.query). Фильтр Public/Private и
  // сортировку по visibility делаем локально: ?status= и ?sort=visibility
  // бэк не поддерживает (B-24/B-24a).
  var q = allRoles.query || '';
  var statusVal = allRoles.filterStatus ? (allRoles.filterStatus.querySelector('input:checked') || {}).value : 'all';
  
  var rows = allRoles.table.querySelectorAll('tbody tr[data-search]');
  var shown = 0;
  
  Array.prototype.forEach.call(rows, function (row) {
    var rowStatus = row.getAttribute('data-status') || '';
    // Строки с неизвестным статусом не прячем — данных для фильтра нет.
    var hit = statusVal === 'all' || rowStatus === '' || rowStatus === statusVal;
    
    row.hidden = !hit;
    if (hit) shown++;
  });
  
  if (allRoles.count) allRoles.count.textContent = String(shown);
  if (allRoles.empty) {
    allRoles.empty.hidden = shown > 0;
    if (shown === 0) {
      var cell = allRoles.empty.querySelector('td') || allRoles.empty;
      cell.textContent = q ? t('admin.no_results', { query: q }) : t('admin.empty');
    }
  }
}

/** В списке All roles убраны права и выведена кнопка Delete */
/**
 * Бэк не умеет сортировать по visibility (BasicModelSortBy: alpha|newest),
 * поэтому Public/Private переставляем на клиенте. При серверной пагинации
 * сортировка неполная — это осознанный fallback до B-24a.
 */
function sortedItems() {
  var items = allRoles.items.slice();
  if (!allRoles.sort) return items;
  var rank = function (item) {
    if (item.visibility === 'public') return 0;
    if (item.visibility === 'private') return 1;
    return 2;
  };
  items.sort(function (a, b) {
    var diff = rank(a) - rank(b);
    if (allRoles.sort === 'desc') diff = -diff;
    return diff;
  });
  return items;
}

function paintSortHeader() {
  var th = allRoles.visTh;
  if (!th) return;
  var dir = allRoles.sort || '';
  th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : dir === 'desc' ? 'descending' : 'none');
  th.textContent = (allRoles.visLabel || '') + (dir === 'asc' ? ' \u25B2' : dir === 'desc' ? ' \u25BC' : '');
}

function initVisibilitySort() {
  var ths = allRoles.table ? allRoles.table.querySelectorAll('thead th') : [];
  var th = ths[2];                       // колонка Visibility: ID, Role, Visibility, Delete
  if (!th) return;
  allRoles.visTh = th;
  allRoles.visLabel = th.textContent.trim();
  allRoles.sort = '';
  th.style.cursor = 'pointer';
  th.style.userSelect = 'none';
  th.setAttribute('tabindex', '0');
  th.setAttribute('role', 'columnheader');
  var toggle = function () {
    allRoles.sort = allRoles.sort === '' ? 'asc' : allRoles.sort === 'asc' ? 'desc' : '';
    paintSortHeader();
    renderAllRoles();                    // сортируем загруженные строки на клиенте
  };
  th.addEventListener('click', toggle);
  th.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(); }
  });
  paintSortHeader();
}

export function renderAllRoles() {
  if (!allRoles.rows) return;
  
  var html = sortedItems().map(function (item) {
    var id = Number(item.id) || 0;
    var titleHtml = item.color ? '<span class="admin-role__dot" style="background:' + esc(item.color) + '"></span> ' : '';
    titleHtml += esc(item.title || 'Без названия');

    var isPrivate = item.visibility === 'private';
    var visHtml = !item.visibility
      ? '<span class="admin-badge admin-badge--muted">—</span>'
      : '<span class="admin-badge ' + (isPrivate ? 'admin-badge--muted' : 'admin-badge--success') + '">' +
          esc(isPrivate ? tx('admin.role_private', 'Private') : tx('admin.role_public', 'Public')) +
        '</span>';

    return '<tr data-allroles-row-id="' + id + '" data-search="' + esc(item.title.toLowerCase()) + '" data-status="' + esc(item.visibility) + '">' +
        '<td class="admin-table__id" data-col="id">' + id + '</td>' +
        '<td data-col="title"><span class="admin-role__title">' + titleHtml + '</span></td>' +
        '<td data-col="vis">' + visHtml + '</td>' +
        '<td data-col="delete"><div class="admin-actions">' + 
          '<button type="button" class="btn btn--danger admin-icon-btn" data-allroles-delete data-allroles-row-id="' + id + '"' + (id === 0 ? ' disabled' : '') +
            ' title="' + esc(t('admin.action_delete') || 'Delete') + '" aria-label="' + esc(t('admin.action_delete') || 'Delete') + '">' +
            TRASH +
          '</button>' +
        '</div></td>' +
      '</tr>';
  }).join('');
  
  var emptyRow = allRoles.rows.querySelector('[data-allroles-empty]');
  allRoles.rows.innerHTML = html;
  if (emptyRow) allRoles.rows.appendChild(emptyRow);
  
  applyAllRolesFilters();
}

export function ensureSystemRoles() {
  if (!allRoles.table) return Promise.resolve(true);
  return rolesApi.list().then(function (items) {
    if (!items) return false;
    roleState.items = items;
    return true;
  });
}

function loadAllRoles(query) {
  // Здесь нужны только id, название и видимость (public/private) — они приходят из
  // данных страницы или GET /api/additional/roles. Права системных ролей не грузим.
  return allRolesApi.list(query);
}

export function refreshAllRoles() {
  if (!allRoles.table) return Promise.resolve();
  // Обновляем с текущим поиском/фильтром/сортировкой, а не сбрасываем их;
  // seq отбрасывает устаревшие ответы (поздний ответ не затирает свежий поиск).
  var seq = ++allRoles.seq;
  return loadAllRoles(allRoles.query).then(function (items) {
    if (!items || seq !== allRoles.seq) return;
    allRoles.items = items;
    setAllRolesStatus('');
    renderAllRoles();
  });
}

export function deleteAllRole(btn) {
  var id = Number(btn.getAttribute('data-allroles-row-id') || 0);
  confirmModal({
    title:       tx('admin.role_delete_title', 'Delete role?'),
    text:        tx('admin.role_delete_text', 'The role will be permanently deleted. This action cannot be undone.'),
    confirmText: tx('admin.action_delete', 'Delete'),
    danger:      true
  }).then(function (confirmed) {
    if (!confirmed) return;
    btn.disabled = true;
    return allRolesApi.remove(id).then(function (result) {
      if (result.mode === 'forbidden') { notify(result.message || tx('js.no_rights', 'You do not have permission to do this'), 'error'); btn.disabled = false; return; }
      if (result.mode !== 'api') { notify(result.message || tx('errors.common.request_failed', 'Request failed'), 'error'); btn.disabled = false; return; }
      allRoles.items = allRoles.items.filter(function (x) { return Number(x.id) !== id; });
      renderAllRoles();
      notify(tx('admin.role_deleted', 'Role deleted'), 'success');
    });
  }).catch(function (err) {
    console.warn('[admin] all-roles delete failed', err);
    notify(tx('errors.common.network_error', 'Network error'), 'error');
    btn.disabled = false;
  });
}

function runAllRolesSearch() {
  var seq = ++allRoles.seq;
  return loadAllRoles(allRoles.query).then(function (items) {
    if (seq !== allRoles.seq) return;
    if (items === null) {
      notify(tx('admin.roles_load_error', 'Failed to load roles'), 'error');
      return;
    }
    allRoles.items = items;
    setAllRolesStatus('');
    renderAllRoles();
  });
}

export function initAllRoles() {
  var table = document.querySelector('[data-allroles-table]');
  var rows = document.querySelector('[data-allroles-rows]');
  if (!table || !rows) return;

  allRoles.table = table;
  allRoles.rows = rows;
  allRoles.empty = rows.querySelector('[data-allroles-empty]');
  allRoles.status = document.querySelector('[data-allroles-status]');
  allRoles.count = document.querySelector('[data-allroles-count]');
  allRoles.search = document.querySelector('[data-allroles-search]');
  allRoles.filterStatus = document.querySelector('[data-allroles-filter-status]');
  initVisibilitySort();

  if (allRoles.search) {
    allRoles.search.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      allRoles.query = (allRoles.search.value || '').trim();
      runAllRolesSearch();
    });
    allRoles.search.addEventListener('search', function () {
      if ((allRoles.search.value || '').trim() === '' && allRoles.query !== '') {
        allRoles.query = '';
        runAllRolesSearch();
      }
    });
  }
  if (allRoles.filterStatus) {
    // ?status= на бэке падает (B-24) — фильтруем уже загруженные строки.
    allRoles.filterStatus.addEventListener('change', function () {
      renderAllRoles();
    });
  }

  setAllRolesStatus(tx('admin.roles_loading', 'Loading roles…'));
  // Первая загрузка может прийти позже, чем пользователь успел поискать:
  // если за это время стартовал другой запрос (seq изменился), её ответ отбрасываем.
  var initSeq = ++allRoles.seq;
  loadAllRoles('').then(function (items) {
    if (initSeq !== allRoles.seq) return;
    if (items === null) {
      setAllRolesStatus(tx('admin.roles_load_error', 'Failed to load roles'));
      return;
    }
    allRoles.items = items;
    setAllRolesStatus('');
    renderAllRoles();
  });
}