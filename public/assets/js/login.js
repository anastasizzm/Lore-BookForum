"use strict";

function initLoginPage() {
  const t = (key, params) => window.LoreI18n ? LoreI18n.t(key, params) : key;

  const form = document.getElementById("loginForm");
  const login = document.getElementById("loginLogin");
  const password = document.getElementById("loginPassword");

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

  function validateLogin() {
    const ok = LoreValidators.isLogin(login.value);
    setError(login, "loginLoginError", ok ? "" : t("common.js.invalid_login"));
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
    setError(password, "loginPasswordError", message);
    return ok;
  }

  login.addEventListener("input", () => { if (submitAttempted) validateLogin(); });
  password.addEventListener("input", () => { if (submitAttempted) validatePassword(); });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    const loginOk = validateLogin();
    const passwordOk = validatePassword();
    if (!loginOk || !passwordOk) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initLoginPage);