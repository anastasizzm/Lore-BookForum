(function (w) {
  'use strict';

  var MAX_TOASTS = 10;         // только предохранитель от бесконечной стопки
  var DEFAULT_TIMEOUT = 7000;
  var LEAVE_MS = 350;          // длительность анимации закрытия

  function svg(inner) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
           'stroke-linecap="round" stroke-linejoin="round" focusable="false">' + inner + '</svg>';
  }

  // Иконки — константы (не пользовательские данные), поэтому innerHTML здесь безопасен
  var TYPES = {
    error: {
      title: 'Error',
      icon: svg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>')
    },
    warning: {
      title: 'Warning',
      icon: svg('<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>')
    },
    success: {
      title: 'Success',
      icon: svg('<circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9.5"/>')
    },
    info: {
      title: 'Info',
      icon: svg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>')
    }
  };

  function has(obj, key) {
    return Object.prototype.hasOwnProperty.call(obj, key);
  }

  function el(tag, className) {
    var node = document.createElement(tag);
    node.className = className;
    return node;
  }

  function container() {
    var box = document.querySelector('[data-toast-box]');
    if (box) return box;

    box = el('div', 'toast-box');
    box.setAttribute('data-toast-box', '');
    box.setAttribute('aria-live', 'polite');
    document.body.appendChild(box);
    return box;
  }

  // Плашки, которые ещё не закрываются. Порядок как в DOM: первая — самая новая.
  function live(box) {
    return Array.prototype.filter.call(box.children, function (n) {
      return n.classList.contains('toast') && !n.classList.contains('is-leaving');
    });
  }

  /* ---------- Закрытие ---------- */
  function dismiss(node) {
    if (!node || !node.parentNode || node.classList.contains('is-leaving')) return;

    node.classList.add('is-leaving');
    var removed = false;
    var done = function () {
      if (removed) return;
      removed = true;
      if (node.parentNode) node.parentNode.removeChild(node);
    };
    node.addEventListener('transitionend', done);
    w.setTimeout(done, LEAVE_MS);   // запасной вариант: нет transition / reduced motion
  }

  /** onclick-совместимая обёртка, как closeMessage(event, this) в Django-версии. */
  function closeMessage(event, node) {
    if (event && event.stopPropagation) event.stopPropagation();
    dismiss(node);
  }

  /* ---------- Показ ---------- */
  function show(text, opts) {
    text = String(text == null ? '' : text).trim();
    if (!text) return null;

    opts = opts || {};
    var type = opts.type === undefined ? 'error' : opts.type;
    if (!has(TYPES, type)) {
      console.warn('Invalid message type: ' + type + ' (shown as "info")');
      type = 'info';
    }

    var meta  = TYPES[type];
    var title = opts.title ? String(opts.title) : meta.title;
    var key   = type + '|' + text;
    var box   = container();

    // Не больше MAX_TOASTS: самая старая (последняя в стопке) убирается автоматически
    var items = live(box);
    while (items.length >= MAX_TOASTS) {
      var oldest = items.pop();
      console.info(oldest.dataset.title + ' message was automatically removed: ' + oldest.dataset.text);
      dismiss(oldest);
    }

    var node = el('div', 'toast toast--' + type);
    node.setAttribute('role', type === 'error' ? 'alert' : 'status');
    node.dataset.key = key;
    node.dataset.title = title;
    node.dataset.text = text;

    var row = el('div', 'toast__row');

    var icon = el('span', 'toast__icon');
    icon.setAttribute('aria-hidden', 'true');
    icon.innerHTML = meta.icon;                // константа, не пользовательские данные

    var content = el('div', 'toast__content');
    var heading = el('h2', 'toast__title');
    heading.textContent = title;
    var message = el('p', 'toast__text');
    message.textContent = text;                // textContent: без XSS

    content.appendChild(heading);
    content.appendChild(message);
    row.appendChild(icon);
    row.appendChild(content);

    var glow = el('span', 'toast__glow');
    glow.setAttribute('aria-hidden', 'true');

    node.appendChild(row);
    node.appendChild(glow);
    node.addEventListener('click', function (e) { closeMessage(e, node); });

    // Новая плашка — наверх стопки
    box.insertBefore(node, box.firstChild);

    // Автоскрытие; пока курсор на плашке, таймер стоит
    var ms = opts.timeout === undefined ? DEFAULT_TIMEOUT : opts.timeout;
    if (ms > 0) {
      var timer = null;
      var start = function () { timer = w.setTimeout(function () { dismiss(node); }, ms); };
      var stop  = function () { w.clearTimeout(timer); };
      node.addEventListener('mouseenter', stop);
      node.addEventListener('mouseleave', start);
      start();
    }

    return node;
  }

  /** Совместимо с Django-версией: showMessage(message, type = 'success'). */
  function showMessage(message, type) {
    return show(message, { type: type === undefined ? 'success' : type });
  }

  /* ---------- Разбор ответа API ---------- */

  // Складывает в out все строки-сообщения из строки / массива / объекта
  function collect(value, out) {
    if (value == null) return;
    if (typeof value === 'string') {
      if (value.trim()) out.push(value.trim());
    } else if (Array.isArray(value)) {
      value.forEach(function (v) { collect(v, out); });
    } else if (typeof value === 'object') {
      if (typeof value.message === 'string' && value.message.trim()) {
        out.push(value.message.trim());               // {code, message}
      } else {
        Object.keys(value).forEach(function (k) { collect(value[k], out); });
      }
    }
  }

  /** Текст ошибки из JSON-тела: errors / error / message / detail / title. */
  function fromPayload(data) {
    if (!data || typeof data !== 'object') return '';

    var out = [];
    // Формат API: { error: { code, message, details? } }.
    // Для ошибок валидации важнее details (по полям), чем общий message.
    if (data.error && typeof data.error === 'object' && data.error.details) {
      collect(data.error.details, out);
    }
    if (!out.length) collect(data.error, out);
    if (!out.length) collect(data.errors, out);
    if (!out.length) collect(data.message, out);
    if (!out.length) collect(data.detail, out);
    if (!out.length) collect(data.title, out);

    // убираем дубли, сохраняя порядок
    var seen = {};
    return out.filter(function (s) {
      if (seen[s]) return false;
      seen[s] = true;
      return true;
    }).join('\n');
  }

  function describe(status, data, raw, fallback) {
    var msg = fromPayload(data);
    if (msg) return msg;

    // Вместо JSON пришёл HTML (PHP-ошибка, страница-заглушка)
    if (/^\s*</.test(raw || '')) return 'Server error. Please try again later.';

    // Текст ошибки приходит от API; свои формулировки не придумываем
    return fallback || ('Request failed (HTTP ' + status + ').');
  }

  /** Читает тело неуспешного ответа и возвращает человеческий текст ошибки. */
  async function readError(res, fallback) {
    var raw = '';
    var data = null;
    try { raw = await res.text(); } catch (_) { /* тело недоступно */ }
    try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
    return describe(res.status, data, raw, fallback);
  }

  /** readError + показать плашку. Возвращает текст ошибки. */
  async function fail(res, fallback) {
    var text = await readError(res, fallback);
    show(text, { type: 'error' });
    return text;
  }

  w.showMessage  = showMessage;
  w.closeMessage = closeMessage;
  w.Messages = {
    show: show,
    dismiss: dismiss,
    close: closeMessage,
    fromPayload: fromPayload,
    describe: describe,
    readError: readError,
    fail: fail
  };
})(window);