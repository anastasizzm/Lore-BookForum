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
 *   4. выпадающие списки жанра и категории (одиночный выбор) из
 *      /api/additional/* — <select name="genreId|categoryId">;
 *   5. клиентскую валидацию (RULES ниже) до отправки;
 *   6. отправку через fetch + FormData (multipart) на POST /api/books/new
 *      с Accept: application/json; обложка и файлы книги перед этим
 *      уходят отдельными запросами POST /api/files/upload (category
 *      cover|book) → iconId / contentIds[];
 *   7. многошаговость (FORM-01, как в article-form.js): шаги
 *      [data-step], «Далее»/«Назад» (кнопки — в общей нижней панели
 *      .book-form__actions, «Назад» скрыта на шаге 1), валидация
 *      ОБЯЗАТЕЛЬНЫХ полей своего шага перед переходом вперёд
 *      (STEP_FIELDS);
 *   8. список глав книги — только UI (input'ы без name, в POST не
 *      уходят: контракт с бэкендом уточняется);
 *   9. локальную копию несохранённых изменений (localStorage,
 *      ключ bookform:v2:new) + плашку «Восстановить / Отбросить»
 *      на шаге 1 — если открыть создание новой книги, не сохранив
 *      прошлые изменения. Копия стирается после успешной отправки.
 *   10. публикацию (11.4): кнопка Publish → POST /api/books/new
 *      (черновик) → 201 { createdId } → POST /books/{id}/publish;
 *      при неудаче publish черновик уже создан — редирект на его
 *      страницу с предупреждением (опубликовать можно и оттуда).
 *   11. окно публикации (11.3): списки авторов/редакторов переехали
 *      из шага 2 в модалку book-publish-dialog — её открывает только
 *      Publish (когда форма валидна), Cancel/Esc/фон закрывают;
 *      отправка — кнопкой внутри окна (data-submit-publish).
 *
 * Поля создателя нет: создатель — всегда один, не изменяем и по умолчанию
 * редактор (определяет бэк по сессии).
 *
 * Контракт с бэком (подробно — в шапке book-form.php):
 *   POST /api/books/new, Accept: application/json →
 *   201 { createdId } → Publish? вторая фаза POST /books/{id}/publish,
 *   иначе уходим на /books/{createdId}; либо
 *   422 { error: { details: { field: [msg] } } } — ошибки рисуем на
 *   полях и возвращаем пользователя на шаг с первой из них;
 *   401 — нет сессии, 419 — CSRF (см. обработку ниже).
 */
