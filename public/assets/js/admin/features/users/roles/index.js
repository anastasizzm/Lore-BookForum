/**
 * Инициализация вложенных ролей пользователей.
 */
import { urRemove, urToggle } from './list.js';
import { urClose, urDdClose, urDdIsOpen, urDdToggle, urOpenAdd, urPick, urSave, urSearch } from './modal.js';
import { isUserRoleOpen, um, userRoles } from './state.js';

export function initUserRoles() {
  var table = document.querySelector('[data-admin-table="users"]');
  if (!table) return;
  userRoles.table = table;
  userRoles.modal = document.querySelector('[data-urole-modal]');

  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) return;

    var expand = e.target.closest('[data-user-expand]');
    if (expand) { urToggle(expand); return; }

    var add = e.target.closest('[data-user-role-add]');
    if (add) { urOpenAdd(add); return; }

    var remove = e.target.closest('[data-user-role-remove]');
    if (remove && !remove.disabled) { urRemove(remove); return; }

    if (!isUserRoleOpen()) return;
    if (e.target.closest('[data-urole-close]')) { urClose(); return; }
    if (e.target.closest('[data-urole-dd-toggle]')) { urDdToggle(); return; }
    var pick = e.target.closest('[data-urole-dd-pick]');
    if (pick) { urPick(pick.getAttribute('data-urole-dd-pick')); return; }
    var save = e.target.closest('[data-urole-save]');
    if (save && !save.disabled) { urSave(); return; }
    if (urDdIsOpen() && !e.target.closest('[data-urole-dd]')) urDdClose();
  });

  document.addEventListener('search', function (e) {
    var s = e.target && e.target.closest && e.target.closest('[data-urole-dd-search]');
    if (s && (s.value || '').trim() === '' && userRoles.query) urSearch('');
  });

  document.addEventListener('keydown', function (e) {
    if (!isUserRoleOpen()) return;
    var s = e.target && e.target.closest && e.target.closest('[data-urole-dd-search]');
    if (s && e.key === 'Enter') { e.preventDefault(); urSearch(s.value); return; }
    if (s && e.key === 'ArrowDown') {
      e.preventDefault();
      var first = um('[data-urole-dd-list] [data-urole-dd-pick]');
      if (first) first.focus();
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      if (urDdIsOpen()) urDdClose(); else urClose();
      return;
    }
    if (e.key !== 'Tab') return;
    var dialog = userRoles.modal.querySelector('.modal__dialog');
    if (!dialog) return;
    var items = Array.prototype.filter.call(
      dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
      function (el) { return !el.disabled && el.offsetParent !== null; }
    );
    if (!items.length) return;
    var firstEl = items[0], lastEl = items[items.length - 1];
    if (e.shiftKey && document.activeElement === firstEl) { e.preventDefault(); lastEl.focus(); }
    else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); firstEl.focus(); }
  });
}
