/**
 * drafts.js — действия на странице черновиков
 * (src/views/pages/drafts/drafts.php, карточки — src/partials/card-draft.php).
 *
 * 1. УДАЛЕНИЕ: ConfirmModal → DELETE /api/{books|articles}/{id} → карточка
 *    уходит из списка (редиректа нет). Когда список опустел — перезагрузка:
 *    счётчик, пагинация и пустое состояние пересчитает сервер.
 *    403/404/4xx — тост с текстом бэка (Messages.readError).
 *
 * 2. ПУБЛИКАЦИЯ — через окно ролей:
 *      книги  → RightsModal.open({ mode:'publish', bookId, onPublished })
 *               (rights-modal.php + rights-modal.js + roles-module.js);
 *      статьи → LoreArticlePublish.open(id, { onPublished, onConflict })
 *               (article-rights-dialog.php + article-publish.js + roles-module.js).
 *    Успех (onPublished) — карточка уходит из списка вместо перехода на
 *    страницу публикации; хост закрывает окно сам после колбэка.
 *    409 «нет контента»: книга — rights-modal.js держит окно открытым и
 *    показывает errors.books.content_pdf (saveError → тост), статья —
 *    article-publish.js закрывает окно и показывает
 *    errors.article.publish_no_content (onConflict). Карточка в обоих
 *    случаях остаётся в списке.
 *
 * 3. СБРОС ФИЛЬТРОВ: [data-filter-reset]. Фильтры этой страницы серверные
 *    (рендер по ?q/?genre/?isbn/?type/?doi), поэтому кнопка ведёт на тот же
 *    путь, сохраняя только поиск (q), состояние панели (f) и локаль (lang) —
 *    как $resetHref в src/partials/library-filters.php.
 */
(function () {
  'use strict';

  function t(key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  }

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  }

  function csrfFetch(url, options) {
    if (window.LoreCsrf && typeof window.LoreCsrf.fetch === 'function') {
      return window.LoreCsrf.fetch(url, options);
    }
    return fetch(url, options);
  }

  function cardOf(el) {
    return el && el.closest ? el.closest('[data-draft-id]') : null;
  }

  function info(card) {
    return {
      id: parseInt(card.getAttribute('data-draft-id'), 10) || 0,
      article: card.getAttribute('data-draft-type') === 'article',
    };
  }

  /**
   * Убирает карточку из сетки и уменьшает счётчик. Если карточек не
   * осталось — перезагружает страницу (иначе висели бы «0 черновиков»
   * и пагинация по опустевшей странице).
   */
  function removeCard(card) {
    if (!card || !card.parentNode) return;
    var list = card.parentNode;
    list.removeChild(card);

    var count = document.querySelector('[data-drafts-count]');
    if (count) {
      var n = parseInt(String(count.textContent).replace(/\D+/g, ''), 10);
      if (!isNaN(n)) count.textContent = String(Math.max(0, n - 1));
    }

    if (!list.children.length) window.location.reload();
  }

  /* --------------------------------------------------------
     УДАЛЕНИЕ
     -------------------------------------------------------- */
  function deleteDraft(card) {
    var one = info(card);
    if (!one.id) return;

    var msgKey = one.article ? 'js.delete_article_text' : 'js.delete_book_text';
    var ask = (window.ConfirmModal && typeof window.ConfirmModal.confirm === 'function')
      ? window.ConfirmModal.confirm({
          title: t(one.article ? 'js.delete_article_title' : 'js.delete_book_title'),
          message: t(msgKey),
          confirmText: t('js.delete'),
          danger: true,
        })
      : Promise.resolve(window.confirm(t(msgKey)));

    Promise.resolve(ask).then(function (confirmed) {
      if (!confirmed) return;

      csrfFetch('/api/' + (one.article ? 'articles' : 'books') + '/' + one.id, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      })
        .then(function (res) {
          if (res.ok) {
            notify(t('drafts.deleted'), 'success');
            removeCard(card);
            return;
          }
          return (window.Messages
            ? window.Messages.readError(res, t('errors.common.delete_failed', { status: res.status }))
            : Promise.resolve(t('errors.common.delete_failed', { status: res.status }))
          ).then(function (msg) { notify(msg); });
        })
        .catch(function (err) {
          console.error('[drafts] delete failed', err);
          notify(t('errors.common.network_error'));
        });
    });
  }

  /* --------------------------------------------------------
     ПУБЛИКАЦИЯ (через окно ролей)
     -------------------------------------------------------- */
  function publishDraft(card) {
    var one = info(card);
    if (!one.id) return;

    /** Опубликовано: карточка уходит из списка вместо редиректа. */
    var done = function () {
      notify(t(one.article ? 'js.article_published' : 'js.book_published'), 'success');
      removeCard(card);
    };

    if (one.article) {
      if (!window.LoreArticlePublish) {
        console.error('[drafts] article publish window is not loaded');
        notify(t('errors.common.network_error'));
        return;
      }
      window.LoreArticlePublish.open(one.id, {
        onPublished: done,
        // 409 «нет контента»: окно закрывает сам article-publish.js и
        // показывает errors.article.publish_no_content — карточка остаётся.
        onConflict: function () {},
      });
      return;
    }

    if (!window.RightsModal) {
      console.error('[drafts] rights window is not loaded');
      notify(t('errors.common.network_error'));
      return;
    }
    window.RightsModal.open({
      mode: 'publish',
      bookId: one.id,
      onPublished: done,
    });
  }

  /* --------------------------------------------------------
     СБРОС ФИЛЬТРОВ
     -------------------------------------------------------- */
  function resetFilters() {
    var url = new URL(window.location.href);
    var keep = {};
    ['q', 'f', 'lang'].forEach(function (key) {
      var value = url.searchParams.get(key);
      if (value) keep[key] = value;
    });
    var qs = new URLSearchParams(keep).toString();
    window.location.href = url.pathname + (qs ? '?' + qs : '');
  }

  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target : null;
    if (!el) return;

    var delBtn = el.closest('[data-draft-delete]');
    if (delBtn) {
      e.preventDefault();
      deleteDraft(cardOf(delBtn));
      return;
    }

    var pubBtn = el.closest('[data-draft-publish]');
    if (pubBtn) {
      e.preventDefault();
      publishDraft(cardOf(pubBtn));
      return;
    }

    if (el.closest('[data-filter-reset]')) {
      e.preventDefault();
      resetFilters();
    }
  });
})();
