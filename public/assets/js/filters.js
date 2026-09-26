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

/* 3. Сброс */
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