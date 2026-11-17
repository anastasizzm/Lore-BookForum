// ============================================
// FILTER PANEL — segmented tabs, dynamic dropdowns
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

  if (window.__loreFiltersInitialized) return;
  window.__loreFiltersInitialized = true;

  const SOURCES = {
    genres: { url: '/api/additional/genres', param: 'genre', allLabelKey: 'js.all_genres' },
    types:  { url: '/api/additional/types',  param: 'kind',  allLabelKey: 'js.all_types' },
  };

  const STRICT_ISBN_CHECKSUM = false;

  if (!window.__loreFiltersCache) window.__loreFiltersCache = new Map();
  const cache = window.__loreFiltersCache;

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

  async function fetchPage(baseUrl, page) {
    // baseUrl может уже содержать ?lang= (он строится из location) —
    // выставляем параметры через URL, чтобы не получить ?lang=...&&lang=...
    const lang = document.documentElement.lang || (window.LoreI18n ? window.LoreI18n.locale() : 'en');
    const url = new URL(baseUrl, window.location.origin);
    url.searchParams.set('lang', lang);
    url.searchParams.set('page', String(page));
    const requestUrl = url.pathname + url.search;
    const cacheKey = requestUrl;
    if (cache.has(cacheKey)) return cache.get(cacheKey);

    const res = await fetch(requestUrl, {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      const msg = window.Messages
        ? await window.Messages.readError(res, tFilter('errors.common.list_load_failed'))
        : tFilter('errors.common.list_load_failed') + ' (HTTP ' + res.status + ').';
      const err = new Error(msg);
      err.fromServer = true;
      throw err;
    }
    let data;
    try { data = await res.json(); } catch (_) {
      const err = new Error(tFilter('errors.common.server_error'));
      err.fromServer = true;
      throw err;
    }
    
    cache.set(cacheKey, data);
    return data;
  }

  // param — переопределение query-параметра с самого dropdown (data-param):
  // страницы с серверным рендером читают в бэке другое имя, чем ссылки
  // библиотеки (?kind= → ?type= на черновиках статей). Без переопределения
  // берём src.param.
  function renderItemHtml(entry, src, current, param) {
    const id = String(entry.id ?? entry.value ?? '');
    const title = String(entry.title ?? entry.label ?? entry.id ?? '');
    const active = id === current;
    return '<li>' +
      '<a href="' + appendParam(param || src.param, id) + '" ' +
         'class="dropdown__item ' + (active ? 'is-active' : '') + '" ' +
         'data-filter-value="' + escapeHtml(id) + '">' +
        escapeHtml(title) +
      '</a>' +
    '</li>';
  }

  async function populateDynamic(dropdown) {
    const kind = dropdown.dataset.dynamic;
    const src  = SOURCES[kind];
    if (!src) return;

    const menu = dropdown.querySelector('.dropdown__menu');
    if (!menu) return;

    if (dropdown.dataset.loaded === '1') return;
    dropdown.dataset.loaded = '1';

    // Имя query-параметра можно переопределить на самом dropdown
    // (data-param): см. комментарий у renderItemHtml.
    const param = dropdown.dataset.param || src.param;

    menu.innerHTML = '<li class="dropdown__loading">' + escapeHtml(tFilter('common.loading')) + '</li>';

    const params  = new URLSearchParams(window.location.search);
    const current = params.get(param);
    const isAll   = !current;

    let nextPage = 1;

    try {
      // 1. Загружаем первую страницу
      const data = await fetchPage(src.url, nextPage);
      const list = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
      
      // Всегда добавляем "Все ..." в начало
      let html = '<li>' +
        '<a href="' + stripParam(param) + '" ' +
           'class="dropdown__item ' + (isAll ? 'is-active' : '') + '" ' +
           'data-filter-value="all">' +
          escapeHtml(tFilter(src.allLabelKey)) +
        '</a>' +
      '</li>';
      
      html += list.map(entry => renderItemHtml(entry, src, current, param)).join('');
      menu.innerHTML = html;

      // 2. Создаем кнопку "Показать ещё"
      const loadMoreLi = document.createElement('li');
      loadMoreLi.className = 'dropdown__load-more-wrap';
      loadMoreLi.hidden = !(data.meta && data.meta.hasNext);

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'dropdown__load-more';
      btn.textContent = tFilter('js.show_more');

      btn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        btn.disabled = true;
        btn.textContent = tFilter('common.loading');

        try {
          nextPage += 1;
          const nextData = await fetchPage(src.url, nextPage);
          const nextList = Array.isArray(nextData) ? nextData : (Array.isArray(nextData.items) ? nextData.items : []);
          
          // Вставляем новые элементы ПЕРЕД кнопкой "Показать ещё"
          const itemsHtml = nextList.map(entry => renderItemHtml(entry, src, current, param)).join('');
          loadMoreLi.insertAdjacentHTML('beforebegin', itemsHtml);
          
          // Если страницы закончились, скрываем кнопку
          const hasMore = !!(nextData.meta && nextData.meta.hasNext);
          loadMoreLi.hidden = !hasMore;
          
        } catch (err) {
          console.error('[filters] failed to load next page', err);
          btn.textContent = tFilter('js.try_again');
        } finally {
          btn.disabled = false;
          if (loadMoreLi.hidden === false) {
            btn.textContent = tFilter('js.show_more');
          }
        }
      });

      loadMoreLi.appendChild(btn);
      menu.appendChild(loadMoreLi);

      dropdown.dispatchEvent(new CustomEvent('dropdown:populated', { bubbles: true }));
    } catch (e) {
      console.error('[filters] dynamic load failed:', kind, e);
      notify(e && e.fromServer ? e.message : tFilter('errors.common.network_error'));
      menu.innerHTML = '<li class="dropdown__error">' + escapeHtml(tFilter('js.load_failed')) + '</li>';
    }
  }

  function navigateWith(changes) {
    // Страницы со списком (library / profile / drafts) обновляются запросом в API
    if (window.LoreList && window.LoreList.apply(changes)) return;
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

  function isbnClean(raw) { let out = ''; const src = String(raw); for (let i = 0; i < src.length && out.length < 13; i++) { const ch = src[i]; if (ch >= '0' && ch <= '9') out += ch; } return out; }
  function isbnFormat(c) { const parts = []; let i = 0; for (let g = 0; g < ISBN_GROUPS.length && i < c.length; g++) { parts.push(c.slice(i, i + ISBN_GROUPS[g])); i += ISBN_GROUPS[g]; } if (i < c.length) parts.push(c.slice(i)); return parts.join('-'); }
  function isbnShapeOk(c) { return c.length <= 3 || /^97[89]/.test(c); }
  function isbnChecksumOk(c) { if (c.length !== 13) return true; let sum = 0; for (let i = 0; i < 12; i++) sum += Number(c[i]) * (i % 2 ? 3 : 1); return (10 - (sum % 10)) % 10 === Number(c[12]); }
  function isbnValidate(value) {
    const c = isbnClean(value);
    if (c === '') return { msg: '', blocking: false };
    if (!isbnShapeOk(c)) return { msg: tFilter('errors.validation.isbn_shape', { hint: ISBN_HINT }), blocking: true };
    if (c.length < 13) return { msg: '', blocking: false };
    if (!isbnChecksumOk(c)) return { msg: tFilter('errors.validation.isbn_checksum'), blocking: STRICT_ISBN_CHECKSUM };
    return { msg: '', blocking: false };
  }

  function doiNormalize(raw) { let v = String(raw).trim(); v = v.replace(/^["'«»""'']+|["'«»""'']+$/g, ''); v = v.replace(/^doi:\s*/i, ''); v = v.replace(/^(?:https?:\/\/)?(?:dx\.)?doi\.org\//i, ''); v = v.replace(/\s+/g, ''); if (v.indexOf('/') !== -1) v = v.replace(/[.,;]+$/, ''); return v; }
  function doiFormat(raw, deleting) { let v = doiNormalize(raw); if (/^10\d/.test(v)) v = '10.' + v.slice(2); else if (v === '10' && !deleting) v = '10.'; return v.slice(0, 200); }
  function doiValidate(value) { if (value === '') return { msg: '', blocking: false }; if (value === '1' || value === '10' || value === '10.') return { msg: '', blocking: false }; if (/^10\.\d{1,7}$/.test(value)) return { msg: '', blocking: false }; if (/^10\.\d{8,}$/.test(value)) return { msg: tFilter('errors.validation.doi_missing_slash', { hint: DOI_HINT }), blocking: true }; if (/^10\.\d+\/\d*$/.test(value)) return { msg: '', blocking: false }; if (/^10\.\d+\//.test(value)) return { msg: tFilter('errors.validation.doi_suffix_digits', { hint: DOI_HINT }), blocking: true }; if (/^10\./.test(value)) return { msg: tFilter('errors.validation.doi_prefix_digits', { hint: DOI_HINT }), blocking: true }; return { msg: tFilter('errors.validation.doi_start', { hint: DOI_HINT }), blocking: true }; }
  function caretFromSig(formatted, sig) { if (sig <= 0) return 0; let count = 0; for (let i = 0; i < formatted.length; i++) { if (formatted[i] !== '-') count++; if (count === sig) return i + 1; } return formatted.length; }

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
      const ev = new CustomEvent('filter:input', { bubbles: true, cancelable: true, detail: { key: key, value: inp.value.trim() } });
      if (!inp.dispatchEvent(ev)) return;
      if (inp.classList.contains('is-invalid')) return;
      navTimer = window.setTimeout(function () { const changes = {}; changes[key] = inp.value.trim(); navigateWith(changes); }, FILTER_NAV_DELAY);
    }

    function format(raw, caretPos, deleting) {
      const atEnd = caretPos == null || caretPos >= raw.length;
      if (isIsbn) {
        const c = isbnClean(raw); const shapeOk = isbnShapeOk(c); let formatted = shapeOk ? isbnFormat(c) : c;
        if (!deleting && atEnd && shapeOk && ISBN_GROUP_ENDS.indexOf(c.length) !== -1) formatted += '-';
        const sig = caretPos == null ? null : isbnClean(raw.slice(0, caretPos)).length;
        return { formatted, caret: atEnd || sig == null ? formatted.length : caretFromSig(formatted, sig) };
      }
      const formatted = doiFormat(raw, deleting); let caret = formatted.length;
      if (caretPos != null && caretPos < raw.length) caret = Math.max(0, Math.min(formatted.length, caretPos + formatted.length - raw.length));
      return { formatted, caret };
    }

    function showState() {
      const r = validate(inp.value); const hasMsg = !!r.msg;
      inp.classList.toggle('is-invalid', hasMsg && r.blocking);
      inp.classList.toggle('is-warning', hasMsg && !r.blocking);
      inp.setAttribute('aria-invalid', hasMsg && r.blocking ? 'true' : 'false');
      inp.title = r.msg || hint;
      return r;
    }

    function onInput(e) {
      inp.setCustomValidity('');
      const raw = inp.value; const pos = inp.selectionStart;
      const deleting = !!(e && e.inputType && e.inputType.indexOf('delete') === 0);
      const res = format(raw, pos, deleting);
      if (res.formatted !== raw) { inp.value = res.formatted; try { inp.setSelectionRange(res.caret, res.caret); } catch (_) {} }
      showState(); applyFilter();
    }

    inp.addEventListener('input', onInput);
    inp.addEventListener('keydown', function (e) {
      const pos = inp.selectionStart; const sel = inp.selectionEnd;
      if (isIsbn && pos === sel) {
        if (e.key === 'Backspace' && pos > 0 && inp.value[pos - 1] === '-') inp.setSelectionRange(pos - 1, pos - 1);
        else if (e.key === 'Delete' && inp.value[pos] === '-') inp.setSelectionRange(pos + 1, pos + 1);
      }
      if (e.key !== 'Enter') return;
      e.preventDefault(); window.clearTimeout(navTimer);
      const r = showState();
      if (r.msg && r.blocking) { notify(r.msg); return; }
      if (r.msg) notify(r.msg, 'warning');
      const changes = {}; changes[inp.name] = inp.value.trim(); navigateWith(changes);
    });

    const initial = format(inp.value, null, true); inp.value = initial.formatted; showState();
  }

  function setupSearch(inp) {
    function go() {
      if (window.LoreFeedSearch) { window.LoreFeedSearch.go(inp.value.trim()); return; }
      navigateWith({ q: inp.value.trim() });
    }
    inp.addEventListener('keydown', function (e) { if (e.key !== 'Enter') return; e.preventDefault(); go(); });
    inp.addEventListener('search', function () { if (inp.value === '' && new URL(window.location.href).searchParams.has('q')) go(); });
    if (inp.form) { inp.form.addEventListener('submit', function (e) { e.preventDefault(); go(); }); }
  }

  function restoreFilterFocus() {
    const panel = document.querySelector('[data-filter-panel]');
    if (!panel || panel.hidden) return;
    const params = new URLSearchParams(window.location.search);
    ['isbn', 'doi'].forEach(function (key) {
      if (!params.get(key)) return;
      const inp = panel.querySelector('input.filter-input[data-format][name="' + key + '"]');
      if (!inp || !inp.value) return;
      inp.focus(); try { inp.setSelectionRange(inp.value.length, inp.value.length); } catch (_) {}
    });
  }

  // ============================================
  // Общая модель списка (library / profile / drafts).
  // Фильтры, табы, сортировка, поиск и Reset меняют список ОДНИМ запросом
  // в API без перезагрузки страницы. Режим задаёт data-list на .books-panel:
  //   library — /books, /articles                (пагинация ссылками)
  //   profile — /users/{id}/books|articles       (пагинация ссылками, +автор)
  //   drafts  — /drafts, книги+статьи вместе     («Показать ещё»)
  // Данные уже показанной выборки (в т.ч. SSR) кладутся в кэш —
  // возврат на неё идёт без запроса.
  // ============================================
  const LIST_KEYS    = ['q', 'genre', 'p_status', 'isbn', 'doi', 'kind', 'sort', 'ctype'];
  const FORWARD_KEYS = LIST_KEYS.concat(['lang']);
  const PAGER_KEYS   = LIST_KEYS.concat(['f', 'lang']);
  const RESET_KEYS   = ['genre', 'p_status', 'isbn', 'doi', 'series', 'kind', 'sort', 'ctype'];
  const GROUP_KEYS   = ['ctype'];

  function tOr(key, fallback) {
    const v = tFilter(key);
    return v && v !== key ? v : fallback;
  }

  const SAVE_ICON =
    '<svg width="14" height="18" viewBox="0 0 14 18" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
    '<path d="M1 2C1 1.44772 1.44772 1 2 1H12C12.5523 1 13 1.44772 13 2V16.5273C13 16.928 12.5574 17.1704 12.2039 16.9631L7 13.9114L1.79612 16.9631C1.44265 17.1704 1 16.928 1 16.5273V2Z" stroke="currentColor" stroke-width="1.5"/>' +
    '</svg>';

  function coverOf(item) {
    let cover = String(item.cover || item.coverUrl || item.iconUrl || '');
    if (!cover) {
      const icon = item.iconId;
      const uuid = icon && typeof icon === 'object' ? (icon.uuid || icon.value || '') : (icon ? String(icon) : '');
      if (uuid) cover = '/api/files/' + encodeURIComponent(uuid) + '/preview';
    }
    return cover;
  }

  function langOf() {
    return new URL(window.location.href).searchParams.get('lang') ||
           document.documentElement.lang ||
           (window.LoreI18n ? window.LoreI18n.locale() : 'en');
  }

  // library: та же разметка, что в card-book.php (режим $item)
  function renderLibraryCard(item, ctx) {
    const isArt = ctx.isArticles;
    const kind  = isArt ? 'article' : 'book';
    const id    = Number(item.id) || 0;
    const title = String(item.title ?? '');
    const cover = coverOf(item);

    const creator = item.creator || item.author;
    let authorName = '';
    let authorId = 0;
    if (creator) {
      const full = (String(creator.name ?? '') + ' ' + String(creator.surname ?? '')).trim();
      authorName = full || String(creator.username ?? '');
      authorId = Number(creator.id) || 0;
    }

    const saved = !!(item.isSaved ?? item.saved ?? false);

    let rs = item.readingStatus;
    if (rs && typeof rs === 'object') rs = rs.value;
    const readingStatus = String(rs || 'none');
    const statusLabel = readingStatus === 'reading'
      ? tOr('common.filter.reading', 'Reading')
      : readingStatus === 'ended'
        ? tOr('common.filter.finished', 'Finished')
        : '';

    const itemUrl   = id > 0 ? (isArt ? '/articles/' : '/books/') + id : '#';
    const authorUrl = authorId > 0 ? '/users/' + authorId : '#';
    const saveLabel = saved ? 'Remove from saved' : 'Save ' + kind;

    return '<article class="card-base card-book">' +
      '<div class="card-book__cover' + (cover ? '' : ' cover--empty') + '">' +
        (cover ? '<img src="' + escapeHtml(cover) + '" alt="' + escapeHtml(title) + '" loading="lazy">' : '') +
        (statusLabel ? '<span class="card-book__status card-book__status--' + escapeHtml(readingStatus) + '">' + escapeHtml(statusLabel) + '</span>' : '') +
        (ctx.draft ? '<span class="card-book__status card-book__status--draft">' + escapeHtml(ctx.draftLabel) + '</span>' : '') +
      '</div>' +
      '<button type="button" class="btn-icon btn-icon--circle card-book__save' + (saved ? ' is-active' : '') + '"' +
        ' data-save-' + kind + ' data-' + kind + '-id="' + id + '"' +
        ' data-save-url="' + escapeHtml('/api/' + kind + 's/' + id + '/save') + '"' +
        ' aria-pressed="' + (saved ? 'true' : 'false') + '"' +
        ' aria-label="' + escapeHtml(saveLabel) + '">' + SAVE_ICON + '</button>' +
      '<h3 class="card-book__title">' +
        '<a class="card-book__link" href="' + escapeHtml(itemUrl) + '" aria-label="' + escapeHtml('Open ' + kind + ': ' + title) + '">' +
          escapeHtml(title) +
        '</a>' +
      '</h3>' +
      (authorName
        ? '<p class="card-book__author"><a class="card-book__author-link" href="' + escapeHtml(authorUrl) + '"' +
          ' aria-label="' + escapeHtml('Open author profile: ' + authorName) + '">' + escapeHtml(authorName) + '</a></p>'
        : '') +
    '</article>';
  }

  // profile: как карточка в profile-publications.php
  function renderProfileCard(item, ctx) {
    const id    = Number(item.id) || 0;
    const base  = ctx.isArticles ? '/articles/' : '/books/';
    const href  = base + id + (ctx.lang ? '?lang=' + encodeURIComponent(ctx.lang) : '');
    const cover = coverOf(item);
    return '<article class="card-base card-book">' +
      '<a class="card-book__link" href="' + escapeHtml(href) + '">' +
        '<div class="card-book__cover ' + (cover ? '' : 'cover--empty') + '">' +
          (cover ? '<img src="' + escapeHtml(cover) + '" alt="" loading="lazy">' : '') +
          (ctx.isArticles && ctx.draft ? '<span class="card-book__status card-book__status--draft">' + escapeHtml(ctx.draftLabel) + '</span>' : '') +
        '</div>' +
        '<h3 class="card-book__title">' + escapeHtml(item.title ?? '') + '</h3>' +
      '</a>' +
      '<p class="card-book__author">' + escapeHtml(ctx.userName) + '</p>' +
    '</article>';
  }

  // drafts: как card-draft.php — вся карточка ведёт в редактирование,
  // Publish / Delete живут уже на экране правки.
  function renderDraftCard(b, ctx) {
    const id    = Number(b.id) || 0;
    const type  = b.__kind || ((b.type === 'article' || b.articleId) ? 'article' : 'book');
    const cover = coverOf(b);
    const lang  = ctx.lang ? '&lang=' + encodeURIComponent(ctx.lang) : '';
    const href  = id > 0
      ? '/' + (type === 'article' ? 'articles' : 'books') + '/create?id=' + id + lang + '#step-1'
      : '#';
    return '<article class="card-base card-draft" data-draft-id="' + id + '" data-draft-type="' + type + '">' +
      '<div class="card-draft__cover' + (cover ? '' : ' cover--empty') + '">' +
        (cover ? '<img src="' + escapeHtml(cover) + '" alt="' + escapeHtml(b.title) + '" loading="lazy">' : '') +
        '<span class="card-draft__badge">' + escapeHtml(ctx.draftLabel) + '</span>' +
      '</div>' +
      '<h3 class="card-draft__title"><a class="card-draft__link" href="' + escapeHtml(href) + '"' +
        ' aria-label="' + escapeHtml(tOr('common.drafts.continue', 'Continue') + ': ' + (b.title || '')) + '">' +
        escapeHtml(b.title) + '</a></h3>' +
    '</article>';
  }

  function pagerHref(url, page) {
    const q = new URLSearchParams();
    PAGER_KEYS.forEach(function (k) {
      const v = url.searchParams.get(k);
      if (v) q.set(k, v);
    });
    if (page > 1) q.set('page', String(page));
    return '?' + q.toString();
  }

  // Пагинация остаётся обычными <a href> (как в library-list.php / profile-publications.php)
  function buildPager(url, page, hasNext) {
    if (page <= 1 && !hasNext) return '';
    const prev = tOr('common.library.prev', tOr('common.common.prev', 'Previous'));
    const next = tOr('common.library.next', tOr('common.common.next', 'Next'));
    const aria = tOr('common.library.pagination', tOr('common.common.pagination', 'Pagination'));
    let html = '<nav class="books-panel__pager" aria-label="' + escapeHtml(aria) + '">';
    if (page > 1)  html += '<a class="btn btn--secondary" href="' + escapeHtml(pagerHref(url, page - 1)) + '">' + escapeHtml(prev) + '</a>';
    if (hasNext)   html += '<a class="btn btn--secondary" href="' + escapeHtml(pagerHref(url, page + 1)) + '">' + escapeHtml(next) + '</a>';
    return html + '</nav>';
  }

  function setupLibraryAjax() {
    const root = document.querySelector('.books-panel');
    if (!root) return;

    const path = window.location.pathname.replace(/\/+$/, '');
    let mode = root.dataset.list || '';
    if (!mode && (path === '/books' || path === '/articles')) mode = 'library';
    if (mode !== 'library' && mode !== 'profile' && mode !== 'drafts') return;

    const isDrafts   = mode === 'drafts';
    const isArticles = path.indexOf('/articles') !== -1;

    const grid = isDrafts ? root.querySelector('[data-drafts-grid]') : root.querySelector('.grid-books');
    if (!grid) return;
    const emptyState = isDrafts ? root.querySelector('[data-drafts-empty]') : root.querySelector('.empty-state');
    const countEl    = isDrafts ? root.querySelector('[data-drafts-count]') : root.querySelector('.books-panel__meta span');
    const plusEl     = isDrafts ? root.querySelector('[data-drafts-plus]')  : null;
    const moreWrap   = isDrafts ? root.querySelector('[data-drafts-more]')  : null;
    const moreBtn    = isDrafts ? root.querySelector('[data-drafts-load-more]') : null;

    // профиль: чьи публикации + как называется параметр в API
    const userId    = root.dataset.listUser || '';
    const userParam = root.dataset.listUserParam || 'creatorId';
    const userName  = root.dataset.listName || '';
    if (mode === 'profile' && !userId) return;

    const API = {
      book:    root.dataset.apiBooks    || '/api/books',
      article: root.dataset.apiArticles || '/api/articles',
    };

    function hrefParam(a, name) {
      return new URL(a.getAttribute('href'), window.location.href).searchParams.get(name) || '';
    }

    // --- Группы табов: статус чтения (status) и тип контента в черновиках (ctype) ---
    const groups = [];
    document.querySelectorAll('[data-filter-panel] .tabs').forEach(function (tabsEl) {
      const tabs = Array.from(tabsEl.querySelectorAll('a.tab[href]'));
      const key = GROUP_KEYS.find(function (k) {
        return tabs.some(function (t) {
          return new URL(t.getAttribute('href'), window.location.href).searchParams.has(k);
        });
      });
      if (key) groups.push({ tabsEl: tabsEl, tabs: tabs, key: key });
    });

    const labels = {};
    groups.forEach(function (g) {
      if (g.key !== 'status') return;
      g.tabs.forEach(function (t) {
        const s = hrefParam(t, 'status').toLowerCase();
        if (s) labels[s] = t.textContent.trim();
      });
    });

    // --- Сортировка: пункты дропдауна в шапке списка ---
    const sortItems = [];
    document.querySelectorAll('.books-panel__head .dropdown__item[href]').forEach(function (a) {
      const value = hrefParam(a, 'sort');
      if (value) sortItems.push({ a: a, value: value, label: a.textContent.trim() });
    });
    const defaultSort = (sortItems.find(function (s) { return s.value === 'newest'; }) || sortItems[0] || {}).value || 'newest';

    const startUrl = new URL(window.location.href);
    const sortOf = function (url) { return url.searchParams.get('sort') || defaultSort; };

    function keyFor(url) {
      const pairs = [];
      url.searchParams.forEach(function (v, k) { if (k !== 'f' && k !== 'sort') pairs.push(k + '=' + v); });
      pairs.push('sort=' + sortOf(url));
      return url.pathname + '?' + pairs.sort().join('&');
    }

    function urlWith(changes) {
      const url = new URL(window.location.href);
      Object.keys(changes).forEach(function (k) {
        if (changes[k]) url.searchParams.set(k, changes[k]);
        else url.searchParams.delete(k);
      });
      url.searchParams.delete('page');
      return url;
    }

    function resetUrl() {
      const url = new URL(window.location.href);
      RESET_KEYS.forEach(function (k) { url.searchParams.delete(k); });
      url.searchParams.delete('page');
      return url;
    }

    // ---------- состояние на экране ----------
    let moreState = {
      page: Number(root.dataset.initialPage || 1) || 1,
      hasNext: root.dataset.initialHasNext === '1',
    };

    function snapshot() {
      const pager = root.querySelector('.books-panel__pager');
      return {
        grid:    grid.innerHTML,
        empty:   grid.hidden,
        count:   countEl ? countEl.textContent : '0',
        pager:   pager ? pager.outerHTML : '',
        page:    moreState.page,
        hasNext: moreState.hasNext,
      };
    }

    function updateDraftsMeta() {
      if (countEl) countEl.textContent = String(grid.children.length);
      if (plusEl) plusEl.hidden = !moreState.hasNext;
      if (moreWrap) moreWrap.hidden = !moreState.hasNext;
    }

    function render(state) {
      grid.innerHTML = state.grid;
      grid.hidden = !!state.empty;
      if (emptyState) emptyState.hidden = !state.empty;
      if (isDrafts) {
        moreState = { page: state.page, hasNext: state.hasNext };
        updateDraftsMeta();
        return;
      }
      if (countEl) countEl.textContent = state.count;
      const old = root.querySelector('.books-panel__pager');
      if (old) old.remove();
      if (state.pager) grid.insertAdjacentHTML('afterend', state.pager);
    }

    // ---------- синхронизация контролов с URL ----------
    const sortLabelOf = function (value) {
      const it = sortItems.find(function (s) { return s.value === value; });
      return it ? it.label : '';
    };
    let shownSort = sortOf(startUrl);

    function replaceTriggerLabel(oldLabel, newLabel) {
      if (!oldLabel || !newLabel || oldLabel === newLabel || !sortItems.length) return;
      const dd = sortItems[0].a.closest('.dropdown');
      const trigger = dd && dd.querySelector('.dropdown__trigger');
      if (!trigger) return;
      const walker = document.createTreeWalker(trigger, NodeFilter.SHOW_TEXT);
      let node;
      while ((node = walker.nextNode())) {
        if (node.nodeValue.indexOf(oldLabel) !== -1) {
          node.nodeValue = node.nodeValue.replace(oldLabel, newLabel);
          return;
        }
      }
    }

    function syncLinks(url) {
      const links = [];
      groups.forEach(function (g) { g.tabs.forEach(function (t) { links.push([t, g.key]); }); });
      sortItems.forEach(function (s) { links.push([s.a, 'sort']); });
      document.querySelectorAll('[data-dropdown][data-dynamic] a.dropdown__item[href]').forEach(function (a) {
        const dd = a.closest('[data-dropdown][data-dynamic]');
        const src = SOURCES[dd.dataset.dynamic];
        if (src) links.push([a, src.param]);
      });
      links.forEach(function (pair) {
        const a = pair[0], own = pair[1];
        const u = new URL(a.getAttribute('href'), window.location.href);
        LIST_KEYS.forEach(function (k) {
          if (k === own) return;
          const v = url.searchParams.get(k);
          if (v) u.searchParams.set(k, v); else u.searchParams.delete(k);
        });
        u.searchParams.delete('page');
        a.setAttribute('href', u.pathname + u.search);
      });
    }

    function applyControls(url) {
      // табы
      groups.forEach(function (g) {
        const cur = (url.searchParams.get(g.key) || '').toLowerCase();
        g.tabs.forEach(function (t) { t.classList.toggle('is-active', hrefParam(t, g.key).toLowerCase() === cur); });
        updateSegmentIndicator(g.tabsEl);
      });
      // сортировка
      const sort = sortOf(url);
      sortItems.forEach(function (s) { s.a.classList.toggle('is-active', s.value === sort); });
      replaceTriggerLabel(sortLabelOf(shownSort), sortLabelOf(sort));
      shownSort = sort;
      // дропдауны жанра/типа
      document.querySelectorAll('[data-dropdown][data-dynamic]').forEach(function (dd) {
        const src = SOURCES[dd.dataset.dynamic];
        if (!src) return;
        const cur = url.searchParams.get(src.param) || '';
        dd.querySelectorAll('a.dropdown__item').forEach(function (a) {
          const v = a.dataset.filterValue === 'all' ? '' : (a.dataset.filterValue || '');
          a.classList.toggle('is-active', v === cur);
        });
      });
      // поля ввода (поиск, ISBN, DOI); то, что пользователь печатает прямо сейчас, не трогаем
      document.querySelectorAll('input[data-filter-key], input[name="q"]').forEach(function (inp) {
        const key = inp.dataset.filterKey || inp.name;
        if (LIST_KEYS.indexOf(key) === -1 || document.activeElement === inp) return;
        const v = url.searchParams.get(key) || '';
        if (inp.value !== v) {
          inp.value = v;
          inp.classList.remove('is-invalid', 'is-warning');
          inp.removeAttribute('aria-invalid');
        }
      });
      syncLinks(url);
    }

    function closeDropdownOf(el) {
      const dd = el.closest('.dropdown');
      if (!dd) return;
      dd.classList.remove('is-open');
      const trg = dd.querySelector('.dropdown__trigger');
      if (trg) trg.setAttribute('aria-expanded', 'false');
    }

    // ---------- загрузка ----------
    function sources(url) {
      if (!isDrafts) return [{ kind: isArticles ? 'article' : 'book' }];
      // «Все» больше нет: на /books/drafts — только книги, на
      // /articles/drafts — только статьи. Старый ?ctype= игнорируем,
      // чтобы он не расходился с активной вкладкой.
      return [{ kind: isArticles ? 'article' : 'book' }];
    }

    async function fetchSource(src, url, page, signal) {
      const api = new URLSearchParams();
      FORWARD_KEYS.forEach(function (k) {
        const v = url.searchParams.get(k);
        if (v) api.set(k, v);
      });
      api.delete('ctype');
      (src.kind === 'article' ? ['status', 'isbn'] : ['kind', 'doi']).forEach(function (k) { api.delete(k); });
      if (!api.has('lang')) api.set('lang', langOf());
      // Библиотека/профиль/черновики рисуют автора на карточке — просим
      // creator всегда. Раньше SSR-страница передавала include, а AJAX-фильтры
      // нет, и после применения фильтра имя автора исчезало.
      if (!api.has('include')) api.set('include', 'creator');
      if (mode === 'profile') { api.set(userParam, userId); api.set('include', 'creator'); }
      if (isDrafts) {
        api.set('p_status', 'draft');
        api.set('include', 'creator');
        if (!api.has('sort')) api.set('sort', 'newest');
      }
      api.set('page', String(page));

      const res = await fetch(API[src.kind] + '?' + api.toString(), {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
        signal: signal || undefined,
      });
      if (!res.ok) {
        const msg = window.Messages
          ? await window.Messages.readError(res, tFilter('errors.common.list_load_failed'))
          : tFilter('errors.common.list_load_failed') + ' (HTTP ' + res.status + ').';
        const err = new Error(msg);
        err.fromServer = true;
        throw err;
      }
      let data;
      try { data = await res.json(); } catch (_) {
        const err = new Error(tFilter('errors.common.server_error'));
        err.fromServer = true;
        throw err;
      }
      const items = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : (Array.isArray(data.data) ? data.data : []));
      items.forEach(function (it) { it.__kind = src.kind; });
      return { items: items, hasNext: !!(data.meta && data.meta.hasNext) };
    }

    function dateOf(it) { return Date.parse(it.updatedAt || it.createdAt || '') || 0; }

    async function load(url, page, signal) {
      const srcs = sources(url);
      const results = await Promise.allSettled(srcs.map(function (s) { return fetchSource(s, url, page, signal); }));
      const ok = results.filter(function (r) { return r.status === 'fulfilled'; }).map(function (r) { return r.value; });
      if (!ok.length) throw results[0].reason;
      let aborted = results.find(function (r) { return r.status === 'rejected' && r.reason && r.reason.name === 'AbortError'; });
      if (aborted) throw aborted.reason;

      let items = [];
      let hasNext = false;
      ok.forEach(function (r) { items = items.concat(r.items); if (r.hasNext) hasNext = true; });

      // Профиль: если API не понял параметр автора и вернул чужое — не показываем, уходим на обычную загрузку
      if (mode === 'profile') {
        const foreign = items.some(function (it) {
          const c = it.creator || it.author;
          return c && c.id !== undefined && c.id !== null && String(c.id) !== String(userId);
        });
        if (foreign) { const err = new Error('foreign items'); err.fallback = true; throw err; }
      }

      // Два источника склеены — упорядочиваем внутри страницы
      if (srcs.length > 1) {
        const s = sortOf(url);
        if (s === 'alpha') items.sort(function (a, b) { return String(a.title || '').localeCompare(String(b.title || '')); });
        else if (s === 'newest') items.sort(function (a, b) { return dateOf(b) - dateOf(a); });
      }

      const draftLabel = tOr('common.drafts.badge', 'Draft');
      const ctx = {
        isArticles: isArticles,
        labels: labels,
        draft: url.searchParams.get('p_status') === 'draft',
        draftLabel: draftLabel,
        userName: userName,
        lang: url.searchParams.get('lang') || '',
      };
      const renderItem = isDrafts ? renderDraftCard : (mode === 'profile' ? renderProfileCard : renderLibraryCard);
      return {
        grid:    items.map(function (it) { return renderItem(it, ctx); }).join(''),
        empty:   items.length === 0,
        count:   String(items.length),
        pager:   isDrafts ? '' : buildPager(url, page, hasNext),
        page:    page,
        hasNext: hasNext,
      };
    }

    // ---------- переход к новой выборке ----------
    const cache = new Map();
    let shownKey = keyFor(startUrl);
    let shownUrl = startUrl;
    let ctl = null;
    let ticket = 0;

    function setBusy(on) {
      grid.style.opacity = on ? '0.5' : '';
      grid.style.pointerEvents = on ? 'none' : '';
      if (on) grid.setAttribute('aria-busy', 'true'); else grid.removeAttribute('aria-busy');
    }

    async function go(targetUrl, push, force) {
      const targetKey = keyFor(targetUrl);
      if (!force && targetKey === shownKey) return;

      const myTicket = ++ticket;
      if (ctl) ctl.abort();
      ctl = null;

      if (!force) cache.set(shownKey, snapshot()); // что сейчас на экране (SSR или полученное ранее)
      applyControls(targetUrl);

      let state = force ? null : cache.get(targetKey);
      if (!state) {
        ctl = new AbortController();
        setBusy(true);
        try {
          const page = isDrafts ? 1 : (Number(targetUrl.searchParams.get('page')) || 1);
          state = await load(targetUrl, page, ctl.signal);
        } catch (e) {
          if (myTicket !== ticket || (e && e.name === 'AbortError')) return; // запустили более новый
          setBusy(false);
          if (e && e.fallback) { window.location.href = targetUrl.toString(); return; }
          console.error('[filters] list load failed', e);
          notify(e && e.fromServer ? e.message : tFilter('errors.common.network_error'));
          applyControls(shownUrl);
          return;
        }
        if (myTicket !== ticket) return;
        setBusy(false);
      }

      render(state);
      shownKey = targetKey;
      shownUrl = new URL(targetUrl.toString());
      if (push) window.history.pushState({ loreList: true }, '', targetUrl.pathname + targetUrl.search);
      document.dispatchEvent(new CustomEvent('library:updated', { detail: { mode: mode, url: targetUrl.toString() } }));
    }

    // «Показать ещё» (черновики)
    let moreBusy = false;
    async function more() {
      if (!isDrafts || moreBusy || !moreState.hasNext) return;
      moreBusy = true;
      root.classList.add('is-loading');
      if (moreBtn) moreBtn.setAttribute('disabled', 'disabled');
      const myTicket = ticket;
      const next = moreState.page + 1;
      try {
        const r = await load(new URL(window.location.href), next, null);
        if (myTicket !== ticket) return;
        grid.insertAdjacentHTML('beforeend', r.grid);
        grid.hidden = false;
        if (emptyState) emptyState.hidden = true;
        moreState = { page: next, hasNext: r.hasNext };
        updateDraftsMeta();
      } catch (e) {
        console.error('[filters] load more failed', e);
        notify(e && e.fromServer ? e.message : tFilter('errors.common.network_error'));
      } finally {
        moreBusy = false;
        root.classList.remove('is-loading');
        if (moreBtn) moreBtn.removeAttribute('disabled');
      }
    }

    // ---------- перехват кликов (делегирование, до остальных обработчиков) ----------
    function linkChange(a) {
      for (let i = 0; i < groups.length; i++) {
        if (groups[i].tabs.indexOf(a) !== -1) {
          const ch = {}; ch[groups[i].key] = hrefParam(a, groups[i].key).toLowerCase(); return ch;
        }
      }
      const s = sortItems.find(function (x) { return x.a === a; });
      if (s) return { sort: s.value };
      const dd = a.closest('[data-dropdown][data-dynamic]');
      if (dd && a.classList.contains('dropdown__item')) {
        const src = SOURCES[dd.dataset.dynamic];
        if (src) {
          const v = a.dataset.filterValue || '';
          const ch = {}; ch[src.param] = v === 'all' ? '' : v; return ch;
        }
      }
      return null;
    }

    document.addEventListener('click', function (e) {
      if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      const t = e.target;
      if (!(t instanceof Element)) return;

      const reset = t.closest('[data-filter-reset]');
      if (reset && reset.closest('[data-filter-panel]')) {
        e.preventDefault();
        e.stopImmediatePropagation();
        go(resetUrl(), true);
        return;
      }

      const a = t.closest('a[href]');
      if (!a) return;
      const change = linkChange(a);
      if (!change) return;
      e.preventDefault();
      e.stopImmediatePropagation();
      closeDropdownOf(a);
      go(urlWith(change), true);
    }, true);

    window.addEventListener('popstate', function () { go(new URL(window.location.href), false); });
    if (moreBtn) moreBtn.addEventListener('click', more);

    // Точка входа для остального кода (поля ввода, поиск)
    window.LoreList = {
      apply: function (changes) { go(urlWith(changes), true); return true; },
      more: more,
    };

    // Профиль и черновики: SSR мог не учесть параметры из URL — один раз освежаем список запросом
    if (root.dataset.listHydrate !== undefined) {
      const hasParams = LIST_KEYS.some(function (k) { return !!startUrl.searchParams.get(k); }) ||
                        (!isDrafts && (Number(startUrl.searchParams.get('page')) || 1) > 1);
      if (hasParams) go(startUrl, false, true);
    }
  }

  function init() {
    // Поля поиска админки (.admin-toolbar) ищут сами через API (public/assets/js/admin/*).
    // Не навешиваем на них поиск ленты/списков: он делает window.location.href = ?q=… и
    // перезагружает страницу. Чтобы исключить своё поле в другом месте — data-own-search.
    document.querySelectorAll('input[name="q"]').forEach(function (inp) {
      if (inp.closest('.admin-toolbar, [data-own-search]')) return;
      setupSearch(inp);
    });
    document.querySelectorAll('[data-filter-panel] input.filter-input[data-format]').forEach(setupFormattedInput);
    document.querySelectorAll('[data-dropdown][data-dynamic]').forEach(function (dd) {
      const row = dd.closest('.filter-panel__row');
      if (row && row.hidden) return;
      populateDynamic(dd);
    });
    restoreFilterFocus();
    setupLibraryAjax();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();