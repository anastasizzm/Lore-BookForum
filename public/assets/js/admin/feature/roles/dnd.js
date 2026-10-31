/**
 * Drag-and-drop порядка ролей: ghost, линия вставки, очередь PATCH /position.
 */
import { tx } from '../../core/i18n.js';
import { searchState } from '../../core/search.js';
import { notify } from '../../core/ui.js';
import { allRoles, refreshAllRoles } from '../all-roles/page.js';
import { rolesApi } from './api.js';
import { GRIP } from './constants.js';
import { rerenderRoles } from './filters.js';
import { footprint, lockedCount, moveBody, neighborsAt, neighborsOf, nextVersion, roleIndex, tailCount } from './order.js';
import { runRolesSearch } from './search.js';
import { findRole, roleState } from './state.js';

var DND_EDGE = 10;
var DND_SCROLL_ZONE = 64;
var DND_SCROLL_STEP = 14;
export var dnd = {
  wrap: null, line: null, plus: null,
  active: false, id: null, raw: -1, lastY: 0, offsetY: 0, pointerId: null,
  ghost: null, raf: 0,
  insertAt: null
};

function dndOn() {
  return true; // На roles.php фильтры убраны, список всегда полный
}

function rollbackMove(entry) {
  var items = roleState.items;
  var idx = roleIndex(entry.id);
  if (idx < 0) return;
  var item = items.splice(idx, 1)[0];

  var at = -1;
  if (entry.prevAboveId != null) { var a = roleIndex(entry.prevAboveId); if (a >= 0) at = a + 1; }
  if (at < 0 && entry.prevBelowId != null) { var b = roleIndex(entry.prevBelowId); if (b >= 0) at = b; }
  if (at < 0) at = entry.prevIndex;
  at = Math.max(lockedCount(), Math.min(items.length - tailCount(), at));

  items.splice(at, 0, item);
  rerenderRoles();
}

export var moveQueue = (function () {
  var inflight = [];
  var waiting = [];
  var idleCallbacks = [];
  var hadFailure = false;

  function mark(set, ids) { ids.forEach(function (id) { set[id] = true; }); }
  function hits(set, ids) { return ids.some(function (id) { return set[id] === true; }); }

  function pendingIds() {
    var set = {};
    inflight.concat(waiting).forEach(function (e) { set[e.id] = true; });
    return set;
  }

  function markRows() {
    var set = pendingIds();
    Array.prototype.forEach.call(document.querySelectorAll('[data-roles-rows] [data-role-row-id]'), function (row) {
      row.classList.toggle('is-pending', set[row.getAttribute('data-role-row-id')] === true);
    });
  }

  function failMessage(result) {
    if (result.mode === 'forbidden') return tx('js.no_rights', 'You do not have permission to do this');
    if (result.mode === 'conflict')  return tx('admin.role_conflict', 'The role was changed by someone else. Refresh the list and try again');
    return result.message || tx('admin.role_move_failed', 'Could not move the role');
  }

  function send(entry) {
    var item = findRole(entry.id);
    var version = item ? item.version : null;

    rolesApi.move(entry.id, version, moveBody(entry.body)).then(function (result) {
      inflight.splice(inflight.indexOf(entry), 1);

      if (result.mode === 'api' || result.mode === 'mock') {
        var current = findRole(entry.id);
        if (current) current.version = nextVersion(result.etag, current.version);
      } else {
        hadFailure = true;
        rollbackMove(entry);
        notify(failMessage(result), 'error');
      }
      pump();
    });
  }

  function pump() {
    var blocked = {};
    inflight.forEach(function (e) { mark(blocked, e.footprint); });

    var rest = [];
    var toSend = [];
    waiting.forEach(function (e) {
      var n = neighborsOf(e.id);
      if (!n) return;
      var fp = footprint(e.id, n);
      var mustWait = hits(blocked, fp);
      mark(blocked, fp);
      if (mustWait) { rest.push(e); return; }
      e.footprint = fp;
      e.body = n;
      inflight.push(e);
      toSend.push(e);
    });
    waiting = rest;
    markRows();
    toSend.forEach(send);
    settle();
  }

  function settle() {
    if (inflight.length || waiting.length) return;
    if (hadFailure) { hadFailure = false; resyncRoles(); }
    var cbs = idleCallbacks;
    idleCallbacks = [];
    cbs.forEach(function (cb) { cb(); });
  }

  return {
    enqueue: function (entry) { waiting.push(entry); pump(); },
    busy: function () { return inflight.length > 0 || waiting.length > 0; },
    isPending: function (id) { return pendingIds()[Number(id)] === true; },
    onIdle: function (cb) { if (this.busy()) idleCallbacks.push(cb); else cb(); },
    markRows: markRows
  };
})();

