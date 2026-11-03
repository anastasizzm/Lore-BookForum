/**
 * Инициализация страниц справочников.
 */
import { saveDictForm, setError } from './form.js';
import { loadLanguages } from './languages.js';
import { dict } from './state.js';
import { refreshFromApi, renderRows, runDictSearch } from './table.js';

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

  // Поиск — на сервере и только по Enter; пустое поле (крестик/стирание) сбрасывает фильтр
  var search = document.querySelector('[data-dict-search]');
  if (search) {
    // Если страницу открыли с ?q=… (например, обычной отправкой формы) — подхватываем запрос
    try {
      var urlQuery = new URLSearchParams(window.location.search).get('q');
      if (urlQuery && urlQuery.trim() !== '') {
        search.value = urlQuery.trim();
        dict.touched = true;          // не даём начальной загрузке перезаписать результат поиска
        runDictSearch(urlQuery);
      }
    } catch (_) {}
    search.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      runDictSearch(search.value);
    });
    search.addEventListener('search', function () {
      if ((search.value || '').trim() === '' && dict.query !== '') runDictSearch('');
    });
  }

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
      input.classList.remove('form-field__input--invalid');
      setError('');
    });
  }

  loadLanguages();
}