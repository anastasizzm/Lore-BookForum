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