/**
 * Инициализация страниц справочников.
 */
import { saveDictForm, setError } from './form.js';
import { loadLanguages } from './languages.js';
import { dict } from './state.js';
import { refreshFromApi, renderRows } from './table.js';

export function initDictionary() {
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
  refreshFromApi();

  if (dict.form) {
    dict.form.addEventListener('submit', function (e) {
      e.preventDefault();
      saveDictForm();
    });
  }

  if (dict.list) {
    dict.list.addEventListener('input', function (e) {
      var input = e.target.closest && e.target.closest('[data-lang-title]');
      if (!input) return;
      var row = input.closest('[data-lang-index]');
      var index = row ? Number(row.getAttribute('data-lang-index')) : -1;
      if (index >= 0 && dict.draft[index]) dict.draft[index].title = input.value;
      setError('');
    });
  }

  loadLanguages();
}
