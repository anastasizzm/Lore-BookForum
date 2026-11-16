/**
 * rights-modal.js — плашка публикации книги с выбором прав
 * (src/partials/rights-modal.php, стили rights-modal.css).
 *
 * Плашка всегда открывается в режиме модуля ролей (data-rights-view="roles"):
 * родной справочник прав удалён, процессом управляет roles-module.js.
 * Режимы (data-rights-mode):
 *   'after-publish' — книга УЖЕ опубликована (форма книги, POST /api/books
 *                     ответил успехом): Publish модуля ведёт на страницу
 *                     книги (data-redirect). Отмена — тоже ведёт на книгу.
 *   'publish'       — книга-черновик на book-details: Publish модуля
 *                     публикует (POST /api/books/{id}/publish) и уводит на
 *                     страницу книги (бейдж и кнопка исчезают).
 *   'roles'         — только управление ролями: Publish сохраняет их
 *                     (модуль делает это сам) и закрывает плашку.
 *
 * Роли и люди сохраняются модулем отдельными запросами
 * (GET/POST/PUT/PATCH/DELETE /api/publications/{id}/roles|users).
 *
 * Родной справочник прав удалён: GET /api/additional/rights не существует,
 * а сохранение целиком делает модуль ролей. В плашке остались только
 * режимы публикации/ухода на страницу книги.
 *
 * Контракт целиком — в шапке src/partials/rights-modal.php.
 * Использование из book-form.js: window.RightsModal.open({mode, bookId, redirect}).
 * Кнопка на book-details: <button data-rights-open data-book-id="N">.
 */
