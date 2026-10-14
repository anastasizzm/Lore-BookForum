/**
 * book-form.js — страница создания книги (src/views/pages/book/book-form.php).
 *
 * Что делает:
 *   1. превью обложки (object URL) + кнопка «убрать»;
 *   2. списки авторов (до 30) и редакторов (без лимита): поиск людей
 *      по нику через заглушку /api/users/search, добавление в список
 *      под поиском, удаление минусом, кнопка «сделать редактором»
 *      у автора (дублирует его в редакторы, автором он остаётся);
 *   3. список выбранных файлов книги (pdf/epub/mobi/fb2, ≥1 файл)
 *      с удалением по минусу;
 *   4. наполнение <select> жанров и категорий из /api/additional/*;
 *   5. клиентскую валидацию (RULES ниже) до отправки;
 *   6. отправку через fetch + FormData (multipart, файлы летят без чтения
 *      в JS). Без JS форма уходит обычным POST — прогрессивное улучшение.
 *
 * Поля создателя нет: создатель — всегда один, не изменяем и по умолчанию
 * редактор (определяет бэк по сессии).
 *
 * Контракт с бэком — в шапке book-form.php: POST /books/store,
 * Accept: application/json → 201 { data: { id, redirect } } либо
 * 422 { errors: { field: [msg] } }. Успех: сначала redirect с бэка,
 * иначе — страница библиотеки /books (P0).
 */
