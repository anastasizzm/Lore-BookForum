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

  // true  — a wrong ISBN check digit blocks the search
  // false — only shows a warning, search still runs (handy with test data)
  const STRICT_ISBN_CHECKSUM = false;

  /* ---------- navigation helper (keeps all other params) ---------- */

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

  /* ---------- ISBN ---------- */

  const is13 = (c) => /^97[89]/.test(c);

  // Keeps digits; 'X' only as the 10th char of an ISBN-10
  function isbnClean(raw) {
    let out = '';
    const src = String(raw).toUpperCase();
    for (let i = 0; i < src.length; i++) {
      const ch = src[i];
      if (out.endsWith('X')) break;
      if (ch >= '0' && ch <= '9') out += ch;
      else if (ch === 'X' && out.length === 9 && !is13(out)) out += ch;
    }
    return out.slice(0, 13);
  }

  function isbnGroups(c) {
    if (is13(c)) return [3, 1, 2, 6, 1];          // 978-3-16-148410-0
    if (c === '9' || c === '97') return [2];       // can still become 978/979
    return [1, 3, 5, 1];                           // 0-306-40615-2
  }

  function isbnFormat(c) {
    const groups = isbnGroups(c);
    const parts = [];
    let i = 0;
    for (let g = 0; g < groups.length && i < c.length; g++) {
      parts.push(c.slice(i, i + groups[g]));
      i += groups[g];
    }
    if (i < c.length) parts.push(c.slice(i));
    return parts.join('-');
  }

  function isbnChecksumOk(c) {
    let sum = 0;
    if (c.length === 13) {
      for (let i = 0; i < 12; i++) sum += Number(c[i]) * (i % 2 ? 3 : 1);
      return (10 - (sum % 10)) % 10 === Number(c[12]);
    }
    if (c.length === 10 && !is13(c)) {
      for (let i = 0; i < 9; i++) sum += Number(c[i]) * (10 - i);
      sum += c[9] === 'X' ? 10 : Number(c[9]);
      return sum % 11 === 0;
    }
    return true; // partial value — nothing to check yet
  }

  function isbnValidate(value) {
    const c = isbnClean(value);
    if (c === '') return { msg: '', blocking: false };
    if (!isbnChecksumOk(c)) {
      return { msg: 'ISBN check digit does not match', blocking: STRICT_ISBN_CHECKSUM };
    }
    return { msg: '', blocking: false };
  }

  /* ---------- DOI ---------- */

  function doiFormat(raw) {
    let v = String(raw).replace(/\s+/g, '');
    if (/^10\d/.test(v)) v = '10.' + v.slice(2);                 // 101 -> 10.1
    const m = /^10\.(\d{4,9})([^\d/].*)$/.exec(v);               // 10.1000x -> 10.1000/x
    if (m) v = '10.' + m[1] + '/' + m[2];
    return v.slice(0, 200);
  }

  function doiValidate(value) {
    if (value === '') return { msg: '', blocking: false };
    const ok = /^(1|10|10\.\d{0,9}|10\.\d{4,9}\/\S*)$/.test(value);
    return ok
      ? { msg: '', blocking: false }
      : { msg: 'DOI must look like 10.1000/abc (4–9 digits after "10.")', blocking: true };
  }

  /* ---------- caret helpers ---------- */

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

  /* ---------- ISBN / DOI inputs ---------- */

  function setupFormattedInput(inp) {
    const kind = inp.dataset.format;
    const isIsbn = kind === 'isbn';
    const validate = isIsbn ? isbnValidate : doiValidate;

    function format(raw, caretPos) {
      if (isIsbn) {
        const formatted = isbnFormat(isbnClean(raw));
        const sig = caretPos == null ? null : isbnClean(raw.slice(0, caretPos)).length;
        return { formatted, caret: sig == null ? formatted.length : caretFromSig(formatted, sig) };
      }
      const formatted = doiFormat(raw);
      let caret = formatted.length;
      if (caretPos != null && caretPos < raw.length) {
        caret = Math.max(0, Math.min(formatted.length, caretPos + formatted.length - raw.length));
      }
      return { formatted, caret };
    }

    function showState() {
      const r = validate(inp.value);
      inp.classList.toggle('is-invalid', !!r.msg);
      inp.setAttribute('aria-invalid', r.msg ? 'true' : 'false');
      inp.title = r.msg;
      return r;
    }

    function onInput() {
      inp.setCustomValidity('');
      const raw = inp.value;
      const pos = inp.selectionStart;
      const res = format(raw, pos);
      if (res.formatted !== raw) {
        inp.value = res.formatted;
        try { inp.setSelectionRange(res.caret, res.caret); } catch (_) {}
      }
      showState();
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
    const initial = format(inp.value, null);
    inp.value = initial.formatted;
    showState();
  }

  /* ---------- search box (?q=) ---------- */

  function setupSearch(inp) {
    function go() { navigateWith({ q: inp.value.trim() }); }

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

  function init() {
    document.querySelectorAll('input[name="q"]').forEach(setupSearch);
    document
      .querySelectorAll('[data-filter-panel] input.filter-input[data-format]')
      .forEach(setupFormattedInput);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

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
      .querySelectorAll('[data-dropdown][data-dynamic]')
      .forEach(function (dd) { populateDynamic(dd); });
  });
})();