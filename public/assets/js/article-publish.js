"use strict";

/**
 * article-publish.js — окно выбора прав при публикации статьи
 * (src/partials/article-rights-dialog.php).
 *
 * Экспорт: window.LoreArticlePublish.open(articleId, { onInvalid, onConflict })
 *   onInvalid(errors) — вызывается, когда бэк вернул 400/422 на publish
 *                        (значит, статья не прошла проверку как для публикации).
 *                        article-form.js в этом случае подсвечивает поля формы.
 *   onConflict(msg)    — вызывается, когда бэк вернул 409: публикация
 *                        запрещена (PublicationsService::publish → ConflictException
 *                        «cannot_publish» — обычно у статьи нет контента).
 *                        article-form.js возвращает на шаг с текстом статьи.
 *
 * Открытие кнопкой: на странице статьи (article-details, publish-bar у
 *   черновика) кнопка несёт data-article-publish-open + data-article-id —
 *   открытие идёт делегированием на document, без участия формы.
 *
 * Контракт с бэком:
 *   GET/POST/PUT/PATCH/DELETE /api/publications/{id}/roles|users — людей и
 *        роли сохраняет roles-module.js отдельными запросами.
 *   POST /api/articles/{id}/publish  → пустое тело; editor_ids[] бэк не читает.
 *        успех   200/201 { data: { id, redirect } }
 *        ошибки  400/422 { errors: { field: [msg] } }
 *        конфликт 409 { error: { message } } — публикация запрещена (нет
 *                 контента): окно закрывается, текст показывает плашка,
 *                 хост возвращает форму к шагу с текстом (onConflict).
 */
