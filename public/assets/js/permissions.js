/**
 * permissions.js - window.LorePermissions: единая точка вопроса «можно ли?».
 *
 * Надстройка над LoreRules (rules.js): транспорт и кеш остаются там, здесь
 * живёт маппер «ключ права -> эндпоинт + флаг» и привязка к DOM.
 * Подключать ПОСЛЕ rules.js.
 *
 * Ключи ("область.право"):
 *   system.*       - GET /api/system/rules
 *   publication.*  - GET /api/books/rules или /api/articles/rules (ctx.kind)
 *   post.*         - GET /api/posts/rules
 *   admin.panel    - составное: любое из admin-прав (AdminAccessOperation)
 *
 * Результат can():
 *   true  - право подтверждено;
 *   false - права нет;
 *   null  - правила не пришли (сеть / 4xx / 5xx) и владельцем пользователь
 *           не является. В DOM null трактуется как «нельзя» (элемент скрыт).
 *
 * Это ТОЛЬКО видимость интерфейса: доступ всегда проверяет бэк.
 *
 * Привязка к DOM (разметка по умолчанию с атрибутом hidden):
 *   data-requires="system.manageAdditionals system.managePeople"  любое из
 *   data-requires-all="..."                                       все сразу
 *   data-requires-kind="book|article"   для publication.* ключей
 *   data-requires-owner="1"             текущий пользователь - автор
 *   data-requires-mode="hide|disable"   по умолчанию hide
 *
 * Для контента, который JS рисует позже, вызывайте
 * LorePermissions.apply(контейнер).
 */
