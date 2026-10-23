"use strict";

/* FAB «+»: открыть/закрыть меню создания. Разметка — partials/fab.php. */
(function () {
  var root = document.querySelector('[data-fab]');
  if (!root) return;

  // На страницах, где угол занят (читалка, форма статьи), кнопку прячем.
  var hideOn = root.getAttribute('data-hide-on');
  if (hideOn && document.querySelector(hideOn)) {
    root.hidden = true;
    return;
  }

  var toggle = root.querySelector('[data-fab-toggle]');
  var menu = root.querySelector('[data-fab-menu]');
  if (!toggle || !menu) return;

  var items = function () { return Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]')); };
  var isOpen = function () { return !menu.hidden; };

  function open(focusFirst) {
    menu.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    if (focusFirst) { var first = items()[0]; if (first) first.focus(); }
  }

  function close(returnFocus) {
    if (!isOpen()) return;
    menu.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');
    if (returnFocus) toggle.focus();
  }

  toggle.addEventListener('click', function (e) {
    e.stopPropagation();
    if (isOpen()) close(false);
    else open(e.detail === 0);          // detail 0 — с клавиатуры: сразу на первый пункт
  });

  document.addEventListener('click', function (e) {
    if (isOpen() && !root.contains(e.target)) close(false);
  });

  document.addEventListener('keydown', function (e) {
    if (!isOpen()) return;
    if (e.key === 'Escape') { close(true); return; }

    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      var list = items();
      var i = list.indexOf(document.activeElement);
      var next = e.key === 'ArrowDown' ? (i + 1) % list.length : (i <= 0 ? list.length - 1 : i - 1);
      e.preventDefault();
      list[next].focus();
    }
  });

  // Фокус ушёл за пределы кнопки (Tab) — закрываем
  root.addEventListener('focusout', function (e) {
    if (!e.relatedTarget || !root.contains(e.relatedTarget)) close(false);
  });

  // Открывающаяся мобильная панель сайдбара не должна оставлять меню висеть
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-burger]')) close(false);
  });

  // Назад из bfcache — меню всегда закрыто
  window.addEventListener('pageshow', function () { close(false); });
})();