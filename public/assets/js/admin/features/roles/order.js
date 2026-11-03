/**
 * Позиции ролей в списке: индексы, соседи, закреплённые роли, ETag-версии.
 */
import { roleState } from './state.js';

export function roleIndex(id) {
  for (var i = 0; i < roleState.items.length; i++) {
    if (Number(roleState.items[i].id) === Number(id)) return i;
  }
  return -1;
}

export function lockedCount() {
  var n = 0;
  while (n < roleState.items.length && roleState.items[n].canManage === false) n++;
  return n;
}

function isBaseRole(item) {
  return !!item && Number(item.id) === 0;
}

/** Раньше базовая роль (id 0) была прибита в конец; теперь двигается как все, хвоста нет. */
export function tailCount() {
  return 0;
}

export function neighborsAt(list, index) {
  return {
    aboveId: index > 0 ? list[index - 1].id : null,
    belowId: index < list.length - 1 ? list[index + 1].id : null
  };
}

export function neighborsOf(id) {
  var i = roleIndex(id);
  return i < 0 ? null : neighborsAt(roleState.items, i);
}

export function footprint(id, n) {
  var out = [Number(id)];
  if (n.aboveId != null) out.push(Number(n.aboveId));
  if (n.belowId != null) out.push(Number(n.belowId));
  return out;
}

export function moveBody(n) {
  return {
    aboveId: n.aboveId != null ? n.aboveId : null,
    belowId: n.belowId != null ? n.belowId : null
  };
}

export function nextVersion(etag, current) {
  var parsed = etag ? parseInt(String(etag).replace(/[^0-9]/g, ''), 10) : NaN;
  return isNaN(parsed) ? (Number(current) + 1) : parsed;
}