(function () {
  'use strict';

  var COVER_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB  — config/settings.php, storage.cover
  var BOOK_FILE_MAX_BYTES = 100 * 1024 * 1024; // 100 MB — config/settings.php, storage.book
  var COVER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

  // POST /api/files/upload (роут files.upload): multipart { file, category }
  // → 201 { createdId: uuid }; category — FileCategory: 'cover' | 'book'.
  var UPLOAD_URL = '/api/files/upload';

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

  // Источники справочников для выпадающих списков [data-catalog-select="..."].
  var SOURCES = {
    genres:     '/api/additional/genres',
    categories: '/api/additional/categories'
  };

  // Заглушка поиска людей (роут добавит бэк, см. контракт в view).
  var PEOPLE_SEARCH_URL = '/api/users/search';

  // Локальная копия несохранённых изменений (плашка «Восстановить»).
  // 'new' — страница создания; для редактирования понадобится свой id.
  // v2 — после переименования полей под контракт BookForm: в v1 лежали
  // старые имена (annotation/author_notes/genre/…), восстановились бы молча.
  var BACKUP_KEY = 'bookform:v2:new';

  var listCache = {};
  // Перерисовка справочников по имени поля (genreId/categoryId) — нужна
  // локальной копии (applyBackup), когда список уже загружен.
  var catalogRenderers = {};

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

  /** Отложенный вызов (для автозаписи копии изменений). */
  function debounce(fn, ms) {
    var timer;
    return function () {
      var args = arguments;
      clearTimeout(timer);
      timer = setTimeout(function () { fn.apply(null, args); }, ms);
    };
  }

  function formatSize(bytes) {
    if (!bytes && bytes !== 0) return '';
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1).replace(/\.0$/, '') + ' MB';
  }

  /* ============================================================
     ЗАГРУЗКА ФАЙЛОВ: POST /api/files/upload (F2-02)
     Один файл за запрос: multipart { file, category }, CSRF-заголовок
     добавляет LoreCsrf.fetch. Успех → 201 { createdId: uuid }.
     ============================================================ */
  /** fetch с CSRF: LoreCsrf.fetch ставит X-CSRF-Token к не-GET методам. */
  function csrfFetch(url, options) {
    if (window.LoreCsrf && typeof LoreCsrf.fetch === 'function') {
      return LoreCsrf.fetch(url, options);
    }
    return fetch(url, options);
  }

  /** Кеш «файл → uuid»: повторная отправка после 422 не переливает файл. */
  var uploadCache = {};

  function uploadCacheKey(category, file) {
    return [category, file.name || '', file.size || 0, file.lastModified || 0].join(':');
  }

  /** Локализованный текст ошибки загрузки (422/419/401/прочее). */
  function describeUploadFailure(status, data, raw) {
    if (status === 401) return t('errors.common.unauthorized');
    if (status === 419) return t('errors.common.csrf_fail');

    var details = data && data.error && data.error.details ? data.error.details : null;
    if (details) {
      var list = details.file || details.cover;
      var first = Array.isArray(list) ? list[0] : list;
      if (first) return String(first);
    }

    var fallback = t('errors.common.save_failed', { status: status });
    return window.Messages
      ? window.Messages.describe(status, data, raw, fallback)
      : fallback;
  }

  /** Загружает один файл; резолвится uuid-строкой, иначе reject. */
  function uploadFile(file, category) {
    var key = uploadCacheKey(category, file);
    if (uploadCache[key]) return Promise.resolve(uploadCache[key]);

    var fd = new FormData();
    fd.append('file', file);
    fd.append('category', category);

    return csrfFetch(UPLOAD_URL, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
    }).then(function (response) {
      return response.text().then(function (raw) {
        var data = null;
        try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

        if (response.ok) {
          var id = data ? data.createdId : null;
          if (!id && data && data.data) id = data.data.id;
          if (id) {
            uploadCache[key] = String(id);
            return String(id);
          }
        }

        var err = new Error(describeUploadFailure(response.status, data, raw));
        err.status = response.status;
        throw err;
      });
    });
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
      return { error: raw === '' ? null : t('errors.validation.isbn_shape', { hint: t('js.isbn_hint') }), warning: null };
    }
    if (c.length !== 13 || !/^97[89]/.test(c)) {
      return { error: t('errors.validation.isbn_shape', { hint: t('js.isbn_hint') }), warning: null };
    }
    if (!isbnChecksumOk(c)) {
      return { error: null, warning: t('bookform.isbn_checksum') };
    }
    return { error: null, warning: null };
  }

  /* ============================================================
     СПИСКИ: выпадающие списки жанра и категории (11.1 → select).
     Выбор ОДИН на группу — бэкенд хранит ровно один genreId и одну
     categoryId (BookForm: int > 0); значение несёт сам <select
     name="genreId|categoryId"> — на него вешаются валидация (RULES),
     ошибки 422 и локальная копия, как на обычное поле.

     Контракт /api/additional/{genres,categories}:
     - 200 {"items": [...], "meta": {"page","pageSize","hasNext"}}
     - пагинация: ?page=N (с 1) и ?ps=N (<= 50);
     - сортировку (sort) НЕ передаём: справочник API падает 500,
       EnumExtensions::tryResolve не импортирован в сервисах;
     - элемент: { id, name, ... }.

     Пока справочники грузятся, кнопки «Далее» заблокированы: выбор
     жанра и категории обязателен, а пустой список дал бы ложную
     422. При сбое загрузки в список попадает пункт-статус с текстом
     «повторить» — клик по select повторяет попытку.
     ============================================================ */
  var CATALOG_PS = 50;      // максимум строк на страницу в API
  var CATALOG_MAX_PAGES = 20;

  var catalogsPending = 0;  // сколько справочников ещё грузится

  /** Блокирует/разблокирует переход дальше, пока идёт загрузка. */
  function syncCatalogGate() {
    var busy = catalogsPending > 0;
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-step-next]'),
      function (btn) { btn.disabled = busy; }
    );
  }

  function fetchAll(url) {
    if (listCache[url]) return Promise.resolve(listCache[url]);

    var all = [];
    var seen = {};          // id → true: защита от дублей между страницами
    var page = 1;

    function next() {
      var sep = url.indexOf('?') === -1 ? '?' : '&';
      return fetch(url + sep + 'page=' + page + '&ps=' + CATALOG_PS, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.text().then(function (raw) {
          try { return JSON.parse(raw); }
          catch (_) { throw new Error('not JSON: ' + raw.slice(0, 120)); }
        });
      }).then(function (data) {
        var list = Array.isArray(data)
          ? data
          : (Array.isArray(data.items) ? data.items
            : (Array.isArray(data.data) ? data.data : []));
        for (var i = 0; i < list.length; i++) {
          var item = list[i] || {};
          var id = String(item.id !== undefined ? item.id : (item.value !== undefined ? item.value : ''));
          if (id === '' || seen[id]) continue;
          seen[id] = true;
          all.push(item);
        }

        var hasNext = !Array.isArray(data) && data.meta && data.meta.hasNext;
        if (hasNext && page < CATALOG_MAX_PAGES) { page += 1; return next(); }
        listCache[url] = all;
        return all;
      });
    }

    return next();
  }

  /**
   * Один справочник в выпадающем списке:
   * [data-catalog-select="genres|categories"].
   * Бэкенд хранит ровно один genreId/одну categoryId (int > 0) — выбор
   * одиночный; <select> сам несёт name/value — на него вешаются валидация
   * (RULES genreId/categoryId), ошибки 422 и локальная копия.
   *
   * current — «источник истины» для selected: select.value нельзя
   * присвоить отсутствующий option (value молча сбросится в ''), поэтому
   * до отрисовки списка выбор держим здесь (applyBackup передаёт его в
   * catalogRenderers[name](value)).
   */
  function initCatalogSelect(select) {
    var kind = select.getAttribute('data-catalog-select');
    var url = SOURCES[kind];
    if (!url) return;

    var name = select.getAttribute('name');   // genreId | categoryId
    var placeholder = select.getAttribute('data-placeholder') || '';
    var current = select.value || '';

    // Выбор пользователя — обычный change самого select.
    select.addEventListener('change', function () { current = select.value; });

    /** Пункт-статус («Загрузка…» / «Не удалось…»); текущий выбор не теряем. */
    function showStatus(text) {
      select.innerHTML = '';

      var opt = document.createElement('option');
      opt.value = '';
      opt.textContent = text;
      if (current === '') { opt.selected = true; opt.disabled = true; }
      select.appendChild(opt);

      if (current !== '') {   // выбор уже сделан — показываем его, не плейсхолдер
        var keep = document.createElement('option');
        keep.value = current;
        keep.textContent = current;
        keep.selected = true;
        select.appendChild(keep);
      }
    }

    /** Рисует options из списка справочника (кэш fetchAll). */
    function renderList(list) {
      var html = '<option value=""' + (current === '' ? ' selected disabled' : '') + '>' +
        escapeHtml(placeholder) + '</option>';
      var found = false;

      for (var i = 0; i < list.length; i++) {
        var item = list[i] || {};
        var id = String(item.id !== undefined ? item.id : (item.value !== undefined ? item.value : ''));
        if (id === '') continue;
        var title = String(
          item.name  !== undefined ? item.name  :
          (item.title !== undefined ? item.title :
          (item.label !== undefined ? item.label : id))
        );
        if (id === current) found = true;
        html += '<option value="' + escapeHtml(id) + '"' +
          (id === current ? ' selected' : '') + '>' + escapeHtml(title) + '</option>';
      }

      // id из $form/книги не нашёлся в справочнике — оставляем его сырым,
      // чтобы выбор не пропадал (POST всё равно унесёт select).
      if (current !== '' && !found) {
        html += '<option value="' + escapeHtml(current) + '" selected>' +
          escapeHtml(current) + '</option>';
      }

      select.innerHTML = html;
      current = select.value;   // выравниваем: selected мог не найтись
    }

    // Для applyBackup (локальная копия): перерисовать выбор, если список
    // уже в кэше; если ещё грузится — renderList подхватит current, когда
    // список будет готов (значение приходит аргументом из applyBackup).
    catalogRenderers[name] = function (value) {
      if (value !== undefined) {
        current = String(value);
        if (current !== '') {
          // option с этим значением может ещё не существовать (список не
          // рисовался / не загрузился): select.value молча сбросился бы в ''
          // — добавляем пункт сырым, renderList позже заменит его названием.
          select.value = current;
          if (select.value !== current) {
            var raw = document.createElement('option');
            raw.value = current;
            raw.textContent = current;
            raw.selected = true;
            select.appendChild(raw);
          }
        }
      }
      var list = listCache[url];
      if (list) renderList(list);
    };

    function load() {
      var willLoad = !listCache[url];   // из кэша — мгновенно, без блокировки

      if (willLoad) {
        catalogsPending += 1;
        select.setAttribute('data-catalog-loading', '');
        showStatus(t('bookform.select_loading'));
        syncCatalogGate();
      }

      return fetchAll(url).then(renderList).catch(function (err) {
        console.error('[book-form] failed to load', url, err);
        notify(t('errors.common.list_load_failed'));
        select.removeAttribute('data-catalog-loading');
        select.setAttribute('data-catalog-failed', '');
        showStatus(t('bookform.select_failed'));
        // Список не отрисован — клик по select повторяет попытку
        // (слушатель один: после успеха он уже не нужен).
        select.addEventListener('click', retry, { once: true });
      }).then(function () {
        if (willLoad) {
          catalogsPending = Math.max(0, catalogsPending - 1);
          select.removeAttribute('data-catalog-loading');
          syncCatalogGate();
        }
      });
    }

    function retry() {
      select.removeAttribute('data-catalog-failed');
      load();
    }

    load();
  }

  /* ============================================================
     ОБЛОЖКА: превью + проверка типа/размера
     ============================================================ */
  function initCover(form) {
    var input    = form.querySelector('[data-cover-input]');
    var preview  = form.querySelector('[data-cover-preview]');
    var placeholder = form.querySelector('[data-cover-placeholder]');
    var removeBtn = form.querySelector('[data-cover-remove]');
    var stateEl  = form.querySelector('[data-cover-upload] [data-file-button-state]');
    if (!input || !preview) return null;

    var objectUrl = null;

    /** Текст под кнопкой: имя файла либо «Файл не выбран». */
    function syncState() {
      if (!stateEl) return;
      var file = input.files && input.files[0];
      stateEl.textContent = file ? (file.name || '') : t('bookform.file_none');
    }

    function clearPreview() {
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
      preview.removeAttribute('src');
      preview.hidden = true;
      if (placeholder) placeholder.hidden = false;
      if (removeBtn) removeBtn.hidden = true;
      syncState();
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
      syncState();
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

  /** MIME без расширения в имени → формат (epub/mobi/fb2/pdf) или ''. */
  var MIME_TO_EXT = {
    'application/pdf': 'pdf',
    'application/epub+zip': 'epub',
    'application/x-mobipocket-ebook': 'mobi',
    'application/fb2': 'fb2',
    'application/x-fictionbook+xml': 'fb2',
  };

  /** Канонический формат файла: по расширению, иначе по MIME, иначе ''. */
  function bookFileFormat(file) {
    var ext = bookFileExt(file.name).toLowerCase();
    if (BOOK_FILE_EXTS.indexOf(ext) !== -1) return ext;
    return MIME_TO_EXT[file.type] || '';
  }

  function initFiles(form) {
    var input = form.querySelector('[data-files-input]');
    var list  = form.querySelector('[data-files-list]');
    var stateEl = form.querySelector('[data-files-field] [data-file-button-state]');
    if (!input || !list) return null;

    /** Текст под кнопкой: имя первого файла (+ещё N) либо «Файл не выбран». */
    function syncState() {
      if (!stateEl) return;
      var files = currentFiles();
      if (!files.length) { stateEl.textContent = t('bookform.file_none'); return; }
      var text = files[0].name || '';
      if (files.length > 1) {
        text += ' ' + t('bookform.file_and_more', { count: files.length - 1 });
      }
      stateEl.textContent = text;
    }

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
      syncState();
    }

    /** Возвращает сообщение об ошибке или null, если файлы валидны. */
    return function () {
      var files = currentFiles();

      // Невалидные файлы и дубли форматов выкидываем из выбора, оставляем
      // годные: один файл на формат (pdf/epub/mobi/fb2), минимум один файл.
      var kept = [];
      var seen = {};
      var rejected = null;
      files.forEach(function (file) {
        var format = bookFileFormat(file);
        if (!format) {
          rejected = rejected || t('js.rule_book_file');
          return;
        }
        if (file.size > BOOK_FILE_MAX_BYTES) {
          rejected = rejected || t('js.rule_book_file_size');
          return;
        }
        if (seen[format]) {
          rejected = rejected || t('js.rule_book_file_dup', { format: format.toUpperCase() });
          return;
        }
        seen[format] = true;
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

      // Enter в строке поиска: результат добавляется кликом, а не Enter'ом —
      // иначе браузер отправит форму (кнопка-сабмитер рядом, в диалоге).
      search.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') e.preventDefault();
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
     ОКНО ПУБЛИКАЦИИ (11.3): авторы/редакторы переехали из шага 2
     в модалку book-publish-dialog (partial, внутри <form> — hidden
     inputs authors[]/editors[] попадают в FormData).
     Открывает окно кнопка Publish [data-publish-open] в нижней
     панели; отправка — кнопкой внутри окна (data-submit-publish).
     ============================================================ */
  function initPublishDialog(form) {
    var root = form.querySelector('[data-book-publish-dialog]');
    if (!root) return null;

    var lastFocus = null;

    function focusables() {
      return Array.prototype.slice.call(
        root.querySelectorAll('button, [href], input, select, textarea, [tabindex]')
      ).filter(function (el) { return !el.disabled && !el.hidden && el.offsetParent !== null; });
    }

    function onKeydown(e) {
      if (root.hidden) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key === 'Tab') {
        var items = focusables();
        if (!items.length) return;
        var first = items[0];
        var last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    }

    function open() {
      if (!root.hidden) return;
      lastFocus = document.activeElement;
      root.hidden = false;
      document.body.classList.add('is-modal-open');
      document.addEventListener('keydown', onKeydown, true);
      // фокус — в поиск авторов (первое поле окна)
      var first = root.querySelector('[data-people-search]');
      if (first) { try { first.focus(); } catch (_) {} }
    }

    function close() {
      if (root.hidden) return;
      root.hidden = true;
      document.body.classList.remove('is-modal-open');
      document.removeEventListener('keydown', onKeydown, true);
      var target = lastFocus;
      lastFocus = null;
      // вернуть фокус на кнопку, открывшую окно
      if (target && target.isConnected && target.offsetParent !== null) {
        try { target.focus(); return; } catch (_) {}
      }
      var fallback = form.querySelector('[data-publish-open]');
      if (fallback) { try { fallback.focus(); } catch (_) {} }
    }

    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-publish-dialog-backdrop]') ||
          e.target.closest('[data-publish-dialog-close]')) {
        close();
      }
    });

    return {
      open: open,
      close: close,
      isOpen: function () { return !root.hidden; },
    };
  }

  /* ============================================================
     ФОРМА
     ============================================================ */
  function initForm(form) {
    var FIELD_KEYS = ['title', 'description', 'authorNotes', 'genreId', 'categoryId', 'publisher',
                      'pages', 'isbn', 'cover', 'files', 'authors', 'editors'];

    var RULES = {
      title:       { required: true,  maxLength: 200,  msgKey: 'js.rule_book_title' },
      // лимит аннотации — 4000 (PublicationFormValidator::DESCR_MAX_LEN)
      description: { required: true,  maxLength: 4000, msgKey: 'js.rule_book_annotation' },
      // Заметки автора (11.2): бэк требует 1..2000 (PublicationFormValidator::
      // NOTES_MAX_LEN) — поле ОБЯЗАТЕЛЬНОЕ. Пустое и слишком длинное —
      // разные сообщения: requiredMsgKey (пусто, включая пробелы —
      // trim в validateField), msgKey (длина > 2000).
      authorNotes: { required: true,  maxLength: 2000, msgKey: 'js.rule_book_author_note',
                     requiredMsgKey: 'js.rule_book_author_note_required' },
      genreId:     { required: true,  msgKey: 'js.rule_book_genre' },
      categoryId:  { required: true,  msgKey: 'js.rule_book_category' },
      publisher:   { required: true,  maxLength: 255,  msgKey: 'js.rule_book_publisher' },
      // pages: целое ≥ 1 — см. custom: 'pages' ниже
      pages:       { required: true,  min: 1,          msgKey: 'js.rule_book_pages' },
      isbn:        { required: false, maxLength: 20,   custom: 'isbn', msgKey: 'errors.validation.isbn_shape' },
      cover:       { required: false, file: 'cover',   msgKey: 'js.rule_book_cover' },
      // минимум один файл, форматы pdf/epub/mobi/fb2 — см. initFiles
      files:       { required: true,  file: 'files',   msgKey: 'js.rule_book_file_required' },
      // списки людей: лимит авторов (30) держит addPerson, тут только
      // рендер серверных ошибок (422 от бэка)
      authors:     { required: false, custom: 'people' },
      editors:     { required: false, custom: 'people' },
    };

    // Обязательные поля каждого шага: «Далее» не пускает дальше,
    // пока они не прошли валидацию (см. initSteps).
    // Шаг 3 «Роли» пропущен — когда он появится, добавить сюда его поля.
    var STEP_FIELDS = {
      1: ['title', 'genreId', 'categoryId', 'publisher', 'pages', 'isbn', 'cover'],
      2: ['description', 'authorNotes', 'files', 'authors', 'editors'],
    };

    var fileValidators = {
      cover: initCover(form),
      files: initFiles(form),
    };

    // Списки авторов/редакторов: поиск, добавление, «сделать редактором».
    initPeople(form);

    // Окно публикации (11.3): Publish в нижней панели открывает его.
    // Открываем ТОЛЬКО когда форма валидна — иначе ошибки полей
    // невидны под модалкой.
    var publishDialog = initPublishDialog(form);
    Array.prototype.forEach.call(form.querySelectorAll('[data-publish-open]'), function (btn) {
      btn.addEventListener('click', function () {
        if (!validateAll()) {
          showFieldErrors();
          focusFirstInvalid();
          return;
        }
        if (publishDialog) publishDialog.open();
      });
    });

    // API переключения шагов (нужен focusFirstInvalid, чтобы показать шаг,
    // на котором лежит первое невалидное поле) и флаг несохранённых
    // изменений (см. локальную копию ниже).
    var stepsApi = null;
    var dirty = false;

    // edit-режим: скрытое поле id (контракт — в шапке book-form.php).
    // id > 0 → POST /books/store обновляет книгу; локальная копия
    // («Восстановить») в edit-режиме не пишется.
    var idField = form.querySelector('input[name="id"]');
    var editId = idField ? (parseInt(idField.value, 10) || 0) : 0;

    function getField(name)   {
      var el = form.querySelector('[name="' + name + '"]');
      if (el) return el;
      // Обложка и файлы книги — input'ы БЕЗ name (в payload они не
      // входят, их грузит book-form.js в /api/files/upload) — ищем
      // по data-атрибуту, чтобы валидация и ошибки находили поле.
      if (name === 'cover') return form.querySelector('[data-cover-input]');
      if (name === 'files') return form.querySelector('[data-files-input]');
      return null;
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
      // Пустое обязательное поле: сообщение requiredMsgKey, если задано
      // (иначе общее msgKey) — «слишком длинное» для пустого значения неверно.
      if (rule.required && value === '') { setError(name, t(rule.requiredMsgKey || rule.msgKey)); return false; }
      if (value === '') { clearError(name); return true; }
      if (rule.maxLength && value.length > rule.maxLength) { setError(name, t(rule.msgKey)); return false; }

      // Целое ≥ min (pages): «3.5», «12abc» и «0» — ошибка.
      if (rule.min !== undefined && rule.min !== null) {
        var num = Number(value);
        if (!isFinite(num) || Math.floor(num) !== num || num < rule.min) {
          setError(name, t(rule.msgKey));
          return false;
        }
      }

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
      if (!firstInvalid) return;

      // Ошибка может жить на скрытом шаге (например, пришёл 422 от бэка) —
      // сначала раскрываем шаг, потом скроллим к полю (жанр/категория —
      // обычные select, фокусируются напрямую).
      var step = firstInvalid.closest ? firstInvalid.closest('.book-step') : null;
      if (step && step.hidden && stepsApi) stepsApi.reveal(step);

      var target = firstInvalid;
      if (target.scrollIntoView) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (target.focus) target.focus();
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

    // Имена полей контракта BookForm могут не совпадать с ключами
    // локальной валидации: contentIds — файлы книги, file — обложка.
    var SERVER_FIELD_MAP = { contentIds: 'files', file: 'cover' };

    function renderServerErrors(errors) {
      if (!errors || typeof errors !== 'object') return;
      Object.keys(errors).forEach(function (key) {
        var field = SERVER_FIELD_MAP[key] || key;
        var msgs = errors[key];
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
    // Отправка: fetch + FormData (multipart) → POST /api/books/new.
    // Ответ (Accept: application/json):
    //   201 { createdId } → Publish (11.4): вторая фаза
    //        POST /books/{id}/publish, иначе — как раньше, уходим
    //        на /books/{createdId} с уведомлением «черновик создан»;
    //   422/400 → error.details: { field: [msg] } — рисуем на полях и
    //        возвращаем пользователя на шаг с первой ошибкой;
    //   401 — нет сессии, 419 — CSRF; прочее — общий текст ошибки.
    // --------------------------------------------------------

    // «Чем отправили»: e.submitter есть не во всех браузерах —
    // держим флаг последнего клика по кнопкам-отправителям.
    // Enter в поле отправляет без клика — срабатывает последнее намерение.
    var publishRequested = false;
    Array.prototype.forEach.call(
      form.querySelectorAll('[data-submit-publish], [data-submit-draft], [data-submit-save]'),
      function (btn) {
        btn.addEventListener('click', function () {
          publishRequested = btn.hasAttribute('data-submit-publish');
        });
      }
    );

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      clearAllErrors();

      var wantPublish = !editId
        && !!(e.submitter
          ? e.submitter.hasAttribute('data-submit-publish')
          : publishRequested);

      // edit-режим заблокирован: endpoint'а обновления нет, а
      // POST /api/books/new только создаёт — не плодим дубликаты.
      if (editId) {
        notify(t('js.book_edit_unavailable'));
        return;
      }

      // Сначала клиентская валидация ВСЕХ полей (порядок полей в DOM:
      // если ошибки и на первом, и на втором шаге — фокус уйдёт на шаг 1,
      // дальше пользователь перейдёт на второй сам).
      if (!validateAll()) {
        showFieldErrors();
        focusFirstInvalid();
        return;
      }

      var action = form.getAttribute('action') || '/api/books/new';

      // ISBN: бэк принимает ТОЛЬКО канонический вид с дефисами
      // (#^\d{3}-\d{1}-\d{3}-\d{5}-\d{1}$#), а значение могло прийти
      // из локальной копии или вставиться целиком — нормализуем.
      var isbnField = form.querySelector('input[name="isbn"]');
      if (isbnField && isbnField.value) {
        var isbnDigits = isbnClean(isbnField.value);
        if (isbnDigits.length === 13) isbnField.value = isbnFormat(isbnDigits);
      }

      var submitBtns = Array.prototype.slice.call(
        form.querySelectorAll('[data-submit-save], [data-submit-draft], [data-submit-publish]')
      );
      var labels = submitBtns.map(function (b) { return b.textContent; });
      // Активная кнопка — та, которой отправили (submitter), иначе
      // Publish по флагу клика, иначе первая в DOM
      var activeBtn = (e.submitter && submitBtns.indexOf(e.submitter) !== -1)
        ? e.submitter
        : null;
      if (!activeBtn && wantPublish) {
        activeBtn = form.querySelector('[data-submit-publish]') || null;
      }
      if (!activeBtn) activeBtn = submitBtns[0] || null;

      function resetButtons() {
        // Ошибка отправки — закрываем окно: ошибки полей видны только
        // на форме (люди их не перекрывают).
        if (publishDialog) publishDialog.close();
        submitBtns.forEach(function (b, i) {
          b.disabled = false;
          if (labels[i] !== undefined) b.textContent = labels[i];
        });
      }

      // FormData(form) сам проставляет boundary и уносит поля; значение
      // кнопки-сабмитера в неё НЕ попадает — статуса в контракте нет.
      // БINARY payload: у input[type=file] НЕТ name — файлы уходят
      // отдельными запросами в /api/files/upload (см. uploadPhase ниже).
      var body = new FormData(form);
      body.delete('id');   // edit-режим отсечён выше; id бэк не принимает

      // ---- Файлы: обложка (cover) и файлы книги (book) → /api/files/upload
      var coverField = getField('cover');
      var filesField = getField('files');
      var coverFile = coverField && coverField.files && coverField.files.length
        ? coverField.files[0]
        : null;
      var bookFiles = filesField && filesField.files
        ? Array.prototype.slice.call(filesField.files)
        : [];

      submitBtns.forEach(function (b) { b.disabled = true; });
      if (activeBtn) {
        activeBtn.textContent = t((coverFile || bookFiles.length)
          ? 'js.bookform_uploading'
          : 'js.bookform_saving');
      }

      /** iconId / contentIds пересобираем на каждой отправке: обложку
       *  или файл могли убрать после прошлой неудачной попытки. */
      function resetUploadIds() {
        var iconField = form.querySelector('input[name="iconId"]');
        if (iconField) iconField.value = '';
        body.delete('contentIds[]');
      }

      /** Грузит выбранные файлы; резолвится, когда id лежат в body. */
      function uploadPhase() {
        resetUploadIds();
        var tasks = [];

        if (coverFile) {
          tasks.push(uploadFile(coverFile, 'cover').then(function (uuid) {
            var iconField = form.querySelector('input[name="iconId"]');
            if (iconField) iconField.value = uuid;
          }, function (err) {
            err.field = 'cover';
            throw err;
          }));
        }

        if (bookFiles.length) {
          tasks.push(
            Promise.all(bookFiles.map(function (file) { return uploadFile(file, 'book'); }))
              .then(function (ids) {
                ids.forEach(function (id) { body.append('contentIds[]', id); });
              }, function (err) {
                err.field = 'files';
                throw err;
              })
          );
        }

        return Promise.all(tasks);
      }

      // ---- Создание книги: POST /api/books/new ----
      function sendCreate() {
        if (activeBtn) activeBtn.textContent = t('js.bookform_saving');

        return csrfFetch(action, {
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

              if (response.ok) {
                clearBackup();
                dirty = false;

                var createdId = data ? parseInt(data.createdId, 10) : NaN;
                if (!createdId && data && data.data) createdId = parseInt(data.data.id, 10);

                var locationHeader = response.headers.get('Location');
                var redirect = createdId
                  ? '/books/' + createdId
                  : (locationHeader || (response.redirected ? response.url : '/books'));

                // Publish (11.4): черновик создан — вторая фаза
                // POST /books/{id}/publish; иначе уходим как раньше.
                if (wantPublish && createdId) {
                  return publishPhase(createdId, redirect);
                }

                notify(t('js.book_draft_created'), 'success');
                window.location.href = redirect;
                return;
              }

              // 422/400: ошибки полей (ExceptionMiddleware отдаёт
              // error.details) — на поля + возврат на шаг с первой.
              if (response.status === 400 || response.status === 422) {
                var details = data && data.error && data.error.details
                  ? data.error.details
                  : (data && data.errors ? data.errors : null);
                renderServerErrors(details);
              }

              if (response.status === 401) { if (publishDialog) publishDialog.close(); notify(t('errors.common.unauthorized')); return; }
              if (response.status === 419) { notify(t('errors.common.csrf_fail')); resetButtons(); return; }

              var fallback = t('errors.common.save_failed', { status: response.status });
              notify(window.Messages
                ? window.Messages.describe(response.status, data, raw, fallback)
                : fallback);
              resetButtons();
            });
          })
          .catch(function (err) {
            console.error('[book-form] submit failed for', action, err);
            resetButtons();
            notify(t('errors.common.network_error'));
          });
      }

      // ---- Публикация (11.4): POST /books/{id}/publish после создания ----
      // P0-роут (см. rights-modal.php): переводит черновик в published.
      // Бэкенд-контракт фронта заморожен — при 404/прочем ошибке
      // НЕ роняем процесс: черновик уже создан, уводим на его страницу
      // с предупреждением (опубликовать можно и оттуда, rights-modal).
      function publishPhase(createdId, redirect) {
        if (activeBtn) activeBtn.textContent = t('js.bookform_publishing');

        return csrfFetch('/books/' + createdId + '/publish', {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
        })
          .then(function (response) {
            if (response.ok) {
              notify(t('js.book_published'), 'success');
            } else {
              notify(t('js.book_publish_failed'), 'warning');
            }
            leaveAndRedirect(redirect);
          })
          .catch(function (err) {
            console.error('[book-form] publish failed for', createdId, err);
            notify(t('js.book_publish_failed'), 'warning');
            leaveAndRedirect(redirect);
          });
      }

      /** Уход со страницы после успеха: снимаем защиту beforeunload. */
      function leaveAndRedirect(url) {
        window.dispatchEvent(new Event('bookform:leave'));
        window.location.href = url;
      }

      uploadPhase()
        .then(function () { return sendCreate(); })
        .catch(function (err) {
          if (err && err.field) {
            // Файл не загрузился (тип/размер/422/419) — ошибка на поле.
            setError(err.field, err.message || t('errors.common.save_failed', { status: '' }));
            showFieldErrors();
            focusFirstInvalid();
            resetButtons();
            return;
          }
          console.error('[book-form] upload phase failed', err);
          resetButtons();
          notify(t('errors.common.network_error'));
        });
    });

    // --------------------------------------------------------
    // Справочники (выпадающие списки жанра и категории)
    // --------------------------------------------------------
    form.querySelectorAll('[data-catalog-select]').forEach(initCatalogSelect);

    // --------------------------------------------------------
    // ГЛАВЫ КНИГИ — только UI (input'ы без name, в POST не уходят;
    // контракт с бэкендом уточняется, см. шапку book-form.php)
    // --------------------------------------------------------
    function renumberChapters() {
      Array.prototype.forEach.call(
        form.querySelectorAll('[data-chapters] [data-chapter]'),
        function (row, i) {
          var num = row.querySelector('[data-chapter-num]');
          if (num) num.textContent = String(i + 1);
        }
      );
    }

    function addChapter(value) {
      var list = form.querySelector('[data-chapters]');
      if (!list) return null;

      var row = document.createElement('li');
      row.className = 'chapters__item';
      row.setAttribute('data-chapter', '');
      row.innerHTML =
        '<span class="chapters__num" data-chapter-num aria-hidden="true"></span>' +
        '<input class="form-field__input chapters__input" type="text" data-chapter-title' +
        ' maxlength="200" autocomplete="off" placeholder="' +
        escapeHtml(t('bookform.chapter_ph')) + '">' +
        '<button class="chapters__remove" type="button" data-chapter-remove aria-label="' +
        escapeHtml(t('bookform.chapter_remove')) + '">−</button>';

      var input = row.querySelector('[data-chapter-title]');
      if (input && value) input.value = String(value);

      list.appendChild(row);
      renumberChapters();
      return row;
    }

    function chapterTitles() {
      return Array.prototype.map.call(
        form.querySelectorAll('[data-chapters] [data-chapter-title]'),
        function (input) { return input.value; }
      );
    }

    function setChapters(list) {
      var box = form.querySelector('[data-chapters]');
      if (!box) return;
      box.innerHTML = '';
      (list || []).forEach(function (title) { addChapter(title); });
      renumberChapters();
    }

    function initChapters() {
      if (!form.querySelector('[data-chapters]')) return;
      renumberChapters();

      var addBtn = form.querySelector('[data-chapter-add]');
      if (addBtn) {
        addBtn.addEventListener('click', function () {
          var row = addChapter('');
          var input = row && row.querySelector('[data-chapter-title]');
          if (input && input.focus) input.focus();
          markDirty();
        });
      }

      // Удаление делегированием: строки пересоздаются при восстановлении
      form.addEventListener('click', function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('[data-chapter-remove]') : null;
        if (!btn) return;
        var row = btn.closest('[data-chapter]');
        if (row && row.parentNode) row.parentNode.removeChild(row);
        renumberChapters();
        markDirty();
      });
    }

    // --------------------------------------------------------
    // МНОГОШАГОВОСТЬ (FORM-01): «Далее» не пускает дальше, пока
    // обязательные поля шага не прошли валидацию
    // --------------------------------------------------------
    function initSteps() {
      var steps = Array.prototype.slice.call(form.querySelectorAll('.book-step'));
      if (!steps.length) {
        return { current: function () { return 1; }, goTo: function () {}, reveal: function () {} };
      }

      var total = steps.length;
      var current = 1;

      function show(n) {
        n = Math.min(Math.max(n, 1), total);
        steps.forEach(function (step) {
          step.hidden = parseInt(step.getAttribute('data-step'), 10) !== n;
        });
        current = n;

        // Нижняя панель (11.4): «Назад» скрыта на первом шаге
        Array.prototype.forEach.call(
          form.querySelectorAll('.book-form__actions [data-step-prev]'),
          function (btn) { btn.hidden = n <= 1; }
        );

        if (window.history && history.replaceState) {
          history.replaceState(null, '', '#step-' + n);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }

      function validateStep(n) {
        var ok = true;
        (STEP_FIELDS[n] || []).forEach(function (name) {
          if (!validateField(name)) ok = false;
        });
        return ok;
      }

      Array.prototype.forEach.call(form.querySelectorAll('[data-step-next]'), function (btn) {
        btn.addEventListener('click', function () {
          if (!validateStep(current)) {
            showFieldErrors();
            focusFirstInvalid();
            return;
          }
          show(current + 1);
        });
      });

      Array.prototype.forEach.call(form.querySelectorAll('[data-step-prev]'), function (btn) {
        btn.addEventListener('click', function () { show(current - 1); });
      });

      var match = location.hash.match(/^#step-(\d+)$/);
      show(match ? parseInt(match[1], 10) : 1);

      return {
        current: function () { return current; },
        goTo: function (n) { show(n); },
        reveal: function (stepEl) {
          var n = parseInt(stepEl.getAttribute('data-step'), 10);
          if (n) show(n);
        },
      };
    }

    // --------------------------------------------------------
    // ЛОКАЛЬНАЯ КОПИЯ НЕСОХРАНЁННЫХ ИЗМЕНЕНИЙ
    // (плашка «Восстановить / Отбросить» на шаге 1: например, открыл
    //  создание новой книги, не сохранив прошлые изменения)
    // --------------------------------------------------------
    var scheduleBackup = debounce(writeBackup, 800);

    // Текстовые поля и селекты, участвующие в копии. Файлы (обложка,
    // файлы книги) и списки людей в копию не попадают — их браузер
    // восстановить не может.
    var BACKUP_FIELDS = ['title', 'description', 'authorNotes', 'publisher', 'pages', 'isbn', 'genreId', 'categoryId'];

    function markDirty() {
      dirty = true;
      scheduleBackup();
    }

    function snapshot() {
      var snap = { savedAt: Date.now(), chapters: chapterTitles() };
      BACKUP_FIELDS.forEach(function (name) {
        var el = getField(name);
        snap[name] = el ? (el.value || '') : '';
      });
      return snap;
    }

    /**
     * Есть ли в копии осмысленное содержимое. Нужно, чтобы:
     *  - не плодить пустые копии (dirty ставит и рендер ошибок через
     *    MutationObserver — без реальных правок писать нечего);
     *  - не спрашивать «выйти без сохранения?» на нетронутой форме.
     */
    function hasContent(snap) {
      if (!snap) return false;
      return BACKUP_FIELDS.some(function (name) {
        return String(snap[name] || '').trim() !== '';
      }) || (snap.chapters || []).some(function (title) { return String(title).trim() !== ''; });
    }

    function writeBackup() {
      if (editId) return;              // локальная копия — только для создания
      if (!dirty) return;
      if (!hasContent(snapshot())) { clearBackup(); return; }
      try { localStorage.setItem(BACKUP_KEY, JSON.stringify(snapshot())); } catch (_) {}
    }

    function clearBackup() {
      if (editId) return;              // не трогаем копию создания книги
      try { localStorage.removeItem(BACKUP_KEY); } catch (_) {}
    }

    function readBackup() {
      try {
        var raw = localStorage.getItem(BACKUP_KEY);
        return raw ? JSON.parse(raw) : null;
      } catch (_) { return null; }
    }

    function applyBackup(b) {
      if (!b) return;

      BACKUP_FIELDS.forEach(function (name) {
        if (b[name] === undefined || b[name] === null) return;
        var el = getField(name);
        if (!el) return;

        el.value = String(b[name]);

        // Справочники (genreId/categoryId): перерисовать выбор, если список
        // уже загружен; иначе renderList подхватит значение при готовности.
        // Значение передаём аргументом: у select присваивание несуществующего
        // option молча сбрасывает value в '' — рендер держит его сам.
        var rerender = catalogRenderers[name];
        if (rerender) rerender(String(b[name]));
      });

      if (Object.prototype.hasOwnProperty.call(b, 'chapters')) setChapters(b.chapters || []);

      dirty = true;
      scheduleBackup();
    }

    function initRestore() {
      var box = form.querySelector('[data-restore]');
      if (!box) return;

      var b = readBackup();
      if (!b) return;

      var cur = snapshot();
      var same = BACKUP_FIELDS.every(function (name) {
        return String(b[name] || '') === String(cur[name] || '');
      }) && JSON.stringify(b.chapters || []) === JSON.stringify(cur.chapters || []);

      // Копия пустая или совпадает с тем, что уже в форме — плашка не нужна
      if (same || !hasContent(b)) { clearBackup(); return; }

      box.hidden = false;

      var apply = box.querySelector('[data-restore-apply]');
      var discard = box.querySelector('[data-restore-discard]');
      if (apply) {
        apply.addEventListener('click', function () {
          applyBackup(b);
          box.hidden = true;
        });
      }
      if (discard) {
        discard.addEventListener('click', function () {
          clearBackup();
          box.hidden = true;
        });
      }
    }

    /** Уход со страницы с несохранёнными изменениями: копия + подтверждение. */
    function onBeforeUnload(e) {
      // только если реально что-то введено (dirty бывает и без правок —
      // например, отрендеренные ошибки валидации)
      if (!dirty || !hasContent(snapshot())) return;
      writeBackup();
      e.preventDefault();
      e.returnValue = '';
      return '';
    }

    // --------------------------------------------------------
    // ПОДКЛЮЧЕНИЕ: шаги, главы, отметки об изменениях
    // --------------------------------------------------------
    initChapters();
    stepsApi = initSteps();

    form.addEventListener('input', markDirty);
    form.addEventListener('change', markDirty);

    // Списки людей и глав меняются через DOM (без input/change) —
    // ловим эти правки наблюдателем; лишние события отсекает
    // сравнение копии с формой в initRestore.
    if (window.MutationObserver) {
      new MutationObserver(function () { markDirty(); })
        .observe(form, { subtree: true, childList: true, characterData: true });
    }

    // Редирект после успешного создания (redirect: url) уходит со
    // страницы — тогда защита «несохранённых изменений» не нужна.
    // Событие оставляем как расширяемый хук для плагинов страницы.
    window.addEventListener('bookform:leave', function () { dirty = false; });

    window.addEventListener('beforeunload', onBeforeUnload);
    initRestore();
  }

  document.querySelectorAll('form[data-book-form]').forEach(initForm);
})();