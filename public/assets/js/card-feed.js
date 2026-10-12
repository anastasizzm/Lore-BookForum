const t = (key, params) => window.LoreI18n ? LoreI18n.t(key, params) : key;

const fcUsers = window.LoreUsers || {
  remember: () => 0,
  renderAuthor: (el, label) => { if (el) el.textContent = label || ''; },
  renderText: (el, text) => { if (el) el.textContent = text || ''; },
  withMention: (text) => String(text == null ? '' : text).trim(),
};

function csrfToken() {
  const fromCookie = window.LoreCsrf ? window.LoreCsrf.token() : '';
  if (fromCookie) return fromCookie;
  const el = document.querySelector('[data-feed-comment-form] [name="_token"]')
          || document.querySelector('[data-csrf] [name="_token"]')
          || document.querySelector('[name="_token"]');
  return el ? el.value : '';
}

function csrfHeaders() {
  const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
  const token = csrfToken();
  if (token) headers['X-CSRF-Token'] = token;
  return headers;
}

function setFeedMsg(el, text) {
  if (!el) return;
  const isError  = el.hasAttribute('data-comment-error') || el.hasAttribute('data-reply-error');
  const isStatus = el.hasAttribute('data-comment-status');
  if ((isError || isStatus) && window.Messages) {
    el.textContent = '';
    el.hidden = true;
    if (text) window.Messages.show(text, { type: isError ? 'error' : 'success' });
    return;
  }
  el.textContent = text;
  el.hidden = text === '';
}

function notify(text) {
  if (window.Messages) window.Messages.show(text, { type: 'error' });
  else console.warn(text);
}

function likeIdOf(btn) {
  if (btn.dataset.likeId) return Number(btn.dataset.likeId);
  const card = btn.closest('.card-feed');
  return card ? Number(card.dataset.postId) : 0;
}

function setLikedUI(btn, liked) {
  btn.classList.toggle('is-liked', liked);
  btn.setAttribute('aria-pressed', String(liked));
  btn.dataset.liked = liked ? '1' : '0';
}

function applyLikedState(root) {
  (root || document).querySelectorAll('[data-like-btn]').forEach((btn) => {
    if (btn.dataset.liked === undefined) return;
    setLikedUI(btn, btn.dataset.liked === '1');
  });
}

async function sendLike(id, liked) {
  try {
    const body = new URLSearchParams();
    const token = csrfToken();
    if (token) body.set('_token', token);

    const res = await fetch(`/api/posts/${id}/like`, {
      method: liked ? 'POST' : 'DELETE',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body,
    });

    if (res.status === 204) return '';
    if (!res.ok) {
      return window.Messages
        ? await window.Messages.readError(res, t('js.like_update_failed'))
        : t('js.like_update_failed') + ` (HTTP ${res.status}).`;
    }
    const raw = await res.text();
    if (/^\s*</.test(raw)) return t('js.like_not_saved');
    return '';
  } catch (_) {
    return t('js.network_error');
  }
}

