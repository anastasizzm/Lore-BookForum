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

  // tOr возвращает fallback, если ключа нет в словаре (иначе в DOM упал бы
  // сам ключ — ту же проблему чинил d744f47).
  function t(key, fallback) {
    return (window.LoreI18n && LoreI18n.tOr)
      ? LoreI18n.tOr(key, fallback)
      : fallback;
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
  // Отдельная защита НЕ нужна от pointer-events — его мы сознательно не
  // ставим, иначе не показывалась бы подсказка title «Нет прав...».
  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-rule][aria-disabled="true"]') : null;
    if (!el) return;
    e.preventDefault();
    e.stopPropagation();
  }, true);

  // Enter в поле формы уводит сабмит в submit, а не в click: ловим и его.
  // Иначе неактивная «Publish» в book-form / article-form отправила бы
  // форму, хотя клик по ней мы уже блокируем.
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.querySelector) return;

    var isBlocked = function (el) {
      return !!el && !!el.getAttribute && el.getAttribute('aria-disabled') === 'true';
    };

    if (e.submitter) {
      // Кнопка известна — решаем только по ней. Иначе неактивная «Publish»
      // заблокировала бы рабочую «Create draft» в той же форме.
      if (!isBlocked(e.submitter)) return;
    } else {
      // submitter неизвестен (старый браузер / сабмит по Enter): пропускаем,
      // пока хотя бы ОДНА кнопка с data-rule активна; блокируем только когда
      // в форме не осталось ни одной рабочей.
      var rules = form.querySelectorAll('[data-rule]');
      var anyActive = false;
      for (var i = 0; i < rules.length; i++) {
        if (!isBlocked(rules[i])) { anyActive = true; break; }
      }
      if (anyActive) return;
    }

    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
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
