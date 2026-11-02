/**
 * Константы страницы ролей: группы прав, иконки, палитра.
 */

export var ROLE_GROUPS = {
  system: ['manageAccounts', 'manageAdditionals', 'managePeople', 'manageRoles'],
  posts:  ['create', 'update', 'delete'],
  pubs:   ['create', 'updateMeta', 'updateContent', 'delete', 'read', 'managePeople', 'manageRoles']
};

export var GRIP = '\u283F';
/** Иконка мусорки для кнопок удаления (текст остаётся в title/aria-label). */
export var TRASH = '<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false">' +
  '<path d="M2.5 4h11M6 4V2.5h4V4M4 4l.7 9a1 1 0 0 0 1 .9h4.6a1 1 0 0 0 1-.9L12 4M6.5 7v4M9.5 7v4" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';

export var ROLE_COLORS = ['#D64545', '#1C7ED6', '#2F9E44', '#F08C00', '#7048E8', '#0CA678', '#D6336C', '#495057'];
