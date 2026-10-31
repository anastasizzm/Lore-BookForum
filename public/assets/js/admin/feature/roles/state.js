/**
 * Состояние страницы системных ролей и хелперы поиска/подсчёта прав.
 */
import { t } from '../../core/i18n.js';
import { currentLang } from '../../core/utils.js';
import { ROLE_GROUPS } from './constants.js';

export var roleState = {
  modal: null,
  titleEl: null,
  loading: null,
  save: null,
  items: [],
  current: null,
  draft: null,
  submitting: false,
  lastFocus: null
};

export function findRole(id) {
  for (var i = 0; i < roleState.items.length; i++) {
    if (Number(roleState.items[i].id) === Number(id)) return roleState.items[i];
  }
  return null;
}

function countPerms(obj, keys) {
  var n = 0;
  keys.forEach(function (k) { if (obj && obj[k]) n++; });
  return n;
}

export function totalPerms(item) {
  return countPerms(item.systemPermissions, ROLE_GROUPS.system) +
         countPerms(item.postPermissions,   ROLE_GROUPS.posts) +
         countPerms(item.pubPermissions,    ROLE_GROUPS.pubs);
}

export function permsLabel(n) {
  var category = n === 1 ? 'one' : 'other';
  try {
    var locale = (window.LoreI18n && LoreI18n.locale) || currentLang();
    category = new Intl.PluralRules(locale).select(n);
  } catch (_) {}

  var fallback = n + (n === 1 ? ' permission' : ' permissions');
  var forms = [category, 'other'];
  for (var i = 0; i < forms.length; i++) {
    var key = 'admin.role_sys_perms_' + forms[i];
    var value = t(key, { n: n });
    if (value && value !== key) return value;
  }
  return fallback;
}
