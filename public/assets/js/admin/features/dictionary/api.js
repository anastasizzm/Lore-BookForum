/**
 * Fetch-слой справочников: /api/additional/{genres|categories|types}.
 *
 * Список приходит локализованным: у записи есть `title` на языке запроса (?lang=),
 * поэтому в таблице показываем его. Все переводы (для формы правки) берём из
 * GET /api/additional/{resource}/{id}, если их нет в списке.
 */
import { httpRequest } from '../../core/http.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { takeBootstrap } from '../../core/bootstrap.js';
import { currentLang } from '../../core/utils.js';

export var dictApi = (function () {
  var BASE = '/api/additional/';
  var MAX_PAGES = 20;
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

  /** translations: [{langCode,title}] | {en:'..', ru:'..'} | [{lang_code,title}] → [{langCode,title}] */
  function normalizeTranslations(raw) {
    var rows = [];
    if (Array.isArray(raw)) {
      raw.forEach(function (row) {
        if (!row || typeof row !== 'object') return;
        var code = String(row.langCode || row.lang_code || row.lang || row.locale || row.code || '');
        if (!code) return;
        var title = row.title;
        if (title == null) title = row.name;
        rows.push({ langCode: code, title: String(title == null ? '' : title) });
      });
    } else if (raw && typeof raw === 'object') {
      Object.keys(raw).forEach(function (code) {
        var v = raw[code];
        var title = v && typeof v === 'object' ? (v.title != null ? v.title : v.name) : v;
        rows.push({ langCode: code, title: String(title == null ? '' : title) });
      });
    }
    return rows;
  }

  function normalizeItem(raw) {
    raw = unwrapItem(raw);
    if (!raw || typeof raw !== 'object') return null;
    var id = Number(raw.id);
    if (!id) return null;
    var title = raw.title != null ? raw.title : (raw.name != null ? raw.name : '');
    return {
      id: id,
      title: String(title || ''),
      lang: String(raw.langCode || raw.lang_code || raw.lang || ''),
      translations: normalizeTranslations(raw.translations)
    };
  }

  function itemsFrom(payload) {
    var list = listFrom(payload, ['genres', 'categories', 'types']);
    if (!list || !list.length) return list ? [] : null;
    var items = list.map(normalizeItem).filter(function (item) { return item !== null; });
    return items.length ? items : null;
  }

  function hasNext(payload) {
    var meta = payload && typeof payload === 'object' ? payload.meta : null;
    return !!(meta && meta.hasNext);
  }

  function errorText(r) {
    var fallback = null;
    if (window.LoreI18n) fallback = LoreI18n.t('errors.common.request_failed', { status: r.status });
    if (window.Messages && Messages.describe) return Messages.describe(r.status, r.data, r.raw, fallback);
    return fallback || ('HTTP ' + r.status);
  }

  function queryString(query, page) {
    var parts = ['lang=' + encodeURIComponent(currentLang()), 'page=' + page];
    if (query) parts.push('q=' + encodeURIComponent(query));
    return '?' + parts.join('&');
  }

  /** Все страницы подряд (справочники небольшие). Резолвится массивом или null. */
  function fetchPages(resource, query, page, acc) {
    return request('GET', resource + queryString(query, page)).then(function (r) {
      var kind = classify(r);
      if (kind === 'ok') {
        setStatus('live');
        var items = itemsFrom(r.data) || [];
        acc = acc.concat(items);
        if (hasNext(r.data) && page < MAX_PAGES) return fetchPages(resource, query, page + 1, acc);
        return acc;
      }
      if (kind === 'mock') { setStatus('mock'); return null; }
      setStatus('live');
      console.warn('[admin] GET ' + BASE + resource + ' failed with HTTP ' + r.status);
      return null;
    });
  }

  return {
    get status() { return status; },
    markUnknown: markUnknown,

    /**
     * list(resource)        — первая загрузка: сперва данные страницы (без запроса к API);
     * list(resource, query) — поиск на сервере (?q=), вызывается по Enter.
     */
    list: function (resource, query) {
      query = String(query || '').trim();
      if (status === 'mock') return Promise.resolve(null);

      if (!query) {
        var boot = takeBootstrap('dictionary');
        var seeded = boot === null ? null : itemsFrom(boot);
        if (seeded && seeded.length) {
          setStatus('live');
          if (hasNext(boot)) {
            return fetchPages(resource, '', 2, seeded).then(function (all) { return all || seeded; });
          }
          return Promise.resolve(seeded);
        }
      }
      return fetchPages(resource, query, 1, []);
    },

    /** Все переводы одной записи: GET /api/additional/{resource}/{id}. */
    retrieve: function (resource, id) {
      return request('GET', resource + '/' + id).then(function (r) {
        if (classify(r) !== 'ok') return null;
        return normalizeItem(unwrap(r.data));
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