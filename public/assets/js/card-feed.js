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