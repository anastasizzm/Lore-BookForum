/**
 * Fetch-слой справочников: /api/additional/{resource}, фолбэк на мок.
 */
import { httpRequest } from '../../core/http.js';

export var dictApi = (function () {
  var BASE = '/api/additional/';
  var status = 'unknown';

  function table() { return document.querySelector('[data-dict-table]'); }

  function setStatus(next) {
    if (status === next) return;
    status = next;
    var el = table();
    if (el) el.setAttribute('data-dict-api', next);
  }

  function markUnknown() {
    var el = table();
    if (el && status === 'unknown') el.setAttribute('data-dict-api', 'unknown');
  }

  function request(method, path, body) {
    return httpRequest(method, BASE + path, body);
  }

  function classify(r) {
    if (r.networkError) return 'mock';
    var looksHtml = /^\s*</.test(r.raw || '');
    if (r.ok) return looksHtml ? 'mock' : 'ok';
    if (looksHtml || !r.isJson) return 'mock';
    if (r.status === 404 || r.status === 405) return 'mock';
    if (r.status === 403) return 'forbidden';
    return 'error';
  }

  function unwrap(payload) {
    if (payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object') {
      return payload.data;
    }
    return payload;
  }

  function normalizeItem(raw) {
    if (!raw || typeof raw !== 'object') return null;
    var id = Number(raw.id);
    if (!id) return null;
    var translations = Array.isArray(raw.translations) ? raw.translations : [];
    var rows = [];
    translations.forEach(function (row) {
      if (!row || typeof row !== 'object') return;
      var code = String(row.langCode || row.lang_code || row.lang || row.locale || '');
      if (!code) return;
      var title = row.title;
      if (title == null) title = row.name;
      rows.push({ langCode: code, title: String(title == null ? '' : title) });
    });
    return { id: id, translations: rows };
  }

  function itemsFrom(payload) {
    var inner = unwrap(payload);
    var list = null;
    if (Array.isArray(inner)) list = inner;
    else if (inner && Array.isArray(inner.items)) list = inner.items;
    else if (inner && Array.isArray(inner.data)) list = inner.data;
    if (!list || !list.length) return list ? [] : null;
    var items = list.map(normalizeItem).filter(function (item) { return item !== null; });
    return items.length ? items : null;
  }

  function errorText(r) {
    var fallback = null;
    if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
    if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
    return fallback || ('HTTP ' + r.status);
  }

  return {
    get status() { return status; },
    markUnknown: markUnknown,

    list: function (resource) {
      if (status === 'mock') return Promise.resolve(null);
      return request('GET', resource).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') { setStatus('live'); return itemsFrom(r.data); }
        if (kind === 'mock') { setStatus('mock'); return null; }
        setStatus('live');
        console.warn('[admin] GET ' + BASE + resource + ' failed with HTTP ' + r.status);
        return null;
      });
    },

    mutate: function (method, resource, id, body) {
      var path = resource + (id ? '/' + id : '');
      return request(method, path, body).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') {
          setStatus('live');
          var item = normalizeItem(unwrap(r.data));
          return { mode: 'api', item: item, status: r.status };
        }
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        return { mode: 'error', status: r.status, message: errorText(r) };
      });
    }
  };
})();
