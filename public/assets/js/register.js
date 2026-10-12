"use strict";

function initRegisterPage() {
  const t = (key, params) => window.LoreI18n ? LoreI18n.t(key, params) : key;

  const form = document.getElementById("registerForm");
  const email = document.getElementById("registerEmail");
  const username = document.getElementById("registerUsername");
  const name = document.getElementById("registerName");
  const surname = document.getElementById("registerSurname");
  const password = document.getElementById("registerPassword");
  const passwordConfirm = document.getElementById("registerPasswordConfirm");

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

  function validateEmail() {
    const ok = LoreValidators.isEmail(email.value);
    setError(email, "registerEmailError", ok ? "" : t("common.js.invalid_email"));
    return ok;
  }

  function validateUsername() {
    const ok = LoreValidators.isUsername(username.value);
    let message = "";
    if (!ok) {
      const v = username.value.trim();
      if (v.length < LoreValidators.LOGIN_MIN_LENGTH) {
        message = t("common.js.username_min", { min: LoreValidators.LOGIN_MIN_LENGTH });
      } else if (!/^[A-Za-z0-9._-]+$/.test(v)) {
        message = t("common.js.username_chars");
      } else {
        message = t("common.js.invalid_username");
      }
    }
    setError(username, "registerUsernameError", message);
    return ok;
  }

  function validateName() {
    const ok = name.value.trim().length > 0;
    setError(name, "registerNameError", ok ? "" : t("common.js.name_required"));
    return ok;
  }

  function validateSurname() {
    const ok = surname.value.trim().length > 0;
    setError(surname, "registerSurnameError", ok ? "" : t("common.js.surname_required"));
    return ok;
  }

  function validatePassword() {
    const ok = LoreValidators.isPassword(password.value);
    let message = "";
    if (!ok) {
      message = password.value.length < LoreValidators.PASSWORD_MIN_LENGTH
        ? t("common.js.password_min", { min: LoreValidators.PASSWORD_MIN_LENGTH })
        : t("common.js.invalid_password");
    }
    setError(password, "registerPasswordError", message);
    return ok;
  }

  function validatePasswordConfirm() {
    const ok = passwordConfirm.value !== "" && passwordConfirm.value === password.value;
    setError(passwordConfirm, "registerPasswordConfirmError", ok ? "" : t("common.js.passwords_different"));
    return ok;
  }

  email.addEventListener("input", () => { if (submitAttempted) validateEmail(); });
  username.addEventListener("input", () => { if (submitAttempted) validateUsername(); });
  name.addEventListener("input", () => { if (submitAttempted) validateName(); });
  surname.addEventListener("input", () => { if (submitAttempted) validateSurname(); });
  password.addEventListener("input", () => {
    if (submitAttempted) {
      validatePassword();
      if (passwordConfirm.value !== "") validatePasswordConfirm();
    }
  });
  passwordConfirm.addEventListener("input", () => { if (submitAttempted) validatePasswordConfirm(); });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    const emailOk    = validateEmail();
    const usernameOk = validateUsername();
    const nameOk     = validateName();
    const surnameOk  = validateSurname();
    const passwordOk = validatePassword();
    const confirmOk  = validatePasswordConfirm();
    if (!emailOk || !usernameOk || !nameOk || !surnameOk || !passwordOk || !confirmOk) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initRegisterPage);