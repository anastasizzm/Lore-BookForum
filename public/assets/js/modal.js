"use strict";

(function (global) {
  function defaults() {
    var t = global.LoreI18n ? global.LoreI18n.t : function (k) { return k; };
    return {
      title:       t('confirm.title'),
      message:     '',
      confirmText: t('confirm.ok'),
      cancelText:  t('cancel'),
      danger:      false
    };
  }

  var els = null;
  var pending = null;
  var lastFocus = null;

  function dialogMarkup() {
    return '' +
      '<div class="modal__backdrop" data-modal-dismiss></div>' +
      '<div class="modal__dialog" role="alertdialog" aria-modal="true"' +
           ' aria-labelledby="confirmModalTitle" aria-describedby="confirmModalText">' +
        '<h2 class="modal__title" id="confirmModalTitle" data-modal-title></h2>' +
        '<p class="modal__text" id="confirmModalText" data-modal-text></p>' +
        '<div class="modal__actions">' +
          '<button type="button" class="btn btn--secondary" data-modal-cancel></button>' +
          '<button type="button" class="btn btn--primary" data-modal-confirm></button>' +
        '</div>' +
      '</div>';
  }

  function ensureRoot() {
    if (els && els.root.isConnected) return els;

    var root = document.querySelector('[data-confirm-modal]');
    if (!root) {
      root = document.createElement('div');
      root.className = 'modal';
      root.setAttribute('data-confirm-modal', '');
      root.hidden = true;
      root.innerHTML = dialogMarkup();
      document.body.appendChild(root);
    } else if (!root.querySelector('.modal__dialog')) {
      root.innerHTML = dialogMarkup();
    }

    els = {
      root: root,
      title: root.querySelector('[data-modal-title]'),
      text: root.querySelector('[data-modal-text]'),
      ok: root.querySelector('[data-modal-confirm]'),
      cancel: root.querySelector('[data-modal-cancel]')
    };

    root.addEventListener('click', function (e) {
      if (!pending) return;
      if (e.target.closest('[data-modal-dismiss]')) finish(false);
      else if (e.target.closest('[data-modal-cancel]')) finish(false);
      else if (e.target.closest('[data-modal-confirm]')) finish(true);
    });

    return els;
  }

  function focusables() {
    return Array.prototype.slice.call(
      els.root.querySelectorAll('button, [href], input, select, textarea, [tabindex]')
    ).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
  }

  function onKeydown(e) {
    if (!pending) return;
    if (e.key === 'Escape') { e.preventDefault(); finish(false); return; }
    if (e.key === 'Tab') {
      var items = focusables();
      if (!items.length) return;
      var first = items[0];
      var last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }

  function restoreFocus() {
    var target = lastFocus;
    lastFocus = null;
    if (target && target.isConnected && target.offsetParent !== null) {
      try { target.focus(); return; } catch (_) {}
    }
    var fallback = document.querySelector('[data-settings-toggle]');
    if (fallback) { try { fallback.focus(); } catch (_) {} }
  }

  function finish(result) {
    if (!pending) return;
    var resolve = pending.resolve;
    pending = null;
    els.root.hidden = true;
    document.body.classList.remove('is-modal-open');
    document.removeEventListener('keydown', onKeydown, true);
    restoreFocus();
    resolve(result);
  }

  function normalize(options) {
    var DEFAULTS = defaults();
    var opts = options || {};
    return {
      title:       (typeof opts.title === 'string' && opts.title !== '') ? opts.title : DEFAULTS.title,
      message:     typeof opts.message === 'string' ? opts.message
                 : (typeof opts.text === 'string' ? opts.text : DEFAULTS.message),
      confirmText: (typeof opts.confirmText === 'string' && opts.confirmText !== '') ? opts.confirmText : DEFAULTS.confirmText,
      cancelText:  (typeof opts.cancelText === 'string' && opts.cancelText !== '') ? opts.cancelText : DEFAULTS.cancelText,
      danger:      opts.danger === undefined ? DEFAULTS.danger : !!opts.danger
    };
  }

  function open(options) {
    var opts = normalize(options);
    ensureRoot();
    if (pending) finish(false);

    els.title.textContent = opts.title;
    els.text.textContent = opts.message;
    els.text.hidden = opts.message === '';
    els.cancel.textContent = opts.cancelText;
    els.ok.textContent = opts.confirmText;
    els.ok.classList.toggle('btn--danger', opts.danger);
    els.ok.classList.toggle('btn--primary', !opts.danger);

    lastFocus = document.activeElement;
    els.root.hidden = false;
    document.body.classList.add('is-modal-open');
    document.addEventListener('keydown', onKeydown, true);
    els.cancel.focus();

    return new Promise(function (resolve) { pending = { resolve: resolve }; });
  }

  function runAction(el) {
    var type = (el.getAttribute('type') || '').toLowerCase();
    var isSubmit = (el.tagName === 'BUTTON' && (type === 'submit' || type === ''))
                || (el.tagName === 'INPUT' && (type === 'submit' || type === 'image'));
    if (isSubmit) {
      var form = el.form || el.closest('form');
      if (form) { form.submit(); return; }
    }
    if (el.tagName === 'A' && el.getAttribute('href')) {
      global.location.href = el.getAttribute('href');
      return;
    }
    el.dispatchEvent(new CustomEvent('confirm:accepted', { bubbles: true }));
  }

  document.addEventListener('click', function (e) {
    var trigger = e.target && e.target.closest ? e.target.closest('[data-confirm]') : null;
    if (!trigger) return;
    e.preventDefault();
    var DEFAULTS = defaults();
    open({
      title:       trigger.getAttribute('data-confirm-title') || DEFAULTS.title,
      message:     trigger.getAttribute('data-confirm') || '',
      confirmText: trigger.getAttribute('data-confirm-ok') || DEFAULTS.confirmText,
      cancelText:  trigger.getAttribute('data-confirm-cancel') || DEFAULTS.cancelText,
      danger:      trigger.hasAttribute('data-confirm-danger')
    }).then(function (confirmed) { if (confirmed) runAction(trigger); });
  });

  global.ConfirmModal = { confirm: open, open: open };
})(window);