export function resyncRoles() {
  if (allRoles.table) { refreshAllRoles(); return; }
  rolesApi.list().then(function (items) {
    if (!items || moveQueue.busy()) return;
    roleState.items = items;
    rerenderRoles();
  });
}

function commitMove(id, to) {
  var items = roleState.items;
  var from = roleIndex(id);
  if (from < 0) return false;
  var locked = lockedCount();
  if (items[from].canManage === false) return false;
  to = Math.max(locked, Math.min(items.length - 1 - tailCount(), to));
  if (to === from) return false;

  var prev = neighborsAt(items, from);
  var item = items.splice(from, 1)[0];
  items.splice(to, 0, item);
  rerenderRoles();
  moveQueue.enqueue({ id: item.id, prevAboveId: prev.aboveId, prevBelowId: prev.belowId, prevIndex: from });
  return true;
}

export function placeCreatedRole(item, insertAt) {
  var items = roleState.items;
  var cur = roleIndex(item.id);
  if (cur < 0) return;
  var prev = neighborsAt(items, cur);
  items.splice(cur, 1);

  var at = -1;
  if (insertAt.aboveId != null) { var a = roleIndex(insertAt.aboveId); if (a >= 0) at = a + 1; }
  if (at < 0 && insertAt.belowId != null) { var b = roleIndex(insertAt.belowId); if (b >= 0) at = b; }
  if (at < 0) at = cur;
  at = Math.max(lockedCount(), Math.min(items.length - tailCount(), at));

  items.splice(at, 0, item);
  if (at === cur) return;
  moveQueue.enqueue({ id: item.id, prevAboveId: prev.aboveId, prevBelowId: prev.belowId, prevIndex: cur });
}

function dndRows() {
  return Array.prototype.filter.call(
    document.querySelectorAll('[data-roles-rows] [data-role-row-id]'),
    function (row) { return !row.hidden; }
  );
}

function rawIndexAt(rows, y) {
  for (var i = 0; i < rows.length; i++) {
    var r = rows[i].getBoundingClientRect();
    if (y < r.top + r.height / 2) return i;
  }
  return rows.length;
}

function boundaryY(rows, idx) {
  if (!rows.length) return 0;
  if (idx <= 0) return rows[0].getBoundingClientRect().top;
  return rows[Math.min(idx, rows.length) - 1].getBoundingClientRect().bottom;
}

function wrapTop(y, half) {
  var rect = dnd.wrap.getBoundingClientRect();
  var top = y - rect.top - dnd.wrap.clientTop + dnd.wrap.scrollTop;
  var table = dnd.wrap.querySelector('table');
  var content = table ? table.offsetTop + table.offsetHeight : dnd.wrap.clientHeight;
  var max = content - half; // граница ≤ низа таблицы, так что кружок стоит по центру линии
  return Math.max(0, Math.min(max, top - half));
}

function showLine(rows, idx) {
  if (!dnd.line) return;
  dnd.line.style.top = wrapTop(boundaryY(rows, idx), 1) + 'px';
  dnd.line.style.left = dnd.wrap.scrollLeft + 'px';
  dnd.line.hidden = false;
}

function hideLine() { if (dnd.line) dnd.line.hidden = true; }

export function hideInsert() {
  if (dnd.plus) {
    dnd.plus.hidden = true;
    dnd.plus.style.display = 'none'; 
  }
  dnd.insertAt = null;
  if (dnd.wrap) dnd.wrap.classList.remove('is-insert-tail');
  if (!dnd.active) hideLine();
}

/** Вставка роли «в это место» отключена на мобильной раскладке (≤720px) — там карточки. */
export function insertDisabled() {
  return !!(window.matchMedia && window.matchMedia('(max-width: 720px)').matches);
}

