/**
 * article-reader.js — текст standalone-статьи прямо на странице деталей.
 *
 * Разметка: src/views/pages/article/article-details.php —
 *   [data-article-content][data-article-id] (внутри плашки .info-box).
 *
 * Контент статьи не приходит в web-модели, поэтому подгружаем его из
 * GET /api/articles/{id}/content. Ответ: { data: { content } } или
 * { data: { bookData, content } }. Для отрывка (bookData) текста нет —
 * там ссылка ведёт сразу в читалку книги.
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

  function load(root) {
    var id = parseInt(root.getAttribute('data-article-id'), 10) || 0;
    if (!id || root.getAttribute('data-article-loaded') === '1') return;
    root.setAttribute('data-article-loaded', '1');

    var url = '/api/articles/' + encodeURIComponent(id) + '/content';
    var doFetch = (window.LoreCsrf && window.LoreCsrf.fetch) ? window.LoreCsrf.fetch : fetch;

    doFetch(url, {
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
        root.innerHTML = '<p>' + escapeHtml(t('common.article.no_text', 'The article text is not available yet.')) + '</p>';
      });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-article-content]'), load);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
