/* ============================================
   PROFILE PUBLICATIONS - tabs load separate data
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

  var PLACEHOLDER = '/img/book-placeholder.svg';

  async function fetchByType(type, userId, genre) {
    var endpoint = type === 'article' ? '/api/articles' : '/api/books';
    var params = new URLSearchParams({
      creator: userId,
      include: 'creator',
    });
    if (genre) params.set('genre', genre);

    var res = await fetch(endpoint + '?' + params.toString(), {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    });

    if (!res.ok) throw new Error('HTTP ' + res.status);
    var data = await res.json();
    return extractItems(data);
  }

  function renderCard(item, type) {
    var url = type === 'book'
      ? '/books/' + encodeURIComponent(item.id)
      : '/articles/' + encodeURIComponent(item.id);
    var label = type === 'book' ? 'Book' : 'Article';
    var year  = formatYear(item.createdAt);

    return '' +
      '<a class="publication-card" href="' + url + '">' +
        '<span class="publication-card__cover">' +
          '<img src="' + PLACEHOLDER + '" alt="" loading="lazy">' +
        '</span>' +
        '<span class="publication-card__title">' + escapeHtml(item.title) + '</span>' +
        '<span class="publication-card__meta">' + label + (year ? ' - ' + year : '') + '</span>' +
      '</a>';
  }

  async function loadPage() {
    var el = document.querySelector('[data-publications-page]');
    if (!el) return;

    var userId  = el.getAttribute('data-publications-page-user-id');
    var type    = el.getAttribute('data-publications-page-type') || 'book';
    var genre   = el.getAttribute('data-publications-page-genre') || null;
    var countEl = document.querySelector('[data-publications-count]');

    if (!userId || userId === '0') {
      el.innerHTML = '<p class="empty-state__text">No publications yet.</p>';
      if (countEl) countEl.textContent = '0 items';
      return;
    }

    var items;
    try {
      items = await fetchByType(type, userId, genre);
    } catch (e) {
      console.error('[profile-publications] load failed:', e);
      el.innerHTML = '<p class="empty-state__text">Failed to load publications.</p>';
      if (countEl) countEl.textContent = '';
      return;
    }

    if (items.length === 0) {
      el.innerHTML = '<p class="empty-state__text">No publications yet.</p>';
      if (countEl) countEl.textContent = '0 items';
      return;
    }

    if (countEl) countEl.textContent = items.length + ' items';

    el.innerHTML = '<div class="grid-publications">' +
      items.map(function (item) { return renderCard(item, type); }).join('') +
      '</div>';
  }

  async function initGenresDropdown() {
    var dropdown = document.querySelector('[data-dropdown][data-dynamic="publications-genres"]');
    if (!dropdown) return;

    var menu = dropdown.querySelector('.dropdown__menu');
    if (!menu) return;

    menu.innerHTML = '<li class="dropdown__loading">Loading...</li>';

    var genres;
    try {
      var res = await fetch('/api/additional/genres', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) throw new Error('HTTP ' + res.status);
      var data = await res.json();
      genres = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
    } catch (e) {
      console.error('[profile-publications] genres load failed:', e);
      menu.innerHTML = '<li class="dropdown__error">Failed to load</li>';
      return;
    }

    var params = new URLSearchParams(window.location.search);
    var currentGenre = params.get('genre');

    function urlWithGenre(genreId) {
      var p = new URLSearchParams(window.location.search);
      if (genreId) p.set('genre', genreId);
      else p.delete('genre');
      var qs = p.toString();
      return window.location.pathname + (qs ? '?' + qs : '');
    }

    var items = [
      { title: 'All genres', href: urlWithGenre(null), active: !currentGenre },
      ...genres.map(function (g) {
        return {
          title: g.title,
          href: urlWithGenre(String(g.id)),
          active: String(g.id) === currentGenre,
        };
      }),
    ];

    menu.innerHTML = items.map(function (item) {
      return '<li><a href="' + item.href + '" class="dropdown__item ' +
        (item.active ? 'is-active' : '') + '">' +
        escapeHtml(item.title) + '</a></li>';
    }).join('');

    var label = dropdown.querySelector('[data-dropdown-label]');
    if (label) {
      if (currentGenre) {
        var found = genres.find(function (g) { return String(g.id) === currentGenre; });
        label.textContent = 'Genre: ' + (found ? found.title : 'All');
      } else {
        label.textContent = 'Genre: All';
      }
    }
  }

  function initReset() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-filter-reset]');
      if (!btn) return;
      if (!document.querySelector('[data-publications-page]')) return;

      e.preventDefault();
      e.stopImmediatePropagation();
      window.location.href = window.location.pathname;
    }, true);
  }

  document.addEventListener('DOMContentLoaded', function () {
    loadPage();
    initGenresDropdown();
    initReset();
  });
})();