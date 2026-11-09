/**
 * dashboard.js — пустое состояние админ-дашборда после проверки прав.
 *
 * Карточки рендерятся скрытыми (hidden) с data-requires, а permissions.js
 * показывает их по правилам GET /api/system/rules. Здесь только следим,
 * чтобы при отсутствии доступных разделов появился текст, а не пустая сетка.
 */
(function () {
  'use strict';

  function sync() {
    var cards = document.querySelectorAll('[data-admin-card]');
    if (!cards.length) return;

    var visible = 0;
    Array.prototype.forEach.call(cards, function (card) {
      if (!card.hidden) visible++;
    });

    var empty = document.querySelector('[data-admin-dashboard-empty]');
    if (empty) empty.hidden = visible > 0;
  }

  document.addEventListener('permissions:applied', function (e) {
    var target = e.target;
    if (target && target.closest && target.closest('[data-admin-card]')) sync();
  });
})();
