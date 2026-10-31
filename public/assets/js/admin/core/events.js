/**
 * Глобальные обработчики document: click / search / keydown / change. Делегируют в features/*.
 */
import { tx } from './i18n.js';
import { notify } from './ui.js';
import { deleteAllRole } from '../features/all-roles/page.js';
import { deleteComment } from '../features/comments/delete.js';
import { addLanguage, closeDictForm, isDictOpen, openDictForm, removeLanguage } from '../features/dictionary/form.js';
import { dict } from '../features/dictionary/state.js';
import { deleteDictItem } from '../features/dictionary/table.js';
import { closeReportModal, decideReport, isReportOpen, openReportModal, reportState } from '../features/reports/modal.js';
import { closeRoleUserModal, openRoleUserModal, roleUserAssign } from '../features/roles/assign-user-modal.js';
import { handleRoleNewClick, openRoleNew } from '../features/roles/create/modal.js';
import { dnd, hideInsert, insertDisabled, moveQueue } from '../features/roles/dnd.js';
import { closeRoleDd, closeRoleModal, ddEl, isRoleDdOpen, isRoleOpen, openRoleAttach, openRoleModal, pickRoleDd, renderRoleLists, saveRole, searchRoleDd, toggleRoleDd } from '../features/roles/permissions-modal.js';
import { findRole, roleState } from '../features/roles/state.js';
import { deleteRole } from '../features/roles/table.js';
import { blockUser } from '../features/users/block.js';

document.addEventListener('click', function (e) {
  if (!e.target || !e.target.closest) return;

  // --- «+» между строками списка ролей (ROLES DND) ---
  if (e.target.closest('[data-role-insert]')) {
    if (insertDisabled()) { hideInsert(); return; }
    var at = dnd.insertAt;
    hideInsert();
    openRoleNew(at);
    return;
  }

  // --- Новая роль (ROLE CREATE) ---
  if (handleRoleNewClick(e.target)) return;

  // --- Справочники (Genres / Categories / Types) ---
  var dictAdd = e.target.closest('[data-admin-dict-add]');
  if (dictAdd) { openDictForm(null); return; }

  var dictEdit = e.target.closest('[data-admin-dict-edit]');
  if (dictEdit) { openDictForm(dictEdit.getAttribute('data-dict-row-id')); return; }

  var dictDelete = e.target.closest('[data-admin-dict-delete]');
  if (dictDelete) { deleteDictItem(dictDelete); return; }

  var langPick = e.target.closest('[data-dict-lang-pick]');
  if (langPick) {
    addLanguage(langPick.getAttribute('data-dict-lang-pick'));
    var dd = langPick.closest('[data-dropdown]');
    if (dd) dd.classList.remove('is-open');
    return;
  }

  var langRemove = e.target.closest('[data-dict-lang-remove]');
  if (langRemove) { removeLanguage(langRemove); return; }

  if (e.target.closest('[data-dict-dismiss], [data-dict-cancel]')) { closeDictForm(); return; }

  // --- Жалобы (REPORTS) ---
  var reportReview = e.target.closest('[data-admin-report-review]');
  if (reportReview) { openReportModal(reportReview); return; }

  if (e.target.closest('[data-admin-report-dismiss], [data-admin-report-cancel]')) { closeReportModal(); return; }

  if (e.target.closest('[data-admin-report-reject]')) { decideReport('reject'); return; }
  if (e.target.closest('[data-admin-report-delete]')) { decideReport('delete'); return; }

  // --- Системные роли (ROLES) ---
  // Кнопка «+» на странице roles: добавить роль в проект с правами
  if (e.target.closest('[data-role-add]')) {
    openRoleAttach();
    return;
  }

  // Дропдаун выбора роли в модалке «+»
  if (e.target.closest('[data-role-dd-toggle]')) { toggleRoleDd(); return; }
  var ddPick = e.target.closest('[data-role-dd-pick]');
  if (ddPick) { pickRoleDd(ddPick.getAttribute('data-role-dd-pick')); return; }
  if (isRoleOpen() && isRoleDdOpen() && !e.target.closest('[data-role-dd]')) closeRoleDd();

  // Назначение роли пользователю (отдельная модалка)
  if (e.target.closest('[data-role-user-open]')) {
    openRoleUserModal();
    return;
  }

  // Кнопка Permissions в строке системной роли
  var roleEdit = e.target.closest('[data-role-edit], [data-role-user-assign-role]');
  if (roleEdit) {
    if (moveQueue.isPending(roleEdit.getAttribute('data-role-id'))) { 
      notify(tx('admin.role_pending', 'The role is being moved. Try again in a moment'), 'error'); 
      return; 
    }
    if (roleEdit.hasAttribute('data-role-user-assign-role')) {
      openRoleUserModal(roleEdit.getAttribute('data-role-id'));
    } else {
      openRoleModal(roleEdit.getAttribute('data-role-id'));
    }
    return;
  }

  if (e.target.closest('[data-role-user-close]')) {
    closeRoleUserModal();
    return;
  }

  var roleDelete = e.target.closest('[data-role-delete]');
  if (roleDelete && !roleDelete.disabled) {
    if (moveQueue.isPending(roleDelete.getAttribute('data-role-id'))) { notify(tx('admin.role_pending', 'The role is being moved. Try again in a moment'), 'error'); return; }
    deleteRole(roleDelete);
    return;
  }

  if (e.target.closest('[data-role-close]')) { closeRoleModal(); return; }

  var roleSave = e.target.closest('[data-role-save]');
  if (roleSave && !roleSave.disabled) { saveRole(); return; }

  // --- Все роли (ALL ROLES) ---
  var allRolesDelete = e.target.closest('[data-allroles-delete]');
  if (allRolesDelete && !allRolesDelete.disabled) { 
    e.preventDefault();
    deleteAllRole(allRolesDelete); 
    return; 
  }

  // --- Пользователи / комментарии ---
  var blockBtn = e.target.closest('[data-admin-block]');
  if (blockBtn && !blockBtn.disabled) { blockUser(blockBtn); return; }

  var deleteBtn = e.target.closest('[data-admin-delete]');
  if (deleteBtn) deleteComment(deleteBtn);
});

