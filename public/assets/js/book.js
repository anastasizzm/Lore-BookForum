"use strict";

/* ============================================
   Book details / Article details
   ============================================ */
(function () {
  // Закладка (Save): обработчик [data-save-book] / [data-save-article] лежит в app.js
  // (POST/DELETE /api/books|articles/{id}/save)

  // Start / Resume / Read again — пока заглушка
  var startReading = document.querySelector('[data-start-reading]');
  if (startReading) {
    startReading.addEventListener('click', function () {
      var status = startReading.dataset.readingStatus; // new | in_progress | finished
      // TODO: new -> открыть с первой главы
      //       in_progress -> открыть с сохранённого места
      //       finished -> сбросить прогресс и открыть с начала
    });
  }

  // Click to Rate
  var rate = document.querySelector('[data-rate]');
  if (rate) {
    var stars = rate.querySelectorAll('[data-rate-value]');
    var current = 0; // TODO: подставить оценку пользователя, если она уже есть

    var paint = function (value) {
      stars.forEach(function (star, i) {
        star.classList.toggle('is-filled', i < value);
        star.setAttribute('aria-checked', String(current > 0 && i + 1 === current));
      });
    };

    stars.forEach(function (star) {
      var value = Number(star.dataset.rateValue);

      // Предпросмотр при наведении
      star.addEventListener('mouseenter', function () {
        paint(value);
      });

      // Выбор оценки; повторный клик по той же звезде снимает оценку
      star.addEventListener('click', function () {
        current = current === value ? 0 : value;
        paint(current);
        // TODO: отправить оценку на сервер (current, 0 = снять оценку)
      });
    });

    // Убрали курсор: возвращаем выбранную оценку
    rate.addEventListener('mouseleave', function () {
      paint(current);
    });

    paint(current);
  }

  // Tabs: Annotation / Table of contents
  var tabs = document.querySelectorAll('.book-tabs-panel [data-row-target]');
  var panels = document.querySelectorAll('.book-tabs-panel__content[data-row]');

  if (tabs.length && panels.length) {
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        var target = tab.dataset.rowTarget;

        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t === tab);
        });

        panels.forEach(function (panel) {
          panel.hidden = panel.dataset.row !== target;
        });
      });
    });
  }

  /* ==========================================================
     КОММЕНТАРИИ (P0-1 / P0-2 / P0-4 / P0-5)
     - отправка: Enter в поле и кнопка-галочка ведут в одну отправку,
       обработчики висят на document (работают даже если DOM
       отрисовался позже, чем этот скрипт);
     - успех: ЛЮБОЙ 2xx без payload-а с ошибкой (201 и 200 равнозначны);
     - список: подгружается из БД через GET /api/posts?publication=…,
       потому что контроллер страницы комментарии не отдаёт;
     - лайки: отдаём общему обработчику card-feed.js
       ([data-like-btn] -> POST/DELETE /api/posts/{id}/like).
     ========================================================== */

  var API_POSTS = '/api/posts';
  var COMMENT_MAX = 2000;
  var PAGE_SIZE = 20;

  // Общая структура комментариев (карточка, ответы, «View N more replies» /
  // «Show less», пагинация «Show more/less comments») живёт в comments.js —
  // на странице та же разметка работает и в ленте (feed).
  var LC = window.LoreComments || {};

  // Ники -> профили (users.js). Фолбэк — если файл не догрузился,
  // текст всё равно отрисуется, просто без ссылок.
  var Users = window.LoreUsers || {
    remember: function () { return 0; },
    renderAuthor: function (el, label) { if (el) el.textContent = label || ''; },
    renderText: function (el, text) { if (el) el.textContent = text || ''; },
    withMention: function (text) { return String(text == null ? '' : text).trim(); }
  };

  function one(sel, root) {
    return (root || document).querySelector(sel);
  }

  function setMsg(el, text) {
    if (!el) return;

    // Ошибки отправки и «Comment sent» — общими плашками (messages.js)
    var isError  = el.hasAttribute('data-comment-error') || el.hasAttribute('data-reply-error');
    var isStatus = el.hasAttribute('data-comment-status');
    if ((isError || isStatus) && window.Messages) {
      el.textContent = '';
      el.hidden = true;
      if (text) window.Messages.show(text, { type: isError ? 'error' : 'success' });
      return;
    }

    el.textContent = text || '';
    el.hidden = !text;
  }

  function csrfTokenValue() {
    // cookie — источник правды (её сравнивает бэк, см. Csrf.js);
    // скрытое поле формы — запасной вариант
    var fromCookie = window.LoreCsrf ? LoreCsrf.token() : '';
    if (fromCookie) return fromCookie;
    var el = one('[data-comment-form] input[name="_token"]') || one('input[name="_token"]');
    return el ? el.value : '';
  }

  /** Ошибка из payload-а ответа ('' — если ответ успешный). */
  function payloadError(data) {
    // Считаем ответом-ошибкой только тело с error / errors ({ error: { code, message, details } })
    if (!data || typeof data !== 'object' || (!data.error && !data.errors)) return '';
    return window.Messages ? window.Messages.fromPayload(data) : '';
  }

  /**
   * Человеческий текст ошибки для не-2xx / HTML-ответов.
   * Бэкенд отдаёт страницу-заглушку с кодом в .login-message__status,
   * но при этом HTTP-статус у неё может быть 200 — код достаём из разметки,
   * чтобы вместо «Failed to send comment (HTTP 200)» показать причину.
   */
  function failureText(res, raw) {
    var body = raw || '';
    var code = /class="login-message__status"[^>]*>\s*(\d{3})\s*</.exec(body);
    var text = /class="login-message__text"[^>]*>([\s\S]*?)<\/p>/.exec(body);

    if (code) {
      var msg = text ? text[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() : '';
      return (msg || 'Request rejected') + ' (HTTP ' + code[1] + ')';
    }
    if (/^\s*</.test(body)) {
      return 'Server returned an unexpected page (HTTP ' + res.status + '). The comment was not saved.';
    }
    return 'Failed to send the comment (HTTP ' + res.status + ')';
  }

  function listEl() { return one('[data-comment-list]'); }
  function emptyEl() { return one('[data-comments-empty]'); }
  function sectionEl() { return one('[data-comments]'); }

  function appendComment(data, atTop) {
    var list = listEl();
    var node = LC.commentNode(data);
    if (!list || !node) return null;
    if (atTop && list.firstElementChild) list.insertBefore(node, list.firstElementChild);
    else list.appendChild(node);
    var empty = emptyEl();
    if (empty) empty.hidden = true;
    return node;
  }

  /**
   * GET /api/posts?publication={id}&include=creator — корневые комментарии.
   * Страницы подгружает «Show more comments» (более старая встаёт ВЫШЕ
   * показанной — хронология), «Show less comments» подгруженное убирает.
   */
  var listPager = LC.pagerState ? LC.pagerState() : { loaded: 0, hasMore: [], loading: false };

  function pagerOpts() {
    return { load: function (page) { loadComments(page); } };
  }

  /** GET /api/posts?publication={id}&include=creator — корневые комментарии из БД. */
  async function loadComments(page) {
    page = Number(page) || 1;

    var section = sectionEl();
    if (!section) return;

    var pubId = Number(section.dataset.publicationId || 0);
    if (!pubId) return;

    var list = listEl();
    if (!list || listPager.loading) return;

    var params = new URLSearchParams({
      publication: String(pubId),
      page: String(page),
      ps: String(PAGE_SIZE),
      include: 'creator'
    });

    listPager.loading = true;
    LC.syncPager(list, listPager, pagerOpts());

    try {
      var res = await fetch(API_POSTS + '?' + params.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });

      var data = null;
      var raw = '';
      try {
        raw = await res.text();
        data = raw ? JSON.parse(raw) : null;
      } catch (_) { data = null; }

      if (!res.ok || !data) {
        console.warn('GET ' + API_POSTS, res.status, raw.slice(0, 200));
        listPager.loading = false;
        LC.syncPager(list, listPager, pagerOpts());   // кнопка = повторить попытку
        if (page === 1 && !list.children.length) {
          setMsg(emptyEl(), 'Comments are unavailable right now. Please reload the page.');
        }
        return;
      }

      var items = Array.isArray(data.items) ? data.items
                : (Array.isArray(data) ? data : []);

      var withReplies = [];
      var fragment = document.createDocumentFragment();

      // API отдаёт корневые комментарии created_at DESC, а читаться они должны
      // в хронологии (старые сверху, новые снизу) — разворачиваем страницу.
      items.slice().reverse().forEach(function (item) {
        var node = LC.commentNode({
          id: item.id,
          author: (item.creator && (item.creator.username || item.creator.name)) || '',
          authorId: item.creator && item.creator.id,
          authorUsername: (item.creator && item.creator.username) || '',
          initials: LC.initialsOf(item.creator),
          avatar: LC.avatarOf(item.creator),
          text: item.content || '',
          date: LC.formatDate(item.createdAt),
          likes: item.likesCount || 0,
          liked: !!item.isLiked,
          replies: Number(item.commentsCount) || 0
        });

        if (!node) return;
        node.dataset.page = String(page);   // «Show less comments» снимает страницы
        fragment.appendChild(node);
        if (Number(item.commentsCount) > 0) withReplies.push(node);
      });

      LC.insertPage(list, fragment, page);

      var empty = emptyEl();
      if (empty) empty.hidden = list.children.length > 0;

      // Комментарии с ответами подтягиваем сразу: ответы живут отдельно
      // (GET /api/posts?parent={id}), иначе после F5 они пропадут.
      withReplies.forEach(function (node) { LC.loadReplies(node); });

      listPager.hasMore[page] = !!(data.meta && data.meta.hasNext);
      listPager.loaded = page;
      listPager.loading = false;
      LC.syncPager(list, listPager, pagerOpts());
    } catch (err) {
      console.warn('GET ' + API_POSTS + ' failed', err);
      listPager.loading = false;
      LC.syncPager(list, listPager, pagerOpts());
    }
  }

  /** Отправка комментария: POST /api/posts (content, publicationId, _token). */
  async function submitComment(form) {
    if (!form || form.dataset.sending === '1') return;

    var input = one('input[name="content"]', form);
    var errEl = one('[data-comment-error]', form);
    var statusEl = one('[data-comment-status]', form);
    var sendBtn = one('[data-comment-send]', form);
    if (!input) return;

    setMsg(errEl, '');
    setMsg(statusEl, '');

    var text = (input.value || '').trim();
    if (text === '') {
      setMsg(errEl, 'Comment cannot be empty');
      return;
    }
    if (text.length > COMMENT_MAX) {
      setMsg(errEl, 'Max length is ' + COMMENT_MAX + ' characters');
      return;
    }

    var pubInput = one('input[name="publicationId"]', form);
    if (!Number(pubInput && pubInput.value)) {
      setMsg(errEl, 'Cannot send the comment: the publication id is missing on this page.');
      return;
    }

    var body = new URLSearchParams(new FormData(form));
    body.set('content', text);
    var csrfValue = csrfTokenValue();
    if (csrfValue) body.set('_token', csrfValue);

    form.dataset.sending = '1';
    if (sendBtn) sendBtn.disabled = true;
    input.readOnly = true;

    try {
      var res = await fetch(form.action || API_POSTS, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: body
      });

      var data = null;
      var raw = '';
      try {
        raw = await res.text();
        data = raw ? JSON.parse(raw) : null;
      } catch (_) { data = null; }

      console.log('POST ' + (form.action || API_POSTS), res.status, data || raw.slice(0, 200));

      var serverError = payloadError(data);
      // HTML-страница-заглушка = ответ бэкенда с ошибкой, хотя HTTP у неё
      // может быть 200 (см. renderNotFound/renderForbid -> Response::html()
      // без статуса). Такой ответ успехом не считаем.
      var htmlErrorPage = /class="login-message__(status|text)/.test(raw);
      // Любой HTML-ответ (в т.ч. PHP Fatal error с кодом 200) — это не успех:
      // API при успехе отдаёт JSON ({"createdId": N}).
      if (/^\s*</.test(raw)) htmlErrorPage = true;

      // Успех = любой 2xx без ошибки в payload-е. 201 и 200 равнозначны:
      // раньше здесь проверялся строго 201, и штатный ответ в 200
      // превращался в «Failed to send comment (HTTP 200)» (P0-1).
      if (res.ok && !serverError && !htmlErrorPage) {
        setMsg(statusEl, 'Comment sent');
        input.value = '';

        LC.bumpCommentCount(sectionEl());

        var created = data ? (data.createdId != null ? data.createdId : data.id) : null;
        var me = LC.meInfo(sectionEl());
        var node = appendComment({
          id: created,
          author: me.author,
          authorId: me.id,
          authorUsername: me.author,
          initials: null,
          user: null,
          text: text,
          date: LC.formatDate(new Date().toISOString()),   // dd.mm.yyyy, как у остальных
          likes: 0,
          liked: false,
          replies: 0
        }, false);   // новый комментарий — в конец списка (хронология: старые сверху)

        if (node && node.scrollIntoView) node.scrollIntoView({ block: 'nearest' });

        // Своя аватарка/инициалы берутся из формы (там они уже отрендерены)
        var formAvatar = one('.avatar', form);
        if (node && formAvatar) {
          var nodeAvatar = one('.avatar', node);
          if (nodeAvatar) nodeAvatar.replaceWith(formAvatar.cloneNode(true));
        }
        var nodeAuthor = node && one('[data-c-author]', node);
        if (nodeAuthor && !nodeAuthor.textContent) {
          nodeAuthor.textContent = (one('.comment-card__author', form) || {}).textContent || '';
        }

        // Ответ не-JSON (страница-заглушка) — сверяемся с БД, что реально сохранилось
        if (!data) window.setTimeout(function () { loadComments(1); }, 700);

        if (typeof applyLikedState === 'function') applyLikedState(document);
        return;
      }

      setMsg(errEl, serverError || failureText(res, raw));
    } catch (err) {
      console.warn('POST ' + API_POSTS + ' failed', err);
      setMsg(errEl, 'Network error. Try again.');
    } finally {
      delete form.dataset.sending;
      input.readOnly = false;
      if (sendBtn) sendBtn.disabled = input.value.trim() === '';
      if (document.activeElement === input || document.activeElement === document.body) {
        input.focus();
      }
    }
  }

  // ---- Делегирование: отправка по Enter (P0-1) ----
  // Кнопка отправки может быть disabled — Enter всё равно отправляет:
  // иначе неявная отправка формы браузером не срабатывает.
  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== 'NumpadEnter') return;
    var input = e.target.closest && e.target.closest('[data-comment-form] input[name="content"]');
    if (!input) return;
    e.preventDefault();
    submitComment(input.closest('[data-comment-form]'));
  });

  // ---- Делегирование: отправка по кнопке/нативному submit (P0-2) ----
  document.addEventListener('submit', function (e) {
    var form = e.target.closest && e.target.closest('[data-comment-form]');
    if (!form) return;
    e.preventDefault();
    submitComment(form);
  });

  // ---- Делегирование: активация галочки + сброс сообщений ----
  document.addEventListener('input', function (e) {
    var input = e.target.closest && e.target.closest('[data-comment-form] input[name="content"]');
    if (!input) return;
    var form = input.closest('[data-comment-form]');
    var sendBtn = one('[data-comment-send]', form);
    if (sendBtn) sendBtn.disabled = input.value.trim() === '';
    setMsg(one('[data-comment-error]', form), '');
    setMsg(one('[data-comment-status]', form), '');
  });

  /* Ответы на комментарии — раскрытие («View N more replies» / «Show less»),
     форма, отправка и их лайки — живут в comments.js: та же разметка
     и та же логика работают в ленте (feed). */

  /* ----------------------------------------------------------
     Загрузка списка из БД при открытии страницы (P0-5)
     ---------------------------------------------------------- */
  function initComments() {
    var section = sectionEl();
    if (!section || !LC.commentNode) return;   // comments.js не загрузился
    if (Number(section.dataset.publicationId || 0)) loadComments(1);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initComments);
  } else {
    initComments();
  }
})();

/* marker-test-12345 */