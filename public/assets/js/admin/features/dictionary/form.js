/**
 * Модалка добавления/редактирования записи справочника (переводы по языкам).
 */
import { t, tx } from '../../core/i18n.js';
import { notify } from '../../core/ui.js';
import { currentLang, esc, langName } from '../../core/utils.js';
import { defaultLang, hasDefaultLang, loadDefaultLang } from '../../core/default-lang.js';
import { dictApi } from './api.js';
import { loadLanguages } from './languages.js';
import { dict } from './state.js';
import { applyLocal, findItem, renderRows, syncFromApi } from './table.js';

/**
 * Обязательно только название на языке системы по умолчанию
 * (GET /api/additional/languages/fallback, см. core/default-lang.js).
 */
function requiredLangs() {
  return [String(defaultLang()).toLowerCase()];
}

function isRequiredLang(code) {
  return requiredLangs().indexOf(String(code || '').toLowerCase()) !== -1;
}

/** Гарантирует, что в черновике есть строка языка по умолчанию (обязательная идёт первой). */
function ensureRequiredRows() {
  var byCode = {};
  dict.draft.forEach(function (row) { byCode[String(row.langCode || '').toLowerCase()] = row; });
  var required = requiredLangs().map(function (code) {
    return byCode[code] || { langCode: code, title: '' };
  });
  var rest = dict.draft.filter(function (row) { return !isRequiredLang(row.langCode); });
  dict.draft = required.concat(rest);
}

export function setError(text) {
  if (!dict.error) return;
  dict.error.textContent = text || '';
  dict.error.hidden = !text;
}

export function setStatus(text) {
  if (!dict.status) return;
  dict.status.textContent = text || '';
  dict.status.hidden = !text;
}

function renderLangs() {
  if (!dict.list) return;

  dict.list.innerHTML = dict.draft.map(function (row, index) {
    var code = String(row.langCode || '');
    var name = langName(code);

    return '<li class="admin-dict__lang-row" data-lang="' + esc(code) + '" data-lang-index="' + index + '">' +
        '<span class="admin-badge admin-dict__lang-code" title="' + esc(name) + '">' + esc(code) + '</span>' +
        '<div class="form-field admin-dict__lang-field">' +
          '<input class="form-field__input" type="text" data-lang-title autocomplete="off"' +
            (isRequiredLang(code) ? ' required aria-required="true"' : '') +
            ' value="' + esc(row.title) + '"' +
            ' placeholder="' + esc(t('admin.col_title')) + '"' +
            ' aria-label="' + esc(name + ': ' + t('admin.col_title')) + '">' +
        '</div>' +
        (isRequiredLang(code) ? '' :
          '<button type="button" class="admin-dict__lang-remove" data-dict-lang-remove' +
            ' title="' + esc(t('admin.dict_lang_remove')) + '"' +
            ' aria-label="' + esc(t('admin.dict_lang_remove')) + '">&times;</button>') +
      '</li>';
  }).join('');

  if (dict.emptyHint) dict.emptyHint.hidden = dict.draft.length > 0;
  renderLangMenu();
}

