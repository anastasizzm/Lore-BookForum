/**
 * Таблица статей админки: загрузка, поиск (по Enter), «Показать ещё», удаление.
 *   GET    /api/articles?include=creator&page=N&q=…
 *   DELETE /api/articles/{id}
 */
import { apiError, apiFetch } from '../../core/http.js';
import { tx } from '../../core/i18n.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { confirmModal, notify } from '../../core/ui.js';
import { esc } from '../../core/utils.js';

var LIST_URL = '/api/articles';
var ITEM_URL = '/api/articles/';    // + id (DELETE)
var VIEW_URL = '/articles/';        // + id (страница статьи)

var articles = {
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

/**
 * Тип статьи: 'book' — отрывок из книги, 'content' — отдельная статья.
 * Если пришёл справочный тип (объект с названием), показываем его название.
 */
function typeInfo(b, raw) {
  var v = b.type != null ? b.type : raw.type;
  var code = typeof v === 'string' ? v.toLowerCase() : '';
  if (code === 'book') return { excerpt: true, label: tx('common.admin.type_excerpt', 'Excerpt') };
  if (code === 'content') return { excerpt: false, label: tx('common.admin.type_standalone', 'Standalone') };
  var label = textOf(v);
  return { excerpt: false, label: label };
}

function normalizeArticle(raw) {
  raw = unwrapItem(raw);
  if (!raw || typeof raw !== 'object') return null;
  var a = (raw.article && typeof raw.article === 'object') ? raw.article : raw;
  var rawId = a.id != null ? a.id : a.articleId;
  var id = Number(rawId);
  if (rawId == null || rawId === '' || isNaN(id)) {
    console.warn('[admin] article without valid id, keys:', Object.keys(raw), raw);
    return null;
  }
  return {
    id: id,
    title: String(a.title || a.name || ('#' + id)),
    type: typeInfo(a, raw),
    author: textOf(a.creator) || textOf(a.author) || textOf(raw.creator) || textOf(raw.author) || textOf(a.authors),
    created: dateOf(a.createdAt || a.created_at || a.created || raw.createdAt),
    canDelete: raw.canDelete !== false && raw.canManage !== false
  };
}

/* ---------- рендер ---------- */

function setStatus(text) {
  if (!articles.status) return;
  articles.status.textContent = text || '';
  articles.status.hidden = !text;
}

function renderRows() {
  if (!articles.rows) return;
  if (!articles.items.length) {
    articles.rows.innerHTML = '<tr class="admin-table__empty"><td colspan="6">' +
      esc(tx('common.admin.empty', 'Nothing found')) + '</td></tr>';
  } else {
    var viewLabel = tx('common.admin.action_view', 'View');
    var delLabel = tx('common.admin.action_delete', 'Delete');
    articles.rows.innerHTML = articles.items.map(function (a) {
      var badge = a.type.label
        ? '<span class="admin-badge' + (a.type.excerpt ? ' admin-badge--accent' : '') + '">' + esc(a.type.label) + '</span>'
        : '';
      return '<tr data-article-id="' + a.id + '">' +
        '<td class="admin-table__id">' + a.id + '</td>' +
        '<td><span class="admin-table__clip" title="' + esc(a.title) + '">' + esc(a.title) + '</span></td>' +
        '<td>' + badge + '</td>' +
        '<td>' + esc(a.author) + '</td>' +
        '<td>' + esc(a.created) + '</td>' +
        '<td><div class="admin-actions">' +
          '<a class="btn btn--secondary" href="' + VIEW_URL + a.id + '">' + esc(viewLabel) + '</a>' +
          '<button type="button" class="btn btn--danger" data-article-delete data-article-id="' + a.id + '"' +
            (a.canDelete ? '' : ' disabled') + '>' + esc(delLabel) + '</button>' +
        '</div></td></tr>';
    }).join('');
  }
  if (articles.count) articles.count.textContent = String(articles.items.length);
  if (articles.more) articles.more.hidden = !articles.hasNext;
}

/* ---------- загрузка ---------- */

function load(reset) {
  if (articles.loading && !reset) return;
  var seq = ++articles.seq;
  articles.loading = true;
  if (reset) { articles.page = 1; articles.items = []; }
  setStatus(tx('admin.articles_loading', 'Loading articles…'));
  if (articles.more) articles.more.disabled = true;

  var url = LIST_URL + '?include=creator&page=' + articles.page +
    (articles.query ? '&q=' + encodeURIComponent(articles.query) : '');

  return apiFetch('GET', url).then(function (r) {
    if (seq !== articles.seq) return;
    articles.loading = false;
    if (articles.more) articles.more.disabled = false;

    if (!r.ok) {
      setStatus(tx('admin.articles_load_error', 'Failed to load articles') + ': ' + apiError(r));
      renderRows();
      return;
    }
    var list = listFrom(r.data, ['articles']);
    if (list === null) {
      setStatus(tx('admin.articles_load_error', 'Failed to load articles'));
      renderRows();
      return;
    }
    var fresh = list.map(normalizeArticle).filter(function (x) { return x !== null; });
    articles.items = articles.items.concat(fresh);

    var meta = r.data && typeof r.data === 'object' ? r.data.meta : null;
    articles.hasNext = !!(meta && !Array.isArray(meta) && meta.hasNext);
    setStatus('');
    renderRows();
  });
}

/* ---------- удаление ---------- */

function deleteArticle(btn) {
  var id = Number(btn.getAttribute('data-article-id'));
  var article = articles.items.filter(function (a) { return a.id === id; })[0];
  if (!article) return;

  confirmModal({
    title:       tx('admin.article_delete_title', 'Delete article?'),
    text:        tx('admin.article_delete_text',
                    'The article "' + article.title + '" will be permanently deleted. This action cannot be undone.',
                    { name: article.title }),
    confirmText: tx('common.admin.action_delete', 'Delete'),
    danger:      true
  }).then(function (confirmed) {
    if (!confirmed) return;
    btn.disabled = true;
    return apiFetch('DELETE', ITEM_URL + encodeURIComponent(id)).then(function (r) {
      if (!r.ok) { notify(apiError(r), 'error'); btn.disabled = false; return; }
      articles.items = articles.items.filter(function (a) { return a.id !== id; });
      renderRows();
      notify(tx('admin.article_deleted', 'Article deleted'), 'success');
    });
  }).catch(function () {
    notify(tx('errors.common.network_error', 'Network error. Try again.'), 'error');
    btn.disabled = false;
  });
}

/* ---------- init ---------- */

export function initArticlesList() {
  var table = document.querySelector('[data-articles-table]');
  if (!table) return;
  articles.table = table;
  articles.rows = document.querySelector('[data-articles-rows]');
  articles.input = document.querySelector('[data-articles-search]');
  articles.count = document.querySelector('[data-articles-count]');
  articles.status = document.querySelector('[data-articles-status]');
  articles.more = document.querySelector('[data-articles-more]');

  if (articles.input) {
    articles.input.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      articles.query = (articles.input.value || '').trim();
      load(true);
    });
    // крестик в поле type=search очищает запрос
    articles.input.addEventListener('search', function () {
      var value = (articles.input.value || '').trim();
      if (value === '' && articles.query !== '') { articles.query = ''; load(true); }
    });
  }

  if (articles.more) {
    articles.more.addEventListener('click', function () {
      if (!articles.hasNext) return;
      articles.page++;
      load(false);
    });
  }

  table.addEventListener('click', function (e) {
    var del = e.target && e.target.closest && e.target.closest('[data-article-delete]');
    if (del && !del.disabled) deleteArticle(del);
  });

  load(true);
}