// Поиск в дропдауне выбора роли: запрос к API только по Enter (и при очистке поля крестиком)
document.addEventListener('search', function (e) {
  var search = e.target && e.target.closest && e.target.closest('[data-role-dd-search]');
  if (search && (search.value || '').trim() === '' && roleState.ddQuery) searchRoleDd('');
});

document.addEventListener('keydown', function (e) {
  var search = e.target && e.target.closest && e.target.closest('[data-role-dd-search]');
  if (!search) return;
  if (e.key === 'Enter') {
    e.preventDefault();
    searchRoleDd(search.value);
  } else if (e.key === 'ArrowDown') {
    e.preventDefault();
    var item = ddEl('[data-role-dd-list] [data-role-dd-pick]');
    if (item) item.focus();
  }
});

// Чекбоксы прав роли: обновляем черновик (источник правды — roleState.draft)
document.addEventListener('change', function (e) {
  if (!e.target || !e.target.closest) return;

  var select = e.target.closest('[data-role-select]');
  if (select && roleState.draft && roleState.current && roleState.current.isNew) {
    var picked = select.value ? findRole(select.value) : null;
    if (picked) {
      roleState.draft.system = Object.assign({}, picked.systemPermissions);
      roleState.draft.posts  = Object.assign({}, picked.postPermissions);
      roleState.draft.pubs   = Object.assign({}, picked.pubPermissions);
      renderRoleLists();
    }
    return;
  }

  var cb = e.target.closest('[data-role-perm]');
  if (!cb || !roleState.draft) return;
  var group = cb.getAttribute('data-role-group');
  var key = cb.getAttribute('data-role-key');
  if (roleState.draft[group]) roleState.draft[group][key] = cb.checked;
});

document.addEventListener('keydown', function (e) {
  var dictOpen = isDictOpen();
  var reportOpen = isReportOpen();
  var roleOpen = isRoleOpen();
  var userRoleOpen = !!(roleUserAssign.modal && !roleUserAssign.modal.hidden);
  if (!dictOpen && !reportOpen && !roleOpen && !userRoleOpen) return;

  if (e.key === 'Escape') {
    e.preventDefault();
    if (roleOpen && isRoleDdOpen()) { closeRoleDd(); return; }
    if (dictOpen) closeDictForm();
    else if (reportOpen) closeReportModal();
    else if (userRoleOpen) closeRoleUserModal();
    else closeRoleModal();
    return;
  }
  if (e.key !== 'Tab') return;

  var dialog;
  if (dictOpen) dialog = dict.modal.querySelector('.modal__dialog');
  else if (reportOpen) dialog = reportState.modal.querySelector('.modal__dialog');
  else if (userRoleOpen) dialog = roleUserAssign.modal.querySelector('.modal__dialog');
  else dialog = roleState.modal.querySelector('.modal__dialog');
  if (!dialog) return;
  var items = Array.prototype.filter.call(
    dialog.querySelectorAll('button, [href], input, select, textarea, [tabindex]'),
    function (el) { return !el.disabled && el.offsetParent !== null; }
  );
  if (!items.length) return;

  var first = items[0];
  var last = items[items.length - 1];
  if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
  else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
});
