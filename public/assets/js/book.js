"use strict";

(function () {
  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  // Start / Resume / Read again — пока заглушка
  var startReading = document.querySelector('[data-start-reading]');
  if (startReading) {
    startReading.addEventListener('click', function () {
      var status = startReading.dataset.readingStatus;
      // TODO: new -> открыть с первой главы; in_progress -> с сохранённого места
    });
  }

  // Click to Rate
  var rate = document.querySelector('[data-rate]');
  if (rate) {
    var stars = rate.querySelectorAll('[data-rate-value]');
    var current = 0;

    var paint = function (value) {
      stars.forEach(function (star, i) {
        star.classList.toggle('is-filled', i < value);
        star.setAttribute('aria-checked', String(current > 0 && i + 1 === current));
      });
    };

    stars.forEach(function (star) {
      var value = Number(star.dataset.rateValue);
      star.addEventListener('mouseenter', function () { paint(value); });
      star.addEventListener('click', function () {
        current = current === value ? 0 : value;
        paint(current);
      });
    });

    rate.addEventListener('mouseleave', function () { paint(current); });
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
        tabs.forEach(function (t) { t.classList.toggle('is-active', t === tab); });
        panels.forEach(function (panel) { panel.hidden = panel.dataset.row !== target; });
      });
    });
  }

  var API_POSTS = '/api/posts';
  var COMMENT_MAX = 2000;
  var PAGE_SIZE = 20;

  var Users = window.LoreUsers || {
    remember: function () { return 0; },
    renderAuthor: function (el, label) { if (el) el.textContent = label || ''; },
    renderText: function (el, text) { if (el) el.textContent = text || ''; },
    withMention: function (text) { return String(text == null ? '' : text).trim(); }
  };

  function one(sel, root) { return (root || document).querySelector(sel); }

  function setMsg(el, text) {
    if (!el) return;
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

  function payloadError(data) {
    if (!data || typeof data !== 'object' || (!data.error && !data.errors)) return '';
    return window.Messages ? window.Messages.fromPayload(data) : '';
  }

  function failureText(res, raw) {
    var body = raw || '';
    var code = /class="login-message__status"[^>]*>\s*(\d{3})\s*</.exec(body);
    var text = /class="login-message__text"[^>]*>([\s\S]*?)<\/p>/.exec(body);

    if (code) {
      var msg = text ? text[1].replace(/<[^>]+>/g, '').replace(/\s+/g, ' ').trim() : '';
      return (msg || t('js.request_rejected')) + ' (HTTP ' + code[1] + ')';
    }
    if (/^\s*</.test(body)) {
      return t('js.unexpected_page_comment', { status: res.status });
    }
    return t('js.send_comment_failed', { status: res.status });
  }

  function formatDate(value) {
    var raw = (value && typeof value === 'object' && !(value instanceof Date)) ? value.date : value;
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

  function avatarOf(user) { return String((user && user.avatar) || ''); }

  function fillAvatar(avatar, initials, raw) {
    if (!avatar) return;
    avatar.replaceChildren();
    if (window.LoreAvatar) LoreAvatar.paint(avatar, null);

    var span = document.createElement('span');
    span.textContent = initials || '?';

    var parsed = (window.LoreAvatar && LoreAvatar.parse(raw)) || { type: 'none' };
    if (parsed.type === 'emoji') {
      var em = document.createElement('span');
      em.className = 'avatar__emoji';
      em.setAttribute('aria-hidden', 'true');
      em.textContent = parsed.emoji;
      avatar.appendChild(em);
      if (window.LoreAvatar) LoreAvatar.paint(avatar, parsed);
      return;
    }
    if (parsed.type !== 'image') { avatar.appendChild(span); return; }

    var img = document.createElement('img');
    img.alt = '';
    img.src = parsed.src;
    avatar.appendChild(img);
    avatar.appendChild(span);
    span.hidden = true;
    img.onerror = function () { img.hidden = true; span.hidden = false; };
  }

  function commentNode(data) {
    var tpl = document.getElementById('comment-card-template');
    if (!tpl) return null;
    var node = tpl.content.firstElementChild.cloneNode(true);
    if (data.id != null && Number(data.id)) node.dataset.commentId = String(Number(data.id));
    fillAvatar(one('.avatar', node), data.initials, data.avatar || '');
    var author = one('[data-c-author]', node);
    if (author) Users.renderAuthor(author, data.author, data.authorId, data.authorUsername);
    if (data.authorUsername) node.dataset.authorUsername = data.authorUsername;
    if (data.authorId) node.dataset.authorId = String(Number(data.authorId));
    Users.renderText(one('[data-c-text]', node), data.text || '');
    var date = one('[data-c-date]', node);
    if (date) date.textContent = data.date || '';

    var likeBtn = one('[data-like-btn]', node);
    if (likeBtn) {
      if (Number(data.id)) {
        likeBtn.dataset.likeId = String(Number(data.id));
        var cnt = one('[data-like-count]', likeBtn);
        if (cnt) cnt.textContent = String(Number(data.likes) || 0);
      } else { likeBtn.remove(); }
    }
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
      try { raw = await res.text(); data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

      if (!res.ok || !data) {
        console.warn('GET ' + API_POSTS, res.status, raw.slice(0, 200));
        if (window.Messages) {
          window.Messages.show(
            window.Messages.describe(res.status, data, raw, t('js.comments_load_failed')),
            { type: 'error' });
        }
        if (!append && listEl() && !listEl().children.length) {
          setMsg(emptyEl(), t('js.comments_unavailable'));
        }
        return;
      }

      var items = Array.isArray(data.items) ? data.items
                : (Array.isArray(data) ? data : []);

      if (!append) {
        var list = listEl();
        if (list) list.replaceChildren();
      }

      var withReplies = [];
      items.forEach(function (item) {
        var node = appendComment({
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
        }, false);
        if (node && Number(item.commentsCount) > 0) withReplies.push(node);
      });
      withReplies.forEach(function (node) { loadReplies(node); });

      if (emptyEl()) {
        var n = listEl() ? listEl().children.length : 0;
        emptyEl().hidden = n > 0;
      }
      if (data.meta && data.meta.hasNext) renderMore(page || 1);
    } catch (err) {
      console.warn('GET ' + API_POSTS + ' failed', err);
      if (window.Messages) window.Messages.show(t('js.network_error'), { type: 'error' });
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
    btn.textContent = t('js.show_more_comments');
    btn.addEventListener('click', function () {
      var next = loadedPage + 1;
      wrap.remove();
      loadComments(next, true);
    });

    wrap.appendChild(btn);
    list.parentNode.insertBefore(wrap, list.nextSibling);
  }

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
    if (text === '') { setMsg(errEl, t('js.comment_empty')); return; }
    if (text.length > COMMENT_MAX) {
      setMsg(errEl, t('js.max_length', { max: COMMENT_MAX }));
      return;
    }

    var pubInput = one('input[name="publicationId"]', form);
    if (!Number(pubInput && pubInput.value)) {
      setMsg(errEl, t('js.publication_id_missing_comment'));
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
      try { raw = await res.text(); data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

      var serverError = payloadError(data);
      var htmlErrorPage = /class="login-message__(status|text)/.test(raw);
      if (/^\s*</.test(raw)) htmlErrorPage = true;

      if (res.ok && !serverError && !htmlErrorPage) {
        setMsg(statusEl, t('js.comment_sent'));
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
          user: null,
          text: text,
          date: new Date().toISOString(),
          likes: 0,
          liked: false
        }, true);

        var formAvatar = one('.avatar', form);
        if (node && formAvatar) {
          var nodeAvatar = one('.avatar', node);
          if (nodeAvatar) nodeAvatar.replaceWith(formAvatar.cloneNode(true));
        }
        var nodeAuthor = node && one('[data-c-author]', node);
        if (nodeAuthor && !nodeAuthor.textContent) {
          nodeAuthor.textContent = (one('.comment-card__author', form) || {}).textContent || '';
        }

        if (!data) window.setTimeout(function () { loadComments(1, false); }, 700);
        if (typeof applyLikedState === 'function') applyLikedState(document);
        return;
      }

      setMsg(errEl, serverError || failureText(res, raw));
    } catch (err) {
      console.warn('POST ' + API_POSTS + ' failed', err);
      setMsg(errEl, t('js.network_error'));
    } finally {
      delete form.dataset.sending;
      input.readOnly = false;
      if (sendBtn) sendBtn.disabled = input.value.trim() === '';
      if (document.activeElement === input || document.activeElement === document.body) {
        input.focus();
      }
    }
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' && e.key !== 'NumpadEnter') return;
    var input = e.target.closest && e.target.closest('[data-comment-form] input[name="content"]');
    if (!input) return;
    e.preventDefault();
    submitComment(input.closest('[data-comment-form]'));
  });

  document.addEventListener('submit', function (e) {
    var form = e.target.closest && e.target.closest('[data-comment-form]');
    if (!form) return;
    e.preventDefault();
    submitComment(form);
  });

  document.addEventListener('input', function (e) {
    var input = e.target.closest && e.target.closest('[data-comment-form] input[name="content"]');
    if (!input) return;
    var form = input.closest('[data-comment-form]');
    var sendBtn = one('[data-comment-send]', form);
    if (sendBtn) sendBtn.disabled = input.value.trim() === '';
    setMsg(one('[data-comment-error]', form), '');
    setMsg(one('[data-comment-status]', form), '');
  });

  var REPLY_PAGE_SIZE = 50;

  function csrfTokenValue() {
    var fromCookie = window.LoreCsrf ? LoreCsrf.token() : '';
    if (fromCookie) return fromCookie;
    var el = one('[data-comment-form] input[name="_token"]') || one('input[name="_token"]');
    return el ? el.value : '';
  }

  function meInfo() {
    var section = sectionEl() || document;
    return {
      id: Number(section.dataset.meId || 0),
      author:   section.dataset.meUsername || section.dataset.meName || '',
      initials: section.dataset.meInitials || '?',
      avatar:   section.dataset.meAvatar || ''
    };
  }

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

  async function loadReplies(card) {
    var commentId = Number((card && card.dataset.commentId) || 0);
    if (!commentId) return;
    var params = new URLSearchParams({
      parent: String(commentId), page: '1', ps: String(REPLY_PAGE_SIZE), include: 'creator'
    });

    try {
      var res = await fetch(API_POSTS + '?' + params.toString(), {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      });
      var data = null;
      try { data = await res.json(); } catch (_) { data = null; }
      if (!res.ok || !data) {
        if (window.Messages) {
          window.Messages.show(
            window.Messages.describe(res.status, data, '', t('js.replies_load_failed')),
            { type: 'error' });
        }
        return;
      }
      var items = Array.isArray(data.items) ? data.items : [];
      var list = one('[data-replies]', card);
      if (!list) return;
      list.replaceChildren();
      items.slice().reverse().forEach(function (item) {
        appendReply(card, {
          author: (item.creator && (item.creator.username || item.creator.name)) || '',
          authorId: item.creator && item.creator.id,
          authorUsername: (item.creator && item.creator.username) || '',
          initials: initialsOf(item.creator),
          avatar: avatarOf(item.creator),
          text: item.content || '',
          date: formatDate(item.createdAt)
        });
      });
    } catch (err) {
      console.warn('GET ' + API_POSTS + '?parent= failed', err);
      if (window.Messages) window.Messages.show(t('js.network_error'), { type: 'error' });
    }
  }

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
        var card = replyBtn.closest('[data-comment-id]');
        var target = (card && card.dataset.authorUsername) || '';
        if (target && first.value.trim() === '') {
          first.value = '@' + target + ' ';
          first.dispatchEvent(new Event('input', { bubbles: true }));
        }
        first.focus();
        try { first.selectionStart = first.selectionEnd = first.value.length; } catch (_) {}
      }
    }
  });

  document.addEventListener('input', function (e) {
    var input = e.target.closest && e.target.closest('.comment-reply-form__input');
    if (!input) return;
    var form = input.closest('form');
    var btn = form && form.querySelector('.comment-reply-form__submit');
    if (btn) btn.disabled = input.value.trim() === '';
    setMsg(form ? one('[data-reply-error]', form) : null, '');
  });

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
    if (!commentId) return setMsg(errEl, t('js.comment_id_missing'));
    if (text === '') return;

    text = Users.withMention(text, card.dataset.authorUsername || '');
    if (input) input.value = text;
    if (text.length > COMMENT_MAX) {
      return setMsg(errEl, t('js.max_length', { max: COMMENT_MAX }));
    }

    var section = sectionEl();
    var pubId = Number((section && section.dataset.publicationId) || 0);
    if (!pubId) return setMsg(errEl, t('js.publication_id_missing'));

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
      try { raw = await res.text(); data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

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
        bumpCount();
        input.value = '';
        form.hidden = true;
        var toggle = one('[data-reply-toggle]', card);
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        return;
      }
      setMsg(errEl, serverError || failureText(res, raw));
    } catch (err) {
      console.warn('POST ' + API_POSTS + '/' + commentId + ' failed', err);
      setMsg(errEl, t('js.network_error'));
    } finally {
      delete form.dataset.sending;
      input.readOnly = false;
      if (sendBtn) sendBtn.disabled = input.value.trim() === '';
    }
  });

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