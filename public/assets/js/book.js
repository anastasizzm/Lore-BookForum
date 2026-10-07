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
    el.textContent = text || '';
    el.hidden = !text;
  }

  /** Ошибка из payload-а ответа ('' — если ответ успешный). */
  function payloadError(data) {
    if (!data || typeof data !== 'object') return '';

    if (data.errors && typeof data.errors === 'object') {
      var flat = [];
      Object.keys(data.errors).forEach(function (key) {
        var v = data.errors[key];
        if (Array.isArray(v)) flat = flat.concat(v.map(String));
        else if (v != null) flat.push(String(v));
      });
      if (flat.length) return flat.join('\n');
    }
    if (typeof data.error === 'string' && data.error) return data.error;
    if (data.error && data.error.message) return String(data.error.message);
    if (data.message && (data.errors || data.error)) return String(data.message);

    return '';
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
    if (res.status === 401) return 'Please sign in again';
    if (res.status === 403) return 'Forbidden — check that your email is verified';
    if (res.status === 419) return 'Session expired — reload the page and try again';
    if (res.status === 500) return 'Server error — the comment was not saved';
    return 'Failed to send the comment (HTTP ' + res.status + ')';
  }

  function formatDate(value) {
    // API отдаёт DateTimeImmutable как {date, timezone_type, timezone}
    var raw = (value && typeof value === 'object' && !(value instanceof Date))
      ? value.date
      : value;
    if (raw instanceof Date) raw = raw.toISOString();
    var m = /(\d{4})-(\d{2})-(\d{2})/.exec(String(raw || ''));
    return m ? m[3] + '.' + m[2] + '.' + m[1] : '';
  }

  function initialsOf(user) {
    var n = String((user && user.name) || '').trim();
    var s = String((user && user.surname) || '').trim();
    var v = (n.charAt(0) + s.charAt(0)).toUpperCase();
    if (!v) {
      var u = String((user && user.username) || '').trim();
      v = u.charAt(0).toUpperCase() || '?';
    }
    return v;
  }

  function avatarUrl(user) {
    var raw = String((user && user.avatar) || '');
    return (raw !== '' && raw !== 'default') ? '/uploads/avatars/' + raw : '';
  }

  /** Собирает <div class="avatar …"> без innerHTML (без XSS). */
  function fillAvatar(avatar, initials, src) {
    if (!avatar) return;
    avatar.replaceChildren();

    var span = document.createElement('span');
    span.textContent = initials || '?';

    if (!src) {
      avatar.appendChild(span);
      return;
    }

    var img = document.createElement('img');
    img.alt = '';
    img.src = src;
    avatar.appendChild(img);
    avatar.appendChild(span);
    span.hidden = true;
    img.onerror = function () {
      img.hidden = true;
      span.hidden = false;
    };
  }

  /** Карточка комментария из <template id="comment-card-template">. */
  function commentNode(data) {
    var tpl = document.getElementById('comment-card-template');
    if (!tpl) return null;

    var node = tpl.content.firstElementChild.cloneNode(true);
    if (data.id != null && Number(data.id)) node.dataset.commentId = String(Number(data.id));

    fillAvatar(one('.avatar', node), data.initials, data.avatar || '');
    var author = one('[data-c-author]', node);
    if (author) Users.renderAuthor(author, data.author, data.authorId, data.authorUsername);
    // для ответа: кому пишем (@ник) — лежит на карточке
    if (data.authorUsername) node.dataset.authorUsername = data.authorUsername;
    if (data.authorId) node.dataset.authorId = String(Number(data.authorId));
    var text = one('[data-c-text]', node);
    Users.renderText(text, data.text || '');            // текст + @упоминания ссылками
    var date = one('[data-c-date]', node);
    if (date) date.textContent = data.date || '';

    var likeBtn = one('[data-like-btn]', node);
    if (likeBtn) {
      if (Number(data.id)) {
        likeBtn.dataset.likeId = String(Number(data.id));  // общий like из card-feed.js
        var cnt = one('[data-like-count]', likeBtn);
        if (cnt) cnt.textContent = String(Number(data.likes) || 0);
      } else {
        likeBtn.remove(); // без id лайк некуда отправлять
      }
    }

    // Состояние «мой лайк» — признак isLiked из контекста юзера в ответе API
    if (likeBtn && typeof setLikedUI === 'function') setLikedUI(likeBtn, !!data.liked);
    return node;
  }

  function listEl() { return one('[data-comment-list]'); }
  function emptyEl() { return one('[data-comments-empty]'); }
  function countEl() { return one('[data-comments-count]'); }
  function sectionEl() { return one('[data-comments]'); }

  function bumpCount() {
    var el = countEl();
    if (el) el.textContent = String((parseInt(el.textContent, 10) || 0) + 1);
  }

  function appendComment(data, atTop) {
    var list = listEl();
    var node = commentNode(data);
    if (!list || !node) return null;
    if (atTop && list.firstElementChild) list.insertBefore(node, list.firstElementChild);
    else list.appendChild(node);
    var empty = emptyEl();
    if (empty) empty.hidden = true;
    return node;
  }

  /** GET /api/posts?publication={id}&include=creator — корневые комментарии из БД. */
  async function loadComments(page, append) {
    var section = sectionEl();
    if (!section) return;

    var pubId = Number(section.dataset.publicationId || 0);
    if (!pubId) return;

    var params = new URLSearchParams({
      publication: String(pubId),
      page: String(page || 1),
      ps: String(PAGE_SIZE),
      include: 'creator'
    });

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
        if (!append && listEl() && !listEl().children.length) {
          setMsg(emptyEl(), 'Comments are unavailable right now. Please reload the page.');
        }
        return;
      }

      var items = Array.isArray(data.items) ? data.items
                : (Array.isArray(data) ? data : []);

      if (!append) {
        var list = listEl();
        if (list) list.replaceChildren();
      }

      // Комментарии с ответами подтягиваем сразу: ответы живут отдельно
      // (GET /api/posts?parent={id}), иначе после F5 они пропадут.
      var withReplies = [];

      items.forEach(function (item) {
        var node = appendComment({
          id: item.id,
          author: (item.creator && (item.creator.username || item.creator.name)) || '',
          authorId: item.creator && item.creator.id,
          authorUsername: (item.creator && item.creator.username) || '',
          initials: initialsOf(item.creator),
          avatar: avatarUrl(item.creator),
          text: item.content || '',
          date: formatDate(item.createdAt),
          likes: item.likesCount || 0,
          liked: !!item.isLiked
        }, false);

        if (node && Number(item.commentsCount) > 0) withReplies.push(node);
      });

      withReplies.forEach(function (node) { loadReplies(node); });

      if (emptyEl()) {
        var n = listEl() ? listEl().children.length : 0;
        emptyEl().hidden = n > 0;
      }

      // «Показать ещё», если комментариев больше одной страницы
      if (data.meta && data.meta.hasNext) renderMore(page || 1);
    } catch (err) {
      console.warn('GET ' + API_POSTS + ' failed', err);
    }
  }

  function renderMore(loadedPage) {
    var list = listEl();
    if (!list || document.querySelector('[data-comments-more]')) return;

    var wrap = document.createElement('div');
    wrap.className = 'comments-section__more';
    wrap.dataset.commentsMore = '1';

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn--secondary btn--pill';
    btn.textContent = 'Show more comments';
    btn.addEventListener('click', function () {
      var next = loadedPage + 1;
      wrap.remove();               // снимаем до загрузки, чтобы кнопка не дублировалась
      loadComments(next, true);
    });

    wrap.appendChild(btn);
    list.parentNode.insertBefore(wrap, list.nextSibling);
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

      // Успех = любой 2xx без ошибки в payload-е. 201 и 200 равнозначны:
      // раньше здесь проверялся строго 201, и штатный ответ в 200
      // превращался в «Failed to send comment (HTTP 200)» (P0-1).
      if (res.ok && !serverError && !htmlErrorPage) {
        setMsg(statusEl, 'Comment sent');
        input.value = '';

        bumpCount();

        var created = data ? (data.createdId != null ? data.createdId : data.id) : null;
        var me = meInfo();
        var node = appendComment({
          id: created,
          author: me.author,
          authorId: me.id,
          authorUsername: me.author,
          initials: null,
          avatar: '',
          text: text,
          date: new Date().toISOString(),
          likes: 0,
          liked: false
        }, true);

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
        if (!data) window.setTimeout(function () { loadComments(1, false); }, 700);

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

  /* ----------------------------------------------------------
     Ответы на комментарии:
       отправка — POST /api/posts/{commentId} (content, publicationId, _token);
       загрузка — GET  /api/posts?parent={commentId}&include=creator.
     ---------------------------------------------------------- */

  var REPLY_PAGE_SIZE = 50;

  function csrfTokenValue() {
    var el = one('[data-comment-form] input[name="_token"]') || one('input[name="_token"]');
    return el ? el.value : '';
  }

  /** Текущий юзер — из атрибутов секции комментариев (book-details.php). */
  function meInfo() {
    var section = sectionEl() || document;
    return {
      id: Number(section.dataset.meId || 0),
      // author — как API вернёт creator.username, иначе после F5 имя «мигает»
      // между «Имя Фамилия» (data-me-name) и логином
      author:   section.dataset.meUsername || section.dataset.meName || '',
      initials: section.dataset.meInitials || '?',
      avatar:   section.dataset.meAvatar || ''
    };
  }

  /** Строка ответа из <template id="reply-template"> (без innerHTML — без XSS). */
  function replyNode(data) {
    var tpl = document.getElementById('reply-template');
    if (!tpl) return null;

    var node = tpl.content.firstElementChild.cloneNode(true);
    var author = one('.comment-reply__author', node);
    Users.renderAuthor(author, data.author, data.authorId, data.authorUsername);
    Users.renderText(one('.comment-reply__text', node), data.text || '');
    fillAvatar(one('.avatar', node), data.initials, data.avatar || '');
    return node;
  }

  function appendReply(card, data) {
    var list = one('[data-replies]', card);
    var node = replyNode(data);
    if (!list || !node) return null;
    list.appendChild(node);
    return node;
  }

  /** GET /api/posts?parent={commentId}&include=creator — ответы из БД. */
  async function loadReplies(card) {
    var commentId = Number((card && card.dataset.commentId) || 0);
    if (!commentId) return;

    var params = new URLSearchParams({
      parent: String(commentId),
      page: '1',
      ps: String(REPLY_PAGE_SIZE),
      include: 'creator'
    });

    try {
      var res = await fetch(API_POSTS + '?' + params.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });

      var data = null;
      try { data = await res.json(); } catch (_) { data = null; }
      if (!res.ok || !data) return;

      var items = Array.isArray(data.items) ? data.items : [];
      var list = one('[data-replies]', card);
      if (!list) return;

      list.replaceChildren();
      // API отдаёт created_at DESC — ответы читаются сверху вниз, разворачиваем
      items.slice().reverse().forEach(function (item) {
        appendReply(card, {
          author: (item.creator && (item.creator.username || item.creator.name)) || '',
          authorId: item.creator && item.creator.id,
          authorUsername: (item.creator && item.creator.username) || '',
          initials: initialsOf(item.creator),
          avatar: avatarUrl(item.creator),
          text: item.content || '',
          date: formatDate(item.createdAt)
        });
      });
    } catch (err) {
      console.warn('GET ' + API_POSTS + '?parent= failed', err);
    }
  }

  // Показать/скрыть форму ответа; при открытии подставляем @ник автора
  document.addEventListener('click', function (e) {
    var replyBtn = e.target.closest && e.target.closest('[data-reply-toggle]');
    if (!replyBtn) return;
    var content = replyBtn.closest('.comment-card__content');
    if (!content) return;
    var form = content.querySelector('[data-reply-form]');
    if (!form) return;
    var open = form.hidden;
    form.hidden = !open;
    replyBtn.setAttribute('aria-expanded', String(open));
    if (open) {
      var first = form.querySelector('input');
      if (first) {
        // Ответ начинается с @ника автора комментария (если поле пустое)
        var card = replyBtn.closest('[data-comment-id]');
        var target = (card && card.dataset.authorUsername) || '';
        if (target && first.value.trim() === '') {
          first.value = '@' + target + ' ';
          first.dispatchEvent(new Event('input', { bubbles: true }));
        }
        first.focus();
        try {
          first.selectionStart = first.selectionEnd = first.value.length;
        } catch (_) { /* type=text в старых браузерах */ }
      }
    }
  });

  // Post активна, только когда есть текст
  document.addEventListener('input', function (e) {
    var input = e.target.closest && e.target.closest('.comment-reply-form__input');
    if (!input) return;
    var form = input.closest('form');
    var btn = form && form.querySelector('.comment-reply-form__submit');
    if (btn) btn.disabled = input.value.trim() === '';
    setMsg(form ? one('[data-reply-error]', form) : null, '');
  });

  // Отправка ответа: POST /api/posts/{commentId}
  document.addEventListener('submit', async function (e) {
    var form = e.target.closest && e.target.closest('[data-reply-form]');
    if (!form) return;
    e.preventDefault();
    if (form.dataset.sending === '1') return;

    var input = one('input', form);
    var sendBtn = one('.comment-reply-form__submit', form);
    var errEl = one('[data-reply-error]', form);
    var card = form.closest('[data-comment-id]');
    var text = input ? (input.value || '').trim() : '';

    setMsg(errEl, '');

    if (!card) return;
    var commentId = Number(card.dataset.commentId || 0);
    if (!commentId) return setMsg(errEl, 'Cannot send the reply: the comment id is missing.');
    if (text === '') return;

    // Ответ отправляется с @ником автора комментария в начале текста
    text = Users.withMention(text, card.dataset.authorUsername || '');
    if (input) input.value = text;

    if (text.length > COMMENT_MAX) return setMsg(errEl, 'Max length is ' + COMMENT_MAX + ' characters');

    var section = sectionEl();
    var pubId = Number((section && section.dataset.publicationId) || 0);
    if (!pubId) return setMsg(errEl, 'Cannot send the reply: the publication id is missing on this page.');

    var body = new URLSearchParams();
    body.set('content', text);
    body.set('publicationId', String(pubId));
    var token = csrfTokenValue();
    if (token) body.set('_token', token);

    form.dataset.sending = '1';
    input.readOnly = true;
    if (sendBtn) sendBtn.disabled = true;

    try {
      var res = await fetch(API_POSTS + '/' + commentId, {
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

      var serverError = payloadError(data);
      var htmlErrorPage = /class="login-message__(status|text)/.test(raw);

      if (res.ok && !serverError && !htmlErrorPage) {
        var me = meInfo();
        appendReply(card, {
          author: me.author,
          authorId: me.id,
          authorUsername: me.author,
          initials: me.initials,
          avatar: me.avatar,
          text: text
        });

        bumpCount(); // счётчик публикации включает ответы (считает триггер в БД)

        input.value = '';
        form.hidden = true;
        var toggle = one('[data-reply-toggle]', card);
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        return;
      }

      setMsg(errEl, serverError || failureText(res, raw));
    } catch (err) {
      console.warn('POST ' + API_POSTS + '/' + commentId + ' failed', err);
      setMsg(errEl, 'Network error. Try again.');
    } finally {
      delete form.dataset.sending;
      input.readOnly = false;
      if (sendBtn) sendBtn.disabled = input.value.trim() === '';
    }
  });

  /* ----------------------------------------------------------
     Загрузка списка из БД при открытии страницы (P0-5)
     ---------------------------------------------------------- */
  function initComments() {
    var section = sectionEl();
    if (!section) return;
    if (Number(section.dataset.publicationId || 0)) loadComments(1, false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initComments);
  } else {
    initComments();
  }
})();

/* marker-test-12345 */
