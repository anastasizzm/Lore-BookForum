// ============================================
// FILTER PANEL — segmented tabs, dynamic dropdowns (genres, article-types)
// ============================================

function tFilter(key, params) {
  return window.LoreI18n ? LoreI18n.t(key, params) : key;
}

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

document.querySelectorAll('.tabs--segmented').forEach(tabsEl => {
  updateSegmentIndicator(tabsEl, true);
  window.addEventListener('resize', () => updateSegmentIndicator(tabsEl, true));
});

document.querySelectorAll('[data-filter-panel] .tab').forEach(tab => {
  tab.addEventListener('click', (e) => {
    const href = tab.getAttribute('href') || '';
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

(function () {
  'use strict';

  const SOURCES = {
    genres: {
      url:         '/api/additional/genres',
      param:       'genre',
      allLabelKey: 'js.all_genres',
    },
    types: {
      url:         '/api/additional/types',
      param:       'kind',
      allLabelKey: 'js.all_types',
    },
  };

  const cache = new Map();
  const STRICT_ISBN_CHECKSUM = false;

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
      // URL для /api/additional/* — патч fetch добавит ?lang=
      const res = await fetch(url + '?page=' + p, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        const msg = window.Messages
          ? await window.Messages.readError(res, tFilter('js.list_load_failed'))
          : tFilter('js.list_load_failed') + ' (HTTP ' + res.status + ').';
        const err = new Error(msg);
        err.fromServer = true;
        throw err;
      }

      let data;
      try {
        data = await res.json();
      } catch (_) {
        const err = new Error(tFilter('js.server_error'));
        err.fromServer = true;
        throw err;
      }
      const list = Array.isArray(data)
        ? data
        : (Array.isArray(data.items) ? data.items : []);

      all.push(...list);

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

    menu.innerHTML = '<li class="dropdown__loading">' + escapeHtml(tFilter('common.loading')) + '</li>';

    try {
      const list = await fetchAll(src.url);

      const params  = new URLSearchParams(window.location.search);
      const current = params.get(src.param);
      const isAll   = !current;

      const items = [
        {
          id:     'all',
          title:  tFilter(src.allLabelKey),
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

      dropdown.dispatchEvent(new CustomEvent('dropdown:populated', { bubbles: true }));
    } catch (e) {
      console.error('[filters] dynamic load failed:', kind, e);
      notify(e && e.fromServer ? e.message : tFilter('js.network_error'));
      menu.innerHTML = '<li class="dropdown__error">' + escapeHtml(tFilter('js.load_failed')) + '</li>';
    }
  }

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

  const ISBN_GROUPS = [3, 1, 3, 5, 1];
  const ISBN_HINT = tFilter('js.isbn_hint');
  const DOI_HINT  = tFilter('js.doi_hint');
  const ISBN_GROUP_ENDS = [3, 4, 7, 12];

  function isbnClean(raw) {
    let out = '';
    const src = String(raw);
    for (let i = 0; i < src.length && out.length < 13; i++) {
      const ch = src[i];
      if (ch >= '0' && ch <= '9') out += ch;
    }
    return out;
  }

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

  function isbnShapeOk(c) {
    return c.length <= 3 || /^97[89]/.test(c);
  }

  function isbnChecksumOk(c) {
    if (c.length !== 13) return true;
    let sum = 0;
    for (let i = 0; i < 12; i++) sum += Number(c[i]) * (i % 2 ? 3 : 1);
    return (10 - (sum % 10)) % 10 === Number(c[12]);
  }

  function isbnValidate(value) {
    const c = isbnClean(value);
    if (c === '') return { msg: '', blocking: false };
    if (!isbnShapeOk(c)) {
      return { msg: tFilter('js.isbn_shape', { hint: ISBN_HINT }), blocking: true };
    }
    if (c.length < 13) return { msg: '', blocking: false };
    if (!isbnChecksumOk(c)) {
      return { msg: tFilter('js.isbn_checksum'), blocking: STRICT_ISBN_CHECKSUM };
    }
    return { msg: '', blocking: false };
  }

  function doiNormalize(raw) {
    let v = String(raw).trim();
    v = v.replace(/^["'«»“”‘’]+|["'«»“”‘’]+$/g, '');
    v = v.replace(/^doi:\s*/i, '');
    v = v.replace(/^(?:https?:\/\/)?(?:dx\.)?doi\.org\//i, '');
    v = v.replace(/\s+/g, '');
    if (v.indexOf('/') !== -1) v = v.replace(/[.,;]+$/, '');
    return v;
  }

  function doiFormat(raw, deleting) {
    let v = doiNormalize(raw);
    if (/^10\d/.test(v)) v = '10.' + v.slice(2);
    else if (v === '10' && !deleting) v = '10.';
    return v.slice(0, 200);
  }

  function doiValidate(value) {
    if (value === '') return { msg: '', blocking: false };
    if (value === '1' || value === '10' || value === '10.') return { msg: '', blocking: false };
    if (/^10\.\d{1,7}$/.test(value)) return { msg: '', blocking: false };
    if (/^10\.\d{8,}$/.test(value)) {
      return { msg: tFilter('js.doi_missing_slash', { hint: DOI_HINT }), blocking: true };
    }
    if (/^10\.\d+\/\d*$/.test(value)) return { msg: '', blocking: false };
    if (/^10\.\d+\//.test(value)) {
      return { msg: tFilter('js.doi_suffix_digits', { hint: DOI_HINT }), blocking: true };
    }
    if (/^10\./.test(value)) {
      return { msg: tFilter('js.doi_prefix_digits', { hint: DOI_HINT }), blocking: true };
    }
    return { msg: tFilter('js.doi_start', { hint: DOI_HINT }), blocking: true };
  }

  function caretFromSig(formatted, sig) {
    if (sig <= 0) return 0;
    let count = 0;
    for (let i = 0; i < formatted.length; i++) {
      if (formatted[i] !== '-') count++;
      if (count === sig) return i + 1;
    }
    return formatted.length;
  }

  const FILTER_NAV_DELAY = 500;

  function setupFormattedInput(inp) {
    const kind = inp.dataset.format;
    const isIsbn = kind === 'isbn';
    const validate = isIsbn ? isbnValidate : doiValidate;
    const hint = isIsbn ? ISBN_HINT : DOI_HINT;
    let navTimer = 0;

    function applyFilter() {
      const key = inp.dataset.filterKey || inp.name;
      if (!key) return;

      window.clearTimeout(navTimer);

      const ev = new CustomEvent('filter:input', {
        bubbles: true,
        cancelable: true,
        detail: { key: key, value: inp.value.trim() },
      });
      if (!inp.dispatchEvent(ev)) return;
      if (inp.classList.contains('is-invalid')) return;

      navTimer = window.setTimeout(function () {
        const changes = {};
        changes[key] = inp.value.trim();
        navigateWith(changes);
      }, FILTER_NAV_DELAY);
    }

    function format(raw, caretPos, deleting) {
      const atEnd = caretPos == null || caretPos >= raw.length;

      if (isIsbn) {
        const c = isbnClean(raw);
        const shapeOk = isbnShapeOk(c);
        let formatted = shapeOk ? isbnFormat(c) : c;
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
      inp.classList.toggle('is-invalid', hasMsg && r.blocking);
      inp.classList.toggle('is-warning', hasMsg && !r.blocking);
      inp.setAttribute('aria-invalid', hasMsg && r.blocking ? 'true' : 'false');
      inp.title = r.msg || hint;
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

      if (isIsbn && pos === sel) {
        if (e.key === 'Backspace' && pos > 0 && inp.value[pos - 1] === '-') {
          inp.setSelectionRange(pos - 1, pos - 1);
        } else if (e.key === 'Delete' && inp.value[pos] === '-') {
          inp.setSelectionRange(pos + 1, pos + 1);
        }
      }

      if (e.key !== 'Enter') return;
      e.preventDefault();
      window.clearTimeout(navTimer);

      const r = showState();
      if (r.msg && r.blocking) { notify(r.msg); return; }
      if (r.msg) notify(r.msg, 'warning');
      const changes = {};
      changes[inp.name] = inp.value.trim();
      navigateWith(changes);
    });

    const initial = format(inp.value, null, true);
    inp.value = initial.formatted;
    showState();
  }

  function setupSearch(inp) {
    // На страницах с [data-library] (library-list, saved-list) поиск
    // обрабатывает library-filters.js — через fetch, без перезагрузки,
    // с сохранением ?lang=. Здесь не навешиваем свой обработчик, чтобы
    // Enter не приводил к полному переходу и не сбрасывал язык.
    if (document.querySelector('[data-library]')) return;

    function go() {
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

    inp.addEventListener('search', function () {
      if (inp.value === '' && new URL(window.location.href).searchParams.has('q')) go();
    });

    if (inp.form) {
      inp.form.addEventListener('submit', function (e) {
        e.preventDefault();
        go();
      });
    }
  }

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
    document.querySelectorAll('[data-filter-panel] input.filter-input[data-format]')
      .forEach(setupFormattedInput);
    document.querySelectorAll('[data-dropdown][data-dynamic]')
      .forEach(function (dd) {
        const row = dd.closest('.filter-panel__row');
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