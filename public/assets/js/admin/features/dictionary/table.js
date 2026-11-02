/**
 * Таблица справочника: рендер, синхронизация с API, удаление.
 */
import { t } from '../../core/i18n.js';
import { applySearch } from '../../core/search.js';
import { confirmModal, notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';
import { dictApi } from './api.js';
import { dict } from './state.js';

function translationTitles(item) {
  var translations = (item && item.translations) || [];
  return translations
    .map(function (row) { return String((row && row.title) || '').trim(); })
    .filter(function (title) { return title !== ''; });
}

export function findItem(id) {
  for (var i = 0; i < dict.items.length; i++) {
    if (Number(dict.items[i].id) === id) return dict.items[i];
  }
  return null;
}

export function renderRows() {
  if (!dict.rows) return;
  var emptyRow = dict.rows.querySelector('[data-admin-empty]');

  dict.rows.innerHTML = dict.items.map(function (item) {
    var id = Number(item.id) || 0;
    var titles = translationTitles(item);
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

  if (emptyRow) dict.rows.appendChild(emptyRow);
  applySearch();
}

function nextLocalId() {
  return dict.items.reduce(function (max, row) {
    return Math.max(max, Number(row.id) || 0);
  }, 0) + 1;
}

export function applyLocal(isEdit, filled, serverItem) {
  var translations = (serverItem && serverItem.translations && serverItem.translations.length)
    ? serverItem.translations
    : filled;

  if (isEdit) {
    var item = findItem(dict.editId);
    if (item) item.translations = translations;
    else if (serverItem) dict.items.push(serverItem);
  } else {
    var id = (serverItem && serverItem.id) ? serverItem.id : nextLocalId();
    dict.items.push({ id: id, translations: translations });
  }
  dict.touched = true;
}

export function syncFromApi() {
  return dictApi.list(dict.resource).then(function (items) {
    if (items === null) return false;
    dict.items = items;
    renderRows();
    return true;
  });
}

export function refreshFromApi() {
  dictApi.markUnknown();
  return dictApi.list(dict.resource).then(function (items) {
    if (items === null) return false;
    var note = document.querySelector('.admin-note[data-dict-stub]');
    if (note) note.hidden = true;
    if (dict.touched) return false;
    dict.items = items;
    renderRows();
    return true;
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
