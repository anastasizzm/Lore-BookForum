/**
 * article-reader.js — модальный оверлей чтения standalone-статьи.
 *
 * Разметка: src/partials/article-reader.php
 *   [data-article-reader][data-article-id]
 *   [data-article-reader-body] — сюда рендерится текст.
 *
 * Открывается кнопкой [data-article-reader-open] на /articles/{id}.
 * Текст статьи не приходит в web-модели, поэтому при первом открытии
 * подгружается из GET /api/articles/{id}/content. Ответ:
 *   { data: { content } } или { data: { bookData, content } }.
 *
 * Для отрывка из книги оверлея нет — кнопка ведёт на /books/{id}/read.
 */
(function () {
  'use strict';

  var state = {
    root: null,
    lastFocus: null,
    loading: false,
    loaded: false
  };

  function t(key, fallback) {
    var v = window.LoreI18n ? window.LoreI18n.t(key) : key;
    return v && v !== key ? v : fallback;
  }

  function escapeHtml(str) {
    return String(str == null ? '' : str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function looksLikeHtml(value) {
    return /<\/?[a-z][\s\S]*>/i.test(String(value || ''));
  }

  /** Минимальная клиентская очистка HTML статьи перед вставкой. */
  function sanitizeHtml(html) {
    var doc = document.implementation.createHTMLDocument('');
    doc.body.innerHTML = String(html || '');

    var blocked = doc.body.querySelectorAll(
      'script, style, iframe, object, embed, form, input, button, link, meta, base'
    );
    Array.prototype.forEach.call(blocked, function (node) {
      if (node.parentNode) node.parentNode.removeChild(node);
    });

    Array.prototype.forEach.call(doc.body.querySelectorAll('*'), function (el) {
      Array.prototype.forEach.call(Array.prototype.slice.call(el.attributes), function (attr) {
        var name = String(attr.name || '').toLowerCase();
        var value = String(attr.value || '');
        if (name.indexOf('on') === 0) {
          el.removeAttribute(attr.name);
          return;
        }
        if ((name === 'href' || name === 'src') && /^\s*javascript:/i.test(value)) {
          el.removeAttribute(attr.name);
        }
      });
    });

    return doc.body.innerHTML;
  }

  function renderText(root, content) {
    var text = String(content || '').trim();
    if (text === '') {
      root.innerHTML = '<p>' + escapeHtml(t('common.article.no_text', 'The article text is not available yet.')) + '</p>';
      return;
    }

    if (looksLikeHtml(text)) {
      root.innerHTML = sanitizeHtml(text);
      return;
    }

    root.innerHTML = text.split(/\r?\n\s*\r?\n/).map(function (para) {
      return '<p>' + escapeHtml(para).replace(/\r?\n/g, '<br>') + '</p>';
    }).join('');
  }

  function fetchContent(id) {
    var url = '/api/articles/' + encodeURIComponent(id) + '/content';
    var doFetch = (window.LoreCsrf && window.LoreCsrf.fetch) ? window.LoreCsrf.fetch : fetch;

    return doFetch(url, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
      .then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
      })
      .then(function (parsed) {
        var data = parsed && parsed.data ? parsed.data : parsed;
        if (!data || typeof data !== 'object') throw new Error('empty payload');
        return data;
      });
  }

  function root() {
    if (!state.root) state.root = document.querySelector('[data-article-reader]');
    return state.root;
  }

  function bodyOf(el) {
    return el ? el.querySelector('[data-article-reader-body]') : null;
  }

  function showLoading(el) {
    var body = bodyOf(el);
    if (body) {
      body.innerHTML = '<p class="article-body__loading">' +
        escapeHtml(t('common.common.loading', 'Loading…')) + '</p>';
    }
  }

  function showNoText(el) {
    var body = bodyOf(el);
    if (body) {
      body.innerHTML = '<p>' +
        escapeHtml(t('common.article.no_text', 'The article text is not available yet.')) + '</p>';
    }
  }

  function loadContent(el) {
    if (!el || state.loading) return;
    if (el.getAttribute('data-article-loaded') === '1') {
      state.loaded = true;
      return;
    }

    var id = parseInt(el.getAttribute('data-article-id'), 10) || 0;
    var body = bodyOf(el);
    if (!id || !body) {
      showNoText(el);
      return;
    }

    state.loading = true;
    showLoading(el);

    fetchContent(id)
      .then(function (data) {
        // Отрывок из книги: собственного текста нет — там кнопка Open book.
        if (data && data.bookData) {
          showNoText(el);
          return;
        }

        renderText(body, data && data.content);
        el.setAttribute('data-article-loaded', '1');
        state.loaded = true;
      })
      .catch(function (err) {
        console.error('[article-reader] content load failed', err);
        // Не помечаем как загруженное: при повторном открытии попробуем снова.
        el.removeAttribute('data-article-loaded');
        state.loaded = false;
        showNoText(el);
      })
      .finally(function () {
        state.loading = false;
      });
  }

  function focusables(el) {
    if (!el) return [];
    return Array.prototype.slice.call(
      el.querySelectorAll('button, [href], input, select, textarea, [tabindex]')
    ).filter(function (node) {
      return !node.disabled && node.offsetParent !== null;
    });
  }

  function onKeydown(e) {
    var el = root();
    if (!el || el.hidden) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key !== 'Tab') return;

    var items = focusables(el);
    if (!items.length) return;
    var first = items[0];
    var last = items[items.length - 1];

    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function open(opener) {
    var el = root();
    if (!el) return;

    state.lastFocus = opener || document.activeElement;
    el.hidden = false;
    document.body.classList.add('is-modal-open');
    document.addEventListener('keydown', onKeydown, true);

    var closeBtn = el.querySelector('[data-article-reader-close]');
    if (closeBtn) closeBtn.focus();

    loadContent(el);
  }

  function close() {
    var el = root();
    if (!el) return;

    el.hidden = true;
    document.body.classList.remove('is-modal-open');
    document.removeEventListener('keydown', onKeydown, true);

    var target = state.lastFocus;
    state.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) {}
    }
  }

  /**
   * Отрывок: contentData (bookData.pageStart) отдаётся отдельным запросом.
   * Если в ссылке ещё нет ?page=, подставляем redirectUrl из ответа.
   */
  function loadBookLink(link) {
    var id = parseInt(link.getAttribute('data-article-id'), 10) || 0;
    if (!id || link.getAttribute('data-book-loaded') === '1') return;
    if (/[?&]page=\d+/.test(link.getAttribute('href') || '')) return;
    link.setAttribute('data-book-loaded', '1');

    fetchContent(id)
      .then(function (data) {
        var bookData = data && data.bookData;
        if (!bookData) return;

        var url = bookData.redirectUrl || '';
        if (!url) {
          var bookId = parseInt(bookData.bookId, 10) || 0;
          if (!bookId) return;
          var params = [];
          if (bookData.pageStart) params.push('page=' + encodeURIComponent(bookData.pageStart));
          var lang = link.getAttribute('data-article-lang') || '';
          if (lang) params.push('lang=' + encodeURIComponent(lang));
          url = '/books/' + bookId + '/read' + (params.length ? '?' + params.join('&') : '');
        }
        link.setAttribute('href', url);
      })
      .catch(function (err) {
        console.warn('[article-reader] book link load failed', err);
      });
  }

  function init() {
    Array.prototype.forEach.call(
      document.querySelectorAll('[data-article-book-read]'),
      loadBookLink
    );
  }

  document.addEventListener('click', function (e) {
    var target = e.target && e.target.closest ? e.target : null;
    if (!target) return;

    var opener = target.closest('[data-article-reader-open]');
    if (opener) {
      e.preventDefault();
      open(opener);
      return;
    }

    var el = root();
    if (!el || el.hidden) return;

    if (target.closest('[data-article-reader-close], [data-article-reader-dismiss]')) {
      e.preventDefault();
      close();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