window.LoreArticlePublish = (function () {
  'use strict';

  var root = null;
  var state = {
    articleId: 0,
    onInvalid: null,
    onConflict: null,
    // Колбэк публикации для страницы черновиков: вместо
    // перехода на /articles/{id} вызывается он (карточка уходит из списка).
    onPublished: null,
    mode: 'publish',               // publish | roles
    screen: 1,                     // стартовый экран модуля: 1 — роли | 2 — пользователи
    context: 'create',             // create — Create → Continue → Publish; edit — «Изменить…»
    lastFocus: null,
    busy: false,
  };

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  function $(sel, scope) { return (scope || root || document).querySelector(sel); }

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

  /* ---------- плашка ролей (publication-roles.js) ---------- */
  function loadRolesPanel() {
    if (!window.LorePubRoles) return;
    var panel = $('[data-pub-roles]');
    if (!panel) return;
    panel.setAttribute('data-pub-roles-id', String(state.articleId || 0));
    window.LorePubRoles.load(panel, state.articleId);
  }

  /* ---------- публикация ---------- */
  function submit() {
    if (state.busy) return Promise.resolve(false);
    state.busy = true;

    var btn = $('[data-rights-submit]');
    var prev = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = t('js.article_saving'); }

    // editor_ids[] бэк не читает: людей/роли сохраняет roles-module.js.
    return csrfFetch('/api/articles/' + state.articleId + '/publish', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (res) {
      return res.text().then(function (raw) {
        var data = null;
        try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
        var isHtml = /^\s*</.test(raw);

        if (res.ok && !isHtml) {
          var payload = (data && data.data) || data || {};
          // Страница черновиков: карточка уходит из списка;
          // иначе — переход на опубликованную статью.
          if (typeof state.onPublished === 'function') {
            var cb = state.onPublished;
            state.onPublished = null;
            cb();
            return true;
          }
          window.location.href = payload.redirect
            || (state.articleId ? '/articles/' + state.articleId : '/articles');
          return true;
        }

        // 409 — бэк не даёт опубликовать публикацию (ConflictException
        // «cannot_publish»): чаще всего у статьи нет контента. Окно
        // закрываем, сообщение показываем, форму возвращаем к тексту.
        if (res.status === 409) {
          var conflictErrors = data && (data.errors
            || (data.error && typeof data.error === 'object' ? data.error.details : null));
          var conflictMsg = (window.Messages ? window.Messages.fromPayload(data) : '')
            || t('errors.article.publish_no_content');
          close();
          if (conflictErrors && typeof state.onInvalid === 'function') state.onInvalid(conflictErrors);
          notify(conflictMsg);
          if (typeof state.onConflict === 'function') state.onConflict(conflictMsg);
          return false;
        }

        if ((res.status === 400 || res.status === 422) && data) {
          var errors = data.errors
            || (data.error && typeof data.error === 'object' ? data.error.details : null);
          close();
          if (typeof state.onInvalid === 'function') state.onInvalid(errors);
          return false;
        }

        var fallback = t('errors.common.save_failed', { status: res.status });
        notify(window.Messages
          ? window.Messages.describe(res.status, data, raw, fallback)
          : fallback);
        return false;
      });
    }).catch(function (err) {
      console.error('[article-publish] publish failed', err);
      notify(t('errors.common.network_error'));
      return false;
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
  }

  function open(articleId, opts) {
    ensureRoot();
    if (!root) {
      console.error('[article-publish] [data-article-rights] not found');
      return;
    }

    state.articleId = Number(articleId) || 0;
    state.onInvalid = (opts && opts.onInvalid) || null;
    state.onConflict = (opts && opts.onConflict) || null;
    state.onPublished = (opts && typeof opts.onPublished === 'function') ? opts.onPublished : null;
    // 'roles' — только модуль ролей (Publish просто сохраняет роли и закрывает);
    // иначе — привычный publish (Publish публикует статью).
    state.mode = (opts && opts.mode === 'roles') ? 'roles' : 'publish';
    state.screen = (opts && opts.screen) ? (parseInt(opts.screen, 10) || 1) : 1;
    state.context = (opts && opts.context) === 'edit' ? 'edit' : 'create';
    state.busy = false;
    state.lastFocus = document.activeElement;

    bindOnce();

    root.hidden = false;
    // Модуль ролей берёт управление окном на себя
    root.setAttribute('data-rights-view', 'roles');
    document.body.classList.add('is-modal-open');
    document.addEventListener('keydown', onKey, true);

    loadRolesPanel();
    openRoles();
  }

  /** Открывает модуль ролей внутри окна (экран 1). */
  function openRoles() {
    var mod = root.querySelector('[data-roles-module]');
    if (!mod || !window.LoreRoles) return;

    var rolesMode = state.mode === 'roles' ? 'roles' : 'publish';

    window.LoreRoles.open(mod, {
      publicationId: state.articleId,
      mode: rolesMode,
      screen: state.screen,
      context: state.context,
      // Publish экрана 2: в режиме 'roles' просто сохраняем роли (модуль
      // сохранил их сам) и закрываем; иначе — публикуем статью (submit хоста).
      onPublish: function (done) {
        if (rolesMode === 'roles') {
          if (window.Messages) window.Messages.show(t('roles.saved'), { type: 'success' });
          close();
          done();
          return;
        }
        submit().then(function (ok) { if (ok) close(); done(); });
      },
      onCancel: function () { close(); }
    });
  }

  function close() {
    if (!root) return;
    // Сбрасываем модуль ролей и убираем режим
    var mod = root.querySelector('[data-roles-module]');
    if (mod && window.LoreRoles) window.LoreRoles.reset(mod);
    root.removeAttribute('data-rights-view');
    root.hidden = true;
    document.body.classList.remove('is-modal-open');
    document.removeEventListener('keydown', onKey, true);
    if (state.lastFocus && state.lastFocus.focus) {
      try { state.lastFocus.focus(); } catch (_) {}
    }
    state.lastFocus = null;
  }

  // Кнопка «Опубликовать статью» на странице статьи (article-details,
  // publish-bar у черновика): <button data-article-publish-open
  // data-article-id="N">. Делегирование на document — кнопка может
  // рендериться (и гаситься permissions.js) после загрузки скрипта.
  document.addEventListener('click', function (e) {
    var opener = e.target && e.target.closest && e.target.closest('[data-article-publish-open]');
    if (!opener) return;
    e.preventDefault();
    var id = parseInt(opener.getAttribute('data-article-id'), 10) || 0;
    if (id) open(id, { screen: 2 });
  });

  return { open: open, close: close };
})();