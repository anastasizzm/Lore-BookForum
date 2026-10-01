document.addEventListener('click', (e) => {
  // Лайк
  const likeBtn = e.target.closest('[data-like-btn]');
  if (likeBtn) {
    const countEl = likeBtn.querySelector('[data-like-count]');
    const liked = likeBtn.classList.toggle('is-liked');
    likeBtn.setAttribute('aria-pressed', liked);
    countEl.textContent = Math.max(0, parseInt(countEl.textContent, 10) + (liked ? 1 : -1));
    // TODO: POST/DELETE /api/posts/{postId}/like
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
    toggleBtn.setAttribute('aria-expanded', open);
    if (open) form.querySelector('.comment-form__input').focus();
    fcSetOpen(card, open);
  }
});

// Сообщения об ошибке/успехе скрываем при вводе; кнопка Post активна, только если есть текст
document.addEventListener('input', (e) => {
  const input = e.target.closest('.comment-form__input');
  if (!input) return;
  const form = input.closest('.comment-form');
  form.querySelector('.comment-form__submit').disabled = input.value.trim() === '';
  setFeedMsg(form.querySelector('[data-comment-error]'), '');
  setFeedMsg(form.querySelector('[data-comment-status]'), '');
});

function setFeedMsg(el, text) {
  if (!el) return;
  el.textContent = text;
  el.hidden = text === '';
}

