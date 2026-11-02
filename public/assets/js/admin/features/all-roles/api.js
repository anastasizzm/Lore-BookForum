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
      if (o.isPrivate === true || o.private === true) return 'private';
      if (o.isPublic === true || o.public === true) return 'public';
      if (o.isPrivate === false || o.private === false) return 'public';
      if (o.isPublic === false || o.public === false) return 'private';
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
    var rawId = raw.id != null ? raw.id
              : raw.roleId != null ? raw.roleId
              : (raw.role && typeof raw.role === 'object' && raw.role.id != null) ? raw.role.id
              : null;
    var id = Number(rawId);
    if (!id) {
      console.warn('[admin] all-roles: item without valid id, keys:', Object.keys(raw), raw);
      return null;
    }
    
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
    list: function (query, statusFilter) {
      // Первая загрузка без фильтров — из данных, которые отдал сервер вместе со страницей
      if (!query && (!statusFilter || statusFilter === 'all')) {
        var boot = takeBootstrap('all-roles');
        var seeded = boot === null ? null : fromPayload(boot);
        if (seeded && seeded.length) { setStatus('live'); return Promise.resolve(seeded); }
      }
      var parts = [];
      if (query) parts.push('q=' + encodeURIComponent(query));
      if (statusFilter && statusFilter !== 'all') parts.push('status=' + encodeURIComponent(statusFilter));
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
        if (kind === 'mock') { setStatus('mock'); return { mode: 'mock' }; }
        if (kind === 'forbidden') return { mode: 'forbidden', status: r.status };
        return { mode: 'error', status: r.status, message: 'HTTP ' + r.status };
      });
    }
  };
})();