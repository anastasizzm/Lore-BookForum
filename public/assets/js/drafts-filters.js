// ============================================================
// drafts-filters.js — черновики (/drafts)
// Только publish / delete. Список, фильтры, сортировка и «Показать ещё»
// ведёт общая модель в filters.js (window.LoreList, data-list="drafts").
// ============================================================
(() => {
  'use strict';

  const root = document.querySelector('[data-drafts]');
  if (!root) return;

  const API_BOOKS    = root.dataset.apiBooks    || '/api/books';
  const API_ARTICLES = root.dataset.apiArticles || '/api/articles';
  const grid         = root.querySelector('[data-drafts-grid]');
  const countEl      = root.querySelector('[data-drafts-count]');
  const emptyEl      = root.querySelector('[data-drafts-empty]');

  // ---------- helpers ----------
  const tr = (key, fallback) => {
    const v = window.LoreI18n ? window.LoreI18n.t(key) : key;
    return v && v !== key ? v : fallback;
  };

  const notify = (text, type) => {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  };

  function csrfToken() {
    const el = document.querySelector('[data-csrf] input[name="csrf_token"], [data-csrf]');
    if (!el) return '';
    const inp = el.querySelector ? el.querySelector('input[name="csrf_token"]') : null;
    if (inp) return inp.value;
    return el.value || el.textContent?.trim() || '';
  }

  function apiUrl(type, id, suffix = '') {
    const base = type === 'article' ? API_ARTICLES : API_BOOKS;
    return base + '/' + id + (suffix ? '/' + suffix : '');
  }

  // ---------- publish / delete ----------
  async function request(method, url) {
    const headers = { Accept: 'application/json' };
    const token = csrfToken();
    if (token) headers['X-CSRF-Token'] = token;
    return fetch(url, { method, headers, credentials: 'same-origin' });
  }

  function setBusy(card, busy) {
    if (!card) return;
    card.classList.toggle('is-busy', busy);
    card.querySelectorAll('button').forEach(b => b.disabled = busy);
  }

  function removeCard(card) {
    if (!card) return;
    card.remove();
    const left = grid ? grid.children.length : 0;
    if (countEl) countEl.textContent = String(left);
    if (grid && left === 0) {
      grid.hidden = true;
      if (emptyEl) emptyEl.hidden = false;
    }
  }

  root.addEventListener('click', async e => {
    const pub = e.target.closest('[data-draft-publish]');
    if (pub) {
      const card = pub.closest('[data-draft-id]');
      const type = pub.dataset.draftType || card?.dataset.draftType || 'book';
      const id   = Number(pub.dataset.draftId || card?.dataset.draftId || 0);
      if (!id) return;

      setBusy(card, true);
      try {
        const res = await request('POST', apiUrl(type, id, 'publish'));
        if (!res.ok) {
          notify(tr('errors.common.save_failed', 'Could not publish.'));
          return;
        }
        // Опубликованный черновик уходит со страницы /drafts
        removeCard(card);
        notify(tr('common.drafts.published', 'Published.'), 'success');
      } catch (err) {
        console.error('[drafts] publish failed', err);
        notify(tr('errors.common.network_error', 'Network error. Try again.'));
      } finally {
        setBusy(card, false);
      }
      return;
    }

    const del = e.target.closest('[data-draft-delete]');
    if (del) {
      const card = del.closest('[data-draft-id]');
      const type = del.dataset.draftType || card?.dataset.draftType || 'book';
      const id   = Number(del.dataset.draftId || card?.dataset.draftId || 0);
      if (!id) return;

      const confirmText = del.dataset.confirm || tr('common.drafts.delete_confirm', 'Delete this draft?');
      if (!window.confirm(confirmText)) return;

      setBusy(card, true);
      try {
        const res = await request('DELETE', apiUrl(type, id));
        if (!res.ok) {
          notify(tr('errors.common.save_failed', 'Could not delete.'));
          return;
        }
        removeCard(card);
        notify(tr('common.drafts.deleted', 'Deleted.'), 'success');
      } catch (err) {
        console.error('[drafts] delete failed', err);
        notify(tr('errors.common.network_error', 'Network error. Try again.'));
      } finally {
        setBusy(card, false);
      }
    }
  });

})();