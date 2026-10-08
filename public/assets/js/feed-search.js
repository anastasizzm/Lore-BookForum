"use strict";

/* ============================================
   Поиск в ленте по НАЗВАНИЮ КНИГИ.

   Серверный ?q= в ленте ищет по users.username (бэкенд:
   PostsScriptDirector::addSearchTempFilter — ILIKE по логину автора
   поста), поэтому запрос «Название книги» в лучшем случае ничего не
   находил. Логика здесь:
     1) страницы ленты забираются БЕЗ ?q (иначе сервер опять отфильтрует
        по автору) — постранично, не больше MAX_PAGES за раз;
     2) остаются только карточки, у которых .card-feed__book-title
        содержит запрос без учёта регистра (посты без книги не подходят —
        искать по названию книги — по названию книги);
     3) ?q= остаётся в URL (pushState), чтобы ссылку можно было
        копировать: при перезагрузке поиск выполнится здесь же;
     4) «Load more» при активном поиске догружает СЛЕДУЮЩУЮ страницу
        ленты и оставляет совпавшие карточки из неё.

   filters.js делегирует сюда из setupSearch() (см. window.LoreFeedSearch),
   скрипт подключается только на странице ленты.
   ============================================ */

window.LoreFeedSearch = (function () {
  // Потолок авто-сканирования за один поиск (как у выпадающих списков
  // в filters.js). Дальше — только по кнопке «Load more».
  var MAX_PAGES = 20;

  var state = { q: '', page: 1, hasNext: false, busy: false };

  // ---------- разметка ----------

  function stack() {
    var panel = document.querySelector('.feed-panel');
    return panel ? panel.querySelector('.stack') : null;
  }

  // Сервер мог отрендерить и «No posts yet», и карточки с чужим
  // результатом поиска по автору — приводим страницу к своему виду.
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

  // ---------- данные ----------

  function pageUrl(page) {
    var p = new URLSearchParams();
    if (page > 1) p.set('page', String(page));
    var qs = p.toString();
    return location.pathname + (qs ? '?' + qs : ''); // без q: сервер фильтрует по автору
  }

  function fetchPage(page) {
    return fetch(pageUrl(page), { credentials: 'same-origin' })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        return {
          cards: Array.prototype.slice.call(
            doc.querySelectorAll('.feed-panel .stack > .card-feed')
          ),
          hasNext: !!doc.querySelector('.feed-panel__load-more a'),
        };
      });
  }

  function matches(card) {
    var title = card.querySelector('.card-feed__book-title');
    return !!title && title.textContent.toLowerCase().indexOf(state.q) !== -1;
  }

  function scan(page, replace) {
    if (state.busy) return;
    state.busy = true;
    setStatus('Searching…');
    setLoadMore(false);

    var target = ensurePanel().querySelector('.stack');
    if (replace) target.replaceChildren();

    function step(n) {
      return fetchPage(n).then(function (data) {
        data.cards.filter(matches).forEach(function (card) {
          target.appendChild(card);
        });
        state.hasNext = data.hasNext;
        state.page = n;
        if (data.hasNext && n < MAX_PAGES) return step(n + 1);
        return null;
      });
    }

    step(page)
      .catch(function () {
        setStatus('Search failed. Try again.');
      })
      .finally(function () {
        state.busy = false;
        setLoadMore(state.hasNext);
        setStatus(
          target.children.length
            ? ''
            : 'Nothing found for “' + (state.original || state.q) +
              '”. Try a book title.'
        );
      });
  }

  // ---------- API (его дергает filters.js) ----------

  function go(query) {
    var q = String(query || '').trim();
    if (!q) {
      // Поле очистили — возвращаем обычную ленту (сервер без ?q)
      location.href = location.pathname;
      return;
    }

    state.q = q.toLowerCase();
    state.original = q;

    // Делимая ссылка: перезагрузка страницы повторит поиск
    var url = new URL(location.href);
    url.searchParams.set('q', q);
    url.searchParams.delete('page');
    history.pushState(null, '', url.pathname + url.search);

    scan(1, true);
  }

  // Продолжить поиск со следующей страницы ленты (кнопка Load more)
  document.addEventListener(
    'click',
    function (e) {
      if (!state.q) return;
      var link = e.target.closest && e.target.closest('.feed-panel__load-more a');
      if (!link) return;
      e.preventDefault();
      if (state.busy || !state.hasNext) return;
      scan(state.page + 1, false);
    },
    true
  );

  // Back/Forward после pushState — перечитываем страницу с сервера,
  // чтобы вернуться к «серверному» виду ленты без поиска.
  window.addEventListener('popstate', function () {
    location.reload();
  });

  // ---------- init: ?q= в адресе при загрузке страницы ----------

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
