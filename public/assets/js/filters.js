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
   DYNAMIC DROPDOWNS + ISBN/DOI + SEARCH
   ============================================ */

(function () {
  'use strict';

  // ---------- dynamic dropdowns ----------

  const SOURCES = {
    genres: {
      url:      '/api/additional/genres',
      param:    'genre',
      allLabel: 'All genres',
    },
    types: {
      url:      '/api/additional/types',
      param:    'kind',
      allLabel: 'All types',
    },
  };

  const cache = new Map(); // url -> array

  // true  — a wrong ISBN check digit blocks the search
  // false — only shows a warning, search still runs (handy with test data)
  const STRICT_ISBN_CHECKSUM = false;

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

  // ---------- navigation helper (keeps all other params) ----------

  function navigateWith(changes) {
    const url = new URL(window.location.href);
    Object.keys(changes).forEach(function (key) {
      const value = changes[key];
      if (value) url.searchParams.set(key, value);
      else url.searchParams.delete(key);
    });
    url.searchParams.delete('page');
    window.location.href = url.toString();
  }

  // ---------- ISBN ----------
  //
  // Формат приложения задан схемой БД и ошибкой бэкенда:
  //   storage/db/schema.sql:  books.isbn ~ '^\d{3}-\d{1}-\d{3}-\d{5}-\d{1}$'
  //   BookExceptionTranslator: "ISBN must match the format XXX-X-XXX-XXXXX-X"
  // Поиск делает ILIKE isbn || '%', поэтому поле обязано собирать номер
  // ровно в этом виде — иначе полный ISBN не находит ничего.
  const ISBN_GROUPS = [3, 1, 3, 5, 1];                       // 978-0-306-40615-2
  const ISBN_HINT = 'Format: 978-0-306-40615-2 (13 digits, 978/979)';
  const DOI_HINT = 'Format: 10.5555/123456';

  // Сколько набрано цифр, когда группа ISBN только что завершилась:
  // 978 | 9780 | 9780306 | 978030640615 — после них сразу ставим «-»
  const ISBN_GROUP_ENDS = [3, 4, 7, 12];

  // Только цифры: колонка хранит \d и дефисы, ISBN-10 (и «X») не сохраняются
  function isbnClean(raw) {
    let out = '';
    const src = String(raw);
    for (let i = 0; i < src.length && out.length < 13; i++) {
      const ch = src[i];
      if (ch >= '0' && ch <= '9') out += ch;
    }
    return out;
  }

  // Дефис встаёт, как только набрана следующая группа: 978-0-306-40615-2
  function isbnFormat(c) {
    const parts = [];
    let i = 0;
    for (let g = 0; g < ISBN_GROUPS.length && i < c.length; g++) {
      parts.push(c.slice(i, i + ISBN_GROUPS[g]));
      i += ISBN_GROUPS[g];
    }
    if (i < c.length) parts.push(c.slice(i));
    return parts.join('-');
  }

  // В БД попадают только 978/979 — остальное не группируем, а показываем ошибку
  function isbnShapeOk(c) {
    return c.length <= 3 || /^97[89]/.test(c);
  }

  function isbnChecksumOk(c) {
    if (c.length !== 13) return true;                        // номер ещё набирается
    let sum = 0;
    for (let i = 0; i < 12; i++) sum += Number(c[i]) * (i % 2 ? 3 : 1);
    return (10 - (sum % 10)) % 10 === Number(c[12]);
  }

  function isbnValidate(value) {
    const c = isbnClean(value);
    if (c === '') return { msg: '', blocking: false };
    if (!isbnShapeOk(c)) {
      return {
        msg: 'ISBN must be 13 digits starting with 978 or 979 — ' + ISBN_HINT,
        blocking: true,
      };
    }
    if (c.length < 13) return { msg: '', blocking: false };
    if (!isbnChecksumOk(c)) {
      // STRICT_ISBN_CHECKSUM = false — предупреждение, поиск всё равно уйдёт
      // (в сиде контрольные разряды случайные, искать по ним всё равно нужно)
      return {
        msg: 'ISBN check digit does not match (search still runs)',
        blocking: STRICT_ISBN_CHECKSUM,
      };
    }
    return { msg: '', blocking: false };
  }

  // ---------- DOI ----------

  // Разбирает вставку из ссылки или цитаты:
  // "https://doi.org/10.5555/1", "doi:10.5555/1", кавычки, пробелы, «.» в конце.
  // Точку в конце убираем только у готового DOI — иначе «10.» при наборе
  // превратится в «1» и точку придётся набирать заново.
  function doiNormalize(raw) {
    let v = String(raw).trim();
    v = v.replace(/^["'«»“”‘’]+|["'«»“”‘’]+$/g, '');
    v = v.replace(/^doi:\s*/i, '');
    v = v.replace(/^(?:https?:\/\/)?(?:dx\.)?doi\.org\//i, '');
    v = v.replace(/\s+/g, '');
    if (v.indexOf('/') !== -1) v = v.replace(/[.,;]+$/, '');
    return v;
  }

  // deleting=true — пользователь стирает символ: «10» не дополняем до «10.»,
  // иначе точку нельзя было бы удалить.
  function doiFormat(raw, deleting) {
    let v = doiNormalize(raw);
    if (/^10\d/.test(v)) v = '10.' + v.slice(2);             // 105555 -> 10.5555
    else if (v === '10' && !deleting) v = '10.';             // 10 -> 10.
    return v.slice(0, 200);
  }

  // Схема БД: articles.doi ~ '^10\.\d+\/\d+$', ошибка бэкенда:
  // "DOI must match the format 10.XXXX/YYYY" — префикс из цифр,
  // цифровой суффикс. Поле — фильтр поиска, поэтому частичный ввод
  // (10.5555, 10.5555/) тоже валиден: он находится как префикс.
  function doiValidate(value) {
    if (value === '') return { msg: '', blocking: false };

    // Префикс набирается: 1 -> 10 -> 10. -> 10.5555
    if (value === '1' || value === '10' || value === '10.') return { msg: '', blocking: false };
    if (/^10\.\d{1,7}$/.test(value)) return { msg: '', blocking: false };

    // Восемь и более цифр без «/» — суффикс забыли набрать
    if (/^10\.\d{8,}$/.test(value)) {
      return { msg: 'DOI is missing "/" after the prefix — ' + DOI_HINT, blocking: true };
    }

    // Суффикс набирается и готовое значение: 10.5555/123456
    if (/^10\.\d+\/\d*$/.test(value)) return { msg: '', blocking: false };

    if (/^10\.\d+\//.test(value)) {
      return { msg: 'DOI suffix must be digits — ' + DOI_HINT, blocking: true };
    }
    if (/^10\./.test(value)) {
      return { msg: 'DOI prefix must be digits after "10." — ' + DOI_HINT, blocking: true };
    }
    return { msg: 'DOI must start with "10." — ' + DOI_HINT, blocking: true };
  }

  // ---------- caret helpers ----------

  // Caret position in `formatted` after `sig` significant (non-hyphen) chars
  function caretFromSig(formatted, sig) {
    if (sig <= 0) return 0;
    let count = 0;
    for (let i = 0; i < formatted.length; i++) {
      if (formatted[i] !== '-') count++;
      if (count === sig) return i + 1;
    }
    return formatted.length;
  }

  // ---------- ISBN / DOI inputs ----------

  // Как быстро применять ввод, если страница фильтрует обычным переходом
  // (Saved, публикации профиля): перезагружаем не на каждую клавишу.
  const FILTER_NAV_DELAY = 500;

  function setupFormattedInput(inp) {
    const kind = inp.dataset.format;
    const isIsbn = kind === 'isbn';
    const validate = isIsbn ? isbnValidate : doiValidate;
    const hint = isIsbn ? ISBN_HINT : DOI_HINT;
    let navTimer = 0;

    /**
     * Применяет фильтр поля, пока пользователь печатает.
     *
     * Страница библиотеки перехватывает cancelable-событие `filter:input`
     * (library-filters.js) и фильтрует через API без перезагрузки. Если
     * обработчика нет — значение уходит в URL: иначе ввод в ISBN/DOI ни к
     * чему не приводит (Saved, публикации профиля).
     */
    function applyFilter() {
      const key = inp.dataset.filterKey || inp.name;
      if (!key) return;

      window.clearTimeout(navTimer);

      const ev = new CustomEvent('filter:input', {
        bubbles: true,
        cancelable: true,
        detail: { key: key, value: inp.value.trim() },
      });
      if (!inp.dispatchEvent(ev)) return;      // страница с API-фильтром

      // Неверный формат не отправляем (на библиотеке так же делает
      // library-filters.js: is-invalid не попадает ни в URL, ни в API)
      if (inp.classList.contains('is-invalid')) return;

      navTimer = window.setTimeout(function () {
        const changes = {};
        changes[key] = inp.value.trim();
        navigateWith(changes);
      }, FILTER_NAV_DELAY);
    }

    /**
     * deleting=true — пользователь стирает символ: разделитель в конце
     * автоматически не добавляем, иначе его нельзя было бы удалить.
     */
    function format(raw, caretPos, deleting) {
      const atEnd = caretPos == null || caretPos >= raw.length;

      if (isIsbn) {
        const c = isbnClean(raw);
        // Не 978/979 — расставлять дефисы некуда: оставляем как есть,
        // ошибку покажет isbnValidate (иначе получается чужой формат)
        const shapeOk = isbnShapeOk(c);
        let formatted = shapeOk ? isbnFormat(c) : c;

        // Группа только что завершилась — сразу ставим дефис:
        // 978 -> 978-, 9780 -> 978-0-
        if (!deleting && atEnd && shapeOk && ISBN_GROUP_ENDS.indexOf(c.length) !== -1) {
          formatted += '-';
        }

        const sig = caretPos == null ? null : isbnClean(raw.slice(0, caretPos)).length;
        return {
          formatted,
          caret: atEnd || sig == null ? formatted.length : caretFromSig(formatted, sig),
        };
      }

      const formatted = doiFormat(raw, deleting);
      let caret = formatted.length;
      if (caretPos != null && caretPos < raw.length) {
        caret = Math.max(0, Math.min(formatted.length, caretPos + formatted.length - raw.length));
      }
      return { formatted, caret };
    }

    function showState() {
      const r = validate(inp.value);
      const hasMsg = !!r.msg;
      // Красным — только то, что блокирует поиск; предупреждение
      // (контрольный разряд) показываем янтарным, поиск продолжает работать
      inp.classList.toggle('is-invalid', hasMsg && r.blocking);
      inp.classList.toggle('is-warning', hasMsg && !r.blocking);
      inp.setAttribute('aria-invalid', hasMsg && r.blocking ? 'true' : 'false');
      inp.title = r.msg || hint;   // пока ошибки нет — подсказка с верным форматом
      return r;
    }

    function onInput(e) {
      inp.setCustomValidity('');
      const raw = inp.value;
      const pos = inp.selectionStart;
      const deleting = !!(e && e.inputType && e.inputType.indexOf('delete') === 0);
      const res = format(raw, pos, deleting);
      if (res.formatted !== raw) {
        inp.value = res.formatted;
        try { inp.setSelectionRange(res.caret, res.caret); } catch (_) {}
      }
      showState();
      applyFilter();
    }

    inp.addEventListener('input', onInput);

    inp.addEventListener('keydown', function (e) {
      const pos = inp.selectionStart;
      const sel = inp.selectionEnd;

      // Backspace/Delete next to an auto-inserted hyphen: hop over it
      if (isIsbn && pos === sel) {
        if (e.key === 'Backspace' && pos > 0 && inp.value[pos - 1] === '-') {
          inp.setSelectionRange(pos - 1, pos - 1);
        } else if (e.key === 'Delete' && inp.value[pos] === '-') {
          inp.setSelectionRange(pos + 1, pos + 1);
        }
      }

      if (e.key !== 'Enter') return;
      e.preventDefault();
      window.clearTimeout(navTimer);   // переход по Enter вместо отложенного

      const r = showState();
      if (r.msg && r.blocking) {
        inp.setCustomValidity(r.msg);
        inp.reportValidity();
        return;
      }
      const changes = {};
      changes[inp.name] = inp.value.trim();
      navigateWith(changes);
    });

    // Normalize value that came from the URL on page load
    const initial = format(inp.value, null, true);
    inp.value = initial.formatted;
    showState();
  }

  // ---------- search box (?q=) ----------

  function setupSearch(inp) {
    function go() {
      // Лента: поиск идёт по названию книги на клиенте — см. feed-search.js
      if (window.LoreFeedSearch) {
        window.LoreFeedSearch.go(inp.value.trim());
        return;
      }
      navigateWith({ q: inp.value.trim() });
    }

    inp.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      go();
    });

    // Clear ("x") button of type="search"
    inp.addEventListener('search', function () {
      if (inp.value === '' && new URL(window.location.href).searchParams.has('q')) go();
    });

    // If the input sits inside a <form>, don't let it reload without our params
    if (inp.form) {
      inp.form.addEventListener('submit', function (e) {
        e.preventDefault();
        go();
      });
    }
  }

  // ---------- init ----------

  /**
   * Возвращает фокус в поле ISBN/DOI, из которого ушли по URL.
   * Фильтр применяется прямо во время ввода (см. applyFilter), поэтому
   * без этого после перехода печатать дальше приходилось бы, кликая по
   * полю заново. Фокусируем только открытую панель — иначе страница
   * прыгала бы к скрытому полю.
   */
  function restoreFilterFocus() {
    const panel = document.querySelector('[data-filter-panel]');
    if (!panel || panel.hidden) return;

    const params = new URLSearchParams(window.location.search);
    ['isbn', 'doi'].forEach(function (key) {
      if (!params.get(key)) return;
      const inp = panel.querySelector(
        'input.filter-input[data-format][name="' + key + '"]'
      );
      if (!inp || !inp.value) return;
      inp.focus();
      try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch (_) {}
    });
  }

  function init() {
    document.querySelectorAll('input[name="q"]').forEach(setupSearch);

    document
      .querySelectorAll('[data-filter-panel] input.filter-input[data-format]')
      .forEach(setupFormattedInput);

    document
      .querySelectorAll('[data-dropdown][data-dynamic]')
      .forEach(function (dd) {
        const row = dd.closest('.filter-panel__row');
        // Пропускаем dropdown'ы в скрытых рядах (books/ articles)
        if (row && row.hidden) return;
        populateDynamic(dd);
      });

    restoreFilterFocus();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();