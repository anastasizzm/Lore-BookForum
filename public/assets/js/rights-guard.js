/**
 * rights-guard.js — JS-страховка форм создания комментариев и ответов.
 *
 * ЗАЧЕМ: пока бэкенд не закрыл POST /api/posts политикой CreatePostOperation
 * (ответом 403), фронт обязан сам не дать уйти в сеть запросу, который
 * Common Rules уже запретили. Это ВРЕМЕННАЯ мера подстраховки: как только
 * роут начнёт отдавать 403, здесь останется только тост, а настоящую
 * защиту даст сервер.
 *
 * КОНТРАКТ ПРАВ:
 *   GET /api/posts/rules -> { create: false } (public/assets/js/rules.js)
 *   create: false — и «Ответить», и отправка нового комментария под запретом.
 *
 * ТРИ ПУТИ ОТПРАВКИ, КОТОРЫЕ ПЕРЕКРЫТЫ (все — capture-фаза document,
 * раньше обработчиков book.js / comments.js / card-feed.js):
 *   1. submit — клик по кнопке-галочке или Enter в поле;
 *   2. keydown Enter, который book.js превращает в ПРЯМОЙ fetch, минуя
 *      событие submit (см. book.js, обработчик на document);
 *   3. нативная отправка: как только правило подтверждено как false, у
 *      формы снимаются action/method — без JS запрос физически не уйти.
 * Ответ прав ещё не пришёл? Событие «держим» и перезапускаем после ответа —
 * отправка не уходит в слепую в первые миллисекунды жизни страницы.
 * Правила не пришли (null: нет сети / 4xx / 5xx)? Как и в book-rights.js —
 * fail-open: не гадаем, пропускаем. Реальную защиту всегда даёт бэкенд.
 *
 * Формы опознаются по их JS-хукам (разметка не менялась):
 *   [data-comment-form]        — книга/статья (отправка book.js)
 *   [data-reply-form]          — ответ на книге/статье (comments.js)
 *   [data-feed-comment-form]   — комментарий в ленте/профиле (card-feed.js)
 *   [data-fc-reply-form]       — ответ в ленте (card-feed.js)
 *
 * ВИД: при подтверждённом create: false на <html> вешается
 * data-comments-denied="1" — им гасятся кнопки «Ответить» и «Комментировать»
 * (правило в component.css). Оно действует и на узлы, клонированные из
 * <template> позже. Сами формы НЕ прячем: попытка отправить должна дать
 * тост «Нет прав» (js.no_rights) вместо молчаливого ничего.
 *
 * Для book-rights.js (кнопки «Опубликовать» / «Создать черновик» с
 * data-rule) здесь тост подключён отдельно — см. LoreGuard.noRights().
 */
