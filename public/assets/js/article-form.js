/**
 * article-form.js — страница создания статьи
 * (src/views/pages/article/article-form.php).
 *
 * Что делает:
 *   1. превью обложки (object URL) + кнопка «убрать»;
 *   2. переключение типа статьи (content ↔ book) с показом нужных полей;
 *   3. наполнение <select> жанров из /api/additional/genres;
 *   4. ошибки сервера 422 -> в слоты [data-error-for] (как в book-form);
 *   5. отправку через fetch + FormData (multipart, файлы летят без чтения
 *      в JS). Без JS форма уходит обычным POST — прогрессивное улучшение.
 *
 * Контракт с бэком — в шапке article-form.php: POST /articles/store
 * (роута ПОКА НЕТ, action="#"), Accept: application/json ->
 * 201 { data: { id, redirect } } либо 422 { errors: { field: [msg] } }.
 *
 * Проверок прав нет: бэк ещё не назвал финальные имена разрешений.
 */
(function () {
  'use strict';

  var COVER_MAX_BYTES = 5 * 1024 * 1024;   // 5 MB (как у книги)
  var COVER_MIMES = ['image/jpeg', 'image/png', 'image/webp'];
  var GENRES_URL = '/api/additional/genres';

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

  var form = document.querySelector('[data-article-form]');
  if (!form) return;

  var getField   = function (name) { return form.querySelector('[name="' + name + '"]'); };
  var getErrorEl = function (name) { return form.querySelector('[data-error-for="' + name + '"]'); };

  function setError(name, message) {
    var field = getField(name);
    var errorEl = getErrorEl(name);
    if (field) field.classList.add('form-field__input--invalid');
    if (errorEl) {
      errorEl.innerHTML = '<span class="field-error-icon" title="' + escapeHtml(message) + '">!</span>';
    }
  }

  function clearError(name) {
    var field = getField(name);
    var errorEl = getErrorEl(name);
    if (field) field.classList.remove('form-field__input--invalid');
    if (errorEl) errorEl.innerHTML = '';
  }

  /* ============================================================
     1. ПРЕВЬЮ ОБЛОЖКИ
     ============================================================ */
  function initCover() {
    var upload = form.querySelector('[data-cover-upload]');
    if (!upload) return;

    var input   = upload.querySelector('[data-cover-input]');
    var preview = upload.querySelector('[data-cover-preview]');
    var ph      = upload.querySelector('[data-cover-placeholder]');
    var remove  = upload.querySelector('[data-cover-remove]');
    var state   = upload.querySelector('[data-file-button-state]');
    var objectUrl = null;

    function reset() {
      if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
      input.value = '';
      preview.src = '';
      preview.hidden = true;
      ph.hidden = false;
      if (remove) remove.hidden = true;
      if (state) state.textContent = t('common.articleform.file_none');
      clearError('cover');
    }

    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      if (!file) { reset(); return; }

      if (COVER_MIMES.indexOf(file.type) === -1 || file.size > COVER_MAX_BYTES) {
        notify(t('common.articleform.cover_hint'));
        reset();
        return;
      }

      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = URL.createObjectURL(file);
      preview.src = objectUrl;
      preview.hidden = false;
      ph.hidden = true;
      if (remove) remove.hidden = false;
      if (state) state.textContent = file.name;
      clearError('cover');
    });

    if (remove) remove.addEventListener('click', reset);
  }

  /* ============================================================
     2. ТИП СТАТЬИ: content (свой текст) / book (отрывок)
     ============================================================ */
  function initType() {
    var group = form.querySelector('[data-article-type]');
    if (!group) return;

    var sections = form.querySelectorAll('[data-article-when]');

    function apply(value) {
      sections.forEach(function (section) {
        var show = section.getAttribute('data-article-when') === value;
        section.hidden = !show;
        // Поля скрытого раздела не required и не участвуют в POST:
        // без этого бэк по articles.type_consistency получил бы и текст,
        // и id книги одновременно.
        section.querySelectorAll('input, textarea').forEach(function (el) {
          el.disabled = !show;
          if (!show) el.required = false;
        });
      });
    }

    function current() {
      var checked = group.querySelector('input[name="type"]:checked');
      return checked ? checked.value : 'content';
    }

    group.addEventListener('change', function () { apply(current()); });
    apply(current());
  }

  /* ============================================================
     3. ЖАНРЫ: GET /api/additional/genres (пагинация — как в filters.js)
     ============================================================ */
  function initGenres() {
    var select = form.querySelector('[data-dynamic-select="genres"]');
    if (!select) return;

    var selected = select.getAttribute('data-selected') || '';

    fetch(GENRES_URL + '?page=1', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    })
      .then(function (res) { return res.ok ? res.json() : null; })
      .then(function (data) {
        if (!data) return;
        var items = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
        if (!items.length) return;

        var placeholder = select.querySelector('option[disabled]');
        select.innerHTML = '';
        items.forEach(function (entry) {
          var id = String(entry.id === undefined ? (entry.value || '') : entry.id);
          var title = String(entry.title || entry.label || id);
          var option = document.createElement('option');
          option.value = id;
          option.textContent = title;
          if (id === selected) option.selected = true;
          select.appendChild(option);
        });

        if (!selected && placeholder) {
          // Без выбора: возвращаем первым пунктом плейсхолдер.
          var ph = document.createElement('option');
          ph.value = '';
          ph.textContent = placeholder.textContent;
          ph.disabled = true;
          ph.selected = true;
          select.insertBefore(ph, select.firstChild);
        }
      })
      .catch(function (err) { console.warn('[article-form] genres load failed', err); });
  }

  /* ============================================================
     4. ВАЛИДАЦИЯ ДО ОТПРАВКИ
     ============================================================ */
  function currentType() {
    var checked = form.querySelector('input[name="type"]:checked');
    return checked ? checked.value : 'content';
  }

  function validate() {
    var ok = true;
    ['title', 'genre', 'content', 'book_id', 'page_start', 'page_end', 'doi'].forEach(clearError);

    var title = getField('title');
    if (title && title.value.trim() === '') { setError('title', t('common.articleform.title_required')); ok = false; }

    var genre = getField('genre');
    if (genre && genre.value === '') { setError('genre', t('common.articleform.genre_required')); ok = false; }

    var type = currentType();
    if (type === 'content') {
      var content = getField('content');
      if (content && content.value.trim() === '') { setError('content', t('common.articleform.content_required')); ok = false; }
    } else {
      var bookId = getField('book_id');
      if (bookId && String(bookId.value).trim() === '') { setError('book_id', t('common.articleform.book_required')); ok = false; }

      ['page_start', 'page_end'].forEach(function (name) {
        var field = getField(name);
        if (field && String(field.value).trim() === '') { setError(name, t('common.articleform.pages_required')); ok = false; }
      });

      var from = getField('page_start'), to = getField('page_end');
      if (from && to && from.value !== '' && to.value !== '' && Number(to.value) < Number(from.value)) {
        setError('page_end', t('common.articleform.pages_order'));
        ok = false;
      }
    }

    // DOI необязательный, но если задан — формат 10.<digits>/<digits>
    var doi = getField('doi');
    if (doi && doi.value.trim() !== '' && !/^10\.\d+\/\d+$/.test(doi.value.trim())) {
      setError('doi', t('js.doi_hint'));
      ok = false;
    }

    return ok;
  }

  /* ============================================================
     5. ОТПРАВКА: fetch + FormData (без JS — обычный POST)
     ============================================================ */
  function renderServerErrors(errors) {
    if (!errors || typeof errors !== 'object') return;
    Object.keys(errors).forEach(function (field) {
      var msgs = errors[field];
      var first = Array.isArray(msgs) ? msgs[0] : msgs;
      var message = first && typeof first === 'object' ? (first.message || '') : String(first);
      if (getErrorEl(field)) setError(field, message);
    });
    var firstInvalid = form.querySelector('.form-field__input--invalid');
    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid.focus();
    }
  }

  function initSubmit() {
    form.addEventListener('submit', function (e) {
      var action = (form.getAttribute('action') || '').trim();

      // Роут articles.store ещё не добавлен (action="#") — запрос делать
      // некуда: честно сообщаем, что сохранение пока недоступно.
      // TODO(роуты): убрать ветку, когда action станет /articles/store.
      if (action === '' || action === '#') {
        e.preventDefault();
        console.warn('[article-form] POST skipped: articles.store route is not added yet');
        return;
      }

      e.preventDefault();
      if (!validate()) {
        var firstInvalid = form.querySelector('.form-field__input--invalid');
        if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstInvalid.focus();
        }
        return;
      }

      var submitBtn = form.querySelector('button[type="submit"]');
      var originalText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = t('js.saving'); }

      function resetButton() {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
      }

      // FormData(form) — файлы уходят как есть, без чтения в JS.
      var data = new FormData(form);
      if (window.LoreCsrf) data.set('_token', LoreCsrf.token());

      var fetchImpl = window.LoreCsrf ? LoreCsrf.fetch : fetch;

      fetchImpl(action, {
        method: 'POST',
        body: data,
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
              var redirect = (data && data.data && data.data.redirect) || '/articles';
              window.location.href = redirect;
              return;
            }

            if (response.status === 422 && data) {
              renderServerErrors(data.errors
                || (data.error && typeof data.error === 'object' ? data.error.details : null));
            }

            var fallback = t('js.request_failed', { status: response.status });
            notify(window.Messages
              ? window.Messages.describe(response.status, data, raw, fallback)
              : fallback);
            resetButton();
          });
        })
        .catch(function (err) {
          console.error('[article-form] save failed for', action, err);
          resetButton();
          notify(t('js.network_error'));
        });
    });
  }

  /* ============================================================
     Ошибки, пришедшие с сервера после редиректа/422 ($errors в view)
     ============================================================ */
  function notifyInitialErrors() {
    var dirty = (form.getAttribute('data-initial-errors') || '').split(',');
    var list = [];
    dirty.forEach(function (name) {
      if (!name) return;
      var el = getErrorEl(name);
      if (!el) return;
      var icon = el.querySelector('.field-error-icon');
      var text = ((icon && icon.getAttribute('title')) || el.textContent).trim();
      if (text !== '') list.push(text);
      var field = getField(name);
      if (field) field.classList.add('form-field__input--invalid');
    });
    if (list.length) notify(list.join('\n'));
  }

  initCover();
  initType();
  initGenres();
  initSubmit();
  notifyInitialErrors();
})();