document.addEventListener('click', (e) => {
  const likeBtn = e.target.closest('[data-like-btn]');
  if (likeBtn) {
    e.preventDefault();
    const id = likeIdOf(likeBtn);
    if (!id) return;

    const countEl = likeBtn.querySelector('[data-like-count]');
    const liked = !likeBtn.classList.contains('is-liked');
    const prev = parseInt(countEl ? countEl.textContent : '0', 10) || 0;

    setLikedUI(likeBtn, liked);
    if (countEl) countEl.textContent = Math.max(0, prev + (liked ? 1 : -1));

    sendLike(id, liked).then((err) => {
      if (!err) return;
      notify(err);
      setLikedUI(likeBtn, !liked);
      if (countEl) countEl.textContent = String(prev);
    });
    return;
  }

  const toggleBtn = e.target.closest('[data-comment-toggle]');
  if (toggleBtn) {
    const card = toggleBtn.closest('.card-feed');
    const form = card.querySelector('[data-feed-comment-form]');
    if (!form) return;
    const open = form.hidden;
    form.hidden = !open;
    toggleBtn.setAttribute('aria-expanded', String(open));
    if (open) {
      const input = form.querySelector('.comment-form__input');
      const target = card.dataset.authorUsername || '';
      if (input) {
        if (target && (!input.value.trim() || /^@\S+\s*$/.test(input.value))) {
          input.value = '@' + target + ' ';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        input.focus();
      }
    }
    return;
  }

  const replyBtn = e.target.closest('[data-fc-reply]');
  if (replyBtn) {
    const node = replyBtn.closest('.feed-comment');
    const form = node ? node.querySelector('[data-fc-reply-form]') : null;
    if (!form) return;
    const open = form.hidden;
    form.hidden = !open;
    replyBtn.setAttribute('aria-expanded', String(open));
    if (open) {
      const input = form.querySelector('.comment-form__input');
      if (input) {
        const target = (node && node.dataset.authorUsername) || '';
        if (target && input.value.trim() === '') {
          input.value = '@' + target + ' ';
          input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        input.focus();
        try { input.selectionStart = input.selectionEnd = input.value.length; } catch (_) {}
      }
    }
  }
});

document.addEventListener('input', (e) => {
  const input = e.target.closest('.comment-form__input');
  if (!input) return;
  const form = input.closest('.comment-form');
  if (!form) return;
  const submit = form.querySelector('.comment-form__submit');
  if (submit) submit.disabled = input.value.trim() === '';
  setFeedMsg(form.querySelector('[data-comment-error]'), '');
  setFeedMsg(form.querySelector('[data-comment-status]'), '');
});

function sendStatus(data, res, fallback) {
  const msg = window.Messages ? window.Messages.fromPayload(data) : '';
  return msg || fallback(res.status);
}

document.addEventListener('submit', async (e) => {
  const form = e.target.closest('[data-feed-comment-form]');
  if (!form) return;
  e.preventDefault();
  if (form.dataset.sending === '1') return;

  const card = form.closest('.card-feed');
  const input = form.querySelector('.comment-form__input');
  const submitBtn = form.querySelector('.comment-form__submit');
  const errEl = form.querySelector('[data-comment-error]');
  const statusEl = form.querySelector('[data-comment-status]');
  const MAX = 2000;

  setFeedMsg(errEl, '');
  setFeedMsg(statusEl, '');

  let text = input.value.trim();
  if (text === '') return;

  const postAuthor = card ? (card.dataset.authorUsername || '') : '';
  if (postAuthor && window.LoreUsers && typeof window.LoreUsers.withMention === 'function') {
    text = window.LoreUsers.withMention(text, postAuthor);
    input.value = text;
  }

  if (text.length > MAX) return setFeedMsg(errEl, t('js.max_length', { max: MAX }));
  if (!Number(form.dataset.postId)) return setFeedMsg(errEl, t('js.post_id_missing'));
  if (!Number(form.elements.publicationId.value)) return setFeedMsg(errEl, t('js.publication_id_missing'));
  if (form.dataset.lastSent === text) return setFeedMsg(errEl, t('js.comment_duplicate'));

  const body = new URLSearchParams(new FormData(form));
  body.set('content', text);
  const csrfValue = csrfToken();
  if (csrfValue) body.set('_token', csrfValue);

  form.dataset.sending = '1';
  input.disabled = true;
  submitBtn.disabled = true;

  try {
    const res = await fetch(form.getAttribute('action'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body,
    });

    let data = null;
    try { data = await res.json(); } catch (_) { }

    if (res.status === 201) {
      form.dataset.lastSent = text;
      setFeedMsg(statusEl, t('js.comment_sent'));
      input.value = '';

      const countEl = card ? card.querySelector('[data-comment-count]') : null;
      if (countEl) countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + 1;

      form.dispatchEvent(new CustomEvent('comment:created', {
        bubbles: true,
        detail: {
          id: data && data.createdId,
          parentId: Number(form.dataset.postId),
          content: text,
        },
      }));
    } else {
      setFeedMsg(errEl, sendStatus(data, res, (s) => t('js.send_comment_failed', { status: s })));
    }
  } catch (err) {
    console.error('[feed] comment request failed:', err);
    setFeedMsg(errEl, t('js.network_error'));
  } finally {
    form.dataset.sending = '0';
    input.disabled = false;
    submitBtn.disabled = input.value.trim() === '';
    input.focus();
  }
});

document.addEventListener('submit', async (e) => {
  const form = e.target.closest('[data-fc-reply-form]');
  if (!form) return;
  e.preventDefault();
  if (form.dataset.sending === '1') return;

  const node = form.closest('.feed-comment');
  const card = form.closest('.card-feed');
  const commentId = node ? Number(node.dataset.commentId) : 0;
  const input = form.querySelector('.comment-form__input');
  const submitBtn = form.querySelector('.comment-form__submit');
  const errEl = form.querySelector('[data-comment-error]');
  const statusEl = form.querySelector('[data-comment-status]');

  setFeedMsg(errEl, '');
  setFeedMsg(statusEl, '');

  let text = input.value.trim();
  if (text === '') return;
  if (!commentId) return setFeedMsg(errEl, t('js.comment_id_missing'));

  text = fcUsers.withMention(text, node ? node.dataset.authorUsername : '');
  input.value = text;

  if (form.dataset.lastSent === text) return setFeedMsg(errEl, t('js.comment_duplicate'));

  const mainForm = card ? card.querySelector('[data-feed-comment-form]') : null;
  const publicationId = mainForm && mainForm.elements.publicationId
      ? mainForm.elements.publicationId.value : '';
  if (!Number(publicationId)) return setFeedMsg(errEl, t('js.publication_id_missing'));

  const body = new URLSearchParams();
  const token = csrfToken();
  if (token) body.set('_token', token);
  body.set('publicationId', publicationId);
  body.set('content', text);

  form.dataset.sending = '1';
  input.disabled = true;
  submitBtn.disabled = true;

  try {
    const res = await fetch(`/api/posts/${commentId}`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: csrfHeaders(),
      body,
    });

    let data = null;
    try { data = await res.json(); } catch (_) { }

    if (res.status === 201) {
      form.dataset.lastSent = text;
      setFeedMsg(statusEl, t('js.reply_sent'));
      input.value = '';
      form.hidden = true;
      if (card && data && data.createdId) {
        fcAppendOwn(card, data.createdId, text);
      }
    } else {
      setFeedMsg(errEl, sendStatus(data, res, (s) => t('js.send_reply_failed', { status: s })));
    }
  } catch (err) {
    console.error('[feed] comment request failed:', err);
    setFeedMsg(errEl, t('js.network_error'));
  } finally {
    form.dataset.sending = '0';
    input.disabled = false;
    submitBtn.disabled = input.value.trim() === '';
  }
});

const FEED_COMMENTS = { url: '/api/posts', pageSize: 10 };
const feedCommentsState = new WeakMap();

function fcParts(card) {
  const root = card.querySelector('[data-feed-comments]');
  if (!root) return null;
  return {
    root,
    list: root.querySelector('[data-fc-list]'),
    status: root.querySelector('[data-fc-status]'),
    more: root.querySelector('[data-fc-more]'),
  };
}

function fcState(card) {
  let st = feedCommentsState.get(card);
  if (!st) {
    st = { page: 0, hasNext: false, loading: false, gen: 0, started: false };
    feedCommentsState.set(card, st);
  }
  return st;
}

function fcFormatDate(value) {
  if (window.LoreI18n && typeof LoreI18n.formatDate === 'function') {
    return LoreI18n.formatDate(value);
  }
  const raw = value && typeof value === 'object' ? value.date : value;
  const m = /(\d{4})-(\d{2})-(\d{2})/.exec(String(raw || ''));
  return m ? `${m[3]}.${m[2]}.${m[1]}` : '';
}

function fcFeedPostIds() {
  const ids = new Set();
  document.querySelectorAll('.card-feed[data-post-id]').forEach((el) => {
    const id = Number(el.dataset.postId);
    if (id) ids.add(id);
  });
  return ids;
}

function fcBoundToPost(items, postId, feedIds) {
  return items.filter((it) => {
    const id = Number(it.id);
    if (!id) return false;
    if (id === postId) return false;
    if (feedIds.has(id)) return false;
    return true;
  });
}

function fcEsc(value) {
  return String(value)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

const fcFirstChar = (s) => Array.from(s || '')[0] || '';

function fcBuildItem(item, tpl) {
  const node = tpl.content.firstElementChild.cloneNode(true);
  const c = item.creator || {};

  const initials = item.__initials
    || ((fcFirstChar(c.name) + fcFirstChar(c.surname)).toUpperCase()
        || fcFirstChar(c.username).toUpperCase());

  const avatarRaw = c.avatar || '';
  const av = (window.LoreAvatar && LoreAvatar.parse(avatarRaw)) || { type: 'none' };

  const wrapInitials = node.querySelector('[data-fc-avatar-initials]');
  const wrapImg = node.querySelector('[data-fc-avatar-img]');
  const wrapEmoji = node.querySelector('[data-fc-avatar-emoji]');
  if (wrapInitials && wrapImg) {
    let used = wrapInitials;
    if (av.type === 'image') used = wrapImg;
    else if (av.type === 'emoji' && wrapEmoji) used = wrapEmoji;

    [wrapInitials, wrapImg, wrapEmoji].forEach((w) => { if (w && w !== used) w.remove(); });
    used.hidden = false;
    used.innerHTML = used.innerHTML
      .split('__INITIALS__').join(fcEsc(initials))
      .split('__EMOJI__').join(av.type === 'emoji' ? av.emoji : '')
      .split('__SRC__').join(fcEsc(av.type === 'image' ? encodeURI(av.src) : ''));
    const fallbackSpan = used.querySelector('.avatar span[hidden]');
    if (fallbackSpan) fallbackSpan.textContent = initials;
    if (window.LoreAvatar) LoreAvatar.paint(used.querySelector('.avatar'), av);
  }

  fcUsers.renderAuthor(node.querySelector('[data-fc-author]'), c.username || '', c.id, c.username);
  if (c.username) node.dataset.authorUsername = c.username;
  fcUsers.renderText(node.querySelector('[data-fc-text]'), item.content || '');
  node.querySelector('[data-fc-date]').textContent = fcFormatDate(item.createdAt);

  // Локализация кнопок в шаблоне (в PHP-шаблоне они на текущем языке,
  // но при клонировании из <template> внутренний HTML — тот, что был
  // при первой загрузке; на всякий случай переписываем из i18n).
  const likeBtn = node.querySelector('[data-like-btn]');
  if (likeBtn) {
    likeBtn.setAttribute('aria-label', t('comments.like'));
  }
  const replyBtn = node.querySelector('[data-comment-toggle], [data-reply-toggle]');
  if (replyBtn) {
    replyBtn.textContent = t('comments.reply');
    replyBtn.setAttribute('aria-label', t('comments.reply'));
  }

  if (item.id != null) {
    node.dataset.commentId = item.id;
    if (likeBtn) {
      likeBtn.dataset.likeId = String(item.id);
      const cnt = likeBtn.querySelector('[data-like-count]');
      if (cnt) cnt.textContent = String(item.likesCount || 0);
      setLikedUI(likeBtn, !!item.isLiked);
    }
  }

  return node;
}

async function fcLoad(card, reset = false) {
  const parts = fcParts(card);
  const tpl = document.getElementById('feed-comment-template');
  const postId = Number(card.dataset.postId);
  if (!parts || !tpl || !postId) return;

  const st = fcState(card);
  if (reset) {
    st.gen++;
    st.page = 0;
    st.hasNext = false;
    st.loading = false;
    parts.list.replaceChildren();
  }
  if (st.loading) return;

  const gen = st.gen;
  const page = st.page + 1;
  st.loading = true;
  parts.more.hidden = true;
  setFeedMsg(parts.status, t('js.loading_comments'));

  const params = new URLSearchParams({
    parent: String(postId),
    page: String(page),
    ps: String(FEED_COMMENTS.pageSize),
    include: 'creator',
  });
  const url = `${FEED_COMMENTS.url}?${params}`;

  const showError = (msg) => {
    if (window.Messages) {
      setFeedMsg(parts.status, '');
      window.Messages.show(msg, { type: 'error' });
    } else {
      setFeedMsg(parts.status, msg);
    }
    parts.more.textContent = t('js.try_again');
    parts.more.hidden = false;
  };

  let received = false;
  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    received = true;

    let data = null;
    try { data = await res.json(); } catch (_) { }

    if (gen !== st.gen) return;

    if (!res.ok || !data || !Array.isArray(data.items)) {
      return showError(sendStatus(data, res, (s) => t('js.comments_load_failed_http', { status: s })));
    }

    const bound = fcBoundToPost(data.items, postId, fcFeedPostIds());

    const existing = new Set();
    parts.list.querySelectorAll('[data-comment-id]').forEach((n) => {
      const id = Number(n.dataset.commentId);
      if (id) existing.add(id);
    });

    const frag = document.createDocumentFragment();
    let added = 0;
    bound.forEach((item) => {
      if (existing.has(Number(item.id))) return;
      frag.appendChild(fcBuildItem(item, tpl));
      added++;
    });
    if (added) parts.list.appendChild(frag);

    st.page = page;
    st.hasNext = !!(data.meta && data.meta.hasNext);
    parts.more.textContent = t('common.load_more');
    parts.more.hidden = !st.hasNext;

    if (page === 1) {
      if (parts.list.children.length > 0) {
        setFeedMsg(parts.status, '');
      } else {
        setFeedMsg(parts.status, data.items.length === 0
          ? t('js.no_comments_yet')
          : t('js.comments_unavailable'));
      }
    } else {
      setFeedMsg(parts.status, '');
    }
  } catch (err) {
    if (gen !== st.gen) return;
    console.error('[feed] failed to load comments:', err);
    showError(received ? t('js.comments_render_failed') : t('js.network_error'));
  } finally {
    if (gen === st.gen) st.loading = false;
  }
}

function fcSyncExpandBtn(card, open) {
  const btn = card.querySelector('[data-fc-expand]');
  if (!btn) return;
  btn.textContent = open ? t('js.show_less') : t('js.show_more');
  btn.setAttribute('aria-expanded', String(open));
}

function fcSetOpen(card, open) {
  const parts = fcParts(card);
  if (!parts) return;
  parts.root.hidden = !open;
  fcSyncExpandBtn(card, open);
  if (!open) return;

  const st = fcState(card);
  if (st.started) return;
  st.started = true;

  const count = parseInt(card.querySelector('[data-comment-count]')?.textContent, 10) || 0;
  if (count === 0) { setFeedMsg(parts.status, t('js.no_comments_yet')); return; }
  fcLoad(card);
}

function fcOwnItem(id, text) {
  return {
    id, content: text, likesCount: 0, createdAt: new Date().toISOString(),
    creator: { username: '', name: '', surname: '', avatar: '' }, __initials: '?',
  };
}

function fcAppendOwn(card, id, text) {
  const tpl = document.getElementById('feed-comment-template');
  const parts = fcParts(card);
  if (!tpl || !parts) return;

  const item = fcOwnItem(id, text);
  item.creator.username = card.dataset.cuName || '';
  item.creator.id = Number(card.dataset.cuId || 0);
  item.__initials = card.dataset.cuInitials || '?';

  const node = fcBuildItem(item, tpl);
  parts.list.insertBefore(node, parts.list.firstChild);

  fcState(card).started = true;
  parts.root.hidden = false;
  fcSyncExpandBtn(card, true);
  if (parts.status && parts.status.textContent === t('js.no_comments_yet')) {
    setFeedMsg(parts.status, '');
  }
  fcLoad(card);
}

document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-fc-expand]');
  if (!btn) return;
  const card = btn.closest('.card-feed');
  if (!card) return;
  const parts = fcParts(card);
  if (!parts) return;
  fcSetOpen(card, parts.root.hidden);
});

