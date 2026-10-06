(function (w) {
  'use strict';
  var presets = w.AVATAR_PRESETS || {};

  function emojiOf(raw) {
    raw = String(raw || '');
    return Object.prototype.hasOwnProperty.call(presets, raw) ? presets[raw] : null;
  }

  function initialsOf(user) {
    var n = String((user && user.name) || '').trim();
    var s = String((user && user.surname) || '').trim();
    var v = (n.charAt(0) + s.charAt(0)).toUpperCase();
    if (!v) v = String((user && user.username) || '').trim().charAt(0).toUpperCase() || '?';
    return v;
  }

  /** Заполняет готовый <div class="avatar"> без innerHTML. */
  function fill(el, user, initialsOverride) {
    if (!el) return;
    var emoji = emojiOf(user && user.avatar);
    var span = document.createElement('span');
    span.textContent = emoji || initialsOverride || initialsOf(user);
    el.classList.toggle('avatar--emoji', !!emoji);
    el.replaceChildren(span);
  }

  w.Avatar = { emojiOf: emojiOf, initialsOf: initialsOf, fill: fill };
})(window);