// Отправка ответа: POST /api/posts/{postId}
document.addEventListener('submit', async (e) => {
  const form = e.target.closest('[data-feed-comment-form]');
  if (!form) return;
  e.preventDefault();
  if (form.dataset.sending === '1') return;

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

  const body = new URLSearchParams(new FormData(form));
  body.set('content', text);

  form.dataset.sending = '1';
  input.disabled = true;
  submitBtn.disabled = true;

  try {
    const res = await fetch(form.getAttribute('action'), {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body,
    });

    let data = null;
    try { data = await res.json(); } catch (_) { /* не JSON */ }
    console.log('POST', form.getAttribute('action'), res.status, data); // для отладки бэка

    if (res.status === 201) {
      setFeedMsg(statusEl, 'Comment sent' + (data && data.createdId ? ` (id ${data.createdId})` : ''));
      input.value = '';

      const countEl = form.closest('.card-feed').querySelector('[data-comment-count]');
      if (countEl) countEl.textContent = (parseInt(countEl.textContent, 10) || 0) + 1;

      // Хук для будущей вставки комментария в список
      form.dispatchEvent(new CustomEvent('comment:created', {
        bubbles: true,
        detail: { id: data && data.createdId, parentId: Number(form.dataset.postId), content: text },
      }));
    } else {
      let msg = '';
      if (data && data.errors) msg = Object.values(data.errors).flat().join('\n');
      if (!msg && data && data.message) msg = data.message;
      if (!msg) {
        msg = res.status === 403 ? 'Forbidden (verify email / CSRF?)'
            : res.status === 401 ? 'Please sign in again'
            : `Failed to send comment (HTTP ${res.status})`;
      }
      setFeedMsg(errEl, msg);
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

/* ============================================
   Ленивая подгрузка комментариев в ленте
   GET /api/posts?parent={postId}&page=N&pageSize=M&props=creator
   Ответ: { items: [...], meta: { page, pageSize, hasNext } }
   Первая загрузка — при первом раскрытии карточки (иконка комментария),
   дальше — кнопка Load more. После отправки своего комментария
   (событие comment:created) список перезагружается с первой страницы.
   ============================================ */
const FEED_COMMENTS = {
  url: '/api/posts',
  pageSize: 10,
  pageParam: 'page',
  pageSizeParam: 'pageSize',
  propsParam: 'props',     // имя параметра смотри в PropertiesQuery::fromInput
  propsValue: 'creator',   // чтобы в ответе пришёл автор комментария
  avatarsDir: '/uploads/avatars/',
};

const feedCommentsState = new WeakMap(); // card -> { page, hasNext, loading, gen, started }

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

function fcEsc(value) {
  return String(value)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

const fcFirstChar = (s) => Array.from(s || '')[0] || '';

// createdAt приходит либо строкой, либо объектом {date: "..."} (так сериализуется DateTimeImmutable)
function fcFormatDate(value) {
  const raw = value && typeof value === 'object' ? value.date : value;
  const m = /(\d{4})-(\d{2})-(\d{2})/.exec(String(raw || ''));
  return m ? `${m[3]}.${m[2]}.${m[1]}` : '';
}

function fcBuildItem(item, tpl) {
  const node = tpl.content.firstElementChild.cloneNode(true);
  const c = item.creator || {};

  const initials = (fcFirstChar(c.name) + fcFirstChar(c.surname)).toUpperCase()
    || fcFirstChar(c.username).toUpperCase();
  const avatarRaw = c.avatar || '';
  const src = avatarRaw && avatarRaw !== 'default' ? FEED_COMMENTS.avatarsDir + avatarRaw : null;

  const wrapInitials = node.querySelector('[data-fc-avatar-initials]');
  const wrapImg = node.querySelector('[data-fc-avatar-img]');
  if (wrapInitials && wrapImg) {
    const used = src ? wrapImg : wrapInitials;
    (src ? wrapInitials : wrapImg).remove();
    used.hidden = false;
    used.innerHTML = used.innerHTML
      .split('__INITIALS__').join(fcEsc(initials))
      .split('__SRC__').join(fcEsc(src ? encodeURI(src) : ''));
  }

  // textContent: без XSS
  node.querySelector('[data-fc-author]').textContent = c.username || '';
  node.querySelector('[data-fc-date]').textContent = fcFormatDate(item.createdAt);
  node.querySelector('[data-fc-text]').textContent = item.content || '';
  if (item.id != null) node.dataset.commentId = item.id;
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
    [FEED_COMMENTS.pageParam]: String(page),
    [FEED_COMMENTS.pageSizeParam]: String(FEED_COMMENTS.pageSize),
    [FEED_COMMENTS.propsParam]: FEED_COMMENTS.propsValue,
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
    console.log('GET', url, res.status, data); // для отладки бэка

    if (gen !== st.gen) return; // пришёл устаревший ответ

    if (!res.ok || !data || !Array.isArray(data.items)) {
      let msg = '';
      if (data && data.errors) msg = Object.values(data.errors).flat().join('\n');
      if (!msg && data && data.message) msg = data.message;
      if (!msg) {
        msg = res.status === 401 ? 'Please sign in again'
            : `Failed to load comments (HTTP ${res.status})`;
      }
      return showError(msg);
    }

    const frag = document.createDocumentFragment();
    data.items.forEach((item) => frag.appendChild(fcBuildItem(item, tpl)));
    parts.list.appendChild(frag);

    st.page = page;
    st.hasNext = !!(data.meta && data.meta.hasNext);
    setFeedMsg(parts.status, page === 1 && data.items.length === 0 ? 'No comments yet.' : '');
    parts.more.textContent = 'Load more';
    parts.more.hidden = !st.hasNext;
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

// Load more / Try again
document.addEventListener('click', (e) => {
  const more = e.target.closest('[data-fc-more]');
  if (!more) return;
  const card = more.closest('.card-feed');
  if (card) fcLoad(card);
});

// Свой комментарий отправлен — перезагружаем список с первой страницы
// (на сервере порядок created_at DESC, новый окажется сверху)
document.addEventListener('comment:created', (e) => {
  const card = e.target.closest && e.target.closest('.card-feed');
  if (!card) return;
  const parts = fcParts(card);
  if (!parts) return;
  parts.root.hidden = false;
  fcState(card).started = true;
  fcLoad(card, true);
});