/* ============================================
   MESSAGES — единое место для показа сообщений и ошибок API.

   Разметка совпадает с innerMessages из layout'а
   (.message.message--error > .message__title + .message__body),
   поэтому стили плашек общие.

   API:
     Messages.show(text, { type: 'error'|'success'|'warning'|'info', title, timeout })
     Messages.readError(res, fallback)  -> Promise<string>  (текст ошибки из ответа API)
     Messages.fail(res, fallback)       -> Promise<string>  (readError + показать плашку)
     Messages.describe(status, data, raw, fallback) -> string
   ============================================ */
(function (w) {
  'use strict';

  var TYPES = { error: 1, success: 1, warning: 1, info: 1 };
  var DEFAULT_TIMEOUT = 7000;
  var MAX_TOASTS = 4;

  function container() {
    var box = document.querySelector('[data-toast-box]');
    if (box) return box;

    box = document.createElement('div');
    box.className = 'messages messages--toast';
    box.setAttribute('data-toast-box', '');
    box.setAttribute('aria-live', 'polite');
    document.body.appendChild(box);
    return box;
  }

  function dismiss(node) {
    if (node && node.parentNode) node.parentNode.removeChild(node);
  }

  function show(text, opts) {
    text = String(text == null ? '' : text).trim();
    if (!text) return null;

    opts = opts || {};
    var type = TYPES[opts.type] ? opts.type : 'error';
    var key = type + '|' + text;
    var box = container();

    // Одинаковое сообщение не плодим: старое убираем, таймер начинается заново
    Array.prototype.slice.call(box.children).forEach(function (n) {
      if (n.dataset.key === key) dismiss(n);
    });
    while (box.children.length >= MAX_TOASTS) dismiss(box.firstElementChild);

    var node = document.createElement('div');
    node.className = 'message message--' + type;
    node.dataset.key = key;
    node.setAttribute('role', type === 'error' ? 'alert' : 'status');

    if (opts.title) {
      var title = document.createElement('div');
      title.className = 'message__title';
      title.textContent = String(opts.title);      // textContent: без XSS
      node.appendChild(title);
    }

    var body = document.createElement('div');
    body.className = 'message__body';
    body.textContent = text;
    node.appendChild(body);

    node.addEventListener('click', function () { dismiss(node); });
    box.appendChild(node);

    var ms = opts.timeout === undefined ? DEFAULT_TIMEOUT : opts.timeout;
    if (ms > 0) w.setTimeout(function () { dismiss(node); }, ms);

    return node;
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
    collect(data.errors, out);
    if (!out.length) collect(data.error, out);
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

    if (status === 401) return 'Please sign in again.';
    if (status === 403) return 'You are not allowed to do this. Check that your email is verified.';
    if (status === 404) return 'Not found.';
    if (status === 419) return 'Session expired — reload the page and try again.';
    if (status === 429) return 'Too many requests. Try again later.';
    if (status >= 500) return 'Server error. Please try again later.';

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

  w.Messages = {
    show: show,
    dismiss: dismiss,
    fromPayload: fromPayload,
    describe: describe,
    readError: readError,
    fail: fail
  };
})(window);