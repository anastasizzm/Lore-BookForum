/**
 * Fetch-слой системных ролей: /api/system/roles (+ /api/additional/roles для имён).
 */
import { httpRequest } from '../../core/http.js';
import { tx } from '../../core/i18n.js';
import { normalizeColor } from '../../core/utils.js';
import { parseFieldErrors } from './field-errors.js';

function normalizeRole(raw) {
  if (!raw || typeof raw !== 'object') return null;
  var role = (raw.role && typeof raw.role === 'object') ? raw.role : raw;
  var id = Number(role.id);
  if (!id && id !== 0) return null;
  return {
    id: id,
    title: String(role.title || ''),
    color: normalizeColor(role.color),
    createdAt: String(role.createdAt || ''),
    canManage: !!raw.canManage,
    status: String(role.status || raw.status || ''),
    version: raw.version != null ? raw.version : 1,
    systemPermissions: Object.assign({}, raw.systemPermissions || {}),
    postPermissions: Object.assign({}, raw.postPermissions || {}),
    pubPermissions: Object.assign({}, raw.pubPermissions || {})
  };
}

function rolesFrom(payload) {
  var inner = payload;
  if (payload && typeof payload === 'object' && payload.data && typeof payload.data === 'object') {
    inner = payload.data;
  }
  var list = null;
  if (Array.isArray(inner)) list = inner;
  else if (inner && Array.isArray(inner.items)) list = inner.items;
  else if (inner && Array.isArray(inner.data)) list = inner.data;
  if (!list || !list.length) return list ? [] : null;
  var items = list.map(normalizeRole).filter(function (item) { return item !== null; });
  return items.length ? items : null;
}

export var rolesApi = (function () {
  var BASE = '/api/system/roles';
  var NAME_BASE = '/api/additional/roles';
  var status = 'unknown';

  function table() { return document.querySelector('[data-roles-table]'); }

  function setStatus(next) {
    if (status === next) return;
    status = next;
    var el = table();
    if (el) el.setAttribute('data-roles-api', next);
    if (next === 'mock') {
      console.warn('[admin] ?mock=1 — roles API is bypassed, changes stay local until reload');
    }
  }

  var forceMock = false;
  try { forceMock = new URLSearchParams(window.location.search).has('mock'); } catch (_) {}

  function request(method, path, body, headers, base) {
    if (forceMock) {
      return Promise.resolve({ status: 0, ok: false, data: null, raw: '', isJson: false, headers: null, mocked: true });
    }
    return httpRequest(method, (base || BASE) + (path || ''), body, headers);
  }

  function classify(r) {
    if (r.mocked) return 'mock';
    if (r.networkError) return 'error';
    var looksHtml = /^\s*</.test(r.raw || '');
    if (r.ok) return looksHtml ? 'error' : 'ok';
    if (r.status === 403) return 'forbidden';
    return 'error';
  }

  function errorText(r) {
    if (r.networkError) return tx('errors.common.network_error', 'Network error. Try again.');
    if (r.ok) return tx('errors.common.server_error', 'Server error. Please try again later.');
    var fallback = null;
    if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
    if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
    return fallback || ('HTTP ' + r.status);
  }

  var lastError = '';

  return {
    get status() { return status; },
    get lastError() { return lastError; },

    list: function (query, statusFilter) {
      lastError = '';
      if (status === 'mock') return Promise.resolve(null);
      var parts = [];
      if (query) parts.push('q=' + encodeURIComponent(query));
      if (statusFilter && statusFilter !== 'all') parts.push('status=' + encodeURIComponent(statusFilter));
      var qs = parts.length ? '?' + parts.join('&') : '';
      return request('GET', qs).then(function (r) {
        var kind = classify(r);
        if (kind === 'mock') { setStatus('mock'); return null; }
        if (kind === 'ok') {
          var items = rolesFrom(r.data);
          if (items === null) {
            lastError = tx('errors.common.server_error', 'Server error. Please try again later.');
            return null;
          }
          setStatus('live');
          return items;
        }
        lastError = kind === 'forbidden' ? tx('js.no_rights', 'You have no rights for this action') : errorText(r);
        return null;
      });
    },

    createName: function (body) {
      return request('POST', '', body, null, NAME_BASE).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') {
          setStatus('live');
          var location = r.headers && r.headers.get ? r.headers.get('Location') : null;
          return { mode: 'api', data: r.data, location: location, status: r.status };
        }
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        return { mode: 'error', status: r.status, message: errorText(r), fields: parseFieldErrors(r.data) };
      });
    },

    create: function (body) {
      return request('POST', '', body).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') {
          setStatus('live');
          var etag = r.headers && r.headers.get ? r.headers.get('ETag') : null;
          return { mode: 'api', data: r.data, etag: etag, status: r.status };
        }
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        return { mode: 'error', status: r.status, message: errorText(r) };
      });
    },

    save: function (id, version, body) {
      return request('PUT', '/' + id, body, { 'If-Match': '"' + String(version) + '"' })
        .then(function (r) {
          var kind = classify(r);
          if (kind === 'ok') {
            setStatus('live');
            var etag = r.headers && r.headers.get ? r.headers.get('ETag') : null;
            return { mode: 'api', etag: etag, status: r.status };
          }
          if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
          if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
          if (r.status === 412) return { mode: 'conflict', status: r.status };
          return { mode: 'error', status: r.status, message: errorText(r) };
        });
    },

    move: function (id, version, body) {
      var headers = (version != null && version !== '') ? { 'If-Match': '"' + String(version) + '"' } : {};
      return request('PATCH', '/' + id + '/position', body, headers).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') {
          setStatus('live');
          var etag = r.headers && r.headers.get ? r.headers.get('ETag') : null;
          return { mode: 'api', etag: etag, status: r.status };
        }
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        if (r.status === 412) return { mode: 'conflict', status: r.status };
        return { mode: 'error', status: r.status, message: errorText(r) };
      });
    },

    remove: function (id, version) {
      var headers = (version != null && version !== '') ? { 'If-Match': '"' + String(version) + '"' } : {};
      return request('DELETE', '/' + id, null, headers).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') { setStatus('live'); return { mode: 'api', status: r.status }; }
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        if (r.status === 412) return { mode: 'conflict', status: r.status };
        return { mode: 'error', status: r.status, message: errorText(r) };
      });
    }
  };
})();
