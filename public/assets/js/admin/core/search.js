/**
 * Клиентский поиск/фильтр строк в таблицах админки ([data-admin-search]).
 */
import { t } from './i18n.js';
import { runRolesSearch } from '../features/roles/search.js';

export var searchState = null;
export function initSearch() {
  var input = document.querySelector('[data-admin-search]');
  var table = document.querySelector('[data-admin-table]');
  
  if (!input || !table || table.hasAttribute('data-allroles-table')) return;

  var serverMode = table.getAttribute('data-admin-table') === 'roles';
  var statusSel = document.querySelector('[data-admin-status]');
  var emptyRow = table.querySelector('[data-admin-empty]');
  var emptyText = emptyRow ? (emptyRow.querySelector('td') || emptyRow).textContent.trim() : '';

  searchState = {
    input: input,
    table: table,
    status: statusSel,
    count: document.querySelector('[data-admin-count]'),
    emptyRow: emptyRow,
    emptyText: emptyText,
    serverQuery: '',
    apply: function () {
      var rawQuery = serverMode ? searchState.serverQuery : (searchState.input.value || '').trim();
      var q = rawQuery.toLowerCase();
      var status = searchState.status ? String(searchState.status.value || '') : '';
      var rows = searchState.table.querySelectorAll('tbody tr[data-search]');
      var shown = 0;

      Array.prototype.forEach.call(rows, function (row) {
        var haystack = (row.getAttribute('data-search') || '').toLowerCase();
        var hitText = serverMode || q === '' || haystack.indexOf(q) !== -1;
        var hitStatus = status === '' || (row.getAttribute('data-status') || '') === status;
        var hit = hitText && hitStatus;
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
            ? t('admin.no_results', { query: rawQuery })
            : searchState.emptyText;
        }
      }
    }
  };

  if (serverMode) {
    input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      searchState.serverQuery = (input.value || '').trim();
      runRolesSearch(searchState.serverQuery);
    });
    input.addEventListener('search', function () {
      var value = (input.value || '').trim();
      if (value === '' && searchState.serverQuery !== '') {
        searchState.serverQuery = '';
        runRolesSearch('');
      }
    });
  } else {
    input.addEventListener('input', searchState.apply);
  }
  if (statusSel) statusSel.addEventListener('change', searchState.apply);
  searchState.apply();
}

export function applySearch() {
  if (searchState) searchState.apply();
}
