/**
 * admin.js — действия админки на страницах src/views/pages/admin/*.
 *
 * Разделы:
 *   1. Пользователи/комментарии — ConfirmModal.confirm() перед действием,
 *      без запросов (реального API админки ещё нет, см. TODO(api)).
 *   2. Справочники (Genres / Categories / Types) — модалка
 *      «Добавить/Редактировать» + удаление через ConfirmModal, работа
 *      ведётся по мок-массиву страницы (script[type="application/json"]
 *      [data-dict-data], partial dictionary-table); список языков для
 *      блока Translations грузится из GET /api/additional/languages,
 *      при недоступности — встроенный мок.
 *   3. Клиентский поиск по таблице (data-search у строки — все переводы).
 *
 * ВАЖНО про API модалки (src/partials/confirm-modal.php + js/modal.js):
 *   ConfirmModal.confirm({ title, message (алиас text), confirmText,
 *                          cancelText, danger }) -> Promise<boolean>
 * колбэка onConfirm у неё НЕТ — действие выполняем в .then(confirmed).
 *
 * Проверок прав здесь нет намеренно: бэк ещё не назвал финальные имена
 * разрешений (canPublication, canManagePublication и т.п.).
 */
(function () {
  'use strict';

  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  var confirmModal = function (options) {
    return window.ConfirmModal ? ConfirmModal.confirm(options) : Promise.resolve(false);
  };

  var notify = function (text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'success' });
    else console.warn(text);
  };

  /* ============================================================
     BLOCK USER — кнопка «Block» в таблице пользователей
     ============================================================ */
  function blockUser(btn) {
    var id   = btn.getAttribute('data-user-id') || '';
    var name = btn.getAttribute('data-user-name') || '';

    confirmModal({
      title:       t('admin.block_title'),
      text:        t('admin.block_text'),
      confirmText: t('admin.block_confirm'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): заменить на fetch('PATCH /api/admin/users/' + id,
      // headers: { 'X-CSRF-Token': LoreCsrf.token(), Accept: 'application/json' })
      // — роута админки пока нет (config/routes.php заморожен).
      console.log('Block user', id, name);

      var row = btn.closest('tr');
      // Ячейка статуса помечена в разметке (users.php), а не по индексу:
      // лишняя колонка в таблице не должна ломать обновление.
      var statusCell = row && row.querySelector('[data-status-cell]');
      if (statusCell) {
        statusCell.innerHTML =
          '<span class="admin-badge admin-badge--danger">' +
          t('admin.status_blocked') +
          '</span>';
      }
      btn.disabled = true;
      notify(t('admin.block_success'), 'success');
    });
  }

  /* ============================================================
     DELETE COMMENT — кнопка «Delete» в таблице комментариев
     ============================================================ */
  function deleteComment(btn) {
    var id = btn.getAttribute('data-comment-id') || '';

    confirmModal({
      title:       t('admin.delete_title'),
      text:        t('admin.delete_text'),
      confirmText: t('admin.action_delete'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): fetch('DELETE /api/admin/comments/' + id) — API ещё нет.
      console.log('Delete comment', id);

      var row = btn.closest('tr');
      if (row) row.remove();
    });
  }

  /* ============================================================
     DICTIONARY — модалка «Добавить/Редактировать» и удаление записей
     справочников (Genres / Categories / Types).

     Источник правды — мок-массив страницы ([data-dict-data]); строки
     таблицы перерисовываются из него, поэтому add/edit/delete сразу
     видны в UI (включая счётчик и пустое состояние поиска).

     Структура записи:
       { id: 1, translations: [{ langCode: 'en', title: 'Fantasy' }, ...] }
     — массив переводов, а не объект с фиксированными ключами: новый
     язык не требует изменений ни на фронте, ни в схеме.

     TODO(api): POST/PATCH/DELETE /api/admin/{resource}[/{id}], тело
     { translations: [{ lang_code, title }, ...] } — роутов админки ещё
     нет (config/routes.php и app/ заморожены), поэтому мутации идут по
     мок-массиву.
     ============================================================ */
  var dict = {
    modal: null,
    form: null,
    rows: null,
    list: null,
    menu: null,
    error: null,
    status: null,
    emptyHint: null,
    titleEl: null,
    resource: '',
    nameOne: '',
    items: [],
    editId: null,
    draft: [],
    languages: null,
    languagesLoading: null,
    submitting: false,
    lastFocus: null
  };

  /** Fallback-список, когда GET /api/additional/languages недоступен. */
  var FALLBACK_LANGUAGES = [
    { code: 'en', name: 'English' },
    { code: 'ru', name: 'Русский' },
    { code: 'es', name: 'Español' }
  ];

  function esc(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function currentLang() {
    return String(window.LORE_LOCALE || document.documentElement.lang || 'en');
  }

  /** Название языка по коду: Intl.DisplayNames (без хардкода en/ru). */
  function langName(code, fallback) {
    var value = String(code || '');
    if (!value) return '';
    try {
      var names = new Intl.DisplayNames([LoreI18n ? LoreI18n.locale : currentLang()], { type: 'language' });
      var named = names.of(value);
      if (named) return named;
    } catch (_) { /* ICU недоступен — уходим на fallback */ }
    return fallback || value.toUpperCase();
  }

  function translationTitles(item) {
    var translations = (item && item.translations) || [];
    return translations
      .map(function (row) { return String((row && row.title) || '').trim(); })
      .filter(function (title) { return title !== ''; });
  }

  function findItem(id) {
    for (var i = 0; i < dict.items.length; i++) {
      if (Number(dict.items[i].id) === id) return dict.items[i];
    }
    return null;
  }

  function setError(text) {
    if (!dict.error) return;
    dict.error.textContent = text || '';
    dict.error.hidden = !text;
  }

  function setStatus(text) {
    if (!dict.status) return;
    dict.status.textContent = text || '';
    dict.status.hidden = !text;
  }

  /** Рендер строк таблицы из мок-массива (и повторный прогон поиска). */
  function renderRows() {
    if (!dict.rows) return;
    var emptyRow = dict.rows.querySelector('[data-admin-empty]');

    dict.rows.innerHTML = dict.items.map(function (item) {
      var id = Number(item.id) || 0;
      var titles = translationTitles(item);
      // data-search — все переводы сразу: поиск ищет и по «Fantasy», и по «Фэнтези»
      var search = titles.join(' ').toLowerCase();

      return '<tr data-dict-row-id="' + id + '" data-search="' + esc(search) + '">' +
          '<td class="admin-table__id">' + id + '</td>' +
          '<td><span class="admin-dict__translations">' + esc(titles.join(' / ')) + '</span></td>' +
          '<td><div class="admin-actions">' +
            '<button type="button" class="btn btn--secondary" data-admin-dict-edit data-dict-row-id="' + id + '">' +
              esc(t('admin.action_edit')) +
            '</button>' +
            '<button type="button" class="btn btn--danger" data-admin-dict-delete data-dict-row-id="' + id + '">' +
              esc(t('admin.action_delete')) +
            '</button>' +
          '</div></td>' +
        '</tr>';
    }).join('');

    // Заглушка пустого состояния живёт отдельным узлом — сохраняем её
    if (emptyRow) dict.rows.appendChild(emptyRow);
    applySearch();
  }

  /* ---------- Блок Translations в модалке ---------- */

  function renderLangs() {
    if (!dict.list) return;

    dict.list.innerHTML = dict.draft.map(function (row, index) {
      var code = String(row.langCode || '');
      var name = langName(code);

      return '<li class="admin-dict__lang-row" data-lang="' + esc(code) + '" data-lang-index="' + index + '">' +
          '<span class="admin-badge admin-dict__lang-code" title="' + esc(name) + '">' + esc(code) + '</span>' +
          '<div class="form-field admin-dict__lang-field">' +
            '<input class="form-field__input" type="text" data-lang-title autocomplete="off"' +
              ' value="' + esc(row.title) + '"' +
              ' placeholder="' + esc(t('admin.col_title')) + '"' +
              ' aria-label="' + esc(name + ': ' + t('admin.col_title')) + '">' +
          '</div>' +
          '<button type="button" class="admin-dict__lang-remove" data-dict-lang-remove' +
            ' title="' + esc(t('admin.dict_lang_remove')) + '"' +
            ' aria-label="' + esc(t('admin.dict_lang_remove')) + '">&times;</button>' +
        '</li>';
    }).join('');

    if (dict.emptyHint) dict.emptyHint.hidden = dict.draft.length > 0;
    renderLangMenu();
  }

  /** Пункты дропдауна: только ещё НЕ добавленные языки (дубликаты скрыты). */
  function renderLangMenu() {
    if (!dict.menu) return;

    var used = dict.draft.map(function (row) { return String(row.langCode || '').toLowerCase(); });
    var all = dict.languages || [];
    var free = all.filter(function (lang) {
      return used.indexOf(String(lang.code).toLowerCase()) === -1;
    });

    if (!all.length) {
      dict.menu.innerHTML = '<li class="admin-dict__menu-note">' + esc(t('admin.dict_lang_none')) + '</li>';
      return;
    }
    if (!free.length) {
      dict.menu.innerHTML = '<li class="admin-dict__menu-note">' + esc(t('admin.dict_lang_all_added')) + '</li>';
      return;
    }

    dict.menu.innerHTML = free.map(function (lang) {
      var name = lang.name || langName(lang.code);
      return '<li><button type="button" class="dropdown__item" data-dict-lang-pick="' + esc(lang.code) + '">' +
        esc(name) + ' (' + esc(lang.code) + ')' +
        '</button></li>';
    }).join('');
  }

  /**
   * Список языков: GET /api/additional/languages (формат ResponseTemplates
   * ::list: { items: [{ code, isActive }] }, поэтому имя языка берём из
   * Intl.DisplayNames по коду). Ошибка/пусто → встроенный мок + console.warn.
   */
  function loadLanguages() {
    if (dict.languages) return Promise.resolve(dict.languages);
    if (dict.languagesLoading) return dict.languagesLoading;

    setStatus(t('admin.dict_lang_loading'));

    dict.languagesLoading = fetch('/api/additional/languages?page=1&ps=50', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (res) {
      return res.text().then(function (raw) {
        // HTML вместо JSON (редирект на логин и т.п.) — тоже недоступность
        if (!res.ok || /^\s*</.test(raw)) throw new Error('HTTP ' + res.status);
        var data = raw ? JSON.parse(raw) : null;
        var list = (data && Array.isArray(data.items)) ? data.items
                 : (Array.isArray(data) ? data : []);
        var languages = list.map(function (entry) {
          return {
            code: String((entry && (entry.code || entry.langCode || entry.value)) || ''),
            name: entry && entry.name ? String(entry.name) : ''
          };
        }).filter(function (entry) { return entry.code !== ''; });

        if (!languages.length) throw new Error('empty languages list');
        return languages;
      });
    }).then(function (languages) {
      dict.languages = languages;
      return languages;
    }, function (err) {
      // TODO(api): GET /api/additional/languages — пока список недоступен
      // (или пуст), берём мок [{code, name}, …] и не блокируем форму.
      console.warn('[admin] GET /api/additional/languages failed, using fallback', err);
      dict.languages = FALLBACK_LANGUAGES.slice();
      return dict.languages;
    }).then(function (languages) {
      dict.languagesLoading = null;
      setStatus('');
      renderLangMenu();
      return languages;
    });

    return dict.languagesLoading;
  }

  function addLanguage(code) {
    var value = String(code || '').trim();
    if (!value) return;
    var duplicate = dict.draft.some(function (row) {
      return String(row.langCode || '').toLowerCase() === value.toLowerCase();
    });
    if (duplicate) return;

    dict.draft.push({ langCode: value, title: '' });
    setError('');
    renderLangs();

    // Новое поле — сразу фокус в него
    var rows = dict.list.querySelectorAll('[data-lang-index]');
    var last = rows[rows.length - 1];
    var input = last && last.querySelector('[data-lang-title]');
    if (input) input.focus();
  }

  function removeLanguage(btn) {
    var row = btn.closest('[data-lang-index]');
    var index = row ? Number(row.getAttribute('data-lang-index')) : -1;
    if (index < 0 || !dict.draft[index]) return;
    dict.draft.splice(index, 1);
    renderLangs();
  }

  /* ---------- Открытие/закрытие модалки ---------- */

  function openDictForm(id) {
    if (!dict.modal) return;

    dict.editId = id ? Number(id) : null;
    var item = dict.editId ? findItem(dict.editId) : null;

    dict.draft = item
      ? (item.translations || []).map(function (row) {
          return { langCode: String(row.langCode || ''), title: String(row.title || '') };
        })
      // Новая запись: начинаем с языка интерфейса, остальные — «+ Add language»
      : [{ langCode: currentLang(), title: '' }];

    if (dict.titleEl) {
      dict.titleEl.textContent = item
        ? t('admin.dict_edit_title', { name: dict.nameOne })
        : t('admin.dict_add_title', { name: dict.nameOne });
    }

    setError('');
    renderLangs();

    dict.lastFocus = document.activeElement;
    dict.modal.hidden = false;
    document.body.classList.add('is-modal-open');

    var first = dict.list ? dict.list.querySelector('[data-lang-title]') : null;
    if (first) { first.focus(); try { first.select(); } catch (_) {} }

    loadLanguages();
  }

  function closeDictForm() {
    if (!dict.modal || dict.modal.hidden) return;

    dict.modal.hidden = true;
    document.body.classList.remove('is-modal-open');

    var dropdown = dict.modal.querySelector('[data-dropdown].is-open');
    if (dropdown) dropdown.classList.remove('is-open');

    var target = dict.lastFocus;
    dict.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) { /* фокус необязателен */ }
    }
  }

  function isDictOpen() { return !!(dict.modal && !dict.modal.hidden); }

  /* ---------- Сохранение и удаление (по мок-массиву) ---------- */

  function saveDictForm() {
    // Пустые переводы не отправляем; валидация — хотя бы один заполненный
    var filled = dict.draft
      .map(function (row) {
        return {
          langCode: String(row.langCode || '').trim(),
          title: String(row.title || '').trim()
        };
      })
      .filter(function (row) { return row.langCode !== '' && row.title !== ''; });

    if (!filled.length) {
      setError(t('admin.dict_validation'));
      return;
    }
    setError('');

    if (dict.submitting) return;
    dict.submitting = true;

    // TODO(api): fetch('POST /api/admin/' + dict.resource
    //           или 'PATCH /api/admin/' + dict.resource + '/' + dict.editId,
    //           body: { translations: filled } — как { lang_code, title },
    //           headers: { 'X-CSRF-Token': LoreCsrf.token(), Accept: 'application/json' })
    if (dict.editId) {
      var item = findItem(dict.editId);
      if (item) item.translations = filled;
    } else {
      var nextId = dict.items.reduce(function (max, row) {
        return Math.max(max, Number(row.id) || 0);
      }, 0) + 1;
      dict.items.push({ id: nextId, translations: filled });
    }

    dict.submitting = false;
    renderRows();
    closeDictForm();
    notify(t('admin.dict_saved'), 'success');
  }

  function deleteDictItem(btn) {
    var id = Number(btn.getAttribute('data-dict-row-id') || 0);

    confirmModal({
      title:       t('admin.dict_delete_title'),
      text:        t('admin.dict_delete_text'),
      confirmText: t('admin.action_delete'),
      danger:      true,
    }).then(function (confirmed) {
      if (!confirmed) return;

      // TODO(api): fetch('DELETE /api/admin/' + dict.resource + '/' + id) — API ещё нет.
      dict.items = dict.items.filter(function (item) { return Number(item.id) !== id; });
      renderRows();
      notify(t('admin.dict_deleted'), 'success');
    });
  }

  /* ---------- Инициализация страницы справочника ---------- */

  function initDictionary() {
    var dataEl = document.querySelector('[data-dict-data]');
    var table  = document.querySelector('[data-dict-table]');
    var modal  = document.querySelector('[data-dict-modal]');
    if (!dataEl || !table || !modal) return;

    var payload = null;
    try {
      payload = JSON.parse(dataEl.textContent || '{}');
    } catch (err) {
      console.warn('[admin] dictionary payload is not valid JSON', err);
    }
    if (!payload) return;

    dict.rows = table.querySelector('[data-dict-rows]');
    dict.modal = modal;
    dict.form = modal.querySelector('[data-dict-form]');
    dict.list = modal.querySelector('[data-dict-langs]');
    dict.menu = modal.querySelector('[data-dict-lang-menu]');
    dict.error = modal.querySelector('[data-dict-error]');
    dict.status = modal.querySelector('[data-dict-lang-status]');
    dict.emptyHint = modal.querySelector('[data-dict-langs-empty]');
    dict.titleEl = modal.querySelector('[data-dict-title]');
    dict.resource = table.getAttribute('data-dict-resource') || payload.resource || '';
    dict.nameOne = modal.getAttribute('data-dict-name') || '';
    dict.items = Array.isArray(payload.items) ? payload.items : [];

    renderRows();

    if (dict.form) {
      dict.form.addEventListener('submit', function (e) {
        e.preventDefault();
        saveDictForm();
      });
    }

    if (dict.list) {
      // Значение поля живёт в dict.draft — иначе перерисовка строк теряла бы ввод
      dict.list.addEventListener('input', function (e) {
        var input = e.target.closest && e.target.closest('[data-lang-title]');
        if (!input) return;
        var row = input.closest('[data-lang-index]');
        var index = row ? Number(row.getAttribute('data-lang-index')) : -1;
        if (index >= 0 && dict.draft[index]) dict.draft[index].title = input.value;
        setError('');
      });
    }

    // Языки грузим сразу: меню дропдауна готово к первому клику
    loadLanguages();
  }

  /* ============================================================
     SEARCH — клиентский поиск в таблицах админки

     Поле [data-admin-search] фильтрует строки таблицы по data-search
     (у строки справочника это ВСЕ её переводы разом) и поддерживает
     в актуальном состоянии счётчик [data-admin-count] и заглушку
     [data-admin-empty]: данных нет — common.admin.empty, поиск пуст —
     admin.no_results с запросом.
     ============================================================ */
  var searchState = null;

  function initSearch() {
    var input = document.querySelector('[data-admin-search]');
    var table = document.querySelector('[data-admin-table]');
    if (!input || !table) return;

    var emptyRow = table.querySelector('[data-admin-empty]');
    var emptyText = emptyRow ? (emptyRow.querySelector('td') || emptyRow).textContent.trim() : '';

    searchState = {
      input: input,
      table: table,
      count: document.querySelector('[data-admin-count]'),
      emptyRow: emptyRow,
      emptyText: emptyText,
      apply: function () {
        var q = (searchState.input.value || '').trim().toLowerCase();
        var rows = searchState.table.querySelectorAll('tbody tr[data-search]');
        var shown = 0;

        Array.prototype.forEach.call(rows, function (row) {
          var haystack = (row.getAttribute('data-search') || '').toLowerCase();
          var hit = q === '' || haystack.indexOf(q) !== -1;
          row.hidden = !hit;
          if (hit) shown++;
        });

        if (searchState.count) searchState.count.textContent = String(shown);

        if (searchState.emptyRow) {
          if (shown > 0) {
            searchState.emptyRow.hidden = true;
          } else {
            searchState.emptyRow.hidden = false;
            var cell = searchState.emptyRow.querySelector('td') || searchState.emptyRow;
            cell.textContent = q
              ? t('admin.no_results', { query: (searchState.input.value || '').trim() })
              : searchState.emptyText;
          }
        }
      }
    };

    input.addEventListener('input', searchState.apply);
    searchState.apply();
  }

  /** Повторный прогон поиска — вызывается после перерисовки строк. */
  function applySearch() {
    if (searchState) searchState.apply();
  }

  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) return;

    // --- Справочники (Genres / Categories / Types) ---
    var dictAdd = e.target.closest('[data-admin-dict-add]');
    if (dictAdd) { openDictForm(null); return; }

    var dictEdit = e.target.closest('[data-admin-dict-edit]');
    if (dictEdit) { openDictForm(dictEdit.getAttribute('data-dict-row-id')); return; }

    var dictDelete = e.target.closest('[data-admin-dict-delete]');
    if (dictDelete) { deleteDictItem(dictDelete); return; }

    var langPick = e.target.closest('[data-dict-lang-pick]');
    if (langPick) {
      addLanguage(langPick.getAttribute('data-dict-lang-pick'));
      // app.js вешает закрытие только на пункты, существовавшие на старте,
      // поэтому новый пункт закрываем дропдаун сами
      var dd = langPick.closest('[data-dropdown]');
      if (dd) dd.classList.remove('is-open');
      return;
    }

    var langRemove = e.target.closest('[data-dict-lang-remove]');
    if (langRemove) { removeLanguage(langRemove); return; }

    if (e.target.closest('[data-dict-dismiss], [data-dict-cancel]')) { closeDictForm(); return; }

    // --- Пользователи / комментарии ---
    var blockBtn = e.target.closest('[data-admin-block]');
    if (blockBtn && !blockBtn.disabled) { blockUser(blockBtn); return; }

    var deleteBtn = e.target.closest('[data-admin-delete]');
    if (deleteBtn) deleteComment(deleteBtn);
  });

  // Esc — закрыть модалку справочника; Tab — фокус зациклен внутри неё
  document.addEventListener('keydown', function (e) {
    if (!isDictOpen()) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      closeDictForm();
      return;
    }
    if (e.key !== 'Tab') return;

    var dialog = dict.modal.querySelector('.modal__dialog');
    if (!dialog) return;
    var items = Array.prototype.filter.call(
      dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
      function (el) { return !el.disabled && el.offsetParent !== null; }
    );
    if (!items.length) return;

    var first = items[0];
    var last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  function initAdmin() {
    initSearch();
    initDictionary();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAdmin);
  } else {
    initAdmin();
  }
})();
