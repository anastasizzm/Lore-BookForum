/**
 * article-reader.js — текст standalone-статьи прямо на странице деталей.
 *
 * Разметка: src/views/pages/article/article-details.php —
 *   [data-article-content][data-article-id] (внутри плашки .info-box).
 *
 * Контент статьи не приходит в web-модели, поэтому подгружаем его из
 * GET /api/articles/{id}/content. Ответ: { data: { content } } или
 * { data: { bookData, content } }. Для отрывка (bookData) текста нет —
 * там ссылка ведёт сразу в читалку книги; pageStart/redirectUrl из этого
 * же ответа подставляются в ссылку «Открыть книгу».
 *
 * Модального окна и кнопки Close больше нет: текст рендерится в плашке.
 */
(function () {
  'use strict';

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

  function load(root) {
    var id = parseInt(root.getAttribute('data-article-id'), 10) || 0;
    if (!id || root.getAttribute('data-article-loaded') === '1') return;
    root.setAttribute('data-article-loaded', '1');

    fetchContent(id)
      .then(function (data) {
        // Отрывок из книги: собственного текста нет — оставляем ссылку
        // «Открыть книгу» (она уже в блоке кнопок).
        if (data.bookData) {
          root.innerHTML = '<p>' + escapeHtml(t('common.article.no_text', 'The article text is not available yet.')) + '</p>';
          return;
        }

        renderText(root, data.content);
      })
      .catch(function (err) {
        console.error('[article] content load failed', err);
        // Не помечаем блок загруженным: повторный клик «Читать статью»
        // попробует загрузить текст ещё раз.
        root.removeAttribute('data-article-loaded');
        root.innerHTML = '<p>' + escapeHtml(t('common.article.no_text', 'The article text is not available yet.')) + '</p>';
      });
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
        console.warn('[article] book link load failed', err);
      });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-article-content]'), load);
    Array.prototype.forEach.call(document.querySelectorAll('[data-article-book-read]'), loadBookLink);
  }

  /**
   * Кнопка «Читать статью»: надёжно прокручивает к тексту и, если он ещё
   * не загружен, повторно запускает загрузку. Раньше это был обычный
   * якорь #article-content — при пустом/поздно загруженном блоке клик
   * выглядел как «ничего не произошло».
   */
  function scrollToArticle(link) {
    var target = document.querySelector('[data-article-content]') || document.getElementById('article-content');
    if (!target) return;

    if (target.getAttribute('data-article-loaded') !== '1') {
      target.removeAttribute('data-article-loaded');
      load(target);
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
    try {
      history.replaceState(null, '', '#article-content');
    } catch (_) {
      location.hash = 'article-content';
    }
  }

  document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('[data-article-read-scroll]') : null;
    if (!link) return;
    e.preventDefault();
    scrollToArticle(link);
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
