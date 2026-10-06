/* ============================================
   Лента: лайки, отправка и отображение комментариев.
   Комментарии всегда привязываются к конкретному postId:
   каждый хранит свой список отдельно (feedCommentsState),
   а ответ сервера фильтруется, чтобы в чужую карточку
   не попали пост/чужие комментарии.
   ============================================ */

/* ---------- CSRF ---------- */
function csrfToken() {
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
  el.textContent = text;
  el.hidden = text === '';
}

/* ---------- Лайки ---------- */
const FEED_LIKED_KEY = 'feed:liked-ids';

function likedIds() {
  try {
    return new Set(JSON.parse(localStorage.getItem(FEED_LIKED_KEY) || '[]'));
  } catch (_) {
    return new Set();
  }
}

function saveLikedIds(set) {
  try { localStorage.setItem(FEED_LIKED_KEY, JSON.stringify(Array.from(set))); } catch (_) {}
}

function likeIdOf(btn) {
  if (btn.dataset.likeId) return Number(btn.dataset.likeId);
  const card = btn.closest('.card-feed');
  return card ? Number(card.dataset.postId) : 0;
}

function setLikedUI(btn, liked) {
  btn.classList.toggle('is-liked', liked);
  btn.setAttribute('aria-pressed', String(liked));
}

// Сердечко должно оставаться залитым после перезагрузки.
// API пока не отдаёт признак «мой лайк», поэтому состояние храним локально.
function applyLikedState(root) {
  const ids = likedIds();
  (root || document).querySelectorAll('[data-like-btn]').forEach((btn) => {
    const id = likeIdOf(btn);
    if (id && ids.has(id)) setLikedUI(btn, true);
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
    return res.ok || res.status === 201 || res.status === 204;
  } catch (_) {
    return false;
  }
}

document.addEventListener('click', (e) => {
  // Лайк (пост или комментарий)
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

    const ids = likedIds();
    if (liked) ids.add(id); else ids.delete(id);
    saveLikedIds(ids);

    sendLike(id, liked).then((ok) => {
      if (ok) return;
      // Откат: сервер не принял — возвращаем исходное состояние
      setLikedUI(likeBtn, !liked);
      if (countEl) countEl.textContent = String(prev);
      const fresh = likedIds();
      if (liked) fresh.delete(id); else fresh.add(id);
      saveLikedIds(fresh);
    });
    return;
  }

  // Показать/скрыть поле комментария
  const toggleBtn = e.target.closest('[data-comment-toggle]');
  if (toggleBtn) {
    const card = toggleBtn.closest('.card-feed');
    const form = card.querySelector('[data-feed-comment-form]');
    if (!form) return;
    const open = form.hidden;
    form.hidden = !open;
    toggleBtn.setAttribute('aria-expanded', String(open));
    if (open) form.querySelector('.comment-form__input').focus();
    fcSetOpen(card, open);
    return;
  }

  // Ответ на конкретный комментарий — открываем/закрываем его форму
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
      if (input) input.focus();
    }
  }
});

// Сообщения об ошибке/успехе скрываем при вводе; кнопка активна, только если есть текст
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
  let msg = '';
  if (data && data.errors) msg = Object.values(data.errors).flat().join('\n');
  if (!msg && data && data.message) msg = data.message;
  if (!msg) {
    msg = res.status === 403 ? 'Forbidden (verify email / CSRF?)'
        : res.status === 401 ? 'Please sign in again'
        : fallback(res.status);
  }
  return msg;
}

/* ---------- Отправка комментария к посту ---------- */
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

  const text = input.value.trim();
  if (text === '') return;
  if (text.length > MAX) return setFeedMsg(errEl, `Max length is ${MAX} characters`);
  if (!Number(form.dataset.postId)) return setFeedMsg(errEl, 'postId is missing');
  if (!Number(form.elements.publicationId.value)) return setFeedMsg(errEl, 'publicationId is missing');
  // Защита от дублей: тот же текст второй раз подряд не отправляем
  if (form.dataset.lastSent === text) return setFeedMsg(errEl, 'You have already sent this comment.');

  const body = new URLSearchParams(new FormData(form));
  body.set('content', text);

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
    try { data = await res.json(); } catch (_) { /* не JSON */ }

    if (res.status === 201) {
      form.dataset.lastSent = text;
      setFeedMsg(statusEl, 'Comment sent');
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
      setFeedMsg(errEl, sendStatus(data, res, (s) => `Failed to send comment (HTTP ${s})`));
    }
  } catch (err) {
    setFeedMsg(errEl, 'Network error. Try again.');
  } finally {
    form.dataset.sending = '0';
    input.disabled = false;
    submitBtn.disabled = input.value.trim() === '';
    input.focus();
  }
});

