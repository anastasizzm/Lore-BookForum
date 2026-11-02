/**
 * book-contents.js — контент книги на странице /books/{id}: список файлов,
 * прикрепление и удаление.
 *
 * Контракт develop (config/endpoints.php):
 *   GET    /api/books/{id}/contents              → { items: File[], meta }
 *   POST   /api/books/{id}/contents              { contentId: uuid } → 200
 *   DELETE /api/books/{id}/contents/{contentId}  → 204
 *   POST   /api/files/upload                     multipart { file, category:'book' }
 *                                                → 201 { createdId: uuid }
 *
 * File в ответе списка: { id(uuid), originalName, format, mimeType, size,
 * status, category, createdAt }.
 *
 * Блок рендерится скрытым и показывается permissions.js
 * (data-requires="publication.updateContent"); реальный доступ держит бэк
 * (UpdateContentPublicationOperation).
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-book-contents]');
  if (!root) return;

  var bookId = parseInt(root.getAttribute('data-book-id'), 10) || 0;
  if (!bookId) return;

  var listEl  = root.querySelector('[data-contents-list]');
  var emptyEl = root.querySelector('[data-contents-empty]');
  var inputEl = root.querySelector('[data-contents-input]');
  var addBtn  = root.querySelector('[data-contents-add]');

  var URLS = {
    list:   '/api/books/' + bookId + '/contents',
    attach: '/api/books/' + bookId + '/contents',
    remove: function (id) { return '/api/books/' + bookId + '/contents/' + id; },
    upload: '/api/files/upload'
  };

  var busy = false;

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  function req(url, opts) {
    return (window.LoreCsrf && typeof window.LoreCsrf.fetch === 'function')
      ? window.LoreCsrf.fetch(url, opts || {})
      : fetch(url, opts || {});
  }

  function notify(text, type) {
    if (window.Messages && typeof window.Messages.show === 'function') {
      window.Messages.show(text, { type: type || 'error' });
    } else {
      console.warn('[book-contents] ' + text);
    }
  }

  function readJson(response) {
    return response.text().then(function (raw) {
      try { return raw ? JSON.parse(raw) : null; } catch (_) { return null; }
    });
  }

  function fmtSize(bytes) {
    var n = Number(bytes) || 0;
    if (n >= 1024 * 1024) return (n / (1024 * 1024)).toFixed(1) + ' MB';
    if (n >= 1024) return Math.round(n / 1024) + ' KB';
    return n + ' B';
  }

  function fileId(item) {
    if (!item) return '';
    var id = item.id;
    if (id && typeof id === 'object' && id.value !== undefined) id = id.value;
    return id ? String(id) : '';
  }

  function render(items) {
    listEl.textContent = '';
    if (emptyEl) emptyEl.hidden = items.length > 0;

    items.forEach(function (item) {
      var id = fileId(item);
      if (!id) return;

      var li = document.createElement('li');
      li.className = 'book-contents__item';
      li.setAttribute('data-content-id', id);

      var link = document.createElement('a');
      link.className = 'book-contents__name';
      link.href = '/api/files/' + id + '/download';
      link.textContent = String(item.originalName || item.format || id);

      var meta = document.createElement('span');
      meta.className = 'book-contents__meta';
      var parts = [];
      if (item.format) parts.push(String(item.format).toUpperCase());
      parts.push(fmtSize(item.size));
      if (item.status === 'pending') parts.push(t('book.contents_pending'));
      meta.textContent = parts.join(' · ');

      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'btn btn--secondary book-contents__remove';
      del.setAttribute('data-contents-remove', '');
      del.textContent = t('book.contents_remove');

      li.appendChild(link);
      li.appendChild(meta);
      li.appendChild(del);
      listEl.appendChild(li);
    });
  }

  function load() {
    return req(URLS.list, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return readJson(response).then(function (data) {
        if (!response.ok) throw new Error('HTTP ' + response.status);
        var items = data && (Array.isArray(data.items) ? data.items
          : (Array.isArray(data.data) ? data.data : []));
        render(items || []);
      });
    }).catch(function (err) {
      console.error('[book-contents] load failed', err);
      notify(t('book.contents_load_failed'));
    });
  }

  /** multipart → POST /api/files/upload (category=book) → uuid. */
  function upload(file) {
    var fd = new FormData();
    fd.append('file', file);
    fd.append('category', 'book');

    return req(URLS.upload, {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      return readJson(response).then(function (data) {
        var id = data && data.createdId;
        if (!response.ok || !id) throw new Error('upload HTTP ' + response.status);
        return String(id);
      });
    });
  }

  function attach(file) {
    return upload(file).then(function (contentId) {
      return req(URLS.attach, {
        method: 'POST',
        body: JSON.stringify({ contentId: contentId }),
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      }).then(function (response) {
        if (!response.ok) throw new Error('attach HTTP ' + response.status);
      });
    });
  }

  function attachFiles(files) {
    var queue = Array.prototype.slice.call(files || []);
    if (!queue.length || busy) return;

    busy = true;
    if (addBtn) { addBtn.disabled = true; addBtn.textContent = t('book.contents_uploading'); }

    var done = 0;
    var failed = 0;

    function next() {
      if (!queue.length) {
        if (addBtn) { addBtn.disabled = false; addBtn.textContent = t('book.contents_add'); }
        busy = false;
        if (inputEl) inputEl.value = '';
        if (failed) notify(t('book.contents_upload_failed'));
        else if (done) notify(t('book.contents_uploaded'), 'success');
        load();
        return;
      }

      attach(queue.shift()).then(function () {
        done++;
      }, function (err) {
        console.error('[book-contents] attach failed', err);
        failed++;
      }).then(next);
    }

    next();
  }

  function remove(id, row) {
    if (busy) return;
    if (window.confirm && !window.confirm(t('book.contents_remove_confirm'))) return;

    busy = true;
    req(URLS.remove(id), {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (response) {
      if (!response.ok) throw new Error('HTTP ' + response.status);
      if (row && row.parentNode) row.parentNode.removeChild(row);
      if (emptyEl && !listEl.querySelector('[data-content-id]')) emptyEl.hidden = false;
      notify(t('book.contents_removed'), 'success');
    }).catch(function (err) {
      console.error('[book-contents] remove failed', err);
      notify(t('book.contents_remove_failed'));
    }).then(function () { busy = false; });
  }

  root.addEventListener('click', function (e) {
    var add = e.target.closest && e.target.closest('[data-contents-add]');
    if (add) {
      e.preventDefault();
      if (inputEl) inputEl.click();
      return;
    }

    var del = e.target.closest && e.target.closest('[data-contents-remove]');
    if (del) {
      e.preventDefault();
      var row = del.closest('[data-content-id]');
      if (row) remove(row.getAttribute('data-content-id'), row);
    }
  });

  if (inputEl) {
    inputEl.addEventListener('change', function () { attachFiles(inputEl.files); });
  }

  load();
})();
