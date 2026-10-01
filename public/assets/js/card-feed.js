document.addEventListener('click', (e) => {
  // Лайк
  const likeBtn = e.target.closest('[data-like-btn]');
  if (likeBtn) {
    const countEl = likeBtn.querySelector('[data-like-count]');
    const liked = likeBtn.classList.toggle('is-liked');
    likeBtn.setAttribute('aria-pressed', liked);
    countEl.textContent = Math.max(0, parseInt(countEl.textContent, 10) + (liked ? 1 : -1));
    return;
  }

  // Показать/скрыть поле комментария
  const toggleBtn = e.target.closest('[data-comment-toggle]');
  if (toggleBtn) {
    const card = toggleBtn.closest('.card-feed');
    const form = card.querySelector('[data-comment-form]');
    const open = form.hidden;
    form.hidden = !open;
    toggleBtn.setAttribute('aria-expanded', open);
    if (open) form.querySelector('input').focus();
  }
});

// Кнопка Post активна, только если есть текст
document.addEventListener('input', (e) => {
  const input = e.target.closest('.comment-form__input');
  if (!input) return;
  input.closest('.comment-form').querySelector('.comment-form__submit').disabled =
    input.value.trim() === '';
});

// Отправка (пока только локально)
document.addEventListener('submit', (e) => {
  const form = e.target.closest('[data-comment-form]');
  if (!form) return;
  e.preventDefault();

  const input = form.querySelector('input');
  if (input.value.trim() === '') return;

  const countEl = form.closest('.card-feed').querySelector('[data-comment-count]');
  countEl.textContent = parseInt(countEl.textContent, 10) + 1;

  input.value = '';
  form.querySelector('.comment-form__submit').disabled = true;
});