/* ---------- Ответ на комментарий: POST /api/posts/{commentId} ---------- */
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

  const text = input.value.trim();
  if (text === '') return;
  if (!commentId) return setFeedMsg(errEl, 'commentId is missing');
  if (form.dataset.lastSent === text) return setFeedMsg(errEl, 'You have already sent this comment.');

  // publicationId берём из основной формы карточки — у комментариев он общий с постом
  const mainForm = card ? card.querySelector('[data-feed-comment-form]') : null;
  const publicationId = mainForm && mainForm.elements.publicationId
      ? mainForm.elements.publicationId.value : '';
  if (!Number(publicationId)) return setFeedMsg(errEl, 'publicationId is missing');

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
    try { data = await res.json(); } catch (_) { /* не JSON */ }

    if (res.status === 201) {
      form.dataset.lastSent = text;
      setFeedMsg(statusEl, 'Reply sent');
      input.value = '';
      form.hidden = true;

      // Ответ привязан к этому же посту — вставляем сразу в его список
      if (card && data && data.createdId) {
        fcAppendOwn(card, data.createdId, text);
      }
    } else {
      setFeedMsg(errEl, sendStatus(data, res, (s) => `Failed to send reply (HTTP ${s})`));
    }
  } catch (err) {
    setFeedMsg(errEl, 'Network error. Try again.');
  } finally {
    form.dataset.sending = '0';
    input.disabled = false;
    submitBtn.disabled = input.value.trim() === '';
  }
});

/* ============================================
   Ленивая подгрузка комментариев
   GET /api/posts?parent={postId}&page=N&ps=M&include=creator
   ============================================ */
const FEED_COMMENTS = {
  url: '/api/posts',
  pageSize: 10,
};

const feedCommentsState = new WeakMap(); // card -> {page, hasNext, loading, gen, started}

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

// createdAt приходит либо строкой, либо объектом {date: "..."} (DateTimeImmutable)
function fcFormatDate(value) {
  const raw = value && typeof value === 'object' ? value.date : value;
  const m = /(\d{4})-(\d{2})-(\d{2})/.exec(String(raw || ''));
  return m ? `${m[3]}.${m[2]}.${m[1]}` : '';
}

/* ---------- Привязка к посту ---------- */
// Идентификаторы постов ленты — чтобы не выдать их как комментарии к другому посту
function fcFeedPostIds() {
  const ids = new Set();
  document.querySelectorAll('.card-feed[data-post-id]').forEach((el) => {
    const id = Number(el.dataset.postId);
    if (id) ids.add(id);
  });
  return ids;
}

// Оставляем только то, что реально принадлежит этому посту
function fcBoundToPost(items, postId, feedIds) {
  return items.filter((it) => {
    const id = Number(it.id);
    if (!id) return false;
    if (id === postId) return false;     // сам пост — не комментарий к нему
    if (feedIds.has(id)) return false;   // другой пост ленты
    return true;
  });
}

function fcBuildItem(item, tpl) {
  const node = tpl.content.firstElementChild.cloneNode(true);
  const c = item.creator || {};

  // Аватар: эмодзи пресета или инициалы (см. avatar.js).
  // item.__initials задан только для своего комментария.
  Avatar.fill(node.querySelector('.avatar'), c, item.__initials);

  // textContent: без XSS
  node.querySelector('[data-fc-author]').textContent = c.username || '';
  node.querySelector('[data-fc-date]').textContent = fcFormatDate(item.createdAt);
  node.querySelector('[data-fc-text]').textContent = item.content || '';

  if (item.id != null) {
    node.dataset.commentId = item.id;
    const likeBtn = node.querySelector('[data-like-btn]');
    if (likeBtn) {
      likeBtn.dataset.likeId = String(item.id);
      const cnt = likeBtn.querySelector('[data-like-count]');
      if (cnt) cnt.textContent = String(item.likesCount || 0);
    }
  }

  applyLikedState(node);
  return node;
}

