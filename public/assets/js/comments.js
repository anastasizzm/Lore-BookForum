"use strict";

(function () {
  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  var API_POSTS = '/api/posts';
  var COMMENT_MAX = 2000;
  var REPLY_PAGE_SIZE = 50;

  var LABEL_SHOW_LESS      = function () { return t('js.show_less'); };
  var LABEL_SHOW_MORE_LIST = function () { return t('js.show_more_comments'); };
  var LABEL_SHOW_LESS_LIST = function () { return t('js.show_less_comments'); };

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

  function csrfToken() {
    if (window.LoreCsrf && LoreCsrf.token) {
      var fromCookie = LoreCsrf.token();
      if (fromCookie) return fromCookie;
    }
    var el = one('[data-comment-form] input[name="_token"]')
          || one('[data-feed-comment-form] [name="_token"]')
          || one('input[name="_token"]');
    return el ? el.value : '';
  }

  function formatDate(value) {
    var raw = (value && typeof value === 'object' && !(value instanceof Date))
      ? value.date : value;
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

  function avatarOf(user) {
    return String((user && user.avatar) || '');
  }

  function fillAvatar(avatar, initials, raw) {
    if (!avatar) return;
    avatar.replaceChildren();
    if (window.LoreAvatar) LoreAvatar.paint(avatar, null);

    var span = document.createElement('span');
    span.textContent = initials || '?';

    var parsed = (window.LoreAvatar && window.LoreAvatar.parse(raw)) || { type: 'none' };
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

  function authorLabel(username, fallback) {
    var v = String(username || '').trim();
    return v || String(fallback || '');
  }

  function repliesLabel(n) {
    n = Number(n) || 0;
    return n === 1
      ? t('js.view_1_reply')
      : t('js.view_n_replies', { n: n });
  }

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
        likeBtn.dataset.liked = data.liked ? '1' : '0';
        if (typeof window.setLikedUI === 'function') window.setLikedUI(likeBtn, !!data.liked);
      } else {
        likeBtn.remove();
      }
    }

    var moreBtn = one('[data-replies-toggle]', node);
    var repliesCount = Number(data.replies) || 0;
    node.dataset.repliesCount = String(repliesCount);
    if (moreBtn) {
      moreBtn.hidden = repliesCount <= 0;
      if (repliesCount > 0) moreBtn.textContent = repliesLabel(repliesCount);
    }

    syncOwnMenu(node, data.authorId);
    return node;
  }

  function replyNode(data) {
    var tpl = document.getElementById('reply-template');
    if (!tpl) return null;

    var node = tpl.content.firstElementChild.cloneNode(true);
    if (data.id != null && Number(data.id)) node.dataset.replyId = String(Number(data.id));

    var author = one('.comment-reply__author', node);
    if (author) {
      Users.renderAuthor(author, authorLabel(data.authorUsername, data.author),
                         data.authorId, data.authorUsername);
    }
    Users.renderText(one('.comment-reply__text', node), data.text || '');
    fillAvatar(one('.avatar', node), data.initials, data.avatar || '');

    var date = one('[data-reply-date]', node);
    if (date) date.textContent = data.date || '';

    if (data.authorUsername) node.dataset.authorUsername = data.authorUsername;
    if (data.authorId) node.dataset.authorId = String(Number(data.authorId));

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

    syncOwnMenu(node, data.authorId);
    return node;
  }

  function scopeOf(node) {
    if (!node || !node.closest) return document;
    return node.closest('[data-comments]') || node.closest('.card-feed') || document;
  }

  function publicationIdOf(scope) {
    if (!scope) return 0;
    var v = Number((scope.dataset && scope.dataset.publicationId) || 0);
    if (v) return v;
    var input = one('[name="publicationId"]', scope);
    return input ? Number(input.value || 0) : 0;
  }

  function bumpCommentCount(scope) {
    var el = scope && (one('[data-comments-count]', scope) || one('[data-comment-count]', scope));
    if (el) el.textContent = String((parseInt(el.textContent, 10) || 0) + 1);
  }

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

  function myId() {
    var sec = document.querySelector('[data-comments][data-me-id]');
    if (sec) return Number(sec.dataset.meId || 0) || 0;
    var card = document.querySelector('.card-feed[data-cu-id]');
    return card ? (Number(card.dataset.cuId || 0) || 0) : 0;
  }

  function syncOwnMenu(node, authorId) {
    var wrap = one('[data-c-menu-wrap]', node);
    if (wrap) wrap.hidden = !(Number(authorId) && Number(authorId) === myId());
  }

  function repliesCountOf(card, list) {
    if (card && card.dataset && card.dataset.repliesCount !== ''
        && card.dataset.repliesCount != null) {
      return Number(card.dataset.repliesCount) || 0;
    }
    return list ? list.children.length : 0;
  }

  function setRepliesExpanded(card, expanded) {
    var list = one('[data-replies]', card);
    var btn = one('[data-replies-toggle]', card);
    if (!list || !btn) return;

    var n = repliesCountOf(card, list);
    if (n <= 0) { list.hidden = true; btn.hidden = true; return; }

    list.hidden = !expanded;
    btn.hidden = false;
    btn.textContent = expanded ? LABEL_SHOW_LESS() : repliesLabel(n);
    btn.setAttribute('aria-expanded', String(!!expanded));
  }

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

  async function loadReplies(card) {
    var commentId = Number((card && card.dataset.commentId) || 0);
    if (!commentId) return;
    if (card.dataset.repliesLoading === '1') return;
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
        if (window.Messages) {
          window.Messages.show(
            window.Messages.describe(res.status, data, '', t('js.replies_load_failed')),
            { type: 'error' });
        }
        syncRepliesToggle(card);
        return;
      }

      var items = Array.isArray(data.items) ? data.items : [];
      var list = one('[data-replies]', card);
      if (!list) return;

      list.replaceChildren();
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

      syncRepliesToggle(card);
    } catch (err) {
      console.warn('GET ' + API_POSTS + '?parent= failed', err);
      if (window.Messages) window.Messages.show(t('js.network_error'), { type: 'error' });
      syncRepliesToggle(card);
    } finally {
      delete card.dataset.repliesLoading;
    }
  }

  function pagerState() { return { loaded: 0, hasMore: [], loading: false }; }

  function insertPage(list, fragment, page) {
    var previous = [];
    each(list.children, function (n) { previous.push(n); });

    if (Number(page) === 1) {
      var apiIds = {};
      each(fragment.children, function (n) {
        var id = n.dataset.commentId;
        if (id) apiIds[id] = true;
      });
      var own = [];
      previous.forEach(function (n) {
        if (n.dataset.page) n.remove(); else own.push(n);
      });
      list.appendChild(fragment);
      own.forEach(function (n) {
        var id = n.dataset.commentId;
        if (id && !apiIds[id]) list.appendChild(n); else n.remove();
      });
    } else {
      list.prepend(fragment);
      previous.forEach(function (n) { if (!n.dataset.page) list.appendChild(n); });
    }
  }

  function dropPages(list, keepPages) {
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
      wrap.appendChild(pagerButton(LABEL_SHOW_MORE_LIST(), function () {
        if (typeof opts.load === 'function') opts.load(state.loaded + 1);
      }));
    }
    if (showLess) {
      wrap.appendChild(pagerButton(LABEL_SHOW_LESS_LIST(), function () {
        state.loaded = 1;
        dropPages(list, 1);
        syncPager(list, state, opts);
      }));
    }
    wrap.hidden = !(showMore || showLess);
  }

  function payloadError(data) {
    if (!data || typeof data !== 'object' || (!data.error && !data.errors)) return '';
    if (window.Messages) return window.Messages.fromPayload(data);
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
    return '';
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
      return t('js.unexpected_page_reply', { status: res.status });
    }
    return t('js.send_reply_failed', { status: res.status });
  }

  function closeMenus(except) {
    each(document.querySelectorAll('[data-c-menu]:not([hidden])'), function (menu) {
      if (menu === except) return;
      menu.hidden = true;
      var btn = menu.parentNode && menu.parentNode.querySelector('[data-c-menu-toggle]');
      if (btn) btn.setAttribute('aria-expanded', 'false');
    });
  }

  document.addEventListener('click', function (e) {
    var toggle = e.target.closest && e.target.closest('[data-c-menu-toggle]');
    if (!toggle) return;
    var wrap = toggle.closest('[data-c-menu-wrap]');
    var menu = wrap && one('[data-c-menu]', wrap);
    if (!menu) return;
    var open = menu.hidden;
    closeMenus(open ? menu : null);
    menu.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-c-menu-wrap]')) return;
    closeMenus();
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeMenus();
  });

  function markDeleted(node) {
    if (!node) return;
    node.dataset.deleted = '1';
    node.classList.add('is-deleted');

    each(node.querySelectorAll(
      '[data-like-btn], [data-reply-toggle], [data-c-menu-wrap], [data-replies-toggle],' +
      ' [data-reply-form], [data-replies], .comment-card__footer'
    ), function (el) { el.hidden = true; });

    var textEl = one('[data-c-text]', node) || one('.comment-reply__text', node);
    if (textEl) {
      textEl.textContent = t('js.comment_deleted');
      textEl.classList.add('comment-card__text--deleted');
    }
  }

  document.addEventListener('click', async function (e) {
    var delBtn = e.target.closest && e.target.closest('[data-c-delete]');
    if (!delBtn) return;

    var replyEl = delBtn.closest('.comment-reply');
    var node = replyEl || delBtn.closest('[data-comment-id]');
    var id = replyEl
      ? Number(node && node.dataset.replyId || 0)
      : Number(node && node.dataset.commentId || 0);
    if (!node || !id || node.dataset.deleting === '1') return;

    closeMenus();

    var ask = (window.ConfirmModal && typeof window.ConfirmModal.confirm === 'function')
      ? window.ConfirmModal.confirm({
          title: t('js.delete_comment_title'),
          message: t('js.delete_comment_text'),
          confirmText: t('js.delete'),
          danger: true
        })
      : Promise.resolve(window.confirm(t('js.delete_comment_text')));

    var confirmed = await ask;
    if (!confirmed) return;

    node.dataset.deleting = '1';
    try {
      var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      var token = csrfToken();
      if (token) headers['X-CSRF-Token'] = token;

      var res = await fetch(API_POSTS + '/' + id, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: headers
      });

      if (res.status === 204) { markDeleted(node); return; }

      var msg = window.Messages
        ? await window.Messages.readError(res, t('js.delete_comment_failed'))
        : t('js.delete_comment_failed') + ' (HTTP ' + res.status + ').';
      if (window.Messages) window.Messages.show(msg, { type: 'error' });
      else console.warn(msg);
    } catch (err) {
      console.error('DELETE ' + API_POSTS + '/' + id + ' failed', err);
      if (window.Messages) window.Messages.show(t('js.network_error'), { type: 'error' });
    } finally {
      delete node.dataset.deleting;
    }
  });

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-replies-toggle]');
    if (!btn) return;
    var card = btn.closest('[data-comment-id]');
    if (!card) return;

    var list = one('[data-replies]', card);
    var opened = !!(list && !list.hidden);

    if (opened) { setRepliesExpanded(card, false); return; }
    if (list && !list.children.length && Number(card.dataset.commentId)) {
      loadReplies(card);
    }
    setRepliesExpanded(card, true);
  });

  function formHome(form) {
    if (!form.__home && form.parentNode) {
      form.__home = { parent: form.parentNode, next: form.nextSibling };
    }
    return form.__home;
  }

  function returnFormHome(form) {
    var home = form.__home;
    if (!home || !home.parent) return;
    var next = home.next && home.next.parentNode === home.parent ? home.next : null;
    home.parent.insertBefore(form, next);
  }

  function placeForm(form, replyEl) {
    var body = replyEl && (one('.comment-reply__content', replyEl)
                        || one('.comment-reply__body', replyEl));
    if (!body) return returnFormHome(form);
    formHome(form);
    body.appendChild(form);
  }

  document.addEventListener('click', function (e) {
    var replyBtn = e.target.closest && e.target.closest('[data-reply-toggle]');
    if (!replyBtn) return;
    var content = replyBtn.closest('.comment-card__content');
    if (!content) return;
    var form = content.querySelector('[data-reply-form]');
    if (!form) return;

    var replyEl = replyBtn.closest('.comment-reply');
    var body = replyEl && (one('.comment-reply__content', replyEl)
                        || one('.comment-reply__body', replyEl));

    var atTarget = body
      ? form.parentNode === body
      : (!form.__home || form.parentNode === form.__home.parent);
    var open = form.hidden || !atTarget;

    form.hidden = !open;
    if (open) placeForm(form, replyEl); else returnFormHome(form);

    Array.prototype.forEach.call(content.querySelectorAll('[data-reply-toggle]'), function (b) {
      b.setAttribute('aria-expanded', String(open));
    });

    if (open) {
      var first = form.querySelector('input');
      if (first) {
        var card = replyBtn.closest('[data-comment-id]');
        var target = (replyEl && replyEl.dataset.authorUsername)
                  || (card && card.dataset.authorUsername) || '';
        form.dataset.mentionTarget = target;

        if (target && (!first.value.trim() || /^@\S+\s*$/.test(first.value))) {
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

    text = Users.withMention(text, (form.dataset.mentionTarget || card.dataset.authorUsername) || '');
    if (input) input.value = text;

    if (text.length > COMMENT_MAX) {
      return setMsg(errEl, t('js.max_length', { max: COMMENT_MAX }));
    }

    var scope = scopeOf(card);
    var pubId = publicationIdOf(scope);
    if (!pubId) return setMsg(errEl, t('js.publication_id_missing'));

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
      try { raw = await res.text(); data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

      var serverError = payloadError(data);
      var htmlErrorPage = /class="login-message__(status|text)/.test(raw) || /^\s*</.test(raw);

      if (res.ok && !serverError && !htmlErrorPage) {
        var me = meInfo(scope);
        appendReply(card, {
          id: data ? (data.createdId != null ? data.createdId : data.id) : null,
          author: me.author,
          authorId: me.id,
          authorUsername: me.author,
          initials: me.initials,
          avatar: me.avatar,
          text: text,
          date: formatDate(new Date().toISOString()),
          likes: 0,
          liked: false
        });

        bumpCommentCount(scope);
        setRepliesExpanded(card, true);

        input.value = '';
        form.hidden = true;
        returnFormHome(form);
        Array.prototype.forEach.call(card.querySelectorAll('[data-reply-toggle]'), function (b) {
          b.setAttribute('aria-expanded', 'false');
        });
        return;
      }

      setMsg(errEl, serverError || failureText(res, raw));
    } catch (err2) {
      console.error('POST ' + API_POSTS + '/' + commentId + ' failed', err2);
      setMsg(errEl, t('js.network_error'));
    } finally {
      delete form.dataset.sending;
      input.readOnly = false;
      if (sendBtn) sendBtn.disabled = input.value.trim() === '';
    }
  });

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
    myId: myId,
    syncOwnMenu: syncOwnMenu,
    markDeleted: markDeleted,
    bumpCommentCount: bumpCommentCount,
    pagerState: pagerState,
    syncPager: syncPager,
    insertPage: insertPage
  };
})();