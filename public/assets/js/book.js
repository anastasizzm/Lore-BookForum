"use strict";

/* ============================================
   Book details page
   ============================================ */
(function () {
  // Toggle bookmark (Save book)
  var bookmark = document.querySelector('[data-toggle-bookmark]');
  if (bookmark) {
    bookmark.setAttribute('aria-pressed', 'false');
    bookmark.addEventListener('click', function () {
      var active = bookmark.classList.toggle('is-active');
      bookmark.setAttribute('aria-pressed', String(active));
    });
  }

  // Start / Resume / Read again — пока заглушка
  var startReading = document.querySelector('[data-start-reading]');
  if (startReading) {
    startReading.addEventListener('click', function () {
      var status = startReading.dataset.readingStatus; // new | in_progress | finished
      // TODO: new -> открыть с первой главы
      //       in_progress -> открыть с сохранённого места
      //       finished -> сбросить прогресс и открыть с начала
    });
  }

  // Click to Rate
  var rate = document.querySelector('[data-rate]');
  if (rate) {
    var stars = rate.querySelectorAll('[data-rate-value]');
    var current = 0; // TODO: подставить оценку пользователя, если она уже есть

    var paint = function (value) {
      stars.forEach(function (star, i) {
        star.classList.toggle('is-filled', i < value);
        star.setAttribute('aria-checked', String(current > 0 && i + 1 === current));
      });
    };

    stars.forEach(function (star) {
      var value = Number(star.dataset.rateValue);

      // Предпросмотр при наведении
      star.addEventListener('mouseenter', function () {
        paint(value);
      });

      // Выбор оценки; повторный клик по той же звезде снимает оценку
      star.addEventListener('click', function () {
        current = current === value ? 0 : value;
        paint(current);
        // TODO: отправить оценку на сервер (current, 0 = снять оценку)
      });
    });

    // Убрали курсор: возвращаем выбранную оценку
    rate.addEventListener('mouseleave', function () {
      paint(current);
    });

    paint(current);
  }

  // Tabs: Annotation / Table of contents
  var tabs = document.querySelectorAll('.book-tabs-panel [data-row-target]');
  var panels = document.querySelectorAll('.book-tabs-panel__content[data-row]');

  if (tabs.length && panels.length) {
    tabs.forEach(function (tab) {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        var target = tab.dataset.rowTarget;

        tabs.forEach(function (t) {
          t.classList.toggle('is-active', t === tab);
        });

        panels.forEach(function (panel) {
          panel.hidden = panel.dataset.row !== target;
        });
      });
    });
  }

  // Комментарии: лайк и ответ (делегирование событий)
  document.addEventListener('click', function (e) {
    // Лайк комментария
    var likeBtn = e.target.closest('[data-comment-like]');
    if (likeBtn) {
      var countEl = likeBtn.querySelector('[data-comment-like-count]');
      var liked = likeBtn.classList.toggle('is-liked');
      likeBtn.setAttribute('aria-pressed', String(liked));
      if (countEl) {
        countEl.textContent = Math.max(0, parseInt(countEl.textContent, 10) + (liked ? 1 : -1));
      }
      // TODO: отправить лайк на сервер (POST/DELETE /api/posts/{postId}/like)
      return;
    }

    // Показать/скрыть форму ответа
    var replyBtn = e.target.closest('[data-reply-toggle]');
    if (replyBtn) {
      var form = replyBtn.closest('.comment-card__content').querySelector('[data-reply-form]');
      var open = form.hidden;
      form.hidden = !open;
      replyBtn.setAttribute('aria-expanded', String(open));
      if (open) form.querySelector('input').focus();
    }
  });

  // Post активна, только когда есть текст
  document.addEventListener('input', function (e) {
    var input = e.target.closest('.comment-reply-form__input');
    if (!input) return;
    input.closest('form').querySelector('.comment-reply-form__submit').disabled =
      input.value.trim() === '';
  });

  // Отправка ответа (пока только локально)
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('[data-reply-form]');
    if (!form) return;
    e.preventDefault();

    var input = form.querySelector('input');
    var text = input.value.trim();
    if (text === '') return;

    var template = document.getElementById('reply-template');
    if (!template) return;

    var reply = template.content.firstElementChild.cloneNode(true);
    reply.querySelector('.comment-reply__text').textContent = text; // textContent: без XSS

    var content = form.closest('.comment-card__content');
    content.querySelector('[data-replies]').appendChild(reply);

    input.value = '';
    form.querySelector('.comment-reply-form__submit').disabled = true;
    form.hidden = true;
    content.querySelector('[data-reply-toggle]').setAttribute('aria-expanded', 'false');
    // TODO: отправить ответ на сервер (POST /api/posts/{postId})
  });

  // ===== Отправка нового комментария: POST /api/posts =====
  // Контракт: urlencoded (content, publicationId, csrf-поле)
  //   успех: 201 {"createdId": N}
  //   ошибка: не-201, JSON с errors / message
  var commentForm = document.querySelector('[data-comment-form]');
  if (commentForm) {
    var cInput  = commentForm.querySelector('input[name="content"]');
    var cError  = commentForm.querySelector('[data-comment-error]');
    var cStatus = commentForm.querySelector('[data-comment-status]');
    var COMMENT_MAX = 2000;
    var sending = false;

    var setMsg = function (el, text) {
      el.textContent = text;
      el.hidden = text === '';
    };

    var resetMsgs = function () {
      setMsg(cError, '');
      setMsg(cStatus, '');
      cInput.setAttribute('aria-invalid', 'false');
    };

    var fail = function (msg) {
      setMsg(cError, msg);
      cInput.setAttribute('aria-invalid', 'true');
    };

    cInput.addEventListener('input', resetMsgs);

    commentForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      if (sending) return;
      resetMsgs();

      var text = cInput.value.trim();
      if (text === '') return fail('Comment cannot be empty');
      if (text.length > COMMENT_MAX) return fail('Max length is ' + COMMENT_MAX + ' characters');
      if (!Number(commentForm.elements.publicationId.value)) return fail('publicationId is missing');

      var body = new URLSearchParams(new FormData(commentForm));
      body.set('content', text);

      sending = true;
      cInput.disabled = true;

      try {
        var res = await fetch(commentForm.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: body
        });

        var data = null;
        try { data = await res.json(); } catch (_) { /* не JSON */ }
        console.log('POST', commentForm.action, res.status, data); // для отладки бэка

        if (res.status === 201) {
          setMsg(cStatus, 'Comment sent' + (data && data.createdId ? ' (id ' + data.createdId + ')' : ''));
          cInput.value = '';

          var counter = document.querySelector('[data-comments-count]');
          if (counter) counter.textContent = (parseInt(counter.textContent, 10) || 0) + 1;

          // Хук для будущей вставки карточки в список
          commentForm.dispatchEvent(new CustomEvent('comment:created', {
            bubbles: true,
            detail: { id: data && data.createdId, content: text }
          }));
        } else {
          var msg = '';
          if (data && data.errors) msg = Object.values(data.errors).flat().join('\n');
          if (!msg && data && data.message) msg = data.message;
          if (!msg) {
            msg = res.status === 403 ? 'Forbidden (verify email / CSRF?)'
                : res.status === 401 ? 'Please sign in again'
                : 'Failed to send comment (HTTP ' + res.status + ')';
          }
          fail(msg);
        }
      } catch (err) {
        fail('Network error. Try again.');
      } finally {
        sending = false;
        cInput.disabled = false;
        cInput.focus();
      }
    });
  }
})();