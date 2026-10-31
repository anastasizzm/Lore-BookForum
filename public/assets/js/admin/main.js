/**
 * Точка входа админки: инициализирует всё, что есть на странице.
 */
import { initSearch } from './core/search.js';
import { initAllRoles } from './features/all-roles/page.js';
import { initDictionary } from './features/dictionary/index.js';
import { initRoleUserModal } from './features/roles/assign-user-modal.js';
import { initRoleNew } from './features/roles/create/modal.js';
import { initRoles } from './features/roles/table.js';
import { initUsersList } from './features/users/list.js';
import { initUserRoles } from './features/users/roles/index.js';

function initAdmin() {
  initSearch();
  initDictionary();
  initRoles();
  initRoleNew();
  initRoleUserModal();
  initAllRoles();
  initUserRoles();
  initUsersList();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdmin);
} else {
  initAdmin();
}
