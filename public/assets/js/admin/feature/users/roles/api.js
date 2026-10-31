/**
 * Загрузка ролей пользователя и каталога системных ролей.
 */
import { apiFetch } from '../../../core/http.js';
import { normalizeColor } from '../../../core/utils.js';
import { rolesApi } from '../../roles/api.js';
import { userRoles } from './state.js';

export function urCatalog() {
  if (userRoles.catalog) return Promise.resolve(userRoles.catalog);
  return rolesApi.list().then(function (items) {
    var map = {};
    var ids = {};
    (items || []).forEach(function (r) { map[r.id] = { title: r.title, color: r.color }; ids[r.id] = true; });
    if (items) { userRoles.catalog = map; userRoles.systemIds = ids; }
    return map;
  });
}

function urNormalize(x, catalog) {
  var role = (x && typeof x === 'object' && x.role && typeof x.role === 'object') ? x.role : x;
  // Если запись — связка «пользователь–роль», её собственный id НЕ id роли:
  // приоритет у roleId, затем id вложенной роли, и только потом id самой записи.
  var rawId;
  if (x && typeof x === 'object' && x.roleId != null) rawId = x.roleId;
  else if (role && typeof role === 'object') rawId = role.id != null ? role.id : role.roleId;
  else rawId = role;
  var id = Number(rawId);
  if (rawId == null || isNaN(id)) return null;
  var cat = catalog[id] || {};
  var obj = (role && typeof role === 'object') ? role : {};
  return {
    id: id,
    title: String(obj.title || obj.name || cat.title || ('#' + id)),
    color: normalizeColor(obj.color || cat.color || '')
  };
}

export function urLoad(userId) {
  return Promise.all([apiFetch('GET', '/api/system/users/' + encodeURIComponent(userId) + '/roles'), urCatalog()]).then(function (res) {
    var r = res[0], catalog = res[1];
    if (!r.ok) return null;
    var inner = (r.data && r.data.data && typeof r.data.data === 'object') ? r.data.data : r.data;
    var list = Array.isArray(inner) ? inner : (inner && Array.isArray(inner.items)) ? inner.items : [];
    if (list.length) console.debug('[admin] user roles, raw item:', list[0]);
    return list.map(function (x) { return urNormalize(x, catalog); }).filter(function (x) { return x !== null; });
  });
}