export function renderLangMenu() {
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

export function addLanguage(code) {
  var value = String(code || '').trim();
  if (!value) return;
  var duplicate = dict.draft.some(function (row) {
    return String(row.langCode || '').toLowerCase() === value.toLowerCase();
  });
  if (duplicate) return;

  dict.draft.push({ langCode: value, title: '' });
  setError('');
  renderLangs();

  var rows = dict.list.querySelectorAll('[data-lang-index]');
  var last = rows[rows.length - 1];
  var input = last && last.querySelector('[data-lang-title]');
  if (input) input.focus();
}

export function removeLanguage(btn) {
  var row = btn.closest('[data-lang-index]');
  var index = row ? Number(row.getAttribute('data-lang-index')) : -1;
  if (index < 0 || !dict.draft[index] || isRequiredLang(dict.draft[index].langCode)) return;
  dict.draft.splice(index, 1);
  renderLangs();
}

export function openDictForm(id) {
  if (!dict.modal) return;
  // Язык по умолчанию нужен до отрисовки обязательной строки
  if (hasDefaultLang()) { openForm(id); return; }
  loadDefaultLang().then(function () { openForm(id); });
}

function openForm(id) {
  if (!dict.modal) return;

  dict.editId = id ? Number(id) : null;
  var item = dict.editId ? findItem(dict.editId) : null;

  function fill(source) {
    var rows = source && source.translations ? source.translations : [];
    if (source && !rows.length && source.title) {
      // в списке пришло только название на текущем языке
      rows = [{ langCode: source.lang || currentLang(), title: source.title }];
    }
    dict.draft = source
      ? rows.map(function (row) {
          return { langCode: String(row.langCode || ''), title: String(row.title || '') };
        })
      : [];
    ensureRequiredRows();
    renderLangs();
  }

  fill(item);

  if (dict.titleEl) {
    dict.titleEl.textContent = item
      ? t('admin.dict_edit_title', { name: dict.nameOne })
      : t('admin.dict_add_title', { name: dict.nameOne });
  }

  setError('');

  dict.lastFocus = document.activeElement;
  dict.modal.hidden = false;
  document.body.classList.add('is-modal-open');

  var first = dict.list ? dict.list.querySelector('[data-lang-title]') : null;
  if (first) { first.focus(); try { first.select(); } catch (_) {} }

  // Все переводы записи — отдельным запросом (в списке только текущий язык)
  if (item && !(item.translations && item.translations.length > 1)) {
    var editId = dict.editId;
    dictApi.retrieve(dict.resource, editId).then(function (full) {
      if (!full || dict.editId !== editId || !full.translations.length) return;
      // не затираем то, что пользователь уже начал править
      var touched = dict.draft.some(function (row, i) {
        var base = (item.title || '');
        return row.title !== base && row.title !== '';
      });
      if (touched) return;
      item.translations = full.translations;
      fill(item);
    });
  }

  loadLanguages();
}

export function closeDictForm() {
  if (!dict.modal || dict.modal.hidden) return;

  dict.modal.hidden = true;
  document.body.classList.remove('is-modal-open');

  var dropdown = dict.modal.querySelector('[data-dropdown].is-open');
  if (dropdown) dropdown.classList.remove('is-open');

  var target = dict.lastFocus;
  dict.lastFocus = null;
  if (target && target.isConnected && target.offsetParent !== null) {
    try { target.focus(); } catch (_) {}
  }
}

export function isDictOpen() { return !!(dict.modal && !dict.modal.hidden); }

function setSaveBusy(busy) {
  if (dict.form) dict.form.classList.toggle('is-busy', busy);
  var save = dict.modal && dict.modal.querySelector('[data-dict-save]');
  if (save) save.disabled = busy;
}

export function saveDictForm() {
  var filled = dict.draft
    .map(function (row) {
      return {
        langCode: String(row.langCode || '').trim(),
        title: String(row.title || '').trim()
      };
    })
    .filter(function (row) { return row.langCode !== '' && row.title !== ''; });

  var missing = requiredLangs().filter(function (code) {
    return !filled.some(function (row) { return row.langCode.toLowerCase() === code; });
  });
  if (missing.length) {
    var defCode = requiredLangs()[0];
    var defName = langName(defCode);
    setError(tx('admin.dict_validation_default',
      'Fill in the title in the default language: ' + defName + ' (' + defCode + ').',
      { lang: defName, code: defCode }));
    Array.prototype.forEach.call(dict.list ? dict.list.querySelectorAll('[data-lang-index]') : [], function (row) {
      var code = String(row.getAttribute('data-lang') || '').toLowerCase();
      var input = row.querySelector('[data-lang-title]');
      if (input) input.classList.toggle('form-field__input--invalid', missing.indexOf(code) !== -1);
    });
    var firstBad = dict.list && dict.list.querySelector('.form-field__input--invalid');
    if (firstBad) firstBad.focus();
    return;
  }
  setError('');

  if (dict.submitting) return;
  dict.submitting = true;
  setSaveBusy(true);

  var isEdit = !!dict.editId;
  // Формат как у /api/additional/roles: translations — словарь { "en": "Fantasy", "ru": "Фэнтези" }.
  // Массив [{lang_code, title}] сервер молча игнорировал: запись создавалась без названий
  // и пропадала из списка после перезагрузки или смены языка.
  var translations = {};
  filled.forEach(function (row) { translations[row.langCode] = row.title; });
  var payload = { translations: translations };

  dictApi.mutate(isEdit ? 'PUT' : 'POST', dict.resource, isEdit ? dict.editId : null, payload)
    .then(function (result) {
      if (result.mode === 'forbidden') {
        notify(t('js.no_rights'), 'error');
        return;
      }
      if (result.mode === 'error') {
        setError(result.message);
        return;
      }

      closeDictForm();
      notify(t('admin.dict_saved'), 'success');
      if (result.mode === 'api') {
        syncFromApi();
      } else {
        applyLocal(isEdit, filled, result.item);
        renderRows();
      }
    })
    .catch(function (err) {
      console.warn('[admin] dictionary save failed', err);
      setError(t('errors.common.network_error'));
    })
    .then(function () {
      dict.submitting = false;
      setSaveBusy(false);
    });
}