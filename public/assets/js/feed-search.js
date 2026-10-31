"use strict";

/* ============================================
   Поиск в ленте по НАЗВАНИЮ КНИГИ (серверный).

   Фильтрация перенесена на бэк: GET /api/posts?q=<query>&page=<n>
   с Accept: text/html + X-Requested-With: XMLHttpRequest возвращает
   HTML-фрагмент .feed-panel (только релевантные карточки + pager).
   Клиент больше НЕ фильтрует карточки и НЕ ограничен MAX_PAGES —
   пагинация серверная, hasNext берётся из .feed-panel__load-more a.

   Бэк-контракт (config/endpoints.php: api.post → PostsController::list):
     - принимает q и page;
     - фильтрует по publication.title (сохраняем семантику старого
       клиентского поиска: искали именно .card-feed__book-title,
       а не текст поста);
     - при Accept: text/html / X-Requested-With возвращает
       фрагмент .feed-panel .stack > .card-feed (+ pager);
     - при Accept: application/json возвращает { items, meta:{page,hasNext} }.
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

  var state = { q: '', original: '', page: 1, hasNext: false, busy: false };

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
    var el = document.querySelector('.feed-panel__load-more');
    if (el) el.hidden = !show;
  }

  function pageUrl(page) {
    var p = new URLSearchParams();
    p.set('q', state.original);
    if (page > 1) p.set('page', String(page));
    if (window.LoreI18n && LoreI18n.locale) p.set('lang', LoreI18n.locale);
    return withLang('/api/posts?' + p.toString());
  }

  function notify(text) {
    if (window.Messages) window.Messages.show(text, { type: 'error' });
    else console.warn(text);
  }

  function fetchPage(page) {
    return fetch(pageUrl(page), {
      credentials: 'same-origin',
      headers: {
        'Accept': 'text/html',
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
      .then(function (res) {
        if (res.ok) return res.text();
        var reading = window.Messages
          ? window.Messages.readError(res, t('errors.common.search_failed'))
          : Promise.resolve(t('errors.common.search_failed_http', { status: res.status }));
        return reading.then(function (msg) {
          var err = new Error(msg);
          err.fromServer = true;
          throw err;
        });
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        return {
          // Сервер уже отфильтровал — берём все карточки как есть.
          cards: Array.prototype.slice.call(
            doc.querySelectorAll('.feed-panel .stack > .card-feed')
          ),
          hasNext: !!doc.querySelector('.feed-panel__load-more a'),
        };
      });
  }

  function scan(page, replace) {
    if (state.busy) return;
    state.busy = true;
    setStatus(t('js.searching'));
    setLoadMore(false);
    var failed = false;

    var target = ensurePanel().querySelector('.stack');
    if (replace) target.replaceChildren();

    function step(n) {
      return fetchPage(n).then(function (data) {
        data.cards.forEach(function (card) {
          target.appendChild(card);
        });
        state.hasNext = data.hasNext;
        state.page = n;
        if (data.hasNext) return step(n + 1);
        return null;
      });
    }

    step(page)
      .catch(function (err) {
        failed = true;
        console.error('[feed-search] failed:', err);
        notify(err && err.fromServer ? err.message : t('errors.common.network_error'));
      })
      .finally(function () {
        state.busy = false;
        setLoadMore(state.hasNext);
        setStatus(
          failed || target.children.length
            ? ''
            : t('js.nothing_found', { query: state.original || state.q })
        );
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

    var url = new URL(location.href);
    url.searchParams.set('q', q);
    url.searchParams.delete('page');
    if (window.LoreI18n && LoreI18n.locale) url.searchParams.set('lang', LoreI18n.locale);
    history.pushState(null, '', url.pathname + url.search);

    scan(1, true);
  }

  document.addEventListener('click', function (e) {
    if (!state.q) return;
    var link = e.target.closest && e.target.closest('.feed-panel__load-more a');
    if (!link) return;
    e.preventDefault();
    if (state.busy || !state.hasNext) return;
    scan(state.page + 1, false);
  }, true);

  window.addEventListener('popstate', function () { location.reload(); });

  function init() {
    var q = new URL(location.href).searchParams.get('q');
    if (!q || !q.trim()) return;
    state.q = q.trim().toLowerCase();
    state.original = q.trim();
    scan(1, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  return { go: go };
})();