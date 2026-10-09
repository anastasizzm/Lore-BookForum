/* ============================================
   Лента: лайки, отправка и отображение комментариев.
   Комментарии всегда привязываются к конкретному postId:
   каждый хранит свой список отдельно (feedCommentsState),
   а ответ сервера фильтруется, чтобы в чужую карточку
   не попали пост/чужие комментарии.
   ============================================ */

/* ---------- CSRF ---------- */
// Общая структура комментариев (карточка, ответы, «View N more replies» /
// «Show less», пагинация «Show more/less comments») — comments.js:
// та же разметка и логика, что на book/article details
const fcComments = window.LoreComments || {};

function csrfToken() {
  // cookie — источник правды (её сравнивает бэк); поле формы — запасной вариант
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

  // Ошибки отправки и «Comment sent» показываем общими плашками (messages.js),
  // а не красным текстом в форме
  const isError  = el.hasAttribute('data-comment-error') || el.hasAttribute('data-reply-error');
  const isStatus = el.hasAttribute('data-comment-status');
  if ((isError || isStatus) && window.Messages) {
    el.textContent = '';
    el.hidden = true;
    if (text) window.Messages.show(text, { type: isError ? 'error' : 'success' });
    return;
  }

  // состояние списка («Loading comments…», «No comments yet.») остаётся на месте
  el.textContent = text;
  el.hidden = text === '';
}

// Плашка с ошибкой (общий механизм — messages.js)
function notify(text) {
  if (window.Messages) window.Messages.show(text, { type: 'error' });
  else console.warn(text);
}

/* ---------- Лайки ---------- */
// Состояние «мой лайк» приходит с сервера:
//   * в разметке — data-liked из PostContext.isLiked (feed-list.php / card-feed.php);
//   * в ответе GET /api/posts — поле isLiked (book.js, card-feed.js).
// Локальный storage больше не используется: он расходится с БД после F5
// и на другом устройстве.

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

// Рисуем по data-liked (серверный признак). Кнопки без data-liked не трогаем.
function applyLikedState(root) {
  (root || document).querySelectorAll('[data-like-btn]').forEach((btn) => {
    if (btn.dataset.liked === undefined) return;
    setLikedUI(btn, btn.dataset.liked === '1');
  });
}