(function (global) {
  'use strict';

  /**
   * Маппер. flags - имена полей ответа бэка; owner:true - автор публикации
   * получает право всегда (owner-override, см. комментарий в rules.js).
   */
  var MAP = {
    // --- system: ManageAccountOperation и AdminAccessOperation-семейство
    // Имя флага бэка - во множественном числе (manageAccounts), у операции - в единственном.
    // BlockAccountOperation в /api/system/rules флага не имеет - ключа для неё нет.
    'system.manageAccounts':    { kind: 'system', flags: ['manageAccounts'] },
    'system.manageAdditionals': { kind: 'system', flags: ['manageAdditionals'] },
    'system.managePeople':      { kind: 'system', flags: ['managePeople'] },
    'system.manageRoles':       { kind: 'system', flags: ['manageRoles'] },

    // --- publication: книги и статьи, область выбирается по ctx.kind
    'publication.create':        { kind: 'publication', flags: ['create'] },
    'publication.read':          { kind: 'publication', flags: ['read'],          owner: true },
    'publication.updateMeta':    { kind: 'publication', flags: ['updateMeta'],    owner: true },
    'publication.updateContent': { kind: 'publication', flags: ['updateContent'], owner: true },
    'publication.edit':          { kind: 'publication', flags: ['updateMeta', 'updateContent'], owner: true },
    // Отдельного флага publish бэк не отдаёт: по договорённости updateMeta || updateContent
    'publication.publish':       { kind: 'publication', flags: ['updateMeta', 'updateContent'], owner: true },
    // Публикация НОВОЙ публикации из формы создания - опирается на create
    'publication.publishNew':    { kind: 'publication', flags: ['create'] },
    'publication.delete':        { kind: 'publication', flags: ['delete'],        owner: true },
    'publication.managePeople':  { kind: 'publication', flags: ['managePeople'],  owner: true },
    'publication.manageRoles':   { kind: 'publication', flags: ['manageRoles'],   owner: true },

    // --- post
    'post.create': { kind: 'post', flags: ['create'] },
    'post.update': { kind: 'post', flags: ['update'], owner: true },
    'post.delete': { kind: 'post', flags: ['delete'], owner: true },

    // --- составные
    // Админ-панель видна, если разрешено хоть одно право из AdminAccessOperation.
    'admin.panel': { any: ['system.manageAdditionals', 'system.managePeople', 'system.manageRoles'] }
  };

  function warn(msg) {
    if (global.console && console.warn) console.warn('[permissions] ' + msg);
  }

  /** Область правил LoreRules для определения; null - определить не удалось. */
  function rulesKind(def, ctx) {
    if (def.kind === 'system') return 'system';
    if (def.kind === 'post')   return 'posts';
    var k = String((ctx && ctx.kind) || '').toLowerCase();
    if (k.indexOf('article') === 0) return 'articles';
    if (k.indexOf('book') === 0)    return 'books';
    return null;
  }

  /** Трёхзначное объединение: mode 'any' или 'all'. */
  function combine(values, mode) {
    var hasNull = false;
    var i;
    if (mode === 'any') {
      for (i = 0; i < values.length; i++) {
        if (values[i] === true) return true;
        if (values[i] === null) hasNull = true;
      }
      return hasNull ? null : false;
    }
    for (i = 0; i < values.length; i++) {
      if (values[i] === false) return false;
      if (values[i] === null) hasNull = true;
    }
    return hasNull ? null : true;
  }

  /**
   * Можно ли? ctx: { kind: 'book'|'article' (для publication.*), isOwner: bool }
   * @returns {Promise<boolean|null>}
   */
  function can(key, ctx) {
    var def = MAP[key];
    if (!def) {
      warn('unknown permission key "' + key + '"');
      return Promise.resolve(false);
    }

    if (def.any) return canAny(def.any, ctx);

    if (!global.LoreRules) {
      warn('rules.js is not loaded');
      return Promise.resolve(null);
    }

    var kind = rulesKind(def, ctx);
    if (!kind) {
      warn('"' + key + '" needs ctx.kind (book|article)');
      return Promise.resolve(null);
    }

    if (def.owner === true && ctx && ctx.isOwner) return Promise.resolve(true);

    return global.LoreRules.load(kind).then(function (rules) {
      if (!rules) return null;
      return def.flags.some(function (flag) { return !!rules[flag]; });
    });
  }

  function canMany(keys, ctx, mode) {
    return Promise.all(keys.map(function (k) { return can(k, ctx); }))
      .then(function (values) { return combine(values, mode); });
  }

  /** Разрешено хотя бы одно из keys. */
  function canAny(keys, ctx) { return canMany(keys, ctx, 'any'); }

  /** Разрешены все keys. */
  function canAll(keys, ctx) { return canMany(keys, ctx, 'all'); }

  // ---------- DOM ----------

  function splitKeys(value) {
    return String(value || '').split(/\s+/).filter(Boolean);
  }

  function setAllowed(el, allowed) {
    var mode = el.getAttribute('data-requires-mode') || 'hide';
    if (mode === 'disable') {
      if ('disabled' in el) el.disabled = !allowed;
      el.setAttribute('aria-disabled', String(!allowed));
      el.classList.toggle('is-disabled', !allowed);
    } else {
      el.hidden = !allowed;
    }
    el.dispatchEvent(new CustomEvent('permissions:applied', {
      bubbles: true,
      detail: { allowed: allowed }
    }));
  }

  /** Применить data-requires* внутри root (по умолчанию - вся страница). */
  function apply(root) {
    var scope = root || document;
    var items = scope.querySelectorAll('[data-requires], [data-requires-all]');

    Array.prototype.forEach.call(items, function (el) {
      var anyKeys = splitKeys(el.getAttribute('data-requires'));
      var allKeys = splitKeys(el.getAttribute('data-requires-all'));
      var owner = el.getAttribute('data-requires-owner');
      var ctx = {
        kind: el.getAttribute('data-requires-kind'),
        isOwner: owner === '1' || owner === 'true'
      };

      var checks = [];
      if (anyKeys.length) checks.push(canAny(anyKeys, ctx));
      if (allKeys.length) checks.push(canAll(allKeys, ctx));

      Promise.all(checks).then(function (values) {
        setAllowed(el, combine(values, 'all') === true);
      });
    });
  }

  global.LorePermissions = {
    can: can,
    canAny: canAny,
    canAll: canAll,
    apply: apply,
    keys: function () { return Object.keys(MAP); }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { apply(); });
  } else {
    apply();
  }
})(window);