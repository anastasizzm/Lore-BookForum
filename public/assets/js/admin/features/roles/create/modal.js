/**
 * Модалка «Новая роль»: языки, видимость, цвет, ошибки полей.
 */
import { tx } from '../../../core/i18n.js';
import { esc, langName } from '../../../core/utils.js';
import { loadLanguages } from '../../dictionary/languages.js';
import { submitRoleNew } from './submit.js';
import { fieldErrorText } from '../field-errors.js';

export var roleNew = {
  modal: null, form: null, nameInput: null, list: null, menu: null,
  dd: null, error: null,
  languages: null,
  draft: [],
  errors: { name: '', visibility: '', color: '', langs: {} },
  insertAt: null,
  submitting: false,
  lastFocus: null
};

function isRoleNewOpen() { return !!(roleNew.modal && !roleNew.modal.hidden); }

function rn(sel) { return roleNew.modal ? roleNew.modal.querySelector(sel) : null; }

export function setRoleNewError(text) {
  if (!roleNew.error) return;
  roleNew.error.textContent = text || '';
  roleNew.error.hidden = !text;
}

export function setRoleNewBusy(busy) {
  roleNew.submitting = busy;
  if (roleNew.form) roleNew.form.classList.toggle('is-busy', busy);
  Array.prototype.forEach.call(roleNew.modal.querySelectorAll('[data-rolenew-submit]'), function (btn) {
    btn.disabled = busy;
  });
}

function closeRoleNewMenu() {
  if (!roleNew.dd) return;
  roleNew.dd.classList.remove('is-open');
  if (roleNew.menu) roleNew.menu.hidden = true;
  var toggle = roleNew.dd.querySelector('[data-rolenew-lang-toggle]');
  if (toggle) toggle.setAttribute('aria-expanded', 'false');
}

function renderRoleNewLangs() {
  if (!roleNew.list) return;
  roleNew.list.innerHTML = roleNew.draft.map(function (row, index) {
    if (index === 0) return '';
    var code = String(row.langCode || '');
    var name = langName(code);
    var langErr = roleNew.errors.langs[index] || '';
    return '<li class="admin-rolenew__lang" data-rolenew-index="' + index + '">' +
        '<span class="admin-badge admin-rolenew__code" title="' + esc(name) + '">' + esc(code) + '</span>' +
        '<input class="form-field__input" type="text" data-rolenew-title maxlength="100" autocomplete="off"' +
          ' value="' + esc(row.title) + '"' +
          ' placeholder="' + esc(name) + '"' +
          ' aria-label="' + esc(name) + '"' + (langErr ? ' aria-invalid="true"' : '') + '>' +
        '<button type="button" class="admin-rolenew__remove" data-rolenew-lang-remove' +
          ' aria-label="' + esc(tx('admin.dict_lang_remove', 'Remove language')) + '">&times;</button>' +
        (langErr ? '<p class="admin-rolenew__field-error" role="alert">' + esc(langErr) + '</p>' : '') +
      '</li>';
  }).join('');
  renderRoleNewMenu();
}

function renderRoleNewMenu() {
  if (!roleNew.menu) return;
  var used = roleNew.draft.map(function (row) { return String(row.langCode || '').toLowerCase(); });
  var all = roleNew.languages || [];
  var free = all.filter(function (lang) { return used.indexOf(String(lang.code).toLowerCase()) === -1; });

  if (!free.length) {
    roleNew.menu.innerHTML = '<li class="admin-rolenew__note">' +
      esc(all.length ? tx('admin.dict_lang_all_added', 'All languages added') : tx('admin.dict_lang_none', 'No languages available')) + '</li>';
    return;
  }
  roleNew.menu.innerHTML = free.map(function (lang) {
    return '<li><button type="button" class="admin-rolenew__item" role="option" data-rolenew-lang-pick="' + esc(lang.code) + '">' +
      esc(lang.name || langName(lang.code)) + ' (' + esc(lang.code) + ')</button></li>';
  }).join('');
}

