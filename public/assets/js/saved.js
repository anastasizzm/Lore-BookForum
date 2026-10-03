"use strict";

/* ============================================
   Страница Saved: после снятия закладки карточка исчезает из списка.
   Событие save:changed приходит из app.js (POST/DELETE /api/{books|articles}/{id}/save)
   ============================================ */
(function () {
  var grid = document.querySelector('[data-saved-grid]');
  if (!grid) return;

  document.addEventListener('save:changed', function (e) {
    if (!e.detail || e.detail.saved) return; // интересует только снятие закладки

    var card = e.target.closest('.card-book');
    if (!card || !grid.contains(card)) return;
    card.remove();

    var counter = document.querySelector('[data-saved-count]');
    if (counter) {
      counter.textContent = Math.max(0, (parseInt(counter.textContent, 10) || 0) - 1);
    }

    if (!grid.querySelector('.card-book')) {
      grid.style.display = 'none'; // .grid-books задаёт display, hidden не сработает
      var empty = document.querySelector('[data-saved-empty]');
      if (empty) empty.hidden = false;
    }
  });
})();