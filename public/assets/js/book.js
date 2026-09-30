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

  // Start reading — пока заглушка
  var startReading = document.querySelector('[data-start-reading]');
  if (startReading) {
    startReading.addEventListener('click', function () {
      // TODO: добавить логику "начать читать"
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
      // TODO: отправить лайк на сервер
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
    // TODO: отправить ответ на сервер
  });
})();