// Возвращает '' при успехе, иначе текст ошибки (его покажет плашка).
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
        ? await window.Messages.readError(res, 'Could not update the like.')
        : `Could not update the like (HTTP ${res.status}).`;
    }

    // 2xx, но вместо JSON пришёл HTML (PHP-ошибка) — это не успех
    const raw = await res.text();
    if (/^\s*</.test(raw)) return 'Server error. The like was not saved.';
    return '';
  } catch (_) {
    return 'Network error. Try again.';
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

    sendLike(id, liked).then((err) => {
      if (!err) return;
      notify(err);
      // Откат: сервер не принял — возвращаем исходное состояние
      setLikedUI(likeBtn, !liked);
      if (countEl) countEl.textContent = String(prev);
    });
    return;
  }

  // Показать/скрыть встроенный ввод поста («Reply» в строке поста)
  const toggleBtn = e.target.closest('[data-comment-toggle]');
  if (toggleBtn) {
    const card = toggleBtn.closest('.card-feed');
    const form = card.querySelector('[data-feed-comment-form]');
    if (!form) return;
    const open = form.hidden;
    form.hidden = !open;
    toggleBtn.setAttribute('aria-expanded', String(open));
    if (open) form.querySelector('.comment-form__input').focus();
    return;
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
  // Текст ошибки берём из ответа API ({ error: { message, details } })
  const msg = window.Messages ? window.Messages.fromPayload(data) : '';
  return msg || fallback(res.status);
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
    console.error('[feed] comment request failed:', err);
    setFeedMsg(errEl, 'Network error. Try again.');
  } finally {
    form.dataset.sending = '0';
    input.disabled = false;
    submitBtn.disabled = input.value.trim() === '';
    input.focus();
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

const feedCommentsState = new WeakMap(); // card -> {loaded, hasMore, loading, gen, started}

function fcParts(card) {
  const root = card.querySelector('[data-feed-comments]');
  if (!root) return null;
  return {
    root,
    list: root.querySelector('[data-fc-list]'),
    status: root.querySelector('[data-fc-status]'),
  };
}

function fcState(card) {
  let st = feedCommentsState.get(card);
  if (!st) {
    // hasMore[N] — есть ли страница N+1; loaded — сколько страниц показано
    st = { loaded: 0, hasMore: [], loading: false, gen: 0, started: false };
    feedCommentsState.set(card, st);
  }
  return st;
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

// Карточка комментария — та же, что на book/article details (comments.js):
// ник без «@», лайк справа по центру, под текстом дата и Reply,
// ответы — за кнопкой «View N more replies» / «Show less»
function fcCommentNode(item) {
  return fcComments.commentNode({
    id: item.id,
    author: (item.creator && (item.creator.username || item.creator.name)) || '',
    authorId: item.creator && item.creator.id,
    authorUsername: (item.creator && item.creator.username) || '',
    initials: fcComments.initialsOf(item.creator),
    avatar: fcComments.avatarOf(item.creator),
    text: item.content || '',
    date: fcComments.formatDate(item.createdAt),
    likes: item.likesCount || 0,
    liked: !!item.isLiked,
    replies: Number(item.commentsCount) || 0,
  });
}

/**
 * GET /api/posts?parent={postId}&page=N — корневые комментарии поста.
 * Страницы подгружает «Show more comments» (более старая встаёт ВЫШЕ
 * показанной — хронология), «Show less comments» подгруженное убирает.
 */
async function fcLoad(card) {
  const parts = fcParts(card);
  const postId = Number(card && card.dataset.postId);
  if (!parts || !postId) return;

  const st = fcState(card);
  if (st.loading) return;

  const gen = st.gen;
  const page = st.loaded + 1;
  const pagerOpts = { load: () => fcLoad(card) };
  st.loading = true;
  fcComments.syncPager(parts.list, st, pagerOpts);
  if (page === 1) setFeedMsg(parts.status, 'Loading comments…');

  const params = new URLSearchParams({
    parent: String(postId),
    page: String(page),
    ps: String(FEED_COMMENTS.pageSize),
    include: 'creator',
  });
  const url = `${FEED_COMMENTS.url}?${params}`;

  const fail = (msg) => {
    if (gen !== st.gen) return;
    st.loading = false;
    setFeedMsg(parts.status, msg);
    // Кнопка остаётся — повторит неудачную загрузку
    fcComments.syncPager(parts.list, st, pagerOpts);
  };

  let received = false; // true, когда ответ сервера получен: дальше ошибка уже не сетевая
  try {
    const res = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });
    received = true;

    let data = null;
    try { data = await res.json(); } catch (_) { /* не JSON */ }

    if (gen !== st.gen) return; // пришёл устаревший ответ

    if (!res.ok || !data || !Array.isArray(data.items)) {
      return fail(sendStatus(data, res, (s) => `Failed to load comments (HTTP ${s})`));
    }

    const bound = fcBoundToPost(data.items, postId, fcFeedPostIds());

    // Не дублируем: своё сообщение уже вставлено оптимистично
    const existing = new Set();
    parts.list.querySelectorAll('[data-comment-id]').forEach((n) => {
      const id = Number(n.dataset.commentId);
      if (id) existing.add(id);
    });

    const frag = document.createDocumentFragment();
    const withReplies = [];
    // API отдаёт created_at DESC, а читается в хронологии — разворачиваем
    bound.slice().reverse().forEach((item) => {
      if (existing.has(Number(item.id))) return;
      const node = fcCommentNode(item);
      if (!node) return;
      node.dataset.page = String(page);   // «Show less comments» снимает страницы
      frag.appendChild(node);
      if (Number(item.commentsCount) > 0) withReplies.push(node);
    });
    fcComments.insertPage(parts.list, frag, page);

    // Ответы комментариев подтягиваем сразу (GET ?parent={commentId}),
    // иначе после F5 они пропадут
    withReplies.forEach((node) => fcComments.loadReplies(node));

    st.hasMore[page] = !!(data.meta && data.meta.hasNext);
    st.loaded = page;
    st.loading = false;
    fcComments.syncPager(parts.list, st, pagerOpts);

    if (page === 1 && parts.list.children.length === 0) {
      // Либо сервер ничего не отдал, либо отдал посты ленты вместо комментариев
      setFeedMsg(parts.status, data.items.length === 0
        ? 'No comments yet.'
        : 'Comments are unavailable right now.');
    } else {
      setFeedMsg(parts.status, '');
    }
  } catch (err) {
    if (gen !== st.gen) return;
    console.error('[feed] failed to load comments:', err);
    fail(received ? 'Could not display the comments.' : 'Network error. Try again.');
  } finally {
    if (gen === st.gen) st.loading = false;
  }
}

