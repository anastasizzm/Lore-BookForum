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