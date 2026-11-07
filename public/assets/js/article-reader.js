/**
 * article-reader.js — оверлей чтения standalone-статьи.
 *
 * Открытие: кнопка [data-article-reader-open] на /articles/{id}.
 * Разметка: src/partials/article-reader.php, стили:
 * public/assets/css/modules/article-reader.css.
 *
 * Для статьи-отрывка кнопка «Читать статью» не открывает оверлей, а ведёт
 * на /books/{id} (см. article-details.php).
 */
(function () {
  'use strict';

  var state = {
    root: null,
    lastFocus: null
  };

  function root() {
    if (!state.root) state.root = document.querySelector('[data-article-reader]');
    return state.root;
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
    var r = root();
    if (!r || r.hidden) return;

    if (e.key === 'Escape') {
      e.preventDefault();
      close();
      return;
    }
    if (e.key !== 'Tab') return;

    var items = focusables(r);
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

  function open() {
    var r = root();
    if (!r) return;

    state.lastFocus = document.activeElement;
    r.hidden = false;
    document.body.classList.add('is-modal-open');
    document.addEventListener('keydown', onKeydown, true);

    var closeBtn = r.querySelector('[data-article-reader-close]');
    if (closeBtn) closeBtn.focus();
  }

  function close() {
    var r = root();
    if (!r) return;

    r.hidden = true;
    document.body.classList.remove('is-modal-open');
    document.removeEventListener('keydown', onKeydown, true);

    var target = state.lastFocus;
    state.lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); } catch (_) {}
    }
  }

  document.addEventListener('click', function (e) {
    var target = e.target && e.target.closest ? e.target : null;
    if (!target) return;

    var opener = target.closest('[data-article-reader-open]');
    if (opener) {
      e.preventDefault();
      open();
      return;
    }

    var r = root();
    if (!r || r.hidden) return;

    if (target.closest('[data-article-reader-close], [data-article-reader-dismiss]')) {
      e.preventDefault();
      close();
    }
  });
})();
