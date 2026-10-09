/* ============================================
   PROFILE - lazy load publications (sidebar)
   ============================================ */

(function () {
  'use strict';

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function extractItems(data) {
    if (!data) return [];
    if (Array.isArray(data)) return data;
    if (Array.isArray(data.items)) return data.items;
    return [];
  }

  function formatYear(value) {
    if (!value) return '';
    if (typeof value === 'object' && value.date) return String(value.date).slice(0, 4);
    return String(value).slice(0, 4);
  }

  async function fetchAll(userId) {
    var include = 'creator';

    var responses = await Promise.all([
      fetch('/api/books?creator=' + encodeURIComponent(userId) + '&include=' + include, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(function (r) {
        if (r.ok) return r.json();
        if (window.Messages) window.Messages.fail(r, 'Could not load publications.');
        return null;
      }).catch(function () {
        if (window.Messages) window.Messages.show('Network error. Try again.', { type: 'error' });
        return null;
      }),

      fetch('/api/articles?creator=' + encodeURIComponent(userId) + '&include=' + include, {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      }).then(function (r) {
        if (r.ok) return r.json();
        if (window.Messages) window.Messages.fail(r, 'Could not load publications.');
        return null;
      }).catch(function () {
        if (window.Messages) window.Messages.show('Network error. Try again.', { type: 'error' });
        return null;
      }),
    ]);

    var books    = extractItems(responses[0]).map(function (i) { return { item: i, type: 'book' }; });
    var articles = extractItems(responses[1]).map(function (i) { return { item: i, type: 'article' }; });

    var all = books.concat(articles);

    all.sort(function (a, b) {
      var da = a.item.createdAt && a.item.createdAt.date ? a.item.createdAt.date : '';
      var db = b.item.createdAt && b.item.createdAt.date ? b.item.createdAt.date : '';
      return db.localeCompare(da);
    });

    return all;
  }

  function renderItem(item, type) {
    var url = type === 'book'
      ? '/books/' + encodeURIComponent(item.id)
      : '/articles/' + encodeURIComponent(item.id);
    var label = type === 'book' ? 'Book' : 'Article';
    var year  = formatYear(item.createdAt);

    return '' +
      '<a class="profile-publications__item" href="' + url + '">' +
        '<span class="profile-publications__cover cover--empty" aria-hidden="true">' +
          // обложки в API нет — рисуем CSS-заглушку (.cover--empty, component.css)
        '</span>' +
        '<span class="profile-publications__info">' +
          '<span class="profile-publications__title">' + escapeHtml(item.title) + '</span>' +
          '<span class="profile-publications__meta">' + label + (year ? ' - ' + year : '') + '</span>' +
        '</span>' +
      '</a>';
  }

  async function loadSidebar(el) {
    var userId  = el.getAttribute('data-publications-user-id');
    var limit   = parseInt(el.getAttribute('data-publications-limit') || '7', 10);
    var moreUrl = el.getAttribute('data-publications-more-url') || '';

    el.innerHTML = '<p class="profile-sidebar-box__text profile-sidebar-box__text--muted">Loading...</p>';

    if (!userId || userId === '0') {
      el.innerHTML = '<p class="profile-sidebar-box__text profile-sidebar-box__text--muted">No publications yet.</p>';
      return;
    }

    var all;
    try {
      all = await fetchAll(userId);
    } catch (e) {
      console.error('[profile] publications load failed:', e);
      el.innerHTML = '<p class="profile-sidebar-box__text profile-sidebar-box__text--muted">Failed to load.</p>';
      return;
    }

    if (all.length === 0) {
      el.innerHTML = '<p class="profile-sidebar-box__text profile-sidebar-box__text--muted">No publications yet.</p>';
      return;
    }

    var shown = all.slice(0, limit);
    el.innerHTML = shown.map(function (entry) {
      return renderItem(entry.item, entry.type);
    }).join('');

            if (moreUrl && all.length > 0) {
      var more = document.createElement('a');
      more.className = 'profile-publications__more';
      more.href = moreUrl;
      more.textContent = 'See all publications';
      el.appendChild(more);
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('[data-publications][data-publications-user-id]');
    if (el) loadSidebar(el);
  });
})();