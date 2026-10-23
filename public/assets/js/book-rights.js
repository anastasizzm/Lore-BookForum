/**
 * book-rights.js — применение Common Rules к кнопкам страницы
 * (меню «три точки» на book/article details, панель публикации,
 * нижняя панель форм book-form / article-form).
 *
 * Данные берутся из window.LoreRules (public/assets/js/rules.js), который
 * делает ОДИН GET на вид прав и кеширует ответ:
 *   книги    -> GET /api/books/rules
 *   статьи   -> GET /api/articles/rules
 *   система  -> GET /api/system/rules (см. sidebar.php, кнопка «Админ-панель»)
 *
 * РАЗМЕТКА (контракт partial'ов и вьюх):
 *   [data-rules-kind="books|articles|posts"]   — какой /api/.../rules дёргать
 *   [data-rules-owner="1"]                     — текущий пользователь владелец
 *   [data-rule="create|edit|publish|delete"]   — элемент, управляющийся правилом
 *
 * ПОВЕДЕНИЕ: все элементы рендерятся в СЕРОМ неактивном состоянии
 * (aria-disabled=true + tabindex=-1) и включаются только после подтверждения
 * права. Права не пришли? Элемент остаётся серым — permissions-UI не должен
 * показывать кнопки, которые на клик вернут 403.
 *
 * Права владельца: глобальные /api/<kind>/rules НЕ знают про owner_id, поэтому
 * признак data-rules-owner="1" складывается с правилами здесь.
 */
(function () {
  'use strict';

  function t(key, fallback) {
    if (window.LoreI18n && typeof LoreI18n.tOr === 'function') return LoreI18n.tOr(key, fallback);
    return (window.LoreI18n && LoreI18n.t) ? LoreI18n.t(key) : fallback;
  }

  var NO_RIGHTS_HINT = function () { return t('js.no_rights', 'No rights for this action'); };

  function setDisabled(el, disabled) {
    el.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    el.classList.toggle('is-disabled', !!disabled);
    if (disabled) {
      el.setAttribute('tabindex', '-1');
      el.setAttribute('title', NO_RIGHTS_HINT());
    } else {
      el.removeAttribute('tabindex');
      el.removeAttribute('title');
    }
  }

  /** true | false | null(правила не пришли — состояние не трогаем). */
  function apply(el, allowed) {
    if (allowed === null || allowed === undefined) return;
    setDisabled(el, !allowed);
  }

  function run(wrap) {
    var kind    = wrap.getAttribute('data-rules-kind') || 'books';
    var isOwner = wrap.getAttribute('data-rules-owner') === '1';
    var items   = wrap.querySelectorAll('[data-rule]');
    if (!items.length) return;

    // Тумблер «⋮» остаётся на месте (решение: серые неактивные пункты вместо
    // скрытия меню) — просто все пункты гаснут, если прав нет.
    Array.prototype.forEach.call(items, function (el) {
      window.LoreRules.canPublication(kind, el.getAttribute('data-rule'), isOwner)
        .then(function (allowed) { apply(el, allowed); });
    });
  }

  // Защита от клика по неактивному элементу: ловим в фазе захвата, чтобы
  // сработать раньше обработчиков details-menu.js / book-form.js / навигации.
  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-rule][aria-disabled="true"]') : null;
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
  }, true);

  function init() {
    if (!window.LoreRules) {
      console.warn('[book-rights] rules.js is not loaded');
      return;
    }
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-rules-kind]'),
      run
    );
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