function mapRoleNewField(field, rows) {
  var f = String(field || '').replace(/\[(\w+)\]/g, '.$1').replace(/^\./, '').toLowerCase();
  if (f === 'visibility') return { type: 'visibility' };
  if (f === 'color') return { type: 'color' };
  if (f === 'title' || f === 'name') return { type: 'name' };
  var m = f.match(/^translations(?:\.([\w-]+))?(?:\.(?:title|name|lang_code|langcode|lang))?$/);
  if (m) {
    var di = 0;
    if (m[1] !== undefined) {
      var sent = /^\d+$/.test(m[1])
        ? rows[Number(m[1])]
        : rows.filter(function (row) { return String(row.langCode).toLowerCase() === m[1]; })[0];
      di = sent ? sent.draftIndex : 0;
    }
    return di === 0 ? { type: 'name' } : { type: 'lang', index: di };
  }
  return null;
}

function roleNewFieldHolder(key) {
  if (!roleNew.modal) return null;
  var holder = roleNew.modal.querySelector('[data-rolenew-field-error="' + key + '"]');
  if (holder) return holder;
  var anchor = key === 'name' ? roleNew.nameInput
    : key === 'visibility' ? roleNew.modal.querySelector('.admin-rolenew__pill')
    : roleNew.modal.querySelector('.admin-rolenew__colors');
  if (!anchor) return null;
  holder = document.createElement('p');
  holder.className = 'admin-rolenew__field-error';
  holder.setAttribute('data-rolenew-field-error', key);
  holder.setAttribute('role', 'alert');
  holder.hidden = true;
  anchor.insertAdjacentElement('afterend', holder);
  return holder;
}

function setRoleNewFieldError(key, text) {
  var holder = roleNewFieldHolder(key);
  if (holder) { holder.textContent = text || ''; holder.hidden = !text; }
  if (key === 'name' && roleNew.nameInput) {
    if (text) roleNew.nameInput.setAttribute('aria-invalid', 'true');
    else roleNew.nameInput.removeAttribute('aria-invalid');
  }
}

export function resetRoleNewErrors() {
  roleNew.errors = { name: '', visibility: '', color: '', langs: {} };
  ['name', 'visibility', 'color'].forEach(function (key) { setRoleNewFieldError(key, ''); });
}

export function showRoleNewErrors(result, rows) {
  var fields = result.fields || [];
  resetRoleNewErrors();
  var general = [];

  fields.forEach(function (fe) {
    var seen = {};
    var parts = [];
    fe.codes.forEach(function (code) {
      var text = fieldErrorText(code);
      if (!seen[text]) { seen[text] = true; parts.push(text); }
    });
    var text = parts.join('. ');
    var target = mapRoleNewField(fe.field, rows);
    if (!target) { general.push((fe.field ? fe.field + ': ' : '') + text); return; }
    if (target.type === 'lang') roleNew.errors.langs[target.index] = text;
    else roleNew.errors[target.type] = roleNew.errors[target.type] ? roleNew.errors[target.type] + ' ' + text : text;
  });

  ['name', 'visibility', 'color'].forEach(function (key) { setRoleNewFieldError(key, roleNew.errors[key]); });
  renderRoleNewLangs();
  setRoleNewError(fields.length ? general.join('\n') : result.message);

  var firstBad = roleNew.modal.querySelector('[aria-invalid="true"]');
  if (firstBad) firstBad.focus();
}

export function openRoleNew(insertAt) {
  if (!roleNew.modal) return;
  roleNew.insertAt = insertAt || null;
  roleNew.draft = [{ langCode: 'en', title: '' }];
  resetRoleNewErrors();
  roleNew.form.reset();
  setRoleNewError('');
  setRoleNewBusy(false);
  closeRoleNewMenu();
  renderRoleNewLangs();

  roleNew.lastFocus = document.activeElement;
  roleNew.modal.hidden = false;
  document.body.classList.add('is-modal-open');
  if (roleNew.nameInput) roleNew.nameInput.focus();

  loadLanguages().then(function (languages) {
    roleNew.languages = languages;
    renderRoleNewMenu();
  });
}

export function closeRoleNew() {
  if (!isRoleNewOpen()) return;
  roleNew.modal.hidden = true;
  document.body.classList.remove('is-modal-open');
  closeRoleNewMenu();
  var target = roleNew.lastFocus;
  roleNew.lastFocus = null;
  if (target && target.isConnected && target.offsetParent !== null) {
    try { target.focus(); } catch (_) {}
  }
}

