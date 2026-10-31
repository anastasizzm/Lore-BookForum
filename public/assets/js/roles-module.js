/**
 * roles-module.js — модуль «назначение ролей пользователям» (4 экрана
 * из Спецификации UI). Разметка: src/partials/roles-module.php,
 * стили: public/assets/css/roles-module.css.
 *
 * ЭКРАНЫ (data-roles-screen):
 *   1 — привязка: таблетка «Roles | Users», список ролей с порядком (ранг)
 *       или список пользователей с ролями, «+» → экран 3, «назад»;
 *   2 — сводка + Publish;
 *   3 — добавление/редактирование записи (поиск + чипы, Save/Save more);
 *   4 — права роли (чекбоксы, If-Match).
 *
 * ХОСТЫ (диалоги публикации) зовут:
 *   LoreRoles.open(root, {
 *     publicationId,                  // id книги/статьи (уже созданы)
 *     mode: 'publish' | 'roles',      // publish — Publish завершает публикацию,
 *                                     // roles   — Publish просто сохраняет роли
 *     onPublish: function (done) {},  // логика хоста; done() — вернуть кнопку
 *     onCancel:  function () {}       // стрелка «назад» / отмена
 *   })
 *   LoreRoles.reset(root)             // при закрытии диалога
 *
 * КОНТРАКТ С БЭКОМ — в шапке src/partials/roles-module.php.
 * Недоступные роуты (404/HTML/сеть) гасятся: статус data-api у корня
 * становится 'mock' и список рисуется на встроенных данных — так же,
 * как в publication-roles.js и admin.js.
 */