(function (global) {
  'use strict';

  var GUARDED = '[data-comment-form],[data-reply-form],' +
                '[data-feed-comment-form],[data-fc-reply-form]';
  var KIND = 'posts';
  var ACTION = 'create';
  var TOAST_THROTTLE_MS = 2500;

  /** undefined — правила грузятся; true/false — ответ; null — не пришли. */
  var state = undefined;
  var rightsPromise = null;
  var held = new WeakSet();   // формы с «повисшим» событием (ждут ответа)
  var lastToastAt = 0;

  function t(key, fallback) {
    return (global.LoreI18n && LoreI18n.tOr) ? LoreI18n.tOr(key, fallback) : fallback;
  }

  /** Тост «Нет прав» с троттлингом: удержание Enter не заспамит плашками. */
  function toastNoRights() {
    var now = Date.now();
    if (now - lastToastAt < TOAST_THROTTLE_MS) return;
    lastToastAt = now;

    var text = t('js.no_rights', 'No rights for this action');
    if (global.Messages) global.Messages.show(text, { type: 'error' });
    else console.warn('[rights-guard] ' + text);
  }

  /** Физическая мера: без action/method нативный submit не уходит в сеть. */
  function stripTransport(form) {
    form.removeAttribute('action');
    form.removeAttribute('method');
    form.setAttribute('data-rule-denied', ACTION);
  }

  function applyState() {
    if (state !== false) return;
    document.documentElement.setAttribute('data-comments-denied', '1');
    Array.prototype.forEach.call(document.querySelectorAll(GUARDED), stripTransport);
  }

  function load() {
    if (rightsPromise) return rightsPromise;
    if (!global.LoreRules) {
      state = null;
      return Promise.resolve(null);
    }

    rightsPromise = LoreRules.canPublication(KIND, ACTION, false).then(function (allowed) {
      // null (правила не пришли) остаётся null — fail-open, см. шапку
      state = allowed === true ? true : (allowed === false ? false : null);
      applyState();
      return state;
    });

    return rightsPromise;
  }

  function isGuarded(el) {
    return !!(el && el.nodeType === 1 && el.matches && el.matches(GUARDED));
  }

  /** Кнопка отправки — submitter для перезапущенного события. */
  function submitterOf(form) {
    return form.querySelector('button[type="submit"]:not([disabled]), input[type="submit"]:not([disabled])');
  }

  function stop(e) {
    if (e) {
      e.preventDefault();
      if (e.stopImmediatePropagation) e.stopImmediatePropagation();
      else e.stopPropagation();
    }
  }

  /** Правило false: глушим событие, снимаем транспорт, показываем тост. */
  function deny(form, e) {
    stop(e);
    stripTransport(form);
    toastNoRights();
  }

  /**
   * Правила ещё грузятся: держим событие и перезапускаем его после ответа.
   * Держим ОДИН раз на форму — повторные Enter за время ответа не плодят
   * параллельные перезапуски.
   */
  function hold(form, submitter, e) {
    stop(e);
    if (held.has(form)) return;
    held.add(form);

    load().then(function (next) {
      if (!held.has(form)) return;
      held.delete(form);
      if (next === false) {
        stripTransport(form);
        toastNoRights();
        return;
      }
      resend(form, submitter);
    });
  }

  /**
   * Повторная отправка после ответа прав. Синтетический submit, а не
   * requestSubmit(): не запускает constraint validation и сохраняет
   * submitter (book-form.js читает e.submitter, чтобы понять Publish).
   */
  function resend(form, submitter) {
    if (!form.isConnected) return;

    var event = null;
    try {
      event = new SubmitEvent('submit', { bubbles: true, cancelable: true, submitter: submitter || null });
    } catch (_) { event = null; }
    if (!event) {
      try { event = new Event('submit', { bubbles: true, cancelable: true }); }
      catch (_) { event = null; }
    }

    if (event) { form.dispatchEvent(event); return; }
    if (form.requestSubmit) {
      try { form.requestSubmit(submitter || undefined); } catch (_) { /* ignore */ }
    }
  }

  /* ---------- Перехваты (capture, document — раньше всех остальных) ---------- */

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!isGuarded(form)) return;

    if (state === false) { deny(form, e); return; }
    if (state === undefined) { hold(form, e.submitter || submitterOf(form), e); return; }
    // true / null — пропускаем дальше без вмешательства
  }, true);

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== 'NumpadEnter') return;
    if (e.defaultPrevented || e.ctrlKey || e.metaKey || e.altKey) return;

    var input = e.target;
    if (!input || input.nodeType !== 1 || !input.closest) return;
    var form = input.closest(GUARDED);
    if (!form) return;

    if (state === false) { deny(form, e); return; }
    if (state === undefined) { hold(form, submitterOf(form), e); return; }
  }, true);

  function init() {
    // Форм на странице нет — GET прав не тратим (подхватит первый submit)
    if (!document.querySelector(GUARDED)) return;
    load();
  }

  global.LoreGuard = {
    noRights: toastNoRights,
    /** true — create:false подтверждён; null — правила не пришли. */
    denied: function () { return state === false; },
    state: function () { return state === undefined ? 'pending' : state; }
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})(window);
