/**
 * Точка входа админки: инициализирует всё, что есть на странице.
 */
import './core/events.js'; // глобальные click/keydown/change-обработчики (Permissions, Block, Delete, ...)
import { initCommentsList } from './features/comments/list.js';
import { initSearch } from './core/search.js';
import { initAllRoles } from './features/all-roles/page.js';
import { initDictionary } from './features/dictionary/index.js';
import { initRoleUserModal } from './features/roles/assign-user-modal.js';
import { initRoleNew } from './features/roles/create/modal.js';
import { initRoles } from './features/roles/table.js';
import { initUsersList } from './features/users/list.js';
import { initUserRoles } from './features/users/roles/index.js';

// Кнопка «Назад на дашборд» — отдельным динамическим импортом: если файл не найден
// или падает, остальная админка продолжает работать. Путь считается от main.js.
function initBackLinkSafe() {
  import('./back-link.js')
    .then(function (m) { m.initBackLink(); })
    .catch(function (err) { console.warn('[admin] back-link не загружен:', err); });
}

function initAdmin() {
  initSearch();
  initDictionary();
  initRoles();
  initRoleNew();
  initRoleUserModal();
  initAllRoles();
  initUserRoles();
  initUsersList();
  initCommentsList();
  initBackLinkSafe();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdmin);
} else {
  initAdmin();
}