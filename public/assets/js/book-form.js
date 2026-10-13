/**
 * book-form.js — страница создания книги (src/views/pages/book/book-form.php).
 *
 * Что делает:
 *   1. превью обложки (object URL) + кнопка «убрать»;
 *   2. имя и размер выбранного PDF + кнопка «убрать»;
 *   3. наполнение <select> жанров и категорий из /api/additional/*;
 *   4. клиентскую валидацию (RULES ниже) до отправки;
 *   5. отправку через fetch + FormData (multipart, файлы летят без чтения
 *      в JS). Без JS форма уходит обычным POST — прогрессивное улучшение.
 *
 * Контракт с бэком — в шапке book-form.php: POST /books/create,
 * Accept: application/json → 201 { data: { id, redirect } } либо
 * 422 { errors: { field: [msg] } }.
 */
(function () {
  'use strict';

  var COVER_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB  (как Uploader::MAX_BYTES)
  var PDF_MAX_BYTES   = 50 * 1024 * 1024;  // 50 MB
  var COVER_MIMES     = ['image/jpeg', 'image/png', 'image/webp'];

  // Источники для <select data-dynamic-select="...">.
  var SOURCES = {
    genres:     '/api/additional/genres',
    categories: '/api/additional/categories'
  };

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
     PDF: имя + размер файла
     ============================================================ */
  function initFile(form) {
    var input   = form.querySelector('[data-file-input]');
    var meta    = form.querySelector('[data-file-meta]');
    var nameEl  = form.querySelector('[data-file-name]');
    var sizeEl  = form.querySelector('[data-file-size]');
    var removeBtn = form.querySelector('[data-file-remove]');
    if (!input || !meta) return null;

    function clearMeta() {
      meta.hidden = true;
      if (nameEl) nameEl.textContent = '';
      if (sizeEl) sizeEl.textContent = '';
      if (removeBtn) removeBtn.hidden = true;
    }

    /** Возвращает сообщение об ошибке или null, если файл валиден. */
    return function () {
      var file = input.files && input.files[0];

      if (!file) { clearMeta(); return null; }

      var isPdf = file.type === 'application/pdf' || /\.pdf$/i.test(file.name || '');
      if (!isPdf) {
        clearMeta();
        input.value = '';
        return t('js.rule_book_file');
      }
      if (file.size > PDF_MAX_BYTES) {
        clearMeta();
        input.value = '';
        return t('js.rule_book_file_size');
      }

      if (nameEl) nameEl.textContent = file.name || '';
      if (sizeEl) sizeEl.textContent = formatSize(file.size);
      meta.hidden = false;
      if (removeBtn) removeBtn.hidden = false;
      return null;
    };
  }

  /* ============================================================
     ФОРМА
     ============================================================ */
  function initForm(form) {
    var FIELD_KEYS = ['title', 'author', 'annotation', 'genre', 'category', 'series', 'isbn', 'cover', 'file'];

    var RULES = {
      title:      { required: true,  maxLength: 200,  msgKey: 'js.rule_book_title' },
      author:     { required: true,  maxLength: 120,  msgKey: 'js.rule_book_author' },
      annotation: { required: false, maxLength: 5000, msgKey: 'js.rule_book_annotation' },
      genre:      { required: true,  select: true,    msgKey: 'js.rule_book_genre' },
      category:   { required: true,  select: true,    msgKey: 'js.rule_book_category' },
      series:     { required: false, maxLength: 120,  msgKey: 'js.rule_book_series' },
      isbn:       { required: false, maxLength: 20,   custom: 'isbn', msgKey: 'js.isbn_shape' },
      cover:      { required: false, file: 'cover',   msgKey: 'js.rule_book_cover' },
      file:       { required: true,  file: 'file',    msgKey: 'js.rule_book_file_required' },
    };

    var fileValidators = {
      cover: initCover(form),
      file:  initFile(form),
    };

    function getField(name)   { return form.querySelector('[name="' + name + '"]'); }
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
      field.addEventListener('blur',  function () { validateField(name); });
      field.addEventListener('input', function () {
        if (field.classList.contains('form-field__input--invalid')) validateField(name);
      });
      field.addEventListener('change', function () { validateField(name); });
    });

    // Кнопки «убрать обложку / убрать файл»
    var coverRemove = form.querySelector('[data-cover-remove]');
    if (coverRemove) {
      coverRemove.addEventListener('click', function () {
        var coverInput = getField('cover');
        if (coverInput) coverInput.value = '';
        validateField('cover');
      });
    }
    var fileRemove = form.querySelector('[data-file-remove]');
    if (fileRemove) {
      fileRemove.addEventListener('click', function () {
        var fileInput = getField('file');
        if (fileInput) fileInput.value = '';
        validateField('file');
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
            if (response.ok && !/^\s*</.test(raw)) {
              notify(t('js.bookform_saved'), 'success');
              var payload = (data && data.data) || data || {};
              var redirect = payload.redirect
                || (payload.id ? '/books/' + payload.id : null)
                || (response.headers.get('Location') || '/books');
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
