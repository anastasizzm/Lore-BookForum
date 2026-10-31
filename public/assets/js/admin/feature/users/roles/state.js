/**
 * Состояние вложенного списка ролей пользователя.
 */

export var userRoles = {
  table: null, modal: null,
  cache: {},          // userId -> [{ id, title, color }]
  catalog: null,      // id -> { title, color } по системным ролям
  systemIds: null,    // id -> true (null, если список не получили)
  current: null,      // { id, name }
  selected: null,
  options: [],
  query: '',
  searching: false,
  seq: 0,
  submitting: false,
  lastFocus: null
};

export function um(sel) { return userRoles.modal ? userRoles.modal.querySelector(sel) : null; }

export function isUserRoleOpen() { return !!(userRoles.modal && !userRoles.modal.hidden); }
