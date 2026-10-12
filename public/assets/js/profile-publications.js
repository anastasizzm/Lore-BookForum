(function () {
  'use strict';

  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  async function initGenresDropdown() {
    var dropdown = document.querySelector('[data-dropdown][data-dynamic="publications-genres"]');
    if (!dropdown) return;
    var menu = dropdown.querySelector('.dropdown__menu');
    if (!menu) return;

    menu.innerHTML = '<li class="dropdown__loading">' + escapeHtml(t('common.loading')) + '</li>';

    var genres;
    try {
      var res = await fetch('/api/additional/genres', {
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        if (window.Messages) await window.Messages.fail(res, t('.js.genres_load_failed'));
        throw new Error('HTTP ' + res.status);
      }
      var data = await res.json();
      genres = Array.isArray(data) ? data : (Array.isArray(data.items) ? data.items : []);
    } catch (e) {
      console.error('[profile-publications] genres load failed:', e);
      if (window.Messages && !/^HTTP/.test(e.message)) {
        window.Messages.show(t('js.network_error'), { type: 'error' });
      }
      menu.innerHTML = '<li class="dropdown__error">' + escapeHtml(t('js.load_failed')) + '</li>';
      return;
    }

    var params = new URLSearchParams(window.location.search);
    var currentGenre = params.get('genre');

    function urlWithGenre(genreId) {
      var p = new URLSearchParams(window.location.search);
      if (genreId) p.set('genre', genreId); else p.delete('genre');
      p.delete('page');
      var qs = p.toString();
      return window.location.pathname + (qs ? '?' + qs : '');
    }

    var items = [
      { title: t('js.all_genres'), href: urlWithGenre(null), active: !currentGenre },
      ...genres.map(function (g) {
        return { title: g.title, href: urlWithGenre(String(g.id)), active: String(g.id) === currentGenre };
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
        label.textContent = t('js.genre_label', { value: found ? found.title : t('js.all_genres') });
      } else {
        label.textContent = t('js.genre_label', { value: t('js.all_genres') });
      }
    }
  }

  function initReset() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-filter-reset]');
      if (!btn) return;
      if (!document.querySelector('.publications-page') &&
          !document.querySelector('.grid-publications') &&
          !document.querySelector('.books-panel')) return;

      var path = window.location.pathname;
      if (!/\/users\/\d+\/(books|articles)/.test(path)) return;

      e.preventDefault();
      e.stopImmediatePropagation();
      window.location.href = path;
    }, true);
  }

  document.addEventListener('DOMContentLoaded', function () {
    initGenresDropdown();
    initReset();
  });
})();