/* ============================================
   PROFILE EDIT - validation, avatar picker, save via PUT API
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

  function clearAllErrors() {
    Object.keys(RULES).forEach(clearError);
  }

  // --- Validate one field ---

  function validateField(name) {
    var rule = RULES[name];
    if (!rule) return true;

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

  // --- Attach handlers on fields ---

  Object.keys(RULES).forEach(function (name) {
    if (name === 'avatar') {
      form.querySelectorAll('input[name="avatar"]').forEach(function (radio) {
        radio.addEventListener('change', function () { validateField('avatar'); });
      });
      return;
    }
    var field = getField(name);
    if (!field) return;

    field.addEventListener('blur', function () { validateField(name); });
    field.addEventListener('input', function () {
      if (field.classList.contains('form-field__input--invalid')) {
        validateField(name);
      }
    });
  });

  // --- Server errors on load (from PHP $errors) ---

  Object.keys(RULES).forEach(function (name) {
    var errorEl = getErrorEl(name);
    if (!errorEl) return;
    var text = errorEl.textContent.trim();
    if (text !== '') {
      if (!errorEl.querySelector('.field-error-icon')) {
        errorEl.innerHTML =
          '<span class="field-error-icon">!</span>' +
          '<span class="field-error-text">' + escapeHtml(text) + '</span>';
      }
      var field = getField(name);
      if (field) field.classList.add('form-field__input--invalid');
    }
  });

  // --- Save via PUT API ---

  function extractUserId() {
    var m = window.location.pathname.match(/\/users\/(\d+)\//);
    return m ? m[1] : null;
  }

  function renderServerErrors(errors) {
    if (!errors || typeof errors !== 'object') return;

    Object.keys(errors).forEach(function (field) {
      var msgs = errors[field];
      var message = Array.isArray(msgs) ? msgs[0] : String(msgs);
      if (RULES[field]) {
        setError(field, message);
      }
    });

    var firstInvalid = form.querySelector('.form-field__input--invalid');
    if (firstInvalid) {
      firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstInvalid.focus();
    }
  }

    form.addEventListener('submit', function (e) {
    e.preventDefault();
    clearAllErrors();

    if (!validateAll()) {
      var firstInvalid = form.querySelector('.form-field__input--invalid');
      if (firstInvalid) {
        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalid.focus();
      }
      return;
    }

    var userId = extractUserId();
    if (!userId) {
      alert('Cannot determine user id from URL.');
      return;
    }

    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn ? submitBtn.textContent : 'Save changes';
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';
    }

    var formData = new FormData(form);
    formData.delete('_method');
    var csrfValue = window.LoreCsrf ? LoreCsrf.token() : '';
    if (csrfValue) formData.set('_token', csrfValue);

    // application/x-www-form-urlencoded
    var body = new URLSearchParams(formData).toString();

    fetch('/api/users/' + userId + '/edit', {
      method: 'PUT',
      body: body,
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
      },
    })
      .then(function (response) {
        if (response.status >= 200 && response.status < 300) {
          window.location.href = '/users/' + userId;
          return null;
        }

        if (response.status === 400 || response.status === 422) {
          return response.json()
            .then(function (data) {
              var errors = data && data.errors ? data.errors : data;
              renderServerErrors(errors);
              if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
              }
            })
            .catch(function () {
              throw new Error('Invalid JSON in error response');
            });
        }

        throw new Error('HTTP ' + response.status);
      })
      .catch(function (err) {
        console.error('[profile-edit] save failed:', err);
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
        }
        alert('Something went wrong. Please try again.');
      });
  });

  // --- Init picker ---
  initAvatarPicker();
})();