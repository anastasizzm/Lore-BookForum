// ============================================
// FILTER PANEL — segmented tabs, dynamic dropdowns (genres, article-types)
// ============================================

/**
 * Позиционирует белый индикатор внутри segmented-контрола.
 * instant=true — без анимации (первый рендер / показ скрытого ряда).
 */
function updateSegmentIndicator(tabsEl, instant = false) {
  const active = tabsEl.querySelector('.tab.is-active');
  if (!active) return;

  const tabsRect   = tabsEl.getBoundingClientRect();
  const activeRect = active.getBoundingClientRect();
  if (tabsRect.width === 0) return;

  if (instant) tabsEl.classList.add('is-initializing');

  tabsEl.style.setProperty('--indicator-x', (activeRect.left - tabsRect.left) + 'px');
  tabsEl.style.setProperty('--indicator-w', activeRect.width + 'px');

  if (instant) {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => tabsEl.classList.remove('is-initializing'));
    });
  }
}

/* Initial setup for all segmented tabs on the page */
document.querySelectorAll('.tabs--segmented').forEach(tabsEl => {
  updateSegmentIndicator(tabsEl, true);
  window.addEventListener('resize', () => updateSegmentIndicator(tabsEl, true));
});


/* ============================================
   TABS — clicks inside filter panel
   ============================================ */

document.querySelectorAll('[data-filter-panel] .tab').forEach(tab => {
  tab.addEventListener('click', (e) => {
    const href = tab.getAttribute('href') || '';
    // Реальная ссылка (не #anchor) — пусть работает как обычная навигация
    if (href && !href.startsWith('#')) return;

    e.preventDefault();

    const tabsEl = tab.closest('.tabs');
    const panel  = tab.closest('[data-filter-panel]');
    if (!tabsEl) return;

    tabsEl.querySelectorAll('.tab').forEach(t => t.classList.remove('is-active'));
    tab.classList.add('is-active');

    if (tabsEl.classList.contains('tabs--segmented')) {
      updateSegmentIndicator(tabsEl);
    }

    // Переключение рядов (Books ↔ Articles)
    const target = tab.getAttribute('data-row-target');
    if (target && panel) {
      panel.querySelectorAll('.filter-panel__row').forEach(row => {
        row.hidden = row.getAttribute('data-filter-row') !== target;
      });

      panel.querySelectorAll('.tab[data-row-target]').forEach(t => {
        t.classList.toggle('is-active', t.getAttribute('data-row-target') === target);
      });

      panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
    }
  });
});


/* ============================================
   DYNAMIC DROPDOWNS — genres, article-types
   ============================================ */

(function () {
  'use strict';

  // Какие динамические источники бывают, где их API и как звать query-параметр
  const SOURCES = {
    genres: {
      url:      '/api/additional/genres',
      param:    'genre',         // ?genre=<id> в URL страницы
      allLabel: 'All genres',
    },
    'article-types': {
      url:      '/api/additional/article-types',
      param:    'kind',          // ?kind=<value> в URL страницы
      allLabel: 'All types',
    },
  };

  const cache = new Map(); // url -> array

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function stripParam(key) {
    const url = new URL(window.location.href);
    url.searchParams.delete(key);
    url.searchParams.delete('page');
    const qs = url.searchParams.toString();
    return qs ? '?' + qs : url.pathname;
  }

  function appendParam(key, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(key, value);
    url.searchParams.delete('page');
    const qs = url.searchParams.toString();
    return qs ? '?' + qs : url.pathname;
  }

  async function fetchAll(url) {
    if (cache.has(url)) return cache.get(url);

    const all = [];
    for (let p = 1; p <= 20; p++) {
      const res = await fetch(url + '?page=' + p, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);

      const data = await res.json();
      const list = Array.isArray(data)
        ? data
        : (Array.isArray(data.items) ? data.items : []);

      all.push(...list);

      // Если сервер отдал плоский массив — пагинации нет, выходим
      // Если отдал объект {items, meta} — идём дальше только при hasNext
      if (Array.isArray(data) || !(data.meta && data.meta.hasNext)) break;
    }

    cache.set(url, all);
    return all;
  }

  async function populateDynamic(dropdown) {
    const kind = dropdown.dataset.dynamic;
    const src  = SOURCES[kind];
    if (!src) return;

    const menu = dropdown.querySelector('.dropdown__menu');
    if (!menu) return;

    menu.innerHTML = '<li class="dropdown__loading">Loading...</li>';

    try {
      const list = await fetchAll(src.url);

      const params  = new URLSearchParams(window.location.search);
      const current = params.get(src.param);
      const isAll   = !current;

      const items = [
        {
          id:     'all',
          title:  src.allLabel,
          href:   stripParam(src.param),
          active: isAll,
        },
        ...list.map(entry => ({
          id:     String(entry.id ?? entry.value ?? ''),
          title:  String(entry.title ?? entry.label ?? entry.id ?? ''),
          href:   appendParam(src.param, String(entry.id ?? entry.value ?? '')),
          active: String(entry.id ?? entry.value ?? '') === current,
        })),
      ];

      menu.innerHTML = items.map(item =>
        '<li>' +
          '<a href="' + item.href + '" ' +
             'class="dropdown__item ' + (item.active ? 'is-active' : '') + '" ' +
             'data-filter-value="' + escapeHtml(item.id) + '">' +
            escapeHtml(item.title) +
          '</a>' +
        '</li>'
      ).join('');

      // library-filters.js слушает это событие и обновляет подпись кнопки
      dropdown.dispatchEvent(new CustomEvent('dropdown:populated', { bubbles: true }));
    } catch (e) {
      console.error('[filters] dynamic load failed:', kind, e);
      menu.innerHTML = '<li class="dropdown__error">Failed to load</li>';
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document
      .querySelectorAll('[data-dropdown][data-dynamic]')
      .forEach(function (dd) { populateDynamic(dd); });
  });
})();