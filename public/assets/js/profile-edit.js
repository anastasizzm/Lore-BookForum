(function () {
  'use strict';

  var t = function (key, params) {
    return window.LoreI18n ? LoreI18n.t(key, params) : key;
  };

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

  var NAME_RE     = /^[\p{L}\p{M}\s'\-]+$/u;
  var USERNAME_RE = /^[A-Za-z0-9_.\-]{3,30}$/;

  var RULES = {
    name:     { required: true,  maxLength: 64,  pattern: NAME_RE,     msgKey: 'js.rule_name' },
    surname:  { required: true,  maxLength: 64,  pattern: NAME_RE,     msgKey: 'js.rule_name' },
    username: { required: true,  pattern: USERNAME_RE,                 msgKey: 'js.rule_username' },
    email:    { required: true,  email: true, maxLength: 255,          msgKey: 'js.rule_email' },
    bio:      { required: false, maxLength: 500,                       msgKey: 'js.rule_bio' },
    avatar:   { required: true,                                        msgKey: 'js.rule_avatar' },
  };

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function notify(text, type) {
    if (window.Messages) window.Messages.show(text, { type: type || 'error' });
    else console.warn(text);
  }

  function extractUserId() {
    var m = window.location.pathname.match(/\/users\/(\d+)\//);
    return m ? m[1] : null;
  }

  function initForm(form) {
    var endpoint = form.getAttribute('data-endpoint');
    if (!endpoint) { console.warn('[profile-edit] form without data-endpoint — skipped'); return; }

    var fieldErrors = {};
    function getField(name)   { return form.querySelector('[name="' + name + '"]'); }
    function getErrorEl(name) { return form.querySelector('[data-error-for="' + name + '"]'); }

    function showFieldErrors() {
      var list = Object.keys(fieldErrors).map(function (k) { return fieldErrors[k]; }).filter(Boolean);
      if (list.length) notify(list.join('\n'));
    }

    function setError(name, message) {
      var field = getField(name);
      var errorEl = getErrorEl(name);
      fieldErrors[name] = message;
      if (field) field.classList.add('form-field__input--invalid');
      if (errorEl) {
        errorEl.innerHTML = '<span class="field-error-icon" title="' +
          escapeHtml(message) + '">!</span>';
      }
    }

    function clearError(name) {
      var field = getField(name);
      var errorEl = getErrorEl(name);
      delete fieldErrors[name];
      if (field) field.classList.remove('form-field__input--invalid');
      if (errorEl) errorEl.innerHTML = '';
    }

    function clearAllErrors() { Object.keys(RULES).forEach(clearError); }

    function validateField(name) {
      var rule = RULES[name];
      if (!rule) return true;

      if (name === 'avatar') {
        var checked = form.querySelector('input[name="avatar"]:checked');
        if (rule.required && !checked) { setError(name, t(rule.msgKey)); return false; }
        clearError(name);
        return true;
      }

      var field = getField(name);
      if (!field) return true;

      var value = (field.value || '').trim();
      if (rule.required && value === '') { setError(name, t(rule.msgKey)); return false; }
      if (!rule.required && value === '') { clearError(name); return true; }

      if (rule.email) {
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(value)) { setError(name, t(rule.msgKey)); return false; }
      }
      if (rule.pattern && !rule.pattern.test(value))       { setError(name, t(rule.msgKey)); return false; }
      if (rule.maxLength && value.length > rule.maxLength) { setError(name, t(rule.msgKey)); return false; }

      clearError(name);
      return true;
    }

    function validateAll() {
      var ok = true;
      Object.keys(RULES).forEach(function (name) { if (!validateField(name)) ok = false; });
      return ok;
    }

    Object.keys(RULES).forEach(function (name) {
      if (name === 'avatar') {
        form.querySelectorAll('input[name="avatar"]').forEach(function (radio) {
          radio.addEventListener('change', function () { validateField('avatar'); });
        });
        return;
      }
      var field = getField(name);
      if (!field) return;
      field.addEventListener('blur',  function () { validateField(name); });
      field.addEventListener('input', function () {
        if (field.classList.contains('form-field__input--invalid')) validateField(name);
      });
    });

    var loadErrors = [];
    Object.keys(RULES).forEach(function (name) {
      var errorEl = getErrorEl(name);
      if (!errorEl) return;
      var icon = errorEl.querySelector('.field-error-icon');
      var text = ((icon && icon.getAttribute('title')) || errorEl.textContent).trim();
      if (text !== '' || icon) {
        if (text !== '') loadErrors.push(text);
        if (!icon) {
          errorEl.innerHTML = '<span class="field-error-icon" title="' +
            escapeHtml(text) + '">!</span>';
        }
        var field = getField(name);
        if (field) field.classList.add('form-field__input--invalid');
      }
    });
    if (loadErrors.length) notify(loadErrors.join('\n'));

    function renderServerErrors(errors) {
      if (!errors || typeof errors !== 'object') return;
      Object.keys(errors).forEach(function (field) {
        var msgs = errors[field];
        var first = Array.isArray(msgs) ? msgs[0] : msgs;
        var message = first && typeof first === 'object' ? (first.message || '') : String(first);
        if (RULES[field]) setError(field, message);
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
        showFieldErrors();
        var firstInvalid = form.querySelector('.form-field__input--invalid');
        if (firstInvalid) {
          firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
          firstInvalid.focus();
        }
        return;
      }

      var userId = extractUserId();
      if (!userId) { notify(t('js.user_id_missing')); return; }

      var submitBtn = form.querySelector('button[type="submit"]');
      var originalText = submitBtn ? submitBtn.textContent : '';
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = t('js.saving');
      }

      function resetButton() {
        if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = originalText; }
      }

      var formData = new FormData(form);
      formData.delete('_method');
      var csrfValue = window.LoreCsrf ? LoreCsrf.token() : '';
      if (csrfValue) formData.set('_token', csrfValue);
      var body = new URLSearchParams(formData).toString();

      fetch(endpoint, {
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
          return response.text().then(function (raw) {
            var data = null;
            try { data = raw ? JSON.parse(raw) : null; } catch (_) { data = null; }

            if (response.ok && !/^\s*</.test(raw)) {
              window.location.href = '/users/' + userId;
              return;
            }

            if ((response.status === 400 || response.status === 422) && data) {
              var errors = data.errors
                || (data.error && typeof data.error === 'object' ? data.error.details : null);
              renderServerErrors(errors);
            }

            var fallback = t('js.save_failed', { status: response.status });
            notify(window.Messages
              ? window.Messages.describe(response.status, data, raw, fallback)
              : fallback);
            resetButton();
          });
        })
        .catch(function (err) {
          console.error('[profile-edit] save failed for', endpoint, err);
          resetButton();
          notify(t('js.network_error'));
        });
    });
  }

  document.querySelectorAll('form[data-profile-form]').forEach(initForm);
  initAvatarPicker();
})();