async function fcLoad(card, reset = false) {
  const parts = fcParts(card);
  const tpl = document.getElementById('feed-comment-template');
  const postId = Number(card.dataset.postId);
  if (!parts || !tpl || !postId) return;

  const st = fcState(card);
  if (reset) {
    st.gen++;               // ответы на старые запросы будут отброшены
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
  setFeedMsg(parts.status, 'Loading comments…');

  const params = new URLSearchParams({
    parent: String(postId),
    page: String(page),
    ps: String(FEED_COMMENTS.pageSize),
    include: 'creator',
  });
  const url = `${FEED_COMMENTS.url}?${params}`;

  const showError = (msg) => {
    setFeedMsg(parts.status, msg);
    parts.more.textContent = 'Try again';
    parts.more.hidden = false;
  };

  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    let data = null;
    try { data = await res.json(); } catch (_) { /* не JSON */ }

    if (gen !== st.gen) return; // пришёл устаревший ответ

    if (!res.ok || !data || !Array.isArray(data.items)) {
      return showError(sendStatus(data, res, (s) => `Failed to load comments (HTTP ${s})`));
    }

    const bound = fcBoundToPost(data.items, postId, fcFeedPostIds());

    // Не дублируем: своё сообщение уже вставлено оптимистично
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
    parts.more.textContent = 'Load more';
    parts.more.hidden = !st.hasNext;

    if (page === 1) {
      if (parts.list.children.length > 0) {
        setFeedMsg(parts.status, '');
      } else {
        // Либо сервер ничего не отдал, либо отдал посты ленты вместо комментариев
        setFeedMsg(parts.status, data.items.length === 0
          ? 'No comments yet.'
          : 'Comments are unavailable right now.');
      }
    } else {
      setFeedMsg(parts.status, '');
    }
  } catch (err) {
    if (gen !== st.gen) return;
    showError('Network error. Try again.');
  } finally {
    if (gen === st.gen) st.loading = false;
  }
}

// Показать/скрыть список вместе с формой; первая загрузка — при первом открытии
function fcSetOpen(card, open) {
  const parts = fcParts(card);
  if (!parts) return;
  parts.root.hidden = !open;
  if (!open) return;

  const st = fcState(card);
  if (st.started) return;
  st.started = true;

  const count = parseInt(card.querySelector('[data-comment-count]')?.textContent, 10) || 0;
  if (count === 0) {
    setFeedMsg(parts.status, 'No comments yet.'); // зря в сеть не ходим
    return;
  }
  fcLoad(card);
}

// Свой комментарий/ответ — показываем сразу, он привязан к этому посту по построению
function fcOwnItem(id, text) {
  return {
    id,
    content: text,
    likesCount: 0,
    createdAt: new Date().toISOString(),
    creator: { username: '', name: '', surname: '', avatar: '' },
    __initials: '?',
  };
}

function fcAppendOwn(card, id, text) {
  const tpl = document.getElementById('feed-comment-template');
  const parts = fcParts(card);
  if (!tpl || !parts) return;

  const item = fcOwnItem(id, text);
  item.creator.username = card.dataset.cuName || '';
  item.creator.avatar = card.dataset.cuAvatar || '';
  item.__initials = card.dataset.cuInitials || '?';

  const node = fcBuildItem(item, tpl);
  parts.list.insertBefore(node, parts.list.firstChild);

  fcState(card).started = true;
  parts.root.hidden = false;
  if (parts.status && parts.status.textContent === 'No comments yet.') {
    setFeedMsg(parts.status, '');
  }

  // Синхронизируемся с сервером: когда API начнёт отдавать
  // комментарии по parent, они подтянутся (дубликаты отсечёт fcLoad)
  fcLoad(card);
}

// Load more / Try again
document.addEventListener('click', (e) => {
  const more = e.target.closest('[data-fc-more]');
  if (!more) return;
  const card = more.closest('.card-feed');
  if (card) fcLoad(card);
});

// Свой комментарий отправлен — вставляем его в список этого поста
// (сервер отдаёт список лениво и порядок created_at DESC, новый окажется сверху)
document.addEventListener('comment:created', (e) => {
  const card = e.target.closest && e.target.closest('.card-feed');
  if (!card) return;
  const detail = e.detail || {};
  if (!detail.id) return;
  fcAppendOwn(card, detail.id, detail.content || '');
});

document.addEventListener('DOMContentLoaded', () => applyLikedState(document));