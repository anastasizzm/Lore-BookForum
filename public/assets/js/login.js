"use strict";

function initLoginPage() {
  const form = document.getElementById("loginForm");
  const login = document.getElementById("loginLogin");
  const password = document.getElementById("loginPassword");

  // errors are shown only after the first "Sign in" click;
  // before that, focusing or leaving a field never triggers validation
  let submitAttempted = false;

  function setError(input, messageId, text) {
    const invalid = text !== "";
    input.classList.toggle("form-field__input--invalid", invalid);
    input.setAttribute("aria-invalid", String(invalid));
    document.getElementById(messageId).textContent = text;
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
    if (!loginOk || !passwordOk) event.preventDefault();
  });
}

document.addEventListener("DOMContentLoaded", initLoginPage);