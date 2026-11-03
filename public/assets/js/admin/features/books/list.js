/**
 * Таблица книг админки: загрузка, поиск (по Enter), «Показать ещё», удаление.
 *   GET    /api/books?include=creator&page=N&q=…
 *   DELETE /api/books/{id}
 */
import { apiError, apiFetch } from '../../core/http.js';
import { tx } from '../../core/i18n.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { confirmModal, notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';

var LIST_URL = '/api/books';
var ITEM_URL = '/api/books/';       // + id (DELETE)
var VIEW_URL = '/books/';           // + id (страница книги)

var books = {
  table: null, rows: null, input: null, count: null, status: null, more: null,
  items: [], page: 1, query: '', hasNext: false, loading: false, seq: 0
};

/* ---------- нормализация ---------- */

/** Строка из значения любой формы: строка, объект ({title|name|username}), массив. */
function textOf(v) {
  if (v == null) return '';
  if (Array.isArray(v)) return v.map(textOf).filter(Boolean).join(', ');
  if (typeof v === 'object') {
    if (v.title != null) return String(v.title);
    if (v.name != null) return String(v.name);
    if (v.username != null) return String(v.username);
    if (v.login != null) return String(v.login);
    if (v.email != null) return String(v.email);
    if (Array.isArray(v.translations) && v.translations.length) return textOf(v.translations[0]);
    return '';
  }
  return String(v);
}

/** Дата: строка ISO или объект { date: '2026-10-28 12:23:30…' } → 'YYYY-MM-DD'. */
function dateOf(v) {
  if (!v) return '';
  var s = typeof v === 'object' ? (v.date || '') : String(v);
  return String(s).slice(0, 10);
}

function normalizeBook(raw) {
  raw = unwrapItem(raw);
  if (!raw || typeof raw !== 'object') return null;
  var b = (raw.book && typeof raw.book === 'object') ? raw.book : raw;
  var rawId = b.id != null ? b.id : b.bookId;
  var id = Number(rawId);
  if (rawId == null || rawId === '' || isNaN(id)) {
    console.warn('[admin] book without valid id, keys:', Object.keys(raw), raw);
    return null;
  }
  var author = textOf(b.creator) || textOf(b.author) || textOf(raw.creator) || textOf(raw.author) || textOf(b.authors);
  var genre = textOf(b.genres) || textOf(b.genre) || textOf(raw.genres) || textOf(raw.genre);
  return {
    id: id,
    title: String(b.title || b.name || ('#' + id)),
    author: author,
    genre: genre,
    created: dateOf(b.createdAt || b.created_at || b.created || raw.createdAt),
    canDelete: raw.canDelete !== false && raw.canManage !== false
  };
}

/* ---------- рендер ---------- */

function setStatus(text) {
  if (!books.status) return;
  books.status.textContent = text || '';
  books.status.hidden = !text;
}

function renderRows() {
  if (!books.rows) return;
  if (!books.items.length) {
    books.rows.innerHTML = '<tr class="admin-table__empty"><td colspan="6">' +
      esc(tx('common.admin.empty', 'Nothing found')) + '</td></tr>';
  } else {
    var viewLabel = tx('common.admin.action_view', 'View');
    var delLabel = tx('common.admin.action_delete', 'Delete');
    books.rows.innerHTML = books.items.map(function (b) {
      return '<tr data-book-id="' + b.id + '">' +
        '<td class="admin-table__id">' + b.id + '</td>' +
        '<td><span class="admin-table__clip" title="' + esc(b.title) + '">' + esc(b.title) + '</span></td>' +
        '<td>' + esc(b.author) + '</td>' +
        '<td>' + (b.genre ? '<span class="admin-badge">' + esc(b.genre) + '</span>' : '') + '</td>' +
        '<td>' + esc(b.created) + '</td>' +
        '<td><div class="admin-actions">' +
          '<a class="btn btn--secondary" href="' + VIEW_URL + b.id + '">' + esc(viewLabel) + '</a>' +
          '<button type="button" class="btn btn--danger" data-book-delete data-book-id="' + b.id + '"' +
            (b.canDelete ? '' : ' disabled') + '>' + esc(delLabel) + '</button>' +
        '</div></td></tr>';
    }).join('');
  }
  if (books.count) books.count.textContent = String(books.items.length);
  if (books.more) books.more.hidden = !books.hasNext;
}