// Ссылка «Show more/Show less» под постом — зеркало состояния списка
function fcSyncExpandBtn(card, open) {
  const btn = card.querySelector('[data-fc-expand]');
  if (!btn) return;
  btn.textContent = open ? 'Show less' : 'Show more';
  btn.setAttribute('aria-expanded', String(open));
}

// Показать/скрыть список; первая загрузка — при первом раскрытии
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
  if (count === 0) {
    setFeedMsg(parts.status, 'No comments yet.'); // зря в сеть не ходим
    return;
  }
  fcLoad(card);
}

// Свой комментарий отправлен — вставляем в КОНЕЦ списка этого поста
// (хронология: старые сверху, новые снизу)
function fcAppendOwn(card, id, text) {
  const parts = fcParts(card);
  if (!parts) return;

  const node = fcComments.commentNode({
    id,
    author: card.dataset.cuName || '',
    authorId: Number(card.dataset.cuId || 0),
    authorUsername: card.dataset.cuName || '',
    initials: card.dataset.cuInitials || '?',
    avatar: card.dataset.cuAvatar || '',
    text,
    date: fcComments.formatDate(new Date().toISOString()),   // dd.mm.yyyy
    likes: 0,
    liked: false,
    replies: 0,
  });
  if (!node) return;

  parts.list.appendChild(node);

  fcState(card).started = true;
  parts.root.hidden = false;
  fcSyncExpandBtn(card, true);   // ссылка под постом: «Show less»
  if (parts.status && parts.status.textContent === 'No comments yet.') {
    setFeedMsg(parts.status, '');
  }

  // Первой загрузки ещё не было — подтягиваем список из БД; дубликат
  // со своим id отсекут existing/insertPage в fcLoad
  if (fcState(card).loaded === 0) fcLoad(card);
}

// «Show more» / «Show less» — раскрыть/свернуть список комментариев под постом
// (маленькая серая ссылка под строкой поста вместо пилюли внизу блока)
document.addEventListener('click', (e) => {
  const btn = e.target.closest('[data-fc-expand]');
  if (!btn) return;
  const card = btn.closest('.card-feed');
  if (!card) return;
  const parts = fcParts(card);
  if (!parts) return;

  fcSetOpen(card, parts.root.hidden);
});

// Свой комментарий отправлен — вставляем в конец списка этого поста
// (хронология: старые сверху, новые снизу)
document.addEventListener('comment:created', (e) => {
  const card = e.target.closest && e.target.closest('.card-feed');
  if (!card) return;
  const detail = e.detail || {};
  if (!detail.id) return;
  fcAppendOwn(card, detail.id, detail.content || '');
});

document.addEventListener('DOMContentLoaded', () => applyLikedState(document));