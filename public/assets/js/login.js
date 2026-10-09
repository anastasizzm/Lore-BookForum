"use strict";

function initLoginPage() {
  const form = document.getElementById("loginForm");
  const login = document.getElementById("loginLogin");
  const password = document.getElementById("loginPassword");

  // errors are shown only after the first "Sign in" click;
  // before that, focusing or leaving a field never triggers validation
  let submitAttempted = false;

  // Тексты ошибок показываем общей плашкой (messages.js), а не красным текстом
  // под полем: у поля остаётся красная рамка и aria-invalid.
  const errors = {};

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    errors[messageId] = text;
    // без messages.js — запасной вариант: старый текст под полем
    document.getElementById(messageId).textContent = window.Messages ? "" : text;
  }

  // Все текущие ошибки формы — одной плашкой (вызывается при отправке)
  function showErrors() {
    const list = Object.values(errors).filter(Boolean);
    if (list.length && window.Messages) {
      window.Messages.show(list.join("\n"), { type: "error" });
    }
  }

  function validateLogin() {
    const ok = LoreValidators.isLogin(login.value);
    setError(login, "loginLoginError", ok ? "" : "Invalid login or email");
    return ok;
  }

  function validatePassword() {
    const ok = LoreValidators.isPassword(password.value);
    let message = "";
    if (!ok) {
      message =
        password.value.length < LoreValidators.PASSWORD_MIN_LENGTH
          ? `Password must be at least ${LoreValidators.PASSWORD_MIN_LENGTH} characters`
          : "Invalid password";
    }
    setError(password, "loginPasswordError", message);
    return ok;
  }

  // after a failed attempt, re-check while typing so the error
  // disappears as soon as the value becomes valid
  login.addEventListener("input", () => {
    if (submitAttempted) validateLogin();
  });
  password.addEventListener("input", () => {
    if (submitAttempted) validatePassword();
  });

  form.addEventListener("submit", (event) => {
    submitAttempted = true;
    // both checks run (no short-circuit) so both errors show at once
    const loginOk = validateLogin();
    const passwordOk = validatePassword();
    if (!loginOk || !passwordOk) {
      event.preventDefault();
      showErrors();
    }
  });
}

document.addEventListener("DOMContentLoaded", initLoginPage);