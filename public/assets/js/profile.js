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

/* ============================================
   PROFILE - posts (ответы пользователя под публикациями)
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

  // createdAt = { date: "2026-10-10 07:57:35...", ... } -> "10.10.2026"
  function formatDate(value) {
    var raw = (value && typeof value === 'object') ? (value.date || '') : String(value || '');
    var day = raw.split(' ')[0];
    var parts = day.split('-');
    return parts.length === 3 ? parts[2] + '.' + parts[1] + '.' + parts[0] : '';
  }

  function emptyText(text) {
    return '<p class="profile-sidebar-box__text profile-sidebar-box__text--muted">' +
      escapeHtml(text) + '</p>';
  }

  function renderPost(post) {
    var pub       = post.publication || {};
    var pubId     = Number(post.publicationId || pub.id || 0);
    var pubTitle  = pub.title || 'Publication';
    var content   = String(post.content || '').trim();
    var date      = formatDate(post.createdAt);
    var likes     = Number(post.likesCount || 0);
    var comments  = Number(post.commentsCount || 0);

    // Тип публикации (book/article) в API не приходит — ссылку резолвит
    // общий обработчик card-feed.js ([data-pub-link], см. «/books/{id} -> 404 -> /articles/{id}»)
    var title = pubId
      ? '<a class="profile-post__title" href="/books/' + pubId + '"' +
        ' data-pub-link data-pub-id="' + pubId + '">' + escapeHtml(pubTitle) + '</a>'
      : '<span class="profile-post__title">' + escapeHtml(pubTitle) + '</span>';

    return '' +
      '<article class="profile-post card-base">' +
        title +
        (content ? '<p class="profile-post__text">' + escapeHtml(content) + '</p>' : '') +
        '<p class="profile-post__meta">' +
          (date ? '<span>' + date + '</span>' : '') +
          '<span>&#9825; ' + likes + '</span>' +
          '<span>&#128172; ' + comments + '</span>' +
        '</p>' +
      '</article>';
  }

  function postsUrl(userId, page) {
    // URLSearchParams кодирует '+' -> '%2B': сервер обязан получить
    // include=creator+publication (пробел в этом параметре роняет бэкенд)
    var params = new URLSearchParams({
      creator: String(userId),
      include: 'creator+publication',
      page: String(page),
    });
    return '/api/posts?' + params.toString();
  }

  async function fetchPage(userId, page) {
    var res = await fetch(postsUrl(userId, page), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });
    if (!res.ok) {
      if (window.Messages) window.Messages.fail(res, 'Could not load posts.');
      return null;
    }
    return res.json();
  }

  document.addEventListener('DOMContentLoaded', function () {
    var el = document.querySelector('[data-posts][data-posts-user-id]');
    if (!el) return;

    var userId = parseInt(el.getAttribute('data-posts-user-id'), 10) || 0;
    if (!userId) {
      el.innerHTML = emptyText('No posts yet.');
      return;
    }

    var page     = 1;
    var hasMore  = false;
    var loading  = false;
    var moreBtn  = null;

    function appendPosts(items) {
      if (moreBtn) { moreBtn.remove(); moreBtn = null; }
      var html = items.map(renderPost).join('');
      if (page === 1) el.innerHTML = html;
      else el.insertAdjacentHTML('beforeend', html);
    }

    function renderMore() {
      if (moreBtn) { moreBtn.remove(); moreBtn = null; }
      if (!hasMore) return;
      moreBtn = document.createElement('button');
      moreBtn.type = 'button';
      moreBtn.className = 'pagination__btn profile-posts__more';
      moreBtn.textContent = 'Show more';
      moreBtn.addEventListener('click', async function () {
        if (loading) return;
        loading = true;
        moreBtn.textContent = 'Loading...';
        try {
          var data = await fetchPage(userId, page + 1);
          if (data) {
            page += 1;
            hasMore = !!(data.meta && data.meta.hasNext);
            appendPosts(data.items || []);
            renderMore();
          } else {
            moreBtn.textContent = 'Show more';
          }
        } finally {
          loading = false;
        }
      });
      el.appendChild(moreBtn);
    }

    (async function load() {
      var data;
      try {
        data = await fetchPage(userId, page);
      } catch (e) {
        console.error('[profile] posts load failed:', e);
        el.innerHTML = emptyText('Failed to load posts.');
        return;
      }

      if (!data) {
        el.innerHTML = emptyText('Failed to load posts.');
        return;
      }

      var items = data.items || [];
      if (items.length === 0) {
        el.innerHTML = emptyText('No posts yet.');
        return;
      }

      hasMore = !!(data.meta && data.meta.hasNext);
      appendPosts(items);
      renderMore();
    })();
  });
})();