(function () {
  'use strict';

  var COVER_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB  (как Uploader::MAX_BYTES)
  var BOOK_FILE_MAX_BYTES = 50 * 1024 * 1024; // 50 MB на файл книги
  var COVER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

  // Поддерживаемые форматы книги (как минимум pdf; эпаб/моби/фб2 — тоже).
  var BOOK_FILE_EXTS  = ['pdf', 'epub', 'mobi', 'fb2'];
  var BOOK_FILE_MIMES = [
    'application/pdf',
    'application/epub+zip',
    'application/x-mobipocket-ebook',
    'application/fb2',
    'application/x-fictionbook+xml',
  ];

  var AUTHORS_MAX = 30; // редакторы — без лимита

  // Источники для <select data-dynamic-select="...">.
  var SOURCES = {
    genres:     '/api/additional/genres',
    categories: '/api/additional/categories'
  };

  // Заглушка поиска людей (роут добавит бэк, см. контракт в view).
  var PEOPLE_SEARCH_URL = '/api/users/search';

  var listCache = {};

  function t(key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  }

  function escapeHtml(str) {
    return String(str === null || str === undefined ? '' : str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  }

  function formatSize(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '') + ' MB';
  }

  /* ============================================================
     ISBN: логика зеркалит isbnValidate из filters.js — цифры,
     префикс 978/979, 13 знаков; неверная контрольная сумма —
     предупреждение, но НЕ блокирует отправку (как в фильтре).
     ============================================================ */
  function isbnClean(raw) {
    var out = '';
    var src = String(raw === null || raw === undefined ? '' : raw);
    for (var i = 0; i < src.length && out.length < 13; i++) {
      if (src[i] >= '0' && src[i] <= '9') out += src[i];
    }
    return out;
  }

  function isbnChecksumOk(c) {
    if (c.length !== 13) return true;
    var sum = 0;
    for (var i = 0; i < 12; i++) sum += Number(c[i]) * (i % 2 ? 3 : 1);
    return (10 - (sum % 10)) % 10 === Number(c[12]);
  }

  // Группы дефисов канонического ISBN-13: 978-0-306-40615-2
  var ISBN_GROUPS = [3, 1, 3, 5, 1];

  /** Цифры -> формат с дефисами (зеркало isbnFormat из filters.js). */
  function isbnFormat(c) {
    var parts = [];
    var i = 0;
    for (var g = 0; g < ISBN_GROUPS.length && i < c.length; g++) {
      parts.push(c.slice(i, i + ISBN_GROUPS[g]));
      i += ISBN_GROUPS[g];
    }
    if (i < c.length) parts.push(c.slice(i));
    return parts.join('-');
  }

  /** Авто-дефисы при вводе: держит каретку по позиции цифры. */
  function formatIsbnField(field) {
    var old = field.value;
    var caret = field.selectionStart === null || field.selectionStart === undefined
      ? old.length : field.selectionStart;

    var digitsBefore = 0;
    for (var i = 0; i < caret && i < old.length; i++) {
      if (old[i] >= '0' && old[i] <= '9') digitsBefore++;
    }

    var formatted = isbnFormat(isbnClean(old));
    if (formatted === old) return;
    field.value = formatted;

    if (digitsBefore === 0) {
      try { field.setSelectionRange(0, 0); } catch (_) {}
      return;
    }
    var seen = 0;
    var pos = formatted.length;
    for (var j = 0; j < formatted.length; j++) {
      if (formatted[j] >= '0' && formatted[j] <= '9') seen++;
      if (seen === digitsBefore) { pos = j + 1; break; }
    }
    try { field.setSelectionRange(pos, pos); } catch (_) {}
  }

  /** { error, warning } — error блокирует отправку, warning нет. */
  function checkIsbn(value) {
    var raw = String(value === null || value === undefined ? '' : value).trim();
    var c = isbnClean(raw);
    if (c === '') {
      // Поле необязательное: пусто — ок; не пусто, но без цифр — мусор.
      return { error: raw === '' ? null : t('js.isbn_shape', { hint: t('js.isbn_hint') }), warning: null };
    }
    if (c.length !== 13 || !/^97[89]/.test(c)) {
      return { error: t('js.isbn_shape', { hint: t('js.isbn_hint') }), warning: null };
    }
    if (!isbnChecksumOk(c)) {
      return { error: null, warning: t('bookform.isbn_checksum') };
    }
    return { error: null, warning: null };
  }

  /* ============================================================
     СПИСКИ: жанры и категории (постранично, как в filters.js)
     ============================================================ */
  function fetchAll(url) {
    if (listCache[url]) return Promise.resolve(listCache[url]);

    var all = [];
    var page = 1;

    function next() {
      return fetch(url + '?page=' + page, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      }).then(function (data) {
        var list = Array.isArray(data)
          ? data
          : (Array.isArray(data.items) ? data.items : []);
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

      // id из $form не нашёлся в справочнике — оставляем его сырым,
      // чтобы повторный POST без JS всё равно унёс выбранное значение.
      if (selected && !found) {
        options += '<option value="' + escapeHtml(selected) + '" selected>' + escapeHtml(selected) + '</option>';
      }

      select.innerHTML = options;
    }).catch(function (err) {
      console.error('[book-form] failed to load', url, err);
      notify(t('js.list_load_failed'));
    });
  }

  /* ============================================================
     ОБЛОЖКА: превью + проверка типа/размера
     ============================================================ */
  function initCover(form) {
    var input    = form.querySelector('[data-cover-input]');
    var preview  = form.querySelector('[data-cover-preview]');
    var placeholder = form.querySelector('[data-cover-placeholder]');
    var removeBtn = form.querySelector('[data-cover-remove]');
    if (!input || !preview) return null;

    var objectUrl = null;

    function clearPreview() {
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
      preview.removeAttribute('src');
      preview.hidden = true;
      if (placeholder) placeholder.hidden = false;
      if (removeBtn) removeBtn.hidden = true;
    }

    /** Возвращает сообщение об ошибке или null, если файл валиден. */
    return function () {
      var file = input.files && input.files[0];

      if (!file) { clearPreview(); return null; }

      if (COVER_MIMES.indexOf(file.type) === -1) {
        clearPreview();
        input.value = '';
        return t('js.rule_book_cover');
      }
      if (file.size > COVER_MAX_BYTES) {
        clearPreview();
        input.value = '';
        return t('js.rule_book_cover_size');
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
     ФАЙЛЫ КНИГИ: список выбранных (pdf/epub/mobi/fb2) + удаление
     ============================================================ */
  function bookFileExt(name) {
    var m = /\.([a-z0-9]+)$/i.exec(String(name || ''));
    return m ? m[1] : '';
  }

  function isBookFile(file) {
    var ext = bookFileExt(file.name).toLowerCase();
    if (BOOK_FILE_EXTS.indexOf(ext) !== -1) return true;
    return BOOK_FILE_MIMES.indexOf(file.type) !== -1;
  }

  function initFiles(form) {
    var input = form.querySelector('[data-files-input]');
    var list  = form.querySelector('[data-files-list]');
    if (!input || !list) return null;

    /** Пересобирает input.files из массива (вырезали файл по минусу). */
    function setInputFiles(files) {
      if (typeof DataTransfer === 'undefined') {
        // Очень старые браузеры — придётся сбросить выбор целиком.
        input.value = '';
        return;
      }
      var dt = new DataTransfer();
      for (var i = 0; i < files.length; i++) dt.items.add(files[i]);
      input.files = dt.files;
    }

    function currentFiles() {
      return input.files ? Array.prototype.slice.call(input.files) : [];
    }

    /** Рисует список: имя + размер + минус у каждого файла. */
    function render() {
      var files = currentFiles();
      list.innerHTML = '';
      files.forEach(function (file, index) {
        var li = document.createElement('li');
        li.className = 'file-upload__item';

        var name = document.createElement('span');
        name.className = 'file-upload__name';
        name.textContent = file.name || '';

        var size = document.createElement('span');
        size.className = 'file-upload__size';
        size.textContent = formatSize(file.size);

        var remove = document.createElement('button');
        remove.className = 'file-upload__remove';
        remove.type = 'button';
        remove.textContent = '−';
        remove.setAttribute('aria-label', t('bookform.file_remove'));
        remove.addEventListener('click', function () {
          var next = currentFiles();
          next.splice(index, 1);
          setInputFiles(next);
          render();
          input.dispatchEvent(new Event('change', { bubbles: true }));
        });

        li.appendChild(name);
        li.appendChild(size);
        li.appendChild(remove);
        list.appendChild(li);
      });
    }

    /** Возвращает сообщение об ошибке или null, если файлы валидны. */
    return function () {
      var files = currentFiles();

      // Невалидные файлы выкидываем из выбора, оставляем годные.
      var kept = [];
      var rejected = null;
      files.forEach(function (file) {
        if (!isBookFile(file)) {
          rejected = rejected || t('js.rule_book_file');
          return;
        }
        if (file.size > BOOK_FILE_MAX_BYTES) {
          rejected = rejected || t('js.rule_book_file_size');
          return;
        }
        kept.push(file);
      });

      if (kept.length !== files.length) setInputFiles(kept);
      render();
      return rejected;
    };
  }

  /* ============================================================
     ЛЮДИ: авторы и редакторы.
     Поиск по нику → GET /api/users/search?q= (заглушка), результаты
     кликом добавляются в список под поиском; минус убирает. У автора
     есть кнопка «сделать редактором» — она дублирует человека в
     список редакторов (автором он остаётся).
     ============================================================ */
  function initPeople(form) {
    var boxes = form.querySelectorAll('[data-people-field]');
    if (!boxes.length) return null;

    /** Контейнеры списка и значений: authors/editors живут рядом. */
    var state = {};

    boxes.forEach(function (box) {
      var kind = box.getAttribute('data-people-field'); // authors | editors
      var search  = box.querySelector('[data-people-search]');
      var results = box.querySelector('[data-people-results]');
      var list    = box.querySelector('[data-people-list]');
      if (!search || !list) return;

      var limit = kind === 'authors' ? AUTHORS_MAX : Infinity;
      var timer = null;
      var seq = 0;

      /** id уже добавленных (в этом списке). */
      function addedIds() {
        return Array.prototype.map.call(
          list.querySelectorAll('[data-person-id]'),
          function (li) { return String(li.getAttribute('data-person-id')); }
        );
      }

      /** Синхронизирует hidden inputs authors[]/editors[] со списком. */
      function syncValues() {
        box.querySelectorAll('[data-people-value]').forEach(function (el) { el.remove(); });
        addedIds().forEach(function (id) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.setAttribute('data-people-value', '');
          input.name = kind + '[]';
          input.value = id;
          box.appendChild(input);
        });
      }

      function showError(text) {
        var errorEl = form.querySelector('[data-error-for="' + kind + '"]');
        if (!errorEl) return;
        errorEl.innerHTML = '<span class="field-error-icon" title="' +
          escapeHtml(text) + '">!</span>';
      }

      function clearErrorEl() {
        var errorEl = form.querySelector('[data-error-for="' + kind + '"]');
        if (errorEl) errorEl.innerHTML = '';
      }

      function addPerson(id, username) {
        id = String(id);
        if (addedIds().indexOf(id) !== -1) return;              // уже в списке
        if (list.querySelectorAll('[data-person-id]').length >= limit) {
          showError(t('js.rule_book_authors_limit'));
          return;
        }
        var li = document.createElement('li');
        li.className = 'people-list__item';
        li.setAttribute('data-person-id', id);
        li.setAttribute('data-person-name', username);

        var name = document.createElement('span');
        name.className = 'people-list__name';
        name.textContent = username;
        li.appendChild(name);

        // Кнопка «сделать редактором» — только у авторов; обработчики
        // вешает делегирование ниже (работает и для серверного рендера).
        if (kind === 'authors') {
          var promote = document.createElement('button');
          promote.className = 'people-list__action';
          promote.type = 'button';
          promote.setAttribute('data-make-editor', '');
          promote.textContent = t('bookform.make_editor');
          li.appendChild(promote);
        }

        var remove = document.createElement('button');
        remove.className = 'people-list__remove';
        remove.type = 'button';
        remove.setAttribute('data-remove-person', '');
        remove.textContent = '−';
        remove.setAttribute('aria-label', t('bookform.remove_person'));
        li.appendChild(remove);

        list.appendChild(li);
        syncValues();
        clearErrorEl();
        changed();
      }

      /** Человек уже редактор? (для состояния кнопки у автора) */
      function contains(id) {
        return addedIds().indexOf(String(id)) !== -1;
      }

      /** Синхронизирует состояние кнопок «сделать редактором» у авторов. */
      function refreshPromotes() {
        if (kind !== 'authors' || !state.editors) return;
        Array.prototype.forEach.call(list.querySelectorAll('[data-person-id]'), function (item) {
          var btn = item.querySelector('[data-make-editor]');
          if (!btn) return;
          var isEditor = state.editors.contains(item.getAttribute('data-person-id'));
          btn.disabled = isEditor;
          btn.textContent = isEditor
            ? t('bookform.make_editor_done')
            : t('bookform.make_editor');
        });
      }

      /** Любое изменение любого списка → обновить кнопки у авторов. */
      function changed() { if (state.onchange) state.onchange(); }

      state[kind] = {
        addPerson: addPerson,
        contains: contains,
        refreshPromotes: refreshPromotes,
        limit: limit,
      };

      // Делегирование: минус и «сделать редактором» работают и у li,
      // отрендеренных сервером после 422 (обработчиков на них нет).
      list.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('[data-remove-person]');
        if (removeBtn) {
          var item = removeBtn.closest('[data-person-id]');
          if (item) { item.remove(); syncValues(); changed(); }
          return;
        }
        var promoteBtn = e.target.closest('[data-make-editor]');
        if (promoteBtn && !promoteBtn.disabled && state.editors) {
          var src = promoteBtn.closest('[data-person-id]');
          if (!src) return;
          state.editors.addPerson(
            src.getAttribute('data-person-id'),
            src.getAttribute('data-person-name')
          );
          refreshPromotes(); // сразу покажем «Уже редактор»
        }
      });

      function renderResults(items, query) {
        results.innerHTML = '';
        if (!query) { results.hidden = true; return; }

        if (!items.length) {
          var empty = document.createElement('li');
          empty.className = 'people-search__empty';
          empty.textContent = t('bookform.people_not_found');
          results.appendChild(empty);
          results.hidden = false;
          return;
        }

        var taken = addedIds();
        items.forEach(function (user) {
          var id = String(user.id !== undefined ? user.id : '');
          if (!id || taken.indexOf(id) !== -1) return;

          var li = document.createElement('li');
          li.className = 'people-search__item';
          var btn = document.createElement('button');
          btn.className = 'people-search__add';
          btn.type = 'button';
          btn.textContent = String(user.username !== undefined ? user.username : id);
          btn.addEventListener('click', function () {
            addPerson(id, btn.textContent);
            search.value = '';
            results.innerHTML = '';
            results.hidden = true;
          });
          li.appendChild(btn);
          results.appendChild(li);
        });
        results.hidden = !results.children.length;
      }

      function doSearch(query) {
        var mySeq = ++seq;
        fetch(PEOPLE_SEARCH_URL + '?q=' + encodeURIComponent(query), {
          headers: { 'Accept': 'application/json' },
          credentials: 'same-origin',
        })
          .then(function (res) {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
          })
          .then(function (data) {
            if (mySeq !== seq) return; // устарел ответ
            var items = Array.isArray(data)
              ? data
              : (Array.isArray(data.items) ? data.items : []);
            renderResults(items, query);
          })
          .catch(function (err) {
            if (mySeq !== seq) return;
            console.error('[book-form] people search failed', err);
            renderResults([], query);
            var first = results.querySelector('.people-search__empty');
            if (first) first.textContent = t('bookform.people_unavailable');
          });
      }

      search.addEventListener('input', function () {
        var query = search.value.trim();
        clearTimeout(timer);
        if (!query) {
          seq++;
          results.innerHTML = '';
          results.hidden = true;
          return;
        }
        timer = setTimeout(function () { doSearch(query); }, 250);
      });
    });

    // Оба списка готовы: изменение любого из них обновляет кнопки
    // «сделать редактором» у авторов (автор мог уже быть редактором).
    if (state.authors) {
      state.onchange = state.authors.refreshPromotes;
      state.authors.refreshPromotes(); // гидрация после 422: сверить сразу
    }

    return state;
  }

  /* ============================================================
     ФОРМА
     ============================================================ */
  function initForm(form) {
    var FIELD_KEYS = ['title', 'annotation', 'genre', 'category', 'series', 'isbn', 'cover', 'files', 'authors', 'editors'];

    var RULES = {
      title:      { required: true,  maxLength: 200,  msgKey: 'js.rule_book_title' },
      annotation: { required: false, maxLength: 5000, msgKey: 'js.rule_book_annotation' },
      genre:      { required: true,  select: true,    msgKey: 'js.rule_book_genre' },
      category:   { required: true,  select: true,    msgKey: 'js.rule_book_category' },
      series:     { required: false, maxLength: 120,  msgKey: 'js.rule_book_series' },
      isbn:       { required: false, maxLength: 20,   custom: 'isbn', msgKey: 'js.isbn_shape' },
      cover:      { required: false, file: 'cover',   msgKey: 'js.rule_book_cover' },
      // минимум один файл, форматы pdf/epub/mobi/fb2 — см. initFiles
      files:      { required: true,  file: 'files',   msgKey: 'js.rule_book_file_required' },
      // списки людей: лимит авторов (30) держит addPerson, тут только
      // рендер серверных ошибок (422 от бэка)
      authors:    { required: false, custom: 'people' },
      editors:    { required: false, custom: 'people' },
    };

    var fileValidators = {
      cover: initCover(form),
      files: initFiles(form),
    };

    // Списки авторов/редакторов: поиск, добавление, «сделать редактором».
    initPeople(form);

    function getField(name)   {
      var el = form.querySelector('[name="' + name + '"]');
      if (!el && name === 'files') el = form.querySelector('[data-files-input]');
      return el;
    }
    function getErrorEl(name) { return form.querySelector('[data-error-for="' + name + '"]'); }

    function setError(name, message) {
      var field = getField(name);
      var errorEl = getErrorEl(name);
      if (field) field.classList.add('form-field__input--invalid');
      if (errorEl) {
        errorEl.innerHTML = '<span class="field-error-icon" title="' +
          escapeHtml(message) + '">!</span>';
      }
    }

    function clearError(name) {
      var field = getField(name);
      var errorEl = getErrorEl(name);
      if (field) field.classList.remove('form-field__input--invalid');
      if (errorEl) {
        errorEl.classList.remove('form-field__error--warning');
        errorEl.innerHTML = '';
      }
    }

    /** Неблокирующее предупреждение (жёлтое, отправку не мешает). */
    function setWarning(name, message) {
      var errorEl = getErrorEl(name);
      if (!errorEl) return;
      errorEl.classList.add('form-field__error--warning');
      errorEl.innerHTML = '<span class="field-error-icon field-error-icon--warning" title="' +
        escapeHtml(message) + '">!</span>';
    }

    function clearAllErrors() { FIELD_KEYS.forEach(clearError); }

    function validateField(name) {
      var rule = RULES[name];
      var field = getField(name);
      if (!rule || !field) return true;

      // Файлы: тип и размер проверяет fileValidators, он же рисует/убирает
      // превью и имя файла.
      if (rule.file) {
        var validateFile = fileValidators[rule.file];
        var fileError = validateFile ? validateFile() : null;
        if (fileError) { setError(name, fileError); return false; }
        if (rule.required && !(field.files && field.files.length)) {
          setError(name, t(rule.msgKey));
          return false;
        }
        clearError(name);
        return true;
      }

      var value = (field.value || '').trim();
      if (rule.required && value === '') { setError(name, t(rule.msgKey)); return false; }
      if (value === '') { clearError(name); return true; }
      if (rule.maxLength && value.length > rule.maxLength) { setError(name, t(rule.msgKey)); return false; }

      // ISBN: ошибка — блокирует, неверная контрольная сумма — только
      // предупреждение (поле остаётся валидным для отправки).
      if (rule.custom === 'isbn') {
        var isbn = checkIsbn(value);
        if (isbn.error) { setError(name, isbn.error); return false; }
        clearError(name);
        if (isbn.warning) setWarning(name, isbn.warning);
        return true;
      }

      clearError(name);
      return true;
    }

    function validateAll() {
      var ok = true;
      FIELD_KEYS.forEach(function (name) { if (!validateField(name)) ok = false; });
      return ok;
    }

    function focusFirstInvalid() {
      var firstInvalid = form.querySelector('.form-field__input--invalid');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (firstInvalid.focus) firstInvalid.focus();
      }
    }

    function showFieldErrors() {
      var list = [];
      FIELD_KEYS.forEach(function (name) {
        var el = getErrorEl(name);
        var icon = el && el.querySelector('.field-error-icon:not(.field-error-icon--warning)');
        var text = icon ? icon.getAttribute('title') : '';
        if (text) list.push(text);
      });
      if (list.length) notify(list.join('\n'));
    }

    function renderServerErrors(errors) {
      if (!errors || typeof errors !== 'object') return;
      Object.keys(errors).forEach(function (field) {
        var msgs = errors[field];
        var first = Array.isArray(msgs) ? msgs[0] : msgs;
        var message = first && typeof first === 'object' ? (first.message || '') : String(first);
        if (RULES[field] && message) setError(field, message);
      });
      focusFirstInvalid();
    }

    // --------------------------------------------------------
    // Валидация по событиям
    // --------------------------------------------------------
    FIELD_KEYS.forEach(function (name) {
      var rule = RULES[name];
      var field = getField(name);
      if (!rule || !field) return;

      if (rule.file) {
        field.addEventListener('change', function () { validateField(name); });
        return;
      }

      // ISBN: авто-дефисы при вводе + проверка.
      if (rule.custom === 'isbn') {
        field.addEventListener('input', function () {
          formatIsbnField(field);
          if (field.classList.contains('form-field__input--invalid')) validateField(name);
        });
        field.addEventListener('blur',  function () { validateField(name); });
        field.addEventListener('change', function () { validateField(name); });
        return;
      }

      field.addEventListener('blur',  function () { validateField(name); });
      field.addEventListener('input', function () {
        if (field.classList.contains('form-field__input--invalid')) validateField(name);
      });
      field.addEventListener('change', function () { validateField(name); });
    });

    // Кнопка «убрать обложку» (у файлов минусы внутри списка, см. initFiles)
    var coverRemove = form.querySelector('[data-cover-remove]');
    if (coverRemove) {
      coverRemove.addEventListener('click', function () {
        var coverInput = getField('cover');
        if (coverInput) coverInput.value = '';
        validateField('cover');
      });
    }

    // --------------------------------------------------------
    // Ошибки, пришедшие с сервера ($errors во вьюхе)
    // --------------------------------------------------------
    var initial = form.getAttribute('data-initial-errors');
    var loadErrors = [];
    if (initial) {
      initial.split(',').forEach(function (name) {
        name = name.trim();
        if (!name) return;
        var errorEl = getErrorEl(name);
        var icon = errorEl && errorEl.querySelector('.field-error-icon');
        var text = icon ? (icon.getAttribute('title') || '').trim() : '';
        if (text) { loadErrors.push(text); setError(name, text); }
      });
    }
    if (loadErrors.length) notify(loadErrors.join('\n'));

    // --------------------------------------------------------
    // Отправка: fetch + FormData (multipart), файлы не читаем в JS
    // --------------------------------------------------------
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearAllErrors();

      if (!validateAll()) {
        showFieldErrors();
        focusFirstInvalid();
        return;
      }

      var action = form.getAttribute('action') || window.location.href;
      var submitBtn = form.querySelector('button[type="submit"]');
      var originalText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = t('js.bookform_saving');
      }

      function resetButton() {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
      }

      // FormData(form) сам проставляет boundary и уносит файлы.
      var body = new FormData(form);

      // LoreCsrf.fetch сам добавляет заголовок X-CSRF-Token (токен из cookie).
      var csrfFetch = (window.LoreCsrf && typeof LoreCsrf.fetch === 'function')
        ? LoreCsrf.fetch
        : function (url, opts) { return fetch(url, opts); };

      csrfFetch(action, {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then(function (response) {
          return response.text().then(function (raw) {
            var data = null;
            try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

            // Успех: JSON с id/redirect, либо редирект бэка (Location).
            // P0: цель по умолчанию — библиотека /books; приоритет —
            // redirect, присланный бэком (payload.redirect / Location).
            if (response.ok && !/^\s*</.test(raw)) {
              notify(t('js.bookform_saved'), 'success');
              var payload = (data && data.data) || data || {};
              var redirect = payload.redirect
                || response.headers.get('Location')
                || '/books';
              window.location.href = redirect;
              return;
            }

            // Успех без тела (302 с Location — бэк без JSON-режима).
            if (response.redirected || response.status === 302 || response.status === 201) {
              notify(t('js.bookform_saved'), 'success');
              window.location.href = response.url || '/books';
              return;
            }

            if ((response.status === 400 || response.status === 422) && data) {
              var errors = data.errors
                || (data.error && typeof data.error === 'object' ? data.error.details : null);
              renderServerErrors(errors);
            }

            var fallback = t('js.save_failed', { status: response.status });
            notify(window.Messages
              ? window.Messages.describe(response.status, data, raw, fallback)
              : fallback);
            resetButton();
          });
        })
        .catch(function (err) {
          console.error('[book-form] submit failed for', action, err);
          resetButton();
          notify(t('js.network_error'));
        });
    });

    // --------------------------------------------------------
    // Справочники
    // --------------------------------------------------------
    form.querySelectorAll('[data-dynamic-select]').forEach(populateSelect);
  }

  document.querySelectorAll('form[data-book-form]').forEach(initForm);
})();
