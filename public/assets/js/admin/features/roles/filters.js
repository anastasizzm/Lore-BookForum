/**
 * Фильтр списка ролей по статусу/названию и перерисовка.
 */
import { searchState } from '../../core/search.js';
import { filterRolesByTitle } from './search.js';
import { roleState } from './state.js';
import { renderRoleRows } from './table.js';

export function rolesStatusValue() {
  var el = document.querySelector('[data-roles-filter-status] input:checked');
  return el && el.value ? el.value : 'all';
}

export function filterRolesByStatus(items, status) {
  if (!status || status === 'all') return items;
  return items.filter(function (item) { return !item.status || item.status === status; });
}

export function applyRolesFilters(items, q) {
  return filterRolesByStatus(filterRolesByTitle(items, q), rolesStatusValue());
}

export function rerenderRoles() {
  renderRoleRows(applyRolesFilters(roleState.items, searchState ? searchState.serverQuery : ''));
}