function onWrapPointerMove(e) {
  if (dnd.active || e.pointerType !== 'mouse') return;
  if (insertDisabled()) { hideInsert(); return; }
  if (e.target.closest && e.target.closest('[data-role-insert]')) return;
  if (!dndOn()) { hideInsert(); return; }

  var rows = dndRows();
  if (!rows.length) { hideInsert(); return; }

  var best = -1;
  var bestDist = DND_EDGE + 1;
  for (var b = 0; b <= rows.length; b++) {
    var d = Math.abs(e.clientY - boundaryY(rows, b));
    if (d < bestDist) { bestDist = d; best = b; }
  }
  if (best < 0 || best < lockedCount() || best > rows.length - tailCount()) { hideInsert(); return; }

  dnd.insertAt = {
    aboveId: best > 0 ? Number(rows[best - 1].getAttribute('data-role-row-id')) : null,
    belowId: best < rows.length ? Number(rows[best].getAttribute('data-role-row-id')) : null
  };
  // нижняя граница: добавляем место под таблицей, чтобы кружок «+» влез целиком
  dnd.wrap.classList.toggle('is-insert-tail', best === rows.length);
  dnd.plus.style.top = wrapTop(boundaryY(rows, best), 12) + 'px';
  dnd.plus.style.left = (dnd.wrap.scrollLeft + Math.max(0, (dnd.wrap.clientWidth - 24) / 2)) + 'px';
  dnd.plus.style.display = 'flex';
  dnd.plus.hidden = false;
  showLine(rows, best);
}

function makeGhost(row, y) {
  var rect = row.getBoundingClientRect();
  var ghost = document.createElement('div');
  ghost.className = 'admin-role-ghost';
  ghost.setAttribute('aria-hidden', 'true');
  ghost.style.width = rect.width + 'px';
  ghost.style.left = rect.left + 'px';
  ghost.style.top = rect.top + 'px';
  var title = row.querySelector('.admin-role__title');
  ghost.innerHTML = '<span class="admin-role-ghost__grip">' + GRIP + '</span>' + (title ? title.outerHTML : '');
  document.body.appendChild(ghost);
  dnd.ghost = ghost;
  dnd.offsetY = y - rect.top;
}

function dndFrame() {
  dnd.raf = 0;
  if (!dnd.active) return;
  var y = dnd.lastY;

  if (dnd.ghost) dnd.ghost.style.top = (y - dnd.offsetY) + 'px';

  var rows = dndRows();
  var raw = rawIndexAt(rows, y);
  var from = roleIndex(dnd.id);
  var valid = from >= 0 && raw >= lockedCount() && raw <= rows.length - tailCount() &&
              raw !== from && raw !== from + 1;
  dnd.raw = valid ? raw : -1;
  if (valid) showLine(rows, raw); else hideLine();

  var dy = 0;
  if (y < DND_SCROLL_ZONE) dy = -DND_SCROLL_STEP;
  else if (y > window.innerHeight - DND_SCROLL_ZONE) dy = DND_SCROLL_STEP;
  if (dy) {
    window.scrollBy(0, dy);
    dnd.raf = window.requestAnimationFrame(dndFrame);
  }
}

function scheduleFrame() {
  if (!dnd.raf) dnd.raf = window.requestAnimationFrame(dndFrame);
}

function onDragMove(e) {
  if (!dnd.active || e.pointerId !== dnd.pointerId) return;
  e.preventDefault();
  dnd.lastY = e.clientY;
  scheduleFrame();
}

function onDragUp(e) {
  if (!dnd.active || e.pointerId !== dnd.pointerId) return;
  dnd.lastY = e.clientY;
  dndFrame();
  endDrag(e.type !== 'pointercancel');
}

function onDragKey(e) {
  if (dnd.active && e.key === 'Escape') { e.preventDefault(); endDrag(false); }
}

function endDrag(commit) {
  var id = dnd.id;
  var raw = dnd.raw;

  document.removeEventListener('pointermove', onDragMove);
  document.removeEventListener('pointerup', onDragUp);
  document.removeEventListener('pointercancel', onDragUp);
  document.removeEventListener('keydown', onDragKey, true);
  if (dnd.raf) { window.cancelAnimationFrame(dnd.raf); dnd.raf = 0; }
  try { dnd.wrap.releasePointerCapture(dnd.pointerId); } catch (_) {}
  if (dnd.ghost && dnd.ghost.parentNode) dnd.ghost.parentNode.removeChild(dnd.ghost);
  dnd.ghost = null;
  hideLine();
  document.body.classList.remove('is-role-dragging');
  Array.prototype.forEach.call(document.querySelectorAll('[data-roles-rows] .is-dragging'), function (row) {
    row.classList.remove('is-dragging');
  });

  dnd.active = false;
  dnd.id = null;
  dnd.raw = -1;

  if (commit && raw >= 0) {
    var from = roleIndex(id);
    if (from >= 0) commitMove(id, raw > from ? raw - 1 : raw);
  }
}

