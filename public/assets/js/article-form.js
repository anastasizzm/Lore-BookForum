/**
 * article-form.js — страница создания / редактирования статьи
 * (src/views/pages/article/article-form.php).
 */
(function () {
  'use strict';

  var form = document.getElementById('articleForm');
  if (!form) return;

  var Text = window.LoreArticleText;
  if (!Text) console.error('[article-form] article-text.js is not loaded');

  /* ============================================================
     КОНСТАНТЫ
     ============================================================ */
  var LIMITS = {
    title: 200,
    annotation: 5000,
    authorNote: 2000,
    contentHtml: 300000,
    coverBytes: 5 * 1024 * 1024,
    imageBytes: 5 * 1024 * 1024,
    excerptPages: 30,
  };
  var COVER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
  var IMAGE_MIMES = COVER_MIMES;
  var IMAGE_UPLOAD_URL = '/api/articles/images';
  var IMG_SRC_RE = /^\/uploads\/articles\/[A-Za-z0-9][A-Za-z0-9._\/-]*$/;
  var DOI_RE = /^10\.\d{4,9}\/\S+$/;
  var AUTOSAVE_MS = 30000;
  var LOCAL_PREFIX = 'articleform:v1:';
  var PDF_BASE = '/assets/js/pdfjs/';

  var SOURCES = {
    genres: '/api/additional/genres',
    types:  '/api/additional/types',
  };

  var DEV_SRC = (function () {
    var s = new URLSearchParams(location.search).get('src');
    return s && s.charAt(0) === '/' && s.charAt(1) !== '/' ? s : '';
  })();

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  // Переход после сохранения без ?lang= сбрасывал язык — бэк определяет
  // локаль по query, поэтому адрес прогоняем через LoreI18n.withLang.
  function withLang(url) {
    return window.LoreI18n && typeof window.LoreI18n.withLang === 'function'
      ? window.LoreI18n.withLang(url)
      : url;
  }

  function escapeHtml(s) { return Text ? Text.escapeHtml(s) : String(s); }
  function clamp(n, min, max) { return Math.min(Math.max(n, min), max); }
  function debounce(fn, ms) {
    var timer;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, ms);
    };
  }
  function $(sel, root) { return (root || form).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || form).querySelectorAll(sel)); }

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  }

  function csrfFetch(url, opts) {
    return (window.LoreCsrf && typeof window.LoreCsrf.fetch === 'function')
      ? window.LoreCsrf.fetch(url, opts)
      : fetch(url, opts);
  }

  /* ============================================================
     СОСТОЯНИЕ
     ============================================================ */
  var state = {
    mode: 'standalone',
    book: null,
    start: null,
    end: null,
    dirty: false,
    saving: false,
    coverDirty: false,
    articleId: 0,
  };

  var idField = $('[name="id"]');
  state.articleId = idField ? (parseInt(idField.value, 10) || 0) : 0;

  var editor = null;
  var coverValidator = null;
  var stepsApi = null;           // заполняется в init(): { current, goTo }

  /* ============================================================
     ЯКОРИ <-> СКРЫТЫЕ ПОЛЯ ФОРМЫ
     ============================================================ */
  var ANCHOR_FIELDS = {
    start: { page: 'page_start', offset: 'start_offset', line: 'start_line', char: 'start_char', quote: 'start_quote' },
    end:   { page: 'page_end',   offset: 'end_offset',   line: 'end_line',   char: 'end_char',   quote: 'end_quote' },
  };

  function fieldByName(name) { return $('[name="' + name + '"]'); }

  function readAnchor(side) {
    var map = ANCHOR_FIELDS[side];
    var page = parseInt((fieldByName(map.page) || {}).value, 10);
    var offset = parseInt((fieldByName(map.offset) || {}).value, 10);
    if (!(page > 0) || !(offset >= 0)) return null;
    return {
      page: page,
      offset: offset,
      line: parseInt((fieldByName(map.line) || {}).value, 10) || 0,
      char: parseInt((fieldByName(map.char) || {}).value, 10) || 0,
      quote: (fieldByName(map.quote) || {}).value || '',
    };
  }

  function writeAnchor(side) {
    var map = ANCHOR_FIELDS[side];
    var a = state[side];
    Object.keys(map).forEach(function (key) {
      var el = fieldByName(map[key]);
      if (el) el.value = a ? String(a[key]) : '';
    });
  }

  function renderSummary() {
    var box = $('[data-fragment-summary]');
    if (!box) return;
    if (state.start && state.end) {
      box.textContent = t('articleform.fragment_summary', {
        sp: state.start.page, sl: state.start.line, sc: state.start.char,
        ep: state.end.page, el: state.end.line, ec: state.end.char,
      });
      box.title = (state.start.quote ? '«' + state.start.quote + '…»' : '') +
        (state.end.quote ? ' — «…' + state.end.quote + '»' : '');
      box.classList.add('is-filled');
    } else {
      box.textContent = t('articleform.fragment_empty');
      box.title = '';
      box.classList.remove('is-filled');
    }
  }

  /* ============================================================
     ИЗМЕНЕНИЯ / ЧЕРНОВИКИ
     ============================================================ */
  var scheduleBackup = debounce(writeBackup, 800);

  function markDirty() {
    state.dirty = true;
    scheduleBackup();
  }

  function localKey() { return LOCAL_PREFIX + (state.articleId || 'new'); }

  function snapshot() {
    return {
      savedAt: Date.now(),
      mode: state.mode,
      title: (fieldByName('title') || {}).value || '',
      genre: (fieldByName('genre') || {}).value || '',
      type: (fieldByName('type') || {}).value || '',
      doi: (fieldByName('doi') || {}).value || '',
      annotation: (fieldByName('annotation') || {}).value || '',
      author_note: (fieldByName('author_note') || {}).value || '',
      content: editor ? editor.getHtml() : '',
      book: state.book,
      start: state.start,
      end: state.end,
    };
  }

  function writeBackup() {
    if (!state.dirty) return;
    try { localStorage.setItem(localKey(), JSON.stringify(snapshot())); } catch (_) {}
  }

  function clearBackup() {
    try { localStorage.removeItem(localKey()); } catch (_) {}
  }

  function readBackup() {
    try {
      var raw = localStorage.getItem(localKey());
      return raw ? JSON.parse(raw) : null;
    } catch (_) { return null; }
  }

  /* ============================================================
     СПИСКИ: жанры и типы
     ============================================================ */
  var listCache = {};

  function fetchAll(url) {
    if (listCache[url]) return Promise.resolve(listCache[url]);
    const lang = document.documentElement.lang || (window.LoreI18n ? window.LoreI18n.locale() : 'en');
    var all = [];
    var page = 1;

    function next() {
      return fetch(url + `?lang=${lang}&page=` + page, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }).then(function (data) {
        var list = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
        for (var i = 0; i < list.length; i++) all.push(list[i]);
        var hasNext = !Array.isArray(data) && data.meta && data.meta.hasNext;
        if (hasNext && page < 20) { page += 1; return next(); }
        listCache[url] = all;
        return all;
      });
    }
    return next();
  }

  function populateSelect(select) {
    var url = SOURCES[select.getAttribute('data-dynamic-select')];
    if (!url) return Promise.resolve();
    var selected = select.getAttribute('data-selected') || '';
    var placeholder = select.getAttribute('data-placeholder') || '';

    return fetchAll(url).then(function (list) {
      var options = '<option value=""' + (selected ? '' : ' selected disabled') + '>' +
        escapeHtml(placeholder) + '</option>';
      var found = false;
      for (var i = 0; i < list.length; i++) {
        var item = list[i] || {};
        var id = String(item.id !== undefined ? item.id : (item.value !== undefined ? item.value : ''));
        if (id === '') continue;
        var title = String(item.title !== undefined ? item.title : (item.label !== undefined ? item.label : id));
        if (id === selected) found = true;
        options += '<option value="' + escapeHtml(id) + '"' + (id === selected ? ' selected' : '') + '>' +
          escapeHtml(title) + '</option>';
      }
      if (selected && !found) {
        options += '<option value="' + escapeHtml(selected) + '" selected>' + escapeHtml(selected) + '</option>';
      }
      select.innerHTML = options;
    }).catch(function (err) {
      console.error('[article-form] failed to load', url, err);
      notify(t('errors.common.list_load_failed'));
    });
  }

  /* ============================================================
     ОБЛОЖКА
     ============================================================ */
  function initCover() {
    var input = $('[data-cover-input]');
    var preview = $('[data-cover-preview]');
    var placeholder = $('[data-cover-placeholder]');
    var removeBtn = $('[data-cover-remove]');
    if (!input || !preview) return null;
    var objectUrl = null;

    function clearPreview() {
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
      preview.removeAttribute('src');
      preview.hidden = true;
      if (placeholder) placeholder.hidden = false;
      if (removeBtn) removeBtn.hidden = true;
    }

    return function () {
      var file = input.files && input.files[0];
      if (!file) { clearPreview(); return null; }
      if (COVER_MIMES.indexOf(file.type) === -1) {
        clearPreview(); input.value = '';
        return t('errors.article.rule_article_cover');
      }
      if (file.size > LIMITS.coverBytes) {
        clearPreview(); input.value = '';
        return t('errors.article.rule_article_cover_size');
      }
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      preview.src = objectUrl;
      preview.hidden = false;
      if (placeholder) placeholder.hidden = true;
      if (removeBtn) removeBtn.hidden = false;
      return null;
    };
  }

  /* ============================================================
     ОЧИСТКА HTML
     ============================================================ */
  var DROP_TAGS = { SCRIPT: 1, STYLE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, NOSCRIPT: 1,
                    TEMPLATE: 1, TEXTAREA: 1, SELECT: 1, INPUT: 1, BUTTON: 1, SVG: 1, CANVAS: 1,
                    HEAD: 1, TITLE: 1, META: 1, LINK: 1 };
  var TAG_MAP = {
    P: 'p', DIV: 'p', SECTION: 'p', ARTICLE: 'p', PRE: 'p', FIGURE: 'p',
    H1: 'h2', H2: 'h2', H3: 'h3', H4: 'h3', H5: 'h3', H6: 'h3',
    B: 'strong', STRONG: 'strong', I: 'em', EM: 'em',
    BLOCKQUOTE: 'blockquote', UL: 'ul', OL: 'ol', LI: 'li', BR: 'br', A: 'a',
    IMG: 'img',
  };
  var INLINE = { strong: 1, em: 1, a: 1, br: 1, img: 1 };

  function safeHref(href) {
    href = String(href || '').trim();
    return /^(https?:|mailto:)/i.test(href) ? href : '';
  }

  function safeImgSrc(src) {
    src = String(src || '').trim();
    if (src.indexOf('..') !== -1) return '';
    return IMG_SRC_RE.test(src) ? src : '';
  }

  function sanitizeHtml(html) {
    var doc = new DOMParser().parseFromString('<body>' + String(html || '') + '</body>', 'text/html');
    var out = document.createElement('div');

    function walk(src, dst, ctx) {
      var nodes = src.childNodes;
      for (var i = 0; i < nodes.length; i++) {
        var n = nodes[i];
        if (n.nodeType === 3) { dst.appendChild(document.createTextNode(n.nodeValue)); continue; }
        if (n.nodeType !== 1) continue;

        var tag = n.tagName.toUpperCase();
        if (DROP_TAGS[tag]) continue;

        var mapped = TAG_MAP[tag];
        if (!mapped) { walk(n, dst, ctx); continue; }

        if (mapped === 'br') { dst.appendChild(document.createElement('br')); continue; }

        if (mapped === 'a') {
          var href = safeHref(n.getAttribute('href'));
          if (!href) { walk(n, dst, ctx); continue; }
          var link = document.createElement('a');
          link.setAttribute('href', href);
          link.setAttribute('rel', 'noopener nofollow ugc');
          link.setAttribute('target', '_blank');
          walk(n, link, ctx);
          dst.appendChild(link);
          continue;
        }

        if (mapped === 'img') {
          var src2 = safeImgSrc(n.getAttribute('src'));
          if (!src2) continue;
          var img = document.createElement('img');
          img.setAttribute('src', src2);
          var alt = n.getAttribute('alt');
          if (alt) img.setAttribute('alt', alt);
          ['width', 'height'].forEach(function (attr) {
            var v = parseInt(n.getAttribute(attr), 10);
            if (v > 0 && v < 4000) img.setAttribute(attr, String(v));
          });
          dst.appendChild(img);
          continue;
        }

        if ((mapped === 'p' || mapped === 'h2' || mapped === 'h3') && (ctx.inP || ctx.inHeading)) {
          walk(n, dst, ctx);
          continue;
        }

        var el = document.createElement(mapped);
        var childCtx = {
          inP: ctx.inP || mapped === 'p',
          inHeading: ctx.inHeading || mapped === 'h2' || mapped === 'h3',
        };
        walk(n, el, childCtx);
        dst.appendChild(el);
      }
    }

    walk(doc.body, out, { inP: false, inHeading: false });
    wrapInlineRuns(out);
    pruneEmpty(out);
    return out.innerHTML;
  }

  function wrapInlineRuns(container) {
    var run = null;
    var nodes = Array.prototype.slice.call(container.childNodes);
    nodes.forEach(function (n) {
      var isInline = n.nodeType === 3 || (n.nodeType === 1 && INLINE[n.tagName.toLowerCase()]);
      if (!isInline) { run = null; return; }
      if (n.nodeType === 3 && !n.nodeValue.trim() && !run) { container.removeChild(n); return; }
      if (!run) { run = document.createElement('p'); container.insertBefore(run, n); }
      run.appendChild(n);
    });
  }

  function pruneEmpty(container) {
    var BLOCKS = 'p,h2,h3,blockquote,ul,ol,li,strong,em';
    var changed = true;
    while (changed) {
      changed = false;
      var els = container.querySelectorAll(BLOCKS);
      for (var i = els.length - 1; i >= 0; i--) {
        var el = els[i];
        if (!el.textContent.trim() && !el.querySelector('br,a,img')) { el.remove(); changed = true; }
      }
    }
  }

  function plainTextToHtml(text) {
    var paras = String(text || '').replace(/\r\n?/g, '\n').split(/\n{2,}/);
    var out = '';
    paras.forEach(function (p) {
      p = p.trim();
      if (p) out += '<p>' + escapeHtml(p).replace(/\n/g, '<br>') + '</p>';
    });
    return out;
  }

  /* ============================================================
     РЕДАКТОР
     ============================================================ */
  var BLOCK_TAG = { h2: 'H2', h3: 'H3', quote: 'BLOCKQUOTE' };

  function initEditor() {
    var area = $('[data-editor]');
    var field = $('[name="content"]');
    var toolbar = $('[data-editor-toolbar]');
    var counter = $('[data-editor-counter]');
    if (!area || !field) return null;

    var editorRoot = area.closest('.rich-editor') || form;
    var imageInput = editorRoot.querySelector('[data-image-input]');

    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (_) {}

    var syncTimer = 0;

    function textLength() { return (area.textContent || '').replace(/\s+/g, ' ').trim().length; }
    function isEmpty() { return textLength() === 0; }

    function updateCounter() {
      if (counter) counter.textContent = t('articleform.counter', { count: textLength() });
    }

    function sync() {
      clearTimeout(syncTimer);
      field.value = isEmpty() ? '' : sanitizeHtml(area.innerHTML);
      updateCounter();
    }

    function scheduleSync() {
      clearTimeout(syncTimer);
      syncTimer = setTimeout(sync, 300);
    }

    function currentBlock() {
      var sel = window.getSelection();
      var node = sel && sel.anchorNode;
      while (node && node !== area) {
        if (node.nodeType === 1 && /^(H2|H3|BLOCKQUOTE)$/.test(node.tagName)) return node.tagName;
        node = node.parentNode;
      }
      return 'P';
    }

    function updateToolbar() {
      var sel = window.getSelection();
      if (!toolbar || !sel || !sel.rangeCount || !area.contains(sel.anchorNode)) return;
      $$('[data-cmd]', toolbar).forEach(function (btn) {
        var cmd = btn.getAttribute('data-cmd');
        var active = false;
        try {
          if (cmd === 'bold' || cmd === 'italic' || cmd === 'insertUnorderedList' || cmd === 'insertOrderedList') {
            active = document.queryCommandState(cmd);
          } else if (BLOCK_TAG[cmd]) {
            active = currentBlock() === BLOCK_TAG[cmd];
          }
        } catch (_) {}
        btn.classList.toggle('is-active', !!active);
      });
    }

    function makeLink() {
      var sel = window.getSelection();
      if (sel && sel.isCollapsed) { document.execCommand('unlink'); return; }
      var url = window.prompt(t('articleform.link_prompt'), 'https://');
      if (url === null) return;
      url = safeHref(url);
      if (!url) { document.execCommand('unlink'); return; }
      document.execCommand('createLink', false, url);
    }

    function insertNodeAtCursor(node) {
      var sel = window.getSelection();
      if (!sel || sel.rangeCount === 0 || !area.contains(sel.anchorNode)) {
        area.appendChild(node);
        return;
      }
      var range = sel.getRangeAt(0);
      range.deleteContents();
      range.insertNode(node);
      var after = document.createRange();
      after.setStartAfter(node);
      after.collapse(true);
      sel.removeAllRanges();
      sel.addRange(after);
    }

    function uploadImage(file) {
      var fd = new FormData();
      fd.append('image', file);
      return csrfFetch(IMAGE_UPLOAD_URL, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      }).then(function (res) {
        return res.text().then(function (raw) {
          var data = null;
          try { data = raw ? JSON.parse(raw) : null; } catch (_) {}
          if (res.status === 403) {
            if (window.Messages) window.Messages.show(t('errors.common.forbidden'), { type: 'error' });
            throw new Error('Forbidden');
          }
          if (!res.ok) throw new Error('HTTP ' + res.status);
          var payload = (data && data.data) || data || {};
          var src = payload.url || payload.src || '';
          src = safeImgSrc(src);
          if (!src) throw new Error('bad url');
          return src;
        });
      });
    }

    function handleImageFiles(files) {
      Array.prototype.slice.call(files || []).forEach(function (file) {
        if (IMAGE_MIMES.indexOf(file.type) === -1) { notify(t('errors.article.article_image')); return; }
        if (file.size > LIMITS.imageBytes) { notify(t('errors.article.article_image_size')); return; }

        var ph = document.createElement('span');
        ph.className = 'rich-editor__upload';
        ph.textContent = t('common.loading');
        ph.contentEditable = 'false';

        area.focus();
        insertNodeAtCursor(ph);
        scheduleSync();

        uploadImage(file).then(function (src) {
          var img = document.createElement('img');
          img.setAttribute('src', src);
          img.setAttribute('alt', '');
          if (ph.parentNode) ph.parentNode.replaceChild(img, ph);
          else area.appendChild(img);
          sync();
          markDirty();
        }).catch(function (err) {
          console.error('[article-form] image upload failed', err);
          notify(t('errors.article.article_image_failed'));
          if (ph.parentNode) ph.parentNode.removeChild(ph);
          scheduleSync();
        });
      });
    }

    function openImageDialog() {
      if (!imageInput) return;
      imageInput.value = '';
      imageInput.click();
    }

    function exec(cmd) {
      area.focus();
      if (BLOCK_TAG[cmd]) {
        var tag = BLOCK_TAG[cmd];
        document.execCommand('formatBlock', false, currentBlock() === tag ? '<P>' : '<' + tag + '>');
      } else if (cmd === 'link') {
        makeLink();
      } else if (cmd === 'image') {
        openImageDialog();
      } else if (cmd === 'clear') {
        document.execCommand('removeFormat');
        document.execCommand('formatBlock', false, '<P>');
      } else {
        document.execCommand(cmd);
      }
      sync();
      updateToolbar();
      markDirty();
    }

    if (toolbar) {
      toolbar.addEventListener('mousedown', function (e) {
        if (e.target.closest('[data-cmd]')) e.preventDefault();
      });
      toolbar.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-cmd]');
        if (btn) exec(btn.getAttribute('data-cmd'));
      });
    }

    document.addEventListener('selectionchange', updateToolbar);

    area.addEventListener('input', function () { scheduleSync(); markDirty(); });
    area.addEventListener('blur', sync);

    area.addEventListener('paste', function (e) {
      var cd = e.clipboardData;
      if (!cd) return;
      e.preventDefault();
      var html = cd.getData('text/html');
      var out = html ? sanitizeHtml(html) : plainTextToHtml(cd.getData('text/plain'));
      if (out) document.execCommand('insertHTML', false, out);
      scheduleSync();
      markDirty();
    });

    area.addEventListener('drop', function (e) { e.preventDefault(); });

    area.addEventListener('click', function (e) {
      var img = e.target && e.target.tagName === 'IMG' ? e.target : null;
      $$('img.is-selected', area).forEach(function (x) { if (x !== img) x.classList.remove('is-selected'); });
      if (img) img.classList.add('is-selected');
    });

    area.addEventListener('keydown', function (e) {
      if (e.key !== 'Backspace' && e.key !== 'Delete') return;
      var selected = area.querySelector('img.is-selected');
      if (!selected) return;
      e.preventDefault();
      if (selected.parentNode) selected.parentNode.removeChild(selected);
      sync();
      markDirty();
    });

    if (imageInput) {
      imageInput.addEventListener('change', function () {
        var files = imageInput.files;
        if (files && files.length) handleImageFiles(files);
        imageInput.value = '';
      });
    }

    function setHtml(html) {
      area.innerHTML = sanitizeHtml(html);
      sync();
    }

    area.innerHTML = sanitizeHtml(field.value);
    updateCounter();

    return {
      area: area,
      getHtml: function () { sync(); return field.value; },
      setHtml: setHtml,
      isEmpty: isEmpty,
      textLength: textLength,
      sync: sync,
    };
  }

  /* ============================================================
     ВЫБОР КНИГИ
     ============================================================ */
  function creatorName(item) {
    var c = item && (item.creator || item.author);
    if (!c) return '';
    var name = [c.name, c.surname].filter(Boolean).join(' ').trim();
    return name || c.username || '';
  }

  function initBookPicker() {
    var search = $('[data-book-search]');
    var results = $('[data-book-results]');
    var chip = $('[data-book-selected]');
    var chipTitle = $('[data-book-selected-title]');
    var changeBtn = $('[data-book-change]');
    var field = fieldByName('book');
    var openBtn = $('[data-open-picker]');
    if (!search || !results || !field) return null;

    var token = 0;

    function showChip() {
      var has = !!state.book;
      if (chip) chip.hidden = !has;
      if (chipTitle) chipTitle.textContent = has ? state.book.title : '';
      search.hidden = has;
      results.hidden = true;
      if (openBtn) openBtn.disabled = !has;
    }

    function choose(book) {
      var changed = !state.book || String(state.book.id) !== String(book.id);
      state.book = book;
      field.value = String(book.id);
      if (changed) {
        state.start = null;
        state.end = null;
        writeAnchor('start');
        writeAnchor('end');
        renderSummary();
      }
      showChip();
      clearErrorFor('book');
      markDirty();
    }

    function clear() {
      state.book = null;
      field.value = '';
      state.start = null; state.end = null;
      writeAnchor('start'); writeAnchor('end');
      renderSummary();
      showChip();
      search.value = '';
      search.focus();
      markDirty();
    }

    function render(items, query) {
      if (!items.length) {
        results.innerHTML = '<li class="book-picker__empty">' + escapeHtml(t('articleform.book_none')) + '</li>';
      } else {
        results.innerHTML = items.map(function (it) {
          var author = creatorName(it);
          return '<li><button type="button" class="book-picker__item" data-pick-id="' + escapeHtml(it.id) +
            '" data-pick-title="' + escapeHtml(it.title) + '">' +
            '<span class="book-picker__title">' + escapeHtml(it.title) + '</span>' +
            (author ? '<span class="book-picker__author">' + escapeHtml(author) + '</span>' : '') +
            '</button></li>';
        }).join('');
      }
      results.hidden = false;
    }

    var load = debounce(function (query) {
      var my = ++token;
      results.innerHTML = '<li class="book-picker__empty">' + escapeHtml(t('articleform.book_loading')) + '</li>';
      results.hidden = false;
      var url = '/api/books?include=creator&page=1' + (query ? '&q=' + encodeURIComponent(query) : '');
      fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (res) {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.json();
        })
        .then(function (data) {
          if (my !== token) return;
          render(Array.isArray(data.items) ? data.items : [], query);
        })
        .catch(function (err) {
          if (my !== token) return;
          console.error('[article-form] books load failed', err);
          results.hidden = true;
          notify(t('errors.article.article_books_failed'));
        });
    }, 300);

    search.addEventListener('focus', function () { load(search.value.trim()); });
    search.addEventListener('input', function () { load(search.value.trim()); });

    results.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-pick-id]');
      if (!btn) return;
      choose({ id: parseInt(btn.getAttribute('data-pick-id'), 10), title: btn.getAttribute('data-pick-title') || '' });
    });

    document.addEventListener('click', function (e) {
      if (!results.hidden && !e.target.closest('[data-book-box]')) results.hidden = true;
    });

    if (changeBtn) changeBtn.addEventListener('click', clear);

    var initialId = parseInt(field.value, 10);
    if (initialId > 0) {
      state.book = { id: initialId, title: form.getAttribute('data-book-title') || ('#' + initialId) };
    }
    showChip();

    return { choose: choose };
  }

  /* ============================================================
     ВЫБОР ФРАГМЕНТА В КНИГЕ (PDF.js)
     ============================================================ */
  var pdfjsPromise = null;
  function loadPdfjs() {
    if (!pdfjsPromise) {
      pdfjsPromise = import(PDF_BASE + 'pdf.min.js').then(function (m) {
        m.GlobalWorkerOptions.workerSrc = PDF_BASE + 'pdf.worker.min.js';
        return m;
      });
    }
    return pdfjsPromise;
  }

  function initFragmentPicker() {
    var root = $('[data-picker]');
    var openBtn = $('[data-open-picker]');
    if (!root || !openBtn || !Text) return null;

    var stage = $('[data-picker-stage]', root);
    var pageBox = $('[data-picker-page]', root);
    var pageInput = $('[data-picker-pagenum]', root);
    var totalEl = $('[data-picker-total]', root);
    var zoomLbl = $('[data-picker-zoomlabel]', root);
    var busyEl = $('[data-picker-busy]', root);
    var startEl = $('[data-picker-start]', root);
    var endEl = $('[data-picker-end]', root);
    var applyBtn = $('[data-picker-apply]', root);
    var insertChk = $('[data-picker-insert]', root);
    var titleEl = $('[data-picker-book]', root);

    var pdfjs = null;
    var pdf = null;
    var pdfUrl = '';
    var infoCache = {};
    var pageNum = 1;
    var zoom = 1;
    var token = 0;
    var curTask = null;
    var curTextLayer = null;
    var cur = null;
    var draft = { start: null, end: null };
    var lastFocus = null;

    function setBusy(text) {
      if (!busyEl) return;
      busyEl.textContent = text || '';
      busyEl.hidden = !text;
    }

    function paintStatus() {
      var s = draft.start;
      var e = draft.end;
      if (startEl) {
        startEl.textContent = s
          ? t('articleform.picker_start_state', { page: s.page, line: s.line, char: s.char })
          : t('articleform.picker_start_unset');
        startEl.title = s && s.quote ? '«' + s.quote + '…»' : '';
      }
      if (endEl) {
        endEl.textContent = e
          ? t('articleform.picker_end_state', { page: e.page, line: e.line, char: e.char })
          : t('articleform.picker_end_unset');
        endEl.title = e && e.quote ? '«…' + e.quote + '»' : '';
      }
      if (applyBtn) applyBtn.disabled = !(s && e);
    }

    async function ensureDoc() {
      var url = DEV_SRC || ('/api/books/' + state.book.id + '/file');
      if (pdf && pdfUrl === url) return;

      pdfjs = await loadPdfjs();
      var res = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/pdf, application/json' },
      });
      if (!res.ok) {
        if (window.Messages) await window.Messages.fail(res, t('errors.article.article_pdf_failed'));
        throw new Error('HTTP ' + res.status);
      }
      var data = await res.arrayBuffer();
      if (pdf) { try { pdf.destroy(); } catch (_) {} }
      pdf = await pdfjs.getDocument({
        data: data,
        cMapUrl: PDF_BASE + 'cmaps/',
        cMapPacked: true,
        standardFontDataUrl: PDF_BASE + 'standard_fonts/',
      }).promise;
      pdfUrl = url;
      infoCache = {};
      if (totalEl) totalEl.textContent = String(pdf.numPages);
      if (pageInput) pageInput.max = String(pdf.numPages);
    }

    async function getInfo(n) {
      if (infoCache[n]) return infoCache[n];
      var page = await pdf.getPage(n);
      var content = await page.getTextContent();
      var built = Text.buildPageText(Text.textItems(content));
      infoCache[n] = { text: built.text, starts: built.starts, content: content };
      return infoCache[n];
    }

    async function showPage(n) {
      n = clamp(n, 1, pdf.numPages);
      var my = ++token;
      if (curTask) { try { curTask.cancel(); } catch (_) {} }
      if (curTextLayer) { try { curTextLayer.cancel(); } catch (_) {} }

      var page = await pdf.getPage(n);
      var info = await getInfo(n);
      if (my !== token) return;

      var base = Math.max(stage.clientWidth - 32, 200) / page.getViewport({ scale: 1 }).width;
      var viewport = page.getViewport({ scale: base * zoom });
      var dpr = Math.min(window.devicePixelRatio || 1, 2);

      var canvas = document.createElement('canvas');
      canvas.width = Math.floor(viewport.width * dpr);
      canvas.height = Math.floor(viewport.height * dpr);
      canvas.style.width = Math.floor(viewport.width) + 'px';
      canvas.style.height = Math.floor(viewport.height) + 'px';

      var tdiv = document.createElement('div');
      tdiv.className = 'textLayer';

      var sheet = document.createElement('div');
      sheet.className = 'fragment-picker__sheet';
      sheet.style.width = Math.floor(viewport.width) + 'px';
      sheet.style.height = Math.floor(viewport.height) + 'px';
      sheet.style.setProperty('--scale-factor', String(viewport.scale));
      sheet.appendChild(canvas);
      sheet.appendChild(tdiv);

      var task = page.render({
        canvasContext: canvas.getContext('2d'),
        viewport: viewport,
        transform: dpr !== 1 ? [dpr, 0, 0, dpr, 0, 0] : null,
      });
      curTask = task;
      var textLayer = new pdfjs.TextLayer({
        textContentSource: info.content,
        container: tdiv,
        viewport: viewport,
      });
      curTextLayer = textLayer;

      try {
        await Promise.all([task.promise, textLayer.render()]);
      } catch (e) {
        if (my !== token) return;
        if (e && (e.name === 'RenderingCancelledException' || e.name === 'AbortException')) return;
        throw e;
      }
      if (my !== token) return;

      pageBox.replaceChildren(sheet);
      var spans = textLayer.textDivs || [];
      var spanIndex = new Map();
      for (var i = 0; i < spans.length; i++) spanIndex.set(spans[i], i);
      cur = { page: n, spans: spans, starts: info.starts, text: info.text, spanIndex: spanIndex };

      pageNum = n;
      if (pageInput) pageInput.value = String(n);
      if (zoomLbl) zoomLbl.textContent = Math.round(zoom * 100) + '%';
      paintHighlight();
      stage.scrollTop = 0;

      if (!info.text.trim()) notify(t('errors.article.article_no_text_layer'), 'warning');
    }

    function spanOf(node) {
      while (node && node !== pageBox) {
        if (cur.spanIndex.has(node)) return node;
        node = node.parentNode;
      }
      return null;
    }

    function offsetFromPoint(node, off) {
      var span = null;
      var within = 0;

      if (node.nodeType === 3) {
        span = spanOf(node);
        within = off;
      } else if (node.nodeType === 1) {
        span = spanOf(node);
        if (span) {
          within = off === 0 ? 0 : (span.textContent || '').length;
        } else {
          var child = node.childNodes[off] || null;
          if (child) {
            span = spanOf(child);
            if (!span && child.querySelector) {
              var inner = child.querySelector('span');
              span = inner && cur.spanIndex.has(inner) ? inner : null;
            }
            within = 0;
          } else {
            var spans = cur.spans;
            span = spans.length ? spans[spans.length - 1] : null;
            within = span ? (span.textContent || '').length : 0;
          }
        }
      }
      if (!span) return null;
      var idx = cur.spanIndex.get(span);
      return cur.starts[idx] + clamp(within, 0, (span.textContent || '').length);
    }

    function selectionOffset(which) {
      var sel = window.getSelection();
      if (!cur || !sel || sel.rangeCount === 0) return null;
      var range = sel.getRangeAt(0);
      if (!pageBox.contains(range.commonAncestorContainer)) return null;
      return which === 'start'
        ? offsetFromPoint(range.startContainer, range.startOffset)
        : offsetFromPoint(range.endContainer, range.endOffset);
    }

    function setPoint(which) {
      if (!cur) return;
      var off = selectionOffset(which);
      if (off === null) { notify(t('errors.article.article_selection_missing')); return; }
      draft[which] = Text.makeAnchor(cur.text, off, cur.page, which);
      paintStatus();
      paintHighlight();
    }

    function paintHighlight() {
      if (!cur) return;
      var s = draft.start;
      var e = draft.end;
      var from = -1;
      var to = -1;
      if (s && e && Text.compareAnchors(s, e) < 0 && cur.page >= s.page && cur.page <= e.page) {
        from = cur.page === s.page ? s.offset : 0;
        to = cur.page === e.page ? e.offset : cur.text.length;
      }
      for (var i = 0; i < cur.spans.length; i++) {
        var a = cur.starts[i];
        var b = a + (cur.spans[i].textContent || '').length;
        cur.spans[i].classList.toggle('is-picked', from >= 0 && b > from && a < to);
      }
    }

    function onKey(e) {
      if (root.hidden) return;
      if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); return; }
      if (e.target.closest && e.target.closest('input, textarea')) return;
      if (e.key === 'ArrowLeft'  || e.key === 'PageUp')   goTo(pageNum - 1);
      if (e.key === 'ArrowRight' || e.key === 'PageDown') goTo(pageNum + 1);
    }

    async function open() {
      if (!state.book) { notify(t('errors.article.rule_article_book')); return; }
      lastFocus = document.activeElement;
      draft = { start: state.start, end: state.end };
      if (titleEl) titleEl.textContent = state.book.title;
      root.hidden = false;
      document.body.classList.add('is-modal-open');
      document.addEventListener('keydown', onKey, true);
      paintStatus();
      setBusy(t('articleform.picker_loading'));
      try {
        await ensureDoc();
        zoom = 1;
        await showPage(draft.start ? draft.start.page : 1);
        setBusy('');
        var closeBtn = $('[data-picker-close]', root);
        if (closeBtn) closeBtn.focus();
      } catch (err) {
        console.error('[article-form] picker open failed', err);
        close();
        if (!(err && /^HTTP /.test(err.message || ''))) notify(t('errors.article.article_pdf_failed'));
      }
    }

    function close() {
      root.hidden = true;
      document.body.classList.remove('is-modal-open');
      document.removeEventListener('keydown', onKey, true);
      token++;
      if (curTask) { try { curTask.cancel(); } catch (_) {} }
      setBusy('');
      if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (_) {} }
    }

    function goTo(n) {
      if (!pdf) return;
      n = clamp(parseInt(n, 10) || 1, 1, pdf.numPages);
      showPage(n).catch(function (err) {
        console.error('[article-form] page failed', err);
        notify(t('errors.article.article_extract_failed'));
      });
    }

    function setZoom(z) {
      zoom = clamp(z, 0.5, 3);
      goTo(pageNum);
    }

    async function apply() {
      var s = draft.start;
      var e = draft.end;
      if (!s || !e) { notify(t('errors.article.rule_article_fragment')); return; }
      if (Text.compareAnchors(s, e) >= 0) { notify(t('errors.article.rule_article_fragment_order')); return; }
      if (e.page - s.page + 1 > LIMITS.excerptPages) {
        notify(t('errors.article.rule_article_fragment_long', { max: LIMITS.excerptPages }));
        return;
      }

      var html = '';
      var wantText = !!(insertChk && insertChk.checked);
      if (wantText) {
        setBusy(t('articleform.picker_reading'));
        if (applyBtn) applyBtn.disabled = true;
        try {
          var texts = {};
          for (var p = s.page; p <= e.page; p++) texts[p] = (await getInfo(p)).text;
          html = Text.paragraphsToHtml(Text.toParagraphs(Text.sliceFragment(texts, s, e)));
        } catch (err) {
          console.error('[article-form] extract failed', err);
          notify(t('errors.article.article_extract_failed'));
          setBusy('');
          paintStatus();
          return;
        }
        setBusy('');
        if (!html) notify(t('errors.article.article_no_text_layer'), 'warning');
      }

      state.start = s;
      state.end = e;
      writeAnchor('start');
      writeAnchor('end');
      renderSummary();
      clearErrorFor('fragment');
      markDirty();
      close();

      if (html) insertFragmentText(html);
    }

    function insertFragmentText(html) {
      if (!editor) return;
      if (editor.isEmpty() || !window.ConfirmModal) {
        if (editor.isEmpty()) { editor.setHtml(html); markDirty(); return; }
      }
      window.ConfirmModal.confirm({
        title: t('articleform.insert_confirm_title'),
        message: t('articleform.insert_confirm_text'),
        confirmText: t('articleform.insert_confirm_ok'),
        danger: true,
      }).then(function (ok) {
        if (ok) { editor.setHtml(html); markDirty(); }
      });
    }

    openBtn.addEventListener('click', open);

    root.addEventListener('mousedown', function (e) {
      if (e.target.closest('[data-picker-set]')) e.preventDefault();
    });

    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-picker-close]') || e.target.closest('[data-picker-backdrop]')) { close(); return; }
      if (e.target.closest('[data-picker-prev]')) { goTo(pageNum - 1); return; }
      if (e.target.closest('[data-picker-next]')) { goTo(pageNum + 1); return; }
      if (e.target.closest('[data-picker-zoom-in]')) { setZoom(zoom + 0.25); return; }
      if (e.target.closest('[data-picker-zoom-out]')) { setZoom(zoom - 0.25); return; }
      var set = e.target.closest('[data-picker-set]');
      if (set) { setPoint(set.getAttribute('data-picker-set')); return; }
      if (e.target.closest('[data-picker-apply]')) { apply(); }
    });

    if (pageInput) pageInput.addEventListener('change', function () { goTo(pageInput.value); });

    var resizeTimer;
    window.addEventListener('resize', function () {
      if (root.hidden || !pdf) return;
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () { goTo(pageNum); }, 200);
    });

    return { open: open, close: close };
  }

  /* ============================================================
     ВАЛИДАЦИЯ И ОТПРАВКА
     ============================================================ */
  var FIELD_KEYS = ['title', 'genre', 'type', 'doi', 'annotation', 'author_note',
                    'content', 'cover', 'book', 'fragment'];

  function invalidTarget(name) {
    if (name === 'content') return $('[data-editor]') || fieldByName('content');
    if (name === 'fragment') return $('[data-fragment-summary]');
    if (name === 'book') return $('[data-book-search]') || $('[data-book-selected]');
    return fieldByName(name);
  }

  function errorEl(name) { return $('[data-error-for="' + name + '"]'); }

  function setError(name, message) {
    var target = invalidTarget(name);
    var box = errorEl(name);
    if (target) target.classList.add('form-field__input--invalid');
    if (box) box.innerHTML = '<span class="field-error-icon" title="' + escapeHtml(message) + '">!</span>';
  }

  function clearErrorFor(name) {
    var target = invalidTarget(name);
    var box = errorEl(name);
    if (target) target.classList.remove('form-field__input--invalid');
    if (box) box.innerHTML = '';
  }

  function clearAllErrors() { FIELD_KEYS.forEach(clearErrorFor); }

  function valueOf(name) {
    var el = fieldByName(name);
    return el ? String(el.value || '').trim() : '';
  }

  function validateField(name, isDraft) {
    var v;
    switch (name) {
      case 'title':
        v = valueOf('title');
        if (v === '' || v.length > LIMITS.title) { setError(name, t('errors.article.rule_article_title')); return false; }
        break;

      case 'genre':
        if (!isDraft && valueOf('genre') === '') { setError(name, t('errors.article.rule_article_genre')); return false; }
        break;

      case 'type':
        if (!isDraft && valueOf('type') === '') { setError(name, t('errors.article.rule_article_type')); return false; }
        break;

      case 'doi':
        v = valueOf('doi');
        if (v !== '' && !DOI_RE.test(v)) { setError(name, t('errors.article.rule_article_doi')); return false; }
        break;

      case 'annotation':
        if (valueOf('annotation').length > LIMITS.annotation) { setError(name, t('errors.article.rule_article_annotation')); return false; }
        break;

      case 'author_note':
        if (valueOf('author_note').length > LIMITS.authorNote) { setError(name, t('errors.article.rule_article_author_note')); return false; }
        break;

      case 'content':
        if (!editor) break;
        // В режиме «отрывок из книги» редактор скрыт; текст статьи необязателен —
        // содержимое (при галочке «Вставить текст») приходит из фрагмента PDF.
        if (!isDraft && state.mode !== 'excerpt' && editor.isEmpty()) {
          setError(name, t('errors.article.rule_article_content'));
          return false;
        }
        if (editor.getHtml().length > LIMITS.contentHtml) {
          setError(name, t('errors.article.rule_article_content_long'));
          return false;
        }
        break;

      case 'cover':
        var coverError = coverValidator ? coverValidator() : null;
        if (coverError) { setError(name, coverError); return false; }
        break;

      case 'book':
        if (!isDraft && state.mode === 'excerpt' && !state.book) { setError(name, t('errors.article.rule_article_book')); return false; }
        break;

      case 'fragment':
        if (state.mode !== 'excerpt' || isDraft) break;
        if (!state.start || !state.end) { setError(name, t('errors.article.rule_article_fragment')); return false; }
        if (Text && Text.compareAnchors(state.start, state.end) >= 0) { setError(name, t('errors.article.rule_article_fragment_order')); return false; }
        if (state.end.page - state.start.page + 1 > LIMITS.excerptPages) {
          setError(name, t('errors.article.rule_article_fragment_long', { max: LIMITS.excerptPages }));
          return false;
        }
        break;
    }
    clearErrorFor(name);
    return true;
  }

  function validateAll(isDraft) {
    var ok = true;
    FIELD_KEYS.forEach(function (name) {
      if (name === 'book' || name === 'fragment') {
        if (state.mode !== 'excerpt') { clearErrorFor(name); return; }
      }
      if (!validateField(name, isDraft)) ok = false;
    });
    return ok;
  }

  function focusFirstInvalid() {
    var el = $('.form-field__input--invalid');
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    if (el.focus) { try { el.focus({ preventScroll: true }); } catch (_) { el.focus(); } }
  }

  /** Есть ли на шаге n поля с ошибками (ошибки лежат в [data-error-for]). */
  function stepHasError(n) {
    var step = $('.article-step[data-step="' + n + '"]');
    return !!(step && step.querySelector('[data-error-for] .field-error-icon'));
  }

  /**
   * Возвращаем пользователя на шаг, где что-то не так. Если ошибки и на первом, и на
   * втором — сначала первый; на второй пользователь перейдёт сам.
   */
  function revealInvalidStep() {
    if (!stepsApi) return;
    for (var n = 1; n <= 2; n++) {
      if (stepHasError(n)) {
        if (stepsApi.current() !== n) stepsApi.goTo(n);
        return;
      }
    }
  }

  function showFieldErrors() {
    var list = [];
    FIELD_KEYS.forEach(function (name) {
      var box = errorEl(name);
      var icon = box && box.querySelector('.field-error-icon');
      var text = icon ? icon.getAttribute('title') : '';
      if (text) list.push(text);
    });
    if (list.length) notify(list.join('\n'));
  }

  var SERVER_KEY = {
    page_start: 'fragment', page_end: 'fragment',
    start_offset: 'fragment', end_offset: 'fragment',
    start_line: 'fragment', end_line: 'fragment',
    start_char: 'fragment', end_char: 'fragment',
    start_quote: 'fragment', end_quote: 'fragment',
    description: 'annotation', authorNotes: 'author_note',
  };

  function renderServerErrors(errors) {
    if (!errors || typeof errors !== 'object') return;
    Object.keys(errors).forEach(function (key) {
      var msgs = errors[key];
      var first = Array.isArray(msgs) ? msgs[0] : msgs;
      var message = first && typeof first === 'object' ? (first.message || '') : String(first || '');
      var name = SERVER_KEY[key] || key;
      if (FIELD_KEYS.indexOf(name) !== -1 && message) setError(name, message);
    });
    revealInvalidStep();
    focusFirstInvalid();
  }

  function formatTime(d) {
    function two(n) { return (n < 10 ? '0' : '') + n; }
    return two(d.getHours()) + ':' + two(d.getMinutes());
  }

  function setDraftStatus(text) {
    var el = $('[data-draft-status]');
    if (el) el.textContent = text || '';
  }

  /**
   * kind:
   *   'draft'   — «Create draft»: сохраняем черновик и открываем страницу статьи;
   *   'publish' — «Publish»: сохраняем черновик и просим бэк проверить статью как для
   *               публикации (check_publish=1). Не готова — возвращаем пользователя на
   *               шаг с ошибками; готова — открывается окно выбора прав, а публикует
   *               уже кнопка в окне (POST /api/articles/{id}/publish).
   * silent — автосохранение: без плашек, без перехода и без подсветки ошибок.
   */
  function openRights() {
    if (!window.LoreArticlePublish || !state.articleId) {
      notify(t('errors.common.save_failed', { status: 0 }));
      return;
    }
    window.LoreArticlePublish.open(state.articleId, {
      // бэк при самой публикации нашёл ошибки в статье — показываем их на форме
      onInvalid: function (errors) { renderServerErrors(errors); },
    });
  }

  function save(kind, silent) {
    if (state.saving) return Promise.resolve(false);

    if (editor) editor.sync();
    var forPublish = kind === 'publish';

    if (!silent) {
      clearAllErrors();
      if (!validateAll(!forPublish)) {
        showFieldErrors();
        revealInvalidStep();
        focusFirstInvalid();
        return Promise.resolve(false);
      }
    } else {
      // Автосохранение: минимальные требования, чтобы не слать заведомо
      // неполный черновик и не получать ошибки сервера.
      if (valueOf('title') === '' || valueOf('title').length > LIMITS.title) {
        return Promise.resolve(false);
      }
      if (state.mode === 'excerpt' && !state.book) {
        return Promise.resolve(false);
      }
    }

    state.saving = true;
    var buttons = $$('[data-submit-draft], [data-submit-publish]');
    var labels = buttons.map(function (b) { return b.textContent; });
    if (silent) {
      setDraftStatus(t('articleform.draft_saving'));
    } else {
      buttons.forEach(function (b) { b.disabled = true; });
      var activeBtn = forPublish ? $('[data-submit-publish]') : $('[data-submit-draft]');
      if (activeBtn) activeBtn.textContent = t('js.article_saving');
    }

    function release() {
      state.saving = false;
      if (!silent) buttons.forEach(function (b, i) { b.disabled = false; b.textContent = labels[i]; });
    }

    var body = new FormData(form);
    body.set('status', 'draft');                       // публикует только окно прав
    if (forPublish) body.set('check_publish', '1');
    else body.delete('check_publish');
    if (!state.coverDirty) body.delete('cover');

    return csrfFetch(form.getAttribute('action') || location.href, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (response) {
      return response.text().then(function (raw) {
        var data = null;
        try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
        var ok = response.ok && !/^\s*</.test(raw);

        if (ok) {
          if (response.status === 403) {
           if (window.Messages) window.Messages.show(t('errors.common.forbidden'), { type: 'error' });
           release();
           return false;
          }
          var payload = (data && data.data) || data || {};
          if (payload.id) {
            state.articleId = parseInt(payload.id, 10) || state.articleId;
            if (idField) idField.value = String(state.articleId);
          }
          clearBackup();
          state.dirty = false;
          state.coverDirty = false;
          var removeFlagReset = fieldByName('remove_cover');
          if (removeFlagReset) removeFlagReset.value = '';

          // адрес страницы редактирования: если окно прав закроют, перезагрузка вернёт в форму
          if (payload.edit_url && window.history && history.replaceState) {
            history.replaceState(null, '', withLang(payload.edit_url));
          }

          if (silent) {
            setDraftStatus(t('articleform.draft_saved_at', { time: formatTime(new Date()) }));
            release();
            return true;
          }

          if (forPublish) {
            // статья прошла проверку бэка — выбираем права и публикуем
            release();
            openRights();
            return true;
          }

          // «Create draft»: открываем страницу статьи (там пометка «Черновик» и кнопка «Publish»)
          notify(t('js.article_draft_saved'), 'success');
          window.location.href = withLang(payload.redirect
            || (state.articleId ? '/articles/' + state.articleId : (response.headers.get('Location') || '/articles')));
          return true;
        }

        if ((response.status === 400 || response.status === 422) && data) {
          var errors = data.errors || (data.error && typeof data.error === 'object' ? data.error.details : null);
          if (!silent) renderServerErrors(errors);
        }

        if (silent) {
          setDraftStatus(t('errors.article.article_draft_failed'));
        } else {
          var fallback = t('errors.common.save_failed', { status: response.status });
          if (window.Messages) notify(window.Messages.describe(response.status, data, raw, fallback));
          else notify(fallback);
        }
        release();
        return false;
      });
    }).catch(function (err) {
      console.error('[article-form] save failed', err);
      if (silent) setDraftStatus(t('errors.article.article_draft_failed'));
      else notify(t('errors.common.network_error'));
      release();
      return false;
    });
  }

  /* ============================================================
     РЕЖИМ И ВОССТАНОВЛЕНИЕ
     ============================================================ */
  function syncMode() {
    var checked = $('[name="mode"]:checked');
    state.mode = checked ? checked.value : 'standalone';
    var excerpt = state.mode === 'excerpt';
    $$('[data-excerpt-only]').forEach(function (el) { el.hidden = !excerpt; });
    $$('[data-excerpt-field]').forEach(function (el) { el.disabled = !excerpt; });
    // Блок «Текст статьи» виден только в режиме «свой текст» (см. article-form.php).
    $$('[data-standalone-only]').forEach(function (el) { el.hidden = excerpt; });
    if (!excerpt) { clearErrorFor('book'); clearErrorFor('fragment'); }
    else { clearErrorFor('content'); }
  }

  function applyBackup(b) {
    if (!b) return;
    function set(name, v) { var el = fieldByName(name); if (el && v !== undefined && v !== null) el.value = String(v); }
    set('title', b.title); set('doi', b.doi); set('annotation', b.annotation); set('author_note', b.author_note);
    ['genre', 'type'].forEach(function (name) {
      var el = fieldByName(name);
      if (!el || !b[name]) return;
      el.setAttribute('data-selected', String(b[name]));
      if (!$('option[value="' + b[name] + '"]', el)) {
        var opt = document.createElement('option');
        opt.value = String(b[name]); opt.textContent = String(b[name]);
        el.appendChild(opt);
      }
      el.value = String(b[name]);
    });

    var radio = $('[name="mode"][value="' + (b.mode === 'excerpt' ? 'excerpt' : 'standalone') + '"]');
    if (radio) radio.checked = true;
    syncMode();

    if (b.book && b.book.id) {
      state.book = b.book;
      var bf = fieldByName('book'); if (bf) bf.value = String(b.book.id);
      var chip = $('[data-book-selected]'); var chipTitle = $('[data-book-selected-title]');
      var search = $('[data-book-search]'); var openBtn = $('[data-open-picker]');
      if (chip) chip.hidden = false;
      if (chipTitle) chipTitle.textContent = b.book.title || '';
      if (search) search.hidden = true;
      if (openBtn) openBtn.disabled = false;
    }
    state.start = b.start || null;
    state.end = b.end || null;
    writeAnchor('start'); writeAnchor('end'); renderSummary();

    if (editor) editor.setHtml(b.content || '');
    markDirty();
  }

  function initRestore() {
    var box = $('[data-restore]');
    if (!box) return;
    var b = readBackup();
    if (!b) return;

    var same = (b.title || '') === valueOf('title') &&
      (b.content || '') === (editor ? editor.getHtml() : '') &&
      (b.annotation || '') === valueOf('annotation');
    if (same || (!b.title && !b.content)) { clearBackup(); return; }

    box.hidden = false;
    var apply = $('[data-restore-apply]', box);
    var discard = $('[data-restore-discard]', box);
    if (apply) apply.addEventListener('click', function () { applyBackup(b); box.hidden = true; });
    if (discard) discard.addEventListener('click', function () { clearBackup(); box.hidden = true; });
  }

  /* ============================================================
     МНОГОШАГОВАЯ ФОРМА
     ============================================================ */
  function initSteps() {
    var steps = $$('.article-step');
    if (!steps.length) return { current: function () { return 1; }, goTo: function () {} };

    var total = steps.length;
    var currentStep = 1;

    function showStep(n) {
      n = clamp(n, 1, total);
      steps.forEach(function (step) {
        step.hidden = parseInt(step.getAttribute('data-step'), 10) !== n;
      });
      currentStep = n;
      if (window.history && history.replaceState) {
        history.replaceState(null, '', '#step-' + n);
      }
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(n) {
      if (n === 1) {
        var title = fieldByName('title');
        if (!title || !title.value.trim()) {
          setError('title', t('errors.article.rule_article_title'));
          return false;
        }
        clearErrorFor('title');

        if (state.mode === 'excerpt') {
          if (!state.book) { setError('book', t('errors.article.rule_article_book')); return false; }
          clearErrorFor('book');
          if (!state.start || !state.end) { setError('fragment', t('errors.article.rule_article_fragment')); return false; }
          clearErrorFor('fragment');
        }
        return true;
      }

      if (n === 2) {
        // Текст обязателен только в режиме «свой текст» (см. validateField).
        if (state.mode === 'standalone' && editor && editor.isEmpty()) {
          setError('content', t('errors.article.rule_article_content'));
          return false;
        }
        clearErrorFor('content');
        return true;
      }

      return true;
    }

    $$('[data-step-next]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        if (!validateStep(currentStep)) {
          showFieldErrors();
          focusFirstInvalid();
          return;
        }
        showStep(currentStep + 1);
      });
    });

    $$('[data-step-prev]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        showStep(currentStep - 1);
      });
    });

    var match = location.hash.match(/^#step-(\d+)$/);
    showStep(match ? parseInt(match[1], 10) : 1);

    return {
      current: function () { return currentStep; },
      goTo: showStep,
    };
  }

  /* ============================================================
     ИНИЦИАЛИЗАЦИЯ
     ============================================================ */
  function init() {
    form.classList.add('is-enhanced');
    if (!Text) return;

    stepsApi = initSteps();

    $$('[data-dynamic-select]').forEach(populateSelect);

    editor = initEditor();
    coverValidator = initCover();

    $$('[name="mode"]').forEach(function (r) {
      r.addEventListener('change', function () { syncMode(); markDirty(); });
    });
    syncMode();

    state.start = readAnchor('start');
    state.end = readAnchor('end');
    initBookPicker();
    initFragmentPicker();
    renderSummary();

    ['title', 'doi', 'annotation', 'author_note'].forEach(function (name) {
      var el = fieldByName(name);
      if (!el) return;
      el.addEventListener('blur', function () { validateField(name, true); });
      el.addEventListener('input', function () {
        markDirty();
        if (el.classList.contains('form-field__input--invalid')) validateField(name, true);
      });
    });
    ['genre', 'type'].forEach(function (name) {
      var el = fieldByName(name);
      if (el) el.addEventListener('change', function () { clearErrorFor(name); markDirty(); });
    });

    var coverInput = $('[data-cover-input]');
    var removeCoverFlag = fieldByName('remove_cover');
    if (coverInput) {
      coverInput.addEventListener('change', function () {
        // Новый файл — значит пользователь передумал удалять обложку.
        if (removeCoverFlag) removeCoverFlag.value = '';
        state.coverDirty = true;
        validateField('cover', true);
        markDirty();
      });
    }
    var coverRemove = $('[data-cover-remove]');
    if (coverRemove) {
      coverRemove.addEventListener('click', function () {
        if (coverInput) coverInput.value = '';
        // Сообщаем серверу, что обложку нужно снять (в БД старая обложка).
        if (removeCoverFlag) removeCoverFlag.value = '1';
        state.coverDirty = true;
        validateField('cover', true);
        markDirty();
      });
    }

    var initial = form.getAttribute('data-initial-errors');
    if (initial) {
      var texts = [];
      initial.split(',').forEach(function (name) {
        name = name.trim();
        if (!name) return;
        var key = SERVER_KEY[name] || name;
        var box = errorEl(key);
        var icon = box && box.querySelector('.field-error-icon');
        var text = icon ? (icon.getAttribute('title') || '').trim() : '';
        if (text) { texts.push(text); setError(key, text); }
      });
      if (texts.length) notify(texts.join('\n'));
    }

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var sub = e.submitter;
      save(sub && sub.value === 'draft' ? 'draft' : 'publish', false);
    });

    setInterval(function () {
      if (state.dirty && !state.saving) save('draft', true);
    }, AUTOSAVE_MS);

    window.addEventListener('beforeunload', function (e) {
      if (!state.dirty) return;
      writeBackup();
      e.preventDefault();
      e.returnValue = '';
    });

    initRestore();
  }

  init();
})();