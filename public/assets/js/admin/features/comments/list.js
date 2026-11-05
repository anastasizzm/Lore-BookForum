/**
 * Таблица комментариев (постов): GET /api/posts, поиск по Enter, «Показать ещё».
 * Удаление — DELETE /api/posts/{id} (см. delete.js, оно вызывает removeCommentFromList).
 */
import { apiError, apiFetch } from '../../core/http.js';
import { t, tx } from '../../core/i18n.js';
import { esc } from '../../core/utils.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { takeBootstrap } from '../../core/bootstrap.js';

var COLS = 6;

var commentsList = {
  tbody: null, status: null, count: null, search: null, more: null,
  query: '', page: 1, ps: 20, total: null, hasMore: false,
  items: [], seq: 0, loading: false
};

var warned = false;

function pick(obj, keys) {
  for (var i = 0; i < keys.length; i++) {
    var v = obj[keys[i]];
    if (v !== undefined && v !== null && v !== '') return v;
  }
  return null;
}

/** Строка из «строки или объекта {username|title|name}». */
function labelOf(v) {
  if (v === null || v === undefined) return '';
  if (typeof v === 'string' || typeof v === 'number') return String(v);
  if (typeof v === 'object') return String(pick(v, ['username', 'name', 'login', 'nickname', 'title']) || '');
  return '';
}

function dateOf(v) {
  if (!v) return '';
  // API serializes DateTimeImmutable as { date, timezone, timezone_type }.
  var s = typeof v === 'object' ? String(v.date || '') : String(v);
  return /^\d{4}-\d{2}-\d{2}/.test(s) ? s.slice(0, 10) : s;
}

function normalize(raw) {
  raw = unwrapItem(raw);
  if (!raw || typeof raw !== 'object') return null;
  var id = Number(raw.id !== undefined ? raw.id : raw.postId);
  if (!id && id !== 0) return null;

  var author = labelOf(pick(raw, ['author', 'user', 'account', 'creator'])) || labelOf(pick(raw, ['authorName', 'username', 'userName']));
  var text = String(pick(raw, ['text', 'content', 'body', 'message', 'comment']) || '');
  var pub = labelOf(pick(raw, ['publication', 'pub', 'book', 'article'])) || labelOf(pick(raw, ['publicationTitle', 'pubTitle']));
  var created = dateOf(pick(raw, ['createdAt', 'created_at', 'created', 'date']));

  if (!warned && !text && !author) {
    warned = true;
    console.warn('[admin] comments: unknown post item shape, keys:', Object.keys(raw), raw);
  }

  return {
    id: id,
    author: author,
    text: text,
    publication: pub,
    created: created,
    canDelete: raw.canDelete !== false && raw.canManage !== false
  };
}

function parse(payload) {
  if (payload === null || payload === undefined) return null;
  var list = listFrom(payload, ['posts', 'comments']);
  if (!list) return null;

  var meta = (payload && typeof payload === 'object' && payload.meta && typeof payload.meta === 'object') ? payload.meta : null;
  var total = null;
  [meta, payload].forEach(function (src) {
    if (total !== null || !src || typeof src !== 'object' || Array.isArray(src)) return;
    ['total', 'totalCount', 'count'].forEach(function (k) {
      if (total === null && src[k] != null && isFinite(Number(src[k]))) total = Number(src[k]);
    });
  });

  return {
    items: list.map(normalize).filter(function (x) { return x !== null; }),
    total: total,
    hasNext: meta && typeof meta.hasNext === 'boolean' ? meta.hasNext : null
  };
}

function rowHtml(c) {
  var id = c.id;
  return '<tr data-comment-id="' + id + '">' +
      '<td class="admin-table__id" data-col="id">' + id + '</td>' +
      '<td data-col="author">' + esc(c.author) + '</td>' +
      '<td data-col="text"><span class="admin-table__clip" title="' + esc(c.text) + '">' + esc(c.text) + '</span></td>' +
      '<td data-col="publication"><span class="admin-table__clip" title="' + esc(c.publication) + '">' + esc(c.publication) + '</span></td>' +
      '<td data-col="created">' + esc(c.created) + '</td>' +
      '<td data-col="actions"><div class="admin-actions">' +
        '<button type="button" class="btn btn--danger" data-admin-delete data-comment-id="' + id + '"' + (c.canDelete ? '' : ' disabled') + '>' +
          esc(t('admin.action_delete')) +
        '</button>' +
      '</div></td>' +
    '</tr>';
}

