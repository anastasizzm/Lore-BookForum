/**
 * publication-roles.js — плашка ролей и людей публикации при публикации
 * (src/partials/publication-roles-panel.php, стили publication-roles.css).
 *
 * Окно публикации книги (rights-modal, режим after-publish/publish) и окно
 * публикации статьи (article-rights-dialog) вставляют эту плашку и вызывают
 * LorePubRoles.load(root, publicationId), когда id публикации уже известен.
 *
 * Контракт (бэк отдаёт WithContext[] → { item + context }):
 *   GET /api/publications/{id}/roles → { items: [ { role: {id,title,color},
 *                                                  pubPermissions: {...},
 *                                                  canManage, version } ] }
 *   GET /api/publications/{id}/users → { items: [ { id, username, ... } ],
 *                                        meta: { hasNext } }
 *
 * Три режима (видно в DOM как data-pub-roles-api у корня):
 *   unknown — ещё не пробовали;
 *   live    — роут отвечает JSON'ом → показываем реальные данные;
 *   mock    — роута нет (404/405/HTML/сеть) → встроенный мок + console.warn.
 *
 * Это ТОЛЬКО просмотр: управление ролями/людьми — отдельная задача UI.
 */
(function () {
  'use strict';

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  /** Мок-данные: грузим, пока роуты /api/publications/{id}/roles|users нет. */
  var MOCK = {
    roles: [
      { id: 0, title: 'Owner',  color: '#2F9E44' },
      { id: 1, title: 'Editor', color: '#1C7ED6' },
      { id: 2, title: 'Author', color: '#F76707' }
    ],
    users: [
      { id: 1, label: 'anastasizzm' },
      { id: 2, label: 'reader_one' }
    ]
  };

  var warnedMock = false;

  function q(root, sel) { return root ? root.querySelector(sel) : null; }

  /** { items:[...] } | { data:{ items:[...] } } | [...] → массив или null. */
  function unwrapItems(payload) {
    var p = payload;
    if (p && typeof p === 'object' && p.data && typeof p.data === 'object' && !Array.isArray(p.data)) {
      p = p.data;
    }
    if (Array.isArray(p)) return p;
    if (p && Array.isArray(p.items)) return p.items;
    if (p && Array.isArray(p.data)) return p.data;
    return null;
  }

  /** JSON или нет (HTML/пусто) — как в admin.js dictApi. */
  function parseJson(raw) {
    if (!raw || !/[\[{]/.test(raw)) return null;
    try { return JSON.parse(raw); } catch (_) { return null; }
  }

  function classify(res, raw, networkError) {
    if (networkError) return 'mock';
    var looksHtml = /^\s*</.test(raw || '');
    if (res.ok) return looksHtml ? 'mock' : 'ok';
    if (looksHtml) return 'mock';
    if (res.status === 404 || res.status === 405) return 'mock';
    if (res.status === 401 || res.status === 403) return 'denied';
    return 'error';
  }

  /* ---------- нормализация ---------- */

  function normalizeRole(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var role = raw.role && typeof raw.role === 'object' ? raw.role : raw;
    var id = role.id != null ? role.id : raw.roleId;
    if (id == null) return null;
    var title = role.title || role.name || ('#' + id);
    return {
      id: String(id),
      title: String(title),
      color: String(role.color || ''),
      perms: raw.pubPermissions || raw.permissions || null
    };
  }

  function normalizeUser(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var u = raw.user && typeof raw.user === 'object' ? raw.user : raw;
    var id = u.id != null ? u.id : raw.id;
    if (id == null) return null;
    return { id: String(id), label: String(u.username || u.name || u.email || ('#' + id)) };
  }

  function mapList(payload, normalize) {
    var items = unwrapItems(payload);
    if (!items) return null;
    return items.map(normalize).filter(function (one) { return one !== null; });
  }

  /* ---------- рендер ---------- */

  function setState(root, state) {
    var map = {
      loading: '[data-pub-roles-loading]',
      error:   '[data-pub-roles-error]',
      empty:   '[data-pub-roles-empty]'
    };
    Object.keys(map).forEach(function (key) {
      var el = q(root, map[key]);
      if (el) el.hidden = state !== key;
    });
    var list = q(root, '[data-pub-roles-body]');
    if (list) list.hidden = (state !== 'list');
  }

  function render(root, roles, users) {
    var rolesBox = q(root, '[data-pub-roles-list]');
    var usersBox = q(root, '[data-pub-people-list]');

    if (rolesBox) {
      rolesBox.innerHTML = roles.map(function (role) {
        var dot = role.color
          ? '<span class="pub-role-badge__dot" style="background:' + esc(role.color) + '"></span>'
          : '';
        return '<li class="pub-role-badge">' + dot +
               '<span class="pub-role-badge__title">' + esc(role.title) + '</span></li>';
      }).join('');
    }

    if (usersBox) {
      usersBox.innerHTML = users.map(function (user) {
        return '<li class="pub-people__item">' + esc(user.label) + '</li>';
      }).join('');
    }

    var empty = !roles.length && !users.length;
    setState(root, empty ? 'empty' : 'list');
  }

  function renderMock(root) {
    render(root, MOCK.roles, MOCK.users);
    root.setAttribute('data-pub-roles-api', 'mock');
    var note = q(root, '[data-pub-roles-mock]');
    if (note) note.hidden = false;
  }

  function showError(root, key) {
    var box = q(root, '[data-pub-roles-error-text]');
    if (box) box.textContent = t(key);
    setState(root, 'error');
  }

  /* ---------- загрузка ---------- */

  function fetchJson(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.text().then(
        function (raw) { return { res: res, raw: raw }; },
        function () { return { res: res, raw: '' }; }
      );
    }, function (err) {
      return { res: { ok: false, status: 0 }, raw: '', networkError: err };
    });
  }

  function loadEndpoint(url, normalize) {
    return fetchJson(url).then(function (r) {
      var kind = classify(r.res, r.raw, r.networkError);
      if (kind === 'ok') return { mode: 'live', items: mapList(parseJson(r.raw), normalize) };
      if (kind === 'mock') return { mode: 'mock' };
      if (kind === 'denied') return { mode: 'denied' };
      return { mode: 'error' };
    });
  }

  /**
   * Загрузить и отрисовать плашку. root — контейнер [data-pub-roles],
   * id — id публикации (0/пусто → показываем пустое состояние, без запросов).
   */
  function load(root, id) {
    if (!root) return Promise.resolve();
    var pubId = parseInt(id, 10) || 0;

    root.setAttribute('data-pub-roles-api', 'unknown');
    var note = q(root, '[data-pub-roles-mock]');
    if (note) note.hidden = true;

    if (!pubId) { setState(root, 'empty'); return Promise.resolve(); }

    setState(root, 'loading');

    var base = '/api/publications/' + pubId;
    return Promise.all([
      loadEndpoint(base + '/roles', normalizeRole),
      loadEndpoint(base + '/users', normalizeUser)
    ]).then(function (results) {
      var rolesRes = results[0];
      var usersRes = results[1];

      // Оба недоступны (роутов ещё нет) → мок
      if (rolesRes.mode === 'mock' && usersRes.mode === 'mock') {
        if (!warnedMock) {
          console.warn('[publication-roles] API not available yet — showing mock roles');
          warnedMock = true;
        }
        renderMock(root);
        return;
      }

      var roles  = rolesRes.mode === 'live' ? (rolesRes.items || []) : [];
      var users  = usersRes.mode === 'live' ? (usersRes.items || []) : [];
      root.setAttribute('data-pub-roles-api', 'live');

      if (rolesRes.mode === 'error' || usersRes.mode === 'error') {
        // частично грузануло — показываем что есть, но при полной пустоте — ошибку
        if (!roles.length && !users.length) { showError(root, 'errors.common.request_failed'); return; }
      }
      if (rolesRes.mode === 'denied' && usersRes.mode === 'denied') {
        showError(root, 'js.no_rights');
        return;
      }

      render(root, roles, users);
    }).catch(function (err) {
      console.warn('[publication-roles] load failed', err);
      showError(root, 'errors.common.network_error');
    });
  }

  /** Повторная попытка (кнопка «Повторить»). */
  function retry(root) {
    var id = root ? parseInt(root.getAttribute('data-pub-roles-id'), 10) || 0 : 0;
    load(root, id);
  }

  // Делегирование: кнопка «Повторить» внутри плашки
  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest && e.target.closest('[data-pub-roles-retry]');
    if (!btn) return;
    var root = btn.closest('[data-pub-roles]');
    if (root) retry(root);
  });

  window.LorePubRoles = { load: load, retry: retry };
})();
