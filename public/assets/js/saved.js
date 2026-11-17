"use strict";

/* ============================================
   Saved: сохранение закладок + AJAX сортировка/поиск/пагинация.

   Страница — серверный рендер /books/saved. Эндпоинт — тот же URL,
   что и адресная строка (бэк сам знает, что это «сохранённые»). Поэтому
   никакого /api/books тут нет — fetch идёт на /books/saved?... и мы
   вытаскиваем из HTML обновлённый <section class="books-panel">.

   Lang сохраняется: все ссылки/URL строятся из location.href,
   где ?lang= уже есть.
   ============================================ */
(function () {
  'use strict';

  var page = document.querySelector('[data-saved-page]');
  if (!page) return;

  var grid = page.querySelector('[data-saved-grid]');
  var counter = page.querySelector('[data-saved-count]');
  var empty = page.querySelector('[data-saved-empty]');
  var search = document.querySelector('input[name="q"]');

  // ---------- Снятие закладки (было раньше) ----------
  document.addEventListener('save:changed', function (e) {
    if (!e.detail || e.detail.saved) return;
    var card = e.target.closest('.card-book');
    if (!card || !grid || !grid.contains(card)) return;
    card.remove();

    if (counter) {
      counter.textContent = Math.max(0, (parseInt(counter.textContent, 10) || 0) - 1);
    }
    if (!grid.querySelector('.card-book')) {
      grid.style.display = 'none';
      if (empty) empty.hidden = false;
    }
  });

  // ---------- AJAX: заменяем содержимое страницы ----------
  var controller = null;
  var busy = false;

  function currentUrl() {
    // Полный URL со всеми параметрами, включая lang.
    return location.pathname + location.search;
  }

  function buildUrl(changes) {
    var url = new URL(location.href);
    Object.keys(changes).forEach(function (key) {
      var v = changes[key];
      if (v === null || v === undefined || v === '') url.searchParams.delete(key);
      else url.searchParams.set(key, String(v));
    });
    // Сбрасываем page при смене фильтра/сортировки/поиска.
    if (!('page' in changes)) url.searchParams.delete('page');
    return url.toString();
  }

  function replacePage(html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');

    var newPage = doc.querySelector('[data-saved-page]');
    if (!newPage) return false;  // пустой стейт — целиком подменяем content

    var newGrid = newPage.querySelector('[data-saved-grid]');
    var newEmpty = newPage.querySelector('[data-saved-empty]');
    var newCounter = newPage.querySelector('[data-saved-count]');
    var newPager = newPage.querySelector('.books-panel__pager');
    var newMoreLine = newPage.querySelector('.books-panel__meta');

    // Грид
    if (newGrid) {
      page.replaceChild(newGrid, grid);
      grid = newGrid;
    } else if (grid) {
      grid.replaceChildren();
      grid.style.display = 'none';
    }

    // Счётчик
    if (newCounter && counter) {
      counter.textContent = newCounter.textContent;
    }

    // Сортировка: подпись триггера и активный пункт приходят с сервера,
    // но сама шапка при AJAX не перерисовывается — синхронизируем вручную.
    syncSortControls(newPage);

    // Пагинация
    var pager = page.querySelector('.books-panel__pager');
    if (newPager) {
      if (pager) pager.replaceWith(newPager);
      else page.appendChild(newPager);
    } else if (pager) {
      pager.remove();
    }

    // «Пусто» после снятия закладок
    if (newEmpty && empty) {
      empty.hidden = newEmpty.hidden;
    }

    // Обновляем URL без перезагрузки
    history.replaceState(null, '', currentUrl());
    return true;
  }

  function syncSortControls(newPage) {
    var currentDd = page.querySelector('.books-panel__head [data-dropdown][data-filter-key="sort"]');
    var newDd = newPage.querySelector('.books-panel__head [data-dropdown][data-filter-key="sort"]');
    if (!currentDd || !newDd) return;

    var currentLabel = currentDd.querySelector('[data-dropdown-label]');
    var newLabel = newDd.querySelector('[data-dropdown-label]');
    if (currentLabel && newLabel) currentLabel.textContent = newLabel.textContent;

    // Набор и порядок сортировок на странице фиксированы, поэтому
    // сопоставляем пункты по индексу и переносим состояние is-active.
    var currentItems = currentDd.querySelectorAll('[data-filter-value]');
    var newItems = newDd.querySelectorAll('[data-filter-value]');
    currentItems.forEach(function (item, index) {
      var fresh = newItems[index];
      if (fresh) item.classList.toggle('is-active', fresh.classList.contains('is-active'));
    });
  }

  async function refresh(url) {
    if (busy) return;
    busy = true;
    if (controller) controller.abort();
    controller = new AbortController();

    page.classList.add('is-loading');
    try {
      var res = await fetch(url, {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        signal: controller.signal,
      });

      if (!res.ok) {
        // При ошибке — обычный переход, но с сохранением lang.
        location.href = url;
        return;
      }

      var html = await res.text();
      replacePage(html);
      history.replaceState(null, '', url);
    } catch (e) {
      if (e.name !== 'AbortError') {
        // Ошибка сети — просто переходим как обычно.
        location.href = url;
      }
    } finally {
      page.classList.remove('is-loading');
      busy = false;
    }
  }

  // ---------- Сортировка ----------
  document.addEventListener('click', function (e) {
    var link = e.target.closest('.dropdown[data-filter-key="sort"] a[data-filter-value]');
    if (!link) return;
    e.preventDefault();

    var value = link.dataset.filterValue;
    if (!value) return;

    var url = buildUrl({ sort: value });
    refresh(url);
  });

  // ---------- Reset фильтров ----------
  // На Saved нет list-AJAX из filters.js, поэтому обрабатываем Reset здесь.
  // Строим URL из текущего адреса: так сбрасываются и фильтры, применённые
  // после AJAX-поиска/сортировки. q, f и lang сохраняются.
  var RESET_KEYS = ['genre', 'status', 'p_status', 'isbn', 'doi', 'series', 'kind', 'sort', 'ctype'];

  document.addEventListener('click', function (e) {
    var reset = e.target.closest('[data-filter-panel] [data-filter-reset]');
    if (!reset) return;

    var url = new URL(location.href);
    RESET_KEYS.forEach(function (key) { url.searchParams.delete(key); });
    url.searchParams.delete('page');

    e.preventDefault();
    refresh(url.toString());
  });

  // ---------- Пагинация ----------
  document.addEventListener('click', function (e) {
    var pagerLink = e.target.closest('.books-panel__pager a');
    if (!pagerLink) return;
    var url = new URL(pagerLink.href, location.origin);

    // Сохраняем текущий lang, если он потерян в ссылке
    if (window.LoreI18n && LoreI18n.locale && !url.searchParams.has('lang')) {
      url.searchParams.set('lang', LoreI18n.locale);
    }

    e.preventDefault();
    refresh(url.toString());
  });

  // ---------- Поиск (только Enter) ----------
  if (search) {
    search.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      var q = search.value.trim();
      var url = buildUrl({ q: q || null });
      refresh(url);
    });
  }
})();