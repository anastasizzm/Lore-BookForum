/* ============================================
   PROFILE EDIT - validation, avatar picker
   ============================================ */

(function () {
  'use strict';

  // ============================================
  // AVATAR PICKER
  // ============================================
  function initAvatarPicker() {
    document.querySelectorAll('.avatar-picker').forEach(function (picker) {
      var options = picker.querySelectorAll('.avatar-picker__option');

      options.forEach(function (opt) {
        var radio = opt.querySelector('.avatar-picker__radio');
        if (radio && radio.checked) opt.classList.add('is-selected');
      });

      options.forEach(function (opt) {
        opt.addEventListener('click', function () {
          options.forEach(function (o) { o.classList.remove('is-selected'); });
          opt.classList.add('is-selected');
        });
      });
    });
  }

  // ============================================
  // FORM VALIDATION
  // ============================================

  var form = document.getElementById('profileEditForm');
  if (!form) return;

  // Регулярки для проверки букв (любые Unicode-буквы)
  // \p{L} - буквы, \p{M} - диакритические метки, \s - пробелы, ' - апостроф, - дефис
  var NAME_RE     = /^[\p{L}\p{M}\s'\-]+$/u;
  var USERNAME_RE = /^[A-Za-z0-9_.\-]{3,30}$/;

    var RULES = {
    name: {
      required: true,
      maxLength: 64,
      pattern: NAME_RE,
      message: 'Letters, spaces, \' and - only.',
    },
    surname: {
      required: true,
      maxLength: 64,
      pattern: NAME_RE,
      message: 'Letters, spaces, \' and - only.',
    },
    username: {
      required: true,
      pattern: USERNAME_RE,
      message: 'A-Z, a-z, 0-9, . - _ only. 3-30 chars.',
    },
    email: {
      required: true,
      email: true,
      maxLength: 255,
      message: 'Format: name@domain.com',
    },
    bio: {
      required: false,
      maxLength: 500,
      message: 'Max 500 chars.',
    },
    avatar: {
      required: true,
      message: 'Pick an avatar.',
    },
  };

  // --- Helpers ---

  function getField(name) {
    return form.querySelector('[name="' + name + '"]');
  }

  function getErrorEl(name) {
    return form.querySelector('[data-error-for="' + name + '"]');
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

    function setError(name, message) {
        var field = getField(name);
        var errorEl = getErrorEl(name);
        if (field) field.classList.add('form-field__input--invalid');
        if (errorEl) {
        errorEl.innerHTML =
            '<span class="field-error-icon">!</span>' +
            '<span class="field-error-text">' + escapeHtml(message) + '</span>';
        }
    }

  function clearError(name) {
    var field = getField(name);
    var errorEl = getErrorEl(name);
    if (field) field.classList.remove('form-field__input--invalid');
    if (errorEl) errorEl.innerHTML = '';
  }

  // --- Validate one field ---

  function validateField(name) {
    var rule = RULES[name];
    if (!rule) return true;

    // Special case: avatar (radio group)
    if (name === 'avatar') {
      var checked = form.querySelector('input[name="avatar"]:checked');
      if (rule.required && !checked) {
        setError(name, rule.message);
        return false;
      }
      clearError(name);
      return true;
    }

    var field = getField(name);
    if (!field) return true;

    var value = (field.value || '').trim();

    if (rule.required && value === '') {
      setError(name, rule.message);
      return false;
    }
    if (!rule.required && value === '') {
      clearError(name);
      return true;
    }

    if (rule.email) {
      var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRe.test(value)) {
        setError(name, rule.message);
        return false;
      }
    }

    if (rule.pattern && !rule.pattern.test(value)) {
      setError(name, rule.message);
      return false;
    }

    if (rule.maxLength && value.length > rule.maxLength) {
      setError(name, rule.message);
      return false;
    }

    clearError(name);
    return true;
  }

  function validateAll() {
    var ok = true;
    Object.keys(RULES).forEach(function (name) {
      if (!validateField(name)) ok = false;
    });
    return ok;
  }

  // --- Attach handlers ---

    // --- Server errors: render icon + text ---
  Object.keys(RULES).forEach(function (name) {
    var errorEl = getErrorEl(name);
    if (!errorEl) return;
    var text = errorEl.textContent.trim();
    if (text !== '') {
      errorEl.innerHTML =
        '<span class="field-error-icon">!</span>' +
        '<span class="field-error-text">' + escapeHtml(text) + '</span>';
      var field = getField(name);
      if (field) field.classList.add('form-field__input--invalid');
    }
  });

  // --- Submit: block if invalid ---

  form.addEventListener('submit', function (e) {
    if (!validateAll()) {
      e.preventDefault();
      var firstInvalid = form.querySelector('.form-field__input--invalid');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalid.focus();
      }
    }
  });

  // --- Server errors: replace text with icons ---

  Object.keys(RULES).forEach(function (name) {
    var errorEl = getErrorEl(name);
    if (!errorEl) return;
    var text = errorEl.textContent.trim();
    if (text !== '') {
      // На сервере уже отрендерена иконка, но если пришёл просто текст —
      // переводим его в иконку.
      if (!errorEl.querySelector('.field-error-icon')) {
        errorEl.innerHTML =
          '<span class="field-error-icon" title="' +
          escapeHtml(text) +
          '">!</span>';
      }
      var field = getField(name);
      if (field) field.classList.add('form-field__input--invalid');
    }
  });

  // --- Init picker ---
  initAvatarPicker();
})();