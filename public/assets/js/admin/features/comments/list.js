/**
 * Таблица комментариев (постов) админки: загрузка, поиск (по Enter), «Показать ещё», удаление.
 *   GET    /api/posts?include=creator&page=N&q=…
 *   DELETE /api/posts/{postId}
 */
import { apiError, apiFetch } from '../../core/http.js';
import { tx } from '../../core/i18n.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { confirmModal, notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';

var LIST_URL = '/api/posts';
var ITEM_URL = '/api/posts/';       // + id (DELETE)

var comments = {
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

function normalizeComment(raw) {
  raw = unwrapItem(raw);
  if (!raw || typeof raw !== 'object') return null;
  var p = (raw.post && typeof raw.post === 'object') ? raw.post : raw;
  var rawId = p.id != null ? p.id : p.postId;
  var id = Number(rawId);
  if (rawId == null || rawId === '' || isNaN(id)) {
    console.warn('[admin] comment without valid id, keys:', Object.keys(raw), raw);
    return null;
  }
  var pub = p.publication != null ? p.publication : raw.publication;
  return {
    id: id,
    author: textOf(p.creator) || textOf(p.author) || textOf(p.user) || textOf(raw.creator) || textOf(raw.author),
    text: String(p.text != null ? p.text : (p.content != null ? p.content : (p.body != null ? p.body : (p.message || '')))),
    publication: textOf(pub) || String(p.publicationTitle || raw.publicationTitle || ''),
    created: dateOf(p.createdAt || p.created_at || p.created || raw.createdAt),
    canDelete: raw.canDelete !== false && raw.canManage !== false
  };
}

/* ---------- рендер ---------- */

function setStatus(text) {
  if (!comments.status) return;
  comments.status.textContent = text || '';
  comments.status.hidden = !text;
}

function renderRows() {
  if (!comments.rows) return;
  if (!comments.items.length) {
    comments.rows.innerHTML = '<tr class="admin-table__empty"><td colspan="6">' +
      esc(tx('common.admin.empty', 'Nothing found')) + '</td></tr>';
  } else {
    var delLabel = tx('common.admin.action_delete', 'Delete');
    comments.rows.innerHTML = comments.items.map(function (c) {
      return '<tr data-comment-id="' + c.id + '">' +
        '<td class="admin-table__id">' + c.id + '</td>' +
        '<td>' + esc(c.author) + '</td>' +
        '<td><span class="admin-table__clip" title="' + esc(c.text) + '">' + esc(c.text) + '</span></td>' +
        '<td><span class="admin-table__clip" title="' + esc(c.publication) + '">' + esc(c.publication) + '</span></td>' +
        '<td>' + esc(c.created) + '</td>' +
        '<td><div class="admin-actions">' +
          '<button type="button" class="btn btn--danger" data-comment-delete data-comment-id="' + c.id + '"' +
            (c.canDelete ? '' : ' disabled') + '>' + esc(delLabel) + '</button>' +
        '</div></td></tr>';
    }).join('');
  }
  if (comments.count) comments.count.textContent = String(comments.items.length);
  if (comments.more) comments.more.hidden = !comments.hasNext;
}

/* ---------- загрузка ---------- */

function load(reset) {
  if (comments.loading && !reset) return;
  var seq = ++comments.seq;
  comments.loading = true;
  if (reset) { comments.page = 1; comments.items = []; }
  setStatus(tx('admin.comments_loading', 'Loading comments…'));
  if (comments.more) comments.more.disabled = true;

  var url = LIST_URL + '?include=creator&page=' + comments.page +
    (comments.query ? '&q=' + encodeURIComponent(comments.query) : '');

  return apiFetch('GET', url).then(function (r) {
    if (seq !== comments.seq) return;
    comments.loading = false;
    if (comments.more) comments.more.disabled = false;

    if (!r.ok) {
      setStatus(tx('admin.comments_load_error', 'Failed to load comments') + ': ' + apiError(r));
      renderRows();
      return;
    }
    var list = listFrom(r.data, ['posts', 'comments']);
    if (list === null) {
      setStatus(tx('admin.comments_load_error', 'Failed to load comments'));
      renderRows();
      return;
    }
    var fresh = list.map(normalizeComment).filter(function (x) { return x !== null; });
    comments.items = comments.items.concat(fresh);

    var meta = r.data && typeof r.data === 'object' ? r.data.meta : null;
    comments.hasNext = !!(meta && !Array.isArray(meta) && meta.hasNext);
    setStatus('');
    renderRows();
  });
}

/* ---------- удаление ---------- */

function deleteComment(btn) {
  var id = Number(btn.getAttribute('data-comment-id'));
  var comment = comments.items.filter(function (c) { return c.id === id; })[0];
  if (!comment) return;

  confirmModal({
    title:       tx('admin.delete_title', 'Delete comment?'),
    text:        tx('admin.delete_text', 'The comment will be permanently deleted. This action cannot be undone.'),
    confirmText: tx('common.admin.action_delete', 'Delete'),
    danger:      true
  }).then(function (confirmed) {
    if (!confirmed) return;
    btn.disabled = true;
    return apiFetch('DELETE', ITEM_URL + encodeURIComponent(id)).then(function (r) {
      if (!r.ok) { notify(apiError(r), 'error'); btn.disabled = false; return; }
      comments.items = comments.items.filter(function (c) { return c.id !== id; });
      renderRows();
      notify(tx('admin.delete_comment_success', 'Comment deleted'), 'success');
    });
  }).catch(function () {
    notify(tx('errors.common.network_error', 'Network error. Try again.'), 'error');
    btn.disabled = false;
  });
}

/* ---------- init ---------- */

export function initCommentsList() {
  var table = document.querySelector('[data-comments-table]');
  if (!table) return;
  comments.table = table;
  comments.rows = document.querySelector('[data-comments-rows]');
  comments.input = document.querySelector('[data-comments-search]');
  comments.count = document.querySelector('[data-comments-count]');
  comments.status = document.querySelector('[data-comments-status]');
  comments.more = document.querySelector('[data-comments-more]');

  if (comments.input) {
    comments.input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      comments.query = (comments.input.value || '').trim();
      load(true);
    });
    // крестик в поле type=search очищает запрос
    comments.input.addEventListener('search', function () {
      var value = (comments.input.value || '').trim();
      if (value === '' && comments.query !== '') { comments.query = ''; load(true); }
    });
  }

  if (comments.more) {
    comments.more.addEventListener('click', function () {
      if (!comments.hasNext) return;
      comments.page++;
      load(false);
    });
  }

  table.addEventListener('click', function (e) {
    var del = e.target && e.target.closest && e.target.closest('[data-comment-delete]');
    if (del && !del.disabled) deleteComment(del);
  });

  load(true);
}