export function roleNewChoice(name) {
  var el = roleNew.form.querySelector('input[name="' + name + '"]:checked');
  return el ? el.value : '';
}

export function initRoleNew() {
  if (roleNew.modal) return; // уже инициализировано
  var modal = document.querySelector('[data-rolenew-modal]');
  if (!modal) return;
  roleNew.modal = modal;
  roleNew.form = modal.querySelector('[data-rolenew-form]');
  roleNew.nameInput = modal.querySelector('[data-rolenew-name]');
  roleNew.list = modal.querySelector('[data-rolenew-langs]');
  roleNew.menu = modal.querySelector('[data-rolenew-menu]');
  roleNew.dd = modal.querySelector('[data-rolenew-dd]');
  roleNew.error = modal.querySelector('[data-rolenew-error]');

  roleNew.form.addEventListener('submit', function (e) {
    e.preventDefault();
    submitRoleNew();
  });

  roleNew.form.addEventListener('change', function (e) {
    var radio = e.target && e.target.name;
    if (radio === 'visibility' || radio === 'color') setRoleNewFieldError(radio, '');
  });

  roleNew.form.addEventListener('input', function (e) {
    var main = e.target.closest && e.target.closest('[data-rolenew-name]');
    if (main) { roleNew.draft[0].title = main.value; setRoleNewError(''); setRoleNewFieldError('name', ''); return; }
    var input = e.target.closest && e.target.closest('[data-rolenew-title]');
    if (!input) return;
    var row = input.closest('[data-rolenew-index]');
    var index = row ? Number(row.getAttribute('data-rolenew-index')) : -1;
    if (index > 0 && roleNew.draft[index]) roleNew.draft[index].title = input.value;
    if (row && roleNew.errors.langs[index]) {
      delete roleNew.errors.langs[index];
      var langErrEl = row.querySelector('.admin-rolenew__field-error');
      if (langErrEl) langErrEl.remove();
      input.removeAttribute('aria-invalid');
    }
  });

  document.addEventListener('keydown', function (e) {
    if (!isRoleNewOpen()) return;
    if (e.key === 'Escape') {
      e.preventDefault();
      if (roleNew.dd.classList.contains('is-open')) closeRoleNewMenu();
      else closeRoleNew();
      return;
    }
    if (e.key !== 'Tab') return;
    var dialog = roleNew.modal.querySelector('.modal__dialog');
    if (!dialog) return;
    var items = Array.prototype.filter.call(
      dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
      function (el) { return !el.disabled && el.offsetParent !== null; }
    );
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
}

export function handleRoleNewClick(target) {
  if (target.closest('[data-rolenew-open]')) { openRoleNew(null); return true; }
  if (!isRoleNewOpen()) return false;

  if (target.closest('[data-rolenew-close]')) { closeRoleNew(); return true; }

  var toggle = target.closest('[data-rolenew-lang-toggle]');
  if (toggle) {
    var open = !roleNew.dd.classList.contains('is-open');
    roleNew.dd.classList.toggle('is-open', open);
    roleNew.menu.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    return true;
  }

  var pick = target.closest('[data-rolenew-lang-pick]');
  if (pick) {
    roleNew.draft.push({ langCode: pick.getAttribute('data-rolenew-lang-pick'), title: '' });
    closeRoleNewMenu();
    renderRoleNewLangs();
    var inputs = roleNew.list.querySelectorAll('[data-rolenew-title]');
    if (inputs.length) inputs[inputs.length - 1].focus();
    return true;
  }

  var remove = target.closest('[data-rolenew-lang-remove]');
  if (remove) {
    var row = remove.closest('[data-rolenew-index]');
    var index = row ? Number(row.getAttribute('data-rolenew-index')) : -1;
    if (index > 0) { roleNew.draft.splice(index, 1); renderRoleNewLangs(); }
    return true;
  }

  if (target.closest('[data-rolenew-submit]')) {
    return false;
  }

  if (!target.closest('[data-rolenew-dd]')) closeRoleNewMenu();
  return false;
}
