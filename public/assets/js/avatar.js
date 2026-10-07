"use strict";

/* ============================================
   Аватар: разбор значения users.avatar.

   В настройках профиля аватар — это НЕ загруженная картинка, а пресет
   ('cat', 'dog', …), который раньше все рендерили как
   <img src="/uploads/avatars/cat"> — файла нет, 404, и вместо аватара
   показывались инициалы. Здесь значение разбирается на три случая:
     {type:'emoji'}  — пресет из настроек, рисуем эмодзи;
     {type:'image'}  — настоящий файл, рисуем <img>;
     {type:'none'}   — '' / 'default' / неизвестный id, остаются инициалы.

   Используют book.js и card-feed.js; серверный рендер делает то же
   самое в src/partials/avatar.php (карта пресетов — avatar-presets.php).
   ============================================ */
window.LoreAvatar = (function () {
  var DIR = '/uploads/avatars/';
  // Держать в sync с src/partials/avatar-presets.php
  var PRESETS = {
    cat: '🐱',
    dog: '🐶',
    fox: '🦊',
    owl: '🦉',
    robot: '🤖',
    star: '⭐',
    book: '📚',
  };

  function parse(raw) {
    var v = String(raw || '');

    // Пусто или 'default' — аватара нет
    if (v === '' || v === 'default') return { type: 'none' };

    // Путь вида /uploads/avatars/{id} приходит из data-me-avatar и шаблонов
    if (v.lastIndexOf(DIR, 0) === 0) v = v.slice(DIR.length);

    // Пресет из настроек профиля
    if (Object.prototype.hasOwnProperty.call(PRESETS, v)) {
      return { type: 'emoji', emoji: PRESETS[v] };
    }

    // Ни "/", ни "." — неизвестный ид пресета: не выдумываем битую картинку
    if (v.indexOf('/') === -1 && v.indexOf('.') === -1) return { type: 'none' };

    // Настоящий файл: уже URL/путь либо имя в папке аватаров
    return {
      type: 'image',
      src: v.charAt(0) === '/' || /^[a-z][a-z0-9+.-]*:/i.test(v) ? v : DIR + v,
    };
  }

  return { parse: parse, PRESETS: PRESETS };
})();
