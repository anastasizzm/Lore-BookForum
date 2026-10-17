"use strict";

/**
 * article-publish.js — окно выбора прав при публикации статьи
 * (src/partials/article-rights-dialog.php).
 *
 * Экспорт: window.LoreArticlePublish.open(articleId, { onInvalid })
 *   onInvalid(errors) — вызывается, когда бэк вернул 400/422 на publish
 *                        (значит, статья не прошла проверку как для публикации).
 *                        article-form.js в этом случае подсвечивает поля формы.
 *
 * Контракт с бэком:
 *   GET  /api/articles/{id}/rights   → { data: { editor_ids: [..] } }   (404 — ок)
 *   GET  /api/users?page=&q=         → { items: [{id, username, name, surname, email?}],
 *                                        meta: { hasNext } }
 *   POST /api/articles/{id}/publish  → multipart, editor_ids[];
 *        успех   200/201 { data: { id, redirect } }
 *        ошибки  400/422 { errors: { field: [msg] } }
 */
window.LoreArticlePublish = (function () {
  'use strict';

  var root = null;
  var state = {
    articleId: 0,
    onInvalid: null,
    selected: Object.create(null),   // id (string) -> true
    users: [],
    page: 1,
    hasNext: false,
    loading: false,
    search: '',
    searchTimer: 0,
    lastFocus: null,
    busy: false,
  };

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function $(sel, scope) { return (scope || root || document).querySelector(sel); }
  function $$(sel, scope) { return Array.prototype.slice.call((scope || root || document).querySelectorAll(sel)); }

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  }

  function csrfFetch(url, opts) {
    if (window.LoreCsrf && typeof window.LoreCsrf.fetch === 'function') {
      return window.LoreCsrf.fetch(url, opts);
    }
    opts = opts || {};
    var headers = new Headers(opts.headers || {});
    var tok = window.LoreCsrf ? window.LoreCsrf.token() : '';
    if (tok && !headers.has('X-CSRF-Token')) headers.set('X-CSRF-Token', tok);
    opts.headers = headers;
    if (!opts.credentials) opts.credentials = 'same-origin';
    return fetch(url, opts);
  }

  function ensureRoot() {
    if (root && root.isConnected) return root;
    root = document.querySelector('[data-article-rights]');
    return root;
  }

  function setStatus(text) {
    var el = $('[data-rights-status]');
    if (!el) return;
    if (text) {
      el.hidden = false;
      el.textContent = text;
    } else {
      el.hidden = true;
      el.textContent = '';
    }
  }

  function userLabel(u) {
    return String(u.username || u.name || ('#' + (u.id || '')));
  }

  function userEmail(u) {
    return String(u.email || '');
  }

  /* ---------- рендер списка ---------- */
  function renderList() {
    var list = $('[data-rights-list]');
    if (!list) return;

    list.innerHTML = '';

    if (!state.users.length) {
      var empty = document.createElement('p');
      empty.className = 'article-rights__empty';
      empty.setAttribute('data-rights-status', '');
      empty.textContent = t('articleform.publish_no_users');
      list.appendChild(empty);
      return;
    }

    var frag = document.createDocumentFragment();
    state.users.forEach(function (u) {
      var id = String(u.id || '');
      if (!id) return;
      var label = document.createElement('label');
      label.className = 'article-rights__item';

      var cb = document.createElement('input');
      cb.type = 'checkbox';
      cb.setAttribute('data-rights-user', '');
      cb.value = id;
      cb.checked = !!state.selected[id];

      var name = document.createElement('span');
      name.className = 'article-rights__user-name';
      name.textContent = userLabel(u);

      label.appendChild(cb);
      label.appendChild(name);

      var email = userEmail(u);
      if (email) {
        var em = document.createElement('span');
        em.className = 'article-rights__user-email';
        em.textContent = email;
        label.appendChild(em);
      }
      frag.appendChild(label);
    });

    if (state.hasNext) {
      var more = document.createElement('button');
      more.type = 'button';
      more.className = 'btn btn--secondary article-rights__more';
      more.textContent = t('common.load_more');
      more.addEventListener('click', function () { loadUsers(state.page + 1, false); });
      frag.appendChild(more);
    }

    list.appendChild(frag);
  }

  /* ---------- загрузка ---------- */
  function loadRights() {
    return fetch('/api/articles/' + state.articleId + '/rights', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    }).then(function (res) {
      if (res.status === 404) return null;   // необязательный эндпоинт
      if (!res.ok) return null;
      return res.json().catch(function () { return null; });
    }).then(function (data) {
      var ids = (data && data.data && Array.isArray(data.data.editor_ids))
        ? data.data.editor_ids : [];
      ids.forEach(function (id) {
        if (id != null && id !== '') state.selected[String(id)] = true;
      });
      renderList();
    }).catch(function () { /* молча — 404/ошибка не критичны */ });
  }

  function loadUsers(page, reset) {
    if (state.loading) return Promise.resolve();
    state.loading = true;
    setStatus(t('articleform.publish_loading'));

    var p = new URLSearchParams();
    p.set('page', String(page || 1));
    if (state.search) p.set('q', state.search);

    return fetch('/api/users?' + p.toString(), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
    }).then(function (res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.json();
    }).then(function (data) {
      var items = Array.isArray(data) ? data : (data.items || []);
      var meta  = data.meta || {};
      if (reset) state.users = [];
      state.users = state.users.concat(items);
      state.page = page;
      state.hasNext = !!meta.hasNext;
      renderList();
      if (state.users.length) setStatus('');
    }).catch(function (err) {
      console.error('[article-publish] users load failed', err);
      setStatus(t('errors.common.users_load_failed'));
    }).finally(function () {
      state.loading = false;
    });
  }

  /* ---------- публикация ---------- */
  function submit() {
    if (state.busy) return;
    state.busy = true;

    var btn = $('[data-rights-submit]');
    var prev = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = t('js.article_saving'); }

    var body = new FormData();
    Object.keys(state.selected).forEach(function (id) {
      if (state.selected[id]) body.append('editor_ids[]', id);
    });

    csrfFetch('/api/articles/' + state.articleId + '/publish', {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (res) {
      return res.text().then(function (raw) {
        var data = null;
        try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
        var isHtml = /^\s*</.test(raw);

        if (res.ok && !isHtml) {
          var payload = (data && data.data) || data || {};
          window.location.href = payload.redirect
            || (state.articleId ? '/articles/' + state.articleId : '/articles');
          return;
        }

        if ((res.status === 400 || res.status === 422) && data) {
          var errors = data.errors
            || (data.error && typeof data.error === 'object' ? data.error.details : null);
          close();
          if (typeof state.onInvalid === 'function') state.onInvalid(errors);
          return;
        }

        var fallback = t('errors.common.save_failed', { status: res.status });
        notify(window.Messages
          ? window.Messages.describe(res.status, data, raw, fallback)
          : fallback);
      });
    }).catch(function (err) {
      console.error('[article-publish] publish failed', err);
      notify(t('errors.common.network_error'));
    }).finally(function () {
      state.busy = false;
      if (btn) { btn.disabled = false; btn.textContent = prev; }
    });
  }

  /* ---------- открытие/закрытие ---------- */
  function onKey(e) {
    if (root.hidden) return;
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); }
  }

  function onListChange(e) {
    var cb = e.target.closest && e.target.closest('[data-rights-user]');
    if (!cb) return;
    var id = String(cb.value || '');
    if (!id) return;
    if (cb.checked) state.selected[id] = true;
    else delete state.selected[id];
  }

  function onSearchInput(e) {
    var inp = e.target.closest && e.target.closest('[data-rights-search]');
    if (!inp) return;
    clearTimeout(state.searchTimer);
    state.searchTimer = setTimeout(function () {
      state.search = inp.value.trim();
      loadUsers(1, true);
    }, 250);
  }

  function bindOnce() {
    if (root.dataset.rightsBound === '1') return;
    root.dataset.rightsBound = '1';

    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-rights-close]') ||
          e.target.closest('[data-rights-cancel]') ||
          e.target.closest('[data-rights-backdrop]')) {
        close();
        return;
      }
      if (e.target.closest('[data-rights-submit]')) submit();
    });

    root.addEventListener('change', onListChange);
    root.addEventListener('input', onSearchInput);
  }

  function open(articleId, opts) {
    ensureRoot();
    if (!root) {
      console.error('[article-publish] [data-article-rights] not found');
      return;
    }

    state.articleId = Number(articleId) || 0;
    state.onInvalid = (opts && opts.onInvalid) || null;
    state.selected = Object.create(null);
    state.users = [];
    state.page = 1;
    state.hasNext = false;
    state.search = '';
    state.busy = false;
    state.lastFocus = document.activeElement;

    bindOnce();

    var search = $('[data-rights-search]');
    if (search) search.value = '';

    root.hidden = false;
    document.body.classList.add('is-modal-open');
    document.addEventListener('keydown', onKey, true);

    setStatus(t('articleform.publish_loading'));
    loadRights();
    loadUsers(1, true);

    if (search) { try { search.focus(); } catch (_) {} }
  }

  function close() {
    if (!root) return;
    root.hidden = true;
    document.body.classList.remove('is-modal-open');
    document.removeEventListener('keydown', onKey, true);
    clearTimeout(state.searchTimer);
    if (state.lastFocus && state.lastFocus.focus) {
      try { state.lastFocus.focus(); } catch (_) {}
    }
    state.lastFocus = null;
  }

  return { open: open, close: close };
})();