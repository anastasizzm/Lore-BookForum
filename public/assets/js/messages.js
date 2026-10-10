(function (w) {
  'use strict';

  var MAX_TOASTS = 10;
  var DEFAULT_TIMEOUT = 7000;
  var LEAVE_MS = 350;

  function svg(inner) {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
           'stroke-linecap="round" stroke-linejoin="round" focusable="false">' + inner + '</svg>';
  }

  var TYPES = {
    error: {
      icon: svg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>')
    },
    warning: {
      icon: svg('<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>')
    },
    success: {
      icon: svg('<circle cx="12" cy="12" r="10"/><path d="M8 12.5l2.5 2.5L16 9.5"/>')
    },
    info: {
      icon: svg('<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>')
    }
  };

  var TITLE_KEY = {
    error:   'js.toast_error',
    warning: 'js.toast_warning',
    success: 'js.toast_success',
    info:    'js.toast_info'
  };

  function tr(key, params) {
    return w.LoreI18n ? w.LoreI18n.t(key, params) : key;
  }

  function titleFor(type) {
    var fallback = type.charAt(0).toUpperCase() + type.slice(1);
    return w.LoreI18n && w.LoreI18n.has(TITLE_KEY[type])
      ? w.LoreI18n.t(TITLE_KEY[type])
      : fallback;
  }

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

  function live(box) {
    return Array.prototype.filter.call(box.children, function (n) {
      return n.classList.contains('toast') && !n.classList.contains('is-leaving');
    });
  }

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
    w.setTimeout(done, LEAVE_MS);
  }

  function closeMessage(event, node) {
    if (event && event.stopPropagation) event.stopPropagation();
    dismiss(node);
  }

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
    var title = opts.title ? String(opts.title) : titleFor(type);
    var key   = type + '|' + text;
    var box   = container();

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
    icon.innerHTML = meta.icon;

    var content = el('div', 'toast__content');
    var heading = el('h2', 'toast__title');
    heading.textContent = title;
    var message = el('p', 'toast__text');
    message.textContent = text;

    content.appendChild(heading);
    content.appendChild(message);
    row.appendChild(icon);
    row.appendChild(content);

    var glow = el('span', 'toast__glow');
    glow.setAttribute('aria-hidden', 'true');

    node.appendChild(row);
    node.appendChild(glow);
    node.addEventListener('click', function (e) { closeMessage(e, node); });

    box.insertBefore(node, box.firstChild);

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

  function showMessage(message, type) {
    return show(message, { type: type === undefined ? 'success' : type });
  }

  function collect(value, out) {
    if (value == null) return;
    if (typeof value === 'string') {
      if (value.trim()) out.push(value.trim());
    } else if (Array.isArray(value)) {
      value.forEach(function (v) { collect(v, out); });
    } else if (typeof value === 'object') {
      if (typeof value.message === 'string' && value.message.trim()) {
        out.push(value.message.trim());
      } else {
        Object.keys(value).forEach(function (k) { collect(value[k], out); });
      }
    }
  }

  function fromPayload(data) {
    if (!data || typeof data !== 'object') return '';
    var out = [];
    if (data.error && typeof data.error === 'object' && data.error.details) {
      collect(data.error.details, out);
    }
    if (!out.length) collect(data.error, out);
    if (!out.length) collect(data.errors, out);
    if (!out.length) collect(data.message, out);
    if (!out.length) collect(data.detail, out);
    if (!out.length) collect(data.title, out);

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

    if (/^\s*</.test(raw || '')) {
      return tr('js.server_error');
    }

    if (fallback) return fallback;

    return tr('js.request_failed', { status: status });
  }

  async function readError(res, fallback) {
    var raw = '';
    var data = null;
    try { raw = await res.text(); } catch (_) { }
    try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }
    return describe(res.status, data, raw, fallback);
  }

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