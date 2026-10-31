/**
 * Поиск по ролям (по Enter).
 */
import { t } from '../../core/i18n.js';
import { notify } from '../../core/ui.js';
import { rolesApi } from './api.js';
import { moveQueue } from './dnd.js';
import { applyRolesFilters, filterRolesByStatus, rolesStatusValue } from './filters.js';
import { roleState } from './state.js';
import { renderRoleRows } from './table.js';

var rolesSearchSeq = 0;
var ROLES_SEARCH_SERVER = false;
export function filterRolesByTitle(items, q) {
  var low = String(q || '').toLowerCase();
  return items.filter(function (item) {
    return !low || String(item.title || '').toLowerCase().indexOf(low) !== -1;
  });
}

export function runRolesSearch(query) {
  if (moveQueue.busy()) { moveQueue.onIdle(function () { runRolesSearch(query); }); return; }

  var q = String(query || '').trim();
  var seq = ++rolesSearchSeq;

  rolesApi.list(ROLES_SEARCH_SERVER ? q : '', rolesStatusValue()).then(function (items) {
    if (seq !== rolesSearchSeq) return;

    if (items === null) {
      if (rolesApi.status === 'mock') { renderRoleRows(applyRolesFilters(roleState.items, q)); return; }
      notify(rolesApi.lastError || t('errors.common.network_error'), 'error');
      return;
    }
    roleState.items = items;
    renderRoleRows(ROLES_SEARCH_SERVER ? filterRolesByStatus(items, rolesStatusValue()) : applyRolesFilters(items, q));
  });
}
