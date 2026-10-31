/**
 * Fetch-слой жалоб: PATCH /api/admin/reports/{id} с фолбэком на мок.
 */
import { httpRequest } from '../../core/http.js';

export var reportsApi = (function () {
  var BASE = '/api/admin/reports';
  var status = 'unknown';

  function table() { return document.querySelector('[data-admin-table="reports"]'); }

  function setStatus(next) {
    if (status === next) return;
    status = next;
    var el = table();
    if (el) el.setAttribute('data-reports-api', next);
    if (next === 'mock') {
      console.warn('[admin] reports API is not available yet — decisions stay on page mocks');
    }
  }

  function request(method, path, body) {
    return httpRequest(method, BASE + (path || ''), body);
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

  function errorText(r) {
    var fallback = null;
    if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
    if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
    return fallback || ('HTTP ' + r.status);
  }

  return {
    get status() { return status; },
    decide: function (id, decision, note) {
      return request('PATCH', '/' + id, { decision: decision, note: note }).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') { setStatus('live'); return { mode: 'api', status: r.status }; }
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        return { mode: 'error', status: r.status, message: errorText(r) };
      });
    }
  };
})();
