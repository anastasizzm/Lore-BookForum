"use strict";

function initPasswordResetPage() {
  const t = (key, params) => window.LoreI18n ? LoreI18n.t(key, params) : key;

  const form = document.getElementById("passwordResetForm");
  if (!form) return;

  const password = document.getElementById("resetPassword");
  const passwordConfirm = document.getElementById("resetPasswordConfirm");

  let submitAttempted = false;
  const errors = {};

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    errors[messageId] = text;
    document.getElementById(messageId).textContent = window.Messages ? "" : text;
  }

  function showErrors() {
    const list = Object.values(errors).filter(Boolean);
    if (list.length && window.Messages) {
      window.Messages.show(list.join("\n"), { type: "error" });
    }
  }

  function validatePassword() {
    const ok = LoreValidators.isPassword(password.value);
    let message = "";
    if (!ok) {
      message = password.value.length < LoreValidators.PASSWORD_MIN_LENGTH
        ? t("common.js.password_min", { min: LoreValidators.PASSWORD_MIN_LENGTH })
        : t("common.js.invalid_password");
    }
    setError(password, "resetPasswordError", message);
    return ok;
  }

  function validatePasswordConfirm() {
    const ok = passwordConfirm.value !== "" && passwordConfirm.value === password.value;
    setError(passwordConfirm, "resetPasswordConfirmError", ok ? "" : t("common.js.passwords_different"));
    return ok;
  }

  password.addEventListener("input", () => {
    if (submitAttempted) {
      validatePassword();
      if (passwordConfirm.value !== "") validatePasswordConfirm();
    }
  });
  passwordConfirm.addEventListener("input", () => { if (submitAttempted) validatePasswordConfirm(); });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    const passwordOk = validatePassword();
    const confirmOk = validatePasswordConfirm();
    if (!passwordOk || !confirmOk) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initPasswordResetPage);