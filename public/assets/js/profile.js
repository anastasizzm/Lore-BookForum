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
   PROFILE - posts
   Карточки постов ТАКИЕ ЖЕ, как в ленте (разметка card-feed.php):
   пост = корневой комментарий, под ним плоский список комментариев
   (без вложенности — один уровень, как на book/article details).
   Лайки, Reply и комментарии обслуживает card-feed.js + comments.js:
   нужны те же data-* атрибуты, что отдаёт card-feed.php.
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
    var m = /(\d{4})-(\d{2})-(\d{2})/.exec(raw);
    return m ? m[3] + '.' + m[2] + '.' + m[1] : '';
  }

  function emptyText(text) {
    return '<p class="profile-sidebar-box__text profile-sidebar-box__text--muted">' +
      escapeHtml(text) + '</p>';
  }

  // Текст поста с переносами строк — как nl2br в card-feed.php
  function textHtml(text) {
    return escapeHtml(text).replace(/\r\n|\r|\n/g, '<br>');
  }

  /** Аватар строки поста — как в avatar.php/comments.js: пресет-эмодзи
      или инициалы (файловые аватары в API не приходят). */
  function paintAvatar(el, initials, raw) {
    if (!el) return;
    el.replaceChildren();
    if (window.LoreAvatar) window.LoreAvatar.paint(el, null);   // сброс фона

    var span = document.createElement('span');
    span.textContent = initials || '?';

    var parsed = (window.LoreAvatar && window.LoreAvatar.parse(raw)) || { type: 'none' };
    if (parsed.type === 'emoji') {
      var em = document.createElement('span');
      em.className = 'avatar__emoji';
      em.setAttribute('aria-hidden', 'true');
      em.textContent = parsed.emoji;
      el.appendChild(em);
      if (window.LoreAvatar) window.LoreAvatar.paint(el, parsed);
      return;
    }
    el.appendChild(span);
  }

  function initialsOf(user) {
    var n = String((user && user.name) || '').trim();
    var s = String((user && user.surname) || '').trim();
    var v = (n.charAt(0) + s.charAt(0)).toUpperCase();
    if (!v) v = String((user && user.username) || '').trim().charAt(0).toUpperCase() || '?';
    return v;
  }

  var LIKE_SVG =
    '<svg width="16" height="15" viewBox="0 0 22 20" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
      '<path d="M11 18.5C11 18.5 1 12.5 1 6.2C1 3.3 3.3 1 6.1 1C8.2 1 10 2.2 11 4C12.2 2.2 13.8 1 15.9 1C18.7 1 21 3.3 21 6.2C21 12.5 11 18.5 11 18.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' +
    '</svg>';

  /**
   * Карточка поста — по разметке card-feed.php (feed-list.php):
   * шапка книги, строка поста (аватар, ник->профиль, текст, сердечко +
   * Reply слева, дата справа), форма ответа, «Show more» и скрытый
   * список комментариев (data-feed-comments -> card-feed.js).
   */
  function renderPost(post, cu, authorFallback) {
    var creator   = post.creator || {};
    var pub       = post.publication || {};
    var postId    = Number(post.id || 0);
    var pubId     = Number(post.publicationId || pub.id || 0);
    var userId    = Number(creator.id || 0);
    var userName  = String(creator.username || authorFallback || '');
    var initials  = initialsOf(creator);
    var avatarKey = String(creator.avatar || '');
    var liked     = !!post.isLiked;
    var likes     = Number(post.likesCount || 0);
    var comments  = Number(post.commentsCount || 0);
    var date      = formatDate(post.createdAt);
    var text      = String(post.content || '');

    // Обложка книги: iconId -> /uploads/covers/{iconId} (как в feed-list.php);
    // нет обложки — CSS-заглушка cover--empty
    var iconId = (pub.iconId == null ? '' : String(pub.iconId));
    var coverHtml = iconId
      ? '<img src="' + escapeHtml('/uploads/covers/' + iconId) + '" alt="" class="card-feed__book-thumb">'
      : '<span class="card-feed__book-thumb cover--empty" aria-hidden="true"></span>';

    var bookHeader = '';
    if (pubId && pub.title) {
      bookHeader =
        '<div class="card-feed__book-header">' +
          coverHtml +
          '<h3 class="card-feed__book-title">' +
            '<a class="card-feed__book-link" href="/books/' + pubId + '"' +
               ' data-pub-link data-pub-id="' + pubId + '">' + escapeHtml(pub.title) + '</a>' +
          '</h3>' +
        '</div>';
    }

    var authorHtml = userId
      ? '<a href="/users/' + userId + '" class="user-link">' + escapeHtml(userName) + '</a>'
      : escapeHtml(userName);

    return '' +
      '<article class="card-base card-feed"' +
        ' data-post-id="' + postId + '"' +
        ' data-publication-id="' + pubId + '"' +
        ' data-author-id="' + userId + '"' +
        ' data-author-username="' + escapeHtml(userName) + '"' +
        ' data-cu-id="' + cu.id + '"' +
        ' data-cu-name="' + escapeHtml(cu.name) + '"' +
        ' data-cu-initials="' + escapeHtml(cu.initials) + '"' +
        ' data-cu-avatar="' + escapeHtml(cu.avatar) + '">' +
        bookHeader +
        '<div class="card-feed__body-section' + (bookHeader ? ' card-feed__body-section--with-book' : '') + '">' +
          '<div class="card-feed__post">' +
            '<div class="comment-card__inner">' +
              '<div class="avatar avatar--sm" data-post-avatar><span>' + escapeHtml(initials) + '</span></div>' +
              '<div class="comment-card__content">' +
                '<div class="comment-card__main">' +
                  '<div class="comment-card__head">' +
                    '<div class="comment-card__author">' + authorHtml + '</div>' +
                    '<div class="comment-card__text">' + textHtml(text) + '</div>' +
                  '</div>' +
                '</div>' +

                '<div class="comment-card__footer">' +
                  '<div class="comment-card__meta">' +
                    '<button type="button" class="btn-icon-small btn-like comment-card__like' + (liked ? ' is-liked' : '') + '"' +
                            ' data-like-btn data-liked="' + (liked ? '1' : '0') + '"' +
                            ' aria-pressed="' + (liked ? 'true' : 'false') + '" aria-label="Like">' +
                      LIKE_SVG +
                      '<span data-like-count>' + likes + '</span>' +
                    '</button>' +
                    '<button type="button" class="comment-card__reply"' +
                            ' data-comment-toggle aria-expanded="false" aria-label="Reply">Reply</button>' +
                  '</div>' +
                  '<span class="comment-card__date">' + escapeHtml(date) + '</span>' +
                '</div>' +

                '<form class="comment-form" action="/api/posts/' + postId + '" method="POST"' +
                      ' data-feed-comment-form data-post-id="' + postId + '" novalidate hidden>' +
                  '<input type="hidden" name="_token" value="">' +
                  '<input type="hidden" name="publicationId" value="' + pubId + '">' +
                  '<input type="text" class="comment-form__input" name="content" placeholder="Add a comment…" maxlength="500" autocomplete="off">' +
                  '<button type="submit" class="comment-form__submit" disabled>Post</button>' +
                  '<p data-comment-error role="alert" hidden' +
                     ' style="color: red; margin-top: 8px; font-size: 14px; width: 100%;"></p>' +
                  '<p data-comment-status role="status" hidden' +
                     ' style="color: green; margin-top: 8px; font-size: 14px; width: 100%;"></p>' +
                '</form>' +

                '<button type="button" class="comment-card__more" data-fc-expand' +
                        ' aria-expanded="false">Show more</button>' +

                '<span class="visually-hidden" data-comment-count>' + comments + '</span>' +

                '<div class="feed-comments" data-feed-comments hidden>' +
                  '<div class="feed-comments__list" data-fc-list></div>' +
                  '<p class="feed-comments__status" data-fc-status role="status" hidden></p>' +
                '</div>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>' +
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

    // Текущий юзер — те же data-cu-*, что в card-feed.php (для своих комментариев)
    var cu = {
      id: parseInt(el.getAttribute('data-posts-cu-id'), 10) || 0,
      name: el.getAttribute('data-posts-cu-name') || '',
      initials: el.getAttribute('data-posts-cu-initials') || '?',
      avatar: el.getAttribute('data-posts-cu-avatar') || '',
    };
    var authorFallback = el.getAttribute('data-posts-author-username') || '';
    var csrf          = el.getAttribute('data-posts-csrf') || '';

    var page     = 1;
    var hasMore  = false;
    var loading  = false;
    var moreBtn  = null;

    function appendPosts(items) {
      if (moreBtn) { moreBtn.remove(); moreBtn = null; }
      var html = items.map(function (post) { return renderPost(post, cu, authorFallback); }).join('');
      if (page === 1) el.innerHTML = html;
      else el.insertAdjacentHTML('beforeend', html);

      // Аватары — через LoreAvatar (пресет-эмодзи или инициалы), без innerHTML
      items.forEach(function (post, i) {
        var node = el.children[el.children.length - items.length + i];
        if (!node) return;
        paintAvatar(
          node.querySelector('[data-post-avatar]'),
          initialsOf(post.creator),
          String((post.creator && post.creator.avatar) || '')
        );
        // CSRF-поле формы ответа — из data-posts-csrf (карточка отрендерена в JS)
        var tokenInput = node.querySelector('[data-feed-comment-form] [name="_token"]');
        if (tokenInput) tokenInput.value = csrf;
      });
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
