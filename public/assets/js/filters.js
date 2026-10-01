// ============================================
// FILTER PANEL — универсальный
// ============================================

/**
 * Позиционирует белый ползунок в segmented-контроле.
 * instant=true — без анимации (для первого рендера / показа скрытого ряда).
 */
function updateSegmentIndicator(tabsEl, instant = false) {
  const active = tabsEl.querySelector('.tab.is-active');
  if (!active) return;

  const tabsRect   = tabsEl.getBoundingClientRect();
  const activeRect = active.getBoundingClientRect();
  if (tabsRect.width === 0) return;   // родитель скрыт

  if (instant) tabsEl.classList.add('is-initializing');

  tabsEl.style.setProperty('--indicator-x', (activeRect.left - tabsRect.left) + 'px');
  tabsEl.style.setProperty('--indicator-w', activeRect.width + 'px');

  if (instant) {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => tabsEl.classList.remove('is-initializing'));
    });
  }
}

/* Первичная инициализация всех segmented-табов на странице */
document.querySelectorAll('.tabs--segmented').forEach(tabsEl => {
  updateSegmentIndicator(tabsEl, true);
  window.addEventListener('resize', () => updateSegmentIndicator(tabsEl, true));
});

/* 1. Тоггл панели фильтров */
document.querySelectorAll('[data-filter-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const panel = document.querySelector('[data-filter-panel]');
    if (!panel) return;
    panel.hidden = !panel.hidden;
    btn.classList.toggle('is-active', !panel.hidden);

    if (!panel.hidden) {
      // панель только что раскрылась — размеры у табов появились
      panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
    }
  });
});

/* 2. Клики по табам */
document.querySelectorAll('[data-filter-panel] .tab').forEach(tab => {
  tab.addEventListener('click', (e) => {
    const href = tab.getAttribute('href') || '';
    // реальная ссылка (не #anchor) — пусть работает как обычная навигация
    if (href && !href.startsWith('#')) return;

    e.preventDefault();

    const tabsEl = tab.closest('.tabs');
    const panel  = tab.closest('[data-filter-panel]');
    if (!tabsEl) return;

    // активный таб в этой группе
    tabsEl.querySelectorAll('.tab').forEach(t => t.classList.remove('is-active'));
    tab.classList.add('is-active');

    if (tabsEl.classList.contains('tabs--segmented')) {
      updateSegmentIndicator(tabsEl);
    }

    // если таб переключает ряды (Книги ↔ Статьи)
    const target = tab.getAttribute('data-row-target');
    if (target && panel) {
      panel.querySelectorAll('.filter-panel__row').forEach(row => {
        row.hidden = row.getAttribute('data-filter-row') !== target;
      });

      // синхронизировать активные табы во всех группах с этим target
      panel.querySelectorAll('.tab[data-row-target]').forEach(t => {
        t.classList.toggle('is-active', t.getAttribute('data-row-target') === target);
      });

      // пересчитать все segmented-индикаторы (в т.ч. в только что показанном ряду)
      panel.querySelectorAll('.tabs--segmented').forEach(t => updateSegmentIndicator(t, true));
    }
  });
});

/* 4. Сброс */
document.querySelectorAll('[data-filter-reset]').forEach(btn => {
  btn.addEventListener('click', () => {
    const panel = btn.closest('[data-filter-panel]');
    if (!panel) return;

    panel.querySelectorAll('input.filter-input').forEach(i => i.value = '');

    // в каждой группе — активен первый таб
    panel.querySelectorAll('.tabs').forEach(group => {
      const tabs = group.querySelectorAll('.tab');
      tabs.forEach((t, i) => t.classList.toggle('is-active', i === 0));
      if (group.classList.contains('tabs--segmented')) {
        updateSegmentIndicator(group, true);
      }
    });

    // показать первый ряд
    const firstRow = panel.querySelector('.filter-panel__row');
    if (firstRow) {
      panel.querySelectorAll('.filter-panel__row').forEach(r => r.hidden = true);
      firstRow.hidden = false;
    }
  });
});


/* ============================================
   LIBRARY FILTERS — динамические дропдауны (жанры)
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

    menu.innerHTML = '<li class="dropdown__loading">Загрузка…</li>';

    try {
      const genres = await fetchGenres();

      if (genres.length === 0) {
        menu.innerHTML = '<li class="dropdown__empty">Жанров нет</li>';
        return;
      }

      const params = new URLSearchParams(window.location.search);
      const currentGenre = params.get('genre');

      // Если жанр не выбран в URL — активен "Все жанры"
      const isAllActive = !currentGenre;

      const items = [
        {
          id: 'all',
          title: 'Все жанры',
          href: stripParam('genre'),
          active: isAllActive,
        },
        ...genres.map(g => ({
          id: String(g.id),
          title: g.title,
          href: '?genre=' + encodeURIComponent(g.id),
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

      // Обновляем метку на триггере
      const label = dropdown.querySelector('[data-dropdown-label]');
      if (label) {
        if (currentGenre) {
          const current = genres.find(g => String(g.id) === currentGenre);
          label.textContent = 'Жанр: ' + (current ? current.title : 'Все жанры');
        } else {
          label.textContent = 'Жанр: Все жанры';
        }
      }
    } catch (e) {
      console.error('[library-filters] genres load failed:', e);
      menu.innerHTML = '<li class="dropdown__error">Ошибка загрузки</li>';
    }
  }

  // --- Утилиты ---

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

  // --- Инициализация ---

  document.addEventListener('DOMContentLoaded', function () {
    document
      .querySelectorAll('[data-dropdown][data-dynamic="genres"]')
      .forEach(function (dd) { populateGenres(dd); });
  });
})();