function setStatus(text) {
  if (!commentsList.status) return;
  commentsList.status.textContent = text || '';
  commentsList.status.hidden = !text;
}

function render() {
  var st = commentsList;
  if (!st.tbody) return;
  if (!st.items.length) {
    st.tbody.innerHTML = '<tr class="admin-table__empty"><td colspan="' + COLS + '">' +
      esc(st.query ? t('admin.no_results', { query: st.query }) : t('admin.empty')) + '</td></tr>';
  } else {
    st.tbody.innerHTML = st.items.map(rowHtml).join('');
  }
  if (st.count) st.count.textContent = String(st.total !== null ? st.total : st.items.length);
  if (st.more) st.more.hidden = !st.hasMore;
}

function apply(parsed, reset) {
  var st = commentsList;
  var known = {};
  var base = reset ? [] : st.items;
  base.forEach(function (c) { known[c.id] = true; });
  var fresh = parsed.items.filter(function (c) { return !known[c.id]; });

  st.items = base.concat(fresh);
  st.total = parsed.total;
  if (parsed.hasNext !== null) st.hasMore = parsed.hasNext && fresh.length > 0;
  else st.hasMore = fresh.length > 0 && (parsed.total !== null ? st.items.length < parsed.total : parsed.items.length >= st.ps);
  setStatus('');
  render();
}

function load(reset) {
  var st = commentsList;
  if (!st.tbody) return Promise.resolve();
  if (reset) st.page = 1;
  var seq = ++st.seq;

  // Первая страница без поиска — из данных, которые сервер положил в страницу
  if (reset && !st.query) {
    var boot = takeBootstrap('comments');
    var seeded = boot === null ? null : parse(boot);
    if (seeded && seeded.items.length) {
      st.loading = false;
      apply(seeded, true);
      return Promise.resolve();
    }
  }

  st.loading = true;
  if (st.more) st.more.disabled = true;
  setStatus(tx('admin.comments_loading', 'Loading comments…'));

  var qs = 'page=' + st.page + '&ps=' + st.ps + (st.query ? '&q=' + encodeURIComponent(st.query) : '');
  return apiFetch('GET', '/api/posts?' + qs).then(function (r) {
    if (seq !== st.seq) return;
    st.loading = false;
    if (st.more) st.more.disabled = false;

    var parsed = r.ok ? parse(r.data) : null;
    if (!parsed) {
      setStatus(tx('admin.comments_load_error', 'Failed to load comments') + ': ' +
        (r.ok ? tx('errors.common.server_error', 'Server error. Please try again later.') : apiError(r)));
      if (reset) { st.items = []; st.total = null; st.hasMore = false; render(); }
      return;
    }
    apply(parsed, reset);
  });
}

function runSearch(query) {
  var q = String(query || '').trim();
  if (q === commentsList.query && commentsList.items.length) return;
  commentsList.query = q;
  load(true);
}

/** Вызывается после успешного DELETE: убирает комментарий из состояния и перерисовывает. */
export function removeCommentFromList(id) {
  var st = commentsList;
  var before = st.items.length;
  st.items = st.items.filter(function (c) { return String(c.id) !== String(id); });
  if (st.total !== null && st.items.length < before) st.total = Math.max(0, st.total - 1);
  render();
}

export function initCommentsList() {
  var tbody = document.querySelector('[data-comments-rows]');
  if (!tbody) return;
  commentsList.tbody = tbody;
  commentsList.status = document.querySelector('[data-comments-status]');
  commentsList.count = document.querySelector('[data-comments-count]');
  commentsList.search = document.querySelector('[data-comments-search]');
  commentsList.more = document.querySelector('[data-comments-more]');

  if (commentsList.search) {
    // Запрос в API — только по Enter, не на каждый символ
    commentsList.search.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      runSearch(commentsList.search.value);
    });
    commentsList.search.addEventListener('search', function () {
      if ((commentsList.search.value || '').trim() === '' && commentsList.query !== '') runSearch('');
    });
  }

  if (commentsList.more) {
    commentsList.more.addEventListener('click', function () {
      if (commentsList.loading) return;
      commentsList.page += 1;
      load(false);
    });
  }

  load(true);
}