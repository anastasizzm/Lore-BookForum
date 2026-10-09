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
  // Держать в sync с src/partials/avatar-presets.php (icon + bg)
  var PRESETS = {
    cat:   { icon: '🐱', bg: '#FFE1C9' },
    dog:   { icon: '🐶', bg: '#F3DFC4' },
    fox:   { icon: '🦊', bg: '#FFD2B0' },
    owl:   { icon: '🦉', bg: '#E4D3C3' },
    robot: { icon: '🤖', bg: '#D7E2F1' },
    star:  { icon: '⭐', bg: '#FFF2B8' },
    book:  { icon: '📚', bg: '#DCD4F0' },
  };

  function parse(raw) {
    var v = String(raw || '');

    // Пусто или 'default' — аватара нет
    if (v === '' || v === 'default') return { type: 'none' };

    // Путь вида /uploads/avatars/{id} приходит из data-me-avatar и шаблонов
    if (v.lastIndexOf(DIR, 0) === 0) v = v.slice(DIR.length);

    // Пресет из настроек профиля
    if (Object.prototype.hasOwnProperty.call(PRESETS, v)) {
      return { type: 'emoji', emoji: PRESETS[v].icon, bg: PRESETS[v].bg };
    }

    // Ни "/", ни "." — неизвестный ид пресета: не выдумываем битую картинку
    if (v.indexOf('/') === -1 && v.indexOf('.') === -1) return { type: 'none' };

    // Настоящий файл: уже URL/путь либо имя в папке аватаров
    return {
      type: 'image',
      src: v.charAt(0) === '/' || /^[a-z][a-z0-9+.-]*:/i.test(v) ? v : DIR + v,
    };
  }

  /**
   * Красит .avatar под результат parse(): для пресета ставит фон
   * (через CSSOM, не inline-атрибутом — безопасно для CSP),
   * для остальных случаев сбрасывает.
   */
  function paint(el, parsed) {
    if (!el) return;
    if (parsed && parsed.type === 'emoji' && parsed.bg) {
      el.classList.add('avatar--preset');
      el.style.setProperty('--avatar-bg', parsed.bg);
    } else {
      el.classList.remove('avatar--preset');
      el.style.removeProperty('--avatar-bg');
    }
  }

  return { parse: parse, paint: paint, PRESETS: PRESETS };
})();