window.LoreRoles = (function () {
  'use strict';

  var PUB_BASE = '/api/publications/';
  var SYSTEM_USERS_URL = '/api/system/users?ps=50';
  var SYSTEM_ROLES_URL = '/api/system/roles?ps=50';

  /** Ключи pubPermissions (бэк: PublicationAccessRules::toArray). */
  var PUB_PERMS = [
    'create', 'updateMeta', 'updateContent',
    'read', 'managePeople', 'manageRoles'
  ];

  /** Мок-данные, пока роуты ролей не развёрнуты (data-api="mock"). */
  var MOCK = {
    roles: [
      { id: '0', title: 'Owner',  color: '#2F9E44', version: 1, canManage: false, perms: {} },
      { id: '1', title: 'Editor', color: '#1C7ED6', version: 1, canManage: true,  perms: {} },
      { id: '2', title: 'Author', color: '#F76707', version: 1, canManage: true,  perms: {} }
    ],
    users: [
      { id: '1', username: 'mossy_otter42',  canManage: true, roles: [{ id: '1', title: 'Editor', color: '#1C7ED6' }] },
      { id: '2', username: 'teal_penguin7',  canManage: true, roles: [{ id: '2', title: 'Author', color: '#F76707' }] }
    ],
    // Каталог людей (GET /api/system/users) для поиска на экране 3
    catalogUsers: [
      { id: '1', username: 'mossy_otter42',  roles: [] },
      { id: '2', username: 'teal_penguin7',  roles: [] },
      { id: '3', username: 'rusty_kazoo',    roles: [] },
      { id: '4', username: 'neon_wombat',    roles: [] },
      { id: '5', username: 'salty_comet9',   roles: [] }
    ],
    // Каталог ролей (GET /api/system/roles) для выбора/вставки роли
    catalogRoles: [
      { id: '1', title: 'Editor',  color: '#1C7ED6' },
      { id: '2', title: 'Author',  color: '#F76707' },
      { id: '3', title: 'Moderator', color: '#9C36B5' },
      { id: '4', title: 'Reviewer',  color: '#1098AD' }
    ]
  };

  /* ============================================================
     ХЕЛПЕРЫ
     ============================================================ */

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn('[roles-module] ' + text);
  }

  function csrfFetch(url, opts) {
    if (window.LoreCsrf && typeof window.LoreCsrf.fetch === 'function') {
      return window.LoreCsrf.fetch(url, opts);
    }
    opts = opts || {};
    var headers = new Headers(opts.headers || {});
    var token = window.LoreCsrf && typeof window.LoreCsrf.token === 'function'
      ? window.LoreCsrf.token() : '';
    if (token && !headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', token);
    opts.headers = headers;
    if (!opts.credentials) opts.credentials = 'same-origin';
    return fetch(url, opts);
  }

  /* ============================================================
     СОСТОЯНИЕ (модуль один на страницу — по одному корню у хоста)
     ============================================================ */

  var st = {
    root: null,
    pubId: 0,
    mode: 'publish',
    onPublish: null,
    onCancel: null,
    api: 'unknown',        // unknown | live | mock | denied | error
    forceMock: false,      // демо-режим: open({ mock: true }) — без сети
    loading: false,
    error: '',
    roles: [],
    users: [],
    expanded: {},          // раскрытые строки пользователей (экран 2): userId -> true
    page: 'users',         // страница экрана 1: users | roles
    screen: 1,             // экран 1 | 2
    context: 'create',     // create | edit — флоу создания (Create → Continue → Publish)
                            // или редактирования (Edit → без Continue / без Publish)
    dndReady: true,        // ручки перетаскивания и точки вставки активны
    publishing: false,
    lastFocus: null,
    bound: false,
    docBound: false,

    /** Экран 3 — добавление/редактирование записи (поиск + чипы). */
    add: {
      open: false,
      mode: 'add',         // add | edit
      userId: '',          // режим edit: кого редактируем
      userLocked: false,   // в edit пользователь не меняется
      users: [],           // выбранные люди  [{ id, username }]
      roles: [],           // выбранные роли  [{ id, title, color }]
      catalogUsers: [],    // каталог людей (GET /api/system/users)
      catalogRoles: [],    // каталог ролей (GET /api/system/roles)
      catalogFallback: false, // людей ищем по уже привязанным (роута нет)
      roleFallback: false,
      userPage: 1,
      userHasNext: false,
      loadingUsers: false,
      userOpen: false,     // выпадающий список людей раскрыт
      roleOpen: false,
      q: '',               // строка поиска людей
      roleQ: '',
      selectedRoleId: '', // выбранная роль в модалке «роль + пользователь»
      status: '',
      saving: false,
      lastFocus: null,
      loadedOnce: false,
      loadedFallback: false,
      original: []         // роли пользователя до редактирования (retrieve)
    },

    /** Экран 4 — права роли (чекбоксы + If-Match). */
    perms: {
      open: false,
      roleId: '',
      title: '',
      draft: {},
      version: 1,
      saving: false,
      status: '',
      lastFocus: null
    },

    /** Текущее перетаскивание (drag & drop порядка ролей). */
    drag: null,            // { index, id }

    /** Оверлей вставки роли («+» между строками). */
    insert: {
      open: false,
      index: 0,            // позиция «перед индексом» в текущем массиве
      catalog: [],
      loading: false,
      status: '',
      lastFocus: null
    },

    /** Очередь запросов на смену позиции (зависимые ждут, нет зависят). */
    moves: {
      inflight: [],        // [{ keys: {id:true}, job }]
      pending: []          // [job]
    }
  };

  function q(sel, scope) {
    var root = scope || st.root;
    return root ? root.querySelector(sel) : null;
  }

  function qa(sel, scope) {
    var root = scope || st.root;
    return root ? Array.prototype.slice.call(root.querySelectorAll(sel)) : null;
  }

  function show(node, visible) {
    if (node) node.hidden = !visible;
  }

  /* ============================================================
     ТРАНСПОРТ: запрос + классификация ответа
     ============================================================ */

  function parseJson(raw) {
    if (!raw || !/[\[{]/.test(raw)) return null;
    try { return JSON.parse(raw); } catch (_) { return null; }
  }

  /** kind: ok | missing (404/405/HTML) | denied (401/403) | error | network */
  function request(method, url, body, extraHeaders) {
    // Мок-режим (нет бэка / демо-страница): никаких сетевых запросов,
    // каждый вызов «успешен» — правки живут только в этом окне.
    if (st.api === 'mock') {
      return Promise.resolve({ kind: 'ok', status: 200, ok: true, data: null, raw: '', etag: '' });
    }

    var opts = {
      method: method,
      credentials: 'same-origin',
      headers: Object.assign({
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }, extraHeaders || {})
    };

    if (body !== undefined && body !== null) {
      if (typeof body === 'string') {
        opts.body = body;
        opts.headers['Content-Type'] = 'application/json';
      } else {
        opts.body = body;   // FormData — Content-Type ставит браузер
      }
    }

    return csrfFetch(url, opts).then(function (res) {
      return res.text().then(function (raw) {
        var isHtml = /^\s*</.test(raw || '');
        var hasJson = /[\[{]/.test(raw || '');
        var kind = 'ok';

        if (res.status === 401 || res.status === 403) {
          kind = 'denied';
        } else if (res.ok) {
          // роут отвечает HTML — перед нами страница, а не API
          kind = isHtml ? 'missing' : 'ok';
        } else if ((res.status === 404 || res.status === 405) && !hasJson) {
          // роут не развёрнут: HTML/пусто от роутера или прокси
          kind = 'missing';
        } else {
          kind = 'error';
        }

        return {
          kind: kind,
          status: res.status,
          ok: res.ok,
          data: parseJson(raw),
          raw: raw,
          etag: (res.headers && res.headers.get && res.headers.get('ETag')) || ''
        };
      });
    }).catch(function () {
      return { kind: 'network', status: 0, ok: false, data: null, raw: '' };
    });
  }

  function listItems(payload) {
    var p = payload;
    if (p && typeof p === 'object' && p.data && typeof p.data === 'object' && !Array.isArray(p.data)) {
      p = p.data;
    }
    if (Array.isArray(p)) return p;
    if (p && Array.isArray(p.items)) return p.items;
    if (p && Array.isArray(p.data)) return p.data;
    return null;
  }

  /* ============================================================
     НОРМАЛИЗАЦИЯ
     ============================================================ */

  function normalizeRole(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var role = raw.role && typeof raw.role === 'object' ? raw.role : raw;
    var id = role.id != null ? role.id : raw.roleId;
    if (id == null) return null;
    return {
      id: String(id),
      title: String(role.title || role.name || ('#' + id)),
      color: String(role.color || ''),
      version: parseInt(raw.version, 10) || 1,
      canManage: raw.canManage !== false,
      perms: raw.pubPermissions && typeof raw.pubPermissions === 'object' ? raw.pubPermissions : null
    };
  }

  function normalizeRoleLite(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var id = raw.id != null ? raw.id : raw.roleId;
    if (id == null) return null;
    return {
      id: String(id),
      title: String(raw.title || raw.name || ('#' + id)),
      color: String(raw.color || '')
    };
  }

  function normalizeUser(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var user = raw.user && typeof raw.user === 'object' ? raw.user : raw;
    var id = user.id != null ? user.id : raw.id;
    if (id == null) return null;
    var roles = Array.isArray(raw.roles) ? raw.roles
      : (Array.isArray(user.roles) ? user.roles : []);
    return {
      id: String(id),
      username: String(user.username || user.name || ('#' + id)),
      roles: roles.map(normalizeRoleLite).filter(function (one) { return one !== null; }),
      canManage: raw.canManage !== false
    };
  }

  function mapList(payload, normalize) {
    var items = listItems(payload);
    if (!items) return null;
    return items.map(normalize).filter(function (one) { return one !== null; });
  }

  /* ============================================================
     ЗАГРУЗКА (роли + пользователи параллельно)
     ============================================================ */

  function base() { return PUB_BASE + st.pubId; }

  function load() {
    if (st.forceMock) {
      // Демо-режим: мок-данные сразу, без сетевых запросов
      st.api = 'mock';
      st.loading = false;
      st.error = '';
      st.roles = MOCK.roles.map(function (one) { return Object.assign({}, one); });
      st.users = MOCK.users.map(function (one) { return Object.assign({}, one); });
      var addMock = st.add;
      addMock.catalogUsers = MOCK.catalogUsers.map(function (one) { return Object.assign({}, one); });
      addMock.catalogRoles = MOCK.catalogRoles.map(function (one) { return Object.assign({}, one); });
      addMock.userPage = 1;
      addMock.userHasNext = false;
      addMock.loadingUsers = false;
      addMock.loadingRoles = false;
      addMock.userOpen = false;
      addMock.roleOpen = false;
      render();
      return Promise.resolve();
    }

    if (!st.pubId) {
      st.api = 'unknown';
      st.loading = false;
      st.error = '';
      st.roles = [];
      st.users = [];
      render();
      return Promise.resolve();
    }

    st.loading = true;
    st.error = '';
    render();

    return Promise.all([
      request('GET', base() + '/roles'),
      request('GET', base() + '/users?include=roles&ps=50')
    ]).then(function (results) {
      var rolesRes = results[0];
      var usersRes = results[1];

      // Оба роута отсутствуют → мок-режим (контракт бэка ещё не развёрнут)
      if (rolesRes.kind === 'missing' && usersRes.kind === 'missing') {
        st.api = 'mock';
        st.roles = MOCK.roles.map(function (one) { return Object.assign({}, one); });
        st.users = MOCK.users.map(function (one) { return Object.assign({}, one); });
        var addMock2 = st.add;
        addMock2.catalogUsers = MOCK.catalogUsers.map(function (one) { return Object.assign({}, one); });
        addMock2.catalogRoles = MOCK.catalogRoles.map(function (one) { return Object.assign({}, one); });
        addMock2.userPage = 1;
        addMock2.userHasNext = false;
        addMock2.loadingUsers = false;
        addMock2.loadingRoles = false;
        st.loading = false;
        render();
        return;
      }

      if (rolesRes.kind === 'denied' && usersRes.kind === 'denied') {
        st.api = 'denied';
        st.loading = false;
        st.error = t('js.no_rights');
        render();
        return;
      }

      if (rolesRes.kind === 'network' && usersRes.kind === 'network') {
        st.api = 'error';
        st.loading = false;
        st.error = t('errors.common.network_error');
        render();
        return;
      }

      st.api = 'live';
      st.roles = rolesRes.kind === 'ok' ? (mapList(rolesRes.data, normalizeRole) || []) : [];
      st.users = usersRes.kind === 'ok' ? (mapList(usersRes.data, normalizeUser) || []) : [];
      st.loading = false;

      var broken = rolesRes.kind === 'error' || usersRes.kind === 'error'
        || rolesRes.kind === 'denied' || usersRes.kind === 'denied';
      if (broken && !st.roles.length && !st.users.length) {
        st.error = t('errors.common.request_failed');
      }
      render();
    });
  }

  function reload() {
    st.api = 'unknown';
    load();
  }

  /* ============================================================
     RENDER: состояния + экран 1
     ============================================================ */

  function setApi(api) {
    if (st.root) st.root.setAttribute('data-api', api);
  }

  function renderStates() {
    var body = q('[data-roles-body]');
    var loading = q('[data-roles-loading]');
    var error = q('[data-roles-error]');
    var errorText = q('[data-roles-error-text]');
    var mockNote = q('[data-roles-mock-note]');

    show(loading, st.loading);
    show(error, !st.loading && !!st.error);
    if (errorText && st.error) errorText.textContent = st.error;
    show(mockNote, !st.loading && st.api === 'mock');

    var showBody = !st.loading && !st.error;
    show(body, showBody);

    setApi(st.api);
  }

  function countRoleUsers(roleId) {
    var count = 0;
    st.users.forEach(function (user) {
      if (user.roles.some(function (role) { return role.id === roleId; })) count++;
    });
    return count;
  }

  function handleSvg() {
    return '<svg width="10" height="14" viewBox="0 0 10 14" fill="currentColor" aria-hidden="true">' +
      '<circle cx="2" cy="2.5" r="1.2"/><circle cx="8" cy="2.5" r="1.2"/>' +
      '<circle cx="2" cy="7" r="1.2"/><circle cx="8" cy="7" r="1.2"/>' +
      '<circle cx="2" cy="11.5" r="1.2"/><circle cx="8" cy="11.5" r="1.2"/></svg>';
  }

  /**
   * Закреплённая верхняя зона: позиция «перед индексом i» недопустима,
   * если выше неё (в строках i..n-1) стоит роль, которой текущий
   * пользователь не управляет — такую роль нельзя двигать и нельзя
   * бросать выше неё (спека: invalid-позиция не подсвечивается).
   */
  function gapValid(index) {
    for (var i = index; i < st.roles.length; i++) {
      if (!st.roles[i].canManage) return false;
    }
    return true;
  }

  function gapHtml(index) {
    if (!gapValid(index)) return '';
    return '<li class="roles-gap" data-gap-index="' + index + '">' +
      '<button type="button" class="roles-gap__plus" data-gap-add="' + index + '"' +
      ' title="' + esc(t('roles.insert_role')) + '"' +
      ' aria-label="' + esc(t('roles.insert_role')) + '">+</button>' +
    '</li>';
  }

  function roleRowHtml(role, index) {
    var locked = !role.canManage;
    var handle = st.dndReady && !locked
      ? '<span class="roles-row__handle" data-role-handle' +
        ' title="' + esc(t('roles.drag_hint')) + '">' + handleSvg() + '</span>'
      : '';
    var users = countRoleUsers(role.id);

    return '<li class="roles-row' + (locked ? ' is-locked' : '') + '"' +
      ' data-role-id="' + esc(role.id) + '"' +
      ' data-role-index="' + index + '"' +
      (st.dndReady && !locked ? ' draggable="true"' : '') + '>' +
      handle +
      '<span class="roles-row__dot" style="background:' + esc(role.color || '#5876A6') + '"></span>' +
      '<span class="roles-row__main">' +
        '<span class="roles-row__title">' + esc(role.title) + '</span>' +
        '<span class="roles-row__meta">' + esc(t('roles.users_count', { count: users })) + '</span>' +
      '</span>' +
      '<span class="roles-row__actions">' +
        '<button type="button" class="roles-row__btn" data-role-perms="' + esc(role.id) + '"' +
          (locked ? ' disabled' : '') + '>' + esc(t('roles.perms')) + '</button>' +
        '<button type="button" class="roles-row__btn" data-role-remove="' + esc(role.id) + '"' +
          ' aria-label="' + esc(t('roles.remove_role_aria', { name: role.title })) + '"' +
          (locked ? ' disabled' : '') + '>&times;</button>' +
      '</span>' +
    '</li>';
  }

  function renderRoles() {
    var list = q('[data-roles-list]');
    var empty = q('[data-roles-empty="roles"]');
    if (!list) return;

    if (!st.roles.length) {
      list.innerHTML = '';
      show(empty, true);
      return;
    }
    show(empty, false);

    // Чередуем точки вставки и строки: gap(0), row(0), gap(1) … gap(n)
    var html = '';
    st.roles.forEach(function (role, index) {
      html += gapHtml(index) + roleRowHtml(role, index);
    });
    html += gapHtml(st.roles.length);
    list.innerHTML = html;
  }

  /** Наивысшая по рангу роль — роль с минимальным индексом в порядке st.roles (ранги). */
  function topRoleOf(user) {
    var roles = user.roles || [];
    if (!roles.length) return null;
    var best = roles[0];
    var bestIndex = Infinity;
    roles.forEach(function (role) {
      st.roles.forEach(function (rank, index) {
        if (String(rank.id) === String(role.id) && index < bestIndex) {
          bestIndex = index;
          best = role;
        }
      });
    });
    return best;
  }

  var CHEVRON_SVG = '<svg class="roles-users__chevron" width="16" height="16" viewBox="0 0 16 16" fill="none"' +
    ' stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
    '<path d="M4 6l4 4 4-4"/></svg>';

  /** Индекс высшей роли пользователя в списке рангов (∞ — ролей нет). */
  function topRankIndex(user) {
    var top = topRoleOf(user);
    if (!top) return Infinity;
    var idx = Infinity;
    st.roles.forEach(function (rank, i) { if (String(rank.id) === String(top.id)) idx = i; });
    return idx;
  }

  function renderUsers() {
    var list = q('[data-roles-users]');
    var table = q('[data-roles-users-table]');
    var empty = q('[data-roles-empty="users"]');
    var hint = q('[data-roles-empty-hint="users"]');
    if (!list) return;
    list.hidden = false;
    if (table) table.hidden = !st.users.length;

    if (!st.users.length) {
      list.innerHTML = '';
      show(empty, true);
      show(hint, true);
      return;
    }
    show(empty, false);
    show(hint, false);

    // Порядок по рангу: чем выше роль в списке rank'ов, тем раньше пользователь;
    // люди без ролей — в конец, при равенстве — по алфавиту.
    var users = st.users.slice().sort(function (a, b) {
      var diff = topRankIndex(a) - topRankIndex(b);
      if (diff) return diff;
      return String(a.username).localeCompare(String(b.username));
    });

    list.innerHTML = users.map(function (user) {
      var expanded = !!st.expanded[String(user.id)];
      var top = topRoleOf(user);

      // колонка ROLE: пилюля высшей роли (или тире, если ролей нет)
      var topHtml = top
        ? '<span class="roles-users__top-role">' +
            '<span class="roles-chip__dot" style="background:' + esc(top.color || '#5876A6') + '"></span>' +
            '<span>' + esc(top.title) + '</span>' +
          '</span>'
        : '<span class="roles-users__top-role roles-users__top-role--none" aria-label="' +
            esc(t('roles.no_roles')) + '">—</span>';

      var rolesHtml;
      if (user.roles && user.roles.length) {
        rolesHtml = user.roles.map(function (role) {
          return '<li class="roles-chip">' +
            '<span class="roles-chip__dot" style="background:' + esc(role.color || '#5876A6') + '"></span>' +
            '<span>' + esc(role.title) + '</span>' +
            '<button type="button" class="roles-chip__x"' +
              ' data-user-role-remove="' + esc(String(role.id)) + '"' +
              ' data-user-id="' + esc(user.id) + '"' +
              ' aria-label="' + esc(t('roles.remove_role_from_user', { name: user.username, role: role.title })) + '"' +
              (user.canManage ? '' : ' disabled') + '>×</button>' +
          '</li>';
        }).join('');
      } else {
        rolesHtml = '<li class="roles-chip roles-chip--muted">' + esc(t('roles.no_roles')) + '</li>';
      }

      return '<li class="roles-users__item' + (expanded ? ' is-expanded' : '') + '" data-user-id="' + esc(user.id) + '">' +
        '<button type="button" class="roles-users__row" data-user-expand="' + esc(user.id) + '"' +
          ' aria-expanded="' + (expanded ? 'true' : 'false') + '"' +
          ' aria-label="' + esc(expanded ? t('roles.collapse') : t('roles.expand')) + '">' +
          '<span class="roles-users__name">' + esc(user.username) + '</span>' +
          topHtml +
          CHEVRON_SVG +
        '</button>' +
        '<div class="roles-users__detail"' + (expanded ? '' : ' hidden') + '>' +
          '<ul class="roles-users__roles">' + rolesHtml + '</ul>' +
          '<button type="button" class="roles-users__add"' +
            ' data-user-add-role="' + esc(user.id) + '"' +
            ' aria-label="' + esc(t('roles.add_role_aria', { name: user.username })) + '"' +
            (user.canManage ? '' : ' disabled') + '>+</button>' +
        '</div>' +
      '</li>';
    }).join('');
  }

  function renderPage() {
    var isRoles = st.page === 'roles';
    qa('[data-roles-tab]').forEach(function (btn) {
      var active = btn.getAttribute('data-roles-tab') === st.page;
      btn.classList.toggle('is-active', active);
      btn.setAttribute('aria-pressed', String(active));
    });
    show(q('[data-roles-page="roles"]'), isRoles);
    show(q('[data-roles-page="users"]'), !isRoles);
  }

  function renderScreen() {
    qa('[data-roles-screen]').forEach(function (screen) {
      var no = screen.getAttribute('data-roles-screen');
      screen.hidden = (no !== String(st.screen));
    });
  }

  function updateGate() {
    var hasUsers = st.users.length > 0;
    var next = q('[data-roles-next]');
    // Continue на экране 1 есть только при создании (редактирование идёт дальше
    // через экран 2 / управление ролями напрямую).
    if (next) next.hidden = st.context !== 'create';
    // Экран 2: Publish требует хотя бы одного пользователя.
    var pub = q('[data-roles-publish]');
    if (pub) pub.disabled = !hasUsers;
    show(q('[data-roles-gate]'), !hasUsers);
  }

  function render() {
    renderStates();
    if (q('[data-roles-body]') && q('[data-roles-body]').hidden) return;
    if (st.screen === 1) {
      // Экран 1 — страница ролей публикации.
      renderRoles();
    } else {
      // Экран 2 — страница пользователей публикации.
      var usersBody = q('[data-roles-users-body]');
      if (usersBody) show(usersBody, true);
      renderUsers();
    }
    renderScreen();
    updateGate();
  }

  /* ============================================================
     ЭКРАН 2: PUBLISH
     ============================================================ */

  function setPublishing(busy) {
    st.publishing = busy;
    var btn = q('[data-roles-publish]');
    if (btn) btn.disabled = busy;
  }

  function publish() {
    if (st.publishing) return;
    if (typeof st.onPublish !== 'function') {
      notify(t('roles.publish_done'), 'success');
      return;
    }
    setPublishing(true);
    st.onPublish(function done() { setPublishing(false); });
  }

  /* ============================================================
     ЭКРАН 3. Добавление / редактирование (поиск + чипы)
     ============================================================ */

  function okKind(res) { return res.kind === 'ok' || res.kind === 'missing'; }

  function num(value) { return parseInt(value, 10) || 0; }

  function findUser(id) {
    var found = null;
    st.users.some(function (one) {
      if (one.id === String(id)) { found = one; return true; }
      return false;
    });
    return found;
  }

  function localEnsureUser(id, username, roles) {
    var user = findUser(id);
    if (!user) {
      user = {
        id: String(id),
        username: String(username || ('#' + id)),
        roles: (roles || []).map(function (role) {
          return { id: role.id, title: role.title, color: role.color };
        }),
        canManage: true
      };
      st.users.push(user);
    }
    return user;
  }

  function localAddRoleToUser(userId, role) {
    var user = localEnsureUser(userId, '');
    if (!user.roles.some(function (one) { return one.id === role.id; })) {
      user.roles.push({ id: role.id, title: role.title, color: role.color });
    }
  }

  function localRemoveRoleFromUser(userId, roleId) {
    var user = findUser(userId);
    if (user) user.roles = user.roles.filter(function (one) { return one.id !== roleId; });
  }

  /** Локальное создание роли (мок / оптимистично): роль добавляется в конец списка. */
  function addLocalRole(id, picked, perms) {
    var newId = String(id != null && id !== '' ? id : ('local-' + Date.now()));
    if (findRole(newId)) return;
    st.roles.push({
      id: newId,
      title: picked.title,
      color: picked.color || '',
      version: 1,
      canManage: true,
      perms: Object.assign({}, perms || {})
    });
  }

  /* ---------- жизненный цикл оверлея ---------- */

  function resetAdd() {
    var add = st.add;
    add.mode = 'add';
    add.userId = '';
    add.userLocked = false;
    add.users = [];
    add.roles = [];
    add.original = [];
    add.q = '';
    add.roleQ = '';
    add.status = '';
    add.saving = false;
    add.userOpen = false;
    add.roleOpen = false;
    add.selectedRoleId = '';
    add.userPage = 1;
    add.userHasNext = false;
  }

  function setAddStatus(text) {
    st.add.status = text || '';
    var status = q('[data-roles-create-status]') || q('[data-roles-create-user-status]');
    if (status) {
      status.textContent = st.add.status;
      status.hidden = !st.add.status;
    }
  }

  function closeCreateModals() {
    st.add.open = false;
    st.add.userOpen = false;
    st.add.roleOpen = false;
    if (st.perms.source === 'create') st.perms.open = false;
    var a = q('[data-roles-create-role]');
    if (a) a.hidden = true;
    var u = q('[data-roles-create-user]');
    if (u) u.hidden = true;
    if (st.add.lastFocus && st.add.lastFocus.focus) {
      try { st.add.lastFocus.focus(); } catch (_) {}
    }
    st.add.lastFocus = null;
  }

  /* ---------- МОДАЛКА: создание роли (экран 1, «+») ---------- */

  function openCreateRole() {
    var add = st.add;
    add.open = true;
    add.lastFocus = document.activeElement;

    var overlay = q('[data-roles-create-role]');
    if (overlay) overlay.hidden = false;

    // права для новой роли: все выключены (роль создаётся «с нуля»)
    st.perms.source = 'create';
    st.perms.open = true;
    st.perms.roleId = '';
    st.perms.title = t('roles.create_title');
    st.perms.version = 1;
    st.perms.saving = false;
    st.perms.status = '';
    st.perms.draft = {};
    PUB_PERMS.forEach(function (key) { st.perms.draft[key] = false; });
    st.perms.lastFocus = document.activeElement;

    var pickerInput = q('[data-roles-create-role-search]');
    if (pickerInput) {
      pickerInput.value = '';
      pickerInput.setAttribute('aria-expanded', 'false');
      add.roleQ = '';
      add.roleOpen = false; // список ролей раскрывается по клику/фокусу (как на фото)
    }
    add.roles = []; // выбор роли не переносится между открытиями модалки

    // Список чекбоксов прав публикации заполняет renderPerms() (ниже).
    var createSave = q('[data-roles-create-add]');
    if (createSave) createSave.disabled = true; // до выбора роли кнопка неактивна
    var createCancel = q('[data-roles-create-cancel]');
    if (createCancel) createCancel.disabled = false;
    setPermsStatus('');

    renderPerms();
    if (pickerInput && pickerInput.focus) { try { pickerInput.focus(); } catch (_) {} }
    renderAddResults();
  }

  function saveCreateRole() {
    var perms = st.perms;
    var add = st.add;
    if (perms.saving) return;

    // название новой роли — выбранная в списке кандидатов роль каталога
    var picked = add.roles[0];
    if (!picked) { setAddStatus(t('roles.need_roles')); return; }
    setAddStatus('');
    perms.saving = true;

    if (st.api === 'mock') {
      // роутов нет — роль создаётся локально (мок)
      addLocalRole('', picked, perms.draft);
      perms.saving = false;
      closeCreateModals();
      render();
      notify(t('roles.saved'), 'success');
      return;
    }

    // создание роли + сразу её права (POST /roles может быть ещё не развёрнут бэком)
    request('POST', base() + '/roles', JSON.stringify({ title: picked.title }))
      .then(function (res) {
        if (res.kind === 'ok' || res.kind === 'missing') {
          var data = res.data || {};
          var newId = data.id != null ? data.id : (data.data && data.data.id != null ? data.data.id : '');
          if (res.kind === 'ok' && newId !== '') {
            addLocalRole(newId, picked, perms.draft);
            perms.roleId = String(newId);
            applyPermsLocally();
          }
          perms.saving = false;
          closeCreateModals();
          render();
          notify(t('roles.saved'), 'success');
        } else {
          perms.saving = false;
          notify(describe(res, t('roles.save_failed', { status: res.status })));
        }
      });
  }

  /* ---------- МОДАЛКА: создание «роль + пользователь» ---------- */

  function openCreateUserModal(userId) {
    var add = st.add;
    add.open = true;
    st.perms.source = null; // модалка «роль + пользователь» не трогает права
    add.lastFocus = document.activeElement;
    add.userId = userId || '';
    // Вариант с конкретным человеком («+» в раскрытой строке) — только ДОБАВЛЕНИЕ ролей:
    // выбор начинается пустым, уже привязанные роли в saveAdd пропускаются.
    add.mode = 'add';
    add.userLocked = !!userId;
    add.users = userId ? [{ id: userId, username: ((findUser(userId) || { username: '' }).username) || ('#' + userId) }] : [];
    add.roles = [];
    add.original = [];
    add.status = '';
    add.saving = false;
    add.userOpen = !userId; // add — сразу показываем каталог людей; edit — после «retrieve»
    add.roleOpen = false;   // дропдаун роли закрыт — раскрывается по клику по триггеру
    add.q = '';
    add.roleQ = '';
    add.selectedRoleId = add.roles[0] ? add.roles[0].id : '';

    var overlay = q('[data-roles-create-user]');
    if (overlay) overlay.hidden = false;
    renderAdd();

    // фокус: человек ещё не выбран — в поиск людей, иначе — в поиск ролей
    var focusTarget = add.userLocked
      ? q('[data-roles-create-user-role-search]')
      : q('[data-roles-create-user-user-search]');
    if (focusTarget && focusTarget.focus) { try { focusTarget.focus(); } catch (_) {} }
  }

  function closeCreateUserModal() { closeCreateModals(); }

  function saveCreateUser() {
    // create (пользователь + роль) и edit (смена ролей пользователя) — одна сохранёнка
    saveAdd(false);
  }

  /** Редактирование ролей пользователя (кнопка «Изменить» в списке). */
  function openEdit(userId) {
    var user = findUser(userId);
    if (!user) { notify(t('roles.users_empty'), 'warning'); return; }
    openCreateUserModal(user.id);
  }

  /* ---------- каталоги для поиска ---------- */

  function loadUserCatalog(page, append) {
    var add = st.add;
    if (add.catalogFallback) return Promise.resolve();

    // Мок-режим: каталог людей без сети
    if (st.api === 'mock') {
      add.loadingUsers = false;
      if (!append) {
        add.catalogUsers = MOCK.catalogUsers.map(function (one) { return Object.assign({}, one); });
        add.userPage = 1;
      }
      add.userHasNext = false;
      renderAddResults();
      return Promise.resolve();
    }

    add.loadingUsers = true;
    if (!append) renderAddResults();

    return request('GET', SYSTEM_USERS_URL + '&page=' + page).then(function (res) {
      add.loadingUsers = false;

      if (res.kind === 'ok') {
        var items = mapList(res.data, normalizeUser) || [];
        add.catalogUsers = append ? add.catalogUsers.concat(items) : items;
        add.userPage = page;
        var meta = res.data && res.data.meta ? res.data.meta : {};
        add.userHasNext = !!meta.hasNext;
      } else if (res.kind === 'missing' || res.kind === 'denied') {
        // Каталога людей нет — ищем среди уже привязанных пользователей
        add.catalogFallback = true;
        add.catalogUsers = st.users.slice();
        add.userHasNext = false;
        if (!add.loadedFallback) {
          add.loadedFallback = true;
          notify(t('roles.search_unavailable'), 'warning');
        }
      } else {
        add.catalogUsers = st.users.slice();
        add.userHasNext = false;
      }

      renderAddResults();
    });
  }

  function loadRoleCatalog() {
    var add = st.add;

    // Мок-режим: каталог ролей без сети
    if (st.api === 'mock') {
      add.catalogRoles = MOCK.catalogRoles.map(function (one) { return Object.assign({}, one); });
      add.roleFallback = false;
      renderAddResults();
      return Promise.resolve();
    }

    return request('GET', SYSTEM_ROLES_URL).then(function (res) {
      if (res.kind === 'ok') {
        add.catalogRoles = mapList(res.data, normalizeRole) || [];
        add.roleFallback = false;
      } else {
        // Каталога нет — предлагаем роли, уже привязанные к публикации
        add.catalogRoles = st.roles.map(function (role) {
          return { id: role.id, title: role.title, color: role.color, version: role.version, canManage: role.canManage, perms: null };
        });
        add.roleFallback = true;
      }
      renderAddResults();
    });
  }

  function matchQuery(item, query) {
    if (!query) return true;
    var haystack = [
      item.username, item.title, item.name, item.email
    ].filter(Boolean).join(' ').toLowerCase();
    return haystack.indexOf(query.toLowerCase()) >= 0;
  }

  function filteredUsers() {
    var add = st.add;
    return add.catalogUsers.filter(function (user) { return matchQuery(user, add.q); }).slice(0, 30);
  }

  function filteredRoles() {
    var add = st.add;
    return add.catalogRoles.filter(function (role) { return matchQuery(role, add.roleQ); }).slice(0, 30);
  }

  /* ---------- рендер оверлея ---------- */

  function chipHtml(title, color, removeAttr, locked) {
    var dot = color
      ? '<span class="roles-chip__dot" style="background:' + esc(color) + '"></span>'
      : '';
    var remove = locked ? '' :
      '<button type="button" class="roles-chip__x" ' + removeAttr +
      ' aria-label="' + esc(t('roles.remove')) + '">&times;</button>';
    return '<li class="roles-chip">' + dot +
      '<span class="roles-chip__title">' + esc(title) + '</span>' + remove + '</li>';
  }

  /* ---------- рендер модалок: создание «роль + пользователь» / «роль + …» ---------- */

  function createUserModalOpen() {
    var overlay = q('[data-roles-create-user]');
    return !!(overlay && !overlay.hidden);
  }

  function renderAdd() {
    var add = st.add;

    var modalU = q('[data-roles-create-user]');
    var modalR = q('[data-roles-create-role]');
    // одновременно открыты быть не могут: source === 'create' — модалка создания роли
    var showCreateRole = add.open && st.perms.source === 'create';
    var showUserModal = add.open && !showCreateRole;
    if (modalU) modalU.hidden = !showUserModal;
    if (modalR) modalR.hidden = !showCreateRole;

    setAddStatus(add.status);

    if (showUserModal) {
      var statusU = q('[data-roles-create-user-status]');
      if (statusU) { statusU.textContent = add.status; statusU.hidden = !add.status; }

      // Вариант «добавить роль конкретному человеку»: блок выбора пользователя скрыт целиком.
      var userPicker = q('[data-roles-create-user-user-picker]');
      if (userPicker) userPicker.hidden = !!add.userLocked;

      var titleU = q('[data-roles-create-user-title]');
      var hintU = q('[data-roles-create-user-hint]');
      if (titleU) titleU.textContent = add.userLocked ? t('roles.create_title') : t('roles.create_user_title');
      if (hintU) hintU.textContent = add.userLocked ? t('roles.add_role_hint') : t('roles.create_user_hint');

      var userInput = q('[data-roles-create-user-user-search]');
      if (userInput) {
        if (add.userLocked) { userInput.disabled = true; userInput.value = add.users[0] ? (add.users[0].username || '') : ''; }
        else { userInput.disabled = add.saving; userInput.value = add.q || ''; }
      }

      // Поле ролей — поиск с выпадающим списком (как у пользователей): пока
      // открыт список, в поле текст запроса; после выбора — название роли.
      var roleInput = q('[data-roles-create-user-role-search]');
      if (roleInput) {
        roleInput.disabled = add.saving;
        roleInput.setAttribute('aria-expanded', add.roleOpen ? 'true' : 'false');
        var pickedRole = add.roles[0];
        roleInput.value = add.roleQ || (pickedRole ? pickedRole.title : '');
      }
    }

    if (showCreateRole) {
      // поле роли в модалке создания — тот же паттерн поиска с выпадающим списком
      var createRoleInput = q('[data-roles-create-role-search]');
      if (createRoleInput) {
        createRoleInput.disabled = st.perms.saving;
        createRoleInput.setAttribute('aria-expanded', add.roleOpen ? 'true' : 'false');
        var createPicked = add.roles[0];
        createRoleInput.value = add.roleQ || (createPicked ? createPicked.title : '');
      }
      // кнопка Add активна только когда роль выбрана (как «Save» на макете)
      var createSaveBtn = q('[data-roles-create-add]');
      if (createSaveBtn) createSaveBtn.disabled = st.perms.saving || !add.roles.length;
    }

    renderAddResults();
  }

  function renderAddResults() {
    var add = st.add;

    // --- модалка «роль + пользователь» (экран 2): пользователи + роли ---
    var userBox = q('[data-roles-create-user-user-results]');
    var roleBox = q('[data-roles-create-user-role-results]');
    if (userBox) {
      if (!add.userOpen) { userBox.hidden = true; userBox.innerHTML = ''; }
      else {
        var users = filteredUsers();
        var userHtml = users.map(function (user) {
          var selected = add.users.some(function (one) { return one.id === user.id; });
          return '<li><button type="button" class="roles-picker__option' + (selected ? ' is-selected' : '') + '" data-add-user-opt="' + esc(user.id) + '" data-username="' + esc(user.username) + '">' + '<span>' + esc(user.username) + '</span></button></li>';
        }).join('');
        if (!users.length) userHtml = '<li class="roles-picker__option" aria-disabled="true">' + esc(add.loadingUsers ? t('roles.searching') : t('roles.nothing_found')) + '</li>';
        userBox.innerHTML = userHtml;
        userBox.hidden = false;
      }
    }

    if (roleBox) {
      if (!add.roleOpen) { roleBox.hidden = true; roleBox.innerHTML = ''; }
      else {
        var roles = filteredRoles();
        var selectedIds = {};
        add.roles.forEach(function (one) { selectedIds[String(one.id)] = true; });
        var roleHtml = roles.map(function (role) {
          var selected = !!selectedIds[String(role.id)];
          var dot = role.color ? '<span class="roles-chip__dot" style="background:' + esc(role.color) + '"></span>' : '';
          return '<li><button type="button" class="roles-picker__option' + (selected ? ' is-selected' : '') + '" data-add-role-opt="' + esc(String(role.id)) + '" data-roletitle="' + esc(role.title) + '" data-rolecolor="' + esc(role.color || '') + '">' + dot + '<span>' + esc(role.title) + '</span></button></li>';
        }).join('');
        if (!roles.length) roleHtml = '<li class="roles-picker__option" aria-disabled="true">' + esc(add.loadingRoles ? t('roles.searching') : t('roles.nothing_found')) + '</li>';
        roleBox.innerHTML = roleHtml;
        roleBox.hidden = false;
      }
    }

    // --- модалка «создание роли» (экран 1): список кандидатов для вставки ---
    var createRoleBox = q('[data-roles-create-role-results]');
    if (createRoleBox) {
      if (!add.roleOpen) { createRoleBox.hidden = true; createRoleBox.innerHTML = ''; }
      else {
        var candidatesSource = (st.add.catalogRoles && st.add.catalogRoles.length)
          ? st.add.catalogRoles
          : (st.insert.catalog || []);
        var candidates = candidatesSource.filter(function (role) {
          return !st.roles.some(function (one) { return String(one.id) === String(role.id); });
        });
        var pickedIds = {};
        add.roles.forEach(function (one) { pickedIds[String(one.id)] = true; });
        var createHtml = candidates.map(function (role) {
          var selected = pickedIds[String(role.id)] ? ' is-selected' : '';
          var dot = role.color ? '<span class="roles-chip__dot" style="background:' + esc(role.color) + '"></span>' : '';
          return '<li><button type="button" class="roles-picker__option' + selected + '" data-add-role-opt="' + esc(String(role.id)) + '" data-roletitle="' + esc(role.title) + '" data-rolecolor="' + esc(role.color || '') + '">' + dot + '<span>' + esc(role.title) + '</span></button></li>';
        }).join('');
        if (!candidates.length) createHtml = '<li class="roles-picker__option" aria-disabled="true">' + esc(add.loadingRoles ? t('roles.searching') : t('roles.nothing_found')) + '</li>';
        createRoleBox.innerHTML = createHtml;
        createRoleBox.hidden = false;
      }
    }
  }

  /* ---------- выбор чипов / опций ---------- */

  function toggleAddUser(id, username) {
    var add = st.add;
    if (add.userLocked) return;
    var userId = String(id);
    var exists = add.users.some(function (one) { return one.id === userId; });
    // Один человек за раз: выбор заменяет предыдущего, повторный клик снимает выбор.
    add.users = exists ? [] : [{ id: userId, username: String(username || ('#' + id)) }];
    if (!exists && createUserModalOpen()) {
      // в поле поиска остаётся выбранный человек, список закрывается
      add.q = String(username || ('#' + id));
      add.userOpen = false;
    }
    renderAdd();
  }

  function toggleAddRole(id, title, color) {
    var add = st.add;
    var roleId = String(id);
    var exists = add.roles.some(function (one) { return String(one.id) === roleId; });
    // Одна роль за раз: выбор заменяет предыдущую роль, повторный клик снимает выбор.
    add.roles = exists
      ? []
      : [{ id: roleId, title: String(title || ('#' + id)), color: String(color || '') }];
    add.roleQ = ''; // в поле остаётся название выбранной роли (или пусто)
    // В модалке «роль + пользователь» выбор закрывает выпадающий список; в модалке
    // создания роли список остаётся открытым — там видна отметка выбранного.
    if (!exists && createUserModalOpen()) add.roleOpen = false;
    renderAdd();
  }

  /* ---------- сохранение (create user+role / edit user roles) ---------- */

  function makeAddRoleStep(userId, role) {
    return function () {
      return request('POST', base() + '/users/' + userId + '/roles',
        JSON.stringify({ roleId: num(role.id) })).then(function (res) {
          if (okKind(res)) localAddRoleToUser(userId, role);
          return res;
        });
    };
  }

  function makeRemoveRoleStep(userId, roleId) {
    return function () {
      return request('DELETE', base() + '/users/' + userId + '/roles/' + roleId)
        .then(function (res) {
          if (okKind(res)) localRemoveRoleFromUser(userId, roleId);
          return res;
        });
    };
  }

  function makeRemoveUserStep(userId) {
    return function () {
      return request('DELETE', base() + '/users/' + userId + '/roles')
        .then(function (res) {
          if (okKind(res)) {
            var user = findUser(userId);
            if (user) user.roles = [];
          }
          return res;
        });
    };
  }

  /** Последовательный прогон шагов: стоп на первой ошибке (форма остаётся). */
  function runSteps(steps) {
    var index = 0;
    function next() {
      if (index >= steps.length) return Promise.resolve({ ok: true });
      var step = steps[index++];
      return step().then(function (res) {
        if (!okKind(res)) return { ok: false, res: res };
        return next();
      });
    }
    return next();
  }

  function saveAdd(andMore) {
    var add = st.add;
    if (add.saving) return;

    if (!add.users.length) { setAddStatus(t('roles.need_users')); return; }
    if (!add.roles.length) { setAddStatus(t('roles.need_roles')); return; }
    setAddStatus('');

    add.saving = true;
    renderAdd();

    var steps = [];

    if (add.mode === 'edit') {
      // Изменение ролей — отдельный запрос на каждое добавление/снятие
      var userId = add.userId;
      var current = add.original.slice();
      add.roles.forEach(function (role) {
        if (current.indexOf(String(role.id)) < 0) steps.push(makeAddRoleStep(userId, role));
      });
      current.forEach(function (roleId) {
        var stillHas = add.roles.some(function (one) { return String(one.id) === String(roleId); });
        if (!stillHas) steps.push(makeRemoveRoleStep(userId, roleId));
      });
    } else {
      add.users.forEach(function (person) {
        var bound = findUser(person.id);
        if (!bound) {
          steps.push(function () {
            return request('POST', base() + '/users', JSON.stringify({
              userId: num(person.id),
              roleId: num(add.roles[0].id)
            })).then(function (res) {
              if (okKind(res)) localEnsureUser(person.id, person.username, [add.roles[0]]);
              return res;
            });
          });
          add.roles.slice(1).forEach(function (role) {
            steps.push(makeAddRoleStep(person.id, role));
          });
          return;
        }
        add.roles.forEach(function (role) {
          var already = bound.roles.some(function (one) { return String(one.id) === String(role.id); });
          if (!already) steps.push(makeAddRoleStep(person.id, role));
        });
      });
    }

    runSteps(steps).then(function (outcome) {
      add.saving = false;

      if (!outcome.ok) {
        var res = outcome.res;
        setAddStatus(describe(res, t('roles.save_failed', { status: res.status })));
        renderAdd();
        render();
        return;
      }

      notify(t('roles.saved'), 'success');
      render();

      if (andMore && add.mode === 'add') {
        add.users = [];
        add.roles = [];
        add.original = [];
        add.q = '';
        add.roleQ = '';
        add.status = '';
        add.userOpen = true;
        renderAdd();
        var search = q('[data-roles-create-user-user-search]');
        if (search) { try { search.focus(); } catch (_) {} }
        return;
      }

      // Сохранение из модалки (create / edit) завершено — закрываем окно.
      closeCreateModals();
    });
  }

  function findRole(roleId) {
    var found = null;
    st.roles.some(function (one) {
      if (one.id === String(roleId)) { found = one; return true; }
      return false;
    });
    return found;
  }

  function setPermsStatus(text) {
    st.perms.status = text || '';
    var status = q('[data-roles-perms-status]');
    if (status) {
      status.textContent = st.perms.status;
      status.hidden = !st.perms.status;
    }
  }

  function resetPerms() {
    st.perms.open = false;
    st.perms.source = null;
    st.perms.roleId = '';
    st.perms.title = '';
    st.perms.draft = {};
    st.perms.version = 1;
    st.perms.saving = false;
    st.perms.status = '';
    st.perms.lastFocus = null;
  }

  function openPerms(roleId) {
    var role = findRole(roleId);
    if (!role) return;

    var perms = st.perms;
    perms.source = 'edit';
    perms.open = true;
    perms.roleId = role.id;
    perms.title = role.title;
    perms.version = role.version || 1;
    perms.saving = false;
    perms.status = '';
    perms.draft = {};
    PUB_PERMS.forEach(function (key) {
      perms.draft[key] = !!(role.perms && role.perms[key]);
    });
    perms.lastFocus = document.activeElement;

    var overlay = q('[data-roles-perms]');
    if (overlay) overlay.hidden = false;
    renderPerms();

    var first = overlay ? overlay.querySelector('input[type="checkbox"]') : null;
    if (first && first.focus) { try { first.focus(); } catch (_) {} }
  }

  function closePerms() {
    if (!st.perms.open) return;
    st.perms.open = false;
    if (st.perms.source === 'create') { closeCreateModals(); return; }
    var overlay = q('[data-roles-perms]');
    if (overlay) overlay.hidden = true;
    if (st.perms.lastFocus && st.perms.lastFocus.focus) {
      try { st.perms.lastFocus.focus(); } catch (_) {}
    }
    st.perms.lastFocus = null;
  }

  function renderPerms() {
    var perms = st.perms;
    if (!perms.open) return;

    var title = (perms.source === 'create')
      ? q('[data-roles-create-role] [data-roles-create-title]')
      : q('[data-roles-perms] [data-roles-perms-title]');
    if (title) title.textContent = perms.source === 'create'
      ? t('roles.create_role_title')
      : (t('roles.perms_title') + ' — ' + perms.title);

    // Группы чекбоксов есть и в модалке создания, и в оверлее прав —
    // показываем нужную по контексту, а не первую попавшуюся в DOM.
    var group = (perms.source === 'create')
      ? q('[data-roles-create-role] [data-perms-group="pub"]')
      : q('[data-roles-perms] [data-perms-group="pub"]');
    show(group, true);

    var list = (perms.source === 'create')
      ? q('[data-roles-create-perms-list="pub"]')
      : q('[data-perms-list="pub"]');
    if (list) {
      list.innerHTML = PUB_PERMS.map(function (key) {
        // id с префиксом контекста: в DOM есть и модалка создания, и оверлей прав
        var id = 'roles-perm-' + (perms.source === 'create' ? 'new-' : 'edit-') + key;
        return '<label class="roles-perm" for="' + id + '">' +
          '<input type="checkbox" id="' + id + '" data-perm-key="' + key + '"' +
            (perms.draft[key] ? ' checked' : '') +
            (perms.saving ? ' disabled' : '') + '>' +
          '<span>' + esc(t('roles.perm_' + key)) + '</span>' +
        '</label>';
      }).join('');
    }

    var save = (perms.source === 'create')
      ? q('[data-roles-create-add]')
      : q('[data-roles-perms-save]');
    // в модалке создания Add активен только с выбранной ролью
    if (save) save.disabled = perms.saving || (perms.source === 'create' && !st.add.roles.length);
    var cancel = (perms.source === 'create')
      ? q('[data-roles-create-cancel]')
      : q('[data-roles-perms-cancel]');
    if (cancel) cancel.disabled = perms.saving;

    setPermsStatus(perms.status);
  }

  function applyPermsLocally() {
    var role = findRole(st.perms.roleId);
    if (role) {
      role.perms = Object.assign({}, st.perms.draft);
      role.version = st.perms.version;
    }
  }

  function savePerms() {
    var perms = st.perms;
    // «Создание роли» (модалка экрана 1) — отдельная сохранёнка POST /roles
    if (perms.source === 'create') { saveCreateRole(); return; }
    if (perms.saving || !perms.roleId) return;

    // Сначала меню чекбоксов, и только потом — плашка подтверждения перед сохранением
    confirmAction('roles.perms_confirm_title', 'roles.perms_confirm_text',
      { name: perms.title }, doSavePerms, 'roles.perms_save');
  }

  function doSavePerms() {
    var perms = st.perms;
    if (perms.saving || !perms.roleId) return;

    perms.saving = true;
    renderPerms();

    if (st.api === 'mock') {
      // роутов нет — правки живут только в этом окне
      applyPermsLocally();
      perms.saving = false;
      notify(t('roles.perms_saved'), 'success');
      closePerms();
      return;
    }

    request('PUT', base() + '/roles/' + perms.roleId,
      JSON.stringify({ pubPermissions: perms.draft }),
      { 'If-Match': '"' + perms.version + '"' }).then(function (res) {
        if (res.kind === 'ok' || res.kind === 'missing') {
          var next = parseInt(String(res.etag || '').replace(/"/g, ''), 10);
          perms.version = isNaN(next) ? perms.version + 1 : next;
          applyPermsLocally();
          perms.saving = false;
          notify(t('roles.perms_saved'), 'success');
          closePerms();
          render();
          return;
        }

        // Ошибка: форма остаётся открытой с введёнными данными
        perms.saving = false;
        if (res.kind === 'denied') setPermsStatus(t('js.no_rights'));
        else if (res.status === 409) setPermsStatus(t('roles.perms_conflict'));
        else setPermsStatus(describe(res, t('roles.save_failed', { status: res.status })));
        renderPerms();
      });
  }

  /* ============================================================
     ПОРЯДОК РОЛЕЙ: drag & drop + очередь зависимых запросов
     ============================================================ */

  /** Все роли, которых касается запрос (перемещаемая + её соседи). */
  function moveKeySet(roleId, aboveId, belowId) {
    var keys = {};
    keys[String(roleId)] = true;
    if (aboveId != null && aboveId !== '') keys[String(aboveId)] = true;
    if (belowId != null && belowId !== '') keys[String(belowId)] = true;
    return keys;
  }

  function keysIntersect(a, b) {
    for (var key in a) {
      if (Object.prototype.hasOwnProperty.call(b, key)) return true;
    }
    return false;
  }

  /**
   * Запрос на смену позиции. Зависимые (пересекающиеся по ролям-соседям)
   * кладутся в очередь, независимые уходят параллельно — UI не блокируется.
   */
  function submitMove(job) {
    job.keys = moveKeySet(job.roleId, job.aboveId, job.belowId);
    var moves = st.moves;
    var conflict = moves.inflight.some(function (one) {
      return keysIntersect(one.keys, job.keys);
    });
    if (conflict) moves.pending.push(job);
    else startMove(job);
  }

  function startMove(job) {
    var moves = st.moves;
    moves.inflight.push({ keys: job.keys, job: job });

    var role = findRole(job.roleId);
    var body = {};
    if (job.aboveId != null && job.aboveId !== '') body.aboveId = num(job.aboveId);
    if (job.belowId != null && job.belowId !== '') body.belowId = num(job.belowId);

    request('PATCH', base() + '/roles/' + job.roleId + '/position',
      JSON.stringify(body),
      { 'If-Match': '"' + (role ? role.version : 1) + '"' }
    ).then(function (res) {
      moves.inflight = moves.inflight.filter(function (one) { return one.job !== job; });

      if (res.kind === 'ok' || res.kind === 'missing') {
        var current = findRole(job.roleId);
        if (current) {
          var next = parseInt(String(res.etag || '').replace(/"/g, ''), 10);
          current.version = isNaN(next) ? current.version + 1 : next;
        }
      } else {
        // Ответ отрицательный — роль возвращается на прежнее место
        rollbackMove(job);
        notify(describe(res, t('roles.move_failed')));
      }

      render();
      flushMoves();
    });
  }

  /** После успеха/ошибки очередь двигается: ждущие зависимости проверяются заново. */
  function flushMoves() {
    var moves = st.moves;
    if (!moves.pending.length) return;
    var waiting = moves.pending;
    moves.pending = [];
    waiting.forEach(function (job) {
      var conflict = moves.inflight.some(function (one) {
        return keysIntersect(one.keys, job.keys);
      });
      if (conflict) moves.pending.push(job);
      else startMove(job);
    });
  }

  /**
   * Перемещение локально + запрос. Позиция описывается СОСЕДЯМИ
   * (above/below), а не числовым рангом; невалидных подсветок нет —
   * недоступные точки вставки в разметку не попадают (gapValid).
   */
  function performMove(fromIndex, toIndex) {
    var role = st.roles[fromIndex];
    if (!role) return;

    st.roles.splice(fromIndex, 1);
    var insertAt = toIndex > fromIndex ? toIndex - 1 : toIndex;
    if (insertAt < 0) insertAt = 0;
    if (insertAt > st.roles.length) insertAt = st.roles.length;

    var job = {
      roleId: role.id,
      aboveId: insertAt > 0 ? st.roles[insertAt - 1].id : null,
      belowId: insertAt < st.roles.length ? st.roles[insertAt].id : null,
      fromIndex: fromIndex
    };

    st.roles.splice(insertAt, 0, role);
    render();
    submitMove(job);
  }

  function rollbackMove(job) {
    var current = -1;
    st.roles.some(function (one, index) {
      if (one.id === job.roleId) { current = index; return true; }
      return false;
    });
    if (current < 0) return;
    var role = st.roles.splice(current, 1)[0];
    var back = job.fromIndex;
    if (back < 0) back = 0;
    if (back > st.roles.length) back = st.roles.length;
    st.roles.splice(back, 0, role);
  }

  function cleanupDrag() {
    st.drag = null;
    if (!st.root) return;
    st.root.classList.remove('is-dragging-role');
    qa('.roles-row.is-dragging').forEach(function (row) { row.classList.remove('is-dragging'); });
    qa('[data-gap-index].is-over').forEach(function (gap) { gap.classList.remove('is-over'); });
  }

  /* ---------- «+»: вставка роли из каталога бэка ---------- */

  function setInsertStatus(text) {
    st.insert.status = text || '';
    var status = q('[data-roles-insert-status]');
    if (status) {
      status.textContent = st.insert.status;
      status.hidden = !st.insert.status;
    }
  }

  function resetInsert() {
    st.insert.open = false;
    st.insert.index = 0;
    st.insert.catalog = [];
    st.insert.loading = false;
    st.insert.status = '';
    st.insert.saving = false;
    st.insert.lastFocus = null;
  }

  function insertCandidates() {
    var bound = st.roles.map(function (role) { return role.id; });
    return (st.insert.catalog || []).filter(function (role) {
      return bound.indexOf(role.id) < 0;
    });
  }

  function renderInsert() {
    var ins = st.insert;
    if (!ins.open) return;

    var list = q('[data-roles-insert-list]');
    if (list) {
      if (ins.loading) {
        list.innerHTML = '<li class="roles-picker__option" aria-disabled="true">' +
          esc(t('roles.searching')) + '</li>';
      } else {
        var candidates = insertCandidates();
        list.innerHTML = candidates.map(function (role) {
          var dot = role.color
            ? '<span class="roles-chip__dot" style="background:' + esc(role.color) + '"></span>'
            : '';
          return '<li><button type="button" class="roles-picker__option"' +
            ' data-insert-role-opt="' + esc(role.id) + '"' +
            ' data-roletitle="' + esc(role.title) + '"' +
            ' data-rolecolor="' + esc(role.color || '') + '"' +
            (ins.saving ? ' disabled' : '') + '>' + dot +
            '<span>' + esc(role.title) + '</span></button></li>';
        }).join('');
        if (!candidates.length) {
          list.innerHTML = '<li class="roles-picker__option" aria-disabled="true">' +
            esc(t('roles.nothing_found')) + '</li>';
        }
      }
    }

    setInsertStatus(ins.status);
    var cancel = q('[data-roles-insert-cancel]');
    if (cancel) cancel.disabled = ins.saving;
  }

  function openInsert(index) {
    var ins = st.insert;
    ins.open = true;
    ins.index = index;
    ins.status = '';
    ins.saving = false;
    ins.lastFocus = document.activeElement;

    var overlay = q('[data-roles-insert]');
    if (overlay) overlay.hidden = false;

    if (st.add.catalogRoles.length) {
      ins.catalog = st.add.catalogRoles.slice();
      ins.loading = false;
      renderInsert();
      return;
    }

    // Мок-режим: каталог ролей без сети
    if (st.api === 'mock') {
      ins.catalog = MOCK.catalogRoles.map(function (one) { return Object.assign({}, one); });
      ins.loading = false;
      renderInsert();
      return;
    }

    ins.loading = true;
    renderInsert();

    request('GET', SYSTEM_ROLES_URL).then(function (res) {
      ins.loading = false;
      if (res.kind === 'ok') {
        ins.catalog = mapList(res.data, normalizeRole) || [];
      } else {
        // Каталога нет — предложим роли публикации (дубликаты отфильтруются)
        ins.catalog = st.roles.map(function (role) {
          return { id: role.id, title: role.title, color: role.color };
        });
      }
      renderInsert();
    });
  }

  function closeInsert() {
    if (!st.insert.open) return;
    st.insert.open = false;
    var overlay = q('[data-roles-insert]');
    if (overlay) overlay.hidden = true;
    if (st.insert.lastFocus && st.insert.lastFocus.focus) {
      try { st.insert.lastFocus.focus(); } catch (_) {}
    }
    st.insert.lastFocus = null;
  }

  function insertRole(roleId, title, color) {
    var ins = st.insert;
    if (ins.saving) return;
    ins.saving = true;
    renderInsert();

    var body = { roleId: num(roleId) };
    if (ins.index > 0) body.aboveId = num(st.roles[ins.index - 1].id);
    if (ins.index < st.roles.length) body.belowId = num(st.roles[ins.index].id);

    request('POST', base() + '/roles', JSON.stringify(body)).then(function (res) {
      ins.saving = false;

      if (res.kind === 'ok' || res.kind === 'missing') {
        var entry = {
          id: String(roleId),
          title: String(title || ('#' + roleId)),
          color: String(color || ''),
          version: 1,
          canManage: true,
          perms: null
        };
        var at = ins.index;
        if (at < 0) at = 0;
        if (at > st.roles.length) at = st.roles.length;
        st.roles.splice(at, 0, entry);
        closeInsert();
        render();
        notify(t('roles.saved'), 'success');
        return;
      }

      setInsertStatus(describe(res, t('roles.save_failed', { status: res.status })));
      renderInsert();
    });
  }

  /* ============================================================
     ОТКРЫТИЕ / ЗАКРЫТИЕ
     ============================================================ */

  function open(root, opts) {
    if (!root) return;
    opts = opts || {};

    st.root = root;
    st.pubId = parseInt(opts.publicationId, 10) || 0;
    st.mode = opts.mode === 'roles' ? 'roles' : 'publish';
    st.forceMock = opts.mock === true;
    st.onPublish = typeof opts.onPublish === 'function' ? opts.onPublish : null;
    st.onCancel = typeof opts.onCancel === 'function' ? opts.onCancel : null;
    st.screen = opts.screen ? (parseInt(opts.screen, 10) || 1) : 1;
    st.page = 'users';
    st.expanded = {};
    st.context = opts.context === 'edit' ? 'edit' : 'create';
    st.error = '';
    st.publishing = false;
    st.lastFocus = document.activeElement;

    closeCreateModals();
    resetAdd();
    closePerms();
    resetPerms();
    closeInsert();
    resetInsert();
    st.add.loadedOnce = false;
    st.add.loadedFallback = false;

    root.setAttribute('data-mode', st.mode);
    renderScreen();
    renderPage();
    load();
    bind(root);
  }

  function reset(root) {
    var target = root || st.root;
    if (!target) return;
    closeCreateModals();
    resetAdd();
    closePerms();
    resetPerms();
    closeInsert();
    resetInsert();
    cleanupDrag();
    st.root = null;
    st.pubId = 0;
    st.roles = [];
    st.users = [];
    st.screen = 1;
    st.page = 'users';
    st.context = 'create';
    st.error = '';
    st.publishing = false;
    st.onPublish = null;
    st.onCancel = null;
    st.api = 'unknown';
    st.forceMock = false;
    setApi('unknown');
  }

  /* ============================================================
     СОБЫТИЯ (делегирование на корне — контент рендерится позже)
     ============================================================ */

  function confirmAction(titleKey, textKey, params, onYes, confirmTextKey) {
    if (window.ConfirmModal && typeof window.ConfirmModal.confirm === 'function') {
      window.ConfirmModal.confirm({
        title: t(titleKey, params),
        message: t(textKey, params),
        confirmText: t(confirmTextKey || 'js.delete'),
        cancelText: t('common.cancel'),
        danger: true
      }).then(function (yes) { if (yes) onYes(); });
      return;
    }
    onYes();
  }

  function toggleUserExpand(userId) {
    var key = String(userId);
    st.expanded[key] = !st.expanded[key];
    renderUsers();
  }

  /** Удаление одной роли пользователя (иконка корзины в раскрытой строке). */
  function removeUserRole(userId, roleId) {
    var user = findUser(userId);
    if (!user) return;
    var role = null;
    (user.roles || []).some(function (one) {
      if (String(one.id) === String(roleId)) { role = one; return true; }
      return false;
    });
    if (!role) return;

    confirmAction('roles.remove_role_from_user_title', 'roles.remove_role_from_user_text',
      { name: user.username, role: role.title }, function () {
        request('DELETE', base() + '/users/' + user.id + '/roles/' + role.id).then(function (res) {
          if (okKind(res)) {
            localRemoveRoleFromUser(user.id, role.id);
            render();
            notify(t('roles.saved'), 'success');
            return;
          }
          notify(describe(res, t('roles.save_failed', { status: res.status })));
        });
      });
  }

  function removeRole(roleId) {
    var role = null;
    st.roles.some(function (one) { if (one.id === roleId) { role = one; return true; } return false; });
    if (!role) return;

    confirmAction('roles.remove_role_title', 'roles.remove_role_text',
      { name: role.title }, function () {
        request('DELETE', base() + '/roles/' + role.id).then(function (res) {
          if (res.kind === 'ok' || res.kind === 'missing') {
            st.roles = st.roles.filter(function (one) { return one.id !== role.id; });
            render();
            notify(t('roles.saved'), 'success');
            return;
          }
          notify(describe(res, t('roles.save_failed', { status: res.status })));
        });
      });
  }

  function describe(res, fallback) {
    if (window.Messages && res.data) {
      return window.Messages.describe(res.status, res.data, res.raw, fallback);
    }
    return fallback;
  }

  function bind(root) {
    if (root.dataset.rolesBound === '1') return;
    root.dataset.rolesBound = '1';

    root.addEventListener('click', function (e) {
      var target = e.target && e.target.closest ? e.target : null;
      if (!target) return;

      var tab = target.closest('[data-roles-tab]');
      if (tab) { st.page = tab.getAttribute('data-roles-tab'); renderPage(); return; }

      if (target.closest('[data-roles-back]')) {
        if (typeof st.onCancel === 'function') st.onCancel();
        return;
      }

      var back = target.closest('[data-roles-screen-back]');
      if (back) {
        st.screen = parseInt(back.getAttribute('data-roles-screen-back'), 10) || 1;
        renderScreen();
        return;
      }

      if (target.closest('[data-roles-next]')) {
        // Экран 1 → экран 2 (пользователи) — только при создании.
        if (st.context !== 'create') return;
        st.screen = 2;
        var usersBody = q('[data-roles-users-body]');
        if (usersBody) show(usersBody, true);
        renderUsers();
        updateGate();
        renderScreen();
        return;
      }

      if (target.closest('[data-roles-retry]')) { reload(); return; }
      if (target.closest('[data-roles-publish]')) { publish(); return; }

      var userExpand = target.closest('[data-user-expand]');
      if (userExpand) { toggleUserExpand(userExpand.getAttribute('data-user-expand')); return; }

      var userRoleRemove = target.closest('[data-user-role-remove]');
      if (userRoleRemove) {
        removeUserRole(userRoleRemove.getAttribute('data-user-id'),
          userRoleRemove.getAttribute('data-user-role-remove'));
        return;
      }

      var userAddRole = target.closest('[data-user-add-role]');
      if (userAddRole) { openEdit(userAddRole.getAttribute('data-user-add-role')); return; }

      var roleRemove = target.closest('[data-role-remove]');
      if (roleRemove) { removeRole(roleRemove.getAttribute('data-role-remove')); return; }

      /* --- экран 4: права роли --- */

      var permsBtn = target.closest('[data-role-perms]');
      if (permsBtn) { openPerms(permsBtn.getAttribute('data-role-perms')); return; }

      if (target.closest('[data-gap-add]')) { openInsert(parseInt(target.getAttribute('data-gap-add'), 10) || 0); return; }

      if (target.closest('[data-roles-insert-cancel]') ||
          target.closest('[data-roles-insert-dismiss]')) {
        closeInsert();
        return;
      }

      var insertOpt = target.closest('[data-insert-role-opt]');
      if (insertOpt) {
        insertRole(insertOpt.getAttribute('data-insert-role-opt'),
          insertOpt.getAttribute('data-roletitle'),
          insertOpt.getAttribute('data-rolecolor'));
        return;
      }

      /* --- модалки создания (экран 1 / экран 2, кнопка «+») --- */

      if (target.closest('[data-roles-add-open]')) {
        // «+»: экран 1 — модалка создания роли; экран 2 — модалка «роль + пользователь».
        if (st.screen === 2) { openCreateUserModal(); return; }
        openCreateRole();
        return;
      }

      // клик по полю-поиску роли открывает список: focusin не сработает, если
      // фокус уже стоит на поле (Safari не фокусирует кнопки-опции по клику)
      if (target.matches('[data-roles-create-user-role-search]') ||
          target.matches('[data-roles-create-role-search]')) {
        st.add.roleOpen = true;
        renderAddResults();
        return;
      }

      // …то же для поля людей
      if (target.matches('[data-roles-create-user-user-search]')) {
        st.add.userOpen = true;
        renderAddResults();
        return;
      }

      if (target.closest('[data-roles-perms-cancel]') ||
          target.closest('[data-roles-perms-dismiss]')) {
        // оверлей прав роли; при source 'create' closePerms сам закроет модалку создания
        closePerms();
        return;
      }

      if (target.closest('[data-roles-create-cancel]') ||
          target.closest('[data-roles-create-role-dismiss]') ||
          target.closest('[data-roles-create-user-cancel]') ||
          target.closest('[data-roles-create-user-dismiss]') ||
          target.closest('[data-roles-add-cancel]') ||
          target.closest('[data-roles-add-dismiss]')) {
        // модалки создания (роль / «роль + пользователь») — закрыть без сохранения
        closeCreateModals();
        return;
      }

      if (target.closest('[data-roles-create-add]')) { saveCreateRole(); return; }
      if (target.closest('[data-roles-perms-save]')) { savePerms(); return; }
      if (target.closest('[data-roles-create-user-add]')) { saveCreateUser(); return; }

      var userOpt = target.closest('[data-add-user-opt]');
      if (userOpt) {
        toggleAddUser(userOpt.getAttribute('data-add-user-opt'),
          userOpt.getAttribute('data-username'));
        return;
      }

      var roleOpt = target.closest('[data-add-role-opt]');
      if (roleOpt) {
        toggleAddRole(roleOpt.getAttribute('data-add-role-opt'),
          roleOpt.getAttribute('data-roletitle'),
          roleOpt.getAttribute('data-rolecolor'));
        return;
      }

      var userChip = target.closest('[data-add-user-remove]');
      if (userChip) { toggleAddUser(userChip.getAttribute('data-add-user-remove')); return; }

      var roleChip = target.closest('[data-add-role-remove]');
      if (roleChip) { toggleAddRole(roleChip.getAttribute('data-add-role-remove')); return; }
    });

    /* --- экран 4: переключение чекбоксов прав --- */
    root.addEventListener('change', function (e) {
      var cb = e.target && e.target.closest ? e.target.closest('[data-perm-key]') : null;
      if (!cb) return;
      st.perms.draft[cb.getAttribute('data-perm-key')] = cb.checked;
    });

    /* --- порядок ролей: drag & drop (HTML5), цель — точки вставки --- */

    root.addEventListener('dragstart', function (e) {
      var row = e.target && e.target.closest ? e.target.closest('.roles-row[draggable="true"]') : null;
      if (!row || !st.dndReady) return;
      var index = parseInt(row.getAttribute('data-role-index'), 10);
      var role = st.roles[index];
      if (!role || !role.canManage) return;

      st.drag = { index: index, id: role.id };
      row.classList.add('is-dragging');
      root.classList.add('is-dragging-role');
      if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', role.id); } catch (_) {}
      }
    });

    root.addEventListener('dragover', function (e) {
      if (!st.drag) return;
      var gap = e.target && e.target.closest ? e.target.closest('.roles-gap') : null;
      if (!gap || !gapValid(parseInt(gap.getAttribute('data-gap-index'), 10))) return;
      e.preventDefault();
      if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
      qa('.roles-gap.is-over').forEach(function (one) { one.classList.remove('is-over'); });
      gap.classList.add('is-over');
    });

    root.addEventListener('drop', function (e) {
      if (!st.drag) return;
      var gap = e.target && e.target.closest ? e.target.closest('.roles-gap') : null;
      if (!gap) return;
      e.preventDefault();
      var toIndex = parseInt(gap.getAttribute('data-gap-index'), 10) || 0;
      var fromIndex = st.drag.index;
      qa('.roles-gap.is-over').forEach(function (one) { one.classList.remove('is-over'); });
      cleanupDrag();
      if (fromIndex !== toIndex) performMove(fromIndex, toIndex);
    });

    root.addEventListener('dragend', function () {
      cleanupDrag();
    });

    /* --- поиск: ввод (дебаунс) --- */
    var searchTimer = 0;

    root.addEventListener('input', function (e) {
      var target = e.target && e.target.closest ? e.target : null;
      if (!target) return;

      if (target.matches('[data-roles-user-search]') || target.matches('[data-roles-create-user-user-search]')) {
        st.add.q = target.value.trim();
        st.add.userOpen = true;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function () { loadMoreIfEmpty(); }, 250);
        renderAddResults();
        return;
      }

      if (target.matches('[data-roles-role-search]') ||
          target.matches('[data-roles-create-user-role-search]') ||
          target.matches('[data-roles-create-role-search]')) {
        st.add.roleQ = target.value.trim();
        st.add.roleOpen = true;
        renderAddResults();
      }
    });

    /* --- поиск: раскрытие/скрытие выпадающих списков --- */
    root.addEventListener('focusin', function (e) {
      var target = e.target && e.target.closest ? e.target : null;
      if (!target) return;
      if (target.matches('[data-roles-user-search]') || target.matches('[data-roles-create-user-user-search]')) { st.add.userOpen = true; renderAddResults(); }
      if (target.matches('[data-roles-role-search]') || target.matches('[data-roles-create-user-role-search]') || target.matches('[data-roles-create-role-search]')) { st.add.roleOpen = true; renderAddResults(); }
    });

    root.addEventListener('focusout', function (e) {
      var target = e.target && e.target.closest ? e.target : null;
      if (!target) return;
      var picker = target.closest('.roles-picker');
      if (!picker) return;
      // даём фокусу переехать внутрь списка результатов (клик по опции)
      setTimeout(function () {
        if (picker.contains(document.activeElement)) return;
        var isUser = target.matches('[data-roles-user-search]') || target.matches('[data-roles-create-user-user-search]');
        if (isUser) { st.add.userOpen = false; renderAddResults(); }
        // у дропдауна роли закрывается и панель с поиском — её скрывает renderAdd
        else { st.add.roleOpen = false; renderAdd(); }
      }, 140);
    });
  }

  /** Пусто по запросу и есть следующая страница — догружаем каталог людей. */
  function loadMoreIfEmpty() {
    var add = st.add;
    if (add.catalogFallback) return;
    if (!add.userOpen) return;
    if (filteredUsers().length) return;
    if (!add.userHasNext || add.loadingUsers) return;
    if (add.userPage >= 5) return;                 // разумный предел догрузки
    loadUserCatalog(add.userPage + 1, true).then(loadMoreIfEmpty);
  }

  /* ============================================================
     ESCAPE / TAB: вложенные оверлеи перехватывают клавиши раньше
     диалога-хоста (слушатель вешается на этапе загрузки скрипта,
     а хосты вешают свои при открытии).
     ============================================================ */

  function overlayInFocus() {
    if (st.insert.open) return q('[data-roles-insert]');
    if (st.add.open) {
      // модалка «роль + пользователь» (screen 2) или модалка «создание роли» (screen 1)
      var addOverlay = q('[data-roles-create-user]');
      if (addOverlay && !addOverlay.hidden) return addOverlay;
      var createOverlay = q('[data-roles-create-role]');
      if (createOverlay && !createOverlay.hidden) return createOverlay;
      return null;
    }
    if (st.perms.open) return q('[data-roles-perms]');
    return null;
  }

  document.addEventListener('keydown', function (e) {
    var overlay = overlayInFocus();
    if (!overlay) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      if (st.insert.open) closeInsert();
      else if (st.add.open) closeCreateModals();
      else if (st.perms.open) closePerms();
      return;
    }

    if (e.key === 'Tab') {
      var dialog = overlay.querySelector('.roles-overlay__dialog');
      if (!dialog) return;
      var focusable = Array.prototype.filter.call(
        dialog.querySelectorAll('button,[href],input,select,textarea,[tabindex]'),
        function (el) { return !el.disabled && el.offsetParent !== null; }
      );
      if (!focusable.length) return;
      var first = focusable[0];
      var last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  }, true);

  /* ============================================================
     ЭКСПОРТ
     ============================================================ */

  return {
    open: open,
    reset: reset,
    reload: reload,
    /** Внутренности для следующих шагов модуля (экраны 3/4, DnD). */
    _state: st,
    _request: request,
    _base: base,
    _notify: notify,
    _describe: describe,
    _t: t,
    _esc: esc,
    _show: show,
    _q: q,
    _qa: qa,
    _render: render,
    _confirm: confirmAction,
    _PUB_PERMS: PUB_PERMS,
    _SYSTEM_USERS_URL: SYSTEM_USERS_URL,
    _SYSTEM_ROLES_URL: SYSTEM_ROLES_URL
  };
})();