/* ---------- загрузка ---------- */

function load(reset) {
  if (books.loading && !reset) return;
  var seq = ++books.seq;
  books.loading = true;
  if (reset) { books.page = 1; books.items = []; }
  setStatus(tx('admin.books_loading', 'Loading books…'));
  if (books.more) books.more.disabled = true;

  var url = LIST_URL + '?include=creator&page=' + books.page +
    (books.query ? '&q=' + encodeURIComponent(books.query) : '');

  return apiFetch('GET', url).then(function (r) {
    if (seq !== books.seq) return;
    books.loading = false;
    if (books.more) books.more.disabled = false;

    if (!r.ok) {
      setStatus(tx('admin.books_load_error', 'Failed to load books') + ': ' + apiError(r));
      renderRows();
      return;
    }
    var list = listFrom(r.data, ['books']);
    if (list === null) {
      setStatus(tx('admin.books_load_error', 'Failed to load books'));
      renderRows();
      return;
    }
    var fresh = list.map(normalizeBook).filter(function (x) { return x !== null; });
    books.items = books.items.concat(fresh);

    var meta = r.data && typeof r.data === 'object' ? r.data.meta : null;
    books.hasNext = !!(meta && !Array.isArray(meta) && meta.hasNext);
    setStatus('');
    renderRows();
  });
}

/* ---------- удаление ---------- */

function deleteBook(btn) {
  var id = Number(btn.getAttribute('data-book-id'));
  var book = books.items.filter(function (b) { return b.id === id; })[0];
  if (!book) return;

  confirmModal({
    title:       tx('admin.book_delete_title', 'Delete book?'),
    text:        tx('admin.book_delete_text',
                    'The book "' + book.title + '" will be permanently deleted. This action cannot be undone.',
                    { name: book.title }),
    confirmText: tx('common.admin.action_delete', 'Delete'),
    danger:      true
  }).then(function (confirmed) {
    if (!confirmed) return;
    btn.disabled = true;
    return apiFetch('DELETE', ITEM_URL + encodeURIComponent(id)).then(function (r) {
      if (!r.ok) { notify(apiError(r), 'error'); btn.disabled = false; return; }
      books.items = books.items.filter(function (b) { return b.id !== id; });
      renderRows();
      notify(tx('admin.book_deleted', 'Book deleted'), 'success');
    });
  }).catch(function () {
    notify(tx('errors.common.network_error', 'Network error. Try again.'), 'error');
    btn.disabled = false;
  });
}

/* ---------- init ---------- */

export function initBooksList() {
  var table = document.querySelector('[data-books-table]');
  if (!table) return;
  books.table = table;
  books.rows = document.querySelector('[data-books-rows]');
  books.input = document.querySelector('[data-books-search]');
  books.count = document.querySelector('[data-books-count]');
  books.status = document.querySelector('[data-books-status]');
  books.more = document.querySelector('[data-books-more]');

  if (books.input) {
    books.input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      books.query = (books.input.value || '').trim();
      load(true);
    });
    // крестик в поле type=search очищает запрос
    books.input.addEventListener('search', function () {
      var value = (books.input.value || '').trim();
      if (value === '' && books.query !== '') { books.query = ''; load(true); }
    });
  }

  if (books.more) {
    books.more.addEventListener('click', function () {
      if (!books.hasNext) return;
      books.page++;
      load(false);
    });
  }

  table.addEventListener('click', function (e) {
    var del = e.target && e.target.closest && e.target.closest('[data-book-delete]');
    if (del && !del.disabled) deleteBook(del);
  });

  load(true);
}