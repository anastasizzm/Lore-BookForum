"use strict";

/* ============================================
   Book details page
   ============================================ */
(function () {
  // Toggle bookmark (Save book)
  var bookmark = document.querySelector('[data-toggle-bookmark]');
  if (bookmark) {
    bookmark.addEventListener('click', function () {
      bookmark.classList.toggle('is-active');
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
})();