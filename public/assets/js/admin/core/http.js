/**
 * Единый транспорт для fetch-слоёв админки (раньше был скопирован в каждый *Api).
 * Всегда резолвится (не reject): сетевые ошибки приходят как { networkError }.
 */
import { tx } from './i18n.js';

/**
 * @returns {Promise<{status:number, ok:boolean, data:any, raw:string, isJson:boolean, headers:Headers|null, networkError?:any}>}
 */
export function httpRequest(method, url, body, headers) {
  var options = {
    method: method,
    credentials: 'same-origin',
    headers: Object.assign(
      { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      headers || {}
    )
  };
  if (body !== undefined && body !== null) {
    options.headers['Content-Type'] = 'application/json';
    options.body = JSON.stringify(body);
  }

  var run = (window.LoreCsrf && LoreCsrf.fetch) ? LoreCsrf.fetch : fetch;
  var result = function (st, ok, raw, hdrs) {
    var data = null;
    var isJson = false;
    if (raw && /^[\s]*[\[{]/.test(raw)) {
      try { data = JSON.parse(raw); isJson = true; } catch (_) { data = null; }
    }
    return { status: st, ok: ok, data: data, raw: raw || '', isJson: isJson, headers: hdrs || null };
  };

  return Promise.resolve()
    .then(function () { return run(url, options); })
    .then(function (res) {
      return res.text().then(
        function (raw) { return result(res.status, res.ok, raw, res.headers); },
        function () { return result(res.status, res.ok, '', res.headers); }
      );
    })
    .catch(function (err) {
      return { status: 0, ok: false, data: null, raw: '', isJson: false, headers: null, networkError: err };
    });
}

/**
 * Упрощённый вариант: ok=false, если вместо JSON пришёл HTML (страница ошибки/логина).
 * Используется списками пользователей и ролей пользователя.
 */
export function apiFetch(method, url, body) {
  return httpRequest(method, url, body).then(function (r) {
    return {
      ok: r.ok && !/^\s*</.test(r.raw || ''),
      status: r.status,
      data: r.data,
      raw: r.raw,
      networkError: r.networkError
    };
  });
}

/** Текст ошибки для результата apiFetch(). */
export function apiError(r) {
  if (r.networkError) return tx('errors.common.network_error', 'Network error. Try again.');
  if (r.status === 403) return tx('js.no_rights', 'You do not have permission to do this');
  var fallback = window.LoreI18n ? LoreI18n.t('errors.common.request_failed', { status: r.status }) : null;
  if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
  return fallback || ('HTTP ' + r.status);
}
