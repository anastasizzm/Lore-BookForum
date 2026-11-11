/**
 * Таблица пользователей: GET /api/system/users?include=roles, поиск по Enter,
 * «Показать ещё». Роли приходят вместе с пользователями (include=roles),
 * поэтому отдельные запросы /users/{id}/roles для отрисовки не нужны.
 */
import { apiError, apiFetch } from '../../core/http.js';
import { t, tx } from '../../core/i18n.js';
import { esc, normalizeColor } from '../../core/utils.js';
import { userRoles } from './roles/state.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { takeBootstrap } from '../../core/bootstrap.js';

var usersList = {
  tbody: null, status: null, count: null, search: null, more: null,
  query: '', page: 1, ps: 20, total: null, hasMore: false,
  items: [], seq: 0, loading: false
};

function usersParse(payload) {
  if (payload === null || payload === undefined) return null;
  var list = listFrom(payload, ['users']);
  if (!list) return null;
  var inner = (!Array.isArray(payload) && payload && typeof payload === 'object' &&
               payload.data && typeof payload.data === 'object' && !Array.isArray(payload.data))
    ? payload.data : payload;

  var total = null;
  [inner, payload].forEach(function (src) {
    if (total !== null || !src || typeof src !== 'object' || Array.isArray(src)) return;
    ['total', 'totalCount', 'count'].forEach(function (k) {
      if (total === null && src[k] != null && isFinite(Number(src[k]))) total = Number(src[k]);
    });
  });

  var meta = (!Array.isArray(payload) && payload && typeof payload === 'object' &&
              payload.meta && typeof payload.meta === 'object' && !Array.isArray(payload.meta))
    ? payload.meta : null;

  return {
    items: list.map(usersNormalize).filter(function (x) { return x !== null; }),
    total: total,
    hasNext: meta && typeof meta.hasNext === 'boolean' ? meta.hasNext : null
  };
}

function usersNormalize(u) {
  u = unwrapItem(u);
  if (!u || typeof u !== 'object') return null;
  var id = Number(u.id !== undefined ? u.id : u.userId);
  if (!id && id !== 0) return null;
  var statusText = String(u.status || '').toLowerCase();
  var roles = Array.isArray(u.roles) ? u.roles.map(function (r) {
    if (typeof r === 'string') return r;
    var node = (r && r.role && typeof r.role === 'object') ? r.role : r;
    return node ? String(node.title || node.name || '') : '';
  }).filter(function (x) { return x !== ''; }) : [];
  // Роли объектами с id (как отдаёт сервер) — сразу годятся для вложенного списка ролей,
  // отдельный запрос при раскрытии строки не нужен.
  var roleObjs = null;
  if (Array.isArray(u.roles) && u.roles.every(function (r) {
    var node = (r && r.role && typeof r.role === 'object') ? r.role : r;
    return node && typeof node === 'object' && node.id != null && isFinite(Number(node.id));
  })) {
    roleObjs = u.roles.map(function (r) {
      var node = (r && r.role && typeof r.role === 'object') ? r.role : r;
      return { id: Number(node.id), title: String(node.title || node.name || ('#' + node.id)), color: normalizeColor(node.color || '') };
    });
  }
  return {
    id: id,
    username: String(u.username || u.name || u.login || u.nickname || ''),
    name: String(u.name || ''),
    surname: String(u.surname || ''),
    email: String(u.email || ''),
    blocked: u.blocked === true || u.isBlocked === true || u.is_blocked === true ||
      Number(u.blocked || u.isBlocked || u.is_blocked) === 1 || /block|ban/.test(statusText),
    canManage: u.canManage !== false,
    roles: roles,
    roleObjs: roleObjs
  };
}

function roleCellHtml(roles) {
  return roles.length
    ? roles.slice(0, 2).map(function (r) { return '<span class="admin-badge">' + esc(r) + '</span>'; }).join(' ') +
      (roles.length > 2 ? ' <span class="admin-badge admin-badge--muted">+' + (roles.length - 2) + '</span>' : '')
    : '<span class="admin-badge admin-badge--muted">—</span>';
}

function blockButtonHtml(u) {
  var blocked = !!u.blocked;
  return '<button type="button" class="btn btn--secondary"' +
    (blocked ? ' data-admin-unblock' : ' data-admin-block') +
    ' data-user-id="' + u.id + '" data-user-name="' + esc(u.username) + '"' +
    (!u.canManage ? ' disabled' : '') + '>' +
    esc(t(blocked ? 'admin.action_unblock' : 'admin.action_block')) +
  '</button>';
}

