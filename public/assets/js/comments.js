"use strict";

/* ============================================
   Комментарии — общая структура для book details,
   article details и ленты (feed).
   Одна и та же раскладка и поведение везде:
     * ник автора — без «@» (собачка только в упоминаниях внутри текста);
     * лайк справа и отцентрирован по комментарию, у ответов — тоже;
     * под текстом: дата и кнопка Reply;
     * ответы раскрываются кнопкой «View N more replies» ↔ «Show less»;
     * корневой список: «Show more comments» ↔ «Show less comments»;
     * комментарии читаются в хронологии (старые сверху, новые снизу).

   Страница обязана содержать шаблоны:
     #comment-card-template — карточка корневого комментария;
     #reply-template        — строка ответа.
   Лайки отдаются общему обработчику card-feed.js
   ([data-like-btn] -> POST/DELETE /api/posts/{id}/like).
   ============================================ */

(function () {
  var API_POSTS = '/api/posts';
  var COMMENT_MAX = 2000;
  var REPLY_PAGE_SIZE = 50;

  /** Подписи кнопок (Instagram-подобные). */
  var LABEL_SHOW_LESS = 'Show less';           // ответы раскрыты
  var LABEL_SHOW_MORE_LIST = 'Show more comments';
  var LABEL_SHOW_LESS_LIST = 'Show less comments';

  // Ники -> профили (users.js грузится раньше). Фолбэк — без ссылок.
  var Users = window.LoreUsers || {
    remember: function () { return 0; },
    renderAuthor: function (el, label) { if (el) el.textContent = label || ''; },
    renderText: function (el, text) { if (el) el.textContent = text || ''; },
    withMention: function (text) { return String(text == null ? '' : text).trim(); }
  };

  function one(sel, root) { return (root || document).querySelector(sel); }
  function each(list, fn) { Array.prototype.forEach.call(list, fn); }

  function setMsg(el, text) {
    if (!el) return;
    el.textContent = text || '';
    el.hidden = !text;
  }

  function csrfToken() {
    var el = one('[data-comment-form] input[name="_token"]')
          || one('[data-feed-comment-form] [name="_token"]')
          || one('input[name="_token"]');
    return el ? el.value : '';
  }

  /** dd.mm.yyyy: API отдаёт строку либо {date, timezone…} (DateTimeImmutable). */
  function formatDate(value) {
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

  /** Значение users.avatar как есть: '' | 'default' | пресет | файл. */
  function avatarOf(user) {
    return String((user && user.avatar) || '');
  }

  /** Собирает <div class="avatar …"> без innerHTML (без XSS). */
  function fillAvatar(avatar, initials, raw) {
    if (!avatar) return;
    avatar.replaceChildren();

    var span = document.createElement('span');
    span.textContent = initials || '?';

    // Аватар-пресет (настройки профиля) — эмодзи, а не битая картинка
    var parsed = (window.LoreAvatar && window.LoreAvatar.parse(raw)) || { type: 'none' };
    if (parsed.type === 'emoji') {
      var em = document.createElement('span');
      em.className = 'avatar__emoji';
      em.setAttribute('aria-hidden', 'true');
      em.textContent = parsed.emoji;
      avatar.appendChild(em);
      return;
    }

    if (parsed.type !== 'image') {
      avatar.appendChild(span);
      return;
    }

    var img = document.createElement('img');
    img.alt = '';
    img.src = parsed.src;
    avatar.appendChild(img);
    avatar.appendChild(span);
    span.hidden = true;
    img.onerror = function () {
      img.hidden = true;
      span.hidden = false;
    };
  }

  /**
   * Метка автора комментария/ответа — просто ник, БЕЗ «@».
   * Собачка остаётся только в упоминаниях внутри текста (Users.renderText)
   * и в префиксе поля ответа (это тоже упоминание).
   */
  function authorLabel(username, fallback) {
    var v = String(username || '').trim();
    return v || String(fallback || '');
  }

  /** Подпись кнопки раскрытия ответов: «View N more reply(ies)». */
  function repliesLabel(n) {
    n = Number(n) || 0;
    return 'View ' + n + ' more ' + (n === 1 ? 'reply' : 'replies');
  }

  /** Карточка комментария из <template id="comment-card-template">. */
  function commentNode(data) {
    var tpl = document.getElementById('comment-card-template');
    if (!tpl) return null;

    var node = tpl.content.firstElementChild.cloneNode(true);
    if (data.id != null && Number(data.id)) node.dataset.commentId = String(Number(data.id));

    fillAvatar(one('.avatar', node), data.initials, data.avatar || '');

    var author = one('[data-c-author]', node);
    if (author) {
      Users.renderAuthor(author, authorLabel(data.authorUsername, data.author),
                         data.authorId, data.authorUsername);
    }
    // для ответа: кому пишем (@ник) — лежит на карточке
    if (data.authorUsername) node.dataset.authorUsername = data.authorUsername;
    if (data.authorId) node.dataset.authorId = String(Number(data.authorId));

    Users.renderText(one('[data-c-text]', node), data.text || '');  // текст + @упоминания
    var date = one('[data-c-date]', node);
    if (date) date.textContent = data.date || '';

    var likeBtn = one('[data-like-btn]', node);
    if (likeBtn) {
      if (Number(data.id)) {
        likeBtn.dataset.likeId = String(Number(data.id));   // общий like из card-feed.js
        var cnt = one('[data-like-count]', likeBtn);
        if (cnt) cnt.textContent = String(Number(data.likes) || 0);
        // Состояние «мой лайк» — признак isLiked из контекста юзера.
        // data-liked пишем сразу: applyLikedState (card-feed.js) применит его,
        // даже если этот файл выполнится позже
        likeBtn.dataset.liked = data.liked ? '1' : '0';
        if (typeof window.setLikedUI === 'function') window.setLikedUI(likeBtn, !!data.liked);
      } else {
        likeBtn.remove(); // без id лайк некуда отправлять
      }
    }

    // Кнопка «View N more replies»: показываем сразу, если у комментария есть
    // ответы (даст их подгрузить loadReplies). Без ответов — остаётся скрытой.
    var moreBtn = one('[data-replies-toggle]', node);
    var repliesCount = Number(data.replies) || 0;
    node.dataset.repliesCount = String(repliesCount);
    if (moreBtn) {
      moreBtn.hidden = repliesCount <= 0;
      if (repliesCount > 0) moreBtn.textContent = repliesLabel(repliesCount);
    }

    return node;
  }

  /** Строка ответа из <template id="reply-template"> (без innerHTML — без XSS). */
  function replyNode(data) {
    var tpl = document.getElementById('reply-template');
    if (!tpl) return null;

    var node = tpl.content.firstElementChild.cloneNode(true);

    var author = one('.comment-reply__author', node);
    if (author) {
      Users.renderAuthor(author, authorLabel(data.authorUsername, data.author),
                         data.authorId, data.authorUsername);
    }
    Users.renderText(one('.comment-reply__text', node), data.text || '');
    fillAvatar(one('.avatar', node), data.initials, data.avatar || '');

    // Лайк ответа — такой же, как у комментария, справа по центру
    var likeBtn = one('[data-like-btn]', node);
    if (likeBtn) {
      if (Number(data.id)) {
        likeBtn.dataset.likeId = String(Number(data.id));
        var cnt = one('[data-like-count]', likeBtn);
        if (cnt) cnt.textContent = String(Number(data.likes) || 0);
        likeBtn.dataset.liked = data.liked ? '1' : '0';
        if (typeof window.setLikedUI === 'function') window.setLikedUI(likeBtn, !!data.liked);
      } else {
        likeBtn.remove();
      }
    }

    return node;
  }

  /* ---------- Область: секция комментариев страницы или карточка ленты ---------- */

  function scopeOf(node) {
    if (!node || !node.closest) return document;
    return node.closest('[data-comments]')
        || node.closest('.card-feed')
        || document;
  }

  function publicationIdOf(scope) {
    if (!scope) return 0;
    var v = Number((scope.dataset && scope.dataset.publicationId) || 0);
    if (v) return v;
    var input = one('[name="publicationId"]', scope);
    return input ? Number(input.value || 0) : 0;
  }

  /** Комментарии и ответы считаются в одном счётчике публикации/поста. */
  function bumpCommentCount(scope) {
    var el = scope && (one('[data-comments-count]', scope) || one('[data-comment-count]', scope));
    if (el) el.textContent = String((parseInt(el.textContent, 10) || 0) + 1);
  }

  /** Текущий юзер: секция — data-me-*, карточка ленты — data-cu-*. */
  function meInfo(scope) {
    scope = scope || document;
    var d = scope.dataset || {};
    if (d.meId !== undefined) {
      return {
        id: Number(d.meId || 0),
        author: d.meUsername || d.meName || '',
        initials: d.meInitials || '?',
        avatar: d.meAvatar || ''
      };
    }
    return {
      id: Number(d.cuId || 0),
      author: d.cuName || '',
      initials: d.cuInitials || '?',
      avatar: d.cuAvatar || ''
    };
  }

  /* ---------------- Ответы: раскрытие «View N more replies» ↔ «Show less» ---------------- */

  function repliesCountOf(card, list) {
    if (card && card.dataset && card.dataset.repliesCount !== ''
        && card.dataset.repliesCount != null) {
      return Number(card.dataset.repliesCount) || 0;
    }
    return list ? list.children.length : 0;
  }

  /** Показывает/прячет ответы; кнопка становится «Show less» / «View N more». */
  function setRepliesExpanded(card, expanded) {
    var list = one('[data-replies]', card);
    var btn = one('[data-replies-toggle]', card);
    if (!list || !btn) return;

    var n = repliesCountOf(card, list);
    if (n <= 0) {                 // ответов нет — не показываем пустую кнопку
      list.hidden = true;
      btn.hidden = true;
      return;
    }

    list.hidden = !expanded;
    btn.hidden = false;
    btn.textContent = expanded ? LABEL_SHOW_LESS : repliesLabel(n);
    btn.setAttribute('aria-expanded', String(!!expanded));
  }

  /** Синхронизирует кнопку с реально загруженными ответами. */
  function syncRepliesToggle(card, expanded) {
    var list = one('[data-replies]', card);
    var n = list ? list.children.length : 0;
    if (card && card.dataset) card.dataset.repliesCount = String(n);
    var open = expanded !== undefined ? !!expanded : !!(list && !list.hidden);
    setRepliesExpanded(card, n > 0 && open);
  }

  function appendReply(card, data) {
    var list = one('[data-replies]', card);
    var node = replyNode(data);
    if (!list || !node) return null;
    list.appendChild(node);
    if (card && card.dataset) card.dataset.repliesCount = String(list.children.length);
    return node;
  }

  /** GET /api/posts?parent={commentId}&include=creator — ответы из БД. */
  async function loadReplies(card) {
    var commentId = Number((card && card.dataset.commentId) || 0);
    if (!commentId) return;
    if (card.dataset.repliesLoading === '1') return;   // уже в полёте
    card.dataset.repliesLoading = '1';

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
      if (!res.ok || !data) {
        syncRepliesToggle(card);   // ответов нет — не показываем пустую кнопку
        return;
      }

      var items = Array.isArray(data.items) ? data.items : [];
      var list = one('[data-replies]', card);
      if (!list) return;

      list.replaceChildren();
      // API отдаёт created_at DESC — ответы читаются сверху вниз, разворачиваем
      items.slice().reverse().forEach(function (item) {
        appendReply(card, {
          id: item.id,
          author: (item.creator && (item.creator.username || item.creator.name)) || '',
          authorId: item.creator && item.creator.id,
          authorUsername: (item.creator && item.creator.username) || '',
          initials: initialsOf(item.creator),
          avatar: avatarOf(item.creator),
          text: item.content || '',
          date: formatDate(item.createdAt),
          likes: item.likesCount || 0,
          liked: !!item.isLiked
        });
      });

      // Кнопка — по фактически загруженному числу ответов; список остаётся
      // скрытым, если его не раскрывали, раскроет только нажатие по кнопке.
      syncRepliesToggle(card);
    } catch (err) {
      console.warn('GET ' + API_POSTS + '?parent= failed', err);
      // Ответы не пришли — кнопку раскрытия прячем, чтобы не показывать пустоту
      syncRepliesToggle(card);
    } finally {
      delete card.dataset.repliesLoading;
    }
  }

  /* ---------------- Пагинация списка: Show more / Show less comments ---------------- */

  function pagerState() {
    return { loaded: 0, hasMore: [], loading: false };
  }

  /** Страница N — вставка в хронологию: старые сверху, новые снизу. */
  function insertPage(list, fragment, page) {
    // Снимок до мутаций: children — живая коллекция, удаление/перемещение
    // во время обхода пропускало бы узлы (каждый второй остался бы старым)
    var previous = [];
    each(list.children, function (n) { previous.push(n); });

    if (Number(page) === 1) {
      // id, которые привёз сервер: свои оптимистичные вставки с совпадающим
      // id уступают ему, без id их нечем сверить — их и так перечитает
      // загрузка списка
      var apiIds = {};
      each(fragment.children, function (n) {
        var id = n.dataset.commentId;
        if (id) apiIds[id] = true;
      });

      // Первая страница заменяет прошлую загрузку (SSR-заглушку, старые
      // страницы); свои (без data-page) вынимаем и возвращаем в конец
      var own = [];
      previous.forEach(function (n) {
        if (n.dataset.page) n.remove();
        else own.push(n);
      });
      list.appendChild(fragment);

      own.forEach(function (n) {
        var id = n.dataset.commentId;
        if (id && !apiIds[id]) list.appendChild(n);   // сервер не вернул — оставляем своё
        else n.remove();
      });
    } else {
      // Более старая страница встаёт ВЫШЕ уже показанной (хронология)
      list.prepend(fragment);
      // Оптимистичные свои комментарии остаются самыми новыми — в конце
      previous.forEach(function (n) { if (!n.dataset.page) list.appendChild(n); });
    }
  }

  /** Убирает загруженные страницы, кроме первых keepPages («Show less comments»). */
  function dropPages(list, keepPages) {
    // Сначала собираем, потом удаляем: children — живая коллекция,
    // удаление во время обхода пропускало бы каждый второй узел
    var doomed = [];
    each(list.children, function (n) {
      if (Number(n.dataset.page || 1) > keepPages) doomed.push(n);
    });
    doomed.forEach(function (n) { n.remove(); });
  }

  function pagerButton(label, onClick) {
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn--secondary btn--pill';
    btn.textContent = label;
    btn.addEventListener('click', onClick);
    return btn;
  }

  /**
   * Кнопки НАД списком: «Show more comments» (подгрузить более старые)
   * и «Show less comments» (убрать уже подгруженные).
   * opts: { load(page) } — подгрузку страницы делает вызывающая сторона.
   */
  function syncPager(list, state, opts) {
    var parent = list && list.parentNode;
    if (!parent) return;

    var wrap = parent.querySelector('[data-comments-more]');
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.className = 'comments-section__more';
      wrap.dataset.commentsMore = '1';
      parent.insertBefore(wrap, list);
    }
    wrap.replaceChildren();

    var showMore = !state.loading && (state.loaded === 0 || !!state.hasMore[state.loaded]);
    var showLess = !state.loading && state.loaded > 1;

    if (showMore) {
      wrap.appendChild(pagerButton(LABEL_SHOW_MORE_LIST, function () {
        if (typeof opts.load === 'function') opts.load(state.loaded + 1);
      }));
    }
    if (showLess) {
      wrap.appendChild(pagerButton(LABEL_SHOW_LESS_LIST, function () {
        state.loaded = 1;                    // назад — к самой первой странице
        dropPages(list, 1);
        syncPager(list, state, opts);
      }));
    }
    wrap.hidden = !(showMore || showLess);
  }

  /* ---------------- Ошибка отправки ответа (общая для всех страниц) ---------------- */

  /** '' — успех; иначе человекочитаемая причина. */
  function replyError(data, res, raw) {
    var body = raw || '';

    if (/^\s*</.test(body)) {
      var code = /class="login-message__status"[^>]*>\s*(\d{3})\s*</.exec(body);
      return code
        ? 'Request rejected (HTTP ' + code[1] + ')'
        : 'Server returned an unexpected page (HTTP ' + res.status + '). The reply was not saved.';
    }

    if (data && typeof data === 'object') {
      if (data.errors && typeof data.errors === 'object') {
        var flat = [];
        Object.keys(data.errors).forEach(function (key) {
          var v = data.errors[key];
          if (Array.isArray(v)) flat = flat.concat(v.map(String));
          else if (v != null) flat.push(String(v));
        });
        if (flat.length) return flat.join('\n');
      }
      if (data.error) {
        return typeof data.error === 'string'
          ? data.error
          : (data.error.message ? String(data.error.message) : '');
      }
      if (data.message && !res.ok) return String(data.message);
    }

    if (res.ok) return '';
    if (res.status === 401) return 'Please sign in again';
    if (res.status === 403) return 'Forbidden — check that your email is verified';
    if (res.status === 419) return 'Session expired — reload the page and try again';
    if (res.status === 500) return 'Server error — the reply was not saved';
    return 'Failed to send the reply (HTTP ' + res.status + ')';
  }

  /* ---------------- Делегирование (document — переживает поздний DOM) ---------------- */

  // Раскрытие/сворачивание ответов: «View N more replies» ↔ «Show less»
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-replies-toggle]');
    if (!btn) return;
    var card = btn.closest('[data-comment-id]');
    if (!card) return;

    var list = one('[data-replies]', card);
    var opened = !!(list && !list.hidden);

    if (opened) {
      setRepliesExpanded(card, false);
      return;
    }
    // Ответы ещё не подгружены (список пуст) — подгружаем по требованию;
    // если уже в полёте (loadReplies), повторный запрос не уйдёт
    if (list && !list.children.length && Number(card.dataset.commentId)) {
      loadReplies(card);
    }
    setRepliesExpanded(card, true);
  });

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
        // Ответ начинается с @ника автора комментария (если поле пустое) —
        // это упоминание, поэтому со «@»
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

    var scope = scopeOf(card);
    var pubId = publicationIdOf(scope);
    if (!pubId) return setMsg(errEl, 'Cannot send the reply: the publication id is missing on this page.');

    var body = new URLSearchParams();
    body.set('content', text);
    body.set('publicationId', String(pubId));
    var token = csrfToken();
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

      var err = replyError(data, res, raw);
      if (!err) {
        var me = meInfo(scope);
        appendReply(card, {
          id: data ? (data.createdId != null ? data.createdId : data.id) : null,
          author: me.author,
          authorId: me.id,
          authorUsername: me.author,
          initials: me.initials,
          avatar: me.avatar,
          text: text,
          date: formatDate(new Date().toISOString()),   // dd.mm.yyyy, как у остальных
          likes: 0,
          liked: false
        });

        bumpCommentCount(scope);   // счётчик публикации/поста считает и ответы

        // Свой ответ виден сразу: раскрываем список, кнопка становится «Show less»
        setRepliesExpanded(card, true);

        input.value = '';
        form.hidden = true;
        var toggle = one('[data-reply-toggle]', card);
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        return;
      }

      setMsg(errEl, err);
    } catch (err2) {
      console.warn('POST ' + API_POSTS + '/' + commentId + ' failed', err2);
      setMsg(errEl, 'Network error. Try again.');
    } finally {
      delete form.dataset.sending;
      input.readOnly = false;
      if (sendBtn) sendBtn.disabled = input.value.trim() === '';
    }
  });

  /* ---------------- Экспорт для book.js (details) и card-feed.js (лента) ---------------- */
  window.LoreComments = {
    API_POSTS: API_POSTS,
    COMMENT_MAX: COMMENT_MAX,
    formatDate: formatDate,
    initialsOf: initialsOf,
    avatarOf: avatarOf,
    commentNode: commentNode,
    replyNode: replyNode,
    appendReply: appendReply,
    loadReplies: loadReplies,
    setRepliesExpanded: setRepliesExpanded,
    syncRepliesToggle: syncRepliesToggle,
    scopeOf: scopeOf,
    publicationIdOf: publicationIdOf,
    meInfo: meInfo,
    bumpCommentCount: bumpCommentCount,
    pagerState: pagerState,
    syncPager: syncPager,
    insertPage: insertPage
  };
})();
