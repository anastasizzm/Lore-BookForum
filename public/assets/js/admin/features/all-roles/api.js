/**
 * Fetch-слой справочника названий ролей: /api/additional/roles.
 */
import { httpRequest } from '../../core/http.js';
import { currentLang, normalizeColor } from '../../core/utils.js';
import { listFrom, unwrapItem } from '../../core/payload.js';
import { takeBootstrap } from '../../core/bootstrap.js';

export var allRolesApi = (function () {
  var BASE = '/api/additional/roles';
  var status = 'unknown';

  function table() { return document.querySelector('[data-allroles-table]'); }
  function setStatus(next) {
    if (status === next) return;
    status = next;
    var el = table();
    if (el) el.setAttribute('data-allroles-api', next);
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

  /**
   * Контракт сортировки списка ролей (GET /api/additional/roles).
   * TODO(бэк): сверить имя параметра и значения с RolesController / BasicModelSortBy.
   *   asc  — сначала Public, desc — сначала Private.
   */
  var SORT = { param: 'sort', values: { asc: 'visibility', desc: '-visibility' } };

  function serverMessage(r) {
    var e = r && r.data && r.data.error;
    if (e && e.details && typeof e.details === 'object') {
      var k = Object.keys(e.details)[0];
      if (k && e.details[k] && e.details[k][0]) return String(e.details[k][0]);
    }
    return (e && e.message) ? String(e.message) : '';
  }

  var warnedVisibility = false;

  /** visibility роли: строка (public/private) или булев флаг; бывает и во вложенном role */
  function visibilityOf(raw) {
    var nodes = [raw, raw.role, raw.item];
    for (var i = 0; i < nodes.length; i++) {
      var o = nodes[i];
      if (!o || typeof o !== 'object') continue;
      var v = o.visibility != null ? o.visibility
            : o.status != null ? o.status
            : o.access != null ? o.access
            : o.scope != null ? o.scope : null;
      if (typeof v === 'string' && v) {
        v = v.toLowerCase();
        if (v.indexOf('priv') !== -1 || v === 'hidden') return 'private';
        if (v.indexOf('pub') !== -1) return 'public';
        return v;
      }
      var pub = o.isPublic != null ? o.isPublic : o.is_public != null ? o.is_public : o.public;
      var priv = o.isPrivate != null ? o.isPrivate : o.is_private != null ? o.is_private : o.private;
      // булево может прийти строкой/числом ("t", "f", 1, 0, "true")
      var asBool = function (x) {
        if (x === true || x === 1 || x === '1' || x === 't' || x === 'true') return true;
        if (x === false || x === 0 || x === '0' || x === 'f' || x === 'false') return false;
        return null;
      };
      var pb = asBool(pub), rb = asBool(priv);
      if (pb !== null) return pb ? 'public' : 'private';
      if (rb !== null) return rb ? 'private' : 'public';
    }
    if (!warnedVisibility) {
      warnedVisibility = true;
      console.warn('[admin] all-roles: no visibility field in role item, keys:', Object.keys(raw), raw);
    }
    return '';
  }

  function normalize(raw) {
    raw = unwrapItem(raw);
    if (!raw || typeof raw !== 'object') return null;
    var id = Number(raw.id);
    if (!id && id !== 0) return null;
    
    var title = raw.title || raw.name || '';
    var translations = Array.isArray(raw.translations) ? raw.translations : [];
    
    if (!title && translations.length > 0) {
       var lang = currentLang().slice(0, 2).toLowerCase();
       var found = translations.find(function(tr) { return String(tr.langCode || tr.lang_code || tr.lang).slice(0, 2).toLowerCase() === lang; });
       title = found ? (found.title || found.name) : (translations[0].title || translations[0].name);
    }
    
    if (!title && raw.translations && typeof raw.translations === 'object' && !Array.isArray(raw.translations)) {
       var transKeys = Object.keys(raw.translations);
       title = raw.translations[currentLang()] || (transKeys.length ? raw.translations[transKeys[0]] : '') || '';
    }

    var p = raw.permissions || {};
    var systemPermissions = raw.systemPermissions || p.system || p.systemPermissions || {};
    var postPermissions = raw.postPermissions || p.posts || p.postPermissions || {};
    var pubPermissions = raw.pubPermissions || p.pubs || p.publications || p.pubPermissions || {};

    return {
      id: id,
      title: String(title || ''),
      visibility: visibilityOf(raw),
      color: normalizeColor(raw.color),
      canManage: raw.canManage !== false,
      version: raw.version != null ? raw.version : 1,
      systemPermissions: typeof systemPermissions === 'object' ? systemPermissions : {},
      postPermissions: typeof postPermissions === 'object' ? postPermissions : {},
      pubPermissions: typeof pubPermissions === 'object' ? pubPermissions : {}
    };
  }

  function fromPayload(payload) {
    var list = listFrom(payload, ['roles']);
    if (!list) return null;
    return list.map(normalize).filter(function (x) { return x !== null; });
  }

  return {
    get status() { return status; },
    list: function (query, statusFilter, sort) {
      // Первая загрузка без фильтров — из данных, которые отдал сервер вместе со страницей
      if (!query && !sort && (!statusFilter || statusFilter === 'all')) {
        var boot = takeBootstrap('all-roles');
        var seeded = boot === null ? null : fromPayload(boot);
        if (seeded && seeded.length) { setStatus('live'); return Promise.resolve(seeded); }
      }
      var parts = [];
      if (query) parts.push('q=' + encodeURIComponent(query));
      if (statusFilter && statusFilter !== 'all') parts.push('status=' + encodeURIComponent(statusFilter));
      // Сортировку выполняет бэк: ?sort=<значение>. Имя параметра и значения — в SORT (ниже).
      if (sort && SORT.values[sort]) parts.push(SORT.param + '=' + encodeURIComponent(SORT.values[sort]));
      var qs = parts.length ? '?' + parts.join('&') : '';
      return request('GET', qs).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') {
          var items = fromPayload(r.data);
          if (items === null) return null;
          setStatus('live');
          return items;
        }
        if (kind === 'mock') { setStatus('mock'); return null; }
        return null;
      });
    },
    remove: function (id) {
      return request('DELETE', '/' + id).then(function (r) {
        var kind = classify(r);
        if (kind === 'ok') { setStatus('live'); return { mode: 'api', status: r.status }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status, message: serverMessage(r) };
        // 404/405/HTML/сеть — это НЕ успех: роль на сервере не удалена, строку локально не убираем
        return { mode: 'error', status: r.status, message: serverMessage(r) || ('HTTP ' + r.status) };
      });
    }
  };
})();