function usersRowHtml(u) {
  var id = u.id;
  var roleCell = roleCellHtml(u.roles);
  var statusCell = u.blocked
    ? '<span class="admin-badge admin-badge--danger">' + esc(t('admin.status_blocked')) + '</span>'
    : '<span class="admin-badge admin-badge--success">' + esc(t('admin.status_active')) + '</span>';
  var rolesLabel = tx('admin.user_roles_toggle', 'Roles');
  var blockBtn = blockButtonHtml(u);

  return '<tr data-user-id="' + id + '"' + (u.blocked ? ' data-user-blocked="1"' : '') + '>' +
      '<td class="admin-table__id" data-col="id">' + id + '</td>' +
      '<td data-col="name">' + esc(u.username) + '</td>' +
      '<td data-col="email"><span class="admin-table__clip" title="' + esc(u.email) + '">' + esc(u.email) + '</span></td>' +
      '<td data-col="role">' + roleCell + '</td>' +
      '<td data-col="status" data-status-cell>' + statusCell + '</td>' +
      '<td data-col="actions"><div class="admin-actions">' +
        '<a class="btn btn--secondary" href="/users/' + id + '">' + esc(t('admin.action_view')) + '</a>' +
        blockBtn +
      '</div></td>' +
      '<td class="admin-table__expand" data-col="expand">' +
        '<button type="button" class="admin-expand" data-user-expand' +
          ' data-user-id="' + id + '" data-user-name="' + esc(u.username) + '"' +
          ' aria-expanded="false" title="' + esc(rolesLabel) + '" aria-label="' + esc(rolesLabel) + '">' +
          '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false">' +
            '<path d="M3.5 6l4.5 4.5L12.5 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>' +
          '</svg>' +
        '</button>' +
      '</td>' +
    '</tr>' +
    '<tr class="admin-user-roles" data-user-roles-row="' + id + '" hidden>' +
      '<td colspan="7"><div class="admin-user-roles__body" data-user-roles-body></div></td>' +
    '</tr>';
}

function setUsersStatus(text) {
  if (!usersList.status) return;
  usersList.status.textContent = text || '';
  usersList.status.hidden = !text;
}

function usersRender() {
  if (!usersList.tbody) return;
  if (!usersList.items.length) {
    usersList.tbody.innerHTML = '<tr class="admin-table__empty"><td colspan="7">' +
      esc(usersList.query ? t('admin.no_results', { query: usersList.query }) : t('admin.empty')) + '</td></tr>';
  } else {
    usersList.tbody.innerHTML = usersList.items.map(usersRowHtml).join('');
  }
  if (usersList.count) {
    usersList.count.textContent = String(usersList.total !== null ? usersList.total : usersList.items.length);
  }
  if (usersList.more) usersList.more.hidden = !usersList.hasMore;
}

/** Применяет разобранную страницу пользователей к состоянию и перерисовывает таблицу. */
function usersApply(parsed, reset) {
  var st = usersList;
  var known = {};
  var base = reset ? [] : st.items;
  base.forEach(function (u) { known[u.id] = true; });
  var fresh = parsed.items.filter(function (u) { return !known[u.id]; });

  st.items = base.concat(fresh);
  st.total = parsed.total;
  if (parsed.hasNext !== null && parsed.hasNext !== undefined) {
    st.hasMore = parsed.hasNext === true && fresh.length > 0;
  } else {
    // Страниц больше нет, если новых строк не пришло (бэк мог проигнорировать page)
    st.hasMore = fresh.length > 0 && (parsed.total !== null
      ? st.items.length < parsed.total
      : parsed.items.length >= st.ps);
  }
  if (reset) userRoles.cache = {};
  // Роли, пришедшие вместе с пользователями, кладём в кэш — раскрытие строки обойдётся без запросов
  parsed.items.forEach(function (u) { if (u.roleObjs) userRoles.cache[u.id] = u.roleObjs; });
  setUsersStatus('');
  usersRender();
}

var SEARCH_MAX_PAGES = 20;

function usersMatches(u, query) {
  var haystack = [u.username, u.name, u.surname, u.email].join(' ').toLowerCase();
  return haystack.indexOf(query.toLowerCase()) !== -1;
}

/**
 * У GET /api/system/users нет серверного q, поэтому поиск идёт по всем
 * страницам system users с include=roles и фильтруется на клиенте.
 * Так сохраняются e-mail, роли, isBlocked и canManage (в /api/users их нет).
 */
function usersSearchAll(seq) {
  var acc = [];

  function loadPage(page) {
    var url = '/api/system/users?include=roles&page=' + page + '&ps=50';
    return apiFetch('GET', url).then(function (r) {
      if (seq !== usersList.seq) return null;
      if (!r.ok) return { error: r };
      var parsed = usersParse(r.data);
      if (!parsed) return { error: r };
      acc = acc.concat(parsed.items);
      var more = parsed.hasNext === true ||
        (parsed.hasNext === null && parsed.items.length >= 50);
      if (more && page < SEARCH_MAX_PAGES) return loadPage(page + 1);
      return { items: acc, truncated: more };
    });
  }

  return loadPage(1);
}

