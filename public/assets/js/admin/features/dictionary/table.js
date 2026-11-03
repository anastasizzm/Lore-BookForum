/**
 * Таблица справочника: рендер (ID / Название / Действия), серверный поиск, удаление.
 */
import { t } from '../../core/i18n.js';
import { confirmModal, notify } from '../../core/ui.js';
import { currentLang, esc } from '../../core/utils.js';
import { dictApi } from './api.js';
import { dict } from './state.js';

/** Название на текущем языке: title из списка → перевод текущей локали → любой перевод. */
export function itemTitle(item) {
  if (!item) return '';
  var translations = item.translations || [];
  var lang = currentLang().toLowerCase();
  var own = '';
  translations.forEach(function (row) {
    if (!own && String(row.langCode || '').toLowerCase() === lang && row.title) own = row.title;
  });
  var any = '';
  translations.forEach(function (row) { if (!any && row.title) any = row.title; });
  return String(item.title || own || any || '').trim();
}

export function findItem(id) {
  for (var i = 0; i < dict.items.length; i++) {
    if (Number(dict.items[i].id) === id) return dict.items[i];
  }
  return null;
}

function setCount(n) {
  var el = document.querySelector('[data-dict-count]');
  if (el) el.textContent = String(n);
}

export function renderRows() {
  if (!dict.rows) return;
  var emptyRow = dict.rows.querySelector('[data-admin-empty]');

  dict.rows.innerHTML = dict.items.map(function (item) {
    var id = Number(item.id) || 0;
    return '<tr data-dict-row-id="' + id + '">' +
        '<td class="admin-table__id">' + id + '</td>' +
        '<td><span class="admin-dict__translations">' + esc(itemTitle(item)) + '</span></td>' +
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

  if (emptyRow) {
    var cell = emptyRow.querySelector('td') || emptyRow;
    if (dict.emptyText === undefined) dict.emptyText = cell.textContent.trim();
    cell.textContent = dict.query ? t('admin.no_results', { query: dict.query }) : dict.emptyText;
    emptyRow.hidden = dict.items.length > 0;
    dict.rows.appendChild(emptyRow);
  }
  setCount(dict.items.length);
}

function nextLocalId() {
  return dict.items.reduce(function (max, row) {
    return Math.max(max, Number(row.id) || 0);
  }, 0) + 1;
}

/** Только для режима mock (API недоступен): правим локальный массив. */
export function applyLocal(isEdit, filled, serverItem) {
  var translations = (serverItem && serverItem.translations && serverItem.translations.length)
    ? serverItem.translations
    : filled;

  if (isEdit) {
    var item = findItem(dict.editId);
    if (item) { item.translations = translations; item.title = ''; }
    else if (serverItem) dict.items.push(serverItem);
  } else {
    var id = (serverItem && serverItem.id) ? serverItem.id : nextLocalId();
    dict.items.push({ id: id, title: '', translations: translations });
  }
  dict.touched = true;
}

/** Перечитать список с сервера (с текущим поисковым запросом). */
export function syncFromApi() {
  return dictApi.list(dict.resource, dict.query).then(function (items) {
    if (items === null) return false;
    dict.items = items;
    renderRows();
    return true;
  });
}

export function refreshFromApi() {
  dictApi.markUnknown();
  return dictApi.list(dict.resource, dict.query).then(function (items) {
    if (items === null) return false;
    var note = document.querySelector('.admin-note[data-dict-stub]');
    if (note) note.hidden = true;
    if (dict.touched) return false;
    dict.items = items;
    renderRows();
    return true;
  });
}

/** Поиск на сервере: GET /api/additional/{resource}?q=… (только по Enter). */
export function runDictSearch(query) {
  dict.query = String(query || '').trim();
  var seq = ++dict.searchSeq;
  var input = document.querySelector('[data-dict-search]');
  if (input) input.setAttribute('aria-busy', 'true');
  return dictApi.list(dict.resource, dict.query).then(function (items) {
    if (seq !== dict.searchSeq) return;
    if (input) input.removeAttribute('aria-busy');
    if (items === null) {
      notify(t('errors.common.search_failed'), 'error');
      return;
    }
    dict.items = items;
    dict.touched = true;
    renderRows();
  });
}

export function deleteDictItem(btn) {
  var id = Number(btn.getAttribute('data-dict-row-id') || 0);

  confirmModal({
    title:       t('admin.dict_delete_title'),
    text:        t('admin.dict_delete_text'),
    confirmText: t('admin.action_delete'),
    danger:      true,
  }).then(function (confirmed) {
    if (!confirmed) return;

    btn.disabled = true;
    return dictApi.mutate('DELETE', dict.resource, id, null).then(function (result) {
      if (result.mode === 'forbidden') {
        notify(t('js.no_rights'), 'error');
        btn.disabled = false;
        return;
      }
      if (result.mode === 'error') {
        notify(result.message, 'error');
        btn.disabled = false;
        return;
      }

      dict.items = dict.items.filter(function (item) { return Number(item.id) !== id; });
      dict.touched = true;
      renderRows();
      notify(t('admin.dict_deleted'), 'success');
    });
  });
}