(function () {
  'use strict';

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  var csrfFetch = (window.LoreCsrf && typeof window.LoreCsrf.fetch === 'function')
    ? window.LoreCsrf.fetch
    : function (url, opts) { return fetch(url, opts); };

  var state = {
    root: null,
    mode: 'after-publish',
    bookId: 0,
    redirect: '',
    // Для формы создания: после создания черновика отмена/крестик должны
    // увести на книгу, иначе форма останется с уже созданной записью.
    cancelRedirect: '',
    // Колбэк публикации для страницы черновиков: вместо
    // перехода на /books/{id} вызывается он (карточка уходит из списка).
    onPublished: null,
    screen: 1,            // стартовый экран модуля: 1 — роли | 2 — пользователи
    context: 'create',    // create — Create → Continue → Publish; edit — «Изменить…»
    saving: false,
    lastFocus: null,
  };

  function root() {
    if (!state.root) state.root = document.querySelector('[data-rights-modal]');
    return state.root;
  }

  function q(sel) { var r = root(); return r ? r.querySelector(sel) : null; }
  function show(node, visible) { if (node) node.hidden = !visible; }

  /* --------------------------------------------------------
     ПУБЛИКАЦИЯ (родного справочника прав больше нет)
     -------------------------------------------------------- */
  function saveError(text) {
    var box = q('[data-rights-save-error]');
    if (box) {
      box.textContent = text || '';
      show(box, !!text);
    }
    // В режиме модуля ролей родное тело плашки скрыто (data-rights-view
    // ="roles") — там ошибку иначе не увидеть, поэтому дублируем тостом.
    if (text && (!box || box.offsetParent === null) && window.Messages) {
      window.Messages.show(text, { type: 'error' });
    }
  }

  function setSaving(saving) {
    state.saving = saving;
    ['[data-rights-confirm]', '[data-rights-cancel]'].forEach(function (sel) {
      var btn = q(sel);
      if (btn) btn.disabled = saving;
    });
  }

  /**
   * Публикация черновика: POST /api/books/{id}/publish
   * (config/endpoints.php → BooksController::publish, ответ — пустое тело).
   * Успех уводит на страницу книги (бейдж «Черновик» и кнопка публикации
   * исчезают); на странице черновиков вместо перехода вызывается
   * state.onPublished (обработчик страницы убирает карточку из списка).
   * Работает и из book-details, и из формы редактирования.
   *
   * 409 «нет контента» (ConflictException, текст бэка уже локализован в
   * errors.json): показываем его, а не общий save_failed.
   */
  function publishBook() {
    return csrfFetch('/api/books/' + state.bookId + '/publish', {
      method: 'POST',
      body: new FormData(),
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    }).then(function (res) {
      if (!res.ok) {
        // 409 «нет контента» (ConflictException): бэк отдаёт уже
        // локализованный текст в {error:{code,message}} — берём его,
        // фолбэк — errors.books.content_pdf.
        var fallback = res.status === 409
          ? t('errors.books.content_pdf')
          : t('rights.save_failed', { status: res.status });
        var msgPromise = window.Messages && typeof window.Messages.readError === 'function'
          ? window.Messages.readError(res, fallback)
          : Promise.resolve(fallback);
        return msgPromise.then(function (msg) { saveError(msg); return false; });
      }
      // Успех: страница черновиков убирает карточку сама; иначе уходим на
      // страницу книги. Данные на сервере — снимаем защиту «несохранённых
      // изменений» книжной формы (она слушает событие 'bookform:leave').
      window.dispatchEvent(new Event('bookform:leave'));
      if (typeof state.onPublished === 'function') {
        var cb = state.onPublished;
        state.onPublished = null;
        cb();
        return true;
      }
      window.location.href = '/books/' + state.bookId;
      return true;
    }).catch(function (err) {
      console.warn('[rights-modal] publish failed', err);
      saveError(t('errors.common.network_error'));
      return false;
    });
  }

  function goToBook() {
    // Данные уже сохранены сервером (книга опубликована) — снимаем защиту
    // «несохранённых изменений», если книжная форма рядом (слушает
    // событие 'bookform:leave').
    window.dispatchEvent(new Event('bookform:leave'));
    var target = state.redirect || (state.bookId ? '/books/' + state.bookId : '/books');
    window.location.href = target;
  }

  /**
   * Publish из модуля ролей (экран 2). Родной выбор прав заменён модулем,
   * поэтому POST /api/publications/{id}/rights здесь НЕ вызывается: роли и
   * люди модуль сохраняет сам, отдельными запросами при каждом изменении
   * (см. roles-module.js). Хосту остаётся завершить публикацию.
   */
  function modulePublish() {
    if (state.saving) return Promise.resolve(false);

    setSaving(true);

    if (state.mode === 'publish') {
      return publishBook().then(function (publishOk) {
        if (!publishOk) { setSaving(false); return false; }
        return true;   // ушли на страницу книги
      });
    }

    // Режим 'roles' / 'after-publish': книга уже опубликована — только уход.
    goToBook();
    return Promise.resolve(true);
  }

  /* --------------------------------------------------------
     ОТКРЫТИЕ / ЗАКРЫТИЕ
     -------------------------------------------------------- */
  function close() {
    var r = root();
    if (r) {
      // Сбрасываем модуль ролей и убираем режим
      var mod = r.querySelector('[data-roles-module]');
      if (mod && window.LoreRoles) window.LoreRoles.reset(mod);
      r.removeAttribute('data-rights-view');
      r.hidden = true;
    }
    document.body.classList.remove('is-modal-open');
    saveError('');
    state.cancelRedirect = '';
    if (state.lastFocus && state.lastFocus.focus) {
      try { state.lastFocus.focus(); } catch (_) {}
    }
    state.lastFocus = null;
  }

  function notifySaved() {
    if (window.Messages) window.Messages.show(t('roles.saved'), { type: 'success' });
  }

  function cancel() {
    if (state.saving) return;
    if (state.mode === 'after-publish' || state.cancelRedirect) {
      // Книга уже создана (и, возможно, опубликована) — отказ от прав
      // не отменяет это; уводим на её страницу.
      goToBook();
      return;
    }
    close();
  }

  /* --------------------------------------------------------
     ПЛАШКА РОЛЕЙ И ЛЮДЕЙ (publication-roles.js)
     -------------------------------------------------------- */
  function loadRolesPanel() {
    if (!window.LorePubRoles) return;
    var panel = root() ? root().querySelector('[data-pub-roles]') : null;
    if (!panel) return;
    panel.setAttribute('data-pub-roles-id', String(state.bookId || 0));
    window.LorePubRoles.load(panel, state.bookId);
  }

  function open(opts) {
    var r = root();
    if (!r) return;

    opts = opts || {};
    // 'roles' — только модуль ролей (Publish просто сохраняет роли и закрывает);
    // иначе — привычные 'after-publish' | 'publish' (Publish ведёт публикацию).
    state.mode = opts.mode === 'after-publish' ? 'after-publish'
      : (opts.mode === 'roles' ? 'roles' : 'publish');
    state.bookId = parseInt(opts.bookId !== undefined ? opts.bookId : r.getAttribute('data-book-id'), 10) || 0;
    state.redirect = opts.redirect || r.getAttribute('data-redirect') || '';
    state.cancelRedirect = opts.cancelRedirect || '';
    // 'screen' — стартовый экран модуля (1 — роли, 2 — пользователи);
    // 'context' — create (Create → Continue → Publish) | edit (без Continue).
    state.screen = opts.screen ? (parseInt(opts.screen, 10) || 1) : 1;
    state.context = opts.context === 'edit' ? 'edit' : 'create';
    state.onPublished = typeof opts.onPublished === 'function' ? opts.onPublished : null;

    r.setAttribute('data-rights-mode', state.mode);
    r.setAttribute('data-book-id', String(state.bookId));
    r.setAttribute('data-redirect', state.redirect);
    // Модуль ролей берёт управление плашкой на себя
    r.setAttribute('data-rights-view', 'roles');

    var label = q('[data-rights-confirm-label]');
    if (label) {
      label.textContent = state.mode === 'publish'
        ? t('rights.publish_confirm')
        : t('rights.confirm');
    }

    saveError('');
    state.lastFocus = document.activeElement;
    r.hidden = false;
    document.body.classList.add('is-modal-open');

    var first = q('[data-rights-cancel]');
    if (first && first.focus) first.focus();

    openRoles(r);
  }

  /** Открывает модуль ролей внутри плашки (экран 1). */
  function openRoles(r) {
    var mod = r.querySelector('[data-roles-module]');
    if (!mod || !window.LoreRoles) return;

    var rolesMode = state.mode === 'roles' ? 'roles'
      : (state.mode === 'after-publish' ? 'after-publish' : 'publish');

    window.LoreRoles.open(mod, {
      publicationId: state.bookId,
      mode: rolesMode,
      screen: state.screen,
      context: state.context,
      // Publish экрана 2: в режиме 'roles' просто сохраняем роли (модуль
      // сохранил их сам) и закрываем; иначе — публикация хоста.
      onPublish: function (done) {
        if (rolesMode === 'roles') {
          notifySaved();
          close();
          done();
          return;
        }
        modulePublish().then(function (ok) { if (ok) close(); done(); });
      },
      // Стрелка «назад»/отмена модуля → закрыть плашку (after-publish ведёт на книгу)
      onCancel: function () { cancel(); }
    });
  }

  /* --------------------------------------------------------
     ПОДКЛЮЧЕНИЕ (делегирование: модалка и кнопки могут
     переживать позднюю отрисовку)
     -------------------------------------------------------- */
  function onReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  onReady(function () {
    document.addEventListener('click', function (e) {
      var target = e.target && e.target.closest ? e.target : null;
      if (!target) return;

      // Кнопка «Опубликовать книгу» на book-details: сразу открываем
      // финальный экран модуля ролей, где находится Publish. Иначе
      // пользователь видел только экран ролей с «Continue» и не понимал,
      // как опубликовать черновик.
      var opener = target.closest('[data-rights-open]');
      if (opener) {
        e.preventDefault();
        open({
          mode: 'publish',
          bookId: opener.getAttribute('data-book-id') || (root() && root().getAttribute('data-book-id')),
          screen: 2,
        });
        return;
      }

      if (!root() || root().hidden) return;

      if (target.closest('[data-rights-confirm]')) { modulePublish(); return; }
      if (target.closest('[data-rights-cancel]')) { cancel(); return; }
      if (target.closest('[data-rights-dismiss]')) { cancel(); return; }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return;
      if (!root() || root().hidden) return;
      cancel();
    });
  });

  window.RightsModal = { open: open, close: close };
})();