/** reset=true — новый запрос (поиск), иначе подгрузка следующей страницы. */
function usersLoad(reset) {
  var st = usersList;
  if (!st.tbody) return Promise.resolve();
  if (reset) { st.page = 1; }
  var seq = ++st.seq;

  // Первая страница без поиска — из данных, которые сервер положил в страницу (без запроса к API)
  if (reset && !st.query) {
    var boot = takeBootstrap('users');
    var seeded = boot === null ? null : usersParse(boot);
    if (seeded && seeded.items.length) {
      st.loading = false;
      usersApply(seeded, true);
      return Promise.resolve();
    }
  }

  st.loading = true;
  if (st.more) st.more.disabled = true;
  setUsersStatus(tx('admin.users_loading', 'Loading users…'));

  var done = function (parsed, error) {
    if (seq !== st.seq) return;
    st.loading = false;
    if (st.more) st.more.disabled = false;
    if (!parsed) {
      setUsersStatus(tx('admin.users_load_error', 'Failed to load users') + ': ' + (error || tx('errors.common.server_error', 'Server error. Please try again later.')));
      if (reset) { st.items = []; st.total = null; st.hasMore = false; usersRender(); }
      return;
    }
    usersApply(parsed, reset);
  };

  if (reset && st.query) {
    return usersSearchAll(seq).then(function (result) {
      if (seq !== st.seq) return;
      if (!result || result.error) {
        var err = result && result.error;
        done(null, err ? apiError(err) : '');
        return;
      }
      var query = st.query;
      var matched = result.items.filter(function (u) { return usersMatches(u, query); });
      done({ items: matched, total: matched.length, hasNext: false }, null);
      if (result.truncated) {
        setUsersStatus(tx('admin.users_search_truncated',
          'Showing the first :limit users. Refine the search.',
          { limit: SEARCH_MAX_PAGES * 50 }));
      }
    });
  }

  var url = '/api/system/users?include=roles&page=' + st.page + '&ps=' + st.ps;
  return apiFetch('GET', url).then(function (r) {
    done(r.ok ? usersParse(r.data) : null, r.ok ? '' : apiError(r));
  });
}

function usersSearch(query) {
  var q = String(query || '').trim();
  if (q === usersList.query && usersList.items.length) return;
  usersList.query = q;
  usersLoad(true);
}

/** Синхронизирует статус и кнопку Block/Unblock после ответа API. */
export function setUserBlocked(userId, blocked) {
  var id = Number(userId);
  if (!id) return;
  var user = usersList.items.filter(function (u) { return u.id === id; })[0] || null;
  if (user) user.blocked = !!blocked;

  var row = document.querySelector('tr[data-user-id="' + id + '"]');
  if (!row) return;
  if (blocked) row.setAttribute('data-user-blocked', '1');
  else row.removeAttribute('data-user-blocked');

  var statusCell = row.querySelector('[data-status-cell]');
  if (statusCell) {
    statusCell.innerHTML = blocked
      ? '<span class="admin-badge admin-badge--danger">' + esc(t('admin.status_blocked')) + '</span>'
      : '<span class="admin-badge admin-badge--success">' + esc(t('admin.status_active')) + '</span>';
  }

  var oldBtn = row.querySelector('[data-admin-block], [data-admin-unblock]');
  if (!oldBtn) return;
  oldBtn.outerHTML = blockButtonHtml({
    id: id,
    username: user ? user.username : (oldBtn.getAttribute('data-user-name') || ''),
    blocked: !!blocked,
    canManage: user ? user.canManage : !oldBtn.disabled
  });
}

export function initUsersList() {
  var tbody = document.querySelector('[data-users-rows]');
  if (!tbody) return;
  usersList.tbody = tbody;
  usersList.status = document.querySelector('[data-users-status]');
  usersList.count = document.querySelector('[data-users-count]');
  usersList.search = document.querySelector('[data-users-search]');
  usersList.more = document.querySelector('[data-users-more]');

  if (usersList.search) {
    // Запрос в API — только по Enter, не на каждый символ
    usersList.search.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      usersSearch(usersList.search.value);
    });
    usersList.search.addEventListener('search', function () {
      if ((usersList.search.value || '').trim() === '' && usersList.query !== '') usersSearch('');
    });
  }

  if (usersList.more) {
    usersList.more.addEventListener('click', function () {
      if (usersList.loading) return;
      usersList.page += 1;
      usersLoad(false);
    });
  }

  usersLoad(true);
}
