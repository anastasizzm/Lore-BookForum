/**
 * Точка входа админки: инициализирует всё, что есть на странице.
 */
import './core/events.js'; // глобальные click/keydown/change-обработчики (Permissions, Block, Delete, ...)
import { initSearch } from './core/search.js';
import { initAllRoles } from './features/all-roles/page.js';
import { initArticlesList } from './features/articles/list.js';
import { initBooksList } from './features/books/list.js';
import { initCommentsList } from './features/comments/list.js';
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
  initBooksList();
  initArticlesList();
  initCommentsList();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAdmin);
} else {
  initAdmin();
}