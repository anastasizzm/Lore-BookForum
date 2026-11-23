"use strict";

/* ============================================
   Поиск в ленте по НАЗВАНИЮ КНИГИ (серверный).

   Бэк всегда отдаёт JSON: GET /api/posts?q=<query>&page=<n>
   → { items: [...], meta: { page, hasNext } }.
   Карточки рисуем на клиенте той же разметкой, что и card-feed.php,
   поэтому делегированные обработчики card-feed.js/comments.js
   (лайк, комментарий, ответ, «Show more», меню) продолжают работать.

   Пагинация серверная: грузим только запрошенную страницу, дальше —
   ссылка «Load more». AbortController + generation не дают устаревшему
   ответу перезаписать более новый поиск.
   ============================================ */

window.LoreFeedSearch = (function () {
  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };
  var withLang = function (url) {
    return window.LoreI18n && typeof LoreI18n.withLang === 'function'
      ? LoreI18n.withLang(url)
      : url;
  };

  var state = {
    q: '',
    original: '',
    page: 1,
    hasNext: false,
    loading: false,
    gen: 0,
    controller: null,
    cu: null
  };

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function textHtml(text) {
    return escapeHtml(text).replace(/\r\n|\r|\n/g, '<br>');
  }

  function initialsOf(user) {
    var n = String((user && user.name) || '').trim();
    var s = String((user && user.surname) || '').trim();
    var v = (n.charAt(0) + s.charAt(0)).toUpperCase();
    if (!v) v = String((user && user.username) || '').trim().charAt(0).toUpperCase() || '?';
    return v;
  }

  function formatDate(value) {
    if (window.LoreI18n && typeof LoreI18n.formatDate === 'function') {
      var localized = LoreI18n.formatDate(value);
      if (localized) return localized;
    }
    var raw = (value && typeof value === 'object') ? (value.date || '') : String(value || '');
    var m = /(\d{4})-(\d{2})-(\d{2})/.exec(raw);
    return m ? m[3] + '.' + m[2] + '.' + m[1] : '';
  }

  function iconIdOf(value) {
    if (value == null) return '';
    if (typeof value === 'object') return String(value.uuid || value.value || '');
    return String(value);
  }

  function ensurePanel() {
    var panel = document.querySelector('.feed-panel');
    if (panel) return panel;
    var empty = document.querySelector('.empty-state');
    if (empty) empty.hidden = true;
    panel = document.createElement('div');
    panel.className = 'feed-panel';
    panel.innerHTML = '<div class="stack"></div>';
    (document.querySelector('.content__inner') || document.body).appendChild(panel);
    return panel;
  }

  function setStatus(text) {
    var st = ensurePanel().querySelector('[data-feed-search-status]');
    if (!st) {
      st = document.createElement('p');
      st.className = 'empty-state__text';
      st.setAttribute('data-feed-search-status', '');
      st.hidden = true;
      ensurePanel().appendChild(st);
    }
    st.textContent = text || '';
    st.hidden = !text;
  }

  function setLoadMore(show) {
    var panel = ensurePanel();
    var wrap = panel.querySelector('.feed-panel__load-more');
    if (!show) {
      if (wrap) wrap.hidden = true;
      return;
    }
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'feed-panel__load-more';
      panel.appendChild(wrap);
    }
    wrap.hidden = false;
    wrap.replaceChildren();

    var url = new URL(location.href);
    url.searchParams.set('q', state.original);
    url.searchParams.set('page', String(state.page + 1));

    var link = document.createElement('a');
    link.className = 'btn btn--secondary';
    link.href = url.pathname + url.search;
    link.textContent = t('common.load_more');
    wrap.appendChild(link);
  }

  function pageUrl(page) {
    var p = new URLSearchParams();
    p.set('q', state.original);
    p.set('include', 'creator+publication');
    if (page > 1) p.set('page', String(page));
    if (window.LoreI18n && LoreI18n.locale) p.set('lang', LoreI18n.locale);
    return withLang('/api/posts?' + p.toString());
  }

  function notify(text) {
    if (window.Messages) window.Messages.show(text, { type: 'error' });
    else console.warn(text);
  }

  function fetchPage(page, signal) {
    return fetch(pageUrl(page), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      signal: signal
    }).then(function (res) {
      if (!res.ok) {
        var reading = window.Messages
          ? window.Messages.readError(res, t('errors.common.search_failed'))
          : Promise.resolve(t('errors.common.search_failed_http', { status: res.status }));
        return reading.then(function (msg) {
          var err = new Error(msg);
          err.fromServer = true;
          throw err;
        });
      }
      return res.json().catch(function () {
        throw new Error(t('errors.common.server_error'));
      });
    }).then(function (data) {
      var items = Array.isArray(data)
        ? data
        : (data && Array.isArray(data.items) ? data.items
          : (data && Array.isArray(data.data) ? data.data : []));
      return {
        items: items,
        hasNext: !!(data && data.meta && data.meta.hasNext)
      };
    });
  }

  function currentUser() {
    if (state.cu) return state.cu;
    // Приоритет — отдельный holder страницы ленты: он есть даже при пустой
    // ленте. Fallback на карточку оставляем для страниц без holder'а.
    var holder = document.querySelector('[data-feed-cu]')
      || document.querySelector('.card-feed[data-cu-id]');
    state.cu = {
      id: holder ? (Number(holder.getAttribute('data-cu-id')) || 0) : 0,
      name: holder ? (holder.getAttribute('data-cu-name') || '') : '',
      initials: holder ? (holder.getAttribute('data-cu-initials') || '?') : '?',
      avatar: holder ? (holder.getAttribute('data-cu-avatar') || '') : ''
    };
    return state.cu;
  }

  var LIKE_SVG =
    '<svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
      '<path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>';

  var DOTS_SVG =
    '<svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
      '<circle cx="8" cy="3" r="1.5"/>' +
      '<circle cx="8" cy="8" r="1.5"/>' +
      '<circle cx="8" cy="13" r="1.5"/>' +
    '</svg>';

  var CHECK_SVG =
    '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
      '<path d="M5 12.5L9.5 17L19 7.5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>';

  function renderCard(post, cu) {
    var creator  = post.creator || {};
    var pub      = post.publication || {};
    var postId   = Number(post.id || 0);
    var pubId    = Number(post.publicationId || pub.id || 0);
    var userId   = Number(creator.id || 0);
    var userName = String(creator.username || creator.name || '');
    var initials = initialsOf(creator);
    var liked    = !!post.isLiked;
    var likes    = Number(post.likesCount || 0);
    var comments = Number(post.commentsCount || 0);
    var date     = formatDate(post.createdAt);
    var text     = String(post.content || '');
    var token    = window.LoreCsrf ? LoreCsrf.token() : '';

    var iconId = iconIdOf(pub.iconId);
    var coverHtml = iconId
      ? '<img src="' + escapeHtml('/api/files/' + encodeURIComponent(iconId) + '/preview') + '" alt="" class="card-feed__book-thumb">'
      : '<span class="card-feed__book-thumb cover--empty" aria-hidden="true"></span>';

    var bookHeader = '';
    if (pubId && pub.title) {
      bookHeader =
        '<div class="card-feed__book-header">' +
          coverHtml +
          '<h3 class="card-feed__book-title">' +
            '<a class="card-feed__book-link" href="/books/' + pubId + '"' +
               ' data-pub-link data-pub-id="' + pubId + '">' + escapeHtml(pub.title) + '</a>' +
          '</h3>' +
        '</div>';
    }

    var authorHtml = userId
      ? '<a href="/users/' + userId + '" class="user-link">' + escapeHtml(userName) + '</a>'
      : escapeHtml(userName);

    return '' +
      '<article class="card-base card-feed"' +
        ' data-post-id="' + postId + '"' +
        ' data-publication-id="' + pubId + '"' +
        ' data-author-id="' + userId + '"' +
        ' data-author-username="' + escapeHtml(userName) + '"' +
        ' data-cu-id="' + (Number(cu.id) || 0) + '"' +
        ' data-cu-name="' + escapeHtml(cu.name) + '"' +
        ' data-cu-initials="' + escapeHtml(cu.initials) + '"' +
        ' data-cu-avatar="' + escapeHtml(cu.avatar) + '">' +
        bookHeader +
        '<div class="card-feed__body-section' + (bookHeader ? ' card-feed__body-section--with-book' : '') + '">' +
          '<div class="card-feed__post">' +
            '<div class="comment-card__inner">' +
              '<div class="avatar avatar--sm" data-post-avatar><span>' + escapeHtml(initials) + '</span></div>' +
              '<div class="comment-card__content">' +
                '<div class="comment-card__main">' +
                  '<div class="comment-card__head">' +
                    '<div class="comment-card__author">' + authorHtml + '</div>' +
                    '<div class="comment-card__text">' + textHtml(text) + '</div>' +
                  '</div>' +
                  '<div class="comment-card__actions" data-c-menu-wrap hidden>' +
                    '<button type="button" class="btn-icon-small comment-card__menu-btn" data-c-menu-toggle' +
                            ' aria-haspopup="true" aria-expanded="false" aria-label="' + escapeHtml(t('comments.options')) + '">' +
                      DOTS_SVG +
                    '</button>' +
                    '<div class="comment-card__menu" data-c-menu hidden>' +
                      '<button type="button" class="comment-card__menu-item comment-card__menu-item--danger"' +
                              ' data-c-delete hidden>' + escapeHtml(t('js.delete')) + '</button>' +
                    '</div>' +
                  '</div>' +
                '</div>' +

                '<div class="comment-card__footer">' +
                  '<div class="comment-card__meta">' +
                    '<button type="button" class="btn-icon-small btn-like comment-card__like' + (liked ? ' is-liked' : '') + '"' +
                            ' data-like-btn data-liked="' + (liked ? '1' : '0') + '"' +
                            ' aria-pressed="' + (liked ? 'true' : 'false') + '" aria-label="' + escapeHtml(t('comments.like')) + '">' +
                      LIKE_SVG +
                      '<span data-like-count>' + likes + '</span>' +
                    '</button>' +
                    '<button type="button" class="comment-card__reply"' +
                            ' data-comment-toggle aria-expanded="false" aria-label="' + escapeHtml(t('comments.reply')) + '">' +
                            escapeHtml(t('comments.reply')) + '</button>' +
                  '</div>' +
                  '<span class="comment-card__date">' + escapeHtml(date) + '</span>' +
                '</div>' +

                '<form class="comment-form" action="/api/posts/' + postId + '" method="POST"' +
                      ' data-feed-comment-form data-post-id="' + postId + '" novalidate hidden>' +
                  '<input type="hidden" name="_token" value="' + escapeHtml(token) + '">' +
                  '<input type="hidden" name="publicationId" value="' + pubId + '">' +
                  '<input type="text" class="comment-form__input" name="content" placeholder="' + escapeHtml(t('comments.add_comment')) + '" maxlength="2000" autocomplete="off">' +
                  '<button type="submit" class="comment-form__submit" disabled' +
                          ' aria-label="' + escapeHtml(t('comments.post')) + '">' + CHECK_SVG + '</button>' +
                  '<p class="comment-form__error" data-comment-error role="alert" hidden></p>' +
                  '<p class="comment-form__status" data-comment-status role="status" hidden></p>' +
                '</form>' +

                '<button type="button" class="comment-card__more" data-fc-expand' +
                        (comments > 0 ? '' : ' hidden') +
                        ' aria-expanded="false">' + escapeHtml(t('js.show_more')) + '</button>' +

                '<span class="visually-hidden" data-comment-count>' + comments + '</span>' +

                '<div class="feed-comments" data-feed-comments hidden>' +
                  '<div class="feed-comments__list" data-fc-list></div>' +
                  '<p class="feed-comments__status" data-fc-status role="status" hidden></p>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>' +
      '</article>';
  }

  function paintAvatar(el, initials, raw) {
    if (!el) return;
    el.replaceChildren();
    if (window.LoreAvatar) window.LoreAvatar.paint(el, null);

    var parsed = (window.LoreAvatar && window.LoreAvatar.parse(raw)) || { type: 'none' };
    if (parsed.type === 'emoji') {
      var em = document.createElement('span');
      em.className = 'avatar__emoji';
      em.setAttribute('aria-hidden', 'true');
      em.textContent = parsed.emoji;
      el.appendChild(em);
      if (window.LoreAvatar) window.LoreAvatar.paint(el, parsed);
      return;
    }

    var span = document.createElement('span');
    span.textContent = initials || '?';
    el.appendChild(span);
  }

  function decorate(node, post) {
    if (!node || !post) return;
    var creator = post.creator || {};
    paintAvatar(
      node.querySelector('[data-post-avatar]'),
      initialsOf(creator),
      String(creator.avatar || '')
    );
    if (window.LoreComments && typeof window.LoreComments.syncPostMenu === 'function') {
      window.LoreComments.syncPostMenu(node);
    }
  }

  function load(page, replace) {
    var target = ensurePanel().querySelector('.stack');
    if (!target) return;

    var gen = ++state.gen;
    if (state.controller && typeof state.controller.abort === 'function') {
      state.controller.abort();
    }
    state.controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
    var signal = state.controller ? state.controller.signal : undefined;

    state.loading = true;
    setStatus(t('js.searching'));
    setLoadMore(false);
    // Читаем текущего пользователя ДО replaceChildren(): иначе на первом
    // поиске исчезают SSR-карточки, из которых брались data-cu-*.
    var cu = currentUser();
    if (replace) target.replaceChildren();

    fetchPage(page, signal).then(function (data) {
      if (gen !== state.gen) return;

      state.page = page;
      state.hasNext = data.hasNext;

      var start = target.children.length;
      target.insertAdjacentHTML('beforeend', data.items.map(function (post) {
        return renderCard(post, cu);
      }).join(''));

      var nodes = Array.prototype.slice.call(target.children, start);
      nodes.forEach(function (node, i) { decorate(node, data.items[i]); });

      setStatus(
        data.items.length || target.children.length
          ? ''
          : t('js.nothing_found', { query: state.original || state.q })
      );
      setLoadMore(data.hasNext);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      if (gen !== state.gen) return;
      console.error('[feed-search] failed:', err);
      notify(err && err.fromServer ? err.message : t('errors.common.network_error'));
      if (!target.children.length) setStatus(t('errors.common.search_failed'));
    }).then(function () {
      if (gen !== state.gen) return;
      state.loading = false;
      state.controller = null;
    });
  }

  function go(query) {
    var q = String(query || '').trim();

    if (!q) {
      location.href = withLang(location.pathname);
      return;
    }

    state.q = q.toLowerCase();
    state.original = q;
    state.page = 1;
    state.hasNext = false;

    var url = new URL(location.href);
    url.searchParams.set('q', q);
    url.searchParams.delete('page');
    if (window.LoreI18n && LoreI18n.locale) url.searchParams.set('lang', LoreI18n.locale);
    history.pushState(null, '', url.pathname + url.search);

    load(1, true);
  }

  document.addEventListener('click', function (e) {
    if (!state.original) return;
    var link = e.target.closest && e.target.closest('.feed-panel__load-more a');
    if (!link) return;
    e.preventDefault();
    if (state.loading || !state.hasNext) return;
    load(state.page + 1, false);
  }, true);

  window.addEventListener('popstate', function () { location.reload(); });

  function init() {
    var q = new URL(location.href).searchParams.get('q');
    if (!q || !q.trim()) return;
    state.q = q.trim().toLowerCase();
    state.original = q.trim();
    load(1, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return { go: go };
})();