function startDrag(id, e) {
  var row = document.querySelector('[data-roles-rows] [data-role-row-id="' + id + '"]');
  if (!row) return;

  hideInsert();
  dnd.active = true;
  dnd.id = Number(id);
  dnd.raw = -1;
  dnd.pointerId = e.pointerId;
  dnd.lastY = e.clientY;

  makeGhost(row, e.clientY);
  row.classList.add('is-dragging');
  document.body.classList.add('is-role-dragging');
  try { dnd.wrap.setPointerCapture(e.pointerId); } catch (_) {}

  document.addEventListener('pointermove', onDragMove, { passive: false });
  document.addEventListener('pointerup', onDragUp);
  document.addEventListener('pointercancel', onDragUp);
  document.addEventListener('keydown', onDragKey, true);
}

function onWrapPointerDown(e) {
  var handle = e.target.closest && e.target.closest('[data-role-handle]');
  if (!handle || dnd.active) return;
  if (e.pointerType === 'mouse' && e.button !== 0) return;
  if (!dndOn()) return;

  var row = handle.closest('[data-role-row-id]');
  var id = row ? Number(row.getAttribute('data-role-row-id')) : NaN;
  var item = isNaN(id) ? null : findRole(id);
  if (!item || item.canManage === false) return;

  e.preventDefault();
  startDrag(id, e);
}

function onWrapKeyDown(e) {
  var handle = e.target.closest && e.target.closest('[data-role-handle]');
  if (!handle || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
  e.preventDefault();
  if (!dndOn()) return;

  var row = handle.closest('[data-role-row-id]');
  var id = row ? Number(row.getAttribute('data-role-row-id')) : NaN;
  var from = isNaN(id) ? -1 : roleIndex(id);
  if (from < 0) return;

  if (commitMove(id, e.key === 'ArrowUp' ? from - 1 : from + 1)) {
    var again = document.querySelector('[data-roles-rows] [data-role-row-id="' + id + '"] [data-role-handle]');
    if (again) again.focus();
  }
}

export function initRoleDnd() {
  var table = document.querySelector('[data-roles-table]');
  var wrap = table ? table.closest('.admin-table-wrap') : null;
  if (!wrap) return;
  dnd.wrap = wrap;

  dnd.line = document.createElement('div');
  dnd.line.className = 'admin-role-dropline';
  dnd.line.hidden = true;
  wrap.appendChild(dnd.line);

  dnd.plus = document.createElement('button');
  dnd.plus.type = 'button';
  dnd.plus.className = 'admin-role-insert';
  dnd.plus.setAttribute('data-role-insert', '');
  dnd.plus.setAttribute('aria-label', tx('admin.role_insert_here', 'Add a role here'));
  dnd.plus.title = tx('admin.role_insert_here', 'Add a role here');
  dnd.plus.innerHTML = '<svg viewBox="0 0 12 12" width="12" height="12" aria-hidden="true" focusable="false">' +
    '<path d="M6 1.5v9M1.5 6h9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
  dnd.plus.style.display = 'none'; 
  dnd.plus.hidden = true;
  wrap.appendChild(dnd.plus);

  wrap.addEventListener('pointerdown', onWrapPointerDown);
  wrap.addEventListener('pointermove', onWrapPointerMove);
  wrap.addEventListener('pointerleave', function () { if (!dnd.active) hideInsert(); });
  wrap.addEventListener('keydown', onWrapKeyDown);
  wrap.addEventListener('scroll', hideInsert);
  window.addEventListener('scroll', function () { if (!dnd.active) hideInsert(); }, { passive: true });

  var statusSel = document.querySelector('[data-roles-filter-status]');
  if (statusSel) {
    statusSel.addEventListener('change', function () {
      runRolesSearch(searchState ? searchState.serverQuery : '');
    });
  }
}
