// ============================================
// FILTER PANEL — segmented tabs, series input, genres
// ============================================

/**
 * Positions the white indicator inside a segmented control.
 * instant=true — no animation (for first render / showing hidden row).
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
   1. TABS — all tabs in the filter panel are real links
      (Books/Articles, All/Reading/Finished); the server
      renders the active state, so no click handling needed.
      Only legacy "#anchor" tabs are handled here.
   ============================================ */

document.querySelectorAll('[data-filter-panel] .tab').forEach(tab => {
  tab.addEventListener('click', (e) => {
    const href = tab.getAttribute('href') || '';
    if (href && !href.startsWith('#')) return;

    e.preventDefault();

    const tabsEl = tab.closest('.tabs');
    if (!tabsEl) return;

    tabsEl.querySelectorAll('.tab').forEach(t => t.classList.remove('is-active'));
    tab.classList.add('is-active');

    if (tabsEl.classList.contains('tabs--segmented')) {
      updateSegmentIndicator(tabsEl);
    }
  });
});

/* ============================================
   2. SERIES NUMBER — Enter applies ?series=...
      (Reset is now a plain link rendered by the server)
   ============================================ */

document
  .querySelectorAll('[data-filter-panel] input.filter-input[name="series"]')
  .forEach(inp => {
    inp.addEventListener('keydown', e => {
      if (e.key !== 'Enter') return;
      e.preventDefault();

      const url = new URL(window.location.href);
      const v = inp.value.trim();
      if (v) url.searchParams.set('series', v);
      else   url.searchParams.delete('series');
      url.searchParams.delete('page');
      window.location.href = url.toString();
    });
  });


/* ============================================
   LIBRARY FILTERS — dynamic genre dropdown
   ============================================ */

(function () {
  'use strict';

  let genresCache = null;

  async function fetchGenres() {
    if (genresCache) return genresCache;

    const res = await fetch('/api/additional/genres', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);

    const data = await res.json();
    const list = Array.isArray(data)
      ? data
      : (Array.isArray(data.items) ? data.items : []);

    genresCache = list;
    return list;
  }

  async function populateGenres(dropdown) {
    const menu = dropdown.querySelector('.dropdown__menu');
    if (!menu) return;

    menu.innerHTML = '<li class="dropdown__loading">Loading...</li>';

    try {
      const genres = await fetchGenres();

      if (genres.length === 0) {
        menu.innerHTML = '<li class="dropdown__empty">No genres</li>';
        return;
      }

      const params = new URLSearchParams(window.location.search);
      const currentGenre = params.get('genre');
      const isAllActive = !currentGenre;

      const items = [
        {
          id: 'all',
          title: 'All genres',
          href: stripParam('genre'),
          active: isAllActive,
        },
        ...genres.map(g => ({
          id: String(g.id),
          title: g.title,
          href: appendParam('genre', String(g.id)),
          active: String(g.id) === currentGenre,
        })),
      ];

      menu.innerHTML = items.map(item =>
        '<li>' +
          '<a href="' + item.href + '" class="dropdown__item ' + (item.active ? 'is-active' : '') + '">' +
            escapeHtml(item.title) +
          '</a>' +
        '</li>'
      ).join('');

      const label = dropdown.querySelector('[data-dropdown-label]');
      if (label) {
        if (currentGenre) {
          const current = genres.find(g => String(g.id) === currentGenre);
          label.textContent = 'Genre: ' + (current ? current.title : 'All genres');
        } else {
          label.textContent = 'Genre: All genres';
        }
      }
    } catch (e) {
      console.error('[library-filters] genres load failed:', e);
      menu.innerHTML = '<li class="dropdown__error">Failed to load</li>';
    }
  }

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
    return url.pathname + (qs ? '?' + qs : '');
  }

  function appendParam(key, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(key, value);
    url.searchParams.delete('page');
    const qs = url.searchParams.toString();
    return url.pathname + (qs ? '?' + qs : '');
  }

  document.addEventListener('DOMContentLoaded', function () {
    document
      .querySelectorAll('[data-dropdown][data-dynamic="genres"]')
      .forEach(function (dd) { populateGenres(dd); });
  });
})();