document.addEventListener('comment:created', (e) => {
  const card = e.target.closest && e.target.closest('.card-feed');
  if (!card) return;
  const detail = e.detail || {};
  if (!detail.id) return;
  fcAppendOwn(card, detail.id, detail.content || '');
});

const pubTypeCache = new Map();

function pubDetailsUrl(id, kind) {
  return (kind === 'article' ? '/articles/' : '/books/') + id;
}

document.addEventListener('click', async (e) => {
  const link = e.target.closest('[data-pub-link]');
  if (!link) return;
  if (e.defaultPrevented || e.button !== 0) return;
  if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  e.preventDefault();

  const id = Number(link.dataset.pubId);
  if (!id) return;

  let kind = pubTypeCache.get(id);
  if (!kind) {
    kind = 'book';
    try {
      const res = await fetch(pubDetailsUrl(id, 'book'), {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      if (res.status === 404) kind = 'article';
    } catch (_) { }
    pubTypeCache.set(id, kind);
  }
  location.href = pubDetailsUrl(id, kind);
});

/* ---------- Локализация статических кнопок при загрузке страницы ----------
   PHP-шаблон уже рендерит строки на текущем языке, но:
     * <template> содержит разметку, отрендеренную один раз, и при клонировании
       строки могут быть на старом языке, если что-то подгрузилось раньше;
     * «Show more» до первого клика приходит из PHP — но если по какой-то
       причине локализация не сработала (старый кеш страницы, hot-reload),
       JS перепишет его на актуальный.
   Проходим по всем [data-fc-expand], [data-comment-toggle], [data-reply-toggle]
   и ставим текст + aria-label из LoreI18n. Это идемпотентно. */
function fcLocalizeStaticButtons() {
  document.querySelectorAll('[data-fc-expand]').forEach((btn) => {
    const open = btn.getAttribute('aria-expanded') === 'true';
    btn.textContent = open ? t('js.show_less') : t('js.show_more');
  });

  document.querySelectorAll('[data-comment-toggle]').forEach((btn) => {
    btn.textContent = t('comments.reply');
    btn.setAttribute('aria-label', t('comments.reply'));
  });

  document.querySelectorAll('[data-reply-toggle]').forEach((btn) => {
    btn.textContent = t('comments.reply');
    btn.setAttribute('aria-label', t('comments.reply'));
  });

  document.querySelectorAll('[data-like-btn]').forEach((btn) => {
    btn.setAttribute('aria-label', t('comments.like'));
  });
}

document.addEventListener('DOMContentLoaded', () => {
  applyLikedState(document);
  fcLocalizeStaticButtons();
});