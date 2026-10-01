// ============================================
// FILTER PANEL — segmented tabs, reset, genres
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
   1. TABS — clicks inside filter panel
   ============================================ */

document.querySelectorAll('[data-filter-panel] .tab').forEach(tab => {
  tab.addEventListener('click', (e) => {
    const href = tab.getAttribute('href') || '';
    // Real link (not #anchor) — let it work as normal navigation
    if (href && !href.startsWith('#')) return;

    e.preventDefault();

    const tabsEl = tab.closest('.tabs');
    const panel  = tab.closest('[data-filter-panel]');
    if (!tabsEl) return;

    // Active tab in this group
    tabsEl.querySelectorAll('.tab').forEach(t => t.classList.remove('is-active'));
    tab.classList.add('is-active');

    if (tabsEl.classList.contains('tabs--segmented')) {
      updateSegmentIndicator(tabsEl);
    }

    // If tab switches rows (Books ↔ Articles)
    const target = tab.getAttribute('data-row-target');
    if (target && panel) {
      panel.querySelectorAll('.filter-panel__row').forEach(row => {
        row.hidden = row.getAttribute('data-filter-row') !== target;
      });

      // Sync active tabs in all groups with this target
      panel.querySelectorAll('.tab[data-row-target]').forEach(t => {
        t.classList.toggle('is-active', t.getAttribute('data-row-target') === target);
      });

      // Recalculate all segmented indicators
      panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
    }
  });
});

/* ============================================
   2. RESET
   ============================================ */

document.querySelectorAll('[data-filter-reset]').forEach(btn => {
  btn.addEventListener('click', () => {
    const panel = btn.closest('[data-filter-panel]');
    if (!panel) return;

    panel.querySelectorAll('input.filter-input').forEach(i => i.value = '');

    // In each group — first tab is active
    panel.querySelectorAll('.tabs').forEach(group => {
      const tabs = group.querySelectorAll('.tab');
      tabs.forEach((t, i) => t.classList.toggle('is-active', i === 0));
      if (group.classList.contains('tabs--segmented')) {
        updateSegmentIndicator(group, true);
      }
    });

    // Show first row
    const firstRow = panel.querySelector('.filter-panel__row');
    if (firstRow) {
      panel.querySelectorAll('.filter-panel__row').forEach(r => r.hidden = true);
      firstRow.hidden = false;
    }
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
    return qs ? '?' + qs : url.pathname;
  }

  function appendParam(key, value) {
  const url = new URL(window.location.href);
  url.searchParams.set(key, value);
  url.searchParams.delete('page');
  const qs = url.searchParams.toString();
  return qs ? '?' + qs : url.pathname;
  }

  document.addEventListener('DOMContentLoaded', function () {
    document
      .querySelectorAll('[data-dropdown][data-dynamic="genres"]')
      .forEach(function (dd) { populateGenres(dd); });
  });
})();