/**
 * rules.js — доступ к Common Rules бэкенда (window.LoreRules).
 *
 * Бэк отдаёт глобальные правила текущего пользователя тремя GET-ами
 * (все под AuthPolicy::Auth, контракт — config/endpoints.php):
 *
 *   GET /api/system/rules       -> { manageAccounts, manageAdditionals,
 *                                    managePeople, manageRoles }
 *   GET /api/publications/rules -> { create, updateMeta, updateContent,
 *                                    delete, read, managePeople,
 *                                    manageRoles }   (PublicationAccessRules;
 *                                    общий для книг И статей — бэк больше
 *                                    не разделяет их на /api/books/rules
 *                                    и /api/articles/rules)
 *   GET /api/posts/rules        -> { create, update, delete }
 *
 * Ответ каждого оборачивается в { "data": { ... } } (Controller::jsonObject).
 *
 * ВАЖНО про владельца:
 *   Глобальные правила НЕ знают про владельца публикации — колонку
 *   `p.owner_id = ru.user_id` добавляет только per-publication провайдер
 *   (app/Repositories/RuleProviders/Concrete/PublicationRuleProvider.php),
 *   у которого эндпоинта нет. Поэтому фронт ОБЯЗАН складывать правила
 *   с признаком «это моя публикация»: автор на своей книге правит всегда.
 *
 * ВАЖНО про publish:
 *   Отдельного флага `publish` в rules нет, поэтому publish маппится на
 *   updateMeta || updateContent, а публикация новой публикации из формы
 *   создания (publish_new) — на create.
 *
 * Значения canXxx():
 *   true  — право подтверждено, включаем элемент;
 *   false — права нет, элемент остаётся серым неактивным;
 *   null  — правила не пришли (нет сети / 4xx / 5xx) — оставляем как есть.
 */
(function (global) {
  'use strict';

  var ENDPOINTS = {
    // Книги и статьи читают ОДИН общий роут (бэк перенёс правила в
    // PublicationPermissionsController, см. config/endpoints.php):
    books:    '/api/publications/rules',
    articles: '/api/publications/rules',
    posts:    '/api/posts/rules',
    system:   '/api/system/rules'
  };

  /**
   * Действие -> правила, которые его разрешают (PublicationAccessRules).
   *
   * publish      — перевод УЖЕ существующего черновика в public.
   *                Отдельного флага `publish` бэк не отдаёт, поэтому по
   *                договорённости маппим на updateMeta || updateContent.
   * publish_new  — публикация НОВОЙ публикации прямо из формы создания
   *                (POST /api/books/new + POST /books/{id}/publish). Здесь
   *                права на обновление ещё нет — публикация и есть создание,
   *                поэтому опираемся на create (роут /books/new бэк и так
   *                закрывает политикой CreatePublicationOperation).
   */
  var PUBLICATION_ACTIONS = {
    create:      ['create'],
    edit:        ['updateMeta', 'updateContent'],
    update:      ['updateMeta', 'updateContent'],
    publish:     ['updateMeta', 'updateContent'],
    publish_new: ['create'],
    delete:      ['delete']
  };

  /** @type {Object<string, Promise<object|null>>} */
  var cache = {};

  /** Тот же csrfFetch-идиом, что в book-form.js: GET токена не требует. */
  function request(url) {
    return fetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
  }

  /**
   * Правила указанного вида. Результат кешируется на время жизни страницы —
   * один GET на вид, а не на каждый элемент.
   * @returns {Promise<object|null>} null — если правила получить не удалось.
   */
  function load(kind) {
    if (Object.prototype.hasOwnProperty.call(cache, kind)) return cache[kind];

    var url = ENDPOINTS[kind];
    if (!url) return Promise.resolve(null);

    cache[kind] = request(url)
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (json) {
        var payload = (json && json.data && typeof json.data === 'object') ? json.data : json;
        return (payload && typeof payload === 'object') ? payload : null;
      })
      .catch(function (err) {
        console.warn('[rules] could not load ' + kind + ' rules', err);
        return null;
      });

    return cache[kind];
  }

  /** Любыми ли из $flags наделён пользователь (или он владелец). */
  function anyFlag(rules, flags, isOwner) {
    // Владелец правит своей публикацией всегда (owner-override на бэке).
    if (isOwner) return true;
    if (!rules) return null;            // прав не знаем — не гадаем
    return flags.some(function (flag) { return !!rules[flag]; });
  }

  /**
   * Право на действие над публикацией.
   * @param {'books'|'articles'|'posts'} kind
   * @param {string} action  create|edit|update|publish|delete
   * @param {boolean} isOwner  текущий пользователь — автор/владелец
   * @returns {Promise<boolean|null>}
   */
  function canPublication(kind, action, isOwner) {
    var flags = PUBLICATION_ACTIONS[action];
    if (!flags) return Promise.resolve(false);
    return load(kind).then(function (rules) {
      return anyFlag(rules, flags, !!isOwner);
    });
  }

  /** Системное правило (/api/system/rules). */
  function canSystem(flag, fallback) {
    return load('system').then(function (rules) {
      if (!rules) return (fallback === undefined ? null : fallback);
      return !!rules[flag];
    });
  }

  global.LoreRules = {
    load: load,
    canPublication: canPublication,
    canSystem: canSystem,
    endpoints: ENDPOINTS
  };
})(window);
