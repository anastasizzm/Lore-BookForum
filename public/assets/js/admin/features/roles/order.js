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

/** Базовая роль (id 0, «User»): у неё самый маленький lexorank, ниже неё ничего поставить нельзя. */
export function isBaseRole(item) {
  return !!item && Number(item.id) === 0;
}

/**
 * Сколько ролей закреплено в конце списка: базовая роль всегда последняя.
 * Бэк считает ранг новой роли между соседями (LexoRank::between) и падает, если роль
 * пытаются поставить под базовую (её ранг 0000000000 — минимальный).
 */
export function tailCount() {
  var items = roleState.items;
  return items.length && isBaseRole(items[items.length - 1]) ? 1 : 0;
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