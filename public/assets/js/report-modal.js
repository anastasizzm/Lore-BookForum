/**
 * report-modal.js — плашка «Пожаловаться» (src/partials/report-modal.php,
 * стили public/assets/css/report-modal.css). Подключается в layouts/main.php,
 * поэтому доступна на всех страницах.
 *
 * Открытие — делегированным кликом по [data-report-open]:
 *   - публикация (пункт меню «три точки» на book/article details):
 *     data-report-kind="book|article", data-report-id, data-report-title;
 *   - пост / комментарий / ответ (пункт меню «три точки» в comment-menu):
 *     data-report-kind="post|comment|reply" — id и подпись JS сам находит
 *     по ближайшему [data-post-id] / [data-comment-id] / [data-reply-id].
 *
 * Програмmatic: window.ReportModal.open({ kind, id, label }).
 *
 * ДАННЫЕ (бэкенд заморожен: config/ и app/ не трогаем):
 *   причины — мок-список в самой плашке (report-modal.php), отправка —
 *   мок (см. send() ниже). КОНТРАКТ, к которому плашка готова:
 *     POST /api/reports
 *     body: target_type (book|article|post|comment|reply), target_id,
 *           reason, comment, _token (CSRF)  →  201 | 422 { errors } | 401.
 */
(function () {
  'use strict';

  function t(key, params) {
    return window.LoreI18n ? window.LoreI18n.t(key, params) : key;
  }

  var KIND_LABEL_KEY = {
    book: 'report.kind_book',
    article: 'report.kind_article',
    post: 'report.kind_post',
    comment: 'report.kind_comment',
    reply: 'report.kind_reply'
  };

  var state = {
    root: null,
    kind: '',
    id: 0,
    label: '',
    sending: false,
    lastFocus: null,
  };

  function root() {
    if (!state.root) state.root = document.querySelector('[data-report-modal]');
    return state.root;
  }

  function q(sel) { var r = root(); return r ? r.querySelector(sel) : null; }
  function show(node, visible) { if (node) node.hidden = !visible; }

  /* --------------------------------------------------------
     ЦЕЛЬ ЖАЛОБЫ: атрибуты opener'а + ближайший контейнер
     -------------------------------------------------------- */
  function firstText(el) {
    if (!el) return '';
    return (el.textContent || '').replace(/\s+/g, ' ').trim();
  }

  function snippet(text) {
    text = String(text || '').replace(/\s+/g, ' ').trim();
    if (!text) return '';
    return text.length > 120 ? text.slice(0, 119) + '…' : text;
  }

  function labelFor(opener, kind) {
    if (kind === 'book' || kind === 'article') {
      // Название публикации на details (info-box с заголовком)
      return snippet(firstText(document.querySelector('.info-box__title')));
    }
    var node = kind === 'reply' ? opener.closest('.comment-reply')
             : kind === 'post' ? opener.closest('.card-feed')
             : opener.closest('.comment-card') || opener.closest('.comment-reply');
    if (!node) return '';
    var text = node.querySelector('.comment-card__head .comment-card__text')
            || node.querySelector('.comment-reply__text')
            || node.querySelector('.comment-card__text');
    return snippet(firstText(text));
  }

  function resolveTarget(opener) {
    var kind = opener.getAttribute('data-report-kind') || '';
    var id = parseInt(opener.getAttribute('data-report-id') || '', 10) || 0;
    var label = opener.getAttribute('data-report-label') || '';

    var detailsWrap = opener.closest('[data-details-menu-wrap]');
    if (!kind && detailsWrap) {
      kind = detailsWrap.getAttribute('data-pub-type') === 'article' ? 'article' : 'book';
    }

    var reply = opener.closest('[data-reply-id]');
    var comment = opener.closest('[data-comment-id]');
    var post = opener.closest('[data-post-id]');

    if (!kind) {
      if (reply) kind = 'reply';
      else if (comment) kind = 'comment';
      else if (post) kind = 'post';
      else kind = detailsWrap
        ? (detailsWrap.getAttribute('data-pub-type') === 'article' ? 'article' : 'book')
        : 'comment';
    }

    if (!id) {
      if (kind === 'reply') {
        id = reply ? parseInt(reply.dataset.replyId, 10) || 0 : 0;
      } else if (kind === 'comment') {
        id = comment ? parseInt(comment.dataset.commentId, 10) || 0 : 0;
      } else if (kind === 'post') {
        id = post ? parseInt(post.dataset.postId, 10) || 0 : 0;
      }
    }

    if (!label) label = labelFor(opener, kind);

    return { kind: kind, id: id, label: label };
  }

  /* --------------------------------------------------------
     ЗАКРЫТИЕ РОДИТЕЛЬСКИХ «ТРЁХ ТОЧЕК» (details-menu / comment-menu)
     -------------------------------------------------------- */
  function closeMenu(wrap, menuSel, toggleSel) {
    var wrapEl = wrap;
    if (!wrapEl) return;
    var menu = wrapEl.querySelector(menuSel);
    if (menu) menu.hidden = true;
    var toggle = wrapEl.querySelector(toggleSel);
    if (toggle) toggle.setAttribute('aria-expanded', 'false');
  }

  function closeParentMenus(opener) {
    closeMenu(opener.closest('[data-details-menu-wrap]'), '[data-details-menu]', '[data-details-menu-toggle]');
    closeMenu(opener.closest('[data-c-menu-wrap]'), '[data-c-menu]', '[data-c-menu-toggle]');
  }

  /* --------------------------------------------------------
     РЕНДЕР: цель, кнопка «Отправить», ошибка
     -------------------------------------------------------- */
  function selectedReason() {
    var checked = q('input[name="report-reason"]:checked');
    return checked ? checked.value : '';
  }

  function setError(text) {
    var box = q('[data-report-error]');
    if (!box) return;
    box.textContent = text || '';
    show(box, !!text);
  }

  function setSending(sending) {
    state.sending = sending;
    var confirmBtn = q('[data-report-confirm]');
    var cancelBtn = q('[data-report-cancel]');
    if (confirmBtn) confirmBtn.disabled = sending || !selectedReason();
    if (cancelBtn) cancelBtn.disabled = sending;

    var label = q('[data-report-confirm-label]');
    if (label) label.textContent = sending ? t('report.sending') : t('report.send');
  }

  function renderTarget() {
    var box = q('[data-report-target]');
    if (!box) return;

    var kindLabel = q('[data-report-kind-label]');
    var titleLabel = q('[data-report-title-label]');

    if (kindLabel) kindLabel.textContent = t(KIND_LABEL_KEY[state.kind] || 'report.kind_comment');
    if (titleLabel) {
      titleLabel.textContent = state.label || '';
      show(titleLabel, !!state.label);
    }
    show(box, !!state.kind);
  }

  function resetForm() {
    Array.prototype.forEach.call(
      q('[data-report-reasons]') ? q('[data-report-reasons]').querySelectorAll('input') : [],
      function (radio) { radio.checked = false; }
    );
    var comment = q('[data-report-comment]');
    if (comment) comment.value = '';
    setError('');
  }

  /* --------------------------------------------------------
     ОТПРАВКА (мок; контракт — в шапке файла)
     -------------------------------------------------------- */
  function payload() {
    return {
      target_type: state.kind,
      target_id: state.id,
      reason: selectedReason(),
      comment: (q('[data-report-comment]') ? q('[data-report-comment]').value : '').trim(),
    };
  }

  function send(body) {
    // TODO(api): заменить на LoreCsrf.fetch('/api/reports', { method: 'POST',
    // body: JSON.stringify(body) }) — роут добавит бэк (config/ заморожен).
    return new Promise(function (resolve) {
      setTimeout(function () {
        console.log('[report] mock submit', body);
        resolve({ ok: true });
      }, 250);
    });
  }

  function confirm() {
    if (state.sending) return;

    if (!selectedReason()) {
      setError(t('report.no_reason'));
      var firstRadio = q('input[name="report-reason"]');
      if (firstRadio) firstRadio.focus();
      return;
    }

    setSending(true);
    send(payload()).then(function (res) {
      setSending(false);
      if (res && res.ok) {
        close();
        if (window.Messages) window.Messages.show(t('report.sent'), { type: 'success' });
        return;
      }
      setError(t('report.failed'));
    });
  }

  /* --------------------------------------------------------
     ОТКРЫТИЕ / ЗАКРЫТИЕ
     -------------------------------------------------------- */
  function close() {
    var r = root();
    if (r) r.hidden = true;
    document.body.classList.remove('is-modal-open');
    if (state.lastFocus && state.lastFocus.focus) {
      try { state.lastFocus.focus(); } catch (_) {}
    }
    state.lastFocus = null;
    state.sending = false;
  }

  function cancel() {
    if (state.sending) return;
    close();
  }

  function open(opts) {
    var r = root();
    if (!r) return;

    opts = opts || {};
    state.kind = opts.kind || r.getAttribute('data-report-kind') || '';
    state.id = parseInt(opts.id !== undefined && opts.id !== null ? opts.id : r.getAttribute('data-report-id'), 10) || 0;
    state.label = opts.label || '';

    r.setAttribute('data-report-kind', state.kind);
    r.setAttribute('data-report-id', String(state.id));
    r.setAttribute('data-report-label', state.label);

    resetForm();
    renderTarget();
    setSending(false);

    state.lastFocus = document.activeElement;
    r.hidden = false;
    document.body.classList.add('is-modal-open');

    var first = q('[data-report-cancel]');
    if (first && first.focus) first.focus();
  }

  /* --------------------------------------------------------
     ПОДКЛЮЧЕНИЕ (делегирование — кнопки могут рисоваться позже)
     -------------------------------------------------------- */
  function onReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
    else fn();
  }

  function focusables() {
    var r = root();
    if (!r) return [];
    return Array.prototype.slice.call(
      r.querySelectorAll('button, [href], input, select, textarea, [tabindex]')
    ).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
  }

  onReady(function () {
    document.addEventListener('click', function (e) {
      var target = e.target && e.target.closest ? e.target : null;
      if (!target) return;

      var opener = target.closest('[data-report-open]');
      if (opener) {
        e.preventDefault();
        closeParentMenus(opener);
        var found = resolveTarget(opener);
        open({ kind: found.kind, id: found.id, label: found.label });
        return;
      }

      if (!root() || root().hidden) return;

      if (target.closest('[data-report-confirm]')) { confirm(); return; }
      if (target.closest('[data-report-cancel], [data-report-dismiss]')) { cancel(); return; }
    });

    // Выбор причины снимает disabled с «Отправить»
    document.addEventListener('change', function (e) {
      if (!root() || root().hidden) return;
      if (e.target && e.target.name === 'report-reason') {
        setError('');
        if (!state.sending) {
          var btn = q('[data-report-confirm]');
          if (btn) btn.disabled = false;
        }
      }
    });

    document.addEventListener('keydown', function (e) {
      if (!root() || root().hidden) return;

      if (e.key === 'Escape') { e.preventDefault(); cancel(); return; }
      if (e.key !== 'Tab') return;

      var items = focusables();
      if (!items.length) return;
      var first = items[0];
      var last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  });

  window.ReportModal = { open: open, close: close };
})();
